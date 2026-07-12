/**
 * JavaScript para el módulo Jurídico
 */

// Variables globales
let currentCasoId = null;
let currentContratoId = null;

// Inicializar tabs
function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const targetTab = this.getAttribute('data-tab');

            // Remover clase active de todos los tabs
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));

            // Activar tab seleccionado
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');

            // Cargar datos según el tab
            switch (targetTab) {
                case 'casos':
                    loadCasos();
                    break;
                case 'contratos':
                    loadContratos();
                    break;
                case 'audiencias':
                    loadAudiencias();
                    break;
            }
        });
    });
}

// Cargar casos
function loadCasos(filters = {}) {
    const searchTerm = document.getElementById('searchCasos')?.value || '';
    const estado = document.getElementById('filterEstadoCasos')?.value || '';

    const params = new URLSearchParams({
        action: 'get_casos',
        search: searchTerm,
        estado: estado,
        ...filters
    });

    fetch(`../../api/juridico.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCasosTable(data.casos);
            } else {
                showAlert('Error al cargar casos: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Actualizar tabla de casos
function updateCasosTable(casos) {
    const tbody = document.getElementById('casosTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    casos.forEach(caso => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(caso.numero_caso)}</td>
            <td>${escapeHtml(caso.tipo)}</td>
            <td>${escapeHtml(caso.cliente || 'N/A')}</td>
            <td>
                <span class="status-badge status-${caso.estado.toLowerCase().replace(' ', '-')}">
                    ${escapeHtml(caso.estado)}
                </span>
            </td>
            <td>${formatDate(caso.fecha_inicio)}</td>
            <td>$${formatNumber(caso.monto_reclamado || 0)}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editCaso(${caso.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-info" onclick="viewCaso(${caso.id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteCaso(${caso.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Cargar contratos
function loadContratos(filters = {}) {
    const searchTerm = document.getElementById('searchContratos')?.value || '';
    const estado = document.getElementById('filterEstadoContratos')?.value || '';

    const params = new URLSearchParams({
        action: 'get_contratos',
        search: searchTerm,
        estado: estado,
        ...filters
    });

    fetch(`../../api/juridico.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateContratosTable(data.contratos);
            } else {
                showAlert('Error al cargar contratos: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Actualizar tabla de contratos
function updateContratosTable(contratos) {
    const tbody = document.getElementById('contratosTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    contratos.forEach(contrato => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(contrato.numero_contrato)}</td>
            <td>${escapeHtml(contrato.tipo)}</td>
            <td>${escapeHtml(contrato.partes || 'N/A')}</td>
            <td>
                <span class="status-badge status-${contrato.estado.toLowerCase()}">
                    ${escapeHtml(contrato.estado)}
                </span>
            </td>
            <td>${formatDate(contrato.fecha_inicio)}</td>
            <td>$${formatNumber(contrato.monto || 0)}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editContrato(${contrato.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-info" onclick="viewContrato(${contrato.id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteContrato(${contrato.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Cargar audiencias
function loadAudiencias(filters = {}) {
    const params = new URLSearchParams({
        action: 'get_audiencias',
        fecha_desde: new Date().toISOString().split('T')[0],
        ...filters
    });

    fetch(`../../api/juridico.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateAudienciasTable(data.audiencias);
            } else {
                showAlert('Error al cargar audiencias: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Actualizar tabla de audiencias
function updateAudienciasTable(audiencias) {
    const tbody = document.querySelector('#audiencias tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    audiencias.forEach(audiencia => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${formatDateTime(audiencia.fecha_audiencia)}</td>
            <td>${escapeHtml(audiencia.numero_caso || 'N/A')}</td>
            <td>${escapeHtml(audiencia.tipo)}</td>
            <td>${escapeHtml(audiencia.lugar || 'N/A')}</td>
            <td>
                <span class="status-badge status-${audiencia.estado.toLowerCase()}">
                    ${escapeHtml(audiencia.estado)}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editAudiencia(${audiencia.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-info" onclick="viewAudiencia(${audiencia.id})">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Funciones CRUD
function editCaso(id) {
    currentCasoId = id;
    // Cargar datos del caso y mostrar modal de edición
    fetch(`../../api/juridico.php?action=get_caso&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateCasoForm(data.caso);
                showModal('modalEditarCaso');
            } else {
                showAlert('Error al cargar caso: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

function viewCaso(id) {
    // Mostrar modal con detalles del caso
    fetch(`../../api/juridico.php?action=get_caso&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showCasoDetails(data.caso);
            } else {
                showAlert('Error al cargar caso: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

function deleteCaso(id) {
    if (confirm('¿Está seguro de eliminar este caso? Esta acción no se puede deshacer.')) {
        fetch('../../api/juridico.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete_caso',
                id: id
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Caso eliminado exitosamente', 'success');
                    loadCasos();
                } else {
                    showAlert('Error al eliminar caso: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error de conexión', 'error');
            });
    }
}

// Funciones similares para contratos y audiencias
function editContrato(id) {
    currentContratoId = id;
    // Implementar lógica similar a editCaso
}

function viewContrato(id) {
    // Implementar lógica similar a viewCaso
}

function deleteContrato(id) {
    // Implementar lógica similar a deleteCaso
}

function editAudiencia(id) {
    // Implementar lógica de edición de audiencia
}

function viewAudiencia(id) {
    // Implementar lógica de visualización de audiencia
}

// Generar reportes
function generateCasosReport() {
    const filters = {
        search: document.getElementById('searchCasos')?.value || '',
        estado: document.getElementById('filterEstadoCasos')?.value || ''
    };

    const params = new URLSearchParams({
        action: 'generate_casos_report',
        format: 'pdf',
        ...filters
    });

    window.open(`../../api/juridico.php?${params}`, '_blank');
}

function generateContratosReport() {
    const filters = {
        search: document.getElementById('searchContratos')?.value || '',
        estado: document.getElementById('filterEstadoContratos')?.value || ''
    };

    const params = new URLSearchParams({
        action: 'generate_contratos_report',
        format: 'excel',
        ...filters
    });

    window.open(`../../api/juridico.php?${params}`, '_blank');
}

// Funciones de utilidad
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

function showAlert(message, type = 'info') {
    // Crear y mostrar alerta
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    const container = document.querySelector('.main-content');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);

        // Auto-hide después de 5 segundos
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES');
}

function formatDateTime(dateTimeString) {
    if (!dateTimeString) return 'N/A';
    const date = new Date(dateTimeString);
    return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
}

function formatNumber(number) {
    return new Intl.NumberFormat('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function () {
    // Configurar búsqueda en tiempo real
    const searchCasos = document.getElementById('searchCasos');
    if (searchCasos) {
        searchCasos.addEventListener('input', debounce(() => loadCasos(), 300));
    }

    const searchContratos = document.getElementById('searchContratos');
    if (searchContratos) {
        searchContratos.addEventListener('input', debounce(() => loadContratos(), 300));
    }

    // Configurar filtros
    const filterEstadoCasos = document.getElementById('filterEstadoCasos');
    if (filterEstadoCasos) {
        filterEstadoCasos.addEventListener('change', () => loadCasos());
    }

    const filterEstadoContratos = document.getElementById('filterEstadoContratos');
    if (filterEstadoContratos) {
        filterEstadoContratos.addEventListener('change', () => loadContratos());
    }
});

// Función debounce para optimizar búsquedas
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