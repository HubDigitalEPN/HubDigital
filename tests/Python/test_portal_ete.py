import importlib.util
from pathlib import Path
import unittest
import re
from xml.etree import ElementTree as ET

spec = importlib.util.spec_from_file_location("portal_ete", Path(__file__).parents[2] / "resources/python/portal_ete/server.py")
ete = importlib.util.module_from_spec(spec)
spec.loader.exec_module(ete)


def row(key, parent=None, name=None):
    return {"clave": key, "padre": parent, "nombre": name or key, "total": 4}


def overlap(first, second):
    """Separating axis theorem: faces rotadas, no sus cajas envolventes."""
    for polygon in (first, second):
        for a, b in zip(polygon, polygon[1:] + polygon[:1]):
            axis = [-(b[1] - a[1]), b[0] - a[0]]
            if axis == [0, 0]:
                continue
            one = [p[0] * axis[0] + p[1] * axis[1] for p in first]
            two = [p[0] * axis[0] + p[1] * axis[1] for p in second]
            if max(one) <= min(two) + .001 or max(two) <= min(one) + .001:
                return False
    return True


INITIAL = [row("filo"), row("clase", "filo"), row("orden", "clase")]


def text_axes(svg):
    """Dirección real de lectura después de componer matrices del SVG Qt."""
    result = {}
    def visit(element, matrix):
        own = element.get("transform", "")
        if own.startswith("matrix("):
            a, b, c, d, e, f = [float(n) for n in re.split(r"[ ,]+", own[7:-1].strip())]
            aa, bb, cc, dd, ee, ff = matrix
            matrix = (aa*a+cc*b, bb*a+dd*b, aa*c+cc*d, bb*c+dd*d, aa*e+cc*f+ee, bb*e+dd*f+ff)
        if element.tag.endswith("}text"):
            result["".join(element.itertext())] = matrix[:2]
        for child in element:
            visit(child, matrix)
    visit(ET.fromstring(svg), (1, 0, 0, 1, 0, 0))
    return result


class PortalETE(unittest.TestCase):
    def test_terminal_phylum_has_its_own_native_graph(self):
        result = ete.render({"nodos": [row("nema", name="Nematomorpha")], "rama": "nema", "expandidos": []})
        self.assertEqual({n["clave"] for n in result["nodos"]}, {"nema"})
        self.assertEqual(result["total"], 1)
        self.assertEqual(result["disposicion"], "lineal")
        self.assertGreater(text_axes(result["svg"])["Nematomorpha"][0], 0)

    def test_single_child_of_a_class_points_right_with_upright_text(self):
        for name in ["Haplotaxida", "Glossiphoniidae"]:
            rows = [row("filo", name="Annelida"), row("clase", "filo"), row("hijo", "clase", name)]
            result = ete.render({"nodos": rows, "rama": "clase", "expandidos": ["clase"]})
            self.assertEqual({n["clave"] for n in result["nodos"]}, {"clase", "hijo"})
            self.assertEqual(result["disposicion"], "lineal")
            child = next(n for n in result["nodos"] if n["clave"] == "hijo")
            self.assertGreater(child["caja"][0], result["raiz"][0])
            axis = text_axes(result["svg"])[name]
            self.assertGreater(axis[0], 0)
            self.assertAlmostEqual(axis[1], 0, places=3)

    def test_semicircle_opens_upward_and_left_labels_are_not_upside_down(self):
        rows = [*INITIAL, row("a", "orden", "Acanthotaxon"), row("b", "orden", "Betataxon"), row("c", "orden", "Cephalotaxon")]
        result = ete.render({"nodos": rows, "rama": "orden", "expandidos": ["orden"]})
        self.assertEqual(result["disposicion"], "radial")
        labels = text_axes(result["svg"])
        for name in ["Acanthotaxon", "Betataxon", "Cephalotaxon"]:
            self.assertGreaterEqual(labels[name][0], -.02, "El eje de lectura no apunta a la izquierda")
        tip = next(n for n in result["nodos"] if n["clave"] == "b")
        self.assertLess(tip["caja"][3], result["raiz"][1], "El arco crece hacia arriba")

    def test_real_ete_views_and_massive_family(self):
        rows = [*INITIAL, row("familia", "orden"), *[row(f"g{i}", "familia") for i in range(122)]]
        for mode in ["rectangular", "semicircular", "circular"]:
            with self.subTest(mode=mode):
                result = ete.render({"nodos": rows, "rama": "familia", "expandidos": ["familia"], "vista": mode})
                self.assertEqual(result["motor"], "ETE 3.1.3")
                self.assertEqual(result["total"], 123)
                self.assertEqual({n["clave"] for n in result["nodos"]}, {"familia", *[f"g{i}" for i in range(122)]})
                self.assertGreater(result["viewBox"][2], 0)
                self.assertGreater(result["viewBox"][3], 0)
                self.assertTrue(ET.fromstring(result["svg"]).tag.endswith("svg"))
                tips = [n["poligono"] for n in result["nodos"] if n["clave"].startswith("g")]
                self.assertEqual(len(tips), 122)
                for i, previous in enumerate(tips):
                    for following in tips[i + 1:]:
                        self.assertFalse(overlap(previous, following), "Las faces nativas no se superponen")

    def test_collapse_focus_and_cache_reuse(self):
        rows = [*INITIAL, row("a", "orden"), row("b", "orden"), row("h", "a"), row("n", "h"), row("ajeno", "b")]
        opened = {"nodos": rows, "rama": "a", "expandidos": ["a", "h"]}
        self.assertEqual(ete.render(opened)["total"], 3)
        collapsed = ete.render({**opened, "expandidos": ["a"]})
        self.assertEqual({n["clave"] for n in collapsed["nodos"]}, {"a", "h"})
        self.assertIs(ete.render({**opened, "expandidos": ["a"]}), collapsed)
        self.assertEqual({n["clave"] for n in ete.render({**opened, "rama": "b", "expandidos": ["b"]})["nodos"]}, {"b", "ajeno"})

    def test_massive_nested_branch_preserves_the_native_tree(self):
        rows = [*INITIAL, row("familia", "orden", name="Formicidae"),
                *[row(f"g{i}", "familia", name="Camponotus" if i == 0 else f"Género científico {i}") for i in range(31)],
                *[row(f"e{i}", "g0", name=f"Camponotus especie {i}") for i in range(122)]]
        result = ete.render({"nodos": rows, "rama": "familia", "expandidos": ["familia", "g0"], "vista": "auto"})
        self.assertEqual(result["vista"], "circular")
        self.assertEqual(result["hojas"], 152)
        self.assertEqual(result["total"], 154)
        self.assertEqual({n["clave"] for n in result["nodos"]},
                         {"familia", *[f"g{i}" for i in range(31)], *[f"e{i}" for i in range(122)]})
        parents = [n for n in result["nodos"] if n["clave"] in {"familia", "g0"}]
        self.assertEqual(len(parents), 2)
        for parent in parents:
            self.assertIn("poligono", parent, "Un padre abierto conserva su etiqueta visible")
            self.assertEqual(len(parent["punto"]), 2, "Su bifurcación sigue siendo interactiva")
        text = "".join(ET.fromstring(result["svg"]).itertext())
        self.assertIn("Formicidae", text)
        self.assertIn("Camponotus", text_axes(result["svg"]))
        for i in range(122):
            self.assertIn(f"Camponotus especie {i}", text)
        self.assertNotIn("Hijos de ", text)
        for x, length in zip(result["raiz"], result["viewBox"][2:]):
            self.assertGreaterEqual(x, 0)
            self.assertLessEqual(x, length)

    def test_named_parents_and_links_preserve_the_catalogue_hierarchy(self):
        rows = [row("familia", name="Formicidae"), row("subfamilia", "familia", "Myrmicinae"),
                row("genero", "subfamilia", "Pheidole"), row("especie", "genero", "Pheidole test"),
                row("directo", "familia", "Camponotus"), row("externo", name="Otro filo")]
        for item, rank in zip(rows, ["Familia", "Subfamilia", "Género", "Especie", "Género", "Filo"]):
            item["etiqueta"] = rank
        for mode in ["semicircular", "circular", "rectangular"]:
            with self.subTest(mode=mode):
                result = ete.render({"nodos": rows, "rama": "familia", "expandidos": ["familia", "subfamilia", "genero"], "vista": mode})
                self.assertEqual({(r["padre"], r["hijo"]) for r in result["relaciones"]},
                                 {("familia", "subfamilia"), ("subfamilia", "genero"), ("genero", "especie"), ("familia", "directo")})
                self.assertEqual(len(result["relaciones"]), result["total"] - 1)
                names = text_axes(result["svg"])
                for name in ["Myrmicinae", "Pheidole", "Pheidole test", "Camponotus"]:
                    self.assertIn(name, names, "Abrir hijos no elimina el nombre del padre")
                self.assertIn("Formicidae · Familia", names)
                self.assertGreater(names["Formicidae · Familia"][0], .98, "La raíz se lee horizontalmente")
                root_zone = next(n for n in result["nodos"] if n["clave"] == "familia")
                self.assertEqual(root_zone["punto"], result["raiz"])
                self.assertNotIn("externo", {n["clave"] for n in result["nodos"]})

    def test_auto_view_switches_at_the_visible_leaf_limit_and_reuses_cache(self):
        for size, mode in [(40, "semicircular"), (41, "circular"), (122, "circular")]:
            rows = [*INITIAL, row("familia", "orden"), *[row(f"g{i}", "familia") for i in range(size)]]
            payload = {"nodos": rows, "rama": "familia", "expandidos": ["familia"], "vista": "auto"}
            result = ete.render(payload)
            self.assertEqual(result["vista"], mode)
            self.assertEqual(result["hojas"], size)
            self.assertIs(ete.render(payload), result)
            collapsed = ete.render({**payload, "expandidos": []})
            self.assertEqual(collapsed["vista"], "semicircular")
            self.assertEqual(collapsed["total"], 1)

    def test_hundreds_of_descendants_are_not_truncated_and_the_limit_is_explicit(self):
        for size in (256, 768):
            with self.subTest(size=size):
                rows = [*INITIAL, row("masiva", "orden"),
                        *[row(f"especie-{i}", "masiva", name=f"Taxón científico {i}") for i in range(size)]]
                payload = {"nodos": rows, "rama": "masiva", "expandidos": ["masiva"], "vista": "auto"}
                result = ete.render(payload)
                self.assertEqual(result["vista"], "circular")
                self.assertEqual(result["hojas"], size)
                self.assertEqual(result["total"], size + 1)
                self.assertEqual({n["clave"] for n in result["nodos"]},
                                 {"masiva", *[f"especie-{i}" for i in range(size)]})
                text = "".join(ET.fromstring(result["svg"]).itertext())
                for i in range(size):
                    self.assertIn(f"Taxón científico {i}", text)
                self.assertIs(ete.render(payload), result)
                self.assertEqual(ete.render({**payload, "expandidos": []})["total"], 1)
        excessive = [*INITIAL, row("masiva", "orden"),
                     *[row(f"e{i}", "masiva") for i in range(3000)]]
        with self.assertRaisesRegex(ValueError, "3000 nodos"):
            ete.render({"nodos": excessive, "rama": "masiva", "expandidos": ["masiva"]})

    def test_names_are_data_and_technical_root_is_not_a_taxon(self):
        rows = [*INITIAL, row("a", "orden", name='Género "<x>"; (A,B)'), row("b", "orden")]
        result = ete.render({"nodos": rows, "expandidos": ["filo", "clase", "orden"]})
        self.assertEqual({n["clave"] for n in result["nodos"]}, {"orden", "a", "b"})
        self.assertEqual(result["total"], 3)
        self.assertNotIn("<x>", result["svg"])
        with self.assertRaises(ValueError):
            ete.render({"nodos": rows, "rama": "no-publicado"})
        with self.assertRaises(ValueError):
            ete.render({"nodos": rows, "vista": "código arbitrario"})

    def test_second_level_is_the_context_root_and_the_viewer_includes_level_three(self):
        rows = [*INITIAL, row("cuarto", "orden")]
        result = ete.render({"nodos": rows, "rama": "clase", "expandidos": ["clase", "orden"]})
        self.assertEqual({n["clave"] for n in result["nodos"]}, {"clase", "orden", "cuarto"})
        self.assertEqual(result["total"], 3)


if __name__ == "__main__":
    unittest.main()
