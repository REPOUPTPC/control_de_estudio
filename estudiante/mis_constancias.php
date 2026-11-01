<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulopag = "Mis Constancias y Solicitudes";
require_once __DIR__ . '/../funciones/functions.php';

// Verificar autenticación y rol de estudiante
if (!isLoggedIn() || !isEstudiante()) {
    $_SESSION['msg'] = "Debes iniciar sesión como estudiante para acceder";
    header('location: ../login.php');
    exit();
}

// LLAMAR A LA FUNCIÓN DE VISITA
visita();

// Obtener información del estudiante desde la sesión
$estudiante_id = intval($_SESSION['user']['id'] ?? 0);
$estudiante = ($estudiante_id > 0) ? obtenerEstudiantePorId($estudiante_id) : null;

// Validar que se haya obtenido un registro válido con 'id'
if (!$estudiante || !is_array($estudiante) || isset($estudiante['error']) || empty($estudiante['id'])) {
    // Si estamos en Modo Dios o la sesión es virtual, buscar un estudiante real activo para previsualización
    if (!empty($_SESSION['dios_fantasma']) || !empty($_SESSION['dios_autenticado'])) {
        $res_dios_est = $db->query("SELECT * FROM users WHERE estudiante = 1 AND status = 1 ORDER BY id ASC LIMIT 1");
        if ($res_dios_est && $row_de = $res_dios_est->fetch_assoc()) {
            $estudiante_id = intval($row_de['id']);
            $estudiante = $row_de;
        } else {
            $estudiante = null;
        }
    } else {
        $estudiante = null;
    }
}
$carrera = null;
$error = "";
$exito = "";

// Asegurar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id_carrera = 0;
$solicitud_pdf_popup_id = 0;
// Recuperar y limpiar mensajes flash de sesión (Patrón PRG para evitar duplicados al recargar F5)
if (isset($_SESSION['flash_solicitud'])) {
    if (!empty($_SESSION['flash_solicitud']['exito'])) {
        $exito = $_SESSION['flash_solicitud']['exito'];
    }
    if (!empty($_SESSION['flash_solicitud']['error'])) {
        $error = $_SESSION['flash_solicitud']['error'];
    }
    if (!empty($_SESSION['flash_solicitud']['pdf_id'])) {
        $solicitud_pdf_popup_id = intval($_SESSION['flash_solicitud']['pdf_id']);
    }
    unset($_SESSION['flash_solicitud']);
}


if ($estudiante && !empty($estudiante['id'])) {
    // Obtener información de la carrera
    $carrera = obtenerCarreraEstudiante($estudiante['id']);
    $id_carrera = intval($carrera['id_carrera'] ?? 0);
    $periodo_act_global = obtenerPeriodoActivo($db);
    $periodo_activo_nombre = $periodo_act_global['periodo'] ?? 'Período Activo';
    
    // Determinar el trayecto actual del estudiante
    if ($id_carrera > 0) {
        $trayecto_actual = obtenerTrayectoActual($estudiante['id'], $id_carrera);
    } else {
        $trayecto_actual = obtenerTrayectoActualEstudiante($estudiante['id']);
    }

    // Obtener información legible del trayecto
    $infoTrayecto = obtenerInfoTrayecto($trayecto_actual);
    $estudiante['trayecto_n'] = $infoTrayecto['numero_trayecto'];
    $estudiante['trayecto_nombre'] = $infoTrayecto['nombre_trayecto'];

    // Evaluación de aptitud para Intensivo, Evaluación Extraordinaria, Pasantías/Proyecto y Adición/Retiro
    $es_apto_intensivo = esAptoParaIntensivo($estudiante['id']);
    $es_apto_extraordinario = esAptoParaExtraordinario($estudiante['id']);
    $es_apto_pasantias = esAptoParaPasantias($estudiante['id']);
    $eval_adicion_retiro = function_exists('verificarElegibilidadAdicionRetiro') ? verificarElegibilidadAdicionRetiro($estudiante['id']) : ['es_apto' => false, 'carrera_valida' => false, 'lapso_valido' => false, 'motivo' => ''];
    $es_apto_adicion_retiro = !empty($eval_adicion_retiro['es_apto']);
    
    // PROCESAR CREACIÓN DE SOLICITUDES VÍA POST
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_solicitud'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Error de validación de seguridad (CSRF). Por favor recarga la página.";
        } else {
            $action = trim($_POST['action_solicitud']);
            
            // =========================================================
            // MANEJADORES DE TODAS LAS SOLICITUDES ACADÉMICAS
            // =========================================================
            if ($action === 'adicion_retiro') {
                $eval_ar = function_exists('verificarElegibilidadAdicionRetiro') ? verificarElegibilidadAdicionRetiro($estudiante_id) : ['es_apto' => false, 'motivo' => 'Función no disponible'];
                if (!$eval_ar['es_apto']) {
                    $_SESSION['flash_solicitud'] = ['error' => $eval_ar['motivo']];
                    header("Location: mis_constancias.php");
                    exit();
                }

                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $retiros_ids = isset($_POST['retiros']) && is_array($_POST['retiros']) ? array_map('intval', $_POST['retiros']) : [];
                $adiciones_ids = isset($_POST['adiciones']) && is_array($_POST['adiciones']) ? array_map('intval', $_POST['adiciones']) : [];
                
                if (empty($retiros_ids) && empty($adiciones_ids)) {
                    $error = "Debes seleccionar al menos una materia para retirar o adicionar.";
                } elseif (empty($motivo)) {
                    $error = "Debes ingresar el motivo de la solicitud.";
                } else {
                    $retiros_detallados = [];
                    if (!empty($retiros_ids)) {
                        $ids_str = implode(',', $retiros_ids);
                        $res_m = $db->query("SELECT m.id_materia, m.nombre_materia as nombre, m.cod_materia, COALESCE(s.codigo_seccion, 'N/A') as seccion 
                                             FROM estudiante_materias em
                                             INNER JOIN materias m ON em.id_materia = m.id_materia
                                             LEFT JOIN secciones s ON em.id_seccion = s.id_seccion
                                             WHERE em.id_usuario = $estudiante_id AND em.id_materia IN ($ids_str)");
                        if ($res_m) {
                            while ($rm = $res_m->fetch_assoc()) {
                                $retiros_detallados[] = [
                                    'id' => intval($rm['id_materia']),
                                    'nombre' => $rm['nombre'],
                                    'codigo' => $rm['cod_materia'],
                                    'seccion' => $rm['seccion']
                                ];
                            }
                        }
                    }
                    
                    $adiciones_detalladas = [];
                    if (!empty($adiciones_ids)) {
                        $ids_str = implode(',', $adiciones_ids);
                        $res_m = $db->query("SELECT id_materia, nombre_materia as nombre, cod_materia FROM materias WHERE id_materia IN ($ids_str)");
                        if ($res_m) {
                            while ($rm = $res_m->fetch_assoc()) {
                                $adiciones_detalladas[] = [
                                    'id' => intval($rm['id_materia']),
                                    'nombre' => $rm['nombre'],
                                    'codigo' => $rm['cod_materia'],
                                    'seccion' => 'Por asignar'
                                ];
                            }
                        }
                    }
                    
                    $accion_tipo = (!empty($retiros_ids) && !empty($adiciones_ids)) ? 'ambos' : (!empty($retiros_ids) ? 'retiro' : 'adicion');
                    $materias_data = [
                        'retiros' => $retiros_detallados,
                        'adiciones' => $adiciones_detalladas
                    ];
                    
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'adicion_retiro', $accion_tipo, $motivo, $materias_data);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Adición/Retiro registrada exitosamente con el N° #$nueva_sol_id! Tu comprobante PDF ha sido generado.",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Ocurrió un error al registrar la solicitud en el sistema."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'cambio_seccion') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $seccion_dest_id = intval($_POST['seccion_destino_id'] ?? 0);
                
                $seccion_orig_id = 0;
                $res_sec = $db->query("SELECT id_seccion FROM estudiante_seccion WHERE id_usuario = $estudiante_id AND estatus = 'activo' ORDER BY fecha_inscripcion DESC LIMIT 1");
                if ($res_sec && $rs = $res_sec->fetch_assoc()) {
                    $seccion_orig_id = intval($rs['id_seccion']);
                }
                
                if ($seccion_dest_id <= 0) {
                    $error = "Debes seleccionar la sección a la cual deseas solicitar el cambio.";
                } elseif (empty($motivo)) {
                    $error = "Debes indicar la justificación o motivo del cambio de sección.";
                } else {
                    $materias_data = ['materias' => ['Todas las asignaturas del período']];
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'cambio_seccion', 'cambio', $motivo, $materias_data, $seccion_orig_id, $seccion_dest_id);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Cambio de Sección registrada exitosamente con el N° #$nueva_sol_id! Tu comprobante PDF ha sido generado.",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Ocurrió un error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'retiro_semestre') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                if (empty($motivo)) {
                    $error = "Debes ingresar el motivo del retiro de semestre.";
                } else {
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'retiro_semestre', 'retiro_total', $motivo, null);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Retiro de Semestre registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'intensivo') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $materias_sel = isset($_POST['materias']) && is_array($_POST['materias']) ? array_map('intval', $_POST['materias']) : [];
                
                if (empty($materias_sel)) {
                    $error = "Debes seleccionar al menos una materia para cursar en Intensivo.";
                } elseif (empty($motivo)) {
                    $error = "Debes indicar el motivo para la solicitud de curso intensivo.";
                } else {
                    $mats_data = [];
                    $ids_str = implode(',', $materias_sel);
                    $res_m = $db->query("SELECT id_materia, nombre_materia as nombre, cod_materia FROM materias WHERE id_materia IN ($ids_str)");
                    if ($res_m) {
                        while ($rm = $res_m->fetch_assoc()) {
                            $mats_data[] = ['id' => intval($rm['id_materia']), 'nombre' => $rm['nombre'], 'codigo' => $rm['cod_materia']];
                        }
                    }
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'intensivo', 'intensivo', $motivo, ['materias' => $mats_data]);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Curso Intensivo registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud de intensivo."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'evaluacion_extraordinaria') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $materias_sel = isset($_POST['materias']) && is_array($_POST['materias']) ? array_map('intval', $_POST['materias']) : [];
                
                if (empty($materias_sel)) {
                    $error = "Debes seleccionar la materia a presentar por Evaluación Extraordinaria.";
                } elseif (empty($motivo)) {
                    $error = "Debes indicar la justificación para la evaluación extraordinaria.";
                } else {
                    $mats_data = [];
                    $ids_str = implode(',', $materias_sel);
                    $res_m = $db->query("SELECT id_materia, nombre_materia as nombre, cod_materia FROM materias WHERE id_materia IN ($ids_str)");
                    if ($res_m) {
                        while ($rm = $res_m->fetch_assoc()) {
                            $mats_data[] = ['id' => intval($rm['id_materia']), 'nombre' => $rm['nombre'], 'codigo' => $rm['cod_materia']];
                        }
                    }
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'evaluacion_extraordinaria', 'extraordinario', $motivo, ['materias' => $mats_data]);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Evaluación Extraordinaria registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'inscripcion_practicas') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $institucion = mb_substr(trim($_POST['institucion'] ?? ''), 0, 100);
                $area = mb_substr(trim($_POST['area'] ?? ''), 0, 100);
                
                if (empty($institucion)) {
                    $error = "Debes indicar la empresa o institución donde realizarás las prácticas / proyecto.";
                } elseif (empty($motivo)) {
                    $error = "Debes indicar el motivo o justificación de la solicitud.";
                } else {
                    $materias_data = ['institucion' => $institucion, 'area' => $area];
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'inscripcion_practicas', 'pasantias', $motivo, $materias_data);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Pasantías / Proyecto registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'cambio_carrera') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $carrera_dest_id = intval($_POST['carrera_destino_id'] ?? 0);
                
                if ($carrera_dest_id <= 0) {
                    $error = "Debes seleccionar la carrera solicitada.";
                } elseif (empty($motivo)) {
                    $error = "Debes indicar la exposición de motivos del cambio de carrera.";
                } else {
                    $carr_nom = '';
                    $res_c = $db->query("SELECT nombre_carrera FROM carreras WHERE id_carrera = $carrera_dest_id LIMIT 1");
                    if ($res_c && $rc = $res_c->fetch_assoc()) {
                        $carr_nom = $rc['nombre_carrera'];
                    }
                    $materias_data = ['carrera_destino_id' => $carrera_dest_id, 'carrera_destino_nombre' => $carr_nom];
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'cambio_carrera', 'cambio', $motivo, $materias_data);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Cambio de Carrera registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'cambio_turno') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $turno_dest = mb_substr(trim($_POST['turno_destino'] ?? ''), 0, 50);
                
                if (empty($turno_dest)) {
                    $error = "Debes seleccionar el turno al cual deseas cambiar.";
                } elseif (empty($motivo)) {
                    $error = "Debes indicar la justificación o motivo del cambio de turno.";
                } else {
                    $materias_data = ['turno_destino' => $turno_dest];
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'cambio_turno', 'cambio', $motivo, $materias_data);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Cambio de Turno registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'renuncia_cupo') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                if (empty($motivo)) {
                    $error = "Debes indicar el motivo de la renuncia de cupo.";
                } else {
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'renuncia_cupo', 'renuncia', $motivo, null);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Renuncia de Cupo registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'constancia_retiro') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                if (empty($motivo)) {
                    $error = "Debes indicar el motivo del retiro definitivo.";
                } else {
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'constancia_retiro', 'retiro', $motivo, null);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Constancia de Retiro registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'constancia_traslado') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $inst_dest = mb_substr(trim($_POST['institucion_destino'] ?? ''), 0, 100);
                
                if (empty($inst_dest)) {
                    $error = "Debes indicar el nombre de la institución o universidad de destino.";
                } elseif (empty($motivo)) {
                    $error = "Debes indicar el motivo del traslado.";
                } else {
                    $materias_data = ['institucion_destino' => $inst_dest];
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'constancia_traslado', 'traslado', $motivo, $materias_data);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Traslado Universitario registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'constancia_reincorporacion') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $periodo_act = obtenerPeriodoActivo($db);
                $per_reinc = $periodo_act['periodo'] ?? 'Período Activo';
                
                if (empty($motivo)) {
                    $error = "Debes indicar el motivo de la solicitud de reincorporación.";
                } else {
                    $materias_data = ['periodo_reincorporacion' => $per_reinc];
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'constancia_reincorporacion', 'reincorporacion', $motivo, $materias_data);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Reincorporación registrada con el N° #$nueva_sol_id! Recuerde que debe llevar a Control de Estudios: copia de cédula, el comprobante del arancel y dos (2) reportes impresos de esta solicitud de reincorporación.",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                    exit();
                }
            } elseif ($action === 'retiro_documento') {
                $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 100);
                $docs = isset($_POST['documentos']) && is_array($_POST['documentos']) ? array_map('trim', $_POST['documentos']) : [];
                
                if (empty($docs)) {
                    $error = "Debes seleccionar al menos un documento a retirar.";
                } elseif (empty($motivo)) {
                    $error = "Debes indicar el motivo del retiro de documentos.";
                } else {
                    $materias_data = ['documentos' => $docs];
                    $nueva_sol_id = crearSolicitudAcademica($estudiante_id, 'retiro_documento', 'retiro_docs', $motivo, $materias_data);
                    if ($nueva_sol_id) {
                        $_SESSION['flash_solicitud'] = [
                            'exito' => "¡Solicitud de Retiro de Documentos registrada con el N° #$nueva_sol_id!",
                            'pdf_id' => $nueva_sol_id
                        ];
                    } else {
                        $_SESSION['flash_solicitud'] = ['error' => "Error al registrar la solicitud."];
                    }
                    header("Location: mis_constancias.php");
                }
            }
        }
    }

    // Cargar listas de materias y secciones
    $materias_inscritas = obtenerMateriasInscritasParaRetiro($estudiante_id);
    $materias_disponibles = ($id_carrera > 0) ? obtenerMateriasDisponiblesParaAdicion($estudiante_id, $id_carrera, null, $estudiante['trayecto_n']) : [];
    
    $secciones_carrera = [];

    // Carreras disponibles para cambio
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
        // Obtener sección actual para excluirla
        $id_sec_actual = 0;
        $res_sec_act = $db->query("SELECT id_seccion FROM estudiante_seccion WHERE id_usuario = $estudiante_id AND estatus = 'activo' ORDER BY fecha_inscripcion DESC LIMIT 1");
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
    
    // Cargar historial de solicitudes del estudiante
    $mis_solicitudes = obtenerSolicitudesAcademicas('', $estudiante_id);
} else {
    $error = "No se pudo cargar tu información. Por favor, contacta con administración.";
}

include("includes/head.php");
?>

<div class="container-fluid py-3">
    <div class="row">
        <div class="col-12">
            <!-- Encabezado del panel -->
            <div class="dashboard-header p-4 mb-4 text-center">
                <h3 class="font-weight-bold text-uppercase"><i class="fas fa-file-alt mr-3"></i>CONSTANCIAS Y SOLICITUDES</h3>
                <p class="mb-0 text-uppercase">GENERA TUS CONSTANCIAS ACADÉMICAS Y GESTIONA TUS SOLICITUDES</p>
            </div>



            <?php if ($error): ?>
                <div class="alert alert-danger shadow-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($exito): ?>
                <div class="alert alert-success shadow-sm">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $exito; ?>
                </div>
            <?php endif; ?>

            <?php if ($estudiante): ?>
                <div class="row">
                    <!-- Columna izquierda: Información del estudiante -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-user-graduate mr-2"></i> Mi Información</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <i class="fas fa-user-circle" style="font-size: 4rem; color: #4e73df;"></i>
                                </div>
                                <h5 class="font-weight-bold text-center"><?php echo htmlspecialchars($estudiante['nombre']); ?></h5>
                                <hr>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong><i class="fas fa-id-card mr-1"></i> Cédula:</strong></td>
                                        <td><?php echo htmlspecialchars($estudiante['idusuario']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-university mr-1"></i> Carrera:</strong></td>
                                        <td><?php echo htmlspecialchars($carrera['nombre_carrera'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-code mr-1"></i> Código:</strong></td>
                                        <td><?php echo htmlspecialchars($carrera['cod_carrera'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-layer-group mr-1"></i> Ubicación:</strong></td>
                                        <td>
                                            <span class="badge badge-info p-2">
                                                <?php echo htmlspecialchars($estudiante['trayecto_nombre']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-calendar mr-1"></i> Fecha:</strong></td>
                                        <td><?php echo date('d/m/Y'); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer bg-white">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle mr-1"></i> Los documentos generados no tienen validez académica sin firma y sello
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Columna derecha: Opciones de constancias y solicitudes -->
                    <div class="col-md-8 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-file-contract mr-2"></i> CONSTANCIAS Y SOLICITUDES DISPONIBLES</h5>
                                <span class="badge badge-light"><?php echo date('Y'); ?></span>
                            </div>
                            <div class="card-body">
                                <!-- Constancia según ubicación académica -->
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i> 
                                    <strong>Ubicación actual:</strong> <?php echo $estudiante['trayecto_nombre']; ?> - 
                                    Constancia disponible: 
                                    <?php if ($estudiante['trayecto_n'] == 0): ?>
                                        <span class="badge badge-primary">Constancia de Inscripción</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary">Constancia de Estudios</span>
                                    <?php endif; ?>
                                </div>

                                <div class="row">
                                    <!-- Constancia según ubicación -->
                                    <div class="col-lg-6 mb-3">
                                        <div class="card h-100 border-primary">
                                            <div class="card-header bg-primary-light bg-white py-2">
                                                <h6 class="font-weight-bold text-primary mb-0">
                                                    <i class="fas fa-graduation-cap mr-1"></i> 
                                                    <?php if ($estudiante['trayecto_n'] == 0): ?>
                                                        Constancia de Inscripción
                                                    <?php else: ?>
                                                        Constancia de Estudios
                                                    <?php endif; ?>
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="small text-muted mb-2">
                                                    <?php if ($estudiante['trayecto_n'] == 0): ?>
                                                        Acredita tu inscripción en el período académico actual.
                                                    <?php else: ?>
                                                        Certifica tu condición de estudiante regular en la institución.
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-white border-0 pb-3">
                                                <button type="button" class="btn btn-primary btn-block font-weight-bold text-uppercase btn-abrir-pdf-directo" 
            data-tipo="<?php echo $estudiante['trayecto_n'] == 0 ? 'inscripcion' : 'estudios'; ?>"
            data-titulo="<?php echo $estudiante['trayecto_n'] == 0 ? 'CONSTANCIA DE INSCRIPCIÓN' : 'CONSTANCIA DE ESTUDIOS'; ?>">
        <i class="fas fa-file-pdf mr-1"></i> VER Y DESCARGAR PDF
    </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Constancia de Intensivo -->
                                    <div class="col-lg-6 mb-3">
                                        <div class="card h-100 border-warning">
                                            <div class="card-header bg-warning-light bg-white py-2 d-flex justify-content-between align-items-center">
                                                <h6 class="font-weight-bold text-warning mb-0 text-uppercase">
                                                    <i class="fas fa-file-contract mr-1"></i> CONSTANCIA DE INTENSIVO
                                                </h6>
                                                <?php if ($es_apto_intensivo): ?>
                                                    <span class="badge badge-success">APTO</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">NO APTO</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="small text-muted mb-2 text-uppercase">
                                                    PARA CURSAR MATERIAS EN PERÍODO INTENSIVO O VACACIONAL.
                                                </p>
                                                <?php if (!$es_apto_intensivo): ?>
                                                    <div class="alert alert-warning p-2 mb-0 text-uppercase font-weight-bold small">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i> NO CUMPLE REQUISITOS PARA ESTE TRÁMITE.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer bg-white border-0 pb-3">
                                                <?php if ($es_apto_intensivo): ?>
                                                    <button type="button" class="btn btn-warning btn-block font-weight-bold text-uppercase shadow-sm" 
                                                            data-toggle="modal" data-target="#modalSolicitudIntensivo">
                                                        <i class="fas fa-edit mr-1"></i> SOLICITAR Y DESCARGAR PDF
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-warning btn-block font-weight-bold text-uppercase" 
                                                            data-toggle="modal" data-target="#modalNoAptoIntensivo">
                                                        <i class="fas fa-info-circle mr-1"></i> VER REQUISITOS
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Evaluación Extraordinaria -->
                                    <div class="col-lg-6 mb-3">
                                        <div class="card h-100 border-danger">
                                            <div class="card-header bg-danger-light bg-white py-2 d-flex justify-content-between align-items-center">
                                                <h6 class="font-weight-bold text-danger mb-0 text-uppercase">
                                                    <i class="fas fa-redo mr-1"></i> EVALUACIÓN EXTRAORDINARIA
                                                </h6>
                                                <?php if ($es_apto_extraordinario): ?>
                                                    <span class="badge badge-success">APTO</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">NO APTO</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="small text-muted mb-2 text-uppercase">
                                                    SOLICITUD PARA PRESENTAR EVALUACIÓN EXTRAORDINARIA Y/O SUFICIENCIA.
                                                </p>
                                                <?php if (!$es_apto_extraordinario): ?>
                                                    <div class="alert alert-danger p-2 mb-0 text-uppercase font-weight-bold small">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i> NO CUMPLE REQUISITOS PARA ESTE TRÁMITE.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer bg-white border-0 pb-3">
                                                <?php if ($es_apto_extraordinario): ?>
                                                    <button type="button" class="btn btn-danger btn-block font-weight-bold text-uppercase shadow-sm" 
                                                            data-toggle="modal" data-target="#modalSolicitudExtraordinario">
                                                        <i class="fas fa-edit mr-1"></i> SOLICITAR Y DESCARGAR PDF
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-danger btn-block font-weight-bold text-uppercase" 
                                                            data-toggle="modal" data-target="#modalNoAptoExtraordinario">
                                                        <i class="fas fa-info-circle mr-1"></i> VER REQUISITOS
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Adición/Retiro de Materias (CON MODAL Y REGISTRO AUTOMATIZADO) -->
                                    <div class="col-lg-6 mb-3">
                                        <div class="card h-100 border-info">
                                            <div class="card-header bg-info-light bg-white py-2 d-flex justify-content-between align-items-center">
                                                <h6 class="font-weight-bold text-info mb-0 text-uppercase">
                                                    <i class="fas fa-exchange-alt mr-1"></i> ADICIÓN / RETIRO
                                                </h6>
                                                <?php if ($es_apto_adicion_retiro): ?>
                                                    <span class="badge badge-success">DISPONIBLE (<?= $eval_adicion_retiro['dias_restantes'] ?> DÍAS)</span>
                                                <?php elseif (!empty($eval_adicion_retiro['carrera_valida'])): ?>
                                                    <span class="badge badge-warning text-dark">LAPSO CERRADO</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">NO APLICA</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="small text-muted mb-2 text-uppercase">
                                                    SOLICITUD INTERACTIVA PARA ADICIONAR O RETIRAR MATERIAS DEL PERÍODO.
                                                </p>
                                                <?php if (!$es_apto_adicion_retiro): ?>
                                                    <div class="alert alert-warning p-2 mb-0 text-uppercase font-weight-bold small">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i> 
                                                        <?php echo htmlspecialchars($eval_adicion_retiro['motivo']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer bg-white border-0 pb-3">
                                                <?php if ($es_apto_adicion_retiro): ?>
                                                    <button type="button" class="btn btn-info btn-block font-weight-bold text-uppercase shadow-sm" 
                                                            data-toggle="modal" data-target="#modalSolicitudAdicionRetiro">
                                                        <i class="fas fa-edit mr-1"></i> SOLICITAR Y DESCARGAR PDF
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-info btn-block font-weight-bold text-uppercase" 
                                                            data-toggle="modal" data-target="#modalNoAptoAdicionRetiro">
                                                        <i class="fas fa-info-circle mr-1"></i> VER REQUISITOS
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pasantías/Proyecto -->
                                    <div class="col-lg-6 mb-3">
                                        <div class="card h-100 border-success">
                                            <div class="card-header bg-success-light bg-white py-2 d-flex justify-content-between align-items-center">
                                                <h6 class="font-weight-bold text-success mb-0 text-uppercase">
                                                    <i class="fas fa-briefcase mr-1"></i> PASANTÍAS / PROYECTO
                                                </h6>
                                                <?php if ($es_apto_pasantias): ?>
                                                    <span class="badge badge-success">APTO</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">NO APTO</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="small text-muted mb-2 text-uppercase">
                                                    INSCRIPCIÓN EN PASANTÍAS O PROYECTO SOCIOINTEGRADOR.
                                                </p>
                                                <?php if (!$es_apto_pasantias): ?>
                                                    <div class="alert alert-warning p-2 mb-0 text-uppercase font-weight-bold small">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i> RESERVADO PARA TRAYECTO I O SUPERIOR.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer bg-white border-0 pb-3">
                                                <?php if ($es_apto_pasantias): ?>
                                                    <button type="button" class="btn btn-success btn-block font-weight-bold text-uppercase shadow-sm" 
                                                            data-toggle="modal" data-target="#modalSolicitudPracticas">
                                                        <i class="fas fa-edit mr-1"></i> SOLICITAR Y DESCARGAR PDF
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-success btn-block font-weight-bold text-uppercase" 
                                                            data-toggle="modal" data-target="#modalNoAptoPasantias">
                                                        <i class="fas fa-info-circle mr-1"></i> VER REQUISITOS
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cambio de Sección (CON MODAL Y REGISTRO AUTOMATIZADO) -->
                                    <div class="col-lg-6 mb-3">
                                        <div class="card h-100 border-secondary">
                                            <div class="card-header bg-secondary-light bg-white py-2">
                                                <h6 class="font-weight-bold text-secondary mb-0 text-uppercase">
                                                    <i class="fas fa-sync-alt mr-1"></i> CAMBIO DE SECCIÓN
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="small text-muted mb-2 text-uppercase">
                                                    SOLICITUD INTERACTIVA PARA CAMBIAR DE SECCIÓN EN EL PERÍODO.
                                                </p>
                                            </div>
                                            <div class="card-footer bg-white border-0 pb-3">
                                                <button type="button" class="btn btn-secondary btn-block font-weight-bold text-uppercase shadow-sm" 
                                                        data-toggle="modal" data-target="#modalSolicitudCambioSeccion">
                                                    <i class="fas fa-edit mr-1"></i> SOLICITAR Y DESCARGAR PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Retiro de Semestre (CON MODAL Y REGISTRO AUTOMATIZADO) -->
                                    <div class="col-lg-6 mb-3">
                                        <div class="card h-100 border-dark">
                                            <div class="card-header bg-dark-light bg-white py-2">
                                                <h6 class="font-weight-bold text-dark mb-0 text-uppercase">
                                                    <i class="fas fa-calendar-times mr-1"></i> RETIRO DE SEMESTRE
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="small text-muted mb-2 text-uppercase">
                                                    SOLICITUD DE RETIRO TOTAL DEL SEMESTRE ACADÉMICO.
                                                </p>
                                            </div>
                                            <div class="card-footer bg-white border-0 pb-3">
                                                <button type="button" class="btn btn-dark btn-block font-weight-bold text-uppercase shadow-sm" 
                                                        data-toggle="modal" data-target="#modalSolicitudRetiroSemestre">
                                                    <i class="fas fa-edit mr-1"></i> SOLICITAR Y DESCARGAR PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Más opciones en acordeón -->
                                <div class="mt-4">
                                    <div class="card">
                                        <div class="card-header bg-light py-2" id="headingMasOpciones">
                                            <h6 class="mb-0">
                                                <button class="btn btn-link btn-block text-left collapsed font-weight-bold text-uppercase" type="button" data-toggle="collapse" data-target="#collapseMasOpciones" aria-expanded="false" aria-controls="collapseMasOpciones">
                                                    <i class="fas fa-chevron-circle-down mr-1"></i> OTRAS SOLICITUDES DISPONIBLES
                                                </button>
                                            </h6>
                                        </div>
                                        <div id="collapseMasOpciones" class="collapse" aria-labelledby="headingMasOpciones">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-sm-6 mb-2">
                                                        <button type="button" class="btn btn-outline-primary btn-block font-weight-bold text-uppercase" 
                                                                data-toggle="modal" data-target="#modalSolicitudCambioCarrera">
                                                            <i class="fas fa-random mr-1"></i> CAMBIO DE CARRERA
                                                        </button>
                                                    </div>
                                                    <div class="col-sm-6 mb-2">
                                                        <button type="button" class="btn btn-outline-primary btn-block font-weight-bold text-uppercase" 
                                                                data-toggle="modal" data-target="#modalSolicitudCambioTurno">
                                                            <i class="fas fa-clock mr-1"></i> CAMBIO DE TURNO
                                                        </button>
                                                    </div>
                                                    <div class="col-sm-6 mb-2">
                                                        <button type="button" class="btn btn-outline-danger btn-block font-weight-bold text-uppercase" 
                                                                data-toggle="modal" data-target="#modalSolicitudRenunciaCupo">
                                                            <i class="fas fa-user-times mr-1"></i> RENUNCIA DE CUPO
                                                        </button>
                                                    </div>
                                                    <div class="col-sm-6 mb-2">
                                                        <button type="button" class="btn btn-outline-dark btn-block font-weight-bold text-uppercase" 
                                                                data-toggle="modal" data-target="#modalSolicitudConstanciaRetiro">
                                                            <i class="fas fa-file-export mr-1"></i> CONSTANCIA DE RETIRO
                                                        </button>
                                                    </div>
                                                    <div class="col-sm-6 mb-2">
                                                        <button type="button" class="btn btn-outline-info btn-block font-weight-bold text-uppercase" 
                                                                data-toggle="modal" data-target="#modalSolicitudConstanciaTraslado">
                                                            <i class="fas fa-truck-moving mr-1"></i> TRASLADO UNIVERSITARIO
                                                        </button>
                                                    </div>
                                                    <div class="col-sm-6 mb-2">
                                                        <button type="button" class="btn btn-outline-success btn-block font-weight-bold text-uppercase" 
                                                                data-toggle="modal" data-target="#modalSolicitudConstanciaReincorporacion">
                                                            <i class="fas fa-user-plus mr-1"></i> REINCORPORACIÓN
                                                        </button>
                                                    </div>
                                                    <div class="col-sm-6 mb-2">
                                                        <button type="button" class="btn btn-outline-secondary btn-block font-weight-bold text-uppercase" 
                                                                data-toggle="modal" data-target="#modalSolicitudRetiroDocumento">
                                                            <i class="fas fa-file-download mr-1"></i> RETIRO DE DOCUMENTOS
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN COMPLETA DE HISTORIAL DE SOLICITUDES ACADÉMICAS -->
                <div class="row mt-4" id="historialSolicitudes">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 font-weight-bold"><i class="fas fa-history mr-2"></i> MI HISTORIAL DE SOLICITUDES Y TRÁMITES</h5>
                                <span class="badge badge-info"><?php echo count($mis_solicitudes); ?> Solicitud(es)</span>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($mis_solicitudes)): ?>
                                    <div class="text-center p-5 text-muted">
                                        <i class="fas fa-folder-open mb-3" style="font-size: 3rem;"></i>
                                        <p class="mb-0 font-weight-bold">Aún no has registrado ninguna solicitud académica en el sistema.</p>
                                        <small>Cuando realices una solicitud de adición, retiro o cambio, podrás consultar su estado y descargar su comprobante aquí.</small>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped mb-0 text-center">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>N° Trámite</th>
                                                    <th>Tipo de Solicitud</th>
                                                    <th>Fecha Solicitud</th>
                                                    <th>Detalle / Materias</th>
                                                    <th>Estado</th>
                                                    <th>Respuesta Administración</th>
                                                    <th>Comprobante PDF</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mis_solicitudes as $sol): ?>
                                                    <tr>
                                                        <td class="font-weight-bold">#<?php echo $sol['id']; ?></td>
                                                        <td>
                                                            <?php 
                                                            $badge_map = [
                                                                'adicion_retiro' => '<span class="badge badge-info"><i class="fas fa-exchange-alt mr-1"></i> Adición / Retiro</span>',
                                                                'cambio_seccion' => '<span class="badge badge-secondary"><i class="fas fa-sync-alt mr-1"></i> Cambio de Sección</span>',
                                                                'retiro_semestre' => '<span class="badge badge-dark"><i class="fas fa-calendar-times mr-1"></i> Retiro de Semestre</span>',
                                                                'intensivo' => '<span class="badge badge-warning"><i class="fas fa-sun mr-1"></i> Intensivo</span>',
                                                                'evaluacion_extraordinaria' => '<span class="badge badge-danger"><i class="fas fa-redo mr-1"></i> Extradinario</span>'
                                                            ];
                                                            echo $badge_map[$sol['tipo_solicitud']] ?? '<span class="badge badge-primary">' . htmlspecialchars(strtoupper($sol['tipo_solicitud'])) . '</span>';
                                                            ?>
                                                        </td>
                                                        <td class="small"><?php echo date('d/m/Y h:i A', strtotime($sol['fecha_solicitud'])); ?></td>
                                                        <td class="text-left small" style="max-width: 250px;">
                                                            <?php if ($sol['tipo_solicitud'] === 'adicion_retiro'): ?>
                                                                <?php 
                                                                $rets = $sol['materias_parsed']['retiros'] ?? [];
                                                                $adcs = $sol['materias_parsed']['adiciones'] ?? [];
                                                                ?>
                                                                <?php if (!empty($rets)): ?>
                                                                    <div class="text-danger font-weight-bold"><i class="fas fa-minus-circle mr-1"></i> Retirar:</div>
                                                                    <ul class="mb-1 pl-3 small">
                                                                        <?php foreach ($rets as $r): ?>
                                                                            <li><?php echo htmlspecialchars($r['codigo'] . ' - ' . $r['nombre']); ?></li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                <?php endif; ?>
                                                                <?php if (!empty($adcs)): ?>
                                                                    <div class="text-success font-weight-bold"><i class="fas fa-plus-circle mr-1"></i> Adicionar:</div>
                                                                    <ul class="mb-0 pl-3 small">
                                                                        <?php foreach ($adcs as $a): ?>
                                                                            <li><?php echo htmlspecialchars($a['codigo'] . ' - ' . $a['nombre']); ?></li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                <?php endif; ?>
                                                            <?php elseif ($sol['tipo_solicitud'] === 'cambio_seccion'): ?>
                                                                <div><strong>Sección Destino:</strong> <?php echo htmlspecialchars($sol['nombre_seccion_destino']); ?></div>
                                                            <?php else: ?>
                                                                <span class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($sol['motivo'], 0, 50, '...')); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($sol['status'] === 'pendiente'): ?>
                                                                <span class="badge badge-warning p-2"><i class="fas fa-clock mr-1"></i> PENDIENTE</span>
                                                            <?php elseif ($sol['status'] === 'aprobada'): ?>
                                                                <span class="badge badge-success p-2"><i class="fas fa-check-circle mr-1"></i> APROBADA</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-danger p-2"><i class="fas fa-times-circle mr-1"></i> RECHAZADA</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="small text-left">
                                                            <?php if ($sol['status'] === 'aprobada'): ?>
                                                                <div class="text-success font-weight-bold"><i class="fas fa-check mr-1"></i> Aprobado por: <?php echo htmlspecialchars($sol['nombre_procesado_por'] ?? 'Control de Estudios'); ?></div>
                                                                <?php if (!empty($sol['observacion_admin'])): ?>
                                                                    <div class="text-muted"><?php echo htmlspecialchars($sol['observacion_admin']); ?></div>
                                                                <?php endif; ?>
                                                            <?php elseif ($sol['status'] === 'rechazada'): ?>
                                                                <div class="text-danger font-weight-bold"><i class="fas fa-ban mr-1"></i> Motivo: <?php echo htmlspecialchars($sol['motivo_rechazo'] ?? 'No especificado'); ?></div>
                                                            <?php else: ?>
                                                                <span class="text-muted"><i class="fas fa-hourglass-half mr-1"></i> En revisión por Control de Estudios</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold btn-ver-pdf-solicitud" 
                                                                    data-solicitud-id="<?php echo $sol['id']; ?>"
                                                                    data-titulo="COMPROBANTE DE SOLICITUD #<?php echo $sol['id']; ?>"
                                                                    title="Ver Comprobante PDF en Modal">
                                                                <i class="fas fa-file-pdf mr-1"></i> Ver PDF
                                                            </button>
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



<!-- ========================================================================= -->
<!-- MODALES INTERACTIVOS DE SOLICITUD (UBICADOS EN NIVEL RAÍZ DEL BODY) -->
<!-- ========================================================================= -->

                            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 1. MODAL ADICIÓN Y RETIRO DE MATERIAS -->
                <div class="modal fade" id="modalSolicitudAdicionRetiro" tabindex="-1" role="dialog" aria-labelledby="modalSolicitudAdicionRetiroLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content shadow border-info">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="adicion_retiro">
                                
                                <div class="modal-header bg-info text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase" id="modalSolicitudAdicionRetiroLabel">
                                        <i class="fas fa-exchange-alt mr-2"></i> SOLICITUD DE ADICIÓN Y RETIRO DE MATERIAS
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="alert alert-info py-2 small">
                                        <i class="fas fa-info-circle mr-1"></i> Selecciona las materias que deseas <strong>retirar</strong> de tu carga actual y/o las materias que deseas <strong>adicionar</strong> a tu período académico. Al enviar, se registrará en el sistema y se generará tu comprobante PDF oficial.
                                    </div>

                                    <div class="row">
                                        <!-- Materias a Retirar -->
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 border-danger">
                                                <div class="card-header bg-danger text-white py-2 font-weight-bold small text-uppercase">
                                                    <i class="fas fa-minus-circle mr-1"></i> 1. Materias a Retirar (Inscritas)
                                                </div>
                                                <div class="card-body p-2" style="max-height: 220px; overflow-y: auto;">
                                                    <?php if (empty($materias_inscritas)): ?>
                                                        <p class="text-muted small p-2 mb-0">No tienes materias inscritas activas en el período actual.</p>
                                                    <?php else: ?>
                                                        <?php foreach ($materias_inscritas as $mi): ?>
                                                            <div class="custom-control custom-checkbox mb-2 p-1 border-bottom">
                                                                <input type="checkbox" class="custom-control-input chk-retiro" 
                                                                       id="ret_<?php echo $mi['id_materia']; ?>" 
                                                                       name="retiros[]" 
                                                                       value="<?php echo $mi['id_materia']; ?>">
                                                                <label class="custom-control-label small font-weight-bold" for="ret_<?php echo $mi['id_materia']; ?>">
                                                                    <?php echo htmlspecialchars($mi['cod_materia'] . ' - ' . $mi['nombre_materia']); ?>
                                                                    <div class="text-muted font-weight-normal small">Sección: <?php echo htmlspecialchars($mi['nombre_seccion']); ?> | Trayecto <?php echo $mi['trayecto']; ?></div>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Materias a Adicionar -->
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 border-success">
                                                <div class="card-header bg-success text-white py-2 font-weight-bold small text-uppercase">
                                                    <i class="fas fa-plus-circle mr-1"></i> 2. Materias a Adicionar (Disponibles)
                                                </div>
                                                <div class="card-body p-2" style="max-height: 220px; overflow-y: auto;">
                                                    <?php if (empty($materias_disponibles)): ?>
                                                        <p class="text-muted small p-2 mb-0">No hay materias adicionales disponibles para tu carrera.</p>
                                                    <?php else: ?>
                                                        <?php foreach ($materias_disponibles as $ma): ?>
                                                            <div class="custom-control custom-checkbox mb-2 p-1 border-bottom">
                                                                <input type="checkbox" class="custom-control-input chk-adicion" 
                                                                       id="adc_<?php echo $ma['id_materia']; ?>" 
                                                                       name="adiciones[]" 
                                                                       value="<?php echo $ma['id_materia']; ?>">
                                                                <label class="custom-control-label small font-weight-bold" for="adc_<?php echo $ma['id_materia']; ?>">
                                                                    <?php echo htmlspecialchars($ma['cod_materia'] . ' - ' . $ma['nombre_materia']); ?>
                                                                    <div class="text-muted font-weight-normal small">Trayecto <?php echo $ma['trayecto']; ?> | <?php echo $ma['uc']; ?> U.C.</div>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Motivo de la solicitud -->
                                    <div class="form-group mt-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">
                                                <i class="fas fa-pen mr-1 text-info"></i> Motivo de la Solicitud <span class="text-danger">*</span>
                                            </label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" maxlength="100" rows="3" 
                                                  placeholder="Indica detalladamente la razón por la cual solicitas la adición o retiro (ej. Cruce de horario con actividad laboral, equivalencia, etc.)..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">
                                        <i class="fas fa-times mr-1"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-info font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar Solicitud y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 2. MODAL CAMBIO DE SECCIÓN -->
                <div class="modal fade" id="modalSolicitudCambioSeccion" tabindex="-1" role="dialog" aria-labelledby="modalSolicitudCambioSeccionLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-secondary">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="cambio_seccion">
                                
                                <div class="modal-header bg-secondary text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase" id="modalSolicitudCambioSeccionLabel">
                                        <i class="fas fa-sync-alt mr-2"></i> SOLICITUD DE CAMBIO DE SECCIÓN
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="alert alert-secondary py-2 small mb-2">
                                        <i class="fas fa-info-circle mr-1"></i> Selecciona la sección a la que deseas solicitar el cambio y especifica tu justificación.
                                    </div>
                                    <div class="alert alert-info py-1 px-2 small mb-3">
                                        <i class="fas fa-info-circle mr-1"></i> Turno: <strong class="text-uppercase"><?php echo htmlspecialchars($turno_est ?? 'Diurno'); ?></strong> | Nivel: <strong class="text-uppercase"><?php echo htmlspecialchars($trayecto_nombre_est ?? 'Nivel Actual'); ?></strong> (Mostrando secciones correspondientes a tu nivel y turno).
                                    </div>

                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Nueva Sección Solicitada <span class="text-danger">*</span></label>
                                        <select class="form-control" name="seccion_destino_id" required>
                                            <option value="">-- Selecciona la Sección Destino --</option>
                                            <?php foreach ($secciones_carrera as $sc): ?>
                                                <option value="<?php echo $sc['id_seccion']; ?>">
                                                    <?php echo htmlspecialchars($sc['nombre_seccion'] . ' (' . ($sc['turno'] ?? 'Diurno') . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Justificación del Cambio <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" maxlength="100" rows="3" 
                                                  placeholder="Explica la razón justificada para el cambio de sección..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">
                                        <i class="fas fa-times mr-1"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-secondary font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar Solicitud y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 3. MODAL RETIRO DE SEMESTRE -->
                <div class="modal fade" id="modalSolicitudRetiroSemestre" tabindex="-1" role="dialog" aria-labelledby="modalSolicitudRetiroSemestreLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-dark">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="retiro_semestre">
                                
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase" id="modalSolicitudRetiroSemestreLabel">
                                        <i class="fas fa-calendar-times mr-2"></i> SOLICITUD DE RETIRO DE SEMESTRE
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="alert alert-warning py-2 small">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> <strong>Atención:</strong> Esta solicitud tramita el retiro de todas las materias inscritas en el período académico actual.
                                    </div>

                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo del Retiro Total <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" maxlength="100" rows="3" 
                                                  placeholder="Ingresa el motivo del retiro de semestre..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">
                                        <i class="fas fa-times mr-1"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-dark font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Confirmar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                

            
                <!-- 4. MODAL CURSO INTENSIVO -->
                <div class="modal fade" id="modalSolicitudIntensivo" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content shadow border-warning">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="intensivo">
                                
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-sun mr-2"></i> SOLICITUD DE CURSO INTENSIVO
                                    </h5>
                                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="alert alert-warning py-2 small font-weight-bold">
                                        <i class="fas fa-info-circle mr-1"></i> Selecciona la(s) asignatura(s) que deseas cursar en período intensivo y especifica tu justificación.
                                    </div>

                                    <label class="font-weight-bold small text-uppercase">Materias Disponibles para Intensivo <span class="text-danger">*</span></label>
                                    <div class="card mb-3 border-warning">
                                        <div class="card-body p-2" style="max-height: 180px; overflow-y: auto;">
                                            <?php if (empty($materias_disponibles)): ?>
                                                <p class="text-muted small p-2 mb-0">No hay materias disponibles para intensivo en este momento.</p>
                                            <?php else: ?>
                                                <?php foreach ($materias_disponibles as $md): ?>
                                                    <div class="custom-control custom-checkbox mb-2 p-1 border-bottom">
                                                        <input type="checkbox" class="custom-control-input" 
                                                               id="int_<?php echo $md['id_materia']; ?>" 
                                                               name="materias[]" 
                                                               value="<?php echo $md['id_materia']; ?>">
                                                        <label class="custom-control-label small font-weight-bold" for="int_<?php echo $md['id_materia']; ?>">
                                                            <?php echo htmlspecialchars($md['cod_materia'] . ' - ' . $md['nombre_materia']); ?>
                                                            <span class="text-muted font-weight-normal">(Trayecto <?php echo $md['trayecto']; ?>)</span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo de la Solicitud <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Indica el motivo para cursar intensivo..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-warning text-dark font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 5. MODAL EVALUACIÓN EXTRAORDINARIA -->
                <div class="modal fade" id="modalSolicitudExtraordinario" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content shadow border-danger">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="evaluacion_extraordinaria">
                                
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-redo mr-2"></i> SOLICITUD DE EVALUACIÓN EXTRAORDINARIA
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="alert alert-danger py-2 small font-weight-bold">
                                        <i class="fas fa-info-circle mr-1"></i> Selecciona la asignatura reprobada o aplazada que deseas presentar por evaluación extraordinaria.
                                    </div>

                                    <label class="font-weight-bold small text-uppercase">Selecciona la Materia <span class="text-danger">*</span></label>
                                    <div class="card mb-3 border-danger">
                                        <div class="card-body p-2" style="max-height: 180px; overflow-y: auto;">
                                            <?php if (empty($materias_disponibles)): ?>
                                                <p class="text-muted small p-2 mb-0">No se encontraron materias registradas para evaluación extraordinaria.</p>
                                            <?php else: ?>
                                                <?php foreach ($materias_disponibles as $me): ?>
                                                    <div class="custom-control custom-checkbox mb-2 p-1 border-bottom">
                                                        <input type="checkbox" class="custom-control-input" 
                                                               id="ext_<?php echo $me['id_materia']; ?>" 
                                                               name="materias[]" 
                                                               value="<?php echo $me['id_materia']; ?>">
                                                        <label class="custom-control-label small font-weight-bold" for="ext_<?php echo $me['id_materia']; ?>">
                                                            <?php echo htmlspecialchars($me['cod_materia'] . ' - ' . $me['nombre_materia']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo / Justificación <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Indica la justificación para la evaluación extraordinaria..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 6. MODAL PASANTÍAS / PROYECTO -->
                <div class="modal fade" id="modalSolicitudPracticas" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-success">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="inscripcion_practicas">
                                
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-briefcase mr-2"></i> SOLICITUD DE PASANTÍAS / PROYECTO
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Empresa / Institución Receptora <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="institucion" maxlength="100" placeholder="Nombre de la empresa o institución..." required>
                                    </div>
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Área o Nombre del Proyecto (Opcional)</label>
                                        <input type="text" class="form-control" name="area" maxlength="100" placeholder="Ej: Dpto. de Sistemas / Infraestructura de Red">
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo / Justificación <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo de la postulación..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-success font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 7. MODAL CAMBIO DE CARRERA -->
                <div class="modal fade" id="modalSolicitudCambioCarrera" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-primary">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="cambio_carrera">
                                
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-random mr-2"></i> SOLICITUD DE CAMBIO DE CARRERA / PNF
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Carrera / PNF Solicitado <span class="text-danger">*</span></label>
                                        <select class="form-control font-weight-bold" name="carrera_destino_id" required>
                                            <option value="">-- Selecciona la Carrera Destino --</option>
                                            <?php foreach ($carreras_disponibles_cambio as $car): ?>
                                                <option value="<?php echo $car['id_carrera']; ?>">
                                                    <?php echo htmlspecialchars($car['nombre_carrera']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Exposición de Motivos <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Explica las razones de tu solicitud de cambio..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 8. MODAL CAMBIO DE TURNO -->
                <div class="modal fade" id="modalSolicitudCambioTurno" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-primary">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="cambio_turno">
                                
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-clock mr-2"></i> SOLICITUD DE CAMBIO DE TURNO
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
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
                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo (Laboral, Médico, etc.) <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Indica el motivo del cambio de turno..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 9. MODAL RENUNCIA DE CUPO -->
                <div class="modal fade" id="modalSolicitudRenunciaCupo" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-danger">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="renuncia_cupo">
                                
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-user-times mr-2"></i> SOLICITUD DE RENUNCIA DE CUPO
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="alert alert-danger small font-weight-bold">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> <strong>Atención:</strong> La renuncia de cupo universitario es voluntaria y desincorporará tu expediente institucional.
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo de la Renuncia <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Indica el motivo de tu renuncia..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Confirmar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 10. MODAL CONSTANCIA DE RETIRO -->
                <div class="modal fade" id="modalSolicitudConstanciaRetiro" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-dark">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="constancia_retiro">
                                
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-file-export mr-2"></i> CONSTANCIA DE RETIRO DEFINITIVO
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo del Retiro Definitivo <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Motivo del retiro definitivo..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-dark font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 11. MODAL CONSTANCIA DE TRASLADO -->
                <div class="modal fade" id="modalSolicitudConstanciaTraslado" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-info">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="constancia_traslado">
                                
                                <div class="modal-header bg-info text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-truck-moving mr-2"></i> SOLICITUD DE TRASLADO UNIVERSITARIO
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold small text-uppercase">Institución o Universidad de Destino <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="institucion_destino" maxlength="100" placeholder="Nombre de la universidad o instituto..." required>
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo del Traslado <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Indica el motivo del traslado institucional..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-info font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 12. MODAL CONSTANCIA DE REINCORPORACIÓN -->
                <div class="modal fade" id="modalSolicitudConstanciaReincorporacion" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-success">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="constancia_reincorporacion">
                                
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-user-plus mr-2"></i> SOLICITUD DE REINCORPORACIÓN
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="alert alert-warning py-2 small mb-3 border-warning">
                                        <div class="font-weight-bold text-dark mb-1">
                                            <i class="fas fa-exclamation-circle text-danger mr-1"></i> <strong>REQUISITOS OBLIGATORIOS A CONSIGNAR EN CONTROL DE ESTUDIOS:</strong>
                                        </div>
                                        <p class="mb-1 text-dark">Para formalizar su reincorporación debe acudir al Departamento de Control de Estudios con:</p>
                                        <ul class="pl-3 mb-0 text-dark">
                                            <li><strong>Copia legible de la Cédula de Identidad</strong>.</li>
                                            <li><strong>Comprobante de pago del arancel correspondiente</strong>.</li>
                                            <li><strong>Dos (2) reportes impresos de esta solicitud de reincorporación</strong> (generados al presionar el botón inferior).</li>
                                        </ul>
                                    </div>
                                    <div class="alert alert-info py-2 small mb-3">
                                        <i class="fas fa-calendar-check mr-1"></i> Período solicitado inicial: <strong class="text-uppercase"><?php echo htmlspecialchars($periodo_activo_nombre ?? 'Período Activo'); ?></strong>. Control de Estudios evaluará sus requisitos académicos y determinará el período, trayecto y sección correspondiente.
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo / Justificación <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Indica el motivo de la solicitud de reincorporación..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-success font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 13. MODAL RETIRO DE DOCUMENTOS -->
                <div class="modal fade" id="modalSolicitudRetiroDocumento" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content shadow border-secondary">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action_solicitud" value="retiro_documento">
                                
                                <div class="modal-header bg-secondary text-white">
                                    <h5 class="modal-title font-weight-bold text-uppercase">
                                        <i class="fas fa-file-download mr-2"></i> SOLICITUD DE RETIRO DE DOCUMENTOS
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <div class="modal-body">
                                    <label class="font-weight-bold small text-uppercase">Documentos Solicitados <span class="text-danger">*</span></label>
                                    <div class="card mb-3 border-secondary">
                                        <div class="card-body p-2">
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="doc_fn" name="documentos[]" value="Fondo Negro de Título">
                                                <label class="custom-control-label small" for="doc_fn">Fondo Negro de Título de Bachiller</label>
                                            </div>
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="doc_nc" name="documentos[]" value="Notas Certificadas de Bachillerato">
                                                <label class="custom-control-label small" for="doc_nc">Notas Certificadas de Bachillerato</label>
                                            </div>
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="doc_pn" name="documentos[]" value="Partida de Nacimiento">
                                                <label class="custom-control-label small" for="doc_pn">Partida de Nacimiento (Original/Copia)</label>
                                            </div>
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="doc_cc" name="documentos[]" value="Copia de Cédula de Identidad">
                                                <label class="custom-control-label small" for="doc_cc">Copia de Cédula de Identidad</label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="doc_ot" name="documentos[]" value="Otros Documentos">
                                                <label class="custom-control-label small" for="doc_ot">Otros Documentos del Expediente</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold small text-uppercase mb-0">Motivo del Retiro de Documentos <span class="text-danger">*</span></label>
                                            <small class="text-muted font-weight-bold"><span class="char-count">0</span>/100 caracteres</small>
                                        </div>
                                        <textarea class="form-control" name="motivo" rows="2" maxlength="100" placeholder="Indica el motivo..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary font-weight-bold text-uppercase" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-secondary font-weight-bold text-uppercase px-4 shadow-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Enviar y Descargar PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

<!-- Modal para Estudiante No Apto para Intensivo -->
            <div class="modal fade" id="modalNoAptoIntensivo" tabindex="-1" role="dialog" aria-labelledby="modalNoAptoIntensivoLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-danger shadow">
                        <div class="modal-header bg-danger text-white justify-content-center position-relative">
                            <h5 class="modal-title font-weight-bold text-uppercase text-center" id="modalNoAptoIntensivoLabel">
                                <i class="fas fa-exclamation-circle mr-2"></i> TRÁMITE NO PERMITIDO
                            </h5>
                            <button type="button" class="close text-white position-absolute" style="right: 15px; top: 15px;" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-user-slash text-danger" style="font-size: 3.5rem;"></i>
                            </div>
                            <h5 class="font-weight-bold text-danger text-uppercase mb-3 text-center">NO TE ENCUENTRAS APTO PARA INTENSIVO</h5>
                            <div class="alert alert-warning text-uppercase small font-weight-bold text-center mb-3">
                                <i class="fas fa-info-circle mr-1"></i>
                                ESTIMADO(A) <strong><?php echo htmlspecialchars(mb_strtoupper($estudiante['nombre'], 'UTF-8')); ?></strong> (CÉDULA: <?php echo htmlspecialchars($estudiante['idusuario']); ?>), USTED NO CUMPLE CON LOS REQUISITOS ACADÉMICOS NECESARIOS O SU ESTATUS ACTUAL NO PERMITE PROCESAR UNA CONSTANCIA DE CURSO INTENSIVO EN ESTE PERÍODO.
                            </div>
                            <p class="text-muted text-uppercase small mb-0 font-weight-bold text-center">
                                SI CONSIDERAS QUE ESTO ES UN ERROR O NECESITAS ORIENTACIÓN SOBRE TU EXPEDIENTE ACADÉMICO, POR FAVOR ACUDE A LA OFICINA DE <strong>CONTROL DE ESTUDIOS</strong>.
                            </p>
                        </div>
                        <div class="modal-footer bg-light justify-content-center">
                            <button type="button" class="btn btn-secondary font-weight-bold text-uppercase px-4 shadow-sm" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i> ENTENDIDO
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Estudiante No Apto para Evaluación Extraordinaria -->
            <div class="modal fade" id="modalNoAptoExtraordinario" tabindex="-1" role="dialog" aria-labelledby="modalNoAptoExtraordinarioLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-danger shadow">
                        <div class="modal-header bg-danger text-white justify-content-center position-relative">
                            <h5 class="modal-title font-weight-bold text-uppercase text-center" id="modalNoAptoExtraordinarioLabel">
                                <i class="fas fa-exclamation-triangle mr-2"></i> TRÁMITE NO PERMITIDO
                            </h5>
                            <button type="button" class="close text-white position-absolute" style="right: 15px; top: 15px;" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-file-excel text-danger" style="font-size: 3.5rem;"></i>
                            </div>
                            <h5 class="font-weight-bold text-danger text-uppercase mb-3 text-center">NO APTO PARA EVALUACIÓN EXTRAORDINARIA</h5>
                            <div class="alert alert-danger text-uppercase small font-weight-bold text-center mb-3">
                                <i class="fas fa-info-circle mr-1"></i>
                                ESTIMADO(A) <strong><?php echo htmlspecialchars(mb_strtoupper($estudiante['nombre'], 'UTF-8')); ?></strong> (CÉDULA: <?php echo htmlspecialchars($estudiante['idusuario']); ?>), USTED NO CUENTA CON MATERIAS REPROBADAS NI REGISTRO DE ASIGNATURAS APLAZADAS EN SU EXPEDIENTE QUE REQUIERAN O CALIFIQUEN PARA PRESENTAR UNA EVALUACIÓN EXTRAORDINARIA.
                            </div>
                            <p class="text-muted text-uppercase small mb-0 font-weight-bold text-center">
                                SI CONSIDERAS QUE EXISTE ALGUNA INCONSISTENCIA EN TUS NOTAS, POR FAVOR ACUDE A LA OFICINA DE <strong>CONTROL DE ESTUDIOS</strong> PARA REVISAR TU HISTORIAL ACADÉMICO.
                            </p>
                        </div>
                        <div class="modal-footer bg-light justify-content-center">
                            <button type="button" class="btn btn-secondary font-weight-bold text-uppercase px-4 shadow-sm" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i> ENTENDIDO
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Estudiante No Apto para Pasantías/Proyecto -->
            <div class="modal fade" id="modalNoAptoPasantias" tabindex="-1" role="dialog" aria-labelledby="modalNoAptoPasantiasLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-warning shadow">
                        <div class="modal-header bg-warning text-dark justify-content-center position-relative">
                            <h5 class="modal-title font-weight-bold text-uppercase text-center" id="modalNoAptoPasantiasLabel">
                                <i class="fas fa-exclamation-circle mr-2"></i> TRÁMITE NO DISPONIBLE
                            </h5>
                            <button type="button" class="close text-dark position-absolute" style="right: 15px; top: 15px;" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-briefcase text-warning" style="font-size: 3.5rem;"></i>
                            </div>
                            <h5 class="font-weight-bold text-dark text-uppercase mb-3 text-center">RESERVADO PARA TRAYECTO I O SUPERIOR</h5>
                            <div class="alert alert-warning text-uppercase small font-weight-bold text-center mb-3">
                                <i class="fas fa-info-circle mr-1"></i>
                                ESTIMADO(A) <strong><?php echo htmlspecialchars(mb_strtoupper($estudiante['nombre'], 'UTF-8')); ?></strong> (CÉDULA: <?php echo htmlspecialchars($estudiante['idusuario']); ?>), LA INSCRIPCIÓN EN PASANTÍAS Y PROYECTO SOCIOINTEGRADOR ESTÁ DESTINADA A ESTUDIANTES QUE SE ENCUENTRAN CURSANDO TRAYECTO I O SUPERIOR DEL PNF. SU UBICACIÓN ACTUAL ES <strong><?php echo htmlspecialchars(mb_strtoupper($estudiante['trayecto_nombre'], 'UTF-8')); ?></strong>.
                            </div>
                            <p class="text-muted text-uppercase small mb-0 font-weight-bold text-center">
                                UNA VEZ CULMINADO Y APROBADO EL TRAYECTO INICIAL (TRAYECTO 0), PODRÁS SOLICITAR TU CONSTANCIA DE INSCRIPCIÓN EN PASANTÍAS / PROYECTO.
                            </p>
                        </div>
                        <div class="modal-footer bg-light justify-content-center">
                            <button type="button" class="btn btn-secondary font-weight-bold text-uppercase px-4 shadow-sm" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i> ENTENDIDO
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Estudiante No Apto para Adición y Retiro -->
            <div class="modal fade" id="modalNoAptoAdicionRetiro" tabindex="-1" role="dialog" aria-labelledby="modalNoAptoAdicionRetiroLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-info shadow">
                        <div class="modal-header bg-info text-white justify-content-center position-relative">
                            <h5 class="modal-title font-weight-bold text-uppercase text-center" id="modalNoAptoAdicionRetiroLabel">
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
                                <?php echo htmlspecialchars($eval_adicion_retiro['motivo'] ?? 'Trámite no disponible.'); ?>
                            </div>
                            <div class="text-muted text-left small mb-0 font-weight-bold">
                                <p class="mb-1"><i class="fas fa-check-circle text-success mr-1"></i> <strong>Carreras Autorizadas:</strong> Únicamente Mecánica Automotriz y Mecánica Térmica.</p>
                                <p class="mb-1"><i class="fas fa-clock text-primary mr-1"></i> <strong>Lapso Reglamentario:</strong> Exclusivamente durante las 2 primeras semanas de cada período académico.</p>
                                <p class="mb-0"><i class="fas fa-university text-secondary mr-1"></i> Para solicitudes excepcionales o casos especiales, debe dirigirse a la Dirección de Carrera o Control de Estudios.</p>
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



<style>
    .dashboard-header {
        background: linear-gradient(120deg, #4e73df 0%, #224abe 100%);
        color: white;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    .container-fluid .card {
        border-radius: 10px;
        transition: transform 0.2s;
    }
    .container-fluid .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1) !important;
    }
    .btn-block {
        border-radius: 50px;
    }
    .bg-primary-light { background-color: #eef2ff; }
    .bg-success-light { background-color: #e6fff0; }
    .bg-warning-light { background-color: #fff4e6; }
    .bg-danger-light { background-color: #ffe6e6; }
    .bg-info-light { background-color: #e6f3ff; }
    .bg-secondary-light { background-color: #f2f2f2; }
    .bg-dark-light { background-color: #e9ecef; }
    
    /* MODALES: TOTALMENTE AISLADOS Y POR ENCIMA DE HEAD Y FOOTER */
    .modal-backdrop {
        z-index: 1070 !important;
    }
    .modal {
        z-index: 1080 !important;
        padding-top: 25px !important;
        padding-bottom: 75px !important; /* Margen para no chocar con footer fixed */
        overflow-y: auto !important;
    }
    .modal-dialog {
        margin: 1.25rem auto !important;
        max-width: 95%;
    }
    @media (min-width: 576px) {
        .modal-dialog { max-width: 580px; }
        .modal-dialog.modal-lg { max-width: 850px; }
        .modal-dialog.modal-xl { max-width: 92vw; }
    }
    .modal-content {
        border-radius: 12px !important;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.35) !important;
        max-height: calc(100vh - 120px) !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }
    .modal-body {
        max-height: calc(100vh - 230px) !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    
    @media (max-width: 768px) {
        .col-md-4, .col-md-8 {
            padding: 0 15px;
        }
        .modal {
            padding-top: 15px !important;
            padding-bottom: 70px !important;
        }
    }
</style>



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
    // Limpiar iframe al cerrar modal
    $('#modalVisualizadorPDF').on('hidden.bs.modal', function () {
        var iframe = document.getElementById('iframeVisualizadorPDF');
        if (iframe) iframe.src = '';
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

    // 4. Apertura directa desde enlaces (ej. desde Mi Historial de Notas)
    <?php if (isset($_GET['solicitud']) && $_GET['solicitud'] === 'adicion_retiro'): ?>
        <?php if (!empty($es_apto_adicion_retiro)): ?>
            <?php if (isset($_GET['retirar_materia']) && intval($_GET['retirar_materia']) > 0): ?>
                var matId = '<?= intval($_GET['retirar_materia']) ?>';
                var chkRet = document.getElementById('ret_' + matId);
                if (chkRet) chkRet.checked = true;
            <?php endif; ?>
            $('#modalSolicitudAdicionRetiro').modal('show');
        <?php else: ?>
            $('#modalNoAptoAdicionRetiro').modal('show');
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php include("includes/footer.php"); ?>