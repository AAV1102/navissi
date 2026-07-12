/**
 * SYSTEM MONITOR - WorkManager ERP v2.0
 * Sistema de mo tiempo real del estado del sistema
 */

class SystemMonitor {
    constructor() {
        this.isMonitoring = false;
        this.statusInterval = null;
        this.statusIndicator = null;
        this.lastStatus = null;
        this.init();
    }

    init() {
        this.createStatusIndicator();
        this.startMonitoring();

        // Monitor network connectivity
        window.addEventListener('online', () => this.handleConnectionChange(true));
        window.addEventListener('offline', () => this.handleConnectionChange(false));
    }

    createStatusIndicator() {
        // Create floating status indicator
        const indicator = document.createElement('div');
        indicator.id = 'system-status-indicator';
        indicator.className = 'system-status-indicator';
        indicator.innerHTML = `
            <div class="status-dot"></div>
            <div class="status-text">Sistema</div>
        `;

        // Add styles
        const styles = document.createElement('style');
        styles.textContent = `
            .system-status-indicator {
                position: fixed;
                bottom: 20px;
                left: 20px;
                background: white;
                border-radius: 25px;
                padding: 8px 16px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 8px;
                z-index: 1000;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 12px;
                font-weight: 500;
            }

            .system-status-indicator:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            }

            .status-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #6c757d;
                animation: pulse 2s infinite;
            }

            .status-dot.healthy {
                background: #28a745;
            }

            .status-dot.warning {
                background: #ffc107;
            }

            .status-dot.error {
                background: #dc3545;
            }

            .status-dot.offline {
                background: #6c757d;
                animation: none;
            }

            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }

            .system-status-modal {
                max-width: 600px;
            }

            .status-check {
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 8px;
                border-left: 4px solid #dee2e6;
            }

            .status-check.healthy {
                background: #d4edda;
                border-left-color: #28a745;
            }

            .status-check.warning {
                background: #fff3cd;
                border-left-color: #ffc107;
            }

            .status-check.error {
                background: #f8d7da;
                border-left-color: #dc3545;
            }

            .status-metric {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }

            .status-metric:last-child {
                border-bottom: none;
            }
        `;

        if (!document.getElementById('system-monitor-styles')) {
            styles.id = 'system-monitor-styles';
            document.head.appendChild(styles);
        }

        document.body.appendChild(indicator);
        this.statusIndicator = indicator;

        // Add click handler to show detailed status
        indicator.addEventListener('click', () => this.showDetailedStatus());
    }

    startMonitoring() {
        if (this.isMonitoring) return;

        this.isMonitoring = true;
        this.checkSystemStatus();

        // Check every 30 seconds
        this.statusInterval = setInterval(() => {
            this.checkSystemStatus();
        }, 30000);
    }

    stopMonitoring() {
        this.isMonitoring = false;
        if (this.statusInterval) {
            clearInterval(this.statusInterval);
            this.statusInterval = null;
        }
    }

    async checkSystemStatus() {
        if (!navigator.onLine) {
            this.updateStatusIndicator('offline', 'Sin conexión');
            return;
        }

        try {
            const response = await fetch('api/system/status.php?action=health', {
                method: 'GET',
                cache: 'no-cache'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const status = await response.json();
            this.lastStatus = status;

            if (status.success) {
                this.updateStatusIndicator(status.overall_status, 'Sistema operativo');
                this.handleStatusChange(status);
            } else {
                this.updateStatusIndicator('error', 'Error del sistema');
            }
        } catch (error) {
            console.error('System status check failed:', error);
            this.updateStatusIndicator('error', 'Error de conexión');
        }
    }

    updateStatusIndicator(status, text) {
        if (!this.statusIndicator) return;

        const dot = this.statusIndicator.querySelector('.status-dot');
        const textEl = this.statusIndicator.querySelector('.status-text');

        // Remove all status classes
        dot.classList.remove('healthy', 'warning', 'error', 'offline');

        // Add current status class
        dot.classList.add(status);
        textEl.textContent = text;
    }

    handleStatusChange(status) {
        // Show notifications for critical issues
        if (status.overall_status === 'error') {
            const criticalIssues = Object.entries(status.checks)
                .filter(([key, check]) => check.status === 'error')
                .map(([key, check]) => check.message);

            if (criticalIssues.length > 0 && typeof showToast !== 'undefined') {
                showToast('Problemas críticos detectados en el sistema', 'error', {
                    persistent: true,
                    actions: [{
                        label: 'Ver detalles',
                        callback: 'systemMonitor.showDetailedStatus()'
                    }]
                });
            }
        }
    }

    handleConnectionChange(isOnline) {
        if (isOnline) {
            this.updateStatusIndicator('healthy', 'Reconectado');
            this.checkSystemStatus();

            if (typeof showToast !== 'undefined') {
                showToast('Conexión restaurada', 'success');
            }
        } else {
            this.updateStatusIndicator('offline', 'Sin conexión');

            if (typeof showToast !== 'undefined') {
                showToast('Conexión perdida - Trabajando sin conexión', 'warning', {
                    persistent: true
                });
            }
        }
    }

    async showDetailedStatus() {
        let statusData = this.lastStatus;

        // Fetch fresh data if we don't have any
        if (!statusData) {
            try {
                const response = await fetch('api/system/status.php?action=health');
                statusData = await response.json();
            } catch (error) {
                console.error('Failed to fetch status:', error);
                statusData = { success: false, error: 'No se pudo obtener el estado del sistema' };
            }
        }

        const modalContent = this.generateStatusModal(statusData);
        this.showModal('Estado del Sistema', modalContent);
    }

    generateStatusModal(statusData) {
        if (!statusData.success) {
            return `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error al obtener el estado del sistema: ${statusData.error || 'Error desconocido'}
                </div>
            `;
        }

        const checks = statusData.checks || {};
        const timestamp = new Date(statusData.timestamp).toLocaleString('es-CO');

        let content = `
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Estado General: ${this.getStatusBadge(statusData.overall_status)}</h6>
                    <small class="text-muted">Última verificación: ${timestamp}</small>
                </div>
            </div>

            <div class="row g-3">
        `;

        // System checks
        Object.entries(checks).forEach(([key, check]) => {
            content += `
                <div class="col-12">
                    <div class="status-check ${check.status}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${this.getCheckTitle(key)}</strong>
                                <div class="small text-muted">${check.message}</div>
                            </div>
                            <div>
                                ${this.getStatusBadge(check.status)}
                                ${check.response_time ? `<small class="text-muted ms-2">${check.response_time}</small>` : ''}
                            </div>
                        </div>
                        ${check.percentage ? `
                            <div class="progress mt-2" style="height: 4px;">
                                <div class="progress-bar ${check.status === 'error' ? 'bg-danger' : check.status === 'warning' ? 'bg-warning' : 'bg-success'}"
                                     style="width: ${check.percentage}%"></div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        content += `
            </div>
            <div class="mt-4">
                <button class="btn btn-outline-primary btn-sm" onclick="systemMonitor.checkSystemStatus()">
                    <i class="fas fa-sync-alt me-1"></i>Actualizar Estado
                </button>
            </div>
        `;

        return content;
    }

    getCheckTitle(key) {
        const titles = {
            database: 'Base de Datos',
            filesystem: 'Sistema de Archivos',
            sessions: 'Sesiones',
            memory: 'Memoria'
        };
        return titles[key] || key.charAt(0).toUpperCase() + key.slice(1);
    }

    getStatusBadge(status) {
        const badges = {
            healthy: '<span class="badge bg-success">Saludable</span>',
            warning: '<span class="badge bg-warning">Advertencia</span>',
            error: '<span class="badge bg-danger">Error</span>',
            offline: '<span class="badge bg-secondary">Sin conexión</span>'
        };
        return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
    }

    showModal(title, content) {
        const modalHtml = `
            <div class="modal fade" id="systemStatusModal" tabindex="-1">
                <div class="modal-dialog system-status-modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-heartbeat me-2"></i>${title}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${content}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal
        const existingModal = document.getElementById('systemStatusModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        new bootstrap.Modal(document.getElementById('systemStatusModal')).show();
    }

    // Public methods for external use
    getLastStatus() {
        return this.lastStatus;
    }

    forceStatusCheck() {
        this.checkSystemStatus();
    }
}

// Create global instance
const systemMonitor = new SystemMonitor();

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SystemMonitor, systemMonitor };
}
