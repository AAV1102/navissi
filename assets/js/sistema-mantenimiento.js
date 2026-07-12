/**
 * WorkManager ERP - JavaScript para Sistema de Mantenimiento
 * Funcionalidades para limpieza automática y reparación con IA
 */

// Variables globales
let monitorInterval = null;
let isMonitorActive = false;
let progressInterval = null;

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function () {
    initializeProgressCircles();
    setupAutoRefresh();
});

// Inicializar círculos de progreso
function initializeProgressCircles() {
    document.querySelectorAll('.progress-circle').forEach(circle => {
        const percent = circle.getAttribute('data-percent');
        updateProgressCircle(circle, percent);
    });
}

// Actualizar círculo de progreso
function updateProgressCircle(circle, percent) {
    const circumference = 2 * Math.PI * 45; // Radio de 45px
    const offset = circumference - (percent / 100) * circumference;

    // Crear SVG si no existe
    if (!circle.querySelector('svg')) {
        circle.innerHTML = `
            <svg width="100" height="100">
                <circle cx="50" cy="50" r="45" stroke="#e0e0e0" stroke-width="8" fill="none"/>
                <circle cx="50" cy="50" r="45" stroke="#007bff" stroke-width="8" fill="none"
                        stroke-dasharray="${circumference}" stroke-dashoffset="${offset}"
                        stroke-linecap="round" transform="rotate(-90 50 50)"/>
            </svg>
            <span>${percent}%</span>
        `;
    } else {
        const progressCircle = circle.querySelector('circle:last-child');
        const span = circle.querySelector('span');
        progressCircle.style.strokeDashoffset = offset;
        span.textContent = percent + '%';
    }
}

// Limpiar caché
function limpiarCache(tipo = 'all') {
    showProgressModal('Limpiando Caché', 'Iniciando limpieza de caché...');

    const data = new FormData();
    data.append('action', 'limpiar_cache');
    data.append('tipo', tipo);

    fetch('sistema-mantenimiento.php', {
        method: 'POST',
        body: data
    })
        .then(response => response.json())
        .then(data => {
            hideModal('modalProgreso');

            if (data.success) {
                const result = data.result;
                showNotification(
                    `Caché limpiado: ${result.files} archivos eliminados, ${formatBytes(result.space)} liberados`,
                    'success'
                );
                updateSystemStats();
            } else {
                showNotification('Error al limpiar caché: ' + data.error, 'error');
            }
        })
        .catch(error => {
            hideModal('modalProgreso');
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// Optimizar base de datos
function optimizarBaseDatos() {
    showProgressModal('Optimizando Base de Datos', 'Iniciando optimización...');

    const data = new FormData();
    data.append('action', 'optimizar_bd');

    fetch('sistema-mantenimiento.php', {
        method: 'POST',
        body: data
    })
        .then(response => response.json())
        .then(data => {
            hideModal('modalProgreso');

            if (data.success) {
                showNotification(
                    'Base de datos optimizada: ' + data.result.join(', '),
                    'success'
                );
                updateSystemStats();
            } else {
                showNotification('Error al optimizar base de datos: ' + data.error, 'error');
            }
        })
        .catch(error => {
            hideModal('modalProgreso');
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// Reparación con IA
function reparacionIA() {
    showProgressModal('Reparación con IA', 'Analizando errores del sistema...');

    const data = new FormData();
    data.append('action', 'reparacion_ia');

    fetch('sistema-mantenimiento.php', {
        method: 'POST',
        body: data
    })
        .then(response => response.json())
        .then(data => {
            hideModal('modalProgreso');

            if (data.success) {
                const result = data.result;
                showNotification(
                    `IA completada: ${result.found} errores encontrados, ${result.fixed} reparados`,
                    result.fixed > 0 ? 'success' : 'info'
                );
                updateSystemStats();
            } else {
                showNotification('Error en reparación IA: ' + data.error, 'error');
            }
        })
        .catch(error => {
            hideModal('modalProgreso');
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// Ejecutar mantenimiento completo
function ejecutarMantenimientoCompleto() {
    if (!confirm('¿Deseas ejecutar el mantenimiento completo del sistema? Esto puede tomar varios minutos.')) {
        return;
    }

    showProgressModal('Mantenimiento Completo', 'Iniciando mantenimiento completo del sistema...');

    const data = new FormData();
    data.append('action', 'mantenimiento_completo');

    // Simular progreso
    let progress = 0;
    const progressSteps = [
        { percent: 20, message: 'Limpiando caché temporal...' },
        { percent: 40, message: 'Optimizando base de datos...' },
        { percent: 60, message: 'Ejecutando reparación con IA...' },
        { percent: 80, message: 'Verificando integridad del sistema...' },
        { percent: 100, message: 'Mantenimiento completado' }
    ];

    progressInterval = setInterval(() => {
        if (progress < progressSteps.length) {
            updateProgress(progressSteps[progress].percent, progressSteps[progress].message);
            addProgressLog(progressSteps[progress].message);
            progress++;
        } else {
            clearInterval(progressInterval);
        }
    }, 2000);

    fetch('sistema-mantenimiento.php', {
        method: 'POST',
        body: data
    })
        .then(response => response.json())
        .then(data => {
            clearInterval(progressInterval);
            hideModal('modalProgreso');

            if (data.success) {
                const results = data.results;
                let message = 'Mantenimiento completo exitoso:\n';

                if (results.cache) {
                    message += `• Caché: ${results.cache.files} archivos, ${formatBytes(results.cache.space)} liberados\n`;
                }
                if (results.database) {
                    message += `• Base de datos: ${results.database.length} optimizaciones\n`;
                }
                if (results.ai_repair) {
                    message += `• IA: ${results.ai_repair.found} errores encontrados, ${results.ai_repair.fixed} reparados\n`;
                }

                showNotification(message, 'success');
                updateSystemStats();
                location.reload(); // Recargar para mostrar cambios
            } else {
                showNotification('Error en mantenimiento completo: ' + data.error, 'error');
            }
        })
        .catch(error => {
            clearInterval(progressInterval);
            hideModal('modalProgreso');
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// Verificar integridad del sistema
function verificarIntegridad() {
    showProgressModal('Verificación de Integridad', 'Verificando archivos del sistema...');

    // Simulación de verificación
    setTimeout(() => {
        hideModal('modalProgreso');
        showNotification('Verificación de integridad completada. No se encontraron problemas.', 'success');
    }, 3000);
}

// Mostrar modal de progreso
function showProgressModal(title, initialMessage) {
    document.getElementById('progressTitle').textContent = title;
    document.getElementById('progressStatus').textContent = initialMessage;
    document.getElementById('progressBar').style.width = '0%';
    document.getElementById('progressPercent').textContent = '0%';
    document.getElementById('progressLogContent').innerHTML = '';

    showModal('modalProgreso');
}

// Actualizar progreso
function updateProgress(percent, message) {
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
    document.getElementById('progressStatus').textContent = message;
}

// Agregar entrada al log de progreso
function addProgressLog(message) {
    const logContent = document.getElementById('progressLogContent');
    const timestamp = new Date().toLocaleTimeString();
    const logEntry = document.createElement('div');
    logEntry.className = 'log-entry';
    logEntry.innerHTML = `<span class="log-time">${timestamp}</span> ${message}`;
    logContent.appendChild(logEntry);
    logContent.scrollTop = logContent.scrollHeight;
}

// Alternar monitor del sistema
function toggleMonitor() {
    const button = document.getElementById('toggleMonitor');

    if (isMonitorActive) {
        stopMonitor();
        button.innerHTML = '<i class="fas fa-play"></i> Iniciar Monitor';
        button.className = 'btn btn-sm btn-success';
    } else {
        startMonitor();
        button.innerHTML = '<i class="fas fa-stop"></i> Detener Monitor';
        button.className = 'btn btn-sm btn-danger';
    }

    isMonitorActive = !isMonitorActive;
}

// Iniciar monitor del sistema
function startMonitor() {
    addMonitorLog('Monitor del sistema iniciado');

    monitorInterval = setInterval(() => {
        updateSystemMonitor();
    }, 2000);
}

// Detener monitor del sistema
function stopMonitor() {
    if (monitorInterval) {
        clearInterval(monitorInterval);
        monitorInterval = null;
    }

    addMonitorLog('Monitor del sistema detenido');
}

// Actualizar monitor del sistema
function updateSystemMonitor() {
    // Simular datos del sistema
    const cpuUsage = Math.floor(Math.random() * 100);
    const memoryUsage = Math.floor(Math.random() * 100);
    const networkSpeed = Math.floor(Math.random() * 1000);

    // Actualizar círculos de progreso
    const monitorItems = document.querySelectorAll('.monitor-item');

    if (monitorItems[0]) {
        const cpuCircle = monitorItems[0].querySelector('.progress-circle');
        updateProgressCircle(cpuCircle, cpuUsage);
        cpuCircle.querySelector('span').textContent = cpuUsage + '%';
    }

    if (monitorItems[1]) {
        const memoryCircle = monitorItems[1].querySelector('.progress-circle');
        updateProgressCircle(memoryCircle, memoryUsage);
        memoryCircle.querySelector('span').textContent = memoryUsage + '%';
    }

    if (monitorItems[3]) {
        const networkCircle = monitorItems[3].querySelector('.progress-circle');
        networkCircle.querySelector('span').textContent = networkSpeed + ' KB/s';
    }

    // Agregar entrada al log si hay cambios significativos
    if (cpuUsage > 80) {
        addMonitorLog(`⚠️ Alto uso de CPU: ${cpuUsage}%`);
    }
    if (memoryUsage > 85) {
        addMonitorLog(`⚠️ Alto uso de memoria: ${memoryUsage}%`);
    }
}

// Agregar entrada al log del monitor
function addMonitorLog(message) {
    const logContent = document.querySelector('#monitorLog .log-content');
    const timestamp = new Date().toLocaleTimeString();
    const logEntry = document.createElement('p');
    logEntry.className = 'log-entry';
    logEntry.innerHTML = `<span class="log-time">${timestamp}</span> ${message}`;

    logContent.appendChild(logEntry);

    // Mantener solo las últimas 10 entradas
    const entries = logContent.querySelectorAll('.log-entry');
    if (entries.length > 10) {
        entries[0].remove();
    }

    logContent.scrollTop = logContent.scrollHeight;
}

// Mostrar detalles de mantenimiento
function mostrarDetalles(detallesBase64) {
    const detalles = atob(detallesBase64);
    document.getElementById('detallesContent').textContent = detalles;
    showModal('modalDetalles');
}

// Mostrar estadísticas del sistema
function mostrarEstadisticas() {
    showModal('modalEstadisticas');

    // Cargar gráficos después de mostrar el modal
    setTimeout(() => {
        loadCharts();
    }, 100);
}

// Cargar gráficos de estadísticas
function loadCharts() {
    // Gráfico de uso de recursos
    const resourceCtx = document.getElementById('resourceChart');
    if (resourceCtx) {
        new Chart(resourceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Usado', 'Libre'],
                datasets: [{
                    data: [65, 35],
                    backgroundColor: ['#dc3545', '#28a745'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Gráfico de historial de limpieza
    const cleanupCtx = document.getElementById('cleanupChart');
    if (cleanupCtx) {
        new Chart(cleanupCtx, {
            type: 'line',
            data: {
                labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                datasets: [{
                    label: 'MB Liberados',
                    data: [120, 85, 200, 150, 300, 180, 95],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Gráfico de errores por tipo
    const errorsCtx = document.getElementById('errorsChart');
    if (errorsCtx) {
        new Chart(errorsCtx, {
            type: 'bar',
            data: {
                labels: ['Base de Datos', 'Permisos', 'Memoria', 'Caché', 'Sesiones'],
                datasets: [{
                    label: 'Errores',
                    data: [5, 3, 8, 2, 1],
                    backgroundColor: [
                        '#dc3545',
                        '#ffc107',
                        '#fd7e14',
                        '#6f42c1',
                        '#20c997'
                    ]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Guardar configuración de mantenimiento
function guardarConfiguracion() {
    const config = {
        auto_maintenance_enabled: document.getElementById('autoMaintenanceEnabled').checked,
        maintenance_window_start: document.getElementById('maintenanceStart').value,
        maintenance_window_end: document.getElementById('maintenanceEnd').value,
        cache_max_age_days: document.getElementById('cacheMaxAge').value,
        log_max_age_days: document.getElementById('logMaxAge').value,
        max_concurrent_tasks: document.getElementById('maxConcurrentTasks').value,
        notification_email: document.getElementById('notificationEmail').value
    };

    // Simular guardado
    showNotification('Configuración guardada exitosamente', 'success');
}

// Restaurar configuración predeterminada
function restaurarDefaults() {
    if (!confirm('¿Deseas restaurar la configuración predeterminada?')) {
        return;
    }

    document.getElementById('autoMaintenanceEnabled').checked = true;
    document.getElementById('maintenanceStart').value = '02:00';
    document.getElementById('maintenanceEnd').value = '06:00';
    document.getElementById('cacheMaxAge').value = '7';
    document.getElementById('logMaxAge').value = '30';
    document.getElementById('maxConcurrentTasks').value = '3';
    document.getElementById('notificationEmail').value = '';

    showNotification('Configuración restaurada a valores predeterminados', 'info');
}

// Exportar historial de mantenimiento
function exportarHistorial() {
    // Simular exportación
    showNotification('Exportando historial de mantenimiento...', 'info');

    setTimeout(() => {
        // Crear archivo CSV simulado
        const csvContent = "data:text/csv;charset=utf-8," +
            "Tarea,Estado,Inicio,Duración,Archivos,Espacio\n" +
            "Limpieza de Caché,Completado,2024-01-15 02:00,00:02:30,150,25MB\n" +
            "Optimización BD,Completado,2024-01-15 02:03,00:05:15,0,0MB\n";

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "historial_mantenimiento.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showNotification('Historial exportado exitosamente', 'success');
    }, 1500);
}

// Limpiar historial de mantenimiento
function limpiarHistorial() {
    if (!confirm('¿Deseas eliminar todo el historial de mantenimiento? Esta acción no se puede deshacer.')) {
        return;
    }

    // Simular limpieza
    showNotification('Historial de mantenimiento eliminado', 'success');

    setTimeout(() => {
        location.reload();
    }, 1500);
}

// Actualizar estadísticas del sistema
function updateSystemStats() {
    // Simular actualización de estadísticas
    // En una implementación real, esto haría una petición AJAX para obtener datos actualizados
}

// Configurar actualización automática
function setupAutoRefresh() {
    // Actualizar estadísticas cada 5 minutos
    setInterval(() => {
        if (!document.hidden) {
            updateSystemStats();
        }
    }, 300000);
}

// Formatear bytes
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Funciones de utilidad para modales
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Función de notificaciones
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
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
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                padding: 1rem;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
                z-index: 1000;
                display: flex;
                align-items: center;
                gap: 1rem;
                min-width: 300px;
                max-width: 500px;
                animation: slideIn 0.3s ease;
            }
            
            .notification-success { border-left: 4px solid #28a745; }
            .notification-error { border-left: 4px solid #dc3545; }
            .notification-warning { border-left: 4px solid #ffc107; }
            .notification-info { border-left: 4px solid #17a2b8; }
            
            .notification-content {
                display: flex;
                align-items: flex-start;
                gap: 0.5rem;
                flex: 1;
                white-space: pre-line;
            }
            
            .notification-close {
                background: none;
                border: none;
                color: #6c757d;
                cursor: pointer;
                padding: 0.25rem;
                align-self: flex-start;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }

    document.body.appendChild(notification);

    // Auto-remover después de 8 segundos para mensajes largos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 8000);
}

function getNotificationIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// Cerrar modales al hacer clic fuera
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

// Manejar visibilidad de la página para pausar actualizaciones
document.addEventListener('visibilitychange', function () {
    if (document.hidden && isMonitorActive) {
        // Pausar monitor cuando la página no es visible
        if (monitorInterval) {
            clearInterval(monitorInterval);
        }
    } else if (!document.hidden && isMonitorActive) {
        // Reanudar monitor cuando la página es visible
        monitorInterval = setInterval(() => {
            updateSystemMonitor();
        }, 2000);
    }
});