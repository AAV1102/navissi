# Fase 4 - Gobierno operativo e integración de áreas

## Resultado

NAVISSI dispone de un flujo único para solicitudes entre áreas. Cada servicio define responsable, nivel de aprobación, SLA, monto de escalamiento y si debe generar un ticket después de aprobarse.

## Módulos

- `Catálogo de Servicios`: autoservicio para empleados y líderes.
- `Solicitudes y Aprobaciones`: bandeja por estado, área, nivel y vencimiento.
- `Detalle de solicitud`: decisión, contexto, SLA, ticket relacionado y línea de tiempo.
- `Gobierno Operativo`: métricas ejecutivas de decisiones, montos, tiempos e identidad digital.

## Catálogo inicial retail

1. Acceso o licencia de sistema.
2. Alta, traslado o retiro de usuario.
3. Cambio o asignación de equipo.
4. Mantenimiento de tienda.
5. Compra operativa.
6. Campaña comercial o colección.
7. Contrato o proveedor.
8. Solicitud interáreas de Talento Humano.
9. Otra solicitud interáreas.

Los responsables usan los departamentos reales de NAVISSI: Dirección de Tecnología, Infraestructura, Logística, Marketing, Recursos Humanos, Operaciones y Gerencia.

## Reglas de decisión

- Una solicitud recibe código `SOL-AAAA-######` y fecha límite UTC.
- Empleados inician normalmente en nivel `DIRECTOR`.
- Solicitudes creadas por un Director o servicios definidos como gerenciales inician en `GERENCIA`.
- Un Director, Coordinador o RRHH solamente puede decidir solicitudes de su área responsable.
- Gerencia y CEO deciden el nivel `GERENCIA`.
- Administración puede intervenir para continuidad operativa y auditoría.
- Si el monto supera el umbral del servicio, la aprobación del Director no cierra el caso: lo escala a Gerencia.
- Rechazar o escalar exige comentario.
- Los servicios tecnológicos configurados crean automáticamente un ticket al recibir aprobación final.
- Cada transición crea un evento inmutable y una notificación en la cola auditable de la fase 3.

## Identidad digital

El tablero muestra cuántos usuarios activos tienen documento vinculado, Microsoft SSO y doble factor. Estos indicadores permiten planear la integración con Microsoft Entra ID sin confundir usuarios corporativos, empleados y cuentas técnicas.

## Validación

- Instalación desde base vacía y carga de nueve servicios.
- Creación con código y SLA.
- Permiso por área responsable.
- Escalamiento automático por monto.
- Aprobación final de Gerencia.
- Creación automática del ticket.
- Línea de tiempo y notificaciones auditables.
- Catálogo, bandeja, detalle y tablero verificados en navegador.
- Vista móvil sin desbordamiento horizontal.
