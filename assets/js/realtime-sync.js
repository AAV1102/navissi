/**
 * WorkManager ERP - Sistema de Sincronización en Tiempo Real
 * Actualiza automáticamente todos los dashboards, stats y tablas
 */

class WorkManagerSync {
    constructor(options = {}) {
        this.apiBase = options.apiBase || '/api/sync';
        this.refreshInterval = options.refreshInterval || 30000; // 30 segundos
        this.autoRefresh = options.autoRefresh !== false;
        this.callbacks = {};
        this.lastData = {};
        this.isRunning = false;
        this.intervalId = null;
        
        // Iniciar automáticamente
        if (this.autoRefresh) {
            this.start();
        }
    }
    
    // Iniciar sincronización automática
    start() {
        if (this.isRunning) return;
        this.isRunning = true;
        
        // Primera carga inmediata
        this.refresh();
        
        // Configurar intervalo
        this.intervalId = setInterval(() => this.refresh(), this.refreshInterval);
        console.log('[WM-Sync] Sincronización iniciada - Intervalo:', this.refreshInterval + 'ms');
    }
    
    // Detener sincronización
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        this.isRunning = false;
        console.log('[WM-Sync] Sincronización detenida');
    }
    
    // Refrescar datos
    async refresh(module = 'all') {
        try {
            const response = await fetch(`${this.apiBase}/stats.php?module=${module}&_t=${Date.now()}`);
            const data = await response.json();
            
            if (data.success) {
                this.lastData = data;
                this.updateUI(data);
                this.triggerCallbacks(data);
                console.log('[WM-Sync] Datos actualizados:', data.timestamp);
            } else {
                console.error('[WM-Sync] Error:', data.error);
            }
            
            return data;
        } catch (error) {
            console.error('[WM-Sync] Error de conexión:', error);
            return null;
        }
    }
    
    // Actualizar elementos de la UI automáticamente
    updateUI(data) {
        // Actualizar stats de inventario
        if (data.inventario) {
            this.updateElement('[data-stat="inv-total"]', data.inventario.total);
            this.updateElement('[data-stat="inv-asignados"]', data.inventario.asignados);
            this.updateElement('[data-stat="inv-disponibles"]', data.inventario.disponibles);
            this.updateElement('[data-stat="inv-mantenimiento"]', data.inventario.mantenimiento);
            this.updateElement('[data-stat="inv-baja"]', data.inventario.baja);
            
            // También actualizar por clases comunes
            this.updateElement('.stat-total-activos', data.inventario.total);
            this.updateElement('.stat-asignados', data.inventario.asignados);
            this.updateElement('.stat-disponibles', data.inventario.disponibles);
        }
        
        // Actualizar stats de empleados
        if (data.empleados) {
            this.updateElement('[data-stat="emp-total"]', data.empleados.total);
            this.updateElement('[data-stat="emp-activos"]', data.empleados.activos);
            this.updateElement('.stat-total-empleados', data.empleados.total);
        }
        
        // Actualizar stats de sedes
        if (data.sedes) {
            this.updateElement('[data-stat="sedes-total"]', data.sedes.total);
            this.updateElement('.stat-total-sedes', data.sedes.total);
        }
        
        // Actualizar stats de licencias
        if (data.licencias) {
            this.updateElement('[data-stat="lic-total"]', data.licencias.total);
            this.updateElement('[data-stat="lic-asignadas"]', data.licencias.asignadas);
            this.updateElement('[data-stat="lic-disponibles"]', data.licencias.disponibles);
        }
        
        // Actualizar stats de tickets
        if (data.tickets) {
            this.updateElement('[data-stat="tickets-total"]', data.tickets.total);
            this.updateElement('[data-stat="tickets-abiertos"]', data.tickets.abiertos);
            this.updateElement('[data-stat="tickets-pendientes"]', data.tickets.pendientes);
        }
        
        // Actualizar timestamp
        this.updateElement('.sync-timestamp', data.timestamp);
    }
    
    // Helper para actualizar elementos
    updateElement(selector, value) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => {
            const formatted = typeof value === 'number' ? value.toLocaleString() : value;
            if (el.textContent !== String(formatted)) {
                el.textContent = formatted;
                // Efecto visual de actualización
                el.classList.add('updated');
                setTimeout(() => el.classList.remove('updated'), 1000);
            }
        });
    }
    
    // Registrar callback para cuando se actualicen datos
    on(event, callback) {
        if (!this.callbacks[event]) {
            this.callbacks[event] = [];
        }
        this.callbacks[event].push(callback);
    }
    
    // Disparar callbacks
    triggerCallbacks(data) {
        if (this.callbacks['update']) {
            this.callbacks['update'].forEach(cb => cb(data));
        }
        
        // Callbacks específicos por módulo
        Object.keys(data).forEach(module => {
            if (this.callbacks[module]) {
                this.callbacks[module].forEach(cb => cb(data[module]));
            }
        });
    }
    
    // Obtener últimos datos sin hacer request
    getData(module = null) {
        if (module) {
            return this.lastData[module] || null;
        }
        return this.lastData;
    }
}

// Instancia global
window.wmSync = new WorkManagerSync({
    refreshInterval: 30000, // 30 segundos
    autoRefresh: true
});

// CSS para efecto de actualización
const style = document.createElement('style');
style.textContent = `
    .updated {
        animation: pulse-update 0.5s ease;
    }
    @keyframes pulse-update {
        0% { background-color: rgba(0, 200, 83, 0.3); }
        100% { background-color: transparent; }
    }
    .sync-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #00c853;
        margin-right: 5px;
        animation: pulse 2s infinite;
    }
    .sync-indicator.offline {
        background: #ff5252;
        animation: none;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
`;
document.head.appendChild(style);

console.log('[WM-Sync] Módulo de sincronización cargado');
