/**
 * WORKMANAGER ROUTES - Sistema de Rutas del Cliente
 * ================================================
 * Sistema de enrutamiento del lado del cliente para WorkManager ERP v2.0
 */

class WorkManagerRouter {
    constructor() {
        this.routes = new Map();
        this.middlewares = [];
        this.currentRoute = null;
        this.history = [];
        this.baseUrl = window.location.origin;
        this.init();
    }

    init() {
        this.setupRoutes();
        this.setupEventListeners();
        this.handleInitialRoute();
    }

    setupRoutes() {
        // Rutas principales del sistema
        this.addRoute('/', {
            component: 'dashboard',
            title: 'Dashboard Principal',
            auth: true,
            permissions: []
        });

        this.addRoute('/dashboard', {
            component: 'dashboard',
            title: 'Dashboard',
            auth: true,
            permissions: []
        });

        this.addRoute('/login', {
            component: 'login',
            title: 'Iniciar Sesión',
            auth: false,
            layout: 'auth'
        });

        this.addRoute('/logout', {
            component: 'logout',
            title: 'Cerrar Sesión',
            auth: true
        });

        // Rutas de administración
        this.addRoute('/admin', {
            component: 'admin-panel',
            title: 'Panel Administrativo',
            auth: true,
            permissions: ['Administrador', 'Super Admin']
        });

        this.addRoute('/admin/users', {
            component: 'admin-users',
            title: 'Gestión de Usuarios',
            auth: true,
            permissions: ['Administrador', 'Super Admin']
        });

        this.addRoute('/admin/system', {
            component: 'admin-system',
            title: 'Configuración del Sistema',
            auth: true,
            permissions: ['Super Admin']
        });

        // Rutas de módulos principales
        this.setupModuleRoutes();
    }

    setupModuleRoutes() {
        const modules = [
            'inventario', 'sistemas', 'tickets', 'empleados', 'usuarios',
            'sedes', 'equipos', 'biomedica', 'juridico', 'logistica',
            'tesoreria', 'nomina', 'vacaciones', 'capacitaciones',
            'evaluaciones', 'contratos', 'documentos', 'reportes',
            'facturacion', 'crm', 'mesa-ayuda', 'soporte', 'sst',
            'gestion-humana', 'hoja-vida', 'permisos-laborales',
            'asistencia', 'citas', 'historia-clinica', 'enfermeria',
            'medico', 'farmacia', 'educacion', 'integraciones',
            'office365', 'powerbi', 'whatsapp', 'n8n-automation',
            'ai-automation', 'mikrotik', 'vpn-management', 'huelleros',
            'qr-codes', 'licencias', 'roles', 'prioridades',
            'categorias-tickets', 'notifications', 'realtime',
            'buscador-universal', 'exportador-universal',
            'importador-universal', 'autodiscovery', 'diagnostico',
            'configuracion', 'flujos', 'servicio-cliente',
            'administracion', 'actives', 'agente-multiplataforma',
            'ia', 'documental', 'documentacion'
        ];

        modules.forEach(module => {
            // Ruta principal del módulo
            this.addRoute(`/modules/${module}`, {
                component: 'module-dashboard',
                title: this.formatModuleName(module),
                auth: true,
                module: module,
                permissions: []
            });

            // Ruta de dashboard del módulo
            this.addRoute(`/modules/${module}/dashboard`, {
                component: 'module-dashboard',
                title: `Dashboard - ${this.formatModuleName(module)}`,
                auth: true,
                module: module,
                permissions: []
            });

            // Rutas CRUD del módulo
            this.addRoute(`/modules/${module}/list`, {
                component: 'module-list',
                title: `Lista - ${this.formatModuleName(module)}`,
                auth: true,
                module: module,
                action: 'list',
                permissions: []
            });

            this.addRoute(`/modules/${module}/create`, {
                component: 'module-form',
                title: `Crear - ${this.formatModuleName(module)}`,
                auth: true,
                module: module,
                action: 'create',
                permissions: []
            });

            this.addRoute(`/modules/${module}/edit/:id`, {
                component: 'module-form',
                title: `Editar - ${this.formatModuleName(module)}`,
                auth: true,
                module: module,
                action: 'edit',
                permissions: []
            });

            this.addRoute(`/modules/${module}/view/:id`, {
                component: 'module-view',
                title: `Ver - ${this.formatModuleName(module)}`,
                auth: true,
                module: module,
                action: 'view',
                permissions: []
            });
        });

        // Rutas especiales
        this.addRoute('/search', {
            component: 'universal-search',
            title: 'Búsqueda Universal',
            auth: true,
            permissions: []
        });

        this.addRoute('/profile', {
            component: 'user-profile',
            title: 'Mi Perfil',
            auth: true,
            permissions: []
        });

        this.addRoute('/settings', {
            component: 'user-settings',
            title: 'Configuración',
            auth: true,
            permissions: []
        });

        this.addRoute('/help', {
            component: 'help-center',
            title: 'Centro de Ayuda',
            auth: true,
            permissions: []
        });

        // Rutas de error
        this.addRoute('/404', {
            component: 'error-404',
            title: 'Página no encontrada',
            auth: false,
            layout: 'error'
        });

        this.addRoute('/403', {
            component: 'error-403',
            title: 'Acceso denegado',
            auth: false,
            layout: 'error'
        });

        this.addRoute('/500', {
            component: 'error-500',
            title: 'Error interno',
            auth: false,
            layout: 'error'
        });
    }

    addRoute(path, config) {
        this.routes.set(path, {
            path,
            ...config,
            params: this.extractParams(path)
        });
    }

    extractParams(path) {
        const params = [];
        const segments = path.split('/');

        segments.forEach((segment, index) => {
            if (segment.startsWith(':')) {
                params.push({
                    name: segment.substring(1),
                    index: index
                });
            }
        });

        return params;
    }

    setupEventListeners() {
        // Interceptar clicks en enlaces
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (link && this.shouldInterceptLink(link)) {
                e.preventDefault();
                this.navigate(link.getAttribute('href'));
            }
        });

        // Manejar botones de navegación del navegador
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.route) {
                this.handleRoute(e.state.route, false);
            } else {
                this.handleInitialRoute();
            }
        });

        // Interceptar envío de formularios con data-route
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.hasAttribute('data-route')) {
                e.preventDefault();
                this.handleFormSubmit(form);
            }
        });
    }

    shouldInterceptLink(link) {
        const href = link.getAttribute('href');

        // No interceptar enlaces externos
        if (href.startsWith('http') && !href.startsWith(this.baseUrl)) {
            return false;
        }

        // No interceptar enlaces con target="_blank"
        if (link.getAttribute('target') === '_blank') {
            return false;
        }

        // No interceptar enlaces con data-no-route
        if (link.hasAttribute('data-no-route')) {
            return false;
        }

        // No interceptar enlaces de descarga
        if (link.hasAttribute('download')) {
            return false;
        }

        return true;
    }

    handleInitialRoute() {
        const path = window.location.pathname;
        const route = this.matchRoute(path);

        if (route) {
            this.handleRoute(route, false);
        } else {
            this.navigate('/404');
        }
    }

    navigate(path, options = {}) {
        const route = this.matchRoute(path);

        if (!route) {
            this.navigate('/404');
            return;
        }

        // Agregar al historial si no es una navegación hacia atrás
        if (options.addToHistory !== false) {
            this.history.push(this.currentRoute);
            history.pushState({ route: route }, route.title, path);
        }

        this.handleRoute(route, options.addToHistory !== false);
    }

    matchRoute(path) {
        // Buscar coincidencia exacta primero
        if (this.routes.has(path)) {
            return { ...this.routes.get(path), matchedPath: path, params: {} };
        }

        // Buscar coincidencias con parámetros
        for (const [routePath, routeConfig] of this.routes) {
            const match = this.matchParameterizedRoute(path, routePath);
            if (match) {
                return {
                    ...routeConfig,
                    matchedPath: path,
                    params: match.params
                };
            }
        }

        return null;
    }

    matchParameterizedRoute(path, routePath) {
        const pathSegments = path.split('/').filter(s => s);
        const routeSegments = routePath.split('/').filter(s => s);

        if (pathSegments.length !== routeSegments.length) {
            return null;
        }

        const params = {};

        for (let i = 0; i < routeSegments.length; i++) {
            const routeSegment = routeSegments[i];
            const pathSegment = pathSegments[i];

            if (routeSegment.startsWith(':')) {
                // Parámetro
                const paramName = routeSegment.substring(1);
                params[paramName] = pathSegment;
            } else if (routeSegment !== pathSegment) {
                // No coincide
                return null;
            }
        }

        return { params };
    }

    async handleRoute(route, addToHistory = true) {
        try {
            // Ejecutar middlewares
            for (const middleware of this.middlewares) {
                const result = await middleware(route);
                if (result === false) {
                    return; // Middleware canceló la navegación
                }
            }

            // Verificar autenticación
            if (route.auth && !await this.checkAuth()) {
                this.navigate('/login');
                return;
            }

            // Verificar permisos
            if (route.permissions && route.permissions.length > 0) {
                if (!await this.checkPermissions(route.permissions)) {
                    this.navigate('/403');
                    return;
                }
            }

            // Actualizar título de la página
            document.title = `${route.title} - WorkManager ERP`;

            // Cargar componente
            await this.loadComponent(route);

            // Actualizar ruta actual
            this.currentRoute = route;

            // Emitir evento de cambio de ruta
            this.emitRouteChange(route);

        } catch (error) {
            console.error('Error handling route:', error);
            this.navigate('/500');
        }
    }

    async loadComponent(route) {
        const container = document.getElementById('main-content') || document.body;

        try {
            let content = '';

            switch (route.component) {
                case 'dashboard':
                    content = await this.loadDashboard();
                    break;

                case 'module-dashboard':
                    content = await this.loadModuleDashboard(route.module);
                    break;

                case 'module-list':
                    content = await this.loadModuleList(route.module);
                    break;

                case 'module-form':
                    content = await this.loadModuleForm(route.module, route.action, route.params);
                    break;

                case 'module-view':
                    content = await this.loadModuleView(route.module, route.params);
                    break;

                case 'admin-panel':
                    content = await this.loadAdminPanel();
                    break;

                case 'universal-search':
                    content = await this.loadUniversalSearch();
                    break;

                case 'user-profile':
                    content = await this.loadUserProfile();
                    break;

                case 'login':
                    window.location.href = '/login.php';
                    return;

                case 'logout':
                    window.location.href = '/logout.php';
                    return;

                default:
                    content = await this.loadErrorPage(route.component);
            }

            container.innerHTML = content;

            // Ejecutar scripts del componente si existen
            await this.executeComponentScripts(route);

        } catch (error) {
            console.error('Error loading component:', error);
            container.innerHTML = this.getErrorContent('Error cargando el componente');
        }
    }

    async loadDashboard() {
        try {
            const response = await fetch('/dashboard.php');
            if (response.ok) {
                return await response.text();
            } else {
                throw new Error('Error loading dashboard');
            }
        } catch (error) {
            return this.getErrorContent('Error cargando el dashboard');
        }
    }

    async loadModuleDashboard(module) {
        try {
            const response = await fetch(`/dashboards/${module}/dashboard.php`);
            if (response.ok) {
                return await response.text();
            } else {
                throw new Error(`Error loading ${module} dashboard`);
            }
        } catch (error) {
            return this.getErrorContent(`Error cargando el dashboard de ${module}`);
        }
    }

    async loadModuleList(module) {
        return `
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <h2>${this.formatModuleName(module)} - Lista</h2>
                        <div id="module-list-container" data-module="${module}">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </div>
                        </d') {
                    </div>
                </div>
            </div>
        `;
    }

    async loadModuleForm(module, action, params) {
        const title = action === 'create' ? 'Crear' : 'Editar';
        const id = params.id || '';

        return `
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <h2>${this.formatModuleName(module)} - ${title}</h2>
                        <div id="module-form-container" data-module="${module}" data-action="${action}" data-id="${id}">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async loadModuleView(module, params) {
        return `
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <h2>${this.formatModuleName(module)} - Ver</h2>
                        <div id="module-view-container" data-module="${module}" data-id="${params.id}">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async loadAdminPanel() {
        try {
            const response = await fetch('/admin/admin-panel.php');
            if (response.ok) {
                return await response.text();
            } else {
                throw new Error('Error loading admin panel');
            }
        } catch (error) {
            return this.getErrorContent('Error cargando el panel administrativo');
        }
    }

    async loadUniversalSearch() {
        return `
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <h2>Búsqueda Universal</h2>
                        <div id="universal-search-container">
                            <div class="card">
                                <div class="card-body">
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" id="searchInput" placeholder="Buscar en todo el sistema...">
                                        <button class="btn btn-primary" type="button" id="searchButton">
                                            <i class="fas fa-search"></i> Buscar
                                        </button>
                                    </div>
                                    <div id="searchResults"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async loadUserProfile() {
        return `
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <h2>Mi Perfil</h2>
                        <div id="user-profile-container">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async loadErrorPage(component) {
        const errorMessages = {
            'error-404': 'Página no encontrada',
            'error-403': 'Acceso denegado',
            'error-500': 'Error interno del servidor'
        };

        const message = errorMessages[component] || 'Error desconocido';

        return `
            <div class="container-fluid">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="text-center">
                            <h1 class="display-1">${component.split('-')[1]}</h1>
                            <h2>${message}</h2>
                            <p class="text-muted">Lo sentimos, ha ocurrido un error.</p>
                            <a href="/" class="btn btn-primary">Volver al inicio</a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    getErrorContent(message) {
        return `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            </div>
        `;
    }

    async executeComponentScripts(route) {
        // Ejecutar scripts específicos del componente
        const scriptMap = {
            'module-dashboard': () => this.initModuleDashboard(route.module),
            'module-list': () => this.initModuleList(route.module),
            'module-form': () => this.initModuleForm(route.module, route.action, route.params),
            'module-view': () => this.initModuleView(route.module, route.params),
            'universal-search': () => this.initUniversalSearch(),
            'user-profile': () => this.initUserProfile()
        };

        const scriptFunction = scriptMap[route.component];
        if (scriptFunction) {
            try {
                await scriptFunction();
            } catch (error) {
                console.error('Error executing component script:', error);
            }
        }
    }

    async initModuleDashboard(module) {
        // Cargar dashboard específico del módulo
        const container = document.getElementById('module-dashboard-container');
        if (container) {
            try {
                const response = await fetch(`/dashboards/${module}/dashboard.php`);
                if (response.ok) {
                    container.innerHTML = await response.text();

                    // Cargar script del módulo si existe
                    const script = document.createElement('script');
                    script.src = `/dashboards/assets/js/${module}-dashboard.js`;
                    script.onerror = () => console.log(`No script found for ${module}`);
                    document.head.appendChild(script);
                }
            } catch (error) {
                console.error(`Error loading ${module} dashboard:`, error);
            }
        }
    }

    async initModuleList(module) {
        // Inicializar lista del módulo
        console.log(`Initializing ${module} list`);
    }

    async initModuleForm(module, action, params) {
        // Inicializar formulario del módulo
        console.log(`Initializing ${module} form - ${action}`, params);
    }

    async initModuleView(module, params) {
        // Inicializar vista del módulo
        console.log(`Initializing ${module} view`, params);
    }

    async initUniversalSearch() {
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        const searchResults = document.getElementById('searchResults');

        if (searchInput && searchButton && searchResults) {
            const performSearch = async () => {
                const query = searchInput.value.trim();
                if (!query) return;

                searchResults.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';

                try {
                    const response = await fetch(`/api/search?q=${encodeURIComponent(query)}`);
                    const result = await response.json();

                    if (result.success && result.data) {
                        this.displaySearchResults(result.data, searchResults);
                    } else {
                        searchResults.innerHTML = '<div class="alert alert-info">No se encontraron resultados</div>';
                    }
                } catch (error) {
                    searchResults.innerHTML = '<div class="alert alert-danger">Error en la búsqueda</div>';
                }
            };

            searchButton.addEventListener('click', performSearch);
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
        }
    }

    displaySearchResults(results, container) {
        if (!results.length) {
            container.innerHTML = '<div class="alert alert-info">No se encontraron resultados</div>';
            return;
        }

        const html = results.map(result => `
            <div class="card mb-2">
                <div class="card-body">
                    <h6 class="card-title">${result.nombre || result.codigo}</h6>
                    <p class="card-text">${result.email || result.descripcion || ''}</p>
                    <small class="text-muted">Tipo: ${result.tipo}</small>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    async initUserProfile() {
        // Inicializar perfil de usuario
        console.log('Initializing user profile');
    }

    async checkAuth() {
        try {
            if (window.authManager) {
                return await window.authManager.isAuthenticated();
            }

            // Fallback check
            const response = await fetch('/auth/auth-manager.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=verify'
            });

            const result = await response.json();
            return result.success && result.authenticated;
        } catch (error) {
            console.error('Auth check error:', error);
            return false;
        }
    }

    async checkPermissions(requiredPermissions) {
        try {
            if (window.authManager) {
                return await window.authManager.hasRole(requiredPermissions);
            }

            // Fallback check - assume access if can't verify
            return true;
        } catch (error) {
            console.error('Permission check error:', error);
            return false;
        }
    }

    formatModuleName(module) {
        return module
            .split('-')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    addMiddleware(middleware) {
        this.middlewares.push(middleware);
    }

    emitRouteChange(route) {
        const event = new CustomEvent('routechange', {
            detail: { route, router: this }
        });
        window.dispatchEvent(event);
    }

    getCurrentRoute() {
        return this.currentRoute;
    }

    getHistory() {
        return [...this.history];
    }

    back() {
        if (this.history.length > 0) {
            const previousRoute = this.history.pop();
            this.navigate(previousRoute.matchedPath, { addToHistory: false });
        } else {
            window.history.back();
        }
    }

    forward() {
        window.history.forward();
    }

    reload() {
        if (this.currentRoute) {
            this.handleRoute(this.currentRoute, false);
        }
    }

    // Método para manejar envío de formularios
    async handleFormSubmit(form) {
        const route = form.getAttribute('data-route');
        const method = form.getAttribute('method') || 'GET';

        if (method.toLowerCase() === 'get') {
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            this.navigate(`${route}?${params.toString()}`);
        } else {
            // Para POST, enviar el formulario normalmente
            form.submit();
        }
    }
}

// Inicializar router cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.wmRouter = new WorkManagerRouter();
});

// Funciones de utilidad globales
window.navigateTo = (path) => {
    if (window.wmRouter) {
        window.wmRouter.navigate(path);
    } else {
        window.location.href = path;
    }
};

window.goBack = () => {
    if (window.wmRouter) {
        window.wmRouter.back();
    } else {
        window.history.back();
    }
};

// Export para módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WorkManagerRouter;
}
