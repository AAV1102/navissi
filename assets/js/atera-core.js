/**
 * ATERA CORE JS
 * WorkManager ERP - Funcionalidades core estilo Atera
 * Fecha: 3 de Enero de 2026
 */

// Namespace global
window.AteraERP = window.AteraERP || {};

/**
 * Configuración global
 */
AteraERP.config = {
    baseUrl: typeof ATERA_CONFIG !== 'undefined' ? ATERA_CONFIG.baseUrl : '/',
    apiUrl: '/api/',
    debug: false
};

/**
 * Sistema de notificaciones Toast
 */
AteraERP.toast = {
    container: null,
    
    init: function() {
        this.container = document.getElementById('toast-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'atera-toast-container';
            document.body.appendChild(this.container);
        }
    },
    
    show: function(options) {
        if (!this.container) this.init();
        
        const defaults = {
            type: 'info',
            title: '',
            message: '',
            duration: 5000,
            closable: true
        };
        
        const settings = { ...defaults, ...options };
        
        const toast = document.createElement('div');
        toast.className = 'atera-toast';
        
        const iconMap = {
            success: 'fas fa-check',
            error: 'fas fa-times',
            warning: 'fas fa-exclamation',
            info: 'fas fa-info'
        };
        
        toast.innerHTML = `
            <div class="atera-toast-icon ${settings.type}">
                <i class="${iconMap[settings.type]}"></i>
            </div>
            <div class="atera-toast-content">
                ${settings.title ? `<div class="atera-toast-title">${settings.title}</div>` : ''}
                <div class="atera-toast-message">${settings.message}</div>
            </div>
            ${settings.closable ? '<button class="atera-toast-close"><i class="fas fa-times"></i></button>' : ''}
        `;
        
        this.container.appendChild(toast);
        
        // Cerrar al hacer clic
        if (settings.closable) {
            toast.querySelector('.atera-toast-close').addEventListener('click', () => {
                this.close(toast);
            });
        }
        
        // Auto cerrar
        if (settings.duration > 0) {
            setTimeout(() => this.close(toast), settings.duration);
        }
        
        return toast;
    },
    
    close: function(toast) {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    },
    
    success: function(message, title = 'Éxito') {
        return this.show({ type: 'success', title, message });
    },
    
    error: function(message, title = 'Error') {
        return this.show({ type: 'error', title, message });
    },
    
    warning: function(message, title = 'Advertencia') {
        return this.show({ type: 'warning', title, message });
    },
    
    info: function(message, title = 'Información') {
        return this.show({ type: 'info', title, message });
    }
};

/**
 * Sistema de carga (Loading)
 */
AteraERP.loading = {
    overlay: null,
    
    show: function(message = 'Cargando...') {
        this.overlay = document.getElementById('loading-overlay');
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.id = 'loading-overlay';
            this.overlay.className = 'atera-loading-overlay';
            this.overlay.innerHTML = `
                <div class="atera-spinner"></div>
                <div class="loading-message" style="margin-top: 15px; color: #333;">${message}</div>
            `;
            document.body.appendChild(this.overlay);
        }
        this.overlay.style.display = 'flex';
    },
    
    hide: function() {
        if (this.overlay) {
            this.overlay.style.display = 'none';
        }
    }
};

/**
 * Sistema de confirmación (usando SweetAlert2 si está disponible)
 */
AteraERP.confirm = async function(options) {
    const defaults = {
        title: '¿Estás seguro?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1a472a',
        cancelButtonColor: '#6c757d'
    };
    
    const settings = { ...defaults, ...options };
    
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: settings.title,
            text: settings.text,
            icon: settings.icon,
            showCancelButton: true,
            confirmButtonColor: settings.confirmButtonColor,
            cancelButtonColor: settings.cancelButtonColor,
            confirmButtonText: settings.confirmButtonText,
            cancelButtonText: settings.cancelButtonText
        });
        return result.isConfirmed;
    } else {
        return confirm(settings.text);
    }
};

/**
 * Sistema de alertas (usando SweetAlert2 si está disponible)
 */
AteraERP.alert = function(options) {
    const defaults = {
        title: 'Información',
        text: '',
        icon: 'info',
        confirmButtonColor: '#1a472a'
    };
    
    const settings = { ...defaults, ...options };
    
    if (typeof Swal !== 'undefined') {
        return Swal.fire(settings);
    } else {
        alert(settings.text);
    }
};

/**
 * Cliente API
 */
AteraERP.api = {
    baseUrl: '/api/',
    
    request: async function(endpoint, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const settings = { ...defaults, ...options };
        
        if (settings.body && typeof settings.body === 'object') {
            settings.body = JSON.stringify(settings.body);
        }
        
        try {
            const response = await fetch(this.baseUrl + endpoint, settings);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Error en la solicitud');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    get: function(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },
    
    post: function(endpoint, data) {
        return this.request(endpoint, { method: 'POST', body: data });
    },
    
    put: function(endpoint, data) {
        return this.request(endpoint, { method: 'PUT', body: data });
    },
    
    delete: function(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
};

/**
 * Utilidades de formularios
 */
AteraERP.forms = {
    serialize: function(form) {
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            if (data[key]) {
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        });
        return data;
    },
    
    validate: function(form) {
        const inputs = form.querySelectorAll('[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    },
    
    reset: function(form) {
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
    }
};

/**
 * Utilidades de tablas
 */
AteraERP.tables = {
    init: function(selector, options = {}) {
        const defaults = {
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            responsive: true,
            pageLength: 25,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        };
        
        const settings = { ...defaults, ...options };
        
        if (typeof $.fn.DataTable !== 'undefined') {
            return $(selector).DataTable(settings);
        }
        
        return null;
    },
    
    refresh: function(table) {
        if (table && typeof table.ajax !== 'undefined') {
            table.ajax.reload();
        }
    }
};

/**
 * Utilidades de modales
 */
AteraERP.modals = {
    show: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal && typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            return bsModal;
        }
        return null;
    },
    
    hide: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal && typeof bootstrap !== 'undefined') {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
    },
    
    create: function(options) {
        const defaults = {
            id: 'dynamicModal',
            title: 'Modal',
            body: '',
            footer: '',
            size: '', // sm, lg, xl
            centered: true
        };
        
        const settings = { ...defaults, ...options };
        
        // Remover modal existente si hay
        const existing = document.getElementById(settings.id);
        if (existing) existing.remove();
        
        const modal = document.createElement('div');
        modal.id = settings.id;
        modal.className = 'modal fade atera-modal';
        modal.tabIndex = -1;
        
        modal.innerHTML = `
            <div class="modal-dialog ${settings.size ? 'modal-' + settings.size : ''} ${settings.centered ? 'modal-dialog-centered' : ''}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${settings.title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">${settings.body}</div>
                    ${settings.footer ? `<div class="modal-footer">${settings.footer}</div>` : ''}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        return this.show(settings.id);
    }
};

/**
 * Utilidades de fechas
 */
AteraERP.dates = {
    format: function(date, format = 'DD/MM/YYYY') {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        
        return format
            .replace('DD', day)
            .replace('MM', month)
            .replace('YYYY', year)
            .replace('HH', hours)
            .replace('mm', minutes);
    },
    
    relative: function(date) {
        const now = new Date();
        const d = new Date(date);
        const diff = now - d;
        
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        
        if (seconds < 60) return 'Hace un momento';
        if (minutes < 60) return `Hace ${minutes} minuto${minutes > 1 ? 's' : ''}`;
        if (hours < 24) return `Hace ${hours} hora${hours > 1 ? 's' : ''}`;
        if (days < 7) return `Hace ${days} día${days > 1 ? 's' : ''}`;
        
        return this.format(date);
    }
};

/**
 * Utilidades de números
 */
AteraERP.numbers = {
    format: function(number, decimals = 0) {
        return new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    },
    
    currency: function(number, currency = 'COP') {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: currency
        }).format(number);
    },
    
    percentage: function(value, total) {
        if (total === 0) return '0%';
        return ((value / total) * 100).toFixed(1) + '%';
    }
};

/**
 * Utilidades de almacenamiento local
 */
AteraERP.storage = {
    set: function(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (e) {
            console.error('Storage error:', e);
            return false;
        }
    },
    
    get: function(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            return defaultValue;
        }
    },
    
    remove: function(key) {
        localStorage.removeItem(key);
    },
    
    clear: function() {
        localStorage.clear();
    }
};

/**
 * Inicialización global
 */
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar toast
    AteraERP.toast.init();
    
    // Inicializar tooltips de Bootstrap
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Manejar formularios con clase .ajax-form
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!AteraERP.forms.validate(this)) {
                AteraERP.toast.error('Por favor complete todos los campos requeridos');
                return;
            }
            
            const action = this.getAttribute('action');
            const method = this.getAttribute('method') || 'POST';
            const data = AteraERP.forms.serialize(this);
            
            try {
                AteraERP.loading.show();
                const response = await AteraERP.api.request(action, {
                    method: method,
                    body: data
                });
                
                AteraERP.toast.success(response.message || 'Operación exitosa');
                
                // Disparar evento personalizado
                this.dispatchEvent(new CustomEvent('ajax-success', { detail: response }));
            } catch (error) {
                AteraERP.toast.error(error.message || 'Error al procesar la solicitud');
            } finally {
                AteraERP.loading.hide();
            }
        });
    });
    
    // Manejar botones de eliminación
    document.querySelectorAll('[data-delete]').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            
            const url = this.getAttribute('data-delete');
            const confirmed = await AteraERP.confirm({
                title: '¿Eliminar este elemento?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning'
            });
            
            if (confirmed) {
                try {
                    AteraERP.loading.show();
                    await AteraERP.api.delete(url);
                    AteraERP.toast.success('Elemento eliminado correctamente');
                    
                    // Recargar página o tabla
                    if (this.hasAttribute('data-reload')) {
                        location.reload();
                    }
                } catch (error) {
                    AteraERP.toast.error(error.message || 'Error al eliminar');
                } finally {
                    AteraERP.loading.hide();
                }
            }
        });
    });
    
    console.log('AteraERP Core initialized');
});

// Estilos adicionales para toast
const toastStyles = document.createElement('style');
toastStyles.textContent = `
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
`;
document.head.appendChild(toastStyles);
