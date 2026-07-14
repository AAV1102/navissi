# Fase 9 · Gobierno de Secretos

## Resultado

NAVISSI incorpora un inventario de riesgo para archivos XLSX, XLSM y CSV que podrían contener contraseñas, tokens u otras credenciales en texto plano. El control revisa exclusivamente las dos ubicaciones autorizadas por TI y no modifica los originales.

## Privacidad por diseño

- No almacena ni presenta valores de celdas, usuarios, contraseñas o tokens.
- No conserva la ruta completa: registra una etiqueta de origen, el nombre del archivo y un hash irreversible de su ubicación.
- Solo persiste hoja, encabezados sensibles, severidad, fecha de modificación y número estimado de registros.
- Un error de lectura se registra únicamente como archivo omitido; su contenido y ruta no aparecen en mensajes.
- La exploración no sigue formatos `.xls` antiguos. Deben convertirse de manera controlada a XLSX antes del análisis.

## Flujo operativo

1. TI ejecuta **Escanear metadatos** desde Automatización e IA → Gobierno de Secretos.
2. NAVISSI clasifica los encabezados de contraseña, secreto, API key, token, PIN o credencial.
3. TI asigna responsable y fecha objetivo.
4. El responsable mueve el secreto a un gestor empresarial, rota la credencial en el sistema de origen y sanea las copias del libro.
5. TI registra la evidencia sin incluir el secreto y marca el hallazgo como rotado.
6. Un nuevo escaneo valida el estado del documento. Si el archivo cambia y conserva columnas sensibles, se abre una revisión nueva.

La tarea operativa de NAVISSI ejecuta además un control automático cada 24 horas. La ejecución de 15 minutos reutiliza el último resultado y no vuelve a leer los libros mientras el control diario esté vigente.

## Estados

- **Activo:** riesgo detectado, todavía sin plan.
- **Planificado:** responsable y fecha obligatorios.
- **Rotado:** evidencia obligatoria; indica rotación y saneamiento verificados.
- **Aceptado:** decisión documentada de aceptar temporalmente el riesgo.
- **Resuelto:** el archivo o la estructura sensible ya no aparece en un nuevo escaneo.

## Integración

Inteligencia Operativa genera hallazgos críticos cuando existen archivos activos con secretos o planes de rotación vencidos. El módulo es de consulta para Gerencia y CEO; las acciones y escaneos se restringen a ADMIN y TI.

## Prueba

```powershell
php tests/phase9_secret_governance_test.php
```

La prueba construye libros temporales con datos ficticios, verifica que el valor sensible nunca llega a la base de datos, valida la gestión con evidencia y elimina los archivos al finalizar.
