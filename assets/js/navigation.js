/**
 * NAVIGATION SYSTEM - Sistema de Navegación Unificado
 * ===================================================
 * Sistema de navegación que trabaja con el router de WorkManager ERP
 */

class NavigationSystem {
    constructor() {
        this.currentModule = null;
        this.breadcrumbs = [];
        this.navigationHistory = [];
        this.favorites = this.loadFavorites();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeNavigation();
        this.setupBreadcrumbs();
        this.setupQuickAccess();
    }

    setupEventListeners() {
        // Escuchar cambios de ruta
        window.addEventListener('routechange', (e) => {
            this.handleRouteChange(e.detail.route);
        });

        // Navegación por teclado
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardNavigation(e);
        });

        // Búsqueda rida
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.openQuickSearch();
            }
        });

        // Favoritos
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-nav-favorite]')) {
                e.preventDefault();
                this.toggleFavorite(e.target.dataset.navFavorite);
            }
        });
    }

    initializeNavigation() {
        this.createMainNavigation();
        this.createModuleNavigation();
        this.createUserNavigation();
    }

    createMainNavigation() {
        const mainNav = document.getElementById('mainNavigation');
        if (!mainNav) return;

        const navigationItems = [
            {
                id: 'dashboard',
                title: 'Dashboard',
                icon: 'fas fa-tachometer-alt',
                url: '/dashboard',
                active: true
            },
            {
                id: 'modules',
                title: 'Módulos',
                icon: 'fas fa-th-large',
                children: this.getModuleNavigation()
            },
            {
                id: 'admin',
                title: 'Administración',
                icon: 'fas fa-cog',
                url: '/admin',
                permissions: ['Administrador', 'Super Admin']
            },
            {
                id: 'reports',
                title: 'Reportes',
                icon: 'fas fa-chart-bar',
                url: '/modules/reportes'
            },
            {
                id: 'search',
                title: 'Búsqueda',
                icon: 'fas fa-search',
                url: '/search'
            }
        ];

        this.renderNavigation(mainNav, navigationItems);
    }

    getModuleNavigation() {
        const moduleCategories = {
            'Inventario y Equipos': [
                { id: 'inventario', title: 'Inventario', icon: 'fas fa-boxes' },
                { id: 'equipos', title: 'Equipos', icon: 'fas fa-desktop' },
                { id: 'biomedica', title: 'Biomédica', icon: 'fas fa-heartbeat' },
                { id: 'sistemas', title: 'Sistemas', icon: 'fas fa-server' }
            ],
            'Recursos Humanos': [
                { id: 'empleados', title: 'Empleados', icon: 'fas fa-users' },
                { id: 'nomina', title: 'Nómina', icon: 'fas fa-money-bill' },
                { id: 'vacaciones', title: 'Vacaciones', icon: 'fas fa-calendar-alt' },
                { id: 'capacitaciones', title: 'Capacitaciones', icon: 'fas fa-graduation-cap' },
                { id: 'evaluaciones', title: 'Evaluaciones', icon: 'fas fa-star' },
                { id: 'gestion-humana', title: 'Gestión Humana', icon: 'fas fa-user-tie' },
                { id: 'hoja-vida', title: 'Hoja de Vida', icon: 'fas fa-file-alt' },
                { id: 'permisos-laborales', title: 'Permisos', icon: 'fas fa-calendar-check' },
                { id: 'asistencia', title: 'Asistencia', icon: 'fas fa-clock' }
            ],
            'Salud y Medicina': [
                { id: 'citas', title: 'Citas Médicas', icon: 'fas fa-calendar-plus' },
                { id: 'historia-clinica', title: 'Historia Clínica', icon: 'fas fa-file-medical' },
                { id: 'enfermeria', title: 'Enfermería', icon: 'fas fa-user-nurse' },
                { id: 'medico', title: 'Médico', icon: 'fas fa-user-md' },
                { id: 'farmacia', title: 'Farmacia', icon: 'fas fa-pills' },
                { id: 'sst', title: 'SST', icon: 'fas fa-hard-hat' }
            ],
            'Administración': [
                { id: 'usuarios', title: 'Usuarios', icon: 'fas fa-user-cog' },
                { id: 'sedes', title: 'Sedes', icon: 'fas fa-building' },
                { id: 'contratos', title: 'Contratos', icon: 'fas fa-file-contract' },
                { id: 'juridico', title: 'Jurídico', icon: 'fas fa-gavel' },
                { id: 'tesoreria', title: 'Tesorería', icon: 'fas fa-coins' },
                { id: 'logistica', title: 'Logística', icon: 'fas fa-truck' }
            ],
            'Soporte y Tickets': [
                { id: 'tickets', title: 'Tickets', icon: 'fas fa-ticket-alt' },
                { id: 'mesa-ayuda', title: 'Mesa de Ayuda', icon: 'fas fa-headset' },
                { id: 'soporte', title: 'Soporte', icon: 'fas fa-tools' },
                { id: 'servicio-cliente', title: 'Servicio al Cliente', icon: 'fas fa-phone' }
            ],
            'Integraciones': [
                { id: 'office365', title: 'Office 365', icon: 'fab fa-microsoft' },
                { id: 'powerbi', title: 'Power BI', icon: 'fas fa-chart-line' },
                { id: 'whatsapp', title: 'WhatsApp', icon: 'fab fa-whatsapp' },
                { id: 'n8n-automation', title: 'N8N', icon: 'fas fa-robot' },
                { id: 'ai-automation', title: 'IA', icon: 'fas fa-brain' },
                { id: 'mikrotik', title: 'MikroTik', icon: 'fas fa-wifi' },
                { id: 'vpn-management', title: 'VPN', icon: 'fas fa-shield-alt' }
            ],
            'Herramientas': [
                { id: 'buscador-universal', title: 'Búsqueda Universal', icon: 'fas fa-search' },
                { id: 'exportador-universal', title: 'Exportador', icon: 'fas fa-download' },
                { id: 'importador-universal', title: 'Importador', icon: 'fas fa-upload' },
                { id: 'qr-codes', title: 'Códigos QR', icon: 'fas fa-qrcode' },
                { id: 'huelleros', title: 'Huelleros', icon: 'fas fa-fingerprint' },
                { id: 'autodiscovery', title: 'Auto Discovery', icon: 'fas fa-search-plus' },
                { id: 'diagnostico', title: 'Diagnóstico', icon: 'fas fa-stethoscope' }
            ]
        };

        const moduleNav = [];

        for (const [category, modules] of Object.entries(moduleCategories)) {
            moduleNav.push({
                id: category.toLowerCase().replace(/\s+/g, '-'),
                title: category,
                icon: 'fas fa-folder',
                children: modules.map(module => ({
                    ...module,
                    url: `/modules/${module.id}`,
                    favorite: this.favorites.includes(module.id)
                }))
            });
        }

        return moduleNav;
    }

    renderNavigation(container, items) {
        const html = items.map(item => this.renderNavigationItem(item)).join('');
        container.innerHTML = `<ul class="nav flex-column">${html}</ul>`;
    }

    renderNavigationItem(item) {
        const hasChildren = item.children && item.children.length > 0;
        const isActive = this.isActiveItem(item);
        const canAccess = this.canAccessItem(item);

        if (!canAccess) return '';

        let html = `
            <li class="nav-item ${hasChildren ? 'has-children' : ''}">
                <a class="nav-link ${isActive ? 'active' : ''}"
                   href="${item.url || '#'}"
                   ${hasChildren ? 'data-bs-toggle="collapse" data-bs-target="#nav-' + item.id + '"' : ''}
                   ${item.favorite ? 'data-nav-favorite="' + item.id + '"' : ''}>
                    <i class="${item.icon} me-2"></i>
                    <span>${item.title}</span>
                    ${hasChildren ? '<i class="fas fa-chevron-down ms-auto"></i>' : ''}
                    ${item.favorite ? '<i class="fas fa-star text-warning ms-1"></i>' : ''}
                </a>
        `;

        if (hasChildren) {
            html += `
                <div class="collapse ${isActive ? 'show' : ''}" id="nav-${item.id}">
                    <ul class="nav flex-column ms-3">
                        ${item.children.map(child => this.renderNavigationItem(child)).join('')}
                    </ul>
                </div>
            `;
        }

        html += '</li>';
        return html;
    }

    isActiveItem(item) {
        if (!window.wmRouter || !window.wmRouter.getCurrentRoute()) return false;

        const currentPath = window.wmRouter.getCurrentRoute().matchedPath;
        return currentPath === item.url ||
               (item.children && item.children.some(child => currentPath === child.url));
    }

    canAccessItem(item) {
        if (!item.permissions || item.permissions.length === 0) return true;

        const userRole = this.getCurrentUserRole();
        return item.permissions.includes(userRole);
    }

    getCurrentUserRole() {
        // Obtener rol del usuario actual
        return window.authManager ? window.authManager.getCurrentUser()?.role : 'Usuario';
    }

    handleRouteChange(route) {
        this.currentModule = route.module || null;
        this.updateBreadcrumbs(route);
        this.updateActiveNavigation(route);
        this.addToHistory(route);
    }

    updateBreadcrumbs(route) {
        const breadcrumbContainer = document.getElementById('breadcrumbs');
        if (!breadcrumbContainer) return;

        this.breadcrumbs = this.generateBreadcrumbs(route);

        const html = this.breadcrumbs.map((crumb, index) => {
            const isLast = index === this.breadcrumbs.length - 1;
            return `
                <li class="breadcrumb-item ${isLast ? 'active' : ''}">
                    ${isLast ? crumb.title : `<a href="${crumb.url}">${crumb.title}</a>`}
                </li>
            `;
        }).join('');

        breadcrumbContainer.innerHTML = `
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    ${html}
                </ol>
            </nav>
        `;
    }

    generateBreadcrumbs(route) {
        const breadcrumbs = [
            { title: 'Inicio', url: '/dashboard' }
        ];

        if (route.module) {
            breadcrumbs.push({
                title: 'Módulos',
                url: '/modules'
            });

            breadcrumbs.push({
                title: this.formatModuleName(route.module),
                url: `/modules/${route.module}`
            });

            if (route.action && route.action !== 'dashboard') {
                const actionTitles = {
                    'list': 'Lista',
                    'create': 'Crear',
                    'edit': 'Editar',
                    'view': 'Ver'
                };

                breadcrumbs.push({
                    title: actionTitles[route.action] || route.action,
                    url: route.matchedPath
                });
            }
        } else if (route.path !== '/' && route.path !== '/dashboard') {
            breadcrumbs.push({
                title: route.title,
                url: route.matchedPath
            });
        }

        return breadcrumbs;
    }

    updateActiveNavigation(route) {
        // Remover clases activas
        document.querySelectorAll('.nav-link.active').forEach(link => {
            link.classList.remove('active');
        });

        // Agregar clase activa al elemento actual
        const currentLink = document.querySelector(`[href="${route.matchedPath}"]`);
        if (currentLink) {
            currentLink.classList.add('active');

            // Expandir padre si es necesario
            const parentCollapse = currentLink.closest('.collapse');
            if (parentCollapse) {
                parentCollapse.classList.add('show');
            }
        }
    }

    addToHistory(route) {
        this.navigationHistory.unshift({
            route: route,
            timestamp: Date.now(),
            title: route.title
        });

        // Mantener solo los últimos 50 elementos
        if (this.navigationHistory.length > 50) {
            this.navigationHistory = this.navigationHistory.slice(0, 50);
        }

        this.updateHistoryUI();
    }

    updateHistoryUI() {
        const historyContainer = document.getElementById('navigationHistory');
        if (!historyContainer) return;

        const recentItems = this.navigationHistory.slice(0, 10);

        const html = recentItems.map(item => `
            <a href="${item.route.matchedPath}" class="dropdown-item">
                <i class="fas fa-history me-2"></i>
                ${item.title}
                <small class="text-muted d-block">${this.formatTime(item.timestamp)}</small>
            </a>
        `).join('');

        historyContainer.innerHTML = html;
    }

    handleKeyboardNavigation(e) {
        // Alt + H: Ir al inicio
        if (e.altKey && e.key === 'h') {
            e.preventDefault();
            window.navigateTo('/dashboard');
        }

        // Alt + B: Ir atrás
        if (e.altKey && e.key === 'b') {
            e.preventDefault();
            window.goBack();
        }

        // Alt + M: Abrir menú de módulos
        if (e.altKey && e.key === 'm') {
            e.preventDefault();
            this.openModulesMenu();
        }

        // Alt + F: Abrir favoritos
        if (e.altKey && e.key === 'f') {
            e.preventDefault();
            this.openFavoritesMenu();
        }
    }

    openQuickSearch() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Búsqueda Rápida</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" class="form-control" id="quickSearchInput" placeholder="Buscar módulos, páginas...">
                        <div id="quickSearchResults" class="mt-3"></div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // Focus en el input
        const input = modal.querySelector('#quickSearchInput');
        input.focus();

        // Configurar búsqueda
        this.setupQuickSearchLogic(input, modal.querySelector('#quickSearchResults'));

        // Limpiar modal al cerrar
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    setupQuickSearchLogic(input, resultsContainer) {
        let searchTimeout;

        input.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = input.value.trim().toLowerCase();

                if (query.length < 2) {
                    resultsContainer.innerHTML = '';
                    return;
                }

                const results = this.searchNavigation(query);
                this.displayQuickSearchResults(results, resultsContainer);
            }, 300);
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const firstResult = resultsContainer.querySelector('a');
                if (firstResult) {
                    firstResult.click();
                }
            }
        });
    }

    searchNavigation(query) {
        const results = [];
        const moduleCategories = this.getModuleNavigation();

        // Buscar en módulos
        moduleCategories.forEach(category => {
            if (category.children) {
                category.children.forEach(module => {
                    if (module.title.toLowerCase().includes(query) ||
                        module.id.toLowerCase().includes(query)) {
                        results.push({
                            title: module.title,
                            url: module.url,
                            category: category.title,
                            icon: module.icon
                        });
                    }
                });
            }
        });

        // Buscar en páginas principales
        const mainPages = [
            { title: 'Dashboard', url: '/dashboard', icon: 'fas fa-tachometer-alt' },
            { title: 'Administración', url: '/admin', icon: 'fas fa-cog' },
            { title: 'Búsqueda', url: '/search', icon: 'fas fa-search' },
            { title: 'Mi Perfil', url: '/profile', icon: 'fas fa-user' },
            { title: 'Configuración', url: '/settings', icon: 'fas fa-cog' }
        ];

        mainPages.forEach(page => {
            if (page.title.toLowerCase().includes(query)) {
                results.push({
                    ...page,
                    category: 'Páginas principales'
                });
            }
        });

        return results.slice(0, 10); // Limitar a 10 resultados
    }

    displayQuickSearchResults(results, container) {
        if (results.length === 0) {
            container.innerHTML = '<div class="text-muted">No se encontraron resultados</div>';
            return;
        }

        const html = results.map(result => `
            <a href="${result.url}" class="d-block p-2 text-decoration-none border-bottom quick-search-result">
                <div class="d-flex align-items-center">
                    <i class="${result.icon} me-3"></i>
                    <div>
                        <div class="fw-bold">${result.title}</div>
                        <small class="text-muted">${result.category}</small>
                    </div>
                </div>
            </a>
        `).join('');

        container.innerHTML = html;

        // Agregar event listeners
        container.querySelectorAll('.quick-search-result').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                window.navigateTo(link.getAttribute('href'));
                bootstrap.Modal.getInstance(container.closest('.modal')).hide();
            });
        });
    }

    toggleFavorite(moduleId) {
        const index = this.favorites.indexOf(moduleId);

        if (index > -1) {
            this.favorites.splice(index, 1);
        } else {
            this.favorites.push(moduleId);
        }

        this.saveFavorites();
        this.updateFavoritesUI();
        this.createMainNavigation(); // Recrear navegación
    }

    loadFavorites() {
        const saved = localStorage.getItem('workmanager_favorites');
        return saved ? JSON.parse(saved) : [];
    }

    saveFavorites() {
        localStorage.setItem('workmanager_favorites', JSON.stringify(this.favorites));
    }

    updateFavoritesUI() {
        // Actualizar iconos de favoritos
        document.querySelectorAll('[data-nav-favorite]').forEach(element => {
            const moduleId = element.dataset.navFavorite;
            const isFavorite = this.favorites.includes(moduleId);
            const star = element.querySelector('.fa-star');

            if (star) {
                star.style.display = isFavorite ? 'inline' : 'none';
            }
        });
    }

    openModulesMenu() {
        // Implementar menú de módulos
        console.log('Opening modules menu');
    }

    openFavoritesMenu() {
        // Implementar menú de favoritos
        console.log('Opening favorites menu');
    }

    setupBreadcrumbs() {
        // Crear contenedor de breadcrumbs si no existe
        if (!document.getElementById('breadcrumbs')) {
            const header = document.querySelector('.main-header') || document.querySelector('header');
            if (header) {
                const breadcrumbContainer = document.createElement('div');
                breadcrumbContainer.id = 'breadcrumbs';
                breadcrumbContainer.className = 'breadcrumb-container';
                header.appendChild(breadcrumbContainer);
            }
        }
    }

    setupQuickAccess() {
        // Crear barra de acceso rápido
        const quickAccessContainer = document.getElementById('quickAccess');
        if (quickAccessContainer) {
            const quickAccessItems = [
                { title: 'Nuevo Ticket', url: '/modules/tickets/create', icon: 'fas fa-plus' },
                { title: 'Búsqueda', url: '/search', icon: 'fas fa-search' },
                { title: 'Reportes', url: '/modules/reportes', icon: 'fas fa-chart-bar' }
            ];

            const html = quickAccessItems.map(item => `
                <a href="${item.url}" class="btn btn-sm btn-outline-primary me-2">
                    <i class="${item.icon} me-1"></i>${item.title}
                </a>
            `).join('');

            quickAccessContainer.innerHTML = html;
        }
    }

    formatModuleName(module) {
        return module
            .split('-')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    formatTime(timestamp) {
        const now = Date.now();
        const diff = now - timestamp;

        if (diff < 60000) return 'Hace un momento';
        if (diff < 3600000) return `Hace ${Math.floor(diff / 60000)} min`;
        if (diff < 86400000) return `Hace ${Math.floor(diff / 3600000)} h`;

        return new Date(timestamp).toLocaleDateString();
    }

    createUserNavigation() {
        const userNav = document.getElementById('userNavigation');
        if (!userNav) return;

        const userItems = [
            { title: 'Mi Perfil', url: '/profile', icon: 'fas fa-user' },
            { title: 'Configuración', url: '/settings', icon: 'fas fa-cog' },
            { title: 'Ayuda', url: '/help', icon: 'fas fa-question-circle' },
            { title: 'Cerrar Sesión', url: '/logout', icon: 'fas fa-sign-out-alt' }
        ];

        const html = userItems.map(item => `
            <a class="dropdown-item" href="${item.url}">
                <i class="${item.icon} me-2"></i>${item.title}
            </a>
        `).join('');

        userNav.innerHTML = html;
    }

    // Métodos públicos para uso externo
    navigateToModule(moduleId, action = 'dashboard') {
        const url = action === 'dashboard' ? `/modules/${moduleId}` : `/modules/${moduleId}/${action}`;
        window.navigateTo(url);
    }

    getCurrentModule() {
        return this.currentModule;
    }

    getBreadcrumbs() {
        return [...this.breadcrumbs];
    }

    getNavigationHistory() {
        return [...this.navigationHistory];
    }

    getFavorites() {
        return [...this.favorites];
    }
}

// Inicializar sistema de navegación
document.addEventListener('DOMContentLoaded', () => {
    window.navigationSystem = new NavigationSystem();
});

// Funciones de utilidad globales
window.navigateToModule = (moduleId, action = 'dashboard') => {
    if (window.navigationSystem) {
        window.navigationSystem.navigateToModule(moduleId, action);
    }
};

window.toggleFavorite = (moduleId) => {
    if (window.navigationSystem) {
        window.navigationSystem.toggleFavorite(moduleId);
    }
};

// Export para módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NavigationSystem;
}
