<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulopag = "Asignación de Secciones a Docentes - Director";
include('../funciones/functions.php');

// CARGAR PERMISOS
cargarPermisosUsuario();

if (!isLoggedIn()) {
    header('location: ../login.php');
    exit();
}
if (!isDirectorCarrera()) {
    $_SESSION['error'] = "No tienes permisos de Director de Carrera para acceder a este módulo.";
    header('location: ../profile_selector.php');
    exit();
}

verificarPermiso('asig_secciones');

// LLAMAR A LA FUNCIÓN DE VISITA
visita();

// Obtener ID del usuario director
$id_usuario_director = $_SESSION['user']['id'] ?? 0;

// Obtener la carrera del director desde la tabla users (campo carrera_di)
$query_carrera = "SELECT carrera_di as id_carrera, 
                         (SELECT nombre_carrera FROM carreras WHERE id_carrera = users.carrera_di) as nombre_carrera
                  FROM users 
                  WHERE id = $id_usuario_director AND usuario = 1";
$result_carrera = $db->query($query_carrera);
$carrera_director = $result_carrera->fetch_assoc();

if (!$carrera_director || !$carrera_director['id_carrera']) {
    if (isAdmin()) {
        $id_carrera_director = intval($_SESSION['user']['carrera_di'] ?? $_SESSION['admin_selected_carrera_di'] ?? 1);
        $cQuery = $db->query("SELECT nombre_carrera FROM carreras WHERE id_carrera = $id_carrera_director");
        $cRow = $cQuery ? $cQuery->fetch_assoc() : null;
        $carrera_director = ['id_carrera' => $id_carrera_director, 'nombre_carrera' => ($cRow['nombre_carrera'] ?? 'Carrera')];
    } else {
        $_SESSION['msg'] = "No tiene una carrera asignada como director";
        header('Location: index.php');
        exit();
    }
}

$id_carrera_director = $carrera_director['id_carrera'];

// Variables para mensajes
$tipo_mensaje = '';
$texto_mensaje = '';

// Manejar petición AJAX para obtener materias del docente POR CARRERA
if(isset($_GET['ajax']) && $_GET['ajax'] == 'materias_docente_carrera' && isset($_GET['id_docente']) && isset($_GET['id_carrera'])) {
    header('Content-Type: application/json');
    $id_docente = $db->real_escape_string($_GET['id_docente']);
    $id_carrera = $db->real_escape_string($_GET['id_carrera']);
    
    $materias = obtenerMateriasDocentePorCarrera($id_docente, $id_carrera);
    echo json_encode($materias);
    exit();
}

// Procesar formulario
if(isset($_POST['asignar'])) {
    $id_usuario = $db->real_escape_string($_POST['id_usuario']);
    $id_seccion = $db->real_escape_string($_POST['id_seccion']);
    $id_materia = $db->real_escape_string($_POST['id_materia']);
    
    // Verificar que la sección pertenezca a la carrera del director
    $query_seccion = "SELECT id_carrera FROM secciones WHERE id_seccion = $id_seccion";
    $result_seccion = $db->query($query_seccion);
    $seccion = $result_seccion->fetch_assoc();
    
    if ($seccion['id_carrera'] != $id_carrera_director) {
        $tipo_mensaje = 'error';
        $texto_mensaje = 'No tiene permisos para asignar secciones de esta carrera';
    } else {
        $resultado = procesarAsignacionSeccion($id_usuario, $id_seccion, $id_materia);
        
        if($resultado['success']) {
            $tipo_mensaje = 'success';
            $texto_mensaje = $resultado['message'];
        } else {
            $tipo_mensaje = 'error';
            $texto_mensaje = $resultado['message'];
        }
    }
}

// Eliminar asignación
if(isset($_GET['eliminar'])) {
    $id = $db->real_escape_string($_GET['eliminar']);
    
    // Verificar que la asignación pertenezca a la carrera del director
    $query_check = "SELECT ds.id_docente_seccion, s.id_carrera 
                    FROM docente_seccion ds
                    INNER JOIN secciones s ON ds.id_seccion = s.id_seccion
                    WHERE ds.id_docente_seccion = $id";
    $result_check = $db->query($query_check);
    $asignacion = $result_check->fetch_assoc();
    
    if ($asignacion['id_carrera'] != $id_carrera_director) {
        $tipo_mensaje = 'error';
        $texto_mensaje = 'No tiene permisos para eliminar esta asignación';
    } else {
        $resultado = eliminarAsignacionSeccion($id);
        
        if($resultado['success']) {
            $tipo_mensaje = 'success';
            $texto_mensaje = $resultado['message'];
        } else {
            $tipo_mensaje = 'error';
            $texto_mensaje = $resultado['message'];
        }
    }
}

// Actualizar asignación existente
if(isset($_POST['actualizar_asignacion_seccion'])) {
    $id_docente_seccion = $db->real_escape_string($_POST['id_docente_seccion_editar']);
    $nueva_seccion = $db->real_escape_string($_POST['id_seccion_editar']);
    $nueva_materia = $db->real_escape_string($_POST['id_materia_editar']);
    
    // Verificar que la asignación pertenezca a la carrera del director
    $query_check_e = "SELECT ds.id_docente_seccion, s.id_carrera 
                      FROM docente_seccion ds
                      INNER JOIN secciones s ON ds.id_seccion = s.id_seccion
                      WHERE ds.id_docente_seccion = $id_docente_seccion";
    $result_check_e = $db->query($query_check_e);
    if ($result_check_e && $result_check_e->num_rows > 0) {
        $row_check_e = $result_check_e->fetch_assoc();
        if ($row_check_e['id_carrera'] != $id_carrera_director) {
            $tipo_mensaje = 'error';
            $texto_mensaje = mb_strtoupper('NO TIENE PERMISOS PARA EDITAR ESTA ASIGNACIÓN DE SECCIÓN', 'UTF-8');
        } else {
            $q_update = "UPDATE docente_seccion 
                         SET id_seccion = '$nueva_seccion', id_materia = '$nueva_materia' 
                         WHERE id_docente_seccion = '$id_docente_seccion'";
            if($db->query($q_update)) {
                $tipo_mensaje = 'success';
                $texto_mensaje = mb_strtoupper('ASIGNACIÓN DE SECCIÓN ACTUALIZADA CORRECTAMENTE', 'UTF-8');
            } else {
                $tipo_mensaje = 'error';
                $texto_mensaje = mb_strtoupper('ERROR AL ACTUALIZAR LA ASIGNACIÓN DE SECCIÓN EN EL SERVIDOR', 'UTF-8');
            }
        }
    }
}

include("includes/head.php");
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mt-4"><?php echo $titulopag; ?></h1>
            
            <!-- Información de la carrera del director -->
            <div class="alert alert-info mb-4">
                <i class="fas fa-building mr-2"></i>
                <strong>Carrera asignada:</strong> <?php echo htmlspecialchars($carrera_director['nombre_carrera']); ?>
                <span class="badge badge-primary ml-2">DIRECTOR</span>
            </div>
            
            <!-- Modal de Éxito -->
            <div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="successModalLabel">¡Operación Exitosa!</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-check-circle fa-3x text-success"></i>
                            </div>
                            <p id="successMessage" class="lead"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-success" data-dismiss="modal">Aceptar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal de Error -->
            <div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="errorModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="errorModalLabel">Error en la Operación</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-exclamation-circle fa-3x text-danger"></i>
                            </div>
                            <p id="errorMessage" class="lead"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal de Confirmación para Eliminar -->
            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Eliminación</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-3">
                                <i class="fas fa-trash-alt fa-2x text-danger"></i>
                            </div>
                            <p class="text-center">¿Está seguro de eliminar esta asignación?</p>
                            <p class="text-center text-muted"><small>Esta acción no se puede deshacer.</small></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <a id="confirmDeleteButton" href="#" class="btn btn-danger">
                                <i class="fas fa-trash-alt mr-1"></i> Eliminar
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Editar Asignación de Sección -->
            <div class="modal fade" id="modalEditar" tabindex="-1" role="dialog" aria-labelledby="modalEditarLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title font-weight-bold" id="modalEditarLabel">
                                <i class="fas fa-edit mr-1"></i> EDITAR ASIGNACIÓN DE SECCIÓN
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="post" action="">
                            <div class="modal-body">
                                <input type="hidden" name="id_docente_seccion_editar" id="id_docente_seccion_editar">
                                <div class="form-group">
                                    <label><i class="fas fa-user-tie mr-1"></i> DOCENTE:</label>
                                    <input type="text" class="form-control text-uppercase font-weight-bold" id="docente_editar_nombre" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="id_seccion_editar"><i class="fas fa-door-open mr-1"></i> NUEVA SECCIÓN:</label>
                                    <select class="form-control text-uppercase" id="id_seccion_editar" name="id_seccion_editar" required onchange="cargarMateriasEditarModal()">
                                        <option value="">SELECCIONE UNA SECCIÓN</option>
                                        <?php
                                        $query_secciones_modal = "SELECT s.id_seccion, s.codigo_seccion, s.id_carrera, c.nombre_carrera
                                                            FROM secciones s
                                                            INNER JOIN carreras c ON s.id_carrera = c.id_carrera
                                                            WHERE s.id_carrera = $id_carrera_director AND s.estatus = 'Activa'
                                                            ORDER BY s.codigo_seccion";
                                        $result_secciones_modal = $db->query($query_secciones_modal);
                                        if($result_secciones_modal && $result_secciones_modal->num_rows > 0) {
                                            while($sec_m = $result_secciones_modal->fetch_assoc()) {
                                                echo "<option value='".$sec_m['id_seccion']."' data-carrera='".$sec_m['id_carrera']."'>".htmlspecialchars(mb_strtoupper($sec_m['codigo_seccion'].' - '.$sec_m['nombre_carrera'], 'UTF-8'))."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="id_materia_editar"><i class="fas fa-book mr-1"></i> NUEVA MATERIA:</label>
                                    <select class="form-control text-uppercase" id="id_materia_editar" name="id_materia_editar" required>
                                        <option value="">SELECCIONE UNA MATERIA</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                    <i class="fas fa-times mr-1"></i> CANCELAR
                                </button>
                                <button type="submit" name="actualizar_asignacion_seccion" class="btn btn-primary font-weight-bold">
                                    <i class="fas fa-save mr-1"></i> GUARDAR CAMBIOS
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Mostrar mensaje en toast -->
            <?php if($tipo_mensaje): ?>
            <div id="toastMessage" data-type="<?php echo $tipo_mensaje; ?>" data-message="<?php echo htmlspecialchars($texto_mensaje); ?>"></div>
            <?php endif; ?>

            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-success text-white font-weight-bold">
                    <i class="fas fa-user-plus mr-1"></i>
                    ASIGNAR NUEVA SECCIÓN A DOCENTE
                </div>
                <div class="card-body">
                    <form method="post" action="" id="asignarForm">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="id_usuario" class="font-weight-bold"><i class="fas fa-user-tie mr-1"></i> DOCENTE:</label>
                                <input type="text" id="buscar_docente_input" class="form-control form-control-sm mb-1 text-uppercase" placeholder="🔍 BUSCAR DOCENTE POR NOMBRE O CÉDULA..." autocomplete="off">
                                <select class="form-control text-uppercase" id="id_usuario" name="id_usuario" required onchange="cargarMateriasPorCarrera()">
                                    <option value="">SELECCIONE UN DOCENTE</option>
                                    <?php
                                    $query_docentes = "SELECT id, nombre, idusuario, carrera 
                                                       FROM users 
                                                       WHERE docente = 1 AND status = 1
                                                       ORDER BY nombre";
                                    $result_docentes = $db->query($query_docentes);
                                    if($result_docentes->num_rows > 0) {
                                        while($docente = $result_docentes->fetch_assoc()) {
                                            $nombre_carrera = '';
                                            if($docente['carrera']) {
                                                $query_carrera_doc = "SELECT nombre_carrera FROM carreras WHERE id_carrera = " . $docente['carrera'];
                                                $result_carrera_doc = $db->query($query_carrera_doc);
                                                if($result_carrera_doc->num_rows > 0) {
                                                    $carrera_doc = $result_carrera_doc->fetch_assoc();
                                                    $nombre_carrera = ' - ' . mb_strtoupper($carrera_doc['nombre_carrera'], 'UTF-8');
                                                }
                                            }
                                            $searchMeta = htmlspecialchars(mb_strtolower($docente['nombre'] . ' ' . $docente['idusuario'], 'UTF-8'));
                                            $nombre_fmt = htmlspecialchars(mb_strtoupper($docente['nombre'], 'UTF-8'));
                                            $cedula_fmt = htmlspecialchars($docente['idusuario']);
                                            echo "<option value='".$docente['id']."' data-search='".$searchMeta."'>".$nombre_fmt." (".$cedula_fmt.")" . $nombre_carrera . "</option>";
                                        }
                                    } else {
                                        echo "<option value=''>NO HAY DOCENTES DISPONIBLES</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="id_seccion" class="font-weight-bold"><i class="fas fa-door-open mr-1"></i> SECCIÓN:</label>
                                <select class="form-control text-uppercase" id="id_seccion" name="id_seccion" required onchange="cargarMateriasPorCarrera()">
                                    <option value="">SELECCIONE UNA SECCIÓN</option>
                                    <?php
                                    $query_secciones = "SELECT s.id_seccion, s.codigo_seccion, s.id_carrera, c.nombre_carrera
                                                        FROM secciones s
                                                        INNER JOIN carreras c ON s.id_carrera = c.id_carrera
                                                        WHERE s.id_carrera = $id_carrera_director AND s.estatus = 'Activa'
                                                        ORDER BY s.codigo_seccion";
                                    $result_secciones = $db->query($query_secciones);
                                    if($result_secciones->num_rows > 0) {
                                        while($seccion = $result_secciones->fetch_assoc()) {
                                            echo "<option value='".$seccion['id_seccion']."' data-carrera='".$seccion['id_carrera']."'>".mb_strtoupper($seccion['codigo_seccion']." - ".$seccion['nombre_carrera'], 'UTF-8')."</option>";
                                        }
                                    } else {
                                        echo "<option value=''>NO HAY SECCIONES ACTIVAS DISPONIBLES PARA SU CARRERA</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="id_materia" class="font-weight-bold"><i class="fas fa-book mr-1"></i> MATERIA:</label>
                                <select class="form-control text-uppercase" id="id_materia" name="id_materia" required disabled>
                                    <option value="">PRIMERO SELECCIONE DOCENTE Y SECCIÓN</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="asignar" class="btn btn-success font-weight-bold px-4">
                            <i class="fas fa-save mr-1"></i> ASIGNAR SECCIÓN
                        </button>
                        <button type="reset" class="btn btn-secondary font-weight-bold">
                            <i class="fas fa-undo mr-1"></i> LIMPIAR
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white font-weight-bold">
                    <i class="fas fa-table mr-1"></i>
                    ASIGNACIONES ACTUALES
                </div>
                <div class="card-body">
                    <!-- DIAGNÓSTICO VISIBLE -->
                    <?php
                    // Contar total de registros en docente_seccion
                    $query_total = "SELECT COUNT(*) as total FROM docente_seccion WHERE estatus = 1";
                    $result_total = $db->query($query_total);
                    $total_registros = $result_total->fetch_assoc();
                    
                    // Contar registros de la carrera del director
                    $query_total_carrera = "SELECT COUNT(*) as total 
                                            FROM docente_seccion ds
                                            INNER JOIN secciones s ON ds.id_seccion = s.id_seccion
                                            WHERE ds.estatus = 1 AND s.id_carrera = $id_carrera_director";
                    $result_total_carrera = $db->query($query_total_carrera);
                    $total_carrera = $result_total_carrera->fetch_assoc();
                    ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>DIAGNÓSTICO:</strong> 
                        TOTAL ASIGNACIONES EN SISTEMA: <strong><?php echo $total_registros['total']; ?></strong> | 
                        ASIGNACIONES DE SU CARRERA: <strong><?php echo $total_carrera['total']; ?></strong>
                    </div>

                    <!-- Buscador en tiempo real arriba de la tabla -->
                    <div class="form-group mb-3">
                        <label for="buscar_tabla_asignaciones" class="font-weight-bold text-primary"><i class="fas fa-search mr-1"></i> BUSCAR DOCENTE, SECCIÓN O MATERIA EN TIEMPO REAL:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-filter"></i></span>
                            </div>
                            <input type="text" id="buscar_tabla_asignaciones" class="form-control text-uppercase" placeholder="ESCRIBA PARA BUSCAR DOCENTE (CÉDULA O NOMBRE), SECCIÓN O MATERIA..." autocomplete="off">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="btn_limpiar_tabla">
                                    <i class="fas fa-times"></i> LIMPIAR
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted font-weight-bold" id="tabla_coincidencias_info">FILTRADO EN TIEMPO REAL ACTIVADO.</small>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover text-uppercase" id="dataTable" width="100%" cellspacing="0">
                            <thead class="thead-dark">
                                <tr>
                                    <th>DOCENTE</th>
                                    <th>SECCIÓN</th>
                                    <th>CARRERA</th>
                                    <th>MATERIA</th>
                                    <th>FECHA ASIGNACIÓN</th>
                                    <th>ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query_asignaciones = "SELECT 
                                    ds.id_docente_seccion,
                                    ds.id_usuario,
                                    ds.id_seccion,
                                    ds.id_materia,
                                    ds.fecha_asignacion,
                                    ds.estatus,
                                    u.nombre as docente,
                                    u.idusuario as cedula_docente,
                                    s.codigo_seccion,
                                    s.id_carrera,
                                    c.nombre_carrera,
                                    m.nombre_materia,
                                    m.cod_materia
                                FROM docente_seccion ds
                                INNER JOIN users u ON ds.id_usuario = u.id
                                INNER JOIN secciones s ON ds.id_seccion = s.id_seccion
                                INNER JOIN carreras c ON s.id_carrera = c.id_carrera
                                INNER JOIN materias m ON ds.id_materia = m.id_materia
                                WHERE ds.estatus = 1 
                                AND s.id_carrera = $id_carrera_director
                                ORDER BY c.nombre_carrera, s.codigo_seccion, u.nombre";

                                $result_asignaciones = $db->query($query_asignaciones);
                                
                                if($result_asignaciones && $result_asignaciones->num_rows > 0) {
                                    while($row = $result_asignaciones->fetch_assoc()) {
                                        $docente_upper = htmlspecialchars(mb_strtoupper($row['docente'], 'UTF-8'));
                                        $cedula_upper = htmlspecialchars($row['cedula_docente']);
                                        $seccion_upper = htmlspecialchars(mb_strtoupper($row['codigo_seccion'], 'UTF-8'));
                                        $carrera_upper = htmlspecialchars(mb_strtoupper($row['nombre_carrera'], 'UTF-8'));
                                        $materia_upper = htmlspecialchars(mb_strtoupper($row['nombre_materia'], 'UTF-8'));
                                        $cod_mat_upper = htmlspecialchars(mb_strtoupper($row['cod_materia'], 'UTF-8'));

                                        echo "<tr>
                                                <td>
                                                    <strong>".$docente_upper."</strong><br>
                                                    <small class='text-muted'>CÉDULA: ".$cedula_upper."</small>
                                                </td>
                                                <td><span class='badge badge-primary'>".$seccion_upper."</span></td>
                                                <td>".$carrera_upper."</td>
                                                <td>
                                                    <strong>".$materia_upper."</strong><br>
                                                    <small class='text-muted'>CÓDIGO: ".$cod_mat_upper."</small>
                                                </td>
                                                <td>".date('d/m/Y H:i', strtotime($row['fecha_asignacion']))."</td>
                                                <td class='text-nowrap'>
                                                    <button class='btn btn-sm btn-primary editar-asignacion mr-1' 
                                                            data-toggle='modal' 
                                                            data-target='#modalEditar'
                                                            data-id='".$row['id_docente_seccion']."'
                                                            data-docente='".$docente_upper."'
                                                            data-cedula='".$cedula_upper."'
                                                            data-id-docente='".$row['id_usuario']."'
                                                            data-id-seccion='".$row['id_seccion']."'
                                                            data-id-materia='".$row['id_materia']."'>
                                                        <i class='fas fa-edit mr-1'></i> CAMBIAR
                                                    </button>
                                                    <button class='btn btn-sm btn-danger eliminar-asignacion' 
                                                            data-toggle='modal' 
                                                            data-target='#confirmDeleteModal'
                                                            data-id='".$row['id_docente_seccion']."'
                                                            data-docente='".$docente_upper."'
                                                            data-seccion='".$seccion_upper."'>
                                                        <i class='fas fa-trash-alt mr-1'></i> ELIMINAR
                                                    </button>
                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo '<tr>
                                            <td colspan="6" class="text-center">
                                                <div class="alert alert-warning mb-0">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <strong>NO HAY ASIGNACIONES REGISTRADAS PARA SU CARRERA</strong><br>
                                                    <small class="text-muted">
                                                        TOTAL EN SISTEMA: '.htmlspecialchars($total_registros['total']).' | 
                                                        DE SU CARRERA: '.htmlspecialchars($total_carrera['total']).'
                                                    </small>
                                                </div>
                                            </td>
                                          </tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function cargarMateriasPorCarrera() {
    var idDocente = document.getElementById('id_usuario').value;
    var selectSeccion = document.getElementById('id_seccion');
    var selectMaterias = document.getElementById('id_materia');
    
    var idCarrera = selectSeccion.options[selectSeccion.selectedIndex]?.getAttribute('data-carrera') || '0';
    
    if(idDocente === '' || selectSeccion.value === '') {
        selectMaterias.innerHTML = '<option value="">PRIMERO SELECCIONE DOCENTE Y SECCIÓN</option>';
        selectMaterias.disabled = true;
        return;
    }
    
    selectMaterias.innerHTML = '<option value="">CARGANDO MATERIAS...</option>';
    selectMaterias.disabled = true;
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?ajax=materias_docente_carrera&id_docente=' + idDocente + '&id_carrera=' + idCarrera, true);
    
    xhr.onload = function() {
        if(this.status == 200) {
            try {
                var materias = JSON.parse(this.responseText);
                
                if(materias.length > 0) {
                    selectMaterias.innerHTML = '';
                    materias.forEach(function(materia) {
                        var option = document.createElement('option');
                        option.value = materia.id_materia;
                        option.textContent = (materia.nombre_materia + ' (' + materia.cod_materia + ')').toUpperCase();
                        selectMaterias.appendChild(option);
                    });
                    selectMaterias.disabled = false;
                } else {
                    selectMaterias.innerHTML = '<option value="">ESTE DOCENTE NO TIENE MATERIAS PARA ESTA CARRERA</option>';
                    selectMaterias.disabled = true;
                    showToast('warning', 'EL DOCENTE NO TIENE MATERIAS ASIGNADAS PARA ESTA CARRERA');
                }
            } catch(e) {
                selectMaterias.innerHTML = '<option value="">ERROR AL PROCESAR MATERIAS</option>';
                selectMaterias.disabled = true;
                showToast('error', 'ERROR AL CARGAR LAS MATERIAS');
            }
        } else {
            selectMaterias.innerHTML = '<option value="">ERROR AL CARGAR MATERIAS</option>';
            selectMaterias.disabled = true;
            showToast('error', 'ERROR DE CONEXIÓN AL SERVIDOR');
        }
    };
    
    xhr.onerror = function() {
        selectMaterias.innerHTML = '<option value="">ERROR DE CONEXIÓN</option>';
        selectMaterias.disabled = true;
        showToast('error', 'ERROR DE CONEXIÓN CON EL SERVIDOR');
    };
    
    xhr.send();
}

function cargarMateriasEditarModal(idMateriaSeleccionar) {
    var idDocente = $('#docente_editar_nombre').attr('data-id-docente');
    var selectSeccion = document.getElementById('id_seccion_editar');
    var selectMaterias = document.getElementById('id_materia_editar');
    
    if(!selectSeccion) return;
    var idCarrera = selectSeccion.options[selectSeccion.selectedIndex]?.getAttribute('data-carrera') || '0';
    
    if(!idDocente || !selectSeccion.value) {
        selectMaterias.innerHTML = '<option value="">PRIMERO SELECCIONE SECCIÓN</option>';
        selectMaterias.disabled = true;
        return;
    }
    
    selectMaterias.innerHTML = '<option value="">CARGANDO MATERIAS...</option>';
    selectMaterias.disabled = true;
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?ajax=materias_docente_carrera&id_docente=' + idDocente + '&id_carrera=' + idCarrera, true);
    
    xhr.onload = function() {
        if(this.status == 200) {
            try {
                var materias = JSON.parse(this.responseText);
                if(materias.length > 0) {
                    selectMaterias.innerHTML = '';
                    materias.forEach(function(materia) {
                        var option = document.createElement('option');
                        option.value = materia.id_materia;
                        option.textContent = (materia.nombre_materia + ' (' + materia.cod_materia + ')').toUpperCase();
                        if(idMateriaSeleccionar && materia.id_materia == idMateriaSeleccionar) {
                            option.selected = true;
                        }
                        selectMaterias.appendChild(option);
                    });
                    selectMaterias.disabled = false;
                } else {
                    selectMaterias.innerHTML = '<option value="">ESTE DOCENTE NO TIENE MATERIAS PARA ESTA SECCIÓN</option>';
                    selectMaterias.disabled = true;
                }
            } catch(e) {
                selectMaterias.innerHTML = '<option value="">ERROR AL PROCESAR MATERIAS</option>';
                selectMaterias.disabled = true;
            }
        }
    };
    xhr.send();
}

function showToast(type, message) {
    var alertClass = type === 'success' ? 'alert-success' : 
                     type === 'error' ? 'alert-danger' : 
                     type === 'warning' ? 'alert-warning' : 'alert-info';
    
    var toast = document.createElement('div');
    toast.className = 'alert ' + alertClass + ' alert-dismissible fade show font-weight-bold';
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.innerHTML = `
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <strong>${type === 'success' ? 'ÉXITO!' : type === 'error' ? 'ERROR!' : 'ADVERTENCIA!'}</strong> ${message.toUpperCase()}
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(function() {
        $(toast).alert('close');
    }, 5000);
}

$(document).ready(function() {
    // BUSCADOR EN TIEMPO REAL PARA SELECTOR DE DOCENTE (POR NOMBRE O CÉDULA)
    const inputBuscarDocente = document.getElementById('buscar_docente_input');
    if (inputBuscarDocente) {
        inputBuscarDocente.addEventListener('input', function() {
            const val = this.value.toLowerCase().trim();
            const select = document.getElementById('id_usuario');
            const options = select.querySelectorAll('option');
            let visibleCount = 0;
            let lastVisibleOption = null;

            options.forEach(opt => {
                if (opt.value === '') {
                    opt.hidden = false;
                    return;
                }
                const searchMeta = opt.getAttribute('data-search') || opt.textContent.toLowerCase();
                if (searchMeta.includes(val)) {
                    opt.hidden = false;
                    visibleCount++;
                    lastVisibleOption = opt;
                } else {
                    opt.hidden = true;
                }
            });

            if (visibleCount === 1 && lastVisibleOption) {
                select.value = lastVisibleOption.value;
                cargarMateriasPorCarrera();
            }
        });
    }

    // BUSCADOR EN TIEMPO REAL DE LA TABLA DE ASIGNACIONES
    const inputBuscarTabla = document.getElementById('buscar_tabla_asignaciones');
    const btnLimpiarTabla = document.getElementById('btn_limpiar_tabla');
    const tablaInfo = document.getElementById('tabla_coincidencias_info');

    if (inputBuscarTabla) {
        function filtrarTabla() {
            const filter = inputBuscarTabla.value.toLowerCase().trim();
            const filas = document.querySelectorAll('#dataTable tbody tr');
            let visibles = 0;

            filas.forEach(fila => {
                const texto = fila.textContent.toLowerCase();
                if (texto.includes(filter)) {
                    fila.style.display = '';
                    visibles++;
                } else {
                    fila.style.display = 'none';
                }
            });

            if (tablaInfo) {
                if (filter === '') {
                    tablaInfo.textContent = 'FILTRADO EN TIEMPO REAL ACTIVADO.';
                } else {
                    tablaInfo.textContent = 'MOSTRANDO ' + visibles + ' COINCIDENCIA(S) DE ' + filas.length + ' ASIGNACIONES.';
                }
            }
        }

        inputBuscarTabla.addEventListener('input', filtrarTabla);
        if (btnLimpiarTabla) {
            btnLimpiarTabla.addEventListener('click', function() {
                inputBuscarTabla.value = '';
                filtrarTabla();
            });
        }
    }

    // MODAL EDITAR ASIGNACIÓN DE SECCIÓN
    $(document).on('click', '.editar-asignacion', function() {
        var id = $(this).data('id');
        var docente = $(this).data('docente');
        var cedula = $(this).data('cedula');
        var idDocente = $(this).data('id-docente');
        var idSeccion = $(this).data('id-seccion');
        var idMateria = $(this).data('id-materia');
        
        $('#id_docente_seccion_editar').val(id);
        $('#docente_editar_nombre').val(docente + ' (CÉDULA: ' + cedula + ')').attr('data-id-docente', idDocente);
        $('#id_seccion_editar').val(idSeccion);
        
        cargarMateriasEditarModal(idMateria);
    });

    // MODAL ELIMINAR ASIGNACIÓN DE SECCIÓN
    $(document).on('click', '.eliminar-asignacion', function() {
        var id = $(this).data('id');
        var docente = $(this).data('docente');
        var seccion = $(this).data('seccion');
        
        $('#confirmDeleteButton').attr('href', '?eliminar=' + id);
        
        $('#confirmDeleteModal .modal-body p:first').html(
            '¿ESTÁ SEGURO DE ELIMINAR LA ASIGNACIÓN DE <strong>' + docente + '</strong> A LA SECCIÓN <strong>' + seccion + '</strong>?'
        );
    });
    
    var toastMessage = $('#toastMessage');
    if(toastMessage.length) {
        var type = toastMessage.data('type');
        var message = toastMessage.data('message');
        
        if(type === 'success') {
            $('#successMessage').text(message);
            $('#successModal').modal('show');
        } else if(type === 'error') {
            $('#errorMessage').text(message);
            $('#errorModal').modal('show');
        }
        
        setTimeout(function() {
            toastMessage.remove();
        }, 100);
    }
    
    $('#asignarForm').on('submit', function(e) {
        var idMateria = $('#id_materia');
        if(idMateria.is(':disabled') || idMateria.val() === '') {
            e.preventDefault();
            showToast('error', 'POR FAVOR SELECCIONE UNA MATERIA VÁLIDA');
            return false;
        }
        return true;
    });
    
    $('#asignarForm button[type="reset"]').on('click', function() {
        $('#id_materia').html('<option value="">PRIMERO SELECCIONE DOCENTE Y SECCIÓN</option>').prop('disabled', true);
        if(inputBuscarDocente) inputBuscarDocente.value = '';
        const options = document.querySelectorAll('#id_usuario option');
        options.forEach(opt => opt.hidden = false);
        showToast('info', 'FORMULARIO LIMPIADO');
    });

    // CONVERSIÓN AUTOMÁTICA A MAYÚSCULAS EN ENTRADAS DE TEXTO
    $(document).on('input', 'input[type="text"]', function() {
        this.value = this.value.toUpperCase();
    });
});
</script>

<?php include("includes/footer.php"); ?>