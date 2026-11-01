<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../funciones/functions.php';
require_once __DIR__ . '/../fpdf/fpdf.php';

if (!isset($_GET['id'])) die("ID no proporcionado.");
$id_preinscripcion = intval($_GET['id']);

$preinscripcion = obtenerPreinscripcionPorId($id_preinscripcion);
if (!$preinscripcion) die('No encontrada.');

// ========== CONVERTIR UBICACIÓN ==========
$nombresUbicacion = obtenerNombresUbicacion(
    $preinscripcion['estado'] ?? null,
    $preinscripcion['municipio'] ?? null,
    $preinscripcion['parroquia'] ?? null
);

$preinscripcion['estado_nombre'] = $nombresUbicacion['estado_nombre'] ?: $preinscripcion['estado'];
$preinscripcion['municipio_nombre'] = $nombresUbicacion['municipio_nombre'] ?: $preinscripcion['municipio'];
$preinscripcion['parroquia_nombre'] = $nombresUbicacion['parroquia_nombre'] ?: $preinscripcion['parroquia'];

$carreras = obtenerTodasLasCarreras();
$carreraMap = [];
foreach ($carreras as $carrera) $carreraMap[$carrera['id']] = $carrera['nombre'];

$titulos = !empty($preinscripcion['titulos']) ? explode('|||', $preinscripcion['titulos']) : [];
$institutos = !empty($preinscripcion['institutos']) ? explode('|||', $preinscripcion['institutos']) : [];

if (!function_exists('txt')) {
    function txt($texto) {
        if (function_exists('formatearTextoPDF')) return formatearTextoPDF($texto);
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

function convertirImagenAJPG($rutaOrigen, $extension) {
    $img = null;
    if ($extension == 'png') $img = @imagecreatefrompng($rutaOrigen);
    elseif ($extension == 'webp') $img = @imagecreatefromwebp($rutaOrigen);
    elseif ($extension == 'jpg' || $extension == 'jpeg') $img = @imagecreatefromjpeg($rutaOrigen);
    if ($img !== false) {
        $imagenTemp = tempnam(sys_get_temp_dir(), 'fpdf_') . '.jpg';
        imagejpeg($img, $imagenTemp, 75);
        imagedestroy($img);
        return $imagenTemp;
    }
    return false;
}

class PDF_Planilla extends FPDF {
    function Header() {
        if(file_exists('../images/uptpc.png')) $this->Image('../images/uptpc.png', 15, 8, 20);
        $this->SetY(8);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 4, txt('REPÚBLICA BOLIVARIANA DE VENEZUELA'), 0, 1, 'C');
        $this->Cell(0, 4, txt('MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN UNIVERSITARIA'), 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 3.5, txt('UNIVERSIDAD POLITÉCNICA TERRITORIAL DE PUERTO CABELLO'), 0, 1, 'C');
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, txt('PLANILLA DE PREINSCRIPCIÓN'), 0, 1, 'C');
        $this->Ln(5);
    }

    function Seccion($titulo) {
        $this->SetFillColor(240, 240, 240);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(185, 10, "  " . txt(strtoupper($titulo)), 1, 1, 'L', true);
    }

    function Dato($label, $valor, $ancho) {
        $this->SetFont('Arial', 'B', 8.5);
        $labelTxt = txt($label . ': ');
        $w = $this->GetStringWidth($labelTxt);
        $this->Cell($w, 10, $labelTxt, 'B', 0); 
        $this->SetFont('Arial', '', 9);
        $this->Cell($ancho - $w, 10, txt($valor ?: 'N/A'), 'B', 0);
    }
}

$pdf = new PDF_Planilla('P', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

// --- FOTO DE PERFIL ---
if (!empty($preinscripcion['foto_perfil'])) {
    $rutaFoto = '../foto_perfil/' . $preinscripcion['foto_perfil'];
    $ext = strtolower(pathinfo($rutaFoto, PATHINFO_EXTENSION));
    if (file_exists($rutaFoto)) {
        $imgTmp = convertirImagenAJPG($rutaFoto, $ext);
        if ($imgTmp) {
            $pdf->Image($imgTmp, 172, 8, 28, 35);
            $pdf->Rect(172, 8, 28, 35);
            unlink($imgTmp);
        }
    }
}

// --- 1. DATOS PERSONALES ---
$pdf->Seccion('1. DATOS PERSONALES');
$y_ini = $pdf->GetY();
$pdf->SetX(15);
$pdf->Dato('Cédula', $preinscripcion['idusuario'], 50);
$pdf->Dato('Fec. Nacimiento', $preinscripcion['fecha_nac'], 70);
$pdf->Dato('Género', $preinscripcion['genero'], 65);
$pdf->Ln(10); $pdf->SetX(15);
$pdf->Dato('Nombre Completo', $preinscripcion['nombre'], 120);
$pdf->Dato('Estado Civil', $preinscripcion['edo_civil'], 65);
$pdf->Ln(10); $pdf->SetX(15);
$pdf->Dato('Etnia', $preinscripcion['etnia'], 60);
$emb = (strtolower($preinscripcion['genero'] ?? '') == 'femenino') ? (($preinscripcion['embarazada'] ?? 0) == 1 ? 'Sí' : 'No') : 'N/A';
$pdf->Dato('Embarazada', $emb, 50);
$pdf->Dato('Email', $preinscripcion['email'], 75);
$pdf->Ln(10); $pdf->SetX(15);
$pdf->Dato('Tlf. Fijo', $preinscripcion['tlf'], 60);
$pdf->Dato('Tlf. Celular', $preinscripcion['cel'], 65);
$pdf->Dato('Tlf. Opcional', $preinscripcion['num_telf_opc'], 60);
$pdf->Ln(2); 
$pdf->Rect(15, $y_ini, 185, $pdf->GetY() - $y_ini);

// --- 2. UBICACIÓN Y VIVIENDA ---
$pdf->Ln(5);
$pdf->Seccion('2. UBICACIÓN Y VIVIENDA');
$y_ini = $pdf->GetY();
$pdf->SetX(15);
$pdf->Dato('Estado', $preinscripcion['estado_nombre'], 60);
$pdf->Dato('Municipio', $preinscripcion['municipio_nombre'], 65);
$pdf->Dato('Parroquia', $preinscripcion['parroquia_nombre'], 60);
$pdf->Ln(10); $pdf->SetX(15);
$pdf->Dato('Comuna', $preinscripcion['comuna'], 95);
$pdf->Dato('Tipo Vivienda', $preinscripcion['tipo_vivienda'], 45);
$pdf->Dato('Tenencia', $preinscripcion['tenencia_vivienda'], 45);
$pdf->Ln(10); $pdf->SetX(15);
$pdf->Dato('Punto Referencia', $preinscripcion['punto_referencia'], 185);
$pdf->Ln(10); $pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 8.5); $pdf->Cell(20, 10, txt('Dirección: '), 'B', 0);
$pdf->SetFont('Arial', '', 9); $pdf->MultiCell(165, 10, txt($preinscripcion['direccion']), 'B');
$pdf->Rect(15, $y_ini, 185, $pdf->GetY() - $y_ini);

// --- 3. FORMACIÓN Y SALUD ---
$pdf->Ln(5);
$pdf->Seccion('3. FORMACIÓN Y SALUD');
$y_ini = $pdf->GetY();
$pdf->SetX(15);
$pdf->Dato('Carrera', $carreraMap[$preinscripcion['carrera']], 115);
$pdf->Dato('Turno', $preinscripcion['turno'], 35);
$pdf->Dato('Sede', $preinscripcion['sede'], 35);
$pdf->Ln(10); $pdf->SetX(15);
$asigOpsuTxt = (!empty($preinscripcion['asignado_opsu']) && $preinscripcion['asignado_opsu'] === 'Sí') ? 'Sí' : 'No';
$pdf->Dato('Asignado OPSU', $asigOpsuTxt, 45);
$pdf->Dato('Lapso OPSU', ($asigOpsuTxt === 'Sí' ? ($preinscripcion['lapso_opsu'] ?: 'N/A') : 'N/A'), 65);
$pdf->Dato('Discapacidad', $preinscripcion['discapacidad'], 75);
$pdf->Ln(10); $pdf->SetX(15);
$pdf->Dato('Enfermedad', $preinscripcion['enfermedad'], 92);
$pdf->Dato('País Título', $preinscripcion['pais_titulo'], 93);
$leg = ($preinscripcion['legalizado_titulo']==1)?'Sí':'No';
$pdf->Dato('Legalizado en Vzla', $leg, 90);
$pdf->Ln(10); $pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 8.5); $pdf->Cell(15, 10, txt('Títulos: '), 'B', 0);
$pdf->SetFont('Arial', '', 9);
$listT = []; foreach($titulos as $i => $t) if(!empty($t)) $listT[] = $t." (".($institutos[$i]??'').")";
$pdf->MultiCell(170, 10, txt(implode(' / ', $listT)), 'B');
$pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 8.5); $pdf->Cell(30, 10, txt('Potencialidades: '), 'B', 0);
$pdf->SetFont('Arial', '', 9); $pdf->MultiCell(155, 10, txt($preinscripcion['potencialidades']), 'B');
$pdf->Rect(15, $y_ini, 185, $pdf->GetY() - $y_ini);

// --- FIRMAS ---
$pdf->Ln(20);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(92, 5, '__________________________', 0, 0, 'C');
$pdf->Cell(92, 5, '__________________________', 0, 1, 'C');
$pdf->Cell(92, 5, txt('Firma del Aspirante'), 0, 0, 'C');
$pdf->Cell(92, 5, txt('Sello y Firma Autorizada'), 0, 1, 'C');

ob_end_clean();
$pdf->Output('I', 'Planilla_Preinscripcion_Final.pdf');
?>