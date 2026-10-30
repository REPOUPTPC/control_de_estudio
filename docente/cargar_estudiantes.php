<?php
require_once('../funciones/functions.php');

// Verificar autenticación y rol
if (!isLoggedIn() || !isDocente()) {
    $_SESSION['msg'] = "Debes iniciar sesión como docente para acceder";
    header('location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notas'])) {
    procesarNotasEstudiantes();
}

if (!isset($_POST['seccion_id']) || !isset($_POST['materia_id'])) {
    die('Parámetros incompletos');
}

$seccion_id = (int)$_POST['seccion_id'];
$materia_id = (int)$_POST['materia_id'];

$materia = obtenerInfoMateria($materia_id);
$estudiantes = obtenerEstudiantesPorSeccion($seccion_id);
$periodo_id = obtenerPeriodoSeccion($seccion_id);
$trayecto_seccion = obtenerTrayectoSeccion($seccion_id);

if (!$materia) die('Error: Materia no encontrada');
if (!$estudiantes) die('No hay estudiantes en esta sección');

$trayecto_actual = $trayecto_seccion['numero_trayecto'];
$id_trayecto_seccion = $trayecto_seccion['id_trayecto'];
$trayecto_a_mostrar = determinarTrayectoAMostrar($id_trayecto_seccion);
$docente_id = obtenerIdUsuario();
$estados_notas = verificarEstadosNotas($estudiantes, $materia_id, $periodo_id, $docente_id, $trayecto_a_mostrar);
$mostrar_campo_soporte = true;

// Verificar disponibilidad de los trimestres para este período académico
$disponibilidad_trimestres = [];
for ($t = 1; $t <= 3; $t++) {
    $disponibilidad_trimestres[$t] = verificarDisponibilidadTrimestre($t, $periodo_id, $docente_id, $seccion_id, $materia_id);
}
$disponibilidad_json = json_encode($disponibilidad_trimestres);
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $_SESSION['success'] ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $_SESSION['error'] ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-info text-white">
        <h5>Estudiantes - <?= htmlspecialchars($materia['nombre_materia']) ?></h5>
        <p class="mb-0">Trayecto <?= $trayecto_actual ?> | Periodo ID: <?= $periodo_id ?></p>
    </div>
    <div class="card-body">
        
        <?php foreach ($disponibilidad_trimestres as $tNum => $disp): ?>
            <?php if (!empty($disp['prorroga'])): ?>
            <div class="alert alert-warning border border-warning shadow-sm">
                <i class="fas fa-clock mr-1"></i>
                <strong>¡Prórroga de 24 Horas Habilitada por Control de Estudios!</strong> <?= htmlspecialchars($disp['mensaje']) ?>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if ($estados_notas['notas_aprobadas']): ?>
        <div class="alert alert-success">
            <strong>✅ Notas Aprobadas:</strong> Algunas notas ya fueron aprobadas y no pueden ser modificadas.
        </div>
        <?php endif; ?>
        
        <?php if ($estados_notas['notas_rechazadas']): ?>
        <div class="alert alert-danger">
            <strong>❌ Notas Rechazadas:</strong> Algunas notas fueron rechazadas y necesitan corrección.
        </div>
        <?php endif; ?>
        
        <?php if ($estados_notas['notas_en_revision']): ?>
        <div class="alert alert-warning">
            <strong>⏳ Notas en Revisión:</strong> Algunas notas están siendo revisadas por los administradores. No pueden ser modificadas.
        </div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong>Estados:</strong><br>
            • <span class="badge badge-secondary">Pendiente</span> - No se ha subido la nota<br>
            • <span class="badge badge-warning">En Revisión</span> - En revisión por administradores (NO MODIFICABLE)<br>
            • <span class="badge badge-success">Aprobada</span> - No se puede modificar<br>
            • <span class="badge badge-danger">Rechazada</span> - Puede corregir y reenviar
        </div>
        
        <form id="form-notas" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="materia_id" value="<?= $materia_id ?>">
            <input type="hidden" name="seccion_id" value="<?= $seccion_id ?>">
            <input type="hidden" name="periodo_id" value="<?= $periodo_id ?>">
            <input type="hidden" name="trayecto_actual" value="<?= $trayecto_actual ?>">
            <input type="hidden" name="id_trayecto_seccion" value="<?= $id_trayecto_seccion ?>">
            <input type="hidden" name="docente_id" value="<?= $docente_id ?>">
            
            <!-- SOPORTE OBLIGATORIO -->
            <div class="card mb-3">
                <div class="card-header bg-warning text-dark">
                    <h6><i class="fas fa-paperclip"></i> Soporte del Grupo <span class="text-danger">*</span></h6>
                </div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label for="soporte_grupo" class="font-weight-bold">
                            <i class="fas fa-file-upload mr-1"></i> Seleccionar Imagen o PDF de Soporte (OBLIGATORIO):
                        </label>
                        <div class="custom-file">
                            <input type="file" name="soporte_grupo" id="soporte_grupo"
                                   class="custom-file-input soporte-grupo" 
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,application/pdf,image/*" required>
                            <label class="custom-file-label" id="label-soporte-grupo" for="soporte_grupo">Elegir archivo PDF o Imagen...</label>
                        </div>
                        <small class="form-text text-muted mt-1">
                            <i class="fas fa-info-circle mr-1"></i> Formatos permitidos: <strong>PDF, JPG, PNG, WEBP</strong>. Máximo 5MB. <strong class="text-danger">* Obligatorio para guardar</strong>
                        </small>
                    </div>
                    
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-eye mr-1"></i> Vista Previa del Documento:</span>
                            <span id="info-archivo-soporte" class="small text-muted font-weight-normal"></span>
                        </label>
                        <div id="preview-grupo" class="p-2 border rounded bg-light text-center" style="min-height: 80px; transition: all 0.3s ease;">
                            <div class="py-3 text-muted">
                                <i class="fas fa-cloud-upload-alt fa-2x mb-1 text-secondary"></i>
                                <p class="mb-0 small">No se ha seleccionado ningún archivo de soporte aún.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th class="text-center">
                                T1
                                <?php if (!$disponibilidad_trimestres[1]['disponible']): ?>
                                    <i class="fas fa-lock text-danger" title="<?= htmlspecialchars($disponibilidad_trimestres[1]['mensaje']) ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-check-circle text-success" title="<?= htmlspecialchars($disponibilidad_trimestres[1]['mensaje']) ?>"></i>
                                <?php endif; ?>
                            </th>
                            <th class="text-center">
                                T2
                                <?php if (!$disponibilidad_trimestres[2]['disponible']): ?>
                                    <i class="fas fa-lock text-danger" title="<?= htmlspecialchars($disponibilidad_trimestres[2]['mensaje']) ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-check-circle text-success" title="<?= htmlspecialchars($disponibilidad_trimestres[2]['mensaje']) ?>"></i>
                                <?php endif; ?>
                            </th>
                            <th class="text-center">
                                T3
                                <?php if (!$disponibilidad_trimestres[3]['disponible']): ?>
                                    <i class="fas fa-lock text-danger" title="<?= htmlspecialchars($disponibilidad_trimestres[3]['mensaje']) ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-check-circle text-success" title="<?= htmlspecialchars($disponibilidad_trimestres[3]['mensaje']) ?>"></i>
                                <?php endif; ?>
                            </th>
                            <th class="text-center">Promedio</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estados_notas['estudiantes_info'] as $info): 
                            $estudiante = $info['datos'];
                            $estado_t1 = $info['trimestre_1_estado'] ?? 'pendiente';
                            $estado_t2 = $info['trimestre_2_estado'] ?? 'pendiente';
                            $estado_t3 = $info['trimestre_3_estado'] ?? 'pendiente';
                            
                            // Convertir a entero para quitar decimales (.00)
                            $valor_t1 = $info['trimestre_1_nota'] !== '' ? (int)$info['trimestre_1_nota'] : '';
                            $valor_t2 = $info['trimestre_2_nota'] !== '' ? (int)$info['trimestre_2_nota'] : '';
                            $valor_t3 = $info['trimestre_3_nota'] !== '' ? (int)$info['trimestre_3_nota'] : '';
                            
                            $readonly_t1 = ($estado_t1 === 'aprobada' || $estado_t1 === 'en_revision');
                            $readonly_t2 = ($estado_t2 === 'aprobada' || $estado_t2 === 'en_revision');
                            $readonly_t3 = ($estado_t3 === 'aprobada' || $estado_t3 === 'en_revision');
                            
                            $disabled_t1 = $readonly_t1 || !$disponibilidad_trimestres[1]['disponible'];
                            $disabled_t2 = $readonly_t2 || !$disponibilidad_trimestres[2]['disponible'];
                            $disabled_t3 = $readonly_t3 || !$disponibilidad_trimestres[3]['disponible'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($estudiante['idusuario']) ?></td>
                                <td><?= htmlspecialchars($estudiante['nombre']) ?></td>
                                
                                <td class="text-center">
                                    <input type="number" 
                                           name="notas[<?= $estudiante['id'] ?>][trimestre_1]" 
                                           class="form-control nota-input text-center trimestre-input" 
                                           data-trimestre="1"
                                           data-original="<?= $valor_t1 ?>"
                                           min="1" max="20" step="1"
                                           value="<?= $valor_t1 ?>"
                                           style="width: 80px; margin: 0 auto;"
                                           onchange="marcarCambio(this, <?= $estudiante['id'] ?>, 1)"
                                           <?= $disabled_t1 ? 'disabled' : '' ?>>
                                </div>
                                
                                <td class="text-center">
                                    <input type="number" 
                                           name="notas[<?= $estudiante['id'] ?>][trimestre_2]" 
                                           class="form-control nota-input text-center trimestre-input" 
                                           data-trimestre="2"
                                           data-original="<?= $valor_t2 ?>"
                                           min="1" max="20" step="1"
                                           value="<?= $valor_t2 ?>"
                                           style="width: 80px; margin: 0 auto;"
                                           onchange="marcarCambio(this, <?= $estudiante['id'] ?>, 2)"
                                           <?= $disabled_t2 ? 'disabled' : '' ?>>
                                </div>
                                
                                <td class="text-center">
                                    <input type="number" 
                                           name="notas[<?= $estudiante['id'] ?>][trimestre_3]" 
                                           class="form-control nota-input text-center trimestre-input" 
                                           data-trimestre="3"
                                           data-original="<?= $valor_t3 ?>"
                                           min="1" max="20" step="1"
                                           value="<?= $valor_t3 ?>"
                                           style="width: 80px; margin: 0 auto;"
                                           onchange="marcarCambio(this, <?= $estudiante['id'] ?>, 3)"
                                           <?= $disabled_t3 ? 'disabled' : '' ?>>
                                </div>
                                
                                <td class="text-center">
                                    <input type="text" id="promedio_<?= $estudiante['id'] ?>"
                                           class="form-control text-center bg-light" readonly
                                           style="width: 80px; margin: 0 auto; font-weight: bold;">
                                    <input type="hidden" name="notas[<?= $estudiante['id'] ?>][nota_final]"
                                           id="promedio_hidden_<?= $estudiante['id'] ?>">
                                </div>
                                
                                <td class="text-center">
                                    <?php
                                    // Prioridad: En Revisión > Rechazada > Aprobada > Pendiente
                                    if (in_array('en_revision', [$estado_t1, $estado_t2, $estado_t3])) {
                                        $badge_class = 'warning';
                                        $badge_text = 'En Revisión';
                                    } elseif (in_array('rechazada', [$estado_t1, $estado_t2, $estado_t3])) {
                                        $badge_class = 'danger';
                                        $badge_text = 'Rechazada';
                                    } elseif (in_array('aprobada', [$estado_t1, $estado_t2, $estado_t3])) {
                                        $badge_class = 'success';
                                        $badge_text = 'Aprobada';
                                    } else {
                                        $badge_class = 'secondary';
                                        $badge_text = 'Pendiente';
                                    }
                                    ?>
                                    <span class="badge badge-<?= $badge_class ?>"><?= $badge_text ?></span>
                                </div>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-warning mt-3">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Importante:</strong> El archivo de soporte es <strong class="text-danger">OBLIGATORIO</strong>. Solo se enviarán las notas que hayan sido modificadas.
            </div>
            
            <button type="submit" class="btn btn-success btn-lg" id="btnGuardarNotas">
                <i class="fas fa-save"></i> Enviar notas modificadas a revisión
            </button>
        </form>
    </div>
</div>

<script>
const disponibilidadTrimestres = <?php echo $disponibilidad_json; ?>;

function calcularPromedio(estudianteId) {
    const t1 = parseFloat(document.querySelector(`input[name="notas[${estudianteId}][trimestre_1]"]`)?.value) || 0;
    const t2 = parseFloat(document.querySelector(`input[name="notas[${estudianteId}][trimestre_2]"]`)?.value) || 0;
    const t3 = parseFloat(document.querySelector(`input[name="notas[${estudianteId}][trimestre_3]"]`)?.value) || 0;
    
    let promedio = 0, notasValidas = 0;
    if (t1 > 0) { promedio += t1; notasValidas++; }
    if (t2 > 0) { promedio += t2; notasValidas++; }
    if (t3 > 0) { promedio += t3; notasValidas++; }
    
    if (notasValidas > 0) {
        let calculo = promedio / notasValidas;
        promedio = calculo % 1 === 0 ? calculo.toFixed(0) : calculo.toFixed(1);
    } else {
        promedio = '';
    }
    
    const promedioField = document.getElementById(`promedio_${estudianteId}`);
    const promedioHidden = document.getElementById(`promedio_hidden_${estudianteId}`);
    if (promedioField) promedioField.value = promedio;
    if (promedioHidden) promedioHidden.value = promedio;
}

function marcarCambio(input, estudianteId, trimestre) {
    const estado = input.getAttribute('data-estado');
    
    if (estado === 'aprobada' || estado === 'en_revision') {
        input.disabled = true;
        return;
    }
    
    const original = input.getAttribute('data-original');
    const nuevo = input.value;
    
    if (original != nuevo) {
        input.style.backgroundColor = '#fff3cd';
        input.style.border = '1px solid #ffc107';
        input.setAttribute('data-modificado', 'true');
    } else {
        input.style.backgroundColor = '';
        input.style.border = '';
        input.setAttribute('data-modificado', 'false');
    }
    calcularPromedio(estudianteId);
}

document.getElementById('btnGuardarNotas')?.addEventListener('click', function(e) {
    e.preventDefault();
    
    for (let trimestre = 1; trimestre <= 3; trimestre++) {
        if (!disponibilidadTrimestres[trimestre].disponible) {
            const inputs = document.querySelectorAll(`input[name*="trimestre_${trimestre}"]`);
            for (let input of inputs) {
                if (input.value && input.value !== '' && input.getAttribute('data-modificado') === 'true') {
                    alert('❌ ' + disponibilidadTrimestres[trimestre].mensaje);
                    return false;
                }
            }
        }
    }
    
    const formData = new FormData(document.getElementById('form-notas'));
    const notasModificadas = {};
    
    document.querySelectorAll('.trimestre-input').forEach(input => {
        const estudianteId = input.name.match(/notas\[(\d+)\]\[(trimestre_\d+)\]/);
        if (estudianteId && input.getAttribute('data-modificado') === 'true') {
            const id = estudianteId[1];
            const campo = estudianteId[2];
            if (!notasModificadas[id]) notasModificadas[id] = {};
            notasModificadas[id][campo] = input.value;
        }
    });
    
    for (let id in notasModificadas) {
        const promedio = document.getElementById(`promedio_hidden_${id}`)?.value;
        if (promedio) notasModificadas[id]['nota_final'] = promedio;
    }
    
    if (Object.keys(notasModificadas).length === 0) {
        alert('No hay notas modificadas para enviar.');
        return false;
    }
    
    for (let key of formData.keys()) {
        if (key.startsWith('notas[')) {
            formData.delete(key);
        }
    }
    
    for (let id in notasModificadas) {
        for (let campo in notasModificadas[id]) {
            formData.append(`notas[${id}][${campo}]`, notasModificadas[id][campo]);
        }
    }
    
    $('#resultados').html(`
        <div class="text-right mb-3" id="volver-container">
            <button class="btn btn-secondary" id="btn-volver"><i class="fas fa-arrow-left"></i> Volver a Secciones</button>
        </div>
        <div class="text-center py-5">
            <div class="spinner-border text-success" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3">Enviando notas modificadas a revisión...</p>
        </div>
    `);
    
    fetch('guardar_notas.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            $('#resultados').html(`<div class="text-right mb-3" id="volver-container"><button class="btn btn-secondary" id="btn-volver"><i class="fas fa-arrow-left"></i> Volver a Secciones</button></div>`);
            const header = $('#modalResultadoHeader');
            const title = $('#modalResultadoTitle');
            const body = $('#modalResultadoBody');
            if (data.success) {
                header.removeClass('bg-danger').addClass('bg-success text-white');
                title.text('Éxito');
                body.html('<div class="alert alert-success">' + data.message + '</div>');
            } else {
                header.removeClass('bg-success').addClass('bg-danger text-white');
                title.text('Error');
                body.html('<div class="alert alert-danger">' + data.message + '</div>');
            }
            $('#modalResultado').modal('show');
        })
        .catch(error => {
            $('#resultados').html(`<div class="text-right mb-3" id="volver-container"><button class="btn btn-secondary" id="btn-volver"><i class="fas fa-arrow-left"></i> Volver a Secciones</button></div>
                <div class="alert alert-danger">Error al enviar: ${error.message}</div>`);
        });
});

document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($estudiantes as $estudiante): ?>
        calcularPromedio(<?= $estudiante['id'] ?>);
    <?php endforeach; ?>
    
    document.querySelectorAll('.trimestre-input').forEach(input => {
        input.addEventListener('input', function() {
            let val = parseInt(this.value);
            if (isNaN(val)) val = 1;
            if (val < 1) this.value = 1;
            if (val > 20) this.value = 20;
            const match = this.name.match(/notas\[(\d+)\]/);
            if (match) {
                const estudianteId = match[1];
                const trimestre = this.getAttribute('data-trimestre');
                marcarCambio(this, estudianteId, trimestre);
            }
        });
        
        input.addEventListener('focus', function() {
            const trimestre = this.getAttribute('data-trimestre');
            if (trimestre && !disponibilidadTrimestres[trimestre].disponible && !this.disabled) {
                alert('❌ ' + disponibilidadTrimestres[trimestre].mensaje);
                this.blur();
            }
        });
    });
});

function initSoportePreview() {
    const inputSoporte = document.getElementById('soporte_grupo');
    const previewBox = document.getElementById('preview-grupo');
    const labelSoporte = document.getElementById('label-soporte-grupo');
    const infoSoporte = document.getElementById('info-archivo-soporte');
    
    if (!inputSoporte || !previewBox) return;

    inputSoporte.onchange = function() {
        const file = this.files[0];
        if (!file) {
            if (labelSoporte) labelSoporte.textContent = 'Elegir archivo PDF o Imagen...';
            if (infoSoporte) infoSoporte.textContent = '';
            previewBox.innerHTML = `
                <div class="py-3 text-muted">
                    <i class="fas fa-cloud-upload-alt fa-2x mb-1 text-secondary"></i>
                    <p class="mb-0 small">No se ha seleccionado ningún archivo de soporte aún.</p>
                </div>`;
            return;
        }

        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        const fileName = file.name;
        if (labelSoporte) labelSoporte.textContent = fileName;
        if (infoSoporte) infoSoporte.innerHTML = `<span class="badge badge-info">${file.type || 'Archivo'}</span> <span class="badge badge-secondary">${fileSizeMB} MB</span>`;

        const fileUrl = URL.createObjectURL(file);

        if (file.type === 'application/pdf' || fileName.toLowerCase().endsWith('.pdf')) {
            previewBox.innerHTML = `
                <div class="text-left mb-2 d-flex justify-content-between align-items-center bg-white p-2 border rounded shadow-sm">
                    <div>
                        <i class="fas fa-file-pdf text-danger fa-lg mr-2"></i>
                        <strong class="text-dark">${fileName}</strong>
                        <span class="text-muted small ml-2">(${fileSizeMB} MB)</span>
                    </div>
                    <div>
                        <a href="${fileUrl}" target="_blank" class="btn btn-xs btn-outline-danger btn-sm font-weight-bold mr-1 py-1 px-2">
                            <i class="fas fa-external-link-alt mr-1"></i> Pantalla Completa
                        </a>
                        <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-1 px-2" onclick="document.getElementById('soporte_grupo').value=''; document.getElementById('soporte_grupo').onchange();">
                            <i class="fas fa-times mr-1"></i> Quitar
                        </button>
                    </div>
                </div>
                <div style="height: 400px; width: 100%; border-radius: 6px; overflow: hidden; border: 1px solid #dee2e6;">
                    <iframe src="${fileUrl}" style="width: 100%; height: 100%; border: none; background-color: #525659;"></iframe>
                </div>
            `;
        } else if (file.type.startsWith('image/')) {
            previewBox.innerHTML = `
                <div class="text-left mb-2 d-flex justify-content-between align-items-center bg-white p-2 border rounded shadow-sm">
                    <div>
                        <i class="fas fa-image text-success fa-lg mr-2"></i>
                        <strong class="text-dark">${fileName}</strong>
                        <span class="text-muted small ml-2">(${fileSizeMB} MB)</span>
                    </div>
                    <div>
                        <a href="${fileUrl}" target="_blank" class="btn btn-xs btn-outline-success btn-sm font-weight-bold mr-1 py-1 px-2">
                            <i class="fas fa-search-plus mr-1"></i> Ver en Grande
                        </a>
                        <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-1 px-2" onclick="document.getElementById('soporte_grupo').value=''; document.getElementById('soporte_grupo').onchange();">
                            <i class="fas fa-times mr-1"></i> Quitar
                        </button>
                    </div>
                </div>
                <div class="p-2 bg-white rounded border text-center">
                    <img src="${fileUrl}" class="img-fluid rounded shadow-sm" style="max-height: 350px; object-fit: contain;">
                </div>
            `;
        } else {
            previewBox.innerHTML = `
                <div class="alert alert-info mb-0">
                    <i class="fas fa-file mr-1"></i> Archivo seleccionado: <strong>${fileName}</strong> (${fileSizeMB} MB)
                </div>
            `;
        }
    };
}

// Ejecutar inmediatamente
initSoportePreview();

</script>

<style>
.nota-input { font-weight: bold; text-align: center; }
.nota-input:disabled { background-color: #e9ecef; cursor: not-allowed; }
</style>