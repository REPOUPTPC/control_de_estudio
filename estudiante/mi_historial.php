<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('../funciones/functions.php');

// Verificar que el usuario es un estudiante
if (!isset($_SESSION['user']) || $_SESSION['user']['estudiante'] != 1) {
    header('location: ../index.php');
    exit();
}

// LLAMAR A LA FUNCIÓN DE VISITA
visita();

$titulopag = "Mi Historial de Notas";
include("includes/head.php");

// Función para obtener la carrera del estudiante
if (!function_exists('obtenerCarreraEstudiante')) {
function obtenerCarreraEstudiante($estudiante_id) {
    global $db;
    
    $query = "SELECT c.id_carrera, c.nombre_carrera, c.cod_carrera 
              FROM users u
              INNER JOIN carreras c ON u.carrera = c.id_carrera
              WHERE u.id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $estudiante_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}
}

// Función para obtener SOLO las materias que el estudiante tiene inscritas
if (!function_exists('obtenerMateriasInscritasEstudiante')) {
function obtenerMateriasInscritasEstudiante($estudiante_id, $carrera_id) {
    global $db;
    
    $query = "SELECT DISTINCT 
                m.id_materia, 
                m.nombre_materia, 
                m.cod_materia, 
                m.trayecto
              FROM estudiante_materias em
              INNER JOIN materias m ON em.id_materia = m.id_materia
              INNER JOIN carrera_materia cm ON m.id_materia = cm.id_materia
              WHERE em.id_usuario = ?
              AND cm.id_carrera = ?
              AND (em.estatus = 'activo' OR em.estatus = 'aprobado')
              ORDER BY m.trayecto, m.nombre_materia";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $estudiante_id, $carrera_id);
    $stmt->execute();
    return $stmt->get_result();
}
}

// Función para obtener información del trayecto desde la tabla trayectos
if (!function_exists('obtenerInfoTrayecto')) {
function obtenerInfoTrayecto($numero_trayecto) {
    global $db;
    
    $query = "SELECT id_trayecto, numero_trayecto, nombre_trayecto 
              FROM trayectos 
              WHERE numero_trayecto = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $numero_trayecto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    $nombres_trayectos = [
        0 => 'Trayecto Inicial',
        1 => 'Trayecto 1',
        2 => 'Trayecto 2', 
        3 => 'Trayecto 3',
        4 => 'Trayecto 4'
    ];
    
    return [
        'id_trayecto' => $numero_trayecto + 1,
        'numero_trayecto' => $numero_trayecto,
        'nombre_trayecto' => isset($nombres_trayectos[$numero_trayecto]) ? $nombres_trayectos[$numero_trayecto] : 'Trayecto ' . $numero_trayecto
    ];
}
}

// Función para obtener las notas del estudiante
if (!function_exists('obtenerNotasEstudianteTrimestres')) {
function obtenerNotasEstudianteTrimestres($estudiante_id) {
    global $db;
    
    $notas = [];
    $estudiante_id = intval($estudiante_id);
    
    $query = "SELECT 
                nt.id_materia,
                nt.trimestre_num,
                nt.nota,
                nt.estado,
                nt.fecha_registro,
                nt.id_periodo,
                pa.nombre_periodo,
                u.nombre as nombre_admin
              FROM notas_trimestres nt
              LEFT JOIN periodos_academicos pa ON nt.id_periodo = pa.id_periodo
              LEFT JOIN users u ON nt.id_admin_aprobador = u.id
              WHERE nt.id_usuario = ?
              AND nt.estado = 'aprobada'
              ORDER BY nt.id_materia, nt.trimestre_num";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $estudiante_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $materia_id = intval($row['id_materia']);
        $trimestre = intval($row['trimestre_num']);
        
        if (!isset($notas[$materia_id])) {
            $notas[$materia_id] = [
                'trimestre_1' => null,
                'trimestre_2' => null,
                'trimestre_3' => null,
                'nota_final' => null,
                'nombre_periodo' => $row['nombre_periodo'] ?? '',
                'fecha_registro' => $row['fecha_registro'] ?? '',
                'nombre_admin' => $row['nombre_admin'] ?? '',
                'estado' => $row['estado']
            ];
        }
        
        $notas[$materia_id]["trimestre_$trimestre"] = $row['nota'];
    }
    $stmt->close();
    
    foreach ($notas as $materia_id => $nota_data) {
        $suma = 0;
        $count = 0;
        for ($i = 1; $i <= 3; $i++) {
            if ($nota_data["trimestre_$i"] !== null && $nota_data["trimestre_$i"] > 0) {
                $suma += floatval($nota_data["trimestre_$i"]);
                $count++;
            }
        }
        if ($count > 0) {
            $notas[$materia_id]['nota_final'] = round($suma / $count, 1);
        }
    }
    
    // Consultar notas definitivas
    $query_nd = "SELECT nd.*, m.trayecto, m.nota_minima_aprobatoria, pa.nombre_periodo 
                 FROM notas_definitivas nd
                 INNER JOIN materias m ON nd.id_materia = m.id_materia
                 LEFT JOIN periodos_academicos pa ON nd.id_periodo = pa.id_periodo
                 WHERE nd.id_usuario = ?";
    $stmt_nd = $db->prepare($query_nd);
    $stmt_nd->bind_param("i", $estudiante_id);
    $stmt_nd->execute();
    $res_nd = $stmt_nd->get_result();
    while ($row_nd = $res_nd->fetch_assoc()) {
        $materia_id = intval($row_nd['id_materia']);
        $tray = intval($row_nd['trayecto']);
        $col = "trayecto_" . $tray;
        $nota_def = isset($row_nd['nota_final']) && $row_nd['nota_final'] !== null 
            ? floatval($row_nd['nota_final']) 
            : (isset($row_nd[$col]) && $row_nd[$col] !== null ? floatval($row_nd[$col]) : null);
        $min_aprob = isset($row_nd['nota_minima_aprobatoria']) ? floatval($row_nd['nota_minima_aprobatoria']) : 12.0;
        
        if (!isset($notas[$materia_id])) {
            $notas[$materia_id] = [
                'trimestre_1' => null,
                'trimestre_2' => null,
                'trimestre_3' => null,
                'nota_final' => $nota_def,
                'nombre_periodo' => $row_nd['nombre_periodo'] ?? '',
                'fecha_registro' => $row_nd['fecha_registro'] ?? '',
                'nombre_admin' => 'Control de Estudios',
                'estado' => ($nota_def !== null ? ($nota_def >= $min_aprob ? 'aprobada' : 'reprobada') : 'en_curso')
            ];
        } elseif ($notas[$materia_id]['nota_final'] === null && $nota_def !== null) {
            $notas[$materia_id]['nota_final'] = $nota_def;
            $notas[$materia_id]['estado'] = ($nota_def >= $min_aprob ? 'aprobada' : 'reprobada');
        }
    }
    $stmt_nd->close();
    
    return $notas;
}
}

// Función para verificar si tiene notas aprobadas en trayectos específicos
if (!function_exists('tieneNotasEnTrayectos')) {
function tieneNotasEnTrayectos($materias_carrera, $notas_estudiante, $trayectos) {
    $tiene_notas = false;
    $materias_carrera->data_seek(0);
    
    while ($materia = $materias_carrera->fetch_assoc()) {
        $trayecto = (int)$materia['trayecto'];
        
        if (in_array($trayecto, $trayectos)) {
            $nota = isset($notas_estudiante[$materia['id_materia']]) ? $notas_estudiante[$materia['id_materia']] : null;
            
            if ($nota && isset($nota['nota_final']) && $nota['nota_final'] !== null && $nota['nota_final'] >= 12) {
                $tiene_notas = true;
                break;
            }
        }
    }
    
    $materias_carrera->data_seek(0);
    return $tiene_notas;
}
}

// Obtener información del estudiante actual
$estudiante = $_SESSION['user'];
$carrera = null;
$materias_carrera = [];
$notas_estudiante = [];

if ($estudiante) {
    $carrera = obtenerCarreraEstudiante($estudiante['id']);
    
    if ($carrera) {
        // Obtener SOLO las materias que el estudiante tiene inscritas
        $materias_carrera = obtenerMateriasInscritasEstudiante($estudiante['id'], $carrera['id_carrera']);
        
        // Obtener notas APROBADAS del estudiante
        $notas_estudiante = obtenerNotasEstudianteTrimestres($estudiante['id']);
    }
}

// Verificar permisos para cada tipo de reporte
$puede_ver_tsu = false;
$puede_ver_ingenieria = false;
$puede_ver_completo = false;

if ($estudiante && $carrera && $materias_carrera && $materias_carrera->num_rows > 0) {
    $puede_ver_tsu = tieneNotasEnTrayectos($materias_carrera, $notas_estudiante, [0, 1, 2]);
    $puede_ver_ingenieria = tieneNotasEnTrayectos($materias_carrera, $notas_estudiante, [3, 4]);
    $puede_ver_completo = tieneNotasEnTrayectos($materias_carrera, $notas_estudiante, [0, 1, 2, 3, 4]);
}

// Evaluación institucional: Adición y Retiro (Únicamente Automotriz y Térmica, primeras 2 semanas)
$eval_adicion_retiro = ($estudiante && isset($estudiante['id']) && function_exists('verificarElegibilidadAdicionRetiro')) 
    ? verificarElegibilidadAdicionRetiro($estudiante['id']) 
    : ['es_apto' => false, 'carrera_valida' => false, 'lapso_valido' => false, 'motivo' => ''];
?>

<div class="container-fluid px-2 px-sm-3 px-md-4">
    <h2 class="my-4 h2-sm">Mi Historial de Notas</h2>
    
    <?php if ($estudiante && $carrera): ?>
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-info text-white d-flex flex-column flex-sm-row justify-content-between align-items-center">
            <h5 class="mb-2 mb-sm-0">Información del Estudiante</h5>
            
            <?php if ($puede_ver_tsu || $puede_ver_ingenieria || $puede_ver_completo): ?>
            <div class="btn-group btn-group-sm flex-wrap justify-content-center" role="group">
                <?php if ($puede_ver_tsu): ?>
                <a href="../admin/historial_desglozado_tsu.php?estudiante_id=<?= $estudiante['id'] ?>&cedula=<?= urlencode($estudiante['idusuario']) ?>&nombre=<?= urlencode($estudiante['nombre']) ?>&carrera=<?= urlencode($carrera['nombre_carrera']) ?>" 
                   class="btn btn-warning mb-1 mb-sm-0" target="_blank">
                    <i class="fas fa-file-pdf"></i> <span class="d-none d-sm-inline">Historial TSU</span><span class="d-inline d-sm-none">TSU</span>
                </a>
                <?php endif; ?>
                
                <?php if ($puede_ver_ingenieria): ?>
                <a href="../admin/historial_desglozado_ingenieria.php?estudiante_id=<?= $estudiante['id'] ?>&cedula=<?= urlencode($estudiante['idusuario']) ?>&nombre=<?= urlencode($estudiante['nombre']) ?>&carrera=<?= urlencode($carrera['nombre_carrera']) ?>" 
                   class="btn btn-info mb-1 mb-sm-0" target="_blank">
                    <i class="fas fa-file-pdf"></i> <span class="d-none d-sm-inline">Historial Ingeniería</span><span class="d-inline d-sm-none">Ing.</span>
                </a>
                <?php endif; ?>
                
                <?php if ($puede_ver_completo): ?>
                <a href="../admin/generar_reporte_consulta.php?estudiante_id=<?= $estudiante['id'] ?>&cedula=<?= urlencode($estudiante['idusuario']) ?>&nombre=<?= urlencode($estudiante['nombre']) ?>&carrera=<?= urlencode($carrera['nombre_carrera']) ?>" 
                   class="btn btn-danger mb-1 mb-sm-0" target="_blank">
                    <i class="fas fa-file-pdf"></i> <span class="d-none d-sm-inline">Historial Completo</span><span class="d-inline d-sm-none">Completo</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-2 p-sm-3">
            <div class="row">
                <div class="col-12 col-md-6 mb-2 mb-md-0">
                    <p><strong><i class="fas fa-id-card"></i> Cédula:</strong> <?= htmlspecialchars($estudiante['idusuario']) ?></p>
                    <p><strong><i class="fas fa-user"></i> Nombre:</strong> <?= htmlspecialchars($estudiante['nombre']) ?></p>
                </div>
                <div class="col-12 col-md-6">
                    <p><strong><i class="fas fa-graduation-cap"></i> Carrera:</strong> <?= htmlspecialchars($carrera['nombre_carrera']) ?> (<?= htmlspecialchars($carrera['cod_carrera']) ?>)</p>
                    <p><strong><i class="fas fa-book"></i> Materias Inscritas:</strong> <span class="badge badge-primary"><?= $materias_carrera->num_rows ?></span></p>
                </div>
            </div>
            
            <?php if (!$puede_ver_tsu && !$puede_ver_ingenieria && !$puede_ver_completo): ?>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i> 
                Aún no tienes notas aprobadas para generar reportes. Los reportes estarán disponibles cuando tengas al menos una materia aprobada.
            </div>
            <?php else: ?>
            <div class="alert alert-light mt-3">
                <i class="fas fa-file-pdf"></i> 
                <strong>Reportes disponibles:</strong>
                <?php if ($puede_ver_tsu): ?> 
                    <span class="badge badge-warning ml-2">TSU</span>
                <?php endif; ?>
                <?php if ($puede_ver_ingenieria): ?> 
                    <span class="badge badge-info ml-2">Ingeniería</span>
                <?php endif; ?>
                <?php if ($puede_ver_completo): ?> 
                    <span class="badge badge-danger ml-2">Completo</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Panel de Regulación Institucional: Adición y Retiro de Materias -->
    <?php if (!empty($eval_adicion_retiro['es_apto'])): ?>
        <div class="card mb-4 border-info shadow-sm">
            <div class="card-header bg-info text-white d-flex flex-column flex-sm-row justify-content-between align-items-center py-2">
                <h6 class="mb-1 mb-sm-0 font-weight-bold text-uppercase">
                    <i class="fas fa-exchange-alt mr-1"></i> Período de Adición y Retiro Habilitado
                </h6>
                <span class="badge badge-light text-info font-weight-bold">
                    <i class="fas fa-clock mr-1"></i> Quedan <?= $eval_adicion_retiro['dias_restantes'] ?> días
                </span>
            </div>
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-8 mb-2 mb-md-0">
                        <p class="mb-1 text-dark">
                            Tu carrera (<strong><?= htmlspecialchars($carrera['nombre_carrera']) ?></strong>) tiene habilitado el proceso de <strong>Adición y Retiro de Asignaturas</strong> durante las 2 primeras semanas del período <strong><?= htmlspecialchars($eval_adicion_retiro['periodo_nombre']) ?></strong>.
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-calendar-alt mr-1 text-primary"></i> Período hábil: del <strong><?= $eval_adicion_retiro['fecha_inicio'] ?></strong> hasta el <strong><?= $eval_adicion_retiro['fecha_limite'] ?></strong>.
                        </small>
                    </div>
                    <div class="col-md-4 text-md-right">
                        <a href="mis_constancias.php?solicitud=adicion_retiro" class="btn btn-info font-weight-bold shadow-sm">
                            <i class="fas fa-edit mr-1"></i> Solicitar Adición / Retiro
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (!empty($eval_adicion_retiro['carrera_valida']) && empty($eval_adicion_retiro['lapso_valido'])): ?>
        <div class="alert alert-secondary border mb-4 shadow-sm py-2 px-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-history text-secondary fa-2x mr-3"></i>
                <div>
                    <strong class="text-dark">Lapso de Adición y Retiro Finalizado</strong>
                    <p class="mb-0 small text-muted">
                        El proceso de Adición y Retiro para tu carrera (<strong><?= htmlspecialchars($carrera['nombre_carrera']) ?></strong>) estuvo activo durante las 2 primeras semanas del período y culminó el <strong><?= $eval_adicion_retiro['fecha_limite'] ?? 'lapso límite' ?></strong>. Si requieres gestionar un trámite extemporáneo, debes acudir a Control de Estudios.
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-light border mb-4 py-2 px-3 small text-muted shadow-sm">
            <i class="fas fa-info-circle text-info mr-1"></i> 
            <strong>Normativa Institucional:</strong> El proceso de <strong>Adición y Retiro / Retiro</strong> de asignaturas es <strong>únicamente para las carreras Mecánica Automotriz y Mecánica Térmica</strong>, y solo durante las <strong>2 primeras semanas</strong> de cada período académico.
        </div>
    <?php endif; ?>

    <?php if ($materias_carrera && $materias_carrera->num_rows > 0): ?>
    <div class="card shadow mb-4">
        <div class="card-header bg-success text-white d-flex flex-column flex-sm-row justify-content-between align-items-center">
            <h5 class="mb-1 mb-sm-0">Materias Inscritas y Notas por Trimestre</h5>
            <?php if (!empty($eval_adicion_retiro['es_apto'])): ?>
                <a href="mis_constancias.php?solicitud=adicion_retiro" class="btn btn-warning btn-sm font-weight-bold text-dark shadow-sm">
                    <i class="fas fa-exchange-alt mr-1"></i> Adición / Retiro (Quedan <?= $eval_adicion_retiro['dias_restantes'] ?> días)
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-2 p-sm-3">
            <!-- Vista para escritorio -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-bordered table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>Trayecto</th>
                            <th>Materia</th>
                            <th>Código</th>
                            <th class="text-center">Trimestre 1</th>
                            <th class="text-center">Trimestre 2</th>
                            <th class="text-center">Trimestre 3</th>
                            <th class="text-center">Nota Final</th>
                            <th class="text-center">Estado</th>
                            <th>Periodo</th>
                            <th>Fecha</th>
                            <th>Aprobado por</th>
                            <?php if (!empty($eval_adicion_retiro['es_apto'])): ?>
                                <th class="text-center" width="110">Acción</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $materias_aprobadas = 0;
                        $materias_reprobadas = 0;
                        $materias_sin_notas = 0;
                        $suma_promedios = 0;
                        $materias_con_notas = 0;
                        $todas_materias = [];
                        
                        $materias_carrera->data_seek(0);
                        while ($materia = $materias_carrera->fetch_assoc()): 
                            $todas_materias[] = $materia;
                            $nota = isset($notas_estudiante[$materia['id_materia']]) ? $notas_estudiante[$materia['id_materia']] : null;
                            
                            $numero_trayecto_materia = (int)$materia['trayecto'];
                            $info_trayecto = obtenerInfoTrayecto($numero_trayecto_materia);
                            $nombre_trayecto = $info_trayecto['nombre_trayecto'];
                            
                            $t1 = $nota['trimestre_1'] ?? null;
                            $t2 = $nota['trimestre_2'] ?? null;
                            $t3 = $nota['trimestre_3'] ?? null;
                            $nota_final = $nota['nota_final'] ?? null;
                            
                            $estado = 'Sin notas';
                            $badge_estado = 'secondary';
                            
                            if ($nota_final !== null) {
                                if ($nota_final >= 12) {
                                    $estado = 'Aprobado';
                                    $badge_estado = 'success';
                                    $materias_aprobadas++;
                                } else {
                                    $estado = 'Reprobado';
                                    $badge_estado = 'danger';
                                    $materias_reprobadas++;
                                }
                                $suma_promedios += $nota_final;
                                $materias_con_notas++;
                            } else {
                                $materias_sin_notas++;
                            }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($nombre_trayecto) ?></td>
                                <td><?= htmlspecialchars($materia['nombre_materia']) ?></td>
                                <td><?= htmlspecialchars($materia['cod_materia']) ?></td>
                                <td class="text-center">
                                    <?php if ($t1 !== null): ?>
                                        <span class="badge <?= $t1 >= 12 ? 'bg-success' : 'bg-danger' ?>">
                                            <?= number_format($t1, 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($t2 !== null): ?>
                                        <span class="badge <?= $t2 >= 12 ? 'bg-success' : 'bg-danger' ?>">
                                            <?= number_format($t2, 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($t3 !== null): ?>
                                        <span class="badge <?= $t3 >= 12 ? 'bg-success' : 'bg-danger' ?>">
                                            <?= number_format($t3, 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($nota_final !== null): ?>
                                        <span class="badge <?= $nota_final >= 12 ? 'bg-success' : 'bg-danger' ?>">
                                            <?= number_format($nota_final, 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-<?= $badge_estado ?>"><?= $estado ?></span>
                                </td>
                                <td><?= $nota ? htmlspecialchars($nota['nombre_periodo']) : '-' ?></td>
                                <td><?= $nota && $nota['fecha_registro'] ? date('d/m/Y', strtotime($nota['fecha_registro'])) : '-' ?></td>
                                <td><?= $nota && !empty($nota['nombre_admin']) ? htmlspecialchars($nota['nombre_admin']) : '-' ?></td>
                                <?php if (!empty($eval_adicion_retiro['es_apto'])): ?>
                                    <td class="text-center">
                                        <a href="mis_constancias.php?solicitud=adicion_retiro&retirar_materia=<?= $materia['id_materia'] ?>" 
                                           class="btn btn-outline-danger btn-sm py-0 px-2 font-weight-bold" 
                                           title="Solicitar retiro de esta asignatura">
                                            <i class="fas fa-minus-circle mr-1"></i> Retirar
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Vista para móviles -->
            <div class="d-block d-md-none">
                <?php 
                $materias_carrera->data_seek(0);
                while ($materia = $materias_carrera->fetch_assoc()): 
                    $nota = isset($notas_estudiante[$materia['id_materia']]) ? $notas_estudiante[$materia['id_materia']] : null;
                    $numero_trayecto_materia = (int)$materia['trayecto'];
                    $info_trayecto = obtenerInfoTrayecto($numero_trayecto_materia);
                    $nombre_trayecto = $info_trayecto['nombre_trayecto'];
                    
                    $t1 = $nota['trimestre_1'] ?? null;
                    $t2 = $nota['trimestre_2'] ?? null;
                    $t3 = $nota['trimestre_3'] ?? null;
                    $nota_final = $nota['nota_final'] ?? null;
                    
                    $estado = 'Sin notas';
                    $badge_estado = 'secondary';
                    
                    if ($nota_final !== null) {
                        if ($nota_final >= 12) {
                            $estado = 'Aprobado';
                            $badge_estado = 'success';
                        } else {
                            $estado = 'Reprobado';
                            $badge_estado = 'danger';
                        }
                    }
                ?>
                    <div class="card mb-3 shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-primary"><?= htmlspecialchars($materia['cod_materia']) ?></strong>
                                <span class="badge badge-<?= $badge_estado ?>"><?= $estado ?></span>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <h6 class="card-title mb-3"><?= htmlspecialchars($materia['nombre_materia']) ?></h6>
                            
                            <div class="row mb-2">
                                <div class="col-5 text-muted"><i class="fas fa-layer-group"></i> Trayecto:</div>
                                <div class="col-7"><?= htmlspecialchars($nombre_trayecto) ?></div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-5 text-muted"><i class="fas fa-chart-line"></i> Trimestres:</div>
                                <div class="col-7">
                                    <?php if ($t1 || $t2 || $t3): ?>
                                        T1: <?= $t1 ? number_format($t1,1) : '-' ?> |
                                        T2: <?= $t2 ? number_format($t2,1) : '-' ?> |
                                        T3: <?= $t3 ? number_format($t3,1) : '-' ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin notas</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-5 text-muted"><i class="fas fa-star"></i> Nota Final:</div>
                                <div class="col-7">
                                    <?php if ($nota_final !== null): ?>
                                        <span class="badge <?= $nota_final >= 12 ? 'bg-success' : 'bg-danger' ?>">
                                            <?= number_format($nota_final, 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-5 text-muted"><i class="fas fa-calendar-alt"></i> Periodo:</div>
                                <div class="col-7"><?= $nota ? htmlspecialchars($nota['nombre_periodo']) : '-' ?></div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-5 text-muted"><i class="fas fa-calendar-check"></i> Fecha:</div>
                                <div class="col-7"><?= $nota && $nota['fecha_registro'] ? date('d/m/Y', strtotime($nota['fecha_registro'])) : '-' ?></div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-5 text-muted"><i class="fas fa-user-check"></i> Aprobado por:</div>
                                <div class="col-7"><?= $nota && !empty($nota['nombre_admin']) ? htmlspecialchars($nota['nombre_admin']) : '-' ?></div>
                            </div>
                            
                            <?php if (!empty($eval_adicion_retiro['es_apto'])): ?>
                                <div class="mt-2 pt-2 border-top text-right">
                                    <a href="mis_constancias.php?solicitud=adicion_retiro&retirar_materia=<?= $materia['id_materia'] ?>" 
                                       class="btn btn-outline-danger btn-sm py-1 px-2 font-weight-bold">
                                        <i class="fas fa-minus-circle mr-1"></i> Retirar Asignatura
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Resumen estadístico -->
            <div class="row mt-4">
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-chart-line"></i> Resumen Académico</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $total_materias = $materias_carrera->num_rows;
                            $promedio_general = $materias_con_notas > 0 ? round($suma_promedios / $materias_con_notas, 1) : 0;
                            $porcentaje_aprobadas = $materias_con_notas > 0 ? round(($materias_aprobadas / $materias_con_notas) * 100, 1) : 0;
                            $porcentaje_completado = $total_materias > 0 ? round(($materias_con_notas / $total_materias) * 100, 1) : 0;
                            ?>
                            
                            <div class="row text-center mb-3">
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="h4 text-primary"><?= $total_materias ?></div>
                                    <small>Materias Inscritas</small>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="h4 text-success"><?= $materias_aprobadas ?></div>
                                    <small>Aprobadas</small>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="h4 text-danger"><?= $materias_reprobadas ?></div>
                                    <small>Reprobadas</small>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="h4 text-warning"><?= $materias_sin_notas ?></div>
                                    <small>Pendientes</small>
                                </div>
                            </div>
                            
                            <div class="mt-2">
                                <p><strong>Promedio General:</strong> 
                                    <span class="badge badge-<?= $promedio_general >= 12 ? 'success' : ($promedio_general > 0 ? 'warning' : 'secondary') ?>">
                                        <?= $promedio_general > 0 ? $promedio_general : 'N/A' ?>
                                    </span>
                                </p>
                                <p><strong>Progreso:</strong> 
                                    <span class="badge badge-info"><?= $porcentaje_completado ?>% completado</span>
                                </p>
                                <p><strong>Efectividad:</strong> 
                                    <span class="badge badge-<?= $porcentaje_aprobadas >= 80 ? 'success' : ($porcentaje_aprobadas >= 50 ? 'warning' : 'danger') ?>">
                                        <?= $porcentaje_aprobadas ?>% de aprobación
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Progreso de Materias Inscritas</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($total_materias > 0): 
                            $materias_por_trayecto = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0];
                            foreach ($todas_materias as $materia) {
                                $trayecto = (int)$materia['trayecto'];
                                if (isset($materias_por_trayecto[$trayecto])) {
                                    $materias_por_trayecto[$trayecto]++;
                                }
                            }
                            ?>
                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar bg-success" style="width: <?= $porcentaje_completado ?>%">
                                    <?= $porcentaje_completado ?>%
                                </div>
                            </div>
                            
                            <div class="progress mb-3" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?= ($materias_aprobadas / $total_materias) * 100 ?>%">
                                    Aprob: <?= $materias_aprobadas ?>
                                </div>
                                <div class="progress-bar bg-danger" style="width: <?= ($materias_reprobadas / $total_materias) * 100 ?>%">
                                    Rep: <?= $materias_reprobadas ?>
                                </div>
                                <div class="progress-bar bg-secondary" style="width: <?= ($materias_sin_notas / $total_materias) * 100 ?>%">
                                    Pend: <?= $materias_sin_notas ?>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">Distribución por Trayectos:</small>
                                <div class="row mt-1">
                                    <?php for ($i = 0; $i <= 4; $i++): 
                                        if ($materias_por_trayecto[$i] > 0): ?>
                                        <div class="col-3 col-md-2 mb-1">
                                            <small><strong>T<?= $i ?>:</strong> <?= $materias_por_trayecto[$i] ?></small>
                                        </div>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-warning">
            No tienes materias inscritas en la carrera: <?= htmlspecialchars($carrera['nombre_carrera']) ?>
        </div>
    <?php endif; ?>
    
    <?php else: ?>
        <div class="alert alert-danger">
            No se pudo cargar la información del estudiante.
        </div>
    <?php endif; ?>
</div>

<style>
@media (max-width: 767.98px) {
    .h2-sm { font-size: 1.4rem; }
    .card-header { padding: 0.75rem; }
    .btn-group { display: flex; flex-wrap: wrap; gap: 0.25rem; }
    .h4 { font-size: 1.2rem; }
}
@media (min-width: 768px) and (max-width: 991.98px) {
    .table th, .table td { padding: 0.5rem; font-size: 0.8rem; }
}
.card { border-radius: 0.5rem; overflow: hidden; }
.badge { font-size: 0.75rem; padding: 0.35rem 0.65rem; }
.bg-success { background-color: #28a745 !important; color: white !important; }
.bg-danger { background-color: #dc3545 !important; color: white !important; }
.bg-warning { background-color: #ffc107 !important; color: #212529 !important; }
</style>

<?php include("includes/footer.php"); ?>