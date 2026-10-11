"""ETE TreeView para la jerarquía pública. Servicio interno, sin base de datos.

Qt dibuja en el hilo principal y sin ventanas; Laravel entrega exclusivamente
taxones publicados y aplica sus permisos antes de llamar a este proceso.
"""
import argparse
import hashlib
import json
import os
import tempfile
import time
from collections import OrderedDict
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path
from xml.etree import ElementTree as ET

os.environ.setdefault("QT_QPA_PLATFORM", "offscreen")
if os.name == "nt":
    os.environ.setdefault("QT_QPA_FONTDIR", str(Path(os.environ.get("WINDIR", "C:/Windows")) / "Fonts"))
from ete3 import Tree, TreeStyle, NodeStyle, TextFace  # noqa: E402
from ete3.treeview.drawer import init_scene
from ete3.treeview.qt4_render import render as render_scene
from ete3.treeview.main import save
from PyQt5.QtCore import Qt, QRectF
from PyQt5.QtGui import QFont, QFontMetrics
from PyQt5.QtWidgets import QGraphicsEllipseItem, QGraphicsRectItem, QGraphicsSimpleTextItem, QGraphicsLineItem, QGraphicsPathItem

MAX_BODY = 8 * 1024 * 1024
CIRCULAR_THRESHOLD = 40
CACHE = OrderedDict()
COLORS = {"Arthropoda": "#17699b", "Annelida": "#568c59", "Mollusca": "#d17d28", "Nematomorpha": "#b94e6b"}
BRANCH_COLORS = ("#17699b", "#7953a0", "#287b58", "#ad5635", "#a34369", "#357b86", "#72642b", "#4b5da2")


def export_svg(root, tree_style, file):
    """Dibujo circular nativo de ETE; sólo extrae sus zonas interactivas."""
    for nid, node in enumerate(root.traverse("preorder")):
        node.add_feature("_nid", nid)
    scene, style = init_scene(root, None, tree_style)
    frame, nodes, faces = render_scene(root, style)
    scene.init_values(root, style, nodes, faces)
    frame.setParentItem(scene.master_item)
    scene.master_item.setPos(0, 0)
    scene.addItem(scene.master_item)
    # La face flotante no interviene en los radios de ETE. Anclar su rótulo
    # horizontal debajo del punto real evita tapar el nombre de otro padre.
    if hasattr(root, "portal_root_face"):
        balls = [item for item in nodes[root].mapped_items if isinstance(item, QGraphicsEllipseItem)]
        if balls:
            anchor = balls[0].sceneBoundingRect().center()
            group = faces[root]["float"]
            box = group.mapToScene(group.boundingRect()).boundingRect()
            group.moveBy(anchor.x() - box.center().x(), anchor.y() + 9 - box.top())
    # Recorta únicamente el marco circular vacío; la posición, los arcos y
    # la rotación de las faces son exactamente los calculados por ETE.
    content = QRectF()
    for item in scene.items():
        if not item.isVisible():
            continue
        if isinstance(item, (QGraphicsRectItem, QGraphicsEllipseItem, QGraphicsPathItem)) and item.pen().style() == Qt.NoPen and item.brush().style() == Qt.NoBrush:
            continue
        if isinstance(item, (QGraphicsSimpleTextItem, QGraphicsLineItem, QGraphicsPathItem, QGraphicsEllipseItem, QGraphicsRectItem)):
            content = content.united(item.sceneBoundingRect())
    # El margen final lo reserva el visor en píxeles de pantalla. Un margen
    # nativo grande se multiplicaba al ampliar árboles pequeños.
    scene.setSceneRect(content.adjusted(-4, -4, 4, 4))
    bounds = scene.sceneRect()
    sx, sy = save(scene, str(file))
    zones = []
    for node, groups in faces.items():
        balls = [item for item in nodes[node].mapped_items if isinstance(item, QGraphicsEllipseItem)]
        ball = balls[0].sceneBoundingRect().center() if balls else None
        location = [(ball.x() - bounds.x()) * sx, (ball.y() - bounds.y()) * sy] if ball else None
        has_face = False
        for group in groups.values():
            if not group.column2faces:
                continue
            has_face = True
            polygon = group.mapToScene(group.boundingRect())
            points = [[(point.x() - bounds.x()) * sx, (point.y() - bounds.y()) * sy] for point in polygon]
            box = [min(p[0] for p in points), min(p[1] for p in points),
                   max(p[0] for p in points), max(p[1] for p in points)]
            zones.append({"nid": node._nid, "caja": box, "poligono": points, "punto": location})
        if not has_face:
            points = [item for item in nodes[node].mapped_items if isinstance(item, QGraphicsEllipseItem)]
            if points:
                point = points[0].sceneBoundingRect().center()
                x, y = (point.x() - bounds.x()) * sx, (point.y() - bounds.y()) * sy
                zones.append({"nid": node._nid, "caja": [x - 11, y - 11, x + 11, y + 11], "tipo": "punto"})
    root_points = [item for item in nodes[root].mapped_items if isinstance(item, QGraphicsEllipseItem)]
    point = root_points[0].sceneBoundingRect().center() if root_points else nodes[root].mapToScene(0, nodes[root].center)
    origin = [(point.x() - bounds.x()) * sx, (point.y() - bounds.y()) * sy]
    return zones, origin
def render(payload):
    rows = payload["nodos"]
    if not isinstance(rows, list) or len(rows) > 30000:
        raise ValueError("Jerarquía fuera de límites")
    opened = set(payload.get("expandidos", []))
    mode = payload.get("vista", "auto")
    if mode not in ("auto", "rectangular", "semicircular", "circular"):
        raise ValueError("Vista no admitida")
    scale = max(40, min(300, float(payload.get("escala", 120))))
    focus = payload.get("rama", "")
    key = hashlib.sha256(json.dumps([rows, sorted(opened), mode, scale, focus], ensure_ascii=False, sort_keys=True).encode()).hexdigest()
    if key in CACHE:
        CACHE.move_to_end(key)
        return CACHE[key]
    index = {r["clave"]: r for r in rows}
    if focus and focus not in index:
        raise ValueError("Rama inexistente")
    depth, colors = {}, {}
    for row in rows:
        lineage, cursor = set(), row
        while cursor.get("padre") in index and cursor["clave"] not in lineage:
            lineage.add(cursor["clave"])
            cursor = index[cursor["padre"]]
        depth[row["clave"]] = len(lineage)
        colors[row["clave"]] = COLORS.get(cursor["nombre"], "#17699b")
    children = {}
    for r in rows:
        children.setdefault(r.get("padre"), []).append(r)
    for group in children.values():
        group.sort(key=lambda r: (r["nombre"].casefold(), r["clave"]))
    root = Tree(name="")
    registry = []
    seen = set()
    branch_colors = {r["clave"]: BRANCH_COLORS[i % len(BRANCH_COLORS)] for i, r in enumerate(children.get(focus, []))} if focus else {}
    pending = [(root, index[focus], colors[focus])] if focus else [(root, r, colors[r["clave"]]) for r in reversed(children.get(None, []))]
    while pending:
        parent, row, color = pending.pop()
        if row["clave"] in seen:
            continue
        seen.add(row["clave"])
        node = parent.add_child(name=row["nombre"], dist=1)
        node.add_features(portal_key=row["clave"], portal_color=color)
        registry.append((node, row))
        if len(registry) > 3000:
            raise ValueError("Selecciona una rama para explorar más de 3000 nodos")
        style = NodeStyle()
        expands = bool(row["clave"] in opened and children.get(row["clave"]))
        style["size"] = 9 if expands else 5
        style["fgcolor"] = color
        style["hz_line_color"] = color
        style["vt_line_color"] = color
        style["hz_line_width"] = 2
        style["vt_line_width"] = 2
        if expands and row["clave"] != focus:
            style["bgcolor"] = "#f0f4f8"
        node.set_style(style)
        # El navegador conserva los niveles 1–2. El nodo seleccionado sirve
        # de raíz de contexto y sus descendientes se dibujan en ETE.
        if not focus and depth[row["clave"]] < 2:
            style["size"] = 0
            if row["clave"] in opened:
                pending.extend((node, child, color) for child in reversed(children.get(row["clave"], [])))
            continue
        # Los padres conservan su nombre junto a la bifurcación. Todas las
        # etiquetas y fondos de subárbol proceden del layout nativo ETE.
        is_root_label = expands and row["clave"] == focus
        position = "float" if is_root_label else "branch-top" if expands else "branch-right"
        name = row["nombre"] + (" · " + row["etiqueta"] if is_root_label and row.get("etiqueta") else "")
        face = TextFace(name, fsize=20 if is_root_label else 18, fgcolor=color, bold=True, fstyle="italic")
        face.margin_left = 5
        face.margin_right = 8
        face.margin_bottom = 4 if expands else 0
        if is_root_label:
            face.background.color = "#ffffff"
            face.rotable = False
            node.add_feature("portal_root_face", face)
        node.add_face(face, column=0, position=position)
        label = row.get("etiqueta", "")
        count = TextFace((label + " · " if label else "") + f'{int(row["total"]):,}'.replace(",", "."), fsize=12, fgcolor="#173752")
        count.margin_right = 6
        if not is_root_label:
            node.add_face(count, column=1, position=position)
        if row["clave"] in opened:
            pending.extend((node, child, branch_colors.get(child["clave"], color)) for child in reversed(children.get(row["clave"], [])))
    if focus and root.children:
        root = root.children[0]
        root.detach()
        root.dist = 0
    else:
        root.dist = 0
        style = NodeStyle()
        style["size"] = 0
        root.set_style(style)
    ts = TreeStyle()
    leaves = len(root.get_leaves())
    if mode == "auto":
        mode = "circular" if leaves > CIRCULAR_THRESHOLD else "semicircular"
    ts.mode = "r" if mode == "rectangular" else "c"
    # ETE invierte las faces sólo entre 90 y 270 grados positivos. Usar
    # -180 dibujaba el arco arriba, pero dejaba sus nombres boca abajo.
    # Una única hoja no forma un arco: su rama sale a la derecha, legible.
    # Centrar el abanico en 270°: con dos hojas ETE divide el arco en dos
    # sectores de 90°, por lo que empezar en 180° dejaba todo a la izquierda.
    ts.arc_start = 180 + 90 / leaves if mode == "semicircular" and leaves > 1 else 0
    ts.arc_span = 180 if mode == "semicircular" else 360
    # La face flotante identifica la raíz sin agrandar su radio ni crear un
    # hueco central. Contrarrotar sólo este texto conserva su lectura horizontal.
    if hasattr(root, "portal_root_face") and ts.mode == "c":
        angles = {leaf: ts.arc_start + i * ts.arc_span / leaves for i, leaf in enumerate(root.iter_leaves())}
        for ancestor in root.traverse("postorder"):
            if ancestor.children:
                angles[ancestor] = (angles[ancestor.children[0]] + angles[ancestor.children[-1]]) / 2
        root.portal_root_face.rotation = (360 - angles[root]) % 360
    ts.scale = scale
    ts.allow_face_overlap = False
    ts.show_leaf_name = False
    ts.show_scale = False  # Son rangos taxonómicos, no distancias evolutivas.
    ts.extra_branch_line_type = 0
    ts.extra_branch_line_color = "#61798e"
    ts.branch_vertical_margin = 8 if ts.mode == "r" else 0
    # ETE sigue separando las faces según su alto real. No añadir ocho
    # píxeles vacíos por hoja: en ramas densas alejaban demasiado los nombres.
    ts.min_leaf_separation = 2
    ts.margin_left = ts.margin_right = ts.margin_top = ts.margin_bottom = 16
    started = time.monotonic()
    with tempfile.TemporaryDirectory(prefix="hubdigital-ete-") as temporary:
        file = Path(temporary) / "tree.svg"
        zones, origin = export_svg(root, ts, file)
        svg = file.read_text(encoding="utf-8")
    document = ET.fromstring(svg)
    bounds = [float(n) for n in document.attrib["viewBox"].split()]
    by_id = {node._nid: row for node, row in registry if focus or depth[row["clave"]] >= 2}
    result = {"svg": svg, "viewBox": bounds, "raiz": origin,
              "nodos": [{"clave": by_id[zone["nid"]]["clave"], **{field: value for field, value in zone.items() if field != "nid"}} for zone in zones if zone["nid"] in by_id],
              "relaciones": [{"padre": node.up.portal_key, "hijo": row["clave"]} for node, row in registry
                             if node.up is not None and hasattr(node.up, "portal_key") and node.up._nid in by_id],
              "motor": "ETE 3.1.3", "vista": mode, "disposicion": "lineal" if leaves == 1 or mode == "rectangular" else "radial",
              "hojas": leaves, "total": len(by_id), "renderMs": round((time.monotonic() - started) * 1000)}
    print(f'ETE {mode}: {len(registry)} nodos, {result["renderMs"]} ms, SVG {bounds[2]:.0f}x{bounds[3]:.0f}', flush=True)
    CACHE[key] = result
    # Límite por memoria además de cantidad: la VM OCI no acumula SVG masivos.
    while len(CACHE) > 24 or sum(len(v["svg"]) for v in CACHE.values()) > 24 * 1024 * 1024:
        CACHE.popitem(last=False)
    return result


class Handler(BaseHTTPRequestHandler):
    def reply(self, status, data):
        body = json.dumps(data, ensure_ascii=False).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        self.reply(200 if self.path == "/health" else 404, {"motor": "ETE 3.1.3", "estado": "disponible"})

    def do_POST(self):
        if self.path != "/render":
            return self.reply(404, {"error": "Ruta inexistente"})
        try:
            length = int(self.headers.get("Content-Length", 0))
            if not 0 < length <= MAX_BODY:
                return self.reply(413, {"error": "Jerarquía fuera de límites"})
            self.reply(200, render(json.loads(self.rfile.read(length))))
        except (ValueError, KeyError, TypeError):
            self.reply(422, {"error": "Jerarquía o vista inválida"})
        except Exception:
            self.log_error("ETE no pudo dibujar la jerarquía")
            self.reply(503, {"error": "No se pudo dibujar el árbol"})


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--port", type=int, default=8001)
    args = parser.parse_args()
    # Inicializa Qt y las métricas una sola vez al arrancar el servicio.
    init_scene(Tree(), None, TreeStyle())
    QFontMetrics(QFont("Arial", 14)).height()
    # HTTPServer es serial: Qt conserva el mismo hilo para todas las imágenes.
    HTTPServer(("127.0.0.1", args.port), Handler).serve_forever()
