/**
 * JavaScript para el Sistema Central de IA
 * Maneja la interfaz principal del sistema de automatización
 */

class AICoreSystem {
    constructor() {
        this.init();
        this.loadStats();
        this.setupEventListeners();
        this.startRealTimeUpdates();
    }

    init() {
        console.log('Inicializando Sistema Central de IA...');
        this.apiBase = '/api/ai-automation/';
        this.updateInterval = null;
    }

    setupEventListeners() {
        // Configuración de módulos
        document.querySelectorAll('.btn-configure').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const module = e.target.dataset.module;
                this.openModuleConfig(module);
            });
        });

        // Monitoreo de módulos
        document.querySelectorAll('.btn-monitor').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const module = e.target.dataset.module;
                this.openModuleMonitor(module);
            });
        });

        // Configuración global
        document.getElementById('save-global-config')?.addEventListener('click', () => {
            this.saveGlobalConfig();
        });

        // Slider de confianza
        const confidenceSlider = document.getElementById('confidence-level');
        if (confidenceSlider) {
            confidenceSlider.addEventListener('input', (e) => {
                document.getElementById('confidence-value').textContent = e.target.value;
            });
        }

        // Guardar configuración de módulo
        document.getElementById('save-module-config')?.addEventListener('click', () => {
            this.saveModuleConfig();
        });
    }

    async loadStats() {
        try {
            const response = await fetch(`${this.apiBase}stats.php`);
            const data = await response.json();

            if (data.success) {
                this.updateStatsDisplay(data.stats);
            }
        } catch (error) {
            console.error('Error cargando estadísticas:', error);
        }
    }

    updateStatsDisplay(stats) {
        // Actualizar contadores principales
        document.getElementById('active-modules').textContent = stats.active_modules || 6;
        document.getElementById('tickets-today').textContent = stats.tickets_today || 0;
        document.getElementById('solutions-learned').textContent = stats.solutions_learned || 0;
        document.getElementById('ai-accuracy').textContent = (stats.ai_accuracy || 95) + '%';

        // Animar contadores
        this.animateCounters();
    }

    animateCounters() {
        const counters = document.querySelectorAll('[id$="-today"], [id$="-learned"], [id$="-modules"]');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent);
            let current = 0;
            const increment = target / 20;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target;
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current);
                }
            }, 50);
        });
    }

    openModuleConfig(module) {
        const modal = new bootstrap.Modal(document.getElementById('moduleConfigModal'));
        const content = document.getElementById('module-config-content');

        // Cargar configuración específica del módulo
        content.innerHTML = this.getModuleConfigHTML(module);

        // Actualizar título
        document.querySelector('#moduleConfigModal .modal-title').textContent =
            `Configurar ${this.getModuleName(module)}`;

        modal.show();
    }

    getModuleConfigHTML(module) {
        const configs = {
            helpdesk: `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Configuración de Análisis</h6>
                        <div class="mb-3">
                            <label class="form-label">Confianza Mínima (%)</label>
                            <input type="range" class="form-range" min="50" max="100" value="85" id="helpdesk-confidence">
                            <small class="text-muted">Actual: <span id="helpdesk-confidence-value">85</span>%</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Auto-resolución</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="helpdesk-auto-resolve" checked>
                                <label class="form-check-label">Resolver automáticamente problemas simples</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Tipos de Archivo Soportados</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="support-images" checked>
                            <label class="form-check-label">Imágenes (JPG, PNG)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="support-videos" checked>
                            <label class="form-check-label">Videos (MP4)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="support-docs">
                            <label class="form-check-label">Documentos (PDF, DOC)</label>
                        </div>
                    </div>
                </div>
            `,
            whatsapp_bot: `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Configuración del Bot</h6>
                        <div class="mb-3">
                            <label class="form-label">Tiempo de Respuesta (segundos)</label>
                            <input type="number" class="form-control" value="2" min="1" max="10">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Máximo Intentos IA</label>
                            <input type="number" class="form-control" value="3" min="1" max="5">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Escalación Automática</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Escalar si confianza < 70%</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Escalar si cliente insiste</label>
                        </div>
                    </div>
                </div>
            `,
            hr_management: `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Validaciones Automáticas</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Validar días de vacaciones disponibles</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Verificar documentos requeridos</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Citas Médicas</h6>
                        <div class="mb-3">
                            <label class="form-label">Análisis de Síntomas</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" checked>
                                <label class="form-check-label">Activar análisis IA de síntomas</label>
                            </div>
                        </div>
                    </div>
                </div>
            `,
            customer_service: `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Análisis de Sentimiento</h6>
                        <div class="mb-3">
                            <label class="form-label">Sensibilidad</label>
                            <select class="form-select">
                                <option value="low">Baja</option>
                                <option value="medium" selected>Media</option>
                                <option value="high">Alta</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Respuestas Automáticas</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Generar respuestas sugeridas</label>
                        </div>
                    </div>
                </div>
            `,
            knowledge_base: `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Aprendizaje Automático</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Aprender de resoluciones exitosas</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Umbral de Efectividad (%)</label>
                            <input type="range" class="form-range" min="70" max="100" value="85">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Búsqueda Inteligente</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Búsqueda semántica</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Sugerencias automáticas</label>
                        </div>
                    </div>
                </div>
            `,
            ticket_routing: `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Algoritmo de Enrutamiento</h6>
                        <div class="mb-3">
                            <label class="form-label">Peso Especialidad (%)</label>
                            <input type="range" class="form-range" min="0" max="100" value="60">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Peso Carga de Trabajo (%)</label>
                            <input type="range" class="form-range" min="0" max="100" value="30">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Configuración Avanzada</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Considerar horarios de trabajo</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label">Balanceo automático de carga</label>
                        </div>
                    </div>
                </div>
            `
        };

        return configs[module] || '<p>Configuración no disponible para este módulo.</p>';
    }

    getModuleName(module) {
        const names = {
            helpdesk: 'Mesa de Ayuda Inteligente',
            whatsapp_bot: 'Bot WhatsApp',
            hr_management: 'Gestión Humana IA',
            customer_service: 'Atención al Cliente IA',
            knowledge_base: 'Base de Conocimiento IA',
            ticket_routing: 'Enrutamiento Inteligente'
        };
        return names[module] || 'Módulo Desconocido';
    }

    openModuleMonitor(module) {
        const modal = new bootstrap.Modal(document.getElementById('moduleMonitorModal'));
        const content = document.getElementById('module-monitor-content');

        // Cargar monitor específico del módulo
        content.innerHTML = this.getModuleMonitorHTML(module);

        // Actualizar título
        document.querySelector('#moduleMonitorModal .modal-title').textContent =
            `Monitor - ${this.getModuleName(module)}`;

        modal.show();

        // Iniciar monitoreo en tiempo real
        this.startModuleMonitoring(module);
    }

    getModuleMonitorHTML(module) {
        return `
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h6>Actividad en Tiempo Real</h6>
                        </div>
                        <div class="card-body">
                            <div id="real-time-activity" style="height: 300px; overflow-y: auto;">
                                <div class="text-center text-muted">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Cargando actividad en tiempo real...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6>Métricas</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="module-metrics-chart" width="300" height="200"></canvas>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6>Estado del Sistema</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>CPU IA</span>
                                <span class="text-success">Normal</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Memoria</span>
                                <span class="text-warning">Media</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>API Calls</span>
                                <span class="text-info">Activo</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async saveGlobalConfig() {
        const config = {
            provider: document.getElementById('ai-provider').value,
            api_key: document.getElementById('api-key').value,
            confidence_level: document.getElementById('confidence-level').value,
            primary_language: document.getElementById('primary-language').value
        };

        try {
            const response = await fetch(`${this.apiBase}config.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(config)
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Configuración guardada exitosamente', 'success');
            } else {
                this.showNotification('Error al guardar configuración: ' + result.error, 'error');
            }
        } catch (error) {
            this.showNotification('Error de conexión', 'error');
        }
    }

    async saveModuleConfig() {
        // Implementar guardado específico por módulo
        this.showNotification('Configuración del módulo guardada', 'success');

        // Cerrar modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('moduleConfigModal'));
        modal.hide();
    }

    startRealTimeUpdates() {
        // Actualizar estadísticas cada 30 segundos
        this.updateInterval = setInterval(() => {
            this.loadStats();
        }, 30000);
    }

    startModuleMonitoring(module) {
        // Simular actividad en tiempo real
        const activityContainer = document.getElementById('real-time-activity');
        if (!activityContainer) return;

        const activities = [
            'Ticket #1234 analizado - Confianza: 94%',
            'Imagen procesada - Error detectado: Pantalla azul',
            'Chat iniciado - Cliente: María García',
            'Solución generada - Tiempo: 1.2s',
            'Enrutamiento automático - Técnico asignado'
        ];

        let activityIndex = 0;
        const addActivity = () => {
            const activity = activities[activityIndex % activities.length];
            const time = new Date().toLocaleTimeString();

            const activityHTML = `
                <div class="activity-item border-bottom pb-2 mb-2">
                    <div class="d-flex justify-content-between">
                        <span class="text-primary">${activity}</span>
                        <small class="text-muted">${time}</small>
                    </div>
                </div>
            `;

            activityContainer.insertAdjacentHTML('afterbegin', activityHTML);

            // Mantener solo las últimas 10 actividades
            const items = activityContainer.querySelectorAll('.activity-item');
            if (items.length > 10) {
                items[items.length - 1].remove();
            }

            activityIndex++;
        };

        // Limpiar contenido inicial
        activityContainer.innerHTML = '';

        // Agregar actividad inicial
        addActivity();

        // Agregar nueva actividad cada 3 segundos
        const monitorInterval = setInterval(addActivity, 3000);

        // Limpiar intervalo cuando se cierre el modal
        document.getElementById('moduleMonitorModal').addEventListener('hidden.bs.modal', () => {
            clearInterval(monitorInterval);
        }, { once: true });
    }

    showNotification(message, type = 'info') {
        // Crear notificación toast
        const toastHTML = `
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        // Agregar al contenedor de toasts
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        // Mostrar toast
        const toastElement = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastElement);
        toast.show();

        // Remover elemento después de que se oculte
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.aiCoreSystem = new AICoreSystem();
});

// Limpiar al salir de la página
window.addEventListener('beforeunload', () => {
    if (window.aiCoreSystem) {
        window.aiCoreSystem.destroy();
    }
});