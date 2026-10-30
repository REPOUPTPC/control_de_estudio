<?php
/**
 * =========================================================================================
 * SISTEMA DE CONTROL DE ESTUDIOS - GENERADOR UNIFICADO DE CONSTANCIAS Y SOLICITUDES EN PDF
 * =========================================================================================
 * 
 * Este módulo centraliza y unifica la generación de todos los documentos académicos (PDF)
 * del sistema, garantizando:
 *  - Autenticación estricta y aislamiento por roles (Estudiante, Administrador, Super Usuario).
 *  - Acceso seguro mediante solicitudes POST (o sesión directa del usuario conectado).
 *  - Prevención de suplantación de identidad (los estudiantes solo generan sus propios documentos).
 *  - Validación académica inteligente de aptitud (Intensivo, Extraordinario, Pasantías/Proyecto).
 *  - Código modular, limpio, estandarizado y libre de duplicidades.
 * 
 * @package    ControlDeEstudios\Constancias
 * @author     UPTPC - Unidad de Tecnología
 * @version    2.0
 */

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

// Incluir dependencias centrales
require_once(__DIR__ . '/../funciones/functions.php');
require_once(__DIR__ . '/../fpdf/fpdf.php');

// =========================================================================================
// 1. AUTENTICACIÓN Y CONTROL DE ACCESO
// =========================================================================================

if (!isLoggedIn()) {
    http_response_code(403);
    die("Acceso denegado: Debe iniciar sesión para generar constancias.");
}

// Determinar el ID del estudiante de forma segura
$id_estudiante = 0;

if (isEstudiante()) {
    $id_estudiante = intval($_SESSION['user']['id']);
} elseif (isAdmin() || isSuperUser() || (function_exists('isDirector') && isDirector()) || (function_exists('isDocente') && isDocente())) {
    if (isset($_POST['id']) && intval($_POST['id']) > 0) {
        $id_estudiante = intval($_POST['id']);
    } elseif (isset($_POST['id_estudiante']) && intval($_POST['id_estudiante']) > 0) {
        $id_estudiante = intval($_POST['id_estudiante']);
    } elseif (isset($_GET['id']) && intval($_GET['id']) > 0) {
        $id_estudiante = intval($_GET['id']);
    } elseif (isset($_POST['solicitud_id']) || isset($_GET['solicitud_id'])) {
        // Se determinará a partir de la solicitud
        $id_estudiante = 0;
    } else {
        $id_estudiante = intval($_SESSION['user']['id']);
    }
} else {
    // Fallback general para cualquier usuario con sesión
    if (isset($_POST['id']) && intval($_POST['id']) > 0) {
        $id_estudiante = intval($_POST['id']);
    } elseif (isset($_SESSION['user']['id'])) {
        $id_estudiante = intval($_SESSION['user']['id']);
    }
}

// Verificar si viene solicitud_id para cargar datos de la solicitud registrada
$solicitud_id = isset($_POST['solicitud_id']) ? intval($_POST['solicitud_id']) : (isset($_GET['solicitud_id']) ? intval($_GET['solicitud_id']) : 0);
$solicitud_data = null;
if ($solicitud_id > 0 && function_exists('obtenerSolicitudAcademicaPorId')) {
    $solicitud_data = obtenerSolicitudAcademicaPorId($solicitud_id);
    if ($solicitud_data) {
        if (!isEstudiante() || intval($solicitud_data['estudiante_id']) === intval($_SESSION['user']['id'])) {
            $id_estudiante = intval($solicitud_data['estudiante_id']);
            $tipo = $solicitud_data['tipo_solicitud'];
        }
    }
}

if ($id_estudiante <= 0) {
    die("Identificador de estudiante inválido.");
}

// Determinar el tipo de documento solicitado
$tipo = !empty($tipo) ? $tipo : (isset($_POST['tipo']) ? trim($_POST['tipo']) : (isset($_GET['tipo']) ? trim($_GET['tipo']) : 'estudios'));
$tipo = strtolower($tipo);

// Mapeo de nombres alternativos a tipos normalizados
$alias_tipos = [
    'pdf_inscripcion.php' => 'inscripcion',
    'pdf_estudios.php' => 'estudios',
    'pdf_intensivo.php' => 'intensivo',
    'pdf_evaluacion_extraordinaria.php' => 'evaluacion_extraordinaria',
    'extraordinario' => 'evaluacion_extraordinaria',
    'pdf_adicion_retiro.php' => 'adicion_retiro',
    'pdf_inscripcion_practicas.php' => 'inscripcion_practicas',
    'pasantias' => 'inscripcion_practicas',
    'proyecto' => 'inscripcion_practicas',
    'pdf_cambio_seccion.php' => 'cambio_seccion',
    'pdf_retiro_semestre.php' => 'retiro_semestre',
    'pdf_cambio_carrera.php' => 'cambio_carrera',
    'pdf_cambio_turno.php' => 'cambio_turno',
    'pdf_renuncia_cupo.php' => 'renuncia_cupo',
    'pdf_constancia_retiro.php' => 'constancia_retiro',
    'pdf_constancia_traslado.php' => 'constancia_traslado',
    'traslado' => 'constancia_traslado',
    'pdf_constancia_reincorporacion.php' => 'constancia_reincorporacion',
    'reincorporacion' => 'constancia_reincorporacion',
    'pdf_retiro_documento.php' => 'retiro_documento',
    'pdf_servicio_comunitario.php' => 'servicio_comunitario',
    'pdf_carta_culminacion.php' => 'carta_culminacion',
    'pdf_notas_certificadas.php' => 'notas_certificadas',
    'pdf_constancia.php' => 'constancia'
];

if (isset($alias_tipos[$tipo])) {
    $tipo = $alias_tipos[$tipo];
}

// Parámetros opcionales
$destino = isset($_POST['destino']) ? trim($_POST['destino']) : (isset($_GET['destino']) ? trim($_GET['destino']) : "UNIVERSIDAD POLITÉCNICA TERRITORIAL DE CIUDAD BOLÍVAR");
$tipo_reporte = isset($_POST['tipo_reporte']) ? strtolower(trim($_POST['tipo_reporte'])) : (isset($_GET['tipo']) ? strtolower(trim($_GET['tipo'])) : 'tsu');
$motivo_personalizado = isset($_POST['motivo']) ? trim($_POST['motivo']) : (isset($_GET['motivo']) ? trim($_GET['motivo']) : '');


// =========================================================================================
// 2. CONSULTA DE INFORMACIÓN DEL ESTUDIANTE
// =========================================================================================

$estudiante = obtenerEstudiantePorId($id_estudiante);
if (!$estudiante) {
    // Consulta directa de fallback
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id_estudiante);
    $stmt->execute();
    $estudiante = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$estudiante) {
    die("Estudiante no encontrado en la base de datos.");
}

$carrera = obtenerCarreraEstudiante($id_estudiante);
$id_carrera = $carrera['id_carrera'] ?? intval($estudiante['carrera'] ?? 0);

if ($id_carrera > 0 && function_exists('obtenerTrayectoActual')) {
    $trayecto_actual = obtenerTrayectoActual($id_estudiante, $id_carrera);
} else {
    $trayecto_actual = function_exists('obtenerTrayectoActualEstudiante') ? obtenerTrayectoActualEstudiante($id_estudiante) : 0;
}

$infoTrayecto = function_exists('obtenerInfoTrayecto') ? obtenerInfoTrayecto($trayecto_actual) : [
    'numero_trayecto' => 0,
    'nombre_trayecto' => 'TRAYECTO INICIAL',
    'descripcion' => 'Trayecto Inicial'
];

// Asignar variables de texto formateadas
$cedula_estudiante = trim($estudiante['idusuario'] ?? '');
$nombre_estudiante = mb_strtoupper(trim($estudiante['nombre'] ?? ''), 'UTF-8');
$nombre_carrera = mb_strtoupper(trim($carrera['nombre_carrera'] ?? 'PROGRAMA NACIONAL DE FORMACIÓN'), 'UTF-8');
$cod_carrera = mb_strtoupper(trim($carrera['cod_carrera'] ?? 'N/A'), 'UTF-8');
$nombre_trayecto = mb_strtoupper(trim($infoTrayecto['nombre_trayecto'] ?? 'TRAYECTO INICIAL'), 'UTF-8');
$turno_estudiante = mb_strtoupper(trim($estudiante['turno'] ?? 'DIURNO'), 'UTF-8');
if (empty($turno_estudiante)) $turno_estudiante = 'DIURNO';


// =========================================================================================
// 3. FUNCIONES AUXILIARES DE FORMATEO Y CLASE BASE PDF
// =========================================================================================

/**
 * Convierte cadenas UTF-8 a ISO-8859-1 para compatibilidad total con FPDF
 */
if (!function_exists('txtPDF')) {
    function txtPDF($texto) {
        if (function_exists('formatearTextoPDF')) {
            return formatearTextoPDF($texto);
        }
        if ($texto === null || $texto === '') return '';
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$texto);
            if ($c !== false) return $c;
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding((string)$texto, 'ISO-8859-1', 'UTF-8');
        }
        if (function_exists('utf8_decode')) {
            return utf8_decode((string)$texto);
        }
        return (string)$texto;
    }
}

/**
 * Retorna la fecha actual desglosada en texto formal legal venezolano
 */
function obtenerFechaLegal() {
    $meses = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
    $dias_letras = [
        1 => "Primero", 2 => "dos", 3 => "tres", 4 => "cuatro", 5 => "cinco",
        6 => "seis", 7 => "siete", 8 => "ocho", 9 => "nueve", 10 => "diez",
        11 => "once", 12 => "doce", 13 => "trece", 14 => "catorce", 15 => "quince",
        16 => "dieciséis", 17 => "diecisiete", 18 => "dieciocho", 19 => "diecinueve", 20 => "veinte",
        21 => "veintiuno", 22 => "veintidós", 23 => "veintitrés", 24 => "veinticuatro", 25 => "veinticinco",
        26 => "veintiséis", 27 => "veintisiete", 28 => "veintiocho", 29 => "veintinueve", 30 => "treinta",
        31 => "treinta y uno"
    ];

    $d = intval(date('d'));
    $m_index = intval(date('m')) - 1;
    $mes = $meses[$m_index] ?? "enero";
    $y = date('Y');
    $anio_letras = ($y == "2026") ? "dos mil veintiséis" : "dos mil " . $y;

    return [
        'dia_num'     => str_pad($d, 2, "0", STR_PAD_LEFT),
        'dia_letras'  => $dias_letras[$d] ?? (string)$d,
        'dia_txt'     => ($dias_letras[$d] ?? (string)$d) . " (" . str_pad($d, 2, "0", STR_PAD_LEFT) . ")",
        'mes'         => $mes,
        'anio_num'    => $y,
        'anio_letras' => $anio_letras,
        'anio_txt'    => $anio_letras . " ($y)"
    ];
}

/**
 * Clase Central FPDF para Constancias Institucionales
 */
class ConstanciaInstitucionalPDF extends FPDF {
    public $documentTitle = "CONSTANCIA DE ESTUDIOS";
    public $subtitle = "DEPARTAMENTO DE CONTROL DE ESTUDIOS";
    public $customHeaderType = "default"; // "default", "solicitud", "secretaria", "none"
    public $showFooter = true;
    public $footerText = "";

    function Header() {
        if ($this->customHeaderType === "none") {
            return;
        }

        // Logo institucional
        $logo_path = __DIR__ . '/../images/uptpc.png';
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 25, 12, 18);
        }

        $this->SetY(12);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, txtPDF('REPÚBLICA BOLIVARIANA DE VENEZUELA'), 0, 1, 'C');
        $this->Cell(0, 4, txtPDF('MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN UNIVERSITARIA'), 0, 1, 'C');
        $this->Cell(0, 4, txtPDF('UNIVERSIDAD POLITÉCNICA TERRITORIAL DE PUERTO CABELLO'), 0, 1, 'C');
        $this->Cell(0, 3.5, txtPDF('RIF: G-20005608-8'), 0, 1, 'C');
        $this->Cell(0, 4, txtPDF('SECRETARÍA DEL CONSEJO DE GESTIÓN UNIVERSITARIA'), 0, 1, 'C');

        if ($this->customHeaderType === "solicitud") {
            $this->Ln(3);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(0, 4, txtPDF('DIRECCIÓN DE PNF Y/O PTF'), 0, 1, 'C');
            $this->Cell(0, 4, txtPDF($this->documentTitle), 0, 1, 'C');
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 5, txtPDF('FECHA: ' . date('d/m/Y')), 0, 1, 'C');
            $this->Ln(4);
        } else {
            $this->Ln(12);
            $this->SetFont('Arial', 'B', 11);
            $this->Cell(0, 5, txtPDF($this->documentTitle), 0, 1, 'C');
            $this->Ln(10);
        }
    }

    function Footer() {
        if (!$this->showFooter) return;

        global $rif_empresa, $direccion_empresa, $correo_institucion;
        $rif_txt = !empty($rif_empresa) ? "RIF: " . $rif_empresa : "RIF: G-20005608-8";
        $dir_txt = !empty($direccion_empresa) ? $direccion_empresa : "Urbanización La Elvira, Zona Industrial Santa Rosa, Galpón N° 8, Puerto Cabello";
        $mail_txt = !empty($correo_institucion) ? $correo_institucion : "control_de_estudios@uptpc.edu.ve";

        $this->SetY(-25);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 4, txtPDF("Este documento no es válido sin la firma y sello del Departamento de Control de Estudios"), 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 3, txtPDF($dir_txt . " - " . $rif_txt), 0, 1, 'C');
        $this->Cell(0, 3, txtPDF("Correo Electrónico: " . $mail_txt), 0, 1, 'C');
    }

    /**
     * Renderiza la firma estándar de la Jefa de Control de Estudios
     */
    function renderFirmaControlEstudios() {
        $this->Ln(30);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, txtPDF("Dra. Zorangel E. Aponte Q."), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, txtPDF("Jefe de Control de Estudios"), 0, 1, 'C');
        $this->Cell(0, 4, txtPDF("Resolución N° 07-2022 de fecha 01/11/2022 Consejo N°07"), 0, 1, 'C');
        $this->Cell(0, 4, txtPDF("De fecha 01/11/2022"), 0, 1, 'C');
    }

    /**
     * Renderiza la firma de la Secretaría del Consejo
     */
    function renderFirmaSecretaria() {
        $this->Ln(30);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, txtPDF("Dra. Blanca A. Crespo C."), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, txtPDF("Secretario del Consejo de Gestión Universitaria"), 0, 1, 'C');
        $this->Cell(0, 4, txtPDF("Resolución N° 34 de fecha 20/07/2022 Gaceta Oficial"), 0, 1, 'C');
        $this->Cell(0, 4, txtPDF("República Bolivariana de Venezuela N° 457.753 de fecha 22/07/2022"), 0, 1, 'C');
    }

    /**
     * Renderiza bloque de fecha legal centrada
     */
    function renderFechaLegalCentrada() {
        $f = obtenerFechaLegal();
        $this->Ln(12);
        $this->SetFont('Arial', '', 11);
        
        $texto_fecha = "Documento que se emite en la ciudad de Puerto Cabello, a los " . $f['dia_txt'] . " del mes de " . $f['mes'] . " del año " . $f['anio_txt'] . ".";
        $w = $this->GetStringWidth(txtPDF($texto_fecha));
        $this->SetX(($this->GetPageWidth() - $w) / 2);

        $this->Write(6, txtPDF("Documento que se emite en la ciudad de Puerto Cabello, a los "));
        $this->SetFont('Arial', 'B', 11); $this->Write(6, txtPDF($f['dia_txt']));
        $this->SetFont('Arial', '', 11); $this->Write(6, txtPDF(" del mes de "));
        $this->SetFont('Arial', 'B', 11); $this->Write(6, txtPDF($f['mes']));
        $this->SetFont('Arial', '', 11); $this->Write(6, txtPDF(" del año "));
        $this->SetFont('Arial', 'B', 11); $this->Write(6, txtPDF($f['anio_txt']));
        $this->SetFont('Arial', '', 11); $this->Write(6, txtPDF("."));
    }
}


// =========================================================================================
// 4. ENRUTADOR Y GENERADORES MODULARES DE DOCUMENTOS
// =========================================================================================

switch ($tipo) {

    // -------------------------------------------------------------------------------------
    // A. CONSTANCIA DE INSCRIPCIÓN (TRAYECTO INICIAL / TRAYECTO 0)
    // -------------------------------------------------------------------------------------
    case 'inscripcion':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CONSTANCIA DE INSCRIPCIÓN";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Quien suscribe "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefe de Control de Estudio de nuestra Institución, hace constar que el (la) Ciudadano (a) que se menciona a continuación."));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", se encuentra formalmente inscrito en esta casa de estudios y es cursante del "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Trayecto Inicial"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" del Programa Nacional de Formación en:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, txtPDF($nombre_carrera), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 6, txtPDF("Para el período académico correspondiente, cumpliendo con las normativas internas y académicas vigentes en nuestra institución universitaria."), 0, 'J');

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Constancia_Inscripcion_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // B. CONSTANCIA DE ESTUDIOS REGULARES
    // -------------------------------------------------------------------------------------
    case 'estudios':
    case 'constancia':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CONSTANCIA DE ESTUDIOS";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Quien suscribe "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefe de Control de Estudio de nuestra Institución, hace constar que el (la) Ciudadano (a) que se menciona a continuación:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", es estudiante regular de esta casa de estudios y actualmente cursa el "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF($nombre_trayecto));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" en el turno "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF($turno_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" del Programa Nacional de Formación en:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, txtPDF($nombre_carrera), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 6, txtPDF("Constancia que se expide a petición de la parte interesada para los fines legales y académicos consiguientes."), 0, 'J');

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Constancia_Estudios_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // C. CONSTANCIA DE INTENSIVO
    // -------------------------------------------------------------------------------------
    case 'intensivo':
        // Validación de aptitud
        if (function_exists('esAptoParaIntensivo') && !esAptoParaIntensivo($id_estudiante)) {
            die("Acceso no permitido: El estudiante no se encuentra apto para cursar o solicitar constancia de intensivo.");
        }

        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CONSTANCIA DE ESTUDIOS DE INTENSIVO";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Quien suscribe "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefe de Control de Estudio de nuestra Institución, hace constar que el (la) Ciudadano (a) que se menciona a continuación:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", se encuentra formalmente inscrito(a) en el Programa Nacional de Formación en:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, txtPDF($nombre_carrera), 0, 1, 'C');
        $pdf->Ln(10);

        $lapso = "2025-1";
        $materia_intensivo = "MATEMÁTICA II";
        $fecha_inicio = "04-08-2025";
        $fecha_fin = "29-08-2025";

        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 6, txtPDF("Para el lapso académico $lapso, para cursar el curso intensivo vacacional en la unidad curricular $materia_intensivo el cual inició el $fecha_inicio y finaliza el $fecha_fin."), 0, 'J');

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Constancia_Intensivo_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // D. EVALUACIÓN EXTRAORDINARIA / EXAMEN DE SUFICIENCIA
    // -------------------------------------------------------------------------------------
    case 'evaluacion_extraordinaria':
        if (function_exists('esAptoParaExtraordinario') && !esAptoParaExtraordinario($id_estudiante)) {
            die("Acceso no permitido: El estudiante no se encuentra apto para solicitar evaluación extraordinaria.");
        }

        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "SOLICITUD DE EVALUACIÓN EXTRAORDINARIA Y/O EXAMEN DE SUFICIENCIA";
        $pdf->customHeaderType = "solicitud";
        $pdf->showFooter = false;
        $pdf->SetMargins(10, 15, 10);
        $pdf->AddPage();

        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 7);

        $h = 7;
        $pdf->Cell(8, $h, txtPDF('N°'), 1, 0, 'C', true);
        $pdf->Cell(25, $h, txtPDF('Cédula'), 1, 0, 'C', true);
        $pdf->Cell(40, $h, txtPDF('Apellidos'), 1, 0, 'C', true);
        $pdf->Cell(40, $h, txtPDF('Nombres'), 1, 0, 'C', true);
        $pdf->Cell(25, $h, txtPDF('PNF/PTF'), 1, 0, 'C', true);
        $pdf->Cell(58, $h, txtPDF('Unidad Curricular'), 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 7);
        // Fila 1 pre-llenada con los datos del estudiante
        $partes_nombre = explode(' ', $nombre_estudiante);
        $apellidos = isset($partes_nombre[2]) ? ($partes_nombre[0] . ' ' . $partes_nombre[1]) : ($partes_nombre[0] ?? '');
        $nombres = isset($partes_nombre[2]) ? implode(' ', array_slice($partes_nombre, 2)) : ($partes_nombre[1] ?? '');

        $pdf->Cell(8, 6, '1', 1, 0, 'C');
        $pdf->Cell(25, 6, txtPDF($cedula_estudiante), 1, 0, 'C');
        $pdf->Cell(40, 6, txtPDF($apellidos), 1, 0, 'C');
        $pdf->Cell(40, 6, txtPDF($nombres), 1, 0, 'C');
        $pdf->Cell(25, 6, txtPDF($cod_carrera), 1, 0, 'C');
        $pdf->Cell(58, 6, '', 1, 1, 'C');

        for ($i = 2; $i <= 15; $i++) {
            $pdf->Cell(8, 6, $i, 1, 0, 'C');
            $pdf->Cell(25, 6, '', 1, 0, 'C');
            $pdf->Cell(40, 6, '', 1, 0, 'C');
            $pdf->Cell(40, 6, '', 1, 0, 'C');
            $pdf->Cell(25, 6, '', 1, 0, 'C');
            $pdf->Cell(58, 6, '', 1, 1, 'C');
        }

        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 8);
        $y_firma = $pdf->GetY();

        $pdf->Rect(10, $y_firma, 60, 20);
        $pdf->SetXY(10, $y_firma);
        $pdf->Cell(60, 5, txtPDF('Firma Estudiante'), 0, 0, 'C');

        $pdf->Rect(75, $y_firma, 60, 20);
        $pdf->SetXY(75, $y_firma);
        $pdf->Cell(60, 5, txtPDF('Firma Director'), 0, 0, 'C');

        $pdf->Rect(140, $y_firma, 66, 20);
        $pdf->SetXY(140, $y_firma);
        $pdf->Cell(66, 5, txtPDF('Observación'), 0, 0, 'C');

        $pdf->SetY(-20);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 10, txtPDF('Control de Estudios Firma y Sello'), 0, 0, 'L');

        ob_end_clean();
        $pdf->Output('I', "Solicitud_Evaluacion_Extraordinaria_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // E. INSCRIPCIÓN EN PASANTÍAS / PROYECTO SOCIOINTEGRADOR
    // -------------------------------------------------------------------------------------
    case 'inscripcion_practicas':
        if (function_exists('esAptoParaPasantias') && !esAptoParaPasantias($id_estudiante)) {
            die("Acceso no permitido: La inscripción en pasantías y proyecto está reservada para Trayecto I o superior.");
        }

        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "INSCRIPCIONES PASANTIAS Y PROYECTO";
        $pdf->SetMargins(15, 20, 15);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(35, 6, txtPDF('FECHA'), 1, 0, 'C', true);
        $pdf->Cell(45, 6, txtPDF('C. I.'), 1, 0, 'C', true);
        $pdf->Cell(105, 6, txtPDF('APELLIDOS Y NOMBRES'), 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(35, 8, date('d/m/Y'), 1, 0, 'C');
        $pdf->Cell(45, 8, 'V-' . $cedula_estudiante, 1, 0, 'C');
        $pdf->Cell(105, 8, txtPDF($nombre_estudiante), 1, 1, 'C');

        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(50, 6, txtPDF('Fecha de Nacimiento:'), 'LTR', 0, 'L', true);
        $pdf->Cell(65, 6, txtPDF('Lugar de Nacimiento'), 'TR', 0, 'L', true);
        $pdf->Cell(70, 6, txtPDF('Correo Electrónico'), 'TR', 1, 'L', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(50, 8, txtPDF($estudiante['f_nac'] ?? $estudiante['fecha_nac'] ?? 'N/A'), 'LRB', 0, 'L');
        $pdf->Cell(65, 8, txtPDF($estudiante['lugar_nac'] ?? 'N/A'), 'RB', 0, 'L');
        $pdf->Cell(70, 8, txtPDF($estudiante['correo'] ?? $estudiante['email'] ?? 'N/A'), 'RB', 1, 'L');

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(115, 6, txtPDF('Dirección de Habitación'), 'LR', 0, 'L', true);
        $pdf->Cell(70, 6, txtPDF('Teléfono'), 'R', 1, 'L', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(115, 8, txtPDF($estudiante['direccion'] ?? 'N/A'), 'LRB', 0, 'L');
        $pdf->Cell(70, 8, txtPDF($estudiante['telefono'] ?? $estudiante['celular'] ?? 'N/A'), 'RB', 1, 'L');

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(85, 6, txtPDF('Nombre de la Empresa / Comunidad'), 'LR', 0, 'L', true);
        $pdf->Cell(100, 6, txtPDF('Dirección de la Empresa / Comunidad'), 'R', 1, 'L', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(85, 10, '', 'LRB', 0, 'L');
        $pdf->Cell(100, 10, '', 'RB', 1, 'L');

        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(85, 6, txtPDF('Recibido y Procesado Por'), 'LR', 0, 'C', true);
        $pdf->Cell(100, 6, txtPDF('Sello y Firma Control de Estudios'), 'R', 1, 'C', true);

        $pdf->Cell(85, 20, '', 'LRB', 0, 'C');
        $pdf->Cell(100, 20, '', 'RB', 1, 'C');

        ob_end_clean();
        $pdf->Output('I', "Inscripcion_Pasantias_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // F. ADICIÓN Y RETIRO DE MATERIAS
    // -------------------------------------------------------------------------------------
    case 'adicion_retiro':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "SOLICITUD DE ADICIÓN Y RETIRO DE UNIDADES CURRICULARES";
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(40, 6, txtPDF('FECHA:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $fecha_doc = !empty($solicitud_data['fecha_solicitud']) ? date('d/m/Y', strtotime($solicitud_data['fecha_solicitud'])) : date('d/m/Y');
        $pdf->Cell(145, 6, $fecha_doc, 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('CÉDULA DE IDENTIDAD:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, 'V-' . $cedula_estudiante, 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('APELLIDOS Y NOMBRES:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, txtPDF($nombre_estudiante), 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('CARRERA / PNF:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, txtPDF($nombre_carrera), 1, 1, 'L');

        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(25, 7, txtPDF('OPERACIÓN'), 1, 0, 'C', true);
        $pdf->Cell(30, 7, txtPDF('CÓDIGO'), 1, 0, 'C', true);
        $pdf->Cell(80, 7, txtPDF('UNIDAD CURRICULAR'), 1, 0, 'C', true);
        $pdf->Cell(25, 7, txtPDF('SECCIÓN'), 1, 0, 'C', true);
        $pdf->Cell(25, 7, txtPDF('TURNO'), 1, 1, 'C', true);

        // Extraer materias de solicitud si existen
        $filas_operaciones = [];
        if ($solicitud_data && !empty($solicitud_data['materias_parsed'])) {
            $parsed = $solicitud_data['materias_parsed'];
            if (!empty($parsed['retiros']) && is_array($parsed['retiros'])) {
                foreach ($parsed['retiros'] as $r) {
                    $filas_operaciones[] = [
                        'operacion' => 'RETIRO',
                        'codigo'    => $r['codigo'] ?? '',
                        'nombre'    => $r['nombre'] ?? '',
                        'seccion'   => $r['seccion'] ?? '',
                        'turno'     => $turno_estudiante
                    ];
                }
            }
            if (!empty($parsed['adiciones']) && is_array($parsed['adiciones'])) {
                foreach ($parsed['adiciones'] as $a) {
                    $filas_operaciones[] = [
                        'operacion' => 'ADICIÓN',
                        'codigo'    => $a['codigo'] ?? '',
                        'nombre'    => $a['nombre'] ?? '',
                        'seccion'   => $a['seccion'] ?? '',
                        'turno'     => $turno_estudiante
                    ];
                }
            }
        }

        $pdf->SetFont('Arial', '', 8);
        $total_filas = max(6, count($filas_operaciones));
        for ($i = 0; $i < $total_filas; $i++) {
            $op_data = $filas_operaciones[$i] ?? null;
            $op_texto = $op_data ? $op_data['operacion'] : (($i % 2 == 0) ? 'RETIRO' : 'ADICIÓN');
            $op_cod = $op_data ? $op_data['codigo'] : '';
            $op_nom = $op_data ? $op_data['nombre'] : '';
            $op_sec = $op_data ? $op_data['seccion'] : '';
            $op_tur = $op_data ? $op_data['turno'] : '';

            $pdf->Cell(25, 7, txtPDF($op_texto), 1, 0, 'C');
            $pdf->Cell(30, 7, txtPDF($op_cod), 1, 0, 'C');
            $pdf->Cell(80, 7, txtPDF($op_nom), 1, 0, 'L');
            $pdf->Cell(25, 7, txtPDF($op_sec), 1, 0, 'C');
            $pdf->Cell(25, 7, txtPDF($op_tur), 1, 1, 'C');
        }

        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 5, txtPDF('MOTIVO DE LA SOLICITUD:'), 0, 1, 'L');
        $motivo_imprimir = $solicitud_data['motivo'] ?? (!empty($motivo_personalizado) ? $motivo_personalizado : 'Solicitud académica formal por parte del estudiante.');
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(185, 4, txtPDF($motivo_imprimir), 1, 'L');
        $pdf->Ln(3);

        $y_firma = $pdf->GetY();
        if ($y_firma > 220) {
            $pdf->AddPage();
            $y_firma = $pdf->GetY() + 5;
        }
        $pdf->Rect(15, $y_firma, 85, 18);
        $pdf->SetXY(15, $y_firma);
        $pdf->Cell(85, 5, txtPDF('Firma del Estudiante'), 0, 0, 'C');

        $pdf->Rect(115, $y_firma, 85, 18);
        $pdf->SetXY(115, $y_firma);
        $pdf->Cell(85, 5, txtPDF('Firma y Sello Control de Estudios'), 0, 1, 'C');

        ob_end_clean();
        $pdf->Output('I', "Solicitud_Adicion_Retiro_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // G. CAMBIO DE SECCIÓN
    // -------------------------------------------------------------------------------------
    case 'cambio_seccion':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "SOLICITUD DE CAMBIO DE SECCIÓN";
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(40, 6, txtPDF('FECHA:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $fecha_doc = !empty($solicitud_data['fecha_solicitud']) ? date('d/m/Y', strtotime($solicitud_data['fecha_solicitud'])) : date('d/m/Y');
        $pdf->Cell(145, 6, $fecha_doc, 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('CÉDULA:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, 'V-' . $cedula_estudiante, 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('ESTUDIANTE:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, txtPDF($nombre_estudiante), 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('PNF / CARRERA:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, txtPDF($nombre_carrera), 1, 1, 'L');

        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(85, 7, txtPDF('UNIDAD CURRICULAR'), 1, 0, 'C', true);
        $pdf->Cell(50, 7, txtPDF('SECCIÓN ACTUAL'), 1, 0, 'C', true);
        $pdf->Cell(50, 7, txtPDF('SECCIÓN SOLICITADA'), 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        $sec_orig_nombre = $solicitud_data['nombre_seccion_origen'] ?? '';
        $sec_dest_nombre = $solicitud_data['nombre_seccion_destino'] ?? '';
        
        $materias_cs = $solicitud_data['materias_parsed']['materias'] ?? [];
        $total_cs = max(5, count($materias_cs));
        for ($i = 0; $i < $total_cs; $i++) {
            $m_nombre = isset($materias_cs[$i]) ? (is_array($materias_cs[$i]) ? ($materias_cs[$i]['nombre'] ?? '') : $materias_cs[$i]) : ($i == 0 && empty($materias_cs) && !empty($sec_dest_nombre) ? 'TODAS LAS MATERIAS INSCRITAS' : '');
            $s_orig = ($i == 0 || !empty($m_nombre)) ? $sec_orig_nombre : '';
            $s_dest = ($i == 0 || !empty($m_nombre)) ? $sec_dest_nombre : '';
            
            $pdf->Cell(85, 7, txtPDF($m_nombre), 1, 0, 'L');
            $pdf->Cell(50, 7, txtPDF($s_orig), 1, 0, 'C');
            $pdf->Cell(50, 7, txtPDF($s_dest), 1, 1, 'C');
        }

        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 5, txtPDF('JUSTIFICACIÓN DEL CAMBIO:'), 0, 1, 'L');
        $motivo_imprimir = $solicitud_data['motivo'] ?? (!empty($motivo_personalizado) ? $motivo_personalizado : 'Solicitud formal de cambio de sección por motivos académicos/laborales.');
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(185, 4, txtPDF($motivo_imprimir), 1, 'L');
        $pdf->Ln(3);

        $y_firma = $pdf->GetY();
        if ($y_firma > 220) {
            $pdf->AddPage();
            $y_firma = $pdf->GetY() + 5;
        }
        $pdf->Rect(15, $y_firma, 85, 18);
        $pdf->SetXY(15, $y_firma);
        $pdf->Cell(85, 5, txtPDF('Firma del Estudiante'), 0, 0, 'C');

        $pdf->Rect(115, $y_firma, 85, 18);
        $pdf->SetXY(115, $y_firma);
        $pdf->Cell(85, 5, txtPDF('Firma y Sello Control de Estudios'), 0, 1, 'C');

        ob_end_clean();
        $pdf->Output('I', "Solicitud_Cambio_Seccion_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // H. RETIRO DE SEMESTRE
    // -------------------------------------------------------------------------------------
    case 'retiro_semestre':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CONSTANCIA DE RETIRO DE SEMESTRE";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Quien suscribe "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefe de Control de Estudio de nuestra Institución, hace constar que el (la) Ciudadano (a):"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", cursante del Programa Nacional de Formación en "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF($nombre_carrera));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", solicitó formalmente el "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("RETIRO DEL SEMESTRE"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" académico correspondiente."));

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Retiro_Semestre_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // I. CAMBIO DE CARRERA
    // -------------------------------------------------------------------------------------
    case 'cambio_carrera':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "SOLICITUD DE CAMBIO DE CARRERA / PNF";
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        $carrera_solicitada = !empty($solicitud_data['materias_parsed']['carrera_destino_nombre']) ? $solicitud_data['materias_parsed']['carrera_destino_nombre'] : '____________________________________________________';
        $motivo_solicitud = !empty($solicitud_data['motivo']) ? $solicitud_data['motivo'] : '';
        $fecha_sol = !empty($solicitud_data['fecha_solicitud']) ? date('d/m/Y', strtotime($solicitud_data['fecha_solicitud'])) : date('d/m/Y');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(40, 6, txtPDF('FECHA:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, $fecha_sol, 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('CÉDULA:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, 'V-' . $cedula_estudiante, 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('ESTUDIANTE:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, txtPDF($nombre_estudiante), 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('CARRERA ACTUAL:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, txtPDF($nombre_carrera), 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('CARRERA SOLICITADA:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(145, 6, txtPDF($carrera_solicitada), 1, 1, 'L');

        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 5, txtPDF('EXPOSICIÓN DE MOTIVOS:'), 0, 1, 'L');
        $y_mot = $pdf->GetY();
        $pdf->Rect(15, $y_mot, 185, 25);
        $pdf->SetXY(17, $y_mot + 2);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->MultiCell(181, 4.5, txtPDF($motivo_solicitud ? $motivo_solicitud : 'Sin motivo registrado.'), 0, 'L');
        $pdf->SetY($y_mot + 28);

        $y_firma = $pdf->GetY();
        $pdf->Rect(15, $y_firma, 85, 18);
        $pdf->SetXY(15, $y_firma);
        $pdf->Cell(85, 5, txtPDF('Firma del Estudiante'), 0, 0, 'C');

        $pdf->Rect(115, $y_firma, 85, 18);
        $pdf->SetXY(115, $y_firma);
        $pdf->Cell(85, 5, txtPDF('Consejo Directivo / Control de Estudios'), 0, 1, 'C');

        ob_end_clean();
        $pdf->Output('I', "Solicitud_Cambio_Carrera_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // J. CAMBIO DE TURNO
    // -------------------------------------------------------------------------------------
    case 'cambio_turno':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "SOLICITUD DE CAMBIO DE TURNO";
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        $turno_solicitado = !empty($solicitud_data['materias_parsed']['turno_destino']) ? $solicitud_data['materias_parsed']['turno_destino'] : '____________________________________________________';
        $motivo_solicitud = !empty($solicitud_data['motivo']) ? $solicitud_data['motivo'] : '';
        $fecha_sol = !empty($solicitud_data['fecha_solicitud']) ? date('d/m/Y', strtotime($solicitud_data['fecha_solicitud'])) : date('d/m/Y');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(40, 6, txtPDF('FECHA:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, $fecha_sol, 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('CÉDULA:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, 'V-' . $cedula_estudiante, 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('ESTUDIANTE:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, txtPDF($nombre_estudiante), 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('TURNO ACTUAL:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, txtPDF($turno_estudiante), 1, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 6, txtPDF('TURNO SOLICITADO:'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(145, 6, txtPDF($turno_solicitado), 1, 1, 'L');

        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 5, txtPDF('MOTIVO DE LA SOLICITUD (ADJUNTAR SOPORTES SI APLICA):'), 0, 1, 'L');
        $y_mot = $pdf->GetY();
        $pdf->Rect(15, $y_mot, 185, 25);
        $pdf->SetXY(17, $y_mot + 2);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->MultiCell(181, 4.5, txtPDF($motivo_solicitud ? $motivo_solicitud : 'Sin motivo registrado.'), 0, 'L');
        $pdf->SetY($y_mot + 28);

        $y_firma = $pdf->GetY();
        $pdf->Rect(15, $y_firma, 85, 18);
        $pdf->SetXY(15, $y_firma);
        $pdf->Cell(85, 5, txtPDF('Firma del Estudiante'), 0, 0, 'C');

        $pdf->Rect(115, $y_firma, 85, 18);
        $pdf->SetXY(115, $y_firma);
        $pdf->Cell(85, 5, txtPDF('Control de Estudios Firma y Sello'), 0, 1, 'C');

        ob_end_clean();
        $pdf->Output('I', "Solicitud_Cambio_Turno_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // K. RENUNCIA DE CUPO
    // -------------------------------------------------------------------------------------
    case 'renuncia_cupo':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "SOLICITUD DE RENUNCIA DE CUPO UNIVERSITARIO";
        $pdf->SetMargins(20, 20, 20);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Yo, "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF($nombre_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", por medio de la presente manifiesto mi decisión voluntaria de "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("RENUNCIAR FORMALMENTE AL CUPO"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" que me fue asignado en el Programa Nacional de Formación en:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, txtPDF($nombre_carrera), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 6, txtPDF("Esta renuncia se realiza de manera irrevocable y de mutuo acuerdo, solicitando a las autoridades de la Universidad Politécnica Territorial de Puerto Cabello procesar la desincorporación correspondiente."), 0, 'J');

        $pdf->renderFechaLegalCentrada();

        $pdf->Ln(20);
        $y_firma = $pdf->GetY();
        $pdf->Rect(20, $y_firma, 80, 20);
        $pdf->SetXY(20, $y_firma);
        $pdf->Cell(80, 5, txtPDF('Firma del Estudiante'), 0, 0, 'C');

        $pdf->Rect(115, $y_firma, 80, 20);
        $pdf->SetXY(115, $y_firma);
        $pdf->Cell(80, 5, txtPDF('Firma y Sello Control de Estudios'), 0, 1, 'C');

        ob_end_clean();
        $pdf->Output('I', "Renuncia_Cupo_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // L. CONSTANCIA DE RETIRO OFICIAL
    // -------------------------------------------------------------------------------------
    case 'constancia_retiro':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CONSTANCIA DE RETIRO DEFINITIVO";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Quien suscribe "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefa de Control de Estudios de nuestra Institución, hace constar que el (la) Ciudadano (a):"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", formalizó su "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("RETIRO OFICIAL"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" del Programa Nacional de Formación en:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, txtPDF($nombre_carrera), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Constancia_Retiro_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // M. CONSTANCIA DE TRASLADO
    // -------------------------------------------------------------------------------------
    case 'constancia_traslado':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CONSTANCIA DE TRASLADO UNIVERSITARIO";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Quien suscribe "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefa de Control de Estudios de nuestra Institución, hace constar que el (la) Ciudadano (a):"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", cursante del PNF en "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF($nombre_carrera));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", tramitó su "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("TRASLADO ACADÉMICO"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" hacia la institución: "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF($destino));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF("."));

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Constancia_Traslado_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // N. CONSTANCIA DE REINCORPORACIÓN
    // -------------------------------------------------------------------------------------
    // N. REPORTE / CONSTANCIA DE REINCORPORACIÓN
    // -------------------------------------------------------------------------------------
    case 'constancia_reincorporacion':
        $es_aprobada = ($solicitud_data && ($solicitud_data['status'] ?? '') === 'aprobada');
        $sol_num = $solicitud_id > 0 ? $solicitud_id : ($solicitud_data['id'] ?? 0);
        $fecha_sol = !empty($solicitud_data['fecha_solicitud']) ? date('d/m/Y', strtotime($solicitud_data['fecha_solicitud'])) : date('d/m/Y');
        $motivo_solicitud = !empty($solicitud_data['motivo']) ? $solicitud_data['motivo'] : (!empty($motivo_personalizado) ? $motivo_personalizado : 'Reincorporación a los estudios universitarios.');
        $periodo_sol = !empty($solicitud_data['materias_parsed']['nombre_periodo_asignado']) 
            ? $solicitud_data['materias_parsed']['nombre_periodo_asignado'] 
            : (!empty($solicitud_data['materias_parsed']['periodo_reincorporacion']) ? $solicitud_data['materias_parsed']['periodo_reincorporacion'] : 'Período Activo');

        if (!$es_aprobada) {
            // REPORTE / PLANILLA DE SOLICITUD DE REINCORPORACIÓN
            $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
            $pdf->documentTitle = "SOLICITUD / REPORTE DE REINCORPORACIÓN";
            $pdf->SetMargins(15, 15, 15);
            $pdf->AddPage();

            // Número de Trámite y Fecha
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(45, 6, txtPDF('SOLICITUD N°:'), 1, 0, 'L', true);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(140, 6, '#' . ($sol_num > 0 ? $sol_num : 'REINC-PENDIENTE') . '  -  FECHA: ' . $fecha_sol, 1, 1, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(45, 6, txtPDF('CÉDULA DE IDENTIDAD:'), 1, 0, 'L', true);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(140, 6, 'V-' . $cedula_estudiante, 1, 1, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(45, 6, txtPDF('APELLIDOS Y NOMBRES:'), 1, 0, 'L', true);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(140, 6, txtPDF($nombre_estudiante), 1, 1, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(45, 6, txtPDF('PROGRAMA / CARRERA:'), 1, 0, 'L', true);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(140, 6, txtPDF($nombre_carrera), 1, 1, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(45, 6, txtPDF('PERÍODO SOLICITADO:'), 1, 0, 'L', true);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(140, 6, txtPDF($periodo_sol), 1, 1, 'L');

            $pdf->Ln(4);
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->Cell(0, 5, txtPDF('MOTIVO / JUSTIFICACIÓN DE LA SOLICITUD:'), 0, 1, 'L');
            $y_mot = $pdf->GetY();
            $pdf->Rect(15, $y_mot, 185, 20);
            $pdf->SetXY(17, $y_mot + 2);
            $pdf->SetFont('Arial', '', 8.5);
            $pdf->MultiCell(181, 4.5, txtPDF($motivo_solicitud), 0, 'L');
            $pdf->SetY($y_mot + 23);

            // Recuadro de Requisitos Obligatorios
            $pdf->SetFillColor(255, 248, 225);
            $pdf->SetDrawColor(255, 179, 0);
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->Cell(0, 6, txtPDF('REQUISITOS OBLIGATORIOS A CONSIGNAR EN CONTROL DE ESTUDIOS:'), 1, 1, 'L', true);
            $pdf->SetDrawColor(0, 0, 0);

            $y_req = $pdf->GetY();
            $pdf->Rect(15, $y_req, 185, 32);
            $pdf->SetXY(18, $y_req + 2);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Write(4.5, txtPDF("Para formalizar el trámite de reincorporación, el estudiante debe consignar en Control de Estudios:\n"));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Write(4.5, txtPDF("  1. Copia ampliada y legible de la Cédula de Identidad.\n"));
            $pdf->Write(4.5, txtPDF("  2. Comprobante original del pago del arancel universitario correspondiente.\n"));
            $pdf->Write(4.5, txtPDF("  3. Dos (2) ejemplares impresos de este reporte/planilla de reincorporación.\n"));
            $pdf->SetFont('Arial', 'I', 7.5);
            $pdf->Write(4, txtPDF("* Nota: Control de Estudios evaluará sus requisitos académicos y proyectos aprobados para determinar el trayecto y sección correspondiente."));
            $pdf->SetY($y_req + 36);

            $y_firma = $pdf->GetY();
            $pdf->Rect(15, $y_firma, 85, 22);
            $pdf->SetXY(15, $y_firma + 1);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(85, 4, txtPDF('Firma del Estudiante'), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(85, 14, txtPDF('C.I. V-' . $cedula_estudiante), 0, 0, 'C');

            $pdf->Rect(115, $y_firma, 85, 22);
            $pdf->SetXY(115, $y_firma + 1);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(85, 4, txtPDF('Recepción y Validación Control de Estudios'), 0, 1, 'C');
            $pdf->SetFont('Arial', 'I', 7.5);
            $pdf->SetXY(115, $y_firma + 15);
            $pdf->Cell(85, 4, txtPDF('Fecha, Firma y Sello'), 0, 0, 'C');

            ob_end_clean();
            $pdf->Output('I', "Reporte_Reincorporacion_" . $cedula_estudiante . ".pdf");
            exit();
        } else {
            // CONSTANCIA DE REINCORPORACIÓN APROBADA
            $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
            $pdf->documentTitle = "CONSTANCIA DE REINCORPORACIÓN";
            $pdf->SetMargins(25, 25, 25);
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 11);

            $pdf->Write(6, txtPDF("Quien suscribe "));
            $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
            $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
            $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
            $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefa de Control de Estudios de nuestra Institución, hace constar que el (la) Ciudadano (a):"));

            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 7, txtPDF($nombre_estudiante), 0, 1, 'C');
            $pdf->Ln(3);

            $pdf->SetFont('Arial', '', 11);
            $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
            $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
            $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", ha formalizado satisfactoriamente su "));
            $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("REINCORPORACIÓN ACADÉMICA"));
            $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" en el Programa Nacional de Formación en:"));

            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 7, txtPDF($nombre_carrera), 0, 1, 'C');
            $pdf->Ln(6);

            $tray_det = $solicitud_data['materias_parsed']['trayecto_determinado'] ?? null;
            $sec_cod = $solicitud_data['materias_parsed']['codigo_seccion_asignada'] ?? null;
            if ($tray_det !== null || !empty($periodo_sol)) {
                $pdf->SetFont('Arial', 'I', 10);
                $det_aprob = "Para cursar actividades correspondientes al " . txtPDF($periodo_sol);
                if ($tray_det !== null) $det_aprob .= ", Trayecto " . $tray_det;
                if (!empty($sec_cod)) $det_aprob .= " (Sección: " . $sec_cod . ")";
                $det_aprob .= ".";
                $pdf->Cell(0, 6, $det_aprob, 0, 1, 'C');
                $pdf->Ln(6);
            }

            $pdf->renderFechaLegalCentrada();
            $pdf->renderFirmaControlEstudios();

            ob_end_clean();
            $pdf->Output('I', "Constancia_Reincorporacion_" . $cedula_estudiante . ".pdf");
            exit();
        }


    // -------------------------------------------------------------------------------------
    // O. RETIRO DE DOCUMENTOS
    // -------------------------------------------------------------------------------------
    case 'retiro_documento':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CONSTANCIA DE RETIRO DE DOCUMENTO";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Quien suscribe "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefa de Control de Estudios de nuestra Institución, hace constar que el (la) Ciudadano (a):"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", cursante del Programa Nacional de Formación en "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF($nombre_carrera));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", procedió al "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("RETIRO DE DOCUMENTOS DE INSCRIPCIÓN / EXPEDIENTE"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" que reposaban en nuestros archivos institucionales."));

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Retiro_Documentos_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // P. SERVICIO COMUNITARIO
    // -------------------------------------------------------------------------------------
    case 'servicio_comunitario':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "ACTA DE CULMINACIÓN DE SERVICIO COMUNITARIO";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Quienes suscriben, "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Blanca A. Crespo C."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", cédula de identidad N° "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-10.959.330"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", Secretaria del Consejo de Gestión Universitaria de la Universidad Politécnica Territorial de Puerto Cabello, hace constar que el (la) Bachiller:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", estudiante regular del Programa Nacional de Formación en "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF($nombre_carrera));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", cumplió satisfactoriamente con la ejecución de "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("ciento veinte (120) horas"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" de Servicio Comunitario conforme a la Ley de Servicio Comunitario del Estudiante de Educación Superior."));

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaSecretaria();

        ob_end_clean();
        $pdf->Output('I', "Acta_Servicio_Comunitario_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // Q. CARTA DE CULMINACIÓN DE ESTUDIOS
    // -------------------------------------------------------------------------------------
    case 'carta_culminacion':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CARTA DE CULMINACIÓN DE ESTUDIOS";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $titulo_obtenido = ($tipo_reporte === 'ingenieria' || $tipo_reporte === 'licenciatura') ? "Ingeniero / Licenciado" : "Técnico Superior Universitario (T.S.U.)";

        $pdf->Write(6, txtPDF("Quien suscribe "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefa de Control de Estudios de nuestra Institución, hace constar que el (la) Ciudadano (a):"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", ha "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("CULMINADO SATISFACTORIAMENTE"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" la totalidad de las unidades curriculares y requisitos académicos exigidos para optar al título de "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF($titulo_obtenido));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(" en el Programa Nacional de Formación en:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, txtPDF($nombre_carrera), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Carta_Culminacion_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // R. NOTAS CERTIFICADAS (CERTIFICACIÓN DE CALIFICACIONES)
    // -------------------------------------------------------------------------------------
    case 'notas_certificadas':
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CERTIFICACIÓN DE CALIFICACIONES";
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, txtPDF("ESTUDIANTE: " . $nombre_estudiante . " | C.I.: V-" . $cedula_estudiante), 0, 1, 'L');
        $pdf->Cell(0, 6, txtPDF("PNF: " . $nombre_carrera), 0, 1, 'L');
        $pdf->Ln(3);

        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(20, 6, txtPDF('CÓDIGO'), 1, 0, 'C', true);
        $pdf->Cell(85, 6, txtPDF('UNIDAD CURRICULAR'), 1, 0, 'C', true);
        $pdf->Cell(20, 6, txtPDF('TRAYECTO'), 1, 0, 'C', true);
        $pdf->Cell(25, 6, txtPDF('NOTA NUM.'), 1, 0, 'C', true);
        $pdf->Cell(35, 6, txtPDF('CONDICIÓN'), 1, 1, 'C', true);

        // Consulta de notas reales del estudiante
        $query_notas = "SELECT nd.*, m.nombre_materia, m.cod_materia, m.trayecto, m.nota_minima_aprobatoria 
                        FROM notas_definitivas nd
                        INNER JOIN materias m ON nd.id_materia = m.id_materia
                        WHERE nd.id_usuario = ? 
                        ORDER BY m.trayecto ASC, m.nombre_materia ASC";
        $stmt_n = $db->prepare($query_notas);
        $stmt_n->bind_param("i", $id_estudiante);
        $stmt_n->execute();
        $res_notas = $stmt_n->get_result();

        $pdf->SetFont('Arial', '', 8);
        $count = 0;
        $suma_notas = 0;

        while ($nota_row = $res_notas->fetch_assoc()) {
            $count++;
            $nota_val = $nota_row['nota_final'] ?? $nota_row['trayecto_0'] ?? $nota_row['trayecto_1'] ?? $nota_row['trayecto_2'] ?? $nota_row['trayecto_3'] ?? $nota_row['trayecto_4'] ?? 0;
            $nota_float = floatval($nota_val);
            $suma_notas += $nota_float;
            $min_aprob = isset($nota_row['nota_minima_aprobatoria']) ? floatval($nota_row['nota_minima_aprobatoria']) : 12.0;
            $condicion = ($nota_float >= $min_aprob) ? 'APROBADA' : 'REPROBADA';

            $pdf->Cell(20, 6, txtPDF($nota_row['cod_materia'] ?? 'MAT-' . $nota_row['id_materia']), 1, 0, 'C');
            $pdf->Cell(85, 6, txtPDF(substr($nota_row['nombre_materia'], 0, 45)), 1, 0, 'L');
            $pdf->Cell(20, 6, txtPDF('Trayecto ' . $nota_row['trayecto']), 1, 0, 'C');
            $pdf->Cell(25, 6, str_pad(number_format($nota_float, 0), 2, "0", STR_PAD_LEFT), 1, 0, 'C');
            $pdf->Cell(35, 6, txtPDF($condicion), 1, 1, 'C');
        }
        $stmt_n->close();

        if ($count === 0) {
            $pdf->Cell(185, 10, txtPDF('No se registran calificaciones definitivas cargadas en el sistema.'), 1, 1, 'C');
        } else {
            $promedio = round($suma_notas / $count, 2);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(125, 6, txtPDF('PROMEDIO ACADÉMICO ACUMULADO:'), 1, 0, 'R', true);
            $pdf->Cell(60, 6, txtPDF($promedio . " PUNTOS"), 1, 1, 'C', true);
        }

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Notas_Certificadas_" . $cedula_estudiante . ".pdf");
        exit();


    // -------------------------------------------------------------------------------------
    // DEFAULT: CONSTANCIA DE ESTUDIOS
    // -------------------------------------------------------------------------------------
    default:
        $pdf = new ConstanciaInstitucionalPDF('P', 'mm', 'Letter');
        $pdf->documentTitle = "CONSTANCIA INSTITUCIONAL";
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->Write(6, txtPDF("Quien suscribe "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("Dra. Zorangel E. Aponte Q."));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", titular de la cédula de identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V.-7.153.528"));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(". Jefe de Control de Estudio de nuestra Institución, hace constar que el (la) Ciudadano (a):"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, txtPDF($nombre_estudiante), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Write(6, txtPDF("Titular de la Cédula de Identidad "));
        $pdf->SetFont('Arial', 'B', 11); $pdf->Write(6, txtPDF("V-" . $cedula_estudiante));
        $pdf->SetFont('Arial', '', 11); $pdf->Write(6, txtPDF(", es estudiante regular del Programa Nacional de Formación en:"));

        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, txtPDF($nombre_carrera), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->renderFechaLegalCentrada();
        $pdf->renderFirmaControlEstudios();

        ob_end_clean();
        $pdf->Output('I', "Constancia_" . $cedula_estudiante . ".pdf");
        exit();
}
?>
