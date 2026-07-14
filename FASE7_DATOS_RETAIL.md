# Fase 7 - Datos e inventario retail

## Hallazgo de fuentes

Se inspeccionaron los libros disponibles en las rutas de TI y WorkManager. Contienen activos tecnológicos, usuarios, documentación y credenciales de Siesa, pero no incluyen ventas históricas, existencias comerciales por tienda, referencias, colores o tallas. Por esa razón NAVISSI no presenta todavía pronósticos de demanda como si fueran resultados reales.

Los libros originales no fueron modificados. Las credenciales encontradas en fuentes heredadas no se copian a las tablas comerciales.

## Modelo implementado

- Maestro de productos y variantes por SKU, referencia, categoría, color y talla.
- Fotografías de existencias por fecha, SKU y tienda.
- Líneas de venta por fecha, documento, SKU, tienda, unidades, valor neto, costo y canal.
- Historial de importaciones con checksum, usuario, resultado y conteo de filas.
- Registro separado de errores de calidad con archivo, hoja, fila y motivo.

## Formas de integración

### CSV

El módulo proporciona plantillas para productos, existencias y ventas. Reconoce separadores coma, punto y coma o tabulación y acepta fechas ISO o formatos habituales.

### XLSX

Un mismo libro puede contener hojas llamadas `PRODUCTOS`, `EXISTENCIAS` y `VENTAS`. NAVISSI reconoce esas hojas y valida cada fila antes de incorporarla.

### n8n o integración Siesa

`api_retail.php` recibe lotes JSON firmados con HMAC. Cada lote exige un `source_id` único y admite hasta 20.000 filas. Repetir el mismo identificador no duplica movimientos.

## Indicadores

- Venta neta y unidades de los últimos 28 días disponibles.
- Existencia más reciente por SKU y tienda.
- Venta diaria promedio.
- Días de cobertura.
- Sell-through aproximado del periodo.
- Quiebres con venta reciente.
- Riesgo por cobertura inferior al lead time.
- Huecos de talla cuando una variante queda en cero y la misma referencia conserva otras tallas disponibles.
- Inventario sin rotación.
- Sobrestock por encima del umbral configurado.

La cobertura se calcula como existencia actual dividida por venta diaria de 28 días. Es una lectura operativa, no un pronóstico estadístico. La fase de predicción requiere meses de historia comercial consistente.

## Integración con Fase 6

Los quiebres, riesgos y sobrestock se publican automáticamente como hallazgos de Inteligencia Operativa. El agente explica fecha de corte y cantidades, pero no ordena compras ni traslados automáticamente.

## Controles

- No se importan archivos mayores de 20 MB desde la interfaz.
- Solo se admiten CSV y XLSX.
- El checksum evita reprocesar un archivo idéntico.
- La API limita el tamaño del cuerpo y la cantidad de filas.
- Los endpoints externos requieren firma HMAC.
- Los errores de una fila no invalidan las demás y quedan auditados.
- No se generan datos demostrativos en la base real.
