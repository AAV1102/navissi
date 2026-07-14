# Correo entrante de Mesa de Ayuda

El flujo es: buzón → `api_correo_mesadeayuda.php` → ticket → triage IA →
asignación por categoría → correo al solicitante y al técnico.

## Token del sincronizador

1. Genere un token aleatorio largo (no use el dominio ni una contraseña de usuario).
2. En el hosting cree el archivo privado `correo_sync_token.txt` dentro de la
   carpeta privada de NAVISSI, con el token en una sola línea y permisos de
   lectura únicamente para PHP.
3. Guarde exactamente el mismo valor como secreto de GitHub:
   `NAVISSI_CORREO_TOKEN`.
4. Verifique que el workflow `sincronizar-correo.yml` recibe HTTP 200. Un token
   ausente o incorrecto devuelve 403 y no procesa mensajes.

## Buzón y SMTP

- Configure el buzón de entrada desde `Correo → Buzones` (Graph o IMAP).
- Configure SMTP en el panel de correo. La configuración se guarda fuera del
  repositorio, en `private/smtp_config.json`.
- Para notificar al técnico, el nombre configurado en `Categorías de Tickets`
  debe coincidir con `usuarios_sistema.nombre` (o puede usarse su correo).

El sistema registra el resultado de cada envío en la bitácora del ticket; no
reintenta automáticamente un correo fallido dentro de la misma ejecución para
evitar duplicados. El workflow vuelve a consultar el buzón en el siguiente ciclo.

## Resumen operativo

El workflow `resumen-diario.yml` usa `NAVISSI_RESUMEN_TOKEN` mediante el header
`X-Navissi-Resumen-Token`. En hosting puede definirse como variable de entorno o
guardarse en `private/resumen_cron_token.txt`. El endpoint no acepta tokens
derivados del dominio.
