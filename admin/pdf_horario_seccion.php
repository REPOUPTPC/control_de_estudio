<?php
// PDF Oficial: Horario Académico de Sección (FPDF)
// Dirección de Control de Estudios y Planificación Académica - UPTPC
// Cuadrícula Oficial en Formato de Intervalos de 30 Minutos

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../funciones/functions.php';
require_once __DIR__ . '/../fpdf/fpdf.php';

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
        <link rel="stylesheet" href="../css/bootstrap.min.css">
        <link rel="stylesheet" href="../vendor/fontawesome-free/css/all.min.css">
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
                    Por motivos de seguridad e integridad del sistema, la generación del reporte oficial de horario de sección no acepta solicitudes mediante el método <strong>GET</strong>.
                </p>
                <div class="alert alert-light border small text-left text-secondary mb-4">
                    <i class="fas fa-shield-alt text-primary mr-1"></i> Este documento oficial debe ser solicitado mediante una petición segura <strong>POST</strong> desde el panel administrativo de Control de Estudios.
                </div>
                <div>
                    <a href="gestion_horario_seccion.php" class="btn btn-primary px-4 font-weight-bold">
                        <i class="fas fa-calendar-alt mr-1"></i> Ir a Horarios de Secciones
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Verificar autenticación y permisos
cargarPermisosUsuario();
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

if (!isAdmin() && !isSuperUser() && !isDirectorCarrera()) {
    http_response_code(403);
    die('Acceso denegado. No posee los privilegios necesarios para emitir este documento.');
}

// Obtener ID de la sección vía POST
$seccion_id = isset($_POST['seccion_id']) ? intval($_POST['seccion_id']) : (isset($_POST['id_seccion']) ? intval($_POST['id_seccion']) : 0);
if ($seccion_id <= 0) {
    die('Identificador de sección no válido.');
}

$seccion = obtenerDetalleSeccion($db, $seccion_id);
if (!$seccion) {
    die('La sección académica solicitada no existe en la base de datos.');
}

$horarios_raw = obtenerHorariosSeccion($db, $seccion_id);
$horarios = is_array($horarios_raw) ? $horarios_raw : [];

// Conversión segura de caracteres para FPDF
function txtPDF($texto) {
    if ($texto === null || $texto === '') return '';
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding((string)$texto, 'ISO-8859-1', 'UTF-8');
    } elseif (function_exists('iconv')) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$texto);
    } else {
        return utf8_decode((string)$texto);
    }
}

// Clase extendida de FPDF con soporte para esquinas redondeadas y membrete institucional
class PDF_Horario_Oficial extends FPDF {
    function Header() {
        // Logo UPTPC (Izquierda)
        $logoUptpc = __DIR__ . '/../images/uptpc.png';
        if (file_exists($logoUptpc)) {
            $this->Image($logoUptpc, 14, 8, 16);
        }

        // Logo MPPEU (Derecha)
        $logoMppeu = __DIR__ . '/../images/mppeu_bb.png';
        if (file_exists($logoMppeu)) {
            $this->Image($logoMppeu, 240, 8, 24);
        }

        // Membrete Institucional Centrado
        $this->SetY(8);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(0, 3.8, txtPDF('REPÚBLICA BOLIVARIANA DE VENEZUELA'), 0, 1, 'C');
        $this->SetFont('Arial', '', 7.5);
        $this->Cell(0, 3.5, txtPDF('MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN UNIVERSITARIA'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 3.8, txtPDF('UNIVERSIDAD POLITÉCNICA TERRITORIAL DE PUERTO CABELLO'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor(11, 94, 215);
        $this->Cell(0, 4, txtPDF('DIRECCIÓN DE CONTROL DE ESTUDIOS Y PLANIFICACIÓN ACADÉMICA'), 0, 1, 'C');

        // Línea decorativa institucional bicromática
        $this->Ln(2);
        $this->SetDrawColor(11, 94, 215);
        $this->SetLineWidth(0.8);
        $this->Line(14, $this->GetY(), 265.4, $this->GetY());
        $this->SetDrawColor(253, 126, 20);
        $this->SetLineWidth(0.4);
        $this->Line(14, $this->GetY() + 1.2, 265.4, $this->GetY() + 1.2);
        $this->Ln(3.5);
    }

    function Footer() {
        $this->SetY(-13);
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.3);
        $this->Line(14, $this->GetY(), 265.4, $this->GetY());
        $this->SetY(-10.5);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(130, 4, txtPDF('Reporte Oficial de Horario Académico - UPTPC | Formato de Intervalos de 30 Minutos'), 0, 0, 'L');
        $this->Cell(121.4, 4, txtPDF('Página ' . $this->PageNo() . ' de {nb}'), 0, 1, 'R');
    }

    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F') $op='f';
        elseif($style=='FD' || $style=='DF') $op='B';
        else $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));
        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }
}

// Inicializar documento PDF en orientación Horizontal (Landscape) tamaño Carta (Letter)
$pdf = new PDF_Horario_Oficial('L', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->SetMargins(14, 10, 14);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 14);

// 1. BANNER METADATOS DE LA SECCIÓN
$turno = $seccion['turno'] ?? 'Diurno';
$codigo_sec = $seccion['codigo_seccion'] ?? 'N/A';
$carrera_nombre = $seccion['nombre_carrera'] ?? 'N/A';
$trayecto = 'Trayecto ' . ($seccion['numero_trayecto'] ?? '0');
$periodo = $seccion['nombre_periodo'] ?? 'N/A';
$inscritos = intval($seccion['inscritos'] ?? 0);
$capacidad = intval($seccion['capacidad_maxima'] ?? 0);

$pdf->SetFillColor(241, 245, 249);
$pdf->SetDrawColor(203, 213, 225);
$pdf->SetLineWidth(0.3);
$bannerY = $pdf->GetY();
$bannerW = 251.4;
$bannerH = 14;
$pdf->RoundedRect($pdf->GetX(), $bannerY, $bannerW, $bannerH, 2, 'DF');

// Fila 1 de datos
$pdf->SetXY(16, $bannerY + 1.8);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(18, 4.5, txtPDF('SECCIÓN:'), 0, 0);
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(11, 94, 215);
$pdf->Cell(35, 4.5, txtPDF($codigo_sec), 0, 0);

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(18, 4.5, txtPDF('CARRERA:'), 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(115, 4.5, txtPDF($carrera_nombre), 0, 0);

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(20, 4.5, txtPDF('PERÍODO:'), 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(40, 4.5, txtPDF($periodo), 0, 1);

// Fila 2 de datos
$pdf->SetXY(16, $bannerY + 7.5);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(18, 4.5, txtPDF('TURNO:'), 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(35, 4.5, txtPDF($turno), 0, 0);

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(18, 4.5, txtPDF('TRAYECTO:'), 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(115, 4.5, txtPDF($trayecto), 0, 0);

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(20, 4.5, txtPDF('ESTUDIANTES:'), 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(40, 4.5, txtPDF("$inscritos / $capacidad Asignados"), 0, 1);

$pdf->SetY($bannerY + $bannerH + 2.5);

// 2. GENERAR INTERVALOS EN FORMATO DE 30 MINUTOS
$hora_inicio_base = ($turno === 'Nocturno') ? '17:00' : '07:00';
$hora_fin_base    = ($turno === 'Nocturno') ? '21:30' : '18:00';

// Ajustar dinámicamente si alguna clase asignada se extiende más allá del rango base
foreach ($horarios as $h) {
    if (strtotime($h['hora_inicio']) < strtotime($hora_inicio_base)) {
        $hora_inicio_base = date('H:00', strtotime($h['hora_inicio']));
    }
    if (strtotime($h['hora_fin']) > strtotime($hora_fin_base)) {
        $hora_fin_base = date('H:i', ceil(strtotime($h['hora_fin'])/1800)*1800);
    }
}

// Generar array de slots consecutivos de 30 minutos
$slots_30m = [];
$curr_ts = strtotime($hora_inicio_base);
$end_ts  = strtotime($hora_fin_base);

while ($curr_ts < $end_ts) {
    $next_ts = $curr_ts + 1800; // 30 minutos = 1800 segundos
    $slots_30m[] = [
        'inicio' => date('H:i', $curr_ts),
        'fin'    => date('H:i', $next_ts)
    ];
    $curr_ts = $next_ts;
}

$num_slots = count($slots_30m);
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$col_hora_w = 23.4;
$col_dia_w = (251.4 - $col_hora_w) / 6; // 38.0 mm por día

// Altura de fila por cada slot de 30 minutos (22 slots = 74.8 mm exactos)
$altura_slot = 3.4;

$grid_x = 14;
$grid_y = $pdf->GetY();

$pdf->SetFillColor(15, 23, 42); // Navy Slate institucional
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetDrawColor(203, 213, 225);
$pdf->SetLineWidth(0.3);

$pdf->Cell($col_hora_w, 5.5, txtPDF('HORA (30 min)'), 1, 0, 'C', true);
foreach ($dias_semana as $d) {
    $pdf->Cell($col_dia_w, 5.5, txtPDF(strtoupper($d)), 1, 0, 'C', true);
}
$pdf->Ln();

$y_grid_top = $pdf->GetY();

// Dibujar estructura vacía de la grilla en formato de 30 minutos
$pdf->SetFont('Arial', '', 6);

for ($s = 0; $s < $num_slots; $s++) {
    $slot = $slots_30m[$s];
    
    // Etiqueta de hora de 30 minutos
    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell($col_hora_w, $altura_slot, $slot['inicio'] . ' - ' . $slot['fin'], 1, 0, 'C', true);
    
    // Celdas de días
    for ($d = 0; $d < 6; $d++) {
        $pdf->SetFillColor(($s % 2 == 0) ? 255 : 252, ($s % 2 == 0) ? 255 : 252, ($s % 2 == 0) ? 255 : 252);
        $pdf->Cell($col_dia_w, $altura_slot, '', 1, 0, 'C', true);
    }
    $pdf->Ln();
}

$y_grid_bottom = $pdf->GetY();

// Paleta cromática armoniosa para diferenciar asignaturas
$colores_materias = [
    ['bg' => [224, 242, 254], 'border' => [2, 132, 199], 'bar' => [3, 105, 161]],   // Azul
    ['bg' => [220, 252, 231], 'border' => [22, 163, 74], 'bar' => [21, 128, 61]],   // Verde
    ['bg' => [254, 243, 199], 'border' => [217, 119, 6], 'bar' => [180, 83, 9]],    // Ámbar
    ['bg' => [243, 232, 255], 'border' => [147, 51, 234], 'bar' => [126, 34, 206]], // Púrpura
    ['bg' => [254, 226, 226], 'border' => [220, 38, 38], 'bar' => [185, 28, 28]],   // Rojo suave
    ['bg' => [241, 245, 249], 'border' => [71, 85, 105], 'bar' => [51, 65, 85]]     // Gris azulado
];

$materia_color_map = [];
$color_idx = 0;

// 3. DIBUJAR LOS CAMPOS OCUPADOS CALZANDO EXACTAMENTE EN LOS SLOTS DE 30 MINUTOS
foreach ($horarios as $clase) {
    $mat_id = $clase['id_materia'];
    if (!isset($materia_color_map[$mat_id])) {
        $materia_color_map[$mat_id] = $colores_materias[$color_idx % count($colores_materias)];
        $color_idx++;
    }
    $color = $materia_color_map[$mat_id];

    $dia_idx = intval($clase['dia']);
    if ($dia_idx < 0 || $dia_idx > 5) continue;

    $ini_hora = substr($clase['hora_inicio'], 0, 5);
    $fin_hora = substr($clase['hora_fin'], 0, 5);

    $ini_ts = strtotime($ini_hora);
    $fin_ts = strtotime($fin_hora);
    $base_ts = strtotime($slots_30m[0]['inicio']);

    // Calcular slot inicial y duración en número exacto de slots de 30 minutos
    $slot_offset = ($ini_ts - $base_ts) / 1800.0;
    $num_slots_dur = ($fin_ts - $ini_ts) / 1800.0;

    $block_x = $grid_x + $col_hora_w + ($dia_idx * $col_dia_w);
    $block_y = $y_grid_top + ($slot_offset * $altura_slot);
    $block_w = $col_dia_w;
    $block_h = $num_slots_dur * $altura_slot;

    // Caja de fondo perfectamente alineada a los campos de 30 minutos
    $pdf->SetFillColor($color['bg'][0], $color['bg'][1], $color['bg'][2]);
    $pdf->SetDrawColor($color['border'][0], $color['border'][1], $color['border'][2]);
    $pdf->SetLineWidth(0.35);
    $pdf->Rect($block_x + 0.3, $block_y + 0.3, $block_w - 0.6, $block_h - 0.6, 'DF');

    // Barra lateral de color identificador
    $pdf->SetFillColor($color['bar'][0], $color['bar'][1], $color['bar'][2]);
    $pdf->Rect($block_x + 0.3, $block_y + 0.3, 2.2, $block_h - 0.6, 'F');

    // Textos internos ajustados dinámicamente según la cantidad de slots de 30 min
    $text_x = $block_x + 3.0;
    $text_w = $block_w - 4.0;
    $current_y = $block_y + 0.6;

    $pdf->SetXY($text_x, $current_y);
    $pdf->SetTextColor($color['bar'][0], $color['bar'][1], $color['bar'][2]);

    if ($num_slots_dur <= 2) {
        // 1 hora (2 slots de 30 min): Asignatura compacta y Aula/Horario
        $pdf->SetFont('Arial', 'B', 6);
        $mat_nombre = $clase['nombre_materia'];
        if (strlen($mat_nombre) > 28) $mat_nombre = substr($mat_nombre, 0, 26) . '..';
        $pdf->Cell($text_w, 2.7, txtPDF($mat_nombre), 0, 1, 'L');
        
        $pdf->SetXY($text_x, $pdf->GetY());
        $pdf->SetFont('Arial', '', 5.5);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell($text_w, 2.5, txtPDF($ini_hora . '-' . $fin_hora . ' | Aula: ' . $clase['aula']), 0, 1, 'L');
    } elseif ($num_slots_dur <= 3) {
        // 1.5 horas (3 slots de 30 min): Asignatura (hasta 2 líneas) y Aula/Horario
        $pdf->SetFont('Arial', 'B', 6.2);
        $pdf->MultiCell($text_w, 2.7, txtPDF($clase['nombre_materia']), 0, 'L');
        
        $pdf->SetXY($text_x, $pdf->GetY() + 0.2);
        $pdf->SetFont('Arial', 'B', 5.6);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell($text_w, 2.5, txtPDF($ini_hora . '-' . $fin_hora . ' | Aula: ' . $clase['aula']), 0, 1, 'L');
    } else {
        // 2 horas o más (4+ slots de 30 min): Asignatura, Docente y Aula/Horario
        $pdf->SetFont('Arial', 'B', 6.5);
        $pdf->MultiCell($text_w, 2.7, txtPDF($clase['nombre_materia']), 0, 'L');

        $pdf->SetXY($text_x, $pdf->GetY() + 0.2);
        $pdf->SetFont('Arial', '', 5.8);
        $pdf->SetTextColor(51, 65, 85);
        $docente_corto = $clase['nombre_docente'];
        if (strlen($docente_corto) > 28) $docente_corto = substr($docente_corto, 0, 26) . '..';
        $pdf->Cell($text_w, 2.5, txtPDF($docente_corto), 0, 1, 'L');

        $pdf->SetXY($text_x, $pdf->GetY() + 0.2);
        $pdf->SetFont('Arial', 'B', 5.6);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell($text_w, 2.5, txtPDF($ini_hora . '-' . $fin_hora . ' | Aula: ' . $clase['aula']), 0, 1, 'L');
    }
}

// 4. TABLA RESUMEN DE ASIGNATURAS Y DOCENTES
$pdf->SetY($y_grid_bottom + 3.0);

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 4, txtPDF('DETALLE Y CARGA HORARIA DE ASIGNATURAS PROGRAMADAS'), 0, 1, 'L');

// Cabecera de la tabla resumen
$pdf->SetFillColor(241, 245, 249);
$pdf->SetDrawColor(203, 213, 225);
$pdf->SetFont('Arial', 'B', 6.5);
$pdf->SetTextColor(71, 85, 105);

$col_r_idx = 8;
$col_r_cod = 22;
$col_r_mat = 75;
$col_r_doc = 65;
$col_r_dia = 25;
$col_r_hor = 32;
$col_r_aul = 24.4;

$pdf->Cell($col_r_idx, 4.5, '#', 1, 0, 'C', true);
$pdf->Cell($col_r_cod, 4.5, txtPDF('CÓDIGO'), 1, 0, 'C', true);
$pdf->Cell($col_r_mat, 4.5, txtPDF('ASIGNATURA'), 1, 0, 'L', true);
$pdf->Cell($col_r_doc, 4.5, txtPDF('DOCENTE ASIGNADO'), 1, 0, 'L', true);
$pdf->Cell($col_r_dia, 4.5, txtPDF('DÍA'), 1, 0, 'C', true);
$pdf->Cell($col_r_hor, 4.5, txtPDF('HORARIO'), 1, 0, 'C', true);
$pdf->Cell($col_r_aul, 4.5, txtPDF('AULA'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 6.5);
$pdf->SetTextColor(30, 41, 59);

$n = 0;
foreach ($horarios as $h) {
    $n++;
    $fill = ($n % 2 == 0);
    $pdf->SetFillColor(250, 250, 250);

    $pdf->Cell($col_r_idx, 4.0, $n, 1, 0, 'C', $fill);
    $pdf->Cell($col_r_cod, 4.0, txtPDF($h['cod_materia']), 1, 0, 'C', $fill);
    $pdf->Cell($col_r_mat, 4.0, txtPDF(substr($h['nombre_materia'], 0, 45)), 1, 0, 'L', $fill);
    $pdf->Cell($col_r_doc, 4.0, txtPDF(substr($h['nombre_docente'], 0, 38)), 1, 0, 'L', $fill);
    $dia_nombre = $dias_semana[intval($h['dia'])] ?? 'N/A';
    $pdf->Cell($col_r_dia, 4.0, txtPDF($dia_nombre), 1, 0, 'C', $fill);
    $pdf->Cell($col_r_hor, 4.0, substr($h['hora_inicio'], 0, 5) . ' - ' . substr($h['hora_fin'], 0, 5), 1, 0, 'C', $fill);
    $pdf->Cell($col_r_aul, 4.0, txtPDF($h['aula']), 1, 1, 'C', $fill);
}

// 5. BLOQUE DE FIRMAS INSTITUCIONALES
if ($pdf->GetY() > 180) {
    $pdf->AddPage();
}

$pdf->SetY($pdf->GetY() + 2);
$firmaY = $pdf->GetY();

$pdf->SetFont('Arial', 'B', 7);
$pdf->SetTextColor(100, 116, 139);

// Firma 1: Dirección de Control de Estudios
$pdf->Line(40, $firmaY + 9, 115, $firmaY + 9);
$pdf->SetXY(40, $firmaY + 10);
$pdf->Cell(75, 3.2, txtPDF('DIRECCIÓN DE CONTROL DE ESTUDIOS'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 6);
$pdf->SetX(40);
$pdf->Cell(75, 2.8, txtPDF('Firma y Sello Oficial'), 0, 0, 'C');

// Firma 2: Coordinación / Dirección de Carrera
$pdf->SetFont('Arial', 'B', 7);
$pdf->Line(150, $firmaY + 9, 225, $firmaY + 9);
$pdf->SetXY(150, $firmaY + 10);
$pdf->Cell(75, 3.2, txtPDF('COORDINACIÓN / DIRECCIÓN DE CARRERA'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 6);
$pdf->SetX(150);
$pdf->Cell(75, 2.8, txtPDF('Firma y Sello de Conformidad'), 0, 1, 'C');

// Salida del documento PDF en navegador
$codigo_sanitizado = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $seccion['codigo_seccion'] ?? 'seccion');
$nombre_archivo = 'Horario_Oficial_Seccion_' . $codigo_sanitizado . '.pdf';

$pdf->Output('I', $nombre_archivo);
exit();
