<?php
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulopag = "Ver Sección";
require_once(__DIR__ . '/../../funciones/functions.php');

// Validar que la petición sea estrictamente por método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso no permitido - UPTPC</title>
        <link rel="stylesheet" href="../../css/bootstrap.min.css">
        <link rel="stylesheet" href="../../vendor/fontawesome-free/css/all.min.css">
        <style>
            body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            .error-card { max-width: 540px; margin: 80px auto; border: none; border-radius: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card error-card shadow p-4 text-center">
                <div class="text-danger mb-3">
                    <i class="fas fa-ban fa-4x text-danger opacity-75"></i>
                </div>
                <h4 class="text-dark font-weight-bold mb-2">Método de Acceso No Permitido</h4>
                <p class="text-muted mb-4">
                    Por motivos de seguridad e integridad de datos, la visualización de secciones no acepta solicitudes mediante el método <strong>GET</strong> (parámetros en la URL).
                </p>
                <div class="alert alert-light border small text-left text-secondary mb-4">
                    <i class="fas fa-shield-alt text-primary mr-1"></i> Esta operación debe ser solicitada mediante el método seguro <strong>POST</strong> desde el panel de Gestión de Secciones.
                </div>
                <div>
                    <a href="gestion_seccion.php" class="btn btn-primary px-4 font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i> Ir a Gestión de Secciones
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

cargarPermisosUsuario();
verificarPermiso('secciones');
visita();

$id_seccion = (int)($_POST['id'] ?? ($_POST['id_seccion'] ?? 0));
if (!$id_seccion) {
    header("Location: gestion_seccion.php");
    exit();
}

$seccion = obtenerDetalleSeccion($db, $id_seccion);
$estudiantes = obtenerEstudiantesDeSeccion($db, $id_seccion);
$horarios = obtenerHorariosSeccion($db, $id_seccion);

$estudiantes_inscritos = count($estudiantes);
$faltan_para_activar = max(0, MINIMO_ESTUDIANTES - $estudiantes_inscritos);
$seccion_llena = ($estudiantes_inscritos >= $seccion['capacidad_maxima']);
$periodo_inactivo = ($seccion['periodo_activo'] == 0);

$ya_inicio = false;
if (isset($seccion['inicia']) && !empty($seccion['inicia'])) {
    $fecha_inicio = new DateTime($seccion['inicia']);
    $fecha_actual = new DateTime();
    $ya_inicio = ($fecha_actual >= $fecha_inicio);
}

if ($periodo_inactivo) $estado_texto = 'Período Inactivo';
else if ($ya_inicio) $estado_texto = 'Activa (Ya inició)';
else $estado_texto = $seccion['estatus'] == 'activa' ? 'Activa' : 'Inactiva';

include(__DIR__ . '/../includes/head.php');
?>

<style>
.btn-retirar{cursor:pointer}
.acciones-botones .btn { margin-bottom: 5px; }
@media (max-width: 768px) {
    .acciones-botones .btn { width: 100%; }
}
</style>

<div class="modal fade" id="confirmarRetiroModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Confirmar Retiro</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body" id="modalConfirmacionBody">¿Está seguro de retirar este estudiante?</div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
            <form method="post" action="procesar_retiro.php">
                <input type="hidden" name="id_usuario" id="modalIdUsuario">
                <input type="hidden" name="id_seccion" value="<?= $id_seccion ?>">
                <button type="submit" class="btn btn-danger btn-sm">Confirmar</button>
            </form>
        </div>
    </div></div>
</div>

<!-- Modal Detalle Estudiante -->
<div class="modal fade" id="detalleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold mb-0">
                    <i class="fas fa-id-card mr-2"></i> Detalles del Estudiante
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div id="detalleEstudianteContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Cargando detalles del estudiante...</p>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid py-2">
    <div class="row mb-2">
        <div class="col-12 d-flex justify-content-between flex-wrap">
            <h2 class="h4 mb-2">Detalles de Sección</h2>
            <a href="gestion_seccion.php" class="btn btn-secondary btn-sm">← Volver</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-2">
                <div class="card-header py-1"><h6 class="m-0">Información General</h6></div>
                <div class="card-body py-2 small">
                    <p class="mb-1"><strong>Código:</strong> <?= $seccion['codigo_seccion'] ?></p>
                    <p class="mb-1"><strong>Carrera:</strong> <?= $seccion['nombre_carrera'] ?></p>
                    <p class="mb-1"><strong>Trayecto:</strong> <?= $seccion['numero_trayecto'] ?></p>
                    <p class="mb-1"><strong>Período:</strong> <?= $seccion['nombre_periodo'] ?></p>
                    <p class="mb-1"><strong>Turno:</strong> <?= $seccion['turno'] ?></p>
                    <p class="mb-1"><strong>Inicio:</strong> <?= isset($seccion['inicia']) ? date('d/m/Y H:i', strtotime($seccion['inicia'])) : 'No definido' ?></p>
                    <p class="mb-1"><strong>Capacidad:</strong> <?= $estudiantes_inscritos ?>/<?= $seccion['capacidad_maxima'] ?></p>
                    <p><strong>Estado:</strong> <span class="badge badge-secondary"><?= $estado_texto ?></span></p>
                </div>
            </div>
            
            <div class="card shadow">
                <div class="card-header py-1"><h6 class="m-0">Acciones</h6></div>
                <div class="card-body py-2 acciones-botones">
                    <form method="POST" action="editar_seccion.php" class="mb-1">
                        <input type="hidden" name="id" value="<?= $id_seccion ?>">
                        <button type="submit" class="btn btn-primary btn-sm btn-block">
                            <i class="fas fa-edit mr-1"></i> Editar Sección
                        </button>
                    </form>
                    
                    <?php if (!$periodo_inactivo): ?>
                        <a href="asignar_estudiantes.php?id=<?= $id_seccion ?>" class="btn btn-warning btn-sm btn-block mb-1">Asignar Estudiantes</a>
                    <?php endif; ?>
                    
                    <a href="horario_seccion.php?id=<?= $id_seccion ?>" class="btn btn-info btn-sm btn-block mb-1">Ver Horario Semanal</a>
                    
                    <!-- Botón para evaluar avance de trayectos -->
                    <a href="avance_trayectos.php?id=<?= $id_seccion ?>" class="btn btn-success btn-sm btn-block mb-1">
                        <i class="fas fa-forward"></i> Evaluar Avance de Trayectos
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="card shadow mb-2">
                <div class="card-header py-1"><h6 class="m-0">Horarios de la Sección</h6></div>
                <div class="card-body py-2">
                    <?php if (!empty($horarios)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm small">
                            <thead><tr><th>Día</th><th>Hora Inicio</th><th>Hora Fin</th><th>Aula</th><th>Materia</th><th>Docente</th></tr></thead>
                            <tbody>
                                <?php foreach ($horarios as $h): ?>
                                <tr>
                                    <td><?= $h['dia_nombre'] ?></td>
                                    <td><?= date('H:i', strtotime($h['hora_inicio'])) ?></td>
                                    <td><?= date('H:i', strtotime($h['hora_fin'])) ?></td>
                                    <td><?= $h['aula'] ?></td>
                                    <td><?= $h['nombre_materia'] ?></td>
                                    <td><?= $h['nombre_docente'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-info py-1 mb-0 small">No hay horarios definidos.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow">
                <div class="card-header py-1 d-flex justify-content-between flex-wrap">
                    <h6 class="m-0">Estudiantes Asignados</h6>
                    <span class="badge badge-primary"><?= $estudiantes_inscritos ?></span>
                </div>
                <div class="card-body py-2">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm small" id="tablaEstudiantes">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Cédula</th>
                                    <th>Fecha Inscripción</th>
                                    <th style="min-width: 190px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['nombre']) ?></td>
                                    <td><?= htmlspecialchars($e['idusuario']) ?></td>
                                    <td><?= $e['fecha_inscripcion'] ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-info btn-sm btn-ver-detalle mr-1" 
                                                data-id="<?= $e['id'] ?>" 
                                                data-nombre="<?= htmlspecialchars($e['nombre']) ?>"
                                                title="Ver más detalles del estudiante">
                                            <i class="fas fa-eye"></i> Ver Detalles
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm btn-retirar" 
                                                data-id="<?= $e['id'] ?>" 
                                                data-nombre="<?= htmlspecialchars($e['nombre']) ?>"
                                                title="Retirar estudiante">
                                            <i class="fas fa-user-minus"></i> Retirar
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($estudiantes)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No hay estudiantes asignados a esta sección</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $(document).on('click', '.btn-ver-detalle', function(e){
        e.preventDefault();
        var studentId = $(this).data('id');
        var modalContent = document.getElementById('detalleEstudianteContent');
        modalContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Cargando detalles del estudiante...</p></div>';
        $('#detalleModal').modal('show');
        
        var formData = new FormData();
        formData.append('id', studentId);
        formData.append('admin_prefix', '../');
        
        fetch('../detalle_estudiante.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response){
            if (!response.ok) {
                throw new Error('Error al cargar la información (código ' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html){
            modalContent.innerHTML = html;
        })
        .catch(function(error){
            modalContent.innerHTML = '<div class="alert alert-danger m-3"><i class="fas fa-exclamation-triangle mr-2"></i>' + error.message + '</div>';
        });
    });

    $(document).on('click', '.btn-retirar', function(){
        $('#modalIdUsuario').val($(this).data('id'));
        $('#modalConfirmacionBody').html('¿Está seguro de retirar a <strong>'+$(this).data('nombre')+'</strong> de la sección?');
        $('#confirmarRetiroModal').modal('show');
    });
    
    // Inicializar DataTable para mejor visualización (opcional)
    if ($('#tablaEstudiantes tbody tr').length > 5) {
        $('#tablaEstudiantes').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json"
            },
            "pageLength": 10,
            "autoWidth": false
        });
    }
});
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>