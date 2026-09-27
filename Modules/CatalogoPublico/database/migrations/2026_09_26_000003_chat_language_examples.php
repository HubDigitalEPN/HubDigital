<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'saludo' => ['buenas', 'qué tal', 'ayuda'],
            'deposito' => ['quiero depositar', 'deseo depositar', 'depositar especímenes'],
            'donacion' => ['quiero donar', 'deseo donar', 'donar especímenes'],
            'documentos' => ['q papeles necesito para dejar unas muestras', 'papeles para dejar muestras'],
        ] as $slug => $phrases) {
            $id = DB::table('divulgacion.chat_nodes')->where('slug', $slug)->value('id');
            if ($id === null) {
                continue;
            }
            foreach ($phrases as $phrase) {
                DB::table('divulgacion.chat_aliases')->insertOrIgnore(['node_id' => $id, 'phrase' => $phrase]);
            }
        }
        foreach (['muestras' => 'especimen', 'ejemplares' => 'especimen', 'bichos' => 'especimen', 'papeles' => 'documentos'] as $term => $canonical) {
            DB::table('divulgacion.chat_synonyms')->insertOrIgnore(['term' => $term, 'canonical' => $canonical]);
        }
    }

    public function down(): void
    {
        // Los aliases pudieron ser editados por curaduría; no se borran en una reversión automática.
    }
};
