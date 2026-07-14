# Fase 3 - Notificaciones y seguridad operativa

## Alcance implementado

- Cola auditable de notificaciones para correo, WhatsApp Business y Microsoft Teams.
- Clave idempotente por evento para impedir mensajes duplicados.
- Hasta cinco intentos con espera progresiva y registro del ultimo error.
- Ejecucion manual desde el Centro de Notificaciones y ejecucion automatizable desde el motor operativo.
- Credenciales individuales para los agentes de inventario, almacenadas unicamente como hash.
- Vinculacion de cada credencial al primer serial que la usa, restriccion por sede, vencimiento, revocacion y auditoria de ultimo uso/IP.
- Adjuntos de tickets almacenados fuera del directorio publico, con nombres aleatorios, validacion MIME y limite de 10 MB.
- Configuracion sensible de WhatsApp y canales guardada cifrada.

## Activacion de canales

Los canales quedan deshabilitados mientras no existan credenciales reales. No se incluyeron claves, correos ni webhooks ficticios.

1. Correo: configurar y probar SMTP en el modulo de correo; despues habilitar Correo en `Automatizacion e IA > Centro de Notificaciones`.
2. WhatsApp: registrar el token y el `phone_number_id` de Meta en `WhatsApp Business`; despues indicar el numero destino y habilitar el canal.
3. Teams: crear un webhook entrante o flujo autorizado para el canal operativo, copiar su URL en el Centro de Notificaciones y habilitar Teams.
4. Procesar una notificacion de prueba y confirmar en el historial que el estado cambie a `ENVIADA`.
5. Programar `scripts/ejecutar_automatizaciones.php` con el Programador de tareas de Windows o invocarlo desde n8n cada quince minutos.

Una entrega fallida no detiene la operacion: queda en `ERROR`, conserva el motivo y agenda el siguiente intento. TI puede reintentarla o descartarla desde el historial.

Al quinto rechazo cambia a `FALLIDA` y deja de reintentarse automaticamente. La cola usa un bloqueo de proceso para impedir que la tarea programada, n8n y un usuario envien el mismo registro simultaneamente.

## Estado operativo del servidor (13 de julio de 2026)

- Correo: activo y validado con una entrega real a la propia cuenta SMTP configurada.
- Tarea `NAVISSI - Automatizaciones Fase 3`: instalada cada 15 minutos, ultima ejecucion con codigo 0 y heartbeat `OK`.
- La tarea esta registrada temporalmente con el usuario interactivo `SISTEMAS` porque la sesion de instalacion no tenia elevacion. Funciona mientras esa sesion del servidor permanezca iniciada.
- Para operacion 24/7 aun sin sesion iniciada, ejecutar una vez como administrador `scripts/instalar_tarea_automatizaciones.ps1`; el instalador reemplazara la tarea y la registrara como `SYSTEM`.
- WhatsApp y Teams: desactivados porque no existen token/`phone_number_id` ni webhook reales. La interfaz impide habilitarlos de forma incompleta.

## Puesta en marcha de n8n

El flujo `n8n/workflows/navissi-operacion-retail.json` se entrega inactivo y listo para importar. Antes de activarlo:

1. Definir la URL interna de NAVISSI y el secreto HMAC del webhook.
2. Si n8n restringe modulos nativos en nodos Code, permitir `crypto` mediante `NODE_FUNCTION_ALLOW_BUILTIN=crypto`.
3. Ejecutar una prueba manual y revisar la correlacion en `Automatizaciones y alertas`.
4. Activar el cron solo despues de validar horarios, responsables de escalamiento y destinos reales.

## Migracion de agentes existentes

Los agentes instalados anteriormente no tienen credencial y recibiran HTTP 401. Deben reinstalarse desde `Inventario y activos > Agente de Inventario`, eligiendo la sede correcta. Cada instalador contiene una credencial de un solo equipo; debe ejecutarse en el equipo destino y eliminarse despues de instalarlo.

Si un equipo cambia de tarjeta madre o serial, TI debe revocar la credencial anterior y generar un instalador nuevo. No se debe reutilizar el mismo instalador en varios equipos.

## Verificacion realizada

- Sintaxis PHP de todos los archivos modificados.
- Instalacion desde una base de datos vacia.
- Seguridad base, cifrado, CSRF y firma HMAC.
- Apertura/cierre de tiendas, SLA, idempotencia y reintentos.
- API del agente: 401 sin token, 200 con token valido, 403 al cambiar de serial.
- Descubrimiento de red autenticado.
- Interfaz en escritorio y movil sin pagina vacia, overlay de error ni desbordamiento horizontal.
