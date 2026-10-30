<?php
// ==========================================
// CONTROLADOR AJAX PARA BÚSQUEDA EN TIEMPO REAL
// (Usa la función centralizada buscarEstudiantesPagosAjax de functions.php)
// ==========================================
if (isset($_POST['ajax_buscar_estudiantes'])) {
    ob_start();
    require_once(__DIR__ . '/../funciones/functions.php');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $termino = trim($_POST['termino'] ?? '');
    $estudiantes = function_exists('buscarEstudiantesPagosAjax') ? buscarEstudiantesPagosAjax($termino, 15) : [];
    
    echo json_encode(['success' => true, 'estudiantes' => $estudiantes]);
    exit();
}

require_once(__DIR__ . '/../funciones/functions.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulopag = "Generación de Constancias y Solicitudes";

// CARGAR PERMISOS Y VERIFICAR
cargarPermisosUsuario();
verificarPermiso('super_user');
visita();

$admin_id = $_SESSION['user']['id'] ?? 0;
$mensaje = '';
$tipo_mensaje = '';
$tab_activa = 'constancias'; // 'constancias' o 'solicitudes'
$solicitud_pdf_popup_id = 0;

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Recuperar flash data (Patrón PRG)
if (isset($_SESSION['flash_admin_solicitud'])) {
    $mensaje = $_SESSION['flash_admin_solicitud']['mensaje'] ?? '';
    $tipo_mensaje = $_SESSION['flash_admin_solicitud']['tipo'] ?? 'success';
    $tab_activa = $_SESSION['flash_admin_solicitud']['tab'] ?? 'solicitudes';
    $solicitud_pdf_popup_id = intval($_SESSION['flash_admin_solicitud']['pdf_id'] ?? 0);
    $id_estudiante_sel = intval($_SESSION['flash_admin_solicitud']['id_estudiante'] ?? 0);
    unset($_SESSION['flash_admin_solicitud']);
}

$estudiante = null;
$carrera = null;
$solicitudes_estudiante = [];
$cant_pendientes_est = 0;
$error = "";
$cedula_busqueda = "";
if (!isset($id_estudiante_sel)) $id_estudiante_sel = 0;

// Si viene por GET id, id_estudiante, cedula o tab
if (isset($_GET['id_estudiante']) && intval($_GET['id_estudiante']) > 0) {
    $id_estudiante_sel = intval($_GET['id_estudiante']);
} elseif (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $id_estudiante_sel = intval($_GET['id']);
}
if (isset($_GET['cedula']) && !empty($_GET['cedula'])) {
    $cedula_busqueda = strtoupper(trim($_GET['cedula']));
}
if (isset($_GET['tab']) && !empty($_GET['tab'])) {
    $tab_activa = trim($_GET['tab']);
}

// =========================================================================
// 1. PROCESAMIENTO DE ACCIONES POST
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula_busqueda = strtoupper(trim($_POST['cedula'] ?? ''));
    if (isset($_POST['id_estudiante']) && intval($_POST['id_estudiante']) > 0) {
        $id_estudiante_sel = intval($_POST['id_estudiante']);
    }

    // 1.1 APROBAR SOLICITUD
    if (isset($_POST['aprobar_solicitud']) && !empty($_POST['solicitud_id'])) {
        $tab_activa = 'solicitudes';
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $mensaje = 'Token de seguridad inválido.';
            $tipo_mensaje = 'danger';
        } else {
            $sol_id = intval($_POST['solicitud_id']);
            $observacion = trim($_POST['observacion_admin'] ?? '');
            $id_periodo_reinc = intval($_POST['id_periodo_reincorporacion'] ?? 0);
            
            $resultado = procesarAprobacionSolicitudAcademica($sol_id, $admin_id, $observacion, $id_periodo_reinc);
            if ($resultado['success']) {
                $mensaje = $resultado['message'];
                $tipo_mensaje = 'success';
            } else {
                $mensaje = $resultado['message'];
                $tipo_mensaje = 'danger';
            }
        }
    }
    
    // 1.2 RECHAZAR SOLICITUD
    if (isset($_POST['rechazar_solicitud']) && !empty($_POST['solicitud_id'])) {
        $tab_activa = 'solicitudes';
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $mensaje = 'Token de seguridad inválido.';
            $tipo_mensaje = 'danger';
        } else {
            $sol_id = intval($_POST['solicitud_id']);
            $motivo = mb_substr(trim($_POST['motivo_rechazo'] ?? ''), 0, 100);
            $observacion = mb_substr(trim($_POST['observacion_admin'] ?? ''), 0, 100);
            
            if (empty($motivo)) {
                $mensaje = 'Debe indicar el motivo por el cual se rechaza la solicitud.';
                $tipo_mensaje = 'warning';
            } else {
                $resultado = procesarRechazoSolicitudAcademica($sol_id, $admin_id, $motivo, $observacion);
                if ($resultado['success']) {
                    $mensaje = $resultado['message'];
                    $tipo_mensaje = 'info';
                } else {
                    $mensaje = $resultado['message'];
                    $tipo_mensaje = 'danger';
                }
            }
        }
    }

    // 1.3 REGISTRAR TRÁMITE DIRECTO DESDE ADMIN
    if (isset($_POST['action_admin_solicitud'])) {
        $action = trim($_POST['action_admin_solicitud']);
        $est_target_id = intval($_POST['estudiante_id'] ?? 0);
        $aprobar_directo = isset($_POST['aprobar_inmediato']) && $_POST['aprobar_inmediato'] == '1';
        $motivo = mb_substr(trim($_POST['motivo'] ?? 'Solicitud tramitada en oficina de Control de Estudios'), 0, 100);
        $nueva_sol_id = 0;

        if ($est_target_id > 0) {
            if ($action === 'adicion_retiro') {
                $eval_ar = function_exists('verificarElegibilidadAdicionRetiro') ? verificarElegibilidadAdicionRetiro($est_target_id) : ['es_apto' => false, 'motivo' => 'Función no disponible'];
                if (!$eval_ar['es_apto']) {
                    $_SESSION['flash_admin_solicitud'] = [
                        'mensaje' => 'No se puede procesar el trámite: ' . $eval_ar['motivo'],
                        'tipo' => 'danger',
                        'tab' => 'constancias',
                        'id_estudiante' => $est_target_id
                    ];
                    header("Location: constancias.php?id=$est_target_id");
                    exit();
                }

                $rets = isset($_POST['retiros']) && is_array($_POST['retiros']) ? array_map('intval', $_POST['retiros']) : [];
                $adcs = isset($_POST['adiciones']) && is_array($_POST['adiciones']) ? array_map('intval', $_POST['adiciones']) : [];
                
                $retiros_det = [];
                if (!empty($rets)) {
                    $ids = implode(',', $rets);
                    $res_m = $db->query("SELECT m.id_materia, m.nombre_materia as nombre, m.cod_materia, COALESCE(s.codigo_seccion, 'N/A') as seccion FROM estudiante_materias em INNER JOIN materias m ON em.id_materia = m.id_materia LEFT JOIN secciones s ON em.id_seccion = s.id_seccion WHERE em.id_usuario = $est_target_id AND em.id_materia IN ($ids)");
                    if ($res_m) while ($rm = $res_m->fetch_assoc()) $retiros_det[] = ['id' => intval($rm['id_materia']), 'nombre' => $rm['nombre'], 'codigo' => $rm['cod_materia'], 'seccion' => $rm['seccion']];
                }

                $adcs_det = [];
                if (!empty($adcs)) {
                    $ids = implode(',', $adcs);
                    $res_m = $db->query("SELECT id_materia, nombre_materia as nombre, cod_materia FROM materias WHERE id_materia IN ($ids)");
                    if ($res_m) while ($rm = $res_m->fetch_assoc()) $adcs_det[] = ['id' => intval($rm['id_materia']), 'nombre' => $rm['nombre'], 'codigo' => $rm['cod_materia'], 'seccion' => 'Por asignar'];
                }

                $accion_tipo = (!empty($rets) && !empty($adcs)) ? 'ambos' : (!empty($rets) ? 'retiro' : 'adicion');
                $materias_data = ['retiros' => $retiros_det, 'adiciones' => $adcs_det];
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'adicion_retiro', $accion_tipo, $motivo, $materias_data);
            } elseif ($action === 'cambio_seccion') {
                $sec_dest_id = intval($_POST['seccion_destino_id'] ?? 0);
                $sec_orig_id = 0;
                $res_sec = $db->query("SELECT id_seccion FROM estudiante_seccion WHERE id_usuario = $est_target_id AND estatus = 'activo' ORDER BY fecha_inscripcion DESC LIMIT 1");
                if ($res_sec && $rs = $res_sec->fetch_assoc()) $sec_orig_id = intval($rs['id_seccion']);
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'cambio_seccion', 'cambio', $motivo, ['materias' => ['Todas las asignaturas']], $sec_orig_id, $sec_dest_id);
            } elseif ($action === 'retiro_semestre') {
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'retiro_semestre', 'retiro_total', $motivo, null);
            } elseif ($action === 'cambio_carrera') {
                $carr_dest_id = intval($_POST['carrera_destino_id'] ?? 0);
                $carr_nom = '';
                $res_c = $db->query("SELECT nombre_carrera FROM carreras WHERE id_carrera = $carr_dest_id LIMIT 1");
                if ($res_c && $rc = $res_c->fetch_assoc()) $carr_nom = $rc['nombre_carrera'];
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'cambio_carrera', 'cambio', $motivo, ['carrera_destino_id' => $carr_dest_id, 'carrera_destino_nombre' => $carr_nom]);
            } elseif ($action === 'cambio_turno') {
                $turno_dest = mb_substr(trim($_POST['turno_destino'] ?? ''), 0, 50);
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'cambio_turno', 'cambio', $motivo, ['turno_destino' => $turno_dest]);
            } elseif ($action === 'intensivo' || $action === 'evaluacion_extraordinaria') {
                $mats_sel = isset($_POST['materias']) && is_array($_POST['materias']) ? array_map('intval', $_POST['materias']) : [];
                $mats_det = [];
                if (!empty($mats_sel)) {
                    $ids = implode(',', $mats_sel);
                    $res_m = $db->query("SELECT id_materia, nombre_materia as nombre, cod_materia FROM materias WHERE id_materia IN ($ids)");
                    if ($res_m) while ($rm = $res_m->fetch_assoc()) $mats_det[] = ['id' => intval($rm['id_materia']), 'nombre' => $rm['nombre'], 'codigo' => $rm['cod_materia']];
                }
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, $action, ($action === 'intensivo' ? 'intensivo' : 'extraordinario'), $motivo, ['materias' => $mats_det]);
            } elseif ($action === 'inscripcion_practicas') {
                $inst = mb_substr(trim($_POST['institucion'] ?? ''), 0, 100);
                $area = mb_substr(trim($_POST['area'] ?? ''), 0, 100);
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'inscripcion_practicas', 'pasantias', $motivo, ['institucion' => $inst, 'area' => $area]);
            } elseif ($action === 'renuncia_cupo') {
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'renuncia_cupo', 'renuncia', $motivo, null);
            } elseif ($action === 'constancia_retiro') {
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'constancia_retiro', 'retiro', $motivo, null);
            } elseif ($action === 'constancia_traslado') {
                $inst_d = mb_substr(trim($_POST['institucion_destino'] ?? ''), 0, 100);
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'constancia_traslado', 'traslado', $motivo, ['institucion_destino' => $inst_d]);
            } elseif ($action === 'constancia_reincorporacion') {
                $periodo_act = obtenerPeriodoActivo($db);
                $per_r = $periodo_act['periodo'] ?? 'Período Activo';
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'constancia_reincorporacion', 'reincorporacion', $motivo, ['periodo_reincorporacion' => $per_r]);
            } elseif ($action === 'retiro_documento') {
                $docs = isset($_POST['documentos']) && is_array($_POST['documentos']) ? array_map('trim', $_POST['documentos']) : [];
                $nueva_sol_id = crearSolicitudAcademica($est_target_id, 'retiro_documento', 'retiro_docs', $motivo, ['documentos' => $docs]);
            }

            if ($nueva_sol_id > 0) {
                if ($aprobar_directo) {
                    procesarAprobacionSolicitudAcademica($nueva_sol_id, $admin_id, 'Aprobado y registrado directamente por Administración');
                }
                $_SESSION['flash_admin_solicitud'] = [
                    'mensaje' => "Trámite #$nueva_sol_id registrado " . ($aprobar_directo ? "y aprobado exitosamente." : "en estado pendiente."),
                    'tipo' => 'success',
                    'tab' => 'solicitudes',
                    'id_estudiante' => $est_target_id,
                    'pdf_id' => $nueva_sol_id
                ];
                header("Location: constancias.php?id=$est_target_id");
                exit();
            } else {
                $mensaje = "Error al procesar el trámite para el estudiante.";
                $tipo_mensaje = "danger";
            }
        }
    }
}

// Cargar estudiante si hay ID o Cédula
if ($id_estudiante_sel > 0) {
    $estudiante = obtenerInfoEstudiantePorId($id_estudiante_sel);
    if ($estudiante) {
        $cedula_busqueda = $estudiante['idusuario'] ?? '';
    }
} elseif (!empty($cedula_busqueda)) {
    $estudiante = buscarEstudiantePorCedulaConsulta($cedula_busqueda);
}

$materias_inscritas = [];
$materias_disponibles = [];
$carreras_disponibles_cambio = [];
$secciones_carrera = [];

if ($estudiante) {
    $carrera = obtenerCarreraEstudiante($estudiante['id']);
    $id_carrera = intval($carrera['id_carrera'] ?? 0);
    $periodo_act_global = obtenerPeriodoActivo($db);
    $periodo_activo_nombre = $periodo_act_global['periodo'] ?? 'Período Activo';
    if ($id_carrera > 0) {
        $trayecto_actual = obtenerTrayectoActual($estudiante['id'], $id_carrera);
    } else {
        $trayecto_actual = obtenerTrayectoActualEstudiante($estudiante['id']);
    }
    $infoTrayecto = obtenerInfoTrayecto($trayecto_actual);
    $estudiante['trayecto_n'] = $infoTrayecto['numero_trayecto'];
    $estudiante['trayecto_nombre'] = $infoTrayecto['nombre_trayecto'];
    
    // Cargar listas académicas para modales interactivos
    $materias_inscritas = obtenerMateriasInscritasParaRetiro($estudiante['id']);
    $materias_disponibles = ($id_carrera > 0) ? obtenerMateriasDisponiblesParaAdicion($estudiante['id'], $id_carrera, null, $estudiante['trayecto_n']) : [];
    $carreras_disponibles_cambio = function_exists('obtenerCarrerasParaCambio') ? obtenerCarrerasParaCambio($id_carrera) : [];
    if (empty($carreras_disponibles_cambio)) {
        $res_all_c = $db->query("SELECT id_carrera, nombre_carrera, cod_carrera FROM carreras WHERE id_carrera > 0 ORDER BY nombre_carrera ASC");
        if ($res_all_c) {
            while ($rc = $res_all_c->fetch_assoc()) {
                if (intval($rc['id_carrera']) !== $id_carrera) {
                    $carreras_disponibles_cambio[] = $rc;
                }
            }
        }
    }

        $turno_est = !empty($estudiante['turno']) ? trim($estudiante['turno']) : 'Diurno';
    $id_trayecto_est = intval($infoTrayecto['id_trayecto'] ?? 0);
    $trayecto_nombre_est = $infoTrayecto['nombre_trayecto'] ?? 'Nivel Actual';

    if ($id_carrera > 0) {
        $id_sec_actual = 0;
        $est_id_val = intval($estudiante['id']);
        $res_sec_act = $db->query("SELECT id_seccion FROM estudiante_seccion WHERE id_usuario = $est_id_val AND estatus = 'activo' ORDER BY fecha_inscripcion DESC LIMIT 1");
        if ($res_sec_act && $rsa = $res_sec_act->fetch_assoc()) {
            $id_sec_actual = intval($rsa['id_seccion']);
        }

        // 1. Filtrar por carrera, turno y trayecto exacto
        if ($id_trayecto_est > 0) {
            $stmt_sc = $db->prepare("SELECT id_seccion, codigo_seccion as nombre_seccion, turno FROM secciones WHERE id_carrera = ? AND LOWER(turno) = LOWER(?) AND id_trayecto = ? AND id_seccion != ? AND (estatus = 'activa' OR estatus IS NULL) ORDER BY codigo_seccion ASC");
            $stmt_sc->bind_param("isii", $id_carrera, $turno_est, $id_trayecto_est, $id_sec_actual);
            $stmt_sc->execute();
            $res_sc = $stmt_sc->get_result();
            if ($res_sc) {
                while ($r = $res_sc->fetch_assoc()) {
                    $secciones_carrera[] = $r;
                }
            }
            $stmt_sc->close();
        }

        // Fallback 1: Si no hay con ese trayecto, filtrar por carrera y turno
        if (empty($secciones_carrera)) {
            $stmt_sc2 = $db->prepare("SELECT id_seccion, codigo_seccion as nombre_seccion, turno FROM secciones WHERE id_carrera = ? AND LOWER(turno) = LOWER(?) AND id_seccion != ? AND (estatus = 'activa' OR estatus IS NULL) ORDER BY codigo_seccion ASC");
            $stmt_sc2->bind_param("isi", $id_carrera, $turno_est, $id_sec_actual);
            $stmt_sc2->execute();
            $res_sc2 = $stmt_sc2->get_result();
            if ($res_sc2) {
                while ($r2 = $res_sc2->fetch_assoc()) {
                    $secciones_carrera[] = $r2;
                }
            }
            $stmt_sc2->close();
        }

        // Fallback 2: General
        if (empty($secciones_carrera)) {
            $res_sc_all = $db->query("SELECT id_seccion, codigo_seccion as nombre_seccion, turno FROM secciones WHERE id_carrera = $id_carrera AND id_seccion != $id_sec_actual ORDER BY codigo_seccion ASC");
            if ($res_sc_all) {
                while ($ra = $res_sc_all->fetch_assoc()) {
                    $secciones_carrera[] = $ra;
                }
            }
        }
    }
    
    // Cargar solicitudes de este estudiante
    $solicitudes_estudiante = function_exists('obtenerSolicitudesAcademicas') ? obtenerSolicitudesAcademicas('', $estudiante['id']) : [];
    $pendientes_arr = array_filter($solicitudes_estudiante, function($s) { return $s['status'] === 'pendiente'; });
    $cant_pendientes_est = count($pendientes_arr);
    
    // Evaluación institucional: Adición y Retiro (Únicamente Automotriz y Térmica, primeras 2 semanas)
    $eval_adicion_retiro = function_exists('verificarElegibilidadAdicionRetiro') 
        ? verificarElegibilidadAdicionRetiro($estudiante['id']) 
        : ['es_apto' => false, 'carrera_valida' => false, 'lapso_valido' => false, 'motivo' => ''];
    $es_apto_adicion_retiro = !empty($eval_adicion_retiro['es_apto']);
    
    if ($cant_pendientes_est > 0 && !isset($_POST['aprobar_solicitud']) && !isset($_POST['rechazar_solicitud']) && empty($mensaje)) {
        $tab_activa = 'solicitudes';
    }
} elseif (!empty($cedula_busqueda) || $id_estudiante_sel > 0) {
    $error = "No se encontró ningún estudiante con los datos ingresados.";
}

include("includes/head.php");
?>

<div class="container-fluid px-4 mt-4 mb-5">
    
    <!-- Encabezado Principal -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-file-contract text-primary mr-2"></i> Generador de Constancias y Solicitudes
            </h4>
            <p class="text-muted small mb-0">Escribe el nombre o cédula para buscar al estudiante en tiempo real y tramitar o emitir sus constancias.</p>
        </div>
        <div>
            <a href="estudiantes.php" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
                <i class="fas fa-users mr-1"></i> Lista de Estudiantes
            </a>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-<?php echo ($tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'info' ? 'info-circle' : 'exclamation-circle')); ?> mr-2"></i>
            <?php echo $mensaje; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Tarjeta Principal del Buscador en Tiempo Real -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 font-weight-bold"><i class="fas fa-search mr-2"></i> Buscar Estudiante</h5>
            <span class="badge badge-light text-primary font-weight-bold"><i class="fas fa-bolt text-warning mr-1"></i> Búsqueda en Tiempo Real</span>
        </div>
        <div class="card-body">
            
            <div class="row justify-content-center">
                <div class="col-md-9 position-relative">
                    <label for="buscador_estudiante" class="small font-weight-bold text-muted text-uppercase">Cédula o Nombre del Estudiante:</label>
                    <div class="input-group input-group-lg">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-primary"></i></span>
                        </div>
                        <input type="text" 
                               id="buscador_estudiante" 
                               class="form-control border-left-0 font-weight-bold" 
                               placeholder="<?php echo $estudiante ? 'Buscar otro estudiante por nombre o cédula...' : 'Escribe la cédula o nombre del estudiante...'; ?>" 
                               value="" 
                               autocomplete="off" 
                               <?php echo $estudiante ? '' : 'autofocus'; ?>>
                        <div class="input-group-append" id="spinner_busqueda_est" style="display: none;">
                            <span class="input-group-text bg-white text-primary">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                        </div>
                        <?php if ($estudiante): ?>
                            <div class="input-group-append">
                                <a href="constancias.php" class="btn btn-outline-secondary" title="Limpiar y buscar otro">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <small class="form-text text-muted">Las opciones aparecerán automáticamente al escribir.</small>

                    <!-- Lista flotante de sugerencias -->
                    <div id="sugerencias_estudiantes" class="list-group shadow position-absolute w-100 mt-1" 
                         style="z-index: 9999; display: none; max-height: 320px; overflow-y: auto; left: 0; right: 0; background: #ffffff; border: 1px solid #007bff; border-radius: 6px;">
                    </div>
                </div>
            </div>

            <!-- Formulario oculto para selección directa -->
            <form method="POST" action="constancias.php" id="form_seleccion_estudiante" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id_estudiante" id="input_sel_id_estudiante" value="0">
                <input type="hidden" name="cedula" id="input_sel_cedula" value="">
            </form>

            <?php if ($error): ?>
                <div class="alert alert-warning shadow-sm mt-3">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($estudiante): ?>
                
                <!-- Alerta si tiene solicitudes pendientes -->
                <?php if ($cant_pendientes_est > 0): ?>
                    <div class="alert alert-warning d-flex justify-content-between align-items-center shadow-sm my-4">
                        <div>
                            <i class="fas fa-bell fa-lg text-warning mr-2"></i>
                            <strong>¡Atención!</strong> Este estudiante tiene <strong><?php echo $cant_pendientes_est; ?></strong> solicitud(es) académica(s) pendiente(s) por revisar y aprobar.
                        </div>
                        <button class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="$('#tab-solicitudes-link').tab('show');">
                            <i class="fas fa-tasks mr-1"></i> Ver Solicitudes
                        </button>
                    </div>
                <?php endif; ?>

                <div class="row mt-4">
                    <!-- Columna de Datos del Estudiante -->
                    <div class="col-md-4 mb-4">
                        <div class="card bg-light shadow-sm border-primary h-100">
                            <div class="card-header bg-primary text-white font-weight-bold d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-id-card mr-1"></i> DATOS DEL ESTUDIANTE</span>
                                <span class="badge badge-light text-primary"><?php echo ($estudiante['status'] ?? 1) == 1 ? 'Activo' : 'Inactivo'; ?></span>
                            </div>
                            <div class="card-body">
                                <h5 class="font-weight-bold text-dark mb-1"><?php echo htmlspecialchars($estudiante['nombre']); ?></h5>
                                <p class="text-muted small mb-3">
                                    <i class="fas fa-id-card mr-1"></i> Cédula: <strong>V-<?php echo htmlspecialchars($estudiante['idusuario']); ?></strong>
                                </p>
                                <hr>
                                <p class="mb-2"><strong>Carrera / PNF:</strong><br><span class="text-primary font-weight-bold"><?php echo htmlspecialchars($carrera['nombre_carrera'] ?? 'Sin Carrera Asignada'); ?></span></p>
                                <p class="mb-2"><strong>Trayecto / Nivel:</strong><br><span class="badge badge-info px-2 py-1"><?php echo htmlspecialchars($estudiante['trayecto_nombre'] ?? 'N/A'); ?></span></p>
                                <p class="mb-0"><strong>Solicitudes Registradas:</strong><br><span class="badge badge-secondary"><?php echo count($solicitudes_estudiante); ?> trámite(s)</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Columna de Pestañas: Constancias vs Solicitudes -->
                    <div class="col-md-8 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white p-2">
                                <ul class="nav nav-pills card-header-pills" id="estudianteTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo ($tab_activa === 'constancias' ? 'active' : ''); ?> font-weight-bold text-uppercase" 
                                           id="tab-constancias-link" data-toggle="tab" href="#tab-constancias" role="tab">
                                            <i class="fas fa-print mr-1"></i> Constancias y Trámites
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo ($tab_activa === 'solicitudes' ? 'active' : ''); ?> font-weight-bold text-uppercase" 
                                           id="tab-solicitudes-link" data-toggle="tab" href="#tab-solicitudes" role="tab">
                                            <i class="fas fa-tasks mr-1"></i> Solicitudes del Estudiante
                                            <?php if ($cant_pendientes_est > 0): ?>
                                                <span class="badge badge-warning badge-pill ml-1"><?php echo $cant_pendientes_est; ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary badge-pill ml-1"><?php echo count($solicitudes_estudiante); ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="card-body p-3">
                                <div class="tab-content" id="estudianteTabsContent">
                                    
                                    <!-- ========================================================================= -->
                                    <!-- PESTAÑA 1: CONSTANCIAS DISPONIBLES (MODALES INTERACTIVOS Y PDF POST) -->
                                    <!-- ========================================================================= -->
                                    <div class="tab-pane fade <?php echo ($tab_activa === 'constancias' ? 'show active' : ''); ?>" id="tab-constancias" role="tabpanel">
                                        <div class="row">
                                            <!-- 1. Constancia de Inscripción o Estudios (Descarga Directa) -->
                                            <?php if ($estudiante['trayecto_n'] == 0): ?>
                                                <div class="col-sm-6 mb-2">
                                                    <button type="button" class="btn btn-outline-primary btn-block text-left font-weight-bold btn-abrir-pdf-directo"
                data-id="<?php echo $estudiante['id']; ?>"
                data-tipo="inscripcion"
                data-titulo="CONSTANCIA DE INSCRIPCIÓN">
            <i class="fas fa-file-invoice mr-1 text-primary"></i> Constancia de Inscripción
        </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="col-sm-6 mb-2">
                                                    <button type="button" class="btn btn-outline-primary btn-block text-left font-weight-bold btn-abrir-pdf-directo"
                data-id="<?php echo $estudiante['id']; ?>"
                data-tipo="estudios"
                data-titulo="CONSTANCIA DE ESTUDIOS">
            <i class="fas fa-user-graduate mr-1 text-primary"></i> Constancia de Estudios
        </button>
                                                </div>
                                            <?php endif; ?>

                                            <!-- 2. Adición / Retiro -->
                                            <div class="col-sm-6 mb-2">
                                                <?php if ($es_apto_adicion_retiro): ?>
                                                    <button type="button" class="btn btn-outline-info btn-block text-left font-weight-bold d-flex justify-content-between align-items-center" 
                                                            data-toggle="modal" data-target="#modalAdminAdicionRetiro">
                                                        <span><i class="fas fa-exchange-alt mr-1 text-info"></i> Adición / Retiro de Materias</span>
                                                        <span class="badge badge-success"><?= $eval_adicion_retiro['dias_restantes'] ?> DÍAS</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-block text-left font-weight-bold d-flex justify-content-between align-items-center opacity-75" 
                                                            data-toggle="modal" data-target="#modalAdminNoAptoAdicionRetiro">
                                                        <span><i class="fas fa-exchange-alt mr-1 text-muted"></i> Adición / Retiro de Materias</span>
                                                        <?php if (!empty($eval_adicion_retiro['carrera_valida'])): ?>
                                                            <span class="badge badge-warning text-dark">LAPSO CERRADO</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">NO APLICA</span>
                                                        <?php endif; ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <!-- 3. Cambio de Sección -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-secondary btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminCambioSeccion">
                                                    <i class="fas fa-sync-alt mr-1 text-secondary"></i> Cambiar Sección
                                                </button>
                                            </div>

                                            <!-- 4. Curso Intensivo -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-warning btn-block text-left font-weight-bold text-dark" 
                                                        data-toggle="modal" data-target="#modalAdminIntensivo">
                                                    <i class="fas fa-sun mr-1 text-warning"></i> Curso Intensivo
                                                </button>
                                            </div>

                                            <!-- 5. Evaluación Extraordinaria -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-danger btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminExtraordinario">
                                                    <i class="fas fa-redo mr-1 text-danger"></i> Evaluación Extraordinaria
                                                </button>
                                            </div>

                                            <!-- 6. Pasantías / Proyecto -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-success btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminPracticas">
                                                    <i class="fas fa-briefcase mr-1 text-success"></i> Pasantías / Proyecto
                                                </button>
                                            </div>

                                            <!-- 7. Cambio de Carrera -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-primary btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminCambioCarrera">
                                                    <i class="fas fa-random mr-1 text-primary"></i> Cambio de Carrera / PNF
                                                </button>
                                            </div>

                                            <!-- 8. Cambio de Turno -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-primary btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminCambioTurno">
                                                    <i class="fas fa-clock mr-1 text-primary"></i> Cambio de Turno
                                                </button>
                                            </div>

                                            <!-- 9. Retiro de Semestre -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-dark btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminRetiroSemestre">
                                                    <i class="fas fa-calendar-times mr-1 text-dark"></i> Retiro de Semestre
                                                </button>
                                            </div>

                                            <!-- 10. Renuncia de Cupo -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-danger btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminRenunciaCupo">
                                                    <i class="fas fa-user-times mr-1 text-danger"></i> Renuncia de Cupo
                                                </button>
                                            </div>

                                            <!-- 11. Constancia de Retiro Oficial -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-dark btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminConstanciaRetiro">
                                                    <i class="fas fa-file-export mr-1 text-dark"></i> Constancia de Retiro
                                                </button>
                                            </div>

                                            <!-- 12. Traslado Universitario -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-info btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminConstanciaTraslado">
                                                    <i class="fas fa-truck-moving mr-1 text-info"></i> Traslado Universitario
                                                </button>
                                            </div>

                                            <!-- 13. Reincorporación -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-success btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminConstanciaReincorporacion">
                                                    <i class="fas fa-user-plus mr-1 text-success"></i> Reincorporación
                                                </button>
                                            </div>

                                            <!-- 14. Retiro de Documentos -->
                                            <div class="col-sm-6 mb-2">
                                                <button type="button" class="btn btn-outline-secondary btn-block text-left font-weight-bold" 
                                                        data-toggle="modal" data-target="#modalAdminRetiroDocumentos">
                                                    <i class="fas fa-file-download mr-1 text-secondary"></i> Retiro de Documentos
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ========================================================================= -->
                                    <!-- PESTAÑA 2: SOLICITUDES ACADÉMICAS DEL ESTUDIANTE -->
                                    <!-- ========================================================================= -->
                                    <div class="tab-pane fade <?php echo ($tab_activa === 'solicitudes' ? 'show active' : ''); ?>" id="tab-solicitudes" role="tabpanel">
                                        <?php if (empty($solicitudes_estudiante)): ?>
                                            <div class="text-center py-5 text-muted">
                                                <i class="fas fa-inbox fa-3x mb-2 text-gray-300"></i>
                                                <p class="mb-0 font-weight-bold">Este estudiante no posee solicitudes académicas registradas.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover table-bordered mb-0 text-center table-sm">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th># ID</th>
                                                            <th>Trámite</th>
                                                            <th>Fecha</th>
                                                            <th>Motivo</th>
                                                            <th>Estado</th>
                                                            <th>Acción</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($solicitudes_estudiante as $s): ?>
                                                            <tr>
                                                                <td class="font-weight-bold">#<?php echo $s['id']; ?></td>
                                                                <td>
                                                                    <?php if ($s['tipo_solicitud'] === 'adicion_retiro'): ?>
                                                                        <span class="badge badge-primary px-2 py-1">ADICIÓN / RETIRO</span>
                                                                    <?php elseif ($s['tipo_solicitud'] === 'cambio_seccion'): ?>
                                                                        <span class="badge badge-info px-2 py-1">CAMBIO SECCIÓN</span>
                                                                    <?php elseif ($s['tipo_solicitud'] === 'retiro_semestre'): ?>
                                                                        <span class="badge badge-danger px-2 py-1">RETIRO SEMESTRE</span>
                                                                    <?php elseif ($s['tipo_solicitud'] === 'cambio_carrera'): ?>
                                                                        <span class="badge badge-primary px-2 py-1">CAMBIO CARRERA</span>
                                                                    <?php elseif ($s['tipo_solicitud'] === 'intensivo'): ?>
                                                                        <span class="badge badge-warning px-2 py-1 text-dark">INTENSIVO</span>
                                                                    <?php elseif ($s['tipo_solicitud'] === 'evaluacion_extraordinaria'): ?>
                                                                        <span class="badge badge-danger px-2 py-1">EXTRAORDINARIO</span>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-secondary px-2 py-1"><?php echo htmlspecialchars(strtoupper($s['tipo_solicitud'])); ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="small"><?php echo date('d/m/Y', strtotime($s['fecha_solicitud'])); ?></td>
                                                                <td class="text-left small" style="max-width: 180px;">
                                                                    <div class="text-truncate" title="<?php echo htmlspecialchars($s['motivo']); ?>">
                                                                        <?php echo htmlspecialchars($s['motivo']); ?>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <?php if ($s['status'] === 'pendiente'): ?>
                                                                        <span class="badge badge-warning font-weight-bold"><i class="fas fa-clock mr-1"></i> PENDIENTE</span>
                                                                    <?php elseif ($s['status'] === 'aprobada'): ?>
                                                                        <span class="badge badge-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> APROBADA</span>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-danger font-weight-bold"><i class="fas fa-times-circle mr-1"></i> RECHAZADA</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group btn-group-sm" role="group">
                                                                        <!-- Ver Detalle -->
                                                                        <button type="button" class="btn btn-outline-info btn-sm btn-ver-detalle" 
                                                                                data-solicitud='<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8'); ?>'
                                                                                title="Ver Detalle">
                                                                            <i class="fas fa-eye"></i>
                                                                        </button>

                                                                        <!-- Comprobante PDF (POST) -->
                                                                        <button type="button" class="btn btn-outline-secondary btn-sm btn-ver-pdf-solicitud" 
                data-solicitud-id="<?php echo $s['id']; ?>" 
                data-titulo="COMPROBANTE DE TRÁMITE #<?php echo $s['id']; ?>" 
                title="Ver Comprobante PDF en Modal">
            <i class="fas fa-file-pdf text-danger"></i>
        </button>

                                                                        <?php if ($s['status'] === 'pendiente'): ?>
                                                                            <!-- Aprobar -->
                                                                            <button type="button" class="btn btn-success btn-sm btn-aprobar-modal" 
                                                                                    data-id="<?php echo $s['id']; ?>"
                                                                                    data-nombre="<?php echo htmlspecialchars($s['nombre_estudiante']); ?>"
                                                                                    data-tipo="<?php echo htmlspecialchars($s['tipo_solicitud']); ?>"
                                                                                    title="Aprobar Solicitud">
                                                                                <i class="fas fa-check"></i>
                                                                            </button>

                                                                            <!-- Rechazar -->
                                                                            <button type="button" class="btn btn-danger btn-sm btn-rechazar-modal" 
                                                                                    data-id="<?php echo $s['id']; ?>"
                                                                                    data-nombre="<?php echo htmlspecialchars($s['nombre_estudiante']); ?>"
                                                                                    title="Rechazar Solicitud">
                                                                                <i class="fas fa-times"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
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

                <!-- ========================================================================= -->
                <!-- MODALES INTERACTIVOS DE TRÁMITES DESDE ADMINISTRACIÓN -->
                <!-- ========================================================================= -->

                <!-- MODAL ADMIN ADICIÓN Y RETIRO NO APTO / NO DISPONIBLE -->
                <div class="modal fade" id="modalAdminNoAptoAdicionRetiro" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content border-info shadow">
                            <div class="modal-header bg-info text-white justify-content-center position-relative">
                                <h5 class="modal-title font-weight-bold text-uppercase text-center">
                                    <i class="fas fa-info-circle mr-2"></i> ADICIÓN Y RETIRO DE MATERIAS
                                </h5>
                                <button type="button" class="close text-white position-absolute" style="right: 15px; top: 15px;" data-dismiss="modal" aria-label="Cerrar">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-exchange-alt text-info" style="font-size: 3.5rem;"></i>
                                </div>
                                <h5 class="font-weight-bold text-dark text-uppercase mb-3 text-center">NORMATIVA INSTITUCIONAL</h5>
                                <div class="alert alert-warning text-uppercase small font-weight-bold text-center mb-3">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <?= htmlspecialchars($eval_adicion_retiro['motivo'] ?? 'Trámite no disponible.'); ?>
                                </div>
                                <div class="text-muted text-left small mb-0 font-weight-bold">
                                    <p class="mb-1"><i class="fas fa-check-circle text-success mr-1"></i> <strong>Carreras Autorizadas:</strong> Únicamente Mecánica Automotriz y Mecánica Térmica.</p>
                                    <p class="mb-1"><i class="fas fa-clock text-primary mr-1"></i> <strong>Lapso Reglamentario:</strong> Exclusivamente durante las 2 primeras semanas de cada período académico.</p>
                                    <p class="mb-0"><i class="fas fa-university text-secondary mr-1"></i> Esta restricción aplica de acuerdo con las disposiciones del reglamento de Control de Estudios de la UPTPC.</p>
                                </div>
                            </div>
                            <div class="modal-footer bg-light justify-content-center">
                                <button type="button" class="btn btn-secondary font-weight-bold text-uppercase px-4 shadow-sm" data-dismiss="modal">
                                    <i class="fas fa-times mr-1"></i> ENTENDIDO
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 1. MODAL ADMIN ADICIÓN Y RETIRO -->
                <div class="modal fade" id="modalAdminAdicionRetiro" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content shadow border-info">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="adicion_retiro">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-info text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-exchange-alt mr-2"></i> ADICIÓN Y RETIRO DE MATERIAS
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <?php if (!empty($eval_adicion_retiro['es_apto'])): ?>
                                        <div class="alert alert-info py-2 px-3 small mb-3">
                                            <i class="fas fa-info-circle mr-1"></i> <strong>Período Reglamentario Activo:</strong> Carrera: <strong><?= htmlspecialchars($carrera['nombre_carrera'] ?? '') ?></strong> | Lapso: <strong><?= htmlspecialchars($eval_adicion_retiro['periodo_nombre'] ?? '') ?></strong> (Vence: <strong><?= $eval_adicion_retiro['fecha_limite'] ?? '' ?></strong> - Quedan <strong><?= $eval_adicion_retiro['dias_restantes'] ?? 0 ?> días</strong>).
                                        </div>
                                    <?php endif; ?>
                                    <div class="alert alert-secondary py-1 px-2 small mb-3">
                                        <i class="fas fa-layer-group mr-1"></i> Nivel del Estudiante: <strong class="text-uppercase"><?php echo htmlspecialchars($estudiante['trayecto_nombre'] ?? 'Nivel Actual'); ?></strong> (Materias de adición filtradas estrictamente por su trayecto).
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 border-danger">
                                                <div class="card-header bg-danger text-white py-1 font-weight-bold small">MATERIAS A RETIRAR (INSCRITAS)</div>
                                                <div class="card-body p-2" style="max-height: 180px; overflow-y: auto;">
                                                    <?php if (empty($materias_inscritas)): ?>
                                                        <p class="text-muted small p-2 mb-0">Sin materias inscritas activas.</p>
                                                    <?php else: ?>
                                                        <?php foreach ($materias_inscritas as $mi): ?>
                                                            <div class="custom-control custom-checkbox mb-1">
                                                                <input type="checkbox" class="custom-control-input" id="adm_ret_<?php echo $mi['id_materia']; ?>" name="retiros[]" value="<?php echo $mi['id_materia']; ?>">
                                                                <label class="custom-control-label small font-weight-bold" for="adm_ret_<?php echo $mi['id_materia']; ?>">
                                                                    <?php echo htmlspecialchars($mi['cod_materia'] . ' - ' . $mi['nombre_materia']); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 border-success">
                                                <div class="card-header bg-success text-white py-1 font-weight-bold small">MATERIAS A ADICIONAR (DISPONIBLES)</div>
                                                <div class="card-body p-2" style="max-height: 180px; overflow-y: auto;">
                                                    <?php if (empty($materias_disponibles)): ?>
                                                        <p class="text-muted small p-2 mb-0">Sin materias disponibles.</p>
                                                    <?php else: ?>
                                                        <?php foreach ($materias_disponibles as $ma): ?>
                                                            <div class="custom-control custom-checkbox mb-1">
                                                                <input type="checkbox" class="custom-control-input" id="adm_adc_<?php echo $ma['id_materia']; ?>" name="adiciones[]" value="<?php echo $ma['id_materia']; ?>">
                                                                <label class="custom-control-label small font-weight-bold" for="adm_adc_<?php echo $ma['id_materia']; ?>">
                                                                    <?php echo htmlspecialchars($ma['cod_materia'] . ' - ' . $ma['nombre_materia']); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo / Justificación:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo de la adición/retiro..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_ar" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_ar">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar y aplicar cambios académicos inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-info font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 2. MODAL ADMIN CAMBIO DE SECCIÓN -->
                <div class="modal fade" id="modalAdminCambioSeccion" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-secondary">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="cambio_seccion">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-secondary text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-sync-alt mr-2"></i> CAMBIO DE SECCIÓN
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info py-1 px-2 small mb-2">
                                        <i class="fas fa-info-circle mr-1"></i> Turno: <strong class="text-uppercase"><?php echo htmlspecialchars($turno_est ?? 'Diurno'); ?></strong> | Nivel: <strong class="text-uppercase"><?php echo htmlspecialchars($trayecto_nombre_est ?? 'Nivel Actual'); ?></strong> (Secciones filtradas por turno y trayecto).
                                    </div>
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Nueva Sección Destino <span class="text-danger">*</span></label>
                                        <select class="form-control font-weight-bold" name="seccion_destino_id" required>
                                            <option value="">-- Selecciona la Sección --</option>
                                            <?php foreach ($secciones_carrera as $sc): ?>
                                                <option value="<?php echo $sc['id_seccion']; ?>">
                                                    <?php echo htmlspecialchars($sc['nombre_seccion'] . ' (' . ($sc['turno'] ?? 'Diurno') . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo del cambio de sección..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_cs" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_cs">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar y aplicar cambios académicos inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-secondary font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 3. MODAL ADMIN CAMBIO DE CARRERA -->
                <div class="modal fade" id="modalAdminCambioCarrera" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-primary">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="cambio_carrera">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-random mr-2"></i> CAMBIO DE CARRERA / PNF
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Carrera Destino <span class="text-danger">*</span></label>
                                        <select class="form-control font-weight-bold" name="carrera_destino_id" required>
                                            <option value="">-- Selecciona la Carrera Destino --</option>
                                            <?php foreach ($carreras_disponibles_cambio as $car): ?>
                                                <option value="<?php echo $car['id_carrera']; ?>">
                                                    <?php echo htmlspecialchars($car['nombre_carrera']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Exposición de Motivos:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo del cambio de carrera..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_cc" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_cc">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar y actualizar carrera del estudiante inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 4. MODAL ADMIN CAMBIO DE TURNO -->
                <div class="modal fade" id="modalAdminCambioTurno" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-primary">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="cambio_turno">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-clock mr-2"></i> CAMBIO DE TURNO
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Turno Solicitado <span class="text-danger">*</span></label>
                                        <select class="form-control font-weight-bold" name="turno_destino" required>
                                            <option value="">-- Selecciona el Turno --</option>
                                            <option value="Diurno">Diurno</option>
                                            <option value="Nocturno">Nocturno</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo del cambio de turno..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_ct" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_ct">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar trámite inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 5. MODAL ADMIN RETIRO DE SEMESTRE -->
                <div class="modal fade" id="modalAdminRetiroSemestre" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-dark">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="retiro_semestre">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-calendar-times mr-2"></i> RETIRO DE SEMESTRE
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo del Retiro:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo del retiro de semestre..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_rs" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_rs">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar y retirar materias del período inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-dark font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 6. MODAL ADMIN INTENSIVO -->
                <div class="modal fade" id="modalAdminIntensivo" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content shadow border-warning">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="intensivo">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-sun mr-2"></i> CURSO INTENSIVO
                                    </h5>
                                    <button type="button" class="close text-dark" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <label class="font-weight-bold small text-uppercase">Materias para Intensivo</label>
                                    <div class="card mb-3 border-warning">
                                        <div class="card-body p-2" style="max-height: 180px; overflow-y: auto;">
                                            <?php if (empty($materias_disponibles)): ?>
                                                <p class="text-muted small p-2 mb-0">Sin materias disponibles.</p>
                                            <?php else: ?>
                                                <?php foreach ($materias_disponibles as $md): ?>
                                                    <div class="custom-control custom-checkbox mb-1">
                                                        <input type="checkbox" class="custom-control-input" id="adm_int_<?php echo $md['id_materia']; ?>" name="materias[]" value="<?php echo $md['id_materia']; ?>">
                                                        <label class="custom-control-label small font-weight-bold" for="adm_int_<?php echo $md['id_materia']; ?>">
                                                            <?php echo htmlspecialchars($md['cod_materia'] . ' - ' . $md['nombre_materia']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_int" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_int">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar e inscribir materias en intensivo inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-warning text-dark font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 7. MODAL ADMIN EXTRAORDINARIO -->
                <div class="modal fade" id="modalAdminExtraordinario" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content shadow border-danger">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="evaluacion_extraordinaria">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-redo mr-2"></i> EVALUACIÓN EXTRAORDINARIA
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <label class="font-weight-bold small text-uppercase">Materias para Extraordinario</label>
                                    <div class="card mb-3 border-danger">
                                        <div class="card-body p-2" style="max-height: 180px; overflow-y: auto;">
                                            <?php if (empty($materias_disponibles)): ?>
                                                <p class="text-muted small p-2 mb-0">Sin materias registradas.</p>
                                            <?php else: ?>
                                                <?php foreach ($materias_disponibles as $me): ?>
                                                    <div class="custom-control custom-checkbox mb-1">
                                                        <input type="checkbox" class="custom-control-input" id="adm_ext_<?php echo $me['id_materia']; ?>" name="materias[]" value="<?php echo $me['id_materia']; ?>">
                                                        <label class="custom-control-label small font-weight-bold" for="adm_ext_<?php echo $me['id_materia']; ?>">
                                                            <?php echo htmlspecialchars($me['cod_materia'] . ' - ' . $me['nombre_materia']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo / Justificación:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_ext" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_ext">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar evaluación extraordinaria inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 8. MODAL ADMIN PASANTÍAS -->
                <div class="modal fade" id="modalAdminPracticas" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-success">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="inscripcion_practicas">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-briefcase mr-2"></i> PASANTÍAS / PROYECTO
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Empresa / Institución <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="institucion" maxlength="100" placeholder="Nombre de la empresa..." required>
                                    </div>
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Área / Proyecto</label>
                                        <input type="text" class="form-control" name="area" maxlength="100" placeholder="Área o proyecto...">
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_pr" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_pr">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar solicitud inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-success font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 9. MODAL ADMIN RENUNCIA CUPO -->
                <div class="modal fade" id="modalAdminRenunciaCupo" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-danger">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="renuncia_cupo">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-user-times mr-2"></i> RENUNCIA DE CUPO
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-danger small font-weight-bold">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Procesar renuncia voluntaria y desincorporación académica.
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo de la renuncia..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_rc" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_rc">
                                            <i class="fas fa-check-circle mr-1"></i> Desincorporar estudiante inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 10. MODAL ADMIN CONSTANCIA RETIRO -->
                <div class="modal fade" id="modalAdminConstanciaRetiro" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-dark">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="constancia_retiro">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-file-export mr-2"></i> CONSTANCIA DE RETIRO OFICIAL
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo del Retiro:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_cr" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_cr">
                                            <i class="fas fa-check-circle mr-1"></i> Registrar y desincorporar estudiante
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-dark font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 11. MODAL ADMIN TRASLADO -->
                <div class="modal fade" id="modalAdminConstanciaTraslado" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-info">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="constancia_traslado">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-info text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-truck-moving mr-2"></i> TRASLADO UNIVERSITARIO
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Universidad / Institución Destino <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="institucion_destino" maxlength="100" placeholder="Nombre de la institución destino..." required>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo del Traslado:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_tr" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_tr">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar constancia de traslado inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-info font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 12. MODAL ADMIN REINCORPORACIÓN -->
                <div class="modal fade" id="modalAdminConstanciaReincorporacion" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-success">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="constancia_reincorporacion">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-user-plus mr-2"></i> REINCORPORACIÓN ACADÉMICA
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info py-2 small mb-3">
                                        <i class="fas fa-calendar-check mr-1"></i> Período de reincorporación: <strong class="text-uppercase"><?php echo htmlspecialchars($periodo_activo_nombre ?? 'Período Activo'); ?></strong> (Período Académico Activo en el Sistema).
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo / Justificación:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_re" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_re">
                                            <i class="fas fa-check-circle mr-1"></i> Reactivar estudiante en el sistema inmediatamente
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-success font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 13. MODAL ADMIN RETIRO DOCUMENTOS -->
                <div class="modal fade" id="modalAdminRetiroDocumentos" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-secondary">
                            <form method="POST" action="constancias.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_admin_solicitud" value="retiro_documento">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                
                                <div class="modal-header bg-secondary text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-file-download mr-2"></i> RETIRO DE DOCUMENTOS
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <label class="font-weight-bold small text-uppercase">Documentos Solicitados</label>
                                    <div class="card mb-3 border-secondary">
                                        <div class="card-body p-2">
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="adm_doc_fn" name="documentos[]" value="Fondo Negro de Título">
                                                <label class="custom-control-label small" for="adm_doc_fn">Fondo Negro de Título de Bachiller</label>
                                            </div>
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="adm_doc_nc" name="documentos[]" value="Notas Certificadas de Bachillerato">
                                                <label class="custom-control-label small" for="adm_doc_nc">Notas Certificadas de Bachillerato</label>
                                            </div>
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="adm_doc_pn" name="documentos[]" value="Partida de Nacimiento">
                                                <label class="custom-control-label small" for="adm_doc_pn">Partida de Nacimiento</label>
                                            </div>
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="adm_doc_cc" name="documentos[]" value="Copia de Cédula de Identidad">
                                                <label class="custom-control-label small" for="adm_doc_cc">Copia de Cédula de Identidad</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo del Retiro:</label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo..."></textarea>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="adm_apr_rd" name="aprobar_inmediato" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold text-success" for="adm_apr_rd">
                                            <i class="fas fa-check-circle mr-1"></i> Aprobar entrega de documentos
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-secondary font-weight-bold px-4"><i class="fas fa-save mr-1"></i> Procesar y Descargar PDF</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Formulario automático para abrir PDF generado en nueva pestaña -->
<!-- ========================================================================= -->
<!-- MODALES INTERACTIVOS DE DETALLE, APROBACIÓN Y RECHAZO -->
<!-- ========================================================================= -->

<!-- 1. MODAL DETALLE DE SOLICITUD -->
<div class="modal fade" id="modalDetalleSolicitudAdmin" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content shadow border-info">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold" id="detalleModalTitulo">
                    <i class="fas fa-info-circle mr-2"></i> Detalle de Solicitud Académica
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" id="detalleModalCuerpo">
                <!-- Se llena dinámicamente vía JS -->
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <input type="hidden" id="modal_detalle_solicitud_id" value="">
                <button type="button" class="btn btn-outline-danger font-weight-bold btn-modal-detalle-pdf" id="btnModalDetalleVerPdf">
            <i class="fas fa-file-pdf mr-1"></i> Ver Comprobante PDF
        </button>
                <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. MODAL CONFIRMAR APROBACIÓN AUTOMÁTICA -->
<div class="modal fade" id="modalConfirmarAprobacion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-success">
            <form method="POST" action="constancias.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="aprobar_solicitud" value="1">
                <input type="hidden" name="solicitud_id" id="aprobar_solicitud_id" value="">
                <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($cedula_busqueda); ?>">
                <input type="hidden" name="id_estudiante" value="<?php echo intval($estudiante['id'] ?? 0); ?>">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-check-circle mr-2"></i> Aprobar Solicitud y Ejecutar Cambios
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="alert alert-success small font-weight-bold">
                        <i class="fas fa-robot mr-1"></i> <strong>Ejecución Automática:</strong> Al confirmar, el sistema aplicará automáticamente los cambios de materias/secciones en la base de datos académica del estudiante.
                    </div>
                    <p id="aprobarTextoConfirmacion" class="mb-3 font-weight-bold text-dark"></p>
                    
                    <!-- Selector de período para Reincorporación -->
                    <div id="divPeriodoReincorporacion" style="display:none;" class="form-group mb-3 p-3 bg-light rounded border border-success">
                        <label class="font-weight-bold text-success small text-uppercase mb-1">
                            <i class="fas fa-calendar-check mr-1"></i> Período Académico de Reincorporación <span class="text-danger">*</span>:
                        </label>
                        <select name="id_periodo_reincorporacion" id="selectPeriodoReincorporacion" class="form-control form-control-sm font-weight-bold">
                            <?php 
                            $res_p_reinc = $db->query("SELECT id_periodo, nombre_periodo, activo FROM periodos_academicos ORDER BY id_periodo DESC");
                            if ($res_p_reinc) {
                                while ($pr = $res_p_reinc->fetch_assoc()) {
                                    $is_act = ($pr['activo'] == 1);
                                    echo '<option value="' . $pr['id_periodo'] . '" ' . ($is_act ? 'selected' : '') . '>';
                                    echo htmlspecialchars($pr['nombre_periodo']) . ($is_act ? ' (Activo)' : '');
                                    echo '</option>';
                                }
                            }
                            ?>
                        </select>
                        <small class="form-text text-muted mt-1">
                            <i class="fas fa-info-circle text-info mr-1"></i> Control de Estudios define en qué período se incorpora. El sistema evaluará el avance del estudiante para asignarlo al trayecto y sección correspondiente, o dejarlo en espera activa si aún no hay sección disponible.
                        </small>
                    </div>

                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-muted text-uppercase">Observación Administrativa (Opcional):</label>
                        <textarea class="form-control" name="observacion_admin" rows="2" maxlength="100" placeholder="Notas internas o comentarios para el estudiante..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success font-weight-bold px-4">
                        <i class="fas fa-check mr-1"></i> Aprobar y Aplicar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. MODAL RECHAZAR SOLICITUD -->
<div class="modal fade" id="modalRechazarSolicitud" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-danger">
            <form method="POST" action="constancias.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="rechazar_solicitud" value="1">
                <input type="hidden" name="solicitud_id" id="rechazar_solicitud_id" value="">
                <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($cedula_busqueda); ?>">
                <input type="hidden" name="id_estudiante" value="<?php echo intval($estudiante['id'] ?? 0); ?>">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-ban mr-2"></i> Rechazar Solicitud Académica
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body p-4">
                    <p id="rechazarTextoConfirmacion" class="mb-3 font-weight-bold text-dark"></p>
                    
                    <div class="form-group">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="small font-weight-bold text-danger text-uppercase mb-0">Motivo del Rechazo <span class="text-danger">*</span>:</label>
                            <small class="text-muted font-weight-bold"><span class="char-count-rechazo">0</span>/100 caracteres</small>
                        </div>
                        <textarea class="form-control" name="motivo_rechazo" id="motivo_rechazo_textarea" rows="3" maxlength="100" required placeholder="Explica detalladamente la causa por la cual se rechaza la solicitud..."></textarea>
                    </div>

                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-muted text-uppercase">Observación Adicional (Opcional):</label>
                        <textarea class="form-control" name="observacion_admin" rows="2" maxlength="100" placeholder="Notas internas..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger font-weight-bold px-4">
                        <i class="fas fa-ban mr-1"></i> Confirmar Rechazo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>


let currentPdfBlobUrl = null;
let reabrirDetalleAlCerrarPdf = false;

function mostrarPDFEnModal(params, titulo) {
    titulo = titulo || 'DOCUMENTO OFICIAL';
    const modalEl = $('#modalVisualizadorPDF');
    const iframe = document.getElementById('iframeVisualizadorPDF');
    const spinner = document.getElementById('spinnerCargandoPDF');
    const tituloEl = document.getElementById('tituloVisualizadorPDF');
    const btnDescargar = document.getElementById('btnDescargarPDFModal');

    if (tituloEl) tituloEl.textContent = titulo;
    if (spinner) {
        spinner.style.display = 'flex';
        spinner.innerHTML = `
            <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
                <span class="sr-only">Cargando...</span>
            </div>
            <h6 class="font-weight-bold text-dark text-uppercase">Generando documento oficial en PDF...</h6>
            <small class="text-muted">Por favor espera un momento mientras se procesa.</small>
        `;
    }
    if (iframe) iframe.src = 'about:blank';
    
    if (currentPdfBlobUrl) {
        URL.revokeObjectURL(currentPdfBlobUrl);
        currentPdfBlobUrl = null;
    }

    modalEl.modal('show');

    const formData = new FormData();
    for (const key in params) {
        if (params[key] !== undefined && params[key] !== null) {
            formData.append(key, params[key]);
        }
    }

    fetch('../constancias/generar_constancia.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(errorText || ('Error en el servidor (' + response.status + ')'));
        }
        const blob = await response.blob();
        if (blob.size === 0) {
            throw new Error('El documento recibido está vacío.');
        }
        return blob;
    })
    .then(blob => {
        const pdfBlob = new Blob([blob], { type: 'application/pdf' });
        currentPdfBlobUrl = URL.createObjectURL(pdfBlob);
        
        if (iframe) {
            iframe.onload = function() {
                if (spinner) spinner.style.display = 'none';
            };
            iframe.src = currentPdfBlobUrl;
        }
        
        // Timeout de seguridad para ocultar spinner en todos los navegadores
        setTimeout(function() {
            if (spinner) spinner.style.display = 'none';
        }, 500);

        if (btnDescargar) {
            btnDescargar.href = currentPdfBlobUrl;
            btnDescargar.download = (titulo.replace(/[^a-zA-Z0-9_-]/g, '_') || 'documento') + '.pdf';
        }
        const btnExt = document.getElementById('btnAbrirNuevaPestanaPDF');
        if (btnExt) {
            btnExt.href = currentPdfBlobUrl;
        }
    })
    .catch(error => {
        console.error('Error al generar PDF:', error);
        if (spinner) {
            spinner.innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5 class="font-weight-bold text-danger">No se pudo cargar el documento</h5>
                    <p class="text-muted small mb-3">${error.message || 'No se pudo generar el PDF solicitado.'}</p>
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cerrar</button>
                </div>
            `;
        }
    });
}

// Delegación de eventos global
document.addEventListener('click', function(e) {
    // 1. Botones directos de constancias
    const btnDirecto = e.target.closest('.btn-abrir-pdf-directo');
    if (btnDirecto) {
        e.preventDefault();
        const id = btnDirecto.getAttribute('data-id');
        const tipo = btnDirecto.getAttribute('data-tipo');
        const titulo = btnDirecto.getAttribute('data-titulo') || 'CONSTANCIA';
        const params = { tipo: tipo };
        if (id) params.id = id;
        mostrarPDFEnModal(params, titulo);
        return;
    }

    // 2. Botones de la tabla de historial de solicitudes
    const btnSol = e.target.closest('.btn-ver-pdf-solicitud');
    if (btnSol) {
        e.preventDefault();
        const solId = btnSol.getAttribute('data-solicitud-id');
        const titulo = btnSol.getAttribute('data-titulo') || ('COMPROBANTE #' + solId);
        reabrirDetalleAlCerrarPdf = false;
        mostrarPDFEnModal({ solicitud_id: solId }, titulo);
        return;
    }

    // 2.1 Botón Ver Comprobante PDF dentro del modal Detalle de Solicitud
    const btnDetallePdf = e.target.closest('#btnModalDetalleVerPdf, .btn-modal-detalle-pdf');
    if (btnDetallePdf) {
        e.preventDefault();
        const solId = btnDetallePdf.getAttribute('data-solicitud-id') || (document.getElementById('modal_detalle_solicitud_id') ? document.getElementById('modal_detalle_solicitud_id').value : '');
        if (!solId) {
            alert('No se pudo identificar la solicitud seleccionada.');
            return;
        }
        const titulo = btnDetallePdf.getAttribute('data-titulo') || ('COMPROBANTE #' + solId);
        reabrirDetalleAlCerrarPdf = true;
        $('#modalDetalleSolicitudAdmin').modal('hide');
        setTimeout(function() {
            mostrarPDFEnModal({ solicitud_id: solId }, titulo);
        }, 350);
        return;
    }

    // 3. Botón Imprimir en modal
    const btnImp = e.target.closest('#btnImprimirPDFModal');
    if (btnImp) {
        e.preventDefault();
        const iframe = document.getElementById('iframeVisualizadorPDF');
        if (iframe && iframe.contentWindow) {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        }
        return;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Contador de caracteres para rechazo
    var rechazoTextarea = document.getElementById('motivo_rechazo_textarea');
    var rechazoCountSpan = document.querySelector('.char-count-rechazo');
    if (rechazoTextarea && rechazoCountSpan) {
        rechazoTextarea.addEventListener('input', function() {
            rechazoCountSpan.textContent = this.value.length;
        });
    }

    // Contador de caracteres para todos los textareas con maxlength
    document.querySelectorAll('textarea[maxlength]').forEach(function(textarea) {
        var parent = textarea.parentElement;
        var countSpan = parent ? parent.querySelector('.char-count') : null;
        if (countSpan) {
            function updateCount() {
                countSpan.textContent = textarea.value.length;
            }
            textarea.addEventListener('input', updateCount);
            updateCount();
        }
    });

    // =========================================================
    // LÓGICA DE BÚSQUEDA EN TIEMPO REAL IDÉNTICA A REGISTRO_PAGOS.PHP
    // =========================================================
    const inputBuscador = document.getElementById('buscador_estudiante');
    const sugerenciasContainer = document.getElementById('sugerencias_estudiantes');
    const spinnerBusqueda = document.getElementById('spinner_busqueda_est');
    const formSeleccion = document.getElementById('form_seleccion_estudiante');
    const inputSelId = document.getElementById('input_sel_id_estudiante');
    const inputSelCedula = document.getElementById('input_sel_cedula');
    let debounceTimer = null;

    function buscarEstudiantes() {
        const termino = inputBuscador.value.trim();
        clearTimeout(debounceTimer);

        if (termino.length < 1) {
            sugerenciasContainer.style.display = 'none';
            sugerenciasContainer.innerHTML = '';
            if (spinnerBusqueda) spinnerBusqueda.style.display = 'none';
            return;
        }

        if (spinnerBusqueda) spinnerBusqueda.style.display = 'flex';

        debounceTimer = setTimeout(() => {
            const fd = new FormData();
            fd.append('ajax_buscar_estudiantes', '1');
            fd.append('termino', termino);

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (spinnerBusqueda) spinnerBusqueda.style.display = 'none';
                    sugerenciasContainer.innerHTML = '';

                    if (data.success && Array.isArray(data.estudiantes) && data.estudiantes.length > 0) {
                        data.estudiantes.forEach(est => {
                            const item = document.createElement('a');
                            item.href = 'javascript:void(0);';
                            item.className = 'list-group-item list-group-item-action p-2 d-flex justify-content-between align-items-center border-bottom text-decoration-none';
                            item.style.cursor = 'pointer';
                            item.innerHTML = `
                                <div>
                                    <strong class="text-dark"><i class="fas fa-user-graduate mr-1 text-primary"></i> ${escapeHtml(est.nombre)}</strong>
                                    <div class="small text-muted">
                                        <span>C.I: ${escapeHtml(est.cedula || est.idusuario)}</span> | 
                                        <span>${escapeHtml(est.nombre_carrera || 'Sin Carrera')}</span>
                                    </div>
                                </div>
                                <span class="btn btn-sm btn-success py-1 px-2"><i class="fas fa-check mr-1"></i> Elegir</span>
                            `;

                            item.addEventListener('click', function(e) {
                                e.preventDefault();
                                if (inputSelId && inputSelCedula && formSeleccion) {
                                    inputSelId.value = est.id;
                                    inputSelCedula.value = est.cedula || est.idusuario;
                                    formSeleccion.submit();
                                }
                            });

                            sugerenciasContainer.appendChild(item);
                        });
                        sugerenciasContainer.style.display = 'block';
                    } else {
                        sugerenciasContainer.innerHTML = `
                            <div class="list-group-item p-3 text-center text-muted">
                                <i class="fas fa-user-slash mr-1"></i> No se encontraron estudiantes activos con "<strong>${escapeHtml(termino)}</strong>".
                            </div>
                        `;
                        sugerenciasContainer.style.display = 'block';
                    }
                })
                .catch(err => {
                    if (spinnerBusqueda) spinnerBusqueda.style.display = 'none';
                    console.error("Error buscando estudiante:", err);
                });
        }, 150);
    }

    if (inputBuscador) {
        inputBuscador.addEventListener('input', buscarEstudiantes);
        inputBuscador.addEventListener('focus', function() {
            if (this.value.trim().length > 0) {
                buscarEstudiantes();
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (inputBuscador && !inputBuscador.contains(e.target) && !sugerenciasContainer.contains(e.target)) {
            sugerenciasContainer.style.display = 'none';
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // =========================================================
    // MODALES: DETALLE, APROBAR, RECHAZAR
    // =========================================================
    document.querySelectorAll('.btn-ver-detalle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var data = JSON.parse(this.getAttribute('data-solicitud'));
            var cuerpo = document.getElementById('detalleModalCuerpo');
            
            var statusBadge = '';
            if (data.status === 'pendiente') {
                statusBadge = '<span class="badge badge-warning font-weight-bold px-2 py-1"><i class="fas fa-clock mr-1"></i> PENDIENTE</span>';
            } else if (data.status === 'aprobada') {
                statusBadge = '<span class="badge badge-success font-weight-bold px-2 py-1"><i class="fas fa-check-circle mr-1"></i> APROBADA</span>';
            } else {
                statusBadge = '<span class="badge badge-danger font-weight-bold px-2 py-1"><i class="fas fa-times-circle mr-1"></i> RECHAZADA</span>';
            }
            
            var html = '<div class="row mb-3">' +
                '<div class="col-md-6">' +
                    '<h6 class="font-weight-bold text-primary mb-1">Información del Estudiante</h6>' +
                    '<div><strong>Nombre:</strong> ' + (data.nombre_estudiante || 'N/A') + '</div>' +
                    '<div><strong>Cédula:</strong> V-' + (data.cedula_estudiante || 'N/A') + '</div>' +
                    '<div><strong>Carrera:</strong> ' + (data.nombre_carrera || 'N/A') + '</div>' +
                '</div>' +
                '<div class="col-md-6 text-md-right">' +
                    '<h6 class="font-weight-bold text-primary mb-1">Datos del Trámite</h6>' +
                    '<div><strong>Trámite N°:</strong> #' + data.id + '</div>' +
                    '<div><strong>Fecha:</strong> ' + data.fecha_solicitud + '</div>' +
                    '<div><strong>Estado:</strong> ' + statusBadge + '</div>' +
                '</div>' +
            '</div><hr>';
            
            if (data.tipo_solicitud === 'adicion_retiro') {
                var rets = (data.materias_parsed && data.materias_parsed.retiros) ? data.materias_parsed.retiros : [];
                var adcs = (data.materias_parsed && data.materias_parsed.adiciones) ? data.materias_parsed.adiciones : [];
                
                html += '<h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-list-alt mr-1"></i> Materias Involucradas:</h6><div class="row">';
                
                // Retiros
                html += '<div class="col-md-6 mb-3"><div class="card border-danger"><div class="card-header bg-danger text-white py-1 font-weight-bold small">MATERIAS A RETIRAR (' + rets.length + ')</div><div class="card-body p-2 small">';
                if (rets.length === 0) {
                    html += '<span class="text-muted">Ninguna materia para retirar.</span>';
                } else {
                    html += '<ul class="mb-0 pl-3">';
                    rets.forEach(function(r) {
                        html += '<li><strong>' + (r.codigo || '') + '</strong>: ' + (r.nombre || '') + ' (Sec: ' + (r.seccion || 'N/A') + ')</li>';
                    });
                    html += '</ul>';
                }
                html += '</div></div></div>';
                
                // Adiciones
                html += '<div class="col-md-6 mb-3"><div class="card border-success"><div class="card-header bg-success text-white py-1 font-weight-bold small">MATERIAS A ADICIONAR (' + adcs.length + ')</div><div class="card-body p-2 small">';
                if (adcs.length === 0) {
                    html += '<span class="text-muted">Ninguna materia para adicionar.</span>';
                } else {
                    html += '<ul class="mb-0 pl-3">';
                    adcs.forEach(function(a) {
                        html += '<li><strong>' + (a.codigo || '') + '</strong>: ' + (a.nombre || '') + '</li>';
                    });
                    html += '</ul>';
                }
                html += '</div></div></div></div>';
            } else if (data.tipo_solicitud === 'cambio_seccion') {
                html += '<div class="alert alert-secondary py-2 mb-3">' +
                    '<div><strong>Sección Actual:</strong> ' + (data.nombre_seccion_origen || 'No especificada') + '</div>' +
                    '<div><strong>Sección Solicitada:</strong> <span class="badge badge-primary font-weight-bold">' + (data.nombre_seccion_destino || 'N/A') + '</span></div>' +
                '</div>';
            } else if (data.tipo_solicitud === 'cambio_carrera') {
                var carrDest = (data.materias_parsed && data.materias_parsed.carrera_destino_nombre) ? data.materias_parsed.carrera_destino_nombre : 'No especificada';
                html += '<div class="alert alert-primary py-2 mb-3">' +
                    '<div><i class="fas fa-random mr-1"></i> <strong>Carrera / PNF Solicitado:</strong> <span class="badge badge-light text-primary font-weight-bold p-1">' + carrDest + '</span></div>' +
                '</div>';
            } else if (data.tipo_solicitud === 'cambio_turno') {
                var turnoDest = (data.materias_parsed && data.materias_parsed.turno_destino) ? data.materias_parsed.turno_destino : 'No especificado';
                html += '<div class="alert alert-info py-2 mb-3">' +
                    '<div><i class="fas fa-clock mr-1"></i> <strong>Turno Solicitado:</strong> <span class="badge badge-light text-dark font-weight-bold p-1">' + turnoDest + '</span></div>' +
                '</div>';
            } else if (data.tipo_solicitud === 'constancia_traslado') {
                var instDest = (data.materias_parsed && data.materias_parsed.institucion_destino) ? data.materias_parsed.institucion_destino : 'No especificada';
                html += '<div class="alert alert-info py-2 mb-3">' +
                    '<div><i class="fas fa-truck-moving mr-1"></i> <strong>Institución Destino:</strong> ' + instDest + '</div>' +
                '</div>';
            } else if (data.tipo_solicitud === 'constancia_reincorporacion') {
                var perReinc = (data.materias_parsed && data.materias_parsed.periodo_reincorporacion) ? data.materias_parsed.periodo_reincorporacion : 'No especificado';
                html += '<div class="alert alert-success py-2 mb-3">' +
                    '<div><i class="fas fa-user-plus mr-1"></i> <strong>Período Solicitado:</strong> ' + perReinc + '</div>' +
                '</div>';
            } else if (data.tipo_solicitud === 'retiro_documento') {
                var docs = (data.materias_parsed && data.materias_parsed.documentos) ? data.materias_parsed.documentos : [];
                html += '<div class="card mb-3 border-secondary"><div class="card-header bg-secondary text-white py-1 font-weight-bold small">DOCUMENTOS SOLICITADOS (' + docs.length + ')</div><div class="card-body p-2 small"><ul class="mb-0 pl-3">';
                docs.forEach(function(d) { html += '<li>' + d + '</li>'; });
                html += '</ul></div></div>';
            } else if (data.tipo_solicitud === 'intensivo' || data.tipo_solicitud === 'evaluacion_extraordinaria') {
                var mats = (data.materias_parsed && data.materias_parsed.materias) ? data.materias_parsed.materias : [];
                html += '<div class="card mb-3 border-warning"><div class="card-header bg-warning text-dark py-1 font-weight-bold small">MATERIAS SOLICITADAS (' + mats.length + ')</div><div class="card-body p-2 small"><ul class="mb-0 pl-3">';
                mats.forEach(function(m) { html += '<li><strong>' + (m.codigo || '') + '</strong>: ' + (m.nombre || '') + '</li>'; });
                html += '</ul></div></div>';
            } else if (data.tipo_solicitud === 'inscripcion_practicas') {
                var inst = (data.materias_parsed && data.materias_parsed.institucion) ? data.materias_parsed.institucion : 'No especificada';
                var area = (data.materias_parsed && data.materias_parsed.area) ? data.materias_parsed.area : 'No especificada';
                html += '<div class="alert alert-success py-2 mb-3">' +
                    '<div><i class="fas fa-building mr-1"></i> <strong>Empresa / Institución:</strong> ' + inst + '</div>' +
                    '<div><i class="fas fa-project-diagram mr-1"></i> <strong>Área / Proyecto:</strong> ' + area + '</div>' +
                '</div>';
            }
            
            html += '<div class="form-group">' +
                '<label class="font-weight-bold text-dark small text-uppercase">Motivo / Justificación del Estudiante:</label>' +
                '<div class="p-3 bg-light border rounded small">' + (data.motivo ? data.motivo.split("\n").join("<br>") : 'No especificado') + '</div>' +
            '</div>';
            
            if (data.status === 'aprobada') {
                html += '<div class="alert alert-success py-2 small">' +
                    '<div><i class="fas fa-check mr-1"></i> <strong>Aprobado por:</strong> ' + (data.nombre_procesado_por || 'Administrador') + ' el ' + (data.fecha_procesado || '') + '</div>' +
                    (data.observacion_admin ? '<div><strong>Observación:</strong> ' + data.observacion_admin + '</div>' : '') +
                '</div>';
            } else if (data.status === 'rechazada') {
                html += '<div class="alert alert-danger py-2 small">' +
                    '<div><i class="fas fa-times mr-1"></i> <strong>Rechazado por:</strong> ' + (data.nombre_procesado_por || 'Administrador') + ' el ' + (data.fecha_procesado || '') + '</div>' +
                    '<div><strong>Motivo del Rechazo:</strong> ' + (data.motivo_rechazo || 'No especificado') + '</div>' +
                '</div>';
            }
            
            cuerpo.innerHTML = html;
            var inputPdf = document.getElementById('modal_detalle_solicitud_id');
            if (inputPdf) {
                inputPdf.value = data.id;
            }
            var btnPdf = document.getElementById('btnModalDetalleVerPdf');
            if (btnPdf) {
                btnPdf.setAttribute('data-solicitud-id', data.id);
                btnPdf.setAttribute('data-titulo', 'COMPROBANTE DE SOLICITUD #' + data.id);
            }
            $('#modalDetalleSolicitudAdmin').modal('show');
        });
    });

    document.querySelectorAll('.btn-aprobar-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-nombre');
            var tipo = this.getAttribute('data-tipo');
            
            document.getElementById('aprobar_solicitud_id').value = id;
            document.getElementById('aprobarTextoConfirmacion').innerHTML = '¿Estás seguro de <strong>APROBAR</strong> la solicitud <strong>#' + id + '</strong> (' + tipo.toUpperCase() + ') del estudiante <strong>' + nombre + '</strong>?';
            
            var divReinc = document.getElementById('divPeriodoReincorporacion');
            if (divReinc) {
                if (tipo === 'constancia_reincorporacion' || tipo === 'reincorporacion') {
                    divReinc.style.display = 'block';
                } else {
                    divReinc.style.display = 'none';
                }
            }

            $('#modalConfirmarAprobacion').modal('show');
        });
    });

    document.querySelectorAll('.btn-rechazar-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-nombre');
            
            document.getElementById('rechazar_solicitud_id').value = id;
            document.getElementById('rechazarTextoConfirmacion').innerHTML = '¿Estás seguro de <strong>RECHAZAR</strong> la solicitud <strong>#' + id + '</strong> del estudiante <strong>' + nombre + '</strong>?';
            
            $('#modalRechazarSolicitud').modal('show');
        });
    });
});
</script>




<!-- ========================================================================= -->
<!-- MODAL VISUALIZADOR DE PDF SIMPLE Y DIRECTO EN LA MISMA PÁGINA -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalVisualizadorPDF" tabindex="-1" role="dialog" aria-labelledby="modalVisualizadorPDFLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90vw; height: 90vh;" role="document">
        <div class="modal-content shadow-lg border-0 h-100" style="border-radius: 8px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-2 px-3 d-flex justify-content-between align-items-center">
                <h5 class="modal-title font-weight-bold text-uppercase mb-0" id="tituloVisualizadorPDF" style="font-size: 0.95rem;">
                    <i class="fas fa-file-pdf text-danger mr-2"></i> DOCUMENTO OFICIAL
                </h5>
                <div>
                    <a id="btnAbrirNuevaPestanaPDF" href="#" target="_blank" class="btn btn-sm btn-outline-light mr-2 font-weight-bold">
                        <i class="fas fa-external-link-alt mr-1"></i> Abrir en ventana
                    </a>
                    <button type="button" class="btn btn-sm btn-danger font-weight-bold px-3" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cerrar
                    </button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary" style="height: calc(90vh - 50px);">
                <iframe id="iframeVisualizadorPDF" src="" class="w-100 h-100 border-0" style="background-color: #525659;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalPDF(url, titulo) {
    titulo = titulo || 'DOCUMENTO OFICIAL';
    var titEl = document.getElementById('tituloVisualizadorPDF');
    var btnExt = document.getElementById('btnAbrirNuevaPestanaPDF');
    var iframe = document.getElementById('iframeVisualizadorPDF');

    if (titEl) titEl.innerHTML = '<i class="fas fa-file-pdf text-danger mr-2"></i> ' + titulo;
    if (btnExt) btnExt.href = url;
    if (iframe) iframe.src = url;

    $('#modalVisualizadorPDF').modal('show');
}

document.addEventListener('DOMContentLoaded', function() {
    // Limpiar iframe al cerrar modal y restaurar modal de detalle si procede
    $('#modalVisualizadorPDF').on('hidden.bs.modal', function () {
        var iframe = document.getElementById('iframeVisualizadorPDF');
        if (iframe) iframe.src = '';
        if (typeof reabrirDetalleAlCerrarPdf !== 'undefined' && reabrirDetalleAlCerrarPdf) {
            reabrirDetalleAlCerrarPdf = false;
            setTimeout(function() {
                $('#modalDetalleSolicitudAdmin').modal('show');
            }, 250);
        }
    });

    // 1. Botones directos de constancias (Inscripción / Estudios)
    document.querySelectorAll('.btn-abrir-pdf-directo').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var tipo = this.getAttribute('data-tipo');
            var id = this.getAttribute('data-id');
            var titulo = this.getAttribute('data-titulo') || 'CONSTANCIA';
            var url = '../constancias/generar_constancia.php?tipo=' + encodeURIComponent(tipo);
            if (id) {
                url += '&id=' + encodeURIComponent(id);
            }
            abrirModalPDF(url, titulo);
        });
    });

    // 2. Botones de la tabla de historial de solicitudes
    document.querySelectorAll('.btn-ver-pdf-solicitud').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var solId = this.getAttribute('data-solicitud-id');
            var titulo = this.getAttribute('data-titulo') || ('COMPROBANTE #' + solId);
            var url = '../constancias/generar_constancia.php?solicitud_id=' + encodeURIComponent(solId);
            abrirModalPDF(url, titulo);
        });
    });

    // 3. Disparador automático si viene de registro reciente
    <?php if (!empty($solicitud_pdf_popup_id) && $solicitud_pdf_popup_id > 0): ?>
        var urlAuto = '../constancias/generar_constancia.php?solicitud_id=<?php echo $solicitud_pdf_popup_id; ?>';
        abrirModalPDF(urlAuto, 'COMPROBANTE DE TRÁMITE #<?php echo $solicitud_pdf_popup_id; ?>');
    <?php endif; ?>
});
</script>

<?php include("includes/footer.php"); ?>
