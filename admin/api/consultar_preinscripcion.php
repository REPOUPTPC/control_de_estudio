<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../funciones/functions.php';

$tipo = trim($_GET['tipo_cedula'] ?? ($_POST['tipo_cedula'] ?? ''));
$numero = preg_replace('/[^0-9]/', '', $_GET['numero_cedula'] ?? ($_POST['numero_cedula'] ?? ($_GET['cedula'] ?? ($_POST['cedula'] ?? ''))));

if (empty($numero)) {
    echo json_encode(['success' => false, 'message' => 'Cédula no proporcionada']);
    exit;
}

// Determinar variantes de cédula (con o sin letra, con o sin guion)
$letras = !empty($tipo) ? [$tipo] : ['V', 'E', 'P'];
$candidatos = [];
foreach ($letras as $l) {
    $candidatos[] = $l . $numero;
    $candidatos[] = $l . '-' . $numero;
}
$candidatos[] = $numero;
$candidatos = array_unique($candidatos);

// 1. Verificar si ya es estudiante formalmente registrado/inscrito en users
$candidatosUsers = array_merge($candidatos, $candidatos);
$inSqlUsers = implode(',', array_fill(0, count($candidatos), '?'));
$typesUsers = str_repeat('s', count($candidatosUsers));

$sqlUser = "SELECT id, idusuario, nombre, username, user_type, estudiante FROM users WHERE idusuario IN ($inSqlUsers) OR username IN ($inSqlUsers) LIMIT 1";
$stmtU = $db->prepare($sqlUser);
if ($stmtU) {
    $stmtU->bind_param($typesUsers, ...$candidatosUsers);
    $stmtU->execute();
    $resU = $stmtU->get_result();
    $userRow = $resU->fetch_assoc();
    $stmtU->close();

    if ($userRow) {
        echo json_encode([
            'success' => false,
            'inscrito' => true,
            'message' => 'Esta cédula ya se encuentra formalmente inscrita como estudiante en el sistema. Si ya es alumno regular, inicie sesión con sus credenciales.'
        ]);
        exit;
    }
}

// 2. Buscar si existe en preinscripcion (preinscrito previamente pero nunca inscrito)
$inSqlPre = implode(',', array_fill(0, count($candidatos), '?'));
$typesPre = str_repeat('s', count($candidatos));

$sqlPre = "SELECT * FROM preinscripcion WHERE idusuario IN ($inSqlPre) ORDER BY id DESC LIMIT 1";
$stmtP = $db->prepare($sqlPre);
if ($stmtP) {
    $stmtP->bind_param($typesPre, ...$candidatos);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    $pre = $resP->fetch_assoc();
    $stmtP->close();

    if ($pre) {
        $cedulaLimpia = preg_replace('/[^0-9]/', '', $pre['idusuario']);
        $tipoLetra = strtoupper(substr($pre['idusuario'], 0, 1));
        if (!in_array($tipoLetra, ['V', 'E', 'P'])) {
            $tipoLetra = 'V';
        }

        echo json_encode([
            'success' => true,
            'encontrado' => true,
            'inscrito' => false,
            'datos' => [
                'id' => (int)$pre['id'],
                'idusuario' => $pre['idusuario'],
                'tipo_cedula' => $tipoLetra,
                'numero_cedula' => $cedulaLimpia,
                'nombre' => $pre['nombre'] ?? '',
                'fecha_nac' => $pre['fecha_nac'] ?? '',
                'genero' => $pre['genero'] ?? '',
                'embarazada' => $pre['embarazada'] ?? '0',
                'edo_civil' => $pre['edo_civil'] ?? '',
                'email' => $pre['email'] ?? '',
                'tlf' => $pre['tlf'] ?? '',
                'cel' => $pre['cel'] ?? '',
                'num_telf_opc' => $pre['num_telf_opc'] ?? '',
                'estado' => $pre['estado'] ?? '',
                'municipio' => $pre['municipio'] ?? '',
                'parroquia' => $pre['parroquia'] ?? '',
                'ciudad' => $pre['ciudad'] ?? '',
                'direccion' => $pre['direccion'] ?? '',
                'comuna' => $pre['comuna'] ?? '',
                'casaapto' => $pre['casaapto'] ?? '',
                'punto_referencia' => $pre['punto_referencia'] ?? '',
                'etnia' => $pre['etnia'] ?? '',
                'grupo_familiar' => $pre['grupo_familiar'] ?? '',
                'acargo_usted' => $pre['acargo_usted'] ?? '',
                'fuente_ingresos' => $pre['fuente_ingresos'] ?? '',
                'tipo_vivienda' => $pre['tipo_vivienda'] ?? '',
                'tenencia_vivienda' => $pre['tenencia_vivienda'] ?? '',
                'enfermedad' => $pre['enfermedad'] ?? '',
                'discapacidad' => $pre['discapacidad'] ?? '',
                'carrera' => $pre['carrera'] ?? '',
                'turno' => $pre['turno'] ?? '',
                'sede' => $pre['sede'] ?? '',
                'asignado_opsu' => $pre['asignado_opsu'] ?? 'No',
                'lapso_opsu' => $pre['lapso_opsu'] ?? '',
                'titulos' => !empty($pre['titulos']) ? explode('|||', $pre['titulos']) : [],
                'institutos' => !empty($pre['institutos']) ? explode('|||', $pre['institutos']) : [],
                'pais_titulo' => !empty($pre['pais_titulo']) ? explode('|||', $pre['pais_titulo']) : [],
                'legalizado_titulo' => !empty($pre['legalizado_titulo']) ? explode('|||', $pre['legalizado_titulo']) : [],
                'potencialidades' => $pre['potencialidades'] ?? ''
            ]
        ]);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'encontrado' => false,
    'inscrito' => false
]);
exit;
