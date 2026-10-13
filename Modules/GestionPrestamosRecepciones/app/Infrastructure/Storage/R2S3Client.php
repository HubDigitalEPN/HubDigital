<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente minimo de la API S3 de Cloudflare R2 para objetos privados.
 *
 * Firma cada solicitud con AWS Signature Version 4. El secreto permanece en el
 * servidor y nunca se incluye en mensajes de error, logs ni respuestas HTTP.
 */
final class R2S3Client
{
    /** @var callable|null Conexiones TLS reutilizadas sólo dentro de este cliente privado. */
    private mixed $transporte = null;

    /** @param array<string, mixed> $configuracion */
    public function __construct(
        private readonly array $configuracion,
        private readonly ?\DateTimeImmutable $ahoraFijo = null,
    ) {}

    public function put(string $ruta, string $contenido, string $mime): void
    {
        $respuesta = $this->enviar('PUT', $ruta, $contenido, $mime);
        $this->exigirExito($respuesta, 'guardar');
    }

    public function get(string $ruta): string
    {
        $respuesta = $this->enviar('GET', $ruta);
        $this->exigirExito($respuesta, 'leer');

        return $respuesta->body();
    }

    /** @return resource */
    public function readStream(string $ruta)
    {
        $respuesta = $this->enviar('GET', $ruta);
        $this->exigirExito($respuesta, 'leer');
        $stream = $respuesta->toPsrResponse()->getBody()->detach();
        if (! is_resource($stream)) {
            throw new \RuntimeException('R2 no entrego un flujo de lectura valido.');
        }

        return $stream;
    }

    public function exists(string $ruta): bool
    {
        $respuesta = $this->enviar('HEAD', $ruta);
        if ($respuesta->status() === 404) {
            return false;
        }
        $this->exigirExito($respuesta, 'consultar');

        return true;
    }

    /** @return array<string, mixed> */
    public function head(string $ruta): array
    {
        $respuesta = $this->enviar('HEAD', $ruta);
        $this->exigirExito($respuesta, 'consultar');

        return [
            'content_type' => $respuesta->header('Content-Type'),
            'content_length' => is_numeric($respuesta->header('Content-Length'))
                ? (int) $respuesta->header('Content-Length')
                : null,
            'etag' => $respuesta->header('ETag'),
        ];
    }

    public function delete(string $ruta): void
    {
        $respuesta = $this->enviar('DELETE', $ruta);
        $this->exigirExito($respuesta, 'eliminar');
    }

    /**
     * @return array{objetos: list<array{ruta: string, tamano: int, etag: ?string}>, truncado: bool, cursor: ?string}
     */
    public function listar(string $prefijo, ?string $cursor = null, int $limite = 1000): array
    {
        $consulta = [
            'list-type' => '2',
            'prefix' => $prefijo,
            'max-keys' => (string) max(1, min(1000, $limite)),
        ];
        if ($cursor !== null && $cursor !== '') {
            $consulta['continuation-token'] = $cursor;
        }

        $respuesta = $this->enviar('GET', '', '', null, $consulta);
        $this->exigirExito($respuesta, 'listar');
        $xml = @simplexml_load_string($respuesta->body());
        if ($xml === false) {
            throw new \RuntimeException('R2 entrego un inventario XML invalido.');
        }

        $objetos = [];
        foreach ($xml->Contents as $objeto) {
            $objetos[] = [
                'ruta' => (string) $objeto->Key,
                'tamano' => (int) $objeto->Size,
                'etag' => trim((string) $objeto->ETag, '"'),
            ];
        }

        return [
            'objetos' => $objetos,
            'truncado' => strtolower((string) $xml->IsTruncated) === 'true',
            'cursor' => isset($xml->NextContinuationToken) ? (string) $xml->NextContinuationToken : null,
        ];
    }

    /** @param array<string, string> $consulta */
    private function enviar(
        string $metodo,
        string $ruta,
        string $contenido = '',
        ?string $mime = null,
        array $consulta = [],
    ): Response {
        $endpoint = rtrim((string) ($this->configuracion['endpoint'] ?? ''), '/');
        $bucket = trim((string) ($this->configuracion['bucket'] ?? ''), '/');
        $clave = (string) ($this->configuracion['access_key_id'] ?? '');
        $secreto = (string) ($this->configuracion['secret_access_key'] ?? '');
        $region = 'auto';

        if ($endpoint === '' || $bucket === '' || $clave === '' || $secreto === '') {
            throw new \RuntimeException('La configuracion S3 de R2 esta incompleta.');
        }

        $rutaCodificada = implode('/', array_map('rawurlencode', explode('/', ltrim($ruta, '/'))));
        $url = $endpoint.'/'.rawurlencode($bucket).($rutaCodificada !== '' ? '/'.$rutaCodificada : '');
        $componentes = parse_url($url);
        if (! is_array($componentes) || ! isset($componentes['scheme'], $componentes['host'], $componentes['path'])) {
            throw new \RuntimeException('El endpoint configurado para R2 no es valido.');
        }
        if (strtolower((string) $componentes['scheme']) !== 'https') {
            throw new \RuntimeException('El endpoint de R2 debe usar HTTPS.');
        }

        $host = (string) $componentes['host'];
        if (isset($componentes['port'])) {
            $host .= ':'.$componentes['port'];
        }
        $ahora = ($this->ahoraFijo ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');
        $fecha = substr($ahora, 0, 8);
        $hashContenido = hash('sha256', $contenido);
        $headersCanonicos = "host:{$host}\nx-amz-content-sha256:{$hashContenido}\nx-amz-date:{$ahora}\n";
        $headersFirmados = 'host;x-amz-content-sha256;x-amz-date';
        ksort($consulta);
        $consultaCanonica = implode('&', array_map(
            static fn (string $clave): string => rawurlencode($clave).'='.rawurlencode($consulta[$clave]),
            array_keys($consulta),
        ));
        $solicitudCanonica = implode("\n", [
            $metodo,
            (string) $componentes['path'],
            $consultaCanonica,
            $headersCanonicos,
            $headersFirmados,
            $hashContenido,
        ]);
        $alcance = "{$fecha}/{$region}/s3/aws4_request";
        $cadenaFirma = "AWS4-HMAC-SHA256\n{$ahora}\n{$alcance}\n".hash('sha256', $solicitudCanonica);
        $claveFecha = hash_hmac('sha256', $fecha, 'AWS4'.$secreto, true);
        $claveRegion = hash_hmac('sha256', $region, $claveFecha, true);
        $claveServicio = hash_hmac('sha256', 's3', $claveRegion, true);
        $claveFirma = hash_hmac('sha256', 'aws4_request', $claveServicio, true);
        $firma = hash_hmac('sha256', $cadenaFirma, $claveFirma);

        $headers = [
            'Authorization' => "AWS4-HMAC-SHA256 Credential={$clave}/{$alcance}, SignedHeaders={$headersFirmados}, Signature={$firma}",
            'Host' => $host,
            'x-amz-content-sha256' => $hashContenido,
            'x-amz-date' => $ahora,
        ];

        $maxIntentos = max(1, min(3, (int) ($this->configuracion['max_attempts'] ?? 3)));
        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $peticion = Http::timeout(max(1, (int) ($this->configuracion['timeout_seconds'] ?? 45)))
                    ->setHandler($this->transporte ??= \GuzzleHttp\Utils::chooseHandler())
                    ->connectTimeout(max(1, (int) ($this->configuracion['connect_timeout_seconds'] ?? 10)))
                    ->withUserAgent('HubDigital-EPN/1.0 almacenamiento-privado')
                    ->withHeaders($headers);

                if ($metodo === 'PUT') {
                    $peticion = $peticion->withBody($contenido, $mime ?: 'application/octet-stream');
                }

                $respuesta = $peticion->send($metodo, $url.($consultaCanonica !== '' ? '?'.$consultaCanonica : ''));
                $reintentable = $respuesta->status() === 429 || $respuesta->serverError();
                if (! $reintentable || $intento === $maxIntentos) {
                    return $respuesta;
                }
            } catch (Throwable) {
                if ($intento === $maxIntentos) {
                    throw new \RuntimeException('No fue posible conectar de forma segura con R2.');
                }
            }

            usleep(150_000 * $intento);
        }

        throw new \RuntimeException('No fue posible completar la operacion en R2.');
    }

    private function exigirExito(Response $respuesta, string $operacion): void
    {
        if (! $respuesta->successful()) {
            throw new \RuntimeException("R2 rechazo la operacion de {$operacion} (HTTP {$respuesta->status()}).");
        }
    }
}
