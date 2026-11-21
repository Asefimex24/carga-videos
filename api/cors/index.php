<?php
include '../../config/config.php';

// configurar_cors.php (Ejecutar 1 vez y luego eliminar)
require '../../vendor/autoload.php';

use Aws\S3\S3Client;

$s3 = new S3Client([
    'version'                 => 'latest',
    'region'                  => $objReg,
    'endpoint'                => $objEndp,
    'use_path_style_endpoint' => false,
    'credentials'             => [
        'key'    => $objKey,
        'secret' => $objSec,
    ],
]);

$s3->putBucketCors([
    'Bucket' => $objBucket,
    'CORSConfiguration' => [
        'CORSRules' => [
            [
                'AllowedOrigins' => ['*'], // En producción cambia '*' por ['https://tudominio.com']
                'AllowedMethods' => ['GET', 'HEAD', 'PUT', 'POST'],
                'AllowedHeaders' => ['*'],
                'ExposeHeaders'  => ['ETag'],
                'MaxAgeSeconds'  => 3600,
            ],
        ],
    ],
]);

echo "CORS configurado correctamente.";