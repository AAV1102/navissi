<?php
require_once __DIR__ . '/../config.php';
$pdo = db();
requiere_login('../');
if (!tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI', 'DIRECTOR', 'GERENCIA', 'CEO'])) { http_response_code(403); exit('No autorizado.'); }

$desde = trim($_GET['desde'] ?? date('Y-m-01', strtotime('-2 months')));
$hasta = trim($_GET['hasta'] ?? date('Y-m-d'));
$bloque = trim($_GET['bloque'] ?? 'tecnicos');

$definiciones = [
    'sla' => [
        'nombre' => 'informe_sla',
        'encabezados' => ['Prioridad', 'Tickets', 'Vencidos', 'Cumplimiento %'],
        'sql' => "SELECT prioridad,
                COUNT(*) AS total,
                SUM(CASE WHEN sla_limite IS NOT NULL AND
                        ((estado NOT IN ('CERRADO','RESUELTO POR IA') AND sla_limite < datetime('now'))
                         OR (estado IN ('CERRADO','RESUELTO POR IA') AND cerrado_en IS NOT NULL AND cerrado_en > sla_limite))
                    THEN 1 ELSE 0 END) AS vencidos
            FROM tickets WHERE date(creado_en) BETWEEN ? AND ? GROUP BY prioridad ORDER BY prioridad",
        'fila' => fn($r) => [$r['prioridad'], $r['total'], $r['vencidos'], $r['total'] > 0 ? round((($r['total'] - $r['vencidos']) / $r['total']) * 100, 1) : 100],
    ],
    'tecnicos' => [
        'nombre' => 'informe_tecnicos',
        'encabezados' => ['Técnico', 'Tickets asignados', 'Resueltos', '% Resolución'],
        'sql' => "SELECT COALESCE(NULLIF(asignado_a,''),'Sin asignar') AS tecnico, COUNT(*) AS total,
                SUM(CASE WHEN estado IN ('CERRADO','RESUELTO POR IA') THEN 1 ELSE 0 END) AS resueltos
            FROM tickets WHERE date(creado_en) BETWEEN ? AND ? GROUP BY tecnico ORDER BY total DESC",
        'fila' => fn($r) => [$r['tecnico'], $r['total'], $r['resueltos'], $r['total'] > 0 ? round(($r['resueltos'] / $r['total']) * 100, 1) : 0],
    ],
    'categorias' => [
        'nombre' => 'informe_categorias',
        'encabezados' => ['Categoría', 'Cantidad'],
        'sql' => "SELECT COALESCE(NULLIF(categoria,''),'Sin categoría') AS categoria, COUNT(*) c FROM tickets WHERE date(creado_en) BETWEEN ? AND ? GROUP BY categoria ORDER BY c DESC",
        'fila' => fn($r) => [$r['categoria'], $r['c']],
    ],
    'inventario' => [
        'nombre' => 'informe_movimientos_inventario',
        'encabezados' => ['Tipo de movimiento', 'Cantidad'],
        'sql' => "SELECT tipo, COUNT(*) c FROM movimientos_equipos WHERE date(creado_en) BETWEEN ? AND ? GROUP BY tipo ORDER BY c DESC",
        'fila' => fn($r) => [$r['tipo'], $r['c']],
    ],
];

$def = $definiciones[$bloque] ?? $definiciones['tecnicos'];
$stmt = $pdo->prepare($def['sql']);
$stmt->execute([$desde, $hasta]);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $def['nombre'] . '_' . $desde . '_a_' . $hasta . '.csv"');
$salida = fopen('php://output', 'w');
fprintf($salida, "\xEF\xBB\xBF"); // BOM UTF-8 para que Excel abra los acentos bien
fputcsv($salida, $def['encabezados'], ';');
foreach ($filas as $r) {
    fputcsv($salida, $def['fila']($r), ';');
}
fclose($salida);
