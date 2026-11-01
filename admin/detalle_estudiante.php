<?php
require_once(__DIR__ . '/../funciones/functions.php');

// Verificar ID
$id = filter_var($_POST['id'] ?? ($_GET['id'] ?? null), FILTER_VALIDATE_INT);
if (!$id) {
    die('<div class="alert alert-danger">ID de estudiante no válido</div>');
}

$raw_admin_prefix = $_POST['admin_prefix'] ?? ($_GET['admin_prefix'] ?? '');
$admin_prefix = ($raw_admin_prefix === '../') ? '../' : '';
$root_prefix = $admin_prefix ? '../..' : '..';

// Obtener datos
$estudiante = obtenerEstudiantePorId($id);
if (isset($estudiante['error'])) {
    die('<div class="alert alert-danger">'.$estudiante['error'].'</div>');
}

// Obtener nombre de la carrera
$nombreCarrera = 'No especificada';
if(isset($estudiante['carrera']) && !empty($estudiante['carrera'])) {
    global $db;
    $id_carrera = $estudiante['carrera'];
    $query = $db->query("SELECT nombre_carrera FROM carreras WHERE id_carrera = $id_carrera");
    if ($query && $carrera = $query->fetch_assoc()) {
        $nombreCarrera = $carrera['nombre_carrera'];
    }
}

// Obtener el nombre de la fuente de ingresos
$fuenteIngresoNombre = 'No especificado';
if (isset($estudiante['fuente_ingresos']) && !empty($estudiante['fuente_ingresos'])) {
    $ingresos = obtenerIngresos($db);
    $fuenteIngresoNombre = $ingresos[$estudiante['fuente_ingresos']] ?? $estudiante['fuente_ingresos'];
}

// =============================================
// OBTENER NOMBRES DE UBICACIÓN
// =============================================
$nombresUbicacion = [
    'estado_nombre' => 'No especificado',
    'municipio_nombre' => 'No especificado',
    'parroquia_nombre' => 'No especificado',
    'ciudad_nombre' => 'No especificado'
];

if (isset($estudiante['estado']) && !empty($estudiante['estado'])) {
    if (is_numeric($estudiante['estado'])) {
        $sql_estado = "SELECT estado FROM estados WHERE id_estado = ?";
        $stmt_estado = $db->prepare($sql_estado);
        if ($stmt_estado) {
            $stmt_estado->bind_param("i", $estudiante['estado']);
            $stmt_estado->execute();
            $stmt_estado->bind_result($estado_nombre);
            if ($stmt_estado->fetch()) {
                $nombresUbicacion['estado_nombre'] = $estado_nombre;
            }
            $stmt_estado->close();
        }
    } else {
        $nombresUbicacion['estado_nombre'] = $estudiante['estado'];
    }
}

if (isset($estudiante['municipio']) && !empty($estudiante['municipio'])) {
    if (is_numeric($estudiante['municipio'])) {
        $sql_municipio = "SELECT municipio FROM municipios WHERE id_municipio = ?";
        $stmt_municipio = $db->prepare($sql_municipio);
        if ($stmt_municipio) {
            $stmt_municipio->bind_param("i", $estudiante['municipio']);
            $stmt_municipio->execute();
            $stmt_municipio->bind_result($municipio_nombre);
            if ($stmt_municipio->fetch()) {
                $nombresUbicacion['municipio_nombre'] = $municipio_nombre;
            }
            $stmt_municipio->close();
        }
    } else {
        $nombresUbicacion['municipio_nombre'] = $estudiante['municipio'];
    }
}

if (isset($estudiante['parroquia']) && !empty($estudiante['parroquia'])) {
    if (is_numeric($estudiante['parroquia'])) {
        $sql_parroquia = "SELECT parroquia FROM parroquias WHERE id_parroquia = ?";
        $stmt_parroquia = $db->prepare($sql_parroquia);
        if ($stmt_parroquia) {
            $stmt_parroquia->bind_param("i", $estudiante['parroquia']);
            $stmt_parroquia->execute();
            $stmt_parroquia->bind_result($parroquia_nombre);
            if ($stmt_parroquia->fetch()) {
                $nombresUbicacion['parroquia_nombre'] = $parroquia_nombre;
            }
            $stmt_parroquia->close();
        }
    } else {
        $nombresUbicacion['parroquia_nombre'] = $estudiante['parroquia'];
    }
}

if (isset($estudiante['ciudad']) && !empty($estudiante['ciudad'])) {
    if (is_numeric($estudiante['ciudad'])) {
        $sql_ciudad = "SELECT ciudad FROM ciudades WHERE id_ciudad = ?";
        $stmt_ciudad = $db->prepare($sql_ciudad);
        if ($stmt_ciudad) {
            $stmt_ciudad->bind_param("i", $estudiante['ciudad']);
            $stmt_ciudad->execute();
            $stmt_ciudad->bind_result($ciudad_nombre);
            if ($stmt_ciudad->fetch()) {
                $nombresUbicacion['ciudad_nombre'] = $ciudad_nombre;
            }
            $stmt_ciudad->close();
        }
    } else {
        $nombresUbicacion['ciudad_nombre'] = $estudiante['ciudad'];
    }
}

// Manejo de foto de perfil
$fotoPerfil = '';
$tieneFoto = false;

if (!empty($estudiante['foto_perfil'])) {
    $rutaFoto = __DIR__ . '/../foto_perfil/' . $estudiante['foto_perfil'];
    if (file_exists($rutaFoto) && is_file($rutaFoto)) {
        $fotoPerfil = $root_prefix . '/foto_perfil/' . $estudiante['foto_perfil'];
        $tieneFoto = true;
    }
}

$fotoPerfil = $tieneFoto 
    ? ($root_prefix . '/foto_perfil/' . $estudiante['foto_perfil']) 
    : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='40' r='20' fill='%236c757d'/%3E%3Ccircle cx='50' cy='100' r='40' fill='%236c757d'/%3E%3Ctext x='50' y='45' text-anchor='middle' fill='white' font-family='Arial' font-size='14'%3EUSER%3C/text%3E%3C/svg%3E";

// Obtener solicitudes académicas del estudiante
$solicitudes_estudiante = function_exists('obtenerSolicitudesAcademicas') ? obtenerSolicitudesAcademicas('', $id) : [];
$solicitudes_pendientes = array_filter($solicitudes_estudiante, function($s) { return $s['status'] === 'pendiente'; });
$cant_pendientes = count($solicitudes_pendientes);
?>


<div class="modal-body p-0">
    <!-- Header con foto y datos principales -->
    <div class="bg-light py-4 px-4 border-bottom">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="avatar-container position-relative">
                    <img src="<?= $fotoPerfil ?>" 
                         alt="Foto de perfil del estudiante" 
                         class="avatar-img rounded-circle border"
                         id="fotoEstudiante">
                    <div class="status-indicator <?= ($estudiante['status'] ?? 0) == 1 ? 'bg-success' : 'bg-secondary' ?>"></div>
                </div>
            </div>
            <div class="col">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div>
                        <h4 class="mb-1 text-dark"><?= htmlspecialchars($estudiante['nombre'] ?? '') ?></h4>
                        <p class="text-muted mb-1">
                            <i class="fas fa-id-card me-1"></i>
                            <?= htmlspecialchars($estudiante['idusuario'] ?? '') ?> 
                            | ID: <?= htmlspecialchars($estudiante['id'] ?? '') ?>
                        </p>
                        <p class="mb-1">
                            <span class="badge bg-primary">
                                <i class="fas fa-graduation-cap me-1"></i>
                                <?= htmlspecialchars($nombreCarrera) ?>
                            </span>
                            <span class="badge <?= ($estudiante['status'] ?? 0) == 1 ? 'bg-success' : 'bg-secondary' ?> ms-2">
                                <?= ($estudiante['status'] ?? 0) == 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                            <?php if (!$tieneFoto): ?>
                                <span class="badge bg-warning ms-2">
                                    <i class="fas fa-camera me-1"></i>Sin foto
                                </span>
                            <?php endif; ?>
                            <?php if ($cant_pendientes > 0): ?>
                                <span class="badge bg-danger ms-2">
                                    <i class="fas fa-bell me-1"></i><?= $cant_pendientes ?> Solicitud(es) Pendiente(s)
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <form method="POST" action="<?= $admin_prefix ?>consulta_notas.php" class="d-inline m-0">
                            <input type="hidden" name="cedula" value="<?= htmlspecialchars($estudiante['idusuario'] ?? '') ?>">
                            <button type="submit" class="btn btn-info btn-sm font-weight-bold shadow-sm" title="Ver historial académico">
                                <i class="fas fa-book-open me-1"></i> Historial Académico
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($cant_pendientes > 0): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center mb-0 mt-3 p-2 rounded shadow-sm">
                <div>
                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                    <strong>¡Atención!</strong> Este estudiante tiene <strong><?= $cant_pendientes ?></strong> solicitud(es) académica(s) pendiente(s) por revisión.
                </div>
                <form method="POST" action="<?= $admin_prefix ?>constancias.php" class="d-inline m-0">
                    <input type="hidden" name="id_estudiante" value="<?= $id ?>">
                    <input type="hidden" name="tab" value="solicitudes">
                    <button type="submit" class="btn btn-sm btn-warning text-dark font-weight-bold shadow-sm">
                        <i class="fas fa-tasks me-1"></i> Gestionar Solicitud
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="container-fluid py-4">
        <!-- Primera fila: Información personal y académica -->
        <div class="row g-4 mb-4">
            <!-- Información Personal -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light-blue text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-user-circle me-2"></i>Información Personal
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Género</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['genero'] ?? 'No especificado') ?></p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Estado Civil</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['edo_civil'] ?? 'No especificado') ?></p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Fecha Nacimiento</label>
                                <p class="mb-0 fw-semibold"><?= !empty($estudiante['fecha_nac']) ? date('d/m/Y', strtotime($estudiante['fecha_nac'])) : 'No especificado' ?></p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Etnia</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['etnia'] ?? 'No especificado') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información Académica -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light-green text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-graduation-cap me-2"></i>Información Académica
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Programa</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($nombreCarrera) ?></p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Sede</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['sede'] ?? 'No especificada') ?></p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Fecha Ingreso</label>
                                <p class="mb-0 fw-semibold"><?= !empty($estudiante['fecha_ingreso']) ? date('d/m/Y', strtotime($estudiante['fecha_ingreso'])) : 'No especificado' ?></p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Última Actualización</label>
                                <p class="mb-0 fw-semibold"><?= !empty($estudiante['fecha_act']) ? date('d/m/Y H:i', strtotime($estudiante['fecha_act'])) : 'No especificado' ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Segunda fila: Contacto y Salud -->
        <div class="row g-4 mb-4">
            <!-- Información de Contacto -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light-purple text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-address-book me-2"></i>Información de Contacto
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Email</label>
                                <p class="mb-0 fw-semibold">
                                    <i class="fas fa-envelope me-1 text-primary"></i>
                                    <?= htmlspecialchars($estudiante['email'] ?? 'No especificado') ?>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Teléfono Principal</label>
                                <p class="mb-0 fw-semibold">
                                    <i class="fas fa-phone me-1 text-success"></i>
                                    <?= htmlspecialchars($estudiante['tlf'] ?? 'No especificado') ?>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Celular</label>
                                <p class="mb-0 fw-semibold">
                                    <i class="fas fa-mobile-alt me-1 text-info"></i>
                                    <?= htmlspecialchars($estudiante['cel'] ?? 'No especificado') ?>
                                </p>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Teléfono Opcional</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['num_telf_opc'] ?? 'No especificado') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos de Salud -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light-orange text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-heartbeat me-2"></i>Datos de Salud
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Enfermedades</label>
                                <p class="mb-0 fw-semibold"><?= !empty($estudiante['enfermedad']) ? htmlspecialchars($estudiante['enfermedad']) : '<span class="text-muted">Ninguna registrada</span>' ?></p>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Discapacidad</label>
                                <p class="mb-0 fw-semibold"><?= !empty($estudiante['discapacidad']) ? htmlspecialchars($estudiante['discapacidad']) : '<span class="text-muted">Ninguna registrada</span>' ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tercera fila: Dirección y Ubicación -->
        <div class="row g-4 mb-4">
            <!-- Dirección Residencial -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light-pink text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-home me-2"></i>Dirección Residencial
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Dirección Completa</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['direccion'] ?? 'No especificado') ?></p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Tipo de Vivienda</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['casaapto'] ?? 'No especificado') ?></p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Punto de Referencia</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['punto_referencia'] ?? 'No especificado') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ubicación Geográfica -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light-cyan text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-map-marker-alt me-2"></i>Ubicación Geográfica
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Estado</label>
                                <p class="mb-0 fw-semibold">
                                    <i class="fas fa-flag me-1 text-primary"></i>
                                    <?= htmlspecialchars($nombresUbicacion['estado_nombre']) ?>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Municipio</label>
                                <p class="mb-0 fw-semibold">
                                    <i class="fas fa-city me-1 text-success"></i>
                                    <?= htmlspecialchars($nombresUbicacion['municipio_nombre']) ?>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Parroquia</label>
                                <p class="mb-0 fw-semibold">
                                    <i class="fas fa-map-pin me-1 text-info"></i>
                                    <?= htmlspecialchars($nombresUbicacion['parroquia_nombre']) ?>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Ciudad</label>
                                <p class="mb-0 fw-semibold">
                                    <i class="fas fa-building me-1 text-warning"></i>
                                    <?= htmlspecialchars($nombresUbicacion['ciudad_nombre']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cuarta fila: Situación Familiar y Vivienda -->
        <div class="row g-4">
            <!-- Situación Familiar -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light-yellow text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-users me-2"></i>Situación Familiar
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Grupo Familiar</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['grupo_familiar'] ?? '0') ?> personas</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Personas a Cargo</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['acargo_usted'] ?? '0') ?> personas</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Fuente de Ingresos</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($fuenteIngresoNombre) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Situación de Vivienda -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light-teal text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-building me-2"></i>Situación de Vivienda
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Tenencia de Vivienda</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['tenencia_vivienda'] ?? 'No especificado') ?></p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">Potencialidades</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($estudiante['potencialidades'] ?? 'No especificado') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quinta fila: Títulos Obtenidos -->
        <?php if (!empty($estudiante['titulos']) && $estudiante['titulos'] !== '|||'): ?>
        <div class="row g-4 mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-light-indigo text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-award me-2"></i>Títulos Obtenidos
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        $titulos = explode('|||', $estudiante['titulos'] ?? '');
                        $institutos = explode('|||', $estudiante['institutos'] ?? '');
                        $pais_titulo = explode('|||', $estudiante['pais_titulo'] ?? '');
                        $legalizado_titulo = explode('|||', $estudiante['legalizado_titulo'] ?? '');
                        ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Título</th>
                                        <th>Institución</th>
                                        <th>País</th>
                                        <th>Legalizado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 0; $i < count($titulos); $i++): ?>
                                        <?php if (!empty($titulos[$i]) && trim($titulos[$i]) !== ''): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(trim($titulos[$i])) ?></td>
                                                <td><?= isset($institutos[$i]) ? htmlspecialchars(trim($institutos[$i])) : 'No especificado' ?></td>
                                                <td><?= isset($pais_titulo[$i]) ? htmlspecialchars(trim($pais_titulo[$i])) : 'No especificado' ?></td>
                                                <td><?= isset($legalizado_titulo[$i]) && trim($legalizado_titulo[$i]) == 'Sí' ? 'Sí' : 'No' ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Fila de Solicitudes Académicas -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card h-100 shadow-sm border-info">
                    <div class="card-header bg-light-blue text-dark d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 font-weight-bold">
                            <i class="fas fa-tasks me-2 text-info"></i>Solicitudes Académicas Registradas
                        </h6>
                        <span class="badge bg-info text-white"><?= count($solicitudes_estudiante) ?> Registro(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($solicitudes_estudiante)): ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="fas fa-inbox mb-2" style="font-size: 2rem;"></i>
                                <p class="mb-0">El estudiante no registra solicitudes académicas.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 text-center">
                                    <thead class="bg-light">
                                        <tr>
                                            <th># ID</th>
                                            <th>Tipo de Trámite</th>
                                            <th>Fecha</th>
                                            <th>Detalle</th>
                                            <th>Estado</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($solicitudes_estudiante as $se): ?>
                                            <tr>
                                                <td class="fw-bold">#<?= $se['id'] ?></td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars(strtoupper($se['tipo_solicitud'])) ?></span>
                                                </td>
                                                <td class="small"><?= date('d/m/Y', strtotime($se['fecha_solicitud'])) ?></td>
                                                <td class="text-start small"><?= htmlspecialchars(mb_strimwidth($se['motivo'], 0, 40, '...')) ?></td>
                                                <td>
                                                    <?php if ($se['status'] === 'pendiente'): ?>
                                                        <span class="badge bg-warning text-dark">PENDIENTE</span>
                                                    <?php elseif ($se['status'] === 'aprobada'): ?>
                                                        <span class="badge bg-success">APROBADA</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">RECHAZADA</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" action="<?= $admin_prefix ?>constancias.php" class="d-inline m-0">
                                                        <input type="hidden" name="id_estudiante" value="<?= $id ?>">
                                                        <input type="hidden" name="tab" value="solicitudes">
                                                        <button type="submit" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2 font-weight-bold" title="Gestionar en Solicitudes">
                                                            <i class="fas fa-tasks me-1"></i> Gestionar
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="<?= $root_prefix ?>/constancias/generar_constancia.php" target="_blank" class="d-inline">
                                                        <input type="hidden" name="solicitud_id" value="<?= $se['id'] ?>">
                                                        <button type="submit" class="btn btn-xs btn-outline-danger btn-sm py-0 px-2" title="Ver PDF">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fotoEstudiante = document.getElementById('fotoEstudiante');
    
    if (fotoEstudiante) {
        fotoEstudiante.addEventListener('error', function() {
            this.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='40' r='20' fill='%236c757d'/%3E%3Ccircle cx='50' cy='100' r='40' fill='%236c757d'/%3E%3Ctext x='50' y='45' text-anchor='middle' fill='white' font-family='Arial' font-size='14'%3EUSER%3C/text%3E%3C/svg%3E";
        });
    }
});
</script>

<style>
.avatar-container {
    position: relative;
    display: inline-block;
}

.avatar-img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border: 4px solid #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    background-color: #f8f9fa;
}

.status-indicator {
    position: absolute;
    bottom: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 3px solid #fff;
}

.bg-light-blue { background-color: #e3f2fd !important; }
.bg-light-green { background-color: #e8f5e9 !important; }
.bg-light-purple { background-color: #f3e5f5 !important; }
.bg-light-orange { background-color: #fff3e0 !important; }
.bg-light-pink { background-color: #fce4ec !important; }
.bg-light-cyan { background-color: #e0f2f1 !important; }
.bg-light-yellow { background-color: #fffde7 !important; }
.bg-light-teal { background-color: #e0f2f1 !important; }
.bg-light-indigo { background-color: #e8eaf6 !important; }

.card {
    border: 1px solid #e0e0e0;
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.form-label {
    font-size: 0.8rem;
    font-weight: 500;
}

.fw-semibold {
    font-weight: 600;
}

.table-responsive {
    max-height: 200px;
    overflow-y: auto;
}

.table-sm th,
.table-sm td {
    padding: 0.5rem;
}

.table-hover tbody tr:hover {
    background-color: #f5f5f5;
}
</style>