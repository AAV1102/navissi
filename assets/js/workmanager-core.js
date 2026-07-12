/**
 * WORKMANAGER CORE - Funcionalidades Básicas
 * ==========================================
 * Funciones principales del sistemManager ERP
 */

// Configuración global
window.WorkManager = {
    version: '2.0',
    baseUrl: window.location.origin,
    apiUrl: window.location.origin + '/api',
    initialized: false
};

// Funciones de utilidad
function showNotification(message, type = 'info', duration = 5000) {
    // Crear contenedor de notificaciones si no existe
    let container = document.getElementById('notifications-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notifications-container';
        container.className = 'position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    // Crear notificación
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    container.appendChild(notification);

    // Auto-remover después del tiempo especificado
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

// Función para mostrar loading
function showLoading(element, show = true) {
    if (!element) return;

    if (show) {
        const spinner = document.createElement('div');
        spinner.className = 'text-center loading-spinner';
        spinner.innerHTML = `
            <div class="spinner-border spinner-border-sm" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        `;
        element.appendChild(spinner);
    } else {
        const spinner = element.querySelector('.loading-spinner');
        if (spinner) {
            spinner.remove();
        }
    }
}

// Función para formatear números
function formatNumber(number) {
    return new Intl.NumberFormat('es-CO').format(number);
}

// Función para formatear fechas
function formatDate(date, options = {}) {
    const defaultOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    };

    const formatOptions = { ...defaultOptions, ...options };
    return new Intl.DateTimeFormat('es-CO', formatOptions).format(new Date(date));
}

// Función para formatear tiempo relativo
function formatTimeAgo(timestamp) {
    const now = Date.now();
    const diff = now - new Date(timestamp).getTime();

    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (seconds < 60) return 'Hace un momento';
    if (minutes < 60) return `Hace ${minutes} minuto${minutes > 1 ? 's' : ''}`;
    if (hours < 24) return `Hace ${hours} hora${hours > 1 ? 's' : ''}`;
    if (days < 7) return `Hace ${days} día${days > 1 ? 's' : ''}`;

    return formatDate(timestamp);
}

// Función para validar formularios
function validateForm(form) {
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

// Función para confirmar acciones
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Función para copiar al portapapeles
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showNotification('Copiado al portapapeles', 'success');
    } catch (err) {
        console.error('Error copying to clipboard:', err);
        showNotification('Error al copiar', 'error');
    }
}

// Función para descargar archivo
function downloadFile(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Función para hacer peticiones AJAX simples
async function makeRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    const requestOptions = { ...defaultOptions, ...options };

    try {
        const response = await fetch(url, requestOptions);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        } else {
            return await response.text();
        }
    } catch (error) {
        console.error('Request error:', error);
        throw error;
    }
}

// Función para inicializar tooltips de Bootstrap
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Función para inicializar popovers de Bootstrap
function initializePopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Función para manejar errores globalmente
function handleError(error, context = '') {
    console.error(`Error in ${context}:`, error);
    showNotification(`Error: ${error.message || 'Algo salió mal'}`, 'error');
}

// Función para debounce (evitar múltiples llamadas rápidas)
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Función para throttle (limitar frecuencia de llamadas)
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes de Bootstrap
    initializeTooltips();
    initializePopovers();

    // Manejar formularios con validación automática
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.hasAttribute('data-validate')) {
            if (!validateForm(form)) {
                e.preventDefault();
                showNotification('Por favor, complete todos los campos requeridos', 'error');
            }
        }
    });

    // Manejar botones de confirmación
    document.addEventListener('click', function(e) {
        const button = e.target.closest('[data-confirm]');
        if (button) {
            e.preventDefault();
            const message = button.getAttribute('data-confirm');
            confirmAction(message, () => {
                // Si es un enlace, navegar
                if (button.tagName === 'A') {
                    window.location.href = button.href;
                }
                // Si es un botón de formulario, enviar formulario
                else if (button.type === 'submit') {
                    button.form.submit();
                }
                // Si tiene data-action, ejecutar
                else if (button.hasAttribute('data-action')) {
                    const action = button.getAttribute('data-action');
                    if (typeof window[action] === 'function') {
                        window[action]();
                    }
                }
            });
        }
    });

    // Manejar botones de copia
    document.addEventListener('click', function(e) {
        const button = e.target.closest('[data-copy]');
        if (button) {
            e.preventDefault();
            const text = button.getAttribute('data-copy');
            copyToClipboard(text);
        }
    });

    // Marcar como inicializado
    window.WorkManager.initialized = true;

    console.log('WorkManager Core initialized successfully');
});

// Exportar funciones globalmente
window.showNotification = showNotification;
window.showLoading = showLoading;
window.formatNumber = formatNumber;
window.formatDate = formatDate;
window.formatTimeAgo = formatTimeAgo;
window.validateForm = validateForm;
window.confirmAction = confirmAction;
window.copyToClipboard = copyToClipboard;
window.downloadFile = downloadFile;
window.makeRequest = makeRequest;
window.handleError = handleError;
window.debounce = debounce;
window.throttle = throttle;

// Alias for showToast (used by importador-universal)
function showToast(message, type = 'info', duration = 5000) {
    showNotification(message, type, duration);
}
window.showToast = showToast;
