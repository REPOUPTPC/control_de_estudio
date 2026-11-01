<?php
require_once __DIR__ . '/../funciones/functions.php';

cargarPermisosUsuario();
if (!isLoggedIn() || (!isAdmin() && !isSuperUser() && !isDirectorCarrera())) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';

switch($action) {
    case 'get_secciones_carrera':
        $id_carrera = (int)($_POST['id_carrera'] ?? 0);
        if ($id_carrera > 0) {
            $secciones = obtenerSeccionesPorCarrera($db, $id_carrera);
        } else {
            $sql = "SELECT s.id_seccion, s.codigo_seccion, t.numero_trayecto, p.nombre_periodo, c.nombre_carrera 
                    FROM secciones s
                    JOIN trayectos t ON s.id_trayecto = t.id_trayecto
                    JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
                    JOIN carreras c ON s.id_carrera = c.id_carrera
                    WHERE s.estatus = 1
                    ORDER BY c.nombre_carrera ASC, s.codigo_seccion ASC";
            $res = $db->query($sql);
            $secciones = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $secciones[] = $r;
                }
            }
        }
        echo json_encode($secciones);
        break;

    case 'get_horario':
        $id_seccion = (int)$_POST['id_seccion'];
        $horarios = obtenerHorariosSeccion($db, $id_seccion);
        
        $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $horas_tabla = ['07:00', '07:30', '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30'];
        
        $horarios_por_dia = array_fill(0, 6, []);
        foreach ($horarios as $h) {
            $horarios_por_dia[(int)$h['dia']][] = $h;
        }
        
        $html = '<div class="table-responsive">
                    <table class="table table-bordered table-horario text-center align-middle">
                        <thead class="thead-dark">
                            <tr><th width="80">Hora</th>';
        foreach ($dias_semana as $dia) {
            $html .= "<th>$dia</th>";
        }
        $html .= '</tr></thead><tbody>';
        
        $skip_cells = [];
        foreach ($horas_tabla as $hora) {
            $html .= '<tr><th class="bg-light align-middle text-muted small" style="width: 80px;">' . $hora . '</th>';
            for ($dia = 0; $dia <= 5; $dia++) {
                if (isset($skip_cells[$dia][$hora])) {
                    continue;
                }

                $clase_encontrada = null;
                foreach ($horarios_por_dia[$dia] as $clase) {
                    if ($hora >= $clase['hora_inicio'] && $hora < $clase['hora_fin']) {
                        $clase_encontrada = $clase;
                        break;
                    }
                }

                if ($clase_encontrada) {
                    $h_ini_ts = strtotime($hora);
                    $h_fin_ts = strtotime($clase_encontrada['hora_fin']);
                    $rowspan = max(1, (int) round(($h_fin_ts - $h_ini_ts) / 1800));

                    $temp_ts = $h_ini_ts;
                    for ($i = 1; $i < $rowspan; $i++) {
                        $temp_ts += 1800;
                        $skip_cells[$dia][date('H:i', $temp_ts)] = true;
                    }

                    $contenido = '<div class="p-1">' .
                                 '<strong class="text-primary d-block">' . htmlspecialchars($clase_encontrada['nombre_materia']) . '</strong>' .
                                 '<small class="text-dark d-block"><i class="fas fa-chalkboard-teacher mr-1"></i>' . htmlspecialchars($clase_encontrada['nombre_docente']) . '</small>' .
                                 '<small class="text-muted d-block"><i class="fas fa-map-marker-alt mr-1"></i>Aula: ' . htmlspecialchars($clase_encontrada['aula']) . '</small>' .
                                 '<button class="btn btn-danger btn-xs btn-sm py-0 px-2 mt-1 btn-eliminar-horario shadow-sm" data-id="' . $clase_encontrada['id_horario'] . '" title="Eliminar clase"><i class="fas fa-trash-alt"></i></button>' .
                                 '</div>';
                    $html .= '<td class="horario-cell asignado align-middle" rowspan="' . $rowspan . '" data-dia="' . $dia . '" data-hora="' . $hora . '" data-id-horario="' . $clase_encontrada['id_horario'] . '">' . $contenido . '</td>';
                } else {
                    $html .= '<td class="horario-cell celda-vacia" data-dia="' . $dia . '" data-hora="' . $hora . '" data-id-horario=""></td>';
                }
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></div>';
        echo $html;
        break;
        
    case 'get_info_seccion':
        $id_seccion = (int)$_POST['id_seccion'];
        $sql = "SELECT s.codigo_seccion, s.turno, t.numero_trayecto, p.nombre_periodo, c.nombre_carrera 
                FROM secciones s
                JOIN trayectos t ON s.id_trayecto = t.id_trayecto
                JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
                JOIN carreras c ON s.id_carrera = c.id_carrera
                WHERE s.id_seccion = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $id_seccion);
        $stmt->execute();
        $seccion = $stmt->get_result()->fetch_assoc();
        echo json_encode([
            'success' => true, 
            'codigo_seccion' => $seccion['codigo_seccion'] ?? '',
            'turno' => $seccion['turno'] ?? '',
            'numero_trayecto' => $seccion['numero_trayecto'] ?? '',
            'nombre_periodo' => $seccion['nombre_periodo'] ?? '',
            'nombre_carrera' => $seccion['nombre_carrera'] ?? ''
        ]);
        break;
        
    case 'get_materias_seccion':
        $id_seccion = (int)$_POST['id_seccion'];
        $materias = obtenerMateriasPorSeccion($id_seccion);
        echo json_encode($materias);
        break;
        
    case 'get_docentes_materia':
        $id_materia = (int)$_POST['id_materia'];
        $docentes = obtenerDocentesPorMateria($id_materia);
        if (empty($docentes)) {
            $res = $db->query("SELECT id, nombre FROM users WHERE (user_type = 'profesor' OR profesor = 1) AND activo = 1 ORDER BY nombre ASC");
            if ($res) {
                $docentes = $res->fetch_all(MYSQLI_ASSOC);
            }
        }
        echo json_encode($docentes);
        break;
        
    case 'verificar_conflicto':
        $id_seccion = (int)$_POST['id_seccion'];
        $dia = (int)$_POST['dia'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin = $_POST['hora_fin'];
        $aula = $_POST['aula'];
        $id_horario = (int)($_POST['id_horario'] ?? 0);
        
        $conflicto = verificarConflictoHorario($dia, $hora_inicio, $hora_fin, $aula, $id_seccion, $id_horario);
        
        if ($conflicto) {
            $msg = "Conflicto detectado con:<br>";
            foreach ($conflicto as $c) {
                $msg .= "• {$c['docente']} - {$c['nombre_materia']} (Sección {$c['codigo_seccion']})<br>";
            }
            echo json_encode(['conflicto' => true, 'mensaje' => $msg]);
        } else {
            echo json_encode(['conflicto' => false]);
        }
        break;
        
    case 'guardar_horario':
        $id_seccion = (int)$_POST['id_seccion'];
        $id_materia = (int)$_POST['id_materia'];
        $id_docente = (int)$_POST['id_docente'];
        $dia = (int)$_POST['dia'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin = $_POST['hora_fin'];
        $aula = $_POST['aula'];
        $id_horario = (int)($_POST['id_horario_editar'] ?? 0);
        
        $conflicto = verificarConflictoHorario($dia, $hora_inicio, $hora_fin, $aula, $id_seccion, $id_horario);
        if ($conflicto) {
            echo json_encode(['success' => false, 'message' => 'Conflicto de horario detectado']);
            exit;
        }
        
        $id_docente_seccion = obtenerDocenteSeccion($id_seccion, $id_materia, $id_docente);
        
        if (!$id_docente_seccion) {
            echo json_encode(['success' => false, 'message' => 'Error al crear relación docente-sección']);
            exit;
        }
        
        if ($id_horario) {
            eliminarHorarioSeccion($id_horario);
        }
        
        $resultado = guardarHorarioSeccion($id_docente_seccion, $dia, $hora_inicio, $hora_fin, $aula);
        
        echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Horario guardado correctamente' : 'Error al guardar']);
        break;
        
    case 'eliminar_horario':
        $id_horario = (int)$_POST['id_horario'];
        $resultado = eliminarHorarioSeccion($id_horario);
        echo json_encode(['success' => $resultado]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
        break;
}
