/**
 * JS for Module: Reportes
 * Atera-style integration
 */

document.addEventListener('DOMContentLoaded', function() {
    refreshReports();
});

function refreshReports() {
    loadReportStats();
    loadSavedReports();
}

async function loadReportStats() {
    try {
        const r = await fetch('api/reportes/crud.php?action=dashboard_stats');
        const res = await r.json();

        if (res.success) {
            const data = res.data;

            // Inventario
            const invEl = document.getElementById('reportStatInventario');
            if (invEl) invEl.textContent = data.inventario.total || 0;

            // Empleados
            const empEl = document.getElementById('reportStatEmpleados');
            if (empEl) empEl.textContent = data.empleados.total || 0;

            // Licencias
            const licEl = document.getElementById('reportStatLicencias');
            if (licEl) licEl.textContent = data.licencias.total || 0;

            // Tickets
            const ticketEl = document.getElementById('reportStatTickets');
            if (ticketEl) ticketEl.textContent = data.tickets.total || 0;
        }
    } catch (e) {
        console.error('Error loading report stats:', e);
    }
}

async function loadSavedReports() {
    const container = document.getElementById('reportesList');
    if (!container) return;

    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary spinner-border-sm"></div></div>';

    try {
        const r = await fetch('api/reportes/crud.php?action=list');
        const res = await r.json();

        if (!res.success || !res.data || res.data.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3">Sin reportes guardados</div>';
            return;
        }

        const items = res.data.slice(0, 6).map(item => `
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                    <div class="fw-bold small text-dark">${item.titulo || item.nombre || 'Reporte'}</div>
                    <div class="text-muted small" style="font-size: 0.75rem">${item.created_at || item.fecha || ''}</div>
                </div>
                <button class="btn btn-sm btn-link text-primary"><i class="fas fa-download"></i></button>
            </div>
        `);
        container.innerHTML = items.join('');
    } catch (e) {
        // If API endpoint doesn't exist or fails, show message
        container.innerHTML = '<div class="text-muted text-center py-3">No se pudieron cargar los reportes</div>';
    }
}

function downloadReport(moduleName, format) {
    const params = new URLSearchParams({
        module: moduleName,
        format: format
    });
    window.open(`api/exportador-universal/export.php?${params.toString()}`, '_blank');
}
