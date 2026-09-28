package org.hubdigital.signatures;

import java.awt.image.BufferedImage;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.Collections;
import java.util.IdentityHashMap;
import org.apache.pdfbox.cos.COSArray;
import org.apache.pdfbox.cos.COSBase;
import org.apache.pdfbox.cos.COSDictionary;
import org.apache.pdfbox.cos.COSName;
import org.apache.pdfbox.cos.COSNumber;
import org.apache.pdfbox.cos.COSObject;
import org.apache.pdfbox.cos.COSStream;
import org.apache.pdfbox.pdmodel.PDDocument;
import org.apache.pdfbox.pdmodel.PDPage;
import org.apache.pdfbox.pdmodel.interactive.annotation.PDAnnotation;
import org.apache.pdfbox.rendering.PDFRenderer;
import org.apache.pdfbox.text.PDFTextStripper;

/** Compara la plantilla completa y permite un único sello dentro de su bloque nominal. */
final class ContenidoOficialPdf {
    private record Anotacion(int pagina, PDAnnotation valor, double[] rect) {}
    private static final String BASE = "https://firmas.hubdigital.invalid/";
    private ContenidoOficialPdf() {}

    static boolean coincide(PDDocument original, PDDocument firmado) throws Exception {
        int paginas = original.getNumberOfPages();
        int maxPaginas = Integer.getInteger("hubdigital.pdf.max_pages", 40);
        int maxPoints = Integer.getInteger("hubdigital.pdf.max_page_points", 1440);
        int dpi = Integer.getInteger("hubdigital.pdf.render_dpi", 110);
        long maxPixeles = Long.getLong("hubdigital.pdf.max_render_pixels", 100_000_000L);
        if (original.isEncrypted() || firmado.isEncrypted() || paginas < 1 || paginas > maxPaginas
            || paginas != firmado.getNumberOfPages() || dpi < 36 || dpi > 300) return false;
        if (!accionesPermitidas(original.getDocumentCatalog().getCOSObject(), false,
                Collections.newSetFromMap(new IdentityHashMap<>()))
            || !accionesPermitidas(firmado.getDocumentCatalog().getCOSObject(), true,
                Collections.newSetFromMap(new IdentityHashMap<>()))) return false;
        List<Anotacion> marcadores = anotaciones(original);
        List<Anotacion> widgets = anotaciones(firmado);
        if (marcadores.size() != 2 || widgets.size() != 1) return false;
        Map<String, Anotacion> enlaces = new HashMap<>();
        for (Anotacion marcador : marcadores) {
            COSDictionary dict = marcador.valor.getCOSObject();
            COSBase accion = dict.getDictionaryObject(COSName.A);
            if (!"Link".equals(dict.getNameAsString(COSName.SUBTYPE)) || dict.containsKey(COSName.AP)
                || dict.containsKey(COSName.DEST) || !(accion instanceof COSDictionary a)
                || !"URI".equals(a.getNameAsString(COSName.S))) return false;
            String uri = a.getString(COSName.URI);
            if (uri == null || enlaces.put(uri, marcador) != null) return false;
        }
        Anotacion widget = widgets.get(0);
        boolean zonaValida = false;
        for (String perfil : List.of("solicitud-deposito/depositante/v1", "acta-recepcion/curador/v1")) {
            Anotacion bloque = enlaces.get(BASE + "bloques/" + perfil);
            Anotacion zona = enlaces.get(BASE + "zonas/" + perfil);
            if (bloque != null && zona != null) zonaValida = widgetEnZona(bloque, zona, widget, original);
        }
        if (!zonaValida || !formulario(original, null) || !formulario(firmado, widget.valor.getCOSObject())) return false;
        double pixeles = 0;
        for (int i = 0; i < paginas; i++) {
            PDPage a = original.getPage(i), b = firmado.getPage(i);
            if (a.getRotation() != 0 || b.getRotation() != 0 || a.getUserUnit() != 1 || b.getUserUnit() != 1
                || !iguales(a.getMediaBox().getCOSArray(), b.getMediaBox().getCOSArray())
                || !iguales(a.getCropBox().getCOSArray(), b.getCropBox().getCOSArray())) return false;
            double w = a.getMediaBox().getWidth(), h = a.getMediaBox().getHeight();
            if (w <= 0 || h <= 0 || w > maxPoints || h > maxPoints) return false;
            pixeles += w * h * dpi * dpi / (72.0 * 72.0);
        }
        if (!Double.isFinite(pixeles) || pixeles > maxPixeles) return false;
        PDFTextStripper texto = new PDFTextStripper();
        texto.setSortByPosition(true);
        if (!texto.getText(original).equals(texto.getText(firmado))) return false;
        PDFRenderer renderOriginal = new PDFRenderer(original);
        PDFRenderer renderBase = new PDFRenderer(firmado);
        PDFRenderer renderVisible = new PDFRenderer(firmado);
        renderOriginal.setAnnotationsFilter(annotation -> false);
        renderBase.setAnnotationsFilter(annotation -> false);
        int paginasConSello = 0;
        for (int i = 0; i < paginas; i++) {
            BufferedImage a = renderOriginal.renderImageWithDPI(i, dpi);
            BufferedImage b = renderBase.renderImageWithDPI(i, dpi);
            if (!iguales(a, b)) return false;
            if (!iguales(b, renderVisible.renderImageWithDPI(i, dpi))) paginasConSello++;
        }
        return paginasConSello == 1;
    }

    private static boolean accionesPermitidas(COSBase base, boolean firmado, Set<COSBase> visitados) {
        if (base instanceof COSObject object) base = object.getObject();
        if (base == null || !visitados.add(base)) return true;
        if (base instanceof COSDictionary dict) {
            Set<String> prohibidas = Set.of("JavaScript", "JS", "Launch", "EmbeddedFiles", "RichMedia",
                "XFA", "OpenAction", "AA", "Next", "EF", "AF", "OCProperties");
            for (COSName key : dict.keySet()) {
                if (prohibidas.contains(key.getName())
                    || ("A".equals(key.getName()) && (firmado || !"Link".equals(dict.getNameAsString(COSName.SUBTYPE))))) return false;
                if (!accionesPermitidas(dict.getItem(key), firmado, visitados)) return false;
            }
        } else if (base instanceof COSArray array) {
            for (int i = 0; i < array.size(); i++) if (!accionesPermitidas(array.get(i), firmado, visitados)) return false;
        }
        return true;
    }

    private static List<Anotacion> anotaciones(PDDocument pdf) throws Exception {
        List<Anotacion> result = new ArrayList<>();
        for (int i = 0; i < pdf.getNumberOfPages(); i++) {
            PDPage pagina = pdf.getPage(i);
            for (PDAnnotation anotacion : pagina.getAnnotations()) {
                COSDictionary dict = anotacion.getCOSObject();
                COSBase propia = dict.getDictionaryObject(COSName.P);
                if (propia != null && propia != pagina.getCOSObject()) throw new java.io.IOException("Anotación ajena a su página.");
                result.add(new Anotacion(i, anotacion, numeros(dict.getDictionaryObject(COSName.RECT), 4)));
            }
        }
        return result;
    }

    private static boolean formulario(PDDocument pdf, COSDictionary widget) {
        COSBase base = pdf.getDocumentCatalog().getCOSObject().getDictionaryObject(COSName.ACRO_FORM);
        if (base == null) return widget == null;
        if (!(base instanceof COSDictionary formulario) || formulario.getBoolean(COSName.NEED_APPEARANCES, false)) return false;
        COSBase campos = formulario.getDictionaryObject(COSName.FIELDS);
        if (!(campos instanceof COSArray array)) return false;
        return widget == null ? array.size() == 0 : array.size() == 1 && array.getObject(0) == widget;
    }

    private static boolean widgetEnZona(Anotacion bloque, Anotacion zona, Anotacion widget, PDDocument original) throws Exception {
        COSDictionary dict = widget.valor.getCOSObject();
        if (bloque.pagina != zona.pagina || widget.pagina != zona.pagina || !iguales(widget.rect, zona.rect)
            || !"Widget".equals(dict.getNameAsString(COSName.SUBTYPE)) || !"Sig".equals(dict.getNameAsString(COSName.FT))
            || !"Signature1".equals(dict.getString(COSName.T)) || dict.getInt(COSName.F) != 4
            || dict.getDictionaryObject(COSName.P) == null) return false;
        Set<String> permitidas = Set.of("Type", "Subtype", "FT", "Rect", "V", "T", "F", "P", "AP");
        if (dict.keySet().stream().anyMatch(key -> !permitidas.contains(key.getName()))) return false;
        double[] b = bloque.rect, z = zona.rect;
        var pagina = original.getPage(zona.pagina).getCropBox();
        if (b[0] < pagina.getLowerLeftX() + 24 || b[1] < pagina.getLowerLeftY() + 42
            || b[2] > pagina.getUpperRightX() - 24 || b[3] > pagina.getUpperRightY() - 24
            || b[2] - b[0] < 220 || b[3] - b[1] < 54 || z[2] - z[0] < 200 || z[3] - z[1] < 45
            || z[0] < b[0] + 2 || z[1] < b[1] + 2 || z[2] > b[2] - 2 || z[3] > b[3] - 2) return false;
        COSBase valor = dict.getDictionaryObject(COSName.V);
        COSBase ap = dict.getDictionaryObject(COSName.AP);
        if (!(valor instanceof COSDictionary firma) || !"Sig".equals(firma.getNameAsString(COSName.TYPE))
            || !"ETSI.CAdES.detached".equals(firma.getNameAsString(COSName.SUB_FILTER))
            || !(ap instanceof COSDictionary apariencia) || apariencia.size() != 1
            || !(apariencia.getDictionaryObject(COSName.N) instanceof COSStream stream)
            || !"XObject".equals(stream.getNameAsString(COSName.TYPE)) || !"Form".equals(stream.getNameAsString(COSName.SUBTYPE))
            || stream.containsKey(COSName.getPDFName("OC"))) return false;
        COSBase matrix = stream.getDictionaryObject(COSName.MATRIX);
        return iguales(numeros(stream.getDictionaryObject(COSName.BBOX), 4), new double[]{0, 0, z[2] - z[0], z[3] - z[1]})
            && (matrix == null || iguales(numeros(matrix, 6), new double[]{1, 0, 0, 1, 0, 0}));
    }

    private static double[] numeros(COSBase base, int cantidad) throws java.io.IOException {
        if (!(base instanceof COSArray array) || array.size() != cantidad) throw new java.io.IOException("Coordenadas PDF inválidas.");
        double[] result = new double[cantidad];
        for (int i = 0; i < cantidad; i++) {
            if (!(array.getObject(i) instanceof COSNumber numero)) throw new java.io.IOException("Coordenada no numérica.");
            result[i] = numero.floatValue();
            if (!Double.isFinite(result[i])) throw new java.io.IOException("Coordenada no finita.");
        }
        if (cantidad == 4 && (result[0] >= result[2] || result[1] >= result[3])) throw new java.io.IOException("Rectángulo vacío.");
        return result;
    }

    private static boolean iguales(COSArray a, COSArray b) throws Exception {
        return iguales(numeros(a, 4), numeros(b, 4));
    }

    private static boolean iguales(double[] a, double[] b) {
        if (a.length != b.length) return false;
        for (int i = 0; i < a.length; i++) if (Math.abs(a[i] - b[i]) > 0.02) return false;
        return true;
    }

    private static boolean iguales(BufferedImage a, BufferedImage b) {
        if (a.getWidth() != b.getWidth() || a.getHeight() != b.getHeight()) return false;
        for (int y = 0; y < a.getHeight(); y++) {
            if (!Arrays.equals(a.getRGB(0, y, a.getWidth(), 1, null, 0, a.getWidth()),
                b.getRGB(0, y, b.getWidth(), 1, null, 0, b.getWidth()))) return false;
        }
        return true;
    }
}
