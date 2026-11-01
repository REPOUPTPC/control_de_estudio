<?php
// Iniciar buffer de salida para evitar que salidas previas corrompan el PDF
ob_start();

require_once __DIR__ . '/../funciones/functions.php';
require_once __DIR__ . '/../fpdf/fpdf.php';

// Validar inicio de sesión
if (!isLoggedIn()) {
    ob_end_clean();
    die("Acceso restringido. Debe iniciar sesión en el sistema.");
}

// Validar rol de administrador o permiso de mensajería/secretaría
if (!isAdmin() && !tienePermiso('mensajeria') && !tienePermiso('secretaria')) {
    ob_end_clean();
    die("Acceso denegado. Se requieren permisos de Control de Estudios.");
}

if (!isset($_GET['id']) || intval($_GET['id']) <= 0) {
    ob_end_clean();
    die("Error: Parámetro de amonestación no válido o ausente.");
}

$id_amonestacion = intval($_GET['id']);
$amonestacion = obtenerAmonestacionPorId($id_amonestacion);

if (!$amonestacion) {
    ob_end_clean();
    die("Error: Registro de amonestación no encontrado.");
}

// Marcar en base de datos que se generó el acta
marcarAmonestacionGenerada($id_amonestacion);

// Función auxiliar de codificación para FPDF (Latin1 / ISO-8859-1)
if (!function_exists('txtPDF')) {
    function txtPDF($texto) {
        if ($texto === null || $texto === '') return '';
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$texto);
            if ($c !== false) return $c;
        }
        if (function_exists('mb_convert_encoding')) return mb_convert_encoding((string)$texto, 'ISO-8859-1', 'UTF-8');
        if (function_exists('utf8_decode')) return utf8_decode((string)$texto);
        return (string)$texto;
    }
}

class PDF_Amonestacion extends FPDF {
    function Header() {
        // Logo UPTPC (Izquierda)
        $logoUptpc = __DIR__ . '/../images/uptpc.png';
        if (file_exists($logoUptpc)) {
            $this->Image($logoUptpc, 15, 10, 22);
        }

        // Logo MPPEU (Derecha)
        $logoMppeu = __DIR__ . '/../images/mppeu_bb.png';
        if (file_exists($logoMppeu)) {
            $this->Image($logoMppeu, 175, 10, 24);
        }

        $this->SetY(10);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 3.8, txtPDF('REPÚBLICA BOLIVARIANA DE VENEZUELA'), 0, 1, 'C');
        $this->Cell(0, 3.8, txtPDF('MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN UNIVERSITARIA'), 0, 1, 'C');
        $this->SetFont('Arial', '', 7.5);
        $this->Cell(0, 3.5, txtPDF('UNIVERSIDAD POLITÉCNICA TERRITORIAL DE PUERTO CABELLO'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 3.8, txtPDF('DIRECCIÓN DE CONTROL DE ESTUDIOS'), 0, 1, 'C');
        
        $this->Ln(7);
        $this->SetDrawColor(200, 35, 51);
        $this->SetLineWidth(0.8);
        $this->Line(15, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
    }

    function Footer() {
        $this->SetY(-18);
        $this->SetDrawColor(180, 180, 180);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 200, $this->GetY());
        $this->SetY(-14);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(95, 4, txtPDF('Sistema Automatizado de Control de Estudios - UPTPC'), 0, 0, 'L');
        $this->Cell(90, 4, txtPDF('Página ' . $this->PageNo() . ' de {nb}'), 0, 1, 'R');
        $this->Cell(0, 3, txtPDF('Documento Administrativo Oficial - Validez institucional sujeta a firmas y sellos reglamentarios'), 0, 0, 'C');
    }
}

// Crear PDF tamaño Carta
$pdf = new PDF_Amonestacion('P', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

// Número correlativo de acta
$añoActual = date('Y', strtotime($amonestacion['created_at']));
$numActa = sprintf("ACTA-AMON-%s-%04d", $añoActual, $amonestacion['id']);

$pdf->SetY(36);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(180, 30, 40);
$pdf->Cell(0, 6, txtPDF('ACTA DE AMONESTACIÓN Y LLAMADO DE ATENCIÓN'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(0, 4, txtPDF('INCUMPLIMIENTO DEL CRONOGRAMA OFICIAL DE CARGA DE CALIFICACIONES'), 0, 1, 'C');

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(120, 5, txtPDF('REF: ' . $numActa), 0, 0, 'L');
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(65, 5, txtPDF('Puerto Cabello, ' . date('d/m/Y h:i A')), 0, 1, 'R');

$pdf->Ln(3);

// CUADRO RESUMEN DE DATOS
$pdf->SetFillColor(245, 246, 248);
$pdf->SetDrawColor(200, 205, 210);
$pdf->SetLineWidth(0.3);

// Fila 1: Docente y Cédula
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 6, txtPDF(' DOCENTE:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(90, 6, txtPDF($amonestacion['docente_nombre']), 1, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 6, txtPDF(' CÉDULA:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(38, 6, txtPDF($amonestacion['docente_cedula']), 1, 1, 'L');

// Fila 2: Correo y Teléfono
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 6, txtPDF(' CORREO:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(90, 6, txtPDF($amonestacion['docente_email'] ?: 'No registrado'), 1, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 6, txtPDF(' TELÉFONO:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(38, 6, txtPDF($amonestacion['docente_telefono'] ?: 'No registrado'), 1, 1, 'L');

// Fila 3: Carrera / PNF
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 6, txtPDF(' CARRERA / PNF:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$carreraTexto = !empty($amonestacion['nombre_carrera']) ? $amonestacion['nombre_carrera'] : (!empty($amonestacion['carrera_nombre']) ? $amonestacion['carrera_nombre'] : 'PNF General');
$pdf->Cell(153, 6, txtPDF($carreraTexto), 1, 1, 'L');

// Fila 4: Asignatura y Código
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 6, txtPDF(' ASIGNATURA:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(90, 6, txtPDF($amonestacion['nombre_materia']), 1, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 6, txtPDF(' CÓDIGO MAT.:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(38, 6, txtPDF($amonestacion['cod_materia'] ?: 'S/C'), 1, 1, 'L');

// Fila 5: Sección, Turno, Período y Trimestre
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 6, txtPDF(' SECCIÓN:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(35, 6, txtPDF($amonestacion['codigo_seccion'] . ' (' . ($amonestacion['turno'] ?: 'Diurno') . ')'), 1, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(28, 6, txtPDF(' PERÍODO:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(35, 6, txtPDF($amonestacion['nombre_periodo']), 1, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 6, txtPDF(' TRIMESTRE:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(30, 6, txtPDF('Trimestre ' . $amonestacion['trimestre_num']), 1, 1, 'C');

// Fila 6: Fecha Límite Vencida y Estado
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 6, txtPDF(' FECHA LÍMITE:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(200, 35, 51);
$pdf->Cell(55, 6, txtPDF(date('d/m/Y', strtotime($amonestacion['fecha_limite'])) . ' (VENCIDA)'), 1, 0, 'L');
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(40, 6, txtPDF(' ESTADO ALERTA:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8.5);
$estadoTxt = 'PENDIENTE DE PRESENTACIÓN';
if ($amonestacion['estado'] === 'habilitado_24h') $estadoTxt = 'PRÓRROGA 24H ACTIVA';
elseif ($amonestacion['estado'] === 'regularizado') $estadoTxt = 'REGULARIZADO';
$pdf->Cell(58, 6, txtPDF($estadoTxt), 1, 1, 'L');

$pdf->Ln(5);

// CUERPO MOTIVATORIO DEL ACTA
$pdf->SetFont('Arial', '', 8.5);
$pdf->MultiCell(0, 4.5, txtPDF(
    "Por medio de la presente acta administrativa, la Dirección de Control de Estudios de la Universidad Politécnica Territorial de Puerto Cabello (UPTPC), en ejercicio de las atribuciones conferidas por la Ley de Universidades, el Reglamento General de la Institución y el Reglamento de Evaluación del Desempeño Estudiantil, deja formal constancia de lo siguiente:"
), 0, 'J');

$pdf->Ln(2);

$pdf->MultiCell(0, 4.5, txtPDF(
    "1. Que el docente plenamente identificado en el encabezado de este documento tenía bajo su responsabilidad académica la unidad curricular y sección señaladas durante el respectivo período académico ordinario."
), 0, 'J');

$pdf->Ln(1.5);

$pdf->MultiCell(0, 4.5, txtPDF(
    "2. Que el cronograma institucional aprobado para la carga, consignación y cierre de calificaciones del Trimestre " . $amonestacion['trimestre_num'] . " contempló como fecha límite improrrogable el día " . date('d/m/Y', strtotime($amonestacion['fecha_limite'])) . ", fecha tras la cual el sistema automatizado de calificaciones procedió al bloqueo preventivo de la carga ordinaria."
), 0, 'J');

$pdf->Ln(1.5);

$pdf->MultiCell(0, 4.5, txtPDF(
    "3. Que habiéndose cumplido el lapso reglamentario, se evidenció la omisión en el registro definitivo de calificaciones de los estudiantes inscritos en la referida sección, lesionando el derecho oportuno de los alumnos a conocer su rendimiento académico y afectando los procesos de prosecución e inscripción subsiguientes."
), 0, 'J');

$pdf->Ln(1.5);

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->MultiCell(0, 4.5, txtPDF(
    "EN CONSECUENCIA, SE EMITE FORMAL LLAMADO DE ATENCIÓN Y AMONESTACIÓN ESCRITA al docente por el incumplimiento de los plazos establecidos para la entrega de notas, instándolo a dar estricto acatamiento a los deberes inherentes a su función profesoral."
), 0, 'J');

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 8.5);

// CLÁUSULA DE COMPARECENCIA Y CONCESIÓN DE 24 HORAS
$pdf->SetFillColor(255, 248, 230);
$pdf->SetDrawColor(255, 193, 7);
$pdf->SetLineWidth(0.5);

$habHastaTexto = !empty($amonestacion['habilitado_hasta']) 
    ? date('d/m/Y h:i A', strtotime($amonestacion['habilitado_hasta']))
    : 'veinticuatro (24) horas continuas a partir del momento de formalizar la presente acta';

$clausulaProrroga = "TÉRMINOS DE LA HABILITACIÓN EXTRAORDINARIA (24 HORAS):\n"
                  . "Previa comparecencia personal del docente ante las dependencias de Control de Estudios para justificar el retardo incurrido, la Dirección autoriza una ventana extraordinaria y perentoria de VEINTICUATRO (24) HORAS CONTINUAS (con vencimiento fijado para: " . $habHastaTexto . "), tiempo en el cual el docente deberá subir, verificar y consolidar las notas en el sistema institucional. Transcurrido dicho plazo sin que se consolide la carga, el expediente será remitido a la Dirección Académica y Consultoría Jurídica para las sanciones administrativas a que hubiere lugar.";

$pdf->MultiCell(0, 4.3, txtPDF($clausulaProrroga), 1, 'J', true);

$pdf->Ln(3);

// COMPROMISO DEL DOCENTE
$pdf->SetFont('Arial', 'I', 8);
$pdf->MultiCell(0, 4.2, txtPDF(
    "El (la) docente declara haber sido notificado(a) del presente acto, asume el compromiso formal de regularizar las calificaciones en el lapso concedido y acepta que el presente documento formará parte de su expediente profesoral ante la institución."
), 0, 'J');

$pdf->Ln(10);

// SECCIÓN DE FIRMAS Y SELLOS (3 BLOQUES)
$yFirmas = $pdf->GetY();
if ($yFirmas > 220) {
    $pdf->AddPage();
    $yFirmas = 30;
}

$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.4);

// Bloque 1: Docente
$pdf->Line(18, $yFirmas + 15, 68, $yFirmas + 15);
$pdf->SetXY(18, $yFirmas + 16);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->Cell(50, 3.5, txtPDF('DOCENTE AMONESTADO'), 0, 1, 'C');
$pdf->SetX(18);
$pdf->SetFont('Arial', '', 7);
$pdf->Cell(50, 3, txtPDF('Firma y Cédula de Identidad'), 0, 1, 'C');
$pdf->SetX(18);
$pdf->Cell(50, 3, txtPDF('Fecha: _____/_____/_________'), 0, 1, 'C');

// Bloque 2: Control de Estudios
$pdf->Line(80, $yFirmas + 15, 135, $yFirmas + 15);
$pdf->SetXY(80, $yFirmas + 16);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->Cell(55, 3.5, txtPDF('DIRECCIÓN CONTROL DE ESTUDIOS'), 0, 1, 'C');
$pdf->SetX(80);
$pdf->SetFont('Arial', '', 7);
$pdf->Cell(55, 3, txtPDF('Firma Autorizada y Sello'), 0, 1, 'C');
$pdf->SetX(80);
$pdf->Cell(55, 3, txtPDF('Funcionario: ' . ($amonestacion['habilitado_por_nombre'] ?: 'Control de Estudios')), 0, 1, 'C');

// Bloque 3: Coordinación de Carrera / PNF
$pdf->Line(147, $yFirmas + 15, 197, $yFirmas + 15);
$pdf->SetXY(147, $yFirmas + 16);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->Cell(50, 3.5, txtPDF('COORDINACIÓN DE CARRERA / PNF'), 0, 1, 'C');
$pdf->SetX(147);
$pdf->SetFont('Arial', '', 7);
$pdf->Cell(50, 3, txtPDF('Firma y Sello'), 0, 1, 'C');
$pdf->SetX(147);
$pdf->Cell(50, 3, txtPDF('Fecha: _____/_____/_________'), 0, 1, 'C');

// Salida del PDF
ob_end_clean();
$pdf->Output('I', 'Amonestacion_' . $numActa . '.pdf');
exit();
