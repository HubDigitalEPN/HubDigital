import assert from 'node:assert/strict';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import forge from 'node-forge';
import {
    PDFArray,
    PDFDict,
    PDFDocument,
    PDFName,
    PDFStream,
    PDFString,
    StandardFonts,
} from 'pdf-lib';

const PERFIL_DEPOSITANTE = 'solicitud-deposito:depositante:v1';
const PERFIL_CURADOR = 'acta-recepcion:curador:v1';
const MARCADORES = {
    [PERFIL_DEPOSITANTE]: {
        bloque: 'https://firmas.hubdigital.invalid/bloques/solicitud-deposito/depositante/v1',
        zona: 'https://firmas.hubdigital.invalid/zonas/solicitud-deposito/depositante/v1',
    },
    [PERFIL_CURADOR]: {
        bloque: 'https://firmas.hubdigital.invalid/bloques/acta-recepcion/curador/v1',
        zona: 'https://firmas.hubdigital.invalid/zonas/acta-recepcion/curador/v1',
    },
};
const RECT_BLOQUE = [48, 540, 547, 630];
const RECT_ZONA = [56, 548, 539, 622];

const clave = 'solo-prueba-hubdigital';
const llaves = forge.pki.rsa.generateKeyPair(1024);
const certificado = forge.pki.createCertificate();
certificado.publicKey = llaves.publicKey;
certificado.serialNumber = '01';
certificado.validity.notBefore = new Date(Date.now() - 60_000);
certificado.validity.notAfter = new Date(Date.now() + 86_400_000);
const nombre = [
    { name: 'commonName', value: 'Consultor de prueba HubDigital' },
    { name: 'serialNumber', value: '1700000001' },
];
certificado.setSubject(nombre);
certificado.setIssuer(nombre);
certificado.setExtensions([
    { name: 'basicConstraints', cA: true },
    { name: 'keyUsage', digitalSignature: true, nonRepudiation: true, keyCertSign: true },
]);
certificado.sign(llaves.privateKey, forge.md.sha256.create());

const p12Asn1 = forge.pkcs12.toPkcs12Asn1(
    llaves.privateKey,
    [certificado],
    clave,
    { algorithm: '3des', friendlyName: 'HubDigital E2E' },
);
const p12Bytes = Uint8Array.from(
    forge.util.binary.raw.decode(forge.asn1.toDer(p12Asn1).getBytes()),
);

function agregarMarcador(documento, pagina, uri, rect) {
    const anotacion = documento.context.obj({
        Type: 'Annot',
        Subtype: 'Link',
        Rect: rect,
        Border: [0, 0, 0],
        A: {
            Type: 'Action',
            S: 'URI',
            URI: PDFString.of(uri),
        },
    });
    pagina.node.addAnnot(documento.context.register(anotacion));
}

async function crearPdf({
    perfil = PERFIL_DEPOSITANTE,
    bloque = RECT_BLOQUE,
    zona = RECT_ZONA,
    incluirBloque = true,
    incluirZona = true,
    duplicarZona = false,
    paginasPrevias = 0,
    zonaEnOtraPagina = false,
    anotacionExtra = false,
    campoPrevio = false,
} = {}) {
    const documento = await PDFDocument.create();
    for (let indice = 0; indice < paginasPrevias; indice += 1) {
        documento.addPage([595, 842]).drawText('Anexo de registros biologicos de prueba');
    }
    const pagina = documento.addPage([595, 842]);
    const fuente = await documento.embedFont(StandardFonts.Helvetica);
    pagina.drawText(`Bloque nominal de firma: ${perfil}`, {
        x: 48,
        y: 650,
        size: 10,
        font: fuente,
    });

    if (incluirBloque) {
        agregarMarcador(documento, pagina, MARCADORES[perfil].bloque, bloque);
    }
    if (incluirZona) {
        agregarMarcador(documento, zonaEnOtraPagina ? documento.addPage([595, 842]) : pagina, MARCADORES[perfil].zona, zona);
    }
    if (duplicarZona) {
        agregarMarcador(documento, pagina, MARCADORES[perfil].zona, zona);
    }
    if (anotacionExtra) {
        agregarMarcador(documento, pagina, 'https://example.invalid/otra-anotacion', [20, 20, 200, 200]);
    }
    if (campoPrevio) {
        documento.getForm().createTextField('campo-ajeno');
    }

    return documento.save();
}

let respuesta;
globalThis.self = {
    postMessage(mensaje) {
        respuesta = mensaje;
    },
};

await import('../../resources/js/workers/pdf-signing.worker.js');

async function firmar(pdfBytes, signatureProfile = PERFIL_DEPOSITANTE) {
    respuesta = undefined;
    const pdf = pdfBytes.buffer.slice(pdfBytes.byteOffset, pdfBytes.byteOffset + pdfBytes.byteLength);
    const p12 = p12Bytes.buffer.slice(p12Bytes.byteOffset, p12Bytes.byteOffset + p12Bytes.byteLength);
    const data = {
            pdf,
            p12,
            passphrase: clave,
            signatureProfile,
            reason: 'Prueba automatizada del Firmador HubDigital',
            location: 'Quito, Ecuador',
    };
    await globalThis.self.onmessage({ data });
    assert.ok(new Uint8Array(p12).every((byte) => byte === 0), 'El worker debe limpiar su copia del P12.');
    assert.equal(data.passphrase, '', 'El worker debe descartar la clave al terminar.');

    return respuesta;
}

const pdfBytes = await crearPdf();
const firmaCorrecta = await firmar(pdfBytes);
assert.equal(firmaCorrecta?.ok, true, firmaCorrecta?.error ?? 'El worker no devolvio una firma.');
assert.equal(firmaCorrecta.certificado.nombre, 'Consultor de prueba HubDigital');
assert.equal(firmaCorrecta.zonaFirma.visible, true);
assert.equal(firmaCorrecta.zonaFirma.perfil, PERFIL_DEPOSITANTE);
assert.equal(firmaCorrecta.zonaFirma.pagina, 1);
assert.deepEqual(firmaCorrecta.zonaFirma.rect, RECT_ZONA);

const pdfFirmado = Buffer.from(firmaCorrecta.pdf);
assert.ok(pdfFirmado.length > pdfBytes.length, 'El PDF firmado debe contener el contenedor criptografico.');
assert.ok(pdfFirmado.includes(Buffer.from('/ByteRange')), 'La firma debe cubrir un ByteRange del PDF.');
assert.ok(
    pdfFirmado.includes(Buffer.from('/ETSI.CAdES.detached')),
    'La firma debe usar el subfiltro ETSI CAdES detached.',
);

const inspeccion = await PDFDocument.load(pdfFirmado);
const anotaciones = inspeccion.getPages()[0].node.lookup(PDFName.of('Annots'), PDFArray);
assert.equal(anotaciones.size(), 1, 'Los marcadores deben sustituirse por un unico widget de firma.');
const widget = anotaciones.lookup(0, PDFDict);
assert.equal(widget.lookup(PDFName.of('Subtype'), PDFName).toString(), '/Widget');
assert.equal(widget.lookup(PDFName.of('FT'), PDFName).toString(), '/Sig');
assert.ok(
    widget.lookup(PDFName.of('AP'), PDFDict).lookupMaybe(PDFName.of('N'), PDFStream),
    'El widget debe contener una apariencia visible.',
);
assert.deepEqual(widget.lookup(PDFName.of('Rect'), PDFArray).asRectangle(), {
    x: RECT_ZONA[0],
    y: RECT_ZONA[1],
    width: RECT_ZONA[2] - RECT_ZONA[0],
    height: RECT_ZONA[3] - RECT_ZONA[1],
});

const actaOriginal = await crearPdf({ perfil: PERFIL_CURADOR, paginasPrevias: 2 });
const actaFirmada = await firmar(actaOriginal, PERFIL_CURADOR);
assert.equal(actaFirmada.ok, true, actaFirmada.error);
assert.equal(actaFirmada.zonaFirma.pagina, 3, 'Debe firmarse la pagina del curador tras los anexos.');
assert.equal(actaFirmada.zonaFirma.perfil, PERFIL_CURADOR);
const inspeccionActa = await PDFDocument.load(actaFirmada.pdf);
assert.equal(inspeccionActa.getPages()[0].node.lookupMaybe(PDFName.of('Annots'), PDFArray)?.size() ?? 0, 0);
assert.equal(inspeccionActa.getPages()[2].node.lookup(PDFName.of('Annots'), PDFArray).size(), 1);

const sinMarcador = await firmar(await crearPdf({ incluirBloque: false, incluirZona: false }));
assert.equal(sinMarcador.ok, false);
assert.match(sinMarcador.error, /no contiene el bloque nominal/u);

const duplicado = await firmar(await crearPdf({ duplicarZona: true }));
assert.equal(duplicado.ok, false);
assert.match(duplicado.error, /marcadores duplicados/u);

const rolIncorrecto = await firmar(await crearPdf({ perfil: PERFIL_CURADOR }), PERFIL_DEPOSITANTE);
assert.equal(rolIncorrecto.ok, false);
assert.match(rolIncorrecto.error, /rol o documento diferente/u);

const fueraDelBloque = await firmar(await crearPdf({ zona: [56, 100, 539, 174] }));
assert.equal(fueraDelBloque.ok, false);
assert.match(fueraDelBloque.error, /fuera del bloque nominal seguro/u);

for (const [opciones, patron] of [
    [{ incluirZona: false }, /no contiene el bloque nominal/u],
    [{ zonaEnOtraPagina: true }, /no pertenece al bloque nominal/u],
    [{ bloque: [48, 10, 547, 100], zona: [56, 18, 539, 92] }, /fuera del bloque nominal seguro/u],
    [{ zona: [539, 548, 56, 622] }, /no pertenece al bloque nominal/u],
    [{ anotacionExtra: true }, /anotaciones no permitidas/u],
    [{ campoPrevio: true }, /ya contiene campos de firma/u],
]) {
    const resultado = await firmar(await crearPdf(opciones));
    assert.equal(resultado.ok, false);
    assert.match(resultado.error, patron);
}

const perfilDesconocido = await firmar(pdfBytes, 'firma-libre:footer:v1');
assert.equal(perfilDesconocido.ok, false);
assert.match(perfilDesconocido.error, /no est.*autorizado/u);

// Exportacion opcional de PDFs con un certificado sintetico para probar Poppler.
if (process.env.HUBDIGITAL_SIGNER_FIXTURE_DIR) {
    const directorio = resolve(process.env.HUBDIGITAL_SIGNER_FIXTURE_DIR);
    await mkdir(directorio, { recursive: true });
    await Promise.all([
        writeFile(resolve(directorio, 'solicitud-original.pdf'), pdfBytes),
        writeFile(resolve(directorio, 'solicitud-firmada.pdf'), pdfFirmado),
        writeFile(resolve(directorio, 'acta-original.pdf'), actaOriginal),
        writeFile(resolve(directorio, 'acta-firmada.pdf'), Buffer.from(actaFirmada.pdf)),
    ]);
}

if (process.env.HUBDIGITAL_SIGNER_TEMPLATE_DIR) {
    const directorio = resolve(process.env.HUBDIGITAL_SIGNER_TEMPLATE_DIR);
    // La raíz sintética se confía únicamente en el directorio temporal de esta prueba.
    await writeFile(resolve(directorio, 'qa.crt'), forge.pki.certificateToPem(certificado));
    for (const [nombre, perfil] of [['solicitud', PERFIL_DEPOSITANTE], ['acta', PERFIL_CURADOR]]) {
        const original = await readFile(resolve(directorio, `${nombre}-original.pdf`));
        const resultado = await firmar(original, perfil);
        assert.equal(resultado.ok, true, `${nombre}: ${resultado.error}`);
        await writeFile(resolve(directorio, `${nombre}-firmada.pdf`), Buffer.from(resultado.pdf));
        process.stdout.write(`${nombre}: pagina ${resultado.zonaFirma.pagina}, Rect ${JSON.stringify(resultado.zonaFirma.rect)}\n`);
        if (nombre === 'solicitud') {
            for (const alteracion of ['desplazada', 'apariencia-vacia', 'anotacion-extra', 'contenido-alterado']) {
                const adulterado = await PDFDocument.load(resultado.pdf, { updateMetadata: false });
                const pagina = adulterado.getPages()[resultado.zonaFirma.pagina - 1];
                const widgetFirma = pagina.node.lookup(PDFName.of('Annots'), PDFArray).lookup(0, PDFDict);
                const [x1, y1, x2, y2] = resultado.zonaFirma.rect;
                if (alteracion === 'desplazada') {
                    widgetFirma.set(PDFName.of('Rect'), adulterado.context.obj([x1, 20, x2, 20 + y2 - y1]));
                } else if (alteracion === 'apariencia-vacia') {
                    const vacia = adulterado.context.register(adulterado.context.flateStream('', {
                        Type: 'XObject', Subtype: 'Form', FormType: 1,
                        BBox: [0, 0, x2 - x1, y2 - y1], Matrix: [1, 0, 0, 1, 0, 0], Resources: {},
                    }));
                    widgetFirma.set(PDFName.of('AP'), adulterado.context.obj({ N: vacia }));
                } else if (alteracion === 'anotacion-extra') {
                    pagina.node.addAnnot(adulterado.context.register(adulterado.context.obj({
                        Type: 'Annot', Subtype: 'Text', Rect: [50, 50, 200, 100],
                        Contents: PDFString.of('Anotacion no autorizada'), F: 4,
                    })));
                } else {
                    pagina.drawText('Texto ajeno al expediente', { x: 48, y: 410, size: 10 });
                }
                // Estas copias prueban el control de contenido por separado: la
                // reescritura tambien invalida intencionalmente su criptografia.
                await writeFile(resolve(directorio, `${nombre}-${alteracion}.pdf`), await adulterado.save({ updateFieldAppearances: false }));
            }
        }
    }
}

process.stdout.write('Firmador HubDigital: PDF CAdES creado con apariencia visible dentro del bloque nominal.\n');
