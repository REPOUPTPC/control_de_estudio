<?php
require_once __DIR__ . '/../funciones/functions.php';

cargarPermisosUsuario();
if (!isLoggedIn() || !isDirectorCarrera()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';

switch($action) {
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
                    <table class="table table-bordered table-horario">
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
                                 '<small class="text-dark d-block">' . htmlspecialchars($clase_encontrada['nombre_docente']) . '</small>' .
                                 '<small class="text-muted d-block">Aula: ' . htmlspecialchars($clase_encontrada['aula']) . '</small>' .
                                 '<button class="btn btn-danger btn-sm btn-eliminar-horario mt-1 shadow-sm" data-id="' . $clase_encontrada['id_horario'] . '">Eliminar</button>' .
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
        $sql = "SELECT s.codigo_seccion, s.turno, t.numero_trayecto, p.nombre_periodo 
                FROM secciones s
                JOIN trayectos t ON s.id_trayecto = t.id_trayecto
                JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
                WHERE s.id_seccion = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $id_seccion);
        $stmt->execute();
        $seccion = $stmt->get_result()->fetch_assoc();
        echo json_encode([
            'success' => true, 
            'codigo_seccion' => $seccion['codigo_seccion'],
            'turno' => $seccion['turno'],
            'numero_trayecto' => $seccion['numero_trayecto'],
            'nombre_periodo' => $seccion['nombre_periodo']
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
        
        echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Horario guardado' : 'Error al guardar']);
        break;
        
    case 'eliminar_horario':
        $id_horario = (int)$_POST['id_horario'];
        $resultado = eliminarHorarioSeccion($id_horario);
        echo json_encode(['success' => $resultado]);
        break;
}
?>