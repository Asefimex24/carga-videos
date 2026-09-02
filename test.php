<?php
// test_sftp.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;

// --- REEMPLAZA CON TUS DATOS DE HETZNER ---
$host = 'u659197.your-storagebox.de'; 
$port = 22;
$user = 'u659197';                   
$pass = 'G_3NQAnqQwkt6XedZWZqf6';     

echo "<pre>";
echo "1. Intentando conectar a $host en el puerto $port...\n";

$sftp = new SFTP($host, $port);

if (!$sftp->login($user, $pass)) {
    echo "❌ ERROR: No se pudo autenticar en Hetzner. Revisa tu usuario o contraseña.\n";
    exit();
}

echo "✅ 2. Autenticación exitosa en Hetzner Storage Box!\n";
echo "Directorio actual remoto: " . $sftp->pwd() . "\n\n";

echo "3. Creando carpeta de prueba 'subidas/test_directo'...\n";
if ($sftp->mkdir('subidas/test_directo', 0755, true)) {
    echo "✅ Carpeta creada correctamente.\n";
    
    echo "4. Creando archivo de prueba 'test.txt'...\n";
    $sftp->put('subidas/test_directo/test.txt', 'Prueba de subida exitosa desde PHP');
    echo "✅ Archivo 'test.txt' subido con éxito.\n";
} else {
    echo "❌ ERROR: No se pudo crear el directorio remoto.\n";
}

echo "\n5. Listado de directorios en el servidor remoto:\n";
print_r($sftp->nlist());

echo "</pre>";