/**
 * SISTEMA DE NOTIFICACIONES - WorkManager ERP v2.0
 * Sistema de notificaciones toast en tiempo real
 */

class NotificationManager {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        this.createContainer();
        this.setupStyles();
    }

    createContainer() {
        if (document.getElementById('notification-container')) {
            this.container = document.getElementById('notification-container');
            return;
        }

        this.container = document.createElement('div');
        this.container.id = 'notification-container';
        this.container.className = 'notification-container';
        document.body.appendChild(this.container);
    }

    setupStyles() {
        if (document.getElementById('notification-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            }

            .notification {
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                margin-bottom: 10px;
                padding: 16px;
                border-left: 4px solid #007bff;
                animation: slideIn 0.3s ease-out;
                position: relative;
                overflow: hidden;
            }

            .notification.success {
                border-left-color: #28a745;
            }

            .notification.error {
                border-left-color: #dc3545;
            }

            .notification.warning {
                border-left-color: #ffc107;
            }

            .notification.info {
                border-left-color: #17a2b8;
            }

            .notification-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 8px;
            }

            .notification-title {
                font-weight: 600;
                font-size: 14px;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .notification-close {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                color: #6c757d;
                padding: 0;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .notification-close:hover {
                color: #495057;
            }

            .notification-message {
                font-size: 13px;
                color: #6c757d;
                margin: 0;
                line-height: 1.4;
            }

            .notification-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: rgba(0,0,0,0.1);
                animation: progress linear;
            }

            .notification.success .notification-progress {
                background: #28a745;
            }

            .notification.error .notification-progress {
                background: #dc3545;
            }

            .notification.warning .notification-progress {
                background: #ffc107;
            }

            .notification.info .notification-progress {
                background: #17a2b8;
            }

            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }

            @keyframes progress {
                from { width: 100%; }
                to { width: 0%; }
            }

            .notification.removing {
                animation: slideOut 0.3s ease-in forwards;
            }
        `;
        document.head.appendChild(styles);
    }

    show(message, type = 'info', options = {}) {
        const {
            title = this.getDefaultTitle(type),
            duration = 4000,
            persistent = false,
            actions = []
        } = options;

        const notification = this.createNotification(message, type, title, duration, persistent, actions);
        this.container.appendChild(notification);

        // Auto remove if not persistent
        if (!persistent && duration > 0) {
            setTimeout(() => {
                this.remove(notification);
            }, duration);
        }

        return notification;
    }

    createNotification(message, type, title, duration, persistent, actions) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;

        const icon = this.getIcon(type);

        notification.innerHTML = `
            <div class="notification-header">
                <h6 class="notification-title">
                    <i class="${icon}"></i>
                    ${title}
                </h6>
                <button class="notification-close" onclick="notificationManager.remove(this.closest('.notification'))">
                    ×
                </button>
            </div>
            <p class="notification-message">${message}</p>
            ${actions.length > 0 ? this.createActions(actions) : ''}
            ${!persistent && duration > 0 ? `<div class="notification-progress" style="animation-duration: ${duration}ms;"></div>` : ''}
        `;

        return notification;
    }

    createActions(actions) {
        const actionsHtml = actions.map(action =>
            `<button class="btn btn-sm btn-outline-primary me-2" onclick="${action.callback}">${action.label}</button>`
        ).join('');

        return `<div class="notification-actions mt-2">${actionsHtml}</div>`;
    }

    remove(notification) {
        if (!notification || !notification.parentNode) return;

        notification.classList.add('removing');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    getDefaultTitle(type) {
        const titles = {
            success: 'Éxito',
            error: 'Error',
            warning: 'Advertencia',
            info: 'Información'
        };
        return titles[type] || 'Notificación';
    }

    getIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-triangle',
            warning: 'fas fa-exclamation-circle',
            info: 'fas fa-info-circle'
        };
        return icons[type] || 'fas fa-bell';
    }

    // Métodos de conveniencia
    success(message, options = {}) {
        return this.show(message, 'success', options);
    }

    error(message, options = {}) {
        return this.show(message, 'error', { ...options, duration: 6000 });
    }

    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }

    info(message, options = {}) {
        return this.show(message, 'info', options);
    }

    // Notificaciones especiales
    loading(message, options = {}) {
        return this.show(message, 'info', {
            ...options,
            title: 'Cargando...',
            persistent: true,
            duration: 0
        });
    }

    progress(message, percentage, options = {}) {
        const notification = this.show(message, 'info', {
            ...options,
            title: `Progreso: ${percentage}%`,
            persistent: true,
            duration: 0
        });

        // Agregar barra de progreso personalizada
        const progressBar = document.createElement('div');
        progressBar.className = 'progress mt-2';
        progressBar.style.height = '6px';
        progressBar.innerHTML = `
            <div class="progress-bar bg-primary" style="width: ${percentage}%"></div>
        `;

        notification.querySelector('.notification-message').appendChild(progressBar);
        return notification;
    }

    // Limpiar todas las notificaciones
    clear() {
        const notifications = this.container.querySelectorAll('.notification');
        notifications.forEach(notification => this.remove(notification));
    }
}

// Crear instancia global
const notificationManager = new NotificationManager();

// Funciones globales para compatibilidad
function showToast(message, type = 'info', options = {}) {
    return notificationManager.show(message, type, options);
}

function showSuccess(message, options = {}) {
    return notificationManager.success(message, options);
}

function showError(message, options = {}) {
    return notificationManager.error(message, options);
}

function showWarning(message, options = {}) {
    return notificationManager.warning(message, options);
}

function showInfo(message, options = {}) {
    return notificationManager.info(message, options);
}

function showLoading(message, options = {}) {
    return notificationManager.loading(message, options);
}

// Integración con fetch para mostrar errores automáticamente
const originalFetch = window.fetch;
window.fetch = function(...args) {
    return originalFetch.apply(this, args)
        .then(response => {
            // Si la respuesta no es ok, intentar mostrar el error
            if (!response.ok && response.headers.get('content-type')?.includes('application/json')) {
                response.clone().json().then(data => {
                    if (data.error) {
                        showError(data.error);
                    }
                }).catch(() => {
                    // Ignorar errores de parsing
                });
            }
            return response;
        })
        .catch(error => {
            // Mostrar errores de red
            showError('Error de conexión: ' + error.message);
            throw error;
        });
};

// Exportar para uso en módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { NotificationManager, notificationManager };
}
