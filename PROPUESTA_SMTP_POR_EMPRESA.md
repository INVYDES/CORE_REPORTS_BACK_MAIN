# Propuesta y Especificación: Configuración SMTP Multi-Empresa (Multi-Tenant)

Este documento detalla la arquitectura, viabilidad y el prompt listo para ejecutar la funcionalidad de **Servidor de Correo Saliente (SMTP) personalizado por Empresa**.

---

## 1. ¿Es viable? ¿Cómo funcionaría?

**Totalmente viable y es la mejor práctica para sistemas multi-empresa (SaaS/Multi-tenant).**

En CoreReports conviven múltiples empresas (por ejemplo, *ASUR*, *MTA*, *Automotriz del Bajío*, etc.). Cuando cada empresa genera y envía un reporte por correo, el correo debe salir con la identidad y el servidor de dicha empresa:
* Si la empresa es **ASUR**, el correo sale desde `notificaciones@asur.com.mx` con el nombre remitente **"ASUR Mantenimiento"**.
* Si la empresa es **MTA**, sale desde `servicios@mta.com` con el nombre remitente **"MTA Soporte"**.

---

## 2. Experiencia de Usuario (UI/UX)

En la sección de **Perfil de Empresa** (`CompanyProfileView.vue`), los administradores de la empresa verán una nueva tarjeta:

### **"Configuración de Correo Saliente (SMTP)"**
* **Interruptor**: `[x] Habilitar envío de reportes desde el correo de nuestra empresa`
* **Campos configurables**:
  * **Servidor SMTP (Host)**: ej. `smtp.gmail.com`, `smtp.office365.com`, `mail.asur.com.mx`
  * **Puerto**: `587` (TLS/STARTTLS) o `465` (SSL)
  * **Seguridad / Encriptación**: Selector (`TLS`, `SSL`, `Ninguna`)
  * **Usuario / Correo Remitente**: `reportes@asur.com.mx`
  * **Contraseña / App Password**: `••••••••••••••••` *(almacenada con cifrado seguro en la base de datos)*
  * **Nombre a mostrar (From Name)**: ej. `ASUR - Control de Mantenimiento`
* **Botón "Probar Conexión SMTP"**:
  * Envía un correo de prueba de 1 segundo al correo del administrador actual.
  * Muestra una confirmación verde si las credenciales son válidas o el mensaje exacto de error del servidor si la contraseña o puerto fallaron.

---

## 3. Arquitectura Técnica

### Base de Datos (`dependencias`)
Nuevos campos en la tabla de empresas:
```sql
ALTER TABLE dependencias ADD COLUMN smtp_host VARCHAR(191) NULL;
ALTER TABLE dependencias ADD COLUMN smtp_port INT NULL DEFAULT 587;
ALTER TABLE dependencias ADD COLUMN smtp_encryption VARCHAR(20) NULL DEFAULT 'tls';
ALTER TABLE dependencias ADD COLUMN smtp_username VARCHAR(191) NULL;
ALTER TABLE dependencias ADD COLUMN smtp_password TEXT NULL; -- Cifrado con Crypt::encryptString()
ALTER TABLE dependencias ADD COLUMN smtp_from_address VARCHAR(191) NULL;
ALTER TABLE dependencias ADD COLUMN smtp_from_name VARCHAR(191) NULL;
ALTER TABLE dependencias ADD COLUMN smtp_activo BOOLEAN DEFAULT FALSE;
```

### Backend Laravel (`core-api`)
Laravel permite instanciar transportes de correo al vuelo dinámicamente sin reiniciar el servidor:
```php
// En tiempo de ejecución se construye el transport con las credenciales de la empresa
$config = [
    'transport' => 'smtp',
    'host' => $empresa->smtp_host,
    'port' => $empresa->smtp_port,
    'encryption' => $empresa->smtp_encryption,
    'username' => $empresa->smtp_username,
    'password' => Crypt::decryptString($empresa->smtp_password),
];
config(['mail.mailers.empresa_' . $empresa->id => $config]);

Mail::mailer('empresa_' . $empresa->id)
    ->to($destinatarios)
    ->send(new ReportePdfMail($reporte, $pdfContent));
```

Si la empresa no ha configurado su propio SMTP, el sistema puede:
1. Usar el SMTP general del sistema (si existe).
2. O advertir amablemente al usuario: *"Para enviar reportes por correo, configure el correo de su empresa en Perfil de Empresa"*.

---

## 4. Prompt Listo para Ejecutar

Cuando decidas implementarlo, solo copia y pega el siguiente texto en el chat:

```text
Activa e implementa la configuración de Correo Saliente (SMTP) personalizado por Empresa según el archivo PROPUESTA_SMTP_POR_EMPRESA.md:

1. Base de datos:
   - Agrega la migración con los campos smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password (cifrado con Crypt::encryptString), smtp_from_address, smtp_from_name y smtp_activo en la tabla dependencias.
2. Backend (core-api):
   - Endpoints en EmpresaController:
     * GET /api/v1/empresa/smtp (devuelve la configuración sin exponer la contraseña en texto plano).
     * PUT /api/v1/empresa/smtp (guarda y cifra la contraseña si se proporciona una nueva).
     * POST /api/v1/empresa/smtp/probar (envía un correo de prueba al usuario autenticado para validar host/puerto/usuario/contraseña).
   - En ReporteController::enviarCorreo y ReporteController::store:
     * Resolver dinámicamente el mailer usando la configuración SMTP de la empresa del reporte.
3. Frontend (Front):
   - En CompanyProfileView.vue:
     * Agregar la sección "Configuración de Correo Saliente (SMTP)" con campos para Host, Puerto, Encriptación, Usuario, Contraseña, Nombre Remitente, Correo Remitente y botón "Probar Conexión".
   - Reactivar las opciones de envío de reporte por correo (ENABLE_EMAIL_FEATURE = true) en ReportCreateView.vue y ReportDetailView.vue.
   - Compilar el frontend (npm run build).
```

