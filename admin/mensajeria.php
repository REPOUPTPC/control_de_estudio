<?php
require_once('../funciones/functions.php');

// CARGAR PERMISOS
cargarPermisosUsuario();
verificarPermiso('mensajeria');

// LLAMAR A LA FUNCIÓN DE VISITA
visita();

// Verificar automáticamente si hay docentes con fecha de carga vencida
if (isAdmin() || tienePermiso('mensajeria')) {
    verificarDocentesCargaVencida();
}

// Asegurar sesión y CSRF token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_user_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0;

// ==========================================
// CONTROLADORES AJAX (100% POST PURO)
// ==========================================
if (isset($_POST['ajax_buscar_destinatarios'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $termino = trim($_POST['termino'] ?? '');
    $filtro_tipo = trim($_POST['filtro_tipo'] ?? '');
    
    $usuarios_res = obtenerUsuariosMensajeria($filtro_tipo, $termino, $current_user_id, 30);
    $usuarios = [];
    
    if ($usuarios_res) {
        while ($u = $usuarios_res->fetch_assoc()) {
            $usuarios[] = [
                'id' => (int)$u['id'],
                'nombre' => $u['nombre'],
                'usuario' => $u['usuario'],
                'cedula' => $u['idusuario'],
                'email' => $u['email'] ?? '',
                'tipo' => obtenerTipoUsuario($u)
            ];
        }
    }
    
    echo json_encode(['success' => true, 'usuarios' => $usuarios]);
    exit();
}

if (isset($_POST['ajax_ver_mensaje'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $mensaje_id = (int)($_POST['mensaje_id'] ?? 0);
    $tipo = ($_POST['tipo'] ?? '') === 'enviados' ? 'enviados' : 'recibidos';
    
    if ($mensaje_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de mensaje inválido']);
        exit();
    }
    
    $mensaje = obtenerMensaje($mensaje_id, $current_user_id, $tipo);
    if (!$mensaje) {
        echo json_encode(['success' => false, 'error' => 'Mensaje no encontrado']);
        exit();
    }
    
    // Si es recibido y no leído, marcar como leído
    if ($tipo === 'recibidos' && empty($mensaje['leido'])) {
        marcarMensajeLeido($mensaje_id, $current_user_id);
        $mensaje['leido'] = 1;
    }
    
    $mensaje['tipo_usuario'] = obtenerTipoUsuario($mensaje);
    $mensaje['fecha_formateada'] = date('d/m/Y h:i A', strtotime($mensaje['fecha_envio']));
    
    echo json_encode(['success' => true, 'mensaje' => $mensaje, 'tipo' => $tipo]);
    exit();
}

if (isset($_POST['ajax_eliminar_mensaje'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
        exit();
    }
    
    $mensaje_id = (int)($_POST['mensaje_id'] ?? 0);
    $tipo = ($_POST['tipo'] ?? '') === 'enviados' ? 'enviados' : 'recibidos';
    
    if ($mensaje_id > 0 && eliminarMensaje($mensaje_id, $current_user_id, $tipo)) {
        echo json_encode(['success' => true, 'message' => 'Mensaje eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo eliminar el mensaje']);
    }
    exit();
}

// ==========================================
// PROCESAR ENVÍO DE MENSAJE (POST)
// ==========================================
$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_mensaje'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje_error = "Error de seguridad. Token CSRF inválido.";
    } else {
        $destinatario_id = (int)($_POST['destinatario_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $mensaje_txt = trim($_POST['mensaje'] ?? '');
        
        if ($destinatario_id > 0 && !empty($titulo) && !empty($mensaje_txt)) {
            $resp = enviarMensaje($current_user_id, $destinatario_id, $titulo, $mensaje_txt);
            if ($resp['success']) {
                $mensaje_exito = $resp['message'];
            } else {
                $mensaje_error = $resp['message'];
            }
        } else {
            $mensaje_error = "Por favor seleccione un destinatario y complete todos los campos obligatorios.";
        }
    }
}

$titulopag = "Sistema de Mensajería - Administración";
include("includes/head.php");

// Obtener mensajes para la vista
$mensajes_recibidos = obtenerMensajesRecibidos($current_user_id);
$mensajes_enviados = obtenerMensajesEnviados($current_user_id);
$total_no_leidos = contarMensajesNoLeidos($current_user_id);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center my-4">
        <h2 class="mb-0"><i class="fas fa-envelope-open-text text-primary mr-2"></i>Sistema de Mensajería</h2>
        <span class="badge badge-primary p-2 font-weight-bold" id="badge_total_no_leidos">
            <i class="fas fa-bell mr-1"></i> <?= $total_no_leidos ?> sin leer
        </span>
    </div>
    
    <?php if (!empty($mensaje_exito)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($mensaje_exito) ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($mensaje_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($mensaje_error) ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Panel de redacción de mensajes -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-pen-fancy mr-2"></i>Nuevo Mensaje</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="formNuevoMensaje">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="destinatario_id" id="destinatario_id" value="" required>
                        
                        <!-- Filtro y buscador en tiempo real de destinatarios -->
                        <div class="form-group position-relative">
                            <label class="font-weight-bold">Destinatario:</label>
                            
                            <div class="input-group mb-2">
                                <select class="custom-select" id="filtro_rol_destinatario" style="max-width: 130px;">
                                    <option value="">Todos</option>
                                    <option value="estudiante">Estudiantes</option>
                                    <option value="docente">Docentes</option>
                                    <option value="admin">Admin</option>
                                    <option value="director_carrera">Directores</option>
                                </select>
                                <input type="text" class="form-control" id="buscador_destinatario" 
                                       placeholder="Buscar por cédula o nombre..." autocomplete="off">
                                <div class="input-group-append" id="spinner_busqueda_dest" style="display: none;">
                                    <span class="input-group-text bg-white"><i class="fas fa-spinner fa-spin text-primary"></i></span>
                                </div>
                            </div>
                            
                            <!-- Contenedor flotante de sugerencias -->
                            <div id="sugerencias_destinatarios" class="list-group shadow position-absolute w-100" 
                                 style="z-index: 9999; display: none; max-height: 280px; overflow-y: auto; left: 0; right: 0; background: #ffffff; border: 1px solid #ced4da; border-radius: 4px;">
                            </div>
                            
                            <!-- Card del destinatario seleccionado -->
                            <div id="destinatario_seleccionado_card" class="p-2 border rounded bg-light mt-2" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-check text-success mr-1"></i>
                                        <strong id="dest_nombre_badge"></strong>
                                        <div class="small text-muted" id="dest_detalles_badge"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="btn_cambiar_destinatario" title="Cambiar destinatario">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="titulo" class="font-weight-bold">Asunto:</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Asunto del mensaje..." required>
                        </div>
                        
                        <div class="form-group">
                            <label for="mensaje" class="font-weight-bold">Mensaje:</label>
                            <textarea class="form-control" id="mensaje" name="mensaje" rows="5" placeholder="Escriba su mensaje aquí..." required></textarea>
                        </div>
                        
                        <button type="submit" name="enviar_mensaje" id="btn_enviar_mensaje" class="btn btn-success btn-block shadow-sm">
                            <i class="fas fa-paper-plane mr-1"></i> Enviar Mensaje
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Panel de Bandejas (Recibidos y Enviados) -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white p-0 border-bottom-0">
                    <ul class="nav nav-tabs nav-justified" id="mensajesTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold py-3" id="recibidos-tab" data-toggle="tab" href="#recibidos" role="tab">
                                <i class="fas fa-inbox text-primary mr-2"></i>Bandeja de Entrada
                                <span class="badge badge-primary ml-1" id="badge_recibidos_count"><?= $mensajes_recibidos ? $mensajes_recibidos->num_rows : 0 ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold py-3" id="enviados-tab" data-toggle="tab" href="#enviados" role="tab">
                                <i class="fas fa-paper-plane text-info mr-2"></i>Mensajes Enviados
                                <span class="badge badge-info ml-1"><?= $mensajes_enviados ? $mensajes_enviados->num_rows : 0 ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body">
                    <div class="tab-content" id="mensajesTabContent">
                        <!-- Bandeja de Entrada -->
                        <div class="tab-pane fade show active" id="recibidos" role="tabpanel">
                            <?php if ($mensajes_recibidos && $mensajes_recibidos->num_rows > 0): ?>
                                <div class="list-group" id="lista_recibidos">
                                    <?php while ($m = $mensajes_recibidos->fetch_assoc()): ?>
                                        <div class="list-group-item list-group-item-action d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3 mb-2 rounded border <?= empty($m['leido']) ? 'border-primary bg-light' : '' ?>" id="item_msg_<?= $m['id'] ?>">
                                            <div class="mr-md-3 mb-2 mb-md-0">
                                                <div class="d-flex align-items-center mb-1">
                                                    <?php if (empty($m['leido'])): ?>
                                                        <span class="badge badge-danger mr-2 badge-nuevo"><i class="fas fa-envelope mr-1"></i>NUEVO</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary mr-2"><i class="fas fa-envelope-open mr-1"></i>Leído</span>
                                                    <?php endif; ?>
                                                    <h6 class="mb-0 font-weight-bold text-dark"><?= htmlspecialchars($m['titulo']) ?></h6>
                                                </div>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-user text-secondary"></i> <strong><?= htmlspecialchars($m['remitente_nombre']) ?></strong> 
                                                    (<?= obtenerTipoUsuario($m) ?> - C.I: <?= htmlspecialchars($m['remitente_cedula']) ?>)
                                                </small>
                                                <div class="small text-muted mt-1 text-truncate" style="max-width: 500px;">
                                                    <?= htmlspecialchars(substr($m['mensaje'], 0, 110)) ?><?= strlen($m['mensaje']) > 110 ? '...' : '' ?>
                                                </div>
                                            </div>
                                            <div class="text-md-right flex-shrink-0">
                                                <div class="small text-muted mb-2"><i class="far fa-clock"></i> <?= date('d/m/Y h:i A', strtotime($m['fecha_envio'])) ?></div>
                                                <button type="button" class="btn btn-sm btn-outline-primary mr-1 btn-ver-mensaje" 
                                                        data-id="<?= $m['id'] ?>" data-tipo="recibidos">
                                                    <i class="fas fa-eye mr-1"></i> Leer
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-mensaje" 
                                                        data-id="<?= $m['id'] ?>" data-tipo="recibidos">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                                    <h5>No tienes mensajes recibidos.</h5>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Mensajes Enviados -->
                        <div class="tab-pane fade" id="enviados" role="tabpanel">
                            <?php if ($mensajes_enviados && $mensajes_enviados->num_rows > 0): ?>
                                <div class="list-group" id="lista_enviados">
                                    <?php while ($m = $mensajes_enviados->fetch_assoc()): ?>
                                        <div class="list-group-item list-group-item-action d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3 mb-2 rounded border" id="item_msg_<?= $m['id'] ?>">
                                            <div class="mr-md-3 mb-2 mb-md-0">
                                                <div class="d-flex align-items-center mb-1">
                                                    <h6 class="mb-0 font-weight-bold text-dark"><?= htmlspecialchars($m['titulo']) ?></h6>
                                                </div>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-user-check text-info"></i> Para: <strong><?= htmlspecialchars($m['destinatario_nombre']) ?></strong> 
                                                    (<?= obtenerTipoUsuario($m) ?> - C.I: <?= htmlspecialchars($m['destinatario_cedula']) ?>)
                                                </small>
                                                <div class="small text-muted mt-1 text-truncate" style="max-width: 500px;">
                                                    <?= htmlspecialchars(substr($m['mensaje'], 0, 110)) ?><?= strlen($m['mensaje']) > 110 ? '...' : '' ?>
                                                </div>
                                            </div>
                                            <div class="text-md-right flex-shrink-0">
                                                <div class="small text-muted mb-2"><i class="far fa-clock"></i> <?= date('d/m/Y h:i A', strtotime($m['fecha_envio'])) ?></div>
                                                <button type="button" class="btn btn-sm btn-outline-info mr-1 btn-ver-mensaje" 
                                                        data-id="<?= $m['id'] ?>" data-tipo="enviados">
                                                    <i class="fas fa-eye mr-1"></i> Ver
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-mensaje" 
                                                        data-id="<?= $m['id'] ?>" data-tipo="enviados">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-paper-plane fa-3x mb-3 text-muted"></i>
                                    <h5>No has enviado mensajes.</h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver mensaje (Cargado 100% por AJAX POST) -->
<div class="modal fade" id="modalVerMensaje" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalMsgTitulo"><i class="fas fa-envelope mr-2"></i>Cargando...</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4" id="modalMsgBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
    const inputBuscador = document.getElementById('buscador_destinatario');
    const selectFiltro = document.getElementById('filtro_rol_destinatario');
    const sugerenciasContainer = document.getElementById('sugerencias_destinatarios');
    const spinnerBusqueda = document.getElementById('spinner_busqueda_dest');
    const inputDestinatarioId = document.getElementById('destinatario_id');
    const cardSeleccionado = document.getElementById('destinatario_seleccionado_card');
    const badgeNombre = document.getElementById('dest_nombre_badge');
    const badgeDetalles = document.getElementById('dest_detalles_badge');
    const btnCambiarDest = document.getElementById('btn_cambiar_destinatario');
    let debounceTimer = null;

    function escapeHtml(text) {
        if (!text) return '';
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // Búsqueda en tiempo real de destinatarios
    function buscarDestinatarios() {
        const termino = (inputBuscador.value || '').trim();
        const filtro = selectFiltro.value;
        clearTimeout(debounceTimer);

        if (termino.length === 0 && !filtro) {
            sugerenciasContainer.style.display = 'none';
            sugerenciasContainer.innerHTML = '';
            if (spinnerBusqueda) spinnerBusqueda.style.display = 'none';
            return;
        }

        if (spinnerBusqueda) spinnerBusqueda.style.display = 'flex';

        debounceTimer = setTimeout(() => {
            const fd = new FormData();
            fd.append('ajax_buscar_destinatarios', '1');
            fd.append('termino', termino);
            fd.append('filtro_tipo', filtro);

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (spinnerBusqueda) spinnerBusqueda.style.display = 'none';
                    sugerenciasContainer.innerHTML = '';

                    if (data.success && Array.isArray(data.usuarios) && data.usuarios.length > 0) {
                        data.usuarios.forEach(u => {
                            const item = document.createElement('a');
                            item.href = 'javascript:void(0);';
                            item.className = 'list-group-item list-group-item-action p-2 d-flex justify-content-between align-items-center border-bottom text-decoration-none';
                            item.style.cursor = 'pointer';
                            item.innerHTML = `
                                <div>
                                    <strong class="text-dark"><i class="fas fa-user mr-1 text-primary"></i> ${escapeHtml(u.nombre)}</strong>
                                    <div class="small text-muted">
                                        <span>C.I: ${escapeHtml(u.cedula)}</span> | 
                                        <span class="badge badge-info">${escapeHtml(u.tipo)}</span>
                                    </div>
                                </div>
                                <span class="btn btn-sm btn-success py-1 px-2"><i class="fas fa-check mr-1"></i>Elegir</span>
                            `;

                            item.addEventListener('click', function(e) {
                                e.preventDefault();
                                seleccionarDestinatario(u);
                            });

                            sugerenciasContainer.appendChild(item);
                        });
                        sugerenciasContainer.style.display = 'block';
                    } else {
                        sugerenciasContainer.innerHTML = `
                            <div class="list-group-item p-3 text-center text-muted">
                                <i class="fas fa-user-slash mr-1"></i> No se encontraron usuarios.
                            </div>
                        `;
                        sugerenciasContainer.style.display = 'block';
                    }
                })
                .catch(err => {
                    if (spinnerBusqueda) spinnerBusqueda.style.display = 'none';
                    console.error("Error buscando destinatarios:", err);
                });
        }, 200);
    }

    function seleccionarDestinatario(u) {
        inputDestinatarioId.value = u.id;
        badgeNombre.textContent = u.nombre;
        badgeDetalles.textContent = `Cédula: ${u.cedula} | ${u.tipo} (${u.email || u.usuario})`;
        cardSeleccionado.style.display = 'block';
        inputBuscador.parentElement.style.display = 'none';
        sugerenciasContainer.style.display = 'none';
        sugerenciasContainer.innerHTML = '';
    }

    if (btnCambiarDest) {
        btnCambiarDest.addEventListener('click', function() {
            inputDestinatarioId.value = '';
            cardSeleccionado.style.display = 'none';
            inputBuscador.parentElement.style.display = 'flex';
            inputBuscador.value = '';
            inputBuscador.focus();
        });
    }

    if (inputBuscador) {
        inputBuscador.addEventListener('input', buscarDestinatarios);
        inputBuscador.addEventListener('focus', buscarDestinatarios);
    }
    if (selectFiltro) {
        selectFiltro.addEventListener('change', buscarDestinatarios);
    }

    document.addEventListener('click', function(e) {
        if (inputBuscador && !inputBuscador.contains(e.target) && !sugerenciasContainer.contains(e.target)) {
            sugerenciasContainer.style.display = 'none';
        }
    });

    // Ver mensaje vía POST AJAX
    document.querySelectorAll('.btn-ver-mensaje').forEach(btn => {
        btn.addEventListener('click', function() {
            const msgId = this.getAttribute('data-id');
            const tipo = this.getAttribute('data-tipo');
            const itemElement = document.getElementById('item_msg_' + msgId);

            $('#modalMsgTitulo').html('<i class="fas fa-spinner fa-spin mr-2"></i>Cargando mensaje...');
            $('#modalMsgBody').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>');
            $('#modalVerMensaje').modal('show');

            const fd = new FormData();
            fd.append('ajax_ver_mensaje', '1');
            fd.append('mensaje_id', msgId);
            fd.append('tipo', tipo);

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.mensaje) {
                        const m = res.mensaje;
                        $('#modalMsgTitulo').text(m.titulo);
                        
                        const deOpara = (tipo === 'recibidos') ? 
                            `<p class="mb-1"><strong>De:</strong> ${escapeHtml(m.remitente_nombre)} <span class="badge badge-info">${escapeHtml(m.tipo_usuario)}</span> (C.I: ${escapeHtml(m.remitente_cedula)})</p>` :
                            `<p class="mb-1"><strong>Para:</strong> ${escapeHtml(m.destinatario_nombre)} <span class="badge badge-info">${escapeHtml(m.tipo_usuario)}</span> (C.I: ${escapeHtml(m.destinatario_cedula)})</p>`;
                        
                        let textoLimpio = m.mensaje || '';
                        const amonIdMatch = textoLimpio.match(/\[AMONESTACION_ACTION:(\d+)\]/);
                        const amonId = amonIdMatch ? amonIdMatch[1] : null;
                        textoLimpio = textoLimpio.replace(/\[AMONESTACION_ACTION:(\d+)\]/g, '').trim();

                        let amonHtml = '';
                        if (amonId) {
                            amonHtml = `
                            <div class="mt-4 p-3 border border-danger rounded bg-white shadow-sm" id="card_amon_${amonId}">
                                <div class="d-flex align-items-center mb-2 text-danger font-weight-bold">
                                    <i class="fas fa-exclamation-triangle fa-lg mr-2"></i>
                                    <span>ACCIONES ADMINISTRATIVAS - CONTROL DE ESTUDIOS</span>
                                </div>
                                <p class="text-muted small mb-3">
                                    Genere el Formato de Amonestación oficial en PDF para su firma física y, tras la comparecencia presencial del docente, concédale la prórroga de 24 horas continuas para la carga de notas.
                                </p>
                                <div class="d-flex flex-wrap align-items-center">
                                    <a href="generar_amonestacion_pdf.php?id=${amonId}" target="_blank" class="btn btn-danger btn-sm mr-2 mb-2">
                                        <i class="fas fa-file-pdf mr-1"></i> Descargar Formato de Amonestación (PDF)
                                    </a>
                                    <button type="button" class="btn btn-warning btn-sm mb-2 btn-habilitar-24h" data-id="${amonId}">
                                        <i class="fas fa-clock mr-1"></i> Habilitar 24 Horas al Docente
                                    </button>
                                </div>
                            </div>`;
                        }

                        const html = `
                            <div class="row pb-3 mb-3 border-bottom">
                                <div class="col-md-7">
                                    ${deOpara}
                                    <p class="mb-0 text-muted small"><i class="far fa-envelope mr-1"></i>${escapeHtml(m.remitente_email || m.destinatario_email || '')}</p>
                                </div>
                                <div class="col-md-5 text-md-right mt-2 mt-md-0">
                                    <small class="text-muted"><i class="far fa-clock mr-1"></i>${m.fecha_formateada}</small>
                                </div>
                            </div>
                            <div class="mensaje-cuerpo p-3 bg-light rounded" style="white-space: pre-wrap; font-size: 1.05rem; line-height: 1.6;">${escapeHtml(textoLimpio)}</div>
                            ${amonHtml}
                        `;
                        $('#modalMsgBody').html(html);

                        // Si era un mensaje nuevo recibido, actualizar la interfaz
                        if (tipo === 'recibidos' && itemElement) {
                            itemElement.classList.remove('border-primary', 'bg-light');
                            const badgeNuevo = itemElement.querySelector('.badge-nuevo');
                            if (badgeNuevo) {
                                badgeNuevo.className = 'badge badge-secondary mr-2';
                                badgeNuevo.innerHTML = '<i class="fas fa-envelope-open mr-1"></i>Leído';
                            }
                        }
                    } else {
                        $('#modalMsgBody').html(`<div class="alert alert-danger mb-0">${escapeHtml(res.error || 'Error al cargar mensaje')}</div>`);
                    }
                })
                .catch(err => {
                    $('#modalMsgBody').html('<div class="alert alert-danger mb-0">Error de conexión al cargar el mensaje.</div>');
                });
        });
    });

    // Eliminar mensaje vía POST AJAX
    document.querySelectorAll('.btn-eliminar-mensaje').forEach(btn => {
        btn.addEventListener('click', function() {
            const msgId = this.getAttribute('data-id');
            const tipo = this.getAttribute('data-tipo');
            const itemElement = document.getElementById('item_msg_' + msgId);

            if (!confirm('¿Está seguro de que desea eliminar este mensaje de su bandeja?')) {
                return;
            }

            const fd = new FormData();
            fd.append('ajax_eliminar_mensaje', '1');
            fd.append('mensaje_id', msgId);
            fd.append('tipo', tipo);
            fd.append('csrf_token', csrfToken);

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        if (itemElement) {
                            itemElement.style.transition = 'all 0.3s';
                            itemElement.style.opacity = '0';
                            setTimeout(() => itemElement.remove(), 300);
                        }
                    } else {
                        alert(res.error || 'No se pudo eliminar el mensaje.');
                    }
                })
                .catch(err => {
                    alert('Error de conexión al intentar eliminar el mensaje.');
                });
        });
    });

    // Delegación para botón de habilitar 24 horas al docente
    $(document).on('click', '.btn-habilitar-24h', function() {
        const amonId = $(this).data('id');
        const btn = $(this);

        const ejecutarHabilitacion = function() {
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Habilitando...');

            const fd = new FormData();
            fd.append('id', amonId);

            fetch('ajax_habilitar_docente_24h.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡24 Horas Habilitadas!',
                            text: res.message,
                            confirmButtonColor: '#28a745'
                        });
                    } else {
                        alert(res.message);
                    }
                    btn.replaceWith(`<span class="badge badge-success p-2 mr-2 mb-2"><i class="fas fa-check-circle mr-1"></i> Habilitado hasta: ${res.habilitado_hasta}</span>`);
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message || 'No se pudo habilitar al docente.',
                            confirmButtonColor: '#dc3545'
                        });
                    } else {
                        alert(res.message || 'Error al habilitar docente.');
                    }
                    btn.prop('disabled', false).html('<i class="fas fa-clock mr-1"></i> Habilitar 24 Horas al Docente');
                }
            })
            .catch(err => {
                alert('Error de conexión al habilitar al docente.');
                btn.prop('disabled', false).html('<i class="fas fa-clock mr-1"></i> Habilitar 24 Horas al Docente');
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Habilitar 24 Horas al Docente?',
                text: 'El docente compareció ante Control de Estudios. Se habilitará el sistema durante 24 horas continuas a partir de este momento para que proceda a subir sus notas.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Habilitar 24h',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarHabilitacion();
                }
            });
        } else {
            if (confirm('¿Confirmar que el docente compareció y habilitarle 24 horas continuas para carga de notas?')) {
                ejecutarHabilitacion();
            }
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include("includes/footer.php"); ?>