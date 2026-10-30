<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulopag = "Gestión de Secciones";
require_once(__DIR__ . '/../../funciones/functions.php');

cargarPermisosUsuario();
verificarPermiso('secciones');
visita();

// Obtener períodos para el filtro
$periodos = obtenerPeriodosAcademicos($db);

// Obtener período seleccionado (si existe)
$periodo_seleccionado = isset($_GET['periodo']) ? (int)$_GET['periodo'] : 0;

include(__DIR__ . '/../includes/head.php');
?>

<style>
.table-actions{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
.table-actions form{display:inline-flex;margin:0}
.table-actions .btn{min-width:2.5rem}
.filtro-periodo{max-width:300px;margin-bottom:10px}
@media(max-width:991.98px){.card-header.d-flex{flex-direction:column;align-items:stretch;gap:.5rem}.table-actions{width:100%}.table-actions form{flex:1 1 auto}}
@media(max-width:767.98px){.form-row{display:flex;flex-direction:column}.form-row .form-group{width:100%!important}.table thead th,.table tbody td{padding:.55rem .65rem;font-size:.9rem}.btn-block{width:100%}}
</style>

<div class="container-fluid">
    <?php 
    if (isset($_SESSION['error'])) { mostrarError($_SESSION['error']); unset($_SESSION['error']); }
    if (isset($_SESSION['success'])) { mostrarExito($_SESSION['success']); unset($_SESSION['success']); }
    if (isset($_SESSION['warning'])) { mostrarAdvertencia($_SESSION['warning']); unset($_SESSION['warning']); }
    ?>
    
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Secciones</h1>
        <a href="crear_seccion.php" class="btn btn-success btn-sm">
            <i class="fas fa-plus-circle"></i> Nueva Sección
        </a>
    </div>
    
    <div class="card shadow">
        <div class="card-header py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Listado de Secciones</h6>
                    <?php if (tienePermiso('admin')): ?>
                        <a href="../aprobar_secciones.php" class="btn btn-info btn-sm mt-1">
                            <i class="fas fa-check-circle"></i> Secciones Pendientes
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Filtro por período -->
                <div class="filtro-periodo">
                    <form method="get" class="form-inline">
                        <label class="mr-2 small">Filtrar por período:</label>
                        <select name="periodo" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                            <option value="0">-- Todos los períodos --</option>
                            <?php foreach ($periodos as $periodo): ?>
                                <option value="<?= $periodo['id_periodo'] ?>" <?= $periodo_seleccionado == $periodo['id_periodo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($periodo['nombre_periodo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($periodo_seleccionado > 0): ?>
                            <a href="gestion_seccion.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body p-2">
            <div class="alert alert-info py-1 mb-2 small">Nota: Las secciones requieren al menos <?= MINIMO_ESTUDIANTES ?> estudiantes para activarse.</div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                    <thead>
                        <tr><th>Código</th><th>Carrera</th><th>Trayecto</th><th>Período</th><th>Inicio</th><th>Aprobación</th><th>Estudiantes</th><th>Capacidad</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        // Obtener secciones con filtro de período
                        $secciones = obtenerListadoSecciones($db, $periodo_seleccionado);
                        
                        if (empty($secciones)):
                        ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">
                                    No hay secciones registradas <?= $periodo_seleccionado > 0 ? 'para el período seleccionado' : '' ?>.
                                </td>
                            </tr>
                        <?php
                        endif;
                        
                        foreach ($secciones as $seccion) {
                            $porcentaje = $seccion['capacidad_maxima'] > 0 ? round(($seccion['inscritos'] / $seccion['capacidad_maxima']) * 100) : 0;
                            $ya_inicio = false;
                            if (isset($seccion['inicia']) && !empty($seccion['inicia'])) {
                                $fecha_inicio = new DateTime($seccion['inicia']);
                                $fecha_actual = new DateTime();
                                $ya_inicio = ($fecha_actual >= $fecha_inicio);
                            }
                            if ($seccion['periodo_activo'] == 0) {
                                $estado_clase = 'secondary'; $estado_texto = 'Período Inactivo'; $mostrar_faltantes = false;
                            } else {
                                if ($ya_inicio) { $estado_clase = 'success'; $estado_texto = 'Activa (Ya inició)'; $mostrar_faltantes = false;
                                } else {
                                    if ($seccion['estatus'] == 'activa') { $estado_clase = 'success'; $estado_texto = 'Activa'; $mostrar_faltantes = false;
                                    } else { $estado_clase = 'danger'; $estado_texto = 'Inactiva'; $mostrar_faltantes = true; }
                                }
                            }
                            $status_text = isset($seccion['status']) ? ucfirst($seccion['status']) : 'Desconocido';
                            switch ($seccion['status'] ?? '') {
                                case 'Aprobada': $status_class = 'success'; break;
                                case 'Pendiente': $status_class = 'warning'; break;
                                case 'Rechazada': $status_class = 'danger'; break;
                                default: $status_class = 'secondary'; break;
                            }
                        ?>
                        <tr>
                            <td class="small"><?= htmlspecialchars($seccion['codigo_seccion']) ?></td>
                            <td class="small"><?= htmlspecialchars($seccion['nombre_carrera']) ?></td>
                            <td class="small">Trayecto <?= $seccion['numero_trayecto'] ?></td>
                            <td class="small"><?= htmlspecialchars($seccion['nombre_periodo']) ?></td>
                            <td class="small"><?= isset($seccion['inicia']) ? date('d/m/Y', strtotime($seccion['inicia'])) : '--' ?></td>
                            <td><span class="badge badge-<?= $status_class ?>"><?= htmlspecialchars($status_text) ?></span></td>
                            <td>
                                <div class="progress" style="height:18px">
                                    <div class="progress-bar <?= $porcentaje >= 80 ? 'bg-success' : 'bg-info' ?>" style="width: <?= $porcentaje ?>%;font-size:11px">
                                        <?= $seccion['inscritos'] ?>/<?= $seccion['capacidad_maxima'] ?>
                                    </div>
                                </div>
                            </td>
                            <td class="small"><?= $seccion['capacidad_maxima'] ?></td>
                            <td><span class="badge badge-<?= $estado_clase ?>"><?= $estado_texto ?></span></td>
                            <td class="table-actions">
                                <form method="POST" action="ver_seccion.php" class="d-inline m-0 p-0">
                                    <input type="hidden" name="id" value="<?= $seccion['id_seccion'] ?>">
                                    <button type="submit" class="btn btn-sm btn-info" title="Ver Sección"><i class="fas fa-eye"></i></button>
                                </form>
                                <form method="POST" action="editar_seccion.php" class="d-inline m-0 p-0">
                                    <input type="hidden" name="id" value="<?= $seccion['id_seccion'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary" title="Editar Sección"><i class="fas fa-edit"></i></button>
                                </form>
                                <?php if ($seccion['periodo_activo'] == 1): ?>
                                    <a href="asignar_estudiantes.php?id=<?= $seccion['id_seccion'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-users"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../includes/footer.php'); ?>