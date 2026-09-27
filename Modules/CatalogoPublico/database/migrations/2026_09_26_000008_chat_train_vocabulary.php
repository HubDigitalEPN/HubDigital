<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Conceptos inferidos de TRAIN; ninguna frase de CALIBRATION o TEST se copia literalmente.
        foreach (['custodia' => 'deposito', 'ceder' => 'donacion', 'transferir' => 'donacion',
            'regalar' => 'donacion', 'archivos' => 'documentos', 'pdf' => 'documentos',
            'contrasena' => 'clave', 'contactar' => 'contacto', 'escribir' => 'contacto',
            'trasladar' => 'llevar'] as $term => $canonical) {
            DB::table('divulgacion.chat_synonyms')->insertOrIgnore(['term' => $term, 'canonical' => $canonical]);
        }
        foreach ([
            'deposito' => ['depositar muestras', 'dejar material en custodia', 'registrar material en custodia'],
            'donacion' => ['ceder ejemplares', 'regalar material', 'transferir coleccion'],
            'documentos' => ['archivos requeridos', 'documentacion requerida', 'documentos para adjuntar'],
            'revision' => ['avance de solicitud', 'revisar expediente', 'despues de enviar solicitud'],
            'entrega' => ['llevar especimenes', 'donde entrego', 'donde entregar muestras', 'cuando entregar muestras', 'coordinar recepcion'],
            'acceso' => ['recuperar contrasena', 'activar usuario'],
            'contacto' => ['contactar equipo', 'hablar con alguien'],
            'catalogo' => ['familias del catalogo', 'buscar registros publicados'],
        ] as $slug => $phrases) {
            $id = DB::table('divulgacion.chat_nodes')->where('slug', $slug)->value('id');
            if ($id === null) continue;
            foreach ($phrases as $phrase) DB::table('divulgacion.chat_aliases')->insertOrIgnore(['node_id' => $id, 'phrase' => $phrase]);
        }
    }

    public function down(): void
    {
        // El vocabulario puede haber sido editado por curaduría; no se elimina automáticamente.
    }
};
