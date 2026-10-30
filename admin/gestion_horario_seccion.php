<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../funciones/functions.php');

// Verificar autenticación y rol
cargarPermisosUsuario();
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['msg'] = "Debes iniciar sesión como administrador para acceder a este módulo";
    header('location: ../login.php');
    exit();
}

visita();

// Obtener todas las carreras activas
$carreras_res = $db->query("SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera ASC");
$lista_carreras = [];
if ($carreras_res) {
    while ($c = $carreras_res->fetch_assoc()) {
        $lista_carreras[] = $c;
    }
}

// Carrera inicial seleccionada
$carreraId = isset($_GET['id_carrera']) ? intval($_GET['id_carrera']) : 0;
$seccionIdInicial = isset($_GET['id_seccion']) ? intval($_GET['id_seccion']) : 0;

// Obtener secciones según carrera
if ($carreraId > 0) {
    $secciones = obtenerSeccionesPorCarrera($db, $carreraId);
} else {
    $sql_sec = "SELECT s.id_seccion, s.codigo_seccion, t.numero_trayecto, p.nombre_periodo, c.nombre_carrera 
                FROM secciones s
                JOIN trayectos t ON s.id_trayecto = t.id_trayecto
                JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
                JOIN carreras c ON s.id_carrera = c.id_carrera
                WHERE s.estatus = 1
                ORDER BY c.nombre_carrera ASC, s.codigo_seccion ASC";
    $res_sec = $db->query($sql_sec);
    $secciones = [];
    if ($res_sec) {
        while ($r = $res_sec->fetch_assoc()) {
            $secciones[] = $r;
        }
    }
}

$titulopag = 'Gestión de Horarios de Secciones - Administrador';
include('includes/head.php');
?>

<div class="container-fluid py-4 px-2 px-md-4">
    <!-- Encabezado Principal -->
    <div class="card shadow-sm border-0 mb-4" style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; border-radius: 12px;">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <h2 class="h3 font-weight-bold mb-1">
                        <i class="fas fa-calendar-alt mr-2"></i> Gestión de Horarios de Secciones
                    </h2>
                    <p class="mb-0 text-white-50">
                        Configuración y planificación semanal de bloques de clases, asignaturas, docentes y aulas por sección
                    </p>
                </div>
                <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
                    <a href="horarios_docentes.php" class="btn btn-warning font-weight-bold shadow-sm text-dark mr-2">
                        <i class="fas fa-clock mr-1"></i> Horarios por Docente
                    </a>
                    <a href="index.php" class="btn btn-outline-light font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i> Volver al Panel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Panel Izquierdo: Selección y Filtros -->
        <div class="col-12 col-lg-4 mb-4">
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 10px;">
                <div class="card-header bg-dark text-white font-weight-bold">
                    <i class="fas fa-filter text-warning mr-1"></i> Selección de Sección
                </div>
                <div class="card-body">
                    <!-- Selector de Carrera -->
                    <div class="form-group mb-3">
                        <label for="carreraSelect" class="small font-weight-bold text-muted">
                            <i class="fas fa-graduation-cap mr-1"></i> Carrera / PNF:
                        </label>
                        <select class="form-control form-control-sm font-weight-bold" id="carreraSelect">
                            <option value="0" <?= $carreraId === 0 ? 'selected' : '' ?>>Todas las Carreras</option>
                            <?php foreach ($lista_carreras as $carr): ?>
                                <option value="<?= $carr['id_carrera'] ?>" <?= $carreraId == $carr['id_carrera'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($carr['nombre_carrera']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selector de Sección -->
                    <div class="form-group mb-3">
                        <label for="seccionSelect" class="small font-weight-bold text-muted">
                            <i class="fas fa-door-open mr-1"></i> Sección Académica:
                        </label>
                        <select class="form-control form-control-sm font-weight-bold" id="seccionSelect">
                            <option value="">-- Seleccione una sección --</option>
                            <?php foreach ($secciones as $sec): ?>
                                <option value="<?= $sec['id_seccion'] ?>" <?= $seccionIdInicial == $sec['id_seccion'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sec['codigo_seccion']) ?> 
                                    <?= isset($sec['nombre_carrera']) ? ' (' . htmlspecialchars($sec['nombre_carrera']) . ')' : '' ?> - 
                                    Trayecto <?= $sec['numero_trayecto'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Detalle de la Sección Seleccionada -->
                    <div id="infoSeccion" class="mt-3"></div>

                    <!-- Botón PDF Horario (POST) -->
                    <div id="btnPdfContainer" class="mt-3" style="display: none;">
                        <form id="formPdfHorario" method="POST" action="pdf_horario_seccion.php" target="_blank" class="m-0 p-0">
                            <input type="hidden" name="seccion_id" id="pdfSeccionId" value="">
                            <button type="submit" id="btnDescargarPdf" class="btn btn-danger btn-block font-weight-bold shadow-sm">
                                <i class="fas fa-file-pdf mr-1"></i> Descargar Horario Oficial en PDF
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Tarjeta de Instrucciones -->
            <div class="card shadow-sm border-0" style="border-radius: 10px;">
                <div class="card-header bg-info text-white font-weight-bold">
                    <i class="fas fa-info-circle mr-1"></i> Instrucciones de Uso
                </div>
                <div class="card-body">
                    <ul class="small mb-0 pl-3">
                        <li class="mb-2">Seleccione la carrera y luego la sección deseada.</li>
                        <li class="mb-2">Haga clic en cualquier celda libre para programar una materia, docente y aula.</li>
                        <li class="mb-2"><span class="badge badge-success px-2 py-1">Verde</span>: Bloques de clases ya programados.</li>
                        <li class="mb-2">El sistema valida cruces de horario de docentes y disponibilidad de aulas automáticamente.</li>
                        <li>Puede eliminar un bloque haciendo clic en el icono rojo <i class="fas fa-trash-alt text-danger"></i> dentro de la celda.</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Panel Derecho: Grilla del Horario Semanal -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0" style="border-radius: 10px; overflow: hidden;">
                <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="font-weight-bold mb-0">
                        <i class="fas fa-table mr-2 text-warning"></i> Horario Semanal de Clases
                    </h6>
                    <span id="badgeEstadoSeccion" class="badge badge-light font-weight-bold">
                        Ninguna sección seleccionada
                    </span>
                </div>
                <div class="card-body p-3">
                    <div id="horarioContainer">
                        <div class="alert alert-info text-center py-5">
                            <i class="fas fa-calendar-week fa-3x mb-3 text-info opacity-50"></i>
                            <h5>Seleccione una sección académica</h5>
                            <p class="text-muted mb-0">Elija una sección en el panel lateral para visualizar o modificar su horario de clases.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Asignar Horario -->
<div class="modal fade" id="asignarHorarioModal" tabindex="-1" role="dialog" aria-labelledby="asignarHorarioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <h5 class="modal-title font-weight-bold" id="asignarHorarioModalLabel">
                    <i class="fas fa-plus-circle text-warning mr-2"></i> Asignar Bloque de Clase
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <form id="formAsignarHorario">
                    <input type="hidden" name="id_seccion" id="modalIdSeccion">
                    <input type="hidden" name="dia" id="modalDia">
                    <input type="hidden" name="hora_inicio" id="modalHoraInicio">
                    <input type="hidden" name="id_horario_editar" id="modalIdHorario">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Día de la Semana:</label>
                            <input type="text" id="modalDiaTexto" class="form-control form-control-sm font-weight-bold bg-light" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Hora de Inicio:</label>
                            <input type="text" id="modalHoraInicioTexto" class="form-control form-control-sm font-weight-bold bg-light" readonly>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="materiaSelect" class="small font-weight-bold text-muted mb-1">Materia / Asignatura *</label>
                        <select class="form-control form-control-sm font-weight-bold" id="materiaSelect" name="id_materia" required>
                            <option value="">Cargando materias...</option>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="docenteSelect" class="small font-weight-bold text-muted mb-1">Docente / Profesor *</label>
                        <select class="form-control form-control-sm font-weight-bold" id="docenteSelect" name="id_docente" required disabled>
                            <option value="">-- Primero seleccione una materia --</option>
                        </select>
                    </div>
                    
                    <div class="form-row mb-3">
                        <div class="form-group col-md-6 mb-2 mb-md-0">
                            <label for="horaFin" class="small font-weight-bold text-muted mb-1">Hora de Culminación *</label>
                            <select class="form-control form-control-sm font-weight-bold" id="horaFin" name="hora_fin" required>
                                <option value="07:30">07:30</option>
                                <option value="08:00">08:00</option>
                                <option value="08:30">08:30</option>
                                <option value="09:00">09:00</option>
                                <option value="09:30">09:30</option>
                                <option value="10:00">10:00</option>
                                <option value="10:30">10:30</option>
                                <option value="11:00">11:00</option>
                                <option value="11:30">11:30</option>
                                <option value="12:00">12:00</option>
                                <option value="12:30">12:30</option>
                                <option value="13:00">13:00</option>
                                <option value="13:30">13:30</option>
                                <option value="14:00">14:00</option>
                                <option value="14:30">14:30</option>
                                <option value="15:00">15:00</option>
                                <option value="15:30">15:30</option>
                                <option value="16:00">16:00</option>
                                <option value="16:30">16:30</option>
                                <option value="17:00">17:00</option>
                                <option value="17:30">17:30</option>
                                <option value="18:00">18:00</option>
                                <option value="18:30">18:30</option>
                                <option value="19:00">19:00</option>
                                <option value="19:30">19:30</option>
                                <option value="20:00">20:00</option>
                                <option value="20:30">20:30</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6 mb-0">
                            <label for="aulaSelect" class="small font-weight-bold text-muted mb-1">Aula / Espacio Físico *</label>
                            <select class="form-control form-control-sm font-weight-bold" id="aulaSelect" name="aula" required>
                                <?php
                                $aulas = $db->query("SELECT CONCAT(nave, ' - ', aula) as nombre_aula FROM aulas ORDER BY nave, aula");
                                if ($aulas) {
                                    while($aula = $aulas->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($aula['nombre_aula']) . "'>" . htmlspecialchars($aula['nombre_aula']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info py-2 px-3 mb-2 d-flex align-items-center">
                        <i class="fas fa-stopwatch mr-2 text-primary"></i>
                        <span class="small font-weight-bold">Duración calculada: <strong id="duracionInfo">0.5 hora(s)</strong></span>
                    </div>
                    
                    <div id="conflictoWarning" class="alert alert-danger py-2 px-3 mb-0" style="display: none;">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span id="conflictoMensaje" class="small font-weight-bold"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-2 px-4">
                <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary font-weight-bold" id="btnGuardarHorario">
                    <i class="fas fa-save mr-1"></i> Guardar Bloque
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmación para Eliminar -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title font-weight-bold mb-0">
                    <i class="fas fa-trash-alt mr-2"></i> Confirmar Eliminación
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                <h5>¿Está seguro de eliminar esta clase del horario?</h5>
                <p class="text-muted small mb-0">Esta acción liberará el espacio de aula y tiempo para esta sección.</p>
            </div>
            <div class="modal-footer bg-light py-2 px-4">
                <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger font-weight-bold" id="confirmDeleteBtn">
                    <i class="fas fa-trash-alt mr-1"></i> Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmación para Horario Fuera de Rango -->
<div class="modal fade" id="modalConfirmHorario" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-warning text-dark py-3 px-4">
                <h5 class="modal-title font-weight-bold mb-0">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Horario Fuera de Rango Regular
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                <p id="modalConfirmMensaje" class="font-weight-bold mb-0"></p>
            </div>
            <div class="modal-footer bg-light py-2 px-4">
                <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning font-weight-bold" id="btnConfirmarHorario">
                    <i class="fas fa-check mr-1"></i> Continuar de todos modos
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.horario-cell {
    cursor: pointer;
    transition: background-color 0.2s;
    min-height: 60px;
    vertical-align: middle !important;
    text-align: center;
    font-size: 0.8rem;
    position: relative;
}
.horario-cell:hover {
    background-color: #e3f2fd !important;
}
.horario-cell.asignado {
    background-color: #d4edda !important;
    border-left: 3px solid #28a745 !important;
}
.horario-cell.asignado:hover {
    background-color: #c3e6cb !important;
}
.celda-vacia {
    background-color: #fcfcfc;
}
.table-horario th, .table-horario td {
    border: 1px solid #dee2e6;
    padding: 6px;
}
.table-horario th {
    background-color: #f8f9fa;
    font-size: 0.82rem;
}
.btn-eliminar-horario {
    font-size: 10px;
}
</style>

<script>
$(document).ready(function() {
    let idSeccionActual = $('#seccionSelect').val() || null;
    let idHorarioAEliminar = null;
    let celdaPendiente = null;
    let diaPendiente = null;
    let horaPendiente = null;
    let turnoSeccionActual = 'Diurno';
    const diasNombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    // Cambio de carrera -> recargar secciones dinámicamente
    $('#carreraSelect').change(function() {
        const idCarrera = $(this).val();
        $('#seccionSelect').html('<option value="">Cargando secciones...</option>');
        $('#horarioContainer').html('<div class="alert alert-info text-center py-5"><i class="fas fa-spinner fa-spin fa-2x mb-3 text-info"></i><p>Cargando secciones...</p></div>');
        $('#infoSeccion').html('');
        $('#btnPdfContainer').hide();
        $('#badgeEstadoSeccion').text('Ninguna sección seleccionada');

        $.ajax({
            url: 'ajax_horario_seccion.php',
            type: 'POST',
            data: { action: 'get_secciones_carrera', id_carrera: idCarrera },
            dataType: 'json',
            success: function(data) {
                let options = '<option value="">-- Seleccione una sección --</option>';
                $.each(data, function(i, sec) {
                    const carreraInfo = sec.nombre_carrera ? ` (${sec.nombre_carrera})` : '';
                    options += `<option value="${sec.id_seccion}">${sec.codigo_seccion}${carreraInfo} - Trayecto ${sec.numero_trayecto}</option>`;
                });
                $('#seccionSelect').html(options);
                $('#horarioContainer').html('<div class="alert alert-info text-center py-5"><i class="fas fa-calendar-week fa-3x mb-3 text-info opacity-50"></i><h5>Seleccione una sección académica</h5><p class="text-muted mb-0">Elija una sección en el panel lateral para visualizar o modificar su horario de clases.</p></div>');
            },
            error: function() {
                $('#seccionSelect').html('<option value="">Error al cargar secciones</option>');
            }
        });
    });

    // Validación por turno
    function validarHorarioPorTurno(turno, hora) {
        let horaNum = parseFloat(hora.replace(':', '.'));
        if (turno === 'Diurno') {
            if (horaNum >= 7.00 && horaNum <= 17.30) {
                return { permitido: true, mensaje: '' };
            } else {
                return { 
                    permitido: false, 
                    mensaje: `El horario seleccionado (${hora}) está fuera del turno Diurno habitual (07:00 - 17:30). ¿Desea continuar de todos modos?` 
                };
            }
        } else if (turno === 'Nocturno') {
            if (horaNum >= 17.30 && horaNum <= 20.30) {
                return { permitido: true, mensaje: '' };
            } else {
                return { 
                    permitido: false, 
                    mensaje: `El horario seleccionado (${hora}) está fuera del turno Nocturno habitual (17:30 - 20:30). ¿Desea continuar de todos modos?` 
                };
            }
        }
        return { permitido: true, mensaje: '' };
    }

    function abrirModalAsignacion(dia, hora) {
        $('#modalIdSeccion').val(idSeccionActual);
        $('#modalDia').val(dia);
        $('#modalDiaTexto').val(diasNombres[dia] || 'Día ' + dia);
        $('#modalHoraInicio').val(hora);
        $('#modalHoraInicioTexto').val(hora);
        $('#modalIdHorario').val('');
        $('#conflictoWarning').hide();
        $('#btnGuardarHorario').prop('disabled', false);

        // Preseleccionar hora fin + 1 hora
        let horaPartes = hora.split(':');
        let h = parseInt(horaPartes[0]);
        let m = horaPartes[1];
        let hFin = h + 1;
        let hFinStr = (hFin < 10 ? '0' : '') + hFin + ':' + m;
        if ($('#horaFin option[value="' + hFinStr + '"]').length > 0) {
            $('#horaFin').val(hFinStr);
        } else {
            $('#horaFin').val($('#horaFin option:first').val());
        }
        calcularDuracion();

        $.ajax({
            url: 'ajax_horario_seccion.php',
            type: 'POST',
            data: { action: 'get_materias_seccion', id_seccion: idSeccionActual },
            dataType: 'json',
            success: function(data) {
                $('#materiaSelect').html('<option value="">-- Seleccione una materia --</option>');
                $.each(data, function(i, materia) {
                    $('#materiaSelect').append(`<option value="${materia.id_materia}">${materia.nombre_materia}</option>`);
                });
                $('#docenteSelect').prop('disabled', true).html('<option value="">-- Primero seleccione una materia --</option>');
                $('#asignarHorarioModal').modal('show');
            }
        });
    }

    // Al cambiar la sección
    $('#seccionSelect').change(function() {
        idSeccionActual = $(this).val();
        if (idSeccionActual) {
            cargarHorario(idSeccionActual);
            cargarInfoSeccion(idSeccionActual);
            $('#btnPdfContainer').show();
            $('#pdfSeccionId').val(idSeccionActual);
        } else {
            $('#horarioContainer').html('<div class="alert alert-info text-center py-5"><i class="fas fa-calendar-week fa-3x mb-3 text-info opacity-50"></i><h5>Seleccione una sección académica</h5></div>');
            $('#infoSeccion').html('');
            $('#pdfSeccionId').val('');
            $('#btnPdfContainer').hide();
            $('#badgeEstadoSeccion').text('Ninguna sección seleccionada');
        }
    });

    function cargarInfoSeccion(idSeccion) {
        $.ajax({
            url: 'ajax_horario_seccion.php',
            type: 'POST',
            data: { action: 'get_info_seccion', id_seccion: idSeccion },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    turnoSeccionActual = data.turno;
                    $('#badgeEstadoSeccion').text(data.codigo_seccion + ' (' + data.turno + ')');
                    $('#infoSeccion').html(`
                        <div class="card bg-light border-0 p-3 shadow-sm" style="border-radius: 8px;">
                            <h6 class="font-weight-bold text-primary mb-2"><i class="fas fa-info-circle mr-1"></i> ${data.codigo_seccion}</h6>
                            <div class="small">
                                <p class="mb-1"><strong>Carrera:</strong> ${data.nombre_carrera}</p>
                                <p class="mb-1"><strong>Trayecto:</strong> ${data.numero_trayecto}</p>
                                <p class="mb-1"><strong>Período:</strong> ${data.nombre_periodo}</p>
                                <p class="mb-0"><strong>Turno:</strong> <span class="badge ${data.turno === 'Nocturno' ? 'badge-dark' : 'badge-primary'}">${data.turno}</span></p>
                            </div>
                        </div>
                    `);
                }
            }
        });
    }

    function cargarHorario(idSeccion) {
        $('#horarioContainer').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Cargando horario semanal...</p></div>');
        
        $.ajax({
            url: 'ajax_horario_seccion.php',
            type: 'POST',
            data: { action: 'get_horario', id_seccion: idSeccion },
            success: function(response) {
                $('#horarioContainer').html(response);
            },
            error: function() {
                $('#horarioContainer').html('<div class="alert alert-danger text-center">Error al cargar el horario</div>');
            }
        });
    }

    // Clic en celda para asignar
    $(document).on('click', '.horario-cell:not(.asignado)', function() {
        if (!idSeccionActual) {
            alert('Por favor seleccione primero una sección');
            return;
        }
        var dia = $(this).data('dia');
        var hora = $(this).data('hora');
        var validacion = validarHorarioPorTurno(turnoSeccionActual, hora);
        
        if (!validacion.permitido) {
            celdaPendiente = $(this);
            diaPendiente = dia;
            horaPendiente = hora;
            $('#modalConfirmMensaje').html(validacion.mensaje);
            $('#modalConfirmHorario').modal('show');
        } else {
            abrirModalAsignacion(dia, hora);
        }
    });

    $('#btnConfirmarHorario').click(function() {
        $('#modalConfirmHorario').modal('hide');
        if (diaPendiente !== null && horaPendiente !== null) {
            abrirModalAsignacion(diaPendiente, horaPendiente);
        }
        celdaPendiente = null;
        diaPendiente = null;
        horaPendiente = null;
    });

    // Cargar docentes al cambiar materia
    $('#materiaSelect').change(function() {
        var idMateria = $(this).val();
        if (idMateria) {
            $('#docenteSelect').prop('disabled', true).html('<option value="">Cargando docentes...</option>');
            $.ajax({
                url: 'ajax_horario_seccion.php',
                type: 'POST',
                data: { action: 'get_docentes_materia', id_materia: idMateria },
                dataType: 'json',
                success: function(data) {
                    $('#docenteSelect').html('<option value="">-- Seleccione un docente --</option>');
                    $.each(data, function(i, docente) {
                        $('#docenteSelect').append(`<option value="${docente.id}">${docente.nombre}</option>`);
                    });
                    $('#docenteSelect').prop('disabled', false);
                    verificarConflicto();
                }
            });
        } else {
            $('#docenteSelect').prop('disabled', true).html('<option value="">-- Primero seleccione una materia --</option>');
        }
    });

    function calcularDuracion() {
        var inicio = $('#modalHoraInicio').val();
        var fin = $('#horaFin').val();
        if (inicio && fin) {
            var inicioParts = inicio.split(':');
            var finParts = fin.split(':');
            var minutosInicio = parseInt(inicioParts[0]) * 60 + parseInt(inicioParts[1]);
            var minutosFin = parseInt(finParts[0]) * 60 + parseInt(finParts[1]);
            var diffMinutos = minutosFin - minutosInicio;
            if (diffMinutos > 0) {
                var horas = (diffMinutos / 60).toFixed(1);
                $('#duracionInfo').text(horas + ' hora(s) (' + diffMinutos + ' min)');
            } else {
                $('#duracionInfo').text('Inválida (hora fin debe ser posterior)');
            }
        }
    }

    $('#horaFin').on('change', function() {
        calcularDuracion();
        verificarConflicto();
    });

    $('#docenteSelect, #aulaSelect').change(function() {
        verificarConflicto();
    });

    function verificarConflicto() {
        var idSeccion = $('#modalIdSeccion').val();
        var dia = $('#modalDia').val();
        var horaInicio = $('#modalHoraInicio').val();
        var horaFin = $('#horaFin').val();
        var aula = $('#aulaSelect').val();
        var idHorario = $('#modalIdHorario').val();

        if (horaInicio && horaFin && dia !== '' && aula) {
            $.ajax({
                url: 'ajax_horario_seccion.php',
                type: 'POST',
                data: {
                    action: 'verificar_conflicto',
                    id_seccion: idSeccion,
                    dia: dia,
                    hora_inicio: horaInicio,
                    hora_fin: horaFin,
                    aula: aula,
                    id_horario: idHorario
                },
                dataType: 'json',
                success: function(data) {
                    if (data.conflicto) {
                        $('#conflictoWarning').show();
                        $('#conflictoMensaje').html(data.mensaje);
                        $('#btnGuardarHorario').prop('disabled', true);
                    } else {
                        $('#conflictoWarning').hide();
                        $('#btnGuardarHorario').prop('disabled', false);
                    }
                }
            });
        }
    }

    // Guardar horario
    $('#btnGuardarHorario').click(function() {
        var materia = $('#materiaSelect').val();
        var docente = $('#docenteSelect').val();
        if (!materia || !docente) {
            alert('Debe seleccionar la materia y el docente');
            return;
        }

        var formData = $('#formAsignarHorario').serialize();
        formData += '&action=guardar_horario';
        
        $.ajax({
            url: 'ajax_horario_seccion.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                $('#asignarHorarioModal').modal('hide');
                if (response.success) {
                    cargarHorario(idSeccionActual);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error al procesar la solicitud');
            }
        });
    });

    // Eliminar horario
    $(document).on('click', '.btn-eliminar-horario', function(e) {
        e.stopPropagation();
        idHorarioAEliminar = $(this).data('id');
        $('#confirmDeleteModal').modal('show');
    });

    $('#confirmDeleteBtn').click(function() {
        $.ajax({
            url: 'ajax_horario_seccion.php',
            type: 'POST',
            data: { action: 'eliminar_horario', id_horario: idHorarioAEliminar },
            dataType: 'json',
            success: function(response) {
                $('#confirmDeleteModal').modal('hide');
                if (response.success) {
                    cargarHorario(idSeccionActual);
                } else {
                    alert('Error al eliminar');
                }
            }
        });
    });

    // Si había sección inicial seleccionada vía GET
    if (idSeccionActual) {
        $('#seccionSelect').trigger('change');
    }
});
</script>

<?php include('includes/footer.php'); ?>
