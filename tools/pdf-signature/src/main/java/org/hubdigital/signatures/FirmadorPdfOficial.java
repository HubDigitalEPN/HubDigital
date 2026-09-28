package org.hubdigital.signatures;

import java.io.IOException;
import java.io.InputStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.security.KeyStore;
import java.security.MessageDigest;
import java.security.PrivateKey;
import java.security.cert.Certificate;
import java.security.cert.X509Certificate;
import java.util.Arrays;
import java.util.Calendar;
import java.util.List;
import java.util.ArrayList;
import javax.naming.ldap.LdapName;
import org.apache.pdfbox.Loader;
import org.apache.pdfbox.cos.COSDictionary;
import org.apache.pdfbox.cos.COSName;
import org.apache.pdfbox.pdmodel.PDDocument;
import org.apache.pdfbox.pdmodel.PDPage;
import org.apache.pdfbox.pdmodel.PDPageContentStream;
import org.apache.pdfbox.pdmodel.PDResources;
import org.apache.pdfbox.pdmodel.common.PDRectangle;
import org.apache.pdfbox.pdmodel.font.PDType1Font;
import org.apache.pdfbox.pdmodel.font.Standard14Fonts;
import org.apache.pdfbox.pdmodel.interactive.annotation.PDAnnotation;
import org.apache.pdfbox.pdmodel.interactive.annotation.PDAnnotationWidget;
import org.apache.pdfbox.pdmodel.interactive.annotation.PDAppearanceDictionary;
import org.apache.pdfbox.pdmodel.interactive.annotation.PDAppearanceStream;
import org.apache.pdfbox.pdmodel.interactive.digitalsignature.PDSignature;
import org.apache.pdfbox.pdmodel.interactive.digitalsignature.SignatureOptions;
import org.bouncycastle.asn1.ASN1EncodableVector;
import org.bouncycastle.asn1.DERSet;
import org.bouncycastle.asn1.cms.Attribute;
import org.bouncycastle.asn1.cms.AttributeTable;
import org.bouncycastle.asn1.ess.ESSCertIDv2;
import org.bouncycastle.asn1.ess.SigningCertificateV2;
import org.bouncycastle.asn1.pkcs.PKCSObjectIdentifiers;
import org.bouncycastle.cert.jcajce.JcaCertStore;
import org.bouncycastle.cms.CMSProcessableByteArray;
import org.bouncycastle.cms.CMSSignedDataGenerator;
import org.bouncycastle.cms.DefaultSignedAttributeTableGenerator;
import org.bouncycastle.cms.jcajce.JcaSignerInfoGeneratorBuilder;
import org.bouncycastle.operator.jcajce.JcaContentSignerBuilder;
import org.bouncycastle.operator.jcajce.JcaDigestCalculatorProviderBuilder;

/** Crea CAdES detached y el sello visible del perfil oficial exclusivamente en Java. */
final class FirmadorPdfOficial {
    private static final String BASE = "https://firmas.hubdigital.invalid/";
    private record Zona(PDPage pagina, int indice, PDRectangle rectangulo, String rol) {}

    private FirmadorPdfOficial() {}

    static void firmar(Path original, Path salida, Path p12, char[] clave, String perfil) throws Exception {
        if (!List.of("solicitud-deposito:depositante:v1", "acta-recepcion:curador:v1").contains(perfil)) {
            throw new IllegalArgumentException("Perfil de firma no autorizado.");
        }
        try {
            KeyStore almacen = abrir(p12, clave);
            List<String> aliases = new ArrayList<>();
            var entradas = almacen.aliases();
            while (entradas.hasMoreElements()) {
                String alias = entradas.nextElement();
                if (almacen.isKeyEntry(alias)) aliases.add(alias);
            }
            if (aliases.size() != 1) throw new IllegalArgumentException("El certificado debe contener una única identidad de firma.");
            PrivateKey privada = (PrivateKey) almacen.getKey(aliases.get(0), clave);
            Certificate[] cadena = almacen.getCertificateChain(aliases.get(0));
            if (privada == null || cadena == null || cadena.length == 0
                || !(cadena[0] instanceof X509Certificate firmante)) throw new IllegalArgumentException("El P12 no contiene una identidad X.509 utilizable.");
            firmante.checkValidity();
            boolean[] usos = firmante.getKeyUsage();
            if (firmante.getBasicConstraints() >= 0 || (usos != null && (usos.length < 1 || !usos[0]) && (usos.length < 2 || !usos[1]))) {
                throw new IllegalArgumentException("El certificado no permite firmar documentos.");
            }
            String algoritmo = switch (privada.getAlgorithm().toUpperCase(java.util.Locale.ROOT)) {
                case "RSA" -> "SHA256withRSA";
                case "EC", "ECDSA" -> "SHA256withECDSA";
                default -> throw new IllegalArgumentException("Algoritmo de clave privada no admitido.");
            };
            try (PDDocument pdf = Loader.loadPDF(original.toFile()); SignatureOptions opciones = new SignatureOptions()) {
                Zona zona = localizar(pdf, perfil);
                Calendar fecha = Calendar.getInstance();
                PDSignature firma = new PDSignature();
                firma.setFilter(PDSignature.FILTER_ADOBE_PPKLITE);
                firma.setSubFilter(PDSignature.SUBFILTER_ETSI_CADES_DETACHED);
                firma.setName(nombre(firmante));
                firma.setReason(zona.rol.equals("depositante") ? "Solicitud de depósito HubDigital" : "Acta de recepción HubDigital");
                firma.setLocation("Ecuador");
                firma.setSignDate(fecha);
                opciones.setPage(zona.indice);
                opciones.setPreferredSignatureSize(65_536);
                pdf.addSignature(firma, contenido -> {
                    try {
                        ASN1EncodableVector atributos = new ASN1EncodableVector();
                        atributos.add(new Attribute(PKCSObjectIdentifiers.id_aa_signingCertificateV2,
                            new DERSet(new SigningCertificateV2(new ESSCertIDv2(
                                MessageDigest.getInstance("SHA-256").digest(firmante.getEncoded()))))));
                        CMSSignedDataGenerator cms = new CMSSignedDataGenerator();
                        cms.addSignerInfoGenerator(new JcaSignerInfoGeneratorBuilder(
                            new JcaDigestCalculatorProviderBuilder().setProvider("BC").build())
                            .setSignedAttributeGenerator(new DefaultSignedAttributeTableGenerator(new AttributeTable(atributos)))
                            .build(new JcaContentSignerBuilder(algoritmo).setProvider("BC").build(privada), firmante));
                        cms.addCertificates(new JcaCertStore(Arrays.asList(cadena)));
                        return cms.generate(new CMSProcessableByteArray(contenido.readAllBytes()), false).getEncoded();
                    } catch (Exception error) {
                        throw new IOException("No se pudo crear la firma CMS.", error);
                    }
                }, opciones);
                var formulario = pdf.getDocumentCatalog().getAcroForm();
                if (formulario == null || formulario.getFields().size() != 1) throw new IOException("No se creó un único campo de firma.");
                PDAnnotationWidget widget = formulario.getFields().get(0).getWidgets().get(0);
                if (formulario.getFields().get(0).getCOSObject() != widget.getCOSObject()) {
                    throw new IOException("El campo y su widget no cumplen el perfil oficial.");
                }
                widget.setRectangle(zona.rectangulo);
                widget.setPage(zona.pagina);
                widget.setAnnotationFlags(4);
                widget.getCOSObject().setString(COSName.T, "Signature1");
                widget.getCOSObject().setName(COSName.FT, "Sig");
                // El campo y su widget son el mismo diccionario, según el contrato del original.
                for (COSName claveCampo : new java.util.HashSet<>(widget.getCOSObject().keySet())) {
                    if (!List.of("Type", "Subtype", "FT", "Rect", "V", "T", "F", "P", "AP").contains(claveCampo.getName())) {
                        widget.getCOSObject().removeItem(claveCampo);
                    }
                }
                apariencia(pdf, widget, zona, firmante, fecha);
                zona.pagina.setAnnotations(List.of(widget));
                zona.pagina.getCOSObject().setNeedToBeUpdated(true);
                formulario.getCOSObject().setNeedToBeUpdated(true);
                try (var out = Files.newOutputStream(salida)) {
                    pdf.saveIncremental(out);
                }
            }
        } finally {
            Arrays.fill(clave, '\0');
        }
    }

    private static KeyStore abrir(Path p12, char[] clave) throws Exception {
        Exception ultimo = null;
        // Algunos P12 antiguos requieren Bouncy Castle; la contraseña siempre es exacta.
        for (String proveedor : List.of("defecto", "BC")) {
            try {
                KeyStore almacen = "defecto".equals(proveedor)
                    ? KeyStore.getInstance("PKCS12") : KeyStore.getInstance("PKCS12", proveedor);
                try (InputStream entrada = Files.newInputStream(p12)) { almacen.load(entrada, clave); }
                return almacen;
            } catch (Exception error) { ultimo = error; }
        }
        throw new IOException("No se pudo abrir el certificado con la credencial indicada.", ultimo);
    }

    private static Zona localizar(PDDocument pdf, String perfil) throws Exception {
        if (pdf.isEncrypted() || !pdf.getSignatureDictionaries().isEmpty()
            || (pdf.getDocumentCatalog().getAcroForm() != null && !pdf.getDocumentCatalog().getAcroForm().getFields().isEmpty())) {
            throw new IllegalArgumentException("La plantilla ya contiene firmas o campos no autorizados.");
        }
        String ruta = perfil.replace(':', '/');
        PDAnnotation bloque = null, zona = null;
        PDPage pagina = null;
        int indice = -1, total = 0;
        for (int i = 0; i < pdf.getNumberOfPages(); i++) {
            PDPage actual = pdf.getPage(i);
            for (PDAnnotation anotacion : actual.getAnnotations()) {
                total++;
                COSDictionary dict = anotacion.getCOSObject();
                var accion = dict.getDictionaryObject(COSName.A);
                if (!"Link".equals(dict.getNameAsString(COSName.SUBTYPE)) || !(accion instanceof COSDictionary a)
                    || !"URI".equals(a.getNameAsString(COSName.S))) throw new IOException("Marcador de firma no autorizado.");
                String uri = a.getString(COSName.URI);
                if ((BASE + "bloques/" + ruta).equals(uri)) {
                    if (bloque != null || (pagina != null && pagina != actual)) throw new IOException("Bloque de firma duplicado o ajeno.");
                    bloque = anotacion;
                } else if ((BASE + "zonas/" + ruta).equals(uri)) {
                    if (zona != null || (pagina != null && pagina != actual)) throw new IOException("Zona de firma duplicada o ajena.");
                    zona = anotacion;
                } else throw new IOException("La plantilla pertenece a un perfil diferente.");
                pagina = actual;
                indice = i;
            }
        }
        if (total != 2 || bloque == null || zona == null || pagina == null) throw new IOException("No se encontró el bloque nominal del firmante.");
        PDRectangle b = bloque.getRectangle(), z = zona.getRectangle(), p = pagina.getCropBox();
        for (PDRectangle rect : List.of(b, z, p)) {
            if (!Float.isFinite(rect.getLowerLeftX()) || !Float.isFinite(rect.getLowerLeftY())
                || !Float.isFinite(rect.getUpperRightX()) || !Float.isFinite(rect.getUpperRightY())) {
                throw new IOException("Coordenadas del bloque de firma inválidas.");
            }
        }
        if (pagina.getRotation() != 0 || pagina.getUserUnit() != 1
            || b.getWidth() < 220 || b.getHeight() < 54 || z.getWidth() < 200 || z.getHeight() < 45
            || b.getLowerLeftX() < p.getLowerLeftX() + 24 || b.getLowerLeftY() < p.getLowerLeftY() + 42
            || b.getUpperRightX() > p.getUpperRightX() - 24 || b.getUpperRightY() > p.getUpperRightY() - 24
            || z.getLowerLeftX() < b.getLowerLeftX() + 2 || z.getLowerLeftY() < b.getLowerLeftY() + 2
            || z.getUpperRightX() > b.getUpperRightX() - 2 || z.getUpperRightY() > b.getUpperRightY() - 2) {
            throw new IOException("La zona visible está fuera del bloque nominal seguro.");
        }
        pagina.setAnnotations(List.of());
        return new Zona(pagina, indice, z, perfil.contains(":depositante:") ? "depositante" : "curador");
    }

    private static void apariencia(PDDocument pdf, PDAnnotationWidget widget, Zona zona, X509Certificate cert, Calendar fecha) throws Exception {
        float ancho = zona.rectangulo.getWidth(), alto = zona.rectangulo.getHeight();
        PDAppearanceStream stream = new PDAppearanceStream(pdf);
        stream.setBBox(new PDRectangle(ancho, alto));
        stream.setResources(new PDResources());
        PDType1Font normal = new PDType1Font(Standard14Fonts.FontName.HELVETICA);
        PDType1Font negrita = new PDType1Font(Standard14Fonts.FontName.HELVETICA_BOLD);
        String huella = java.util.HexFormat.of().formatHex(MessageDigest.getInstance("SHA-256").digest(cert.getEncoded()));
        List<String> lineas = List.of("FIRMADO ELECTRONICAMENTE POR", nombre(cert),
            "Rol documental: " + zona.rol.toUpperCase(java.util.Locale.ROOT),
            "Fecha: " + fecha.toInstant(), "Certificado SHA-256: " + huella.substring(0, 24),
            "Firmador HubDigital | ETSI CAdES detached");
        try (PDPageContentStream contenido = new PDPageContentStream(pdf, stream)) {
            contenido.setNonStrokingColor(0.965f, 0.98f, 0.97f);
            contenido.addRect(0, 0, ancho, alto);
            contenido.fill();
            contenido.setStrokingColor(0.18f, 0.42f, 0.31f);
            contenido.addRect(0.5f, 0.5f, ancho - 1, alto - 1);
            contenido.stroke();
            float y = alto - 12;
            for (int i = 0; i < lineas.size(); i++) {
                PDType1Font fuente = i < 2 ? negrita : normal;
                float tamano = i < 2 ? 8 : 6.3f;
                String texto = lineas.get(i).replaceAll("[^\\x20-\\x7E\\u00A0-\\u00FF]", "?");
                while (!texto.isEmpty() && fuente.getStringWidth(texto) * tamano / 1000 > ancho - 16) texto = texto.substring(0, texto.length() - 1);
                contenido.beginText();
                contenido.setNonStrokingColor(0.07f, 0.22f, 0.35f);
                contenido.setFont(fuente, tamano);
                contenido.newLineAtOffset(8, y);
                contenido.showText(texto);
                contenido.endText();
                y -= Math.min(9, (alto - 16) / lineas.size());
            }
        }
        PDAppearanceDictionary apariencia = new PDAppearanceDictionary();
        apariencia.setNormalAppearance(stream);
        widget.setAppearance(apariencia);
        widget.getCOSObject().setNeedToBeUpdated(true);
    }

    private static String nombre(X509Certificate certificado) throws Exception {
        for (var rdn : new LdapName(certificado.getSubjectX500Principal().getName()).getRdns()) {
            if ("CN".equalsIgnoreCase(rdn.getType())) return String.valueOf(rdn.getValue());
        }
        return certificado.getSubjectX500Principal().getName();
    }
}
