<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'funciones/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Recargar permisos desde la base de datos para asegurar estado real y actualizado
cargarPermisosUsuario();

$availableProfiles = getAvailableProfiles();

// Si el perfil actual en sesión ya no está disponible, desarmarlo
if (isset($_SESSION['current_profile']) && !in_array($_SESSION['current_profile'], $availableProfiles)) {
    unset($_SESSION['current_profile']);
}

// Verifica que $availableProfiles es un array
if (!is_array($availableProfiles)) {
    $availableProfiles = [];
}

// Inicializa la variable de error
$error = null;

// Configuración de rutas para cada perfil
$profileRoutes = [
    'admin' => 'admin/index.php',
    'super_user' => 'super_user/index.php',
    'docente' => 'docente/index.php',
    'estudiante' => 'estudiante/index.php',
    'director_de_carrera' => 'director_de_carrera/index.php'
];

// Redirección automática si solo tiene un perfil
if (count($availableProfiles) == 1) {
    $_SESSION['current_profile'] = $availableProfiles[0];
    $route = $profileRoutes[$availableProfiles[0]] ?? 'index.php';
    header("Location: $route");
    exit();
}

// Procesar selección de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile'])) {
    $selectedProfile = is_array($_POST['profile']) ? $_POST['profile'][0] : $_POST['profile'];
    if (in_array($selectedProfile, $availableProfiles)) {
        $_SESSION['current_profile'] = $selectedProfile;
        $route = $profileRoutes[$selectedProfile] ?? 'index.php';
        header("Location: $route");
        exit();
    } else {
        $error = "Perfil no válido";
    }
}

// Obtener nombre de usuario de forma segura
$username = 'Usuario'; // Valor por defecto

if (isset($_SESSION['user']['username'])) {
    if (is_array($_SESSION['user']['username'])) {
        // Si es un array, convertimos a string (tomamos el primer elemento)
        $username = (string)reset($_SESSION['user']['username']);
    } else {
        $username = (string)$_SESSION['user']['username'];
    }
}

// Función auxiliar para sanitizar valores
function sanitizeValue($value) {
    if (is_array($value)) {
        return htmlspecialchars(implode(', ', $value));
    }
    return htmlspecialchars((string)$value);
}

// Procesar cierre de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    // Destruir todas las variables de sesión
    $_SESSION = array();
    
    // Destruir la sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    // Redirigir a login.php
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            padding-bottom: 50px;
        }
        .profile-card {
            transition: transform 0.3s;
            cursor: pointer;
        }
        .profile-card:hover {
            transform: scale(1.05);
        }
        .profile-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .btn-back-container {
            margin-bottom: 10px;
            margin-left: 15px;
        }
        .card-header h4 {
            margin: 0;
        }
        .btn-logout {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .btn-logout:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-profile-card {
            min-height: 170px !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
        }
        @media (max-width: 768px) {
            .btn-back-container {
                margin-left: 0;
                text-align: center;
            }
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            .logout-form {
                width: 100%;
            }
            .btn-logout {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Contenedor fluid para que ocupe todo el ancho disponible -->
    <div class="container-fluid px-4">
        <!-- Botones de acción en el header -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="header-actions">
                    <!-- Botón de volver atrás -->
                    <div class="btn-back-container">
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver Atrás
                        </a>
                    </div>
                    
                    <!-- Botón de salir del sistema -->
                    <div class="logout-form">
                        <form method="POST" action="profile_selector.php">
                            <button type="submit" name="logout" value="1" class="btn btn-logout">
                                <i class="fas fa-sign-out-alt"></i> Salir del Sistema
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center py-4">
            <div class="col-md-10 col-lg-9">
                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0 text-center">Selecciona tu perfil</h4>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-center mb-4">Hola, <strong><?php echo sanitizeValue($username); ?></strong>. Tienes acceso a los siguientes perfiles:</p>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo sanitizeValue($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="profile_selector.php">
                            <div class="row g-3">
                                <?php 
                                if (!empty($availableProfiles)):
                                    foreach ($availableProfiles as $profile): 
                                        // Aseguramos que $profile sea string y saneamos
                                        $profileStr = is_array($profile) ? (string)reset($profile) : (string)$profile;
                                        $profileSanitized = sanitizeValue($profileStr);
                                ?>
                                    <div class="col-md-6 mb-3">
                                        <button type="submit" name="profile" value="<?php echo $profileSanitized; ?>" class="btn btn-outline-primary w-100 py-3 btn-profile-card">
                                            <div class="profile-icon">
                                                <?php 
                                                    switch($profileStr) {
                                                        case 'admin':
                                                            echo '<i class="fas fa-user-shield"></i>';
                                                            break;
                                                        case 'super_user':
                                                            echo '<i class="fas fa-crown"></i>';
                                                            break;
                                                        case 'docente':
                                                            echo '<i class="fas fa-chalkboard-teacher"></i>';
                                                            break;
                                                        case 'estudiante':
                                                            echo '<i class="fas fa-user-graduate"></i>';
                                                            break;
                                                        case 'usuario':
                                                            echo '<i class="fas fa-user-tie"></i>';
                                                            break;
                                                        default:
                                                            echo '<i class="fas fa-user"></i>';
                                                    }
                                                ?>
                                            </div>
                                            <h5 class="mb-1">
                                                <?php 
                                                    switch($profileStr) {
                                                        case 'admin':
                                                            echo 'Administrador';
                                                            break;
                                                        case 'super_user':
                                                            echo 'Super Usuario';
                                                            break;
                                                        case 'docente':
                                                            echo 'Docente';
                                                            break;
                                                        case 'estudiante':
                                                            echo 'Estudiante';
                                                            break;
                                                        case 'usuario':
                                                            echo 'Director de Carrera';
                                                            break;
                                                        default:
                                                            echo ucfirst($profileStr);
                                                    }
                                                ?>
                                            </h5>
                                            <small class="text-muted">Haz clic para entrar como <?php echo $profileSanitized; ?></small>
                                        </button>
                                    </div>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                    <div class="col-12">
                                        <div class="alert alert-warning text-center">
                                            No tienes perfiles disponibles. Contacta al administrador.
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botón adicional de salir en la parte inferior para móviles -->
        <div class="row mt-4 d-md-none">
            <div class="col-12">
                <div class="d-grid">
                    <form method="POST" action="profile_selector.php">
                        <button type="submit" name="logout" value="1" class="btn btn-logout btn-lg">
                            <i class="fas fa-sign-out-alt"></i> Salir del Sistema
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>