<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulopag = "Control y Seguimiento de Notas de Docentes - Administrador";
require_once(__DIR__ . '/../funciones/functions.php');

// Verificar autenticación y rol
cargarPermisosUsuario();
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['msg'] = "Debes iniciar sesión como administrador para acceder a este módulo";
    header('location: ../login.php');
    exit();
}

visita();

// Obtener todas las carreras disponibles
$carreras_res = $db->query("SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera ASC");
$lista_carreras = [];
if ($carreras_res) {
    while ($c = $carreras_res->fetch_assoc()) {
        $lista_carreras[] = $c;
    }
}

// Filtro de carrera (0 = todas)
$carrera_filtro = isset($_GET['id_carrera']) ? intval($_GET['id_carrera']) : 0;

// Obtener lista de períodos para el filtro
$periodos_res = $db->query("SELECT id_periodo as id, nombre_periodo as periodo, activo FROM periodos_academicos ORDER BY id_periodo DESC");
$lista_periodos = [];
$periodo_activo_id = 0;
if ($periodos_res) {
    while ($p = $periodos_res->fetch_assoc()) {
        $lista_periodos[] = $p;
        if ($p['activo']) {
            $periodo_activo_id = intval($p['id']);
        }
    }
}

// Filtro seleccionado de período (por defecto 0 para ver todos)
$periodo_filtro = isset($_GET['id_periodo']) ? intval($_GET['id_periodo']) : 0;

// Construir condición WHERE
$where_clauses = [];
if ($carrera_filtro > 0) {
    $where_clauses[] = "s.id_carrera = $carrera_filtro";
}
if ($periodo_filtro > 0) {
    $where_clauses[] = "s.id_periodo = $periodo_filtro";
}
$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Obtener asignaciones docente-materia-sección
$sql = "SELECT 
            ds.id_docente_seccion,
            ds.id_usuario as id_docente,
            u.nombre as nombre_docente,
            u.idusuario as cedula_docente,
            u.email as email_docente,
            u.cel as cel_docente,
            s.id_seccion,
            s.codigo_seccion,
            s.turno,
            s.id_trayecto,
            s.id_periodo as id_periodo_seccion,
            c.id_carrera,
            c.nombre_carrera,
            pa.nombre_periodo,
            t.nombre_trayecto,
            m.id_materia,
            m.cod_materia,
            m.nombre_materia,
            -- Conteo de estudiantes inscritos en la sección
            (SELECT COUNT(DISTINCT es.id_usuario) 
             FROM estudiante_seccion es 
             JOIN users ue ON es.id_usuario = ue.id
             WHERE es.id_seccion = s.id_seccion AND es.estatus = 'activo') as total_estudiantes,
            -- Conteo de estudiantes con notas cargadas en notas_trimestres pertenecientes a la sección
            (SELECT COUNT(DISTINCT nt.id_usuario) 
             FROM notas_trimestres nt 
             WHERE nt.id_materia = m.id_materia 
             AND nt.id_docente = ds.id_usuario 
             AND nt.id_usuario IN (SELECT es2.id_usuario FROM estudiante_seccion es2 WHERE es2.id_seccion = s.id_seccion AND es2.estatus = 'activo')) as estudiantes_con_notas,
            -- Conteo por trimestres
            (SELECT COUNT(DISTINCT nt.id_usuario) FROM notas_trimestres nt WHERE nt.id_materia = m.id_materia AND nt.id_docente = ds.id_usuario AND nt.trimestre_num = 1 AND nt.id_usuario IN (SELECT es2.id_usuario FROM estudiante_seccion es2 WHERE es2.id_seccion = s.id_seccion AND es2.estatus = 'activo')) as notas_t1,
            (SELECT COUNT(DISTINCT nt.id_usuario) FROM notas_trimestres nt WHERE nt.id_materia = m.id_materia AND nt.id_docente = ds.id_usuario AND nt.trimestre_num = 2 AND nt.id_usuario IN (SELECT es2.id_usuario FROM estudiante_seccion es2 WHERE es2.id_seccion = s.id_seccion AND es2.estatus = 'activo')) as notas_t2,
            (SELECT COUNT(DISTINCT nt.id_usuario) FROM notas_trimestres nt WHERE nt.id_materia = m.id_materia AND nt.id_docente = ds.id_usuario AND nt.trimestre_num = 3 AND nt.id_usuario IN (SELECT es2.id_usuario FROM estudiante_seccion es2 WHERE es2.id_seccion = s.id_seccion AND es2.estatus = 'activo')) as notas_t3,
            -- Soporte y última fecha
            (SELECT np.soporte FROM notas_pendientes np WHERE np.id_materia = m.id_materia AND np.id_docente = ds.id_usuario AND np.soporte IS NOT NULL ORDER BY np.id DESC LIMIT 1) as soporte_archivo,
            (SELECT MAX(nt.fecha_registro) FROM notas_trimestres nt WHERE nt.id_materia = m.id_materia AND nt.id_docente = ds.id_usuario) as ultima_fecha_carga
        FROM docente_seccion ds
        JOIN users u ON ds.id_usuario = u.id
        JOIN secciones s ON ds.id_seccion = s.id_seccion
        JOIN materias m ON ds.id_materia = m.id_materia
        JOIN carreras c ON s.id_carrera = c.id_carrera
        LEFT JOIN periodos_academicos pa ON s.id_periodo = pa.id_periodo
        LEFT JOIN trayectos t ON s.id_trayecto = t.id_trayecto
        $where_sql
        ORDER BY c.nombre_carrera ASC, u.nombre ASC, s.codigo_seccion ASC, m.nombre_materia ASC";

$asignaciones = [];
$total_asignaciones = 0;
$total_subidas = 0;
$total_parciales = 0;
$total_sin_subir = 0;

$res = $db->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $total_est = intval($row['total_estudiantes']);
        $con_notas = intval($row['estudiantes_con_notas']);
        
        if ($total_est > 0 && $con_notas >= $total_est) {
            $estado_slug = 'subida';
            $total_subidas++;
        } elseif ($con_notas > 0) {
            $estado_slug = 'parcial';
            $total_parciales++;
        } else {
            $estado_slug = 'sin_subir';
            $total_sin_subir++;
        }
        $row['estado_slug'] = $estado_slug;
        $asignaciones[] = $row;
    }
}
$total_asignaciones = count($asignaciones);
$porcentaje_cumplimiento = $total_asignaciones > 0 ? round(($total_subidas / $total_asignaciones) * 100, 1) : 0;

include("includes/head.php");
?>

<div class="container-fluid py-4 px-2 px-md-4">
    <!-- Encabezado Principal -->
    <div class="card shadow-sm border-0 mb-4" style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; border-radius: 12px;">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <h2 class="h3 font-weight-bold mb-1">
                        <i class="fas fa-clipboard-check mr-2"></i> Control y Seguimiento Institucional de Notas
                    </h2>
                    <p class="mb-0 text-white-50">
                        Supervisión y auditoría del cumplimiento de carga de calificaciones en todas las carreras y programas
                    </p>
                </div>
                <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
                    <a href="amonestaciones_docentes.php" class="btn btn-warning font-weight-bold shadow-sm text-dark mr-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Ver Amonestaciones
                    </a>
                    <a href="index.php" class="btn btn-outline-light font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i> Volver al Panel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas KPI de Estado de Carga -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow-sm h-100 py-2 border-0" style="border-left: 5px solid #4e73df !important; border-radius: 10px;">
                <div class="card-body py-3">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Asignaciones</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="kpiTotal"><?= $total_asignaciones ?></div>
                            <div class="small text-muted mt-1">Materias asignadas a docentes</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow-sm h-100 py-2 border-0" style="border-left: 5px solid #28a745 !important; border-radius: 10px;">
                <div class="card-body py-3">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Notas Subidas / Completas</div>
                            <div class="h4 mb-0 font-weight-bold text-success" id="kpiSubidas"><?= $total_subidas ?></div>
                            <div class="small text-muted mt-1"><?= $porcentaje_cumplimiento ?>% de cumplimiento</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow-sm h-100 py-2 border-0" style="border-left: 5px solid #ffc107 !important; border-radius: 10px;">
                <div class="card-body py-3">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Carga Parcial / En Proceso</div>
                            <div class="h4 mb-0 font-weight-bold text-warning" id="kpiParciales"><?= $total_parciales ?></div>
                            <div class="small text-muted mt-1">Algunos trimestres cargados</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow-sm h-100 py-2 border-0" style="border-left: 5px solid #dc3545 !important; border-radius: 10px;">
                <div class="card-body py-3">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Sin Subir Notas (Pendientes)</div>
                            <div class="h4 mb-0 font-weight-bold text-danger" id="kpiSinSubir"><?= $total_sin_subir ?></div>
                            <div class="small text-muted mt-1">Requieren atención/recordatorio</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-danger opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 10px;">
        <div class="card-body p-3">
            <form method="GET" action="seguimiento_notas.php" id="formFiltros">
                <div class="row g-3 align-items-center">
                    <div class="col-md-3 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-graduation-cap mr-1"></i> Carrera / PNF:</label>
                        <select name="id_carrera" class="form-control form-control-sm font-weight-bold" onchange="document.getElementById('formFiltros').submit();">
                            <option value="0" <?= $carrera_filtro === 0 ? 'selected' : '' ?>>Todas las Carreras</option>
                            <?php foreach ($lista_carreras as $lc): ?>
                                <option value="<?= $lc['id_carrera'] ?>" <?= $carrera_filtro == $lc['id_carrera'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lc['nombre_carrera']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-calendar mr-1"></i> Período Académico:</label>
                        <select name="id_periodo" class="form-control form-control-sm" onchange="document.getElementById('formFiltros').submit();">
                            <option value="0" <?= $periodo_filtro === 0 ? 'selected' : '' ?>>Todos los Períodos</option>
                            <?php foreach ($lista_periodos as $lp): ?>
                                <option value="<?= $lp['id'] ?>" <?= $periodo_filtro == $lp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lp['periodo']) ?> <?= $lp['activo'] ? '(Activo)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-filter mr-1"></i> Estado Carga:</label>
                        <select id="filtroEstado" class="form-control form-control-sm font-weight-bold">
                            <option value="todos">Todos los Estados</option>
                            <option value="subida">✅ Con Notas Subidas</option>
                            <option value="parcial">⏳ Carga Parcial</option>
                            <option value="sin_subir">❌ Sin Notas Subidas</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-search mr-1"></i> Buscar en vivo:</label>
                        <input type="text" id="buscadorRapido" class="form-control form-control-sm" placeholder="Profesor, Cédula, Materia o Sección...">
                    </div>

                    <div class="col-md-1 text-md-right mt-3 mt-md-0">
                        <button type="button" id="btnLimpiarFiltros" class="btn btn-sm btn-outline-secondary w-100" title="Reiniciar búsqueda">
                            <i class="fas fa-redo-alt"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla Principal de Seguimiento -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 10px; overflow: hidden;">
        <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="font-weight-bold mb-0">
                <i class="fas fa-list-alt mr-2 text-warning"></i> Reporte Institucional de Asignaciones y Calificaciones
            </h6>
            <span class="badge badge-light font-weight-bold" id="badgeTotalVisibles">
                Mostrando <?= $total_asignaciones ?> registros
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center align-middle" id="tablaSeguimiento">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 16%;" class="text-left">Carrera</th>
                            <th style="width: 20%;" class="text-left">Docente / Profesor</th>
                            <th style="width: 20%;" class="text-left">Materia</th>
                            <th style="width: 10%;">Sección / Turno</th>
                            <th style="width: 8%;">Estudiantes</th>
                            <th style="width: 13%;">Estado de Carga</th>
                            <th style="width: 5%;">Trimestres</th>
                            <th style="width: 8%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodySeguimiento">
                        <?php if (empty($asignaciones)): ?>
                            <tr>
                                <td colspan="8" class="py-5 text-muted">
                                    <i class="fas fa-info-circle fa-2x mb-2 text-muted"></i>
                                    <p class="mb-0">No se encontraron asignaciones con los filtros seleccionados.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($asignaciones as $asig): 
                                $tot_est = intval($asig['total_estudiantes']);
                                $con_not = intval($asig['estudiantes_con_notas']);
                                $slug = $asig['estado_slug'];
                                $t1 = intval($asig['notas_t1']);
                                $t2 = intval($asig['notas_t2']);
                                $t3 = intval($asig['notas_t3']);
                            ?>
                                <tr class="fila-docente" 
                                    data-estado="<?= $slug ?>" 
                                    data-carrera="<?= htmlspecialchars(strtolower($asig['nombre_carrera'])) ?>"
                                    data-docente="<?= htmlspecialchars(strtolower($asig['nombre_docente'])) ?>"
                                    data-materia="<?= htmlspecialchars(strtolower($asig['nombre_materia'])) ?>"
                                    data-seccion="<?= htmlspecialchars(strtolower($asig['codigo_seccion'])) ?>"
                                    data-cedula="<?= htmlspecialchars(strtolower($asig['cedula_docente'])) ?>">
                                    
                                    <!-- Carrera -->
                                    <td class="text-left">
                                        <span class="badge badge-light border text-dark font-weight-bold d-block text-truncate" title="<?= htmlspecialchars($asig['nombre_carrera']) ?>" style="max-width: 180px; font-size: 0.78rem;">
                                            <i class="fas fa-graduation-cap text-primary mr-1"></i><?= htmlspecialchars($asig['nombre_carrera']) ?>
                                        </span>
                                        <small class="text-muted"><?= htmlspecialchars($asig['nombre_periodo'] ?? '') ?></small>
                                    </td>

                                    <!-- Docente -->
                                    <td class="text-left">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-light border mr-2 d-flex align-items-center justify-content-center text-primary font-weight-bold" style="width: 36px; height: 36px; border-radius: 50%; min-width: 36px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="font-weight-bold text-dark"><?= htmlspecialchars($asig['nombre_docente']) ?></div>
                                                <div class="small text-muted">C.I: <?= htmlspecialchars($asig['cedula_docente']) ?></div>
                                                <?php if (!empty($asig['email_docente'])): ?>
                                                    <div class="small text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($asig['email_docente']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Materia -->
                                    <td class="text-left">
                                        <div class="font-weight-bold text-primary"><?= htmlspecialchars($asig['nombre_materia']) ?></div>
                                        <div class="small text-muted">Código: <?= htmlspecialchars($asig['cod_materia']) ?></div>
                                        <span class="badge badge-light border" style="font-size: 0.72rem;"><?= htmlspecialchars($asig['nombre_trayecto'] ?? 'Trayecto') ?></span>
                                    </td>

                                    <!-- Sección -->
                                    <td>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($asig['codigo_seccion']) ?></strong>
                                        <span class="badge badge-secondary" style="font-size: 0.72rem;"><?= htmlspecialchars($asig['turno']) ?></span>
                                    </td>

                                    <!-- Estudiantes -->
                                    <td>
                                        <span class="badge badge-info px-2 py-1" style="font-size: 0.85rem;">
                                            <i class="fas fa-users mr-1"></i> <?= $tot_est ?>
                                        </span>
                                    </td>

                                    <!-- Estado de Carga -->
                                    <td>
                                        <?php if ($slug === 'subida'): ?>
                                            <span class="badge badge-success px-2 py-2 d-block font-weight-bold shadow-sm">
                                                <i class="fas fa-check-circle mr-1"></i> SUBIDAS (<?= $con_not ?>/<?= $tot_est ?>)
                                            </span>
                                            <?php if (!empty($asig['ultima_fecha_carga'])): ?>
                                                <div class="small text-muted mt-1" style="font-size: 0.70rem;">
                                                    <?= date('d/m/Y h:i A', strtotime($asig['ultima_fecha_carga'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif ($slug === 'parcial'): ?>
                                            <span class="badge badge-warning text-dark px-2 py-2 d-block font-weight-bold shadow-sm">
                                                <i class="fas fa-clock mr-1"></i> PARCIAL (<?= $con_not ?>/<?= $tot_est ?>)
                                            </span>
                                            <?php if (!empty($asig['ultima_fecha_carga'])): ?>
                                                <div class="small text-muted mt-1" style="font-size: 0.70rem;">
                                                    <?= date('d/m/Y h:i A', strtotime($asig['ultima_fecha_carga'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-danger px-2 py-2 d-block font-weight-bold shadow-sm">
                                                <i class="fas fa-times-circle mr-1"></i> PENDIENTE (0/<?= $tot_est ?>)
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Desglose Trimestres -->
                                    <td>
                                        <div class="d-flex justify-content-center gap-1">
                                            <span class="badge <?= $t1 > 0 ? 'badge-success' : 'badge-light border text-muted' ?>" title="Trimestre 1: <?= $t1 ?> notas">T1</span>
                                            <span class="badge <?= $t2 > 0 ? 'badge-success' : 'badge-light border text-muted' ?>" title="Trimestre 2: <?= $t2 ?> notas">T2</span>
                                            <span class="badge <?= $t3 > 0 ? 'badge-success' : 'badge-light border text-muted' ?>" title="Trimestre 3: <?= $t3 ?> notas">T3</span>
                                        </div>
                                    </td>

                                    <!-- Acciones -->
                                    <td>
                                        <div class="d-flex justify-content-center flex-wrap gap-1">
                                            <button type="button" class="btn btn-sm btn-info btn-ver-notas-docente py-1 px-2 font-weight-bold shadow-sm" 
                                                    data-docente="<?= $asig['id_docente'] ?>" 
                                                    data-materia="<?= $asig['id_materia'] ?>" 
                                                    data-seccion="<?= $asig['id_seccion'] ?>"
                                                    title="Ver Calificaciones de la Sección">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (!empty($asig['soporte_archivo'])): ?>
                                                <a href="../soportes/<?= htmlspecialchars($asig['soporte_archivo']) ?>" target="_blank" class="btn btn-sm btn-danger py-1 px-2 font-weight-bold" title="Ver Soporte Adjunto">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            <?php endif; ?>
                                            <form method="POST" action="mensajeria.php" class="d-inline" target="_self">
                                                <input type="hidden" name="destinatario" value="<?= $asig['id_docente'] ?>">
                                                <input type="hidden" name="asunto" value="Recordatorio de Calificaciones - <?= htmlspecialchars($asig['nombre_materia']) ?> (Sección <?= htmlspecialchars($asig['codigo_seccion']) ?>)">
                                                <input type="hidden" name="mensaje_cuerpo" value="Estimado(a) profesor(a) <?= htmlspecialchars($asig['nombre_docente']) ?>, Control de Estudios le recuerda la carga oportuna de notas para <?= htmlspecialchars($asig['nombre_materia']) ?> sección <?= htmlspecialchars($asig['codigo_seccion']) ?>. Saludos institucionales.">
                                                <button type="submit" class="btn btn-sm btn-outline-primary py-1 px-2 font-weight-bold" title="Enviar Mensaje a <?= htmlspecialchars($asig['nombre_docente']) ?>">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </form>
                                        </div>
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

<!-- MODAL PARA VER DETALLE DE CALIFICACIONES DE LA SECCIÓN -->
<div class="modal fade" id="modalDetalleNotasDocente" tabindex="-1" role="dialog" aria-labelledby="modalDetalleNotasDocenteLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="modal-title font-weight-bold mb-0" id="modalDetalleNotasDocenteLabel">
                    <i class="fas fa-clipboard-list text-warning mr-2"></i> Detalle de Calificaciones por Estudiante
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" id="modalDetalleNotasDocenteBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Cargando calificaciones...</p>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-4">
                <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtroEstado = document.getElementById('filtroEstado');
    const buscador = document.getElementById('buscadorRapido');
    const btnLimpiar = document.getElementById('btnLimpiarFiltros');
    const filas = document.querySelectorAll('.fila-docente');
    const badgeTotal = document.getElementById('badgeTotalVisibles');

    function aplicarFiltros() {
        const estadoVal = filtroEstado ? filtroEstado.value : 'todos';
        const searchVal = buscador ? buscador.value.toLowerCase().trim() : '';
        let visibles = 0;

        filas.forEach(function(fila) {
            const estado = fila.getAttribute('data-estado');
            const carrera = fila.getAttribute('data-carrera') || '';
            const docente = fila.getAttribute('data-docente') || '';
            const materia = fila.getAttribute('data-materia') || '';
            const seccion = fila.getAttribute('data-seccion') || '';
            const cedula = fila.getAttribute('data-cedula') || '';

            const coincideEstado = (estadoVal === 'todos' || estado === estadoVal);
            const coincideTexto = (searchVal === '' || 
                                   carrera.includes(searchVal) ||
                                   docente.includes(searchVal) || 
                                   materia.includes(searchVal) || 
                                   seccion.includes(searchVal) || 
                                   cedula.includes(searchVal));

            if (coincideEstado && coincideTexto) {
                fila.style.display = '';
                visibles++;
            } else {
                fila.style.display = 'none';
            }
        });

        if (badgeTotal) {
            badgeTotal.textContent = 'Mostrando ' + visibles + ' de ' + filas.length + ' registros';
        }
    }

    if (filtroEstado) filtroEstado.addEventListener('change', aplicarFiltros);
    if (buscador) buscador.addEventListener('input', aplicarFiltros);

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function() {
            if (filtroEstado) filtroEstado.value = 'todos';
            if (buscador) buscador.value = '';
            aplicarFiltros();
        });
    }

    // Modal de Calificaciones
    $(document).on('click', '.btn-ver-notas-docente', function(e) {
        e.preventDefault();
        const docId = $(this).attr('data-docente');
        const matId = $(this).attr('data-materia');
        const secId = $(this).attr('data-seccion');
        
        const modalBody = document.getElementById('modalDetalleNotasDocenteBody');
        modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Cargando calificaciones...</p></div>';
        $('#modalDetalleNotasDocente').modal('show');

        fetch('ajax_detalle_notas_docente.php?id_docente=' + docId + '&id_materia=' + matId + '&id_seccion=' + secId)
            .then(response => response.text())
            .then(html => {
                modalBody.innerHTML = html;
            })
            .catch(error => {
                modalBody.innerHTML = '<div class="alert alert-danger">Error al cargar datos: ' + error.message + '</div>';
            });
    });
});
</script>

<?php include("includes/footer.php"); ?>
