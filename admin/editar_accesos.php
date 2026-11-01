<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulopag = "Gestión de Niveles de Acceso";
include('../funciones/functions.php');

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CARGAR PERMISOS - hacerlo ANTES de verificar
cargarPermisosUsuario();

// Verificar permiso para editar acceso
verificarPermiso('editar_acceso');

// LLAMAR A LA FUNCIÓN DE VISITA
visita();

// Verificar si es admin (esta función debe existir en functions.php)
if (!isAdmin()) {
    $_SESSION['error'] = "No tien permisos de administrador para acceder a esta página.";
    header('location: ../login.php');
    exit();
}

// Procesar formulario de permisos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar'])) {
    if (isset($_POST['permisos']) && is_array($_POST['permisos'])) {
        $actualizaciones_exitosas = 0;
        $total_usuarios = count($_POST['permisos']);
        
        foreach ($_POST['permisos'] as $user_id => $permisos) {
            if (actualizarPermisosUsuario($user_id, $permisos)) {
                $actualizaciones_exitosas++;
            }
        }
        
        if ($actualizaciones_exitosas > 0) {
            cargarPermisosUsuario();
            $_SESSION['msg'] = "Permisos actualizados correctamente para los usuarios";
        } else {
            $_SESSION['msg'] = "Error al actualizar los permisos";
        }
        
        header('Location: editar_accesos.php');
        exit();
    }
}

// El resto del código HTML permanece igual...
include("includes/head.php");
?>

<div class="container-fluid py-3">
    <h2 class="mb-4"><i class="fas fa-user-lock"></i> Gestión de Niveles de Acceso</h2>
    
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
    <?php endif; ?>
    
    <!-- Controles de Filtrado y Búsqueda -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="filtro-accesos" class="form-label">Filtrar:</label>
                    <select id="filtro-accesos" class="custom-select d-block w-100">
                        <option value="personal">Personal</option>
                        <option value="estudiantes">Solo estudiantes</option>
                        <option value="sin-accesos">Sin accesos</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="buscador" class="form-label">Buscar:</label>
                    <div class="input-group">
                        <input type="text" id="buscador" class="form-control" placeholder="Buscar usuario...">
                        <button id="limpiar-busqueda" class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulario de Permisos -->
    <form method="POST" action="">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>Usuario</th>
                        <th>Director de Carrera</th>
                        <th>Estudiante</th>
                        <th>Docente</th>
                        <th>Admin</th>
                        <th>Super User</th>
                        <th>Editar Usuarios</th>
                        <th>Editar Notas</th>
                        <th>Editar Accesos</th>
                        <th>Editar Valores</th>
                        <th>Editar Estudiantes</th>
                        <th>Agregar Estudiantes</th>
                        <th>Agregar Docentes</th>
                        <th>Editar Docentes</th>
                        <th>Agregar Carrera</th>
                        <th>Agregar Materia</th>
                        <th>Editar Materia</th>
                        <!-- Campos existentes -->
                        <th>Pagos</th>
                        <th>Auditoría</th>
                        <th>Secciones</th>
                        <th>Relación Materia-Carrera</th>
                        <th>Periodos Académicos</th>
                        <th>Asignar Secciones</th>
                        <th>Asignar Cursos</th>
                        <th>Horarios</th>
                        <th>Gestión Director Carrera</th>
                        <th>Notas Cargadas</th>
                        <th>Consultar Notas</th>
                        <th>Consultar Notas Pasadas</th>
                        <th>Tipos de Pago</th>
                        <th>Tipos de Horario</th>
                        <th>Horario Personal</th>
                        <th>Respaldo BD</th>
                        <!-- NUEVOS CAMPOS AGREGADOS -->
                        <th>Gestión Carrera</th>
                        <th>Gestión Periodo Académico</th>
                        <th>Gestión Asignar Cursos</th>
                        <th>Gestión Horario</th>
                        <th>Títulos RE Materia</th>
                        <!-- NUEVOS CAMPOS GRADO -->
                        <th>Grado</th>
                        <th>Gestión Grado</th>
                        <!-- NUEVO CAMPO VISITA -->
                        <th>Visita</th>
                        <!-- NUEVOS ACCESOS DEL PANEL ADMINISTRATIVO -->
                        <th>Constancias</th>
                        <th>Preinscripciones</th>
                        <th>Inscripción Materias</th>
                        <th>Aprobar Secciones</th>
                        <th>Aulas</th>
                        <th>Actas Calificación</th>
                        <th>Secretaría</th>
                        <th>Ver Estudiantes</th>
                        <th>Ver Docentes</th>
                        <th>Mensajería</th>
                    </tr>
                </thead>
                <tbody id="tabla-usuarios">
                    <?php
                    $result = obtenerUsuariosConPermisos();
                    
                    if ($result && $result->num_rows > 0):
                        while ($user = $result->fetch_assoc()):
                            // Determinar tipo de usuario
                            $esUsuario = $user['usuario'];
                            $esEstudiante = $user['estudiante'];
                            $tieneAccesos = $user['docente'] || $user['admin'] || $user['super_user'] || 
                                           $user['editar_user'] || $user['editar_nota'] || $user['editar_acceso'] || 
                                           $user['editar_valores'] || $user['editar_estudiante'] || $user['agregar_estudiante'] || 
                                           $user['agregar_docente'] || $user['editar_docente'] || $user['agregar_carrera'] || 
                                           $user['agregar_materia'] || $user['editar_materia'] || $user['pagos'] || 
                                           $user['auditoria'] || $user['secciones'] || $user['rela_materia_carrera'] || 
                                           $user['periodos_academicos'] || $user['asig_secciones'] || $user['asig_cursos'] || 
                                           $user['horarios'] || $user['gestion_director_carrera'] || $user['notas_cargadas'] || 
                                           $user['consultar_notas'] || $user['consultar_notas_pasadas'] || $user['tipos_pago'] || 
                                           $user['tipos_horario'] || $user['horario_personal'] || $user['respaldo_bd'] ||
                                           (isset($user['gestionar_carrera']) && $user['gestionar_carrera']) || 
                                           (isset($user['gestion_periodo_academico']) && $user['gestion_periodo_academico']) || 
                                           (isset($user['gestion_asig_cursos']) && $user['gestion_asig_cursos']) || 
                                           (isset($user['gestion_horario']) && $user['gestion_horario']) || 
                                           (isset($user['titulos_re_materia']) && $user['titulos_re_materia']) ||
                                           (isset($user['grado']) && $user['grado']) || 
                                           (isset($user['gestion_grado']) && $user['gestion_grado']) ||
                                           (isset($user['visita']) && $user['visita']) ||
                                           (isset($user['constancias']) && $user['constancias']) ||
                                           (isset($user['preinscripciones']) && $user['preinscripciones']) ||
                                           (isset($user['inscripcion_materias']) && $user['inscripcion_materias']) ||
                                           (isset($user['aprobar_secciones']) && $user['aprobar_secciones']) ||
                                           (isset($user['aulas']) && $user['aulas']) ||
                                           (isset($user['actas_calificacion']) && $user['actas_calificacion']) ||
                                           (isset($user['secretaria']) && $user['secretaria']) ||
                                           (isset($user['ver_estudiantes']) && $user['ver_estudiantes']) ||
                                           (isset($user['ver_docentes']) && $user['ver_docentes']) ||
                                           (isset($user['mensajeria']) && $user['mensajeria']);
                            
                            $clases = 'fila-usuario';
                            $clases .= $esUsuario ? ' usuario' : '';
                            $clases .= $esEstudiante ? ' estudiante' : '';
                            $clases .= $tieneAccesos ? ' personal' : '';
                            $clases .= (!$esUsuario && !$esEstudiante && !$tieneAccesos) ? ' sin-accesos' : '';
                    ?>
                    <tr class="<?= $clases ?>" data-nombre="<?= htmlspecialchars(strtolower($user['username'])) ?>">
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <!-- Campos originales -->
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][usuario]" <?= $user['usuario'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][estudiante]" <?= $user['estudiante'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][docente]" <?= $user['docente'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][admin]" <?= $user['admin'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][super_user]" <?= $user['super_user'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][editar_user]" <?= $user['editar_user'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][editar_nota]" <?= $user['editar_nota'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][editar_acceso]" <?= $user['editar_acceso'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][editar_valores]" <?= $user['editar_valores'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][editar_estudiante]" <?= $user['editar_estudiante'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][agregar_estudiante]" <?= $user['agregar_estudiante'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][agregar_docente]" <?= $user['agregar_docente'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][editar_docente]" <?= $user['editar_docente'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][agregar_carrera]" <?= $user['agregar_carrera'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][agregar_materia]" <?= $user['agregar_materia'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][editar_materia]" <?= $user['editar_materia'] ? 'checked' : '' ?>>
                        </td>
                        
                        <!-- Campos existentes -->
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][pagos]" <?= isset($user['pagos']) && $user['pagos'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][auditoria]" <?= isset($user['auditoria']) && $user['auditoria'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][secciones]" <?= isset($user['secciones']) && $user['secciones'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][rela_materia_carrera]" <?= isset($user['rela_materia_carrera']) && $user['rela_materia_carrera'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][periodos_academicos]" <?= isset($user['periodos_academicos']) && $user['periodos_academicos'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][asig_secciones]" <?= isset($user['asig_secciones']) && $user['asig_secciones'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][asig_cursos]" <?= isset($user['asig_cursos']) && $user['asig_cursos'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][horarios]" <?= isset($user['horarios']) && $user['horarios'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][gestion_director_carrera]" <?= isset($user['gestion_director_carrera']) && $user['gestion_director_carrera'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][notas_cargadas]" <?= isset($user['notas_cargadas']) && $user['notas_cargadas'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][consultar_notas]" <?= isset($user['consultar_notas']) && $user['consultar_notas'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][consultar_notas_pasadas]" <?= isset($user['consultar_notas_pasadas']) && $user['consultar_notas_pasadas'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][tipos_pago]" <?= isset($user['tipos_pago']) && $user['tipos_pago'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][tipos_horario]" <?= isset($user['tipos_horario']) && $user['tipos_horario'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][horario_personal]" <?= isset($user['horario_personal']) && $user['horario_personal'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][respaldo_bd]" <?= isset($user['respaldo_bd']) && $user['respaldo_bd'] ? 'checked' : '' ?>>
                        </td>
                        
                        <!-- NUEVOS CAMPOS AGREGADOS -->
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][gestionar_carrera]" <?= isset($user['gestionar_carrera']) && $user['gestionar_carrera'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][gestion_periodo_academico]" <?= isset($user['gestion_periodo_academico']) && $user['gestion_periodo_academico'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][gestion_asig_cursos]" <?= isset($user['gestion_asig_cursos']) && $user['gestion_asig_cursos'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][gestion_horario]" <?= isset($user['gestion_horario']) && $user['gestion_horario'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][titulos_re_materia]" <?= isset($user['titulos_re_materia']) && $user['titulos_re_materia'] ? 'checked' : '' ?>>
                        </td>
                        
                        <!-- NUEVOS CAMPOS GRADO -->
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][grado]" <?= isset($user['grado']) && $user['grado'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][gestion_grado]" <?= isset($user['gestion_grado']) && $user['gestion_grado'] ? 'checked' : '' ?>>
                        </td>
                        
                        <!-- NUEVO CAMPO VISITA -->
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][visita]" <?= isset($user['visita']) && $user['visita'] ? 'checked' : '' ?>>
                        </td>
                        
                        <!-- NUEVOS ACCESOS DEL PANEL ADMINISTRATIVO -->
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][constancias]" <?= isset($user['constancias']) && $user['constancias'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][preinscripciones]" <?= isset($user['preinscripciones']) && $user['preinscripciones'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][inscripcion_materias]" <?= isset($user['inscripcion_materias']) && $user['inscripcion_materias'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][aprobar_secciones]" <?= isset($user['aprobar_secciones']) && $user['aprobar_secciones'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][aulas]" <?= isset($user['aulas']) && $user['aulas'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][actas_calificacion]" <?= isset($user['actas_calificacion']) && $user['actas_calificacion'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][secretaria]" <?= isset($user['secretaria']) && $user['secretaria'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][ver_estudiantes]" <?= isset($user['ver_estudiantes']) && $user['ver_estudiantes'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][ver_docentes]" <?= isset($user['ver_docentes']) && $user['ver_docentes'] ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="permisos[<?= (int)$user['id'] ?>][mensajeria]" <?= isset($user['mensajeria']) && $user['mensajeria'] ? 'checked' : '' ?>>
                        </td>
                    </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="52" class="text-center">No hay usuarios registrados</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-right mt-3">
            <button type="submit" name="guardar" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>

<!-- JavaScript para el filtrado en tiempo real -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtroAccesos = document.getElementById('filtro-accesos');
    const buscador = document.getElementById('buscador');
    const limpiarBusqueda = document.getElementById('limpiar-busqueda');
    const filasUsuarios = document.querySelectorAll('#tabla-usuarios tr.fila-usuario');
    
    // Función para aplicar ambos filtros
    function aplicarFiltros() {
        const filtro = filtroAccesos.value;
        const textoBusqueda = buscador.value.toLowerCase();
        
        filasUsuarios.forEach(fila => {
            const esUsuario = fila.classList.contains('usuario');
            const esEstudiante = fila.classList.contains('estudiante');
            const esPersonal = fila.classList.contains('personal');
            const esSinAccesos = fila.classList.contains('sin-accesos');
            const nombreUsuario = fila.getAttribute('data-nombre');
            const coincideBusqueda = nombreUsuario.includes(textoBusqueda);
            
            // Aplicar filtro principal
            let mostrarFila = false;
            
            switch(filtro) {
                case 'personal':
                    mostrarFila = esPersonal;
                    break;
                case 'estudiantes':
                    mostrarFila = esEstudiante;
                    break;
                case 'sin-accesos':
                    mostrarFila = esSinAccesos;
                    break;
            }
            
            // Aplicar búsqueda
            if (textoBusqueda && !coincideBusqueda) {
                mostrarFila = false;
            }
            
            // Mostrar/ocultar fila según los filtros
            fila.style.display = mostrarFila ? '' : 'none';
        });
    }
    
    // Event listeners
    filtroAccesos.addEventListener('change', aplicarFiltros);
    buscador.addEventListener('input', aplicarFiltros);
    
    limpiarBusqueda.addEventListener('click', function() {
        buscador.value = '';
        aplicarFiltros();
    });
    
    // Aplicar filtros al cargar la página (mostrar solo personal por defecto)
    aplicarFiltros();
});
</script>

<?php include("includes/footer.php"); ?>