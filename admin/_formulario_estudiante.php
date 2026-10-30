<?php
// Obtener los datos necesarios para los select (si no se pasaron como parámetros)
if (!isset($tiposCedula)) {
    $tiposCedula = obtenerTiposCedula($db);
}
if (!isset($estadosCiviles)) {
    $estadosCiviles = obtenerEstadosCiviless($db);
}
if (!isset($tiposVivienda)) {
    $tiposVivienda = obtenerTiposVivienda($db);
}
if (!isset($tenenciasVivienda)) {
    $tenenciasVivienda = obtenerTenenciaViviendas($db);
}
if (!isset($opcionesStatus)) {
    $opcionesStatus = obtenerOpcionesStatus($db);
}
if (!isset($carreras)) {
    $carreras = obtenerTodasLasCarreras();
}
if (!isset($ingresos)) {
    $ingresos = obtenerIngresos($db);
}
if (!isset($estados)) {
    $estados = obtenerEstados($db);
}

// Obtener cupos disponibles para todas las carreras y turnos según el período académico
$periodoFormulario = resolverIdPeriodo(isset($id_periodo) ? $id_periodo : null);
$todosLosCupos = [];
foreach ($carreras as $carrera) {
    $turnosCupos = ['Diurno', 'Nocturno'];
    foreach ($turnosCupos as $turno) {
        $todosLosCupos[$carrera['id']][$turno] = obtenerCuposDisponiblesPorCarreraYTurno($carrera['id'], $turno, $periodoFormulario);
    }
}

// Determinar si estamos en modo modal
$esModal = isset($esModal) ? $esModal : false;
$modo_preinscripcion = isset($modo_preinscripcion) ? $modo_preinscripcion : false;
$formId = $esModal ? 'formEstudianteModal' : 'formEstudiante';
$prefijo = $esModal ? '_modal' : '';
$actionUrl = $modo_preinscripcion ? 'preinscripcion.php' : ($esModal ? 'procesar_estudiante.php' : htmlspecialchars($_SERVER["PHP_SELF"]));
$fechaSolicitud = date('Y-m-d');
?>

<form id="<?php echo $formId; ?>" method="post" action="<?php echo $actionUrl; ?>" enctype="multipart/form-data">
    <?php if ($modo_preinscripcion): ?>
    <!-- Alerta de estudiante ya formalmente inscrito -->
    <div id="avisoEstudianteInscrito<?php echo $prefijo; ?>" class="alert alert-danger py-2 px-3 mb-4 shadow-sm" style="display: none; border-left: 5px solid #dc3545;">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x mr-3 text-danger"></i>
                <div>
                    <strong>Esta cédula ya se encuentra formalmente inscrita como estudiante.</strong><br>
                    <span class="small text-muted">No puede modificar la cédula ni enviar una preinscripción. Si ya es alumno regular, inicie sesión con sus credenciales.</span>
                </div>
            </div>
            <div class="mt-2 mt-md-0">
                <a href="login.php" class="btn btn-sm btn-danger">
                    <i class="fas fa-sign-in-alt mr-1"></i> Ir al Login
                </a>
            </div>
        </div>
    </div>

    <!-- Alerta de preinscripción anterior encontrada -->
    <div id="avisoPreinscrito<?php echo $prefijo; ?>" class="alert alert-info py-2 px-3 mb-4 shadow-sm" style="display: none; border-left: 5px solid #0dcaf0;">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <i class="fas fa-user-check fa-2x mr-3 text-info"></i>
                <div>
                    <strong>¡Datos de preinscripción previa cargados!</strong><br>
                    <span class="small text-muted">Por seguridad institucional, su <strong>cédula</strong>, <strong>nombre</strong> y <strong>fecha de nacimiento</strong> quedan protegidos y no pueden ser modificados. Los demás datos pueden ser actualizados.</span>
                </div>
            </div>
            <div class="mt-2 mt-md-0">
                <button type="button" class="btn btn-sm btn-info" id="btnVerDetallePreinscrito<?php echo $prefijo; ?>" title="Ver aviso modal nuevamente">
                    <i class="fas fa-info-circle mr-1"></i> Ver Aviso
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Estudiante Ya Inscrito (Bootstrap Modal Fallback) -->
    <div class="modal fade" id="modalEstudianteInscrito<?php echo $prefijo; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-exclamation-triangle mr-2"></i> Estudiante Ya Registrado</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="textoEstudianteInscrito<?php echo $prefijo; ?>">Esta cédula ya se encuentra formalmente inscrita como estudiante en el sistema.</p>
                    <p class="text-muted small mb-0">Si usted ya es estudiante de la institución, debe iniciar sesión en el portal en lugar de preinscribirse.</p>
                </div>
                <div class="modal-footer">
                    <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt mr-1"></i> Iniciar Sesión</a>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Preinscripción Previa Encontrada (Bootstrap Modal Fallback) -->
    <div class="modal fade" id="modalPreinscripcionPrevia<?php echo $prefijo; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-info-circle mr-2"></i> Preinscripción Previa Encontrada</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Detectamos que ya se había preinscrito anteriormente a nombre de: <strong id="nombreModalPreinscrito<?php echo $prefijo; ?>"></strong> (<span id="cedulaModalPreinscrito<?php echo $prefijo; ?>"></span>).</p>
                    <div class="alert alert-warning py-2 px-3 my-3" style="border-left: 4px solid #ffc107;">
                        <strong><i class="fas fa-lock mr-1"></i> Datos protegidos por seguridad institucional:</strong><br>
                        • Su <strong>Cédula</strong>, <strong>Nombre</strong> y <strong>Fecha de Nacimiento</strong> no pueden modificarse.
                    </div>
                    <p class="text-muted small mb-0">
                        Todos los demás datos (carrera, turno, asignación OPSU, teléfonos, dirección, etc.) han sido cargados y puede actualizarlos libremente antes de formalizar.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-dismiss="modal"><i class="fas fa-check mr-1"></i> Continuar y Actualizar Datos</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sección 1: Identificación -->
    <h5 class="mb-3"><i class="fas fa-id-card mr-2"></i> Identificación</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="cedula_completa<?php echo $prefijo; ?>" class="form-label required">
                    Cédula
                    <span id="badgeCedulaBloqueada<?php echo $prefijo; ?>" class="badge bg-secondary ms-2" style="display:none;"><i class="fas fa-lock"></i> Registrada</span>
                </label>
                <div class="input-group">
                    <select class="custom-select" id="tipo_cedula<?php echo $prefijo; ?>" name="tipo_cedula" style="max-width: 80px;">
                        <?php foreach ($tiposCedula as $tipo): ?>
                            <?php 
                            $tipoLetra = substr($tipo['tipo'], 0, 1);
                            ?>
                            <option value="<?php echo htmlspecialchars($tipoLetra); ?>" <?php echo (isset($_POST['tipo_cedula']) && $_POST['tipo_cedula'] === $tipoLetra) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipoLetra); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="form-control" id="numero_cedula<?php echo $prefijo; ?>" name="numero_cedula" placeholder="Ej: 12345678" required value="<?php echo htmlspecialchars($_POST['numero_cedula'] ?? ''); ?>">
                    <?php if ($modo_preinscripcion): ?>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-primary" id="btnConsultarCedula<?php echo $prefijo; ?>" title="Verificar si posee una preinscripción previa">
                            <i class="fas fa-search"></i> <span class="d-none d-sm-inline">Verificar</span>
                        </button>
                    </div>
                    <?php endif; ?>
                    <input type="hidden" id="idusuario<?php echo $prefijo; ?>" name="idusuario" value="<?php echo htmlspecialchars($_POST['idusuario'] ?? ''); ?>">
                </div>
                <small class="text-muted">Formato: V-12345678 o E-12345678</small>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="nombre<?php echo $prefijo; ?>" class="form-label required">
                    Apellidos y nombres
                    <span id="badgeNombreBloqueado<?php echo $prefijo; ?>" class="badge bg-secondary ms-2" style="display:none;"><i class="fas fa-lock"></i> Registrado</span>
                </label>
                <input type="text" class="form-control" id="nombre<?php echo $prefijo; ?>" name="nombre" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <!-- Sección 2: Datos Personales -->
    <h5 class="mb-3"><i class="fas fa-user-tag mr-2"></i> Datos Personales</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <!-- Foto de Perfil -->
            <div class="mb-3">
                <label for="foto_perfil<?php echo $prefijo; ?>" class="form-label">Foto de Perfil <span class="badge bg-secondary">Opcional</span></label>
                <input type="file" class="form-control" id="foto_perfil<?php echo $prefijo; ?>" name="foto_perfil" 
                       accept=".jpg,.jpeg,.png,.pdf,.webp" 
                       onchange="window.previewImage(this, 'preview<?php echo $prefijo; ?>', 'previewImage<?php echo $prefijo; ?>')">
                <small class="text-muted d-block mt-1">Formatos permitidos: JPG, JPEG, PNG, WEBP (Máx: 5MB). No es obligatorio.</small>
                <div id="preview<?php echo $prefijo; ?>" class="mt-2" style="display:none;">
                    <img id="previewImage<?php echo $prefijo; ?>" src="#" alt="Vista previa" style="max-width: 140px; max-height: 140px; border-radius: 8px; border: 2px solid #0d6efd; padding: 2px; object-fit: cover;">
                </div>
            </div>

            <div class="mb-3">
                <label for="fecha_nac<?php echo $prefijo; ?>" class="form-label required">
                    Fecha de Nacimiento
                    <span id="badgeFechaNacBloqueada<?php echo $prefijo; ?>" class="badge bg-secondary ms-2" style="display:none;"><i class="fas fa-lock"></i> Registrada</span>
                </label>
                <input type="date" class="form-control" id="fecha_nac<?php echo $prefijo; ?>" name="fecha_nac" required value="<?php echo htmlspecialchars($_POST['fecha_nac'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label class="form-label required">Género</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="genero" id="genero_m<?php echo $prefijo; ?>" value="Masculino" required <?php echo (isset($_POST['genero']) && $_POST['genero'] === 'Masculino') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="genero_m<?php echo $prefijo; ?>">Masculino</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="genero" id="genero_f<?php echo $prefijo; ?>" value="Femenino" <?php echo (isset($_POST['genero']) && $_POST['genero'] === 'Femenino') ? 'checked' : ''; ?> >
                        <label class="form-check-label" for="genero_f<?php echo $prefijo; ?>">Femenino</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="genero" id="genero_o<?php echo $prefijo; ?>" value="Otro" <?php echo (isset($_POST['genero']) && $_POST['genero'] === 'Otro') ? 'checked' : ''; ?> >
                        <label class="form-check-label" for="genero_o<?php echo $prefijo; ?>">Otro</label>
                    </div>
                </div>
            </div>

            <div class="mb-3" id="embarazoContainer<?php echo $prefijo; ?>" style="display: none;">
                <label class="form-label">¿Está embarazada?</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="embarazada" id="embarazada_si<?php echo $prefijo; ?>" value="1" <?php echo (isset($_POST['embarazada']) && $_POST['embarazada'] == '1') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="embarazada_si<?php echo $prefijo; ?>">Sí</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="embarazada" id="embarazada_no<?php echo $prefijo; ?>" value="0" <?php echo (!isset($_POST['embarazada']) || $_POST['embarazada'] === '0') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="embarazada_no<?php echo $prefijo; ?>">No</label>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edo_civil<?php echo $prefijo; ?>" class="form-label required">Estado Civil</label>
                <select class="custom-select" id="edo_civil<?php echo $prefijo; ?>" name="edo_civil" required>
                    <option value="" selected disabled>Seleccione una opción</option>
                    <?php foreach ($estadosCiviles as $id => $estadoCivil): ?>
                        <option value="<?php echo htmlspecialchars($estadoCivil); ?>" <?php echo (isset($_POST['edo_civil']) && $_POST['edo_civil'] === $estadoCivil) ? 'selected' : ''; ?>><?php echo htmlspecialchars($estadoCivil); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Etnia (Condicional) -->
            <div class="mb-3">
                <label class="form-label">¿Pertenece a alguna etnia?</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="posee_etnia" id="etnia_no<?php echo $prefijo; ?>" value="no" <?php echo (!isset($_POST['posee_etnia']) || $_POST['posee_etnia'] === 'no') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="etnia_no<?php echo $prefijo; ?>">No</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="posee_etnia" id="etnia_si<?php echo $prefijo; ?>" value="si" <?php echo (isset($_POST['posee_etnia']) && $_POST['posee_etnia'] === 'si') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="etnia_si<?php echo $prefijo; ?>">Sí</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-3" id="etniaContainer<?php echo $prefijo; ?>" style="display: none;">
                <label for="etnia<?php echo $prefijo; ?>" class="form-label">Especifique la etnia</label>
                <input type="text" class="form-control" id="etnia<?php echo $prefijo; ?>" name="etnia" placeholder="Ej: Wayúu, Añú, etc." value="<?php echo htmlspecialchars($_POST['etnia'] ?? ''); ?>">
                <small class="text-muted">Indique el nombre de la etnia a la que pertenece</small>
            </div>
        </div>
    </div>

    <!-- Sección 3: Formación Académica -->
    <h5 class="mb-3"><i class="fas fa-graduation-cap mr-2"></i> Formación Académica</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="mb-3">
                <label for="carrera<?php echo $prefijo; ?>" class="form-label required">Programa</label>
                <select name="carrera" id="carrera<?php echo $prefijo; ?>" class="form-control" required>
                    <option value="">-- Seleccione una carrera --</option>
                    <?php foreach ($carreras as $carrera): ?>
                        <?php 
                        $cuposDiurno = $todosLosCupos[$carrera['id']]['Diurno'] ?? null;
                        $cuposNocturno = $todosLosCupos[$carrera['id']]['Nocturno'] ?? null;
                        $tieneAlgunCupo = ($cuposDiurno['tiene_cupo'] ?? false) || ($cuposNocturno['tiene_cupo'] ?? false);
                        ?>
                        <option value="<?php echo htmlspecialchars($carrera['id']); ?>" 
                            data-cupos='<?php echo htmlspecialchars(json_encode($todosLosCupos[$carrera['id']])); ?>'
                            data-nombre="<?php echo htmlspecialchars($carrera['nombre']); ?>"
                            <?php echo (isset($_POST['carrera']) && $_POST['carrera'] == $carrera['id']) ? 'selected' : ''; ?>
                            style="<?php echo (!$modo_preinscripcion && !$tieneAlgunCupo) ? 'color: red;' : ''; ?>">
                            <?php echo htmlspecialchars($carrera['nombre']); ?>
                            <?php if (!$modo_preinscripcion && !$tieneAlgunCupo): ?>
                                (SIN CUPOS)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$modo_preinscripcion): ?>
                <div id="cuposInfo<?php echo $prefijo; ?>" class="mt-2 small"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($modo_preinscripcion): ?>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="turno<?php echo $prefijo; ?>" class="form-label required">Turno</label>
                <select name="turno" id="turno<?php echo $prefijo; ?>" class="form-control" required disabled>
                    <option value="">-- Primero seleccione una carrera --</option>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <div class="<?php echo $modo_preinscripcion ? 'col-md-4' : 'col-md-8'; ?>">
            <div class="mb-3">
                <label for="sede<?php echo $prefijo; ?>" class="form-label required">Sede</label>
                <select name="sede" id="sede<?php echo $prefijo; ?>" class="form-control" required>
                    <option value="">-- Seleccione una sede --</option>
                    <option value="Puerto Cabello" <?php echo (isset($_POST['sede']) && $_POST['sede'] === 'Puerto Cabello') ? 'selected' : ''; ?>>Puerto Cabello</option>
                    <option value="COEF" <?php echo (isset($_POST['sede']) && $_POST['sede'] === 'COEF') ? 'selected' : ''; ?>>COEF</option>
                </select>
            </div>
        </div>

        <!-- Asignado OPSU y Lapso Académico -->
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label required"><strong>¿Asignado por OPSU?</strong></label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="asignado_opsu" id="opsu_si<?php echo $prefijo; ?>" value="Sí" <?php echo (isset($_POST['asignado_opsu']) && $_POST['asignado_opsu'] === 'Sí') ? 'checked' : ''; ?> required>
                        <label class="form-check-label" for="opsu_si<?php echo $prefijo; ?>">Sí</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="asignado_opsu" id="opsu_no<?php echo $prefijo; ?>" value="No" <?php echo (!isset($_POST['asignado_opsu']) || $_POST['asignado_opsu'] !== 'Sí') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="opsu_no<?php echo $prefijo; ?>">No</label>
                    </div>
                </div>
                <small class="text-muted">Indique si fue asignado a través del Sistema Nacional de Ingreso (OPSU).</small>
            </div>
        </div>

        <div class="col-md-6" id="contenedorLapsoOpsu<?php echo $prefijo; ?>" style="<?php echo (isset($_POST['asignado_opsu']) && $_POST['asignado_opsu'] === 'Sí') ? '' : 'display: none;'; ?>">
            <div class="mb-3">
                <label for="lapso_opsu<?php echo $prefijo; ?>" class="form-label required"><strong>Lapso Académico de Asignación OPSU</strong></label>
                <select name="lapso_opsu" id="lapso_opsu<?php echo $prefijo; ?>" class="form-control">
                    <option value="">-- Seleccione el lapso académico --</option>
                    <?php 
                    $periodosOpsu = function_exists('obtenerPeriodosAcademicos') ? obtenerPeriodosAcademicos($db) : [];
                    $lapsoActualSel = $_POST['lapso_opsu'] ?? '';
                    foreach ($periodosOpsu as $per): 
                        $nomPer = $per['nombre_periodo'] ?? ($per['codigo_lapso'] ?? '');
                    ?>
                        <option value="<?php echo htmlspecialchars($nomPer); ?>" <?php echo ($lapsoActualSel === $nomPer) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nomPer); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Indique de qué lapso académico proviene su asignación OPSU.</small>
            </div>
        </div>
    </div>
        
        <!-- Títulos Obtenidos e Instituciones -->
        <div class="col-md-12">
            <div class="mb-3">
                <label class="form-label">Títulos Obtenidos e Instituciones</label>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="titulos<?php echo $prefijo; ?>" name="titulos[]" 
                               placeholder="Título obtenido" value="<?php echo htmlspecialchars($_POST['titulos'][0] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="institutos<?php echo $prefijo; ?>" name="institutos[]" 
                               placeholder="Institución donde obtuvo el título" value="<?php echo htmlspecialchars($_POST['institutos'][0] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-control pais-titulo" id="pais_titulo<?php echo $prefijo; ?>" name="pais_titulo[]">
                            <option value="">País de emisión</option>
                            <option value="Venezuela" <?php echo (isset($_POST['pais_titulo'][0]) && $_POST['pais_titulo'][0] === 'Venezuela') ? 'selected' : ''; ?>>Venezuela</option>
                            <option value="Otro" <?php echo (isset($_POST['pais_titulo'][0]) && $_POST['pais_titulo'][0] === 'Otro') ? 'selected' : ''; ?>>Otro país</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary w-100" id="addTituloInstituto<?php echo $prefijo; ?>">
                            <i class="fas fa-plus mr-1"></i> Agregar
                        </button>
                    </div>
                </div>
                <div class="row g-3 mb-3 legalizado-row" id="legalizadoRow<?php echo $prefijo; ?>" style="display: none;">
                    <div class="col-md-12">
                        <label class="form-label">¿Fue legalizado en Venezuela?</label>
                        <select class="form-control legalizado-titulo" id="legalizado_titulo<?php echo $prefijo; ?>" name="legalizado_titulo[]">
                            <option value="">Seleccione...</option>
                            <option value="Sí" <?php echo (isset($_POST['legalizado_titulo'][0]) && $_POST['legalizado_titulo'][0] === 'Sí') ? 'selected' : ''; ?>>Sí</option>
                            <option value="No" <?php echo (isset($_POST['legalizado_titulo'][0]) && $_POST['legalizado_titulo'][0] === 'No') ? 'selected' : ''; ?>>No</option>
                        </select>
                        <div class="text-danger legalizado-message mt-2" style="display:none;">Debe legalizar el título en Venezuela.</div>
                    </div>
                </div>
                <div id="titulosInstitutosContainer<?php echo $prefijo; ?>">
                    <!-- Aquí se agregarán los pares de títulos e instituciones -->
                    <?php if (!empty($_POST['titulos']) && is_array($_POST['titulos']) && !empty($_POST['institutos']) && is_array($_POST['institutos'])): ?>
                        <?php foreach ($_POST['titulos'] as $index => $titulo): ?>
                            <?php if ($index === 0) continue; ?>
                            <?php $instituto = $_POST['institutos'][$index] ?? ''; ?>
                            <?php $paisTitulo = $_POST['pais_titulo'][$index] ?? ''; ?>
                            <?php $legalizadoTitulo = $_POST['legalizado_titulo'][$index] ?? ''; ?>
                            <?php if ($titulo !== '' || $instituto !== '' || $paisTitulo !== ''): ?>
                                <div class="title-pair-group">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" name="titulos[]" value="<?php echo htmlspecialchars($titulo); ?>" placeholder="Título obtenido" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="institutos[]" value="<?php echo htmlspecialchars($instituto); ?>" placeholder="Institución" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-control pais-titulo" name="pais_titulo[]">
                                                <option value="">País de emisión</option>
                                                <option value="Venezuela" <?php echo ($paisTitulo === 'Venezuela') ? 'selected' : ''; ?>>Venezuela</option>
                                                <option value="Otro" <?php echo ($paisTitulo === 'Otro') ? 'selected' : ''; ?>>Otro país</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-outline-danger remove-field w-100">
                                                <i class="fas fa-minus"></i> Eliminar
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row g-3 mb-3 legalizado-row" style="display: <?php echo ($paisTitulo !== '' && $paisTitulo !== 'Venezuela') ? 'flex' : 'none'; ?>;">
                                        <div class="col-md-12">
                                            <label class="form-label">¿Fue legalizado en Venezuela?</label>
                                            <select class="form-control legalizado-titulo" name="legalizado_titulo[]">
                                                <option value="">Seleccione...</option>
                                                <option value="Sí" <?php echo ($legalizadoTitulo === 'Sí') ? 'selected' : ''; ?>>Sí</option>
                                                <option value="No" <?php echo ($legalizadoTitulo === 'No') ? 'selected' : ''; ?>>No</option>
                                            </select>
                                            <div class="text-danger legalizado-message mt-2" style="display: <?php echo ($legalizadoTitulo === 'No') ? 'block' : 'none'; ?>;">Debe legalizar el título en Venezuela.</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección 4: Ubicación y Vivienda -->
    <h5 class="mb-3"><i class="fas fa-home mr-2"></i> Vivienda</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="estado<?php echo $prefijo; ?>" class="form-label required">Estado</label>
                <select class="form-control" id="estado<?php echo $prefijo; ?>" name="estado" required>
                    <option value="">Seleccione un estado</option>
                    <?php foreach ($estados as $estado): ?>
                        <option value="<?php echo htmlspecialchars($estado['id_estado']); ?>" <?php echo (isset($_POST['estado']) && $_POST['estado'] == $estado['id_estado']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($estado['estado']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="municipio<?php echo $prefijo; ?>" class="form-label required">Municipio</label>
                <select class="form-control" id="municipio<?php echo $prefijo; ?>" name="municipio" required disabled>
                    <option value="">Primero seleccione un estado</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="parroquia<?php echo $prefijo; ?>" class="form-label required">Parroquia</label>
                <select class="form-control" id="parroquia<?php echo $prefijo; ?>" name="parroquia" required disabled>
                    <option value="">Primero seleccione un municipio</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="comuna<?php echo $prefijo; ?>" class="form-label">Comuna</label>
                <input type="text" class="form-control" id="comuna<?php echo $prefijo; ?>" name="comuna" value="<?php echo htmlspecialchars($_POST['comuna'] ?? ''); ?>" placeholder="Ingrese la comuna">
            </div>
            
            <!-- Campos ocultos para los nombres -->
            <input type="hidden" id="nombre_estado<?php echo $prefijo; ?>" name="nombre_estado">
            <input type="hidden" id="nombre_municipio<?php echo $prefijo; ?>" name="nombre_municipio">
            <input type="hidden" id="nombre_parroquia<?php echo $prefijo; ?>" name="nombre_parroquia">
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="direccion<?php echo $prefijo; ?>" class="form-label required">Dirección</label>
                <textarea class="form-control" id="direccion<?php echo $prefijo; ?>" name="direccion" rows="2" required><?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="casaapto<?php echo $prefijo; ?>" class="form-label">Tipo de Vivienda</label>
                <select class="form-control" id="casaapto<?php echo $prefijo; ?>" name="tipo_vivienda">
                    <option value="">Seleccione...</option>
                    <?php foreach ($tiposVivienda as $id => $vivienda): ?>
                        <option value="<?php echo htmlspecialchars($vivienda); ?>" <?php echo (isset($_POST['tipo_vivienda']) && $_POST['tipo_vivienda'] === $vivienda) ? 'selected' : ''; ?>><?php echo htmlspecialchars($vivienda); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="punto_referencia<?php echo $prefijo; ?>" class="form-label">Punto de Referencia</label>
                <input type="text" class="form-control" id="punto_referencia<?php echo $prefijo; ?>" name="punto_referencia" value="<?php echo htmlspecialchars($_POST['punto_referencia'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <!-- Sección 5: Situación Familiar -->
    <h5 class="mb-3"><i class="fas fa-users mr-2"></i> Situación Familiar</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="grupo_familiar<?php echo $prefijo; ?>" class="form-label">Grupo Familiar</label>
                <input type="number" class="form-control" id="grupo_familiar<?php echo $prefijo; ?>" name="grupo_familiar" min="1" placeholder="Número de personas">
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="acargo_usted<?php echo $prefijo; ?>" class="form-label">Personas a su cargo</label>
                <input type="number" class="form-control" id="acargo_usted<?php echo $prefijo; ?>" name="acargo_usted" min="0">
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="fuente_ingresos<?php echo $prefijo; ?>" class="form-label">Fuente de Ingresos</label>
                <select class="custom-select d-block w-100" id="fuente_ingresos<?php echo $prefijo; ?>" name="fuente_ingresos">
                    <option value="">Seleccione una opción</option>
                    <?php foreach ($ingresos as $id => $ingreso): ?>
                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($ingreso); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="tenencia_vivienda<?php echo $prefijo; ?>" class="form-label">Tenencia de Vivienda</label>
                <select class="form-control" id="tenencia_vivienda<?php echo $prefijo; ?>" name="tenencia_vivienda">
                    <option value="">Seleccione...</option>
                    <?php foreach ($tenenciasVivienda as $id => $tenencia): ?>
                        <option value="<?php echo htmlspecialchars($tenencia); ?>"><?php echo htmlspecialchars($tenencia); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Sección 6: Salud -->
    <h5 class="mb-3"><i class="fas fa-heartbeat mr-2"></i> Salud</h5>
    <div class="row g-3 mb-4">
        <!-- Discapacidad (Condicional) -->
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">¿Posee alguna discapacidad?</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="posee_discapacidad" id="discapacidad_no<?php echo $prefijo; ?>" value="no" <?php echo (!isset($_POST['posee_discapacidad']) || $_POST['posee_discapacidad'] === 'no') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="discapacidad_no<?php echo $prefijo; ?>">No</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="posee_discapacidad" id="discapacidad_si<?php echo $prefijo; ?>" value="si" <?php echo (isset($_POST['posee_discapacidad']) && $_POST['posee_discapacidad'] === 'si') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="discapacidad_si<?php echo $prefijo; ?>">Sí</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-3" id="discapacidadContainer<?php echo $prefijo; ?>" style="display: none;">
                <label for="discapacidad<?php echo $prefijo; ?>" class="form-label">Especifique el tipo de discapacidad</label>
                <input type="text" class="form-control" id="discapacidad<?php echo $prefijo; ?>" name="discapacidad" placeholder="Ej: Visual, Auditiva, Motora, etc.">
                <small class="text-muted">Indique el tipo de discapacidad que posee</small>
            </div>
        </div>
        
        <!-- Enfermedad (Condicional) -->
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">¿Posee alguna enfermedad?</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="posee_enfermedad" id="enfermedad_no<?php echo $prefijo; ?>" value="no" <?php echo (!isset($_POST['posee_enfermedad']) || $_POST['posee_enfermedad'] === 'no') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enfermedad_no<?php echo $prefijo; ?>">No</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="posee_enfermedad" id="enfermedad_si<?php echo $prefijo; ?>" value="si" <?php echo (isset($_POST['posee_enfermedad']) && $_POST['posee_enfermedad'] === 'si') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enfermedad_si<?php echo $prefijo; ?>">Sí</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-3" id="enfermedadContainer<?php echo $prefijo; ?>" style="display: none;">
                <label for="enfermedad<?php echo $prefijo; ?>" class="form-label">Especifique la(s) enfermedad(es)</label>
                <input type="text" class="form-control" id="enfermedad<?php echo $prefijo; ?>" name="enfermedad" placeholder="Ej: Diabetes, Hipertensión, etc.">
                <small class="text-muted">Puede indicar múltiples enfermedades separadas por coma</small>
            </div>
        </div>
    </div>

    <!-- Sección 7: Contacto -->
    <h5 class="mb-3"><i class="fas fa-address-book mr-2"></i> Contacto</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="tlf<?php echo $prefijo; ?>" class="form-label required">Teléfono Casa</label>
                <input type="tel" class="form-control" id="tlf<?php echo $prefijo; ?>" name="tlf" required value="<?php echo htmlspecialchars($_POST['tlf'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="cel<?php echo $prefijo; ?>" class="form-label">Teléfono Celular</label>
                <input type="tel" class="form-control" id="cel<?php echo $prefijo; ?>" name="cel" value="<?php echo htmlspecialchars($_POST['cel'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="email<?php echo $prefijo; ?>" class="form-label required">Correo Electrónico</label>
                <input type="email" class="form-control" id="email<?php echo $prefijo; ?>" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="num_telf_opc<?php echo $prefijo; ?>" class="form-label">Teléfono Opcional</label>
                <input type="tel" class="form-control" id="num_telf_opc<?php echo $prefijo; ?>" name="num_telf_opc" value="<?php echo htmlspecialchars($_POST['num_telf_opc'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <!-- Sección 8: Potencialidades -->
    <h5 class="mb-3"><i class="fas fa-lightbulb mr-2"></i> Potencialidades</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="mb-3">
                <label for="potencialidades<?php echo $prefijo; ?>" class="form-label">Potencialidades</label>
                <textarea class="form-control" id="potencialidades<?php echo $prefijo; ?>" name="potencialidades" rows="3" placeholder="Describe tus habilidades, fortalezas o talentos relevantes"><?php echo htmlspecialchars($_POST['potencialidades'] ?? ''); ?></textarea>
                <small class="text-muted">Ejemplo: Deporte, Cultura, comunicación, creatividad, hobby etc.</small>
            </div>
        </div>
    </div>

    <!-- Sección 9: Datos del Sistema -->
    <h5 class="mb-3"><i class="fas fa-university mr-2"></i> Datos del Sistema</h5>
    <div class="row g-3">
        <?php if (!$modo_preinscripcion): ?>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="fecha_ingreso<?php echo $prefijo; ?>" class="form-label required">Fecha de Ingreso</label>
                    <input type="date" class="form-control" id="fecha_ingreso<?php echo $prefijo; ?>" name="fecha_ingreso" value="<?php echo htmlspecialchars($_POST['fecha_ingreso'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="status<?php echo $prefijo; ?>" class="form-label required">Status</label>
                    <select class="custom-select" id="status<?php echo $prefijo; ?>" name="status" required>
                        <option value="" selected disabled>Seleccione un status</option>
                        <?php foreach ($opcionesStatus as $valor => $texto): ?>
                            <option value="<?php echo htmlspecialchars($valor); ?>" <?php echo (isset($_POST['status']) && $_POST['status'] == $valor) ? 'selected' : ''; ?>><?php echo htmlspecialchars($texto); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php else: ?>
            <input type="hidden" id="fecha_ingreso<?php echo $prefijo; ?>" name="fecha_ingreso" value="<?php echo htmlspecialchars($_POST['fecha_ingreso'] ?? $fechaSolicitud); ?>">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($_POST['status'] ?? 'Pendiente'); ?>">
            <div class="col-md-12">
                <div class="alert alert-info mb-0">
                    Su preinscripción se registrará con fecha de solicitud <strong><?php echo htmlspecialchars($_POST['fecha_ingreso'] ?? $fechaSolicitud); ?></strong> y quedará como <strong>Pendiente</strong> hasta revisión administrativa.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <?php if ($esModal): ?>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i> Cancelar
            </button>
        <?php else: ?>
            <button type="button" onclick="window.location.href='index.php'" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Regresar
        </button>
        <?php endif; ?>
        
        <div>
            <button type="reset" class="btn btn-secondary mr-2">
                <i class="fas fa-eraser mr-1"></i> Limpiar
            </button>
            <button type="submit" class="btn btn-primary" id="btnSubmitForm<?php echo $prefijo; ?>">
                <i class="fas fa-save mr-1"></i> <?php echo $modo_preinscripcion ? 'Enviar Preinscripción' : 'Guardar'; ?>
            </button>
        </div>
    </div>
</form>

<script>
// Script para manejar la adición de campos de título e institución juntos
document.addEventListener('DOMContentLoaded', function() {
    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateLegalizadoRow(selectElement) {
        const pairGroup = selectElement.closest('.title-pair-group') || selectElement.closest('.row.g-3.mb-3');
        if (!pairGroup) {
            return;
        }

        const legalizadoRow = pairGroup.querySelector('.legalizado-row');
        const legalizadoSelect = pairGroup.querySelector('.legalizado-titulo');
        const legalizadoMessage = pairGroup.querySelector('.legalizado-message');

        if (!legalizadoRow || !legalizadoSelect) {
            return;
        }

        if (selectElement.value && selectElement.value !== 'Venezuela') {
            legalizadoRow.style.display = 'flex';
            legalizadoSelect.required = true;
        } else {
            legalizadoRow.style.display = 'none';
            legalizadoSelect.required = false;
            legalizadoSelect.value = '';
            if (legalizadoMessage) {
                legalizadoMessage.style.display = 'none';
            }
        }
    }

    function updateLegalizadoMessage(selectElement) {
        const pairGroup = selectElement.closest('.title-pair-group') || selectElement.closest('.row.g-3.mb-3');
        const legalizadoMessage = pairGroup ? pairGroup.querySelector('.legalizado-message') : null;
        if (legalizadoMessage) {
            legalizadoMessage.style.display = selectElement.value === 'No' ? 'block' : 'none';
        }
    }

    function updateMainLegalizadoRow(prefix) {
        const paisSelect = document.getElementById('pais_titulo' + prefix);
        const legalizadoRow = document.getElementById('legalizadoRow' + prefix);
        const legalizadoSelect = document.getElementById('legalizado_titulo' + prefix);
        const legalizadoMessage = legalizadoRow ? legalizadoRow.querySelector('.legalizado-message') : null;

        if (!paisSelect || !legalizadoRow || !legalizadoSelect) {
            return;
        }

        if (paisSelect.value && paisSelect.value !== 'Venezuela') {
            legalizadoRow.style.display = 'flex';
            legalizadoSelect.required = true;
        } else {
            legalizadoRow.style.display = 'none';
            legalizadoSelect.required = false;
            legalizadoSelect.value = '';
            if (legalizadoMessage) {
                legalizadoMessage.style.display = 'none';
            }
        }
    }

    function addTitleInstitutionPair(prefix) {
        const tituloInput = document.getElementById('titulos' + prefix);
        const institutoInput = document.getElementById('institutos' + prefix);
        const paisSelect = document.getElementById('pais_titulo' + prefix);
        const legalizadoSelect = document.getElementById('legalizado_titulo' + prefix);

        const titulo = tituloInput ? tituloInput.value.trim() : '';
        const instituto = institutoInput ? institutoInput.value.trim() : '';
        const paisTitulo = paisSelect ? paisSelect.value : '';
        const legalizadoTitulo = legalizadoSelect ? legalizadoSelect.value : '';

        if (titulo === '' || instituto === '') {
            alert('Por favor complete ambos campos: título e institución');
            return;
        }

        const container = document.getElementById('titulosInstitutosContainer' + prefix);
        const newPair = document.createElement('div');
        newPair.className = 'title-pair-group';
        newPair.innerHTML = `
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="titulos[]" value="${escapeHtml(titulo)}" placeholder="Título obtenido" readonly>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="institutos[]" value="${escapeHtml(instituto)}" placeholder="Institución" readonly>
                </div>
                <div class="col-md-3">
                    <select class="form-control pais-titulo" name="pais_titulo[]">
                        <option value="">País de emisión</option>
                        <option value="Venezuela" ${paisTitulo === 'Venezuela' ? 'selected' : ''}>Venezuela</option>
                        <option value="Otro" ${paisTitulo === 'Otro' ? 'selected' : ''}>Otro país</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger remove-field w-100">
                        <i class="fas fa-minus"></i> Eliminar
                    </button>
                </div>
            </div>
            <div class="row g-3 mb-3 legalizado-row" style="display: ${paisTitulo !== '' && paisTitulo !== 'Venezuela' ? 'flex' : 'none'};">
                <div class="col-md-12">
                    <label class="form-label">¿Fue legalizado en Venezuela?</label>
                    <select class="form-control legalizado-titulo" name="legalizado_titulo[]">
                        <option value="">Seleccione...</option>
                        <option value="Sí" ${legalizadoTitulo === 'Sí' ? 'selected' : ''}>Sí</option>
                        <option value="No" ${legalizadoTitulo === 'No' ? 'selected' : ''}>No</option>
                    </select>
                    <div class="text-danger legalizado-message mt-2" style="display: ${legalizadoTitulo === 'No' ? 'block' : 'none'};">Debe legalizar el título en Venezuela.</div>
                </div>
            </div>
        `;
        container.appendChild(newPair);

        document.getElementById('titulos' + prefix).value = '';
        document.getElementById('institutos' + prefix).value = '';
        if (paisSelect) {
            paisSelect.value = '';
        }
        if (legalizadoSelect) {
            legalizadoSelect.value = '';
        }
        document.getElementById('titulos' + prefix).focus();

        newPair.querySelector('.remove-field').addEventListener('click', function() {
            container.removeChild(newPair);
        });
    }

    // Agregar manejadores de eliminación a los pares ya existentes en POST
    const existingRemoveButtons = document.querySelectorAll('#titulosInstitutosContainer<?php echo $prefijo; ?> .remove-field');
    existingRemoveButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const pairRow = button.closest('.title-pair-group');
            if (pairRow) {
                pairRow.parentNode.removeChild(pairRow);
            }
        });
    });

    // Evento para el botón de añadir
    const addButton = document.getElementById('addTituloInstituto' + '<?php echo $prefijo; ?>');
    if (addButton) {
        addButton.addEventListener('click', function() {
            addTitleInstitutionPair('<?php echo $prefijo; ?>');
        });
    }

    // Actualizar legalizado para el row principal y los pares existentes
    const paisTituloField = document.getElementById('pais_titulo' + '<?php echo $prefijo; ?>');
    const legalizadoField = document.getElementById('legalizado_titulo' + '<?php echo $prefijo; ?>');
    if (paisTituloField) {
        paisTituloField.addEventListener('change', function() {
            updateMainLegalizadoRow('<?php echo $prefijo; ?>');
        });
        updateMainLegalizadoRow('<?php echo $prefijo; ?>');
    }
    if (legalizadoField) {
        legalizadoField.addEventListener('change', function() {
            updateLegalizadoMessage(this);
        });
        updateLegalizadoMessage(legalizadoField);
    }

    const container = document.getElementById('titulosInstitutosContainer' + '<?php echo $prefijo; ?>');
    if (container) {
        container.addEventListener('change', function(e) {
            if (e.target.matches('.pais-titulo')) {
                updateLegalizadoRow(e.target);
            }
            if (e.target.matches('.legalizado-titulo')) {
                updateLegalizadoMessage(e.target);
            }
        });
    }

    // Manejar el evento Enter en los campos principales
    const titulosField = document.getElementById('titulos' + '<?php echo $prefijo; ?>');
    const institutosField = document.getElementById('institutos' + '<?php echo $prefijo; ?>');

    if (titulosField) {
        titulosField.addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                e.preventDefault();
                addTitleInstitutionPair('<?php echo $prefijo; ?>');
            }
        });
    }

    if (institutosField) {
        institutosField.addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                e.preventDefault();
                addTitleInstitutionPair('<?php echo $prefijo; ?>');
            }
        });
    }
});

// Manejo del campo de cédula
document.addEventListener('DOMContentLoaded', function() {
    const tipoCedula = document.getElementById('tipo_cedula<?php echo $prefijo; ?>');
    const numeroCedula = document.getElementById('numero_cedula<?php echo $prefijo; ?>');
    const idUsuario = document.getElementById('idusuario<?php echo $prefijo; ?>');
    
    if (tipoCedula && numeroCedula && idUsuario) {
        function actualizarCedulaCompleta() {
            const numeroLimpio = numeroCedula.value.replace(/[^0-9]/g, '');
            numeroCedula.value = numeroLimpio;
            idUsuario.value = tipoCedula.value + numeroLimpio;
        }
        
        tipoCedula.addEventListener('change', actualizarCedulaCompleta);
        numeroCedula.addEventListener('input', actualizarCedulaCompleta);
        actualizarCedulaCompleta();
    }
});

// Función para vista previa de imagen
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const previewImage = document.getElementById('previewImage' + '<?php echo $prefijo; ?>');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileType = file.type;
        
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
        if (!allowedTypes.includes(fileType)) {
            alert('Error: Solo se permiten archivos JPG, JPEG, PNG, WEBP y PDF.');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            alert('Error: El archivo no debe superar los 5MB.');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        if (fileType.startsWith('image/')) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                preview.style.display = 'block';
            }
            
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '<div class="alert alert-info p-2">Archivo PDF seleccionado</div>';
            preview.style.display = 'block';
        }
    } else {
        preview.style.display = 'none';
    }
}

// =============================================
// SCRIPT PARA SELECTS AUTOMÁTICOS DE UBICACIÓN
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const prefijo = '<?php echo $prefijo; ?>';
    const estadoSelect = document.getElementById('estado' + prefijo);
    const municipioSelect = document.getElementById('municipio' + prefijo);
    const parroquiaSelect = document.getElementById('parroquia' + prefijo);
    const apiBase = '<?php echo isset($modo_preinscripcion) && $modo_preinscripcion ? 'admin/' : ''; ?>';
    
    const nombreEstadoInput = document.getElementById('nombre_estado' + prefijo);
    const nombreMunicipioInput = document.getElementById('nombre_municipio' + prefijo);
    const nombreParroquiaInput = document.getElementById('nombre_parroquia' + prefijo);
    
    if (!estadoSelect || !municipioSelect || !parroquiaSelect) {
        console.error('No se encontraron los selects de ubicación');
        return;
    }
    
    estadoSelect.addEventListener('change', function() {
        const estadoId = this.value;
        const estadoTexto = this.options[this.selectedIndex].text;
        
        if (nombreEstadoInput) {
            nombreEstadoInput.value = estadoTexto;
        }
        
        resetSelect(municipioSelect, 'Cargando municipios...', true);
        resetSelect(parroquiaSelect, 'Primero seleccione un municipio', true);
        
        if (!estadoId) {
            resetSelect(municipioSelect, 'Primero seleccione un estado', true);
            return;
        }
        
        cargarMunicipios(estadoId);
    });
    
    municipioSelect.addEventListener('change', function() {
        const municipioId = this.value;
        const municipioTexto = this.options[this.selectedIndex].text;
        
        if (nombreMunicipioInput) {
            nombreMunicipioInput.value = municipioTexto;
        }
        
        resetSelect(parroquiaSelect, 'Cargando parroquias...', true);
        if (nombreParroquiaInput) {
            nombreParroquiaInput.value = '';
        }
        
        if (!municipioId) {
            resetSelect(parroquiaSelect, 'Primero seleccione un municipio', true);
            return;
        }
        
        cargarParroquias(municipioId);
    });
    
    parroquiaSelect.addEventListener('change', function() {
        const parroquiaTexto = this.options[this.selectedIndex].text;
        
        if (nombreParroquiaInput) {
            nombreParroquiaInput.value = this.value ? parroquiaTexto : '';
        }
    });
    
    function cargarMunicipiosAlternativo(estadoId, selectedMunicipioId = '', selectedParroquiaId = '') {
        fetch(apiBase + 'api/obtener_municipios.php?estado_id=' + estadoId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.municipios) {
                updateSelect(municipioSelect, data.municipios, 'Seleccione un municipio', false);
                municipioSelect.disabled = false;
                if (selectedMunicipioId) {
                    municipioSelect.value = selectedMunicipioId;
                    if (municipioSelect.value === selectedMunicipioId && nombreMunicipioInput) {
                        nombreMunicipioInput.value = municipioSelect.options[municipioSelect.selectedIndex]?.text || '';
                    }
                    if (selectedMunicipioId) {
                        cargarParroquias(selectedMunicipioId, selectedParroquiaId);
                    }
                }
            } else {
                resetSelect(municipioSelect, 'No hay municipios disponibles', false);
                municipioSelect.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error alternativo:', error);
            resetSelect(municipioSelect, 'Error de conexión', true);
        });
    }

    function cargarMunicipios(estadoId, selectedMunicipioId = '', selectedParroquiaId = '') {
        const formData = new FormData();
        formData.append('estado_id', estadoId);

        fetch(apiBase + 'api/obtener_municipios.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.municipios) {
                updateSelect(municipioSelect, data.municipios, 'Seleccione un municipio', false);
                municipioSelect.disabled = false;
                if (selectedMunicipioId) {
                    municipioSelect.value = selectedMunicipioId;
                    if (municipioSelect.value === selectedMunicipioId && nombreMunicipioInput) {
                        nombreMunicipioInput.value = municipioSelect.options[municipioSelect.selectedIndex]?.text || '';
                    }
                    if (selectedMunicipioId) {
                        cargarParroquias(selectedMunicipioId, selectedParroquiaId);
                    }
                }
            } else {
                resetSelect(municipioSelect, 'No hay municipios disponibles', false);
                municipioSelect.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error al cargar municipios:', error);
            resetSelect(municipioSelect, 'Error al cargar municipios', true);
            cargarMunicipiosAlternativo(estadoId, selectedMunicipioId, selectedParroquiaId);
        });
    }

    function cargarParroquias(municipioId, selectedParroquiaId = '') {
        const formData = new FormData();
        formData.append('municipio_id', municipioId);
        
        fetch(apiBase + 'api/obtener_parroquias.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.parroquias && data.parroquias.length > 0) {
                updateSelect(parroquiaSelect, data.parroquias, 'Seleccione una parroquia', false);
                parroquiaSelect.disabled = false;
                if (selectedParroquiaId) {
                    parroquiaSelect.value = selectedParroquiaId;
                    if (parroquiaSelect.value === selectedParroquiaId && nombreParroquiaInput) {
                        nombreParroquiaInput.value = parroquiaSelect.options[parroquiaSelect.selectedIndex]?.text || '';
                    }
                } else if (nombreParroquiaInput) {
                    nombreParroquiaInput.value = '';
                }
            } else {
                resetSelect(parroquiaSelect, 'No hay parroquias disponibles', false);
                parroquiaSelect.disabled = false;
                parroquiaSelect.value = '';
            }
        })
        .catch(error => {
            console.error('Error al cargar parroquias:', error);
            resetSelect(parroquiaSelect, 'Error de conexión', true);
            
            fetch(apiBase + 'api/obtener_parroquias.php?municipio_id=' + municipioId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.parroquias) {
                    updateSelect(parroquiaSelect, data.parroquias, 'Seleccione una parroquia', false);
                    parroquiaSelect.disabled = false;
                    if (selectedParroquiaId) {
                        parroquiaSelect.value = selectedParroquiaId;
                        if (parroquiaSelect.value === selectedParroquiaId && nombreParroquiaInput) {
                            nombreParroquiaInput.value = parroquiaSelect.options[parroquiaSelect.selectedIndex]?.text || '';
                        }
                    } else if (nombreParroquiaInput) {
                        nombreParroquiaInput.value = '';
                    }
                } else {
                    resetSelect(parroquiaSelect, 'No hay parroquias disponibles', false);
                    parroquiaSelect.disabled = false;
                }
            })
            .catch(error2 => {
                console.error('Error alternativo:', error2);
                resetSelect(parroquiaSelect, 'Error de conexión', true);
            });
        });
    }
    
    function resetSelect(selectElement, placeholder, disabled) {
        if (!selectElement) return;
        
        selectElement.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        selectElement.appendChild(option);
        
        selectElement.disabled = disabled;
        selectElement.value = '';
    }
    
    function updateSelect(selectElement, options, placeholder, disabled) {
        if (!selectElement) return;
        
        selectElement.innerHTML = '';
        
        const optionPlaceholder = document.createElement('option');
        optionPlaceholder.value = '';
        optionPlaceholder.textContent = placeholder;
        selectElement.appendChild(optionPlaceholder);
        
        options.forEach(option => {
            const opt = document.createElement('option');
            opt.value = option.id;
            opt.textContent = option.nombre;
            selectElement.appendChild(opt);
        });
        
        selectElement.disabled = disabled;
    }
    
    function cargarDatosUbicacionSiExisten() {
        const selectedEstadoId = <?php echo json_encode($_POST['estado'] ?? ''); ?>;
        const selectedMunicipioId = <?php echo json_encode($_POST['municipio'] ?? ''); ?>;
        const selectedParroquiaId = <?php echo json_encode($_POST['parroquia'] ?? ''); ?>;

        if (!selectedEstadoId) {
            return;
        }

        estadoSelect.value = selectedEstadoId;
        if (nombreEstadoInput) {
            nombreEstadoInput.value = estadoSelect.options[estadoSelect.selectedIndex]?.text || '';
        }

        cargarMunicipios(selectedEstadoId, selectedMunicipioId, selectedParroquiaId);
    }
    
    cargarDatosUbicacionSiExisten();
});

// =============================================
// SCRIPT PARA MANEJO CONDICIONAL DE ETNIA, DISCAPACIDAD Y ENFERMEDAD
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const prefijo = '<?php echo $prefijo; ?>';
    
    // Etnia
    const radioEtniaSi = document.getElementById('etnia_si' + prefijo);
    const radioEtniaNo = document.getElementById('etnia_no' + prefijo);
    const etniaContainer = document.getElementById('etniaContainer' + prefijo);
    const campoEtnia = document.getElementById('etnia' + prefijo);
    
    if (radioEtniaSi && radioEtniaNo && etniaContainer && campoEtnia) {
        function toggleEtniaField() {
            if (radioEtniaSi.checked) {
                etniaContainer.style.display = 'block';
                campoEtnia.setAttribute('required', 'required');
            } else {
                etniaContainer.style.display = 'none';
                campoEtnia.removeAttribute('required');
                campoEtnia.value = '';
            }
        }
        
        toggleEtniaField();
        radioEtniaSi.addEventListener('change', toggleEtniaField);
        radioEtniaNo.addEventListener('change', toggleEtniaField);
    }
    
    // Discapacidad
    const radioDiscSi = document.getElementById('discapacidad_si' + prefijo);
    const radioDiscNo = document.getElementById('discapacidad_no' + prefijo);
    const discContainer = document.getElementById('discapacidadContainer' + prefijo);
    const campoDisc = document.getElementById('discapacidad' + prefijo);
    
    if (radioDiscSi && radioDiscNo && discContainer && campoDisc) {
        function toggleDiscapacidadField() {
            if (radioDiscSi.checked) {
                discContainer.style.display = 'block';
                campoDisc.setAttribute('required', 'required');
            } else {
                discContainer.style.display = 'none';
                campoDisc.removeAttribute('required');
                campoDisc.value = '';
            }
        }
        
        toggleDiscapacidadField();
        radioDiscSi.addEventListener('change', toggleDiscapacidadField);
        radioDiscNo.addEventListener('change', toggleDiscapacidadField);
    }
    
    // Enfermedad
    const radioEnfSi = document.getElementById('enfermedad_si' + prefijo);
    const radioEnfNo = document.getElementById('enfermedad_no' + prefijo);
    const enfContainer = document.getElementById('enfermedadContainer' + prefijo);
    const campoEnf = document.getElementById('enfermedad' + prefijo);
    
    if (radioEnfSi && radioEnfNo && enfContainer && campoEnf) {
        function toggleEnfermedadField() {
            if (radioEnfSi.checked) {
                enfContainer.style.display = 'block';
                campoEnf.setAttribute('required', 'required');
            } else {
                enfContainer.style.display = 'none';
                campoEnf.removeAttribute('required');
                campoEnf.value = '';
            }
        }
        
        toggleEnfermedadField();
        radioEnfSi.addEventListener('change', toggleEnfermedadField);
        radioEnfNo.addEventListener('change', toggleEnfermedadField);
    }

    // Embarazo
    const radioGeneroMasculino = document.getElementById('genero_m' + prefijo);
    const radioGeneroFemenino = document.getElementById('genero_f' + prefijo);
    const radioGeneroOtro = document.getElementById('genero_o' + prefijo);
    const embarazoContainer = document.getElementById('embarazoContainer' + prefijo);
    const embarazoSi = document.getElementById('embarazada_si' + prefijo);
    const embarazoNo = document.getElementById('embarazada_no' + prefijo);

    function toggleEmbarazoField() {
        if (radioGeneroFemenino && radioGeneroFemenino.checked) {
            if (embarazoContainer) {
                embarazoContainer.style.display = 'block';
            }
            if (embarazoSi) embarazoSi.setAttribute('required', 'required');
            if (embarazoNo) embarazoNo.setAttribute('required', 'required');
        } else {
            if (embarazoContainer) {
                embarazoContainer.style.display = 'none';
            }
            if (embarazoSi) {
                embarazoSi.removeAttribute('required');
                embarazoSi.checked = false;
            }
            if (embarazoNo) {
                embarazoNo.removeAttribute('required');
                embarazoNo.checked = true;
            }
        }
    }

    if (radioGeneroMasculino) radioGeneroMasculino.addEventListener('change', toggleEmbarazoField);
    if (radioGeneroFemenino) radioGeneroFemenino.addEventListener('change', toggleEmbarazoField);
    if (radioGeneroOtro) radioGeneroOtro.addEventListener('change', toggleEmbarazoField);
    toggleEmbarazoField();

    // =============================================
    // TOGGLE ASIGNADO OPSU Y LAPSO ACADÉMICO
    // =============================================
    const radioOpsuSi = document.getElementById('opsu_si' + prefijo);
    const radioOpsuNo = document.getElementById('opsu_no' + prefijo);
    const contenedorLapsoOpsu = document.getElementById('contenedorLapsoOpsu' + prefijo);
    const campoLapsoOpsu = document.getElementById('lapso_opsu' + prefijo);

    function toggleOpsuField() {
        if (radioOpsuSi && radioOpsuSi.checked) {
            if (contenedorLapsoOpsu) contenedorLapsoOpsu.style.display = 'block';
            if (campoLapsoOpsu) campoLapsoOpsu.setAttribute('required', 'required');
        } else {
            if (contenedorLapsoOpsu) contenedorLapsoOpsu.style.display = 'none';
            if (campoLapsoOpsu) {
                campoLapsoOpsu.removeAttribute('required');
                campoLapsoOpsu.value = '';
            }
        }
    }

    if (radioOpsuSi) radioOpsuSi.addEventListener('change', toggleOpsuField);
    if (radioOpsuNo) radioOpsuNo.addEventListener('change', toggleOpsuField);
    toggleOpsuField();

    <?php if ($modo_preinscripcion): ?>
    // =============================================
    // DETECCIÓN Y PRECARGA DE PREINSCRIPCIÓN PREVIA
    // (Modales informativos, sin recargar página)
    // =============================================
    const numeroCedulaInput = document.getElementById('numero_cedula' + prefijo);
    const tipoCedulaSelect = document.getElementById('tipo_cedula' + prefijo);
    const nombreInput = document.getElementById('nombre' + prefijo);
    const fechaNacInput = document.getElementById('fecha_nac' + prefijo);
    const avisoPreinscrito = document.getElementById('avisoPreinscrito' + prefijo);
    const avisoEstudianteInscrito = document.getElementById('avisoEstudianteInscrito' + prefijo);
    const btnVerDetallePreinscrito = document.getElementById('btnVerDetallePreinscrito' + prefijo);
    const btnConsultarCedula = document.getElementById('btnConsultarCedula' + prefijo);
    const btnSubmitForm = document.getElementById('btnSubmitForm' + prefijo) || (document.getElementById('<?php echo $formId; ?>') ? document.getElementById('<?php echo $formId; ?>').querySelector('button[type="submit"]') : null);
    const badgeCedula = document.getElementById('badgeCedulaBloqueada' + prefijo);
    const badgeNombre = document.getElementById('badgeNombreBloqueado' + prefijo);
    const badgeFechaNac = document.getElementById('badgeFechaNacBloqueada' + prefijo);

    let ultimaCedulaConsultada = '';
    let datosPreinscritoCargados = null;
    let consultaEnProgreso = false;

    // Bloqueo total para estudiante ya registrado (no puede cambiar cédula ni enviar preinscripción)
    function bloquearEstudianteInscrito() {
        if (numeroCedulaInput) {
            numeroCedulaInput.readOnly = true;
            numeroCedulaInput.style.backgroundColor = '#e9ecef';
            numeroCedulaInput.style.cursor = 'not-allowed';
            numeroCedulaInput.style.pointerEvents = 'none';
        }
        if (tipoCedulaSelect) {
            tipoCedulaSelect.style.pointerEvents = 'none';
            tipoCedulaSelect.style.backgroundColor = '#e9ecef';
        }
        if (badgeCedula) {
            badgeCedula.innerHTML = '<i class="fas fa-lock mr-1"></i> Alumno Ya Inscrito';
            badgeCedula.className = 'badge bg-danger ms-2';
            badgeCedula.style.display = 'inline-block';
        }
        if (avisoEstudianteInscrito) avisoEstudianteInscrito.style.display = 'block';
        if (avisoPreinscrito) avisoPreinscrito.style.display = 'none';
        if (btnSubmitForm) btnSubmitForm.disabled = true;
    }

    // Bloqueo estricto para aspirante preinscrito (cédula, nombre y fecha_nac inmodificables)
    function bloquearCamposPreinscrito(d) {
        if (numeroCedulaInput) {
            numeroCedulaInput.readOnly = true;
            numeroCedulaInput.style.backgroundColor = '#e9ecef';
            numeroCedulaInput.style.cursor = 'not-allowed';
            numeroCedulaInput.style.pointerEvents = 'none';
        }
        if (tipoCedulaSelect) {
            tipoCedulaSelect.style.pointerEvents = 'none';
            tipoCedulaSelect.style.backgroundColor = '#e9ecef';
            if (d && d.tipo_cedula) tipoCedulaSelect.value = d.tipo_cedula;
        }
        if (badgeCedula) {
            badgeCedula.innerHTML = '<i class="fas fa-lock mr-1"></i> Cédula Inmodificable';
            badgeCedula.className = 'badge bg-secondary ms-2';
            badgeCedula.style.display = 'inline-block';
        }

        if (nombreInput) {
            if (d && d.nombre) nombreInput.value = d.nombre;
            nombreInput.readOnly = true;
            nombreInput.style.backgroundColor = '#e9ecef';
            nombreInput.style.cursor = 'not-allowed';
            if (badgeNombre) badgeNombre.style.display = 'inline-block';
        }

        if (fechaNacInput) {
            if (d && d.fecha_nac) fechaNacInput.value = d.fecha_nac;
            fechaNacInput.readOnly = true;
            fechaNacInput.style.backgroundColor = '#e9ecef';
            fechaNacInput.style.cursor = 'not-allowed';
            fechaNacInput.style.pointerEvents = 'none';
            if (badgeFechaNac) badgeFechaNac.style.display = 'inline-block';
        }

        if (avisoEstudianteInscrito) avisoEstudianteInscrito.style.display = 'none';
        if (avisoPreinscrito) avisoPreinscrito.style.display = 'block';
        if (btnSubmitForm) btnSubmitForm.disabled = false;
    }

    // Modal: Estudiante ya formalmente registrado en el sistema (sin opción de cambiar cédula)
    function mostrarModalEstudianteInscrito(mensaje) {
        const texto = mensaje || 'Esta cédula ya se encuentra formalmente inscrita como estudiante en el sistema.';
        bloquearEstudianteInscrito();

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Estudiante Ya Registrado',
                html: '<div class="text-left" style="font-size: 14.5px;">' +
                      '<p class="mb-2">' + texto + '</p>' +
                      '<div class="alert alert-danger py-2 px-3 my-2" style="font-size: 13px; border-left: 4px solid #dc3545;">' +
                      '<strong><i class="fas fa-lock mr-1"></i> Restricción institucional:</strong><br>' +
                      'Esta cédula ya posee matrícula regular activa.' +
                      '</div>' +
                      '<p class="text-muted small mb-0">Por favor ingrese al sistema con sus credenciales de acceso institucional.</p>' +
                      '</div>',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-sign-in-alt mr-1"></i> Iniciar Sesión',
                cancelButtonText: '<i class="fas fa-times mr-1"></i> Cerrar',
                confirmButtonColor: '#003366',
                cancelButtonColor: '#6c757d',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'login.php';
                }
            });
        } else if (window.$ && typeof $('#modalEstudianteInscrito' + prefijo).modal === 'function') {
            const txtElem = document.getElementById('textoEstudianteInscrito' + prefijo);
            if (txtElem) txtElem.textContent = texto;
            $('#modalEstudianteInscrito' + prefijo).modal('show');
        } else {
            alert(texto);
        }
    }

    // Modal: Preinscripción previa encontrada
    function mostrarModalPreinscripcionPrevia(d) {
        const nombreAspirante = d && d.nombre ? d.nombre : '';
        const cedulaAspirante = d && d.idusuario ? d.idusuario : '';

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Preinscripción Previa Encontrada',
                html: '<div class="text-left" style="font-size: 14px;">' +
                      '<p class="mb-2">Detectamos que ya se había preinscrito anteriormente a nombre de: <strong>' + (nombreAspirante || 'este aspirante') + '</strong>' +
                      (cedulaAspirante ? ' (Cédula: <strong>' + cedulaAspirante + '</strong>)' : '') + '.</p>' +
                      '<div class="alert alert-warning py-2 px-3 my-2" style="font-size: 13px; border-left: 4px solid #ffc107;">' +
                      '<strong><i class="fas fa-lock mr-1"></i> Datos protegidos por seguridad institucional:</strong><br>' +
                      '• Su <strong>Cédula</strong>, <strong>Nombre</strong> y <strong>Fecha de Nacimiento</strong> no pueden ser modificados.' +
                      '</div>' +
                      '<p class="mb-0 text-muted" style="font-size: 13px;">' +
                      'Todos los demás datos (carrera, turno, asignación OPSU, teléfonos, dirección, títulos, etc.) han sido cargados en el formulario y puede actualizarlos libremente antes de enviar su solicitud.' +
                      '</p>' +
                      '</div>',
                confirmButtonText: '<i class="fas fa-check mr-1"></i> Continuar y Actualizar Datos',
                confirmButtonColor: '#003366',
                allowOutsideClick: true
            });
        } else if (window.$ && typeof $('#modalPreinscripcionPrevia' + prefijo).modal === 'function') {
            const nomElem = document.getElementById('nombreModalPreinscrito' + prefijo);
            const cedElem = document.getElementById('cedulaModalPreinscrito' + prefijo);
            if (nomElem) nomElem.textContent = nombreAspirante;
            if (cedElem) cedElem.textContent = cedulaAspirante;
            $('#modalPreinscripcionPrevia' + prefijo).modal('show');
        } else {
            alert('Se cargaron sus datos de una preinscripción previa. Cédula, nombre y fecha de nacimiento quedan protegidos.');
        }
    }

    function consultarPreinscripcionPrevia(forzarMostrarModal = false) {
        if (!numeroCedulaInput) return;
        const num = numeroCedulaInput.value.replace(/\D/g, '');
        const tipo = tipoCedulaSelect ? tipoCedulaSelect.value : 'V';

        if (num.length < 6) return;
        const cedulaCompleta = tipo + num;

        // Si ya tenemos los datos en memoria para esta misma cédula y el usuario vuelve a consultar:
        if (cedulaCompleta === ultimaCedulaConsultada && datosPreinscritoCargados) {
            if (forzarMostrarModal) {
                mostrarModalPreinscripcionPrevia(datosPreinscritoCargados);
            }
            return;
        }

        if (consultaEnProgreso) return;

        consultaEnProgreso = true;

        const apiConsultarUrl = 'admin/api/consultar_preinscripcion.php';
        fetch(apiConsultarUrl + '?tipo_cedula=' + encodeURIComponent(tipo) + '&numero_cedula=' + encodeURIComponent(num))
            .then(res => res.json())
            .then(data => {
                consultaEnProgreso = false;
                if (!data) return;

                if (data.inscrito) {
                    ultimaCedulaConsultada = cedulaCompleta;
                    datosPreinscritoCargados = null;
                    mostrarModalEstudianteInscrito(data.message);
                    return;
                }

                if (data.success && data.encontrado && data.datos) {
                    const d = data.datos;
                    ultimaCedulaConsultada = cedulaCompleta;
                    datosPreinscritoCargados = d;

                    // 1. BLOQUEAR ESTRICTAMENTE CÉDULA, NOMBRE Y FECHA DE NACIMIENTO
                    bloquearCamposPreinscrito(d);

                    // 2. PRECARGAR LOS DEMÁS DATOS (EDITABLES)
                    if (d.genero) {
                        const rGen = document.querySelector('input[name="genero"][value="' + d.genero + '"]');
                        if (rGen) { rGen.checked = true; rGen.dispatchEvent(new Event('change')); }
                    }
                    if (d.embarazada !== undefined && d.embarazada !== null) {
                        const rEmb = document.getElementById('embarazada_' + (d.embarazada == '1' ? 'si' : 'no') + prefijo);
                        if (rEmb) rEmb.checked = true;
                    }
                    if (d.edo_civil) {
                        const selEdo = document.getElementById('edo_civil' + prefijo);
                        if (selEdo) selEdo.value = d.edo_civil;
                    }
                    if (d.email) {
                        const inpEmail = document.getElementById('email' + prefijo);
                        if (inpEmail) inpEmail.value = d.email;
                    }
                    if (d.tlf) {
                        const inpTlf = document.getElementById('tlf' + prefijo);
                        if (inpTlf) inpTlf.value = d.tlf;
                    }
                    if (d.cel) {
                        const inpCel = document.getElementById('cel' + prefijo);
                        if (inpCel) inpCel.value = d.cel;
                    }
                    if (d.num_telf_opc) {
                        const inpOpc = document.getElementById('num_telf_opc' + prefijo);
                        if (inpOpc) inpOpc.value = d.num_telf_opc;
                    }
                    if (d.direccion) {
                        const inpDir = document.getElementById('direccion' + prefijo);
                        if (inpDir) inpDir.value = d.direccion;
                    }
                    if (d.comuna) {
                        const inpCom = document.getElementById('comuna' + prefijo);
                        if (inpCom) inpCom.value = d.comuna;
                    }
                    if (d.casaapto) {
                        const inpCasa = document.getElementById('casaapto' + prefijo);
                        if (inpCasa) inpCasa.value = d.casaapto;
                    }
                    if (d.punto_referencia) {
                        const inpPunto = document.getElementById('punto_referencia' + prefijo);
                        if (inpPunto) inpPunto.value = d.punto_referencia;
                    }
                    if (d.etnia) {
                        const selEtnia = document.getElementById('etnia' + prefijo);
                        if (selEtnia) selEtnia.value = d.etnia;
                    }
                    if (d.grupo_familiar !== undefined && d.grupo_familiar !== null) {
                        const inpGrup = document.getElementById('grupo_familiar' + prefijo);
                        if (inpGrup) inpGrup.value = d.grupo_familiar;
                    }
                    if (d.acargo_usted !== undefined && d.acargo_usted !== null) {
                        const inpAcargo = document.getElementById('acargo_usted' + prefijo);
                        if (inpAcargo) inpAcargo.value = d.acargo_usted;
                    }
                    if (d.fuente_ingresos) {
                        const selFuen = document.getElementById('fuente_ingresos' + prefijo);
                        if (selFuen) selFuen.value = d.fuente_ingresos;
                    }
                    if (d.tipo_vivienda) {
                        const selViv = document.getElementById('tipo_vivienda' + prefijo);
                        if (selViv) selViv.value = d.tipo_vivienda;
                    }
                    if (d.tenencia_vivienda) {
                        const selTen = document.getElementById('tenencia_vivienda' + prefijo);
                        if (selTen) selTen.value = d.tenencia_vivienda;
                    }
                    if (d.sede) {
                        const selSede = document.getElementById('sede' + prefijo);
                        if (selSede) selSede.value = d.sede;
                    }

                    // Discapacidad y Enfermedad
                    if (d.discapacidad && d.discapacidad !== 'Ninguna' && d.discapacidad.trim() !== '') {
                        const rDiscSi = document.getElementById('discapacidad_si' + prefijo);
                        if (rDiscSi) { rDiscSi.checked = true; rDiscSi.dispatchEvent(new Event('change')); }
                        const inpDisc = document.getElementById('discapacidad' + prefijo);
                        if (inpDisc) inpDisc.value = d.discapacidad;
                    }
                    if (d.enfermedad && d.enfermedad !== 'Ninguna' && d.enfermedad.trim() !== '') {
                        const rEnfSi = document.getElementById('enfermedad_si' + prefijo);
                        if (rEnfSi) { rEnfSi.checked = true; rEnfSi.dispatchEvent(new Event('change')); }
                        const inpEnf = document.getElementById('enfermedad' + prefijo);
                        if (inpEnf) inpEnf.value = d.enfermedad;
                    }

                    // Estado, Municipio, Parroquia
                    if (d.estado) {
                        const estadoSelect = document.getElementById('estado' + prefijo);
                        if (estadoSelect) {
                            estadoSelect.value = d.estado;
                            if (typeof cargarMunicipios === 'function') {
                                cargarMunicipios(d.estado, d.municipio || '', d.parroquia || '');
                            }
                        }
                    }

                    // Carrera y Turno
                    if (d.carrera) {
                        const selCar = document.getElementById('carrera' + prefijo);
                        if (selCar) {
                            selCar.value = d.carrera;
                            if (typeof actualizarTurnos === 'function') {
                                actualizarTurnos();
                            }
                            if (d.turno) {
                                setTimeout(() => {
                                    const selTur = document.getElementById('turno' + prefijo);
                                    if (selTur) selTur.value = d.turno;
                                }, 150);
                            }
                        }
                    }

                    // Asignado OPSU y Lapso
                    if (d.asignado_opsu === 'Sí') {
                        if (radioOpsuSi) {
                            radioOpsuSi.checked = true;
                            toggleOpsuField();
                        }
                        if (campoLapsoOpsu && d.lapso_opsu) {
                            campoLapsoOpsu.value = d.lapso_opsu;
                        }
                    } else {
                        if (radioOpsuNo) {
                            radioOpsuNo.checked = true;
                            toggleOpsuField();
                        }
                    }

                    // Potencialidades
                    if (d.potencialidades) {
                        const inpPot = document.getElementById('potencialidades' + prefijo);
                        if (inpPot) inpPot.value = d.potencialidades;
                    }

                    // Mostrar modal de aviso informativo
                    mostrarModalPreinscripcionPrevia(d);
                } else {
                    // Cédula no registrada previamente
                    datosPreinscritoCargados = null;
                    if (forzarMostrarModal && num.length >= 6) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Cédula Disponible',
                                text: 'No posee registros previos. Puede continuar con su preinscripción completando el formulario.',
                                confirmButtonColor: '#003366',
                                timer: 2500,
                                showConfirmButton: false
                            });
                        }
                    }
                }
            })
            .catch(err => {
                consultaEnProgreso = false;
                console.error('Error al consultar preinscripción previa:', err);
            });
    }

    if (numeroCedulaInput) {
        // Al salir del campo (blur), consultar y abrir modal si corresponde
        numeroCedulaInput.addEventListener('blur', function() {
            consultarPreinscripcionPrevia(true);
        });

        // Al presionar Enter en el campo de cédula
        numeroCedulaInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                consultarPreinscripcionPrevia(true);
            }
        });

        // Al teclear: si se altera el número, se restablece el estado para que vuelva a detonarse sin recargar
        numeroCedulaInput.addEventListener('input', function() {
            const num = this.value.replace(/\D/g, '');
            const currentFull = (tipoCedulaSelect ? tipoCedulaSelect.value : 'V') + num;
            if (currentFull !== ultimaCedulaConsultada) {
                ultimaCedulaConsultada = '';
                datosPreinscritoCargados = null;
                clearTimeout(window.cedulaCheckTimeout);
                if (num.length >= 7) {
                    window.cedulaCheckTimeout = setTimeout(function() {
                        consultarPreinscripcionPrevia(true);
                    }, 800);
                }
            }
        });
    }

    if (tipoCedulaSelect) {
        tipoCedulaSelect.addEventListener('change', function() {
            ultimaCedulaConsultada = '';
            datosPreinscritoCargados = null;
            if (numeroCedulaInput && numeroCedulaInput.value.replace(/\D/g, '').length >= 6) {
                consultarPreinscripcionPrevia(true);
            }
        });
    }

    // Botón "Verificar" junto al campo de cédula
    if (btnConsultarCedula) {
        btnConsultarCedula.addEventListener('click', function() {
            consultarPreinscripcionPrevia(true);
        });
    }

    // Botón "Ver Aviso" en el banner informativo
    if (btnVerDetallePreinscrito) {
        btnVerDetallePreinscrito.addEventListener('click', function() {
            if (datosPreinscritoCargados) {
                mostrarModalPreinscripcionPrevia(datosPreinscritoCargados);
            } else {
                consultarPreinscripcionPrevia(true);
            }
        });
    }

    // Si ya viene una cédula al cargar la página (por POST previo)
    if (numeroCedulaInput && numeroCedulaInput.value.replace(/\D/g, '').length >= 6) {
        consultarPreinscripcionPrevia(false);
    }
    <?php endif; ?>
});

// =============================================
// SCRIPT PARA CUPOS DINÁMICOS EN CARRERA Y TURNO
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const prefijo = '<?php echo $prefijo; ?>';
    const carreraSelect = document.getElementById('carrera' + prefijo);
    const turnoSelect = document.getElementById('turno' + prefijo);
    const cuposInfoDiv = document.getElementById('cuposInfo' + prefijo);
    
    if (!carreraSelect) return;
    
    function actualizarCuposTiempoReal(carreraId, turno) {
        <?php if ($modo_preinscripcion): ?>
        return;
        <?php endif; ?>
        if (!carreraId || !turno) return;
        const apiUrl = '<?php echo ($modo_preinscripcion ? "admin/" : ""); ?>api/obtener_cupos_disponibles.php';
        fetch(apiUrl + '?carrera_id=' + carreraId + '&turno=' + turno + '&periodo_id=<?php echo (int)$periodoFormulario; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (turnoSelect && turnoSelect.options.length > 0) {
                        for (let i = 0; i < turnoSelect.options.length; i++) {
                            const opt = turnoSelect.options[i];
                            if (opt.value === turno) {
                                const disponible = data.disponibles;
                                const total = data.total;
                                const ocupados = data.ocupados;
                                
                                if (disponible > 0) {
                                    opt.textContent = turno + ' (' + disponible + ' cupos disponibles de ' + total + ')';
                                    opt.style.color = '';
                                } else {
                                    opt.textContent = turno + ' (SIN CUPOS - ' + ocupados + '/' + total + ' ocupados)';
                                    opt.style.color = 'red';
                                }
                                break;
                            }
                        }
                    }
                    
                    if (cuposInfoDiv && data.disponibles !== undefined) {
                        const carreraNombre = carreraSelect.options[carreraSelect.selectedIndex]?.getAttribute('data-nombre') || '';
                        let infoHtml = '<div class="alert alert-sm p-2 mt-1" style="font-size:11px;">';
                        infoHtml += '<i class="fas fa-chart-line"></i> <strong>' + carreraNombre + ' - Turno ' + turno + ':</strong><br>';
                        
                        if (data.disponibles > 0) {
                            infoHtml += '<span class="text-success">✓ ' + data.disponibles + ' cupos disponibles</span><br>';
                            infoHtml += '<small>' + data.ocupados + ' inscritos / ' + data.total + ' total</small>';
                        } else {
                            infoHtml += '<span class="text-danger">✗ SIN CUPOS DISPONIBLES</span><br>';
                            infoHtml += '<small>' + data.ocupados + ' inscritos / ' + data.total + ' total</small>';
                        }
                        infoHtml += '</div>';
                        cuposInfoDiv.innerHTML = infoHtml;
                    }
                }
            })
            .catch(error => console.error('Error al obtener cupos:', error));
    }
    
    function actualizarTurnos() {
        const selectedOption = carreraSelect.options[carreraSelect.selectedIndex];
        const carreraId = carreraSelect.value;
        
        <?php if ($modo_preinscripcion): ?>
        if (!carreraId) {
            if (turnoSelect) {
                turnoSelect.disabled = true;
                turnoSelect.innerHTML = '<option value="">-- Primero seleccione una carrera --</option>';
            }
            if (cuposInfoDiv) cuposInfoDiv.innerHTML = '';
            return;
        }
        if (turnoSelect) {
            turnoSelect.disabled = false;
            const currentValue = turnoSelect.value;
            turnoSelect.innerHTML = '<option value="">-- Seleccione un turno --</option>' +
                                    '<option value="Diurno">Diurno</option>' +
                                    '<option value="Nocturno">Nocturno</option>';
            if (currentValue) turnoSelect.value = currentValue;
        }
        if (cuposInfoDiv) cuposInfoDiv.innerHTML = '';
        return;
        <?php endif; ?>

        if (!carreraId || !selectedOption || !selectedOption.dataset.cupos) {
            if (turnoSelect) {
                turnoSelect.disabled = true;
                turnoSelect.innerHTML = '<option value="">-- Primero seleccione una carrera --</option>';
            }
            cuposInfoDiv.innerHTML = '';
            return;
        }
        
        let cuposData = {};
        try {
            cuposData = JSON.parse(selectedOption.dataset.cupos);
        } catch(e) {
            cuposData = {};
        }
        
        if (turnoSelect) {
            turnoSelect.disabled = false;
            const currentValue = turnoSelect.value;
            turnoSelect.innerHTML = '<option value="">-- Seleccione un turno --</option>';
            
            if (cuposData.Diurno) {
                const diurno = cuposData.Diurno;
                const option = document.createElement('option');
                option.value = 'Diurno';
                if (diurno.disponibles > 0) {
                    option.textContent = 'Diurno (' + diurno.disponibles + ' cupos disponibles de ' + diurno.total + ')';
                    option.style.color = '';
                } else {
                    option.textContent = 'Diurno (SIN CUPOS - ' + diurno.ocupados + '/' + diurno.total + ' ocupados)';
                    option.style.color = 'red';
                }
                turnoSelect.appendChild(option);
            }
            
            if (cuposData.Nocturno) {
                const nocturno = cuposData.Nocturno;
                const option = document.createElement('option');
                option.value = 'Nocturno';
                if (nocturno.disponibles > 0) {
                    option.textContent = 'Nocturno (' + nocturno.disponibles + ' cupos disponibles de ' + nocturno.total + ')';
                    option.style.color = '';
                } else {
                    option.textContent = 'Nocturno (SIN CUPOS - ' + nocturno.ocupados + '/' + nocturno.total + ' ocupados)';
                    option.style.color = 'red';
                }
                turnoSelect.appendChild(option);
            }
            
            if (turnoSelect.options.length === 1) {
                turnoSelect.innerHTML = '<option value="">-- No hay turnos disponibles --</option>';
                turnoSelect.disabled = true;
            } else if (currentValue && Array.from(turnoSelect.options).some(opt => opt.value === currentValue)) {
                turnoSelect.value = currentValue;
                actualizarCuposTiempoReal(carreraId, currentValue);
                if (window.cuposInterval) clearInterval(window.cuposInterval);
                window.cuposInterval = setInterval(() => {
                    if (carreraSelect.value && turnoSelect.value && turnoSelect.value !== '') {
                        actualizarCuposTiempoReal(carreraSelect.value, turnoSelect.value);
                    }
                }, 10000);
            }
        }
        
        let infoHtml = '<div class="alert alert-sm alert-info p-2 mt-1" style="font-size:11px;">';
        infoHtml += '<i class="fas fa-chart-line"></i> <strong>Resumen de cupos:</strong><br>';
        
        if (cuposData.Diurno) {
            const d = cuposData.Diurno;
            infoHtml += '▪️ Diurno: ';
            if (d.disponibles > 0) {
                infoHtml += '<span class="text-success">' + d.disponibles + ' disponibles</span>';
            } else {
                infoHtml += '<span class="text-danger">Sin cupos</span>';
            }
            infoHtml += ' (' + d.ocupados + '/' + d.total + ')<br>';
        }
        
        if (cuposData.Nocturno) {
            const n = cuposData.Nocturno;
            infoHtml += '▪️ Nocturno: ';
            if (n.disponibles > 0) {
                infoHtml += '<span class="text-success">' + n.disponibles + ' disponibles</span>';
            } else {
                infoHtml += '<span class="text-danger">Sin cupos</span>';
            }
            infoHtml += ' (' + n.ocupados + '/' + n.total + ')';
        }
        
        infoHtml += '</div>';
        cuposInfoDiv.innerHTML = infoHtml;
    }
    
    carreraSelect.addEventListener('change', function() {
        if (window.cuposInterval) clearInterval(window.cuposInterval);
        actualizarTurnos();
    });
    
    if (turnoSelect) {
        turnoSelect.addEventListener('change', function() {
            const carreraId = carreraSelect.value;
            const turno = turnoSelect.value;
            if (carreraId && turno) {
                actualizarCuposTiempoReal(carreraId, turno);
            }
        });
    }
    
    if (carreraSelect.value) {
        actualizarTurnos();
    }
});

// Validación del formulario
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('<?php echo $formId; ?>');
    
    if (!form) return;
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        
        if (validarFormulario()) {
            form.submit();
        }
    });
    
    function validarFormulario() {
        let isValid = true;
        let mensajesError = [];
        
        const prefijo = '<?php echo $prefijo; ?>';
        
        const numeroCedula = document.getElementById('numero_cedula' + prefijo).value;
        if (!/^\d{6,9}$/.test(numeroCedula)) {
            mensajesError.push('La cédula debe contener entre 6 y 9 dígitos numéricos');
            isValid = false;
        }


        // Foto de perfil opcional (no se requiere validación obligatoria)
        
        const nombre = document.getElementById('nombre' + prefijo).value;
        if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s']+$/.test(nombre.trim())) {
            mensajesError.push('El nombre solo puede contener letras, espacios y apóstrofes');
            isValid = false;
        }
        
        const estado = document.getElementById('estado' + prefijo).value;
        if (!estado) {
            mensajesError.push('Debe seleccionar un estado');
            isValid = false;
        }
        
        const municipio = document.getElementById('municipio' + prefijo).value;
        if (!municipio) {
            mensajesError.push('Debe seleccionar un municipio');
            isValid = false;
        }

        const parroquia = document.getElementById('parroquia' + prefijo).value;
        if (!parroquia) {
            mensajesError.push('Debe seleccionar una parroquia');
            isValid = false;
        }
        
        const telefono = document.getElementById('tlf' + prefijo).value;
        if (!/^[0-9]{10,11}$/.test(telefono.replace(/\D/g, ''))) {
            mensajesError.push('El teléfono debe contener 10 u 11 dígitos');
            isValid = false;
        }
        
        const email = document.getElementById('email' + prefijo).value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            mensajesError.push('Ingrese un correo electrónico válido');
            isValid = false;
        }
        
        const fechaNac = document.getElementById('fecha_nac' + prefijo).value;
        if (fechaNac) {
            const fechaNacDate = new Date(fechaNac);
            const hoy = new Date();
            if (fechaNacDate > hoy) {
                mensajesError.push('La fecha de nacimiento no puede ser futura');
                isValid = false;
            }
            
            const edadMinima = new Date();
            edadMinima.setFullYear(edadMinima.getFullYear() - 15);
            if (fechaNacDate > edadMinima) {
                mensajesError.push('El estudiante debe tener al menos 15 años');
                isValid = false;
            }
        }
        
        const fechaIngresoElement = document.getElementById('fecha_ingreso' + prefijo);
        const fechaIngreso = fechaIngresoElement ? fechaIngresoElement.value : '';
        if (fechaNac && fechaIngreso) {
            const fechaNacDate = new Date(fechaNac);
            const fechaIngresoDate = new Date(fechaIngreso);
            if (fechaIngresoDate < fechaNacDate) {
                mensajesError.push('La fecha de ingreso no puede ser anterior a la fecha de nacimiento');
                isValid = false;
            }
        }
        
        const radioEtniaSi = document.getElementById('etnia_si' + prefijo);
        const campoEtnia = document.getElementById('etnia' + prefijo);
        
        if (radioEtniaSi && radioEtniaSi.checked && campoEtnia) {
            if (!campoEtnia.value.trim()) {
                mensajesError.push('Debe especificar la etnia si seleccionó "Sí"');
                isValid = false;
            }
        }
        
        const radioDiscSi = document.getElementById('discapacidad_si' + prefijo);
        const campoDisc = document.getElementById('discapacidad' + prefijo);
        
        if (radioDiscSi && radioDiscSi.checked && campoDisc) {
            if (!campoDisc.value.trim()) {
                mensajesError.push('Debe especificar el tipo de discapacidad si seleccionó "Sí"');
                isValid = false;
            }
        }
        
        const radioEnfSi = document.getElementById('enfermedad_si' + prefijo);
        const campoEnf = document.getElementById('enfermedad' + prefijo);
        
        if (radioEnfSi && radioEnfSi.checked && campoEnf) {
            if (!campoEnf.value.trim()) {
                mensajesError.push('Debe especificar la(s) enfermedad(es) si seleccionó "Sí"');
                isValid = false;
            }
        }

        const radioGeneroFemenino = document.getElementById('genero_f' + prefijo);
        const embarazoSi = document.getElementById('embarazada_si' + prefijo);
        const embarazoNo = document.getElementById('embarazada_no' + prefijo);

        if (radioGeneroFemenino && radioGeneroFemenino.checked) {
            if (embarazoSi && embarazoNo && !embarazoSi.checked && !embarazoNo.checked) {
                mensajesError.push('Debe indicar si la estudiante está embarazada o no');
                isValid = false;
            }
        }

        const radioOpsuSi = document.getElementById('opsu_si' + prefijo);
        const campoLapsoOpsu = document.getElementById('lapso_opsu' + prefijo);
        if (radioOpsuSi && radioOpsuSi.checked && campoLapsoOpsu) {
            if (!campoLapsoOpsu.value.trim()) {
                mensajesError.push('Debe seleccionar el lapso académico de asignación OPSU');
                isValid = false;
            }
        }
        
        if (mensajesError.length > 0) {
            mostrarErrores(mensajesError);
        }
        
        return isValid;
    }
    
    function mostrarErrores(errores) {
        let errorContainer = document.getElementById('errorContainer');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.id = 'errorContainer';
            errorContainer.className = 'alert alert-danger';
            form.prepend(errorContainer);
        }
        
        errorContainer.innerHTML = '';
        
        const titulo = document.createElement('strong');
        titulo.textContent = 'Por favor corrija los siguientes errores:';
        errorContainer.appendChild(titulo);
        
        const lista = document.createElement('ul');
        lista.className = 'mb-0 mt-2';
        
        errores.forEach(error => {
            const item = document.createElement('li');
            item.textContent = error;
            lista.appendChild(item);
        });
        
        errorContainer.appendChild(lista);
        errorContainer.scrollIntoView({ behavior: 'smooth' });
    }
    
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            const errorContainer = document.getElementById('errorContainer');
            if (errorContainer) {
                errorContainer.remove();
            }
        });
    });
});

window.previewImage = function(input, previewDivId, previewImgId) {
    const previewDiv = document.getElementById(previewDivId);
    let previewImg = document.getElementById(previewImgId);
    if (!previewImg && previewDivId) {
        previewImg = document.getElementById(previewDivId.replace('preview', 'previewImage'));
    }
    if (input && input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            if (file.type.startsWith('image/')) {
                if (previewImg) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'inline-block';
                }
                if (previewDiv) {
                    previewDiv.style.display = 'block';
                }
            } else {
                if (previewDiv) {
                    previewDiv.innerHTML = '<div class="alert alert-info py-1 px-2 small mb-0"><i class="fas fa-file-pdf text-danger me-1"></i> Documento ' + file.name + ' seleccionado</div>';
                    previewDiv.style.display = 'block';
                }
            }
        };
        reader.readAsDataURL(file);
    } else {
        if (previewDiv) previewDiv.style.display = 'none';
    }
};
</script>