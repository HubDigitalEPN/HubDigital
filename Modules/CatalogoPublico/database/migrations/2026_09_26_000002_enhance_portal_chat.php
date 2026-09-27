<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divulgacion.chat_settings', function (Blueprint $table): void {
            $table->string('key', 60)->primary();
            $table->text('value');
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('divulgacion.chat_metrics_daily', function (Blueprint $table): void {
            $table->date('day')->primary();
            $table->unsignedInteger('conversations')->default(0);
            $table->unsignedInteger('messages')->default(0);
            $table->unsignedInteger('resolved')->default(0);
            $table->unsignedInteger('unknown')->default(0);
            $table->unsignedInteger('catalog_queries')->default(0);
            $table->unsignedInteger('feedback_positive')->default(0);
            $table->unsignedInteger('feedback_negative')->default(0);
            $table->double('confidence_sum')->default(0);
            $table->double('latency_ms_sum')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
        Schema::table('divulgacion.chat_unmatched', function (Blueprint $table): void {
            $table->string('group_key', 64)->nullable()->index();
            $table->foreignId('best_node_id')->nullable()->constrained('divulgacion.chat_nodes')->nullOnDelete();
        });
        DB::table('divulgacion.chat_settings')->insertOrIgnore([
            'key' => 'ranking',
            'value' => json_encode(config('chatbot.ranking'), JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
        DB::table('divulgacion.chat_unmatched')->whereNull('group_key')->update(['group_key' => DB::raw('question_hash')]);

        $definitions = [
            ['deposito_requisitos', 'deposito', 'Requisitos del depósito', 'Los requisitos dependen del tipo de trámite, la procedencia y la situación regulatoria declarada. El formulario indica los documentos aplicables antes del envío a curaduría.', 'qué requisitos hay|requisitos para deposito|que necesito para depositar'],
            ['donacion_requisitos', 'donacion', 'Requisitos de la donación', 'Selecciona «Donación» al iniciar la solicitud. El formulario indica los datos y documentos aplicables; curaduría revisa el expediente antes de coordinar la recepción.', 'qué requisitos hay|requisitos para donacion|que necesito para donar'],
            ['documentos_permisos', 'documentos', 'Permisos y guía de movilización', 'Adjunta la autorización de recolección y la guía de movilización cuando correspondan a tu trámite. El formulario mostrará los requisitos aplicables a tu caso.', 'permiso de recoleccion|guia de movilizacion|que permiso debo adjuntar'],
            ['documentos_firma', 'documentos', 'Firma de la documentación', 'La solicitud se genera y firma dentro de HubDigital. El sistema comprueba los PDF firmados que se adjuntan antes de continuar con el expediente.', 'firma de documentos|pdf firmado|como firmo la solicitud'],
            ['revision_estado', 'revision', 'Consultar estado', 'Puedes revisar el avance de tu expediente en «Mis solicitudes». Si curaduría solicita una corrección, atiéndela desde tu cuenta.', 'donde veo mi solicitud|consultar estado|seguimiento de solicitud'],
            ['catalogo_busqueda', 'catalogo', 'Buscar en el catálogo', 'El catálogo público permite buscar registros divulgados por código, taxonomía o geografía. Los datos restringidos no aparecen en las respuestas del asistente.', 'buscar por codigo|buscar por taxon|buscar por provincia'],
            ['acceso_registro', 'acceso', 'Crear una cuenta', 'Puedes crear una cuenta desde el portal para iniciar una solicitud de depósito o donación.', 'como me registro|crear cuenta|registrarme'],
        ];
        foreach ($definitions as [$slug, $parentSlug, $title, $answer, $aliases]) {
            $parent = DB::table('divulgacion.chat_nodes')->where('slug', $parentSlug)->value('id');
            if ($parent === null) {
                continue;
            }
            DB::table('divulgacion.chat_nodes')->insertOrIgnore([
                'slug' => $slug, 'parent_id' => $parent, 'title' => $title, 'answer' => $answer,
                'status' => 'published', 'position' => 50, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $id = DB::table('divulgacion.chat_nodes')->where('slug', $slug)->value('id');
            foreach (explode('|', $aliases) as $phrase) {
                DB::table('divulgacion.chat_aliases')->insertOrIgnore(['node_id' => $id, 'phrase' => $phrase]);
            }
        }
    }

    public function down(): void
    {
        // El contenido curatorial y la analítica no se eliminan automáticamente.
        throw new RuntimeException('La reversión de conocimiento y métricas requiere una decisión explícita de curaduría.');
    }
};
