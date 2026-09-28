import { Buffer } from 'buffer';
import forge from 'node-forge';
import {
    PDFArray,
    PDFDict,
    PDFDocument,
    PDFHexString,
    PDFName,
    PDFNumber,
    PDFString,
    StandardFonts,
} from 'pdf-lib';
import { pdflibAddPlaceholder } from '@signpdf/placeholder-pdf-lib';
import { P12Signer } from '@signpdf/signer-p12';
import { SignPdf } from '@signpdf/signpdf';
import { SUBFILTER_ETSI_CADES_DETACHED } from '@signpdf/utils';

globalThis.Buffer = Buffer;

const BASE_MARCADOR = 'https://firmas.hubdigital.invalid/';

export const PERFILES_FIRMA_PDF = Object.freeze({
    'solicitud-deposito:depositante:v1': Object.freeze({
        rol: 'depositante',
        bloque: `${BASE_MARCADOR}bloques/solicitud-deposito/depositante/v1`,
        zona: `${BASE_MARCADOR}zonas/solicitud-deposito/depositante/v1`,
    }),
    'acta-recepcion:curador:v1': Object.freeze({
        rol: 'curador',
        bloque: `${BASE_MARCADOR}bloques/acta-recepcion/curador/v1`,
        zona: `${BASE_MARCADOR}zonas/acta-recepcion/curador/v1`,
    }),
});

function atributo(atributos, nombre) {
    return atributos.find((item) => item.name === nombre || item.shortName === nombre)?.value ?? null;
}

function abrirCertificado(bytes, clave) {
    const der = forge.util.createBuffer(Buffer.from(bytes).toString('binary'));
    const asn1 = forge.asn1.fromDer(der);
    const p12 = forge.pkcs12.pkcs12FromAsn1(asn1, false, clave);
    const certificados = p12.getBags({ bagType: forge.pki.oids.certBag })[forge.pki.oids.certBag] ?? [];
    const claves = [
        ...(p12.getBags({ bagType: forge.pki.oids.pkcs8ShroudedKeyBag })[forge.pki.oids.pkcs8ShroudedKeyBag] ?? []),
        ...(p12.getBags({ bagType: forge.pki.oids.keyBag })[forge.pki.oids.keyBag] ?? []),
    ];
    if (certificados.length === 0 || claves.length === 0) {
        throw new Error('El PKCS#12 no contiene un certificado y una clave privada utilizables.');
    }

    const clavePrivada = claves[0].key;
    const certificado = certificados.find(({ cert }) => (
        clavePrivada?.n && cert.publicKey?.n
        && clavePrivada.n.compareTo(cert.publicKey.n) === 0
        && clavePrivada.e.compareTo(cert.publicKey.e) === 0
    ))?.cert;
    if (!certificado) {
        throw new Error('No se encontró el certificado que corresponde a la clave privada.');
    }

    return {
        nombre: atributo(certificado.subject.attributes, 'commonName') ?? 'Titular del certificado',
        identificacion: atributo(certificado.subject.attributes, 'serialNumber'),
        organizacion: atributo(certificado.subject.attributes, 'organizationName'),
        emisor: atributo(certificado.issuer.attributes, 'commonName'),
        numeroSerie: certificado.serialNumber,
        validoDesde: certificado.validity.notBefore.toISOString(),
        validoHasta: certificado.validity.notAfter.toISOString(),
        huellaSha256: forge.md.sha256.create()
            .update(forge.asn1.toDer(forge.pki.certificateToAsn1(certificado)).getBytes())
            .digest().toHex(),
    };
}

function textoPdf(objeto) {
    return objeto instanceof PDFString || objeto instanceof PDFHexString
        ? objeto.decodeText()
        : null;
}

function uriAnotacion(anotacion) {
    const accion = anotacion.lookupMaybe(PDFName.of('A'), PDFDict);
    if (!accion) {
        return null;
    }

    return textoPdf(accion.lookupMaybe(PDFName.of('URI'), PDFString, PDFHexString));
}

function rectanguloAnotacion(anotacion) {
    const rectangulo = anotacion.lookupMaybe(PDFName.of('Rect'), PDFArray);
    if (!rectangulo || rectangulo.size() !== 4) {
        return null;
    }

    const coordenadas = Array.from({ length: 4 }, (_, indice) => (
        rectangulo.lookupMaybe(indice, PDFNumber)?.asNumber()
    ));
    if (coordenadas.some((valor) => !Number.isFinite(valor))) {
        return null;
    }

    const [x1, y1, x2, y2] = coordenadas;

    return x1 < x2 && y1 < y2 ? coordenadas : null;
}

function buscarMarcadores(pdf, perfilId) {
    const perfil = PERFILES_FIRMA_PDF[perfilId];
    if (!perfil) {
        throw new Error('El perfil de firma solicitado no está autorizado por HubDigital.');
    }

    const formulario = pdf.catalog.lookupMaybe(PDFName.of('AcroForm'), PDFDict);
    const campos = formulario?.lookupMaybe(PDFName.of('Fields'), PDFArray);
    if (campos && campos.size() > 0) {
        throw new Error('El PDF original ya contiene campos de firma y no es una plantilla segura.');
    }

    const coincidencias = { bloque: [], zona: [] };
    const marcadoresAjenos = [];
    pdf.getPages().forEach((pagina, paginaIndice) => {
        const anotaciones = pagina.node.lookupMaybe(PDFName.of('Annots'), PDFArray);
        if (!anotaciones) {
            return;
        }

        for (let indice = 0; indice < anotaciones.size(); indice += 1) {
            const anotacion = anotaciones.lookupMaybe(indice, PDFDict);
            if (!anotacion) {
                throw new Error('La plantilla contiene anotaciones no permitidas.');
            }

            const uri = uriAnotacion(anotacion);
            const accion = anotacion.lookupMaybe(PDFName.of('A'), PDFDict);
            if (!uri?.startsWith(BASE_MARCADOR)
                || anotacion.lookupMaybe(PDFName.of('Subtype'), PDFName)?.toString() !== '/Link'
                || accion?.lookupMaybe(PDFName.of('S'), PDFName)?.toString() !== '/URI') {
                throw new Error('La plantilla contiene anotaciones no permitidas.');
            }

            const marcador = { pagina, paginaIndice, anotaciones, indice, rect: rectanguloAnotacion(anotacion), uri };
            if (uri === perfil.bloque) {
                coincidencias.bloque.push(marcador);
            } else if (uri === perfil.zona) {
                coincidencias.zona.push(marcador);
            } else {
                marcadoresAjenos.push(marcador);
            }
        }
    });

    if (marcadoresAjenos.length > 0) {
        throw new Error('La plantilla contiene un marcador de firma para un rol o documento diferente.');
    }
    if (coincidencias.bloque.length === 0 || coincidencias.zona.length === 0) {
        throw new Error(`El PDF no contiene el bloque nominal de firma del ${perfil.rol}.`);
    }
    if (coincidencias.bloque.length !== 1 || coincidencias.zona.length !== 1) {
        throw new Error(`El PDF contiene marcadores duplicados para la firma del ${perfil.rol}.`);
    }

    const bloque = coincidencias.bloque[0];
    const zona = coincidencias.zona[0];
    if (!bloque.rect || !zona.rect || bloque.paginaIndice !== zona.paginaIndice) {
        throw new Error('La zona visible no pertenece al bloque nominal del firmante.');
    }

    const [bx1, by1, bx2, by2] = bloque.rect;
    const [zx1, zy1, zx2, zy2] = zona.rect;
    const anchoBloque = bx2 - bx1;
    const altoBloque = by2 - by1;
    const anchoZona = zx2 - zx1;
    const altoZona = zy2 - zy1;
    const { width: anchoPagina, height: altoPagina } = bloque.pagina.getSize();
    const rotacion = ((bloque.pagina.getRotation().angle % 360) + 360) % 360;
    const contenida = zx1 >= bx1 + 2 && zy1 >= by1 + 2 && zx2 <= bx2 - 2 && zy2 <= by2 - 2;
    const bloqueSeguro = bx1 >= 24 && by1 >= 42 && bx2 <= anchoPagina - 24 && by2 <= altoPagina - 24
        && anchoBloque >= 220 && altoBloque >= 54;
    const zonaSegura = anchoZona >= 200 && altoZona >= 45;

    if (rotacion !== 0 || !bloqueSeguro || !zonaSegura || !contenida) {
        throw new Error('Las coordenadas de firma están fuera del bloque nominal seguro de la plantilla.');
    }

    // Se eliminan de mayor a menor para no desplazar el segundo indice cuando
    // DomPDF ubicó ambos marcadores en el mismo arreglo de anotaciones.
    [bloque, zona]
        .sort((a, b) => b.indice - a.indice)
        .forEach((marcador) => marcador.anotaciones.remove(marcador.indice));

    return { perfil, pagina: zona.pagina, paginaIndice: zona.paginaIndice, rect: zona.rect };
}

function textoVisible(valor) {
    return String(valor ?? '')
        .replace(/[^\x20-\x7E\u00A0-\u00FF]/gu, '?')
        .replace(/\s+/gu, ' ')
        .trim();
}

function recortarTexto(texto, fuente, tamano, anchoMaximo) {
    let salida = textoVisible(texto);
    while (salida.length > 1 && fuente.widthOfTextAtSize(salida, tamano) > anchoMaximo) {
        salida = salida.slice(0, -1).trimEnd();
    }

    return salida !== textoVisible(texto) ? `${salida.slice(0, -3)}...` : salida;
}

function numeroPdf(valor) {
    return Number(valor.toFixed(2)).toString();
}

async function aplicarAparienciaVisible(pdf, pagina, rect, certificado, fechaFirma, perfil) {
    const [x1, y1, x2, y2] = rect;
    const ancho = x2 - x1;
    const alto = y2 - y1;
    const fuenteNormal = await pdf.embedFont(StandardFonts.Helvetica);
    const fuenteNegrita = await pdf.embedFont(StandardFonts.HelveticaBold);
    const margen = 8;
    const anchoTexto = ancho - (margen * 2);
    const tituloTamano = 8.2;
    const nombreTamano = 8;
    const detalleTamano = 6.3;
    const lineas = [
        { fuente: fuenteNegrita, recurso: 'FHB', tamano: tituloTamano, texto: 'FIRMADO ELECTRONICAMENTE POR' },
        { fuente: fuenteNegrita, recurso: 'FHB', tamano: nombreTamano, texto: certificado.nombre },
        { fuente: fuenteNormal, recurso: 'FHN', tamano: detalleTamano, texto: `Rol documental: ${perfil.rol.toUpperCase()}` },
        ...(certificado.identificacion
            ? [{ fuente: fuenteNormal, recurso: 'FHN', tamano: detalleTamano, texto: `Identificacion: ${certificado.identificacion}` }]
            : []),
        { fuente: fuenteNormal, recurso: 'FHN', tamano: detalleTamano, texto: `Fecha: ${fechaFirma.toISOString().replace('T', ' ').replace(/\.\d{3}Z$/u, ' UTC')}` },
        { fuente: fuenteNormal, recurso: 'FHN', tamano: detalleTamano, texto: `Certificado SHA-256: ${certificado.huellaSha256.slice(0, 24).toUpperCase()}` },
        { fuente: fuenteNormal, recurso: 'FHN', tamano: detalleTamano, texto: 'Firmador HubDigital | ETSI CAdES detached' },
    ];

    const interlineado = Math.min(9, (alto - 16) / lineas.length);
    let y = alto - 12;
    const operaciones = [
        'q',
        '0.965 0.98 0.97 rg',
        `0 0 ${numeroPdf(ancho)} ${numeroPdf(alto)} re f`,
        '0.18 0.42 0.31 RG',
        '1 w',
        `0.5 0.5 ${numeroPdf(ancho - 1)} ${numeroPdf(alto - 1)} re S`,
    ];

    lineas.forEach((linea, indice) => {
        const texto = recortarTexto(linea.texto, linea.fuente, linea.tamano, anchoTexto);
        operaciones.push(
            'BT',
            `/${linea.recurso} ${numeroPdf(linea.tamano)} Tf`,
            indice < 2 ? '0.07 0.22 0.35 rg' : '0.20 0.29 0.25 rg',
            `${numeroPdf(margen)} ${numeroPdf(y)} Td`,
            `${linea.fuente.encodeText(texto).toString()} Tj`,
            'ET',
        );
        y -= interlineado;
    });
    operaciones.push('Q');

    const apariencia = pdf.context.flateStream(operaciones.join('\n'), {
        Type: 'XObject',
        Subtype: 'Form',
        FormType: 1,
        BBox: [0, 0, ancho, alto],
        Matrix: [1, 0, 0, 1, 0, 0],
        Resources: {
            Font: {
                FHN: fuenteNormal.ref,
                FHB: fuenteNegrita.ref,
            },
        },
    });
    const aparienciaRef = pdf.context.register(apariencia);
    const anotaciones = pagina.node.lookup(PDFName.of('Annots'), PDFArray);
    const widget = anotaciones.lookup(anotaciones.size() - 1, PDFDict);
    widget.set(PDFName.of('AP'), pdf.context.obj({ N: aparienciaRef }));
}

self.onmessage = async ({ data }) => {
    let p12Bytes = data.p12;
    try {
        const certificado = abrirCertificado(p12Bytes, data.passphrase);
        const fechaFirma = new Date();
        const pdf = await PDFDocument.load(new Uint8Array(data.pdf), { updateMetadata: false });
        const zona = buscarMarcadores(pdf, data.signatureProfile);

        pdflibAddPlaceholder({
            pdfPage: zona.pagina,
            reason: data.reason ?? 'Aceptación del documento emitido por HubDigital',
            contactInfo: '',
            name: certificado.nombre,
            location: data.location ?? 'Quito, Ecuador',
            signingTime: fechaFirma,
            signatureLength: 32768,
            subFilter: SUBFILTER_ETSI_CADES_DETACHED,
            widgetRect: zona.rect,
            appName: 'Firmador HubDigital',
        });
        await aplicarAparienciaVisible(pdf, zona.pagina, zona.rect, certificado, fechaFirma, zona.perfil);

        const preparado = await pdf.save({ updateFieldAppearances: false });
        const firmador = new P12Signer(Buffer.from(p12Bytes), {
            passphrase: data.passphrase,
            asn1StrictParsing: false,
        });
        const firmado = await new SignPdf().sign(Buffer.from(preparado), firmador, fechaFirma);
        const resultado = firmado.buffer.slice(firmado.byteOffset, firmado.byteOffset + firmado.byteLength);

        self.postMessage({
            ok: true,
            pdf: resultado,
            certificado,
            zonaFirma: {
                perfil: data.signatureProfile,
                pagina: zona.paginaIndice + 1,
                rect: zona.rect,
                visible: true,
            },
        }, [resultado]);
    } catch (error) {
        self.postMessage({ ok: false, error: String(error?.message ?? error) });
    } finally {
        if (p12Bytes?.byteLength) {
            new Uint8Array(p12Bytes).fill(0);
        }
        data.passphrase = '';
        data.pdf = null;
        p12Bytes = null;
    }
};
