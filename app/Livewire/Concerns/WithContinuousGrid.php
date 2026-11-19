<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\PaginadorGrillaContinua;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Livewire\Attributes\Locked;

/** Ventana continua de consultas frescas: no guarda copias de filas ni crece sin límite. */
trait WithContinuousGrid
{
    #[Locked] public array $gridVentana = [];

    public function cargarGrillaContinua(int $direccion, string $consulta, int $borde): void
    {
        abort_unless(in_array($direccion,[-1,1],true),422);
        $v=$this->gridVentana;
        if(($v['consulta']??'')!==$consulta || ($v[$direccion===1?'hasta':'desde']??0)!==$borde)return;
        if($direccion===1 && $v['hasta']<$v['paginas']){
            $v['hasta']++;$v['desde']=max($v['desde'],$v['hasta']-$v['maximo']+1);
        }elseif($direccion===-1 && $v['desde']>1){
            $v['desde']--;$v['hasta']=min($v['hasta'],$v['desde']+$v['maximo']-1);
        }
        $this->gridVentana=$v;
    }

    protected function rangoGrillaContinua(string $consulta, int $total, int $porPagina, int $pagina): array
    {
        $porPagina=max(1,$porPagina);$paginas=max(1,(int)ceil($total/$porPagina));$pagina=max(1,min($pagina,$paginas));
        $token=hash('sha256',$consulta.'|'.$porPagina.'|'.$total);
        if(($this->gridVentana['consulta']??'')!==$token || ($this->gridVentana['ancla']??0)!==$pagina){
            $this->gridVentana=['consulta'=>$token,'desde'=>$pagina,'hasta'=>$pagina,'ancla'=>$pagina,'paginas'=>$paginas,'maximo'=>max(1,min(3,intdiv(600,$porPagina)))];
        }
        $v=$this->gridVentana;
        return [($v['desde']-1)*$porPagina,($v['hasta']-$v['desde']+1)*$porPagina];
    }

    protected function paginarGrilla(EloquentBuilder|QueryBuilder $consulta, ?int $porPagina = null): PaginadorGrillaContinua
    {
        $porPagina??=$this->filasGrilla();$pagina=$this->getPage();
        $total=(clone $consulta)->getCountForPagination();
        [$offset,$limite]=$this->rangoGrillaContinua(json_encode([$consulta->toSql(),$consulta->getBindings()],JSON_THROW_ON_ERROR),$total,$porPagina,$pagina);
        $items=(clone $consulta)->offset($offset)->limit($limite)->get();
        return new PaginadorGrillaContinua($items,$total,$porPagina,$pagina,$offset,$this->gridVentana);
    }
}
