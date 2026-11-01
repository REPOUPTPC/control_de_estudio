<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulopag = "Gestión de Secciones";
include('../funciones/functions.php');

//CARGAR PERMISOS
cargarPermisosUsuario();
verificarPermiso('secciones');

// LLAMAR A LA FUNCIÓN DE VISITA
visita();

// Obtener parámetros de acción
$action = $_POST['action'] ?? ($_GET['action'] ?? 'list');
$seccion_id = $_POST['id'] ?? ($_GET['id'] ?? 0);
$periodo_id = $_POST['periodo'] ?? 0;

// Procesar formularios antes de cualquier output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_seccion'])) {
        // Procesar creación de sección
        $id_carrera = (int)$_POST['id_carrera'];
        $codigo_seccion = trim($_POST['codigo_seccion']);
        
        // Generar código automáticamente si no se proporciona
        if (empty($codigo_seccion)) {
            $turno_seccion = trim($_POST['turno']);
            $codigo_seccion = generarCodigoSeccion($id_carrera, $turno_seccion);
            if (!$codigo_seccion) {
                $_SESSION['error'] = 'No hay códigos disponibles para esta carrera y turno. Configure los rangos de códigos primero.';
                header("Location: cod_secciones.php");
                exit();
            }
        }
        
        $datos = [
            'codigo_seccion' => $codigo_seccion,
            'id_carrera' => $id_carrera,
            'id_trayecto' => (int)$_POST['id_trayecto'],
            'id_periodo' => (int)$_POST['id_periodo'],
            'capacidad_maxima' => (int)$_POST['capacidad_maxima'],
            'turno' => trim($_POST['turno']),
            'inicia' => $_POST['inicia']
        ];
        
        $resultado = crearSeccion($db, $datos);
        
        if ($resultado['success']) {
            $_SESSION['success'] = $resultado['message'];
        } else {
            $_SESSION['error'] = $resultado['message'];
        }
        header("Location: gestion_seccion.php");
        exit();
        
    } elseif (isset($_POST['editar_seccion'])) {
        // Procesar edición de sección
        $datos = [
            'id_seccion' => (int)$_POST['id'],
            'codigo_seccion' => trim($_POST['codigo_seccion']),
            'id_carrera' => (int)$_POST['id_carrera'],
            'id_trayecto' => (int)$_POST['id_trayecto'],
            'id_periodo' => (int)$_POST['id_periodo'],
            'capacidad_maxima' => (int)$_POST['capacidad_maxima'],
            'turno' => trim($_POST['turno']),
            'inicia' => $_POST['inicia']
        ];
        
        $resultado = editarSeccion($db, $datos);
        
        if ($resultado['success']) {
            $_SESSION['success'] = $resultado['message'];
        } else {
            $_SESSION['error'] = $resultado['message'];
        }
        header("Location: gestion_seccion.php");
        exit();
        
    } elseif (isset($_POST['asignar_estudiantes'])) {
        // Procesar asignación de estudiantes
        $seccion_id = (int)$_POST['seccion_id'];
        $estudiantes = $_POST['estudiantes'] ?? [];
        
        $resultado = asignarEstudiantes($db, $seccion_id, $estudiantes);
        
        if ($resultado['success']) {
            $_SESSION['success'] = $resultado['message'];
            if (isset($resultado['warning'])) {
                $_SESSION['warning'] = $resultado['warning'];
            }
        } else {
            $_SESSION['error'] = $resultado['message'];
        }
        header("Location: gestion_seccion.php?action=view&id=".$seccion_id);
        exit();
    } elseif ($action === 'retirar_estudiante') {
        // Procesar retiro de estudiante
        $usuario_id = (int)$_POST['id_usuario'];
        $seccion_id = (int)$_POST['id_seccion'];
        
        $resultado = retirarEstudiante($db, $seccion_id, $usuario_id);
        
        if ($resultado['success']) {
            $_SESSION['success'] = $resultado['message'];
        } else {
            $_SESSION['error'] = $resultado['message'];
        }
        header("Location: gestion_seccion.php?action=view&id=".$seccion_id);
        exit();
    }
}

include("includes/head.php");
?>

<style>
    .table-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        align-items: center;
    }
    .table-actions form {
        display: inline-flex;
        margin: 0;
    }
    .table-actions .btn {
        min-width: 2.5rem;
    }
    .table-responsive {
        overflow-x: auto;
    }
    .horario-cell {
        min-height: 60px;
        padding: .5rem;
    }
    @media (max-width: 991.98px) {
        .card-header.d-flex {
            flex-direction: column;
            align-items: stretch;
            gap: .5rem;
        }
        .card-header.d-flex .badge {
            width: auto;
        }
        .table-actions {
            width: 100%;
        }
        .table-actions form {
            flex: 1 1 auto;
        }
    }
    @media (max-width: 767.98px) {
        .form-row {
            display: flex;
            flex-direction: column;
        }
        .form-row .form-group {
            width: 100% !important;
        }
        .table thead th,
        .table tbody td {
            padding: .55rem .65rem;
            font-size: .9rem;
        }
        .btn-block {
            width: 100%;
        }
        #horario-clases .table-responsive {
            margin-bottom: .75rem;
        }
        #horario-clases table {
            min-width: 720px;
        }
        .horario-cell {
            min-height: 50px;
        }
    }
</style>

<!-- Modal de Confirmación -->
<div class="modal fade" id="confirmarRetiroModal" tabindex="-1" role="dialog" aria-labelledby="confirmarRetiroModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmarRetiroModalLabel">Confirmar Retiro de Estudiante</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalConfirmacionBody">
                ¿Está seguro que desea retirar a este estudiante de la sección?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form id="formRetiroEstudiante" method="post" style="display:inline">
                    <input type="hidden" name="action" value="retirar_estudiante">
                    <input type="hidden" name="id_usuario" id="modalIdUsuario" value="">
                    <input type="hidden" name="id_seccion" id="modalIdSeccion" value="">
                    <button type="submit" class="btn btn-danger">Confirmar Retiro</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <?php 
    if (isset($_SESSION['error'])) {
        mostrarError($_SESSION['error']);
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['success'])) {
        mostrarExito($_SESSION['success']);
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['warning'])) {
        mostrarAdvertencia($_SESSION['warning']);
        unset($_SESSION['warning']);
    }
    
    if ($action === 'list'): ?>
        <!-- LISTADO DE SECCIONES -->
        <h1 class="h3 mb-4 text-gray-800">Gestión de Secciones</h1>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Listado de Secciones</h6>
                    <?php if (tienePermiso('admin')): ?>
                        <a href="aprobar_secciones.php" class="btn btn-info btn-sm mt-2">
                            <i class="fas fa-check-circle"></i> Secciones Pendientes
                        </a>
                    <?php endif; ?>
                </div>
                <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="new">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-plus-circle"></i> Nueva Sección
                    </button>
                </form>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Nota:</strong> Las secciones requieren al menos <?= MINIMO_ESTUDIANTES ?> estudiantes para activarse. Una vez iniciadas, no se desactivan por falta de estudiantes.
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Carrera</th>
                                <th>Trayecto</th>
                                <th>Período</th>
                                <th>Inicio</th>
                                <th>Aprobación</th>
                                <th>Estudiantes</th>
                                <th>Capacidad</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $secciones = obtenerListadoSecciones($db);
                            
                            foreach ($secciones as $seccion) {
                                $porcentaje = $seccion['capacidad_maxima'] > 0 ? 
                                    round(($seccion['inscritos'] / $seccion['capacidad_maxima']) * 100) : 0;
                                
                                // Verificar si la sección ya inició
                                $ya_inicio = false;
                                if (isset($seccion['inicia']) && !empty($seccion['inicia'])) {
                                    $fecha_inicio = new DateTime($seccion['inicia']);
                                    $fecha_actual = new DateTime();
                                    $ya_inicio = ($fecha_actual >= $fecha_inicio);
                                }
                                
                                // Determinar clase y texto del estado
                                if ($seccion['periodo_activo'] == 0) {
                                    $estado_clase = 'secondary';
                                    $estado_texto = 'Período Inactivo';
                                    $mostrar_faltantes = false;
                                } else {
                                    if ($ya_inicio) {
                                        $estado_clase = 'success';
                                        $estado_texto = 'Activa (Ya inició)';
                                        $mostrar_faltantes = false;
                                    } else {
                                        // Estado normal de la sección
                                        if ($seccion['estatus'] == 'activa') {
                                            $estado_clase = 'success';
                                            $estado_texto = 'Activa';
                                            $mostrar_faltantes = false;
                                        } else {
                                            $estado_clase = 'danger';
                                            $estado_texto = 'Inactiva';
                                            $mostrar_faltantes = true;
                                        }
                                    }
                                }

                                $status_text = isset($seccion['status']) ? ucfirst($seccion['status']) : 'Desconocido';
                                switch ($seccion['status'] ?? '') {
                                    case 'Aprobada':
                                        $status_class = 'success';
                                        break;
                                    case 'Pendiente':
                                        $status_class = 'warning';
                                        break;
                                    case 'Rechazada':
                                        $status_class = 'danger';
                                        break;
                                    default:
                                        $status_class = 'secondary';
                                        break;
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($seccion['codigo_seccion']) ?></td>
                                <td><?= htmlspecialchars($seccion['nombre_carrera']) ?></td>
                                <td>Trayecto <?= $seccion['numero_trayecto'] ?></td>
                                <td><?= htmlspecialchars($seccion['nombre_periodo']) ?></td>
                                <td><?= isset($seccion['inicia']) ? date('d/m/Y H:i', strtotime($seccion['inicia'])) : '--' ?></td>
                                <td>
                                    <span class="badge badge-<?= $status_class ?>">
                                        <?= htmlspecialchars($status_text) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar <?= $porcentaje >= 80 ? 'bg-success' : 'bg-info' ?>" 
                                             role="progressbar" style="width: <?= $porcentaje ?>%">
                                            <?= $seccion['inscritos'] ?>/<?= $seccion['capacidad_maxima'] ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= $seccion['capacidad_maxima'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $estado_clase ?>">
                                        <?= $estado_texto ?>
                                        <?php if ($seccion['periodo_activo'] == 0): ?>
                                            <br><small>(Período desactivado)</small>
                                        <?php elseif ($mostrar_faltantes && $seccion['inscritos'] > 0): ?>
                                            <br><small>(Faltan <?= MINIMO_ESTUDIANTES - $seccion['inscritos'] ?> para activar)</small>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="table-actions">
                                    <form method="post">
                                        <input type="hidden" name="action" value="view">
                                        <input type="hidden" name="id" value="<?= $seccion['id_seccion'] ?>">
                                        <button type="submit" class="btn btn-sm btn-info" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= $seccion['id_seccion'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </form>
                                    <?php if ($seccion['periodo_activo'] == 1): ?>
                                        <form method="post">
                                            <input type="hidden" name="action" value="assign">
                                            <input type="hidden" name="id" value="<?= $seccion['id_seccion'] ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" title="Asignar Estudiantes">
                                                <i class="fas fa-users"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'new' || $action === 'edit'): ?>
        <!-- FORMULARIO DE EDICIÓN/CREACIÓN -->
        <?php
        $datos_selects = obtenerDatosSelects($db);
        $carreras = $datos_selects['carreras'];
        $trayectos = $datos_selects['trayectos'];
        $periodos = $datos_selects['periodos'];
        
        // Datos de la sección si es edición
        $seccion = [];
        if ($action === 'edit' && $seccion_id > 0) {
            $seccion = obtenerDatosSeccion($db, $seccion_id);
        }
        ?>
        
        <h1 class="h3 mb-4 text-gray-800"><?= $action === 'new' ? 'Nueva' : 'Editar' ?> Sección</h1>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Datos de la Sección</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Importante:</strong> La sección se activará automáticamente cuando tenga al menos <?= MINIMO_ESTUDIANTES ?> estudiantes asignados. Una vez que la sección haya iniciado, no se desactivará por falta de estudiantes.
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $action === 'new' ? 'crear_seccion' : 'editar_seccion' ?>">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= $seccion_id ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label for="codigo_seccion">Código de Sección *</label>
                            <input type="text" class="form-control" id="codigo_seccion" name="codigo_seccion" 
                                   value="<?= $seccion['codigo_seccion'] ?? '' ?>" required readonly>
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label for="id_carrera">Carrera *</label>
                            <select class="form-control" id="id_carrera" name="id_carrera" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($carreras as $carrera): ?>
                                    <option value="<?= $carrera['id_carrera'] ?>" 
                                        <?= isset($seccion['id_carrera']) && $seccion['id_carrera'] == $carrera['id_carrera'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($carrera['nombre_carrera']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-md-2">
                            <label for="id_trayecto">Trayecto *</label>
                            <select class="form-control" id="id_trayecto" name="id_trayecto" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($trayectos as $trayecto): ?>
                                    <option value="<?= $trayecto['id_trayecto'] ?>" 
                                        <?= isset($seccion['id_trayecto']) && $seccion['id_trayecto'] == $trayecto['id_trayecto'] ? 'selected' : '' ?>>
                                        <?= $trayecto['numero_trayecto'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-md-2">
                            <label for="turno">Turno *</label>
                            <select class="form-control" id="turno" name="turno" required>
                                <option value="">Seleccione...</option>
                                <option value="Diurno" <?= isset($seccion['turno']) && $seccion['turno'] == 'Diurno' ? 'selected' : '' ?>>Diurno</option>
                                <option value="Nocturno" <?= isset($seccion['turno']) && $seccion['turno'] == 'Nocturno' ? 'selected' : '' ?>>Nocturno</option>
                            </select>
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label for="id_periodo">Período *</label>
                            <select class="form-control" id="id_periodo" name="id_periodo" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($periodos as $periodo): ?>
                                    <option value="<?= $periodo['id_periodo'] ?>" 
                                        <?= isset($seccion['id_periodo']) && $seccion['id_periodo'] == $periodo['id_periodo'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($periodo['nombre_periodo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                        
                        <div class="form-group col-md-3">
                            <label for="id_periodo">Período *</label>
                            <select class="form-control" id="id_periodo" name="id_periodo" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($periodos as $periodo): ?>
                                    <option value="<?= $periodo['id_periodo'] ?>" 
                                        <?= isset($seccion['id_periodo']) && $seccion['id_periodo'] == $periodo['id_periodo'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($periodo['nombre_periodo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="capacidad_maxima">Capacidad Máxima *</label>
                            <input type="number" class="form-control" id="capacidad_maxima" name="capacidad_maxima" 
                                   value="<?= $seccion['capacidad_maxima'] ?? 30 ?>" min="<?= MINIMO_ESTUDIANTES ?>" required>
                            <small class="form-text text-muted">Mínimo <?= MINIMO_ESTUDIANTES ?> estudiantes</small>
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label for="inicia">Fecha y Hora de Inicio *</label>
                            <input type="datetime-local" class="form-control" id="inicia" name="inicia" 
                                   value="<?= isset($seccion['inicia']) ? date('Y-m-d\TH:i', strtotime($seccion['inicia'])) : '' ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="<?= $action === 'new' ? 'crear_seccion' : 'editar_seccion' ?>" 
                            class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="gestion_seccion.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </form>
                
                <script>
                $(document).ready(function() {
                    function generarCodigo() {
                        var id_carrera = $('#id_carrera').val();
                        
                        if (id_carrera) {
                            var turno = $('#turno').val();
                            $.ajax({
                                url: 'ajax_generar_codigo.php',
                                type: 'POST',
                                data: {
                                    id_carrera: id_carrera,
                                    turno: turno
                                },
                                success: function(response) {
                                    var data = JSON.parse(response);
                                    if (data.success) {
                                        $('#codigo_seccion').val(data.codigo);
                                    } else {
                                        $('#codigo_seccion').val('');
                                        alert(data.message);
                                    }
                                },
                                error: function() {
                                    $('#codigo_seccion').val('');
                                    alert('Error al generar código');
                                }
                            });
                        } else {
                            $('#codigo_seccion').val('');
                        }
                    }
                    
                    $('#id_carrera, #turno').change(generarCodigo);
                    if ($('#id_carrera').val() && $('#turno').val()) {
                        generarCodigo();
                    }
                });
                </script>
            </div>
        </div>

    <?php elseif ($action === 'assign' && $seccion_id > 0): ?>
        <!-- ASIGNACIÓN DE ESTUDIANTES -->
        <?php
        $seccion = obtenerDetalleSeccion($db, $seccion_id);
        $estudiantes = obtenerEstudiantesDisponibles($db, $seccion_id, $seccion['id_carrera']);
        $asignados = obtenerEstudiantesAsignados($db, $seccion_id);
        
        // Verificar si la sección está llena
        $seccion_llena = ($seccion['inscritos'] >= $seccion['capacidad_maxima']);
        $periodo_inactivo = ($seccion['periodo_activo'] == 0);
        
        if ($periodo_inactivo) {
            $_SESSION['error'] = "No se pueden asignar estudiantes a una sección con período inactivo.";
            header("Location: gestion_seccion.php?action=view&id=".$seccion_id);
            exit();
        }
        ?>
        
        <h1 class="h3 mb-4 text-gray-800">Asignar Estudiantes</h1>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    Sección: <?= htmlspecialchars($seccion['codigo_seccion']) ?> - <?= htmlspecialchars($seccion['nombre_carrera']) ?>
                </h6>
                <div>
                    <?php
                    // Verificar si la sección ya inició
                    $ya_inicio = false;
                    if (isset($seccion['inicia']) && !empty($seccion['inicia'])) {
                        $fecha_inicio = new DateTime($seccion['inicia']);
                        $fecha_actual = new DateTime();
                        $ya_inicio = ($fecha_actual >= $fecha_inicio);
                    }
                    
                    if ($ya_inicio) {
                        $estado_clase = 'success';
                        $estado_texto = 'Activa (Ya inició)';
                    } else {
                        $estado_clase = $seccion['estatus'] == 'activa' ? 'success' : 'danger';
                        $estado_texto = ucfirst($seccion['estatus']);
                    }
                    ?>
                    <span class="badge badge-<?= $estado_clase ?>">
                        <?= $estado_texto ?>
                    </span>
                    <span class="badge badge-info ml-2">
                        Cupos: <?= $seccion['inscritos'] ?>/<?= $seccion['capacidad_maxima'] ?>
                    </span>
                    <?php if (!$ya_inicio && $seccion['estatus'] == 'inactiva'): ?>
                        <span class="badge badge-danger ml-2">
                            Faltan <?= MINIMO_ESTUDIANTES - $seccion['inscritos'] ?> para activar
                        </span>
                    <?php endif; ?>
                    <?php if ($seccion_llena): ?>
                        <span class="badge badge-warning ml-2">
                            Sección llena
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($seccion_llena): ?>
                    <div class="alert alert-warning">
                        <strong>Atención:</strong> La sección ha alcanzado su capacidad máxima. Puede desasignar estudiantes marcándolos para retirarlos.
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="formAsignarEstudiantes">
                    <input type="hidden" name="action" value="asignar_estudiantes">
                    <input type="hidden" name="seccion_id" value="<?= $seccion_id ?>">
                    <input type="hidden" id="capacidadMaxima" value="<?= $seccion['capacidad_maxima'] ?>">
                    <input type="hidden" id="inscritosActuales" value="<?= $seccion['inscritos'] ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="tablaEstudiantes" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="seleccionarTodos">
                                    </th>
                                    <th>Nombre</th>
                                    <th>Cedula</th>
                                    <th>Asignado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $est): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="estudiantes[]" value="<?= $est['id'] ?>"
                                                <?= in_array($est['id'], $asignados) ? 'checked' : '' ?>
                                                class="checkbox-estudiante">
                                        </td>
                                        <td><?= htmlspecialchars($est['nombre']) ?></td>
                                        <td><?= htmlspecialchars($est['idusuario']) ?></td>
                                        <td>
                                            <?php if ($est['asignado'] > 0): ?>
                                                <span class="badge badge-success">Sí</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        Estudiantes seleccionados: <span id="contador-seleccionados">0</span>/<?= $seccion['capacidad_maxima'] ?>
                    </div>
                    
                    <button type="submit" name="asignar_estudiantes" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Asignaciones
                    </button>
                    <a href="gestion_seccion.php?action=view&id=<?= $seccion_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </form>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Obtener valores iniciales
            const capacidadMaxima = parseInt($('#capacidadMaxima').val());
            const inscritosActuales = parseInt($('#inscritosActuales').val());
            
            // Contar checkboxes marcados inicialmente
            let seleccionados = $('#formAsignarEstudiantes input[name="estudiantes[]"]:checked').length;
            
            // Actualizar contador inicial
            $('#contador-seleccionados').text(seleccionados);
            
            // Inicializar DataTable
            var table = $('#tablaEstudiantes').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json"
                },
                "autoWidth": false,
                "responsive": true
            });
            
            // Función para actualizar el contador y deshabilitar checkboxes si es necesario
            function actualizarContador() {
                // Usar la API de DataTables para obtener todos los registros (incluyendo paginación)
                let totalSeleccionados = 0;
                table.rows().every(function() {
                    const row = this.node();
                    const checkbox = $(row).find('.checkbox-estudiante');
                    if (checkbox.is(':checked')) {
                        totalSeleccionados++;
                    }
                });
                
                seleccionados = totalSeleccionados;
                $('#contador-seleccionados').text(seleccionados);
                
                // Actualizar estado de "Seleccionar todos"
                const totalEstudiantes = $('#formAsignarEstudiantes input[name="estudiantes[]"]').length;
                $('#seleccionarTodos').prop('checked', seleccionados === totalEstudiantes);
                
                // Deshabilitar checkboxes no seleccionados si se alcanzó la capacidad máxima
                if (seleccionados >= capacidadMaxima) {
                    $('.checkbox-estudiante:not(:checked)').prop('disabled', true);
                } else {
                    $('.checkbox-estudiante').prop('disabled', false);
                }
            }
            
            // Manejar cambios en los checkboxes
            $(document).on('change', '.checkbox-estudiante', function() {
                // Verificar si estamos intentando marcar un checkbox cuando ya se alcanzó la capacidad máxima
                if ($(this).is(':checked') && seleccionados >= capacidadMaxima) {
                    $(this).prop('checked', false);
                    alert('No puedes seleccionar más estudiantes que la capacidad máxima de la sección.');
                    return;
                }
                
                actualizarContador();
            });
            
            // Seleccionar/deseleccionar todos
            $('#seleccionarTodos').change(function() {
                const seleccionarTodos = $(this).is(':checked');
                
                if (seleccionarTodos) {
                    // Verificar si podemos seleccionar todos
                    const totalEstudiantes = $('#formAsignarEstudiantes input[name="estudiantes[]"]').length;
                    if (totalEstudiantes > capacidadMaxima) {
                        $(this).prop('checked', false);
                        alert('No puedes seleccionar todos los estudiantes porque superaría la capacidad máxima.');
                        return;
                    }
                }
                
                // Marcar/desmarcar todos los checkboxes usando la API de DataTables
                table.rows().every(function() {
                    const row = this.node();
                    const checkbox = $(row).find('.checkbox-estudiante');
                    checkbox.prop('checked', seleccionarTodos);
                });
                
                actualizarContador();
            });
            
            // Manejar el envío del formulario
            $('#formAsignarEstudiantes').on('submit', function() {
                // Forzar a DataTables a mostrar todos los registros temporalmente
                table.page.len(-1).draw();
                // Esperar un momento para que se complete el redibujado
                setTimeout(() => {
                    // Continuar con el envío del formulario
                    return true;
                }, 500);
            });
            
            // Actualizar contador y estado inicial
            actualizarContador();
        });
        </script>

    <?php elseif ($action === 'view_schedule' && $seccion_id > 0): ?>
    <!-- VISTA DE HORARIO SEMANAL -->
    <?php
    $seccion = obtenerDetalleSeccion($db, $seccion_id);
    $horarios = obtenerHorariosSeccion($db, $seccion_id);
    
    // Asegurarnos que $horarios es un array
    $horarios = is_array($horarios) ? $horarios : [];
    
    // Preparar datos para la leyenda
    $leyenda_materias = [];
    ?>
    
    <h1 class="h3 mb-4 text-gray-800">Horario Semanal - <?= htmlspecialchars($seccion['codigo_seccion']) ?></h1>
    
    <!-- Botones fuera de la caja del horario -->
    <div class="mb-3">
        <a href="gestion_seccion.php?action=view&id=<?= $seccion_id ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a la sección
        </a>
        <form method="POST" action="pdf_horario_seccion.php" target="_blank" class="d-inline float-right ml-2 m-0 p-0">
            <input type="hidden" name="seccion_id" value="<?= $seccion_id ?>">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-file-pdf"></i> Descargar PDF
            </button>
        </form>
        
    </div>
    
    <div class="card shadow mb-4" id="horario-clases">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Horario de Clases</h6>
            <span class="badge badge-info"><?= count($horarios) ?> bloques horarios</span>
        </div>
        <div class="card-body">
            <?php if (empty($horarios)): ?>
                <div class="alert alert-info">
                    No se han definido horarios para esta sección.
                </div>
            <?php else: ?>
                <!-- Información para PDF (oculta en web) -->
                <div id="pdf-info" class="pdf-only text-center mb-3" style="display: none;">
                    <h4>Universidad Politécnica Territorial de Puerto Cabello</h4>
                    <h5>Horario de Clases - <?= htmlspecialchars($seccion['codigo_seccion']) ?></h5>
                    <p>
                        <strong>Carrera:</strong> <?= htmlspecialchars($seccion['nombre_carrera']) ?> | 
                        <strong>Trayecto:</strong> <?= $seccion['numero_trayecto'] ?> | 
                        <strong>Período:</strong> <?= htmlspecialchars($seccion['nombre_periodo']) ?>
                    </p>
                </div>
                
                <?php
                // Construir tabla en formato de 30 minutos con rowspan para bloques
                $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                
                $turno_sec = $seccion['turno'] ?? 'Diurno';
                $inicio_h = ($turno_sec === 'Nocturno') ? 17 : 7;
                $fin_h = ($turno_sec === 'Nocturno') ? 21 : 18;

                // Expandir si hay clases fuera del rango base
                foreach ($horarios as $h) {
                    $ini_h_val = (int)substr($h['hora_inicio'], 0, 2);
                    $fin_ts = strtotime($h['hora_fin']);
                    $fin_h_val = (int)date('H', $fin_ts);
                    if ((int)date('i', $fin_ts) > 0) $fin_h_val++;
                    if ($ini_h_val < $inicio_h) $inicio_h = $ini_h_val;
                    if ($fin_h_val > $fin_h) $fin_h = $fin_h_val;
                }

                $horas_disponibles = [];
                for ($h = $inicio_h; $h <= $fin_h; $h++) {
                    $horas_disponibles[] = sprintf('%02d:00', $h);
                    if ($h < $fin_h) {
                        $horas_disponibles[] = sprintf('%02d:30', $h);
                    }
                }

                // Agrupar horarios por día
                $horarios_por_dia = array_fill(0, 6, []);
                $leyenda_materias = [];
                foreach ($horarios as $horario) {
                    $dia = (int)$horario['dia'];
                    $hora_inicio = date('H:i', strtotime($horario['hora_inicio']));
                    $hora_fin = date('H:i', strtotime($horario['hora_fin']));
                    $horarios_por_dia[$dia][] = array_merge($horario, ['hora_inicio' => $hora_inicio, 'hora_fin' => $hora_fin]);

                    $clave = $horario['nombre_materia'].$horario['nombre_docente'].$horario['aula'];
                    if (!isset($leyenda_materias[$clave])) {
                        $leyenda_materias[$clave] = [
                            'materia' => $horario['nombre_materia'],
                            'docente' => $horario['nombre_docente'],
                            'aula' => $horario['aula'],
                            'horario' => $hora_inicio.' - '.$hora_fin
                        ];
                    }
                }

                $skip_cells = [];
                ?>

                <style>
                    .horario-cell {
                        border: 1px solid #dee2e6;
                        padding: 6px;
                        cursor: default;
                        position: relative;
                        text-align: center;
                        vertical-align: middle;
                        box-sizing: border-box;
                        word-break: break-word;
                    }
                    .horario-cell:hover { background-color: #f8f9fa; }
                    .bg-asignada { background-color: #e8f4fd !important; border-left: 4px solid #0b5ed7 !important; }
                    .bg-asignada:hover { background-color: #d8ecfc !important; }
                    small { font-size: 0.82em; }
                    table.table { table-layout: fixed; width: 100%; }
                </style>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th style="width: 125px;" class="text-center">Hora (30 min)</th>
                                <?php foreach ($dias_semana as $dia): ?>
                                    <th class="text-center"><?= $dia ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($horas_disponibles as $hora): 
                            $hora_fin_slot = date('H:i', strtotime($hora) + 1800);
                        ?>
                            <tr>
                                <th class="bg-light align-middle text-muted small text-center"><?= $hora ?> - <?= $hora_fin_slot ?></th>
                                <?php for ($dia_index = 0; $dia_index <= 5; $dia_index++): ?>
                                    <?php
                                    if (isset($skip_cells[$dia_index][$hora])) {
                                        continue;
                                    }

                                    $clase_encontrada = null;
                                    foreach ($horarios_por_dia[$dia_index] as $asig) {
                                        if ($hora >= $asig['hora_inicio'] && $hora < $asig['hora_fin']) {
                                            $clase_encontrada = $asig;
                                            break;
                                        }
                                    }

                                    if ($clase_encontrada):
                                        $h_ini_ts = strtotime($hora);
                                        $h_fin_ts = strtotime($clase_encontrada['hora_fin']);
                                        $rowspan = max(1, (int) round(($h_fin_ts - $h_ini_ts) / 1800));
                                        
                                        $temp_ts = $h_ini_ts;
                                        for ($i = 1; $i < $rowspan; $i++) {
                                            $temp_ts += 1800;
                                            $skip_cells[$dia_index][date('H:i', $temp_ts)] = true;
                                        }
                                    ?>
                                        <td class="horario-cell bg-asignada align-middle" rowspan="<?= $rowspan ?>">
                                            <div class="p-1">
                                                <strong class="text-primary d-block mb-1"><?= htmlspecialchars($clase_encontrada['nombre_materia']) ?></strong>
                                                <small class="text-dark d-block mb-1"><i class="fas fa-chalkboard-teacher mr-1"></i><?= htmlspecialchars($clase_encontrada['nombre_docente']) ?></small>
                                                <span class="badge badge-light border text-muted"><i class="fas fa-map-marker-alt mr-1"></i>Aula: <?= htmlspecialchars($clase_encontrada['aula']) ?></span>
                                            </div>
                                        </td>
                                    <?php else: ?>
                                        <td class="horario-cell text-muted small"></td>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                    
                    <!-- Leyenda de materias -->
                    <div class="card border-left-primary shadow py-2 mb-4">
                        <div class="card-body">
                            <h5 class="font-weight-bold text-primary mb-3">Detalle de Materias</h5>
                            <div class="row">
                                <?php foreach ($leyenda_materias as $item): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="mr-2">
                                                <i class="fas fa-book text-gray-500"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($item['materia']) ?></strong><br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($item['horario']) ?><br>
                                                    Prof: <?= htmlspecialchars($item['docente']) ?> | 
                                                    Aula: <?= htmlspecialchars($item['aula']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .horario-block {
            background-color: #f8f9fa;
            border-left: 4px solid #4e73df;
            text-align: center;
            font-weight: bold;
            vertical-align: middle;
            position: relative;
        }
        
        .horario-block.continuacion {
            background-color: #f1f3f9;
            border-left: 4px solid #a0a7c5;
            font-weight: normal;
        }
        
        .continuacion-simbolo {
            color: #6c757d;
            margin-right: 5px;
        }
        
        .table {
            table-layout: fixed;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 10px;
            height: 50px;
            vertical-align: middle;
            border: 1px solid #dee2e6;
        }
        
        .celda-horario {
            background-color: white;
        }
        
        /* Estilos para impresión */
        @media print {
            body * {
                visibility: hidden;
            }
            #horario-clases, #horario-clases * {
                visibility: visible;
            }
            #horario-clases {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .btn, .mb-3 {
                display: none !important;
            }
            .pdf-only {
                display: block !important;
            }
        }
        
        /* Estilos para PDF */
        .pdf-only {
            display: none;
        }
        </style>
        
        <script>
        function imprimirHorario() {
            window.print();
        }
        
        // Función para generar el PDF con membrete
        function generarPDF() {
            // Mostrar información para PDF
            document.getElementById('pdf-info').style.display = 'block';
            
            // Configuración de jsPDF
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            const margin = 10;
            const pageWidth = doc.internal.pageSize.getWidth();
            
            // Función para agregar membrete al PDF
            function agregarMembretePDF(doc, pageWidth, margin) {
                // Cargar imagen del logo
                const logoImg = new Image();
                logoImg.crossOrigin = 'Anonymous';
                logoImg.src = '../images/uptpc.png';
                
                return new Promise((resolve) => {
                    logoImg.onload = function() {
                        // Agregar logo (arriba a la izquierda)
                        doc.addImage(logoImg, 'PNG', margin, 10, 20, 20);
                        
                        // Agregar texto del membrete
                        doc.setFontSize(12);
                        doc.setFont(undefined, 'bold');
                        doc.text('República Bolivariana de Venezuela', pageWidth / 2, 15, { align: 'center' });
                        doc.text('Ministerio del Poder Popular para la Educación Universitaria', pageWidth / 2, 20, { align: 'center' });
                        doc.text('Universidad Politécnica Territorial de Puerto Cabello', pageWidth / 2, 25, { align: 'center' });
                        
                        // Agregar fecha
                        const hoy = new Date();
                        const fecha = hoy.toLocaleDateString('es-ES');
                        doc.setFont(undefined, 'normal');
                        doc.text(fecha, pageWidth - margin, 15, { align: 'right' });
                        
                        resolve(35); // Retornar posición Y después del membrete
                    };
                    
                    logoImg.onerror = function() {
                        // Fallback sin imagen
                        doc.setFontSize(12);
                        doc.setFont(undefined, 'bold');
                        doc.text('República Bolivariana de Venezuela', pageWidth / 2, 15, { align: 'center' });
                        doc.text('Ministerio del Poder Popular para la Educación Universitaria', pageWidth / 2, 20, { align: 'center' });
                        doc.text('Universidad Politécnica Territorial de Puerto Cabello', pageWidth / 2, 25, { align: 'center' });
                        
                        // Agregar fecha
                        const hoy = new Date();
                        const fecha = hoy.toLocaleDateString('es-ES');
                        doc.setFont(undefined, 'normal');
                        doc.text(fecha, pageWidth / 2, 32, { align: 'center' });
                        
                        resolve(40); // Retornar posición Y después del membrete
                    };
                });
            }
            
            // Llamar a la función para agregar el membrete
            agregarMembretePDF(doc, pageWidth, margin).then(startY => {
                // Capturar el contenido HTML y agregarlo al PDF
                html2canvas(document.getElementById('horario-clases'), {
                    scale: 2,
                    useCORS: true,
                    logging: false
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/jpeg', 1.0);
                    const imgWidth = pageWidth - (margin * 2);
                    const imgHeight = (canvas.height * imgWidth) / canvas.width;
                    
                    // Agregar contenido al PDF
                    doc.addImage(imgData, 'JPEG', margin, startY, imgWidth, imgHeight);
                    
                    // Guardar el PDF
                    doc.save('Horario_<?= $seccion['codigo_seccion'] ?>.pdf');
                    
                    // Ocultar información para PDF después de generarlo
                    document.getElementById('pdf-info').style.display = 'none';
                });
            });
        }
        </script>

    <?php elseif ($action === 'view' && $seccion_id > 0): ?>
        <!-- VISTA DETALLADA DE SECCIÓN -->
        <?php
        $seccion = obtenerDetalleSeccion($db, $seccion_id);
        $estudiantes = obtenerEstudiantesDeSeccion($db, $seccion_id);
        $horarios = obtenerHorariosSeccion($db, $seccion_id);
        
        $estudiantes_inscritos = count($estudiantes);
        $faltan_para_activar = max(0, MINIMO_ESTUDIANTES - $estudiantes_inscritos);
        $seccion_llena = ($estudiantes_inscritos >= $seccion['capacidad_maxima']);
        $periodo_inactivo = ($seccion['periodo_activo'] == 0);
        
        // Verificar si la sección ya inició
        $ya_inicio = false;
        if (isset($seccion['inicia']) && !empty($seccion['inicia'])) {
            $fecha_inicio = new DateTime($seccion['inicia']);
            $fecha_actual = new DateTime();
            $ya_inicio = ($fecha_actual >= $fecha_inicio);
        }
        
        // Determinar el estado a mostrar
        if ($periodo_inactivo) {
            $estado_clase = 'secondary';
            $estado_texto = 'Período Inactivo';
        } else {
            if ($ya_inicio) {
                $estado_clase = 'success';
                $estado_texto = 'Activa (Ya inició)';
            } else {
                // Estado normal de la sección (basado en estudiantes)
                if ($seccion['estatus'] == 'activa') {
                    $estado_clase = 'success';
                    $estado_texto = 'Activa';
                } else {
                    $estado_clase = 'danger';
                    $estado_texto = 'Inactiva';
                }
            }
        }
        ?>
        
        <h1 class="h3 mb-4 text-gray-800">Detalles de Sección</h1>
        
        <div class="row">
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Información General</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Código:</strong> <?= htmlspecialchars($seccion['codigo_seccion']) ?></p>
                        <p><strong>Carrera:</strong> <?= htmlspecialchars($seccion['nombre_carrera']) ?></p>
                        <p><strong>Trayecto:</strong> <?= $seccion['numero_trayecto'] ?></p>
                        <p><strong>Período:</strong> <?= htmlspecialchars($seccion['nombre_periodo']) ?></p>
                        <p><strong>Inicio de clases:</strong> <?= isset($seccion['inicia']) ? date('d/m/Y H:i', strtotime($seccion['inicia'])) : 'No definido' ?></p>
                        <p><strong>Capacidad:</strong> <?= $estudiantes_inscritos ?>/<?= $seccion['capacidad_maxima'] ?></p>
                        <p><strong>Estado:</strong> 
                            <span class="badge badge-<?= $estado_clase ?>">
                                <?= $estado_texto ?>
                                <?php if ($periodo_inactivo): ?>
                                    <br><small>(Período desactivado)</small>
                                <?php elseif (!$ya_inicio && $seccion['estatus'] == 'inactiva'): ?>
                                    <br><small>(Faltan <?= $faltan_para_activar ?> estudiantes para activar)</small>
                                <?php elseif ($ya_inicio): ?>
                                    <br><small>(Ya inició - No se desactiva)</small>
                                <?php endif; ?>
                                <?php if ($seccion_llena): ?>
                                    <br><small>(Sección llena)</small>
                                <?php endif; ?>
                            </span>
                        </p>
                        
                        <div class="mt-4">
                            <form method="post" style="display:block; margin-bottom:10px;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="id" value="<?= $seccion_id ?>">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-edit"></i> Editar Sección
                                </button>
                            </form>
                            <?php if (!$periodo_inactivo): ?>
                                <form method="post" style="display:block; margin-bottom:10px;">
                                    <input type="hidden" name="action" value="assign">
                                    <input type="hidden" name="id" value="<?= $seccion_id ?>">
                                    <button type="submit" class="btn btn-warning btn-block">
                                        <i class="fas fa-users"></i> Asignar Estudiantes
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:block; margin-bottom:10px;">
                                <input type="hidden" name="action" value="view_schedule">
                                <input type="hidden" name="id" value="<?= $seccion_id ?>">
                                <button type="submit" class="btn btn-info btn-block">
                                    <i class="fas fa-calendar-alt"></i> Ver Horario Semanal
                                </button>
                            </form>
                            <a href="gestion_seccion.php" class="btn btn-secondary btn-block">
                                <i class="fas fa-arrow-left"></i> Volver al listado
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Card para mostrar los horarios de la sección -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Horarios de la Sección</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($horarios)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Día</th>
                                            <th>Hora Inicio</th>
                                            <th>Hora Fin</th>
                                            <th>Aula</th>
                                            <th>Materia</th>
                                            <th>Docente</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($horarios as $horario): ?>
                                            <tr>
                                                <td><?= $horario['dia_nombre'] ?></td>
                                                <td><?= date('H:i', strtotime($horario['hora_inicio'])) ?></td>
                                                <td><?= date('H:i', strtotime($horario['hora_fin'])) ?></td>
                                                <td><?= htmlspecialchars($horario['aula']) ?></td>
                                                <td><?= htmlspecialchars($horario['nombre_materia']) ?></td>
                                                <td><?= htmlspecialchars($horario['nombre_docente']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No se han definido horarios para esta sección.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Estudiantes Asignados</h6>
                        <span class="badge badge-primary"><?= $estudiantes_inscritos ?> estudiantes</span>
                        <?php if ($periodo_inactivo): ?>
                            <span class="badge badge-secondary">
                                Período inactivo - No se pueden hacer cambios
                            </span>
                        <?php elseif (!$ya_inicio && $seccion['estatus'] == 'inactiva'): ?>
                            <span class="badge badge-danger">
                                Se requiere <?= MINIMO_ESTUDIANTES ?> para activar (faltan <?= $faltan_para_activar ?>)
                            </span>
                        <?php elseif ($ya_inicio): ?>
                            <span class="badge badge-success">
                                Sección ya inició - No se desactiva
                            </span>
                        <?php endif; ?>
                        <?php if ($seccion_llena): ?>
                            <span class="badge badge-warning">
                                Sección llena
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Cedula</th>
                                        <th>Fecha Inscripción</th>
                                        <?php if (!$periodo_inactivo): ?>
                                            <th>Acciones</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes as $est): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($est['nombre']) ?></td>
                                            <td><?= htmlspecialchars($est['idusuario']) ?></td>
                                            <td><?= $est['fecha_inscripcion'] ?></td>
                                            <?php if (!$periodo_inactivo): ?>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger btn-retirar" 
                                                            data-id="<?= $est['id'] ?>" 
                                                            data-seccion="<?= $seccion_id ?>"
                                                            data-nombre="<?= htmlspecialchars($est['nombre']) ?>">
                                                        <i class="fas fa-user-minus"></i> Retirar
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Manejar clic en botón de retirar
            $('.btn-retirar').click(function() {
                var idUsuario = $(this).data('id');
                var idSeccion = $(this).data('seccion');
                var nombreEstudiante = $(this).data('nombre');
                
                // Actualizar el modal con los datos del estudiante
                $('#modalConfirmacionBody').html(
                    '¿Está seguro que desea retirar al estudiante <strong>' + nombreEstudiante + '</strong> de la sección?'
                );
                
                // Setear los valores en el formulario del modal
                $('#modalIdUsuario').val(idUsuario);
                $('#modalIdSeccion').val(idSeccion);
                
                // Mostrar el modal
                $('#confirmarRetiroModal').modal('show');
            });
        });
        </script>
    <?php endif; ?>
</div>

<?php include("includes/footer.php"); ?>
</div>
</body>
</html>