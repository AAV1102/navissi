<?php
/**
 * Campos específicos por tipo de movimiento, tomados literalmente de tus
 * hojas FMT_Prestamo_Equipo / FMT_Devolucion_Equipo / FMT_Repotenciamiento /
 * FMT_Asignacion_Activos del Excel maestro (Campo / Valor / Responsable /
 * Fecha / Firma / Observaciones). Cada tipo nuevo que agregues aquí aparece
 * automático en el formulario y en el formato imprimible - no hay que tocar
 * nada más.
 */
function tipos_movimiento(): array {
    return [
        'NUEVO' => 'Equipo nuevo (ingreso/compra)',
        'ASIGNACION' => 'Asignación (entrega)',
        'PRESTAMO' => 'Préstamo',
        'DEVOLUCION' => 'Devolución',
        'REPOTENCIAMIENTO' => 'Repotenciamiento',
        'FORMATEO' => 'Formateo / reinstalación',
        'BACKUP' => 'Backup / restauración de información',
        'RENTING' => 'Renting / Leasing',
        'BODEGA' => 'Ingreso a bodega',
        'BAJA' => 'Baja definitiva',
        'SALIDA_PROVEEDOR' => 'Salida a proveedor (reparación/revisión)',
        'PAZ_Y_SALVO' => 'Paz y salvo de equipos (retiro de empleado)',
    ];
}

// Quién debe firmar cada tipo de formato, en orden. Hasta 3 firmantes
// (columnas firma_*/firma2_*/firma3_* de movimientos_equipos). El tipo que
// no aparezca aquí usa una sola firma genérica ("Firma de aceptación").
function firmantes_por_tipo(string $tipo): array {
    return match ($tipo) {
        'NUEVO' => ['firma' => 'Recibido por (TI/Infraestructura)', 'firma2' => 'Autorizado por (Compras/Gerencia)'],
        'ASIGNACION' => ['firma' => 'Entrega (TI/Infraestructura)', 'firma2' => 'Recibe (empleado)'],
        'PRESTAMO' => ['firma' => 'Entrega (TI/Infraestructura)', 'firma2' => 'Recibe (empleado)'],
        'DEVOLUCION' => ['firma' => 'Recibe (TI/Infraestructura)', 'firma2' => 'Entrega (empleado)'],
        'RENTING' => ['firma' => 'Responsable TI/Infraestructura', 'firma2' => 'Proveedor / arrendador'],
        'BAJA' => ['firma' => 'Responsable TI/Infraestructura', 'firma2' => 'Autoriza la baja (Gerencia)'],
        'SALIDA_PROVEEDOR' => ['firma' => 'Directora de Gestión Humana', 'firma2' => 'Responsable TI/Infraestructura (entrega)', 'firma3' => 'Proveedor (quien retira el equipo)'],
        'PAZ_Y_SALVO' => ['firma' => 'Empleado que se retira', 'firma2' => 'TI/Infraestructura (verifica el equipo)', 'firma3' => 'Gestión Humana'],
        default => ['firma' => 'Firma de aceptación'],
    };
}

// Tipos donde el equipo sale de instalaciones y luego puede "volver" (habilita
// el botón de marcar regreso en movimiento_detalle.php).
function tipo_permite_regreso(string $tipo): bool {
    return in_array($tipo, ['SALIDA_PROVEEDOR', 'PRESTAMO', 'RENTING'], true);
}

function campos_por_tipo(string $tipo): array {
    return match ($tipo) {
        'PRESTAMO' => [
            'estado_entrega' => 'Estado de entrega',
            'fecha_limite_devolucion' => 'Fecha límite de devolución',
            'condiciones' => 'Condiciones del préstamo',
        ],
        'DEVOLUCION' => [
            'estado_devolucion' => 'Estado de devolución',
            'novedades' => 'Novedades',
            'recibe_ti' => 'Recibe (TI)',
        ],
        'ASIGNACION' => [
            'accesorios' => 'Accesorios entregados',
            'condiciones_uso' => 'Condiciones de uso',
        ],
        'REPOTENCIAMIENTO' => [
            'diagnostico' => 'Diagnóstico',
            'componentes_actuales' => 'Componentes actuales',
            'componentes_instalados' => 'Componentes instalados',
            'resultado_pruebas' => 'Resultado de pruebas',
        ],
        'FORMATEO' => [
            'motivo_formateo' => 'Motivo (lentitud, virus, cambio de usuario, falla de SO...)',
            'respaldo_previo' => '¿Se hizo respaldo de información antes? (sí/no y dónde quedó)',
            'sistema_instalado' => 'Sistema operativo y versión instalada',
            'software_reinstalado' => 'Software reinstalado',
            'resultado_pruebas' => 'Resultado de pruebas',
        ],
        'BACKUP' => [
            'tipo_backup' => 'Tipo (respaldo / restauración)',
            'origen_destino' => 'Origen → destino (ej. disco local → OneDrive/servidor)',
            'contenido' => 'Contenido respaldado/restaurado',
            'tamano_aprox' => 'Tamaño aproximado',
            'verificacion' => 'Verificación de integridad (se abrió y se confirmó que sirve)',
        ],
        'RENTING' => [
            'proveedor' => 'Proveedor',
            'canon_mensual' => 'Canon mensual',
            'fecha_inicio_contrato' => 'Fecha inicio contrato',
            'fecha_fin_contrato' => 'Fecha fin contrato',
        ],
        'NUEVO' => [
            'proveedor' => 'Proveedor',
            'factura' => 'Número de factura',
            'valor_compra' => 'Valor de compra',
            'garantia_hasta' => 'Garantía hasta',
        ],
        'BODEGA' => [
            'motivo_ingreso' => 'Motivo de ingreso a bodega',
            'estado_fisico' => 'Estado físico del equipo',
        ],
        'BAJA' => [
            'motivo_baja' => 'Motivo de la baja',
            'disposicion_final' => 'Disposición final (chatarrización, donación, venta...)',
        ],
        'SALIDA_PROVEEDOR' => [
            'proveedor_empresa' => 'Empresa proveedora',
            'proveedor_nombre' => 'Nombre de quien retira el equipo',
            'proveedor_documento' => 'Documento de quien retira',
            'proveedor_telefono' => 'Teléfono de contacto',
            'motivo_salida' => 'Motivo (falla reportada / revisión solicitada)',
            'fecha_estimada_retorno' => 'Fecha estimada de retorno',
        ],
        'PAZ_Y_SALVO' => [
            'estado_equipo_devuelto' => 'Estado en que se devuelve el equipo',
            'accesorios_devueltos' => 'Accesorios devueltos',
            'faltantes_o_danos' => 'Faltantes o daños (si aplica)',
            'autoriza_descuento' => 'Autoriza descuento por nómina/liquidación (sí/no y monto)',
        ],
        default => [],
    };
}

function titulo_formato(string $tipo): string {
    return match ($tipo) {
        'PRESTAMO' => 'FORMATO DE PRÉSTAMO DE EQUIPO',
        'DEVOLUCION' => 'FORMATO DE DEVOLUCIÓN DE EQUIPO',
        'REPOTENCIAMIENTO' => 'FORMATO DE REPOTENCIAMIENTO',
        'FORMATEO' => 'FORMATO DE FORMATEO / REINSTALACIÓN',
        'BACKUP' => 'FORMATO DE BACKUP / RESTAURACIÓN DE INFORMACIÓN',
        'ASIGNACION' => 'FORMATO DE ASIGNACIÓN DE ACTIVOS',
        'RENTING' => 'FORMATO DE RENTING / LEASING DE EQUIPO',
        'NUEVO' => 'FORMATO DE INGRESO DE EQUIPO NUEVO',
        'BODEGA' => 'FORMATO DE INGRESO A BODEGA',
        'BAJA' => 'FORMATO DE BAJA DE EQUIPO',
        'SALIDA_PROVEEDOR' => 'FORMATO DE SALIDA DE EQUIPO A PROVEEDOR (REPARACIÓN/REVISIÓN)',
        'PAZ_Y_SALVO' => 'FORMATO DE PAZ Y SALVO DE EQUIPOS',
        default => "FORMATO DE {$tipo}",
    };
}
