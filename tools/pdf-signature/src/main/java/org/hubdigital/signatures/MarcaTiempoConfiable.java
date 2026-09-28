package org.hubdigital.signatures;

import java.io.ByteArrayInputStream;
import java.security.MessageDigest;
import java.security.cert.CertificateFactory;
import java.security.cert.TrustAnchor;
import java.security.cert.X509Certificate;
import java.time.Instant;
import java.util.ArrayList;
import java.util.Collection;
import java.util.Date;
import java.util.List;
import java.util.Set;
import org.bouncycastle.asn1.cms.Attribute;
import org.bouncycastle.asn1.cms.AttributeTable;
import org.bouncycastle.asn1.cms.ContentInfo;
import org.bouncycastle.asn1.pkcs.PKCSObjectIdentifiers;
import org.bouncycastle.cert.X509CertificateHolder;
import org.bouncycastle.cms.SignerInformation;
import org.bouncycastle.cms.CMSSignedData;
import org.bouncycastle.cms.jcajce.JcaSimpleSignerInfoVerifierBuilder;
import org.bouncycastle.tsp.TimeStampToken;
import org.apache.pdfbox.pdmodel.interactive.digitalsignature.PDSignature;

/** Solo acepta la fecha RFC 3161 si el token, su huella y la TSA son confiables. */
final class MarcaTiempoConfiable {
    private MarcaTiempoConfiable() {}

    static Date obtener(SignerInformation signer, Set<TrustAnchor> anchors,
        Collection<X509CertificateHolder> certificadosPdf) {
        AttributeTable unsigned = signer.getUnsignedAttributes();
        if (unsigned == null) return null;
        Attribute attribute = unsigned.get(PKCSObjectIdentifiers.id_aa_signatureTimeStampToken);
        if (attribute == null) return null;
        for (int i = 0; i < attribute.getAttrValues().size(); i++) {
            try {
                TimeStampToken token = new TimeStampToken(ContentInfo.getInstance(attribute.getAttrValues().getObjectAt(i)));
                Date fecha = validar(token, signer.getSignature(), anchors, certificadosPdf);
                if (fecha != null) return fecha;
            } catch (Exception ignored) { /* sello sin prueba criptográfica suficiente */ }
        }
        return null;
    }

    static Date obtenerDocumento(PDSignature signature, byte[] bytes, Set<TrustAnchor> anchors,
        Collection<X509CertificateHolder> certificadosPdf) {
        try {
            TimeStampToken token = new TimeStampToken(new CMSSignedData(
                new ByteArrayInputStream(signature.getContents(bytes))));
            return validar(token, signature.getSignedContent(bytes), anchors, certificadosPdf);
        } catch (Exception ignored) {
            return null;
        }
    }

    private static Date validar(TimeStampToken token, byte[] contenido, Set<TrustAnchor> anchors,
        Collection<X509CertificateHolder> certificadosPdf) throws Exception {
        String oid = token.getTimeStampInfo().getMessageImprintAlgOID().getId();
        String hash = switch (oid) {
            case "1.3.14.3.2.26" -> "SHA-1";
            case "2.16.840.1.101.3.4.2.1" -> "SHA-256";
            case "2.16.840.1.101.3.4.2.2" -> "SHA-384";
            case "2.16.840.1.101.3.4.2.3" -> "SHA-512";
            default -> null;
        };
        if (hash == null || !MessageDigest.isEqual(token.getTimeStampInfo().getMessageImprintDigest(),
            MessageDigest.getInstance(hash).digest(contenido))) return null;
        Date fecha = token.getTimeStampInfo().getGenTime();
        if (fecha.after(Date.from(Instant.now().plusSeconds(300)))) return null;
        var holders = token.getCertificates().getMatches(token.getSID());
        if (holders.size() != 1) return null;
        X509Certificate tsa = convertir((X509CertificateHolder) holders.iterator().next());
        List<String> extended = tsa.getExtendedKeyUsage();
        if (extended == null || !extended.contains("1.3.6.1.5.5.7.3.8")) return null;
        tsa.checkValidity(fecha);
        token.validate(new JcaSimpleSignerInfoVerifierBuilder().build(tsa));
        List<X509CertificateHolder> disponibles = new ArrayList<>(certificadosPdf);
        disponibles.addAll(token.getCertificates().getMatches(null));
        PdfSignatureCli.construirRuta(tsa, disponibles, anchors, fecha);
        return fecha;
    }

    private static X509Certificate convertir(X509CertificateHolder holder) throws Exception {
        return (X509Certificate) CertificateFactory.getInstance("X.509")
            .generateCertificate(new ByteArrayInputStream(holder.getEncoded()));
    }
}
