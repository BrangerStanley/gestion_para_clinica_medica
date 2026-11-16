# 🏥 IAA — Módulo de Gestión Médica

Módulo de **Gestión Médica del panel de administración del IAA** (Instituto
Autónomo de Ayuda a los Funcionarios del Estado Yaracuy, Venezuela), publicado
para revisión del código e instalación local.

Este repositorio no incluye el sistema de autenticación. Sus semillas contienen
únicamente datos ficticios; las cuentas creadas por ellas no tienen contraseñas
válidas.

---

## 📦 Contenido

| Ruta | Descripción |
|---|---|
| `config/` | Capa de configuración: entorno (`.env`), rutas, seguridad (sesión, CSRF, cabeceras), permisos, conexión PDO/PostgreSQL y helpers. |
| `controllers/` | Endpoints JSON del módulo: `get_*` (lectura), `procesar*` (alta/edición/borrado con re-autenticación del administrador) y `exportar_estadisticas_gestion.php`. |
| `models/` | Modelos de dominio: `ServicioModel`, `EspecialidadModel`, `MedicoModel`, `PatologiaModel`, `EstadisticasCuadroModel`, `PermisoModel`. |
| `views/` | Vistas del panel administrativo (`gestion_medica.php`, `gestion_servicios.php`, `gestion_especialidades.php`, `gestion_medicos.php`, `gestion_patologias.php`, `expedientes_medicos.php`, `dashboard.php`), sus *partials*, CSS y JS. |
| `public/` | Recursos estáticos del panel (Bootstrap 5, Bootstrap Icons, logo). |
| `database/` | Esquema PostgreSQL (`esquema.sql`), semilla base (`semilla_gestion_medica.sql`) y datos de ejemplo (`semilla_ejemplos.sql`). |

### Vistas del desplegable «Gestión Médica»

1. **Cuadro Médico** (`views/gestion_medica.php`) — Servicios, especialidades y médicos en una sola vista.
2. **Expedientes médicos (solo lectura)** (`views/expedientes_medicos.php`).
3. **Servicios** (`views/gestion_servicios.php`) — CRUD con re-autenticación del administrador.
4. **Especialidades** (`views/gestion_especialidades.php`) — CRUD.
5. **Médicos** (`views/gestion_medicos.php`) — Administración de fichas de médicos existentes: edición, estado y asignación de servicios. El alta se realiza desde la gestión de usuarios del sistema integrado.
6. **Patologías (CIE-10)** (`views/gestion_patologias.php`) — CRUD del catálogo de diagnósticos.

---

## 🧰 Requisitos

- **PHP ≥ 8.2** con `pdo_pgsql`, `mbstring`, `openssl`, `gd`, `fileinfo`, `json`.
- **PostgreSQL 15+** (probado con 18). La capa `config/Database.php` habla PDO
  contra PostgreSQL; no hay compatibilidad con MySQL/MariaDB.
- **Apache** con `mod_rewrite` (XAMPP sirve para desarrollo) o el servidor
  embebido de PHP.

---

## 🚀 Instalación

### 1. Clonar y configurar el entorno

```bash
git clone <url-del-repositorio> administracion_medica
cd administracion_medica
cp .env.example .env     # Windows: copy .env.example .env
```

Edite `.env` con las credenciales de su PostgreSQL:

```ini
IAA_DB_HOST=127.0.0.1
IAA_DB_PORT=5432
IAA_DB_USER=iaa_app_user
IAA_DB_PASS=...
IAA_DB_NAME=iaa_gestion_medica
IAA_DB_SSLMODE=prefer
```

### 2. Crear la base de datos

```bash
psql -U postgres -c "CREATE DATABASE iaa_gestion_medica;"
psql -U postgres -d iaa_gestion_medica -v ON_ERROR_STOP=1 -f database/esquema.sql
psql -U postgres -d iaa_gestion_medica -v ON_ERROR_STOP=1 -f database/semilla_gestion_medica.sql
psql -U postgres -d iaa_gestion_medica -v ON_ERROR_STOP=1 -f database/semilla_ejemplos.sql
psql -U postgres -d iaa_gestion_medica -v ON_ERROR_STOP=1 -f database/semilla_expedientes_ejemplo.sql
```

`semilla_ejemplos.sql` carga datos sintéticos de demostración: **5 profesionales**
con cuenta sin contraseña válida, **4 pacientes ficticios**, **6 solicitudes/citas** de
la semana en curso y **5 expedientes de prueba**.
`semilla_expedientes_ejemplo.sql` completa **dos expedientes de ejemplo**
con campos de **ginecología** y **odontología** rellenados con texto genérico de
prueba. Ambos scripts son idempotentes.

### 3. Levantar la aplicación

Con Apache (XAMPP), colocando la carpeta en `htdocs`:

```
http://localhost/administracion_medica/
```

Con el servidor embebido de PHP:

```bash
php -S localhost:8000 -t .
```

Abre `http://localhost:8000/`. La aplicación calcula su ruta base según dónde
se instale; con Apache, usa el nombre de la carpeta bajo `htdocs`.

### 4. Acceso local

Este repositorio **no incluye login**. Por seguridad, el modo demo está
desactivado por defecto y el panel no se debe exponer a una red sin integrar
primero la autenticación del sistema principal.

Para una prueba aislada en el equipo local, cambia en `.env`:

```ini
IAA_MODO_DEMO=1
```

El modo demo solo se activa cuando tanto el host como el cliente son loopback
(`localhost`, `127.0.0.1` o `::1`). Concede rol de administrador y permite
operaciones de escritura, por lo que **no debe usarse en un servidor público**.
Para cualquier despliegue, deja `IAA_MODO_DEMO=0` e integra un login real.

---

## 🔐 Seguridad incluida

- Sesión endurecida (`config/seguridad.php`): cookie `HttpOnly` + `SameSite`,
  regenerated ID, control de horario laboral y cabeceras CSP con *nonce*.
- **CSRF** en todos los formularios y endpoints (`config/csrf`, `views/js/csrf.js`).
- **Re-autenticación del administrador** antes de cada operación de escritura
  (`views/js/gestion_core.js` + `views/partials/modales_admin.php`); el modo
  demo local omite esa re-autenticación.
- Control de acceso por rol (5 = administrador) y por permiso canónico
  (`configurar_servicios`, `gestionar_patologias`).
- Bitácora de auditoría de accesos y de operaciones sensibles.

---

## 📌 Alcance de la extracción

Se extrajeron del sistema original **sin modificar la lógica** los archivos
necesarios para que el módulo funcione de forma autónoma. Se dejaron fuera,
por pertenecer a otros módulos del IAA, los siguientes elementos:

- Vistas de afiliados, solicitudes, citas, historias clínicas y el panel
  financiero (sus tablas siguen creadas en `database/esquema.sql`).
- Gestión de usuarios y de la matriz de roles (`views/gestion_roles.php`).
- Aprobación de cuentas y alertas del panel general (`admin_badge.js`).
- Los scripts de calidad de código (`bin/`), por no pertenecer al módulo funcional.

Los únicos ajustes sobre el código original son de **encaminamiento y alcance**:
la constante `BASE_URL` de `config/rutas.php` y las rutas absolutas de los
exportadores en `views/js/*.js` apuntan a la carpeta de este repositorio; se
retiró la pantalla de login, sus controladores y la recuperación de contraseña,
de modo que el repositorio contiene únicamente la parte administrativa.

---

## 📄 Derechos de uso

La publicación permite revisar el código, pero no concede una licencia para
reutilizarlo, modificarlo o redistribuirlo. Los derechos pertenecen a sus
titulares. Las marcas y recursos gráficos institucionales se incluyen con
autorización de publicación; no se concede permiso independiente para su uso.
Las licencias de los recursos de terceros están en
[THIRD_PARTY_NOTICES.md](./THIRD_PARTY_NOTICES.md).
