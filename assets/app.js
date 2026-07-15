// Mejora progresiva de tablas: agrega filtro instantaneo y orden al clic en
// cabecera a CUALQUIER tabla de listado (no las .deftable, esas son ficha
// clave/valor) sin tener que tocar cada modulo uno por uno.
//
// Nota: las tablas de este proyecto no usan <thead>/<tbody> explicitos (solo
// <tr><th>... para la cabecera seguido de <tr><td>...), asi que no se puede
// confiar en table.tHead/tBodies - se toma la primera fila con <th> como
// cabecera y el resto como filas de datos.
(function () {
    // Toda tabla que no sea .deftable queda envuelta en un contenedor con
    // scroll horizontal Y vertical (altura maxima), para que nunca desborde
    // la pagina ni quede sin forma de verla completa en pantallas chicas.
    function envolverConScroll(tabla) {
        if (tabla.classList.contains('deftable') || tabla.dataset.envuelta) return null;
        tabla.dataset.envuelta = '1';
        var scroll = document.createElement('div');
        scroll.className = 'tabla-scroll';
        tabla.parentNode.insertBefore(scroll, tabla);
        scroll.appendChild(tabla);
        return scroll;
    }

    function iniciarTabla(tabla) {
        if (tabla.classList.contains('deftable') || tabla.dataset.vivaListo) return;
        var scrollWrap = envolverConScroll(tabla);
        var filasTodas = Array.prototype.slice.call(tabla.rows);
        if (!filasTodas.length) return;
        var filaHeader = filasTodas[0].querySelector('th') ? filasTodas[0] : null;
        var filas = filaHeader ? filasTodas.slice(1) : filasTodas;
        if (filas.length < 3) return; // filtro/orden no valen la pena en tablas chiquitas, pero el scroll ya quedo puesto
        tabla.dataset.vivaListo = '1';
        tabla.classList.add('tabla-viva');

        // Filtro instantaneo
        var wrap = document.createElement('div');
        wrap.className = 'tabla-wrap';
        var referencia = scrollWrap || tabla;
        referencia.parentNode.insertBefore(wrap, referencia);
        var filtro = document.createElement('input');
        filtro.type = 'search';
        filtro.className = 'tabla-filtro';
        filtro.placeholder = 'Filtrar esta tabla...';
        filtro.setAttribute('aria-label', 'Filtrar esta tabla');
        wrap.appendChild(filtro);
        wrap.appendChild(referencia);

        filtro.addEventListener('input', function () {
            var q = filtro.value.trim().toLowerCase();
            filas.forEach(function (fila) {
                var texto = fila.textContent.toLowerCase();
                fila.classList.toggle('fila-oculta', q !== '' && texto.indexOf(q) === -1);
            });
        });

        // Orden al clic en cada encabezado
        if (!filaHeader) return;
        var contenedorFilas = filas[0].parentNode;
        Array.prototype.forEach.call(filaHeader.cells, function (th, idx) {
            var marcador = document.createElement('span');
            marcador.className = 'sort-ic';
            marcador.textContent = ' ↕';
            th.appendChild(marcador);
            th.addEventListener('click', function () {
                var asc = th.dataset.orden !== 'asc';
                Array.prototype.forEach.call(filaHeader.cells, function (otro) { otro.classList.remove('sorted'); delete otro.dataset.orden; });
                th.classList.add('sorted');
                th.dataset.orden = asc ? 'asc' : 'desc';
                var filasOrdenadas = filas.slice().sort(function (a, b) {
                    var va = (a.cells[idx] ? a.cells[idx].textContent : '').trim();
                    var vb = (b.cells[idx] ? b.cells[idx].textContent : '').trim();
                    var na = parseFloat(va.replace(/[^0-9.\-]/g, ''));
                    var nb = parseFloat(vb.replace(/[^0-9.\-]/g, ''));
                    var cmp;
                    if (!isNaN(na) && !isNaN(nb) && va.replace(/[^0-9.\-]/g, '') !== '' && vb.replace(/[^0-9.\-]/g, '') !== '') {
                        cmp = na - nb;
                    } else {
                        cmp = va.localeCompare(vb, 'es', { sensitivity: 'base' });
                    }
                    return asc ? cmp : -cmp;
                });
                filasOrdenadas.forEach(function (fila) { contenedorFilas.appendChild(fila); });
            });
        });
    }

    document.querySelectorAll('main table').forEach(iniciarTabla);

    // Boton de pantalla completa por panel: se agrega solo a cada .panel con
    // encabezado <h3>, sin tener que tocar cada modulo.
    document.querySelectorAll('main .panel > h3').forEach(function (h3) {
        var panel = h3.parentElement;
        if (panel.dataset.fsListo) return;
        panel.dataset.fsListo = '1';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'panel-fullscreen-btn';
        btn.title = 'Pantalla completa';
        btn.setAttribute('aria-label', 'Pantalla completa de este panel');
        btn.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/></svg>';
        h3.appendChild(btn);
        btn.addEventListener('click', function () {
            var activo = panel.classList.toggle('panel-fullscreen');
            document.body.classList.toggle('tiene-panel-fullscreen', activo);
            btn.title = activo ? 'Salir de pantalla completa' : 'Pantalla completa';
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var abierto = document.querySelector('.panel-fullscreen');
            if (abierto) { abierto.classList.remove('panel-fullscreen'); document.body.classList.remove('tiene-panel-fullscreen'); }
        }
    });

    // ---------------------------------------------------------------
    // Formularios de "Agregar/Nuevo X" ocultos tras un boton, en TODO
    // el sitio automaticamente (sin tener que repetirlo modulo por
    // modulo). Si el panel ya trae su propio manejo manual (como
    // Inventario, que tiene campos dinamicos por tipo), se respeta y
    // no se toca - se detecta por [data-form-manual].
    // ---------------------------------------------------------------
    var PATRON_TITULO_CREAR = /^(Nuevo|Nueva|Agregar|Crear)\b/i;
    document.querySelectorAll('main .panel').forEach(function (panel) {
        if (panel.dataset.formManual || panel.dataset.formAutoListo) return;
        var h3 = panel.querySelector(':scope > h3');
        var form = panel.querySelector(':scope > form');
        if (!h3 || !form) return;
        if (!PATRON_TITULO_CREAR.test(h3.textContent.trim())) return;
        panel.dataset.formAutoListo = '1';

        // Si el formulario ya trae un campo id con valor (modo edicion via
        // ?editar=), se deja visible de una vez para no esconder la edicion.
        var campoId = form.querySelector('input[name="id"]');
        var enEdicion = campoId && parseInt(campoId.value, 10) > 0;

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-abrir-form-auto';
        boton.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg> ' + h3.textContent.trim().replace(/\s+/g, ' ');
        panel.parentNode.insertBefore(boton, panel);

        if (!enEdicion) panel.hidden = true;
        boton.hidden = enEdicion;

        boton.addEventListener('click', function () {
            panel.hidden = false;
            boton.hidden = true;
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            var primerCampo = panel.querySelector('input, select, textarea');
            if (primerCampo) primerCampo.focus();
        });

        // Un botón "Cancelar" dentro del formulario (si lo tiene) también
        // debería volver a ocultar el panel en vez de solo navegar.
        var cancelar = form.querySelector('a.btn-secondary, #btn-cancelar-form');
        if (cancelar && !campoId) {
            cancelar.addEventListener('click', function (e) {
                e.preventDefault();
                panel.hidden = true;
                boton.hidden = false;
            });
        }
    });

    // ---------------------------------------------------------------
    // Reordenar por arrastre (drag & drop): tarjetas del dashboard y
    // paneles de nivel superior, en cualquier modulo. El orden elegido
    // se guarda por usuario+pagina en localStorage y se re-aplica solo
    // al volver a esa misma pantalla.
    // ---------------------------------------------------------------
    function activarDragDrop(contenedor, storageKey, selectorItems) {
        var items = Array.prototype.slice.call(contenedor.querySelectorAll(selectorItems));
        if (items.length < 2) return;
        items.forEach(function (item) { item.setAttribute('draggable', 'true'); item.classList.add('draggable-item'); });

        // Restaurar el orden guardado (si existe) comparando por texto, para
        // no depender de indices que puedan cambiar si se agregan/quitan filas.
        try {
            var guardado = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (guardado && guardado.length) {
                var porTexto = {};
                items.forEach(function (item) { porTexto[item.textContent.trim()] = item; });
                guardado.forEach(function (texto) {
                    if (porTexto[texto]) contenedor.appendChild(porTexto[texto]);
                });
            }
        } catch (err) { /* ignorar orden invalido */ }

        var arrastrando = null;
        contenedor.addEventListener('dragstart', function (e) {
            if (!e.target.classList || !e.target.classList.contains('draggable-item')) return;
            arrastrando = e.target;
            e.target.classList.add('arrastrando');
        });
        contenedor.addEventListener('dragend', function (e) {
            if (!arrastrando) return;
            arrastrando.classList.remove('arrastrando');
            arrastrando = null;
            var ordenActual = Array.prototype.slice.call(contenedor.querySelectorAll(selectorItems)).map(function (i) { return i.textContent.trim(); });
            localStorage.setItem(storageKey, JSON.stringify(ordenActual));
        });
        contenedor.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!arrastrando) return;
            var despues = Array.prototype.slice.call(contenedor.querySelectorAll(selectorItems)).find(function (item) {
                if (item === arrastrando) return false;
                var caja = item.getBoundingClientRect();
                return e.clientY < caja.top + caja.height / 2 || (Math.abs(e.clientX - caja.left) < caja.width && e.clientX < caja.left + caja.width / 2);
            });
            if (despues) contenedor.insertBefore(arrastrando, despues);
            else contenedor.appendChild(arrastrando);
        });
    }

    // Apagado por defecto: el arrastre automático en TODA tarjeta/panel del
    // sitio interfería con clics normales en botones/enlaces dentro de esas
    // tarjetas ("no funciona nada"). Ahora solo se activa si el usuario lo
    // prende a propósito desde el panel de Accesibilidad.
    function inicializarReordenarArrastrando() {
        var pagina = window.location.pathname;
        document.querySelectorAll('main .cards').forEach(function (cards, idx) {
            activarDragDrop(cards, 'navissi_orden_cards_' + pagina + '_' + idx, ':scope > .card, :scope > a.card-link');
        });

        // Paneles de nivel superior directamente dentro de <main> (no los que
        // estan anidados en layouts especiales como helpdesk-layout).
        var panelesNivelSuperior = Array.prototype.slice.call(document.querySelectorAll('main > .panel'));
        if (panelesNivelSuperior.length >= 2) {
            var main = document.querySelector('main');
            panelesNivelSuperior.forEach(function (panel) {
                if (panel.hidden) return; // no agregar mango a paneles de formulario ocultos
                var mango = document.createElement('span');
                mango.className = 'panel-drag-handle';
                mango.title = 'Arrastrar para reordenar';
                mango.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="18" r="1"/></svg>';
                var h3 = panel.querySelector(':scope > h3');
                if (h3) h3.insertBefore(mango, h3.firstChild); else panel.insertBefore(mango, panel.firstChild);
            });
            activarDragDrop(main, 'navissi_orden_paneles_' + pagina, ':scope > .panel:not([hidden])');
        }
    }
    if (localStorage.getItem('navissi_reordenar_arrastrando') === '1') {
        inicializarReordenarArrastrando();
    }
    var toggleReordenar = document.getElementById('a11y-reordenar');
    if (toggleReordenar) {
        toggleReordenar.checked = localStorage.getItem('navissi_reordenar_arrastrando') === '1';
        toggleReordenar.addEventListener('change', function (e) {
            localStorage.setItem('navissi_reordenar_arrastrando', e.target.checked ? '1' : '0');
            if (e.target.checked) { alert('Activado. Recarga la página para ver los controles de arrastre.'); }
        });
    }

    // Mostrar/ocultar contraseña: delegado en document para que funcione en
    // cualquier formulario (login, cambiar contraseña, crear usuario...) sin
    // tener que repetir el listener en cada página.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-mostrar-clave');
        if (!btn) return;
        var campo = document.getElementById(btn.dataset.target);
        if (!campo) return;
        var mostrando = campo.type === 'text';
        campo.type = mostrando ? 'password' : 'text';
        btn.setAttribute('aria-label', mostrando ? 'Mostrar contraseña' : 'Ocultar contraseña');
        btn.classList.toggle('activo', !mostrando);
    });

    // Editor de texto enriquecido (WYSIWYG) reutilizable: cualquier
    // <textarea class="wysiwyg"> en cualquier formulario del sitio se
    // convierte en un editor con barra de negrita/cursiva/listas/enlaces,
    // sin depender de ninguna librería externa. Al enviar el formulario, el
    // HTML del editor se vuelve a copiar al textarea (oculto) para que el
    // backend lo reciba como siempre, sin tener que cambiar cada módulo.
    function inicializarWysiwyg() {
        document.querySelectorAll('textarea.wysiwyg:not([data-wysiwyg-listo])').forEach(function (textarea) {
            textarea.setAttribute('data-wysiwyg-listo', '1');
            textarea.style.display = 'none';

            var envoltorio = document.createElement('div');
            envoltorio.className = 'wysiwyg-envoltorio';

            var barra = document.createElement('div');
            barra.className = 'wysiwyg-barra';
            var botones = [
                ['bold', 'N', 'Negrita'], ['italic', 'I', 'Cursiva'], ['underline', 'S', 'Subrayado'],
                ['insertUnorderedList', '•', 'Lista'], ['insertOrderedList', '1.', 'Lista numerada'],
                ['createLink', '🔗', 'Insertar enlace'], ['removeFormat', '×', 'Quitar formato'],
            ];
            botones.forEach(function (b) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'wysiwyg-btn';
                btn.title = b[2];
                btn.textContent = b[1];
                btn.addEventListener('click', function () {
                    editable.focus();
                    if (b[0] === 'createLink') {
                        var url = prompt('URL del enlace:', 'https://');
                        if (url) document.execCommand('createLink', false, url);
                    } else {
                        document.execCommand(b[0], false, null);
                    }
                });
                barra.appendChild(btn);
            });

            var editable = document.createElement('div');
            editable.className = 'wysiwyg-editable';
            editable.contentEditable = 'true';
            editable.innerHTML = textarea.value || '';
            editable.setAttribute('data-placeholder', textarea.placeholder || 'Escribe aquí...');
            editable.addEventListener('input', function () { textarea.value = editable.innerHTML; });

            envoltorio.appendChild(barra);
            envoltorio.appendChild(editable);
            textarea.parentNode.insertBefore(envoltorio, textarea);

            var form = textarea.closest('form');
            if (form) form.addEventListener('submit', function () { textarea.value = editable.innerHTML; });
        });
    }
    inicializarWysiwyg();
    window.navissiWysiwygRefrescar = inicializarWysiwyg; // por si un panel se carga despues (ej. AJAX)
})();
