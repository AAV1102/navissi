/**
 * WorkManager ERP - Integraciones JavaScript
 * Funcionalidad para gestión de integraciones en tiempo real
 */

class IntegracionesManager {
    constructor() {
        this.currentIntegration = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadIntegrationStatus();
    }

    bindEvents() {
        // Configuración de integración
        document.getElementById('formConfigIntegration')?.addEventListener('submit', (e) => {
            this.handleConfigSubmit(e);
        });

        // Auto-refresh cada 30 segundos
        setInterval(() => {
            this.loadIntegrationStatus();
        }, 30000);
    }

    configureIntegration(integrationName) {
        this.currentIntegration = integrationName;

        fetch(`integraciones.php?action=get_config&nombre=${integrationName}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showConfigModal(integrationName, data.config);
                } else {
                    this.showError('Error cargando configuración');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('Error de conexión');
            });
    }

    showConfigModal(integrationName, config) {
        const modal = document.getElementById('modalConfigIntegration');
        const title = document.getElementById('modalConfigTitle');
        const nameField = document.getElementById('configIntegrationName');
        const fieldsContainer = document.getElementById('configFields');

        title.textContent = `Configurar ${this.getIntegrationDisplayName(integrationName)}`;
        nameField.value = integrationName;

        // Generar campos de configuración
        fieldsContainer.innerHTML = this.generateConfigFields(integrationName, config);

        showModal('modalConfigIntegration');
    }

    generateConfigFields(integrationName, config) {
        const templates = {
            zoho_desk: `
                <div class="form-group">
                    <label for="client_id">Client ID *</label>
                    <input type="text" id="client_id" name="client_id" class="form-control" 
                           value="${config.client_id || ''}" required>
                    <small class="form-text">Obténlo desde Zoho Developer Console</small>
                </div>
                <div class="form-group">
                    <label for="client_secret">Client Secret *</label>
                    <input type="password" id="client_secret" name="client_secret" class="form-control" 
                           value="${config.client_secret || ''}" required>
                </div>
                <div class="form-group">
                    <label for="api_domain">Dominio API</label>
                    <select id="api_domain" name="api_domain" class="form-control">
                        <option value="https://desk.zoho.com" ${config.api_domain === 'https://desk.zoho.com' ? 'selected' : ''}>Global (.com)</option>
                        <option value="https://desk.zoho.eu" ${config.api_domain === 'https://desk.zoho.eu' ? 'selected' : ''}>Europa (.eu)</option>
                        <option value="https://desk.zoho.in" ${config.api_domain === 'https://desk.zoho.in' ? 'selected' : ''}>India (.in)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="sync_tickets" ${config.sync_tickets ? 'checked' : ''}>
                        Sincronizar tickets automáticamente
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="sync_contacts" ${config.sync_contacts ? 'checked' : ''}>
                        Sincronizar contactos
                    </label>
                </div>
            `,
            microsoft_365: `
                <div class="form-group">
                    <label for="tenant_id">Tenant ID *</label>
                    <input type="text" id="tenant_id" name="tenant_id" class="form-control" 
                           value="${config.tenant_id || ''}" required>
                </div>
                <div class="form-group">
                    <label for="client_id">Application ID *</label>
                    <input type="text" id="client_id" name="client_id" class="form-control" 
                           value="${config.client_id || ''}" required>
                </div>
                <div class="form-group">
                    <label for="client_secret">Client Secret *</label>
                    <input type="password" id="client_secret" name="client_secret" class="form-control" 
                           value="${config.client_secret || ''}" required>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="sync_users" ${config.sync_users ? 'checked' : ''}>
                        Sincronizar usuarios
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="sync_licenses" ${config.sync_licenses ? 'checked' : ''}>
                        Sincronizar licencias
                    </label>
                </div>
            `,
            slack: `
                <div class="form-group">
                    <label for="webhook_url">Webhook URL *</label>
                    <input type="url" id="webhook_url" name="webhook_url" class="form-control" 
                           value="${config.webhook_url || ''}" required>
                    <small class="form-text">URL del webhook de Slack</small>
                </div>
                <div class="form-group">
                    <label for="channel_alerts">Canal de Alertas</label>
                    <input type="text" id="channel_alerts" name="channel_alerts" class="form-control" 
                           value="${config.channel_alerts || '#alerts'}" placeholder="#alerts">
                </div>
                <div class="form-group">
                    <label for="channel_tickets">Canal de Tickets</label>
                    <input type="text" id="channel_tickets" name="channel_tickets" class="form-control" 
                           value="${config.channel_tickets || '#tickets'}" placeholder="#tickets">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="notify_new_tickets" ${config.notify_new_tickets ? 'checked' : ''}>
                        Notificar nuevos tickets
                    </label>
                </div>
            `,
            teams: `
                <div class="form-group">
                    <label for="webhook_url">Webhook URL *</label>
                    <input type="url" id="webhook_url" name="webhook_url" class="form-control" 
                           value="${config.webhook_url || ''}" required>
                    <small class="form-text">URL del webhook de Microsoft Teams</small>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="notify_tickets" ${config.notify_tickets ? 'checked' : ''}>
                        Notificar tickets
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="notify_maintenance" ${config.notify_maintenance ? 'checked' : ''}>
                        Notificar mantenimientos
                    </label>
                </div>
            `
        };

        return templates[integrationName] || '<p>Configuración no disponible</p>';
    }

    handleConfigSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const config = {};

        // Convertir FormData a objeto
        for (let [key, value] of formData.entries()) {
            if (key === 'nombre' || key === 'action') continue;

            // Manejar checkboxes
            if (e.target.querySelector(`[name="${key}"]`)?.type === 'checkbox') {
                config[key] = true;
            } else {
                config[key] = value;
            }
        }

        // Agregar checkboxes no marcados como false
        e.target.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            if (!formData.has(checkbox.name)) {
                config[checkbox.name] = false;
            }
        });

        this.saveConfiguration(this.currentIntegration, config);
    }

    saveConfiguration(integrationName, config) {
        const submitBtn = document.querySelector('#formConfigIntegration button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

        fetch('integraciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'update_config',
                nombre: integrationName,
                config: JSON.stringify(config)
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess('Configuración guardada exitosamente');
                    hideModal('modalConfigIntegration');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showError('Error guardando configuración');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('Error de conexión');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
    }

    authorizeIntegration(integrationName) {
        // Obtener URL de autorización
        fetch(`integraciones.php?action=get_oauth_url&nombre=${integrationName}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.oauth_url) {
                    // Abrir ventana de autorización
                    const authWindow = window.open(
                        data.oauth_url,
                        'oauth_authorization',
                        'width=600,height=700,scrollbars=yes,resizable=yes'
                    );

                    // Monitorear el cierre de la ventana
                    const checkClosed = setInterval(() => {
                        if (authWindow.closed) {
                            clearInterval(checkClosed);
                            // Recargar página después de un momento
                            setTimeout(() => location.reload(), 2000);
                        }
                    }, 1000);
                } else {
                    this.showError('Error obteniendo URL de autorización');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('Error de conexión');
            });
    }

    toggleIntegration(integrationName, activate) {
        const action = activate ? 'activar' : 'desactivar';

        if (!confirm(`¿Estás seguro de que deseas ${action} esta integración?`)) {
            return;
        }

        fetch('integraciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'toggle_integration',
                nombre: integrationName,
                activa: activate ? '1' : '0'
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess(`Integración ${activate ? 'activada' : 'desactivada'} exitosamente`);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showError(`Error ${activate ? 'activando' : 'desactivando'} integración`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('Error de conexión');
            });
    }

    syncIntegration(integrationName) {
        if (!confirm('¿Deseas sincronizar esta integración ahora?')) {
            return;
        }

        // Mostrar indicador de carga
        const syncBtn = document.querySelector(`[onclick="syncIntegration('${integrationName}')"]`);
        const originalText = syncBtn.innerHTML;

        syncBtn.disabled = true;
        syncBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sincronizando...';

        fetch('integraciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'sync_integration',
                nombre: integrationName
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess('Sincronización completada exitosamente');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    this.showError(data.message || 'Error en la sincronización');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('Error de conexión');
            })
            .finally(() => {
                syncBtn.disabled = false;
                syncBtn.innerHTML = originalText;
            });
    }

    loadIntegrationStatus() {
        // Actualizar estado de las integraciones sin recargar la página
        fetch('integraciones.php?action=get_status')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateIntegrationCards(data.integrations);
                }
            })
            .catch(error => {
                console.error('Error loading status:', error);
            });
    }

    updateIntegrationCards(integrations) {
        integrations.forEach(integration => {
            const card = document.querySelector(`[data-integration="${integration.nombre}"]`);
            if (card) {
                const statusBadge = card.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.className = `status-badge status-${integration.activa ? 'success' : 'inactive'}`;
                    statusBadge.textContent = integration.activa ? 'Activa' : 'Inactiva';
                }

                const lastSync = card.querySelector('.last-sync');
                if (lastSync && integration.last_sync) {
                    lastSync.innerHTML = `<small>Última sincronización: ${new Date(integration.last_sync).toLocaleString()}</small>`;
                }
            }
        });
    }

    getIntegrationDisplayName(name) {
        const names = {
            zoho_desk: 'Zoho Desk',
            microsoft_365: 'Microsoft 365',
            google_workspace: 'Google Workspace',
            slack: 'Slack',
            teams: 'Microsoft Teams'
        };

        return names[name] || name.replace('_', ' ').toUpperCase();
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        // Crear notificación temporal
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            ${message}
        `;

        // Agregar estilos si no existen
        if (!document.getElementById('notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 5px;
                    color: white;
                    font-weight: 600;
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                }
                .notification-success { background: #28a745; }
                .notification-error { background: #dc3545; }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(notification);

        // Remover después de 5 segundos
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

// Funciones globales para compatibilidad
function configureIntegration(name) {
    window.integracionesManager.configureIntegration(name);
}

function authorizeIntegration(name) {
    window.integracionesManager.authorizeIntegration(name);
}

function toggleIntegration(name, activate) {
    window.integracionesManager.toggleIntegration(name, activate);
}

function syncIntegration(name) {
    window.integracionesManager.syncIntegration(name);
}

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function () {
    window.integracionesManager = new IntegracionesManager();
});