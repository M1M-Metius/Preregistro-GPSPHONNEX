# Pre-Registro IVMS — GPSPhonnex
**Versión:** v4.1  
**Empresa:** Varinfe Corp / GPSPhonnex  
**Cliente:** Orica (Chile, Perú, Brasil)

## Descripción
Aplicación web para pre-registro de vehículos antes de la instalación del sistema IVMS. Permite a los técnicos ingresar datos de los equipos (Galileosky, MDVR Howen, Iridium, Tablet MDT, Sensor DU-BLE) con soporte para captura de fotos y extracción automática de IMEI/MAC usando Claude Vision AI.

## Funcionalidades
- ✅ Login con autenticación contra tabla `usuario` (MySQL)
- ✅ Formulario wizard 3 pasos: Vehículo → Telemetría → ADAS
- ✅ 8 fotos individuales por registro (una por campo)
- ✅ Compresión automática de fotos en el cliente (fix low memory)
- ✅ Extracción automática de IMEI/MAC/ICCID con Claude Vision AI
- ✅ Panel de administración con edición y archivado
- ✅ Diferenciación por país (Chile 🇨🇱 / Perú 🇵🇪 / Brasil 🇧🇷)
- ✅ Comprobante compartible por WhatsApp al finalizar
- ✅ Menú responsive para móvil (hamburger menu)
- ✅ Notificación por email al Centro de Control

## Hardware IVMS
- **Galileosky 10 Hub** — Telemetría y CAN bus
- **Howen Hero-ME32-04** — MDVR con ADAS/DMS
- **MDT Hero-AT5 V2** — Tablet conductor + RFID
- **Iridium** — Backup satelital
- **DU-BLE (3Scort)** — Sensor de ángulo pitch/roll

## Instalación

### 1. Base de datos
```sql
-- Instalación nueva:
SOURCE preregistro_ivms.sql;

-- Actualización desde v3:
SOURCE alter_v3.sql;
SOURCE alter_v4.sql;
```

### 2. Configuración
```bash
cp config.example.php config.php
nano config.php   # Completar DB_USER, DB_PASS, ANTHROPIC_API_KEY
```

### 3. Carpeta de uploads
```bash
mkdir -p uploads/fotos_ivms
chown www-data:www-data uploads/
chmod 755 uploads/
```

### 4. Usuarios por país
```sql
INSERT INTO usuario (no_nombre, n_pass, id_usua, us_tipo, fe_crea, id_est_usu, failed_attempts)
VALUES
  ('Pre-Registro Chile',  MD5('Chile2026!'),  'chilepreivms',  3, CURDATE(), 'activo', 0),
  ('Pre-Registro Perú',   MD5('Peru2026!'),   'perupreivms',   3, CURDATE(), 'activo', 0),
  ('Pre-Registro Brasil', MD5('Brasil2026!'), 'brasilpreivms', 3, CURDATE(), 'activo', 0);
```

## Archivos
| Archivo | Descripción |
|---|---|
| `preregistro_ivms.html` | Aplicación principal (single file) |
| `config.php` | Credenciales BD y API keys (NO subir a git) |
| `auth_login.php` | Endpoint autenticación |
| `save_preregistro.php` | Endpoint guardar registro + fotos + email |
| `get_registros.php` | Endpoint listar / editar / archivar |
| `analizar_foto.php` | Análisis de fotos con Claude Vision AI |
| `preregistro_ivms.sql` | Tabla principal (instalación nueva) |
| `alter_v3.sql` | Migración v3 (agrega pais_iso) |
| `alter_v4.sql` | Migración v4 (agrega 8 columnas de fotos) |

## Notas importantes
- ⚠️ `config.php` está en `.gitignore` — nunca subir al repositorio
- 📷 Las fotos se comprimen a max 1280px en el cliente antes de subir
- 🔒 HTTPS requerido para captura de cámara en producción
- 🤖 Requiere API key de Anthropic para análisis de fotos con IA
