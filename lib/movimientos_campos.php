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
        'NUEVO' => 'Equipo nuevo (ingreso)',
        'ASIGNACION' => 'Asignación',
        'PRESTAMO' => 'Préstamo',
        'DEVOLUCION' => 'Devolución',
        'REPOTENCIAMIENTO' => 'Repotenciamiento',
        'RENTING' => 'Renting / Leasing',
        'BODEGA' => 'Ingreso a bodega',
        'BAJA' => 'Baja definitiva',
        'SALIDA_PROVEEDOR' => 'Salida a proveedor (reparación/revisión)',
    ];
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
        default => [],
    };
}

function titulo_formato(string $tipo): string {
    return match ($tipo) {
        'PRESTAMO' => 'FORMATO DE PRÉSTAMO DE EQUIPO',
        'DEVOLUCION' => 'FORMATO DE DEVOLUCIÓN DE EQUIPO',
        'REPOTENCIAMIENTO' => 'FORMATO DE REPOTENCIAMIENTO',
        'ASIGNACION' => 'FORMATO DE ASIGNACIÓN DE ACTIVOS',
        'RENTING' => 'FORMATO DE RENTING / LEASING DE EQUIPO',
        'NUEVO' => 'FORMATO DE INGRESO DE EQUIPO NUEVO',
        'BODEGA' => 'FORMATO DE INGRESO A BODEGA',
        'BAJA' => 'FORMATO DE BAJA DE EQUIPO',
        'SALIDA_PROVEEDOR' => 'FORMATO DE SALIDA DE EQUIPO A PROVEEDOR (REPARACIÓN/REVISIÓN)',
        default => "FORMATO DE {$tipo}",
    };
}
