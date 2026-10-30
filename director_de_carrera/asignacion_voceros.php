<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulopag = "Asignación de Voceros";
include('../funciones/functions.php');

cargarPermisosUsuario();

// Verificar autenticación básica
if (!isLoggedIn()) {
    header('location: ../login.php');
    exit();
}
if (!isDirectorCarrera()) {
    $_SESSION['error'] = "No tienes permisos de Director de Carrera para acceder a este módulo.";
    header('location: ../profile_selector.php');
    exit();
}

// LLAMAR A LA FUNCIÓN DE VISITA
visita();

$carrera_director = $_SESSION['user']['carrera_di'] ?? 1;

// Configuración de paginación
$registros_por_pagina = 15;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Procesar petición AJAX para alternar vocero
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_vocero') {
    header('Content-Type: application/json');
    $response = ['success' => false];

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $new_value = isset($_POST['value']) ? intval($_POST['value']) : 0;

    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'msg' => 'Usuario inválido']);
        exit();
    }

    // Verificar que el usuario sea estudiante y pertenezca a la carrera del director
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

    if ($user['carrera'] != $carrera_director && !isAdmin()) {
        echo json_encode(['success' => false, 'msg' => 'No tiene permisos para modificar este estudiante']);
        exit();
    }

    $old_values = ['vocero' => intval($user['vocero'])];
    $new_values = ['vocero' => $new_value];

    // Actualizar campo vocero
    $query_update = "UPDATE users SET vocero = ? WHERE id = ?";
    $stmt2 = $db->prepare($query_update);
    $stmt2->bind_param('ii', $new_value, $user_id);
    $ok = $stmt2->execute();

    if ($ok && $stmt2->affected_rows >= 0) {
        // Registrar auditoría
        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                'UPDATE',
                'users',
                $user_id,
                $old_values,
                $new_values,
                'Voceros',
                'Asignación/retirada de vocero para usuario: ' . ($user['idusuario'] ?? $user['nombre'])
            );
        }

        echo json_encode(['success' => true, 'vocero' => $new_value]);
        exit();
    }

    echo json_encode(['success' => false, 'msg' => 'Error al actualizar']);
    exit();
}

// Responder búsqueda en tiempo real (AJAX) con paginación
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    header('Content-Type: application/json');
    $q_ajax = isset($_GET['q']) ? trim($_GET['q']) : '';
    $filter_voceros = isset($_GET['filter_voceros']) && $_GET['filter_voceros'] === '1' ? true : false;
    $pagina_ajax = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $offset_ajax = ($pagina_ajax - 1) * $registros_por_pagina;

    $where_ajax = "WHERE u.estudiante = 1 AND u.carrera = ?";
    $params_ajax = [$carrera_director];

    // Agregar filtro de voceros si está activo
    if ($filter_voceros) {
        $where_ajax .= " AND u.vocero = 1";
    }

    if ($q_ajax !== '') {
        $where_ajax .= " AND (u.nombre LIKE ? OR u.idusuario LIKE ? )";
        $like_ajax = "%" . $q_ajax . "%";
        $params_ajax[] = $like_ajax;
        $params_ajax[] = $like_ajax;
    }

    // Consulta para obtener total de registros
    $count_query = "SELECT COUNT(*) as total FROM users u " . $where_ajax;
    $stmt_count = $db->prepare($count_query);
    if (count($params_ajax) === 1) {
        $stmt_count->bind_param('i', $params_ajax[0]);
    } elseif (count($params_ajax) === 3) {
        $stmt_count->bind_param('iss', $params_ajax[0], $params_ajax[1], $params_ajax[2]);
    }
    $stmt_count->execute();
    $total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Consulta con paginación
    $query_ajax = "SELECT u.id, u.nombre, u.idusuario, u.vocero, u.carrera FROM users u " . $where_ajax . " ORDER BY u.nombre LIMIT ? OFFSET ?";
    $params_ajax[] = $registros_por_pagina;
    $params_ajax[] = $offset_ajax;
    
    $stmt_ajax = $db->prepare($query_ajax);
    if (count($params_ajax) === 3) {
        $stmt_ajax->bind_param('iii', $params_ajax[0], $params_ajax[1], $params_ajax[2]);
    } elseif (count($params_ajax) === 5) {
        $stmt_ajax->bind_param('issii', $params_ajax[0], $params_ajax[1], $params_ajax[2], $params_ajax[3], $params_ajax[4]);
    }
    $stmt_ajax->execute();
    $res_ajax = $stmt_ajax->get_result();
    $rows = [];
    while ($r = $res_ajax->fetch_assoc()) {
        if (intval($r['carrera']) !== intval($carrera_director)) continue;
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

// Consulta de listado con buscador y paginación
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter_voceros = isset($_GET['filter_voceros']) && $_GET['filter_voceros'] === '1' ? true : false;

$params = [];
$where = "WHERE u.estudiante = 1 AND u.carrera = ?";
$params[] = $carrera_director;

// Agregar filtro de voceros si está activo
if ($filter_voceros) {
    $where .= " AND u.vocero = 1";
}

if ($q !== '') {
    $where .= " AND (u.nombre LIKE ? OR u.idusuario LIKE ? )";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
}

// Consulta para obtener total de registros
$count_query = "SELECT COUNT(*) as total FROM users u " . $where;
$stmt_count = $db->prepare($count_query);
if (count($params) === 1) {
    $stmt_count->bind_param('i', $params[0]);
} elseif (count($params) === 3) {
    $stmt_count->bind_param('iss', $params[0], $params[1], $params[2]);
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta principal con paginación
$query = "SELECT u.id, u.nombre, u.idusuario, u.vocero, u.carrera FROM users u " . $where . " ORDER BY u.nombre LIMIT ? OFFSET ?";
$params[] = $registros_por_pagina;
$params[] = $offset;

$stmt = $db->prepare($query);

// Bind dinámico
if (count($params) === 3) {
    $stmt->bind_param('iii', $params[0], $params[1], $params[2]);
} elseif (count($params) === 5) {
    $stmt->bind_param('issii', $params[0], $params[1], $params[2], $params[3], $params[4]);
}

$stmt->execute();
$result = $stmt->get_result();

?>
<!doctype html>
<html lang="es">
<head>
    <?php include("includes/head.php"); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=yes">
    <title><?= htmlspecialchars($titulopag) ?></title>
    <style>
        /* Estilos responsivos */
        @media (max-width: 768px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            h3.mb-0 {
                font-size: 1.3rem;
            }
            
            .d-flex.align-items-center.justify-content-between {
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .d-flex.align-items-center.justify-content-between div {
                margin-top: 8px;
            }
            
            /* Formulario de búsqueda responsivo */
            form.form-inline {
                flex-direction: column;
                align-items: stretch !important;
            }
            
            form.form-inline .form-group {
                width: 100% !important;
                margin-right: 0 !important;
                margin-bottom: 10px;
            }
            
            form.form-inline .btn {
                margin: 5px 0;
                width: 100%;
            }
            
            /* Tabla responsiva */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table {
                min-width: 500px;
            }
            
            .table th,
            .table td {
                padding: 8px 6px;
                font-size: 0.8rem;
            }
            
            /* Botones en tabla */
            .btn-sm {
                padding: 5px 10px;
                font-size: 0.7rem;
                white-space: nowrap;
            }
            
            /* Paginación responsiva */
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .page-link {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .pagination-info {
                text-align: center;
                margin-bottom: 15px;
                font-size: 0.8rem;
            }
            
            /* Modal responsivo */
            .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }
            
            .modal-body {
                padding: 15px;
            }
            
            /* Badges */
            .badge {
                font-size: 0.7rem;
                padding: 4px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table th,
            .table td {
                font-size: 0.7rem;
                padding: 6px 4px;
            }
            
            .btn-sm {
                padding: 4px 8px;
                font-size: 0.65rem;
            }
            
            .page-link {
                padding: 4px 8px;
                font-size: 0.7rem;
            }
            
            h3.mb-0 {
                font-size: 1.1rem;
            }
        }
        
        /* Estilos para loading */
        .loading-overlay {
            position: relative;
        }
        
        .loading-overlay::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.7);
            z-index: 10;
        }
        
        .spinner-small {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 0.5s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Estilos para el botón de filtro */
        .btn-filter-active {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h3 class="mb-0">
                <i class="fas fa-users"></i> <?= htmlspecialchars($titulopag) ?>
            </h3>
            <div class="text-muted">
                <i class="fas fa-chalkboard-teacher"></i> Director: <strong><?= htmlspecialchars($_SESSION['user']['nombre'] ?? '') ?></strong>
            </div>
        </div>

        <!-- Formulario de búsqueda con filtro de voceros -->
        <form method="get" class="form-inline mb-3" id="searchForm">
            <div class="form-group mr-2 flex-grow-1">
                <div class="input-group w-100">
                    <div class="input-group-prepend">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                    <input type="text" name="q" id="searchInput" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Buscar por nombre o cédula...">
                    <?php if ($q !== ''): ?>
                        <div class="input-group-append">
                            <a href="asignacion_voceros.php<?= $filter_voceros ? '?filter_voceros=1' : '' ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
            <!-- Botón de filtro rápido para voceros -->
            <button type="button" id="filterVocerosBtn" class="btn ml-2 <?= $filter_voceros ? 'btn-success' : 'btn-outline-success' ?> btn-filter-active">
                <i class="fas <?= $filter_voceros ? 'fa-eye' : 'fa-star' ?>"></i>
                <span id="filterText"><?= $filter_voceros ? 'Mostrar todos' : 'Mostrar solo voceros' ?></span>
            </button>
        </form>

        <!-- Información de paginación -->
        <div class="pagination-info d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div class="text-muted small">
                <i class="fas fa-chart-line"></i> Mostrando <strong><?= $result->num_rows ?></strong> de <strong><?= $total_registros ?></strong> estudiantes
                <?php if ($filter_voceros): ?>
                    <span class="badge badge-success ml-2"><i class="fas fa-star"></i> Filtro activo: Solo voceros</span>
                <?php endif; ?>
            </div>
            <div class="text-muted small">
                <i class="fas fa-calendar-alt"></i> Página <strong><?= $pagina_actual ?></strong> de <strong><?= max(1, $total_paginas) ?></strong>
            </div>
        </div>

        <!-- Tabla de estudiantes -->
        <div class="table-responsive" id="tableContainer">
            <table class="table table-sm table-hover table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th><i class="fas fa-user"></i> Nombre</th>
                        <th><i class="fas fa-id-card"></i> Cédula</th>
                        <th><i class="fas fa-star"></i> Vocero</th>
                        <th><i class="fas fa-cog"></i> Acción</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php if (intval($row['carrera']) !== intval($carrera_director)) continue; ?>
                            <tr data-user-id="<?= $row['id'] ?>">
                                <td>
                                    <strong><?= htmlspecialchars($row['nombre']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($row['idusuario']) ?></td>
                                <td>
                                    <?php if ($row['vocero']): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> Sí
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-times-circle"></i> No
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm <?= $row['vocero'] ? 'btn-danger' : 'btn-success' ?> toggle-vocero" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-value="<?= $row['vocero'] ? 0 : 1 ?>" 
                                            data-name="<?= htmlspecialchars($row['nombre'], ENT_QUOTES) ?>">
                                        <i class="fas <?= $row['vocero'] ? 'fa-times' : 'fa-check' ?>"></i>
                                        <?= $row['vocero'] ? ' Quitar' : ' Marcar' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">
                                <i class="fas fa-info-circle fa-2x text-muted mb-2 d-block"></i>
                                No se encontraron estudiantes
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <nav aria-label="Navegación de páginas" class="mt-3">
            <ul class="pagination justify-content-center">
                <!-- Primera página -->
                <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>" aria-label="Primera">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                </li>
                
                <!-- Anterior -->
                <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])) ?>" aria-label="Anterior">
                        <i class="fas fa-angle-left"></i>
                    </a>
                </li>
                
                <!-- Números de página -->
                <?php
                $rango = 2;
                $inicio = max(1, $pagina_actual - $rango);
                $fin = min($total_paginas, $pagina_actual + $rango);
                
                if ($inicio > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">1</a>
                    </li>
                    <?php if ($inicio > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                    <li class="page-item <?= ($i == $pagina_actual) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($fin < $total_paginas): ?>
                    <?php if ($fin < $total_paginas - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>"><?= $total_paginas ?></a>
                    </li>
                <?php endif; ?>
                
                <!-- Siguiente -->
                <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])) ?>" aria-label="Siguiente">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </li>
                
                <!-- Última página -->
                <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>" aria-label="Última">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>

    <?php include("includes/footer.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

    <!-- Modal de confirmación -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">
                        <i class="fas fa-question-circle"></i> Confirmar acción
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="confirmText">¿Desea continuar?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmBtn">
                        <i class="fas fa-check"></i> Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Búsqueda en tiempo real con debounce, paginación y filtro de voceros
    (function(){
        var timer = null;
        var input = document.querySelector('#searchInput');
        var tbody = document.querySelector('#tableBody');
        var tableContainer = document.querySelector('#tableContainer');
        var currentPage = <?= $pagina_actual ?>;
        var isLoading = false;
        var filterVocerosActive = <?= $filter_voceros ? 'true' : 'false' ?>;
        
        // Botón de filtro
        var filterBtn = document.querySelector('#filterVocerosBtn');
        var filterText = document.querySelector('#filterText');
        
        function escapeHtml(str){ 
            return (str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); 
        }
        
        function escapeHtmlAttr(str){ 
            return (str||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); 
        }
        
        // Función para actualizar la URL con el estado del filtro
        function updateUrlFilter() {
            var url = new URL(window.location.href);
            if (filterVocerosActive) {
                url.searchParams.set('filter_voceros', '1');
            } else {
                url.searchParams.delete('filter_voceros');
            }
            // Mantener el parámetro de búsqueda si existe
            var searchQuery = input ? input.value.trim() : '';
            if (searchQuery) {
                url.searchParams.set('q', searchQuery);
            } else {
                url.searchParams.delete('q');
            }
            window.history.pushState({}, '', url);
        }
        
        function renderPagination(paginationData, searchTerm) {
            if (!paginationData || paginationData.total_pages <= 1) {
                var oldNav = document.querySelector('nav[aria-label="Navegación de páginas"]');
                if (oldNav) oldNav.style.display = 'none';
                return;
            }
            
            var currentPageNum = paginationData.current_page;
            var totalPages = paginationData.total_pages;
            
            var html = '<nav aria-label="Navegación de páginas" class="mt-3"><ul class="pagination justify-content-center">';
            
            // Primera página
            html += '<li class="page-item ' + (currentPageNum <= 1 ? 'disabled' : '') + '">';
            html += '<a class="page-link" href="#" data-page="1"><i class="fas fa-angle-double-left"></i></a></li>';
            
            // Anterior
            html += '<li class="page-item ' + (currentPageNum <= 1 ? 'disabled' : '') + '">';
            html += '<a class="page-link" href="#" data-page="' + (currentPageNum - 1) + '"><i class="fas fa-angle-left"></i></a></li>';
            
            // Números de página
            var rango = 2;
            var inicio = Math.max(1, currentPageNum - rango);
            var fin = Math.min(totalPages, currentPageNum + rango);
            
            if (inicio > 1) {
                html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
                if (inicio > 2) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            
            for (var i = inicio; i <= fin; i++) {
                html += '<li class="page-item ' + (i == currentPageNum ? 'active' : '') + '">';
                html += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
            }
            
            if (fin < totalPages) {
                if (fin < totalPages - 1) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                html += '<li class="page-item"><a class="page-link" href="#" data-page="' + totalPages + '">' + totalPages + '</a></li>';
            }
            
            // Siguiente
            html += '<li class="page-item ' + (currentPageNum >= totalPages ? 'disabled' : '') + '">';
            html += '<a class="page-link" href="#" data-page="' + (currentPageNum + 1) + '"><i class="fas fa-angle-right"></i></a></li>';
            
            // Última página
            html += '<li class="page-item ' + (currentPageNum >= totalPages ? 'disabled' : '') + '">';
            html += '<a class="page-link" href="#" data-page="' + totalPages + '"><i class="fas fa-angle-double-right"></i></a></li>';
            
            html += '</ul></nav>';
            
            // Reemplazar o agregar navegación
            var oldNav = document.querySelector('nav[aria-label="Navegación de páginas"]');
            if (oldNav) {
                oldNav.outerHTML = html;
            } else {
                tableContainer.insertAdjacentHTML('afterend', html);
            }
            
            // Agregar event listeners a los nuevos enlaces
            document.querySelectorAll('.page-link[data-page]').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var page = parseInt(this.getAttribute('data-page'));
                    if (!isNaN(page) && page !== currentPageNum) {
                        loadSearchResults(input.value.trim(), page);
                    }
                });
            });
        }
        
        function renderRows(data){
            var html = '';
            if (data.rows && data.rows.length > 0) {
                data.rows.forEach(function(r){
                    var btnClass = r.vocero == 1 ? 'btn-danger' : 'btn-success';
                    var btnIcon = r.vocero == 1 ? 'fa-times' : 'fa-check';
                    var btnText = r.vocero == 1 ? ' Quitar' : ' Marcar';
                    html += '<tr data-user-id="'+r.id+'">';
                    html += '<td><strong>'+escapeHtml(r.nombre)+'</strong></td>';
                    html += '<td>'+escapeHtml(r.idusuario)+'</td>';
                    html += '<td>'+(r.vocero==1?'<span class="badge badge-success"><i class="fas fa-check-circle"></i> Sí</span>':'<span class="badge badge-secondary"><i class="fas fa-times-circle"></i> No</span>')+'</td>';
                    html += '<td><button class="btn btn-sm '+btnClass+' toggle-vocero" data-id="'+r.id+'" data-value="'+(r.vocero==1?0:1)+'" data-name="'+escapeHtmlAttr(r.nombre)+'"><i class="fas '+btnIcon+'"></i>'+btnText+'</button></td>';
                    html += '</tr>';
                });
            } else {
                html = '<tr><td colspan="4" class="text-center py-4"><i class="fas fa-info-circle fa-2x text-muted mb-2 d-block"></i>No se encontraron estudiantes</td></tr>';
            }
            tbody.innerHTML = html;
            
            // Actualizar información de paginación
            if (data.pagination) {
                var infoDiv = document.querySelector('.pagination-info');
                if (infoDiv) {
                    var filterBadge = filterVocerosActive ? '<span class="badge badge-success ml-2"><i class="fas fa-star"></i> Filtro activo: Solo voceros</span>' : '';
                    infoDiv.innerHTML = `
                        <div class="text-muted small"><i class="fas fa-chart-line"></i> Mostrando <strong>${data.rows.length}</strong> de <strong>${data.pagination.total_records}</strong> estudiantes ${filterBadge}</div>
                        <div class="text-muted small"><i class="fas fa-calendar-alt"></i> Página <strong>${data.pagination.current_page}</strong> de <strong>${data.pagination.total_pages}</strong></div>
                    `;
                }
                renderPagination(data.pagination, input.value.trim());
            }
        }
        
        function loadSearchResults(query, page) {
            if (isLoading) return;
            isLoading = true;
            
            currentPage = page || 1;
            var url = 'asignacion_voceros.php?ajax=search&q=' + encodeURIComponent(query) + '&pagina=' + currentPage + '&filter_voceros=' + (filterVocerosActive ? '1' : '0');
            
            // Mostrar indicador de carga
            tableContainer.classList.add('loading-overlay');
            
            fetch(url).then(function(r){return r.json();}).then(function(json){
                if (json.success) {
                    renderRows(json);
                }
                tableContainer.classList.remove('loading-overlay');
                isLoading = false;
            }).catch(function(error){
                console.error('Error:', error);
                tableContainer.classList.remove('loading-overlay');
                isLoading = false;
            });
        }
        
        // Función para alternar el filtro de voceros
        function toggleFilterVoceros() {
            filterVocerosActive = !filterVocerosActive;
            
            if (filterVocerosActive) {
                filterBtn.classList.remove('btn-outline-success');
                filterBtn.classList.add('btn-success');
                filterBtn.innerHTML = '<i class="fas fa-eye"></i> <span id="filterText">Mostrar todos</span>';
                filterText = document.querySelector('#filterText');
            } else {
                filterBtn.classList.remove('btn-success');
                filterBtn.classList.add('btn-outline-success');
                filterBtn.innerHTML = '<i class="fas fa-star"></i> <span id="filterText">Mostrar solo voceros</span>';
                filterText = document.querySelector('#filterText');
            }
            
            updateUrlFilter();
            loadSearchResults(input ? input.value.trim() : '', 1);
        }
        
        // Evento del botón de filtro
        if (filterBtn) {
            filterBtn.addEventListener('click', toggleFilterVoceros);
        }
        
        // Búsqueda en tiempo real
        if (input) {
            input.addEventListener('input', function(){
                clearTimeout(timer);
                timer = setTimeout(function(){
                    var q = input.value.trim();
                    loadSearchResults(q, 1);
                }, 500);
            });
        }
        
        // Delegación para botones generados dinámicamente
        document.addEventListener('click', function(e){
            var btn = e.target.closest('.toggle-vocero');
            if (!btn) return;
            e.preventDefault();
            var userId = btn.getAttribute('data-id');
            var value = btn.getAttribute('data-value');
            var name = btn.getAttribute('data-name') || '';
            
            // Mostrar modal
            var confirmText = document.getElementById('confirmText');
            confirmText.innerHTML = '¿Desea ' + (value==1 ? 'marcar' : 'quitar') + ' como vocero a <strong>'+ escapeHtml(name) +'</strong>?';
            $('#confirmModal').data('userid', userId).data('value', value).modal('show');
        });
        
        // Confirmar acción
        document.getElementById('confirmBtn').addEventListener('click', function(){
            var modal = $('#confirmModal');
            var userId = modal.data('userid');
            var value = modal.data('value');
            var btn = document.querySelector('button.toggle-vocero[data-id="'+userId+'"]');
            if (btn) { 
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-small"></span>';
            }
            modal.modal('hide');
            
            var form = new FormData();
            form.append('action','toggle_vocero');
            form.append('user_id', userId);
            form.append('value', value);
            
            fetch('asignacion_voceros.php', { method: 'POST', body: form }).then(function(r){ return r.json(); }).then(function(json){
                if (json.success) {
                    // Recargar resultados actuales
                    var currentQuery = input ? input.value.trim() : '';
                    loadSearchResults(currentQuery, currentPage);
                } else {
                    alert(json.msg || 'Error al actualizar');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = value == 1 ? '<i class="fas fa-check"></i> Marcar' : '<i class="fas fa-times"></i> Quitar';
                    }
                }
            }).catch(function(){
                alert('Error en la comunicación');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = value == 1 ? '<i class="fas fa-check"></i> Marcar' : '<i class="fas fa-times"></i> Quitar';
                }
            });
        });
        
    })();
    </script>
</body>
</html>