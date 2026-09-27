<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'archivo' => 'documentos', 'archivos' => 'documentos',
            'envie' => 'enviar', 'ocurre' => 'sigue',
            'expediente' => 'solicitud', 'reviso' => 'consultar',
            'acercar' => 'llevar', 'acercarme' => 'llevar',
        ] as $term => $canonical) {
            DB::table('divulgacion.chat_synonyms')->insertOrIgnore(['term' => $term, 'canonical' => $canonical]);
        }
        foreach ([
            'revision' => ['consultar mi expediente', 'ya envie solicitud que sigue'],
            'entrega' => ['llevar muestras manana'],
        ] as $slug => $phrases) {
            $id = DB::table('divulgacion.chat_nodes')->where('slug', $slug)->value('id');
            if ($id === null) {
                continue;
            }
            foreach ($phrases as $phrase) {
                DB::table('divulgacion.chat_aliases')->insertOrIgnore(['node_id' => $id, 'phrase' => $phrase]);
            }
        }
    }

    public function down(): void
    {
        // Los términos curatoriales no se eliminan sin revisión humana.
    }
};
