/**
 * JS for Module: Inventario
 * Aligned with Atera-style layout and API
 */

let inventarioManager;

document.addEventListener('DOMContentLoaded', function() {
    inventarioManager = new InventarioManager();
});

// Global functions for HTML onclick handlers
function reloadInventory() { inventarioManager.loadInventory(); }
function filterByCategory(cat) { inventarioManager.setFilter('categoria', cat); }
function filterByStatus(status) { inventarioManager.setFilter('estado', status); }
function setInventoryView(view) { inventarioManager.setView(view); }
function exportInventory(format) { inventarioManager.export(format); }
function openImportModal() {
    const modal = new bootstrap.Modal(document.getElementById('importModal'));
    modal.show();
}
function handleImport(event) { inventarioManager.handleImport(event); }
function openNewEquipmentModal() { inventarioManager.openModal(); }
function clearAllInventory() { inventarioManager.clearAll(); }
function downloadTemplate() { inventarioManager.downloadTemplate(); }
function toggleExtendedFilter(type) { inventarioManager.toggleExtendedFilter(type); }

class InventarioManager {
    constructor() {
        this.state = {
            page: 0,
            pageSize: 100,
            filters: {
                search: '',
                tipo: '',
                categoria: '',
                estado: '',
                sede: '',
                extended: {}
            },
            view: 'inventario' // inventario, activos, equipos
        };

        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadInventory();
        this.loadStats();
    }

    loadStats() {
        fetch('api/inventario/crud.php?action=stats')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.updateStatsUI(data.data);
                }
            })
            .catch(console.error);
    }

    updateStatsUI(stats) {
        // Update Total
        const totalEl = document.getElementById('statTotal');
        if (totalEl) totalEl.textContent = stats.total || 0;

        // Process status counts
        let assigned = 0;
        let available = 0;
        let maintenance = 0;

        if (stats.by_status && Array.isArray(stats.by_status)) {
            stats.by_status.forEach(item => {
                const status = item.estado ? item.estado.toLowerCase() : '';
                const count = parseInt(item.count) || 0;

                if (status.includes('asignado') || status.includes('uso')) {
                    assigned += count;
                } else if (status.includes('disponible')) {
                    available += count;
                } else if (status.includes('mantenimiento') || status.includes('reparacion')) {
                    maintenance += count;
                }
            });
        }

        // Update UI
        const assignedEl = document.getElementById('statAssigned');
        if (assignedEl) assignedEl.textContent = assigned;

        const availableEl = document.getElementById('statAvailable');
        if (availableEl) availableEl.textContent = available;

        const maintenanceEl = document.getElementById('statMaintenance');
        if (maintenanceEl) maintenanceEl.textContent = maintenance;
    }

    setupEventListeners() {
        // Search debounce
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce((e) => {
                this.state.filters.search = e.target.value;
                this.state.page = 0;
                this.loadInventory();
            }, 500));
        }

        // Dropdown filters
        ['filterTipo', 'filterCategoria', 'filterEstado', 'filterSede'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', (e) => {
                    const key = id.replace('filter', '').toLowerCase();
                    this.state.filters[key] = e.target.value;
                    this.state.page = 0;
                    this.loadInventory();
                });
            }
        });

        // Pagination
        document.getElementById('inventoryPageSize')?.addEventListener('change', (e) => {
            this.state.pageSize = parseInt(e.target.value);
            this.state.page = 0;
            this.loadInventory();
        });

        document.getElementById('inventoryPrev')?.addEventListener('click', () => {
            if (this.state.page > 0) {
                this.state.page--;
                this.loadInventory();
            }
        });

        document.getElementById('inventoryNext')?.addEventListener('click', () => {
            this.state.page++;
            this.loadInventory();
        });

        // Form Submit
        document.getElementById('equipmentForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveEquipment();
        });
    }

    debounce(func, wait) {
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

    setFilter(key, value) {
        this.state.filters[key] = value;
        this.state.page = 0;

        // Update UI
        if (key === 'categoria') {
            document.querySelectorAll('[data-filter-type="category"]').forEach(el => {
                el.classList.toggle('active', el.dataset.filterValue === value);
            });
             // Also update dropdown
             const dropdown = document.getElementById('filterCategoria');
             if (dropdown) dropdown.value = value;
        } else if (key === 'estado') {
            document.querySelectorAll('[data-filter-type="status"]').forEach(el => {
                el.classList.toggle('active', el.dataset.filterValue === value);
            });
             const dropdown = document.getElementById('filterEstado');
             if (dropdown) dropdown.value = value;
        }

        this.loadInventory();
    }

    setView(view) {
        this.state.view = view;
        document.querySelectorAll('[data-inventory-view]').forEach(el => {
            el.classList.toggle('active', el.dataset.inventoryView === view);
        });
        this.loadInventory();
    }

    toggleExtendedFilter(type) {
        // Toggle UI logic here if needed
        console.log('Toggle extended filter', type);
    }

    loadInventory() {
        const tbody = document.getElementById('inventoryTableBody');
        const range = document.getElementById('inventoryRange');
        const totalEl = document.getElementById('inventoryTotal');
        const pageInfo = document.getElementById('inventoryPageInfo');

        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>';

        // Build Params
        const params = new URLSearchParams();
        params.append('action', 'list');
        params.append('limit', this.state.pageSize);
        params.append('offset', this.state.page * this.state.pageSize);

        // Add filters
        Object.entries(this.state.filters).forEach(([key, value]) => {
            if (value && typeof value !== 'object') params.append(key, value);
        });

        fetch(`api/inventario/crud.php?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.renderTable(data.data, tbody);

                    // Update Pagination UI
                    const start = this.state.page * this.state.pageSize + 1;
                    const end = Math.min((this.state.page + 1) * this.state.pageSize, data.total);

                    if (range) range.textContent = `${start}-${end}`;
                    if (totalEl) totalEl.textContent = data.total;
                    if (pageInfo) pageInfo.textContent = `${this.state.page + 1} / ${Math.ceil(data.total / this.state.pageSize)}`;

                    // Stats update could go here if API returned it
                } else {
                    tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${data.error || 'Error cargando datos'}</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Error de conexión</td></tr>';
            });
    }

    renderTable(data, tbody) {
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No se encontraron activos</td></tr>';
            return;
        }

        tbody.innerHTML = data.map(item => {
            let statusBadge = 'bg-secondary';
            if (item.estado === 'disponible') statusBadge = 'bg-success';
            if (item.estado === 'asignado') statusBadge = 'bg-primary';
            if (item.estado === 'mantenimiento') statusBadge = 'bg-warning text-dark';
            if (item.estado === 'baja') statusBadge = 'bg-danger';

            return `
                <tr>
                    <td><span class="fw-bold text-primary">${item.placa || item.id}</span></td>
                    <td>
                        <div class="fw-bold">${item.categoria || item.tecnologia || item.tipo_equipo || 'Desconocido'}</div>
                        <div class="small text-muted">${item.marca || ''} ${item.modelo || ''}</div>
                    </td>
                    <td><div class="small font-monospace">${item.serial || '-'}</div></td>
                    <td>
                        ${(item.usuario_nombre || item.asignado_a) ?
                            `<div><i class="fas fa-user me-1 text-muted"></i>${item.usuario_nombre || ''} ${item.usuario_apellido || ''} <small class="d-block text-muted">${item.asignado_a || ''}</small></div>` :
                            '<span class="text-muted small">Sin asignar</span>'}
                    </td>
                    <td>${item.sede_nombre || '-'}</td>
                    <td><span class="badge ${statusBadge} rounded-pill text-uppercase" style="font-size:0.7rem">${item.estado}</span></td>
                    <td>${item.licencia ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
                    <td>${item.garantia ? '<i class="fas fa-shield-alt text-info"></i>' : '-'}</td>
                    <td>${item.fecha_auditoria ? '<small>'+item.fecha_auditoria+'</small>' : '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-link text-primary" onclick="inventarioManager.editItem(${item.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-link text-danger" onclick="inventarioManager.deleteItem(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    openModal(id = null) {
        const modalEl = document.getElementById('equipmentModal');
        if (!modalEl) return;

        const modal = new bootstrap.Modal(modalEl);
        document.getElementById('equipmentForm').reset();
        document.getElementById('equipment_id').value = '';
        document.getElementById('equipmentModalTitle').textContent = 'Nuevo Equipo';

        if (id) {
            // Load data for edit
            // this.loadItem(id);
        }

        modal.show();
    }

    editItem(id) {
        alert('Editar item ' + id);
        // Implement fetch item details and populate modal
    }

    deleteItem(id) {
        if (confirm('¿Está seguro de eliminar este activo?')) {
            // Implement delete API call
            alert('Eliminar item ' + id);
        }
    }

    saveEquipment() {
        // Collect form data and POST to API
        alert('Guardar equipo');
    }

    handleImport(event) {
        event.preventDefault();
        alert('Importar CSV');
    }

    export(format) {
        alert('Exportar a ' + format);
    }

    clearAll() {
        if (confirm('¡ADVERTENCIA! Esto borrará todo el inventario. ¿Está seguro?')) {
            alert('Limpiar todo');
        }
    }

    downloadTemplate() {
        alert('Descargar plantilla');
    }
}
