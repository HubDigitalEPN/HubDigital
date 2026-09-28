package org.hubdigital.signatures;

import java.io.ByteArrayInputStream;
import java.io.InputStream;
import java.net.InetAddress;
import java.net.HttpURLConnection;
import java.net.URI;
import java.security.cert.CertPath;
import java.security.cert.CertPathValidator;
import java.security.cert.CertPathValidatorException;
import java.security.cert.CertificateFactory;
import java.security.cert.PKIXParameters;
import java.security.cert.PKIXRevocationChecker;
import java.security.cert.TrustAnchor;
import java.security.cert.X509CRL;
import java.security.cert.X509Certificate;
import java.security.MessageDigest;
import java.time.Instant;
import java.util.ArrayList;
import java.util.Date;
import java.util.EnumSet;
import java.util.HashSet;
import java.util.LinkedHashSet;
import java.util.HexFormat;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;
import org.apache.pdfbox.cos.COSArray;
import org.apache.pdfbox.cos.COSBase;
import org.apache.pdfbox.cos.COSDictionary;
import org.apache.pdfbox.cos.COSName;
import org.apache.pdfbox.cos.COSObject;
import org.apache.pdfbox.cos.COSStream;
import org.apache.pdfbox.pdmodel.PDDocument;
import org.bouncycastle.asn1.ASN1OctetString;
import org.bouncycastle.asn1.ASN1Primitive;
import org.bouncycastle.asn1.x509.AccessDescription;
import org.bouncycastle.asn1.x509.AuthorityInformationAccess;
import org.bouncycastle.asn1.x509.CRLDistPoint;
import org.bouncycastle.asn1.x509.DistributionPoint;
import org.bouncycastle.asn1.x509.DistributionPointName;
import org.bouncycastle.asn1.x509.Extension;
import org.bouncycastle.asn1.x509.GeneralName;
import org.bouncycastle.asn1.x509.GeneralNames;
import org.bouncycastle.cert.ocsp.BasicOCSPResp;
import org.bouncycastle.cert.ocsp.OCSPResp;
import org.bouncycastle.cert.ocsp.SingleResp;

/** Resuelve revocación con evidencia del PDF, OCSP y finalmente CRL efímeras. */
final class RevocationResolver {
    enum Estado { NO_REVOCADO, REVOCADO, DESCONOCIDO, NO_DISPONIBLE, NO_COMPROBADO }
    record Resultado(Estado estado, String fuente, Instant validoHasta) {
        Resultado(Estado estado, String fuente) { this(estado, fuente, null); }
    }
    record Evidencia(List<byte[]> ocsp, List<byte[]> crl) {}
    private record Entrada(Resultado resultado, Instant validoHasta) {}

    private static final ConcurrentHashMap<String, CompletableFuture<Entrada>> CACHE = new ConcurrentHashMap<>();
    private static final ExecutorService CONSULTAS = Executors.newFixedThreadPool(4, task -> {
        Thread thread = new Thread(task, "hubdigital-revocacion");
        thread.setDaemon(true);
        return thread;
    });
    private static final int LIMITE_CACHE = 256;
    private static final int LIMITE_OCSP = 1_000_000;
    private static final int LIMITE_CRL = 10_000_000;

    private RevocationResolver() {}

    static Evidencia extraer(PDDocument pdf) {
        List<byte[]> ocsp = new ArrayList<>();
        List<byte[]> crl = new ArrayList<>();
        COSBase dssBase = pdf.getDocumentCatalog().getCOSObject().getDictionaryObject(COSName.getPDFName("DSS"));
        if (dssBase instanceof COSDictionary dss) {
            agregar(dss.getDictionaryObject(COSName.getPDFName("OCSPs")), ocsp, LIMITE_OCSP);
            agregar(dss.getDictionaryObject(COSName.getPDFName("CRLs")), crl, LIMITE_CRL);
            COSBase vriBase = dss.getDictionaryObject(COSName.getPDFName("VRI"));
            if (vriBase instanceof COSDictionary vri) {
                for (COSName key : vri.keySet()) {
                    COSBase entry = vri.getDictionaryObject(key);
                    if (entry instanceof COSDictionary dictionary) {
                        agregar(dictionary.getDictionaryObject(COSName.getPDFName("OCSP")), ocsp, LIMITE_OCSP);
                        agregar(dictionary.getDictionaryObject(COSName.getPDFName("OCSPs")), ocsp, LIMITE_OCSP);
                        agregar(dictionary.getDictionaryObject(COSName.getPDFName("CRL")), crl, LIMITE_CRL);
                        agregar(dictionary.getDictionaryObject(COSName.getPDFName("CRLs")), crl, LIMITE_CRL);
                    }
                }
            }
        }
        return new Evidencia(ocsp, crl);
    }

    private static void agregar(COSBase base, List<byte[]> salida, int limite) {
        if (base instanceof COSObject object) base = object.getObject();
        if (base instanceof COSArray array) {
            for (int i = 0; i < array.size() && salida.size() < 32; i++) agregar(array.getObject(i), salida, limite);
        } else if (base instanceof COSStream stream && salida.size() < 32) {
            try (InputStream in = stream.createInputStream()) {
                byte[] bytes = in.readNBytes(limite + 1);
                if (bytes.length <= limite) salida.add(bytes);
            } catch (Exception ignored) { /* evidencia inutilizable: se prueban otras fuentes */ }
        }
    }

    static Resultado resolver(X509Certificate signer, X509Certificate issuer, CertPath path,
        Set<TrustAnchor> anchors, Evidencia evidencia, URI ocspExcepcional, URI crlExcepcional,
        Date fechaValidacion, int timeoutSeconds) {
        // Dos firmas del mismo certificado pueden tener fechas probadas diferentes.
        // Una revocación posterior al primer sello no debe reutilizar su dictamen.
        String clave;
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            digest.update(signer.getEncoded());
            digest.update(issuer.getEncoded());
            for (byte[] ocsp : evidencia.ocsp()) digest.update(ocsp);
            for (byte[] crl : evidencia.crl()) digest.update(crl);
            clave = HexFormat.of().formatHex(digest.digest()) + "|" + fechaValidacion.getTime();
        } catch (Exception e) {
            return new Resultado(Estado.NO_DISPONIBLE, "No se pudo identificar la evidencia de revocación.");
        }
        if (CACHE.size() >= LIMITE_CACHE) CACHE.entrySet().removeIf(e -> {
            if (!e.getValue().isDone()) return false;
            Entrada entrada = e.getValue().getNow(null);
            return entrada == null || !entrada.validoHasta().isAfter(Instant.now());
        });
        CompletableFuture<Entrada> anterior = CACHE.get(clave);
        if (anterior != null) {
            Entrada entrada = anterior.join();
            if (entrada.validoHasta().isAfter(Instant.now())) return entrada.resultado();
            CACHE.remove(clave, anterior);
        }
        CompletableFuture<Entrada> nueva = new CompletableFuture<>();
        CompletableFuture<Entrada> existente = CACHE.size() >= LIMITE_CACHE
            ? null : CACHE.putIfAbsent(clave, nueva);
        if (existente != null && !existente.join().validoHasta().isBefore(Instant.now())) {
            return existente.join().resultado();
        }
        if (existente != null) CACHE.replace(clave, existente, nueva);
        try {
            Future<Resultado> consulta = CONSULTAS.submit(() -> consultar(signer, issuer, path,
                anchors, evidencia, ocspExcepcional, crlExcepcional, fechaValidacion, timeoutSeconds));
            Resultado resultado;
            try {
                // Permitir el intento OCSP y sus alternativas CRL dentro de un
                // presupuesto acotado, incluso si la primera fuente no responde.
                resultado = consulta.get(Math.min(12, Math.max(4, timeoutSeconds * 4L)), TimeUnit.SECONDS);
            } catch (Exception e) {
                consulta.cancel(true);
                resultado = new Resultado(Estado.NO_DISPONIBLE, "La consulta de revocación excedió el tiempo configurado.");
            }
            Instant ahora = Instant.now();
            // La JVM se reutiliza: un dictamen nunca sobrevive a la evidencia
            // firmada que lo sustenta. OCSP sin nextUpdate accesible se consulta otra vez.
            Instant hasta = resultado.validoHasta() == null ? ahora : resultado.validoHasta();
            if (hasta.isAfter(ahora.plusSeconds(300))) hasta = ahora.plusSeconds(300);
            nueva.complete(new Entrada(resultado, hasta));
            return resultado;
        } catch (Exception e) {
            Resultado resultado = new Resultado(Estado.NO_DISPONIBLE, "No fue posible consultar la revocación.");
            nueva.complete(new Entrada(resultado, Instant.now().plusSeconds(30)));
            return resultado;
        }
    }

    private static Resultado consultar(X509Certificate signer, X509Certificate issuer, CertPath path,
        Set<TrustAnchor> anchors, Evidencia evidencia, URI ocspExcepcional, URI crlExcepcional,
        Date fechaValidacion, int timeoutSeconds) {
        for (byte[] encoded : evidencia.ocsp()) {
            if (!correspondeOcsp(encoded, signer)) continue;
            Resultado resultado = comprobarOcsp(signer, path, anchors, encoded, null, fechaValidacion);
            if (resultado.estado() == Estado.NO_REVOCADO || resultado.estado() == Estado.REVOCADO) return resultado;
        }
        for (byte[] encoded : evidencia.crl()) {
            Resultado resultado = comprobarCrl(encoded, signer, issuer, "CRL incorporada al PDF", fechaValidacion);
            if (resultado != null) return resultado;
        }

        boolean intento = false;
        for (URI ocsp : fuentesExternas(urlsCertificado(signer, true), ocspExcepcional)) {
            if (!fuentePublica(ocsp)) continue;
            intento = true;
            Resultado resultado = comprobarOcsp(signer, path, anchors, null, ocsp, fechaValidacion);
            if (resultado.estado() == Estado.NO_REVOCADO || resultado.estado() == Estado.REVOCADO) return resultado;
        }
        for (URI crl : fuentesExternas(urlsCertificado(signer, false), crlExcepcional)) {
            if (!fuentePublica(crl)) continue;
            intento = true;
            try {
                HttpURLConnection connection = (HttpURLConnection) crl.toURL().openConnection();
                connection.setInstanceFollowRedirects(false);
                connection.setConnectTimeout(timeoutSeconds * 1000);
                connection.setReadTimeout(timeoutSeconds * 1000);
                try (InputStream in = connection.getInputStream()) {
                    byte[] encoded = in.readNBytes(LIMITE_CRL + 1);
                    if (encoded.length <= LIMITE_CRL) {
                        Resultado resultado = comprobarCrl(encoded, signer, issuer, "CRL del certificado", fechaValidacion);
                        if (resultado != null) return resultado;
                    }
                }
            } catch (Exception ignored) { /* estado separado de la validez criptográfica */ }
        }
        return new Resultado(intento ? Estado.NO_DISPONIBLE : Estado.NO_COMPROBADO,
            intento ? "No fue posible consultar OCSP ni CRL." : "El certificado no publica una fuente de revocación utilizable.");
    }

    private static List<URI> fuentesExternas(List<URI> delCertificado, URI excepcional) {
        List<URI> fuentes = new ArrayList<>(delCertificado);
        if (excepcional != null && !fuentes.contains(excepcional)) fuentes.add(excepcional);
        return fuentes;
    }

    private static Resultado comprobarOcsp(X509Certificate signer, CertPath path, Set<TrustAnchor> anchors,
        byte[] embedded, URI responder, Date fechaValidacion) {
        try {
            PKIXParameters params = new PKIXParameters(anchors);
            params.setRevocationEnabled(false);
            params.setDate(fechaValidacion);
            PKIXRevocationChecker checker = (PKIXRevocationChecker) CertPathValidator.getInstance("PKIX").getRevocationChecker();
            checker.setOptions(EnumSet.of(PKIXRevocationChecker.Option.ONLY_END_ENTITY,
                PKIXRevocationChecker.Option.NO_FALLBACK));
            if (embedded != null) checker.setOcspResponses(Map.of(signer, embedded));
            if (responder != null) checker.setOcspResponder(responder);
            params.addCertPathChecker(checker);
            CertPathValidator.getInstance("PKIX").validate(path, params);
            return new Resultado(Estado.NO_REVOCADO, embedded != null ? "OCSP incorporado al PDF" : "OCSP del certificado");
        } catch (CertPathValidatorException e) {
            if (e.getReason() == CertPathValidatorException.BasicReason.REVOKED)
                return new Resultado(Estado.REVOCADO, "OCSP confirmó la revocación.");
            return new Resultado(Estado.NO_DISPONIBLE, "OCSP no disponible.");
        } catch (Exception e) {
            return new Resultado(Estado.NO_DISPONIBLE, "OCSP no disponible.");
        }
    }

    private static boolean correspondeOcsp(byte[] encoded, X509Certificate signer) {
        try {
            OCSPResp response = new OCSPResp(encoded);
            if (!(response.getResponseObject() instanceof BasicOCSPResp basic)) return false;
            for (SingleResp single : basic.getResponses()) {
                if (single.getCertID().getSerialNumber().equals(signer.getSerialNumber())) return true;
            }
        } catch (Exception ignored) { /* se descarta evidencia ilegible */ }
        return false;
    }

    private static Resultado comprobarCrl(byte[] encoded, X509Certificate signer, X509Certificate issuer,
        String fuente, Date fechaValidacion) {
        try (InputStream in = new ByteArrayInputStream(encoded)) {
            X509CRL crl = (X509CRL) CertificateFactory.getInstance("X.509").generateCRL(in);
            Date ahora = new Date();
            if (!crl.getIssuerX500Principal().equals(issuer.getSubjectX500Principal())
                || crl.getThisUpdate() == null || crl.getThisUpdate().after(ahora)
                || crl.getNextUpdate() == null || !crl.getNextUpdate().after(ahora)) return null;
            crl.verify(issuer.getPublicKey());
            var revocado = crl.getRevokedCertificate(signer);
            boolean revocadoEnFecha = revocado != null && !revocado.getRevocationDate().after(fechaValidacion);
            return new Resultado(revocadoEnFecha ? Estado.REVOCADO : Estado.NO_REVOCADO,
                fuente, crl.getNextUpdate().toInstant());
        } catch (Exception e) {
            return null;
        }
    }

    private static List<URI> urlsCertificado(X509Certificate cert, boolean ocsp) {
        Set<URI> fuentes = new LinkedHashSet<>();
        try {
            byte[] wrapped = cert.getExtensionValue(ocsp
                ? Extension.authorityInfoAccess.getId() : Extension.cRLDistributionPoints.getId());
            if (wrapped == null) return List.of();
            ASN1Primitive value = ASN1Primitive.fromByteArray(ASN1OctetString.getInstance(wrapped).getOctets());
            if (ocsp) {
                for (AccessDescription access : AuthorityInformationAccess.getInstance(value).getAccessDescriptions()) {
                    if (AccessDescription.id_ad_ocsp.equals(access.getAccessMethod())
                        && access.getAccessLocation().getTagNo() == GeneralName.uniformResourceIdentifier)
                        agregarUrl(fuentes, access.getAccessLocation().getName().toString());
                }
            } else {
                for (DistributionPoint point : CRLDistPoint.getInstance(value).getDistributionPoints()) {
                    DistributionPointName name = point.getDistributionPoint();
                    if (name == null || name.getType() != DistributionPointName.FULL_NAME) continue;
                    for (GeneralName location : GeneralNames.getInstance(name.getName()).getNames()) {
                        if (location.getTagNo() == GeneralName.uniformResourceIdentifier)
                            agregarUrl(fuentes, location.getName().toString());
                    }
                }
            }
        } catch (Exception ignored) { /* extensión ausente o mal formada */ }
        return List.copyOf(fuentes);
    }

    private static void agregarUrl(Set<URI> fuentes, String valor) {
        if (fuentes.size() >= 8) return;
        try {
            // Algunas CA publican espacios en el nombre del emisor o una sola
            // barra tras http:. La CRL sigue exigiendo firma del emisor y vigencia.
            String fuente = valor.trim().replace(" ", "%20");
            if (fuente.matches("(?i)^https?:/[^/].*")) {
                int inicioHost = fuente.indexOf(":/") + 2;
                fuente = fuente.substring(0, inicioHost) + "/" + fuente.substring(inicioHost);
            }
            URI uri = URI.create(fuente);
            if (("http".equalsIgnoreCase(uri.getScheme()) || "https".equalsIgnoreCase(uri.getScheme()))
                && uri.getHost() != null && uri.getUserInfo() == null) fuentes.add(uri);
        } catch (IllegalArgumentException ignored) { /* una dirección dañada no oculta las siguientes */ }
    }

    private static boolean fuentePublica(URI uri) {
        if (!("http".equalsIgnoreCase(uri.getScheme()) || "https".equalsIgnoreCase(uri.getScheme()))
            || uri.getUserInfo() != null || uri.getHost() == null) return false;
        try {
            for (InetAddress address : InetAddress.getAllByName(uri.getHost())) {
                if (address.isAnyLocalAddress() || address.isLoopbackAddress() || address.isLinkLocalAddress()
                    || address.isSiteLocalAddress() || address.isMulticastAddress()) return false;
            }
            return true;
        } catch (Exception e) {
            return false;
        }
    }
}
