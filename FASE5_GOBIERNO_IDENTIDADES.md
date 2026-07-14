# Fase 5 - Gobierno de identidades

## Resultado

NAVISSI coordina altas, traslados y retiros entre Recursos Humanos, Tecnología, Operaciones, Microsoft 365 e Inventario. Cada ciclo tiene aprobación previa, tareas secuenciales, evidencia, responsables y progreso auditable.

## Funcionalidad

- Diagnóstico de empleados activos sin usuario NAVISSI o sin cuenta Microsoft vinculada.
- Detección de retiros que todavía conservan una cuenta Microsoft activa.
- Identificación de cuentas Microsoft sin empleado activo para clasificarlas como personales, técnicas o pendientes de vinculación.
- Ciclos `ALTA`, `TRASLADO` y `RETIRO` con plantillas operativas propias.
- Vinculación con solicitudes aprobadas del catálogo de servicios de la fase 4.
- Registro de evidencia y bloqueo secuencial: una tarea no avanza si la anterior sigue pendiente.
- Acciones locales controladas para vincular SSO, actualizar área o bloquear el acceso NAVISSI.
- Acciones Microsoft Graph separadas y visibles para bloquear cuentas y revocar sesiones.

## Controles de seguridad

- Un ciclo sin solicitud aprobada inicia pendiente y solo Administración o RRHH puede aprobarlo.
- Aprobar el ciclo habilita el plan, pero no ejecuta ninguna acción externa.
- Solo Administración o Tecnología puede ejecutar tareas automáticas.
- Las acciones de Microsoft 365 exigen escribir el correo corporativo exacto; la validación se repite en el servidor.
- La interfaz advierte que la operación modifica el tenant real.
- Los errores de Graph quedan registrados en la tarea sin marcarla como completada.
- Las pruebas automáticas nunca llaman al tenant ni modifican cuentas reales.

## Planes por evento

### Alta

Validación de RRHH, creación de usuario NAVISSI, vinculación con la identidad Microsoft sincronizada, licencias y grupos, asignación de equipo y confirmación de entrega.

### Traslado

Validación del cambio, actualización del área NAVISSI, revisión de grupos y licencias Microsoft, movimiento del equipo y confirmación con el líder de destino.

### Retiro

Validación de RRHH, bloqueo NAVISSI, bloqueo Microsoft 365, revocación de sesiones, recuperación de activos, cierre de accesos adicionales y custodia de datos.

## Estado de Microsoft 365

La autenticación de aplicación contra Microsoft Graph fue comprobada en modo de lectura y la sincronización local contiene 90 identidades, 88 activas. No se bloqueó, habilitó ni revocó ninguna cuenta real durante la implementación o validación. Antes de usar las tareas externas en producción debe confirmarse que la aplicación registrada tenga consentimiento administrativo para las operaciones de escritura requeridas.

## Validación

- Instalación de las tablas desde una base vacía.
- Reconciliación entre empleado, usuario NAVISSI y cuenta Microsoft sincronizada.
- Creación y aprobación de ciclos con sus tareas correctas.
- Ejecución de vinculación local y bloqueo local.
- Progreso calculado por tareas obligatorias.
- Tareas Graph mantenidas pendientes durante pruebas.
- Confirmación exacta de correo validada en el servidor.
- Tablero, detalle de ciclo y vista móvil verificados sin desbordamiento horizontal.
