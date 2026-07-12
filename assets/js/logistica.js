/**
 * JavaScript para el módulo Logística
 */

// Variables globales
let currentOrdenId = null;
let currentProductoId = null;

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
                case 'ordenes':
                    loadOrdenes();
                    break;
                case 'inventario':
                    loadInventario();
                    break;
                case 'entregas':
                    loadEntregas();
                    break;
            }
        });
    });
}

// Cargar órdenes
function loadOrdenes(filters = {}) {
    const searchTerm = document.getElementById('searchOrdenes')?.value || '';
    const estado = document.getElementById('filterEstadoOrdenes')?.value || '';

    const params = new URLSearchParams({
        action: 'get_ordenes',
        search: searchTerm,
        estado: estado,
        ...filters
    });

    fetch(`../../api/logistica.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateOrdenesTable(data.ordenes);
            } else {
                showAlert('Error al cargar órdenes: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Actualizar tabla de órdenes
function updateOrdenesTable(ordenes) {
    const tbody = document.getElementById('ordenesTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    ordenes.forEach(orden => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(orden.numero_orden)}</td>
            <td>${escapeHtml(orden.nombre_proveedor || 'N/A')}</td>
            <td>${escapeHtml(orden.solicitante)}</td>
            <td>${escapeHtml(orden.servicio)}</td>
            <td>${formatDate(orden.fecha_solicitud)}</td>
            <td>
                ${orden.monto_total ? `$${formatNumber(orden.monto_total)}` : 'N/A'}
            </td>
            <td>
                <span class="status-badge status-${orden.estado.toLowerCase().replace(' ', '-')}">
                    ${escapeHtml(orden.estado)}
                </span>
            </td>
            <td>
                ${orden.estado === 'Pendiente' ? `
                    <button class="btn btn-sm btn-success" onclick="aprobarOrden(${orden.id})">
                        <i class="fas fa-check"></i> Aprobar
                    </button>
                ` : ''}
                <button class="btn btn-sm btn-primary" onclick="editOrden(${orden.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-info" onclick="viewOrden(${orden.id})">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Cargar inventario
function loadInventario(filters = {}) {
    const searchTerm = document.getElementById('searchInventario')?.value || '';
    const categoria = document.getElementById('filterCategoria')?.value || '';

    const params = new URLSearchParams({
        action: 'get_inventario',
        search: searchTerm,
        categoria: categoria,
        ...filters
    });

    fetch(`../../api/logistica.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateInventarioTable(data.productos);
            } else {
                showAlert('Error al cargar inventario: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Actualizar tabla de inventario
function updateInventarioTable(productos) {
    const container = document.getElementById('inventarioContainer');
    if (!container) return;

    let html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Stock Actual</th>
                    <th>Stock Mínimo</th>
                    <th>Ubicación</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
    `;

    productos.forEach(producto => {
        const stockBajo = producto.cantidad_actual <= producto.cantidad_minima;
        const stockClass = stockBajo ? 'text-danger fw-bold' : '';

        html += `
            <tr>
                <td>${escapeHtml(producto.codigo_producto || 'N/A')}</td>
                <td>${escapeHtml(producto.nombre_producto)}</td>
                <td>${escapeHtml(producto.categoria || 'N/A')}</td>
                <td class="${stockClass}">
                    ${producto.cantidad_actual}
                    ${stockBajo ? '<i class="fas fa-exclamation-triangle text-warning ms-1"></i>' : ''}
                </td>
                <td>${producto.cantidad_minima}</td>
                <td>${escapeHtml(producto.ubicacion || 'N/A')}</td>
                <td>
                    <span class="status-badge status-${producto.estado}">
                        ${ucfirst(producto.estado)}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editProducto(${producto.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-success" onclick="registrarMovimiento(${producto.id}, 'entrada')">
                        <i class="fas fa-plus"></i> Entrada
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="registrarMovimiento(${producto.id}, 'salida')">
                        <i class="fas fa-minus"></i> Salida
                    </button>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

// Cargar entregas
function loadEntregas(filters = {}) {
    const estado = document.getElementById('filterEstadoEntregas')?.value || '';
    const fechaDesde = document.getElementById('fechaDesdeEntregas')?.value || '';
    const fechaHasta = document.getElementById('fechaHastaEntregas')?.value || '';

    const params = new URLSearchParams({
        action: 'get_entregas',
        estado: estado,
        fecha_desde: fechaDesde,
        fecha_hasta: fechaHasta,
        ...filters
    });

    fetch(`../../api/logistica.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateEntregasTable(data.entregas);
            } else {
                showAlert('Error al cargar entregas: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Actualizar tabla de entregas
function updateEntregasTable(entregas) {
    const tbody = document.querySelector('#entregas tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    entregas.forEach(entrega => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(entrega.numero_guia)}</td>
            <td>${escapeHtml(entrega.numero_orden || 'N/A')}</td>
            <td>${escapeHtml(entrega.transportadora)}</td>
            <td>${formatDate(entrega.fecha_envio)}</td>
            <td>
                ${entrega.fecha_entrega_estimada ? formatDate(entrega.fecha_entrega_estimada) : 'N/A'}
            </td>
            <td>
                <span class="status-badge status-${entrega.estado.toLowerCase().replace(' ', '-')}">
                    ${escapeHtml(entrega.estado)}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="trackEntrega('${entrega.numero_guia}')">
                    <i class="fas fa-search"></i> Rastrear
                </button>
                ${entrega.estado !== 'Entregado' ? `
                    <button class="btn btn-sm btn-success" onclick="confirmarEntrega(${entrega.id})">
                        <i class="fas fa-check"></i> Confirmar
                    </button>
                ` : ''}
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Aprobar orden
function aprobarOrden(ordenId) {
    if (confirm('¿Está seguro de aprobar esta orden de compra?')) {
        fetch('../../api/logistica.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'aprobar_orden',
                id: ordenId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Orden aprobada exitosamente', 'success');
                    loadOrdenes();
                } else {
                    showAlert('Error al aprobar orden: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error de conexión', 'error');
            });
    }
}

// Registrar movimiento de inventario
function registrarMovimiento(productoId, tipoMovimiento) {
    currentProductoId = productoId;

    // Cargar datos del producto
    fetch(`../../api/logistica.php?action=get_producto&id=${productoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateMovimientoForm(data.producto, tipoMovimiento);
                showModal('modalRegistrarMovimiento');
            } else {
                showAlert('Error al cargar producto: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Poblar formulario de movimiento
function populateMovimientoForm(producto, tipoMovimiento) {
    document.getElementById('movimientoProducto').textContent = producto.nombre_producto;
    document.getElementById('movimientoStockActual').textContent = producto.cantidad_actual;
    document.getElementById('movimientoTipo').value = tipoMovimiento;
    document.getElementById('movimientoTipoLabel').textContent = tipoMovimiento === 'entrada' ? 'Entrada' : 'Salida';

    // Configurar validación según el tipo
    const cantidadInput = document.getElementById('movimientoCantidad');
    if (tipoMovimiento === 'salida') {
        cantidadInput.max = producto.cantidad_actual;
        cantidadInput.setAttribute('data-max-stock', producto.cantidad_actual);
    } else {
        cantidadInput.removeAttribute('max');
        cantidadInput.removeAttribute('data-max-stock');
    }
}

// Confirmar movimiento
function confirmarMovimiento() {
    const form = document.getElementById('formRegistrarMovimiento');
    const formData = new FormData(form);

    const movimientoData = {
        action: 'registrar_movimiento',
        producto_id: currentProductoId,
        tipo_movimiento: formData.get('tipo_movimiento'),
        cantidad: parseInt(formData.get('cantidad')),
        motivo: formData.get('motivo'),
        referencia: formData.get('referencia'),
        observaciones: formData.get('observaciones')
    };

    // Validar cantidad para salidas
    if (movimientoData.tipo_movimiento === 'salida') {
        const maxStock = parseInt(document.getElementById('movimientoCantidad').getAttribute('data-max-stock'));
        if (movimientoData.cantidad > maxStock) {
            showAlert(`La cantidad no puede ser mayor al stock actual (${maxStock})`, 'error');
            return;
        }
    }

    fetch('../../api/logistica.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(movimientoData)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Movimiento registrado exitosamente', 'success');
                hideModal('modalRegistrarMovimiento');
                loadInventario();
            } else {
                showAlert('Error al registrar movimiento: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Rastrear entrega
function trackEntrega(numeroGuia) {
    // Aquí podrías integrar con APIs de transportadoras
    showAlert(`Función de rastreo para guía: ${numeroGuia}. Integración con transportadora pendiente.`, 'info');
}

// Confirmar entrega
function confirmarEntrega(entregaId) {
    if (confirm('¿Confirmar que la entrega fue recibida?')) {
        fetch('../../api/logistica.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'confirmar_entrega',
                id: entregaId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Entrega confirmada exitosamente', 'success');
                    loadEntregas();
                } else {
                    showAlert('Error al confirmar entrega: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error de conexión', 'error');
            });
    }
}

// Cargar productos con stock bajo
function loadStockBajo() {
    loadInventario({ stock_bajo: true });
}

// Funciones CRUD
function editOrden(id) {
    currentOrdenId = id;
    // Implementar lógica de edición
}

function viewOrden(id) {
    // Implementar lógica de visualización
}

function editProducto(id) {
    currentProductoId = id;
    // Implementar lógica de edición
}

// Generar reportes
function generateOrdenesReport() {
    const filters = {
        search: document.getElementById('searchOrdenes')?.value || '',
        estado: document.getElementById('filterEstadoOrdenes')?.value || ''
    };

    const params = new URLSearchParams({
        action: 'generate_ordenes_report',
        format: 'pdf',
        ...filters
    });

    window.open(`../../api/logistica.php?${params}`, '_blank');
}

function generateInventarioReport() {
    const filters = {
        search: document.getElementById('searchInventario')?.value || '',
        categoria: document.getElementById('filterCategoria')?.value || ''
    };

    const params = new URLSearchParams({
        action: 'generate_inventario_report',
        format: 'excel',
        ...filters
    });

    window.open(`../../api/logistica.php?${params}`, '_blank');
}

function generateEntregasReport() {
    const filters = {
        estado: document.getElementById('filterEstadoEntregas')?.value || '',
        fecha_desde: document.getElementById('fechaDesdeEntregas')?.value || '',
        fecha_hasta: document.getElementById('fechaHastaEntregas')?.value || ''
    };

    const params = new URLSearchParams({
        action: 'generate_entregas_report',
        format: 'pdf',
        ...filters
    });

    window.open(`../../api/logistica.php?${params}`, '_blank');
}

function generateProveedoresReport() {
    const params = new URLSearchParams({
        action: 'generate_proveedores_report',
        format: 'pdf'
    });

    window.open(`../../api/logistica.php?${params}`, '_blank');
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
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    const container = document.querySelector('.main-content');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);

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

function formatNumber(number) {
    return new Intl.NumberFormat('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number);
}

function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function () {
    // Configurar búsqueda en tiempo real
    const searchOrdenes = document.getElementById('searchOrdenes');
    if (searchOrdenes) {
        searchOrdenes.addEventListener('input', debounce(() => loadOrdenes(), 300));
    }

    const searchInventario = document.getElementById('searchInventario');
    if (searchInventario) {
        searchInventario.addEventListener('input', debounce(() => loadInventario(), 300));
    }

    // Configurar filtros
    const filterEstadoOrdenes = document.getElementById('filterEstadoOrdenes');
    if (filterEstadoOrdenes) {
        filterEstadoOrdenes.addEventListener('change', () => loadOrdenes());
    }

    const filterCategoria = document.getElementById('filterCategoria');
    if (filterCategoria) {
        filterCategoria.addEventListener('change', () => loadInventario());
    }

    const filterEstadoEntregas = document.getElementById('filterEstadoEntregas');
    if (filterEstadoEntregas) {
        filterEstadoEntregas.addEventListener('change', () => loadEntregas());
    }

    // Validación de cantidad en movimientos
    const cantidadInput = document.getElementById('movimientoCantidad');
    if (cantidadInput) {
        cantidadInput.addEventListener('input', function () {
            const maxStock = this.getAttribute('data-max-stock');
            if (maxStock && parseInt(this.value) > parseInt(maxStock)) {
                this.setCustomValidity(`La cantidad no puede ser mayor a ${maxStock}`);
            } else {
                this.setCustomValidity('');
            }
        });
    }
});

// Función debounce
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