<?php
declare(strict_types=1);

require_once __DIR__.'/xlsx_reader.php';

/**
 * Gobierno de archivos con credenciales. Solo conserva metadatos de riesgo:
 * jamás persiste, devuelve ni registra el contenido de las celdas detectadas.
 */
function secretos_raices_autorizadas(): array {
    return [
        ['etiqueta'=>'WorkManager · outputs','ruta'=>'C:\\Mesa de Ayuda\\en-esta-ruta-esta-mi-software\\outputs'],
        ['etiqueta'=>'TI 2026 · OneDrive','ruta'=>'C:\\Users\\SISTEMAS\\OneDrive - GRUPO 10Z SAS\\TI 2026'],
    ];
}

function secretos_normalizar(string $valor): string {
    $valor=strtr(trim($valor),['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']);
    $valor=strtoupper($valor);
    return trim((string)preg_replace('/[^A-Z0-9]+/',' ',$valor));
}

function secretos_clasificar_encabezado(string $encabezado): ?array {
    $n=secretos_normalizar($encabezado);
    if($n===''||in_array($n,['CLAVE UNICA','CLAVE PRIMARIA','CLAVE FORANEA','CODIGO CLAVE'],true))return null;
    $criticos=['CONTRASENA','PASSWORD','PASSCODE','CLIENT SECRET','API KEY','ACCESS TOKEN','BEARER TOKEN','PRIVATE KEY','SECRETO','SECRET'];
    foreach($criticos as $p)if($n===$p||str_contains($n,$p))return ['tipo'=>'SECRETO','severidad'=>'CRITICA','columna'=>substr(trim($encabezado),0,80)];
    $altos=['TOKEN','CREDENCIAL','CLAVE ACCESO','PIN','PSK'];
    foreach($altos as $p)if($n===$p||str_contains($n,$p))return ['tipo'=>'CREDENCIAL','severidad'=>'ALTA','columna'=>substr(trim($encabezado),0,80)];
    return null;
}

function secretos_detectar_filas(array $filas,string $hoja): ?array {
    $limite=min(12,count($filas));$mejor=null;
    for($i=0;$i<$limite;$i++){
        $columnas=[];$severidad='ALTA';$tipo='CREDENCIAL';
        foreach(($filas[$i]??[]) as $celda){
            if(!is_scalar($celda))continue;
            $c=secretos_clasificar_encabezado((string)$celda);
            if(!$c)continue;
            $columnas[$c['columna']]=true;
            if($c['severidad']==='CRITICA')$severidad='CRITICA';
            if($c['tipo']==='SECRETO')$tipo='SECRETO';
        }
        if($columnas&&(!$mejor||count($columnas)>count($mejor['columnas'])))$mejor=['fila'=>$i,'columnas'=>array_keys($columnas),'severidad'=>$severidad,'tipo'=>$tipo];
    }
    if(!$mejor)return null;
    $mejor['registros']=max(0,count($filas)-$mejor['fila']-1);
    $mejor['hoja']=substr($hoja,0,100);
    return $mejor;
}

function secretos_leer_csv_metadatos(string $ruta): array {
    $h=fopen($ruta,'rb');if(!$h)throw new RuntimeException('No se pudo leer el archivo.');
    $muestra=[];$lineas=0;$separador=',';
    try{
        while(($linea=fgets($h))!==false){
            $lineas++;
            if($lineas===1){$conteos=[','=>substr_count($linea,','),';'=>substr_count($linea,';'),"\t"=>substr_count($linea,"\t")];arsort($conteos);$separador=(string)array_key_first($conteos);}
            if($lineas<=12)$muestra[]=str_getcsv($linea,$separador);
        }
    }finally{fclose($h);}
    $d=secretos_detectar_filas($muestra,'CSV');
    if($d)$d['registros']=max(0,$lineas-$d['fila']-1);
    return $d?[$d]:[];
}

function secretos_inspeccionar_archivo(string $ruta): array {
    $ext=strtolower(pathinfo($ruta,PATHINFO_EXTENSION));
    if($ext==='csv')return secretos_leer_csv_metadatos($ruta);
    if(!in_array($ext,['xlsx','xlsm'],true))return [];
    $salida=[];
    foreach(xlsx_read_all_sheets($ruta) as $hoja=>$filas){
        $d=secretos_detectar_filas($filas,(string)$hoja);
        // El contenido de $filas se descarta aquí; solo continúa la descripción de columnas.
        if($d)$salida[]=$d;
    }
    return $salida;
}

function secretos_archivos_en_raiz(string $raiz): iterable {
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz,FilesystemIterator::SKIP_DOTS));
    foreach($it as $archivo){
        if(!$archivo->isFile())continue;
        if(in_array(strtolower($archivo->getExtension()),['xlsx','xlsm','csv'],true))yield $archivo->getPathname();
    }
}

function secretos_escanear_raices(PDO $pdo,array $raices,array $actor,string $origen='MANUAL'): array {
    $corr='secretos-'.strtolower(preg_replace('/[^A-Za-z0-9]/','',$origen)).'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(3));
    $pdo->prepare('INSERT INTO secretos_escaneos(correlacion,origen,iniciado_por) VALUES(?,?,?)')->execute([$corr,strtoupper($origen),$actor['nombre']??'Sistema']);
    $scanId=(int)$pdo->lastInsertId();$revisados=0;$omitidos=0;$hallazgos=0;$raicesOk=0;$fuentes=[];
    try{
        foreach($raices as $def){
            $ruta=(string)($def['ruta']??'');$fuente=substr((string)($def['etiqueta']??'Origen autorizado'),0,100);
            if($ruta===''||!is_dir($ruta)){$omitidos++;continue;}
            $raicesOk++;$fuentes[]=$fuente;
            foreach(secretos_archivos_en_raiz($ruta) as $archivo){
                $revisados++;
                try{$detectados=secretos_inspeccionar_archivo($archivo);}catch(Throwable $e){$omitidos++;continue;}
                $real=realpath($archivo)?:$archivo;$hash=hash('sha256',strtolower(str_replace('/','\\',$real)));
                $mtime=(int)(filemtime($archivo)?:0);$mod=$mtime?gmdate('Y-m-d H:i:s',$mtime):null;
                foreach($detectados as $d){
                    $cols=array_values(array_unique(array_map(fn($x)=>substr((string)$x,0,80),$d['columnas'])));
                    sort($cols,SORT_NATURAL|SORT_FLAG_CASE);
                    $clave=hash('sha256',$hash.'|'.$mtime.'|'.$d['hoja'].'|'.implode('|',$cols));
                    $sql="INSERT INTO secretos_hallazgos(clave_unica,archivo_hash,archivo_nombre,fuente,archivo_modificado_en,hoja,tipo,severidad,columnas_json,registros_estimados,ultimo_escaneo_id)
                          VALUES(?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(clave_unica) DO UPDATE SET
                          ultimo_escaneo_id=excluded.ultimo_escaneo_id,actualizado_en=CURRENT_TIMESTAMP,
                          registros_estimados=excluded.registros_estimados,columnas_json=excluded.columnas_json";
                    $pdo->prepare($sql)->execute([$clave,$hash,substr(basename($archivo),0,180),$fuente,$mod,$d['hoja'],$d['tipo'],$d['severidad'],json_encode($cols,JSON_UNESCAPED_UNICODE),$d['registros'],$scanId]);
                    $hallazgos++;
                }
            }
        }
        if($fuentes){
            $marcas=implode(',',array_fill(0,count($fuentes),'?'));
            $sql="UPDATE secretos_hallazgos SET estado='RESUELTO',resuelto_en=CURRENT_TIMESTAMP,actualizado_en=CURRENT_TIMESTAMP
                  WHERE fuente IN ({$marcas}) AND estado IN('ACTIVO','PLANIFICADO') AND (ultimo_escaneo_id IS NULL OR ultimo_escaneo_id!=?)";
            $pdo->prepare($sql)->execute([...$fuentes,$scanId]);
        }
        $resumen="{$revisados} archivos revisados; {$hallazgos} hallazgos de metadatos";
        $pdo->prepare("UPDATE secretos_escaneos SET estado='COMPLETADO',raices_revisadas=?,archivos_revisados=?,archivos_omitidos=?,hallazgos=?,resumen=?,finalizado_en=CURRENT_TIMESTAMP WHERE id=?")->execute([$raicesOk,$revisados,$omitidos,$hallazgos,$resumen,$scanId]);
        return ['ok'=>true,'escaneo_id'=>$scanId,'archivos'=>$revisados,'omitidos'=>$omitidos,'hallazgos'=>$hallazgos,'resumen'=>$resumen];
    }catch(Throwable $e){
        $pdo->prepare("UPDATE secretos_escaneos SET estado='ERROR',raices_revisadas=?,archivos_revisados=?,archivos_omitidos=?,hallazgos=?,resumen='Error interno durante el escaneo',finalizado_en=CURRENT_TIMESTAMP WHERE id=?")->execute([$raicesOk,$revisados,$omitidos,$hallazgos,$scanId]);
        throw $e;
    }
}

function secretos_escanear(PDO $pdo,array $actor,string $origen='MANUAL'): array {
    return secretos_escanear_raices($pdo,secretos_raices_autorizadas(),$actor,$origen);
}

function secretos_escanear_programado(PDO $pdo): ?array {
    $ultimo=$pdo->query("SELECT iniciado_en FROM secretos_escaneos WHERE estado='COMPLETADO' ORDER BY id DESC LIMIT 1")->fetchColumn();
    if($ultimo&&strtotime((string)$ultimo)>=time()-86400)return null;
    return secretos_escanear($pdo,['nombre'=>'Automatización NAVISSI','rol'=>'TI'],'PROGRAMADO');
}

function secretos_gestionar(PDO $pdo,int $id,array $datos,array $actor): void {
    $estado=strtoupper(trim((string)($datos['estado']??'')));
    if(!in_array($estado,['ACTIVO','PLANIFICADO','ROTADO','ACEPTADO'],true))throw new InvalidArgumentException('Estado de gestión inválido.');
    $responsable=substr(trim((string)($datos['responsable']??'')),0,120);
    $fecha=trim((string)($datos['fecha_objetivo']??''));
    if($fecha!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha))throw new InvalidArgumentException('Fecha objetivo inválida.');
    $evidencia=substr(trim((string)($datos['evidencia']??'')),0,500);
    if(in_array($estado,['ROTADO','ACEPTADO'],true)&&$evidencia==='')throw new InvalidArgumentException('La evidencia es obligatoria para cerrar o aceptar el riesgo.');
    if($estado==='PLANIFICADO'&&($responsable===''||$fecha===''))throw new InvalidArgumentException('Asigna responsable y fecha objetivo para planificar.');
    $q=$pdo->prepare("UPDATE secretos_hallazgos SET estado=?,responsable=?,fecha_objetivo=?,evidencia=?,gestionado_por=?,gestionado_en=CURRENT_TIMESTAMP,resuelto_en=CASE WHEN ?='ROTADO' THEN CURRENT_TIMESTAMP ELSE NULL END,actualizado_en=CURRENT_TIMESTAMP WHERE id=?");
    $q->execute([$estado,$responsable?:null,$fecha?:null,$evidencia?:null,$actor['nombre']??'Sistema',$estado,$id]);
    if($q->rowCount()!==1)throw new RuntimeException('Hallazgo no encontrado.');
}

function secretos_metricas(PDO $pdo): array {
    return [
        'activos'=>(int)$pdo->query("SELECT COUNT(*) FROM secretos_hallazgos WHERE estado='ACTIVO'")->fetchColumn(),
        'criticos'=>(int)$pdo->query("SELECT COUNT(*) FROM secretos_hallazgos WHERE estado='ACTIVO' AND severidad='CRITICA'")->fetchColumn(),
        'archivos'=>(int)$pdo->query("SELECT COUNT(DISTINCT archivo_hash) FROM secretos_hallazgos WHERE estado IN('ACTIVO','PLANIFICADO')")->fetchColumn(),
        'planificados'=>(int)$pdo->query("SELECT COUNT(*) FROM secretos_hallazgos WHERE estado='PLANIFICADO'")->fetchColumn(),
        'vencidos'=>(int)$pdo->query("SELECT COUNT(*) FROM secretos_hallazgos WHERE estado='PLANIFICADO' AND fecha_objetivo<date('now')")->fetchColumn(),
    ];
}
