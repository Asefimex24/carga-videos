<?php

$id = intval($_GET['id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'presentacion';

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Reproductor</title>
</head>

<body>

<h2>Video</h2>

<video
    width="100%"
    height="auto"
    controls
    preload="metadata"
>
    <source
        src="stream.php?id=<?=$id?>&tipo=<?=$tipo?>"
        type="video/mp4">
</video>

</body>
</html>