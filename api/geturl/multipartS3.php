
<?php
// multipartR2_Bucket

header('Content-Type: application/json; charset=utf-8');

include '../../config/config.php';
require '../../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// =============================================
// FUNCIONES
// =============================================

function limpiarSegmentoRuta(string $texto): string {
    $texto = mb_strtoupper(trim($texto), 'UTF-8');

    $reemplazos = [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I',
        'Ó' => 'O', 'Ú' => 'U',
        'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I',
        'Ö' => 'O', 'Ü' => 'U', 'Ñ' => 'N',
        ' ' => '_'
    ];

    $texto = strtr($texto, $reemplazos);
    $texto = preg_replace('/[^A-Z0-9_-]/', '', $texto);

    return preg_replace('/_+/', '_', $texto);
}

// =============================================
// CONFIGURACIÓN CLOUDFLARE R2
// =============================================

$bucket = $r2Bucket;

$s3 = new S3Client([
    'version' => 'latest',
    'region' => 'auto',

    'endpoint' => $r2Endp,

    'use_path_style_endpoint' => true,

    'credentials' => [
        'key' => $r2Key,
        'secret' => $r2Sec,
    ],

    'signature_version' => 'v4',
]);

// =============================================
// PROCESAR SOLICITUD
// =============================================

$input = json_decode(file_get_contents('php://input'), true);

$accion = $input['accion'] ?? '';

try {

    // =============================================
    // ACCIÓN 1: INICIAR MULTIPART UPLOAD
    // =============================================

    if ($accion === 'iniciar') {

        $sucursal = limpiarSegmentoRuta(
            $input['sucursal'] ?? 'GENERAL'
        );

        $cliente = limpiarSegmentoRuta(
            $input['cliente'] ?? ''
        );

        $credito = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            $input['credito'] ?? ''
        );

        $fechaDesembolso = preg_replace(
            '/[^0-9-]/',
            '',
            $input['fechaDesem'] ?? date('Y-m-d')
        );

        $ext1 = strtolower(
            pathinfo(
                $input['file1Name'] ?? 'video1.mp4',
                PATHINFO_EXTENSION
            )
        ) ?: 'mp4';

        $ext2 = strtolower(
            pathinfo(
                $input['file2Name'] ?? 'video2.mp4',
                PATHINFO_EXTENSION
            )
        ) ?: 'mp4';

        $type1 = $input['file1Type'] ?? 'video/mp4';
        $type2 = $input['file2Type'] ?? 'video/mp4';

        $parts1 = (int)($input['parts1'] ?? 1);
        $parts2 = (int)($input['parts2'] ?? 1);

        if (
            empty($cliente) ||
            empty($credito) ||
            empty($fechaDesembolso)
        ) {
            throw new Exception(
                'El cliente, número de crédito y fecha son obligatorios.'
            );
        }

        // Validación de partes
        if (
            $parts1 < 1 || $parts1 > 10000 ||
            $parts2 < 1 || $parts2 > 10000
        ) {
            throw new Exception('Cantidad de fragmentos inválida.');
        }

        // Preparar cada video
        $prepararVideo = function (
            string $key,
            string $contentType,
            int $totalParts
        ) use ($s3, $bucket) {

            $init = $s3->createMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $key,
                'ContentType' => $contentType,
            ]);

            $uploadId = $init['UploadId'];

            $urls = [];

            for (
                $partNumber = 1;
                $partNumber <= $totalParts;
                $partNumber++
            ) {

                $cmd = $s3->getCommand('UploadPart', [
                    'Bucket' => $bucket,
                    'Key' => $key,
                    'UploadId' => $uploadId,
                    'PartNumber' => $partNumber,
                ]);

                $request = $s3->createPresignedRequest(
                    $cmd,
                    '+45 minutes'
                );

                $urls[] = [
                    'partNumber' => $partNumber,
                    'url' => (string)$request->getUri(),
                ];
            }

            return [
                'key' => $key,
                'uploadId' => $uploadId,
                'parts' => $urls,
            ];
        };

        $key1 = "videos/{$sucursal}/{$cliente}/{$credito}/presentacion_{$fechaDesembolso}.{$ext1}";

        $key2 = "videos/{$sucursal}/{$cliente}/{$credito}/contrato_{$fechaDesembolso}.{$ext2}";

        $video1 = $prepararVideo(
            $key1,
            $type1,
            $parts1
        );

        $video2 = $prepararVideo(
            $key2,
            $type2,
            $parts2
        );

        echo json_encode([
            'ok' => true,
            'video1' => $video1,
            'video2' => $video2,
        ]);

        exit;
    }

    // =============================================
    // ACCIÓN 2: COMPLETAR MULTIPART UPLOAD
    // =============================================

    if ($accion === 'completar') {

        $key = $input['key'] ?? '';
        $uploadId = $input['uploadId'] ?? '';
        $parts = $input['parts'] ?? [];

        if (
            empty($key) ||
            empty($uploadId) ||
            empty($parts) ||
            !is_array($parts)
        ) {
            throw new Exception('Datos de subida incompletos.');
        }

        // Ordenar fragmentos
        usort($parts, fn($a, $b) =>
            (int)$a['PartNumber'] <=> (int)$b['PartNumber']
        );

        // Validar numeración y ETags
        foreach ($parts as $index => $part) {

            if (
                (int)($part['PartNumber'] ?? 0) !== $index + 1 ||
                empty($part['ETag'])
            ) {
                throw new Exception('Fragmentos inválidos.');
            }
        }

        $result = $s3->completeMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => $parts
            ],
        ]);

        echo json_encode([
            'ok' => true,
            'key' => $key,
            'location' => $result['Location'] ?? null,
        ]);

        exit;
    }

    // =============================================
    // ACCIÓN 3: ABORTAR MULTIPART UPLOAD
    // =============================================

    if ($accion === 'abortar') {

        $key = $input['key'] ?? '';
        $uploadId = $input['uploadId'] ?? '';

        if (!empty($key) && !empty($uploadId)) {

            $s3->abortMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $key,
                'UploadId' => $uploadId,
            ]);
        }

        echo json_encode(['ok' => true]);

        exit;
    }

    throw new Exception('Acción no válida.');

} catch (Throwable $e) {

    error_log('R2 Multipart Error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Error procesando la operación de almacenamiento.'
    ]);
}
