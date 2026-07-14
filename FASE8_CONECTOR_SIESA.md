# Fase 8 - Conector autorizado con Siesa

## Respuesta corta

Sí, Siesa Enterprise y SBS contemplan integración. El sitio oficial describe más de 300 conectores, configuración dinámica de APIs, panel administrativo, auditoría de transacciones y portal para desarrolladores. La documentación técnica, endpoints y credenciales no se publican como una API abierta general: deben corresponder al producto, versión, tenant y contrato de cada cliente.

Fuentes oficiales consultadas:

- https://www.siesa.com/renovacion-tecnologica/
- https://www.siesa.com/enterprise/
- https://www.siesa.com/e-commerce-b2b/

## Qué debe solicitar GRUPO 10Z

1. Confirmación del producto y versión contratada: Enterprise, SBS, POS o versión anterior.
2. Acceso al portal de desarrolladores o especificación OpenAPI/Swagger.
3. Ambiente sandbox y ambiente productivo.
4. Método de autenticación, client ID, secreto, scopes y política de rotación.
5. Endpoints de lectura para maestro de ítems, referencias, colores, tallas, existencias por bodega y ventas.
6. Paginación, límites de consumo, filtros por fecha y estrategia incremental.
7. Códigos de compañía, centro de operación, bodega y tienda.
8. Autorización para conservar una réplica analítica dentro de NAVISSI.
9. Contacto de soporte del partner y procedimiento para cambios de versión.

## Métodos admitidos por NAVISSI

### API REST oficial

El conector admite OAuth2 Client Credentials, Bearer o API Key, pero se debe seleccionar únicamente el método documentado por Siesa. Las URLs deben usar HTTPS y los endpoints deben ser rutas relativas bajo la URL oficial configurada.

### n8n como middleware

Si el conector de Siesa solo es accesible desde una red privada o mediante un partner, n8n puede transformar la respuesta al contrato de `api_retail.php`. Este endpoint ya está protegido por HMAC e idempotencia.

### Exportaciones programadas

Cuando el contrato no incluya API, Siesa puede generar reportes CSV/XLSX programados. NAVISSI ya importa productos, existencias y ventas desde esos archivos.

## Controles implementados

- Configuración y secretos cifrados con AES-256-GCM fuera del sitio web.
- La sincronización permanece detenida hasta marcar la autorización formal.
- Solo roles TI y Administración pueden configurar o probar.
- La conexión Siesa es exclusivamente de lectura.
- No se reutilizan usuarios POS ni sesiones del ERP.
- No se consulta directamente la base de datos de la nube.
- No se automatiza la interfaz mediante scraping.
- Cada prueba y sincronización queda en una bitácora sin almacenar tokens.
- La tarea de 15 minutos solo intenta Siesa si el conector está habilitado y se cumple el intervalo configurado.

## Estado actual

El conector está instalado pero deshabilitado. No se realizó ninguna llamada al tenant Siesa porque todavía no se cuenta con documentación técnica ni credenciales de integración autorizadas.
