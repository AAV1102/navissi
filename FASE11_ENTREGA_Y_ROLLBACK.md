# Fase 11 — Entrega y recuperación

## Antes de publicar

- Confirmar que el certificado HTTPS del hosting es válido.
- Rotar cualquier credencial que haya estado en el historial Git.
- Configurar los secretos de GitHub Actions.
- Ejecutar `scripts/preflight_deploy.ps1 -Mode DryRun`.
- Revisar el diff y confirmar que no contiene `-k`, tokens literales ni `http://grupo10z.com.co`.

## Recuperación

Si el despliegue falla, no se debe sobrescribir la base de datos del hosting. Restaurar el último commit funcional desde GitHub, corregir la causa y ejecutar nuevamente el preflight. Los archivos `data/` y `private/` permanecen fuera del paquete y deben conservarse mediante el respaldo del hosting.

## Criterio de bloqueo

El despliegue queda bloqueado si falta `.env.deploy.local`, hay cambios sin commit, aparece un archivo sensible versionado, se detecta `-k` o el certificado del proveedor no valida.

## Cambio del correo administrador

El correo de la cuenta NAVISSI se cambia desde `Seguridad → Usuarios y roles` con el botón **Guardar correo**. Esto modifica únicamente la cuenta de acceso de NAVISSI; no cambia la cuenta FTP ni la cuenta del panel del proveedor. Las contraseñas no se muestran en pantalla: se cambian mediante contraseña temporal, restablecimiento seguro o el flujo de recuperación.
