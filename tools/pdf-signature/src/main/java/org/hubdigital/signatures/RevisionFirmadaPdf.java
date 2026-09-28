package org.hubdigital.signatures;

import java.io.InputStream;
import java.security.MessageDigest;
import java.util.Arrays;
import java.util.Collections;
import java.util.HashSet;
import java.util.IdentityHashMap;
import java.util.Map;
import java.util.Set;
import org.apache.pdfbox.Loader;
import org.apache.pdfbox.cos.COSArray;
import org.apache.pdfbox.cos.COSBase;
import org.apache.pdfbox.cos.COSDictionary;
import org.apache.pdfbox.cos.COSName;
import org.apache.pdfbox.cos.COSObject;
import org.apache.pdfbox.cos.COSStream;
import org.apache.pdfbox.cos.COSString;
import org.apache.pdfbox.pdmodel.PDDocument;

/** Comprueba todo el grafo firmado; solo admite metadatos y evidencia LTV posteriores. */
final class RevisionFirmadaPdf {
    private RevisionFirmadaPdf() {}

    static boolean coincide(byte[] bytes, int fin, PDDocument actual) throws Exception {
        if (fin == bytes.length) return true;
        String colaFirmada = new String(bytes, Math.max(0, fin - 1024), Math.min(fin, 1024),
            java.nio.charset.StandardCharsets.ISO_8859_1);
        if (!colaFirmada.matches("(?s).*%%EOF[\\x00\\x09\\x0a\\x0c\\x0d\\x20]*")) return false;
        try (PDDocument firmada = Loader.loadPDF(Arrays.copyOf(bytes, fin))) {
            return coincide(firmada.getDocumentCatalog().getCOSObject(),
                actual.getDocumentCatalog().getCOSObject(), true, false, new IdentityHashMap<>());
        }
    }

    private static boolean coincide(COSBase anterior, COSBase actual, boolean catalogo, boolean formulario,
        Map<COSBase, Set<COSBase>> visitados) throws Exception {
        if (anterior instanceof COSObject object) anterior = object.getObject();
        if (actual instanceof COSObject object) actual = object.getObject();
        if (anterior == null || actual == null) return anterior == actual;
        Set<COSBase> pares = visitados.computeIfAbsent(anterior,
            key -> Collections.newSetFromMap(new IdentityHashMap<>()));
        if (!pares.add(actual)) return true;
        if (anterior instanceof COSDictionary a && actual instanceof COSDictionary b) {
            boolean stream = a instanceof COSStream;
            if (stream != (b instanceof COSStream)) return false;
            if (stream && !MessageDigest.isEqual(huella((COSStream) a), huella((COSStream) b))) return false;
            // Algunos firmadores añaden fuentes predeterminadas al guardar evidencia LTV.
            // Solo son inocuas si todos los campos son firmas con apariencia y recursos propios.
            boolean recursosPredeterminadosInocuos = formulario
                && soloFirmasConAparienciaPropia(a) && soloFirmasConAparienciaPropia(b);
            Set<COSName> claves = new HashSet<>(a.keySet());
            claves.addAll(b.keySet());
            for (COSName clave : claves) {
                String nombre = clave.getName();
                if (catalogo && Set.of("Metadata", "DSS", "Extensions").contains(nombre)) continue;
                if (recursosPredeterminadosInocuos && Set.of("DA", "DR").contains(nombre)) continue;
                if (stream && Set.of("Length", "Filter", "DecodeParms").contains(nombre)) continue;
                if (!coincide(a.getItem(clave), b.getItem(clave), false,
                    catalogo && "AcroForm".equals(nombre), visitados)) return false;
            }
            return true;
        }
        if (anterior instanceof COSArray a && actual instanceof COSArray b) {
            if (a.size() != b.size()) return false;
            for (int i = 0; i < a.size(); i++) {
                if (!coincide(a.get(i), b.get(i), false, false, visitados)) return false;
            }
            return true;
        }
        if (anterior instanceof COSString a && actual instanceof COSString b) {
            return Arrays.equals(a.getBytes(), b.getBytes());
        }
        return anterior.equals(actual);
    }

    private static boolean soloFirmasConAparienciaPropia(COSDictionary formulario) {
        if (formulario.getBoolean(COSName.NEED_APPEARANCES, false) || formulario.containsKey(COSName.XFA)) return false;
        COSBase campos = formulario.getDictionaryObject(COSName.FIELDS);
        if (!(campos instanceof COSArray lista) || lista.size() == 0) return false;
        Set<COSBase> visitados = Collections.newSetFromMap(new IdentityHashMap<>());
        for (int i = 0; i < lista.size(); i++) {
            if (!(lista.getObject(i) instanceof COSDictionary campo)
                || !firmaConAparienciaPropia(campo, false, visitados)) return false;
        }
        return true;
    }

    private static boolean firmaConAparienciaPropia(COSDictionary campo, boolean firmaHeredada,
        Set<COSBase> visitados) {
        if (!visitados.add(campo)) return false;
        String tipo = campo.getNameAsString(COSName.FT);
        boolean firma = tipo == null ? firmaHeredada : "Sig".equals(tipo);
        if (!firma) return false;
        COSBase hijos = campo.getDictionaryObject(COSName.KIDS);
        if (hijos instanceof COSArray lista && lista.size() > 0) {
            for (int i = 0; i < lista.size(); i++) {
                if (!(lista.getObject(i) instanceof COSDictionary hijo)
                    || !firmaConAparienciaPropia(hijo, firma, visitados)) return false;
            }
            return true;
        }
        COSBase apariencia = campo.getDictionaryObject(COSName.AP);
        return "Widget".equals(campo.getNameAsString(COSName.SUBTYPE))
            && apariencia instanceof COSDictionary ap
            && ap.getDictionaryObject(COSName.N) instanceof COSStream normal
            && normal.getDictionaryObject(COSName.RESOURCES) instanceof COSDictionary;
    }

    private static byte[] huella(COSStream stream) throws Exception {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        try (InputStream in = stream.createInputStream()) {
            byte[] buffer = new byte[8192];
            long total = 0;
            for (int leidos; (leidos = in.read(buffer)) != -1;) {
                total += leidos;
                if (total > 64L * 1024 * 1024) throw new java.io.IOException("Stream PDF demasiado grande.");
                digest.update(buffer, 0, leidos);
            }
        }
        return digest.digest();
    }
}
