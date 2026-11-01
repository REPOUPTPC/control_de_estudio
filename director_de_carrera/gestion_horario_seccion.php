<?php
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../funciones/functions.php');

cargarPermisosUsuario();

// Verificar autenticación y rol
if (!isLoggedIn()) {
    header('location: ../login.php');
    exit();
}
if (!isDirectorCarrera()) {
    $_SESSION['error'] = "No tienes permisos de Director de Carrera para acceder a este módulo.";
    header('location: ../profile_selector.php');
    exit();
}

$carreraId = $_SESSION['user']['carrera_di'] ?? 0;
if (!$carreraId && isAdmin()) {
    $carreraId = $_SESSION['admin_selected_carrera_di'] ?? 1;
}
if (!$carreraId) {
    $error_message = 'No se pudo determinar la carrera asignada.';
}

// Obtener periodo activo
$periodoActivo = obtenerPeriodoActivo();
$periodoId = $periodoActivo['id_periodo'] ?? 0;

// Obtener secciones de la carrera
$secciones = obtenerSeccionesPorCarrera($db, $carreraId);

$titulopag = 'Gestión de Horarios de Secciones';
include('includes/head.php');
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Gestionar Horarios de Secciones</h2>
                <p class="text-muted mb-0">Administra los horarios de las secciones de tu carrera</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <strong><i class="fas fa-list"></i> Seleccionar Sección</strong>
                </div>
                <div class="card-body">
                    <?php if (empty($secciones)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No hay secciones activas para tu carrera.
                            <a href="crear_seccion.php" class="btn btn-sm btn-primary mt-2">Crear Sección</a>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="seccionSelect">Sección</label>
                            <select class="form-control" id="seccionSelect">
                                <option value="">-- Seleccione una sección --</option>
                                <?php foreach ($secciones as $seccion): ?>
                                    <option value="<?= $seccion['id_seccion'] ?>">
                                        <?= htmlspecialchars($seccion['codigo_seccion']) ?> - 
                                        Trayecto <?= $seccion['numero_trayecto'] ?> - 
                                        <?= htmlspecialchars($seccion['nombre_periodo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <div id="infoSeccion" class="mt-3"></div>
                        <div id="btnPdfContainer" class="mt-3" style="display: none;">
                            <form id="formPdfHorario" method="POST" action="../admin/pdf_horario_seccion.php" target="_blank" class="m-0 p-0">
                                <input type="hidden" name="seccion_id" id="pdfSeccionId" value="">
                                <button type="submit" id="btnDescargarPdf" class="btn btn-danger btn-block font-weight-bold shadow-sm">
                                    <i class="fas fa-file-pdf mr-1"></i> Descargar Horario Oficial en PDF
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <strong><i class="fas fa-info-circle"></i> Instrucciones</strong>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Seleccione una sección para ver su horario</li>
                        <li>Haga clic en una celda vacía para asignar una clase</li>
                        <li>Las celdas verdes muestran clases ya asignadas</li>
                        <li>Las celdas rojas indican conflictos de horario</li>
                        <li>Puede eliminar una clase haciendo clic en el botón eliminar</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <strong><i class="fas fa-table"></i> Horario Semanal</strong>
                </div>
                <div class="card-body">
                    <div id="horarioContainer">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> Seleccione una sección para ver su horario.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para asignar horario -->
<div class="modal fade" id="asignarHorarioModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle"></i> Asignar Clase
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formAsignarHorario">
                    <input type="hidden" name="id_seccion" id="modalIdSeccion">
                    <input type="hidden" name="dia" id="modalDia">
                    <input type="hidden" name="hora_inicio" id="modalHoraInicio">
                    <input type="hidden" name="id_horario_editar" id="modalIdHorario">
                    
                    <div class="form-group">
                        <label for="materiaSelect">Materia *</label>
                        <select class="form-control" id="materiaSelect" name="id_materia" required>
                            <option value="">Cargando materias...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="docenteSelect">Docente *</label>
                        <select class="form-control" id="docenteSelect" name="id_docente" required disabled>
                            <option value="">-- Primero seleccione una materia --</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="horaFin">Hora de Fin *</label>
                            <select class="form-control" id="horaFin" name="hora_fin" required>
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
                        <div class="form-group col-md-6">
                            <label for="aulaSelect">Aula *</label>
                            <select class="form-control" id="aulaSelect" name="aula" required>
                                <?php
                                $aulas = $db->query("SELECT CONCAT(nave, ' - ', aula) as nombre_aula FROM aulas ORDER BY nave, aula");
                                while($aula = $aulas->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($aula['nombre_aula']) . "'>" . htmlspecialchars($aula['nombre_aula']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-clock"></i>
                        <strong>Duración:</strong> <span id="duracionInfo">0.5 hora(s)</span>
                    </div>
                    
                    <div id="conflictoWarning" class="alert alert-danger mt-3" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span id="conflictoMensaje"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarHorario">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para eliminar -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de eliminar esta clase del horario?</p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para horario fuera de rango -->
<div class="modal fade" id="modalConfirmHorario" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Horario fuera del rango normal
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="modalConfirmMensaje"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarHorario">Continuar</button>
            </div>
        </div>
    </div>
</div>

<style>
.horario-cell {
    cursor: pointer;
    transition: background-color 0.2s;
    min-height: 60px;
    vertical-align: middle;
    text-align: center;
    font-size: 0.8rem;
}
.horario-cell:hover {
    background-color: #e3f2fd !important;
}
.horario-cell.asignado {
    background-color: #d4edda;
}
.horario-cell.asignado:hover {
    background-color: #c3e6cb !important;
}
.celda-vacia {
    background-color: #f8f9fa;
}
.table-horario th, .table-horario td {
    border: 1px solid #dee2e6;
    padding: 8px;
}
.table-horario th {
    background-color: #f5f5f5;
}
.btn-eliminar-horario {
    padding: 2px 6px;
    font-size: 11px;
    margin-top: 5px;
}
</style>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    let idSeccionActual = null;
    let idHorarioAEliminar = null;
    let celdaPendiente = null;
    let diaPendiente = null;
    let horaPendiente = null;
    let turnoSeccionActual = 'Diurno';
    
    // Función para verificar si la hora está dentro del rango normal del turno
    function validarHorarioPorTurno(turno, hora) {
        let horaNum = parseFloat(hora.replace(':', '.'));
        
        if (turno === 'Diurno') {
            if (horaNum >= 7.00 && horaNum <= 17.30) {
                return { permitido: true, mensaje: '' };
            } else {
                return { 
                    permitido: false, 
                    mensaje: `El horario seleccionado (${hora}) está fuera del horario normal del turno Diurno (07:00 - 17:30). ¿Desea continuar?` 
                };
            }
        } else if (turno === 'Nocturno') {
            if (horaNum >= 17.30 && horaNum <= 20.30) {
                return { permitido: true, mensaje: '' };
            } else {
                return { 
                    permitido: false, 
                    mensaje: `El horario seleccionado (${hora}) está fuera del horario normal del turno Nocturno (17:30 - 20:30). ¿Desea continuar?` 
                };
            }
        }
        
        return { permitido: true, mensaje: '' };
    }
    
    // Función para abrir el modal de asignación
    function abrirModalAsignacion(dia, hora) {
        $('#modalIdSeccion').val(idSeccionActual);
        $('#modalDia').val(dia);
        $('#modalHoraInicio').val(hora);
        $('#modalIdHorario').val('');
        $('#conflictoWarning').hide();
        
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
                $('#asignarHorarioModal').modal('show');
            }
        });
    }
    
    // Cargar horario al seleccionar sección
    $('#seccionSelect').change(function() {
        idSeccionActual = $(this).val();
        if (idSeccionActual) {
            cargarHorario(idSeccionActual);
            cargarInfoSeccion(idSeccionActual);
            $('#btnPdfContainer').show();
            $('#pdfSeccionId').val(idSeccionActual);
        } else {
            $('#horarioContainer').html('<div class="alert alert-info text-center"><i class="fas fa-info-circle"></i> Seleccione una sección para ver su horario.</div>');
            $('#infoSeccion').html('');
            $('#pdfSeccionId').val('');
            $('#btnPdfContainer').hide();
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
                    $('#infoSeccion').html(`
                        <div class="alert alert-info">
                            <strong>${data.codigo_seccion}</strong><br>
                            Trayecto: ${data.numero_trayecto}<br>
                            Período: ${data.nombre_periodo}<br>
                            Turno: ${data.turno}
                        </div>
                    `);
                }
            }
        });
    }
    
    function cargarHorario(idSeccion) {
        $('#horarioContainer').html('<div class="text-center py-5"><div class="spinner-border"></div><p class="mt-2">Cargando horario...</p></div>');
        
        $.ajax({
            url: 'ajax_horario_seccion.php',
            type: 'POST',
            data: { action: 'get_horario', id_seccion: idSeccion },
            success: function(response) {
                $('#horarioContainer').html(response);
            },
            error: function() {
                $('#horarioContainer').html('<div class="alert alert-danger">Error al cargar el horario</div>');
            }
        });
    }
    
    // Click en celda vacía para asignar clase
    $(document).on('click', '.horario-cell:not(.asignado)', function() {
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
    
    // Cargar docentes al seleccionar materia
    $('#materiaSelect').change(function() {
        var idMateria = $(this).val();
        var idSeccion = $('#modalIdSeccion').val();
        
        if (idMateria) {
            $('#docenteSelect').prop('disabled', true).html('<option value="">Cargando docentes...</option>');
            
            $.ajax({
                url: 'ajax_horario_seccion.php',
                type: 'POST',
                data: { action: 'get_docentes_materia', id_materia: idMateria, id_seccion: idSeccion },
                dataType: 'json',
                success: function(data) {
                    $('#docenteSelect').html('<option value="">-- Seleccione un docente --</option>');
                    $.each(data, function(i, docente) {
                        $('#docenteSelect').append(`<option value="${docente.id}">${docente.nombre}</option>`);
                    });
                    $('#docenteSelect').prop('disabled', false);
                }
            });
        } else {
            $('#docenteSelect').prop('disabled', true).html('<option value="">-- Primero seleccione una materia --</option>');
        }
    });
    
    // Calcular duración
    $('#modalHoraInicio, #horaFin').on('change', function() {
        var inicio = $('#modalHoraInicio').val();
        var fin = $('#horaFin').val();
        if (inicio && fin) {
            var inicioNum = parseFloat(inicio.replace(':', '.'));
            var finNum = parseFloat(fin.replace(':', '.'));
            var horas = finNum - inicioNum;
            $('#duracionInfo').text(horas + ' hora(s)');
        }
    });
    
    // Verificar conflictos antes de guardar
    $('#materiaSelect, #docenteSelect, #horaFin, #aulaSelect').change(function() {
        var idSeccion = $('#modalIdSeccion').val();
        var dia = $('#modalDia').val();
        var horaInicio = $('#modalHoraInicio').val();
        var horaFin = $('#horaFin').val();
        var aula = $('#aulaSelect').val();
        var idHorario = $('#modalIdHorario').val();
        
        if (horaInicio && horaFin && dia && aula) {
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
    });
    
    // Guardar horario
    $('#btnGuardarHorario').click(function() {
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
                    alert('Horario guardado correctamente');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error de conexión');
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
                    alert('Horario eliminado');
                } else {
                    alert('Error al eliminar');
                }
            }
        });
    });
});
</script>

<?php include('includes/footer.php'); ?>