<?php

$pagina = [
    'textos' => [
        'titulo' => 'El Laboratorio',
        'principal' => 'Generamos conocimiento sobre taxonomía-sistemática, ecología y evolución y diversidad de insectos, especialmente hormigas (Formicidae), toritos saltarines arbóreos (Membracidae, Aetaliondae) y escarabajos de la familia Scarabaeidae. Custodiamos y preservamos una colección científica que bordea los 29.000 especímenes organizados taxonómicamente en 40 órdenes de anélidos, artrópodos, moluscos y nemátodos, provenientes de más de 1.800 localidades de Ecuador y otros países en el mundo. Debido a su amplia representatividad geográfica y diversidad taxonómica, la colección del Laboratorio se cuenta entre las más importantes del país. Actualmente, los grupos más relevantes son las hormigas, crustáceos y moluscos de agua dulce y macroinvertebrados acuáticos. Nuestro trabajo contribuye a documentar y generar conocimiento para la conservación de la naturaleza.',
        'personal_destacado' => 'Nuestro', 'personal_titulo' => 'personal',
        'personal_descripcion' => 'Profesionales y colaboradores del Departamento de Biología, organizados por sus ramas de investigación.',
        'rama_entomologia_titulo' => 'Entomología · Laboratorio de Invertebrados',
        'adrian_nombre' => 'Adrian Troya', 'adrian_cargo' => 'Curador',
        'adrian_bio' => 'Biólogo y entomólogo dedicado al estudio de las hormigas (Formicidae). Investiga taxonomía, sistemática, biogeografía, filogenia y evolución. Los especímenes de las colecciones apoyan sus estudios sobre diversidad, identificación de especies y parentesco evolutivo.',
        'adrian_especialidad' => 'Colabora con investigadores nacionales y extranjeros en la taxonomía, filogenia y evolución de las hormigas neotropicales del género Neoponera. Contacto: adrian.troya@epn.edu.ec · 022-976-300, ext. 6101.',
        'vladimir_nombre' => 'Vladimir Carvajal', 'vladimir_cargo' => 'Entomólogo',
        'vladimir_bio' => 'Biólogo, entomólogo y fotógrafo de naturaleza con formación en conservación. Investiga invertebrados terrestres y de agua dulce, especialmente crustáceos continentales y moluscos terrestres. Documenta su diversidad mediante macrofotografía y fotomicrografía. Contacto: vladimir.carvajal@epn.edu.ec · 022-976-300, ext. 6004.',
        'david_nombre' => 'David Donoso', 'david_cargo' => 'Colaborador',
        'david_bio' => 'Biólogo y entomólogo que estudia ecología de ecosistemas, redes tróficas del suelo, ciclos de nutrientes y cambio global. Trabaja también en comunidades y sistemática de hormigas neotropicales y entomología forense. Contacto: david.donoso@epn.edu.ec · 022-976-300, ext. 6002.',
        'colaboracion_titulo' => 'Voluntarios, pasantes y tesistas',
        'colaboracion_texto' => 'Recibimos estudiantes y colaboradores interesados en la investigación, curaduría y conservación de invertebrados.',
        'contacto_titulo' => '¿Te interesa colaborar?', 'contacto_accion' => 'Contacta al curador.',
        'contacto_email' => 'adrian.troya@epn.edu.ec',
    ],
    // Solo encuadres fotográficos de la referencia; todo el texto se renderiza en HTML.
    'fotos' => [
        'cabecera' => ['etiqueta' => 'Hormiga de cabecera', 'alt' => 'Hormiga sobre una hoja', 'encuadre' => '685 0 901 215'],
        'adrian' => ['etiqueta' => 'Retrato de Adrian', 'alt' => 'Trabajo de curaduría con un microscopio', 'encuadre' => '68 261 347 504'],
        'hormiga' => ['etiqueta' => 'Hormiga del perfil de Adrian', 'alt' => 'Vista lateral de una hormiga', 'encuadre' => '441 592 247 172'],
        'vladimir' => ['etiqueta' => 'Retrato de Vladimir', 'alt' => 'Observación de invertebrados con un microscopio', 'encuadre' => '743 261 426 157'],
        'crustaceo' => ['etiqueta' => 'Crustáceo', 'alt' => 'Detalle de un pequeño crustáceo', 'encuadre' => '743 433 330 99'],
        'molusco' => ['etiqueta' => 'Molusco', 'alt' => 'Detalle de la concha de un molusco', 'encuadre' => '1085 451 206 81'],
        'escarabajo' => ['etiqueta' => 'Detalle de escarabajo', 'alt' => 'Detalle de la cabeza de un escarabajo', 'encuadre' => '1304 451 214 81'],
        'david' => ['etiqueta' => 'Retrato de David', 'alt' => 'Investigación de campo en un bosque', 'encuadre' => '743 563 287 194'],
        'suelo' => ['etiqueta' => 'Escarabajo del suelo', 'alt' => 'Escarabajo entre la hojarasca', 'encuadre' => '1334 563 184 152'],
        'colaboracion' => ['etiqueta' => 'Franja de colaboración', 'alt' => 'Conservación de una colección entomológica', 'encuadre' => '0 806 685 161'],
    ],
];

// Perfiles e imágenes de las páginas institucionales indicadas; los textos siguen
// siendo editables y las fotos originales se sirven desde este sitio.
$ramas = [
    'mastozoologia' => ['Mastozoología', 'mastozoologia/personal', [
        'edith_masto' => ['Edith Montalvo', 'edith_montalvo.jpg', 'Bióloga con experiencia en colecciones científicas. Máster en Biodiversidad y Cambio Climático por la Universidad Indoamérica, Quito. Responsable encargada de la colección de mamíferos del Departamento de Biología. Contacto: edith.montalvo@epn.edu.ec · 022-976-300, ext. 6006.'],
    ]],
    'ornitologia' => ['Ornitología', 'ornitologia/personal', [
        'edith_aves' => ['Edith Montalvo', 'Edith_M.jpg', 'Bióloga especializada en aves, con experiencia en diversidad, colecciones científicas, etología y parasitología, incluida malaria aviar. Ha participado como expositora en reuniones ornitológicas. Máster en Biodiversidad y Cambio Climático por la Universidad Indoamérica, Quito. Responsable de la colección de aves del Departamento de Biología. Contacto: edith.montalvo@epn.edu.ec · 022-976-300, ext. 6006.'],
        'dayana' => ['Dayana Togán', 'Dayana_T1.jpg', 'Practicante preprofesional de Biología de la Universidad Central del Ecuador. Su formación se orienta a la conservación y al manejo de especímenes en museos.'],
    ]],
    'herpetologia' => ['Herpetología', 'herpetologia/personal', [
        'monica' => ['Mónica Guerra', 'Monica_Guerra.png', 'Doctora en Biología con especialización en Ecología, Evolución y Comportamiento. Docente e investigadora en herpetología, bioacústica, especiación y evolución del comportamiento animal. Combina experimentos de campo con genética molecular y cuenta con publicaciones científicas. Contacto: monica.guerra01@epn.edu.ec · 022-976-300, ext. 6002.'],
        'diego' => ['Diego Almeida Reinoso', 'Diego_Almeida.png', 'Licenciado en Ciencias de la Educación, especialidad Biología y Química, y doctor en Biología por la Universidad Central del Ecuador. Especialista en investigación, monitoreo, taxonomía de anfibios y colecciones de herpetofauna. Trabaja en conservación ex situ de anfibios amenazados. Desarrolló SAR, alimento para renacuajos, y promueve la cría de insectos como alternativa alimenticia. Ha publicado sobre nuevas especies, conservación y ecología de anfibios. Contacto: diego.almeida02@epn.edu.ec · 022-976-300, ext. 6002.'],
        'michelle' => ['Michelle Vélez Cárdenas', 'Michelle_Velez.jpg', 'Ingeniera en Biotecnología con experiencia en análisis microbiológicos y moleculares para identificar especies. Ha colaborado en proyectos sobre cambio climático y captura de carbono, y en enseñanza de Química y Biología. Apoya el mantenimiento de la colección científica de Herpetología y su actualización taxonómica.'],
    ]],
    'ictiologia' => ['Ictiología', 'ictologia/personal', [
        'pablo' => ['Pablo Arguello', 'Pablo_Arguello.png', 'Licenciado en Ciencias Biológicas y Ambientales por la Universidad Central del Ecuador. Curador interino de la colección de peces de la Escuela Politécnica Nacional y miembro de la Sociedad Americana de Ictiología y Herpetología (ASIH). Investiga taxonomía, biodiversidad y evolución de peces neotropicales. Tiene experiencia en curaduría, investigación y monitoreo ambiental de campo y laboratorio. Ha participado en encuentros académicos y publicado artículos científicos. Contacto: pablo.arguello@epn.edu.ec · 022-976-300, ext. 6007.'],
    ]],
    'ecologia' => ['Ecología', 'ecologia/selene-baez', [
        'selene' => ['Selene Báez', 'Selene_Baez.png', 'Doctora por el Departamento de Biología de la University of New Mexico, Estados Unidos; máster en Ciencias por el Departamento de Botánica Sistemática de la Universidad de Aarhus, Dinamarca; y bióloga por la Pontificia Universidad Católica del Ecuador. Docente de Ecología del Departamento de Biología de la Escuela Politécnica Nacional. Ha participado en eventos académicos nacionales e internacionales y publicado artículos en revistas científicas indexadas. Contacto: selene.baez@epn.edu.ec · 022-976-300, ext. 6009.'],
    ]],
];
foreach ($ramas as $clave => [$titulo, $fuente, $personas]) {
    $pagina['ramas'][$clave] = ['personas' => array_keys($personas), 'fuente' => 'https://biologia.epn.edu.ec/index.php/secciones/'.$fuente];
    $pagina['textos']['rama_'.$clave.'_titulo'] = $titulo;
    foreach ($personas as $id => [$nombre, $archivo, $bio]) {
        $pagina['textos'][$id.'_nombre'] = $nombre;
        $pagina['textos'][$id.'_bio'] = $bio;
        $pagina['fotos'][$id] = ['etiqueta' => $nombre.' · '.$titulo, 'alt' => $nombre, 'archivo' => $archivo];
    }
}
foreach (['adrian' => 'Adrian.jpg', 'vladimir' => 'vladimir_carvajal.jpg', 'david' => 'David_Donoso_2.jpg', 'colaboracion' => 'asistente.jpg'] as $id => $archivo) {
    $pagina['fotos'][$id]['archivo'] = $archivo;
    $pagina['fotos'][$id]['alt'] = $pagina['textos'][$id.'_nombre'] ?? 'Voluntarios, pasantes y tesistas';
}
return $pagina;
