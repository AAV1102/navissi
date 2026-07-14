# Fase 10 — Despliegue local, GitHub y hosting

## Operación

1. Rotar la credencial FTP histórica en el panel del hosting.
2. Copiar `.env.deploy.example` como `.env.deploy.local` y completar los valores nuevos.
3. Ejecutar `powershell -ExecutionPolicy Bypass -File scripts/preflight_deploy.ps1 -Mode DryRun`.
4. Para publicar, usar `DEPLOY_TODO.bat`; el flujo se detendrá si faltan secretos o hay cambios sin commit.

## GitHub Actions

Crear estos secretos en el repositorio:

- `NAVISSI_RESUMEN_TOKEN`
- `NAVISSI_CORREO_TOKEN`

Los workflows ya no guardan tokens en el código y solo permiten HTTPS/TLS 1.2 o superior.
