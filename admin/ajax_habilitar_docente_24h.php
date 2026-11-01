<?php
require_once __DIR__ . '/../funciones/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión para realizar esta acción.']);
    exit();
}

if (!isAdmin() && !tienePermiso('mensajeria') && !tienePermiso('secretaria')) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos de administrador para habilitar prórrogas.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no permitido.']);
    exit();
}

$id_amonestacion = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id_amonestacion <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de amonestación no válido o ausente.']);
    exit();
}

$admin_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 1;

$resultado = habilitarDocente24Horas($id_amonestacion, $admin_id);

echo json_encode($resultado);
exit();
