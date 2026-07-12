/**
 * JS for Module: Informes
 * Atera-style Reports Dashboard
 */

let currentChart = null;

document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
});

function loadDashboardStats() {
    // Fetch aggregated stats from the API
    fetch('/api/reportes/crud.php?action=dashboard_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const stats = data.data;

                // Update Tickets
                updateStat('statTickets', stats.tickets.total);
                updateStat('statTicketsCritical', stats.tickets.critical + ' Críticos', true);

                // Update Inventario
                updateStat('statInventario', stats.inventario.total);
                updateStat('statInventarioAssigned', stats.inventario.assigned + ' Asignados', true);

                // Update Empleados
                updateStat('statEmpleados', stats.empleados.total);
                updateStat('statEmpleadosActive', stats.empleados.active + ' Activos', true);

                // Update Licencias
                updateStat('statLicencias', stats.licencias.total);
                updateStat('statLicenciasAssigned', stats.licencias.assigned + ' Asignadas', true);
            }
        })
        .catch(error => console.error('Error loading stats:', error));
}

function updateStat(elementId, value, isText = false) {
    const el = document.getElementById(elementId);
    if (el) {
        if (isText) {
             // Preserve icon if present
             const icon = el.querySelector('i');
             if (icon) {
                 el.innerHTML = '';
                 el.appendChild(icon);
                 el.append(' ' + value);
             } else {
                 el.textContent = value;
             }
        } else {
            el.textContent = value;
        }
    }
}

function loadReport(reportType) {
    // Update UI
    document.getElementById('reportViewer').style.display = 'block';
    document.getElementById('reportTitle').textContent = formatReportTitle(reportType);

    // Scroll to viewer
    document.getElementById('reportViewer').scrollIntoView({ behavior: 'smooth' });

    // Show Loading
    const tbody = document.querySelector('#reportTable tbody');
    tbody.innerHTML = '<tr><td colspan="100%" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>';

    // Fetch real data from API
    fetch(`/api/reportes/crud.php?action=report&type=${reportType}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderReport(reportType, data.data);
            } else {
                renderReport(reportType, generateMockData(reportType));
            }
        })
        .catch(error => {
            console.error('Error loading report:', error);
            renderReport(reportType, generateMockData(reportType));
        });
}

function closeReport() {
    document.getElementById('reportViewer').style.display = 'none';
}

function refreshReports() {
    loadDashboardStats();
    if (document.getElementById('reportViewer').style.display === 'block') {
        const title = document.getElementById('reportTitle').textContent;
        // Reload current report logic here if needed
    }
}

function generateCustomReport() {
    alert('Constructor de informes personalizados (Próximamente)');
}

function downloadReport(module, format) {
    // Redirect to the universal exporter API
    const url = `/api/exportador-universal/export.php?module=${module}&format=${format}`;
    window.location.href = url;
}

function exportReport(format) {
    // Export the currently viewed report
    alert(`Exportando informe visual a ${format}... (Funcionalidad en desarrollo)`);
}

function formatReportTitle(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function renderReport(type, data) {
    renderChart(type, data.chart);
    renderTable(data.table);
}

function renderChart(type, chartData) {
    const ctx = document.getElementById('reportChart').getContext('2d');

    if (currentChart) {
        currentChart.destroy();
    }

    currentChart = new Chart(ctx, {
        type: chartData.type || 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Cantidad',
                data: chartData.data,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Gráfico de Datos'
                }
            }
        }
    });
}

function renderTable(tableData) {
    const thead = document.querySelector('#reportTable thead');
    const tbody = document.querySelector('#reportTable tbody');

    if (!tableData || tableData.length === 0) {
        tbody.innerHTML = '<tr><td class="text-center">No hay datos</td></tr>';
        return;
    }

    // Headers
    const headers = Object.keys(tableData[0]);
    thead.innerHTML = '<tr>' + headers.map(h => `<th>${h.toUpperCase()}</th>`).join('') + '</tr>';

    // Rows
    tbody.innerHTML = tableData.map(row => {
        return '<tr>' + headers.map(h => `<td>${row[h]}</td>`).join('') + '</tr>';
    }).join('');
}

// Mock Data Generator (Keep this for demo purposes until real endpoints exist)
function generateMockData(type) {
    // This would be replaced by actual API data
    const labels = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo'];
    const data = labels.map(() => Math.floor(Math.random() * 50) + 10);

    const tableData = labels.map((label, index) => ({
        Periodo: label,
        Cantidad: data[index],
        Variacion: (Math.random() * 10 - 5).toFixed(2) + '%'
    }));

    return {
        chart: {
            labels: labels,
            data: data,
            type: 'bar'
        },
        table: tableData
    };
}
