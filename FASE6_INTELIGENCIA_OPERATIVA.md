# Fase 6 - Inteligencia operativa verificable

## Resultado

NAVISSI cuenta con una capa transversal de control que revisa datos reales de la operación, conserva la evidencia de cada conclusión y permite convertir un hallazgo en trabajo trazable. No requiere una API de IA para funcionar y no envía información empresarial a un modelo externo.

## Agentes de control

- **Control de Tiendas:** aperturas, cierres y validaciones operativas pendientes.
- **Mesa de Ayuda:** SLA vencidos y tickets prioritarios sin responsable.
- **Gobierno de Identidades:** empleados sin cuenta NAVISSI y retiros con Microsoft 365 activo.
- **Control de Activos:** equipos en reparación y agentes sin conexión reciente.
- **Control Financiero y Retail:** utilización de licencias, contratos próximos a vencer, devoluciones, mermas y lanzamientos de campañas o colecciones.

Cada agente usa consultas determinísticas. El hallazgo muestra dominio, severidad, fecha UTC, resumen, valores de evidencia y enlace al registro que lo originó.

## Flujo de gestión

1. La tarea programada de NAVISSI ejecuta primero los controles operativos.
2. Después ejecuta los cinco agentes y actualiza los hallazgos.
3. Un responsable puede tomar la gestión, descartarla de forma explícita o convertirla en ticket.
4. El ticket conserva como origen `INTELIGENCIA` y copia la evidencia.
5. Cuando la condición desaparece, una nueva lectura resuelve automáticamente el hallazgo sin borrar su historial.

## Integración n8n

- `api_automatizaciones.php` incluye la lectura de inteligencia dentro del ciclo existente.
- `api_inteligencia.php` permite ejecutar únicamente los agentes o consultar su estado.
- Ambos endpoints aceptan solamente POST con firma HMAC `X-Navissi-Signature`.
- La correlación hace idempotente cada ejecución y evita procesar dos veces el mismo ciclo.

Ejemplo de cuerpo para ejecución:

```json
{"action":"run","correlation_id":"n8n-20260713-2100"}
```

Ejemplo para consulta:

```json
{"action":"status"}
```

## Gobierno de IA

- No hay respuestas generativas presentadas como hechos.
- No se ejecutan bloqueos, compras, bajas ni cambios de cuentas desde un hallazgo.
- La automatización identifica y explica; una persona decide y ejecuta.
- Toda recomendación conserva su evidencia estructurada y su estado histórico.
- Las integraciones externas permanecen protegidas por firma HMAC y control de roles.

## Validación

- Instalación desde base vacía.
- Detección simultánea de riesgos de servicio, identidad y licenciamiento.
- Idempotencia por correlación.
- Conversión de hallazgo a ticket con origen auditable.
- Resolución automática cuando cambia la evidencia.
- Ejecución integrada con la tarea operativa de 15 minutos.
- Verificación de escritorio y móvil sin desbordamiento horizontal.
