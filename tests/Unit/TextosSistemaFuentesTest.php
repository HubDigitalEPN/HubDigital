<?php

use App\Support\TextosSistema;

test('el inventario editorial encuentra fuentes propias sin recorrer dependencias ni compilados', function (): void {
    $directorio=sys_get_temp_dir().DIRECTORY_SEPARATOR.'hd-editorial-'.bin2hex(random_bytes(8));
    mkdir($directorio,0700);
    $fuentes=['resources/views/portal.blade.php','public/js/editor.js','Modules/Propio/resources/views/inicio.blade.php'];
    $ajenos=['Modules/Propio/node_modules/libreria/dependencia.js','vendor/framework/vista.php','public/build/assets/app.js','public/build-catalogopublico/app.js','leeme.txt'];
    try {
        foreach(array_merge($fuentes,$ajenos) as $ruta){$archivo=$directorio.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$ruta);if(!is_dir(dirname($archivo)))mkdir(dirname($archivo),0700,true);file_put_contents($archivo,'Texto QA del inventario');}
        $metodo=new ReflectionMethod(TextosSistema::class,'archivos');
        $encontrados=[];
        foreach($metodo->invoke(null,[$directorio]) as $archivo)$encontrados[]=str_replace('\\','/',$archivo->getRelativePathname());
        expect($encontrados)->toEqualCanonicalizing($fuentes);
    } finally {
        $resuelto=realpath($directorio);
        if($resuelto && dirname($resuelto)===realpath(sys_get_temp_dir()) && str_starts_with(basename($resuelto),'hd-editorial-')){
            $iterador=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resuelto,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($iterador as $archivo){if($archivo->isDir())rmdir($archivo->getPathname());else unlink($archivo->getPathname());}rmdir($resuelto);
        }
    }
});
