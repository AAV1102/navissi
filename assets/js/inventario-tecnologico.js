/**
 * JavaScript para el Sistema de Inventario Tecnológico
 * WorkManager ERP - Integral IPS
 * Desarrollado por: Anderson Ayala Vera
 */

// Variables globales
let currentTab = 'dashboard';
let dataTable = null;

// Inicialización cuando se carga la página
document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initModals();
    initDataTables();
    initEventListeners();
    loadDashboardData();
});

/**
 * Inicializar sistema de tabs
 */
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetTab = this.getAttribute('data-tab');

            // Remover clase active de todos los tabs
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Activar tab seleccionado
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');

            currentTab = targetTab;

            // Cargar datos específicos del tab
            loadTabData(targetTab);
        });
    });
}

/**
 * Inicializar modales
 */
function initModals() {
    // Configurar validación de formularios
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Manejar envío de formularios
    setupFormHandlers();
}

/**
 * Inicializar DataTables
 */
function initDataTables() {
    const tableConfig = {
        responsive: true,
        pageLength: 25,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
        },
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                text: '<i class="fas fa-copy"></i> Copiar'
            },
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV'
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Imprimir'
            }
        ]
    };

    // Inicializar tablas si existen
    const tables = ['#equiposIndividualesTable', '#equiposAgrupadosTable', '#historialCambiosTable', '#equiposBajaTable', '#mantenimientosTable'];

    tables.forEach(tableId => {
        const table = document.querySelector(tableId);
        if (table) {
            $(tableId).DataTable(tableConfig);
        }
    });
}

/**
 * Configurar event listeners
 */
function initEventListeners() {
    // Búsqueda en tiempo real
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', function () {
            const tableId = this.getAttribute('data-table');
            if (tableId && $.fn.DataTable.isDataTable(tableId)) {
                $(tableId).DataTable().search(this.value).draw();
            }
        });
    });

    // Filtros
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function () {
            applyFilters();
        });
    });

    // Botones de acción
    document.addEventListener('click', function (e) {
        if (e.target.matches('[data-action]')) {
            const action = e.target.getAttribute('data-action');
            const equipoId = e.target.getAttribute('data-equipo-id');
            const tipoTabla = e.target.getAttribute('data-tipo-tabla');

            handleAction(action, equipoId, tipoTabla);
        }
    });
}

/**
 * Configurar manejadores de formularios
 */
function setupFormHandlers() {
    // Formulario agregar equipo individual
    const formAgregarEquipo = document.getElementById('formAgregarEquipo');
    if (formAgregarEquipo) {
        formAgregarEquipo.addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(this, 'agregar_equipo_individual');
        });
    }

    // Formulario agregar equipo agrupado
    const formAgregarEquipoAgrupado = document.getElementById('formAgregarEquipoAgrupado');
    if (formAgregarEquipoAgrupado) {
        formAgregarEquipoAgrupado.addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(this, 'agregar_equipo_agrupado');
        });
    }

    // Formulario programar mantenimiento
    const formProgramarMantenimiento = document.getElementById('formProgramarMantenimiento');
    if (formProgramarMantenimiento) {
        formProgramarMantenimiento.addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(this, 'programar_mantenimiento');
        });
    }

    // Formulario cambiar estado
    const formCambiarEstado = document.getElementById('formCambiarEstado');
    if (formCambiarEstado) {
        formCambiarEstado.addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(this, 'cambiar_estado');
        });
    }

    // Formulario dar de baja
    const formDarBaja = document.getElementById('formDarBaja');
    if (formDarBaja) {
        formDarBaja.addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(this, 'dar_baja');
        });
    }

    // Formulario asignar equipo
    const formAsignarEquipo = document.getElementById('formAsignarEquipo');
    if (formAsignarEquipo) {
        formAsignarEquipo.addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(this, 'asignar_equipo');
        });
    }
}

/**
 * Cargar datos del dashboard
 */
function loadDashboardData() {
    // Actualizar estadísticas en tiempo real
    fetch('api/inventario/stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboardStats(data.stats);
            }
        })
        .catch(error => {
            console.error('Error cargando estadísticas:', error);
        });
}

/**
 * Cargar datos específicos de cada tab
 */
function loadTabData(tabName) {
    switch (tabName) {
        case 'individuales':
            loadIndividuales();
            break;
        case 'agrupados':
            loadAgrupados();
            break;
        case 'por-sede':
            loadSedeStats();
            break;
        case 'reportes':
            loadReportesData();
            break;
    }
}

/**
 * Cargar equipos individuales
 */
function loadIndividuales(filters = {}) {
    showLoading('individualesTableContainer');

    const params = new URLSearchParams(filters).toString();
    fetch(`api/inventario/individuales.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderIndividualesTable(data.equipos);
            } else {
                showError('Error cargando equipos individuales: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error de conexión al cargar equipos individuales');
        })
        .finally(() => {
            hideLoading('individualesTableContainer');
        });
}

/**
 * Cargar equipos agrupados
 */
function loadAgrupados(filters = {}) {
    showLoading('agrupadosTableContainer');

    const params = new URLSearchParams(filters).toString();
    fetch(`api/inventario/agrupados.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderAgrupadosTable(data.equipos);
            } else {
                showError('Error cargando equipos agrupados: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error de conexión al cargar equipos agrupados');
        })
        .finally(() => {
            hideLoading('agrupadosTableContainer');
        });
}

/**
 * Cargar estadísticas por sede
 */
function loadSedeStats() {
    const sedeCards = document.querySelectorAll('[id^="equipos-sede-"]');

    sedeCards.forEach(card => {
        const sedeId = card.id.replace('equipos-sede-', '');

        fetch(`api/inventario/sede-stats.php?sede_id=${sedeId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    card.textContent = data.total_equipos;

                    const empleadosElement = document.getElementById(`empleados-sede-${sedeId}`);
                    if (empleadosElement) {
                        empleadosElement.textContent = data.total_empleados;
                    }
                }
            })
            .catch(error => {
                console.error('Error cargando stats de sede:', error);
                card.textContent = 'Error';
            });
    });
}

/**
 * Renderizar tabla de equipos individuales
 */
function renderIndividualesTable(equipos) {
    const container = document.getElementById('individualesTableContainer');

    let html = `
        <div class="table-responsive">
            <table class="table table-hover" id="individualesTable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th>Marca/Modelo</th>
                        <th>Serial</th>
                        <th>Sede</th>
                        <th>Empleado</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
    `;

    equipos.forEach(equipo => {
        html += `
            <tr>
                <td>
                    <div class="barcode-display-small">
                        ${equipo.codigo_individual || 'N/A'}
                    </div>
                </td>
                <td>${equipo.tipo_equipo || 'N/A'}</td>
                <td>
                    ${equipo.marca || ''}
                    ${equipo.modelo ? `<br><small class="text-muted">${equipo.modelo}</small>` : ''}
                </td>
                <td>${equipo.serial || 'N/A'}</td>
                <td>${equipo.sede_nombre || 'Sin sede'}</td>
                <td>${equipo.empleado_nombre || 'Sin asignar'}</td>
                <td>
                    <span class="badge bg-${getStatusColor(equipo.estado)}">
                        ${equipo.estado || 'N/A'}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary btn-sm" onclick="editarEquipo(${equipo.id}, 'individual')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="cambiarEstado(${equipo.id}, 'individual')">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="imprimirEtiqueta(${equipo.id}, 'individual')">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;

    // Reinicializar DataTable
    $('#individualesTable').DataTable({
        responsive: true,
        pageLength: 25,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
        }
    });
}

/**
 * Renderizar tabla de equipos agrupados
 */
function renderAgrupadosTable(equipos) {
    const container = document.getElementById('agrupadosTableContainer');

    let html = `
        <div class="table-responsive">
            <table class="table table-hover" id="agrupadosTable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th>Marca/Modelo</th>
                        <th>Cantidad</th>
                        <th>Sede</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
    `;

    equipos.forEach(equipo => {
        html += `
            <tr>
                <td>
                    <div class="barcode-display-small">
                        ${equipo.codigo_unificado || 'N/A'}
                    </div>
                </td>
                <td>${equipo.tipo_equipo || 'N/A'}</td>
                <td>
                    ${equipo.marca || ''}
                    ${equipo.modelo ? `<br><small class="text-muted">${equipo.modelo}</small>` : ''}
                </td>
                <td>
                    <span class="badge bg-info">${equipo.cantidad_equipos || 0} unidades</span>
                </td>
                <td>${equipo.sede_nombre || 'Sin sede'}</td>
                <td>
                    <span class="badge bg-${getStatusColor(equipo.estado)}">
                        ${equipo.estado || 'N/A'}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary btn-sm" onclick="editarEquipo(${equipo.id}, 'agrupado')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="dividirLote(${equipo.id})">
                            <i class="fas fa-cut"></i>
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="imprimirEtiqueta(${equipo.id}, 'agrupado')">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;

    // Reinicializar DataTable
    $('#agrupadosTable').DataTable({
        responsive: true,
        pageLength: 25,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
        }
    });
}

/**
 * Manejar acciones de botones
 */
function handleAction(action, equipoId, tipoTabla) {
    switch (action) {
        case 'edit':
            editarEquipo(equipoId, tipoTabla);
            break;
        case 'change-status':
            cambiarEstado(equipoId, tipoTabla);
            break;
        case 'assign':
            asignarEquipo(equipoId);
            break;
        case 'maintenance':
            programarMantenimiento(equipoId, tipoTabla);
            break;
        case 'print-label':
            imprimirEtiqueta(equipoId, tipoTabla);
            break;
        case 'delete':
            darBaja(equipoId, tipoTabla);
            break;
    }
}

/**
 * Editar equipo
 */
function editarEquipo(id, tipo) {
    const endpoint = tipo === 'individual' ? 'api/inventario/individuales.php' : 'api/inventario/agrupados.php';

    fetch(`${endpoint}?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.equipos && data.equipos.length > 0) {
                const equipo = data.equipos[0];
                const modalId = tipo === 'individual' ? 'modalNuevoIndividual' : 'modalNuevoAgrupado';
                const modalElement = document.getElementById(modalId);
                const form = modalElement.querySelector('form');

                // Cambiar título del modal y texto del botón
                modalElement.querySelector('.modal-title').textContent = `Editar Equipo ${tipo === 'individual' ? 'Individual' : 'Agrupado'}`;
                form.querySelector('button[type="submit"]').textContent = 'Actualizar Equipo';

                // Llenar campos
                Object.keys(equipo).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input) {
                        input.value = equipo[key];
                    }
                });

                // Añadir ID y acción de edición
                let idInput = form.querySelector('input[name="id"]');
                if (!idInput) {
                    idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    form.appendChild(idInput);
                }
                idInput.value = id;

                let actionInput = form.querySelector('input[name="action"]');
                if (!actionInput) {
                    actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    form.appendChild(actionInput);
                }
                actionInput.value = tipo === 'individual' ? 'editar_equipo_individual' : 'editar_equipo_agrupado';

                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                showError('No se pudo obtener la información del equipo');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error de conexión al obtener datos del equipo');
        });
}

/**
 * Cambiar estado de equipo
 */
function cambiarEstado(id, tipo) {
    document.getElementById('cambiarEstadoEquipoId').value = id;
    document.getElementById('cambiarEstadoTipoTabla').value = tipo;

    const modal = new bootstrap.Modal(document.getElementById('cambiarEstadoModal'));
    modal.show();
}

/**
 * Asignar equipo a empleado
 */
function asignarEquipo(id) {
    document.getElementById('asignarEquipoId').value = id;

    // Cargar lista de empleados
    loadEmpleadosSelect();

    const modal = new bootstrap.Modal(document.getElementById('asignarEquipoModal'));
    modal.show();
}

/**
 * Programar mantenimiento
 */
function programarMantenimiento(id, tipo) {
    // Llenar formulario con datos del equipo
    const modal = new bootstrap.Modal(document.getElementById('programarMantenimientoModal'));
    modal.show();
}

/**
 * Imprimir etiqueta
 */
function imprimirEtiqueta(id, tipo) {
    const url = `modules/sistemas/print-label.php?id=${id}&tipo=${tipo}`;
    window.open(url, '_blank', 'width=800,height=600');
}

/**
 * Dar de baja equipo
 */
function darBaja(id, tipo) {
    document.getElementById('darBajaEquipoId').value = id;
    document.getElementById('darBajaTipoTabla').value = tipo;

    const modal = new bootstrap.Modal(document.getElementById('darBajaModal'));
    modal.show();
}

/**
 * Dividir lote de equipos agrupados
 */
function dividirLote(id) {
    if (confirm('¿Está seguro de que desea dividir este lote en equipos individuales?')) {
        fetch('api/inventario/dividir-lote.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ lote_id: id })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Lote dividido exitosamente', 'success');
                    loadAgrupados();
                    loadIndividuales();
                } else {
                    showError('Error al dividir lote: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error de conexión al dividir lote');
            });
    }
}

/**
 * Enviar formulario
 */
function submitForm(form, action) {
    const formData = new FormData(form);
    formData.append('action', action);

    fetch('modules/sistemas/inventario-tecnologico.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');

                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(form.closest('.modal'));
                if (modal) {
                    modal.hide();
                }

                // Recargar datos
                loadTabData(currentTab);
                loadDashboardData();

                // Limpiar formulario
                form.reset();
                form.classList.remove('was-validated');
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error de conexión al procesar la solicitud');
        });
}

/**
 * Cargar empleados en select
 */
function loadEmpleadosSelect() {
    fetch('api/empleados/crud.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.querySelector('#asignarEquipoModal select[name="empleado_id"]');
                if (select) {
                    select.innerHTML = '<option value="">Seleccionar empleado...</option>';
                    data.data.forEach(empleado => {
                        const option = document.createElement('option');
                        option.value = empleado.id;
                        option.textContent = `${empleado.nombre_completo} - ${empleado.cargo || 'Sin cargo'}`;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error cargando empleados:', error);
        });
}

/**
 * Aplicar filtros
 */
function applyFilters() {
    const sede_id = document.getElementById('filterSede')?.value;
    const estado = document.getElementById('filterEstado')?.value;
    const search = document.getElementById('filterSearch')?.value;

    const filters = {};
    if (sede_id) filters.sede_id = sede_id;
    if (estado) filters.estado = estado;
    if (search) filters.search = search;

    if (currentTab === 'individuales') {
        loadIndividuales(filters);
    } else if (currentTab === 'agrupados') {
        loadAgrupados(filters);
    }
}

/**
 * Generar reporte
 */
function generarReporte(tipo) {
    const url = `api/reportes/inventario.php?tipo=${tipo}`;
    window.open(url, '_blank');
}

/**
 * Exportar cambios
 */
function exportarCambios() {
    window.open('api/export/historial-cambios.php', '_blank');
}

/**
 * Exportar bajas
 */
function exportarBajas() {
    window.open('api/export/equipos-baja.php', '_blank');
}

/**
 * Copiar al portapapeles
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(window.location.origin + text).then(() => {
        showNotification('URL copiada al portapapeles', 'success');
    });
}

/**
 * Obtener color de estado
 */
function getStatusColor(estado) {
    const colors = {
        'disponible': 'success',
        'asignado': 'primary',
        'mantenimiento': 'warning',
        'baja': 'danger',
        'reparacion': 'info',
        'activo': 'success',
        'inactivo': 'secondary'
    };
    return colors[estado] || 'secondary';
}

/**
 * Actualizar estadísticas del dashboard
 */
function updateDashboardStats(stats) {
    // Actualizar contadores en tiempo real
    const elements = {
        'total_equipos': stats.total_equipos,
        'equipos_asignados': stats.individual_asignados + stats.agrupados_asignados,
        'equipos_mantenimiento': stats.equipos_mantenimiento,
        'valor_total': stats.valor_total_inventario
    };

    Object.keys(elements).forEach(key => {
        const element = document.querySelector(`[data-stat="${key}"]`);
        if (element) {
            element.textContent = typeof elements[key] === 'number' ?
                elements[key].toLocaleString() : elements[key];
        }
    });
}

/**
 * Mostrar loading
 */
function showLoading(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando datos...</p>
            </div>
        `;
    }
}

/**
 * Ocultar loading
 */
function hideLoading(containerId) {
    // El loading se oculta automáticamente al cargar el contenido
}

/**
 * Mostrar notificación
 */
function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notification);

    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

/**
 * Mostrar error
 */
function showError(message) {
    showNotification(message, 'danger');
}

/**
 * Cargar datos de reportes
 */
function loadReportesData() {
    // Implementar carga de datos para reportes
    console.log('Cargando datos de reportes...');
}
