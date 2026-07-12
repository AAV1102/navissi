/**
 * JavaScript para el módulo de Sedes
 * Funcionalidades: CRUD de sedes, visualización de mapas, reportes
 */

// Variables globales
let currentSedeId = null;
let sedesData = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function () {
    initSedes();
    loadSedesData();
});

function initSedes() {
    // Inicializar tooltips
    initTooltips();

    // Configurar eventos de formularios
    setupFormEvents();

    // Configurar filtros de búsqueda
    setupSearchFilters();
}

function loadSedesData() {
    fetch('../../api/sedes/crud.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                sedesData = data.data; // En crud.php los datos vienen en 'data'
                updateSedesDisplay();
            }
        })
        .catch(error => {
            console.error('Error cargando sedes:', error);
            showNotification('Error cargando datos de sedes', 'error');
        });
}

function updateSedesDisplay() {
    // Actualizar contadores en tiempo real si es necesario
    updateSedeCounters();
}

function updateSedeCounters() {
    sedesData.forEach(sede => {
        const equiposElement = document.getElementById(`equipos-sede-${sede.id}`);
        const empleadosElement = document.getElementById(`empleados-sede-${sede.id}`);

        if (equiposElement) {
            equiposElement.textContent = (parseInt(sede.equipos_individuales) + parseInt(sede.equipos_agrupados)) || 0;
        }

        if (empleadosElement) {
            empleadosElement.textContent = sede.empleados || 0;
        }
    });
}

function setupFormEvents() {
    // Formulario de nueva sede
    const formNuevaSede = document.getElementById('formNuevaSede');
    if (formNuevaSede) {
        formNuevaSede.addEventListener('submit', handleCreateSede);
    }

    // Formulario de edición de sede
    const formEditSede = document.getElementById('formEditSede');
    if (formEditSede) {
        formEditSede.addEventListener('submit', handleUpdateSede);
    }
}

function setupSearchFilters() {
    // Filtro de búsqueda general
    const searchInput = document.getElementById('searchSedes');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterSedes, 300));
    }

    // Filtros por departamento
    const departmentFilter = document.getElementById('filterDepartamento');
    if (departmentFilter) {
        departmentFilter.addEventListener('change', filterSedes);
    }

    // Filtros por estado
    const statusFilter = document.getElementById('filterEstado');
    if (statusFilter) {
        statusFilter.addEventListener('change', filterSedes);
    }
}

function filterSedes() {
    const searchTerm = document.getElementById('searchSedes')?.value.toLowerCase() || '';
    const departmentFilter = document.getElementById('filterDepartamento')?.value || '';
    const statusFilter = document.getElementById('filterEstado')?.value || '';

    const sedeCards = document.querySelectorAll('.sede-card');

    sedeCards.forEach(card => {
        const sedeId = card.getAttribute('data-sede-id');
        const sede = sedesData.find(s => s.id == sedeId);

        if (!sede) return;

        let visible = true;

        // Filtro de búsqueda
        if (searchTerm) {
            const searchableText = `${sede.nombre} ${sede.ciudad} ${sede.departamento} ${sede.codigo || ''}`.toLowerCase();
            visible = visible && searchableText.includes(searchTerm);
        }

        // Filtro por departamento
        if (departmentFilter) {
            visible = visible && sede.departamento === departmentFilter;
        }

        // Filtro por estado
        if (statusFilter) {
            visible = visible && (sede.estado || 'activa').toLowerCase() === statusFilter.toLowerCase();
        }

        card.style.display = visible ? 'block' : 'none';
    });
}

function handleCreateSede(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const sedeData = Object.fromEntries(formData.entries());

    // Validaciones
    if (!sedeData.nombre || !sedeData.ciudad || !sedeData.departamento) {
        showNotification('Por favor completa los campos obligatorios', 'warning');
        return;
    }

    // Mostrar loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
    submitBtn.disabled = true;

    fetch('../../api/sedes/crud.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'create',
            ...sedeData
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Sede creada exitosamente', 'success');
                hideModal('modalNuevaSede');
                event.target.reset();
                loadSedesData(); // Recargar datos

                // Redirigir a la nueva sede si se creó
                if (data.id) { // En crud.php el campo es 'id'
                    setTimeout(() => {
                        window.location.href = `sede-detail.php?id=${data.id}`;
                    }, 1500);
                }
            } else {
                showNotification(data.message || 'Error al crear la sede', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión al crear la sede', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
}

function handleUpdateSede(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const sedeData = Object.fromEntries(formData.entries());

    if (!currentSedeId) {
        showNotification('Error: ID de sede no válido', 'error');
        return;
    }

    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
    submitBtn.disabled = true;

    fetch('../../api/sedes/crud.php', {
        method: 'POST', // Usar POST para compatibilidad con crud.php
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update',
            id: currentSedeId,
            ...sedeData
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Sede actualizada exitosamente', 'success');
                hideModal('modalEditSede');
                loadSedesData();
            } else {
                showNotification(data.message || 'Error al actualizar la sede', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión al actualizar la sede', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
}

function viewSedeDetail(sedeId) {
    window.location.href = `sede-detail.php?id=${sedeId}`;
}

function editSede(sedeId) {
    currentSedeId = sedeId;

    // Buscar datos de la sede
    const sede = sedesData.find(s => s.id == sedeId);
    if (!sede) {
        showNotification('Sede no encontrada', 'error');
        return;
    }

    // Llenar formulario de edición
    fillEditForm(sede);

    // Mostrar modal de edición
    showModal('modalEditSede');
}

function fillEditForm(sede) {
    const form = document.getElementById('formEditSede');
    if (!form) return;

    // Llenar campos del formulario
    const fields = ['nombre', 'codigo', 'ciudad', 'departamento', 'direccion', 'telefono', 'email', 'observaciones'];

    fields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (input && sede[field] !== undefined) {
            input.value = sede[field] || '';
        }
    });

    // Estado
    const estadoSelect = form.querySelector('[name="estado"]');
    if (estadoSelect && sede.estado) {
        estadoSelect.value = sede.estado;
    }
}

function deleteSede(sedeId) {
    if (!confirm('¿Estás seguro de que deseas eliminar esta sede? Esta acción no se puede deshacer.')) {
        return;
    }

    fetch('../../api/sedes/crud.php', {
        method: 'POST', // Usar POST para compatibilidad con crud.php
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'delete',
            id: sedeId
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Sede eliminada exitosamente', 'success');
                loadSedesData();
            } else {
                showNotification(data.message || 'Error al eliminar la sede', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión al eliminar la sede', 'error');
        });
}

function generateSedesReport() {
    showNotification('Generando reporte general de sedes...', 'info');

    fetch('../../api/sedes/crud.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'generate_general_report',
            format: 'json'
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar datos del reporte en consola por ahora
                console.log('Reporte generado:', data.data);

                // Crear un blob con los datos JSON para descarga
                const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = data.filename || 'reporte_sedes_general.json';
                link.click();
                URL.revokeObjectURL(url);

                showNotification('Reporte generado exitosamente', 'success');
            } else {
                showNotification(data.message || 'Error al generar el reporte', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión al generar el reporte', 'error');
        });
}

function generateSedeReport(sedeId) {
    showNotification('Generando reporte de sede...', 'info');

    fetch('../../api/sedes/crud.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'generate_sede_report',
            sede_id: sedeId,
            format: 'json'
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar datos del reporte en consola por ahora
                console.log('Reporte de sede generado:', data.data);

                // Crear un blob con los datos JSON para descarga
                const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = data.filename || `reporte_sede_${sedeId}.json`;
                link.click();
                URL.revokeObjectURL(url);

                showNotification('Reporte de sede generado exitosamente', 'success');
            } else {
                showNotification(data.message || 'Error al generar el reporte', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión al generar el reporte', 'error');
        });
}

function exportSedesData(format = 'excel') {
    showNotification(`Exportando datos en formato ${format.toUpperCase()}...`, 'info');

    fetch('../../api/sedes/crud.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'export_data',
            format: format
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.download_url) {
                    const link = document.createElement('a');
                    link.href = data.download_url;
                    link.download = data.filename || `sedes_export.${format}`;
                    link.click();

                    showNotification(`Datos exportados exitosamente en ${format.toUpperCase()}`, 'success');
                } else {
                    showNotification(`Exportación en ${format.toUpperCase()} (funcionalidad pendiente)`, 'info');
                }
            } else {
                showNotification(data.message || 'Error al exportar los datos', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión al exportar los datos', 'error');
        });
}

function initSedeMap() {
    // Inicializar mapa interactivo de sedes
    // Esta función se puede expandir para integrar con Google Maps o similar
    const mapContainer = document.getElementById('sedesMap');
    if (!mapContainer) return;

    // Por ahora, mostrar marcadores simples
    sedesData.forEach(sede => {
        const marker = document.createElement('div');
        marker.className = 'sede-marker';
        marker.innerHTML = `
            <div class="marker-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="marker-info">
                <strong>${sede.nombre}</strong>
                <small>${sede.ciudad}, ${sede.departamento}</small>
            </div>
        `;
        marker.addEventListener('click', () => viewSedeDetail(sede.id));
        mapContainer.appendChild(marker);
    });
}

function searchSedesByLocation(location) {
    const filteredSedes = sedesData.filter(sede =>
        sede.ciudad.toLowerCase().includes(location.toLowerCase()) ||
        sede.departamento.toLowerCase().includes(location.toLowerCase())
    );

    displayFilteredSedes(filteredSedes);
}

function displayFilteredSedes(sedes) {
    const sedeCards = document.querySelectorAll('.sede-card');

    sedeCards.forEach(card => {
        const sedeId = card.getAttribute('data-sede-id');
        const shouldShow = sedes.some(sede => sede.id == sedeId);
        card.style.display = shouldShow ? 'block' : 'none';
    });
}

function getSedeStatistics() {
    return {
        total: sedesData.length,
        activas: sedesData.filter(s => (s.estado || 'activa').toLowerCase() === 'activa').length,
        inactivas: sedesData.filter(s => (s.estado || 'activa').toLowerCase() === 'inactiva').length,
        porDepartamento: sedesData.reduce((acc, sede) => {
            const dept = sede.departamento || 'Sin departamento';
            acc[dept] = (acc[dept] || 0) + 1;
            return acc;
        }, {}),
        totalEquipos: sedesData.reduce((total, sede) =>
            total + parseInt(sede.equipos_individuales || 0) + parseInt(sede.equipos_agrupados || 0), 0),
        totalEmpleados: sedesData.reduce((total, sede) => total + parseInt(sede.empleados || 0), 0)
    };
}

// Funciones de utilidad
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

function initTooltips() {
    // Inicializar tooltips para elementos con data-tooltip
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(event) {
    const text = event.target.getAttribute('data-tooltip');
    if (!text) return;

    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.id = 'active-tooltip';

    document.body.appendChild(tooltip);

    const rect = event.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
}

function hideTooltip() {
    const tooltip = document.getElementById('active-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Exportar funciones para uso global
window.SedesModule = {
    viewSedeDetail,
    editSede,
    deleteSede,
    generateSedesReport,
    generateSedeReport,
    exportSedesData,
    getSedeStatistics,
    searchSedesByLocation
};
