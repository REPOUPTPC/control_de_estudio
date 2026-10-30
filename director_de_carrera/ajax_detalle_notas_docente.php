<?php
require_once(__DIR__ . '/../funciones/functions.php');

cargarPermisosUsuario();

if (!isLoggedIn() || !isDirectorCarrera()) {
    echo '<div class="alert alert-danger">No tienes permisos de Director de Carrera.</div>';
    exit;
}

$id_docente = isset($_GET['id_docente']) ? intval($_GET['id_docente']) : 0;
$id_materia = isset($_GET['id_materia']) ? intval($_GET['id_materia']) : 0;
$id_seccion = isset($_GET['id_seccion']) ? intval($_GET['id_seccion']) : 0;

if ($id_docente <= 0 || $id_materia <= 0 || $id_seccion <= 0) {
    echo '<div class="alert alert-danger">Parámetros incompletos.</div>';
    exit;
}

global $db;

// Obtener info docente, materia y seccion
$stmt = $db->prepare("SELECT u.nombre as docente, u.idusuario as cedula_docente, u.email as email_docente,
                             m.nombre_materia, m.cod_materia,
                             s.codigo_seccion, s.turno, c.nombre_carrera
                      FROM docente_seccion ds
                      JOIN users u ON ds.id_usuario = u.id
                      JOIN materias m ON ds.id_materia = m.id_materia
                      JOIN secciones s ON ds.id_seccion = s.id_seccion
                      JOIN carreras c ON s.id_carrera = c.id_carrera
                      WHERE ds.id_usuario = ? AND ds.id_materia = ? AND ds.id_seccion = ?");
$stmt->bind_param("iii", $id_docente, $id_materia, $id_seccion);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$info) {
    echo '<div class="alert alert-warning">No se encontró información de la asignación.</div>';
    exit;
}

// Obtener estudiantes inscritos en la sección con sus notas en esta materia
$sql_estudiantes = "SELECT 
                        u.id as estudiante_id,
                        u.idusuario as cedula,
                        u.nombre as nombre_estudiante,
                        es.estatus as estatus_inscripcion,
                        -- Notas trimestrales
                        (SELECT nt.nota FROM notas_trimestres nt WHERE nt.id_usuario = u.id AND nt.id_materia = $id_materia AND nt.id_docente = $id_docente AND nt.trimestre_num = 1 ORDER BY nt.id DESC LIMIT 1) as t1,
                        (SELECT nt.estado FROM notas_trimestres nt WHERE nt.id_usuario = u.id AND nt.id_materia = $id_materia AND nt.id_docente = $id_docente AND nt.trimestre_num = 1 ORDER BY nt.id DESC LIMIT 1) as t1_estado,
                        (SELECT nt.nota FROM notas_trimestres nt WHERE nt.id_usuario = u.id AND nt.id_materia = $id_materia AND nt.id_docente = $id_docente AND nt.trimestre_num = 2 ORDER BY nt.id DESC LIMIT 1) as t2,
                        (SELECT nt.estado FROM notas_trimestres nt WHERE nt.id_usuario = u.id AND nt.id_materia = $id_materia AND nt.id_docente = $id_docente AND nt.trimestre_num = 2 ORDER BY nt.id DESC LIMIT 1) as t2_estado,
                        (SELECT nt.nota FROM notas_trimestres nt WHERE nt.id_usuario = u.id AND nt.id_materia = $id_materia AND nt.id_docente = $id_docente AND nt.trimestre_num = 3 ORDER BY nt.id DESC LIMIT 1) as t3,
                        (SELECT nt.estado FROM notas_trimestres nt WHERE nt.id_usuario = u.id AND nt.id_materia = $id_materia AND nt.id_docente = $id_docente AND nt.trimestre_num = 3 ORDER BY nt.id DESC LIMIT 1) as t3_estado
                    FROM estudiante_seccion es
                    JOIN users u ON es.id_usuario = u.id
                    WHERE es.id_seccion = $id_seccion AND es.estatus = 'activo'
                    ORDER BY u.nombre ASC";

$result_est = $db->query($sql_estudiantes);
$estudiantes = [];
if ($result_est) {
    while ($r = $result_est->fetch_assoc()) {
        $estudiantes[] = $r;
    }
}

// Soporte cargado
$soporte_stmt = $db->prepare("SELECT soporte, tipo_archivo, fecha_subida FROM notas_pendientes WHERE id_docente = ? AND id_materia = ? AND soporte IS NOT NULL ORDER BY id DESC LIMIT 1");
$soporte_stmt->bind_param("ii", $id_docente, $id_materia);
$soporte_stmt->execute();
$soporte_info = $soporte_stmt->get_result()->fetch_assoc();
$soporte_stmt->close();
?>

<div class="card border-0 mb-3">
    <div class="card-body p-3 bg-light rounded shadow-sm">
        <div class="row">
            <div class="col-md-6">
                <p class="mb-1"><strong><i class="fas fa-chalkboard-teacher text-primary mr-1"></i> Docente:</strong> <?= htmlspecialchars($info['docente']) ?> (C.I: <?= htmlspecialchars($info['cedula_docente']) ?>)</p>
                <p class="mb-1"><strong><i class="fas fa-book text-info mr-1"></i> Materia:</strong> <?= htmlspecialchars($info['nombre_materia']) ?> (<?= htmlspecialchars($info['cod_materia']) ?>)</p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong><i class="fas fa-door-open text-warning mr-1"></i> Sección:</strong> <?= htmlspecialchars($info['codigo_seccion']) ?> | Turno: <?= htmlspecialchars($info['turno']) ?></p>
                <p class="mb-1"><strong><i class="fas fa-graduation-cap text-success mr-1"></i> Carrera:</strong> <?= htmlspecialchars($info['nombre_carrera']) ?></p>
            </div>
        </div>
        <?php if ($soporte_info && !empty($soporte_info['soporte'])): ?>
            <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge badge-success px-2 py-1"><i class="fas fa-paperclip mr-1"></i> Acta de soporte adjunta</span>
                    <span class="small text-muted ml-2">Subido el: <?= date('d/m/Y h:i A', strtotime($soporte_info['fecha_subida'])) ?></span>
                </div>
                <a href="../soportes/<?= htmlspecialchars($soporte_info['soporte']) ?>" target="_blank" class="btn btn-xs btn-outline-danger btn-sm py-0 px-2 font-weight-bold">
                    <i class="fas fa-file-pdf mr-1"></i> Ver Soporte
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($estudiantes)): ?>
    <div class="alert alert-warning text-center py-4">
        <i class="fas fa-user-slash fa-2x mb-2 text-warning"></i>
        <h6>No hay estudiantes activos inscritos en esta sección.</h6>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover table-bordered text-center align-middle mb-0">
            <thead class="bg-dark text-white">
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 15%;">Cédula</th>
                    <th class="text-left" style="width: 35%;">Estudiante</th>
                    <th style="width: 12%;">Trimestre 1</th>
                    <th style="width: 12%;">Trimestre 2</th>
                    <th style="width: 12%;">Trimestre 3</th>
                    <th style="width: 9%;">Definitiva</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 0;
                $con_nota = 0;
                foreach ($estudiantes as $est): 
                    $count++;
                    $t1 = $est['t1'] ?? null;
                    $t2 = $est['t2'] ?? null;
                    $t3 = $est['t3'] ?? null;
                    $tiene_alguna = ($t1 !== null || $t2 !== null || $t3 !== null);
                    if ($tiene_alguna) $con_nota++;

                    // Promedio o nota definitiva
                    $notas_validas = array_filter([$t1, $t2, $t3], function($v) { return $v !== null && is_numeric($v); });
                    $definitiva = count($notas_validas) > 0 ? round(array_sum($notas_validas) / count($notas_validas), 1) : null;
                ?>
                    <tr>
                        <td><?= $count ?></td>
                        <td><strong><?= htmlspecialchars($est['cedula']) ?></strong></td>
                        <td class="text-left"><?= htmlspecialchars($est['nombre_estudiante']) ?></td>
                        <td>
                            <?php if ($t1 !== null): ?>
                                <span class="badge <?= $t1 >= 10 ? 'badge-success' : 'badge-danger' ?> px-2 py-1" style="font-size: 0.85rem;">
                                    <?= number_format($t1, 1) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-light text-muted border">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t2 !== null): ?>
                                <span class="badge <?= $t2 >= 10 ? 'badge-success' : 'badge-danger' ?> px-2 py-1" style="font-size: 0.85rem;">
                                    <?= number_format($t2, 1) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-light text-muted border">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t3 !== null): ?>
                                <span class="badge <?= $t3 >= 10 ? 'badge-success' : 'badge-danger' ?> px-2 py-1" style="font-size: 0.85rem;">
                                    <?= number_format($t3, 1) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-light text-muted border">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($definitiva !== null): ?>
                                <strong class="<?= $definitiva >= 10 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($definitiva, 1) ?>
                                </strong>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3 px-1">
        <span class="small text-muted font-weight-bold">
            Total Estudiantes: <?= count($estudiantes) ?> | Con Calificaciones: <?= $con_nota ?>
        </span>
        <span class="badge <?= $con_nota == count($estudiantes) ? 'badge-success' : ($con_nota > 0 ? 'badge-warning text-dark' : 'badge-danger') ?> px-3 py-2 font-weight-bold">
            <?= $con_nota == count($estudiantes) ? '<i class="fas fa-check-circle mr-1"></i> SECCIÓN COMPLETA' : ($con_nota > 0 ? '<i class="fas fa-clock mr-1"></i> CARGA PARCIAL' : '<i class="fas fa-exclamation-circle mr-1"></i> SIN NOTAS CARGADAS') ?>
        </span>
    </div>
<?php endif; ?>
