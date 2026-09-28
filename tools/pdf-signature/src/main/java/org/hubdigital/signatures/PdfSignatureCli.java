package org.hubdigital.signatures;

import java.io.ByteArrayInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.time.Instant;
import java.net.URI;
import java.security.KeyStore;
import java.security.KeyPair;
import java.security.KeyPairGenerator;
import java.security.PrivateKey;
import java.security.Security;
import java.security.MessageDigest;
import java.security.Signature;
import java.security.cert.CertPath;
import java.security.cert.CertStore;
import java.security.cert.CertPathBuilder;
import java.security.cert.CertPathBuilderException;
import java.security.cert.PKIXBuilderParameters;
import java.security.cert.PKIXCertPathBuilderResult;
import java.security.cert.X509CertSelector;
import java.security.cert.Certificate;
import java.security.cert.CertificateFactory;
import java.security.cert.CollectionCertStoreParameters;
import java.security.cert.TrustAnchor;
import java.security.cert.X509Certificate;
import java.security.cert.X509CRL;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collection;
import java.util.Date;
import java.util.Enumeration;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.math.BigInteger;
import org.apache.pdfbox.Loader;
import org.apache.pdfbox.cos.COSDictionary;
import org.apache.pdfbox.cos.COSArray;
import org.apache.pdfbox.cos.COSBase;
import org.apache.pdfbox.cos.COSName;
import org.apache.pdfbox.cos.COSObject;
import org.apache.pdfbox.cos.COSString;
import org.apache.pdfbox.pdmodel.PDDocument;
import org.apache.pdfbox.pdmodel.PDPage;
import org.apache.pdfbox.pdmodel.interactive.digitalsignature.PDSignature;
import org.apache.pdfbox.text.PDFTextStripper;
import org.bouncycastle.cert.X509CertificateHolder;
import org.bouncycastle.cert.jcajce.JcaCertStore;
import org.bouncycastle.cert.jcajce.JcaX509CertificateConverter;
import org.bouncycastle.cert.jcajce.JcaX509v3CertificateBuilder;
import org.bouncycastle.cms.CMSProcessableByteArray;
import org.bouncycastle.cms.CMSSignedData;
import org.bouncycastle.cms.CMSSignedDataGenerator;
import org.bouncycastle.cms.SignerInformation;
import org.bouncycastle.cms.jcajce.JcaSignerInfoGeneratorBuilder;
import org.bouncycastle.cms.jcajce.JcaSimpleSignerInfoVerifierBuilder;
import org.bouncycastle.jce.provider.BouncyCastleProvider;
import org.bouncycastle.operator.jcajce.JcaContentSignerBuilder;
import org.bouncycastle.operator.jcajce.JcaDigestCalculatorProviderBuilder;
import org.bouncycastle.asn1.x500.X500Name;
import org.bouncycastle.asn1.ASN1OctetString;
import org.bouncycastle.asn1.cms.Attribute;
import org.bouncycastle.asn1.cms.AttributeTable;
import org.bouncycastle.asn1.cms.CMSAttributes;
import org.bouncycastle.asn1.pkcs.PKCSObjectIdentifiers;

/** Firma y valida el CMS separado incrustado en un PDF; no depende de Windows. */
public final class PdfSignatureCli {
    private static volatile Set<TrustAnchor> cachedAnchors;
    private static volatile List<X509Certificate> cachedAuthorities;
    private PdfSignatureCli() {}

    public static void main(String[] args) {
        Security.addProvider(new BouncyCastleProvider());
        Security.setProperty("ocsp.enable", "true");
        System.setProperty("com.sun.security.enableCRLDP", "true");
        try {
            // Windows puede perder caracteres que no existen en la página de códigos
            // de su línea de comandos. El transporte PHP envía las rutas como UTF-8.
            if (args.length == 2 && "--paths-stdin".equals(args[1])
                && Set.of("verify", "inspect", "text").contains(args[0])) {
                byte[] entrada = System.in.readNBytes(65_537);
                if (entrada.length > 65_536) throw new IllegalArgumentException("Rutas PDF demasiado largas.");
                String[] rutas = new String(entrada, StandardCharsets.UTF_8).split("\\x00", -1);
                if (rutas.length < 1 || rutas.length > ("verify".equals(args[0]) ? 2 : 1)
                    || Arrays.stream(rutas).anyMatch(String::isEmpty)) {
                    throw new IllegalArgumentException("Indica una ruta PDF, o dos para comparar con el original.");
                }
                String comando = args[0];
                args = new String[rutas.length + 1];
                args[0] = comando;
                System.arraycopy(rutas, 0, args, 1, rutas.length);
            }
            if (args.length == 2 && "verify".equals(args[0])) {
                emit(verify(Path.of(args[1])));
            } else if (args.length == 3 && "verify".equals(args[0])) {
                emitOfficial(Path.of(args[1]), Path.of(args[2]));
            } else if (args.length == 2 && "inspect".equals(args[0])) {
                emit(inspect(Path.of(args[1])));
            } else if (args.length == 2 && "text".equals(args[0])) {
                emitText(Path.of(args[1]));
            } else if (args.length == 2 && "inspect-crl".equals(args[0])) {
                emit(inspectCrl(Path.of(args[1])));
            } else if (args.length == 5 && "sign".equals(args[0])) {
                sign(Path.of(args[1]), Path.of(args[2]), Path.of(args[3]), Path.of(args[4]));
                emit(new Result("firmado", "Firma CMS creada en el PDF.", true));
            } else if (args.length == 1 && "selftest".equals(args[0])) {
                selftest();
                emit(new Result("firmado", "Autoprueba Java: firma genuina, alteración, ausencia de firma, PDF activo y PDF dañado comprobados.", true));
            } else {
                emit(new Result("verificacion_no_disponible", "Uso: verify PDF [ORIGINAL] | inspect PDF | text PDF | sign ENTRADA SALIDA P12 ARCHIVO_CLAVE", false));
                System.exit(2);
            }
        } catch (Exception e) {
            diagnosticarError(e);
            emit(new Result("verificacion_no_disponible", "No fue posible completar la operación PDF. Revisa el archivo y la configuración del validador.", false));
            System.exit(2);
        }
    }

    private static Result verify(Path path) throws Exception {
        long inicio = System.nanoTime();
        byte[] bytes = Files.readAllBytes(path);
        try (PDDocument pdf = Loader.loadPDF(bytes)) {
            diagnostico("abrir_pdf", inicio);
            List<PDSignature> signatures = pdf.getSignatureDictionaries();
            if (signatures.isEmpty()) return new Result("sin_firma", "El PDF no contiene firma digital.", false);
            if (pdf.isEncrypted()) return new Result("firma_invalida", "El PDF está cifrado.", false);
            for (PDSignature signature : signatures) {
                if (!rangoValido(signature, bytes)) {
                    return new Result("firma_invalida", "Los rangos de bytes de una firma están dañados o excluyen contenido ajeno a la firma.", false);
                }
            }
            RevocationResolver.Evidencia evidencia = RevocationResolver.extraer(pdf);
            signatures.sort((a, b) -> Long.compare(
                (long) a.getByteRange()[2] + a.getByteRange()[3],
                (long) b.getByteRange()[2] + b.getByteRange()[3]));
            PDSignature ultima = signatures.get(signatures.size() - 1);
            int finFirmado = ultima.getByteRange()[2] + ultima.getByteRange()[3];
            if (!RevisionFirmadaPdf.coincide(bytes, finFirmado, pdf)) {
                return new Result("firma_invalida", "El contenido del PDF cambió después de la última firma.", false);
            }
            List<X509CertificateHolder> certificadosPdf = new ArrayList<>();
            for (PDSignature signature : signatures) {
                CMSSignedData cms = new CMSSignedData(new ByteArrayInputStream(signature.getContents(bytes)));
                certificadosPdf.addAll(cms.getCertificates().getMatches(null));
            }
            List<Date> fechasConfiables = new ArrayList<>();
            int firmasAutor = 0;
            for (PDSignature signature : signatures) {
                if (!"ETSI.RFC3161".equals(signature.getSubFilter())) {
                    firmasAutor++;
                    fechasConfiables.add(null);
                    continue;
                }
                fechasConfiables.add(MarcaTiempoConfiable.obtenerDocumento(signature, bytes,
                    trustAnchors(), certificadosPdf));
            }
            if (firmasAutor == 0) return new Result("sin_firma", "El PDF contiene sellos de tiempo, pero ninguna firma de autor.", false);
            List<Date> fechasFirmas = new ArrayList<>();
            for (PDSignature signature : signatures) {
                Date fecha = null;
                long fin = (long) signature.getByteRange()[2] + signature.getByteRange()[3];
                for (int i = 0; i < signatures.size(); i++) {
                    Date candidata = fechasConfiables.get(i);
                    if (candidata != null && signatures.get(i).getByteRange()[1] >= fin
                        && (fecha == null || candidata.before(fecha))) fecha = candidata;
                }
                fechasFirmas.add(fecha);
            }
            boolean missingRevocationSource = false;
            String revocationStatus = "NO_REVOCADO";
            Result fallo = null;
            boolean criptografiaIntegra = true;
            ExecutorService workers = Executors.newFixedThreadPool(Math.min(4, signatures.size()));
            try {
                List<Future<Result>> results = new ArrayList<>();
                for (int index = 0; index < signatures.size(); index++) {
                    final int current = index;
                    results.add(workers.submit(() -> verifyOne(bytes, signatures.get(current),
                        evidencia, fechasFirmas.get(current), certificadosPdf)));
                }
                for (int index = 0; index < results.size(); index++) {
                    Result result = results.get(index).get();
                    criptografiaIntegra &= result.cryptographicallyValid;
                    if ("firmado_sin_revocacion".equals(result.status)) {
                        missingRevocationSource = true;
                        if ("NO_REVOCADO".equals(revocationStatus)
                            || "NO_DISPONIBLE".equals(result.estadoRevocacion)) revocationStatus = result.estadoRevocacion;
                    } else if (!"firmado".equals(result.status)) {
                        if (fallo == null || (!result.cryptographicallyValid && fallo.cryptographicallyValid)) {
                            fallo = new Result(result.status, "Firma " + (index + 1) + ": " + result.reason,
                                result.cryptographicallyValid, result.estadoRevocacion);
                        }
                    }
                }
            } finally {
                workers.shutdownNow();
            }
            if (fallo != null) return new Result(fallo.status, fallo.reason, criptografiaIntegra, fallo.estadoRevocacion);
            return new Result(missingRevocationSource ? "firmado_sin_revocacion" : "firmado",
                "Se verificaron criptográficamente " + firmasAutor + " firma(s) y "
                    + (signatures.size() - firmasAutor) + " sello(s) de tiempo."
                    + (missingRevocationSource ? " El estado de revocación no pudo comprobarse en al menos una firma." : ""),
                true, revocationStatus);
        } catch (org.apache.pdfbox.pdmodel.encryption.InvalidPasswordException e) {
            return new Result("firma_invalida", "El PDF está cifrado y no se puede validar.", false);
        } catch (Exception e) {
            diagnosticarError(e);
            return new Result("firma_invalida", "La estructura de firma PDF o CMS no es válida.", false);
        }
    }

    private static Result inspectCrl(Path path) {
        try (InputStream in = Files.newInputStream(path)) {
            X509CRL crl = (X509CRL) CertificateFactory.getInstance("X.509").generateCRL(in);
            Date now = new Date();
            if (crl.getThisUpdate() == null || crl.getThisUpdate().after(now)
                || crl.getNextUpdate() == null || !crl.getNextUpdate().after(now)) {
                return new Result("crl_caducada", "La CRL no está vigente.", false);
            }
            return new Result("crl_vigente", "La CRL tiene formato y vigencia correctos.", true);
        } catch (Exception e) {
            return new Result("crl_invalida", "El archivo no contiene una CRL X.509 válida.", false);
        }
    }

    private static Result inspect(Path path) {
        try {
            byte[] bytes = Files.readAllBytes(path);
            if (bytes.length < 8 || !new String(bytes, 0, 8, StandardCharsets.ISO_8859_1).matches("%PDF-[12]\\.\\d.*")) {
                return new Result("archivo_inseguro", "El archivo no comienza con el número mágico de PDF.", false);
            }
            String cola = new String(bytes, Math.max(0, bytes.length - 4096), Math.min(4096, bytes.length), StandardCharsets.ISO_8859_1);
            if (!cola.contains("%%EOF")) return new Result("archivo_inseguro", "El archivo no tiene una estructura PDF completa.", false);
            try (PDDocument pdf = Loader.loadPDF(bytes)) {
                if (pdf.isEncrypted() || pdf.getNumberOfPages() < 1) {
                    return new Result("archivo_inseguro", "El PDF está cifrado o no contiene páginas legibles.", false);
                }
                Set<String> forbidden = Set.of("JavaScript", "JS", "AA", "Launch",
                    "EmbeddedFile", "EmbeddedFiles", "RichMedia", "Rendition", "XFA", "GoToR",
                    "SubmitForm", "ImportData", "FileAttachment");
                Set<COSBase> visitados = java.util.Collections.newSetFromMap(new java.util.IdentityHashMap<>());
                for (var objectKey : pdf.getDocument().getXrefTable().keySet()) {
                    COSObject object = pdf.getDocument().getObjectFromPool(objectKey);
                    String peligro = contenidoActivo(object, forbidden, visitados);
                    if (peligro != null) return new Result("archivo_inseguro", "El PDF contiene acciones o contenido activo: " + peligro, false);
                }
                double ancho = 0, alto = 0, area = 0;
                for (PDPage pagina : pdf.getPages()) {
                    double w = pagina.getMediaBox().getWidth(), h = pagina.getMediaBox().getHeight();
                    double unidad = pagina.getUserUnit();
                    w *= unidad;
                    h *= unidad;
                    if (!Double.isFinite(w) || !Double.isFinite(h) || w <= 0 || h <= 0) {
                        return new Result("archivo_inseguro", "El PDF contiene dimensiones de página inválidas.", false);
                    }
                    ancho = Math.max(ancho, w);
                    alto = Math.max(alto, h);
                    area += w * h;
                }
                int maxPaginas = Integer.getInteger("hubdigital.pdf.max_pages", 40);
                int maxPoints = Integer.getInteger("hubdigital.pdf.max_page_points", 1440);
                int dpi = Integer.getInteger("hubdigital.pdf.render_dpi", 110);
                long maxPixeles = Long.getLong("hubdigital.pdf.max_render_pixels", 100_000_000L);
                if (pdf.getNumberOfPages() > maxPaginas || ancho > maxPoints || alto > maxPoints
                    || dpi < 36 || dpi > 300 || !Double.isFinite(area)
                    || area * dpi * dpi / (72.0 * 72.0) > maxPixeles) {
                    return new Result("archivo_inseguro", "El PDF excede los límites de páginas, dimensiones o procesamiento gráfico.", false);
                }
                int firmasAutor = 0, sellosTiempo = 0;
                for (PDSignature firma : pdf.getSignatureDictionaries()) {
                    if ("ETSI.RFC3161".equals(firma.getSubFilter())) sellosTiempo++;
                    else firmasAutor++;
                }
                return new Result("seguro", "Estructura PDF sin JavaScript ni contenido activo.", true,
                    "NO_COMPROBADO", new PdfInfo(pdf.getNumberOfPages(), ancho, alto, area, firmasAutor, sellosTiempo));
            }
        } catch (Exception e) {
            diagnosticarError(e);
            return new Result("archivo_inseguro", "El PDF no pudo abrirse de forma segura.", false);
        }
    }

    private static String contenidoActivo(COSBase base, Set<String> forbidden, Set<COSBase> visitados) {
        if (base instanceof COSObject object) base = object.getObject();
        if (base == null || !visitados.add(base)) return null;
        if (base instanceof COSDictionary dictionary) {
            for (COSName key : dictionary.keySet()) {
                if (forbidden.contains(key.getName())) return key.getName();
                if ("OpenAction".equals(key.getName()) && !destinoInterno(dictionary.getDictionaryObject(key))) return "OpenAction";
                String peligro = contenidoActivo(dictionary.getItem(key), forbidden, visitados);
                if (peligro != null) return peligro;
            }
            String action = dictionary.getNameAsString(COSName.S);
            if (action != null && forbidden.contains(action)) return action;
        } else if (base instanceof COSArray array) {
            for (int i = 0; i < array.size(); i++) {
                String peligro = contenidoActivo(array.get(i), forbidden, visitados);
                if (peligro != null) return peligro;
            }
        }
        return null;
    }

    private static boolean destinoInterno(COSBase base) {
        if (base instanceof COSDictionary dictionary) {
            return "GoTo".equals(dictionary.getNameAsString(COSName.S))
                && !dictionary.containsKey(COSName.getPDFName("Next"))
                && destinoInterno(dictionary.getDictionaryObject(COSName.D));
        }
        if (!(base instanceof COSArray array) || array.size() < 2 || array.size() > 6) return false;
        if (!(array.getObject(0) instanceof COSDictionary pagina)
            || !"Page".equals(pagina.getNameAsString(COSName.TYPE))) return false;
        return array.getObject(1) instanceof COSName tipo
            && Set.of("XYZ", "Fit", "FitH", "FitV", "FitR", "FitB", "FitBH", "FitBV").contains(tipo.getName());
    }

    private static void emitText(Path path) throws Exception {
        try (PDDocument pdf = Loader.loadPDF(path.toFile())) {
            if (pdf.isEncrypted()) throw new java.io.IOException("PDF cifrado.");
            PDFTextStripper extractor = new PDFTextStripper();
            extractor.setSortByPosition(true);
            StringBuilder salida = new StringBuilder("{\"paginas\":[");
            for (int pagina = 1; pagina <= pdf.getNumberOfPages(); pagina++) {
                extractor.setStartPage(pagina);
                extractor.setEndPage(pagina);
                if (pagina > 1) salida.append(',');
                salida.append("{\"numero\":").append(pagina).append(",\"texto\":\"")
                    .append(escape(extractor.getText(pdf))).append("\"}");
            }
            System.out.println(salida.append("]}"));
        }
    }

    private static boolean rangoValido(PDSignature signature, byte[] bytes) throws Exception {
        int[] range = signature.getByteRange();
        if (range == null || range.length != 4 || range[0] != 0 || range[1] < 1
            || range[2] <= range[1] || range[3] < 1 || (long) range[2] + range[3] > bytes.length) return false;
        if (bytes[range[1]] != '<' || bytes[range[2] - 1] != '>') return false;
        for (int i = range[1] + 1; i < range[2] - 1; i++) {
            int c = bytes[i] & 0xff;
            if (Character.digit((char) c, 16) < 0 && c != 0 && c != 9 && c != 10 && c != 12 && c != 13 && c != 32) return false;
        }
        COSBase contenido = signature.getCOSObject().getDictionaryObject(COSName.CONTENTS);
        return contenido instanceof COSString valor
            && MessageDigest.isEqual(valor.getBytes(), signature.getContents(bytes));
    }

    private static Result verifyOne(byte[] bytes, PDSignature signature,
        RevocationResolver.Evidencia evidencia, Date fechaDocumento,
        List<X509CertificateHolder> certificadosPdf) throws Exception {
        long inicio = System.nanoTime();
        byte[] signed = signature.getSignedContent(bytes);
        byte[] contents = signature.getContents(bytes);
        diagnostico("extraer_contenido_firmado", inicio);
        String formato = signature.getSubFilter();
        boolean selloDocumento = "ETSI.RFC3161".equals(formato);
        CMSSignedData cms;
        if (selloDocumento || "adbe.pkcs7.sha1".equals(formato)) {
            cms = new CMSSignedData(new ByteArrayInputStream(contents));
            if (!selloDocumento && (cms.getSignedContent() == null
                || !MessageDigest.isEqual((byte[]) cms.getSignedContent().getContent(),
                    MessageDigest.getInstance("SHA-1").digest(signed)))) {
                return new Result("firma_invalida", "El resumen SHA-1 no corresponde al PDF firmado.", false);
            }
        } else if ("adbe.pkcs7.detached".equals(formato) || "ETSI.CAdES.detached".equals(formato)) {
            cms = new CMSSignedData(new CMSProcessableByteArray(signed), new ByteArrayInputStream(contents));
        } else {
            return new Result("firma_invalida", "Formato de firma PDF no compatible: " + formato, false);
        }
        diagnostico("leer_cms", inicio);
        var crlsCms = cms.getCRLs().getMatches(null);
        if (!crlsCms.isEmpty()) {
            List<byte[]> crls = new ArrayList<>(evidencia.crl());
            for (var crl : crlsCms) {
                if (crls.size() >= 32) break;
                byte[] encoded = crl.getEncoded();
                if (encoded.length <= 10_000_000) crls.add(encoded);
            }
            evidencia = new RevocationResolver.Evidencia(evidencia.ocsp(), crls);
        }
        Collection<SignerInformation> signers = cms.getSignerInfos().getSigners();
        if (signers.size() != 1) return new Result("firma_invalida", "El CMS no tiene un firmante único.", false);
        SignerInformation signer = signers.iterator().next();
        Collection<X509CertificateHolder> matches = cms.getCertificates().getMatches(signer.getSID());
        if (matches.size() != 1) return new Result("firma_invalida", "No se encontró el certificado del firmante.", false);
        X509Certificate certificate = (X509Certificate) CertificateFactory.getInstance("X.509")
            .generateCertificate(new ByteArrayInputStream(matches.iterator().next().getEncoded()));
        diagnostico("extraer_certificado", inicio);
        Date fechaValidacion = fechaDocumento == null ? new Date() : fechaDocumento;
        if (selloDocumento) {
            Date fechaSello = MarcaTiempoConfiable.obtenerDocumento(signature, bytes,
                trustAnchors(), certificadosPdf);
            if (fechaSello == null) return new Result("firma_invalida", "El sello de tiempo del documento no es íntegro o confiable.", false);
            fechaValidacion = fechaSello;
        } else if (!("adbe.pkcs7.sha1".equals(formato)
            ? signer.verify(new JcaSimpleSignerInfoVerifierBuilder().build(certificate.getPublicKey()))
            : verificarCms(signer, certificate, signed))) {
            return new Result("firma_invalida", "Falló la comprobación criptográfica del contenido firmado.", false);
        }
        diagnostico("firma_criptografica", inicio);
        boolean[] usoClave = certificate.getKeyUsage();
        if (usoClave != null && (usoClave.length == 0 || (!usoClave[0] && (usoClave.length < 2 || !usoClave[1])))) {
            return new Result("certificado_no_confiable", "El certificado no autoriza firma digital ni no repudio.", true);
        }
        AttributeTable unsigned = signer.getUnsignedAttributes();
        if (unsigned != null && unsigned.get(PKCSObjectIdentifiers.id_aa_signatureTimeStampToken) != null) {
            Date fechaConfiable = MarcaTiempoConfiable.obtener(signer, trustAnchors(), certificadosPdf);
            if (fechaConfiable != null && fechaConfiable.before(fechaValidacion)) fechaValidacion = fechaConfiable;
        }
        try {
            certificate.checkValidity(fechaValidacion);
        } catch (java.security.cert.CertificateExpiredException e) {
            return new Result("certificado_caducado", "El certificado está caducado y no hay un sello de tiempo confiable durante su vigencia.", true);
        } catch (java.security.cert.CertificateNotYetValidException e) {
            return new Result("certificado_aun_no_vigente", "El certificado aún no era válido en la fecha comprobada.", true);
        }
        return validateChain(certificate, evidencia, fechaValidacion, certificadosPdf);
    }

    private static boolean verificarCms(SignerInformation signer, X509Certificate certificate, byte[] signed)
        throws Exception {
        long inicio = System.nanoTime();
        String algoritmo = switch (signer.getEncryptionAlgOID()) {
            case "1.2.840.113549.1.1.11" -> "SHA256withRSA";
            case "1.2.840.113549.1.1.12" -> "SHA384withRSA";
            case "1.2.840.113549.1.1.13" -> "SHA512withRSA";
            case "1.2.840.10045.4.3.2" -> "SHA256withECDSA";
            case "1.2.840.10045.4.3.3" -> "SHA384withECDSA";
            case "1.2.840.10045.4.3.4" -> "SHA512withECDSA";
            default -> null;
        };
        String hash = switch (signer.getDigestAlgOID()) {
            case "2.16.840.1.101.3.4.2.1" -> "SHA-256";
            case "2.16.840.1.101.3.4.2.2" -> "SHA-384";
            case "2.16.840.1.101.3.4.2.3" -> "SHA-512";
            default -> null;
        };
        if (algoritmo == null || hash == null || !algoritmo.replace("-", "").startsWith(hash.replace("-", ""))) {
            diagnostico("cms_ruta_generica", inicio);
            return signer.verify(new JcaSimpleSignerInfoVerifierBuilder().build(certificate.getPublicKey()));
        }
        AttributeTable attributes = signer.getSignedAttributes();
        byte[] signedBytes = signed;
        if (attributes != null) {
            Attribute digestAttribute = attributes.get(CMSAttributes.messageDigest);
            Attribute contentAttribute = attributes.get(CMSAttributes.contentType);
            if (digestAttribute == null || digestAttribute.getAttrValues().size() != 1
                || contentAttribute == null || contentAttribute.getAttrValues().size() != 1
                || !PKCSObjectIdentifiers.data.equals(contentAttribute.getAttrValues().getObjectAt(0))) return false;
            byte[] expected = ASN1OctetString.getInstance(digestAttribute.getAttrValues().getObjectAt(0)).getOctets();
            if (!MessageDigest.isEqual(expected, MessageDigest.getInstance(hash).digest(signed))) return false;
            diagnostico("cms_digest", inicio);
            signedBytes = signer.getEncodedSignedAttributes();
        }
        Signature verifier = Signature.getInstance(algoritmo);
        diagnostico("cms_instancia_jca", inicio);
        var publicKey = certificate.getPublicKey();
        diagnostico("cms_clave_publica", inicio);
        verifier.initVerify(publicKey);
        diagnostico("cms_iniciar_verificador", inicio);
        verifier.update(signedBytes);
        diagnostico("cms_previo_firma", inicio);
        return verifier.verify(signer.getSignature());
    }

    private static Result validateChain(X509Certificate signer,
        RevocationResolver.Evidencia evidencia, Date fechaValidacion,
        List<X509CertificateHolder> certificadosPdf) throws Exception {
        long inicio = System.nanoTime();
        String issuerName = signer.getIssuerX500Principal().getName().toUpperCase(java.util.Locale.ROOT);
        Set<TrustAnchor> anchors = trustAnchors();
        if (anchors.isEmpty()) return new Result("certificado_no_confiable", "No hay autoridades de confianza configuradas.", true);
        diagnostico("almacen_confianza", inicio);
        PKIXCertPathBuilderResult construida;
        try {
            construida = construirRuta(signer, certificadosPdf, anchors, fechaValidacion);
            diagnostico("cadena_pkix", inicio);
        } catch (CertPathBuilderException e) {
            diagnosticarError(e);
            return new Result("almacen_incompleto", "No se pudo construir una cadena X.509 vigente hasta una raíz de confianza. Revisa los certificados emisores.", true);
        }
        CertPath path = construida.getCertPath();
        List<? extends Certificate> chain = path.getCertificates();
        if (chain.isEmpty()) return new Result("firmado_sin_revocacion",
            "La firma y el certificado raíz son íntegros; su revocación no está comprobada.", true, "NO_COMPROBADO");
        URI responderExcepcional = fuenteExcepcional(issuerName, "HUBDIGITAL_SIGNATURE_OCSP_RESPONDERS");
        URI crlExcepcional = fuenteExcepcional(issuerName, "HUBDIGITAL_SIGNATURE_CRL_OVERRIDES");
        int timeout = Integer.getInteger("hubdigital.revocation.timeout", 2);
        X509Certificate issuer = chain.size() > 1 ? (X509Certificate) chain.get(1) : construida.getTrustAnchor().getTrustedCert();
        RevocationResolver.Resultado revocation = RevocationResolver.resolver(signer, issuer, path,
            anchors, evidencia, responderExcepcional, crlExcepcional, fechaValidacion,
            Math.max(1, Math.min(timeout, 5)));
        diagnostico("revocacion", inicio);
        if (revocation.estado() == RevocationResolver.Estado.REVOCADO) {
            return new Result("certificado_revocado", revocation.fuente(), true, "REVOCADO");
        }
        if (revocation.estado() == RevocationResolver.Estado.NO_REVOCADO) {
            return new Result("firmado", "Firma, integridad y cadena válidas; revocación comprobada mediante "
                + revocation.fuente() + ".", true, "NO_REVOCADO");
        }
        return new Result("firmado_sin_revocacion", "Firma, integridad y cadena válidas. "
            + revocation.fuente(), true, revocation.estado().name());
    }

    static PKIXCertPathBuilderResult construirRuta(X509Certificate signer,
        Collection<X509CertificateHolder> certificados, Set<TrustAnchor> anchors, Date fecha) throws Exception {
        List<X509Certificate> disponibles = new ArrayList<>(authorityCertificates());
        disponibles.add(signer);
        for (X509CertificateHolder holder : certificados) {
            disponibles.add((X509Certificate) CertificateFactory.getInstance("X.509")
                .generateCertificate(new ByteArrayInputStream(holder.getEncoded())));
        }
        X509CertSelector destino = new X509CertSelector();
        destino.setCertificate(signer);
        PKIXBuilderParameters parametros = new PKIXBuilderParameters(anchors, destino);
        parametros.setRevocationEnabled(false);
        parametros.setDate(fecha);
        parametros.addCertStore(CertStore.getInstance("Collection", new CollectionCertStoreParameters(disponibles)));
        return (PKIXCertPathBuilderResult) CertPathBuilder.getInstance("PKIX").build(parametros);
    }

    private static synchronized List<X509Certificate> authorityCertificates() throws Exception {
        if (cachedAuthorities != null) return cachedAuthorities;
        List<X509Certificate> autoridades = new ArrayList<>();
        String configuredDir = System.getenv("HUBDIGITAL_SIGNATURE_TRUST_DIR");
        Path trustDir = configuredDir == null || configuredDir.isBlank()
            ? Path.of(PdfSignatureCli.class.getProtectionDomain().getCodeSource().getLocation().toURI())
                .getParent().resolve("../signature-trust").normalize()
            : Path.of(configuredDir);
        if (Files.isDirectory(trustDir)) {
            try (var files = Files.list(trustDir)) {
                for (Path file : files.filter(p -> p.getFileName().toString().matches("(?i).*\\.(pem|cer|crt)")).toList()) {
                    try (InputStream in = Files.newInputStream(file)) {
                        for (Certificate cert : CertificateFactory.getInstance("X.509").generateCertificates(in)) {
                            if (cert instanceof X509Certificate ca && ca.getBasicConstraints() >= 0) autoridades.add(ca);
                        }
                    }
                }
            }
        }
        cachedAuthorities = List.copyOf(autoridades);
        return cachedAuthorities;
    }

    static synchronized Set<TrustAnchor> trustAnchors() throws Exception {
        if (cachedAnchors != null) return cachedAnchors;
        Set<TrustAnchor> anchors = new HashSet<>();
        KeyStore trust = KeyStore.getInstance(KeyStore.getDefaultType());
        String configured = System.getenv("HUBDIGITAL_SIGNATURE_TRUSTSTORE");
        if (configured != null && !configured.isBlank()) {
            try (InputStream in = Files.newInputStream(Path.of(configured))) {
                String password = System.getenv("HUBDIGITAL_SIGNATURE_TRUSTSTORE_PASSWORD");
                trust.load(in, password == null ? new char[0] : password.toCharArray());
            }
        } else {
            Path cacerts = Path.of(System.getProperty("java.home"), "lib", "security", "cacerts");
            try (InputStream in = Files.newInputStream(cacerts)) { trust.load(in, "changeit".toCharArray()); }
        }
        Enumeration<String> aliases = trust.aliases();
        while (aliases.hasMoreElements()) {
            Certificate cert = trust.getCertificate(aliases.nextElement());
            if (cert instanceof X509Certificate x509) anchors.add(new TrustAnchor(x509, null));
        }
        for (X509Certificate root : authorityCertificates()) {
            if (root.getSubjectX500Principal().equals(root.getIssuerX500Principal())) {
                root.verify(root.getPublicKey());
                anchors.add(new TrustAnchor(root, null));
            }
        }
        cachedAnchors = Set.copyOf(anchors);
        return cachedAnchors;
    }

    private static URI fuenteExcepcional(String issuerName, String variable) {
        String configured = System.getenv(variable);
        if (configured == null) return null;
        for (String entry : configured.split("\\|")) {
            int separator = entry.indexOf('=');
            if (separator <= 0 || !issuerName.contains(entry.substring(0, separator).trim().toUpperCase(java.util.Locale.ROOT))) continue;
            try { return URI.create(entry.substring(separator + 1).trim()); }
            catch (IllegalArgumentException ignored) { return null; }
        }
        return null;
    }

    private static void diagnostico(String fase, long inicio) {
        if ("1".equals(System.getenv("HUBDIGITAL_SIGNATURE_DIAGNOSTICS"))) {
            System.err.println(fase + "_ms=" + (System.nanoTime() - inicio) / 1_000_000);
        }
    }

    private static void diagnosticarError(Exception error) {
        if ("1".equals(System.getenv("HUBDIGITAL_SIGNATURE_DIAGNOSTICS"))) {
            error.printStackTrace(System.err);
        }
    }

    private static void sign(Path input, Path output, Path p12, Path passwordFile) throws Exception {
        String raw = Files.readString(passwordFile, StandardCharsets.UTF_8);
        KeyStore keystore = null;
        String password = null;
        for (String line : raw.split("\\R")) {
            line = line.trim();
            if (line.isEmpty()) continue;
            String[] words = line.split("\\s+");
            List<String> candidates = new ArrayList<>();
            candidates.add(line);
            candidates.add(words[words.length - 1]);
            if (words.length > 1) candidates.add(String.join(" ", Arrays.copyOfRange(words, 1, words.length)));
            if (line.contains("=")) candidates.add(line.substring(line.indexOf('=') + 1).trim());
            for (String candidate : candidates) {
                try (InputStream in = Files.newInputStream(p12)) {
                    KeyStore opened = KeyStore.getInstance("PKCS12");
                    opened.load(in, candidate.toCharArray());
                    keystore = opened;
                    password = candidate;
                    break;
                } catch (java.io.IOException ignored) { /* probar la siguiente forma de la clave */ }
            }
            if (keystore != null) break;
        }
        if (keystore == null) throw new IllegalArgumentException("No se pudo abrir el P12 con la credencial proporcionada.");
        String alias = null;
        Enumeration<String> aliases = keystore.aliases();
        while (aliases.hasMoreElements()) {
            String candidate = aliases.nextElement();
            if (keystore.isKeyEntry(candidate)) { alias = candidate; break; }
        }
        if (alias == null) throw new IllegalArgumentException("El P12 no tiene clave privada.");
        PrivateKey key = (PrivateKey) keystore.getKey(alias, password.toCharArray());
        Certificate[] chain = keystore.getCertificateChain(alias);
        X509Certificate signer = (X509Certificate) chain[0];
        signer.checkValidity(new Date());
        List<X509Certificate> certificates = Arrays.stream(chain).map(c -> (X509Certificate) c).toList();
        Files.createDirectories(output.toAbsolutePath().getParent());
        try (PDDocument pdf = Loader.loadPDF(input.toFile()); FileOutputStream out = new FileOutputStream(output.toFile())) {
            PDSignature signature = new PDSignature();
            signature.setFilter(PDSignature.FILTER_ADOBE_PPKLITE);
            signature.setSubFilter(PDSignature.SUBFILTER_ETSI_CADES_DETACHED);
            signature.setName(signer.getSubjectX500Principal().getName());
            signature.setSignDate(java.util.Calendar.getInstance());
            pdf.addSignature(signature, content -> {
                try {
                    CMSSignedDataGenerator generator = new CMSSignedDataGenerator();
                    String algorithm = "EC".equalsIgnoreCase(key.getAlgorithm()) ? "SHA256withECDSA" : "SHA256withRSA";
                    generator.addSignerInfoGenerator(new JcaSignerInfoGeneratorBuilder(
                        new JcaDigestCalculatorProviderBuilder().setProvider("BC").build()
                    ).build(new JcaContentSignerBuilder(algorithm).setProvider("BC").build(key), signer));
                    generator.addCertificates(new JcaCertStore(certificates));
                    return generator.generate(new CMSProcessableByteArray(content.readAllBytes()), false).getEncoded();
                } catch (Exception e) { throw new java.io.IOException("No se pudo crear CMS", e); }
            });
            pdf.saveIncremental(out);
        }
    }

    private static void selftest() throws Exception {
        Path temp = Files.createTempDirectory("hubdigital-pdf-signature-");
        try {
            Path unsigned = temp.resolve("sin-firma.pdf");
            Path signed = temp.resolve("firmado.pdf");
            Path altered = temp.resolve("alterado.pdf");
            Path active = temp.resolve("activo.pdf");
            Path malformed = temp.resolve("malformado.pdf");
            Path p12 = temp.resolve("prueba.p12");
            Path password = temp.resolve("clave.txt");
            try (PDDocument pdf = new PDDocument()) {
                pdf.addPage(new PDPage());
                pdf.save(unsigned.toFile());
            }
            if (!"seguro".equals(inspect(unsigned).status))
                throw new IllegalStateException("Se rechazó un PDF seguro.");
            try (PDDocument pdf = new PDDocument()) {
                pdf.addPage(new PDPage());
                pdf.getDocumentCatalog().getCOSObject().setItem(COSName.OPEN_ACTION, new COSDictionary());
                pdf.save(active.toFile());
            }
            if (!"archivo_inseguro".equals(inspect(active).status))
                throw new IllegalStateException("Se aceptó un PDF con acción activa.");
            Files.writeString(malformed, "%PDF-1.7\ncontenido inválido", StandardCharsets.UTF_8);
            if (!"archivo_inseguro".equals(inspect(malformed).status))
                throw new IllegalStateException("Se aceptó un PDF dañado.");
            KeyPairGenerator keygen = KeyPairGenerator.getInstance("RSA");
            keygen.initialize(2048);
            KeyPair keys = keygen.generateKeyPair();
            X500Name name = new X500Name("C=EC,O=HubDigital QA,CN=Prueba criptográfica");
            Date from = Date.from(Instant.now().minusSeconds(60));
            Date to = Date.from(Instant.now().plusSeconds(3600));
            X509Certificate cert = new JcaX509CertificateConverter().setProvider("BC").getCertificate(
                new JcaX509v3CertificateBuilder(name, BigInteger.valueOf(System.nanoTime()).abs(),
                    from, to, name, keys.getPublic())
                    .build(new JcaContentSignerBuilder("SHA256withRSA").setProvider("BC").build(keys.getPrivate())));
            KeyStore store = KeyStore.getInstance("PKCS12");
            store.load(null, null);
            store.setKeyEntry("qa", keys.getPrivate(), "temporal".toCharArray(), new Certificate[] { cert });
            try (var out = Files.newOutputStream(p12)) { store.store(out, "temporal".toCharArray()); }
            Files.writeString(password, "temporal", StandardCharsets.UTF_8);

            sign(unsigned, signed, p12, password);
            cachedAnchors = Set.of(new TrustAnchor(cert, null));
            Result genuino = verify(signed);
            if (!"firmado_sin_revocacion".equals(genuino.status) || !genuino.cryptographicallyValid)
                throw new IllegalStateException("Una firma genuina sin fuente de revocación no fue aceptada.");
            byte[] changed = Files.readAllBytes(signed);
            changed[7] = changed[7] == '6' ? (byte) '7' : (byte) '6';
            Files.write(altered, changed);
            if (!"firma_invalida".equals(verify(altered).status)) throw new IllegalStateException("Se aceptó un PDF alterado.");
            if (!"sin_firma".equals(verify(unsigned).status)) throw new IllegalStateException("Se aceptó un PDF sin firma.");
        } finally {
            try (var files = Files.list(temp)) {
                for (Path file : files.toList()) Files.deleteIfExists(file);
            }
            Files.deleteIfExists(temp);
        }
    }

    private record PdfInfo(int paginas, double ancho, double alto, double area, int firmasAutor, int sellosTiempo) {}

    private record Result(String status, String reason, boolean cryptographicallyValid, String estadoRevocacion, PdfInfo pdfInfo) {
        Result(String status, String reason, boolean cryptographicallyValid, String estadoRevocacion) {
            this(status, reason, cryptographicallyValid, estadoRevocacion, null);
        }
        Result(String status, String reason, boolean cryptographicallyValid) {
            this(status, reason, cryptographicallyValid,
                "certificado_revocado".equals(status) ? "REVOCADO" : "NO_COMPROBADO", null);
        }
    }

    private static void emit(Result result) {
        System.out.println(json(result));
    }

    private static void emitOfficial(Path path, Path original) throws Exception {
        Result result = verify(path);
        boolean certificadoValido = "firmado".equals(result.status) || "firmado_sin_revocacion".equals(result.status);
        boolean formato = false, coincide = false;
        String certificado = "{}";
        try (PDDocument firmado = Loader.loadPDF(path.toFile()); PDDocument base = Loader.loadPDF(original.toFile())) {
            List<PDSignature> firmas = firmado.getSignatureDictionaries();
            if (firmas.size() == 1) {
                PDSignature firma = firmas.get(0);
                formato = "ETSI.CAdES.detached".equals(firma.getSubFilter());
                if (formato
                    && "seguro".equals(inspect(path).status) && "seguro".equals(inspect(original).status)) {
                    coincide = ContenidoOficialPdf.coincide(base, firmado);
                }
                if (result.cryptographicallyValid) {
                    CMSSignedData cms = new CMSSignedData(new ByteArrayInputStream(firma.getContents(Files.readAllBytes(path))));
                    SignerInformation firmante = cms.getSignerInfos().getSigners().iterator().next();
                    Collection<X509CertificateHolder> holders = cms.getCertificates().getMatches(firmante.getSID());
                    if (holders.size() == 1) {
                        X509Certificate cert = (X509Certificate) CertificateFactory.getInstance("X.509")
                            .generateCertificate(new ByteArrayInputStream(holders.iterator().next().getEncoded()));
                        String dn = cert.getSubjectX500Principal().getName();
                        String nombre = dn;
                        for (var rdn : new javax.naming.ldap.LdapName(dn).getRdns()) {
                            if ("CN".equalsIgnoreCase(rdn.getType())) nombre = String.valueOf(rdn.getValue());
                        }
                        String fechaFirma = firma.getSignDate() == null ? null : firma.getSignDate().toInstant().toString();
                        String algoritmoHash = switch (firmante.getDigestAlgOID()) {
                            case "1.3.14.3.2.26" -> "SHA-1";
                            case "2.16.840.1.101.3.4.2.1" -> "SHA-256";
                            case "2.16.840.1.101.3.4.2.2" -> "SHA-384";
                            case "2.16.840.1.101.3.4.2.3" -> "SHA-512";
                            default -> firmante.getDigestAlgOID();
                        };
                        certificado = "{\"nombre\":\"" + escape(nombre) + "\",\"distinguished_name\":\"" + escape(dn)
                            + "\",\"entidad_emisora\":\"" + escape(cert.getIssuerX500Principal().getName())
                            + "\",\"fecha_firma\":" + (fechaFirma == null ? "null" : "\"" + escape(fechaFirma) + "\"")
                            + ",\"algoritmo_hash\":\"" + escape(algoritmoHash)
                            + "\",\"tipo_firma\":\"" + escape(firma.getSubFilter())
                            + "\",\"valido_desde\":\"" + cert.getNotBefore().toInstant()
                            + "\",\"valido_hasta\":\"" + cert.getNotAfter().toInstant() + "\"}";
                    }
                }
            }
            if (certificadoValido && (!formato || !coincide)) {
                result = new Result("firma_invalida", !formato
                    ? "La solicitud o acta debe contener una única firma ETSI CAdES detached."
                    : "El contenido o el sello visible no coincide con el original oficial.",
                    result.cryptographicallyValid, result.estadoRevocacion);
            }
        }
        boolean aceptable = certificadoValido && formato && coincide;
        String salida = json(result);
        System.out.println(salida.substring(0, salida.length() - 1)
            + ",\"documento_completo_firmado\":" + result.cryptographicallyValid
            + ",\"contenido_oficial_coincide\":" + coincide
            + ",\"certificado_vigente\":" + certificadoValido
            + ",\"certificado_confiable\":" + certificadoValido
            + ",\"formato_firma_aceptado\":" + formato
            + ",\"aceptable\":" + aceptable + ",\"certificado\":" + certificado + "}");
    }

    private static String json(Result result) {
        boolean valido = "firmado".equals(result.status) || "firmado_sin_revocacion".equals(result.status);
        boolean indeterminado = "verificacion_no_disponible".equals(result.status)
            || "seguro".equals(result.status) || result.status.startsWith("crl_");
        String certificado = switch (result.status) {
            case "certificado_revocado" -> "REVOCADO";
            case "certificado_caducado" -> "CADUCADO";
            case "certificado_aun_no_vigente" -> "AUN_NO_VIGENTE";
            case "certificado_no_confiable", "almacen_incompleto" -> "NO_CONFIABLE";
            default -> valido ? "VALIDO" : "NO_COMPROBADO";
        };
        return "{\"status\":\"" + escape(result.status) + "\",\"reason\":\""
            + escape(result.reason) + "\",\"cryptographically_valid\":" + result.cryptographicallyValid
            + ",\"estado_documento\":\"" + (valido ? "VALIDO" : (indeterminado ? "INDETERMINADO" : "INVALIDO"))
            + "\",\"estado_firma\":\"" + (indeterminado ? "NO_COMPROBADA"
                : (result.cryptographicallyValid ? "VALIDA" : "INVALIDA"))
            + "\",\"estado_certificado\":\"" + certificado
            + "\",\"estado_revocacion\":\"" + escape(result.estadoRevocacion) + "\""
            + (result.pdfInfo == null ? "" : ",\"paginas\":" + result.pdfInfo.paginas
                + ",\"ancho_maximo_points\":" + result.pdfInfo.ancho
                + ",\"alto_maximo_points\":" + result.pdfInfo.alto
                + ",\"area_paginas_points2\":" + result.pdfInfo.area
                + ",\"firmas_autor\":" + result.pdfInfo.firmasAutor
                + ",\"sellos_tiempo\":" + result.pdfInfo.sellosTiempo)
            + "}";
    }

    private static String escape(String s) {
        StringBuilder result = new StringBuilder();
        for (char c : s.toCharArray()) {
            if (c == '\\' || c == '"') result.append('\\').append(c);
            else if (c < 32 || c > 126) result.append(String.format("\\u%04x", (int) c));
            else result.append(c);
        }
        return result.toString();
    }
}
