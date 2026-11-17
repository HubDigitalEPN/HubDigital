<?php

$pagina = [
    'textos' => [
        'titulo' => 'El Laboratorio',
        'principal' => 'Generamos conocimiento sobre taxonomía-sistemática, ecología y evolución y diversidad de insectos, especialmente hormigas (Formicidae), toritos saltarines arbóreos (Membracidae, Aetaliondae) y escarabajos de la familia Scarabaeidae. Custodiamos y preservamos una colección científica que bordea los 29.000 especímenes organizados taxonómicamente en 40 órdenes de anélidos, artrópodos, moluscos y nemátodos, provenientes de más de 1.800 localidades de Ecuador y otros países en el mundo. Debido a su amplia representatividad geográfica y diversidad taxonómica, la colección del Laboratorio se cuenta entre las más importantes del país. Actualmente, los grupos más relevantes son las hormigas, crustáceos y moluscos de agua dulce y macroinvertebrados acuáticos. Nuestro trabajo contribuye a documentar y generar conocimiento para la conservación de la naturaleza.',
        'personal_destacado' => 'Nuestro', 'personal_titulo' => 'personal',
        'personal_descripcion' => 'Profesionales y colaboradores del Departamento de Biología, organizados por sus ramas de investigación.',
        'rama_entomologia_titulo' => 'Entomología · Laboratorio de Invertebrados',
        'adrian_nombre' => 'Adrian Troya', 'adrian_cargo' => 'Curador',
        'adrian_bio' => 'Biólogo por la Universidad Central del Ecuador, con maestría en Ecología y Evolución por la Universidad de Múnich, Alemania, y doctorado en Ciencias Biológicas, con especialización en Entomología, Taxonomía y Sistemática, por la Universidade Federal do Paraná, Brasil.

Investiga la clasificación y el descubrimiento de especies de insectos tropicales, especialmente hormigas (Formicidae). Sus estudios combinan taxonomía, biogeografía y filogenética molecular para comprender cómo los procesos geológicos, ambientales y las transformaciones humanas influyen en su distribución y evolución. Examina las características morfológicas que permiten reconocer especies y utiliza los especímenes preservados en las colecciones como fuente de información científica. Es investigador del Departamento de Biología de la Escuela Politécnica Nacional y responsable de su colección de insectos y otros invertebrados.',
        'adrian_especialidad' => 'Colabora con investigadores nacionales y extranjeros en la taxonomía, filogenia y evolución de las hormigas neotropicales del género Neoponera. Contacto: adrian.troya@epn.edu.ec · 022-976-300, ext. 6101.',
        'vladimir_nombre' => 'Vladimir Carvajal', 'vladimir_cargo' => 'Entomólogo',
        'vladimir_bio' => 'Biólogo y entomólogo, con formación en Biología de la Conservación. Su trabajo se centra en la taxonomía de invertebrados terrestres y de agua dulce. Investiga crustáceos continentales y moluscos terrestres. Es fotógrafo de naturaleza y utiliza la macrofotografía y la fotomicrografía como herramientas para documentar la biodiversidad de estos organismos. Contacto: vladimir.carvajal@epn.edu.ec · 022-976-300, ext. 6004.',
        'david_nombre' => 'David Donoso', 'david_cargo' => 'Colaborador',
        'david_bio' => 'Biólogo y entomólogo. Sus líneas de investigación comprenden la ecología de ecosistemas, las cadenas tróficas del suelo, los ciclos de nutrientes y el cambio global. Ha desarrollado investigaciones sobre las comunidades, la biodiversidad y la sistemática de hormigas neotropicales. También ha trabajado en entomología forense, estudiando insectos que aportan información de interés para este campo. Contacto: david.donoso@epn.edu.ec · 022-976-300, ext. 6002.',
        'colaboracion_titulo' => 'Voluntarios, pasantes y tesistas',
        'colaboracion_texto' => 'Recibimos estudiantes y colaboradores interesados en la investigación, curaduría y conservación de invertebrados.',
        'contacto_titulo' => '¿Te interesa colaborar?', 'contacto_accion' => 'Contacta al curador.',
        'contacto_email' => 'adrian.troya@epn.edu.ec',
    ],
    'fotos' => [],
];

// Esta página publica únicamente el equipo de Entomología.
$pagina['ramas'] = [];
foreach (['adrian' => 'adrian-retrato.jpg', 'vladimir' => 'vladimir_carvajal.jpg', 'david' => 'David_Donoso_2.jpg'] as $id => $archivo) {
    $pagina['fotos'][$id]['archivo'] = $archivo;
    $pagina['fotos'][$id]['etiqueta'] = $pagina['textos'][$id.'_nombre'];
    $pagina['fotos'][$id]['alt'] = $pagina['textos'][$id.'_nombre'] ?? 'Voluntarios, pasantes y tesistas';
}
// Recortes medidos sobre cada original: ancho y alto originales, x, y, ancho y alto
// del encuadre. Mantienen una escala comparable de cabeza y hombros, sin retocar rostros.
foreach ([
    'adrian' => [707, 825, 275, 90, 135, 180],
    'vladimir' => [473, 649, 85, 0, 300, 400],
    'david' => [720, 800, 110, 0, 600, 800],
] as $persona => $encuadre) $pagina['fotos'][$persona]['retrato'] = $encuadre;
return $pagina;
