/**
 * WORKMANAGER ERP - UNIFIED JAVASCRIPT SYSTEM v2.0
 * ================================================
 * Consolidated JavaScript file containing all dashboard functionality
 */

// ==================== GLOBAL UTILITIES ====================

function showToast(message, type = 'info', duration = 4000) {
    if (window.__toastLock) return;
    window.__toastLock = true;
    try {
        const existingToasts = document.querySelectorAll('.toast-notification');
        existingToasts.forEach(toast => toast.remove());

        const toast = document.createElement('div');
        toast.className = `toast-notification alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = `
            top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); border: none; border-radius: 8px;
        `;

        const icons = {
            success: 'fas fa-check-circle',
            danger: 'fas fa-exclamation-triangle',
            warning: 'fas fa-exclamation-circle',
            info: 'fas fa-info-circle'
        };

        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="${icons[type] || icons.info} me-2"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;

        document.body.appendChild(toast);
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 150);
            }
        }, duration);
    } finally {
        window.__toastLock = false;
    }
}

function showLoading(message = 'Cargando...') {
    hideLoading();
    const loading = document.createElement('div');
    loading.id = 'globalLoading';
    loading.className = 'position-fixed w-100 h-100 d-flex align-items-center justify-content-center';
    loading.style.cssText = `
        top: 0; left: 0; background: rgba(0,0,0,0.7); z-index: 9998; backdrop-filter: blur(2px);
    `;
    loading.innerHTML = `
        <div class="text-center text-white">
            <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="h5">${message}</div>
        </div>
    `;
    document.body.appendChild(loading);
}

function hideLoading() {
    const loading = document.getElementById('globalLoading');
    if (loading) loading.remove();
}

function confirmAction(title, text, callback, confirmText = 'Sí, continuar', cancelText = 'Cancelar') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title, text: text, icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText, cancelButtonText: cancelText
        }).then((result) => {
            if (result.isConfirmed) callback();
        });
    } else {
        if (confirm(`${title}\n\n${text}`)) callback();
    }
}

function initializeDataTable(selector, options = {}) {
    if (!$.fn.DataTable) {
        console.warn('DataTables not loaded');
        return null;
    }
    const defaultOptions = {
        responsive: true,
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        pageLength: 25,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        order: [[0, 'asc']]
    };
    const finalOptions = { ...defaultOptions, ...options };
    if ($.fn.DataTable.isDataTable(selector)) {
        $(selector).DataTable().destroy();
    }
    return $(selector).DataTable(finalOptions);
}

function exportToExcel(tableSelector, filename = 'export') {
    const table = document.querySelector(tableSelector);
    if (!table) {
        showToast('Tabla no encontrada', 'danger');
        return;
    }
    let csv = '';
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            rowData.push('"' + col.textContent.replace(/"/g, '""') + '"');
        });
        csv += rowData.join(',') + '\n';
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${filename}.csv`;
    link.click();
    showToast('Archivo exportado', 'success');
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validateRequiredFields(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    return isValid;
}

// ==================== DASHBOARD FUNCTIONS ====================

function initializeHeader() {
    const notifBtn = document.querySelector('[onclick="toggleNotifications()"]');
    if (notifBtn) notifBtn.onclick = function () { toggleNotifications(); };

    const aiBtn = document.querySelector('[onclick="toggleAI()"]');
    if (aiBtn) aiBtn.onclick = function () { toggleAI(); };

    const userBtn = document.querySelector('[onclick="toggleUserMenu()"]');
    if (userBtn) userBtn.onclick = function () { toggleUserMenu(); };
}

function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    if (!dropdown) {
        const notifBtn = document.querySelector('.btn i.fa-bell').closest('.btn');
        const div = document.createElement('div');
        div.id = 'notificationsDropdown';
        div.className = 'dropdown-menu dropdown-menu-end show';
        div.style.cssText = 'position: absolute; top: 100%; right: 0; z-index: 1050; min-width: 350px; max-height: 400px; overflow-y: auto;';
        div.innerHTML = `
            <div class="dropdown-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Notificaciones</h6>
                <span class="badge bg-primary">3</span>
            </div>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#">
                <i class="fas fa-info-circle text-info me-2"></i>
                Sistema actualizado correctamente
                <small class="d-block text-muted">Hace 5 minutos</small>
            </a>
            <a class="dropdown-item" href="#">
                <i class="fas fa-check-circle text-success me-2"></i>
                Nuevo usuario registrado
                <small class="d-block text-muted">Hace 1 hora</small>
            </a>
            <a class="dropdown-item" href="#">
                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                Mantenimiento programado
                <small class="d-block text-muted">Hace 2 horas</small>
            </a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-center text-primary" href="#">Ver todas las notificaciones</a>
        `;
        notifBtn.parentElement.style.position = 'relative';
        notifBtn.parentElement.appendChild(div);

        setTimeout(() => {
            document.addEventListener('click', function closeDropdown(e) {
                if (!notifBtn.contains(e.target) && !div.contains(e.target)) {
                    div.remove();
                    document.removeEventListener('click', closeDropdown);
                }
            });
        }, 100);
    } else {
        dropdown.remove();
    }
}

function toggleAI() {
    const panel = document.getElementById('aiPanel');
    if (panel) {
        panel.classList.toggle('show');
        showToast(panel.classList.contains('show') ? 'Asistente IA activado' : 'Asistente IA desactivado', 'info');
    } else {
        showToast('Panel IA en desarrollo', 'warning');
    }
}

function toggleUserMenu() {
    const dropdown = document.getElementById('userMenuDropdown');
    if (!dropdown) {
        const userBtn = document.querySelector('.btn i.fa-user').closest('.btn');
        const div = document.createElement('div');
        div.id = 'userMenuDropdown';
        div.className = 'dropdown-menu dropdown-menu-end show';
        div.style.cssText = 'position: absolute; top: 100%; right: 0; z-index: 1050; min-width: 250px;';
        div.innerHTML = `
            <div class="dropdown-header">
                <div class="d-flex align-items-center">
                    <div class="avatar me-2">
                        <i class="fas fa-user-circle fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">${window.userName || 'Usuario'}</h6>
                        <small class="text-muted">${window.userRole || 'Administrador'}</small>
                    </div>
                </div>
            </div>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#" onclick="loadModule('user')">
                <i class="fas fa-user me-2"></i>Mi Perfil
            </a>
            <a class="dropdown-item" href="#" onclick="openSettings()">
                <i class="fas fa-cog me-2"></i>Configuración
            </a>
            <a class="dropdown-item" href="#" onclick="runMasterSync()">
                <i class="fas fa-sync me-2 text-info"></i>Sincronizar Datos Real
            </a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
            </a>
        `;
        userBtn.parentElement.style.position = 'relative';
        userBtn.parentElement.appendChild(div);

        setTimeout(() => {
            document.addEventListener('click', function closeDropdown(e) {
                if (!userBtn.contains(e.target) && !div.contains(e.target)) {
                    div.remove();
                    document.removeEventListener('click', closeDropdown);
                }
            });
        }, 100);
    } else {
        dropdown.remove();
    }
}

function configureWidgets() {
    const modal = document.getElementById('widgetConfigModal');
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
}

function toggleWidget(widgetId) {
    const widget = document.getElementById(widgetId);
    if (widget) {
        widget.style.display = widget.style.display === 'none' ? 'block' : 'none';
        localStorage.setItem(`widget_${widgetId}_visible`, widget.style.display !== 'none');
        showToast(`Widget ${widget.style.display === 'none' ? 'ocultado' : 'mostrado'}`, 'success');
    }
}

function updateBreadcrumb(moduleName) {
    const breadcrumb = document.getElementById('breadcrumb');
    if (!breadcrumb) return;

    const moduleData = {
        'dashboard': { title: 'Dashboard', icon: 'fas fa-home' },
        'inventario': { title: 'Inventario', icon: 'fas fa-boxes' },
        'licencias': { title: 'Licencias', icon: 'fas fa-key' },
        'usuarios': { title: 'Usuarios', icon: 'fas fa-users' },
        'tickets': { title: 'Tickets', icon: 'fas fa-ticket-alt' },
        'sedes': { title: 'Sedes', icon: 'fas fa-building' },
        'empleados': { title: 'Empleados', icon: 'fas fa-user-tie' },
        'ajustes': { title: 'Ajustes', icon: 'fas fa-cog' },
        'mesa-ayuda': { title: 'Mesa de Ayuda', icon: 'fas fa-headset' },
        'medico': { title: 'Gestión Médica', icon: 'fas fa-user-md' }
    };

    const data = moduleData[moduleName] || { title: moduleName.charAt(0).toUpperCase() + moduleName.slice(1), icon: 'fas fa-folder' };

    breadcrumb.innerHTML = `
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="fas fa-home me-1"></i>Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">
            <i class="${data.icon} me-1"></i>${data.title}
        </li>
    `;
}

let fabMenuOpen = false;

function toggleQuickActions() {
    const menu = document.getElementById('fabMenu');
    if (!menu) {
        createFabMenu();
    } else {
        fabMenuOpen = !fabMenuOpen;
        menu.style.display = fabMenuOpen ? 'block' : 'none';
    }
}

function createFabMenu() {
    const fab = document.querySelector('.quick-actions-btn').parentElement;
    const menu = document.createElement('div');
    menu.id = 'fabMenu';
    menu.className = 'fab-menu';
    menu.style.cssText = 'position: absolute; bottom: 70px; right: 0; z-index: 1040;';
    menu.innerHTML = `
        <div class="list-group shadow">
            <a href="#" class="list-group-item list-group-item-action" onclick="showToast('Nuevo ticket', 'info')">
                <i class="fas fa-ticket-alt me-2"></i>Nuevo Ticket
            </a>
            <a href="#" class="list-group-item list-group-item-action" onclick="loadModule('inventario')">
                <i class="fas fa-box me-2"></i>Agregar Equipo
            </a>
            <a href="#" class="list-group-item list-group-item-action" onclick="loadModule('usuarios')">
                <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
            </a>
            <a href="#" class="list-group-item list-group-item-action" onclick="configureWidgets()">
                <i class="fas fa-cog me-2"></i>Configurar Widgets
            </a>
        </div>
    `;
    fab.style.position = 'relative';
    fab.appendChild(menu);
    fabMenuOpen = true;

    setTimeout(() => {
        document.addEventListener('click', function closeFab(e) {
            if (!fab.contains(e.target)) {
                menu.style.display = 'none';
                fabMenuOpen = false;
                document.removeEventListener('click', closeFab);
            }
        });
    }, 100);
}

function openSettings() {
    const modal = document.getElementById('settingsModal');
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
}

async function refreshDashboard() {
    showLoading();
    try {
        const response = await fetch('api/dashboard/stats.php');
        const data = await response.json();

        if (data.usuarios) document.getElementById('stat-usuarios').textContent = data.usuarios;
        if (data.equipos) document.getElementById('stat-equipos').textContent = data.equipos;
        if (data.tickets) document.getElementById('stat-tickets').textContent = data.tickets;
        if (data.sedes) document.getElementById('stat-sedes').textContent = data.sedes;

        showToast('Dashboard actualizado', 'success');
    } catch (error) {
        showToast('Error al actualizar dashboard', 'danger');
    } finally {
        hideLoading();
    }
}

async function runMasterSync() {
    if (!confirm('¿Desea iniciar la sincronización maestra de datos? Esto actualizará sedes, empleados, inventario y licencias.')) return;

    showLoading('Sincronizando datos reales...');
    try {
        const response = await fetch('api/admin/master_sync.php');
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                title: 'Sincronización Exitosa',
                html: `<pre style="text-align: left; font-size: 0.8rem;">${data.output}</pre>`,
                icon: 'success',
                confirmButtonText: 'Genial'
            });
            const currentModule = new URLSearchParams(window.location.search).get('module');
            if (currentModule) loadModule(currentModule);
            refreshDashboard();
        } else {
            Swal.fire('Error', data.message || 'Error en la sincronización', 'error');
        }
    } catch (error) {
        showToast('Error de conexión con el servidor', 'danger');
    } finally {
        hideLoading();
    }
}
// ==================== ENHANCED SEARCH SYSTEM ====================

class BuscadorUniversalIntegrado {
    constructor() {
        this.searchTimeout = null;
        this.currentRequest = null;
        this.init();
    }

    init() {
        const input = document.getElementById('buscador-universal-input');
        const results = document.getElementById('buscador-universal-results');

        if (!input || !results) return;

        input.addEventListener('input', (e) => this.handleSearch(e.target.value));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.hideResults();
            if (e.key === 'Enter') e.preventDefault();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-container')) {
                this.hideResults();
            }
        });

        const closeBtn = results.querySelector('.close-results');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hideResults());
        }
    }

    handleSearch(term) {
        if (this.searchTimeout) clearTimeout(this.searchTimeout);
        if (this.currentRequest) this.currentRequest.abort();

        if (term.length < 2) {
            this.hideResults();
            return;
        }

        this.searchTimeout = setTimeout(() => this.performSearch(term), 300);
    }

    async performSearch(term) {
        this.showLoading();
        const controller = new AbortController();
        this.currentRequest = controller;

        try {
            const body = new URLSearchParams({
                action: 'buscar_universal',
                termino: term
            }).toString();

            const response = await fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body,
                signal: controller.signal
            });
            const res = await response.json();

            if (res.success) {
                const normalized = this.normalizeResults(res);
                this.displayResults(normalized, normalized.length, term);
            } else {
                this.showError('Error en la busqueda');
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Error en busqueda:', error);
                this.showError('Error de conexion');
            }
        } finally {
            this.currentRequest = null;
        }
    }

    normalizeResults(res) {
        const raw = Array.isArray(res.resultados) ? res.resultados : (Array.isArray(res.data) ? res.data : []);
        return raw.map(item => this.normalizeItem(item)).filter(Boolean);
    }

    normalizeItem(item) {
        if (!item || typeof item !== 'object') return null;

        if (item.tipo_elemento) {
            const title = item.titulo || '';
            const subtitle = item.descripcion || '';
            return {
                type: item.tipo_elemento,
                id: item.elemento_id || item.id || 0,
                title,
                title_plain: this.stripTags(title),
                subtitle,
                subtitle_plain: this.stripTags(subtitle),
                info: item.sede_nombre || item.usuario_nombre || '',
                icon_class: item.icono || this.getTypeIcon(item.tipo_elemento),
                url: item.url_acceso || ''
            };
        }

        if (item.type) {
            const title = item.title || '';
            const subtitle = item.subtitle || '';
            return {
                type: item.type,
                id: item.id || 0,
                title,
                title_plain: this.stripTags(title),
                subtitle,
                subtitle_plain: this.stripTags(subtitle),
                info: item.info || '',
                icon_class: item.icon_class || this.getTypeIcon(item.type),
                url: item.url || ''
            };
        }

        return null;
    }

    displayResults(resultados, total, term) {
        const resultsContainer = document.getElementById('buscador-universal-results');

        if (!resultsContainer) {
            console.error('Results container not found');
            return;
        }

        let resultsContent = resultsContainer.querySelector('.search-results-content');
        let resultsCount = resultsContainer.querySelector('.results-count');

        if (!resultsContent) {
            resultsContainer.innerHTML = `
                <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                    <small class="text-muted results-count"></small>
                    <button type="button" class="btn-close btn-sm close-results" aria-label="Close"></button>
                </div>
                <div class="search-results-content" style="max-height: 400px; overflow-y: auto;"></div>
            `;
            resultsContent = resultsContainer.querySelector('.search-results-content');
            resultsCount = resultsContainer.querySelector('.results-count');

            resultsContainer.querySelector('.close-results').addEventListener('click', () => this.hideResults());
        }

        resultsCount.textContent = `${total} resultado${total !== 1 ? 's' : ''} para "${term}"`;
        resultsContent.innerHTML = '';

        if (resultados.length === 0) {
            resultsContent.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <p class="text-muted mb-0">No se encontraron resultados para "${term}"</p>
                </div>
            `;
        } else {
            const grouped = this.groupByType(resultados);

            Object.keys(grouped).forEach(tipo => {
                const items = grouped[tipo];
                const section = document.createElement('div');
                section.className = 'result-section';
                section.innerHTML = `
                    <div class="bg-light px-3 py-1 text-uppercase small fw-bold text-muted sticky-top">
                        ${this.getTypeName(tipo)} (${items.length})
                    </div>
                    ${items.map(item => this.createResultItem(item)).join('')}
                `;
                resultsContent.appendChild(section);
            });
        }

        resultsContent.querySelectorAll('.buscador-result').forEach(item => {
            item.addEventListener('click', (event) => {
                event.preventDefault();
                this.openResult(item.dataset.type, item.dataset.id, item.dataset.title, item.dataset.url);
            });
        });

        this.showResults();
    }

    createResultItem(item) {
        const safeTitle = this.escapeAttr(item.title_plain || item.title || '');
        const safeUrl = this.escapeAttr(item.url || '');
        return `
            <a href="#" class="d-flex align-items-center p-2 border-bottom text-decoration-none text-dark hover-bg-light buscador-result"
               data-type="${item.type}" data-id="${item.id}" data-title="${safeTitle}" data-url="${safeUrl}">
                <div class="me-3 text-center" style="width: 30px;">
                    <i class="${item.icon_class || this.getTypeIcon(item.type)} text-primary"></i>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-bold text-truncate">${item.title}</div>
                    <div class="small text-muted text-truncate">
                        ${item.subtitle}
                        ${item.info ? `<span class="badge bg-light text-dark border ms-1">${item.info}</span>` : ''}
                    </div>
                </div>
            </a>
        `;
    }

    groupByType(resultados) {
        return resultados.reduce((groups, item) => {
            const type = item.type;
            if (!groups[type]) groups[type] = [];
            groups[type].push(item);
            return groups;
        }, {});
    }

    getTypeIcon(tipo) {
        const icons = {
            'usuario': 'fas fa-user',
            'empleado': 'fas fa-user',
            'equipo': 'fas fa-desktop',
            'ticket': 'fas fa-ticket-alt',
            'sede': 'fas fa-building',
            'licencia': 'fas fa-key',
            'documento': 'fas fa-file-alt',
            'qr': 'fas fa-qrcode',
            'hostname': 'fas fa-server',
            'serial': 'fas fa-barcode'
        };
        return icons[tipo] || 'fas fa-search';
    }

    getTypeName(tipo) {
        const names = {
            'usuario': 'Usuarios',
            'empleado': 'Empleados',
            'equipo': 'Equipos',
            'ticket': 'Tickets',
            'sede': 'Sedes',
            'licencia': 'Licencias',
            'documento': 'Documentos',
            'qr': 'Codigos QR'
        };
        return names[tipo] || tipo.charAt(0).toUpperCase() + tipo.slice(1);
    }

    openResult(tipo, id, title = '', url = '') {
        this.hideResults();

        console.log(`Opening ${tipo} #${id}`);
        const type = (tipo || '').toLowerCase();

        switch (type) {
            case 'usuario':
                loadModule('usuarios');
                break;
            case 'empleado':
                loadModule('empleados');
                setTimeout(() => { if (typeof editEmpleado === 'function') editEmpleado(id); }, 1000);
                break;
            case 'equipo':
                loadModule('inventario');
                setTimeout(() => { if (typeof viewEquipment === 'function') viewEquipment(id); }, 1000);
                break;
            case 'ticket':
                loadModule('tickets');
                setTimeout(() => { if (typeof viewTicket === 'function') viewTicket(id); }, 1000);
                break;
            case 'sede':
                loadModule('sedes');
                setTimeout(() => {
                    const input = document.getElementById('searchSede');
                    if (input) {
                        input.value = (title || '').trim();
                        if (typeof filterTable === 'function') {
                            filterTable();
                        } else if (typeof loadSedes === 'function') {
                            loadSedes();
                        }
                    }
                }, 600);
                break;
            case 'licencia':
                loadModule('licencias');
                break;
            case 'documento':
                loadModule('documentacion');
                break;
            default:
                if (url) {
                    window.location.href = url;
                } else {
                    showToast('Navegacion a elemento en desarrollo', 'info');
                }
        }
    }

    stripTags(value) {
        if (!value) return '';
        return String(value).replace(/<[^>]*>/g, '');
    }

    escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    showLoading() {
        const resultsContainer = document.getElementById('buscador-universal-results');
        if (!resultsContainer) return;

        if (!resultsContainer.querySelector('.search-results-content')) {
            resultsContainer.innerHTML = '<div class="search-results-content"></div>';
        }

        const resultsContent = resultsContainer.querySelector('.search-results-content');
        resultsContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2 mb-0">Buscando...</p>
            </div>
        `;
        this.showResults();
    }

    showError(message) {
        const resultsContent = document.querySelector('#buscador-universal-results .search-results-content');
        if (resultsContent) {
            resultsContent.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                    <p class="mb-0">${message}</p>
                </div>
            `;
        }
        this.showResults();
    }

    showResults() {
        const container = document.getElementById('buscador-universal-results');
        if (container) {
            container.style.display = 'block';
            container.classList.add('show');
        }
    }

    hideResults() {
        const container = document.getElementById('buscador-universal-results');
        if (container) {
            container.style.display = 'none';
            container.classList.remove('show');
        }
    }
}
// ==================== QR CODE & UTILITY FUNCTIONS ====================

function generarQRElemento(tipo, id) {
    showToast('Generando QR...', 'info');
    setTimeout(() => {
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${tipo}:${id}`;
        mostrarQRModal(qrUrl);
    }, 500);
}

function mostrarQRModal(qrUrl) {
    const modalId = 'qrModal';
    let modalEl = document.getElementById(modalId);
    if (modalEl) modalEl.remove();

    const modalHtml = `
    <div class="modal fade" id="${modalId}" tabindex="-1">
        <div class="modal-dialog modal-sm text-center">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Código QR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <img src="${qrUrl}" class="img-fluid mb-3" alt="QR Code">
                    <div class="d-grid">
                        <button class="btn btn-primary" onclick="imprimirQR('${qrUrl}')">
                            <i class="fas fa-print me-2"></i>Imprimir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

function imprimirQR(imagenUrl) {
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html>
        <head><title>Imprimir QR</title></head>
        <body style="text-align:center; padding-top: 50px;">
            <h3>WorkManager ERP</h3>
            <img src="${imagenUrl}" style="width: 200px; height: 200px;">
            <p>Generado el ${new Date().toLocaleString()}</p>
            <script>window.print(); setTimeout(() => window.close(), 1000);</script>
        </body>
        </html>
    `);
    ventana.document.close();
}

function verHojaVida(tipo, id) {
    if (tipo === 'equipo' && typeof viewEquipment === 'function') {
        viewEquipment(id);
    } else {
        showToast('Vista detallada no disponible para este elemento', 'warning');
    }
}

function descargarAgente() {
    const modalId = 'agenteModal';
    let modalEl = document.getElementById(modalId);
    if (modalEl) modalEl.remove();

    const modalHtml = `
    <div class="modal fade" id="${modalId}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Descargar Agente Remoto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Seleccione la plataforma para descargar el instalador del agente:</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="descargarAgenteEspecifico('windows')">
                            <i class="fab fa-windows me-2"></i>Windows (PowerShell)
                        </button>
                        <button class="btn btn-dark" onclick="descargarAgenteEspecifico('linux')">
                            <i class="fab fa-linux me-2"></i>Linux (Bash)
                        </button>
                        <button class="btn btn-secondary" onclick="descargarAgenteEspecifico('macos')">
                            <i class="fab fa-apple me-2"></i>macOS (Bash)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

function descargarAgenteEspecifico(plataforma) {
    const link = document.createElement('a');
    const ext = plataforma === 'windows' ? 'ps1' : 'sh';
    link.href = `api/agent/download.php?os=${encodeURIComponent(plataforma)}`;
    link.download = `workmanager-agent-${plataforma}.${ext}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    const modal = bootstrap.Modal.getInstance(document.getElementById('agenteModal'));
    if (modal) modal.hide();

    showToast('Descarga iniciada. Ejecuta el script como administrador.', 'success');
}

// ==================== MULTILINGUAL SUPPORT ====================

const translations = {
    es: {
        'Dashboard': 'Panel Principal',
        'Users': 'Usuarios',
        'Equipment': 'Equipos',
        'Tickets': 'Tickets',
        'Locations': 'Sedes',
        'Settings': 'Configuración',
        'Logout': 'Cerrar Sesión',
        'My Profile': 'Mi Perfil',
        'Notifications': 'Notificaciones',
        'Search': 'Buscar',
        'Quick Actions': 'Acciones Rápidas'
    },
    en: {
        'Panel Principal': 'Dashboard',
        'Usuarios': 'Users',
        'Equipos': 'Equipment',
        'Sedes': 'Locations',
        'Configuración': 'Settings',
        'Cerrar Sesión': 'Logout',
        'Mi Perfil': 'My Profile',
        'Notificaciones': 'Notifications',
        'Buscar': 'Search',
        'Acciones Rápidas': 'Quick Actions'
    }
};

let currentLanguage = 'es';

function translate(text, targetLang = null) {
    const lang = targetLang || currentLanguage;
    return translations[lang]?.[text] || text;
}

function changeLanguage(lang) {
    currentLanguage = lang;
    localStorage.setItem('dashboard_language', lang);

    document.querySelectorAll('[data-translate]').forEach(el => {
        const key = el.getAttribute('data-translate');
        el.textContent = translate(key, lang);
    });

    showToast(`Idioma cambiado a ${lang === 'es' ? 'Español' : 'English'}`, 'success');
}

// ==================== MODERN COMPONENTS SYSTEM ====================

class ModernComponentsSystem {
    constructor() {
        this.components = new Map();
        this.themes = {
            light: {
                primary: '#6366f1', secondary: '#64748b', success: '#10b981',
                warning: '#f59e0b', danger: '#ef4444', info: '#3b82f6',
                background: '#ffffff', surface: '#f8fafc', text: '#1e293b'
            },
            dark: {
                primary: '#818cf8', secondary: '#94a3b8', success: '#34d399',
                warning: '#fbbf24', danger: '#f87171', info: '#60a5fa',
                background: '#0f172a', surface: '#1e293b', text: '#f8fafc'
            }
        };
        this.currentTheme = 'light';
        this.init();
    }

    init() {
        this.loadTheme();
        this.initializeComponents();
        this.setupEventListeners();
    }

    loadTheme() {
        const savedTheme = localStorage.getItem('workmanager-theme') || 'light';
        this.setTheme(savedTheme);
    }

    setTheme(theme) {
        this.currentTheme = theme;
        const colors = this.themes[theme];

        Object.entries(colors).forEach(([key, value]) => {
            document.documentElement.style.setProperty(`--color-${key}`, value);
        });

        document.body.className = document.body.className.replace(/theme-\w+/g, '');
        document.body.classList.add(`theme-${theme}`);

        localStorage.setItem('workmanager-theme', theme);
        this.emit('themeChanged', { theme, colors });
    }

    registerComponent(name, componentClass) {
        this.components.set(name, componentClass);
    }

    createComponent(name, element, options = {}) {
        const ComponentClass = this.components.get(name);
        if (!ComponentClass) {
            console.warn(`Component ${name} not found`);
            return null;
        }
        return new ComponentClass(element, options);
    }

    emit(event, data) {
        document.dispatchEvent(new CustomEvent(`modern-components:${event}`, { detail: data }));
    }

    on(event, callback) {
        document.addEventListener(`modern-components:${event}`, callback);
    }

    initializeComponents() {
        document.querySelectorAll('[data-component]').forEach(element => {
            const componentName = element.dataset.component;
            const options = element.dataset.options ? JSON.parse(element.dataset.options) : {};
            this.createComponent(componentName, element, options);
        });
    }

    setupEventListeners() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-theme-toggle]')) {
                const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
                this.setTheme(newTheme);
            }
        });
    }
}

class ModernCard {
    constructor(element, options = {}) {
        this.element = element;
        this.options = {
            hover: true, glow: false, tilt: false, gradient: false, ...options
        };
        this.init();
    }

    init() {
        this.element.classList.add('modern-card');
        if (this.options.hover) this.setupHoverEffects();
        if (this.options.tilt) this.setupTiltEffect();
        if (this.options.gradient) this.setupGradientEffect();
        if (this.options.glow) this.setupGlowEffect();
    }

    setupHoverEffects() {
        this.element.addEventListener('mouseenter', () => {
            this.element.style.transform = 'translateY(-8px) scale(1.02)';
            this.element.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.15)';
        });

        this.element.addEventListener('mouseleave', () => {
            this.element.style.transform = 'translateY(0) scale(1)';
            this.element.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
    }

    setupTiltEffect() {
        this.element.addEventListener('mousemove', (e) => {
            const rect = this.element.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;

            const rotateX = (y / rect.height) * 10;
            const rotateY = (x / rect.width) * 10;

            this.element.style.transform = `perspective(1000px) rotateX(${-rotateX}deg) rotateY(${rotateY}deg)`;
        });

        this.element.addEventListener('mouseleave', () => {
            this.element.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
        });
    }

    setupGradientEffect() {
        this.element.style.background = 'linear-gradient(135deg, var(--color-primary), var(--color-secondary))';
        this.element.style.color = 'white';
    }

    setupGlowEffect() {
        this.element.addEventListener('mouseenter', () => {
            this.element.style.boxShadow = '0 0 30px var(--color-primary)';
        });

        this.element.addEventListener('mouseleave', () => {
            this.element.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
    }
}

class ModernNotification {
    constructor() {
        this.container = this.createContainer();
        this.notifications = [];
    }

    createContainer() {
        const container = document.createElement('div');
        container.className = 'modern-notifications';
        container.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 10000;
            display: flex; flex-direction: column; gap: 12px; max-width: 400px;
        `;
        document.body.appendChild(container);
        return container;
    }

    show(message, type = 'info', options = {}) {
        const notification = document.createElement('div');
        const id = Date.now() + Math.random();

        notification.className = `modern-notification modern-notification--${type}`;
        notification.style.cssText = `
            padding: 16px 20px; border-radius: 12px; background: var(--color-surface);
            border: 1px solid var(--color-${type}); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px); transform: translateX(100%); transition: all 0.3s ease; cursor: pointer;
        `;

        notification.innerHTML = `
            <div class="modern-notification__content">
                <div class="modern-notification__message">${message}</div>
                ${options.action ? `<button class="modern-notification__action">${options.action}</button>` : ''}
            </div>
            <button class="modern-notification__close">&times;</button>
        `;

        this.container.appendChild(notification);

        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(0)';
        });

        const duration = options.duration || 5000;
        const timeoutId = setTimeout(() => {
            this.remove(notification);
        }, duration);

        notification.querySelector('.modern-notification__close').addEventListener('click', () => {
            clearTimeout(timeoutId);
            this.remove(notification);
        });

        this.notifications.push({ id, element: notification, timeoutId });
        return id;
    }

    remove(notification) {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    success(message, options = {}) { return this.show(message, 'success', options); }
    error(message, options = {}) { return this.show(message, 'danger', options); }
    warning(message, options = {}) { return this.show(message, 'warning', options); }
    info(message, options = {}) { return this.show(message, 'info', options); }
}

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', function () {
    console.log('WorkManager ERP Unified System v2.0 loaded');

    // Initialize header functionality
    initializeHeader();

    // Initialize universal search
    window.buscadorUniversal = new BuscadorUniversalIntegrado();

    // Initialize modern components system
    window.modernComponents = new ModernComponentsSystem();

    // Initialize global notification system
    window.modernNotifications = new ModernNotification();

    // Load widget preferences from localStorage
    const widgets = document.querySelectorAll('[data-widget]');
    widgets.forEach(widget => {
        const visible = localStorage.getItem(`widget_${widget.id}_visible`);
        if (visible === 'false') {
            widget.style.display = 'none';
        }
    });

    // Load saved language
    const savedLang = localStorage.getItem('dashboard_language');
    if (savedLang) {
        changeLanguage(savedLang);
    }

    // Register modern components
    modernComponents.registerComponent('card', ModernCard);

    console.log('All systems initialized successfully');
});

// ==================== GLOBAL EXPORTS ====================

// Make functions globally available
window.showToast = showToast;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.confirmAction = confirmAction;
window.initializeDataTable = initializeDataTable;
window.exportToExcel = exportToExcel;
window.isValidEmail = isValidEmail;
window.validateRequiredFields = validateRequiredFields;
window.toggleNotifications = toggleNotifications;
window.toggleAI = toggleAI;
window.toggleUserMenu = toggleUserMenu;
window.configureWidgets = configureWidgets;
window.toggleWidget = toggleWidget;
window.updateBreadcrumb = updateBreadcrumb;
window.toggleQuickActions = toggleQuickActions;
window.openSettings = openSettings;
window.refreshDashboard = refreshDashboard;
window.runMasterSync = runMasterSync;
window.generarQRElemento = generarQRElemento;
window.mostrarQRModal = mostrarQRModal;
window.imprimirQR = imprimirQR;
window.verHojaVida = verHojaVida;
window.descargarAgente = descargarAgente;
window.descargarAgenteEspecifico = descargarAgenteEspecifico;
window.translate = translate;
window.changeLanguage = changeLanguage;
window.ModernComponents = modernComponents;
window.ModernCard = ModernCard;
