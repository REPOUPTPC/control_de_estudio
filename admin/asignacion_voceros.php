<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulopag = "Asignación de Voceros Estudiantiles - Control de Estudios";
require_once('../funciones/functions.php');

// Verificar permisos de administración
cargarPermisosUsuario();
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['msg'] = "Debes iniciar sesión como administrador para acceder";
    header('location: ../login.php');
    exit();
}

visita();

$registros_por_pagina = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax']) && !(isset($_POST['action']) && $_POST['action'] === 'toggle_vocero')) {
    $pagina_actual = isset($_POST['pagina']) ? max(1, intval($_POST['pagina'])) : 1;
    $carrera_filtro = isset($_POST['id_carrera']) ? intval($_POST['id_carrera']) : 0;
    $q = isset($_POST['q']) ? trim($_POST['q']) : '';
    $filter_voceros = isset($_POST['filter_voceros']) && $_POST['filter_voceros'] === '1';
} else {
    $pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : (isset($_POST['pagina']) ? max(1, intval($_POST['pagina'])) : 1);
    $carrera_filtro = isset($_GET['id_carrera']) ? intval($_GET['id_carrera']) : (isset($_POST['id_carrera']) ? intval($_POST['id_carrera']) : 0);
    $q = isset($_GET['q']) ? trim($_GET['q']) : (isset($_POST['q']) ? trim($_POST['q']) : '');
    $filter_voceros = (isset($_GET['filter_voceros']) && $_GET['filter_voceros'] === '1') || (isset($_POST['filter_voceros']) && $_POST['filter_voceros'] === '1');
}

$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener todas las carreras disponibles
$carreras_res = $db->query("SELECT id_carrera, nombre_carrera FROM carreras WHERE activa = 1 ORDER BY nombre_carrera ASC");
$lista_carreras = [];
if ($carreras_res) {
    while ($c = $carreras_res->fetch_assoc()) {
        $lista_carreras[] = $c;
    }
}

// Subconsulta reutilizable para información de secciones activas del estudiante
$from_sql = "FROM users u 
             LEFT JOIN carreras c ON c.id_carrera = u.carrera 
             LEFT JOIN (
                 SELECT 
                     es.id_usuario,
                     GROUP_CONCAT(DISTINCT s.codigo_seccion ORDER BY s.codigo_seccion ASC SEPARATOR ', ') AS codigo_seccion,
                     GROUP_CONCAT(DISTINCT s.id_seccion ORDER BY s.id_seccion DESC SEPARATOR ',') AS ids_secciones,
                     GROUP_CONCAT(DISTINCT COALESCE(t.nombre_trayecto, CONCAT('Trayecto ', s.id_trayecto)) SEPARATOR ', ') AS trayectos
                 FROM estudiante_seccion es
                 INNER JOIN secciones s ON s.id_seccion = es.id_seccion
                 LEFT JOIN trayectos t ON t.id_trayecto = s.id_trayecto
                 WHERE es.estatus = 'activo'
                 GROUP BY es.id_usuario
             ) sec_info ON sec_info.id_usuario = u.id";

// Procesar petición AJAX para alternar vocero
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_vocero') {
    header('Content-Type: application/json; charset=utf-8');
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $new_value = isset($_POST['value']) ? intval($_POST['value']) : 0;

    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'msg' => 'ID de estudiante no válido']);
        exit();
    }

    $query_check = "SELECT id, nombre, idusuario, vocero, carrera FROM users WHERE id = ? AND estudiante = 1 LIMIT 1";
    $stmt = $db->prepare($query_check);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'msg' => 'Estudiante no encontrado']);
        exit();
    }

    $user = $result->fetch_assoc();
    $old_values = ['vocero' => intval($user['vocero'])];
    $new_values = ['vocero' => $new_value];

    $query_update = "UPDATE users SET vocero = ? WHERE id = ?";
    $stmt2 = $db->prepare($query_update);
    $stmt2->bind_param('ii', $new_value, $user_id);
    $ok = $stmt2->execute();

    if ($ok) {
        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                'UPDATE',
                'users',
                $user_id,
                $old_values,
                $new_values,
                'Voceros',
                'Asignación/retirada de vocero por administrador para: ' . ($user['idusuario'] ?? $user['nombre'])
            );
        }

        // Obtener la sección del estudiante si está asignado a alguna
        $query_sec = "SELECT 
                        GROUP_CONCAT(DISTINCT s.codigo_seccion ORDER BY s.codigo_seccion ASC SEPARATOR ', ') as codigo_seccion,
                        GROUP_CONCAT(DISTINCT COALESCE(t.nombre_trayecto, CONCAT('Trayecto ', s.id_trayecto)) SEPARATOR ', ') as trayectos
                      FROM estudiante_seccion es
                      INNER JOIN secciones s ON s.id_seccion = es.id_seccion
                      LEFT JOIN trayectos t ON t.id_trayecto = s.id_trayecto
                      WHERE es.id_usuario = ? AND es.estatus = 'activo'";
        $stmt_sec = $db->prepare($query_sec);
        $stmt_sec->bind_param('i', $user_id);
        $stmt_sec->execute();
        $sec_info = $stmt_sec->get_result()->fetch_assoc();
        $stmt_sec->close();

        echo json_encode([
            'success' => true, 
            'vocero' => $new_value,
            'codigo_seccion' => $sec_info['codigo_seccion'] ?? '',
            'trayectos' => $sec_info['trayectos'] ?? ''
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'msg' => 'Error al actualizar en base de datos']);
    exit();
}

// Petición AJAX para búsqueda (soporta POST y GET)
if ((isset($_POST['ajax']) && $_POST['ajax'] === 'search') || (isset($_POST['action']) && $_POST['action'] === 'search') || (isset($_GET['ajax']) && $_GET['ajax'] === 'search')) {
    header('Content-Type: application/json; charset=utf-8');
    $q_ajax = trim($_POST['q'] ?? $_GET['q'] ?? '');
    $carrera_ajax = intval($_POST['id_carrera'] ?? $_GET['id_carrera'] ?? 0);
    $filter_voc_ajax = ($_POST['filter_voceros'] ?? $_GET['filter_voceros'] ?? '') === '1';
    $pagina_ajax = max(1, intval($_POST['pagina'] ?? $_GET['pagina'] ?? 1));
    $offset_ajax = ($pagina_ajax - 1) * $registros_por_pagina;

    $conditions = ["u.estudiante = 1"];
    $types = "";
    $params_query = [];

    if ($carrera_ajax > 0) {
        $conditions[] = "u.carrera = ?";
        $types .= "i";
        $params_query[] = $carrera_ajax;
    }

    if ($filter_voc_ajax) {
        $conditions[] = "u.vocero = 1";
    }

    if ($q_ajax !== '') {
        $conditions[] = "(u.nombre LIKE ? OR u.idusuario LIKE ? OR sec_info.codigo_seccion LIKE ?)";
        $types .= "sss";
        $like = "%" . $q_ajax . "%";
        $params_query[] = $like;
        $params_query[] = $like;
        $params_query[] = $like;
    }

    $where_sql = "WHERE " . implode(" AND ", $conditions);

    // Conteo
    $count_sql = "SELECT COUNT(*) as total $from_sql $where_sql";
    $stmt_c = $db->prepare($count_sql);
    if (!empty($types)) {
        $stmt_c->bind_param($types, ...$params_query);
    }
    $stmt_c->execute();
    $total_registros = $stmt_c->get_result()->fetch_assoc()['total'];
    $total_paginas = max(1, ceil($total_registros / $registros_por_pagina));

    // Datos
    $query_data = "SELECT u.id, u.nombre, u.idusuario, u.vocero, u.carrera, c.nombre_carrera,
                          COALESCE(sec_info.codigo_seccion, '') AS codigo_seccion,
                          COALESCE(sec_info.ids_secciones, '') AS ids_secciones,
                          COALESCE(sec_info.trayectos, '') AS trayectos 
                   $from_sql 
                   $where_sql 
                   ORDER BY u.nombre ASC 
                   LIMIT ? OFFSET ?";
    $types_data = $types . "ii";
    $params_data = array_merge($params_query, [$registros_por_pagina, $offset_ajax]);

    $stmt_d = $db->prepare($query_data);
    $stmt_d->bind_param($types_data, ...$params_data);
    $stmt_d->execute();
    $res_d = $stmt_d->get_result();
    $rows = [];
    while ($r = $res_d->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'pagination' => [
            'current_page' => $pagina_ajax,
            'total_pages' => $total_paginas,
            'total_records' => $total_registros,
            'per_page' => $registros_por_pagina
        ]
    ]);
    exit();
}

// Carga inicial no-AJAX
$conditions = ["u.estudiante = 1"];
$types = "";
$params_query = [];

if ($carrera_filtro > 0) {
    $conditions[] = "u.carrera = ?";
    $types .= "i";
    $params_query[] = $carrera_filtro;
}

if ($filter_voceros) {
    $conditions[] = "u.vocero = 1";
}

if ($q !== '') {
    $conditions[] = "(u.nombre LIKE ? OR u.idusuario LIKE ? OR sec_info.codigo_seccion LIKE ?)";
    $types .= "sss";
    $like = "%" . $q . "%";
    $params_query[] = $like;
    $params_query[] = $like;
    $params_query[] = $like;
}

$where_sql = "WHERE " . implode(" AND ", $conditions);

$count_sql = "SELECT COUNT(*) as total $from_sql $where_sql";
$stmt_c = $db->prepare($count_sql);
if (!empty($types)) {
    $stmt_c->bind_param($types, ...$params_query);
}
$stmt_c->execute();
$total_registros = $stmt_c->get_result()->fetch_assoc()['total'];
$total_paginas = max(1, ceil($total_registros / $registros_por_pagina));

$query_data = "SELECT u.id, u.nombre, u.idusuario, u.vocero, u.carrera, c.nombre_carrera,
                      COALESCE(sec_info.codigo_seccion, '') AS codigo_seccion,
                      COALESCE(sec_info.ids_secciones, '') AS ids_secciones,
                      COALESCE(sec_info.trayectos, '') AS trayectos 
               $from_sql 
               $where_sql 
               ORDER BY u.nombre ASC 
               LIMIT ? OFFSET ?";
$types_data = $types . "ii";
$params_data = array_merge($params_query, [$registros_por_pagina, $offset]);

$stmt_d = $db->prepare($query_data);
$stmt_d->bind_param($types_data, ...$params_data);
$stmt_d->execute();
$estudiantes = $stmt_d->get_result();

include("includes/head.php");
?>

<div class="container-fluid px-4 py-3">
    <!-- Encabezado -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="h3 font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-users-cog text-success mr-2"></i>Asignación de Voceros Estudiantiles
            </h2>
            <p class="text-muted small mb-0">
                Designación oficial y gestión de voceros estudiantiles por programa académico nacional o institucional.
            </p>
        </div>
        <div class="mt-3 mt-md-0">
            <span class="badge badge-pill badge-success px-3 py-2 font-weight-bold shadow-sm">
                <i class="fas fa-user-shield mr-1"></i> Control Total Administrador
            </span>
        </div>
    </div>

    <!-- Filtros y Buscador -->
    <div class="card shadow-sm mb-4 border-0" style="border-radius: 10px;">
        <div class="card-body p-3">
            <form method="POST" action="asignacion_voceros.php" id="formFiltros" class="row g-3 align-items-center">
                <?php if (isset($_SESSION['csrf_token'])): ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <?php endif; ?>
                <input type="hidden" name="pagina" id="inputPagina" value="<?= $pagina_actual ?>">

                <div class="col-md-4 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-graduation-cap mr-1"></i> Carrera / Programa:</label>
                    <select name="id_carrera" id="selectCarrera" class="form-control form-control-sm font-weight-bold">
                        <option value="0">-- Todas las Carreras / Programas --</option>
                        <?php foreach ($lista_carreras as $car): ?>
                            <option value="<?= $car['id_carrera'] ?>" <?= $carrera_filtro == $car['id_carrera'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($car['nombre_carrera']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-search mr-1"></i> Búsqueda en Vivo:</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="inputBuscar" name="q" class="form-control" placeholder="Buscar por Nombre, Cédula o Sección..." value="<?= htmlspecialchars($q) ?>">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-2 mb-md-0 d-flex flex-column align-items-md-end justify-content-center">
                    <label class="small font-weight-bold text-muted mb-1 d-none d-md-block">&nbsp;</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="checkSoloVoceros" name="filter_voceros" value="1" <?= $filter_voceros ? 'checked' : '' ?>>
                        <label class="custom-control-label small font-weight-bold text-dark" for="checkSoloVoceros">
                            Mostrar solo Voceros
                        </label>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Estudiantes -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 10px;">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list mr-2"></i>Listado de Estudiantes Registrados
            </h6>
            <span class="badge badge-light border" id="badgeTotalRegistros">
                Total: <?= $total_registros ?> estudiantes
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" id="tablaEstudiantes" style="font-size: 0.92rem;">
                    <thead class="thead-light">
                        <tr>
                            <th width="13%">Cédula</th>
                            <th width="27%">Nombre y Apellido</th>
                            <th width="20%">Programa / Carrera</th>
                            <th width="15%">Sección</th>
                            <th width="13%" class="text-center">Estado Vocero</th>
                            <th width="12%" class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyEstudiantes">
                        <?php if ($estudiantes && $estudiantes->num_rows > 0): ?>
                            <?php while ($e = $estudiantes->fetch_assoc()): ?>
                                <?php 
                                    $esVocero = intval($e['vocero']) === 1; 
                                    $codigoSec = trim($e['codigo_seccion'] ?? '');
                                    $trayectosSec = trim($e['trayectos'] ?? '');
                                ?>
                                <tr id="fila_<?= $e['id'] ?>">
                                    <td class="font-weight-bold text-secondary"><?= htmlspecialchars($e['idusuario']) ?></td>
                                    <td class="font-weight-bold text-dark"><?= htmlspecialchars($e['nombre']) ?></td>
                                    <td><span class="badge badge-light border text-dark"><?= htmlspecialchars($e['nombre_carrera'] ?: 'Sin Asignar') ?></span></td>
                                    <td id="col_seccion_<?= $e['id'] ?>">
                                        <?php if (!empty($codigoSec)): ?>
                                            <span class="badge badge-info px-2 py-1 font-weight-bold">
                                                <i class="fas fa-chalkboard-teacher mr-1"></i>Sección <?= htmlspecialchars($codigoSec) ?>
                                            </span>
                                            <?php if (!empty($trayectosSec)): ?>
                                                <div class="small text-muted font-weight-bold mt-1"><?= htmlspecialchars($trayectosSec) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-light border text-muted px-2 py-1">
                                                <i class="fas fa-minus-circle mr-1"></i>Sin Sección
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" id="col_estado_<?= $e['id'] ?>">
                                        <?php if ($esVocero): ?>
                                            <span class="badge badge-success px-2 py-1 font-weight-bold d-inline-block mb-1">
                                                <i class="fas fa-bullhorn mr-1"></i>Vocero Activo
                                            </span>
                                            <?php if (!empty($codigoSec)): ?>
                                                <div class="small font-weight-bold text-success">
                                                    <i class="fas fa-check-circle mr-1"></i>Vocero Secc. <?= htmlspecialchars($codigoSec) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="small font-weight-bold text-muted">
                                                    <i class="fas fa-exclamation-triangle text-warning mr-1"></i>Sin sección asignada
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-secondary px-2 py-1">Estudiante Regular</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" 
                                                class="btn btn-sm btn-toggle-vocero font-weight-bold <?= $esVocero ? 'btn-danger' : 'btn-success' ?>" 
                                                data-id="<?= $e['id'] ?>" 
                                                data-current="<?= $esVocero ? 1 : 0 ?>"
                                                data-seccion="<?= htmlspecialchars($codigoSec) ?>"
                                                data-trayecto="<?= htmlspecialchars($trayectosSec) ?>">
                                            <i class="fas <?= $esVocero ? 'fa-user-minus' : 'fa-user-plus' ?> mr-1"></i>
                                            <?= $esVocero ? 'Quitar Vocero' : 'Asignar Vocero' ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle mr-1"></i> No se encontraron estudiantes con los criterios especificados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="p-3 d-flex justify-content-between align-items-center border-top">
                <small class="text-muted" id="infoPaginacion">
                    Página <?= $pagina_actual ?> de <?= $total_paginas ?>
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="ulPaginacion">
                        <?php for ($p = 1; $p <= min(10, $total_paginas); $p++): ?>
                            <li class="page-item <?= $p === $pagina_actual ? 'active' : '' ?>">
                                <a class="page-link btn-pag" href="javascript:void(0);" data-page="<?= $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    let debounceTimer;

    // Limpiar cualquier parámetro GET de la URL del navegador para mantenerla siempre limpia
    if (window.history.replaceState && window.location.search) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    // Toggle vocero
    $(document).on('click', '.btn-toggle-vocero', function() {
        const btn = $(this);
        const userId = btn.data('id');
        const currentVal = parseInt(btn.data('current'));
        const newVal = currentVal === 1 ? 0 : 1;
        const accionTexto = newVal === 1 ? 'asignar como vocero' : 'retirar cargo de vocero';

        Swal.fire({
            title: '¿Confirmar acción?',
            text: `¿Desea ${accionTexto} a este estudiante?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newVal === 1 ? '#28a745' : '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

                const fd = new FormData();
                fd.append('action', 'toggle_vocero');
                fd.append('user_id', userId);
                fd.append('value', newVal);

                fetch('asignacion_voceros.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    btn.prop('disabled', false);
                    if (res.success) {
                        btn.data('current', newVal);

                        const codSec = (res.codigo_seccion || btn.data('seccion') || '').toString().trim();
                        const trayec = (res.trayectos || btn.data('trayecto') || '').toString().trim();

                        const colEstado = $(`#col_estado_${userId}`);
                        if (newVal === 1) {
                            let htmlEstado = '<span class="badge badge-success px-2 py-1 font-weight-bold d-inline-block mb-1"><i class="fas fa-bullhorn mr-1"></i>Vocero Activo</span>';
                            if (codSec !== '') {
                                htmlEstado += `<div class="small font-weight-bold text-success"><i class="fas fa-check-circle mr-1"></i>Vocero Secc. ${escapeHtml(codSec)}</div>`;
                            } else {
                                htmlEstado += '<div class="small font-weight-bold text-muted"><i class="fas fa-exclamation-triangle text-warning mr-1"></i>Sin sección asignada</div>';
                            }
                            colEstado.html(htmlEstado);
                            btn.removeClass('btn-success').addClass('btn-danger').html('<i class="fas fa-user-minus mr-1"></i> Quitar Vocero');
                        } else {
                            colEstado.html('<span class="badge badge-secondary px-2 py-1">Estudiante Regular</span>');
                            btn.removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-user-plus mr-1"></i> Asignar Vocero');
                        }

                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: 'El estado del vocero ha sido modificado exitosamente.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', res.msg || 'No se pudo actualizar.', 'error');
                    }
                })
                .catch(err => {
                    btn.prop('disabled', false);
                    Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
                });
            }
        });
    });

    // Búsqueda en vivo con debounce (POST)
    $('#inputBuscar').on('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            ejecutarBusqueda(1);
        }, 350);
    });

    // Filtro por carrera y switch voceros en tiempo real (POST)
    $('#selectCarrera').on('change', function() {
        ejecutarBusqueda(1);
    });

    $('#checkSoloVoceros').on('change', function() {
        ejecutarBusqueda(1);
    });

    // Formulario por POST sin recarga completa
    $('#formFiltros').on('submit', function(e) {
        e.preventDefault();
        ejecutarBusqueda(1);
    });

    function ejecutarBusqueda(pagina) {
        const q = $('#inputBuscar').val().trim();
        const carreraId = $('#selectCarrera').val();
        const filterVoc = $('#checkSoloVoceros').is(':checked') ? '1' : '0';
        $('#inputPagina').val(pagina);

        const fd = new FormData();
        fd.append('ajax', 'search');
        fd.append('id_carrera', carreraId);
        fd.append('q', q);
        fd.append('filter_voceros', filterVoc);
        fd.append('pagina', pagina);

        fetch('asignacion_voceros.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const tbody = $('#tbodyEstudiantes');
                tbody.empty();

                if (res.rows.length === 0) {
                    tbody.html('<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-info-circle mr-1"></i> No se encontraron resultados.</td></tr>');
                } else {
                    res.rows.forEach(e => {
                        const esVoc = parseInt(e.vocero) === 1;
                        const codSec = (e.codigo_seccion || '').trim();
                        const trayec = (e.trayectos || '').trim();

                        // Columna Sección
                        let seccionHtml = '';
                        if (codSec !== '') {
                            seccionHtml = `<span class="badge badge-info px-2 py-1 font-weight-bold"><i class="fas fa-chalkboard-teacher mr-1"></i>Sección ${escapeHtml(codSec)}</span>`;
                            if (trayec !== '') {
                                seccionHtml += `<div class="small text-muted font-weight-bold mt-1">${escapeHtml(trayec)}</div>`;
                            }
                        } else {
                            seccionHtml = `<span class="badge badge-light border text-muted px-2 py-1"><i class="fas fa-minus-circle mr-1"></i>Sin Sección</span>`;
                        }

                        // Columna Estado Vocero
                        let estadoBadge = '';
                        if (esVoc) {
                            estadoBadge = `<span class="badge badge-success px-2 py-1 font-weight-bold d-inline-block mb-1"><i class="fas fa-bullhorn mr-1"></i>Vocero Activo</span>`;
                            if (codSec !== '') {
                                estadoBadge += `<div class="small font-weight-bold text-success"><i class="fas fa-check-circle mr-1"></i>Vocero Secc. ${escapeHtml(codSec)}</div>`;
                            } else {
                                estadoBadge += `<div class="small font-weight-bold text-muted"><i class="fas fa-exclamation-triangle text-warning mr-1"></i>Sin sección asignada</div>`;
                            }
                        } else {
                            estadoBadge = `<span class="badge badge-secondary px-2 py-1">Estudiante Regular</span>`;
                        }

                        const btnClass = esVoc ? 'btn-danger' : 'btn-success';
                        const btnIcon = esVoc ? 'fa-user-minus' : 'fa-user-plus';
                        const btnTexto = esVoc ? 'Quitar Vocero' : 'Asignar Vocero';

                        const tr = `
                        <tr id="fila_${e.id}">
                            <td class="font-weight-bold text-secondary">${escapeHtml(e.idusuario)}</td>
                            <td class="font-weight-bold text-dark">${escapeHtml(e.nombre)}</td>
                            <td><span class="badge badge-light border text-dark">${escapeHtml(e.nombre_carrera || 'Sin Asignar')}</span></td>
                            <td id="col_seccion_${e.id}">${seccionHtml}</td>
                            <td class="text-center" id="col_estado_${e.id}">${estadoBadge}</td>
                            <td class="text-center">
                                <button type="button" 
                                        class="btn btn-sm ${btnClass} btn-toggle-vocero font-weight-bold" 
                                        data-id="${e.id}" 
                                        data-current="${esVoc ? 1 : 0}"
                                        data-seccion="${escapeHtml(codSec)}"
                                        data-trayecto="${escapeHtml(trayec)}">
                                    <i class="fas ${btnIcon} mr-1"></i> ${btnTexto}
                                </button>
                            </td>
                        </tr>`;
                        tbody.append(tr);
                    });
                }

                $('#badgeTotalRegistros').text(`Total: ${res.pagination.total_records} estudiantes`);
                $('#infoPaginacion').text(`Página ${res.pagination.current_page} de ${res.pagination.total_pages}`);

                // Paginación por POST
                const ulPag = $('#ulPaginacion');
                ulPag.empty();
                const maxPags = Math.min(10, res.pagination.total_pages);
                for (let p = 1; p <= maxPags; p++) {
                    const active = p === res.pagination.current_page ? 'active' : '';
                    ulPag.append(`<li class="page-item ${active}"><a class="page-link btn-pag" href="javascript:void(0);" data-page="${p}">${p}</a></li>`);
                }
            }
        });
    }

    $(document).on('click', '.btn-pag', function(e) {
        e.preventDefault();
        const p = $(this).data('page');
        ejecutarBusqueda(p);
    });
});
</script>

<?php include("includes/footer.php"); ?>
