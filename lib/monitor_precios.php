<?php
/**
 * Motor del Monitor de Precios: vigila tiendas online (Shopify, Zara, o
 * cualquier sitio con datos estructurados JSON-LD) y guarda cada escaneo
 * para comparar precio lleno, precio con descuento y % de descuento.
 * Sin dependencias externas (solo curl + json_decode), igual que el resto
 * de NAVISSI. Puerto de la logica ya probada en el monitor de precios en
 * Python que se usaba localmente.
 */

const MP_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

function mp_curl_get(string $url, array $headers = [], ?string $cookieJar = null): array {
    $ch = curl_init($url);
    $opciones = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => MP_USER_AGENT,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json, text/plain, */*', 'Accept-Language: en-US,en;q=0.9,es;q=0.8'], $headers),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ];
    if ($cookieJar) {
        $opciones[CURLOPT_COOKIEJAR] = $cookieJar;
        $opciones[CURLOPT_COOKIEFILE] = $cookieJar;
    }
    curl_setopt_array($ch, $opciones);
    $body = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tipo = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
    curl_close($ch);
    return ['body' => $body, 'codigo' => $codigo, 'tipo' => $tipo];
}

/** Detecta el tipo de tienda: shopify, zara, o jsonld (generico). */
function mp_detectar_tipo(string $url): array {
    if (str_contains($url, 'zara.com')) {
        return ['zara', 'Detectado: Zara (API interna)'];
    }
    // Tennis (tns.us) es Shopify, pero su endpoint puede responder 403/429 a
    // la sonda de detección.  No lo degradamos a JSON-LD: usamos el conector
    // Shopify directamente, igual que el monitor local.
    if (preg_match('~https?://([^/]*\.)?(tns\.us|tennis\.com\.co)(/|$)~i', $url)) {
        return ['shopify', 'Detectado: Tennis (conector Shopify)'];
    }
    if (preg_match('~(https?://[^/]+)(/collections/[^/?#]+)?~', $url, $m)) {
        $dominio = $m[1];
        $coleccion = $m[2] ?? '';
        foreach ([$dominio . $coleccion . '/products.json', $dominio . '/products.json'] as $candidato) {
            $r = mp_curl_get($candidato . '?limit=1');
            if ($r['codigo'] === 200) {
                $data = json_decode($r['body'], true);
                if (is_array($data) && isset($data['products'])) {
                    return ['shopify', 'Detectado: tienda Shopify (datos limpios en JSON)'];
                }
            }
        }
    }
    return ['jsonld', 'Tipo no reconocido: se intentará leer datos estructurados (JSON-LD)'];
}

function mp_num($x): ?float {
    if ($x === null || $x === '') return null;
    return is_numeric($x) ? (float) $x : null;
}

/** Shopify: /products.json trae precio final (price) y precio lleno (compare_at_price) reales. */
function mp_scrape_shopify(string $url): array {
    if (!preg_match('~(https?://[^/]+)(/collections/[^/?#]+)?~', $url, $m)) {
        throw new RuntimeException('URL de Shopify inválida.');
    }
    $dominio = $m[1];
    $base = $dominio . ($m[2] ?? '') . '/products.json';
    $filas = [];
    for ($pagina = 1; $pagina <= 50; $pagina++) {
        $r = mp_curl_get($base . '?limit=250&page=' . $pagina);
        if ($r['codigo'] !== 200) break;
        $data = json_decode($r['body'], true);
        $productos = $data['products'] ?? [];
        if (!$productos) break;
        foreach ($productos as $p) {
            foreach (($p['variants'] ?? []) as $v) {
                $precio = mp_num($v['price'] ?? null);
                $antes = mp_num($v['compare_at_price'] ?? null);
                if (!$antes || ($precio !== null && $antes <= $precio)) $antes = null; // sin descuento real
                $filas[] = [
                    'clave' => (string) ($v['id'] ?? ($p['handle'] . '-' . ($v['title'] ?? ''))),
                    'producto' => $p['title'] ?? '',
                    'variante' => $v['title'] ?? '',
                    'precio' => $precio,
                    'precio_antes' => $antes,
                    'descuento_pct' => $antes ? round(($antes - $precio) / $antes * 100, 1) : null,
                    'disponible' => !empty($v['available']) ? 1 : 0,
                    'url' => $dominio . '/products/' . ($p['handle'] ?? ''),
                ];
            }
        }
        usleep(300000);
    }
    return $filas;
}

/**
 * Zara: API interna de categorías. El monitor original en Python solo traía
 * el precio final; aqui se agrega la lectura de 'oldPrice' (precio lleno
 * antes del descuento, en centavos igual que 'price') cuando Zara lo trae,
 * para poder calcular precio lleno + % de descuento + precio con descuento.
 */
function mp_scrape_zara(string $url): array {
    $cookieJar = sys_get_temp_dir() . '/navissi_mp_zara_' . uniqid() . '.txt';
    // Mantener la sesión y simular navegación normal reduce falsos bloqueos.
    mp_curl_get('https://www.zara.com/us/', ['Referer: https://www.zara.com/'], $cookieJar);
    usleep(300000);

    $categorias = [];
    if (preg_match('/-l(\d+)\.html/', $url, $mCat)) {
        $categorias[] = [(int) $mCat[1], 'categoría de la URL'];
    } else {
        $r = mp_curl_get('https://www.zara.com/us/en/categories?ajax=true', [
            'Referer: https://www.zara.com/us/en/',
            'X-Requested-With: XMLHttpRequest',
        ], $cookieJar);
        if ($r['codigo'] !== 200 || !str_contains($r['tipo'], 'json')) {
            @unlink($cookieJar);
            throw new RuntimeException('Zara bloqueó la petición (protección anti-bots). Vuelve a intentarlo más tarde o pega la URL de una categoría específica (ej. mujer > camisas).');
        }
        $data = json_decode($r['body'], true);
        $ids = [];
        $recorrer = function ($nodos) use (&$recorrer, &$categorias, &$ids) {
            foreach ($nodos as $n) {
                if (!empty($n['id']) && !isset($ids[$n['id']])) {
                    $ids[$n['id']] = true;
                    $categorias[] = [$n['id'], $n['name'] ?? ''];
                }
                if (!empty($n['subcategories'])) $recorrer($n['subcategories']);
            }
        };
        $recorrer($data['categories'] ?? []);
    }

    $filas = [];
    $vistos = [];
    foreach ($categorias as [$catId, $catNombre]) {
        $r = mp_curl_get("https://www.zara.com/us/en/category/{$catId}/products?ajax=true", [
            'Referer: https://www.zara.com/us/en/',
            'X-Requested-With: XMLHttpRequest',
        ], $cookieJar);
        if ($r['codigo'] !== 200) continue;
        $data = json_decode($r['body'], true);
        foreach (($data['productGroups'] ?? []) as $grupo) {
            foreach (($grupo['elements'] ?? []) as $elem) {
                foreach (($elem['commercialComponents'] ?? []) as $prod) {
                    $ref = $prod['reference'] ?? $prod['id'] ?? null;
                    if (!$ref || isset($vistos[$ref])) continue;
                    $vistos[$ref] = true;
                    $precioCentavos = $prod['price'] ?? null;
                    $antesCentavos = $prod['oldPrice'] ?? null;
                    $precio = $precioCentavos !== null ? $precioCentavos / 100 : null;
                    $antes = ($antesCentavos !== null && $antesCentavos > ($precioCentavos ?? 0)) ? $antesCentavos / 100 : null;
                    $seo = $prod['seo'] ?? [];
                    $filas[] = [
                        'clave' => (string) $ref,
                        'producto' => $prod['name'] ?? '',
                        'variante' => $catNombre,
                        'precio' => $precio,
                        'precio_antes' => $antes,
                        'descuento_pct' => $antes ? round(($antes - $precio) / $antes * 100, 1) : null,
                        'disponible' => 1,
                        'url' => !empty($seo) ? 'https://www.zara.com/us/en/' . ($seo['keyword'] ?? '') . '-p' . ($seo['seoProductId'] ?? '') . '.html' : '',
                    ];
                }
            }
        }
        usleep(300000);
    }
    @unlink($cookieJar);
    return $filas;
}

/** Generico: busca datos estructurados schema.org/Product (JSON-LD) en la pagina. */
function mp_scrape_jsonld(string $url): array {
    $r = mp_curl_get($url);
    if ($r['codigo'] !== 200 || !$r['body']) {
        throw new RuntimeException('No se pudo leer la página (HTTP ' . $r['codigo'] . ').');
    }
    preg_match_all('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $r['body'], $bloques);
    $filas = [];
    $extraer = function ($obj) use (&$extraer, &$filas, $url) {
        if (is_int(array_key_first($obj ?? [])) ) {
            foreach ($obj as $o) $extraer($o);
            return;
        }
        if (!is_array($obj)) return;
        $tipos = (array) ($obj['@type'] ?? []);
        if (in_array('Product', $tipos, true)) {
            $ofertas = $obj['offers'] ?? [];
            if (isset($ofertas[0])) $ofertas = $ofertas[0];
            $precio = mp_num($ofertas['price'] ?? null);
            $filas[] = [
                'clave' => (string) ($obj['sku'] ?? $obj['@id'] ?? $obj['url'] ?? $obj['name'] ?? uniqid()),
                'producto' => $obj['name'] ?? '',
                'variante' => '',
                'precio' => $precio,
                'precio_antes' => mp_num($ofertas['highPrice'] ?? null) ?: null,
                'descuento_pct' => null,
                'disponible' => str_contains((string) ($ofertas['availability'] ?? ''), 'InStock') ? 1 : 0,
                'url' => $obj['url'] ?? $url,
            ];
        }
        if (in_array('ItemList', $tipos, true)) {
            foreach (($obj['itemListElement'] ?? []) as $it) $extraer($it['item'] ?? $it);
        }
        foreach ($obj as $v) {
            if (is_array($v)) $extraer($v);
        }
    };
    foreach ($bloques[1] as $b) {
        $data = json_decode(trim($b), true);
        if ($data) $extraer($data);
    }
    if (!$filas) {
        throw new RuntimeException('No se encontraron datos estructurados en esta página. Este sitio necesitaría un conector a medida.');
    }
    // Completar descuento_pct cuando hay precio_antes.
    foreach ($filas as &$f) {
        if ($f['precio_antes'] && $f['precio']) {
            $f['descuento_pct'] = round(($f['precio_antes'] - $f['precio']) / $f['precio_antes'] * 100, 1);
        }
    }
    return $filas;
}

function mp_escanear_sitio(PDO $pdo, array $sitio): array {
    $scrapers = ['shopify' => 'mp_scrape_shopify', 'zara' => 'mp_scrape_zara', 'jsonld' => 'mp_scrape_jsonld'];
    $fn = $scrapers[$sitio['tipo']] ?? null;
    if (!$fn) throw new RuntimeException('Tipo de sitio desconocido.');

    $error = null;
    $filas = [];
    try {
        $filas = $fn($sitio['url']);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $pdo->prepare("INSERT INTO monitor_precios_escaneos (sitio_id, productos_encontrados, error) VALUES (?,?,?)")
        ->execute([$sitio['id'], count($filas), $error]);
    $escaneoId = (int) $pdo->lastInsertId();

    if ($filas) {
        $stmt = $pdo->prepare("INSERT INTO monitor_precios_productos (escaneo_id, clave, producto, variante, precio, precio_antes, descuento_pct, disponible, url) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ($filas as $f) {
            $stmt->execute([$escaneoId, $f['clave'], $f['producto'], $f['variante'], $f['precio'], $f['precio_antes'], $f['descuento_pct'], $f['disponible'], $f['url']]);
        }
    }

    return ['escaneo_id' => $escaneoId, 'productos' => count($filas), 'error' => $error];
}

/** Compara dos escaneos del mismo sitio: nuevos, retirados y cambios de precio. */
function mp_comparar(PDO $pdo, int $escaneoAntesId, int $escaneoAhoraId): array {
    $leer = function (int $id) use ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM monitor_precios_productos WHERE escaneo_id = ?");
        $stmt->execute([$id]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) $out[$f['clave']] = $f;
        return $out;
    };
    $antes = $leer($escaneoAntesId);
    $ahora = $leer($escaneoAhoraId);
    $nuevos = array_values(array_diff_key($ahora, $antes));
    $retirados = array_values(array_diff_key($antes, $ahora));
    $cambios = [];
    foreach (array_intersect_key($ahora, $antes) as $clave => $f1) {
        $f0 = $antes[$clave];
        if ($f0['precio'] !== null && $f1['precio'] !== null && (float) $f0['precio'] !== (float) $f1['precio']) {
            $cambios[] = [
                'producto' => $f1['producto'], 'variante' => $f1['variante'],
                'precio_anterior' => $f0['precio'], 'precio_nuevo' => $f1['precio'],
                'variacion_pct' => $f0['precio'] ? round(($f1['precio'] - $f0['precio']) / $f0['precio'] * 100, 1) : null,
                'url' => $f1['url'],
            ];
        }
    }
    return ['nuevos' => $nuevos, 'retirados' => $retirados, 'cambios' => $cambios];
}
