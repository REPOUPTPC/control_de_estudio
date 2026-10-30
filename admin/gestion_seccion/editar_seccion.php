<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulopag = "Editar Sección";
require_once(__DIR__ . '/../../funciones/functions.php');

// Validar que la petición sea estrictamente por método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso no permitido - UPTPC</title>
        <link rel="stylesheet" href="../../css/bootstrap.min.css">
        <link rel="stylesheet" href="../../vendor/fontawesome-free/css/all.min.css">
        <style>
            body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            .error-card { max-width: 540px; margin: 80px auto; border: none; border-radius: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card error-card shadow p-4 text-center">
                <div class="text-danger mb-3">
                    <i class="fas fa-ban fa-4x text-danger opacity-75"></i>
                </div>
                <h4 class="text-dark font-weight-bold mb-2">Método de Acceso No Permitido</h4>
                <p class="text-muted mb-4">
                    Por motivos de seguridad e integridad de datos, la edición de secciones no acepta solicitudes mediante el método <strong>GET</strong> (parámetros en la URL).
                </p>
                <div class="alert alert-light border small text-left text-secondary mb-4">
                    <i class="fas fa-shield-alt text-primary mr-1"></i> Esta operación debe ser solicitada mediante el método seguro <strong>POST</strong> desde el panel de Gestión de Secciones.
                </div>
                <div>
                    <a href="gestion_seccion.php" class="btn btn-primary px-4 font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i> Ir a Gestión de Secciones
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

cargarPermisosUsuario();
verificarPermiso('secciones');
visita();

// Obtener ID de la sección vía POST
$id_seccion = (int)($_POST['id'] ?? ($_POST['id_seccion'] ?? 0));
if (!$id_seccion) {
    $_SESSION['error'] = "Identificador de sección no válido o no especificado.";
    header("Location: gestion_seccion.php");
    exit();
}

// Obtener datos actuales de la sección usando prepared statement
$stmt_sec = $db->prepare("SELECT * FROM secciones WHERE id_seccion = ?");
$stmt_sec->bind_param("i", $id_seccion);
$stmt_sec->execute();
$result_sec = $stmt_sec->get_result();
$seccion = $result_sec->fetch_assoc();
$stmt_sec->close();

if (!$seccion) {
    $_SESSION['error'] = "La sección académica solicitada no existe.";
    header("Location: gestion_seccion.php");
    exit();
}

// Obtener carreras activas
$result_carreras = $db->query("SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera ASC");
$carreras = [];
if ($result_carreras) {
    while ($row = $result_carreras->fetch_assoc()) {
        $carreras[] = $row;
    }
}

// Obtener trayectos
$result_trayectos = $db->query("SELECT id_trayecto, numero_trayecto, nombre_trayecto FROM trayectos ORDER BY numero_trayecto ASC");
$trayectos = [];
if ($result_trayectos) {
    while ($row = $result_trayectos->fetch_assoc()) {
        $trayectos[] = $row;
    }
}

// Obtener períodos académicos
$result_periodos = $db->query("SELECT id_periodo, nombre_periodo FROM periodos_academicos ORDER BY id_periodo DESC");
$periodos = [];
if ($result_periodos) {
    while ($row = $result_periodos->fetch_assoc()) {
        $periodos[] = $row;
    }
}

$error_message = '';
$success_message = '';

// Procesar guardado de cambios sólo si se presionó el botón de guardar
if (isset($_POST['guardar_cambios'])) {
    $codigo_seccion = trim($_POST['codigo_seccion'] ?? '');
    $id_carrera = (int)($_POST['id_carrera'] ?? 0);
    $id_trayecto = (int)($_POST['id_trayecto'] ?? 0);
    $turno = trim($_POST['turno'] ?? '');
    $id_periodo = (int)($_POST['id_periodo'] ?? 0);
    $capacidad_maxima = (int)($_POST['capacidad_maxima'] ?? 0);
    $inicia_raw = trim($_POST['inicia'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $estatus = trim($_POST['estatus'] ?? '');

    // Formatear fecha y hora para MySQL
    $inicia = !empty($inicia_raw) ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $inicia_raw))) : date('Y-m-d H:i:s');

    // Sincronizar estatus / status
    if (empty($estatus)) {
        $estatus = ($status === 'Aprobada') ? 'activa' : 'inactiva';
    }

    if (empty($codigo_seccion)) {
        $error_message = "Debe ingresar un código de sección.";
    } elseif ($id_carrera <= 0) {
        $error_message = "Debe seleccionar una carrera válida.";
    } elseif ($id_trayecto <= 0) {
        $error_message = "Debe seleccionar un trayecto válido.";
    } elseif (empty($turno)) {
        $error_message = "Debe seleccionar un turno.";
    } elseif ($id_periodo <= 0) {
        $error_message = "Debe seleccionar un período académico.";
    } elseif ($capacidad_maxima < 1) {
        $error_message = "La capacidad máxima debe ser al menos 1.";
    } elseif (empty($status)) {
        $error_message = "Debe seleccionar un estado de aprobación.";
    } else {
        // Verificar que no exista otra sección con el mismo código en el mismo período
        $check_stmt = $db->prepare("SELECT id_seccion FROM secciones 
                                     WHERE codigo_seccion = ? 
                                     AND id_periodo = ?
                                     AND id_seccion != ?");
        $check_stmt->bind_param("sii", $codigo_seccion, $id_periodo, $id_seccion);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($check_res && $check_res->num_rows > 0) {
            $error_message = "El código de sección '$codigo_seccion' ya existe en el período seleccionado.";
        } else {
            // Extraer o mantener numero_seccion
            $num_sec = intval(preg_replace('/[^0-9]/', '', $codigo_seccion));
            if ($num_sec <= 0) {
                $num_sec = intval($seccion['numero_seccion'] ?? 1);
            }

            $datos_antiguos = $seccion;

            // Actualizar sección con prepared statement
            $update_stmt = $db->prepare("UPDATE secciones SET 
                                            codigo_seccion = ?,
                                            numero_seccion = ?,
                                            id_carrera = ?,
                                            id_trayecto = ?,
                                            turno = ?,
                                            id_periodo = ?,
                                            capacidad_maxima = ?,
                                            inicia = ?,
                                            status = ?,
                                            estatus = ?
                                         WHERE id_seccion = ?");
            if (!$update_stmt) {
                $error_message = "Error al preparar actualización: " . $db->error;
            } else {
                $update_stmt->bind_param(
                    "siiisiisssi",
                    $codigo_seccion,
                    $num_sec,
                    $id_carrera,
                    $id_trayecto,
                    $turno,
                    $id_periodo,
                    $capacidad_maxima,
                    $inicia,
                    $status,
                    $estatus,
                    $id_seccion
                );

                if ($update_stmt->execute()) {
                    $success_message = "Sección actualizada correctamente.";

                    // Auditoría institucional
                    if (function_exists('registrarAuditoria')) {
                        try {
                            $audit_ant = [
                                'codigo_seccion' => $datos_antiguos['codigo_seccion'] ?? null,
                                'id_carrera' => $datos_antiguos['id_carrera'] ?? null,
                                'id_trayecto' => $datos_antiguos['id_trayecto'] ?? null,
                                'id_periodo' => $datos_antiguos['id_periodo'] ?? null,
                                'capacidad_maxima' => $datos_antiguos['capacidad_maxima'] ?? null,
                                'turno' => $datos_antiguos['turno'] ?? null,
                                'status' => $datos_antiguos['status'] ?? null,
                                'estatus' => $datos_antiguos['estatus'] ?? null
                            ];
                            $audit_nue = [
                                'codigo_seccion' => $codigo_seccion,
                                'id_carrera' => $id_carrera,
                                'id_trayecto' => $id_trayecto,
                                'id_periodo' => $id_periodo,
                                'capacidad_maxima' => $capacidad_maxima,
                                'turno' => $turno,
                                'status' => $status,
                                'estatus' => $estatus
                            ];
                            registrarAuditoria("UPDATE", "secciones", $id_seccion, $audit_ant, $audit_nue);
                        } catch (Exception $e) {}
                    }

                    // Recargar datos actualizados de la sección
                    $stmt_reload = $db->prepare("SELECT * FROM secciones WHERE id_seccion = ?");
                    $stmt_reload->bind_param("i", $id_seccion);
                    $stmt_reload->execute();
                    $seccion = $stmt_reload->get_result()->fetch_assoc();
                    $stmt_reload->close();
                } else {
                    $error_message = "Error al actualizar la sección: " . $update_stmt->error;
                }
                $update_stmt->close();
            }
        }
        $check_stmt->close();
    }
}

include(__DIR__ . '/../includes/head.php');
?>

<div class="container-fluid py-3">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0 text-gray-800">
                <i class="fas fa-edit text-primary mr-2"></i> Editar Sección Académica
            </h2>
            <a href="gestion_seccion.php" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Volver a Secciones
            </a>
        </div>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($success_message) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="card shadow border-0" style="border-radius: 10px;">
        <div class="card-header bg-primary text-white py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-layer-group mr-2"></i> Parámetros de la Sección: <?= htmlspecialchars($seccion['codigo_seccion']) ?>
            </h6>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="editar_seccion.php" id="formEditarSeccion">
                <input type="hidden" name="id" value="<?= $id_seccion ?>">
                <input type="hidden" name="id_seccion" value="<?= $id_seccion ?>">
                <input type="hidden" name="guardar_cambios" value="1">

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label font-weight-bold">Código de Sección <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="codigo_seccion" id="inputCodigoSeccion"
                               value="<?= htmlspecialchars($seccion['codigo_seccion']) ?>" required>
                        <small class="text-muted">Identificador único dentro del período.</small>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label font-weight-bold">Carrera / PNF <span class="text-danger">*</span></label>
                        <select class="form-control" name="id_carrera" required>
                            <option value="">Seleccione una carrera</option>
                            <?php foreach ($carreras as $carrera): ?>
                                <option value="<?= $carrera['id_carrera'] ?>" 
                                    <?= ($seccion['id_carrera'] == $carrera['id_carrera']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($carrera['nombre_carrera']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label font-weight-bold">Trayecto <span class="text-danger">*</span></label>
                        <select class="form-control" name="id_trayecto" required>
                            <option value="">Seleccione un trayecto</option>
                            <?php foreach ($trayectos as $trayecto): ?>
                                <option value="<?= $trayecto['id_trayecto'] ?>" 
                                    <?= ($seccion['id_trayecto'] == $trayecto['id_trayecto']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($trayecto['nombre_trayecto']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label font-weight-bold">Turno <span class="text-danger">*</span></label>
                        <select class="form-control" name="turno" required>
                            <option value="Diurno" <?= ($seccion['turno'] == 'Diurno') ? 'selected' : '' ?>>Diurno</option>
                            <option value="Nocturno" <?= ($seccion['turno'] == 'Nocturno') ? 'selected' : '' ?>>Nocturno</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label font-weight-bold">Período <span class="text-danger">*</span></label>
                        <select class="form-control" name="id_periodo" required>
                            <option value="">Seleccione un período</option>
                            <?php foreach ($periodos as $periodo): ?>
                                <option value="<?= $periodo['id_periodo'] ?>" 
                                    <?= ($seccion['id_periodo'] == $periodo['id_periodo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($periodo['nombre_periodo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label font-weight-bold">Capacidad Máxima <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="capacidad_maxima" 
                               value="<?= (int)$seccion['capacidad_maxima'] ?>" min="1" max="100" required>
                        <small class="text-muted">Cupos disponibles para asignación.</small>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label font-weight-bold">Fecha de Inicio <span class="text-danger">*</span></label>
                        <?php 
                        $inicia_val = !empty($seccion['inicia']) ? date('Y-m-d\TH:i', strtotime($seccion['inicia'])) : date('Y-m-d\T07:00');
                        ?>
                        <input type="datetime-local" class="form-control" name="inicia" 
                               value="<?= $inicia_val ?>" required>
                        <small class="text-muted">Fecha estimada de inicio de actividades.</small>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label font-weight-bold">Estado de Aprobación <span class="text-danger">*</span></label>
                        <select class="form-control" name="status" required>
                            <option value="Aprobada" <?= ($seccion['status'] == 'Aprobada') ? 'selected' : '' ?>>Aprobada</option>
                            <option value="Pendiente" <?= ($seccion['status'] == 'Pendiente') ? 'selected' : '' ?>>Pendiente</option>
                            <option value="Rechazada" <?= ($seccion['status'] == 'Rechazada') ? 'selected' : '' ?>>Rechazada</option>
                        </select>
                        <small class="text-muted">Estatus administrativo oficial.</small>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label font-weight-bold">Disponibilidad / Condición <span class="text-danger">*</span></label>
                        <select class="form-control" name="estatus" required>
                            <option value="activa" <?= (($seccion['estatus'] ?? '') == 'activa') ? 'selected' : '' ?>>Activa</option>
                            <option value="inactiva" <?= (($seccion['estatus'] ?? '') == 'inactiva') ? 'selected' : '' ?>>Inactiva</option>
                            <option value="completa" <?= (($seccion['estatus'] ?? '') == 'completa') ? 'selected' : '' ?>>Completa (Sin cupos)</option>
                        </select>
                        <small class="text-muted">Condición operativa en inscripciones.</small>
                    </div>
                </div>
                
                <hr class="my-4">

                <div class="row">
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <a href="gestion_seccion.php" class="btn btn-secondary px-4">
                            <i class="fas fa-times mr-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-success px-4 font-weight-bold shadow-sm">
                            <i class="fas fa-save mr-1"></i> Guardar Cambios
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('formEditarSeccion').addEventListener('submit', function(e) {
    const codigo = document.querySelector('input[name="codigo_seccion"]').value.trim();
    if (!codigo) {
        e.preventDefault();
        alert('Debe ingresar un código de sección válido.');
        return false;
    }
    
    const capacidad = parseInt(document.querySelector('input[name="capacidad_maxima"]').value, 10);
    if (isNaN(capacidad) || capacidad < 1) {
        e.preventDefault();
        alert('La capacidad máxima debe ser de al menos 1 estudiante.');
        return false;
    }
    
    return true;
});
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>