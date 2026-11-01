<?php
include('../funciones/functions.php');

cargarPermisosUsuario();

// Verificar autenticación y rol
if (!isLoggedIn()) {
    header('location: ../login.php');
    exit();
}
if (!isDirectorCarrera()) {
    $_SESSION['error'] = "No tienes permisos de Director de Carrera para acceder a este módulo.";
    header('location: ../profile_selector.php');
    exit();
}

// Verificar redirección guardada (manteniendo tu lógica original)
if (isset($_SESSION['here']) && !empty($_SESSION['here'])) {
    header("Location: " . $_SESSION['here']);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Director de Carrera - Sistema Académico</title>
    <link rel="stylesheet" type="text/css" href="../style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
    <style>
        :root {
            --primary-color: #FF8C00;
            --primary-dark: #E67E00;
            --primary-light: #FFA94D;
            --accent-color: #FF5722;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: var(--primary-color);
            position: relative;
            padding: 20px;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        button[name=register_btn] {
            background: var(--primary-color);
            border: none;
        }
        
        button[name=register_btn]:hover {
            background: var(--primary-dark);
        }
        
        .profile-switcher {
            position: absolute;
            right: 20px;
            top: 15px;
        }
        
        .profile_info {
            display: flex;
            align-items: center;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin: 20px auto;
            max-width: 800px;
        }
        
        .profile_info img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-right: 20px;
            border: 3px solid var(--primary-light);
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background: var(--primary-color);
            color: white;
            font-weight: bold;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-dark);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-color);
        }
        
        .welcome-message {
            text-align: center;
            padding: 20px;
            margin: 20px auto;
            max-width: 800px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Director de Carrera</h2>
        <?php if (count($_SESSION['user']['available_profiles'] ?? []) > 1): ?>
        <div class="profile-switcher">
            <a href="../profile_selector.php" class="btn btn-light btn-sm">Cambiar perfil</a>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="content">
        <!-- notification message -->
        <?php if (isset($_SESSION['success'])) : ?>
            <div class="error success">
                <h3>
                    <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                    ?>
                </h3>
            </div>
        <?php endif ?>

        <!-- welcome message -->
        <div class="welcome-message">
            <h3>Bienvenido al Panel de Director de Carrera</h3>
            <p>Gestiona programas académicos, docentes y estudiantes de tu facultad</p>
        </div>

        <!-- logged in user information -->
        <div class="profile_info">
            <img src="../images/director_profile.png">

            <div>
                <?php if (isset($_SESSION['user'])) : ?>
                    <strong><?php echo $_SESSION['user']['username']; ?></strong>

                    <small>
                        <i style="color: #888;">(<?php echo ucfirst($_SESSION['current_profile']); ?>)</i> 
                        <br>
                    </small>
                <?php endif ?>
            </div>
        </div>

        

    <script language='JavaScript'>
        // Redirección automática después de 5 segundos (puedes ajustar el tiempo)
        setTimeout(function() {
            window.location = 'index.php'; // Cambia esta ruta según necesites
        }, );
    </script>
</body>
</html>