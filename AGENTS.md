# AGENTS.md — Contexto para asistentes de IA (leer PRIMERO)

> Objetivo de este archivo: que cualquier IA retome el trabajo **sin re-explorar el proyecto** y gastando el mínimo de tokens.
> Mantenerlo actualizado: al terminar una tarea, actualizar la sección **"Estado actual / Bitácora"**.

---

## 1. Qué es el proyecto
Sistema de Control de Estudios de la **UPTPC** (Universidad Politécnica Territorial de Puerto Cabello).
Roles: `admin`, `super_user`, `director_de_carrera`, `docente`, `estudiante`, más un panel oculto `.dios/`.
Documentación funcional ampliada: `DOCUMENTACION_SISTEMA.md` (no es necesario leerla si este archivo basta).

## 2. Stack actual
- PHP 8.x procedural + `mysqli` (SQL concatenado, sin ORM, sin Composer en la raíz).
- MySQL/MariaDB, BD `uptpc` (dump de referencia: `proyecto_tsu.sql`, 72 tablas, 1 trigger `actualizar_total_usuarios`).
- Frontend: Bootstrap 4.6, jQuery, DataTables, AlertifyJS, Summernote, FontAwesome (todo vendorizado en `funciones/`).
- PDF: FPDF (`fpdf/`) — usado en ~46 archivos (constancias, actas, horarios, planillas).
- Correo: PHPMailer 5.x vía SMTP Gmail → `funciones/enviar_email.php` (`enviarEmail($email,$nombre,$asunto,$cuerpo)`).
  Config SMTP en `funciones/variables.php`.
- Archivos subidos al disco local: `foto_perfil/` (~13 MB), `soportes/` (~23 MB), `uploads/`, `images/`.
- Sesiones PHP nativas (`$_SESSION['user']`), login en `funciones/functions.php` (~línea 24760) con `password_verify` (bcrypt).

## 3. Mapa de archivos clave
| Ruta | Nota |
|---|---|
| `funciones/functions.php` | **~1 MB, ~486 funciones, ~25k líneas.** NUNCA leerlo completo: usar `grep -n "function nombre"` y leer rangos. |
| `funciones/conexion.php` | Credenciales MySQL (está en `.gitignore`). No exponer. |
| `funciones/variables.php` | Variables globales, logos, SMTP. |
| `funciones/services/*.php` | Servicios: Carrera, Estudiante, Preinscripcion, Prosecucion, Seccion. |
| `admin/` y `super_user/` | Casi duplicados entre sí (~mismo set de páginas). Cambios en uno suelen requerirse en el otro. |
| `admin/facturascripts/`, `admin/VTGram/` (y en `super_user/`) | Código de terceros/no usado (~1700 archivos). **Ignorar.** |
| `constancias/generar_constancia.php` | Generación de constancias PDF. |
| `sincronizar_db.php/.sh` | Usa `exec()` (mysqldump/mysql). |
| `sistema_viejo/pnfdiurno/` | Código PHP del **sistema viejo** (~880 archivos, nombres con espacios). Solo consulta. |
| `sistemaviejo/pnfdiurno.sql` | **Dump del sistema viejo (79 MB, 491k líneas, 50 tablas).** NUNCA leer completo. |
| `proyecto_tsu.sql` | Dump del sistema nuevo (1 MB, 11.5k líneas). |
| `scratch/` | Scripts temporales de depuración. |

### Reglas para ahorrar tokens
1. Para estructura de BD: `grep -n "CREATE TABLE" archivo.sql` y luego leer solo el bloque de la tabla (`sed -n 'X,Yp'`).
2. Para ver datos de muestra: leer 5–10 filas del `INSERT`, no más.
3. No listar `node_modules/`, `facturascripts/`, `VTGram/`, `PHPMailer/`, `fpdf/`.
4. Los `.sql` están en `.gitignore` (no se versionan).

## 4. Tablas
**Sistema nuevo (`proyecto_tsu.sql`)**: agenda, aprobaciones_avance, auditoria, aulas, bancos, bitacora, carreras, carrera_materia, carrera_versiones, ciudades, codigos_secciones, contenido, control_avance_trayecto, docente_materia, docente_seccion, estados, estado_civil, estudiante_materias, estudiante_seccion, evaluacion, genero, graduados, historial_cambios_notas, horarios, ingresos, mallas, malla_materia, materias, mensajeria, metodos_pago, municipios, nombre_curso, notas_definitivas, notas_pendientes, notas_trimestres, pagos, parroquias, password_resets, periodos_academicos, preinscripcion, prelaciones, relacion_cursos, respaldos_descargas, revision_mensajes, secciones, secretaria_config, secretaria_configuracion_carga, secretaria_cupos, seguridad_bloqueos, seguridad_intentos, seguridad_rps, seguridad_sistema, seguridad_tokens_invalidos, solicitudes_academicas, status, tenencia_vivienda, tipos_horario, tipo_cedula, tipo_formacion, tipo_horario_personal, tipo_pago, tipo_vivienda, titulos, titulos_obtenidos, titulo_materia, trayectos, users, user_types, user_user_types, usuarios_cursos, version_materia, visitas.

**Sistema viejo (`pnfdiurno.sql`, MySQL 5.7)**: agregarseccion, alumno, aula, cambio_carrera, ciudades, cod_electivas, dia, directivos, docente, electivas, estados, horarios, horas, lapso, lapso_inscrip, lismat, lismat_mod_cxc, municipios, nota, notas, notas_axc, notas_cxc, notas_exc, notas_gxc, notas_ixc, notas_mxc, notas_rxc, notas_txc, nota_copy, noticias, parroquias, pensum, seccion, sede, tipos_lapso, user (+ tablas `*_auditoria`).
Claves del viejo: `pensum` char(3) (ej. `GXC`), `cod_mat` char(5), `lapso` formato `AAAA-N` (ej. `2018-1`), `cod_doc` = id docente.

## 5. Usuario de prueba
- `herrejose@gmail.com` / `12345678` (users.id = 2, username `V-12345678`, todos los permisos).

## 6. Líneas de trabajo (roadmap)
- **A. Migración de datos viejo → nuevo**: script que reciba `pnfdiurno.sql` y migre al esquema de `proyecto_tsu.sql`. *Estado: solo análisis inicial, no hay código.*
- **B. Replataforma a Vercel + TypeScript + Turso + Cloudflare R2 + proveedor de email**: *Estado: análisis de viabilidad entregado (ver bitácora). No hay código.*

## 7. Estado actual / Bitácora
| Fecha | Acción | Resultado |
|---|---|---|
| 2026-08-31 | Revisión inicial de `pnfdiurno.sql` y `proyecto_tsu.sql` | Se confirmó que la IA puede leer los dumps directamente (no hace falta importarlos a MySQL). |
| 2026-11-01 | Creado `AGENTS.md` | Contexto para IA. |
| 2026-11-01 | Análisis de viabilidad Vercel/TS/Turso/R2 | Viable pero es una **reescritura** (~340 archivos PHP propios, ~131k líneas). Pendiente: decisión del usuario sobre framework y orden (migración de datos primero vs. replataforma). |

### Riesgos/pendientes detectados
- Credenciales SMTP (contraseña de aplicación Gmail) **hardcodeadas** en `funciones/enviar_email.php` y `funciones/variables.php` → rotarlas y moverlas a variables de entorno.
- SQL concatenado (riesgo de inyección) en gran parte del código.
- Lógica duplicada `admin/` ↔ `super_user/`.
