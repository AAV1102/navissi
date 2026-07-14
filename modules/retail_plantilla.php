<?php
require_once __DIR__.'/../config.php';requiere_roles(['ADMIN','TI','GERENCIA','CEO','DIRECTOR','ANALISTA'],'../');$tipo=strtoupper((string)($_GET['tipo']??'VENTAS'));$plantillas=[
'PRODUCTOS'=>[['SKU','REFERENCIA','DESCRIPCION','CATEGORIA','COLOR','TALLA','COSTO','PRECIO'],['SKU-001-S','REF-001','Producto ejemplo','VESTIDOS','NEGRO','S','45000','119900']],
'EXISTENCIAS'=>[['FECHA','SKU','SEDE_CODIGO','SEDE_NOMBRE','UNIDADES','COSTO_UNITARIO'],[date('Y-m-d'),'SKU-001-S','001','Tienda ejemplo','12','45000']],
'VENTAS'=>[['FECHA','DOCUMENTO','LINEA_DOCUMENTO','SKU','SEDE_CODIGO','SEDE_NOMBRE','UNIDADES','VALOR_NETO','COSTO','CANAL'],[date('Y-m-d'),'FV-0001','1','SKU-001-S','001','Tienda ejemplo','1','119900','45000','TIENDA']]
];if(!isset($plantillas[$tipo])){http_response_code(404);exit;}header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="plantilla_retail_'.strtolower($tipo).'.csv"');echo "\xEF\xBB\xBF";$o=fopen('php://output','wb');foreach($plantillas[$tipo] as $fila)fputcsv($o,$fila,';');fclose($o);
