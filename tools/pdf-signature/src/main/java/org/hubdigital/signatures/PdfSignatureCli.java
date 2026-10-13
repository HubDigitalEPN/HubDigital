package org.hubdigital.signatures;

import java.io.ByteArrayInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.BufferedReader;
import java.io.InputStreamReader;
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
import java.util.Base64;
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
import org.apache.pdfbox.io.RandomAccessReadBuffer;
import org.apache.pdfbox.pdfparser.PDFParser;
import org.apache.pdfbox.cos.COSStream;
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

    static boolean originalSeguro(Path path) throws Exception { return "seguro".equals(inspect(path).status); }

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
            if (args.length == 1 && "verify-stream".equals(args[0])) {
                verifyStream();
            } else if (args.length == 2 && "serve".equals(args[0])) {
                PdfSignatureWorker.serve(Path.of(args[1]));
            } else if (args.length == 2 && Set.of("sign", "sign-verify").contains(args[0]) && "--request-stdin".equals(args[1])) {
                byte[] entrada = System.in.readNBytes(65_537);
                try {
                    if (entrada.length > 65_536) throw new IllegalArgumentException("Petición de firma demasiado larga.");
                    System.out.println(signRequest(entrada, "sign-verify".equals(args[0])));
                } finally {
                    Arrays.fill(entrada, (byte) 0);
                }
            } else if (args.length == 2 && "verify".equals(args[0])) {
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
            if (args.length == 1 && "selftest".equals(args[0])) {
                System.err.println("Autoprueba sintetica: " + e.getClass().getName() + ": " + e.getMessage());
            }
            diagnosticarError(e);
            System.out.println(errorResult(e));
            System.exit(2);
        }
    }

    /** Mismo contrato en CLI y servicio; ninguna credencial se conserva entre peticiones. */
    static String signRequest(byte[] entrada, boolean validar) throws Exception {
        String[] datos = new String(entrada, StandardCharsets.UTF_8).split("\\x00", -1);
        try {
            if (datos.length != 5) throw new IllegalArgumentException("Petición de firma incompleta.");
            if (!originalSeguro(Path.of(datos[0]))) throw new IllegalArgumentException("El original no es un PDF seguro.");
            FirmadorPdfOficial.firmar(Path.of(datos[0]), Path.of(datos[1]), Path.of(datos[2]), datos[3].toCharArray(), datos[4]);
            return validar ? officialResult(Path.of(datos[1]), Path.of(datos[0]))
                : json(new Result("firmado", "Firma creada por Java.", true));
        } finally {
            Arrays.fill(datos, "");
        }
    }

    static String errorResult(Exception e) {
        String mensaje = "No fue posible completar la operación PDF. Revisa el archivo y la configuración del validador.";
        if (e instanceof java.security.cert.CertificateExpiredException) mensaje = "El certificado está caducado.";
        else if (e instanceof java.security.cert.CertificateNotYetValidException) mensaje = "El certificado aún no está vigente.";
        else if (e instanceof java.io.IOException && "No se pudo abrir el certificado con la credencial indicada.".equals(e.getMessage())) {
            mensaje = "No se pudo abrir el certificado. Verifica el archivo y su contraseña.";
        }
        return json(new Result("verificacion_no_disponible", mensaje, false));
    }

    /** Prepara fuentes y proveedores con una identidad sintética antes de aceptar usuarios. */
    static void prepareEngine() throws Exception {
        Set<TrustAnchor> reales = trustAnchors();
        Path temp = Files.createTempDirectory("motor-preparacion-");
        char[] password = null;
        try {
            KeyPairGenerator generator = KeyPairGenerator.getInstance("RSA");
            generator.initialize(2048);
            KeyPair keys = generator.generateKeyPair();
            X500Name name = new X500Name("CN=Preparacion interna del motor PDF");
            X509Certificate cert = new JcaX509CertificateConverter().setProvider("BC").getCertificate(
                new JcaX509v3CertificateBuilder(name, BigInteger.valueOf(System.nanoTime()).abs(),
                    Date.from(Instant.now().minusSeconds(60)), Date.from(Instant.now().plusSeconds(3600)), name, keys.getPublic())
                    .build(new JcaContentSignerBuilder("SHA256withRSA").setProvider("BC").build(keys.getPrivate())));
            cachedAnchors = Set.of(new TrustAnchor(cert, null));
            byte[] random = new byte[24]; new java.security.SecureRandom().nextBytes(random);
            password = Base64.getEncoder().encodeToString(random).toCharArray();
            Arrays.fill(random, (byte) 0);
            Path p12 = temp.resolve("identidad-sintetica.p12"), original = temp.resolve("oficio.pdf"), signed = temp.resolve("firmado.pdf");
            KeyStore store = KeyStore.getInstance("PKCS12"); store.load(null, null);
            store.setKeyEntry("motor", keys.getPrivate(), password, new Certificate[]{cert});
            try (var out = Files.newOutputStream(p12)) { store.store(out, password); }
            Path legacy = temp.resolve("identidad-sintetica-legacy.p12");
            guardarIdentidadLegacy(legacy, keys, cert, password);
            String profile = "solicitud-deposito:depositante:v1";
            try (PDDocument pdf = new PDDocument()) {
                PDPage page = new PDPage(); pdf.addPage(page);
                org.apache.pdfbox.pdmodel.font.PDFont font;
                Path fontPath = Path.of("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf");
                font = Files.isRegularFile(fontPath)
                    ? org.apache.pdfbox.pdmodel.font.PDType0Font.load(pdf, fontPath.toFile())
                    : new org.apache.pdfbox.pdmodel.font.PDType1Font(org.apache.pdfbox.pdmodel.font.Standard14Fonts.FontName.HELVETICA);
                try (var content = new org.apache.pdfbox.pdmodel.PDPageContentStream(pdf, page)) {
                    content.beginText(); content.setFont(font, 10); content.newLineAtOffset(50, 730);
                    for (int i = 0; i < 20; i++) {
                        content.showText("Preparacion de fuentes y procesamiento del oficio institucional.");
                        content.newLineAtOffset(0, -16);
                    }
                    content.endText();
                }
                List<org.apache.pdfbox.pdmodel.interactive.annotation.PDAnnotation> markers = new ArrayList<>();
                for (String kind : List.of("bloques", "zonas")) {
                    var link = new org.apache.pdfbox.pdmodel.interactive.annotation.PDAnnotationLink();
                    link.setRectangle("bloques".equals(kind)
                        ? new org.apache.pdfbox.pdmodel.common.PDRectangle(50, 100, 480, 100)
                        : new org.apache.pdfbox.pdmodel.common.PDRectangle(58, 108, 464, 84));
                    var action = new org.apache.pdfbox.pdmodel.interactive.action.PDActionURI();
                    action.setURI("https://firmas.hubdigital.invalid/" + kind + "/" + profile.replace(':', '/'));
                    link.setAction(action); markers.add(link);
                }
                page.setAnnotations(markers); pdf.save(original.toFile());
            }
            for (int pass = 0; pass < 4; pass++) {
                Files.deleteIfExists(signed);
                FirmadorPdfOficial.firmar(original, signed, pass % 2 == 0 ? p12 : legacy, password.clone(), profile);
                officialResult(signed, original);
            }
        } finally {
            // La identidad de preparación nunca forma parte de la confianza del servicio publicado.
            cachedAnchors = reales;
            if (password != null) Arrays.fill(password, '\0');
            try (var files = Files.list(temp)) {
                for (Path file : files.toList()) Files.deleteIfExists(file);
            }
            Files.deleteIfExists(temp);
        }
    }

    /** Ejercita formatos antiguos con claves sintéticas; restaura todos los parámetros del proceso. */
    private static void guardarIdentidadLegacy(Path path, KeyPair keys, X509Certificate cert, char[] password) throws Exception {
        java.util.Map<String, String> settings = java.util.Map.of(
            "keystore.pkcs12.keyProtectionAlgorithm", "PBEWithSHA1AndDESede",
            "keystore.pkcs12.certProtectionAlgorithm", "PBEWithSHA1AndRC2_40",
            "keystore.pkcs12.macAlgorithm", "HmacPBESHA1",
            "keystore.pkcs12.keyPbeIterationCount", "102400",
            "keystore.pkcs12.certPbeIterationCount", "102400",
            "keystore.pkcs12.macIterationCount", "102400");
        java.util.Map<String, String> previous = new java.util.HashMap<>();
        for (String key : settings.keySet()) previous.put(key, System.getProperty(key));
        String oldLegacy = System.getProperty("keystore.pkcs12.legacy");
        try {
            for (String key : settings.keySet()) System.setProperty(key, settings.get(key));
            System.clearProperty("keystore.pkcs12.legacy");
            KeyStore store = KeyStore.getInstance("PKCS12"); store.load(null, null);
            store.setKeyEntry("motor", keys.getPrivate(), password, new Certificate[]{cert});
            try (var output = Files.newOutputStream(path)) { store.store(output, password); }
        } finally {
            for (String key : settings.keySet()) {
                if (previous.get(key) == null) System.clearProperty(key); else System.setProperty(key, previous.get(key));
            }
            if (oldLegacy == null) System.clearProperty("keystore.pkcs12.legacy");
            else System.setProperty("keystore.pkcs12.legacy", oldLegacy);
        }
    }

    /** Prepara únicamente evidencia pública; ninguna identidad de usuario participa. */
    static void preparePublicRevocation() throws Exception {
        Path root = Path.of(System.getenv("HUBDIGITAL_SIGNATURE_TRUST_DIR")).toRealPath();
        Path catalog = root.resolve("crl-preparacion.tsv");
        if (!Files.isRegularFile(catalog) || Files.size(catalog) > 8192) return;
        List<Runnable> jobs = new ArrayList<>();
        for (String line : Files.readAllLines(catalog, StandardCharsets.UTF_8)) {
            if (line.isBlank() || line.startsWith("#") || jobs.size() >= 2) continue;
            String[] fields = line.split("\t", -1);
            if (fields.length != 2 || !fields[0].matches("[a-zA-Z0-9_-]+\\.crt")) continue;
            Path path = root.resolve(fields[0]).toRealPath();
            if (!path.getParent().equals(root) || Files.size(path) > 65536) continue;
            X509Certificate authority;
            try (InputStream in = Files.newInputStream(path)) {
                authority = (X509Certificate) CertificateFactory.getInstance("X.509").generateCertificate(in);
            }
            authority.checkValidity(new Date());
            if (authority.getBasicConstraints() < 0) continue;
            construirRuta(authority, List.of(), trustAnchors(), new Date());
            URI source = URI.create(fields[1]);
            jobs.add(() -> {
                try {
                    authority.checkValidity(new Date());
                    RevocationResolver.prepararCrl(authority, source);
                } catch (Exception ignored) { /* conserva la consulta normal */ }
            });
        }
        if (!jobs.isEmpty()) {
            var scheduler = java.util.concurrent.Executors.newSingleThreadScheduledExecutor(task -> {
                Thread thread = new Thread(task, "hubdigital-crl-publica"); thread.setDaemon(true); return thread;
            });
            var first = scheduler.submit(() -> jobs.forEach(Runnable::run));
            try { first.get(8, java.util.concurrent.TimeUnit.SECONDS); }
            catch (Exception unavailable) { first.cancel(true); }
            scheduler.scheduleWithFixedDelay(() -> jobs.forEach(Runnable::run), 120, 120, java.util.concurrent.TimeUnit.SECONDS);
        }
    }

    private static Result verify(Path path) throws Exception {
        return verify(path, true);
    }

    /** Una JVM por trabajador PHP; cada línea conserva el mismo dictamen Java. */
    private static void verifyStream() throws Exception {
        BufferedReader input = new BufferedReader(new InputStreamReader(System.in, StandardCharsets.UTF_8));
        String line;
        while ((line = input.readLine()) != null) {
            try {
                if (line.length() > 87_384) throw new IllegalArgumentException("Rutas PDF demasiado largas.");
                byte[] decoded = Base64.getDecoder().decode(line);
                if (decoded.length > 65_536) throw new IllegalArgumentException("Rutas PDF demasiado largas.");
                String[] paths = new String(decoded, StandardCharsets.UTF_8).split("\\x00", -1);
                if (paths.length < 1 || paths.length > 2 || Arrays.stream(paths).anyMatch(String::isEmpty)) {
                    throw new IllegalArgumentException("Indica una ruta PDF, o dos para comparar con el original.");
                }
                if (paths.length == 2) emitOfficial(Path.of(paths[0]), Path.of(paths[1]));
                else emit(verify(Path.of(paths[0])));
            } catch (Exception e) {
                diagnosticarError(e);
                emit(new Result("verificacion_no_disponible", "Java no pudo completar esta validación PDF.", false));
            }
            System.out.flush();
        }
    }

    private static Result verify(Path path, boolean documentoHistorico) throws Exception {
        long inicio = System.nanoTime();
        byte[] bytes = Files.readAllBytes(path);
        try (PDDocument pdf = Loader.loadPDF(bytes)) {
            diagnostico("abrir_pdf", inicio);
            List<PDSignature> signatures = pdf.getSignatureDictionaries();
            if (signatures.isEmpty()) return new Result("sin_firma", "El PDF no contiene firma digital.", false);
            if (pdf.isEncrypted()) return new Result("firma_invalida", "El PDF está cifrado.", false);
            boolean contieneCms = false;
            for (PDSignature signature : signatures) {
                COSBase contenido = signature.getCOSObject().getDictionaryObject(COSName.CONTENTS);
                if (contenido instanceof COSString valor) {
                    try {
                        CMSSignedData cms = new CMSSignedData(valor.getBytes());
                        contieneCms |= !cms.getSignerInfos().getSigners().isEmpty();
                    } catch (Exception ignored) { /* un campo de firma no garantiza un CMS utilizable */ }
                }
            }
            if (!contieneCms) return new Result("sin_firma",
                "El PDF conserva campos de firma, pero no contiene una firma digital CMS utilizable.", false);
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
            // Una revisión posterior, incluso de metadatos o DSS, debe quedar
            // cubierta por una nueva firma o sello de tiempo del documento.
            boolean documentoCompleto = finFirmado == bytes.length;
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
            List<FechaFirma> fechasComprobadas = new ArrayList<>();
            ExecutorService workers = Executors.newFixedThreadPool(Math.min(4, signatures.size()));
            try {
                List<Future<Result>> results = new ArrayList<>();
                for (int index = 0; index < signatures.size(); index++) {
                    final int current = index;
                    results.add(workers.submit(() -> verifyOne(bytes, signatures.get(current),
                        evidencia, fechasFirmas.get(current), certificadosPdf, documentoHistorico, documentoCompleto)));
                }
                for (int index = 0; index < results.size(); index++) {
                    Result result = results.get(index).get();
                    fechasComprobadas.addAll(result.fechasFirma);
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
            if (fallo != null) return new Result(fallo.status, fallo.reason, criptografiaIntegra,
                fallo.estadoRevocacion).conFechas(fechasComprobadas);
            return new Result(missingRevocationSource ? "firmado_sin_revocacion" : "firmado",
                "Se verificaron criptográficamente " + firmasAutor + " firma(s) y "
                    + (signatures.size() - firmasAutor) + " sello(s) de tiempo."
                    + (missingRevocationSource ? " El estado de revocación no pudo comprobarse en al menos una firma." : ""),
                true, revocationStatus).conFechas(fechasComprobadas);
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
            if (Files.size(path) > 32L * 1024 * 1024) return new Result("archivo_inseguro", "El PDF excede el tamaño permitido.", false);
            byte[] bytes = Files.readAllBytes(path);
            if (bytes.length < 8 || !new String(bytes, 0, 8, StandardCharsets.ISO_8859_1).matches("%PDF-[12]\\.\\d.*")) {
                return new Result("archivo_inseguro", "El archivo no comienza con el número mágico de PDF.", false);
            }
            String cola = new String(bytes, Math.max(0, bytes.length - 4096), Math.min(4096, bytes.length), StandardCharsets.ISO_8859_1);
            if (!cola.contains("%%EOF")) return new Result("archivo_inseguro", "El archivo no tiene una estructura PDF completa.", false);
            try (RandomAccessReadBuffer origen = new RandomAccessReadBuffer(bytes);
                 PDDocument pdf = new PDFParser(origen).parse(false)) {
                if (pdf.isEncrypted() || pdf.getNumberOfPages() < 1) {
                    return new Result("archivo_inseguro", "El PDF está cifrado o no contiene páginas legibles.", false);
                }
                Set<String> forbidden = Set.of("JavaScript", "JS", "AA", "Launch",
                    "EmbeddedFile", "EmbeddedFiles", "RichMedia", "Rendition", "XFA", "GoToR",
                    "SubmitForm", "ImportData", "FileAttachment");
                Set<COSBase> visitados = java.util.Collections.newSetFromMap(new java.util.IdentityHashMap<>());
                long decodificados = 0;
                long maxDecoded = Long.getLong("hubdigital.pdf.max_decoded_bytes", 268_435_456L);
                byte[] bloque = new byte[16_384];
                for (var objectKey : new ArrayList<>(pdf.getDocument().getXrefTable().keySet())) {
                    COSObject object = pdf.getDocument().getObjectFromPool(objectKey);
                    if (object.getObject() instanceof COSStream stream) {
                        try (InputStream contenido = stream.createInputStream()) {
                            int cantidad;
                            while ((cantidad = contenido.read(bloque)) != -1) {
                                decodificados += cantidad;
                                if (decodificados > maxDecoded) return new Result("archivo_inseguro", "El PDF excede el límite de contenido descomprimido.", false);
                            }
                        }
                    }
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
        List<X509CertificateHolder> certificadosPdf, boolean documentoHistorico, boolean documentoCompleto) throws Exception {
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
        Date ahora = new Date();
        Date fechaConfiable = fechaDocumento;
        if (selloDocumento) {
            Date fechaSello = MarcaTiempoConfiable.obtenerDocumento(signature, bytes,
                trustAnchors(), certificadosPdf);
            if (fechaSello == null) return new Result("firma_invalida", "El sello de tiempo del documento no es íntegro o confiable.", false);
            fechaConfiable = fechaSello;
        } else if (!("adbe.pkcs7.sha1".equals(formato)
            ? signer.verify(new JcaSimpleSignerInfoVerifierBuilder().build(certificate.getPublicKey()))
            : verificarCms(signer, certificate, signed))) {
            return new Result("firma_invalida", "Falló la comprobación criptográfica del contenido firmado.", false);
        }
        diagnostico("firma_criptografica", inicio);
        if (!documentoCompleto) {
            return new Result("documento_modificado",
                "La firma es criptográficamente válida, pero el PDF tiene una revisión posterior que ninguna firma ni sello de tiempo de documento cubre.", true);
        }
        boolean[] usoClave = certificate.getKeyUsage();
        if (usoClave != null && (usoClave.length == 0 || (!usoClave[0] && (usoClave.length < 2 || !usoClave[1])))) {
            return new Result("certificado_no_confiable", "El certificado no autoriza firma digital ni no repudio.", true);
        }
        AttributeTable unsigned = signer.getUnsignedAttributes();
        if (unsigned != null && unsigned.get(PKCSObjectIdentifiers.id_aa_signatureTimeStampToken) != null) {
            Date fechaSello = MarcaTiempoConfiable.obtener(signer, trustAnchors(), certificadosPdf);
            if (fechaSello != null && (fechaConfiable == null || fechaSello.before(fechaConfiable))) fechaConfiable = fechaSello;
        }
        Date fechaValidacion = fechaConfiable == null ? ahora : fechaConfiable;
        String baseTemporal = fechaConfiable == null ? "FECHA_ACTUAL" : "SELLO_TIEMPO_CONFIABLE";
        // Los documentos recibidos se comprueban en su fecha de firma. La fecha
        // declarada está cubierta por la firma, pero no equivale a una prueba TSA.
        if (fechaConfiable == null && documentoHistorico && !selloDocumento) {
            AttributeTable firmados = signer.getSignedAttributes();
            Attribute fechaCms = firmados == null ? null : firmados.get(CMSAttributes.signingTime);
            if (fechaCms != null) {
                if (firmados.getAll(CMSAttributes.signingTime).size() != 1 || fechaCms.getAttrValues().size() != 1) {
                    return new Result("firma_invalida", "La firma declara fechas CMS ambiguas.", true);
                }
                fechaValidacion = org.bouncycastle.asn1.cms.Time.getInstance(
                    fechaCms.getAttrValues().getObjectAt(0)).getDate();
                baseTemporal = "FECHA_DECLARADA_CMS";
            } else if (signature.getSignDate() != null) {
                fechaValidacion = signature.getSignDate().getTime();
                baseTemporal = "FECHA_DECLARADA_PDF";
            }
        }
        FechaFirma fecha = new FechaFirma(fechaValidacion, baseTemporal,
            certificate.getNotBefore(), certificate.getNotAfter(), certificate.getNotAfter().before(ahora));
        if (fechaValidacion.after(Date.from(Instant.now().plusSeconds(300)))) {
            return new Result("firma_invalida", "La fecha de firma declarada es futura.", true).conFechas(List.of(fecha));
        }
        try {
            certificate.checkValidity(fechaValidacion);
        } catch (java.security.cert.CertificateExpiredException e) {
            return new Result("certificado_caducado", "El certificado estaba caducado en la fecha de validación de la firma.", true)
                .conFechas(List.of(fecha));
        } catch (java.security.cert.CertificateNotYetValidException e) {
            return new Result("certificado_aun_no_vigente", "El certificado aún no era válido en la fecha comprobada.", true)
                .conFechas(List.of(fecha));
        }
        return validateChain(certificate, evidencia, fechaValidacion, certificadosPdf).conFechas(List.of(fecha));
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
            selftestOficial(temp, p12, cert, keys);
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

    private static void selftestOficial(Path temp, Path p12, X509Certificate cert, KeyPair keys) throws Exception {
        RevocationResolver.selftestCrl(cert, keys.getPrivate());
        Path original = temp.resolve("oficio.pdf"), signed = temp.resolve("oficio-firmado.pdf");
        String profile = "solicitud-deposito:depositante:v1";
        try (PDDocument pdf = new PDDocument()) {
            PDPage page = new PDPage(); pdf.addPage(page);
            List<org.apache.pdfbox.pdmodel.interactive.annotation.PDAnnotation> markers = new ArrayList<>();
            for (String kind : List.of("bloques", "zonas")) {
                var link = new org.apache.pdfbox.pdmodel.interactive.annotation.PDAnnotationLink();
                link.setRectangle("bloques".equals(kind)
                    ? new org.apache.pdfbox.pdmodel.common.PDRectangle(50, 100, 480, 100)
                    : new org.apache.pdfbox.pdmodel.common.PDRectangle(58, 108, 464, 84));
                var action = new org.apache.pdfbox.pdmodel.interactive.action.PDActionURI();
                action.setURI("https://firmas.hubdigital.invalid/" + kind + "/" + profile.replace(':', '/'));
                link.setAction(action); markers.add(link);
            }
            page.setAnnotations(markers); pdf.save(original.toFile());
        }
        FirmadorPdfOficial.firmar(original, signed, p12, "temporal".toCharArray(), profile);
        if (!ContenidoOficialPdf.preparar(original)) throw new IllegalStateException("No se preparó el original oficial.");
        if (!verify(signed).cryptographicallyValid) throw new IllegalStateException("La firma oficial no es criptográficamente válida.");
        String dictamen = officialResult(signed, original);
        if (!dictamen.contains("\"aceptable\":true") || !dictamen.contains("\"pdf_seguro\":true")
            || !dictamen.contains("\"pdf_sha256\":\"" + sha256(signed) + "\"")
            || !dictamen.contains("\"original_sha256\":\"" + sha256(original) + "\"")) {
            throw new IllegalStateException("El dictamen no acredita los bytes inspeccionados y firmados.");
        }
        PdfSignatureWorker.validatePath(temp.toRealPath(), original.toString(), false, 16L * 1024 * 1024);
        boolean outsideRejected = false, overwriteRejected = false;
        try { PdfSignatureWorker.validatePath(temp.toRealPath(), temp.getParent().resolve("fuera.pdf").toString(), true, 0); }
        catch (IllegalArgumentException expected) { outsideRejected = true; }
        try { PdfSignatureWorker.validatePath(temp.toRealPath(), signed.toString(), true, 0); }
        catch (IllegalArgumentException expected) { overwriteRejected = true; }
        if (!outsideRejected || !overwriteRejected) throw new IllegalStateException("El servicio permite rutas ajenas o sobreescritura.");
        byte[] originalSinCambios = Files.readAllBytes(original);
        Path modificado = temp.resolve("original-cambiado.pdf");
        try (PDDocument cambiado = Loader.loadPDF(original.toFile())) {
            try (var contenido = new org.apache.pdfbox.pdmodel.PDPageContentStream(cambiado, cambiado.getPage(0),
                org.apache.pdfbox.pdmodel.PDPageContentStream.AppendMode.APPEND, true, true)) {
                contenido.beginText(); contenido.setFont(new org.apache.pdfbox.pdmodel.font.PDType1Font(
                    org.apache.pdfbox.pdmodel.font.Standard14Fonts.FontName.HELVETICA), 12);
                contenido.newLineAtOffset(50, 700); contenido.showText("Original distinto en la misma ruta"); contenido.endText();
            }
            cambiado.save(modificado.toFile());
        }
        Files.move(modificado, original, java.nio.file.StandardCopyOption.REPLACE_EXISTING);
        if (officialResult(signed, original).contains("\"aceptable\":true")) {
            throw new IllegalStateException("Se reutilizó una base preparada para un original distinto en la misma ruta.");
        }
        Files.write(original, originalSinCambios);
        if (!officialResult(signed, original).contains("\"aceptable\":true")) {
            throw new IllegalStateException("La base preparada no conserva la comparación completa del original.");
        }
        try (PDDocument base = Loader.loadPDF(original.toFile()); PDDocument result = Loader.loadPDF(signed.toFile())) {
            if (!ContenidoOficialPdf.coincide(base, result)) throw new IllegalStateException("La firma oficial no coincide con su zona y contenido.");
            var image = new org.apache.pdfbox.rendering.PDFRenderer(result).renderImageWithDPI(0, 220);
            int[] pixels = image.getRGB(0, 0, image.getWidth(), image.getHeight(), null, 0, image.getWidth());
            var bitmap = new com.google.zxing.BinaryBitmap(new com.google.zxing.common.HybridBinarizer(
                new com.google.zxing.RGBLuminanceSource(image.getWidth(), image.getHeight(), pixels)));
            String qr = new com.google.zxing.MultiFormatReader().decode(bitmap).getText();
            String digest = java.util.HexFormat.of().formatHex(MessageDigest.getInstance("SHA-256").digest(Files.readAllBytes(original)));
            if (!qr.contains(digest) || !qr.contains("Certificado SHA-256:")) throw new IllegalStateException("El QR no identifica el original y certificado.");
            // La firma sin su apariencia visible debe fallar el contrato oficial.
            var widget = result.getDocumentCatalog().getAcroForm().getFields().get(0).getWidgets().get(0);
            widget.getCOSObject().removeItem(COSName.AP);
            if (ContenidoOficialPdf.coincide(base, result)) throw new IllegalStateException("Se aceptó una firma sin apariencia visible.");
        }
        Path rejected = temp.resolve("no-debe-existir.pdf");
        boolean wrongPassword = false;
        try { FirmadorPdfOficial.firmar(original, rejected, p12, "incorrecta".toCharArray(), profile); }
        catch (java.io.IOException expected) { wrongPassword = true; }
        if (!wrongPassword || Files.exists(rejected)) throw new IllegalStateException("Se aceptó una contraseña incorrecta.");
        Path legacy = temp.resolve("identidad-legacy-qa.p12"), legacySigned = temp.resolve("legacy-firmado.pdf");
        guardarIdentidadLegacy(legacy, keys, cert, "temporal".toCharArray());
        FirmadorPdfOficial.firmar(original, legacySigned, legacy, "temporal".toCharArray(), profile);
        if (!officialResult(legacySigned, original).contains("\"aceptable\":true")) {
            throw new IllegalStateException("El contenedor antiguo no genera una firma oficial válida.");
        }
        boolean legacyWrongPassword = false;
        try { FirmadorPdfOficial.firmar(original, rejected, legacy, "incorrecta".toCharArray(), profile); }
        catch (java.io.IOException expected) { legacyWrongPassword = true; }
        if (!legacyWrongPassword || Files.exists(rejected)) throw new IllegalStateException("Se aceptó una clave incorrecta de un contenedor antiguo.");
        Path invalidP12 = temp.resolve("invalido.p12"); Files.writeString(invalidP12, "esto no es un certificado");
        boolean badCertificate = false;
        try { FirmadorPdfOficial.firmar(original, rejected, invalidP12, "temporal".toCharArray(), profile); }
        catch (java.io.IOException expected) { badCertificate = true; }
        if (!badCertificate || Files.exists(rejected)) throw new IllegalStateException("Se aceptó un certificado malformado.");
        X500Name name = new X500Name("C=EC,CN=Certificado caducado QA");
        X509Certificate expired = new JcaX509CertificateConverter().setProvider("BC").getCertificate(
            new JcaX509v3CertificateBuilder(name, BigInteger.valueOf(91),
                Date.from(Instant.now().minusSeconds(7200)), Date.from(Instant.now().minusSeconds(3600)), name, keys.getPublic())
                .build(new JcaContentSignerBuilder("SHA256withRSA").setProvider("BC").build(keys.getPrivate())));
        boolean expiredRejected = false;
        try { FirmadorPdfOficial.firmarConIdentidad(original, rejected, keys.getPrivate(), new Certificate[]{expired}, profile); }
        catch (java.security.cert.CertificateExpiredException expected) { expiredRejected = true; }
        if (!expiredRejected || Files.exists(rejected)) throw new IllegalStateException("Se aceptó un certificado caducado.");
        // El token y el P12 comparten la construcción de CMS y apariencia; hardware no simulado.
        Path tokenEquivalent = temp.resolve("identidad-firmada.pdf");
        FirmadorPdfOficial.firmarConIdentidad(original, tokenEquivalent, keys.getPrivate(), new Certificate[]{cert}, profile);
        if (!verify(tokenEquivalent).cryptographicallyValid) throw new IllegalStateException("Falló la firma con identidad suministrada.");
    }

    private record FechaFirma(Date fecha, String baseTemporal, Date validoDesde, Date validoHasta, boolean caducadoAhora) {}

    private record Result(String status, String reason, boolean cryptographicallyValid, String estadoRevocacion,
        PdfInfo pdfInfo, List<FechaFirma> fechasFirma) {
        Result(String status, String reason, boolean cryptographicallyValid, String estadoRevocacion, PdfInfo pdfInfo) {
            this(status, reason, cryptographicallyValid, estadoRevocacion, pdfInfo, List.of());
        }
        Result(String status, String reason, boolean cryptographicallyValid, String estadoRevocacion) {
            this(status, reason, cryptographicallyValid, estadoRevocacion, null);
        }
        Result(String status, String reason, boolean cryptographicallyValid) {
            this(status, reason, cryptographicallyValid,
                "certificado_revocado".equals(status) ? "REVOCADO" : "NO_COMPROBADO", null);
        }
        Result conFechas(List<FechaFirma> fechas) {
            return new Result(status, reason, cryptographicallyValid, estadoRevocacion, pdfInfo, List.copyOf(fechas));
        }
    }

    private static void emit(Result result) {
        System.out.println(json(result));
    }

    private static void emitOfficial(Path path, Path original) throws Exception {
        System.out.println(officialResult(path, original));
    }

    static String officialResult(Path path, Path original) throws Exception {
        String huella = sha256(path), huellaOriginal = sha256(original);
        // Al firmar una solicitud o acta nueva, una fecha declarada por el
        // cliente no permite usar un certificado que ya está caducado hoy.
        Result result = verify(path, false);
        boolean certificadoValido = "firmado".equals(result.status) || "firmado_sin_revocacion".equals(result.status);
        boolean formato = false, coincide = false, seguro = false;
        String certificado = "{}";
        try (PDDocument firmado = Loader.loadPDF(path.toFile()); PDDocument base = Loader.loadPDF(original.toFile())) {
            List<PDSignature> firmas = firmado.getSignatureDictionaries();
            if (firmas.size() == 1) {
                PDSignature firma = firmas.get(0);
                formato = "ETSI.CAdES.detached".equals(firma.getSubFilter());
                seguro = "seguro".equals(inspect(path).status) && "seguro".equals(inspect(original).status);
                if (formato && seguro) {
                    coincide = ContenidoOficialPdf.coincide(base, firmado, huellaOriginal);
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
        if (!huella.equals(sha256(path)) || !huellaOriginal.equals(sha256(original))) {
            throw new java.io.IOException("El PDF cambió durante su validación.");
        }
        boolean aceptable = certificadoValido && formato && coincide && seguro;
        String salida = json(result);
        return salida.substring(0, salida.length() - 1)
            + ",\"documento_completo_firmado\":" + result.cryptographicallyValid
            + ",\"contenido_oficial_coincide\":" + coincide
            + ",\"certificado_vigente\":" + certificadoValido
            + ",\"certificado_confiable\":" + certificadoValido
            + ",\"formato_firma_aceptado\":" + formato
            + ",\"pdf_seguro\":" + seguro + ",\"pdf_sha256\":\"" + huella + "\""
            + ",\"original_sha256\":\"" + huellaOriginal + "\""
            + ",\"aceptable\":" + aceptable + ",\"certificado\":" + certificado + "}";
    }

    static String sha256(Path path) throws Exception {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        try (InputStream input = Files.newInputStream(path)) {
            byte[] buffer = new byte[16_384];
            int read;
            while ((read = input.read(buffer)) != -1) digest.update(buffer, 0, read);
        }
        return java.util.HexFormat.of().formatHex(digest.digest());
    }

    private static String json(Result result) {
        boolean valido = "firmado".equals(result.status) || "firmado_sin_revocacion".equals(result.status);
        boolean indeterminado = "verificacion_no_disponible".equals(result.status)
            || "seguro".equals(result.status) || result.status.startsWith("crl_");
        String certificado = switch (result.status) {
            case "certificado_revocado" -> "REVOCADO";
            case "certificado_caducado" -> "CADUCADO";
            case "documento_modificado" -> "NO_COMPROBADO";
            case "certificado_aun_no_vigente" -> "AUN_NO_VIGENTE";
            case "certificado_no_confiable", "almacen_incompleto" -> "NO_CONFIABLE";
            default -> valido ? "VALIDO" : "NO_COMPROBADO";
        };
        return "{\"status\":\"" + escape(result.status) + "\",\"reason\":\""
            + escape(result.reason) + "\",\"cryptographically_valid\":" + result.cryptographicallyValid
            + ",\"estado_documento\":\"" + (valido ? "VALIDO" : (indeterminado ? "INDETERMINADO" : "INVALIDO"))
            + "\",\"estado_firma\":\"" + (indeterminado ? "NO_COMPROBADA"
                : ("sin_firma".equals(result.status) ? "SIN_FIRMA"
                    : (result.cryptographicallyValid ? "VALIDA" : "INVALIDA")))
            + "\",\"estado_certificado\":\"" + certificado
            + "\",\"estado_revocacion\":\"" + escape(result.estadoRevocacion) + "\""
            + ",\"validacion_temporal\":[" + result.fechasFirma.stream().map(fecha ->
                "{\"fecha_validacion\":\"" + fecha.fecha.toInstant()
                    + "\",\"base_temporal\":\"" + fecha.baseTemporal
                    + "\",\"sello_tiempo_confiable\":" + "SELLO_TIEMPO_CONFIABLE".equals(fecha.baseTemporal)
                    + ",\"certificado_caducado_actualmente\":" + fecha.caducadoAhora
                    + ",\"certificado_valido_desde\":\"" + fecha.validoDesde.toInstant()
                    + "\",\"certificado_valido_hasta\":\"" + fecha.validoHasta.toInstant() + "\"}"
            ).collect(java.util.stream.Collectors.joining(",")) + "]"
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
