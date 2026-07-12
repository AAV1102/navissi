/**
 * JavaScript para el módulo Tesorería
 */

// Variables globales
let currentFacturaId = null;
let currentProveedorId = null;

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
                case 'facturas':
                    loadFacturas();
                    break;
                case 'proveedores':
                    loadProveedores();
                    break;
                case 'flujo-caja':
                    loadFlujoCaja();
                    break;
            }
        });
    });
}

// Cargar facturas
function loadFacturas(filters = {}) {
    const searchTerm = document.getElementById('searchFacturas')?.value || '';
    const estado = document.getElementById('filterEstadoFacturas')?.value || '';
    const proveedor = document.getElementById('filterProveedor')?.value || '';

    const params = new URLSearchParams({
        action: 'get_facturas',
        search: searchTerm,
        estado: estado,
        proveedor_id: proveedor,
        ...filters
    });

    fetch(`../../api/tesoreria.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateFacturasTable(data.facturas);
            } else {
                showAlert('Error al cargar facturas: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Actualizar tabla de facturas
function updateFacturasTable(facturas) {
    const tbody = document.getElementById('facturasTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    facturas.forEach(factura => {
        const row = document.createElement('tr');
        const fechaVencimiento = factura.fecha_vencimiento ? formatDate(factura.fecha_vencimiento) : 'N/A';
        const isVencida = factura.fecha_vencimiento && new Date(factura.fecha_vencimiento) < new Date() && factura.estado === 'pendiente';

        row.innerHTML = `
            <td>${escapeHtml(factura.numero_factura)}</td>
            <td>${escapeHtml(factura.nombre_proveedor || 'N/A')}</td>
            <td>$${formatNumber(factura.monto)}</td>
            <td>${formatDate(factura.fecha_emision)}</td>
            <td class="${isVencida ? 'text-danger fw-bold' : ''}">${fechaVencimiento}</td>
            <td>
                <span class="status-badge status-${factura.estado} ${isVencida ? 'status-vencida' : ''}">
                    ${isVencida ? 'Vencida' : ucfirst(factura.estado)}
                </span>
            </td>
            <td>
                ${factura.estado === 'pendiente' ? `
                    <button class="btn btn-sm btn-success" onclick="registrarPago(${factura.id})">
                        <i class="fas fa-money-bill"></i> Pagar
                    </button>
                ` : ''}
                <button class="btn btn-sm btn-primary" onclick="editFactura(${factura.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-info" onclick="viewFactura(${factura.id})">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Cargar proveedores
function loadProveedores(filters = {}) {
    const searchTerm = document.getElementById('searchProveedores')?.value || '';

    const params = new URLSearchParams({
        action: 'get_proveedores',
        search: searchTerm,
        ...filters
    });

    fetch(`../../api/tesoreria.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProveedoresTable(data.proveedores);
            } else {
                showAlert('Error al cargar proveedores: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Actualizar tabla de proveedores
function updateProveedoresTable(proveedores) {
    const tbody = document.querySelector('#proveedores tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    proveedores.forEach(proveedor => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(proveedor.nombre)}</td>
            <td>${escapeHtml(proveedor.nit || 'N/A')}</td>
            <td>${escapeHtml(proveedor.telefono || 'N/A')}</td>
            <td>${escapeHtml(proveedor.email || 'N/A')}</td>
            <td>${escapeHtml(proveedor.categoria || 'N/A')}</td>
            <td>
                <span class="status-badge status-${proveedor.estado}">
                    ${ucfirst(proveedor.estado)}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editProveedor(${proveedor.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-info" onclick="viewProveedor(${proveedor.id})">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Cargar flujo de caja
function loadFlujoCaja() {
    const fechaDesde = document.getElementById('fechaDesde')?.value || '';
    const fechaHasta = document.getElementById('fechaHasta')?.value || '';
    const tipo = document.getElementById('filterTipoMovimiento')?.value || '';

    const params = new URLSearchParams({
        action: 'get_flujo_caja',
        fecha_desde: fechaDesde,
        fecha_hasta: fechaHasta,
        tipo: tipo
    });

    fetch(`../../api/tesoreria.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateFlujoCajaTable(data.movimientos);
                updateFlujoCajaResumen(data.resumen);
            } else {
                showAlert('Error al cargar flujo de caja: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Actualizar tabla de flujo de caja
function updateFlujoCajaTable(movimientos) {
    const container = document.getElementById('flujoCajaContainer');
    if (!container) return;

    let html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Categoría</th>
                    <th>Descripción</th>
                    <th>Monto</th>
                    <th>Referencia</th>
                </tr>
            </thead>
            <tbody>
    `;

    movimientos.forEach(movimiento => {
        const tipoClass = movimiento.tipo === 'ingreso' ? 'text-success' : 'text-danger';
        const signo = movimiento.tipo === 'ingreso' ? '+' : '-';

        html += `
            <tr>
                <td>${formatDate(movimiento.fecha)}</td>
                <td>
                    <span class="badge bg-${movimiento.tipo === 'ingreso' ? 'success' : 'danger'}">
                        ${ucfirst(movimiento.tipo)}
                    </span>
                </td>
                <td>${escapeHtml(movimiento.categoria || 'N/A')}</td>
                <td>${escapeHtml(movimiento.descripcion || 'N/A')}</td>
                <td class="${tipoClass} fw-bold">${signo}$${formatNumber(movimiento.monto)}</td>
                <td>${escapeHtml(movimiento.referencia || 'N/A')}</td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

// Actualizar resumen de flujo de caja
function updateFlujoCajaResumen(resumen) {
    if (!resumen) return;

    // Actualizar elementos del resumen si existen
    const ingresosElement = document.querySelector('.flow-item.income .amount');
    const egresosElement = document.querySelector('.flow-item.expense .amount');
    const balanceElement = document.querySelector('.flow-item.balance .amount');

    if (ingresosElement) ingresosElement.textContent = `$${formatNumber(resumen.total_ingresos || 0)}`;
    if (egresosElement) egresosElement.textContent = `$${formatNumber(resumen.total_egresos || 0)}`;
    if (balanceElement) {
        const balance = (resumen.total_ingresos || 0) - (resumen.total_egresos || 0);
        balanceElement.textContent = `$${formatNumber(balance)}`;

        // Actualizar clase según el balance
        const balanceContainer = balanceElement.closest('.flow-item.balance');
        if (balanceContainer) {
            balanceContainer.classList.remove('positive', 'negative');
            balanceContainer.classList.add(balance >= 0 ? 'positive' : 'negative');
        }
    }
}

// Registrar pago de factura
function registrarPago(facturaId) {
    currentFacturaId = facturaId;

    // Cargar datos de la factura
    fetch(`../../api/tesoreria.php?action=get_factura&id=${facturaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populatePaymentForm(data.factura);
                showModal('modalRegistrarPago');
            } else {
                showAlert('Error al cargar factura: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Poblar formulario de pago
function populatePaymentForm(factura) {
    document.getElementById('pagoFacturaNumero').textContent = factura.numero_factura;
    document.getElementById('pagoFacturaMonto').textContent = `$${formatNumber(factura.monto)}`;
    document.getElementById('pagoMonto').value = factura.monto;
    document.getElementById('pagoFecha').value = new Date().toISOString().split('T')[0];
}

// Confirmar pago
function confirmarPago() {
    const form = document.getElementById('formRegistrarPago');
    const formData = new FormData(form);

    const paymentData = {
        action: 'registrar_pago',
        factura_id: currentFacturaId,
        monto: formData.get('monto'),
        fecha_pago: formData.get('fecha_pago'),
        metodo_pago: formData.get('metodo_pago'),
        referencia: formData.get('referencia'),
        banco: formData.get('banco'),
        observaciones: formData.get('observaciones')
    };

    fetch('../../api/tesoreria.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(paymentData)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Pago registrado exitosamente', 'success');
                hideModal('modalRegistrarPago');
                loadFacturas();
            } else {
                showAlert('Error al registrar pago: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión', 'error');
        });
}

// Funciones CRUD
function editFactura(id) {
    currentFacturaId = id;
    // Implementar lógica de edición
}

function viewFactura(id) {
    // Implementar lógica de visualización
}

function editProveedor(id) {
    currentProveedorId = id;
    // Implementar lógica de edición
}

function viewProveedor(id) {
    // Implementar lógica de visualización
}

// Generar reportes
function generateFacturasReport() {
    const filters = {
        search: document.getElementById('searchFacturas')?.value || '',
        estado: document.getElementById('filterEstadoFacturas')?.value || '',
        proveedor_id: document.getElementById('filterProveedor')?.value || ''
    };

    const params = new URLSearchParams({
        action: 'generate_facturas_report',
        format: 'pdf',
        ...filters
    });

    window.open(`../../api/tesoreria.php?${params}`, '_blank');
}

function generateFlujoCajaReport() {
    const filters = {
        fecha_desde: document.getElementById('fechaDesde')?.value || '',
        fecha_hasta: document.getElementById('fechaHasta')?.value || '',
        tipo: document.getElementById('filterTipoMovimiento')?.value || ''
    };

    const params = new URLSearchParams({
        action: 'generate_flujo_caja_report',
        format: 'excel',
        ...filters
    });

    window.open(`../../api/tesoreria.php?${params}`, '_blank');
}

function generateProveedoresReport() {
    const params = new URLSearchParams({
        action: 'generate_proveedores_report',
        format: 'pdf'
    });

    window.open(`../../api/tesoreria.php?${params}`, '_blank');
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
    const searchFacturas = document.getElementById('searchFacturas');
    if (searchFacturas) {
        searchFacturas.addEventListener('input', debounce(() => loadFacturas(), 300));
    }

    const searchProveedores = document.getElementById('searchProveedores');
    if (searchProveedores) {
        searchProveedores.addEventListener('input', debounce(() => loadProveedores(), 300));
    }

    // Configurar filtros
    const filterEstadoFacturas = document.getElementById('filterEstadoFacturas');
    if (filterEstadoFacturas) {
        filterEstadoFacturas.addEventListener('change', () => loadFacturas());
    }

    const filterProveedor = document.getElementById('filterProveedor');
    if (filterProveedor) {
        filterProveedor.addEventListener('change', () => loadFacturas());
    }

    const filterTipoMovimiento = document.getElementById('filterTipoMovimiento');
    if (filterTipoMovimiento) {
        filterTipoMovimiento.addEventListener('change', () => loadFlujoCaja());
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