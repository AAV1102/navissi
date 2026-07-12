/**
 * JS for Module: Tickets (Mesa de Ayuda)
 * Aligned with Atera-style layout and API
 */

let ticketsState = {
    page: 0,
    pageSize: 50,
    filters: {
        queue: 'all',
        status: 'all',
        search: ''
    }
};

document.addEventListener('DOMContentLoaded', function() {
    loadTicketStats();
    loadTickets();
    loadInventoryOptions();
    loadTechOptions();

    // Search listener
    const searchInput = document.getElementById('ticketsSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce((e) => {
            ticketsState.filters.search = e.target.value;
            ticketsState.page = 0;
            loadTickets();
        }, 500));
    }

    // Pagination listeners
    document.getElementById('btnPrevPage')?.addEventListener('click', prevTicketsPage);
    document.getElementById('btnNextPage')?.addEventListener('click', nextTicketsPage);
    document.getElementById('ticketsPageSize')?.addEventListener('change', changeTicketsPageSize);
});

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function reloadTickets() {
    loadTicketStats();
    loadTickets();
}

function loadTicketStats() {
    fetch('api/tickets/crud.php?action=stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const update = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = val || 0;
                };
                update('statTotal', data.data.total);
                update('statOpen', data.data.open);
                update('statClosed', data.data.closed);
                update('statCritical', data.data.critical);
            }
        })
        .catch(console.error);
}

function loadTickets() {
    const tbody = document.getElementById('ticketsTableBody');
    const pagingInfo = document.getElementById('ticketsPagingInfo');

    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';

    // Build Query String
    const params = new URLSearchParams();
    params.append('action', 'list');
    params.append('limit', ticketsState.pageSize);
    params.append('offset', ticketsState.page * ticketsState.pageSize);

    if (ticketsState.filters.queue !== 'all') {
        params.append('categoria', ticketsState.filters.queue);
    }
    if (ticketsState.filters.status !== 'all') {
        params.append('estado', ticketsState.filters.status);
    }
    if (ticketsState.filters.search) {
        params.append('search', ticketsState.filters.search);
    }

    fetch(`api/tickets/crud.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderTicketsTable(data.data, tbody);
                updateTicketsPaging(data.total, pagingInfo);
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${data.error || 'Error cargando datos'}</td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Error de conexión</td></tr>';
        });
}

function renderTicketsTable(data, body) {
    if (!data || data.length === 0) {
        body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay tickets encontrados</td></tr>';
        return;
    }

    body.innerHTML = data.map(item => {
        // Status Badge
        let statusClass = 'bg-secondary';
        const st = (item.estado || '').toLowerCase();
        if (st === 'abierto') statusClass = 'bg-danger';
        else if (st === 'en progreso') statusClass = 'bg-primary';
        else if (st === 'resuelto') statusClass = 'bg-success';
        else if (st === 'cerrado') statusClass = 'bg-dark';
        else if (st === 'espera') statusClass = 'bg-warning text-dark';

        // Priority
        let priorityBadge = '<span class="badge bg-secondary">Baja</span>';
        const pr = (item.prioridad || '').toLowerCase();
        if (pr === 'media') priorityBadge = '<span class="badge bg-info text-dark">Media</span>';
        if (pr === 'alta') priorityBadge = '<span class="badge bg-warning text-dark">Alta</span>';
        if (pr.includes('critica')) priorityBadge = '<span class="badge bg-danger">Crítica</span>';

        // Tech Name
        const techName = item.tecnico_nombre || 'Sin Asignar';
        const techInitials = getInitials(techName);

        return `
            <tr>
                <td class="ps-4">
                    <div class="fw-bold text-primary">#${item.numero_ticket || item.id}</div>
                    <div class="small text-muted">${item.categoria || 'General'}</div>
                </td>
                <td>
                    <div class="fw-bold text-dark">${item.titulo}</div>
                    ${item.activo_placa ? `<div class="small text-muted"><i class="fas fa-desktop me-1"></i>${item.activo_placa}</div>` : ''}
                </td>
                <td>${priorityBadge}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle bg-light text-primary me-2 border d-flex align-items-center justify-content-center"
                             style="width:30px;height:30px;border-radius:50%;font-size:12px;">
                            ${techInitials}
                        </div>
                        <span class="small">${techName}</span>
                    </div>
                </td>
                <td><span class="badge ${statusClass} rounded-pill">${item.estado}</span></td>
                <td>
                    <div class="small">${formatDate(item.fecha_creacion)}</div>
                    <div class="small text-muted">${formatTime(item.fecha_creacion)}</div>
                </td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm btn-link text-primary" onclick="editTicket(${item.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-link text-danger" onclick="deleteTicket(${item.id})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function updateTicketsPaging(total, element) {
    const start = ticketsState.page * ticketsState.pageSize + 1;
    const end = Math.min((ticketsState.page + 1) * ticketsState.pageSize, total);
    if (element) element.textContent = `Mostrando ${start}-${end} de ${total}`;
}

// Filters
function filterTickets(type, value) {
    if (type === 'queue') {
        ticketsState.filters.queue = value;
        // Update active tab UI
        document.querySelectorAll('[onclick^="filterTickets(\'queue\'"]').forEach(btn => {
            if (btn.getAttribute('onclick').includes(`'${value}'`)) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    } else if (type === 'status') {
        ticketsState.filters.status = value;
    }
    ticketsState.page = 0;
    loadTickets();
}

function prevTicketsPage() {
    if (ticketsState.page > 0) {
        ticketsState.page--;
        loadTickets();
    }
}

function nextTicketsPage() {
    ticketsState.page++;
    loadTickets();
}

function changeTicketsPageSize() {
    const el = document.getElementById('ticketsPageSize');
    if (el) {
        ticketsState.pageSize = parseInt(el.value);
        ticketsState.page = 0;
        loadTickets();
    }
}

// Modal & CRUD
function openTicketModal() {
    const form = document.getElementById('ticketForm');
    if (form) form.reset();
    document.getElementById('tkt_id').value = '';
    document.getElementById('ticketModalTitle').textContent = 'Nuevo Ticket de Soporte';

    const modal = new bootstrap.Modal(document.getElementById('ticketModal'));
    modal.show();
}

function saveTicket() {
    const form = document.getElementById('ticketForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const id = document.getElementById('tkt_id').value;
    formData.append('action', id ? 'update' : 'create');

    // Mapeo manual si los nombres no coinciden con la API,
    // pero en index.php puse los names correctos (titulo, categoria, etc)

    fetch('api/tickets/crud.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const modalEl = document.getElementById('ticketModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();

            // Show success toast or alert
            // alert('Ticket guardado correctamente');

            loadTickets();
            loadTicketStats();
        } else {
            alert('Error: ' + (data.error || 'No se pudo guardar'));
        }
    })
    .catch(console.error);
}

function editTicket(id) {
    fetch(`api/tickets/crud.php?action=get&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const t = data.data;
                document.getElementById('tkt_id').value = t.id;
                document.getElementById('tkt_titulo').value = t.titulo;
                document.getElementById('tkt_categoria').value = t.categoria;
                document.getElementById('tkt_equipo').value = t.equipo_id || '';
                document.getElementById('tkt_descripcion').value = t.descripcion;
                document.getElementById('tkt_prioridad').value = t.prioridad;
                document.getElementById('tkt_estado').value = t.estado;
                document.getElementById('tkt_asignado').value = t.asignado_a || '';

                document.getElementById('ticketModalTitle').textContent = `Editar Ticket #${t.numero_ticket}`;

                const modal = new bootstrap.Modal(document.getElementById('ticketModal'));
                modal.show();
            }
        })
        .catch(console.error);
}

function deleteTicket(id) {
    if (!confirm('¿Está seguro de eliminar este ticket? Esta acción no se puede deshacer.')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('api/tickets/crud.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadTickets();
            loadTicketStats();
        } else {
            alert('Error al eliminar');
        }
    })
    .catch(console.error);
}

// Helpers
function loadInventoryOptions() {
    fetch('api/inventario/crud.php?action=list&limit=500')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const sel = document.getElementById('tkt_equipo');
                if (!sel) return;
                sel.innerHTML = '<option value="">Seleccione equipo...</option>';
                data.data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    const nombre = item.nombre_funcional || item.tipo_equipo || item.modelo || 'Equipo';
                    opt.textContent = `${item.placa || item.serial || 'S/N'} - ${nombre}`;
                    sel.appendChild(opt);
                });
            }
        })
        .catch(console.error);
}

function loadTechOptions() {
    fetch('api/usuarios/crud.php?action=list')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const sel = document.getElementById('tkt_asignado');
                if (!sel) return;
                sel.innerHTML = '<option value="">Sin Asignar</option>';
                data.data.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id;
                    opt.textContent = u.nombre_completo || u.username;
                    sel.appendChild(opt);
                });
            }
        })
        .catch(console.error);
}

function getInitials(name) {
    if (!name) return '--';
    return name.match(/(\b\S)?/g).join("").match(/(^\S|\S$)?/g).join("").toUpperCase().substring(0, 2);
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function downloadTemplate(type) {
    // Implement download logic
    console.log('Download template', type);
}
