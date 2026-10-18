@component('layouts.portal', ['title' => $tipo === 'privacidad' ? 'Política de privacidad' : 'Términos de uso'])
<article class="portal-container mx-auto max-w-4xl space-y-5 px-5 py-10 leading-relaxed text-text-primary">
    <h1 class="font-display text-3xl font-bold text-blue-navy">{{ $tipo === 'privacidad' ? 'Política de privacidad' : 'Términos de uso' }}</h1>
    @if($tipo === 'privacidad')
        <p>Esta plataforma del Laboratorio de Invertebrados de la Escuela Politécnica Nacional utiliza los datos de registro para administrar cuentas y perfiles, tramitar solicitudes de depósito y mantener la trazabilidad de documentos y actuaciones. El catálogo público presenta únicamente los campos autorizados para divulgación; la autenticación no hace públicos los documentos privados de una solicitud.</p>
        <p>La sesión permite reconocer al usuario y aplicar sus permisos. Consulte la documentación institucional sobre tratamiento de datos personales y sus derechos en el <a class="underline text-science-blue" href="https://atenea.epn.edu.ec/handle/25000/2104">Acuerdo de responsabilidad para el uso de medios y servicios electrónicos y tratamiento de datos personales de la EPN</a>. El canal institucional para consultas sobre datos personales es <a class="underline" href="mailto:datos_personales@epn.edu.ec">datos_personales@epn.edu.ec</a>.</p>
        <p>Este aviso describe el uso de datos de la plataforma y remite a la normativa institucional; no reemplaza los documentos oficiales de la EPN.</p>
    @else
        <p>La exploración de los grupos taxonómicos, imágenes autorizadas, mapas y estadísticas agregadas es pública. Consultar registros individuales y descargar o exportar datos requiere una cuenta verificada con perfil de administración o curaduría. Los demás perfiles acceden a las funciones correspondientes a su actividad.</p>
        <p>Utilice la información de forma responsable, conserve su procedencia y cite la versión consultada. El contenido propio del laboratorio está disponible bajo <a class="underline text-science-blue" href="https://creativecommons.org/licenses/by/4.0/deed.es" rel="license">CC BY 4.0</a>: atribuya la autoría, enlace la licencia e indique los cambios. Los materiales de terceros conservan sus licencias originales. Esta licencia se refiere al contenido publicado, no a credenciales, datos personales ni documentos privados.</p>
        <p>Los datos científicos pueden actualizarse con la revisión curatorial. La clasificación publicada no representa por sí misma una filogenia ni una escala temporal.</p>
        <p>Como referencia institucional, consulte las <a class="underline text-science-blue" href="https://www.epn.edu.ec/administrativo/politicas-y-lineamientos-ti/">Políticas y lineamientos TI de la EPN</a>, que incluyen la Política de Uso de la Información, Activos de Información Institucional y Seguridad Informática.</p>
    @endif
    <a class="inline-block underline text-science-blue" href="{{ route('portal.catalogo', ['vista' => 'mapa']) }}">Volver a la distribución de registros</a>
</article>
@endcomponent
