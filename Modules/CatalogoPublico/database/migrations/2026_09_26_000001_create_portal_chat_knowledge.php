<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // pg_trgm ya se utiliza en el inventario. La búsqueda mantiene un fallback sin extensiones.
            foreach (['pg_trgm', 'unaccent'] as $extension) {
                $available = DB::table('pg_available_extensions')->where('name', $extension)->exists();
                if ($available) {
                    try {
                        DB::statement('CREATE EXTENSION IF NOT EXISTS '.$extension);
                    } catch (\Throwable $error) {
                        report($error);
                    }
                }
            }
        }

        Schema::create('divulgacion.chat_nodes', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('divulgacion.chat_nodes')->nullOnDelete();
            $table->string('title', 160);
            $table->text('answer');
            $table->string('action', 40)->nullable();
            $table->string('status', 16)->default('draft');
            $table->boolean('needs_curator_review')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('uses')->default(0);
            $table->unsignedInteger('helpful')->default(0);
            $table->unsignedInteger('unhelpful')->default(0);
            $table->timestamps();
            $table->index(['status', 'position']);
        });

        Schema::create('divulgacion.chat_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('node_id')->constrained('divulgacion.chat_nodes')->cascadeOnDelete();
            $table->string('phrase', 240);
            $table->unique(['node_id', 'phrase']);
        });

        Schema::create('divulgacion.chat_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('node_id')->constrained('divulgacion.chat_nodes')->cascadeOnDelete();
            $table->text('text');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('uses')->default(0);
            $table->unsignedInteger('helpful')->default(0);
            $table->unsignedInteger('unhelpful')->default(0);
        });

        Schema::create('divulgacion.chat_synonyms', function (Blueprint $table): void {
            $table->id();
            $table->string('term', 80)->unique();
            $table->string('canonical', 80);
        });

        Schema::create('divulgacion.chat_transitions', function (Blueprint $table): void {
            $table->foreignId('from_id')->constrained('divulgacion.chat_nodes')->cascadeOnDelete();
            $table->foreignId('to_id')->constrained('divulgacion.chat_nodes')->cascadeOnDelete();
            $table->unsignedInteger('uses')->default(0);
            $table->primary(['from_id', 'to_id']);
        });

        Schema::create('divulgacion.chat_unmatched', function (Blueprint $table): void {
            $table->id();
            $table->string('question_hash', 64)->unique();
            $table->string('sample', 240);
            $table->unsignedInteger('occurrences')->default(1);
            $table->decimal('best_score', 5, 4)->default(0);
            $table->string('status', 16)->default('pending');
            $table->timestamps();
            $table->index(['status', 'occurrences']);
        });

        $nodes = [
            ['saludo', null, 'Saludo', '¡Hola! Puedo ayudarte con depósitos, documentación y el catálogo público. Cuéntame qué necesitas.', null, 'hola|buenos dias|buenas tardes|buenas noches|que puedes hacer', 0],
            ['deposito', null, 'Depósito temporal', 'Claro. Para iniciar un depósito temporal, crea una cuenta de depositante y abre una solicitud. El formulario te guía por los datos y documentos antes de enviarla a curaduría.', 'DEPOSIT_LINK', 'quiero hacer un deposito|tengo muestras y quiero entregarlas|como deposito especimenes|deposito temporal|dejar ejemplares', 1],
            ['donacion', null, 'Donación', 'Si quieres donar material, selecciona «Donación» al iniciar la solicitud. El equipo curatorial revisará el expediente antes de coordinar la recepción física.', 'DEPOSIT_LINK', 'quiero donar especimenes|donacion|regalar muestras|transferir material', 2],
            ['documentos', 'deposito', 'Documentos del depósito', 'La solicitud se genera y firma dentro de HubDigital. Adjunta la autorización de recolección y la guía de movilización cuando correspondan; el formulario te mostrará los requisitos aplicables a tu caso.', 'DEPOSIT_LINK', 'que documentos necesito|que papeles tengo que mandar|requisitos|que tengo que adjuntar|que permisos necesito|que documetos nesecito para el deposito', 3],
            ['procedencia', 'documentos', 'Procedencia del material', 'El expediente solicita información sobre la procedencia del material. Según el trámite, el sistema puede pedir evidencia de procedencia lícita, cesión o justificación institucional.', null, 'de donde deben venir las muestras|procedencia|permiso de recoleccion|guia de movilizacion', 4],
            ['revision', 'deposito', 'Revisión del expediente', 'Después de enviar la solicitud, curaduría revisa la documentación y las condiciones de custodia. Puedes consultar el estado desde «Mis solicitudes».', 'MY_REQUESTS_LINK', 'ya mande mis documentos que sigue|que pasa despues|como revisan mi solicitud|estado de mi solicitud|donde veo el estado', 5],
            ['entrega', 'revision', 'Entrega física', 'Espera las instrucciones del equipo curatorial antes de trasladar los especímenes. La entrega física se coordina después de la revisión documental.', null, 'donde llevo los bichos|puedo llevarlos manana|cuando entrego las muestras|entrega fisica|donde entrego especimenes', 6],
            ['catalogo', null, 'Catálogo público', 'Puedes buscar los registros divulgados por código, taxonomía o geografía en el catálogo digital. Dime qué taxón o código buscas y revisaré los datos públicos.', 'CATALOG_LINK', 'buscar especimenes|consultar catalogo|tienen registros de|buscar taxon|coleccion de invertebrados', 7],
            ['acceso', null, 'Acceso al sistema', 'Puedes crear una cuenta o iniciar sesión desde el portal. Si ya eres depositante, encontrarás tus solicitudes en tu cuenta.', 'LOGIN_LINK', 'como creo cuenta|no puedo ingresar|iniciar sesion|acceso al sistema|olvide mi clave', 8],
            ['contacto', null, 'Contacto', 'Si necesitas orientación institucional, escribe a adrian.troya@epn.edu.ec. Incluye una descripción breve de tu consulta.', 'CONTACT_LINK', 'como contacto al laboratorio|correo del laboratorio|hablar con curaduria|necesito ayuda', 9],
            ['limites_pendiente', 'deposito', 'Otros límites aplicables', 'La información sobre este caso requiere revisión de curaduría.', null, 'otros limites|restricciones especiales', 10],
        ];

        $ids = [];
        foreach ($nodes as [$slug, $parent, $title, $answer, $action, $aliases, $position]) {
            $id = DB::table('divulgacion.chat_nodes')->insertGetId([
                'slug' => $slug,
                'parent_id' => $parent ? $ids[$parent] : null,
                'title' => $title,
                'answer' => $answer,
                'action' => $action,
                'status' => $slug === 'limites_pendiente' ? 'draft' : 'published',
                'needs_curator_review' => $slug === 'limites_pendiente',
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[$slug] = $id;
            foreach (explode('|', $aliases) as $phrase) {
                DB::table('divulgacion.chat_aliases')->insert(['node_id' => $id, 'phrase' => $phrase]);
            }
        }

        foreach (['muestra' => 'especimen', 'ejemplar' => 'especimen', 'bicho' => 'especimen', 'papeles' => 'documentos', 'adjuntar' => 'documentos', 'mandar' => 'enviar', 'dejar' => 'deposito', 'entregar' => 'deposito'] as $term => $canonical) {
            DB::table('divulgacion.chat_synonyms')->insert(['term' => $term, 'canonical' => $canonical]);
        }

        foreach ([
            'saludo' => [
                '¡Hola! Cuéntame qué necesitas. Puedo orientarte sobre depósitos, documentos y el catálogo público.',
                'Hola. Si vas a depositar material o buscas un registro público, dime por dónde quieres empezar.',
            ],
            'deposito' => [
                'Claro. Abre una solicitud como depositante para registrar el material. HubDigital te indicará qué datos y documentos corresponden antes de enviarla a curaduría.',
                'Te ayudo con el depósito temporal. Crea tu solicitud en el portal; allí puedes completar el expediente por etapas y enviarlo a revisión.',
            ],
        ] as $slug => $variants) {
            foreach ($variants as $text) {
                DB::table('divulgacion.chat_variants')->insert(['node_id' => $ids[$slug], 'text' => $text]);
            }
        }
    }

    public function down(): void
    {
        foreach (['chat_unmatched', 'chat_transitions', 'chat_synonyms', 'chat_variants', 'chat_aliases', 'chat_nodes'] as $name) {
            Schema::dropIfExists('divulgacion.'.$name);
        }
    }
};
