/**
 * DASHBOARD MODERNO JS - WorkManager ERP v2.0
 * ================================
 * JavaScript para el dashboard estilo centro de control
 */

// Variables globales
let systemStatsChart;
let updateIntervals = {};
let isActivityPaused = false;
let config = {};

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    config = window.dashboardConfig || {};
    initializeDashboard();
    startRealTimeUpdates();
});

// Inicializar dashboard
function initializeDashboard() {
    loadModules();
    loadAlerts();
    loadServerStatus();
    loadRealtimeActivity();
    loadSLAMetrics();
    loadLocationTickets();
    initializeCharts();

    showNotification('Centro de Control iniciado', 'success');
}

// Cargar módulos dinámicamente
function loadModules() {
    const container = document.getElementById('modules-container');
    const moduleColors = [
        'linear-gradient(135deg, #667eea, #764ba2)',
        'linear-gradient(135deg, #f093fb, #f5576c)',
        'linear-gradient(135deg, #4facfe, #00f2fe)',
        'linear-gradient(135deg, #43e97b, #38f9d7)',
        'linear-gradient(135deg, #fa709a, #fee140)',
        'linear-gradient(135deg, #a8edea, #fed6e3)',
        'linear-gradient(135deg, #ff9a9e, #fecfef)',
        'linear-gradient(135deg, #ffecd2, #fcb69f)',
        'linear-gradient(135deg, #d299c2, #fef9d7)',
        'linear-gradient(135deg, #89f7fe, #66a6ff)'
    ];

    const moduleIcons = {
        'inventario': 'fas fa-boxes',
        'empleados': 'fas fa-users',
        'tickets': 'fas fa-ticket-alt',
        'sistemas': 'fas fa-server',
        'reportes': 'fas fa-chart-bar',
        'admin': 'fas fa-cog',
        'sedes': 'fas fa-building',
        'equipos': 'fas fa-laptop',
        'biomedica': 'fas fa-heartbeat',
        'juridico': 'fas fa-gavel',
        'tesoreria': 'fas fa-coins',
        'nomina': 'fas fa-money-bill',
        'vacaciones': 'fas fa-calendar-alt',
        'capacitaciones': 'fas fa-graduation-cap',
        'evaluaciones': 'fas fa-star',
        'contratos': 'fas fa-file-contract',
        'documentos': 'fas fa-file-alt',
        'facturacion': 'fas fa-receipt',
        'crm': 'fas fa-handshake',
        'mesa-ayuda': 'fas fa-headset',
        'soporte': 'fas fa-tools',
        'sst': 'fas fa-hard-hat',
        'gestion-humana': 'fas fa-user-tie',
        'hoja-vida': 'fas fa-id-card',
        'permisos-laborales': 'fas fa-calendar-check',
        'asistencia': 'fas fa-clock',
        'citas': 'fas fa-calendar-plus',
        'historia-clinica': 'fas fa-file-medical',
        'enfermeria': 'fas fa-user-nurse',
        'medico': 'fas fa-user-md',
        'farmacia': 'fas fa-pills',
        'educacion': 'fas fa-book',
        'integraciones': 'fas fa-plug',
        'office365': 'fab fa-microsoft',
        'whatsapp': 'fab fa-whatsapp',
        'n8n-automation': 'fas fa-robot',
        'ai-automation': 'fas fa-brain',
        'mikrotik': 'fas fa-wifi',
        'vpn-management': 'fas fa-shield-alt',
        'buscador-universal': 'fas fa-search',
        'exportador-universal': 'fas fa-download',
        'importador-universal': 'fas fa-upload',
        'qr-codes': 'fas fa-qrcode',
        'huelleros': 'fas fa-fingerprint',
        'autodiscovery': 'fas fa-search-plus',
        'diagnostico': 'fas fa-stethoscope',
        'configuracion': 'fas fa-sliders-h',
        'flujos': 'fas fa-project-diagram',
        'servicio-cliente': 'fas fa-phone',
        'administracion': 'fas fa-user-shield',
        'actives': 'fas fa-check-circle',
        'agente-multiplataforma': 'fas fa-desktop',
        'ia': 'fas fa-robot',
        'documental': 'fas fa-folder',
        'documentacion': 'fas fa-book-open',
        'licencias': 'fas fa-key',
        'roles': 'fas fa-users-cog',
        'prioridades': 'fas fa-flag',
        'categorias-tickets': 'fas fa-tags',
        'telecomunicaciones': 'fas fa-broadcast-tower',
        'ciberseguridad': 'fas fa-shield-virus',
        'automation': 'fas fa-cogs',
        'setup': 'fas fa-wrench',
        'logistica': 'fas fa-truck',
        'empresas': 'fas fa-industry'
    };

    let html = '';
    config.modules.forEach((module, index) => {
        const colorIndex = index % moduleColors.length;
        const icon = moduleIcons[module] || 'fas fa-cube';
        const moduleName = formatModuleName(module);

        html += `
            <div class="module-card fade-in" onclick="openModule('${module}')" style="animation-delay: ${index * 0.1}s">
                <div class="module-header">
                    <div class="module-icon" style="background: ${moduleColors[colorIndex]};">
                        <i class="${icon}"></i>
                    </div>
                    <div class="module-info">
                        <h3>${moduleName}</h3>
                        <p>Módulo ${module}</p>
                    </div>
                </div>
                <div class="module-stats">
                    <div class="module-stat">
                        <div class="module-stat-value" id="stat-${module}-records">-</div>
                        <div class="module-stat-label">Registros</div>
                    </div>
                    <div class="module-stat">
                        <div class="module-stat-value" id="stat-${module}-active">-</div>
                        <div class="module-stat-label">Activos</div>
                    </div>
                    <div class="module-stat">
                        <div class="module-stat-value">
                            <span class="status-indicator status-online"></span>
                        </div>
                        <div class="module-stat-label">Estado</div>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;

    // Cargar estadísticas de módulos
    setTimeout(() => {
        loadModuleStats();
    }, 1000);
}

// Cargar estadísticas de módulos desde la BD
async function loadModuleStats() {
    try {
        const response = await fetch('/api/dashboard/module-stats.php');
        const data = await response.json();

        if (data.success && data.stats) {
            Object.entries(data.stats).forEach(([module, stats]) => {
                const recordsEl = document.getElementById(`stat-${module}-records`);
                const activeEl = document.getElementById(`stat-${module}-active`);

                if (recordsEl) {
                    animateNumber(recordsEl, 0, stats.records || 0, 1000);
                }
                if (activeEl) {
                    animateNumber(activeEl, 0, stats.active || 0, 1000);
                }
            });

            // Actualizar estadísticas del sistema
            if (data.system) {
                updateSystemStats(data.system);
            }
        } else {
            // Fallback a estadísticas simuladas
            config.modules.forEach((module, index) => {
                const recordsEl = document.getElementById(`stat-${module}-records`);
                const activeEl = document.getElementById(`stat-${module}-active`);

                const records = Math.floor(Math.random() * 1000) + 50;
                const active = Math.floor(records * (0.7 + Math.random() * 0.3));

                if (recordsEl) {
                    animateNumber(recordsEl, 0, records, 1000);
                }
                if (activeEl) {
                    animateNumber(activeEl, 0, active, 1000);
                }
            });
        }
    } catch (error) {
        console.error('Error loading module stats:', error);
    }
}

// Actualizar estadísticas del sistema
function updateSystemStats(systemStats) {
    if (systemStats.total_records) {
        const totalEl = document.querySelector('.stat-value[data-stat="total-records"]');
        if (totalEl) {
            animateNumber(totalEl, 0, systemStats.total_records, 1500);
        }
    }

    if (systemStats.system_health) {
        const healthEl = document.getElementById('system-health');
        if (healthEl) {
            healthEl.textContent = systemStats.system_health + '%';
        }
    }
}

// Actualizar estadísticas de alertas
function updateAlertStats(alertStats) {
    // Actualizar indicadores de alertas en el header si existen
    const criticalEl = document.querySelector('.alert-critical-count');
    const warningEl = document.querySelector('.alert-warning-count');

    if (criticalEl) {
        criticalEl.textContent = alertStats.critical || 0;
    }
    if (warningEl) {
        warningEl.textContent = alertStats.warning || 0;
    }
}

// Actualizar recursos del sistema
function updateSystemResources(resources) {
    // Actualizar gráfico con datos reales de recursos
    if (systemStatsChart && resources) {
        const currentTime = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });

        // Actualizar datos del gráfico
        systemStatsChart.data.labels.shift();
        systemStatsChart.data.labels.push(currentTime);

        // CPU
        systemStatsChart.data.datasets[0].data.shift();
        systemStatsChart.data.datasets[0].data.push(resources.cpu?.usage || 0);

        // Memoria
        systemStatsChart.data.datasets[1].data.shift();
        systemStatsChart.data.datasets[1].data.push(resources.memory?.usage || 0);

        // Disco
        systemStatsChart.data.datasets[2].data.shift();
        systemStatsChart.data.datasets[2].data.push(resources.disk?.usage || 0);

        systemStatsChart.update('none');
    }
}

// Cargar alertas recientes
async function loadAlerts() {
    const container = document.getElementById('alerts-content');

    try {
        const response = await fetch('/api/dashboard/alerts.php');
        const data = await response.json();

        if (data.success && data.alerts) {
            let html = '';
            data.alerts.slice(0, 5).forEach(alert => {
                const level = alert.level || alert.prioridad || 'info';
                const statusClass = level === 'critical' || level === 'critica' ? 'status-offline' :
                                  level === 'warning' || level === 'alta' ? 'status-warning' : 'status-online';

                const title = alert.title || alert.titulo || 'Alerta del sistema';
                const description = alert.description || alert.descripcion || 'Sin descripción';
                const location = alert.location || alert.ubicacion || 'Sistema';
                const timestamp = new Date(alert.created_at || alert.fecha_creacion || Date.now());

                html += `
                    <div class="d-flex align-items-center mb-2 p-2 rounded fade-in" style="background: rgba(0,0,0,0.02);">
                        <span class="status-indicator ${statusClass}"></span>
                        <div class="flex-grow-1">
                            <div class="fw-bold small">${title}</div>
                            <div class="text-muted small">${description}</div>
                            <div class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i>${location}</div>
                        </div>
                        <small class="text-muted">${formatTimeAgo(timestamp)}</small>
                    </div>
                `;
            });

            container.innerHTML = html || '<div class="text-muted text-center">No hay alertas recientes</div>';

            // Actualizar estadísticas de alertas en el header
            if (data.stats) {
                updateAlertStats(data.stats);
            }
        } else {
            throw new Error(data.error || 'Error cargando alertas');
        }
    } catch (error) {
        console.error('Error loading alerts:', error);
        container.innerHTML = '<div class="text-danger text-center">Error cargando alertas</div>';
    }
}

// Cargar estado de servidores
async function loadServerStatus() {
    const container = document.getElementById('server-status');

    try {
        const response = await fetch('/api/dashboard/server-status.php');
        const data = await response.json();

        if (data.success && data.services) {
            let html = '';
            Object.values(data.services).forEach(server => {
                const badgeClass = server.status === 'online' ? 'bg-success' :
                                  server.status === 'warning' ? 'bg-warning' : 'bg-danger';
                const statusText = server.status === 'online' ? 'Online' :
                                  server.status === 'warning' ? 'Lento' : 'Offline';

                html += `
                    <div class="d-flex justify-content-between align-items-center mb-2 fade-in">
                        <span>
                            <span class="status-indicator status-${server.status}"></span>
                            ${server.name}
                        </span>
                        <div class="text-end">
                            <span class="badge ${badgeClass}">${statusText}</span>
                            <div class="small text-muted">${server.uptime || 'N/A'}</div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            // Actualizar recursos del sistema si están disponibles
            if (data.resources) {
                updateSystemResources(data.resources);
            }
        } else {
            throw new Error(data.error || 'Error cargando estado de servidores');
        }
    } catch (error) {
        console.error('Error loading server status:', error);
        container.innerHTML = '<div class="text-danger text-center">Error cargando estado</div>';
    }
}

// Cargar actividad en tiempo real
async function loadRealtimeActivity() {
    if (isActivityPaused) return;

    const container = document.getElementById('realtime-activity');

    try {
        const response = await fetch('/api/dashboard/activity.php');
        const data = await response.json();

        if (data.success && data.activities) {
            // Tomar solo la actividad más reciente para agregar
            const latestActivity = data.activities[0];

            if (latestActivity) {
                const timestamp = new Date(latestActivity.timestamp).toLocaleTimeString();
                const icon = latestActivity.icon || 'fas fa-info-circle';
                const color = latestActivity.color || 'text-info';

                const activityHtml = `
                    <div class="d-flex align-items-center mb-2 p-2 rounded fade-in" style="background: rgba(0,0,0,0.02);">
                        <div class="flex-shrink-0">
                            <i class="${icon} ${color}"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold small">${latestActivity.user}</div>
                            <div class="text-muted small">${latestActivity.description}</div>
                            <div class="text-muted small">
                                <i class="fas fa-clock me-1"></i>${timestamp}
                                ${latestActivity.location ? `<i class="fas fa-map-marker-alt ms-2 me-1"></i>${latestActivity.location}` : ''}
                            </div>
                        </div>
                    </div>
                `;

                container.insertAdjacentHTML('afterbegin', activityHtml);

                // Mantener solo las últimas 5 actividades
                const activities_elements = container.children;
                if (activities_elements.length > 5) {
                    container.removeChild(activities_elements[activities_elements.length - 1]);
                }
            }
        }
    } catch (error) {
        console.error('Error loading activity:', error);
        // Fallback a actividad simulada
        loadSimulatedActivity();
    }
}

// Función de respaldo para actividad simulada
function loadSimulatedActivity() {
    if (isActivityPaused) return;

    const container = document.getElementById('realtime-activity');
    const activities = [
        { user: 'Juan Pérez', action: 'creó un ticket de soporte', icon: 'fas fa-ticket-alt', color: 'text-warning' },
        { user: 'María García', action: 'actualizó inventario de equipos', icon: 'fas fa-boxes', color: 'text-primary' },
        { user: 'Carlos López', action: 'asignó equipo a empleado', icon: 'fas fa-user-plus', color: 'text-success' },
        { user: 'Ana Rodríguez', action: 'cerró ticket #1234', icon: 'fas fa-check-circle', color: 'text-success' },
        { user: 'Luis Martínez', action: 'generó reporte mensual', icon: 'fas fa-chart-bar', color: 'text-info' },
        { user: 'Sistema', action: 'ejecutó backup automático', icon: 'fas fa-database', color: 'text-secondary' }
    ];

    const randomActivity = activities[Math.floor(Math.random() * activities.length)];
    const timestamp = new Date().toLocaleTimeString();

    const activityHtml = `
        <div class="d-flex align-items-center mb-2 p-2 rounded fade-in" style="background: rgba(0,0,0,0.02);">
            <div class="flex-shrink-0">
                <i class="${randomActivity.icon} ${randomActivity.color}"></i>
            </div>
            <div class="flex-grow-1 ms-3">
                <div class="fw-bold small">${randomActivity.user}</div>
                <div class="text-muted small">${randomActivity.action}</div>
                <div class="text-muted small">${timestamp}</div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('afterbegin', activityHtml);

    // Mantener solo las últimas 5 actividades
    const activities_elements = container.children;
    if (activities_elements.length > 5) {
        container.removeChild(activities_elements[activities_elements.length - 1]);
    }
}

// Inicializar gráficos
function initializeCharts() {
    const ctx = document.getElementById('systemStatsChart').getContext('2d');

    systemStatsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
            datasets: [{
                label: 'CPU %',
                data: [45, 52, 48, 61, 55, 47],
                borderColor: 'rgb(37, 99, 235)',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Memoria %',
                data: [38, 42, 45, 48, 44, 41],
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Disco %',
                data: [65, 67, 69, 71, 68, 66],
                borderColor: 'rgb(245, 158, 11)',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6
                }
            }
        }
    });

    // Actualizar gráfico cada 30 segundos con datos simulados
    setInterval(() => {
        updateChart();
    }, 30000);
}

// Actualizar gráfico con nuevos datos
function updateChart() {
    if (!systemStatsChart) return;

    // Generar nuevos datos simulados
    const newCpuData = systemStatsChart.data.datasets[0].data.slice(1);
    newCpuData.push(Math.floor(Math.random() * 40) + 30);

    const newMemoryData = systemStatsChart.data.datasets[1].data.slice(1);
    newMemoryData.push(Math.floor(Math.random() * 30) + 35);

    const newDiskData = systemStatsChart.data.datasets[2].data.slice(1);
    newDiskData.push(Math.floor(Math.random() * 10) + 60);

    // Actualizar labels
    const currentTime = new Date();
    const newLabel = currentTime.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    systemStatsChart.data.labels.shift();
    systemStatsChart.data.labels.push(newLabel);

    // Actualizar datos
    systemStatsChart.data.datasets[0].data = newCpuData;
    systemStatsChart.data.datasets[1].data = newMemoryData;
    systemStatsChart.data.datasets[2].data = newDiskData;

    systemStatsChart.update('none');
}

// Iniciar actualizaciones en tiempo real
function startRealTimeUpdates() {
    if (!config.realTimeEnabled) return;

    // Actualizar actividad cada 3 segundos
    updateIntervals.activity = setInterval(loadRealtimeActivity, 3000);

    // Actualizar estadísticas cada 30 segundos
    updateIntervals.stats = setInterval(() => {
        loadModuleStats();
        updateHeaderStats();
    }, 30000);

    // Actualizar alertas cada 60 segundos
    updateIntervals.alerts = setInterval(loadAlerts, 60000);

    // Actualizar estado de servidores cada 45 segundos
    updateIntervals.servers = setInterval(loadServerStatus, 45000);

    // Actualizar SLA cada 2 minutos
    updateIntervals.sla = setInterval(loadSLAMetrics, 120000);

    // Actualizar tickets por ubicación cada 90 segundos
    updateIntervals.locations = setInterval(loadLocationTickets, 90000);
}

// Actualizar estadísticas del header
function updateHeaderStats() {
    const activeUsers = Math.floor(Math.random() * 50) + 20;
    const systemHealth = (95 + Math.random() * 5).toFixed(1);

    animateNumber(document.getElementById('active-users'),
                  parseInt(document.getElementById('active-users').textContent) || 0,
                  activeUsers, 1000);

    document.getElementById('system-health').textContent = systemHealth + '%';
}

// Funciones de utilidad
function formatModuleName(module) {
    return module
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = Math.floor((now - time) / 1000);

    if (diff < 60) return diff + ' seg';
    if (diff < 3600) return Math.floor(diff / 60) + ' min';
    if (diff < 86400) return Math.floor(diff / 3600) + ' h';
    return Math.floor(diff / 86400) + ' d';
}

function animateNumber(element, start, end, duration) {
    if (!element) return;

    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;

    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            current = end;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current).toLocaleString();
    }, 16);
}

function showNotification(message, type = 'info') {
    const container = document.querySelector('.toast-container');
    const toast = document.createElement('div');
    toast.className = `toast custom-toast show`;
    toast.innerHTML = `
        <div class="toast-body d-flex align-items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle text-success' :
                              type === 'error' ? 'exclamation-triangle text-danger' :
                              'info-circle text-info'} me-2"></i>
            ${message}
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 5000);
}

// Event handlers
function openModule(module) {
    showNotification(`Abriendo módulo ${formatModuleName(module)}...`, 'info');
    setTimeout(() => {
        window.location.href = `/dashboards/${module}/dashboard.php`;
    }, 500);
}

function refreshAlerts() {
    loadAlerts();
    showNotification('Alertas actualizadas', 'success');
}

function refreshStats() {
    loadModuleStats();
    updateHeaderStats();
    updateChart();
    showNotification('Estadísticas actualizadas', 'success');
}

function refreshServerStatus() {
    loadServerStatus();
    showNotification('Estado de servidores actualizado', 'success');
}

function refreshModules() {
    loadModuleStats();
    showNotification('Módulos actualizados', 'success');
}

function toggleModuleView() {
    const container = document.getElementById('modules-container');
    const toggle = document.getElementById('view-toggle');

    if (container.style.gridTemplateColumns === '1fr') {
        container.style.gridTemplateColumns = 'repeat(auto-fill, minmax(280px, 1fr))';
        toggle.className = 'fas fa-th';
        showNotification('Vista de cuadrícula activada', 'info');
    } else {
        container.style.gridTemplateColumns = '1fr';
        toggle.className = 'fas fa-list';
        showNotification('Vista de lista activada', 'info');
    }
}

function toggleActivity() {
    isActivityPaused = !isActivityPaused;
    const toggle = document.getElementById('activity-toggle');
    toggle.className = isActivityPaused ? 'fas fa-play' : 'fas fa-pause';

    if (isActivityPaused) {
        clearInterval(updateIntervals.activity);
        showNotification('Actividad en tiempo real pausada', 'info');
    } else {
        updateIntervals.activity = setInterval(loadRealtimeActivity, 3000);
        showNotification('Actividad en tiempo real reanudada', 'success');
    }
}

// Cargar métricas de SLA
async function loadSLAMetrics() {
    const container = document.getElementById('sla-metrics');

    try {
        const response = await fetch('/api/dashboard/server-status.php');
        const data = await response.json();

        if (data.success && data.sla) {
            const sla = data.sla;
            let html = `
                <div class="row g-2">
                    <div class="col-6">
                        <div class="text-center p-2">
                            <div class="h4 text-primary mb-0">${sla.availability}</div>
                            <small class="text-muted">Disponibilidad</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2">
                            <div class="h4 text-success mb-0">${sla.customer_satisfaction}</div>
                            <small class="text-muted">Satisfacción</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2">
                            <div class="h4 text-info mb-0">${sla.response_time}</div>
                            <small class="text-muted">Tiempo Respuesta</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2">
                            <div class="h4 text-warning mb-0">${sla.resolution_time}</div>
                            <small class="text-muted">Tiempo Resolución</small>
                        </div>
                    </div>
                </div>
            `;
            container.innerHTML = html;
        } else {
            throw new Error('Error cargando métricas SLA');
        }
    } catch (error) {
        console.error('Error loading SLA metrics:', error);
        container.innerHTML = '<div class="text-danger text-center">Error cargando SLA</div>';
    }
}

// Cargar tickets por ubicación
async function loadLocationTickets() {
    const container = document.getElementById('location-tickets');

    try {
        const response = await fetch('/api/dashboard/server-status.php');
        const data = await response.json();

        if (data.success && data.locations) {
            let html = '';
            Object.values(data.locations).forEach(location => {
                const alertClass = location.alerts > 2 ? 'text-danger' : location.alerts > 0 ? 'text-warning' : 'text-success';

                html += `
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded fade-in" style="background: rgba(0,0,0,0.02);">
                        <div>
                            <div class="fw-bold small">${location.name}</div>
                            <div class="text-muted small">
                                <i class="fas fa-server me-1"></i>${location.online}/${location.servers} servidores
                                <i class="fas fa-ticket-alt ms-2 me-1"></i>${location.tickets} tickets
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="small ${alertClass}">
                                <i class="fas fa-exclamation-triangle me-1"></i>${location.alerts}
                            </div>
                            <div class="small text-success">${location.satisfaction}</div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        } else {
            throw new Error('Error cargando tickets por ubicación');
        }
    } catch (error) {
        console.error('Error loading location tickets:', error);
        container.innerHTML = '<div class="text-danger text-center">Error cargando ubicaciones</div>';
    }
}

// Funciones de actualización adicionales
function refreshSLA() {
    loadSLAMetrics();
    showNotification('Métricas SLA actualizadas', 'success');
}

function refreshLocationTickets() {
    loadLocationTickets();
    showNotification('Tickets por ubicación actualizados', 'success');
}

// Cleanup al salir
window.addEventListener('beforeunload', () => {
    Object.values(updateIntervals).forEach(interval => {
        if (interval) clearInterval(interval);
    });
});
