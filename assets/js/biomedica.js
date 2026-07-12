/**
 * WorkManager ERP - Biomédica JavaScript
 * Funcionalidades para el módulo de equipos biomédicos
 */

// Variables globales
let equiposBiomedicos = [];
let currentEquipo = null;

// Inicialización
document.addEventListener('DOMContentLoaded', function () {
    initBiomedica();
});

function initBiomedica() {
    loadEquiposBiomedicos();
    setupEventListeners();
}

function setupEventListeners() {
    // Formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });

    // Búsqueda
    const searchInput = document.getElementById('searchEquipos');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterEquipos, 300));
    }

    // Filtros
    const filters = document.querySelectorAll('.filter-select');
    filters.forEach(filter => {
        filter.addEventListener('change', filterEquipos);
    });
}

// Cargar equipos biomédicos
async function loadEquiposBiomedicos() {
    try {
        showLoading('Cargando equipos biomédicos...');

        const response = await fetch('/api/biomedica.php?action=get_equipos');
        const data = await response.json();

        if (data.success) {
            equiposBiomedicos = data.data;
            renderEquiposTable();
        } else {
            showError('Error al cargar equipos: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Renderizar tabla de equipos
function renderEquiposTable() {
    const tableBody = document.querySelector('#equiposTable tbody');
    if (!tableBody) return;

    if (equiposBiomedicos.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center">
                    <div class="empty-state">
                        <i class="fas fa-heartbeat fa-3x text-muted mb-3"></i>
                        <h5>No hay equipos biomédicos registrados</h5>
                        <p class="text-muted">Comienza agregando tu primer equipo biomédico</p>
                        <button class="btn btn-primary" onclick="showModal('modalNuevoEquipo')">
                            <i class="fas fa-plus"></i> Agregar Equipo
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tableBody.innerHTML = equiposBiomedicos.map(equipo => `
        <tr>
            <td>
                <strong>${escapeHtml(equipo.codigo_activo)}</strong>
            </td>
            <td>
                <div>
                    <strong>${escapeHtml(equipo.nombre_equipo)}</strong><br>
                    <small class="text-muted">${escapeHtml(equipo.serial || 'Sin serial')}</small>
                </div>
            </td>
            <td>
                ${escapeHtml(equipo.marca)} ${escapeHtml(equipo.modelo)}
            </td>
            <td>
                ${escapeHtml(equipo.sede_nombre || 'Sin sede')}
            </td>
            <td>
                <span class="status-badge status-${equipo.estado.toLowerCase()}">
                    ${escapeHtml(equipo.estado)}
                </span>
            </td>
            <td>
                ${equipo.fecha_proximo_mantenimiento ?
            formatDate(equipo.fecha_proximo_mantenimiento) :
            '<span class="text-muted">No programado</span>'
        }
            </td>
            <td>
                <div class="btn-group">
                    <button class="btn btn-sm btn-primary" onclick="editEquipo(${equipo.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-success" onclick="registrarMantenimiento(${equipo.id})" title="Mantenimiento">
                        <i class="fas fa-tools"></i>
                    </button>
                    <button class="btn btn-sm btn-info" onclick="verHistorial(${equipo.id})" title="Historial">
                        <i class="fas fa-history"></i>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="generarQR(${equipo.id})" title="Código QR">
                        <i class="fas fa-qrcode"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Filtrar equipos
function filterEquipos() {
    const searchTerm = document.getElementById('searchEquipos')?.value.toLowerCase() || '';
    const sedeFilter = document.getElementById('filterSede')?.value || '';
    const estadoFilter = document.getElementById('filterEstado')?.value || '';

    const filtered = equiposBiomedicos.filter(equipo => {
        const matchesSearch = !searchTerm ||
            equipo.nombre_equipo.toLowerCase().includes(searchTerm) ||
            equipo.codigo_activo.toLowerCase().includes(searchTerm) ||
            equipo.marca.toLowerCase().includes(searchTerm) ||
            equipo.modelo.toLowerCase().includes(searchTerm);

        const matchesSede = !sedeFilter || equipo.sede_id == sedeFilter;
        const matchesEstado = !estadoFilter || equipo.estado.toLowerCase() === estadoFilter.toLowerCase();

        return matchesSearch && matchesSede && matchesEstado;
    });

    // Renderizar resultados filtrados
    const tableBody = document.querySelector('#equiposTable tbody');
    if (tableBody) {
        const originalEquipos = equiposBiomedicos;
        equiposBiomedicos = filtered;
        renderEquiposTable();
        equiposBiomedicos = originalEquipos;
    }
}

// Crear nuevo equipo
async function createEquipo(formData) {
    try {
        showLoading('Creando equipo...');

        const response = await fetch('/api/biomedica.php?action=crear_equipo', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Equipo creado exitosamente');
            hideModal('modalNuevoEquipo');
            loadEquiposBiomedicos();
        } else {
            showError('Error al crear equipo: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Editar equipo
function editEquipo(equipoId) {
    const equipo = equiposBiomedicos.find(e => e.id === equipoId);
    if (!equipo) return;

    currentEquipo = equipo;

    // Llenar formulario de edición
    fillEditForm(equipo);
    showModal('modalEditarEquipo');
}

// Llenar formulario de edición
function fillEditForm(equipo) {
    const form = document.getElementById('formEditarEquipo');
    if (!form) return;

    const fields = [
        'codigo_activo', 'nombre_equipo', 'marca', 'modelo', 'serial',
        'sede_id', 'ubicacion', 'estado', 'fecha_adquisicion',
        'responsable', 'observaciones'
    ];

    fields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (input && equipo[field] !== undefined) {
            input.value = equipo[field] || '';
        }
    });
}

// Actualizar equipo
async function updateEquipo(formData) {
    if (!currentEquipo) return;

    try {
        showLoading('Actualizando equipo...');

        formData.append('id', currentEquipo.id);

        const response = await fetch('/api/biomedica.php?action=actualizar_equipo', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Equipo actualizado exitosamente');
            hideModal('modalEditarEquipo');
            loadEquiposBiomedicos();
            currentEquipo = null;
        } else {
            showError('Error al actualizar equipo: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Registrar mantenimiento
function registrarMantenimiento(equipoId) {
    const equipo = equiposBiomedicos.find(e => e.id === equipoId);
    if (!equipo) return;

    currentEquipo = equipo;

    // Llenar información del equipo en el modal
    document.getElementById('mantenimientoEquipoInfo').innerHTML = `
        <strong>${escapeHtml(equipo.nombre_equipo)}</strong><br>
        <small class="text-muted">Código: ${escapeHtml(equipo.codigo_activo)}</small>
    `;

    showModal('modalMantenimiento');
}

// Crear mantenimiento
async function createMantenimiento(formData) {
    if (!currentEquipo) return;

    try {
        showLoading('Registrando mantenimiento...');

        formData.append('equipo_id', currentEquipo.id);

        const response = await fetch('/api/biomedica.php?action=registrar_mantenimiento', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Mantenimiento registrado exitosamente');
            hideModal('modalMantenimiento');
            loadEquiposBiomedicos();
            currentEquipo = null;
        } else {
            showError('Error al registrar mantenimiento: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Ver historial de mantenimientos
async function verHistorial(equipoId) {
    try {
        showLoading('Cargando historial...');

        const response = await fetch(`/api/biomedica.php?action=get_historial&equipo_id=${equipoId}`);
        const data = await response.json();

        if (data.success) {
            renderHistorialModal(data.data);
            showModal('modalHistorial');
        } else {
            showError('Error al cargar historial: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Renderizar modal de historial
function renderHistorialModal(historial) {
    const container = document.getElementById('historialContainer');
    if (!container) return;

    if (historial.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h5>Sin historial de mantenimientos</h5>
                <p class="text-muted">Este equipo no tiene mantenimientos registrados</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="timeline">
            ${historial.map(item => `
                <div class="timeline-item">
                    <div class="timeline-marker">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="timeline-content">
                        <h6>${escapeHtml(item.tipo_mantenimiento)}</h6>
                        <p class="mb-1">${escapeHtml(item.descripcion)}</p>
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> ${formatDate(item.fecha_mantenimiento)}
                            <i class="fas fa-user ml-2"></i> ${escapeHtml(item.tecnico_responsable)}
                            ${item.costo ? `<i class="fas fa-dollar-sign ml-2"></i> $${formatNumber(item.costo)}` : ''}
                        </small>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

// Generar código QR
function generarQR(equipoId) {
    const equipo = equiposBiomedicos.find(e => e.id === equipoId);
    if (!equipo) return;

    const qrData = {
        id: equipo.id,
        codigo: equipo.codigo_activo,
        nombre: equipo.nombre_equipo,
        tipo: 'equipo_biomedico'
    };

    const qrString = JSON.stringify(qrData);
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrString)}`;

    document.getElementById('qrCodeImage').src = qrUrl;
    document.getElementById('qrEquipoInfo').innerHTML = `
        <strong>${escapeHtml(equipo.nombre_equipo)}</strong><br>
        <small class="text-muted">Código: ${escapeHtml(equipo.codigo_activo)}</small>
    `;

    showModal('modalQRCode');
}

// Generar reporte
async function generateBiomedicaReport() {
    try {
        showLoading('Generando reporte...');

        const response = await fetch('/api/reports.php?action=biomedica_report', {
            method: 'POST'
        });

        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reporte_biomedica_${formatDate(new Date(), 'YYYY-MM-DD')}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            showSuccess('Reporte generado exitosamente');
        } else {
            showError('Error al generar reporte');
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Manejar envío de formularios
function handleFormSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    switch (form.id) {
        case 'formNuevoEquipo':
            createEquipo(formData);
            break;
        case 'formEditarEquipo':
            updateEquipo(formData);
            break;
        case 'formMantenimiento':
            createMantenimiento(formData);
            break;
    }
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

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateString, format = 'DD/MM/YYYY') {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();

    switch (format) {
        case 'YYYY-MM-DD':
            return `${year}-${month}-${day}`;
        default:
            return `${day}/${month}/${year}`;
    }
}

function formatNumber(number) {
    return new Intl.NumberFormat('es-CO').format(number);
}

function showLoading(message = 'Cargando...') {
    // Implementar loading spinner
    console.log('Loading:', message);
}

function hideLoading() {
    // Ocultar loading spinner
    console.log('Loading hidden');
}

function showSuccess(message) {
    // Implementar notificación de éxito
    console.log('Success:', message);
}

function showError(message) {
    // Implementar notificación de error
    console.error('Error:', message);
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
    }
}