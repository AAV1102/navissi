/**
 * Empleados Module Script
 * Handles CRUD, Stats, and Listing for Employee Management
 * Aligned with Atera-style UI
 */

const EmpleadosModule = (function() {
    // State
    let state = {
        page: 1,
        limit: 10,
        total: 0,
        filters: {
            q: '',
            sede_id: '',
            estado: ''
        },
        isLoading: false
    };

    // DOM Elements
    const elements = {
        tableBody: document.getElementById('tableBodyEmpleados'),
        pagination: document.getElementById('paginationEmpleados'),
        searchInput: document.getElementById('searchEmpleados'),
        filterSede: document.getElementById('filterSede'),
        filterEstado: document.getElementById('filterEstado'),
        recordCount: document.getElementById('recordCount'),
        modal: new bootstrap.Modal(document.getElementById('modalEmpleado')),
        form: document.getElementById('formEmpleado'),
        modalTitle: document.getElementById('modalEmpleadoTitle'),
        stats: {
            total: document.getElementById('statTotal'),
            active: document.getElementById('statActive'),
            withDevices: document.getElementById('statWithDevices'),
            inactive: document.getElementById('statInactive')
        }
    };

    // Initialize
    function init() {
        bindEvents();
        loadStats();
        loadTable();
    }

    // Event Bindings
    function bindEvents() {
        // Search & Filters
        if (elements.searchInput) {
            elements.searchInput.addEventListener('input', debounce(() => {
                state.filters.q = elements.searchInput.value.trim();
                state.page = 1;
                loadTable();
            }, 500));
        }

        if (elements.filterSede) {
            elements.filterSede.addEventListener('change', () => {
                state.filters.sede_id = elements.filterSede.value;
                state.page = 1;
                loadTable();
            });
        }

        if (elements.filterEstado) {
            elements.filterEstado.addEventListener('change', () => {
                state.filters.estado = elements.filterEstado.value;
                state.page = 1;
                loadTable();
            });
        }
    }

    // Load Stats
    function loadStats() {
        fetch('api/empleados/crud.php?action=stats')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateStatsUI(data.data);
                }
            })
            .catch(console.error);
    }

    function updateStatsUI(data) {
        if (elements.stats.total) elements.stats.total.textContent = data.total || 0;
        
        // Active count
        const activeCount = data.by_estado ? (data.by_estado['Activo'] || data.by_estado['activo'] || 0) : 0;
        if (elements.stats.active) elements.stats.active.textContent = activeCount;

        // Inactive count (Sum of Inactivo, Retirado, etc. or just total - active)
        const inactiveCount = (data.total || 0) - activeCount;
        if (elements.stats.inactive) elements.stats.inactive.textContent = inactiveCount;

        // With Devices
        const devicesCount = data.equipos ? (data.equipos.con_equipos || 0) : 0;
        if (elements.stats.withDevices) elements.stats.withDevices.textContent = devicesCount;
    }

    // Load Table Data
    function loadTable() {
        if (state.isLoading) return;
        state.isLoading = true;
        
        renderLoading();

        const params = new URLSearchParams({
            action: 'list',
            page: state.page,
            limit: state.limit,
            q: state.filters.q,
            sede_id: state.filters.sede_id,
            estado: state.filters.estado,
            offset: (state.page - 1) * state.limit
        });

        fetch(`api/empleados/crud.php?${params}`)
            .then(res => res.json())
            .then(data => {
                state.isLoading = false;
                if (data.success) {
                    state.total = data.total;
                    renderTable(data.data);
                    renderPagination();
                    updateRecordCount();
                } else {
                    renderError(data.error || 'Error al cargar datos');
                }
            })
            .catch(err => {
                state.isLoading = false;
                renderError('Error de conexión');
                console.error(err);
            });
    }

    function renderLoading() {
        if (elements.tableBody) {
            elements.tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <div class="text-muted mt-2">Cargando empleados...</div>
                    </td>
                </tr>
            `;
        }
    }

    function renderError(msg) {
        if (elements.tableBody) {
            elements.tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5 text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> ${msg}
                    </td>
                </tr>
            `;
        }
    }

    function renderTable(rows) {
        if (!elements.tableBody) return;

        if (!rows || rows.length === 0) {
            elements.tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="fas fa-search mb-2" style="font-size: 2rem; opacity: 0.5;"></i>
                        <p class="mb-0">No se encontraron empleados</p>
                    </td>
                </tr>
            `;
            return;
        }

        elements.tableBody.innerHTML = rows.map(row => {
            const initials = getInitials(row.nombre, row.apellido);
            const statusBadge = getStatusBadge(row.estado);
            const avatarColor = getAvatarColor(row.nombre);
            
            return `
                <tr class="anime-fade-in">
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3 text-white fw-bold shadow-sm" 
                                 style="width: 40px; height: 40px; background-color: ${avatarColor}; font-size: 14px;">
                                ${initials}
                            </div>
                            <div>
                                <div class="fw-bold text-dark">${row.nombre_completo || 'Sin Nombre'}</div>
                                <div class="small text-muted font-monospace">ID: ${row.cedula || 'N/A'}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold text-dark">${row.cargo || 'Sin Cargo'}</div>
                        <div class="small text-muted">${row.area || row.departamento || '-'}</div>
                    </td>
                    <td>
                        <div class="text-dark"><i class="fas fa-envelope text-muted me-2 small"></i>${row.email || row.correo || '-'}</div>
                        <div class="small text-muted"><i class="fas fa-phone text-muted me-2 small"></i>${row.telefono || '-'}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <i class="fas fa-building me-1 text-secondary"></i> ${row.sede_nombre || 'Sin Sede'}
                        </span>
                    </td>
                    <td>${statusBadge}</td>
                    <td class="text-end pe-4">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary" onclick="EmpleadosModule.openModal(${row.id})" title="Editar">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="EmpleadosModule.deleteItem(${row.id})" title="Eliminar">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination() {
        if (!elements.pagination) return;

        const totalPages = Math.ceil(state.total / state.limit);
        let html = '';

        // Prev
        html += `
            <li class="page-item ${state.page === 1 ? 'disabled' : ''}">
                <button class="page-link border-0" onclick="EmpleadosModule.changePage(${state.page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </li>
        `;

        // Pages (Simplified)
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= state.page - 1 && i <= state.page + 1)) {
                html += `
                    <li class="page-item ${state.page === i ? 'active' : ''}">
                        <button class="page-link border-0 rounded-circle mx-1 ${state.page === i ? 'bg-primary text-white shadow-sm' : ''}" 
                                onclick="EmpleadosModule.changePage(${i})">${i}</button>
                    </li>
                `;
            } else if (i === state.page - 2 || i === state.page + 2) {
                html += `<li class="page-item disabled"><span class="page-link border-0">...</span></li>`;
            }
        }

        // Next
        html += `
            <li class="page-item ${state.page === totalPages || totalPages === 0 ? 'disabled' : ''}">
                <button class="page-link border-0" onclick="EmpleadosModule.changePage(${state.page + 1})">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </li>
        `;

        elements.pagination.innerHTML = html;
    }

    function updateRecordCount() {
        if (elements.recordCount) {
            const start = (state.page - 1) * state.limit + 1;
            const end = Math.min(state.page * state.limit, state.total);
            elements.recordCount.textContent = state.total > 0 
                ? `${start}-${end} de ${state.total} empleados` 
                : '0 empleados';
        }
    }

    // Modal Actions
    function openModal(id = null) {
        if (!elements.form) return;
        
        elements.form.reset();
        document.getElementById('empleadoAction').value = id ? 'update' : 'create';
        document.getElementById('empleadoId').value = id || '';
        
        if (id) {
            elements.modalTitle.textContent = 'Editar Empleado';
            // Fetch details
            fetch(`api/empleados/crud.php?action=get&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        populateForm(data.data);
                        elements.modal.show();
                    } else {
                        alert('Error al cargar datos del empleado');
                    }
                });
        } else {
            elements.modalTitle.textContent = 'Nuevo Empleado';
            elements.modal.show();
        }
    }

    function populateForm(data) {
        const form = elements.form;
        // Standard fields
        ['nombres', 'apellidos', 'documento', 'correo', 'cargo', 'sede_id', 'estado'].forEach(key => {
            const input = form.elements[key];
            if (input) {
                // Map API keys to Form keys if needed
                let val = data[key];
                if (key === 'nombres') val = data.nombre || data.nombres;
                if (key === 'apellidos') val = data.apellido || data.apellidos;
                if (key === 'documento') val = data.cedula || data.documento_numero || data.documento;
                if (key === 'correo') val = data.email || data.correo;
                
                input.value = val || '';
            }
        });
    }

    function save() {
        if (!elements.form.checkValidity()) {
            elements.form.reportValidity();
            return;
        }

        const formData = new FormData(elements.form);
        const data = Object.fromEntries(formData.entries());

        // Map form fields to API expected fields
        const payload = {
            action: data.action,
            id: data.id,
            nombre: data.nombres,
            apellido: data.apellidos,
            cedula: data.documento,
            email: data.correo,
            cargo: data.cargo,
            sede_id: data.sede_id,
            estado: data.estado
        };

        const btn = document.querySelector('#modalEmpleado .btn-primary');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

        fetch('api/empleados/crud.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                elements.modal.hide();
                loadTable();
                loadStats();
                // Show toast or alert
            } else {
                alert(res.error || 'Error al guardar');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }

    function deleteItem(id) {
        if (!confirm('¿Está seguro de eliminar este empleado?')) return;

        fetch(`api/empleados/crud.php?action=delete&id=${id}`, { method: 'POST' })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    loadTable();
                    loadStats();
                } else {
                    alert(res.error || 'Error al eliminar');
                }
            })
            .catch(console.error);
    }

    // Helpers
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function getInitials(name, lastName) {
        return ((name?.[0] || '') + (lastName?.[0] || '')).toUpperCase();
    }

    function getAvatarColor(str) {
        const colors = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'];
        let hash = 0;
        if (!str) return colors[0];
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return colors[Math.abs(hash) % colors.length];
    }

    function getStatusBadge(status) {
        const s = (status || '').toLowerCase();
        if (s === 'activo') return '<span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 rounded-pill">Activo</span>';
        if (s === 'inactivo') return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2 rounded-pill">Inactivo</span>';
        if (s === 'retirado') return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-3 py-2 rounded-pill">Retirado</span>';
        return `<span class="badge bg-light text-dark border px-3 py-2 rounded-pill">${status}</span>`;
    }

    // Public API
    return {
        init,
        changePage: (p) => { state.page = p; loadTable(); },
        openModal,
        saveEmpleado: save,
        deleteItem
    };
})();

// Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', EmpleadosModule.init);

// Global Exposure for HTML onclicks
window.openEmpleadoModal = EmpleadosModule.openModal;
window.saveEmpleado = EmpleadosModule.saveEmpleado;
window.loadEmpleadosData = () => EmpleadosModule.init(); // Refresh
