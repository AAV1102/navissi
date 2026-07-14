<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/layout.php';
require_once __DIR__.'/../lib/gobierno_secretos.php';
requiere_roles(['ADMIN','TI','GERENCIA','CEO'],'../');
$pdo=db();$actor=usuario_actual();$puedeGestionar=in_array($actor['rol']??'', ['ADMIN','TI'],true);$msg=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!$puedeGestionar)throw new RuntimeException('Tu perfil tiene acceso de consulta, no de gestión.');
        $accion=(string)($_POST['accion']??'');
        if($accion==='escanear'){$r=secretos_escanear($pdo,$actor);$msg=['ok',$r['resumen'].'. Ningún valor sensible fue copiado.'];}
        elseif($accion==='gestionar'){secretos_gestionar($pdo,(int)($_POST['id']??0),$_POST,$actor);$msg=['ok','Plan de tratamiento actualizado con trazabilidad.'];}
    }catch(Throwable $e){$msg=['error',$e->getMessage()];}
}
$metricas=secretos_metricas($pdo);
$hallazgos=$pdo->query("SELECT * FROM secretos_hallazgos WHERE estado!='RESUELTO' ORDER BY CASE severidad WHEN 'CRITICA' THEN 1 ELSE 2 END,CASE estado WHEN 'ACTIVO' THEN 1 WHEN 'PLANIFICADO' THEN 2 ELSE 3 END,actualizado_en DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$ultimo=$pdo->query('SELECT * FROM secretos_escaneos ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC)?:null;
layout_inicio('Gobierno de Secretos','Gobierno de Secretos','../');
?>
<div class="page-kicker">FASE 9 · SEGURIDAD CON EVIDENCIA</div>
<div class="page-heading-row"><div><h1><?=icon('shield','icon-lg')?> Gobierno de Secretos</h1><p class="subtitle">Localiza archivos que podrían contener credenciales en texto plano y coordina su rotación sin copiar, mostrar ni enviar los valores encontrados.</p></div><?php if($puedeGestionar):?><form method="post"><input type="hidden" name="accion" value="escanear"><button><?=icon('search')?> Escanear metadatos</button></form><?php endif;?></div>
<?php if($msg):?><div class="msg-<?=e($msg[0])?>"><?=e($msg[1])?></div><?php endif;?>
<section class="secret-guardrail"><div><span class="page-kicker">LÍMITE DE PRIVACIDAD</span><strong>Los secretos nunca entran a NAVISSI</strong><p>El análisis se limita a nombres de archivo, hoja y encabezados. Los libros originales permanecen intactos y en su ubicación.</p></div><span class="badge badge-activo">SOLO METADATOS</span></section>
<div class="stats-grid secret-stats">
  <div class="stat-card danger"><span>Críticos activos</span><strong><?=(int)$metricas['criticos']?></strong><small>Requieren rotación prioritaria</small></div>
  <div class="stat-card"><span>Archivos expuestos</span><strong><?=(int)$metricas['archivos']?></strong><small>Sin mostrar su ruta completa</small></div>
  <div class="stat-card"><span>Con plan</span><strong><?=(int)$metricas['planificados']?></strong><small>Responsable y fecha asignados</small></div>
  <div class="stat-card <?=$metricas['vencidos']?'danger':''?>"><span>Planes vencidos</span><strong><?=(int)$metricas['vencidos']?></strong><small>Necesitan escalamiento</small></div>
</div>
<div class="panel-grid-2 secret-context">
 <section class="panel"><h3>Alcance autorizado</h3><div class="connection-modes"><div><strong>WorkManager · outputs</strong><span>Libros maestros y archivos operativos heredados.</span></div><div><strong>TI 2026 · OneDrive</strong><span>Documentación de tecnología de GRUPO 10Z.</span></div></div></section>
 <section class="panel"><h3>Último control</h3><?php if($ultimo):?><div class="scan-summary"><strong><?=e($ultimo['estado'])?></strong><span><?=e($ultimo['resumen'])?></span><small><?=e($ultimo['iniciado_en'])?> UTC · <?=e($ultimo['iniciado_por'])?></small></div><?php else:?><p class="small">Aún no se ha ejecutado el primer escaneo.</p><?php endif;?><p class="small">Formatos admitidos: XLSX, XLSM y CSV. Los XLS heredados se omiten hasta su conversión segura.</p></section>
</div>
<section class="panel"><div class="section-heading"><div><span class="page-kicker">PLAN DE TRATAMIENTO</span><h3>Hallazgos vigentes</h3></div><span class="badge badge-warn"><?=count($hallazgos)?> registros</span></div>
<?php if(!$hallazgos):?><div class="empty-state"><strong>Sin riesgos registrados</strong><p>Ejecuta el escaneo para construir el inventario inicial.</p></div><?php else:?><div class="secret-list">
<?php foreach($hallazgos as $h):$cols=json_decode((string)$h['columnas_json'],true)?:[];$vencido=$h['estado']==='PLANIFICADO'&&$h['fecha_objetivo']&&$h['fecha_objetivo']<gmdate('Y-m-d');?>
 <article class="secret-item <?=$h['severidad']==='CRITICA'?'critical':''?>">
  <div class="secret-main"><div class="secret-title"><span class="badge <?=$h['severidad']==='CRITICA'?'badge-err':'badge-warn'?>"><?=e($h['severidad'])?></span><strong><?=e($h['archivo_nombre'])?></strong></div><p><?=e($h['fuente'])?> · hoja <b><?=e($h['hoja'])?></b></p><div class="secret-tags"><?php foreach($cols as $col):?><span><?=e($col)?></span><?php endforeach;?></div><small>≈ <?=(int)$h['registros_estimados']?> registros · archivo modificado <?=e($h['archivo_modificado_en']?:'sin fecha')?> UTC</small></div>
  <div class="secret-status"><span class="badge <?=$h['estado']==='ACTIVO'?'badge-err':($h['estado']==='PLANIFICADO'?'badge-warn':'badge-activo')?>"><?=e($h['estado'])?></span><?php if($h['responsable']):?><strong><?=e($h['responsable'])?></strong><?php endif;?><?php if($h['fecha_objetivo']):?><small class="<?=$vencido?'text-danger':''?>">Objetivo: <?=e($h['fecha_objetivo'])?><?=$vencido?' · VENCIDO':''?></small><?php endif;?></div>
  <?php if($puedeGestionar):?><details class="secret-actions"><summary>Gestionar</summary><form method="post"><input type="hidden" name="accion" value="gestionar"><input type="hidden" name="id" value="<?=(int)$h['id']?>"><div class="grid-form"><div><label>Estado</label><select name="estado"><option value="ACTIVO" <?=$h['estado']==='ACTIVO'?'selected':''?>>Activo</option><option value="PLANIFICADO" <?=$h['estado']==='PLANIFICADO'?'selected':''?>>Planificado</option><option value="ROTADO" <?=$h['estado']==='ROTADO'?'selected':''?>>Rotado</option><option value="ACEPTADO" <?=$h['estado']==='ACEPTADO'?'selected':''?>>Riesgo aceptado</option></select></div><div><label>Responsable</label><input name="responsable" value="<?=e($h['responsable'])?>" placeholder="Nombre o área"></div><div><label>Fecha objetivo</label><input type="date" name="fecha_objetivo" value="<?=e($h['fecha_objetivo'])?>"></div></div><label>Evidencia / decisión</label><textarea name="evidencia" rows="2" placeholder="No escribas contraseñas. Registra ticket, acta o confirmación de rotación."><?=e($h['evidencia'])?></textarea><button>Guardar tratamiento</button></form></details><?php endif;?>
 </article>
<?php endforeach;?></div><?php endif;?></section>
<section class="panel"><h3>Secuencia recomendada</h3><ol class="integration-checklist"><li>Asignar un responsable y fecha a cada archivo crítico.</li><li>Mover las credenciales a un gestor empresarial de secretos con MFA y control de acceso.</li><li>Rotar la contraseña o token en el sistema de origen; no basta con borrar la celda.</li><li>Sanear o eliminar de forma controlada las copias del archivo y sus versiones compartidas.</li><li>Registrar ticket o acta como evidencia y ejecutar un nuevo escaneo.</li></ol></section>
<?php layout_fin();?>
