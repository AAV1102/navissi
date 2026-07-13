# Fase 0 de seguridad

## Primer acceso

La contraseña pública `navissi2026` fue invalidada. La clave aleatoria inicial de
`admin@navissi.com` está en:

`C:\Mesa de Ayuda\NAVISSI-INVENTARIO-private\bootstrap-admin.txt`

El sistema exige cambiarla al entrar y elimina ese archivo después del cambio.

## Almacenamiento privado

SQLite, claves de cifrado, configuraciones de IA/Microsoft/SMTP y secretos de
webhooks viven en `C:\Mesa de Ayuda\NAVISSI-INVENTARIO-private`, fuera del sitio.
El respaldo anterior a la migración está en `backups\pre-fase0-navissi.sqlite`.
Los archivos IPS quedaron conservados en `legacy-ips`; no participan en NAVISSI.

No se debe publicar, sincronizar a Git ni copiar parcialmente `encryption.key`:
sin esa llave no se pueden recuperar las credenciales cifradas.

## n8n

Cada POST a `api_webhook_ticket.php` debe incluir `X-Navissi-Signature`, calculado
como HMAC-SHA256 hexadecimal del cuerpo JSON exacto. El secreto se consulta desde
el módulo n8n, solo con rol ADMIN/TI.

## WhatsApp Cloud API

Además del Access Token, Phone Number ID y Verify Token, se debe guardar el
`App Secret` de la aplicación de Meta. NAVISSI valida `X-Hub-Signature-256` antes
de procesar cualquier mensaje. Mientras falte el App Secret, el webhook responde
503 de forma segura.

## Ejecución

`INICIAR.bat` ya usa `router.php`, que bloquea el directorio `data`. En Docker,
`docker-compose.yml` monta `./private` como `/var/lib/navissi` mediante
`NAVISSI_PRIVATE_DIR`.

Prueba de regresión:

```powershell
& 'C:\xampp\php\windowsXamppPhp\php.exe' '.\tests\security_phase0_test.php'
```
