<?php
require_once __DIR__ . '/notificaciones.php';

function catalogo_servicios_listar(PDO $pdo, bool $soloActivos = true): array {
    return $pdo->query('SELECT * FROM catalogo_servicios' . ($soloActivos ? ' WHERE activo=1' : '') . ' ORDER BY orden,nombre')->fetchAll(PDO::FETCH_ASSOC);
}

function catalogo_servicio(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT * FROM catalogo_servicios WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function solicitud_evento(PDO $pdo, int $solicitudId, string $accion, ?string $anterior, ?string $nuevo, ?string $nivel, string $actor, ?string $comentario = null, array $meta = []): void {
    $pdo->prepare('INSERT INTO solicitudes_aprobacion_eventos(solicitud_id,accion,estado_anterior,estado_nuevo,nivel,actor,comentario,metadatos_json) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([$solicitudId, $accion, $anterior, $nuevo, $nivel, $actor, $comentario, json_encode($meta, JSON_UNESCAPED_UNICODE)]);
}

function solicitud_destinatarios_nivel(PDO $pdo, string $nivel, string $area): array {
    if ($nivel === 'GERENCIA') {
        return $pdo->query("SELECT email FROM usuarios_sistema WHERE activo=1 AND rol IN('GERENCIA','CEO','SUPER_ADMIN') AND email IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    }
    $stmt = $pdo->prepare("SELECT email FROM usuarios_sistema WHERE activo=1 AND rol IN('DIRECTOR','COORDINADOR','ADMIN','SUPER_ADMIN') AND (area_responsable=? OR rol IN('ADMIN','SUPER_ADMIN')) AND email IS NOT NULL");
    $stmt->execute([$area]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function solicitud_notificar(PDO $pdo, array $solicitud, string $evento, string $asunto, string $contenido, array $extras = []): void {
    notificaciones_encolar_operacion($pdo, $evento, $asunto, $contenido, $extras, ['solicitud_id' => (int)$solicitud['id'], 'codigo' => $solicitud['codigo']]);
}

function solicitud_crear(PDO $pdo, array $usuario, int $catalogoId, string $descripcion, ?float $monto, string $prioridad, ?int $sedeId = null, array $datos = []): array {
    $servicio = catalogo_servicio($pdo, $catalogoId);
    if (!$servicio || !(int)$servicio['activo']) throw new InvalidArgumentException('El servicio seleccionado no está disponible.');
    $descripcion = trim($descripcion);
    if ($descripcion === '') throw new InvalidArgumentException('La descripción es obligatoria.');
    if ((int)$servicio['requiere_monto'] && ($monto === null || $monto < 0)) throw new InvalidArgumentException('Este servicio requiere indicar el monto estimado.');
    if (!in_array($prioridad, ['BAJA','NORMAL','ALTA','URGENTE'], true)) $prioridad = 'NORMAL';

    $documento = $usuario['documento'] ?? null;
    $areaSolicitante = $usuario['area_responsable'] ?? null;
    if ($documento) {
        $stmt = $pdo->prepare('SELECT area FROM empleados WHERE documento=? LIMIT 1');
        $stmt->execute([$documento]);
        $areaSolicitante = $stmt->fetchColumn() ?: $areaSolicitante;
    }
    $rol = rol_efectivo();
    $nivel = $servicio['nivel_aprobacion'] === 'GERENCIA' || in_array($rol, ['DIRECTOR','GERENCIA','CEO'], true) ? 'GERENCIA' : 'DIRECTOR';
    $fechaLimite = gmdate('Y-m-d H:i:s', time() + max(1, (int)$servicio['sla_horas']) * 3600);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO solicitudes_aprobacion(tipo,solicitante_documento,solicitante_nombre,area_responsable,descripcion,monto,prioridad,estado,nivel_actual,catalogo_id,sede_id,fecha_limite,datos_json,actualizado_en) VALUES(?,?,?,?,?,?,?,'PENDIENTE',?,?,?,?,?,CURRENT_TIMESTAMP)")
            ->execute([$servicio['codigo'], $documento, $usuario['nombre'], $servicio['area_responsable'], $descripcion, $monto, $prioridad, $nivel, $catalogoId, $sedeId ?: ($usuario['sede_id'] ?? null), $fechaLimite, json_encode(array_merge($datos, ['area_solicitante' => $areaSolicitante]), JSON_UNESCAPED_UNICODE)]);
        $id = (int)$pdo->lastInsertId();
        $codigo = 'SOL-' . gmdate('Y') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE solicitudes_aprobacion SET codigo=? WHERE id=?')->execute([$codigo, $id]);
        solicitud_evento($pdo, $id, 'CREADA', null, 'PENDIENTE', $nivel, $usuario['nombre'], $descripcion, ['catalogo_codigo' => $servicio['codigo']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $solicitud = solicitud_obtener($pdo, $id);
    $destinatarios = solicitud_destinatarios_nivel($pdo, $nivel, $servicio['area_responsable']);
    solicitud_notificar($pdo, $solicitud, "SOLICITUD_{$id}_CREADA", "{$codigo} · Nueva solicitud", "{$servicio['nombre']} solicitado por {$usuario['nombre']}. SLA: {$servicio['sla_horas']} horas.", $destinatarios);
    return $solicitud;
}

function solicitud_obtener(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT s.*,c.nombre servicio_nombre,c.descripcion servicio_descripcion,c.nivel_aprobacion servicio_nivel,c.monto_escalamiento,c.crea_ticket,c.categoria_ticket,c.prioridad_ticket,c.sla_horas,c.area_tramite,sd.nombre sede_nombre FROM solicitudes_aprobacion s LEFT JOIN catalogo_servicios c ON c.id=s.catalogo_id LEFT JOIN sedes sd ON sd.id=s.sede_id WHERE s.id=?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function solicitud_puede_aprobar(array $solicitud): bool {
    if ($solicitud['estado'] !== 'PENDIENTE') return false;
    $rol = rol_efectivo();
    if (in_array($rol, ['SUPER_ADMIN','ADMIN'], true)) return true;
    if ($solicitud['nivel_actual'] === 'GERENCIA') return in_array($rol, ['GERENCIA','CEO'], true);
    $areaUsuario = usuario_actual()['area_responsable'] ?? null;
    return in_array($rol, ['DIRECTOR','COORDINADOR','RRHH'], true) && $areaUsuario !== null && strcasecmp((string)$areaUsuario, (string)$solicitud['area_responsable']) === 0;
}

function solicitud_crear_ticket(PDO $pdo, array $solicitud): ?int {
    if (!(int)$solicitud['crea_ticket'] || !empty($solicitud['ticket_id'])) return $solicitud['ticket_id'] ? (int)$solicitud['ticket_id'] : null;
    $prioridad = $solicitud['prioridad_ticket'] ?: ($solicitud['prioridad'] === 'URGENTE' ? 'URGENTE' : 'MEDIA');
    $sla = gmdate('Y-m-d H:i:s', time() + max(1, (int)$solicitud['sla_horas']) * 3600);
    // Si el catalogo define area_tramite, el ticket se asigna a esa area (ej. Contabilidad
    // solicita y el Director de Contabilidad aprueba, pero quien tramita es RRHH) en vez
    // del area que aprobo.
    $areaDestino = $solicitud['area_tramite'] ?: $solicitud['area_responsable'];
    $pdo->prepare("INSERT INTO tickets(titulo,descripcion,categoria,prioridad,estado,sede_id,solicitante,solicitante_contacto,sla_limite,origen,creado_por_documento,solicitante_area) VALUES(?,?,?,?,'ABIERTO',?,?,?,?,?,?,?)")
        ->execute([$solicitud['codigo'] . ' · ' . $solicitud['servicio_nombre'], $solicitud['descripcion'], $solicitud['categoria_ticket'] ?: 'SOLICITUD_INTERAREA', $prioridad, $solicitud['sede_id'], $solicitud['solicitante_nombre'], null, $sla, 'APROBACION', $solicitud['solicitante_documento'], $areaDestino]);
    $ticketId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE solicitudes_aprobacion SET ticket_id=? WHERE id=?')->execute([$ticketId, $solicitud['id']]);
    return $ticketId;
}

function solicitud_resolver(PDO $pdo, int $id, string $decision, string $comentario, array $actor): array {
    $solicitud = solicitud_obtener($pdo, $id);
    if (!$solicitud) throw new InvalidArgumentException('Solicitud no encontrada.');
    if (!solicitud_puede_aprobar($solicitud)) throw new RuntimeException('No tienes permiso para decidir esta solicitud.');
    $decision = strtoupper($decision);
    if (!in_array($decision, ['APROBAR','RECHAZAR','ESCALAR'], true)) throw new InvalidArgumentException('Decisión inválida.');

    $estadoAnterior = $solicitud['estado'];
    $nuevoEstado = 'PENDIENTE';
    $nuevoNivel = $solicitud['nivel_actual'];
    $accion = $decision;
    if ($decision === 'RECHAZAR') $nuevoEstado = 'RECHAZADA';
    elseif ($decision === 'ESCALAR') $nuevoNivel = 'GERENCIA';
    else {
        $superaMonto = $solicitud['nivel_actual'] === 'DIRECTOR' && $solicitud['monto_escalamiento'] !== null && (float)$solicitud['monto'] >= (float)$solicitud['monto_escalamiento'];
        if ($superaMonto) { $nuevoNivel = 'GERENCIA'; $accion = 'APROBADA_NIVEL'; }
        else $nuevoEstado = 'APROBADA';
    }

    $pdo->beginTransaction();
    try {
        $resuelto = $nuevoEstado === 'PENDIENTE' ? null : gmdate('Y-m-d H:i:s');
        $pdo->prepare('UPDATE solicitudes_aprobacion SET estado=?,nivel_actual=?,aprobador=?,comentario_aprobador=?,resuelto_en=?,actualizado_en=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$nuevoEstado, $nuevoNivel, $actor['nombre'], $comentario ?: null, $resuelto, $id]);
        solicitud_evento($pdo, $id, $accion, $estadoAnterior, $nuevoEstado, $nuevoNivel, $actor['nombre'], $comentario ?: null);
        if ($nuevoEstado === 'APROBADA') {
            $solicitud['estado'] = $nuevoEstado;
            solicitud_crear_ticket($pdo, $solicitud);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $actualizada = solicitud_obtener($pdo, $id);
    $extras = [];
    if (!empty($solicitud['solicitante_documento'])) {
        $correoSolicitante = $pdo->prepare('SELECT email FROM usuarios_sistema WHERE documento=? AND activo=1 LIMIT 1');
        $correoSolicitante->execute([$solicitud['solicitante_documento']]);
        if ($correo = $correoSolicitante->fetchColumn()) $extras[] = $correo;
    }
    if ($nuevoEstado === 'PENDIENTE') $extras = array_merge($extras, solicitud_destinatarios_nivel($pdo, $nuevoNivel, $solicitud['area_responsable']));
    solicitud_notificar($pdo, $actualizada, "SOLICITUD_{$id}_{$accion}_" . count(solicitud_eventos($pdo, $id)), "{$actualizada['codigo']} · {$accion}", "Decisión registrada por {$actor['nombre']}. Estado: {$nuevoEstado}." . ($comentario ? " Comentario: {$comentario}" : ''), $extras);
    return $actualizada;
}

function solicitud_eventos(PDO $pdo, int $id): array {
    $stmt = $pdo->prepare('SELECT * FROM solicitudes_aprobacion_eventos WHERE solicitud_id=? ORDER BY id');
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function solicitud_sla(array $solicitud): string {
    if ($solicitud['estado'] !== 'PENDIENTE') return 'CERRADA';
    if (!$solicitud['fecha_limite']) return 'SIN_SLA';
    return strtotime($solicitud['fecha_limite'] . ' UTC') < time() ? 'VENCIDA' : 'EN_TIEMPO';
}
