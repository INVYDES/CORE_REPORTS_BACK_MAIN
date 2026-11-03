# Almacenamiento en IONOS Object Storage

La aplicacion usa el disco `ionos` de Laravel (driver S3) para guardar logotipos,
fotografias y documentos de reportes. El disco se activa con:

```dotenv
FILESYSTEM_UPLOADS_DISK=ionos
```

## Variables de entorno

```dotenv
IONOS_S3_ACCESS_KEY=...
IONOS_S3_SECRET_KEY=...
IONOS_S3_REGION=eu-central-1
IONOS_S3_BUCKET=core-reports
# Opcional: si se omite se usa https://s3.{region}.ionoscloud.com
IONOS_S3_ENDPOINT=https://s3.eu-central-1.ionoscloud.com
# Opcional: dominio/CDN publico del bucket para las imagenes
IONOS_S3_PUBLIC_URL=
IONOS_S3_USE_PATH_STYLE_ENDPOINT=false
UPLOADS_SIGNED_URL_TTL=60
```

Regiones habituales: `eu-central-1` (Frankfurt), `eu-central-2` (Berlin),
`eu-south-2` (Logrono), `eu-central-3`, `eu-central-4`, `us-central-1`.

## Como se guardan los archivos

- Imagenes (`jpg`, `jpeg`, `png`, `gif`, `webp`): se guardan con visibilidad
  publica en `evidencias/{dependencia}/imagenes` y `logos`.
- Documentos (`pdf`, `csv`, `xls`, `xlsx`): se guardan en
  `evidencias/{dependencia}/documentos` con la visibilidad por defecto del
  disco (`private` en IONOS).
- La base de datos guarda la ruta interna (`ruta`) y el disco (`disco`). La URL
  que consume el frontend se calcula al leer:
  - imagenes: URL publica del bucket;
  - documentos: ruta firmada de Laravel (`/api/v1/evidencias/{id}/documento`)
    con validez `UPLOADS_SIGNED_URL_TTL`.

El bucket debe permitir ACLs por objeto para que las imagenes sean publicas y
los documentos privados. Si IONOS no permite ACLs por objeto en tu cuenta,
configura el bucket como privado y sirve las imagenes mediante un dominio/CDN
con politica publica solo para los prefijos `evidencias/*/imagenes/*` y
`logos/*`.

## Verificar la conexion

```bash
php artisan storage:check ionos
php artisan storage:check ionos --public
php artisan storage:check ionos --signed
```

- `--public` comprueba por HTTP que la URL publica responde 200.
- `--signed` comprueba que se genera y funciona una URL firmada.

Si falla, revisa credenciales, region, endpoint, nombre del bucket y que el
bucket exista. Un `403` en `--public` suele indicar falta de ACL/politica
publica; un error DNS indica endpoint o region incorrectos.

## Migrar archivos existentes

Los archivos que ya estan en `storage/app/public` se pueden copiar a IONOS y
actualizar la base de datos sin cambiar el codigo:

```bash
# Simulacion
php artisan uploads:migrate --from=public --to=ionos --dry-run

# Migracion real, conservando los archivos locales
php artisan uploads:migrate --from=public --to=ionos

# Migracion real borrando el origen tras copiar
php artisan uploads:migrate --from=public --to=ionos --delete
```

El comando es idempotente: si el archivo ya existe en el destino no lo vuelve a
copiar, y actualiza `ruta`, `disco` y `logo_ruta`/`logo_disco` en la base de
datos. Conviene ejecutarlo en una ventana de mantenimiento y con backup previo.

## CORS y acceso desde el navegador

Si el frontend muestra imagenes directamente desde el bucket, configura CORS
en el bucket para el dominio de la aplicacion:

- `AllowedOrigins`: `https://tu-dominio.com`
- `AllowedMethods`: `GET`, `HEAD`
- `AllowedHeaders`: `*`

Los documentos no necesitan CORS porque se descargan a traves de la API.

## Rollback

Para volver al disco local:

1. Ejecuta `uploads:migrate --from=ionos --to=public` (sin `--delete`).
2. Cambia `FILESYSTEM_UPLOADS_DISK=public`.
3. Limpia la cache de configuracion: `php artisan config:clear`.
