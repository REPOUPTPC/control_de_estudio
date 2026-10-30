<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

$titulopag = "Asignar Estudiantes";
require_once(__DIR__ . '/../../funciones/functions.php');

cargarPermisosUsuario();
verificarPermiso('secciones');
visita();

$id_seccion = (int)$_GET['id'] ?? 0;
if (!$id_seccion) header("Location: gestion_seccion.php");

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = asignarEstudiantes($db, $id_seccion, $_POST['estudiantes'] ?? []);
    if ($resultado['success']) {
        $_SESSION['success'] = $resultado['message'];
        if(isset($resultado['warning'])) $_SESSION['warning'] = $resultado['warning'];
        ?>
        <form id="redirAsig" method="POST" action="ver_seccion.php">
            <input type="hidden" name="id" value="<?= $id_seccion ?>">
        </form>
        <script>document.getElementById('redirAsig').submit();</script>
        <?php
        exit();
    } else $error_message = $resultado['message'];
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
        <form method="POST">
            <input type="hidden" id="capacidadMaxima" value="<?=$seccion['capacidad_maxima']?>">
            <div class="table-responsive"><table class="table table-bordered table-sm" id="tablaEstudiantes">
                <thead><tr><th width="30"><input type="checkbox" id="seleccionarTodos"></th><th>Nombre</th><th>Cédula</th><th>Asignado</th></tr></thead>
                <tbody><?php foreach($estudiantes as $e): ?>
                    <tr><td><input type="checkbox" name="estudiantes[]" value="<?=$e['id']?>" class="checkbox-estudiante" <?=in_array($e['id'],$asignados)?'checked':''?>></td>
                    <td><?=$e['nombre']?></td><td><?=$e['idusuario']?></td>
                    <td><?=in_array($e['id'],$asignados)?'<span class="badge badge-success">Sí</span>':'<span class="badge badge-secondary">No</span>'?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <div class="alert alert-info py-1 mt-2 small">Seleccionados: <span id="contador-seleccionados">0</span>/<?=$seccion['capacidad_maxima']?></div>
            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
        </form>
    </div></div>
</div>
<script>
$(document).ready(function(){
    var cap=parseInt($('#capacidadMaxima').val()), sel=$('.checkbox-estudiante:checked').length;
    $('#contador-seleccionados').text(sel);
    var table=$('#tablaEstudiantes').DataTable({"language":{"url":"//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json"},"autoWidth":false});
    function actualizar(){
        var total=0; table.rows().every(function(){if($(this.node()).find('.checkbox-estudiante').is(':checked')) total++;});
        $('#contador-seleccionados').text(total);
        if(total>=cap) $('.checkbox-estudiante:not(:checked)').prop('disabled',true);
        else $('.checkbox-estudiante').prop('disabled',false);
    }
    $(document).on('change','.checkbox-estudiante',function(){
        if($(this).is(':checked') && parseInt($('#contador-seleccionados').text())>=cap){
            $(this).prop('checked',false); alert('Capacidad máxima alcanzada'); return;
        } actualizar();
    });
    $('#seleccionarTodos').change(function(){
        if($(this).is(':checked') && $('.checkbox-estudiante').length>cap){$(this).prop('checked',false); alert('Supera capacidad máxima'); return;}
        table.rows().every(function(){$(this.node()).find('.checkbox-estudiante').prop('checked',$('#seleccionarTodos').is(':checked'));});
        actualizar();
    });
    actualizar();
});
</script>
<?php include(__DIR__ . '/../includes/footer.php'); ?>