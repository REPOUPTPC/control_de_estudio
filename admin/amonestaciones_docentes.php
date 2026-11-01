<?php
require_once('../funciones/functions.php');

// Verificar permisos de administración
cargarPermisosUsuario();
if (!isAdmin() && !tienePermiso('secretaria') && !tienePermiso('notas_cargadas')) {
    $_SESSION['msg'] = "Debes iniciar sesión como administrador para acceder a este módulo";
    header('location: ../login.php');
    exit();
}

visita();

$titulopag = "Amonestaciones Docentes - Control de Estudios";

// Escaneo forzado si se solicita
$mensaje_alerta = '';
if (isset($_POST['accion_escanear'])) {
    $nuevas = verificarDocentesCargaVencida(true);
    if ($nuevas > 0) {
        $mensaje_alerta = "<div class='alert alert-warning alert-dismissible fade show'><i class='fas fa-exclamation-triangle mr-2'></i>Se detectaron y notificaron <strong>$nuevas</strong> casos de docentes con fechas de carga vencidas. <button type='button' class='close' data-dismiss='alert'>&times;</button></div>";
    } else {
        $mensaje_alerta = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle mr-2'></i>Verificación completada. No se encontraron nuevas asignaciones con fechas vencidas sin notificar. <button type='button' class='close' data-dismiss='alert'>&times;</button></div>";
    }
} else {
    // Verificación rutinaria al cargar
    verificarDocentesCargaVencida();
}

// Filtros
$filtro_estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null;
$filtro_periodo = isset($_GET['periodo']) && intval($_GET['periodo']) > 0 ? intval($_GET['periodo']) : null;

$amonestaciones = obtenerTodasAmonestaciones($filtro_estado, $filtro_periodo);
$periodos = obtenerPeriodosAcademicos($db);

include("includes/head.php");
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="h3 font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-exclamation-triangle text-danger mr-2"></i>Amonestaciones y Prórrogas Docentes
            </h2>
            <p class="text-muted small mb-0">
                Gestión institucional de docentes con plazos vencidos de carga de notas, actas de amonestación y habilitación de prórrogas de 24 horas.
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <form method="POST" class="d-inline">
                <button type="submit" name="accion_escanear" class="btn btn-danger btn-sm shadow-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Escanear Cargas Vencidas Ahora
                </button>
            </form>
            <a href="mensajeria.php" class="btn btn-primary btn-sm shadow-sm ml-2">
                <i class="fas fa-envelope mr-1"></i> Ir a Mensajería
            </a>
        </div>
    </div>

    <?= $mensaje_alerta ?>

    <!-- TARJETAS DE RESUMEN -->
    <?php
    $total_amonestaciones = count($amonestaciones);
    $total_pendientes = 0;
    $total_habilitados = 0;
    $total_regularizados = 0;

    foreach ($amonestaciones as $a) {
        if ($a['estado'] === 'pendiente_presentacion') $total_pendientes++;
        elseif ($a['estado'] === 'habilitado_24h') $total_habilitados++;
        elseif ($a['estado'] === 'regularizado') $total_regularizados++;
    }
    ?>
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow-sm h-100 py-2 border-danger" style="border-left: 4px solid #dc3545;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Pendientes de Comparecencia</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_pendientes ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-clock fa-2x text-gray-300 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow-sm h-100 py-2 border-warning" style="border-left: 4px solid #ffc107;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Prórrogas de 24h Activas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_habilitados ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hourglass-half fa-2x text-gray-300 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow-sm h-100 py-2 border-success" style="border-left: 4px solid #28a745;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Casos Regularizados</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_regularizados ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow-sm h-100 py-2 border-info" style="border-left: 4px solid #17a2b8;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Expedientes</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_amonestaciones ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-folder-open fa-2x text-gray-300 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row align-items-center">
                <div class="col-md-4 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1">Filtrar por Estado:</label>
                    <select name="estado" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Todos los estados --</option>
                        <option value="pendiente_presentacion" <?= $filtro_estado === 'pendiente_presentacion' ? 'selected' : '' ?>>Pendientes de Presentación</option>
                        <option value="habilitado_24h" <?= $filtro_estado === 'habilitado_24h' ? 'selected' : '' ?>>Habilitados 24 Horas</option>
                        <option value="regularizado" <?= $filtro_estado === 'regularizado' ? 'selected' : '' ?>>Regularizados</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1">Filtrar por Período Académico:</label>
                    <select name="periodo" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Todos los períodos --</option>
                        <?php if ($periodos): ?>
                            <?php foreach ($periodos as $p): ?>
                                <option value="<?= $p['id_periodo'] ?>" <?= $filtro_periodo == $p['id_periodo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre_periodo']) ?> <?= $p['activo'] ? '(Activo)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                    <a href="amonestaciones_docentes.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-eraser mr-1"></i> Limpiar Filtros
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLA DE EXPEDIENTES -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list mr-2"></i>Expedientes de Amonestaciones Registradas
            </h6>
            <span class="badge badge-light border"><?= count($amonestaciones) ?> registros encontrados</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="thead-light">
                        <tr>
                            <th># Ref</th>
                            <th>Docente</th>
                            <th>Asignatura & Sección</th>
                            <th>Período / Trimestre</th>
                            <th>Fecha Límite</th>
                            <th>Estado Actual</th>
                            <th>Prórroga 24h</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($amonestaciones)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle mr-1"></i> No se encontraron expedientes con los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($amonestaciones as $am): ?>
                                <?php
                                $año = date('Y', strtotime($am['created_at']));
                                $ref = sprintf("ACTA-%s-%04d", $año, $am['id']);
                                ?>
                                <tr>
                                    <td class="font-weight-bold text-secondary"><?= $ref ?></td>
                                    <td>
                                        <div class="font-weight-bold text-dark"><?= htmlspecialchars($am['docente_nombre']) ?></div>
                                        <small class="text-muted">C.I: <?= htmlspecialchars($am['docente_cedula']) ?></small><br>
                                        <small class="text-muted"><i class="far fa-envelope mr-1"></i><?= htmlspecialchars($am['docente_email'] ?: 'Sin email') ?></small>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold"><?= htmlspecialchars($am['nombre_materia']) ?></div>
                                        <span class="badge badge-secondary">Sec: <?= htmlspecialchars($am['codigo_seccion']) ?></span>
                                        <small class="text-muted d-block"><?= htmlspecialchars($am['carrera_nombre'] ?: 'PNF') ?></small>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($am['nombre_periodo']) ?></div>
                                        <span class="badge badge-primary">Trimestre <?= $am['trimestre_num'] ?></span>
                                    </td>
                                    <td>
                                        <span class="text-danger font-weight-bold">
                                            <i class="fas fa-calendar-times mr-1"></i><?= date('d/m/Y', strtotime($am['fecha_limite'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($am['estado'] === 'pendiente_presentacion'): ?>
                                            <span class="badge badge-danger p-2">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>Pendiente Presentación
                                            </span>
                                        <?php elseif ($am['estado'] === 'habilitado_24h'): ?>
                                            <span class="badge badge-warning p-2">
                                                <i class="fas fa-clock mr-1"></i>Habilitado 24h
                                            </span>
                                        <?php elseif ($am['estado'] === 'regularizado'): ?>
                                            <span class="badge badge-success p-2">
                                                <i class="fas fa-check-circle mr-1"></i>Regularizado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($am['estado'] === 'habilitado_24h' && !empty($am['habilitado_hasta'])): ?>
                                            <?php
                                            $vencida = strtotime($am['habilitado_hasta']) < time();
                                            ?>
                                            <div class="<?= $vencida ? 'text-danger font-weight-bold' : 'text-success font-weight-bold' ?>">
                                                <?= date('d/m/Y h:i A', strtotime($am['habilitado_hasta'])) ?>
                                                <?php if ($vencida): ?>
                                                    <span class="badge badge-danger">Vencida</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Activa</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">Por: <?= htmlspecialchars($am['habilitado_por_nombre'] ?: 'Control de Estudios') ?></small>
                                        <?php elseif ($am['estado'] === 'regularizado'): ?>
                                            <span class="text-muted small">Notas cargadas</span>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin habilitar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <!-- Botón descargar PDF -->
                                        <a href="generar_amonestacion_pdf.php?id=<?= $am['id'] ?>" target="_blank" class="btn btn-outline-danger btn-sm mr-1" title="Descargar Formato de Amonestación (PDF)">
                                            <i class="fas fa-file-pdf mr-1"></i> Formato PDF
                                        </a>

                                        <!-- Botón habilitar 24h -->
                                        <?php if ($am['estado'] !== 'regularizado'): ?>
                                            <button type="button" class="btn btn-warning btn-sm btn-habilitar-24h" data-id="<?= $am['id'] ?>" title="Habilitar 24 Horas al Docente">
                                                <i class="fas fa-clock mr-1"></i> Habilitar 24h
                                            </button>
                                        <?php else: ?>
                                            <span class="badge badge-light border text-success"><i class="fas fa-check mr-1"></i>Listo</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $(document).on('click', '.btn-habilitar-24h', function() {
        const amonId = $(this).data('id');
        const btn = $(this);

        Swal.fire({
            title: '¿Habilitar 24 Horas al Docente?',
            text: 'Confirme que el docente se presentó en Control de Estudios. Se otorgará una prórroga de 24 horas continuas a partir de este instante para cargar las notas de esta sección.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, Habilitar 24 Horas',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Habilitando...');

                const fd = new FormData();
                fd.append('id', amonId);

                fetch('ajax_habilitar_docente_24h.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡24 Horas Habilitadas!',
                            text: res.message,
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message || 'No se pudo habilitar al docente.',
                            confirmButtonColor: '#dc3545'
                        });
                        btn.prop('disabled', false).html('<i class="fas fa-clock mr-1"></i> Habilitar 24h');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-clock mr-1"></i> Habilitar 24h');
                });
            }
        });
    });
});
</script>

<?php include("includes/footer.php"); ?>
