<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$titulo = "Preinscripción";
include('funciones/functions.php');

$modo_preinscripcion = true;
$esModal = false;

$tiposCedula = obtenerTiposCedula($db);
$estadosCiviles = obtenerEstadosCiviless($db);
$tiposVivienda = obtenerTiposVivienda($db);
$tenenciasVivienda = obtenerTenenciaViviendas($db);
$opcionesStatus = obtenerOpcionesStatus($db);
$carreras = obtenerTodasLasCarreras();
$ingresos = obtenerIngresos($db);
$estados = obtenerEstados($db);
$mostrarPreinscripcion = obtenerConfiguracionSecretaria('mostrar_preinscripcion', '1');

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodoActivo = obtenerPeriodoActivo();
    $_POST['tipo_cedula'] = $_POST['tipo_cedula'] ?? '';
    $_POST['numero_cedula'] = preg_replace('/[^0-9]/', '', $_POST['numero_cedula'] ?? '');
    $_POST['idusuario'] = trim($_POST['tipo_cedula'] . $_POST['numero_cedula']);
    $_POST['turno'] = $_POST['turno'] ?? '';
    $_POST['status'] = 'Pendiente';
    $_POST['fecha_ingreso'] = $_POST['fecha_ingreso'] ?? date('Y-m-d');
    $_POST['id_periodo'] = $periodoActivo ? (int)$periodoActivo['id_periodo'] : null;

    if ($mostrarPreinscripcion === '0') {
        $error_message = 'La preinscripción se encuentra temporalmente deshabilitada. Por favor contacte a Secretaría.';
    } elseif (!$periodoActivo) {
        $error_message = 'No hay un período académico activo disponible para preinscripciones. Por favor contacte a Secretaría.';
    } elseif (empty($_POST['tipo_cedula']) || empty($_POST['numero_cedula'])) {
        $error_message = 'Debe indicar tipo y número de cédula para la preinscripción.';
    } else {
        $validacion = validarEstudiante($_POST);
        if ($validacion === true) {
            $resultado = insertarPreinscripcion($_POST);
            if ($resultado['success']) {
                $success_message = $resultado['message'];
                
                // ==============================================
                // 🔹 ENVIAR CORREO DE CONFIRMACIÓN DE PREINSCRIPCIÓN
                // ==============================================
                $nombre_estudiante = $_POST['nombre'] ?? '';
                $email_estudiante = $_POST['email'] ?? '';
                $carrera_nombre = obtenerNombreCarrera($_POST['carrera'] ?? '');
                $turno = $_POST['turno'] ?? '';
                $cedula = $_POST['idusuario'] ?? '';
                $fecha_solicitud = date('d/m/Y');
                $id_preinscripcion = $resultado['id'] ?? '';

                // Verificar que tengamos email y nombre antes de enviar
                if (!empty($email_estudiante) && !empty($nombre_estudiante)) {
                    $asunto = "✅ Preinscripción Exitosa - UPTPC";
                    $cuerpo = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <title>Preinscripción Exitosa</title>
                    </head>
                    <body style='font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;'>
                        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>
                            
                            <div style='background: linear-gradient(135deg, #003366 0%, #00509e 100%); padding: 30px 20px; text-align: center;'>
                                <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>🏛️ UPTPC</h1>
                                <p style='color: #ffd700; margin: 5px 0 0; font-size: 14px;'>Universidad Politécnica Territorial de Puerto Cabello</p>
                                <p style='color: #cce5ff; margin: 5px 0 0; font-size: 12px;'>Sistema de Control de Estudios</p>
                            </div>
                            
                            <div style='padding: 30px 25px;'>
                                <h2 style='color: #003366; margin-top: 0;'>¡Hola, $nombre_estudiante!</h2>
                                
                                <div style='background-color: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin: 15px 0;'>
                                    <p style='color: #155724; font-size: 18px; margin: 0; font-weight: bold;'>✅ ¡Tu preinscripción ha sido enviada exitosamente!</p>
                                </div>
                                
                                <p style='color: #333; font-size: 16px; line-height: 1.5;'>Hemos recibido tu solicitud de preinscripción para el <strong>Sistema de Control de Estudios de la Universidad Politécnica Territorial de Puerto Cabello (UPTPC)</strong>.</p>
                                
                                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                                    <p style='margin: 5px 0;'><strong>📋 Datos de tu preinscripción:</strong></p>
                                    <p style='margin: 5px 0;'>🔹 <strong>Nombre:</strong> $nombre_estudiante</p>
                                    <p style='margin: 5px 0;'>🔹 <strong>Cédula:</strong> $cedula</p>
                                    <p style='margin: 5px 0;'>🔹 <strong>Programa:</strong> " . htmlspecialchars($carrera_nombre) . "</p>
                                    <p style='margin: 5px 0;'>🔹 <strong>Turno:</strong> $turno</p>
                                    <p style='margin: 5px 0;'>🔹 <strong>Fecha de solicitud:</strong> $fecha_solicitud</p>
                                    " . (!empty($id_preinscripcion) ? "<p style='margin: 5px 0;'>🔹 <strong>ID Preinscripción:</strong> $id_preinscripcion</p>" : "") . "
                                </div>
                                
                                <div style='background-color: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0;'>
                                    <p style='color: #856404; margin: 0;'>
                                        <strong>📌 IMPORTANTE:</strong><br>
                                        Debes presentarte en <strong>Control de Estudios</strong> para formalizar tu inscripción, llevando contigo tus <strong>documentos originales y copia</strong>. 
                                        Lleva contigo la planilla de preinscripción que se descargó al finalizar este proceso, en los días hábiles informados. 
                                        Ten en cuenta que <strong>la preinscripción no asegura un cupo en la universidad</strong>.
                                    </p>
                                </div>
                                
                                <div style='border-left: 4px solid #003366; background-color: #e8f0fe; padding: 12px 15px; margin: 20px 0; border-radius: 5px;'>
                                    <p style='color: #003366; font-size: 13px; margin: 0;'>
                                        <strong>🔔 Próximos pasos:</strong><br>
                                        1. Imprime tu planilla de preinscripción.<br>
                                        2. Dirígete a la oficina de Control de Estudios con tus documentos originales y copia.<br>
                                        3. Formaliza tu inscripción (recuerda que la preinscripción no asegura un cupo en la universidad).
                                    </p>
                                </div>
                            </div>
                            
                            
                        </div>
                    </body>
                    </html>";

                    // Enviar el correo usando la función existente
                    enviarEmail($email_estudiante, $nombre_estudiante, $asunto, $cuerpo);
                }
                // ==============================================
                // FIN DEL ENVÍO DE CORREO
                // ==============================================
                
                // Agregar botón para descargar planilla en el mensaje de éxito
                $success_message .= '<br><br>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-download"></i> 
                    <strong>¿No se descargó automáticamente?</strong>
                    <a href="admin/generar_planilla_pdf.php?id=' . $resultado['id'] . '" class="btn btn-sm btn-primary ms-3" target="_blank">
                        <i class="fas fa-file-pdf"></i> Descargar Planilla
                    </a>
                </div>';
                
                // JavaScript para descarga automática del PDF
                echo '<script>
                    setTimeout(function() {
                        window.open("admin/generar_planilla_pdf.php?id=' . $resultado['id'] . '", "_blank");
                    }, 500);
                </script>';
                
                $_POST = [];
            } else {
                $error_message = $resultado['message'];
            }
        } else {
            $error_message = implode('<br>', $validacion);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preinscripción</title>
    <?php echo $bootstrap_head; ?>
</head>
<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-file-signature me-2"></i> Preinscripción</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" role="alert"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-file-pdf me-2"></i> 
                        <strong>IMPORTANTE:</strong> Al finalizar su preinscripción, se descargará automáticamente una planilla en formato PDF. 
                        Deberá presentar esta planilla impresa en <strong>Control de Estudios</strong> para formalizar su inscripción, 
                        debiendo llevar los <strong>documentos originales y copia</strong>. 
                        <strong>La planilla es OBLIGATORIA</strong> y debe ser entregada dentro de los días hábiles siguientes a su preinscripción. 
                        Tenga en cuenta que <strong>la preinscripción no asegura un cupo en la universidad</strong>.
                    </div>

                    <p class="mb-4">Complete el formulario de preinscripción con sus datos personales, académicos y de contacto. Todos los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

                    <?php if ($mostrarPreinscripcion !== '0'): ?>
                        <?php include 'admin/_formulario_estudiante.php'; ?>
                    <?php else: ?>
                        <div class="alert alert-warning" role="alert">
                            La preinscripción está deshabilitada actualmente. Por favor contacte a Secretaría para más información.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="funciones/bootstrap-4.6.2-dist/js/bootstrap.bundle.min.js"></script>

<?php if (!empty($error_message)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Atención',
            html: <?php echo json_encode($error_message); ?>,
            confirmButtonColor: '#003366',
            confirmButtonText: 'Entendido'
        });
    }
});
</script>
<?php endif; ?>

</body>
</html>