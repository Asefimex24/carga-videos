<?php

header('Content-Type: application/json; charset=utf-8');

include '../../config/config.php';

try {

    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    $sql = "
        SELECT
            id_registro,
            sucursal,
            cliente,
            credito,
            fecha_registro,
            video1,
            video2,
            video3
        FROM videos
        ORDER BY fecha_desembolso DESC
    ";

    $stmt = $pdo->query($sql);

    echo json_encode([
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
