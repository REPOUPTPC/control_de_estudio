<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulopag = "Asignar Estudiantes";
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
                    Por motivos de seguridad e integridad de datos, la asignación de estudiantes a secciones no acepta solicitudes mediante el método <strong>GET</strong> (parámetros en la URL).
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
$estudiantes = obtenerEstudiantesDisponibles($db, $id_seccion, $seccion['id_carrera']);
$asignados = obtenerEstudiantesAsignados($db, $id_seccion);

$seccion_llena = ($seccion['inscritos'] >= $seccion['capacidad_maxima']);
$periodo_inactivo = ($seccion['periodo_activo'] == 0);

if ($periodo_inactivo) { 
    $_SESSION['error'] = "Período inactivo"; 
    ?>
    <form id="redirPeriodo" method="POST" action="ver_seccion.php">
        <input type="hidden" name="id" value="<?= $id_seccion ?>">
    </form>
    <script>document.getElementById('redirPeriodo').submit();</script>
    <?php
    exit(); 
}

if (isset($_POST['guardar_asignacion'])) {
    $resultado = asignarEstudiantes($db, $id_seccion, $_POST['estudiantes'] ?? []);
    if ($resultado['success']) {
        $_SESSION['success'] = $resultado['message'];
        if (isset($resultado['warning'])) $_SESSION['warning'] = $resultado['warning'];
        ?>
        <form id="redirAsig" method="POST" action="ver_seccion.php">
            <input type="hidden" name="id" value="<?= $id_seccion ?>">
        </form>
        <script>document.getElementById('redirAsig').submit();</script>
        <?php
        exit();
    } else {
        $error_message = $resultado['message'];
    }
}

include(__DIR__ . '/../includes/head.php');
?>

<div class="container-fluid py-2">
    <div class="row mb-2"><div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="h4 mb-0">Asignar Estudiantes</h2>
        <form method="POST" action="ver_seccion.php" class="d-inline m-0">
            <input type="hidden" name="id" value="<?= $id_seccion ?>">
            <button type="submit" class="btn btn-secondary btn-sm">← Volver</button>
        </form>
    </div></div>
    <?php if(isset($error_message)) echo '<div class="alert alert-danger py-1">'.$error_message.'</div>'; ?>
    <div class="card shadow"><div class="card-header py-1 d-flex justify-content-between">
        <h6 class="m-0">Sección: <?=$seccion['codigo_seccion']?> - <?=$seccion['nombre_carrera']?></h6>
        <span class="badge badge-info">Cupos: <?=$seccion['inscritos']?>/<?=$seccion['capacidad_maxima']?></span>
    </div><div class="card-body py-2">
        <?php if($seccion_llena): ?><div class="alert alert-warning py-1 small">Sección llena.</div><?php endif; ?>

        <!-- Buscador de Estudiantes en Tiempo Real -->
        <div class="bg-light p-3 rounded mb-3 border">
            <div class="position-relative">
                <label for="buscador_estudiante" class="font-weight-bold text-gray-800 small text-uppercase mb-1">
                    <i class="fas fa-search text-primary mr-1"></i> Buscar Estudiante (Cédula o Nombre):
                </label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white border-right-0"><i class="fas fa-user-graduate text-muted"></i></span>
                    </div>
                    <input type="text" 
                           id="buscador_estudiante" 
                           class="form-control border-left-0 font-weight-bold" 
                           placeholder="Escriba la cédula o nombre para filtrar la lista en tiempo real..." 
                           autocomplete="off">
                    <div class="input-group-append">
                        <button type="button" id="btn_limpiar_buscador" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1 text-info"></i> La lista de estudiantes se filtra automáticamente mientras escribe.
                    </small>
                    <small class="text-muted font-weight-bold">
                        Mostrando <span id="contador-visibles"><?= count($estudiantes) ?></span> de <?= count($estudiantes) ?> estudiantes
                    </small>
                </div>
            </div>
        </div>

        <form method="POST" action="asignar_estudiantes.php" id="formAsignarEstudiantes">
            <input type="hidden" name="guardar_asignacion" value="1">
            <input type="hidden" name="id" value="<?= $id_seccion ?>">
            <input type="hidden" id="capacidadMaxima" value="<?=$seccion['capacidad_maxima']?>">
            <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                <table class="table table-bordered table-hover table-sm mb-0" id="tablaEstudiantes">
                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th width="40" class="text-center">
                                <input type="checkbox" id="seleccionarTodos" title="Seleccionar/Deseleccionar todos los visibles">
                            </th>
                            <th>Nombre y Apellido</th>
                            <th width="150">Cédula</th>
                            <th width="120" class="text-center">Asignado</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTablaEstudiantes">
                        <?php if (empty($estudiantes)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="fas fa-user-slash mr-1"></i> No hay estudiantes registrados para esta carrera.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($estudiantes as $e): 
                                $es_asignado = in_array($e['id'], $asignados);
                                $texto_busqueda = strtolower(trim(($e['nombre'] ?? '') . ' ' . ($e['idusuario'] ?? '')));
                            ?>
                                <tr class="fila-estudiante" data-busqueda="<?= htmlspecialchars($texto_busqueda) ?>" style="cursor: pointer;">
                                    <td class="text-center" style="vertical-align: middle;">
                                        <input type="checkbox" name="estudiantes[]" value="<?=$e['id']?>" class="checkbox-estudiante" <?=$es_asignado ? 'checked' : ''?>>
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <div class="font-weight-bold text-dark">
                                            <i class="fas fa-user-graduate text-muted mr-1"></i> <?= htmlspecialchars($e['nombre']) ?>
                                        </div>
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <span class="badge badge-light border text-dark font-weight-bold px-2 py-1"><?= htmlspecialchars($e['idusuario']) ?></span>
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        <?= $es_asignado ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i> Sí</span>' : '<span class="badge badge-secondary px-2 py-1">No</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="filaSinResultados" style="display: none;">
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="fas fa-search fa-2x text-muted mb-2 d-block"></i>
                                    No se encontraron estudiantes que coincidan con la búsqueda.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 p-2 bg-light rounded border">
                <div>
                    <span class="font-weight-bold text-gray-800">Seleccionados:</span> 
                    <span class="badge badge-primary px-2 py-1" style="font-size: 0.95rem;">
                        <span id="contador-seleccionados">0</span> / <?= $seccion['capacidad_maxima'] ?> cupos
                    </span>
                </div>
                <button type="submit" class="btn btn-primary btn-sm px-4 font-weight-bold">
                    <i class="fas fa-save mr-1"></i> Guardar Asignación
                </button>
            </div>
        </form>
    </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var buscador = document.getElementById('buscador_estudiante');
    var btnLimpiar = document.getElementById('btn_limpiar_buscador');
    var filas = Array.prototype.slice.call(document.querySelectorAll('.fila-estudiante'));
    var filaSinResultados = document.getElementById('filaSinResultados');
    var contadorVisibles = document.getElementById('contador-visibles');
    var contadorSeleccionados = document.getElementById('contador-seleccionados');
    var capMaxima = parseInt(document.getElementById('capacidadMaxima').value, 10) || 0;
    var chkSeleccionarTodos = document.getElementById('seleccionarTodos');

    function actualizarContadorSeleccionados() {
        var checkboxes = document.querySelectorAll('.checkbox-estudiante');
        var seleccionados = 0;
        checkboxes.forEach(function(chk) {
            if (chk.checked) seleccionados++;
        });

        if (contadorSeleccionados) {
            contadorSeleccionados.textContent = seleccionados;
        }

        // Si se alcanzó o superó la capacidad máxima, deshabilitar los que no están marcados
        checkboxes.forEach(function(chk) {
            if (!chk.checked) {
                chk.disabled = (seleccionados >= capMaxima);
            } else {
                chk.disabled = false;
            }
        });

        // Actualizar el estado de "seleccionar todos" respecto a las filas visibles
        if (chkSeleccionarTodos) {
            var visiblesCheckboxes = [];
            filas.forEach(function(fila) {
                if (fila.style.display !== 'none') {
                    var chk = fila.querySelector('.checkbox-estudiante');
                    if (chk) visiblesCheckboxes.push(chk);
                }
            });

            if (visiblesCheckboxes.length > 0) {
                var todosMarcados = visiblesCheckboxes.every(function(chk) { return chk.checked; });
                chkSeleccionarTodos.checked = todosMarcados;
            } else {
                chkSeleccionarTodos.checked = false;
            }
        }

        return seleccionados;
    }

    function filtrarEstudiantes() {
        var termino = buscador ? buscador.value.toLowerCase().trim() : '';
        var visibles = 0;

        filas.forEach(function(fila) {
            var busquedaTexto = fila.getAttribute('data-busqueda') || fila.textContent.toLowerCase();
            if (termino === '' || busquedaTexto.indexOf(termino) !== -1) {
                fila.style.display = '';
                visibles++;
            } else {
                fila.style.display = 'none';
            }
        });

        if (contadorVisibles) {
            contadorVisibles.textContent = visibles;
        }

        if (filaSinResultados) {
            filaSinResultados.style.display = (visibles === 0 && filas.length > 0) ? '' : 'none';
        }

        actualizarContadorSeleccionados();
    }

    if (buscador) {
        buscador.addEventListener('input', filtrarEstudiantes);
        buscador.addEventListener('keyup', filtrarEstudiantes);
        buscador.addEventListener('change', filtrarEstudiantes);
        buscador.addEventListener('search', filtrarEstudiantes);
    }

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function() {
            if (buscador) {
                buscador.value = '';
                filtrarEstudiantes();
                buscador.focus();
            }
        });
    }

    // Delegación de eventos para checkboxes de estudiantes
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('checkbox-estudiante')) {
            var marcados = document.querySelectorAll('.checkbox-estudiante:checked').length;
            if (e.target.checked && marcados > capMaxima) {
                e.target.checked = false;
                alert('Capacidad máxima de la sección alcanzada (' + capMaxima + ' cupos).');
            }
            actualizarContadorSeleccionados();
        }
    });

    // Evento para "Seleccionar Todos"
    if (chkSeleccionarTodos) {
        chkSeleccionarTodos.addEventListener('change', function() {
            var marcar = this.checked;
            var seleccionadosActuales = document.querySelectorAll('.checkbox-estudiante:checked').length;

            filas.forEach(function(fila) {
                if (fila.style.display !== 'none') {
                    var chk = fila.querySelector('.checkbox-estudiante');
                    if (chk) {
                        if (marcar) {
                            if (!chk.checked) {
                                if (seleccionadosActuales >= capMaxima) {
                                    alert('Se alcanzó la capacidad máxima (' + capMaxima + ' cupos). No se pueden asignar más estudiantes.');
                                    return;
                                }
                                chk.checked = true;
                                seleccionadosActuales++;
                            }
                        } else {
                            if (chk.checked) {
                                chk.checked = false;
                                seleccionadosActuales--;
                            }
                        }
                    }
                }
            });

            actualizarContadorSeleccionados();
        });
    }

    // Clic en la fila para marcar/desmarcar con facilidad
    filas.forEach(function(fila) {
        fila.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
                return;
            }
            var chk = fila.querySelector('.checkbox-estudiante');
            if (chk && !chk.disabled) {
                var seleccionadosActuales = document.querySelectorAll('.checkbox-estudiante:checked').length;
                if (!chk.checked && seleccionadosActuales >= capMaxima) {
                    alert('Capacidad máxima de la sección alcanzada (' + capMaxima + ' cupos).');
                    return;
                }
                chk.checked = !chk.checked;
                actualizarContadorSeleccionados();
            }
        });
    });

    // Iniciar contadores
    actualizarContadorSeleccionados();
});
</script>
<?php include(__DIR__ . '/../includes/footer.php'); ?>