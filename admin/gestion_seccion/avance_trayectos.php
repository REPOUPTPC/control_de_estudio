<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulopag = "Avance de Trayectos";
require_once(__DIR__ . '/../../funciones/functions.php');

cargarPermisosUsuario();
verificarPermiso('secciones');
visita();

$id_seccion = (int)$_GET['id'] ?? 0;
if (!$id_seccion) header("Location: gestion_seccion.php");

$seccion = obtenerDetalleSeccion($db, $id_seccion);
$estudiantes = obtenerEstudiantesSeccionConRequisitos($id_seccion);

$pueden_avanzar = array_filter($estudiantes, function($e) { return $e['puede_avanzar']; });
$no_pueden_avanzar = array_filter($estudiantes, function($e) { return !$e['puede_avanzar']; });

include(__DIR__ . '/../includes/head.php');
?>

<div class="container-fluid py-2">
    <div class="row mb-2">
        <div class="col-12 d-flex justify-content-between">
            <h2 class="h4 mb-0">Avance de Trayectos - <?= htmlspecialchars($seccion['codigo_seccion']) ?></h2>
            <div>
                <form method="POST" action="ver_seccion.php" class="d-inline m-0">
                    <input type="hidden" name="id" value="<?= $id_seccion ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">← Volver</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Información de la sección -->
    <div class="card shadow mb-3">
        <div class="card-header bg-primary text-white py-1">
            <h6 class="m-0">Información de la Sección</h6>
        </div>
        <div class="card-body py-2">
            <div class="row">
                <div class="col-md-3"><strong>Código:</strong> <?= htmlspecialchars($seccion['codigo_seccion']) ?></div>
                <div class="col-md-3"><strong>Carrera:</strong> <?= htmlspecialchars($seccion['nombre_carrera']) ?></div>
                <div class="col-md-3"><strong>Trayecto Actual:</strong> <?= $seccion['numero_trayecto'] ?></div>
                <div class="col-md-3"><strong>Siguiente Trayecto:</strong> <?= $seccion['numero_trayecto'] + 1 ?></div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    <strong>Requisitos para avanzar al Trayecto <?= $seccion['numero_trayecto'] + 1 ?>:</strong>
                    <?php
                    switch ($seccion['numero_trayecto']) {
                        case 0: echo "Aprobar el 50% de las materias del trayecto 0"; break;
                        case 1: echo "Aprobar Proyecto Socio Integrador con nota ≥ 16"; break;
                        case 2: echo "Aprobar TODAS las materias y obtener título TSU"; break;
                        case 3: echo "Aprobar Proyecto Socio Integrador con nota ≥ 16"; break;
                        case 4: echo "Es el último trayecto, no puede avanzar más"; break;
                        default: echo "Trayecto no válido";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas de avance -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body py-2 text-center">
                    <h3 class="mb-0"><?= count($pueden_avanzar) ?></h3>
                    <small>Pueden Avanzar</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body py-2 text-center">
                    <h3 class="mb-0"><?= count($no_pueden_avanzar) ?></h3>
                    <small>NO Pueden Avanzar</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body py-2 text-center">
                    <h3 class="mb-0"><?= count($estudiantes) ?></h3>
                    <small>Total Estudiantes</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones de acción -->
    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <?php if ($seccion['numero_trayecto'] < 4): ?>
                <button type="button" class="btn btn-success" id="btnAvanzarSeccion" 
                        data-seccion-id="<?= $id_seccion ?>"
                        data-trayecto-actual="<?= $seccion['numero_trayecto'] ?>"
                        data-estudiantes-avanzan="<?= count($pueden_avanzar) ?>">
                    <i class="fas fa-forward"></i> Avanzar Sección al Trayecto <?= $seccion['numero_trayecto'] + 1 ?>
                </button>
                <small class="text-muted ml-2">Se moverán automáticamente <?= count($pueden_avanzar) ?> estudiantes a la nueva sección</small>
            <?php else: ?>
                <div class="alert alert-warning mb-0">Esta sección está en el último trayecto (4), no puede avanzar más.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabla de estudiantes que PUEDEN avanzar -->
    <div class="card shadow mb-3">
        <div class="card-header bg-success text-white py-1">
            <h6 class="m-0">Estudiantes que PUEDEN Avanzar (<?= count($pueden_avanzar) ?>)</h6>
        </div>
        <div class="card-body py-2">
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr><th>Cédula</th><th>Estudiante</th><th>Motivo</th><th>Detalles</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pueden_avanzar as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['idusuario']) ?></td>
                            <td><?= htmlspecialchars($e['nombre']) ?></td>
                            <td><span class="badge badge-success"><?= htmlspecialchars($e['motivo']) ?></span></td>
                            <td>
                                <?php if (!empty($e['detalles'])): ?>
                                    <small class="text-muted">
                                        <?php if (isset($e['detalles']['total_materias'])): ?>
                                            Materias: <?= $e['detalles']['aprobadas'] ?>/<?= $e['detalles']['total_materias'] ?> (<?= $e['detalles']['porcentaje'] ?>%)
                                        <?php elseif (isset($e['detalles']['nota_obtenida'])): ?>
                                            Nota: <?= $e['detalles']['nota_obtenida'] ?>/<?= $e['detalles']['nota_requerida'] ?>
                                        <?php elseif (isset($e['detalles']['pendiente_titulo'])): ?>
                                            ⚠️ Título pendiente
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                             </div>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pueden_avanzar)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No hay estudiantes que cumplan los requisitos</div></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabla de estudiantes que NO PUEDEN avanzar -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white py-1">
            <h6 class="m-0">Estudiantes que NO PUEDEN Avanzar (<?= count($no_pueden_avanzar) ?>)</h6>
        </div>
        <div class="card-body py-2">
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr><th>Cédula</th><th>Estudiante</th><th>Motivo</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($no_pueden_avanzar as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['idusuario']) ?></td>
                            <td><?= htmlspecialchars($e['nombre']) ?></td>
                            <td><span class="badge badge-warning"><?= htmlspecialchars($e['motivo']) ?></span></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary btn-mover-rezagado"
                                        data-id-usuario="<?= $e['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($e['nombre']) ?>"
                                        data-cedula="<?= htmlspecialchars($e['idusuario']) ?>"
                                        data-seccion-origen-id="<?= $id_seccion ?>"
                                        data-seccion-origen-codigo="<?= htmlspecialchars($seccion['codigo_seccion']) ?>"
                                        data-trayecto-actual="<?= $seccion['numero_trayecto'] ?>"
                                        data-id-trayecto="<?= $seccion['id_trayecto'] ?>"
                                        data-carrera="<?= $seccion['id_carrera'] ?>">
                                    <i class="fas fa-exchange-alt"></i> Mover a otra sección
                                </button>
                             </div>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($no_pueden_avanzar)): ?>
                            <tr><td colspan="4" class="text-center text-muted">Todos los estudiantes cumplen los requisitos</div></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para mover rezagados -->
<div class="modal fade" id="modalMoverRezagado" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Mover Estudiante a Otra Sección</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p><strong>Estudiante:</strong> <span id="estudianteNombre"></span> (<span id="estudianteCedula"></span>)</p>
                <p><strong>Sección de origen:</strong> <span id="seccionOrigen"></span></p>
                <p><strong>Trayecto actual:</strong> <span id="trayectoActual"></span></p>
                <div class="form-group">
                    <label>Seleccionar Sección Destino:</label>
                    <select class="form-control" id="seccionDestino" required>
                        <option value="">Cargando secciones disponibles...</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarMover">Mover Estudiante</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentEstudiante = {};
    
    $('.btn-mover-rezagado').click(function() {
        currentEstudiante = {
            id: $(this).data('id-usuario'),
            nombre: $(this).data('nombre'),
            cedula: $(this).data('cedula'),
            seccion_origen_id: $(this).data('seccion-origen-id'),
            seccion_origen_codigo: $(this).data('seccion-origen-codigo') || '',
            trayecto: $(this).data('id-trayecto'),
            carrera: $(this).data('carrera')
        };

        $('#estudianteNombre').text(currentEstudiante.nombre);
        $('#estudianteCedula').text(currentEstudiante.cedula);
        $('#seccionOrigen').text(currentEstudiante.seccion_origen_codigo);
        $('#trayectoActual').text('Trayecto ' + currentEstudiante.trayecto);
        
        // Cargar secciones disponibles para mover al estudiante
        function cargarSeccionesDisponibles() {
            $.ajax({
                url: 'ajax_obtener_secciones_disponibles.php',
                type: 'POST',
                data: {
                    id_carrera: currentEstudiante.carrera,
                    id_trayecto: currentEstudiante.trayecto
                },
                success: function(data) {
                    // Detectar si el servidor redirigió a login (respuesta HTML de inicio de sesión)
                    if (typeof data === 'string' && (data.indexOf('login.php') !== -1 || data.indexOf('<form') !== -1)) {
                        $('#seccionDestino').html('<option value="">Sesión expirada o no autorizado. Vuelva a iniciar sesión.</option>');
                        return;
                    }
                    $('#seccionDestino').html(data);
                },
                error: function() {
                    $('#seccionDestino').html('<option value="">Error al cargar secciones</option>');
                }
            });
        }

        cargarSeccionesDisponibles();
        
        $('#modalMoverRezagado').modal('show');
    });
    
    $('#btnConfirmarMover').click(function() {
        const seccionDestino = $('#seccionDestino').val();
        if (!seccionDestino) {
            alert('Seleccione una sección destino');
            return;
        }
        
        $.ajax({
            url: 'procesar_mover_estudiante.php',
            type: 'POST',
            data: {
                id_usuario: currentEstudiante.id,
                id_seccion_origen: currentEstudiante.seccion_origen_id,
                id_seccion_destino: seccionDestino
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                } catch(e) {
                    alert('Error al procesar la respuesta');
                }
            },
            error: function() {
                alert('Error al mover el estudiante');
            }
        });
    });
    
    $('#btnAvanzarSeccion').click(function() {
        const seccionId = $(this).data('seccion-id');
        const estudiantesAvanzan = $(this).data('estudiantes-avanzan');
        
        if (estudiantesAvanzan == 0) {
            alert('No hay estudiantes que cumplan los requisitos para avanzar');
            return;
        }
        
        if (confirm(`¿Está seguro de avanzar esta sección? Se moverán ${estudiantesAvanzan} estudiantes al siguiente trayecto.`)) {
            $.ajax({
                url: 'procesar_avance_seccion.php',
                type: 'POST',
                data: { id_seccion: seccionId },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.success) {
                            alert(data.message);
                            if (data.nueva_seccion_id) {
                                if (confirm('¿Desea ver la nueva sección creada?')) {
                                    const f = document.createElement('form');
                                    f.method = 'POST';
                                    f.action = 'ver_seccion.php';
                                    const inp = document.createElement('input');
                                    inp.type = 'hidden';
                                    inp.name = 'id';
                                    inp.value = data.nueva_seccion_id;
                                    f.appendChild(inp);
                                    document.body.appendChild(f);
                                    f.submit();
                                } else {
                                    location.reload();
                                }
                            } else {
                                location.reload();
                            }
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch(e) {
                        alert('Error al procesar la respuesta');
                    }
                },
                error: function() {
                    alert('Error al avanzar la sección');
                }
            });
        }
    });
});
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>