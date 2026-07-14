# Auditoría operativa y retail — 2026-07-14

| Módulo | Estado | Evidencia |
|---|---|---|
| Mesa de ayuda / ticket detalle | Conforme | Inserta tickets, comentarios, SLA y triage IA; rutas internas existentes |
| Inventario / equipos / detalle | Conforme | CRUD sobre `inventario`, sedes y empleados; enlaces a movimientos y hoja de vida |
| Movimientos / logística | Conforme | `movimientos_equipos` y `movimientos_bodega`; historial y actualización de ubicación |
| Retail inteligencia / plantilla | Conforme condicionado | Importación CSV/XLSX/API idempotente; requiere fuente SIESA real para métricas |
| Salud de tiendas | Conforme | Registro de apertura/validación/cierre, cálculo de nivel y ticket de novedad |
| Compras de equipos | Conforme | Historial enlazado por serial/equipo; tabla `compras_equipo` migrada |
| Devoluciones | Corregido | Estados y tipo de solución validados; rechazadas no cuentan como pendientes |
| Mermas | Corregido | Cantidad positiva y valor no negativo |
| Proveedores | Conforme | Redirección intencional a contratos, sin tabla duplicada |
| Sedes / sede detalle | Conforme | CRUD y relaciones con inventario, empleados y credenciales |

## Pendientes verificables

- El host local no tiene PHP CLI; la sintaxis debe ejecutarse en Docker/GitHub Actions.
- La analítica retail no puede mostrar información comercial hasta importar productos, existencias y ventas de SIESA.
- Revisar en una fase de hardening si se exige CSRF explícito por formulario y doble aprobación para eliminar mermas/devoluciones.

No se ejecutó despliegue, push ni envío de correo real durante esta auditoría.
