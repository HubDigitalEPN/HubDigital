<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;

/** Presentación con las plantillas originales, sin montar ni operar un expediente. */
final class VistaPreviaWizard
{
    public static function pasos(): array
    {
        $plantillas = [0 => ['solicitud','general'], 1 => ['tramite','tramite'], 2 => ['origen','origen'],
            3 => ['documentos','documentos'], 4 => ['datos','datos'], 7 => ['matriz','detalle'], 8 => ['envio','envio']];

        return array_map(static fn (array $paso): array => [WizardCopy::text('pasos.'.$paso[0]), ...$paso], $plantillas);
    }

    public static function datos(int $paso, string $modalidad): array
    {
        abort_unless(isset(self::pasos()[$paso]) && in_array($modalidad,['Depósito','Donación'],true),422);
        // No mount(), no búsqueda/restauración de borradores ni llamadas de negocio.
        $datos=get_object_vars(new RegistroSolicitudDeposito);
        $datos['paso']=$paso;
        if($paso===8){$datos['solicitudId']='00000000-0000-0000-0000-000000000000';$datos['numeroSolicitud']='VISTA PREVIA';}
        $datos['tipoTramite']=$modalidad;
        $datos['finalidadActividad']='Investigación';
        $datos['cargoConsultor']='Investigador de ejemplo';
        $datos['institucionConsultor']='Institución de ejemplo';
        $datos['datosExtraidos']=['N.º Permiso Recolección'=>'VISTA-PREVIA-001','N.º Permiso Movilización'=>'VISTA-PREVIA-002','N.º Investigación'=>'PROYECTO-DE-EJEMPLO','Grupo Animal'=>'Insectos','Provincia'=>'Pichincha','Administración Política'=>'Quito','Localidad'=>'Localidad de ejemplo','Origen Donación'=>'Material de ejemplo','N.º Individuos'=>24,'N.º Morfoespecies'=>3,'N.º Lotes'=>4];
        $datos['provincia']='Pichincha';
        $datos['situacionRegulatoria']='Posee permisos del MAE';
        $datos['documentosRequeridos']=$modalidad==='Donación'
            ? []
            : ['Copia de la autorización de recolección (MAE)','Copia del permiso de movilización'];
        $datos['provinciasCatalogo']=CatalogoTerritorialEcuador::provincias();
        $datos['localidadesOrigenCatalogo']=[];$datos['localidadesCatalogo']=[];
        $datos['institucionesCatalogo']=[];$datos['cargosCatalogo']=[];
        return $datos;
    }

    /** El fragmento es HTML de nuestras propias vistas. Ningún control opera datos. */
    public static function sanear(string $html, string $modalidad): string
    {
        $doc=new DOMDocument;
        $anterior=libxml_use_internal_errors(true);
        try{$doc->loadHTML('<?xml encoding="UTF-8"><div id="vista-wizard-fragmento">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);}finally{libxml_clear_errors();libxml_use_internal_errors($anterior);}
        $xpath=new DOMXPath($doc);
        foreach(iterator_to_array($xpath->query('//script|//style|//dialog|//ui-modal|//input[@type="hidden"]')) as $nodo)$nodo->parentNode?->removeChild($nodo);
        foreach(iterator_to_array($xpath->query('//*')) as $nodo){
            // La carga y la validación pertenecen al trámite real, nunca a esta maqueta.
            $esCarga = $nodo->hasAttribute('data-flux-loading-indicator') || str_contains($nodo->getAttribute('class'), 'animate-spin');
            foreach ($nodo->attributes as $atributo) if (str_starts_with($atributo->name, 'wire:loading') && !preg_match('/\.(?:remove|attr|class)(?:\.|$)/', $atributo->name)) $esCarga = true;
            if ($esCarga) { $nodo->parentNode?->removeChild($nodo); continue; }
            $mostrar=$nodo->getAttribute('x-show');
            if ($nodo->getAttribute('role') === 'radio') {
                foreach (['Depósito', 'Donación'] as $tipo) {
                    if (str_starts_with(trim($nodo->textContent), $tipo)) {
                        $nodo->setAttribute('data-wizard-modalidad', $tipo);
                        $nodo->setAttribute('aria-checked', $tipo === $modalidad ? 'true' : 'false');
                    }
                }
            }
            if (preg_match('/^tipo === [\x27"](Depósito|Donación)[\x27"]$/u',$mostrar,$m) && $m[1]!==$modalidad){$nodo->parentNode?->removeChild($nodo);continue;}
            foreach(iterator_to_array($nodo->attributes) as $atributo){
                if (str_starts_with($atributo->name, 'wire:model')) $nodo->setAttribute('data-disposicion-model', $atributo->value);
                if(preg_match('/^(?:wire:|x-|on|@)/i',$atributo->name) || in_array($atributo->name,['autofocus','formaction','href','action'],true))$nodo->removeAttribute($atributo->name);
                if(in_array($atributo->name,['required','aria-required','pattern','min','max','minlength','maxlength','aria-invalid'],true))$nodo->removeAttribute($atributo->name);
            }
            if(in_array($nodo->tagName,['input','select','textarea'],true)){$nodo->setAttribute('readonly','');$nodo->setAttribute('tabindex','-1');if($nodo->tagName==='select'||$nodo->getAttribute('type')==='file')$nodo->setAttribute('disabled','');}
            if($nodo->tagName==='button')$nodo->setAttribute('type','button');
        }
        $diccionario=TextosSistema::diccionario();
        foreach(iterator_to_array($xpath->query('//text()')) as $texto){$original=TextosSistema::normalizar($texto->nodeValue);if(isset($diccionario[$original]))$texto->nodeValue=$diccionario[$original];}
        $root=$doc->getElementById('vista-wizard-fragmento');$resultado='';
        foreach($root->childNodes as $nodo)$resultado.=$doc->saveHTML($nodo);
        return $resultado;
    }
}
