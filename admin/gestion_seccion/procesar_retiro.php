<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

require_once(__DIR__ . '/../../funciones/functions.php');

cargarPermisosUsuario();
verificarPermiso('secciones');

$usuario_id = (int)$_POST['id_usuario'];
$seccion_id = (int)$_POST['id_seccion'];

$resultado = retirarEstudiante($db, $seccion_id, $usuario_id);

if ($resultado['success']) $_SESSION['success'] = $resultado['message'];
else $_SESSION['error'] = $resultado['message'];

if ($seccion_id) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Redireccionando...</title>
    </head>
    <body>
        <form id="redirectPost" method="POST" action="ver_seccion.php">
            <input type="hidden" name="id" value="<?= $seccion_id ?>">
        </form>
        <script>
            document.getElementById('redirectPost').submit();
        </script>
    </body>
    </html>
    <?php
    exit();
} else {
    header("Location: gestion_seccion.php");
    exit();
}
?>