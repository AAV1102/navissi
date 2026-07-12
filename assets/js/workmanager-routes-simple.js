/**
 * WORKMANAGER ROUTES SIMPLE - Sistema de Rutas Simplificado
 * =========================================================
 * Sistema de enrutamiento que NO interfiere con el dashboard existente
 */

class SimpleRouter {
    constructor() {
        this.enabled = false;
        // Solo habilitar en rutas específicas que lo necesiten
        this.checkIfShouldEnable();
    }

    checkIfShouldEnable() {
        const path = window.location.pathname;

        // Solo habilitar en rutas SPA específicas
        const spaRoutes = [
            '/spa/',
            '/app/',
            '/modules/spa/'
        ];

        for (const route of spaRoutes) {
            if (path.includes(route)) {
                this.enabled = true;
                this.init();
                break;
            }
        }
    }

    init() {
        console.log('Simple Router initialized for SPA routes only');
        // Configuración mínima solo para rutas SPA
    }
}

// Funciones de utilidad que NO interfieren con la navegación normal
window.navigateToModule = (moduleId, action = 'dashboard') => {
    const url = action === 'dashboard' ?
        `/dashboards/${moduleId}/dashboard.php` :
        `/dashboards/${moduleId}/${action}.php`;
    window.location.href = url;
};

window.navigateTo = (path) => {
    // Navegación simple sin interferir con el sistema existente
    window.location.href = path;
};

window.goBack = () => {
    window.history.back();
};

// Solo inicializar si estamos en una ruta SPA
document.addEventListener('DOMContentLoaded', () => {
    window.simpleRouter = new SimpleRouter();
});

// Export para módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SimpleRouter;
}
