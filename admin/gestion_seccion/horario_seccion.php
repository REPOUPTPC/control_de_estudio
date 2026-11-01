<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

$titulopag = "Horario de Sección";
require_once(__DIR__ . '/../../funciones/functions.php');

cargarPermisosUsuario();
verificarPermiso('secciones');
visita();

$id_seccion = (int)$_GET['id'] ?? 0;
if (!$id_seccion) header("Location: gestion_seccion.php");

$seccion = obtenerDetalleSeccion($db, $id_seccion);
$horarios = obtenerHorariosSeccion($db, $id_seccion);
$horarios = is_array($horarios) ? $horarios : [];

include(__DIR__ . '/../includes/head.php');
?>

<style>
.tabla-horario-mejorada{table-layout:fixed;border-collapse:collapse;border-radius:8px;overflow:hidden;border:1px solid #6c757d;width:100%}
.tabla-horario-mejorada thead th{background-color:#2c3e50;color:#fff;text-transform:uppercase;font-size:.65rem;border:1px solid #495057;padding:4px}
.tabla-horario-mejorada tbody td{border:1px solid #6c757d}
.hora-col-mejorada{background-color:#f8f9fa;font-weight:bold;width:55px;text-align:center;padding:4px;font-size:.65rem;border:1px solid #6c757d}
.materia-container-mejorada{padding:2px!important;vertical-align:middle}
.bloque-clase-mejorada{background-color:#e8f5e9;color:#2e7d32;border:1px solid #388e3c;border-radius:4px;padding:3px}
.materia-nombre-mejorada{font-weight:800;font-size:.6rem;text-transform:uppercase}
.docente-nombre-mejorada{font-size:.55rem}
.aula-tag-mejorada{font-size:.5rem;background:#fff8;padding:1px 3px;border-radius:3px}

/* Estilos para impresión - UNA SOLA HOJA */
@media print {
    .no-print, .btn, .mb-2, .card-header, .navbar, .container > .row, #mobileNavbarBackdrop {
        display: none !important;
    }
    @page {
        size: landscape;
        margin: 0.2cm;
    }
    body {
        margin: 0;
        padding: 0;
        background: white;
    }
    .container-fluid {
        padding: 0 !important;
        margin: 0 !important;
    }
    .card {
        margin: 0 !important;
        border: none !important;
        box-shadow: none !important;
    }
    .card-body {
        padding: 0 !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
    .tabla-horario-mejorada {
        width: 100%;
        border-collapse: collapse;
    }
    .tabla-horario-mejorada th,
    .tabla-horario-mejorada td {
        padding: 3px !important;
        font-size: 0.55rem !important;
    }
    .hora-col-mejorada {
        width: 45px !important;
    }
    .materia-nombre-mejorada {
        font-size: 0.5rem !important;
    }
    .docente-nombre-mejorada {
        font-size: 0.45rem !important;
    }
    .aula-tag-mejorada {
        font-size: 0.4rem !important;
    }
    footer, .footer {
        display: none !important;
    }
}
</style>

<div class="container-fluid py-2">
    <div class="row mb-2 no-print">
        <div class="col-12 d-flex justify-content-between">
            <h2 class="h4 mb-0">Horario Semanal - <?= $seccion['codigo_seccion'] ?></h2>
            <div class="d-flex align-items-center">
                <button onclick="window.print();" class="btn btn-success btn-sm mr-1">Imprimir</button>
                <form method="POST" action="ver_seccion.php" class="d-inline m-0">
                    <input type="hidden" name="id" value="<?= $id_seccion ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">← Volver</button>
                </form>
            </div>
        </div>
    </div>
    <div class="card shadow">
        <div class="card-header py-1 no-print">
            <h6 class="m-0">Horario de Clases - Turno <?= $seccion['turno'] ?></h6>
        </div>
        <div class="card-body py-2">
            <?php if(empty($horarios)): ?>
                <div class="alert alert-info py-1 mb-0 small">No hay horarios definidos.</div>
            <?php else: 
                $dias_semana = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                $turno_seccion = $seccion['turno'] ?? 'Diurno';
                
                function horaToNum($h) {
                    return (int)substr($h,0,2) + (int)substr($h,3,2)/60;
                }
                
                $hora_min = 24;
                $hora_max = 0;
                foreach($horarios as $h) {
                    $h_ini = horaToNum($h['hora_inicio']);
                    $h_fin = horaToNum($h['hora_fin']);
                    if($h_ini < $hora_min) $hora_min = $h_ini;
                    if($h_fin > $hora_max) $hora_max = $h_fin;
                }
                
                if($turno_seccion == 'Diurno') {
                    $hay_clases_fuera = false;
                    foreach($horarios as $h) {
                        $h_ini = horaToNum($h['hora_inicio']);
                        $h_fin = horaToNum($h['hora_fin']);
                        if($h_ini < 7 || $h_fin > 17.5) {
                            $hay_clases_fuera = true;
                            break;
                        }
                    }
                    if($hay_clases_fuera) {
                        $inicio = max(7, floor($hora_min));
                        $fin = min(20, ceil($hora_max));
                    } else {
                        $inicio = 7;
                        $fin = 17;
                    }
                } else {
                    $hay_clases_fuera = false;
                    foreach($horarios as $h) {
                        $h_ini = horaToNum($h['hora_inicio']);
                        if($h_ini < 17.5) {
                            $hay_clases_fuera = true;
                            break;
                        }
                    }
                    if($hay_clases_fuera) {
                        $inicio = max(7, floor($hora_min));
                        $fin = min(20, ceil($hora_max));
                    } else {
                        $inicio = 17;
                        $fin = 20;
                    }
                }
                
                $horas_tabla = [];
                for($h=$inicio; $h<=$fin; $h++) {
                    $horas_tabla[] = sprintf("%02d:00", $h);
                    if($h < $fin) {
                        $horas_tabla[] = sprintf("%02d:30", $h);
                    }
                }
                
                $horarios_por_dia = array_fill(0,6,[]);
                foreach($horarios as $h){$dia=(int)$h['dia'];$horarios_por_dia[$dia][]=$h;}
            ?>
            <div class="table-responsive">
                <table class="table table-bordered text-center tabla-horario-mejorada">
                    <thead>
                        <tr>
                            <th class="hora-col-mejorada">HORA</th>
                            <?php foreach($dias_semana as $dia) echo "<th>$dia</th>"; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $skip_cells = []; 
                        foreach($horas_tabla as $hora): 
                        ?>
                            <tr>
                                <td class="hora-col-mejorada"><?=$hora?></td>
                                <?php for($dia=0;$dia<=5;$dia++):
                                    if(isset($skip_cells[$dia][$hora])) continue;
                                    $clase=null;
                                    foreach($horarios_por_dia[$dia] as $c){
                                        if($hora>=$c['hora_inicio'] && $hora<$c['hora_fin']){
                                            $clase=$c;
                                            break;
                                        }
                                    }
                                    if($clase):
                                        $h_ini=strtotime($hora);
                                        $h_fin=strtotime($clase['hora_fin']);
                                        $rowspan=($h_fin-$h_ini)/1800;
                                        $temp=$h_ini;
                                        for($i=1;$i<$rowspan;$i++){
                                            $temp+=1800;
                                            $skip_cells[$dia][date('H:i',$temp)]=true;
                                        }
                                ?>
                                        <td rowspan="<?=$rowspan?>" class="materia-container-mejorada">
                                            <div class="bloque-clase-mejorada">
                                                <div class="materia-nombre-mejorada"><?=htmlspecialchars($clase['nombre_materia'])?></div>
                                                <div class="docente-nombre-mejorada"><?=htmlspecialchars($clase['nombre_docente'])?></div>
                                                <div><span class="aula-tag-mejorada">📖 <?=htmlspecialchars($clase['aula'])?></span></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <td class="bg-light"><?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-2">
                <h6 class="small font-weight-bold">Detalle de Materias</h6>
                <div class="row">
                    <?php 
                    $unique=[]; 
                    foreach($horarios as $item){
                        $key=$item['nombre_materia']; 
                        if(!isset($unique[$key])) $unique[$key]=$item;
                    }
                    foreach($unique as $item): 
                    ?>
                    <div class="col-12 col-md-6 col-lg-4 mb-1">
                        <div class="border p-1 rounded small">
                            <strong><?=htmlspecialchars($item['nombre_materia'])?></strong><br>
                            <small>📅 <?=$dias_semana[$item['dia']]?> | 🕒 <?=date('H:i',strtotime($item['hora_inicio']))?>-<?=date('H:i',strtotime($item['hora_fin']))?><br>
                            👨‍🏫 <?=htmlspecialchars($item['nombre_docente'])?> | 📖 <?=htmlspecialchars($item['aula'])?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../includes/footer.php'); ?>