<?php

// 1. Eliminar el límite de tiempo de ejecución para este script
set_time_limit(0); 
ini_set('max_execution_time', 0);

// --- CONFIGURACIÓN ---
// $MODO_PRUEBA_LOCAL = false; // Cambiar a 'false' cuando tengas tus accesos de Hetzner


// ------------------------------------------------------------------
// 1. CONFIGURACIÓN DE ERRORES Y LIBRERÍAS
// ------------------------------------------------------------------
ini_set('display_errors', 0); // En producción 0, pon en 1 si necesitas depurar
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;

// ------------------------------------------------------------------
// 2. PARÁMETROS DE CONEXIÓN HETZNER STORAGE BOX
// ------------------------------------------------------------------
$sftp_host = 'u659197.your-storagebox.de'; // Reemplaza con tu host
$sftp_port = 22;
$sftp_user = 'u659197';                    // Reemplaza con tu usuario
$sftp_pass = 'G_3NQAnqQwkt6XedZWZqf6';      // Reemplaza con tu contraseña

// Directores locales temporales en Laragon
$temp_dir = __DIR__ . '/uploads_temp';
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0777, true);
}

// ------------------------------------------------------------------
// 3. PROCESAMIENTO DE FRAGMENTOS (CHUNKS) DE RESUMABLE.JS
// ------------------------------------------------------------------

// Verificación GET de Resumable.js (saber si un fragmento ya se subió)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $identifier = $_GET['resumableIdentifier'] ?? '';
    $filename = $_GET['resumableFilename'] ?? '';
    $chunkNumber = $_GET['resumableChunkNumber'] ?? '';

    $chunkFile = $temp_dir . '/' . $identifier . '.part' . $chunkNumber;

    if (file_exists($chunkFile)) {
        http_response_code(200);
    } else {
        http_response_code(204); // No content
    }
    exit();
}

// Recepción POST de fragmentos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $identifier  = $_POST['resumableIdentifier'] ?? '';
    $filename    = $_POST['resumableFilename'] ?? '';
    $chunkNumber = (int)($_POST['resumableChunkNumber'] ?? 1);
    $totalChunks = (int)($_POST['resumableTotalChunks'] ?? 1);

    // Guardar el fragmento actual en temporal
    $destFile = $temp_dir . '/' . $identifier . '.part' . $chunkNumber;
    move_uploaded_file($_FILES['file']['tmp_name'], $destFile);

    // Verificar si ya tenemos todos los fragmentos
    $allChunksExist = true;
    for ($i = 1; $i <= $totalChunks; $i++) {
        if (!file_exists($temp_dir . '/' . $identifier . '.part' . $i)) {
            $allChunksExist = false;
            break;
        }
    }

    // Si aún faltan fragmentos, respondemos OK a Resumable.js para que siga subiendo
    if (!$allChunksExist) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'chunk_uploaded', 'chunk' => $chunkNumber]);
        exit();
    }

    // --------------------------------------------------------------
    // 4. ENSAMBLADO LOCAL DEL ARCHIVO COMPLETO
    // --------------------------------------------------------------
    $finalLocalFile = $temp_dir . '/' . $identifier . '_' . $filename;
    $out = fopen($finalLocalFile, 'wb');

    if ($out) {
        for ($i = 1; $i <= $totalChunks; $i++) {
            $chunkPath = $temp_dir . '/' . $identifier . '.part' . $i;
            $in = fopen($chunkPath, 'rb');
            if ($in) {
                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }
                fclose($in);
            }
            @unlink($chunkPath); // Limpiar fragmento temporal
        }
        fclose($out);
    }

    // Responder a Resumable.js que el archivo individual está listo localmente
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'file_assembled',
        'file_path' => $finalLocalFile,
        'filename' => $filename
    ]);
    exit();
}

// ------------------------------------------------------------------
// 5. PROCESAMIENTO FINAL Y TRANSFERENCIA A HETZNER (SFTP)
// ------------------------------------------------------------------
// Esta sección se ejecuta cuando el cliente presiona el botón final con la metadata
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize') {
    
    header('Content-Type: application/json');



    // Recibir metadata del formulario
    $sucursal = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['sucursal'] ?? 'general');
    $cliente  = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['cliente'] ?? 'cliente');
    $id       = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['id'] ?? '000');
    $fecha    = date('Y-m-d_H-i');

    $file1_local = $_POST['file1_path'] ?? ''; // Ruta local del video de presentación
    $file2_local = $_POST['file2_path'] ?? ''; // Ruta local del video de contrato

    try {
        // Conexión SFTP usando phpseclib3
        $sftp = new SFTP($sftp_host, $sftp_port);

        if (!$sftp->login($sftp_user, $sftp_pass)) {
            throw new Exception("Error de autenticación en Hetzner Storage Box.");
        }

        // Definir estructura de carpetas remota
        // Estructura: subidas/SUCURSAL/FECHA_CLIENTE_ID/
        $remote_dir = "subidas/{$sucursal}/{$fecha}_{$cliente}_{$id}";

        if (!$sftp->file_exists($remote_dir)) {
            // El tercer argumento 'true' crea directorios anidados de forma recursiva
            if (!$sftp->mkdir($remote_dir, 0755, true)) {
                throw new Exception("No se pudo crear la carpeta remota: {$remote_dir}");
            }
        }

        // A. Generar y subir el archivo de metadata
        $contenido_txt = "=== REGISTRO DE INSPECCIÓN ===\n";
        $contenido_txt .= "Sucursal: {$sucursal}\n";
        $contenido_txt .= "Cliente: {$cliente}\n";
        $contenido_txt .= "ID Inspección: {$id}\n";
        $contenido_txt .= "Fecha de subida: " . date('Y-m-d H:i:s') . "\n";
        
        $sftp->put("{$remote_dir}/datos_registro.txt", $contenido_txt);

        // B. Subir el Video 1 (Presentación)
        if (!empty($file1_local) && file_exists($file1_local)) {
            $ext1 = pathinfo($file1_local, PATHINFO_EXTENSION);
            $remote_file1 = "{$remote_dir}/presentacion." . ($ext1 ?: 'mp4');
            
            $uploaded1 = $sftp->put($remote_file1, $file1_local, SFTP::SOURCE_LOCAL_FILE);
            if (!$uploaded1) {
                throw new Exception("Error al transferir el video de presentación a Hetzner.");
            }
            @unlink($file1_local); // Borrar el ensamblado local tras subir a la nube
        }

        // C. Subir el Video 2 (Firma de Contrato)
        if (!empty($file2_local) && file_exists($file2_local)) {
            $ext2 = pathinfo($file2_local, PATHINFO_EXTENSION);
            $remote_file2 = "{$remote_dir}/firma_contrato." . ($ext2 ?: 'mp4');
            
            $uploaded2 = $sftp->put($remote_file2, $file2_local, SFTP::SOURCE_LOCAL_FILE);
            if (!$uploaded2) {
                throw new Exception("Error al transferir el video de contrato a Hetzner.");
            }
            @unlink($file2_local); // Borrar el ensamblado local tras subir a la nube
        }

        echo json_encode([
            'success' => true,
            'message' => 'Proceso completado con éxito. Videos y registros almacenados en Hetzner Storage Box.',
            'remote_folder' => $remote_dir
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => $e->getMessage()
        ]);
    }
    exit();
}