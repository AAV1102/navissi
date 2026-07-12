/**
 * WorkManager ERP - Admin JavaScript
 * Funcionalidades para el módulo de administración
 */

// Variables globales
let usuarios = [];
let systemLogs = [];
let currentUser = null;

// Inicialización
document.addEventListener('DOMContentLoaded', function () {
    initAdmin();
});

function initAdmin() {
    initTabs();
    loadSystemStats();
    setupEventListeners();
}

function setupEventListeners() {
    // Tabs
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const tabId = this.dataset.tab;
            switchTab(tabId);
        });
    });

    // Formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });
}

// Inicializar tabs
function initTabs() {
    const firstTab = document.querySelector('.tab-btn');
    if (firstTab) {
        switchTab(firstTab.dataset.tab);
    }
}

// Cambiar tab
function switchTab(tabId) {
    // Actualizar botones
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');

    // Actualizar contenido
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');

    // Cargar datos específicos del tab
    switch (tabId) {
        case 'usuarios':
            loadUsuarios();
            break;
        case 'logs':
            loadSystemLogs();
            break;
        case 'configuracion':
            loadSystemConfig();
            break;
    }
}

// Cargar estadísticas del sistema
async function loadSystemStats() {
    try {
        showLoading('Cargando estadísticas...');

        const response = await fetch('/api/admin.php?action=get_stats');
        const data = await response.json();

        if (data.success) {
            updateStatsDisplay(data.data);
        } else {
            showError('Error al cargar estadísticas: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Actualizar display de estadísticas
function updateStatsDisplay(stats) {
    const statElements = {
        'total_usuarios': document.querySelector('.stat-card:nth-child(1) h3'),
        'total_sedes': document.querySelector('.stat-card:nth-child(2) h3'),
        'total_equipos': document.querySelector('.stat-card:nth-child(3) h3'),
        'tickets_abiertos': document.querySelector('.stat-card:nth-child(4) h3')
    };

    Object.keys(statElements).forEach(key => {
        if (statElements[key] && stats[key] !== undefined) {
            statElements[key].textContent = formatNumber(stats[key]);
        }
    });
}

// Cargar usuarios
async function loadUsuarios() {
    try {
        showLoading('Cargando usuarios...');

        const response = await fetch('/api/admin.php?action=get_usuarios');
        const data = await response.json();

        if (data.success) {
            usuarios = data.data;
            renderUsuariosTable();
        } else {
            showError('Error al cargar usuarios: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Renderizar tabla de usuarios
function renderUsuariosTable() {
    const tableBody = document.getElementById('usuariosTable');
    if (!tableBody) return;

    if (usuarios.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center">
                    <div class="empty-state">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No hay usuarios registrados</h5>
                        <p class="text-muted">Comienza agregando el primer usuario</p>
                        <button class="btn btn-primary" onclick="showModal('modalNuevoUsuario')">
                            <i class="fas fa-plus"></i> Agregar Usuario
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tableBody.innerHTML = usuarios.map(usuario => `
        <tr>
            <td>${usuario.id}</td>
            <td>
                <div>
                    <strong>${escapeHtml(usuario.nombre)} ${escapeHtml(usuario.apellido)}</strong>
                </div>
            </td>
            <td>${escapeHtml(usuario.email)}</td>
            <td>
                <span class="badge badge-${getRoleBadgeClass(usuario.rol)}">
                    ${escapeHtml(usuario.rol)}
                </span>
            </td>
            <td>
                <span class="status-badge status-${usuario.estado.toLowerCase()}">
                    ${escapeHtml(usuario.estado)}
                </span>
            </td>
            <td>
                ${usuario.ultimo_acceso ? formatDate(usuario.ultimo_acceso) : 'Nunca'}
            </td>
            <td>
                <div class="btn-group">
                    <button class="btn btn-sm btn-primary" onclick="editUsuario(${usuario.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="resetPassword(${usuario.id})" title="Resetear Contraseña">
                        <i class="fas fa-key"></i>
                    </button>
                    <button class="btn btn-sm btn-${usuario.estado === 'activo' ? 'secondary' : 'success'}" 
                            onclick="toggleUserStatus(${usuario.id})" 
                            title="${usuario.estado === 'activo' ? 'Desactivar' : 'Activar'}">
                        <i class="fas fa-${usuario.estado === 'activo' ? 'ban' : 'check'}"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Crear nuevo usuario
async function createUsuario(formData) {
    try {
        showLoading('Creando usuario...');

        const response = await fetch('/api/admin.php?action=crear_usuario', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Usuario creado exitosamente');
            hideModal('modalNuevoUsuario');
            loadUsuarios();
        } else {
            showError('Error al crear usuario: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Editar usuario
function editUsuario(usuarioId) {
    const usuario = usuarios.find(u => u.id === usuarioId);
    if (!usuario) return;

    currentUser = usuario;

    // Llenar formulario de edición
    fillEditUserForm(usuario);
    showModal('modalEditarUsuario');
}

// Llenar formulario de edición de usuario
function fillEditUserForm(usuario) {
    const form = document.getElementById('formEditarUsuario');
    if (!form) return;

    const fields = ['nombre', 'apellido', 'email', 'rol', 'estado'];

    fields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (input && usuario[field] !== undefined) {
            input.value = usuario[field] || '';
        }
    });
}

// Actualizar usuario
async function updateUsuario(formData) {
    if (!currentUser) return;

    try {
        showLoading('Actualizando usuario...');

        formData.append('id', currentUser.id);

        const response = await fetch('/api/admin.php?action=actualizar_usuario', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Usuario actualizado exitosamente');
            hideModal('modalEditarUsuario');
            loadUsuarios();
            currentUser = null;
        } else {
            showError('Error al actualizar usuario: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Cargar logs del sistema
async function loadSystemLogs() {
    try {
        showLoading('Cargando logs...');

        const response = await fetch('/api/admin.php?action=get_logs');
        const data = await response.json();

        if (data.success) {
            systemLogs = data.data;
            renderLogsContainer();
        } else {
            showError('Error al cargar logs: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Renderizar contenedor de logs
function renderLogsContainer() {
    const container = document.getElementById('logsContainer');
    if (!container) return;

    if (systemLogs.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-list-alt fa-3x text-muted mb-3"></i>
                <h5>No hay logs del sistema</h5>
                <p class="text-muted">Los logs aparecerán aquí cuando se registren actividades</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="logs-list">
            ${systemLogs.map(log => `
                <div class="log-item">
                    <div class="log-header">
                        <span class="log-action badge badge-${getLogBadgeClass(log.accion)}">
                            ${escapeHtml(log.accion)}
                        </span>
                        <span class="log-date">${formatDateTime(log.fecha)}</span>
                    </div>
                    <div class="log-description">
                        ${escapeHtml(log.descripcion)}
                    </div>
                    <div class="log-meta">
                        <small class="text-muted">
                            <i class="fas fa-user"></i> Usuario ID: ${log.usuario_id}
                            <i class="fas fa-globe ml-2"></i> IP: ${log.ip_address}
                        </small>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

// Crear backup
async function createBackup() {
    if (!confirm('¿Estás seguro de que deseas crear un backup de la base de datos?')) {
        return;
    }

    try {
        showLoading('Creando backup...');

        const response = await fetch('/api/admin.php?action=create_backup', {
            method: 'POST'
        });

        const data = await response.json();

        if (data.success) {
            showSuccess(`Backup creado exitosamente: ${data.file}`);
        } else {
            showError('Error al crear backup: ' + data.error);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Mostrar información del sistema
async function showSystemInfo() {
    try {
        showLoading('Cargando información del sistema...');

        const response = await fetch('/api/admin.php?action=get_system_info');
        const data = await response.json();

        if (data.success) {
            renderSystemInfoModal(data.data);
            showModal('modalSystemInfo');
        } else {
            showError('Error al cargar información: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Renderizar modal de información del sistema
function renderSystemInfoModal(info) {
    const container = document.getElementById('systemInfoContainer');
    if (!container) return;

    container.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h5>Servidor</h5>
                <ul class="list-unstyled">
                    <li><strong>PHP:</strong> ${info.php_version}</li>
                    <li><strong>Servidor:</strong> ${info.server_software}</li>
                    <li><strong>Memoria:</strong> ${formatBytes(info.memory_usage)}</li>
                    <li><strong>Límite de memoria:</strong> ${info.memory_limit}</li>
                    <li><strong>Tiempo de ejecución:</strong> ${info.max_execution_time}s</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h5>Almacenamiento</h5>
                <ul class="list-unstyled">
                    <li><strong>Base de datos:</strong> ${formatBytes(info.database_size)}</li>
                    <li><strong>Espacio libre:</strong> ${formatBytes(info.disk_usage.free)}</li>
                    <li><strong>Espacio total:</strong> ${formatBytes(info.disk_usage.total)}</li>
                    <li><strong>Subida máxima:</strong> ${info.upload_max_filesize}</li>
                    <li><strong>POST máximo:</strong> ${info.post_max_size}</li>
                </ul>
            </div>
        </div>
    `;
}

// Resetear contraseña de usuario
async function resetPassword(usuarioId) {
    const usuario = usuarios.find(u => u.id === usuarioId);
    if (!usuario) return;

    if (!confirm(`¿Estás seguro de que deseas resetear la contraseña de ${usuario.nombre} ${usuario.apellido}?`)) {
        return;
    }

    try {
        showLoading('Reseteando contraseña...');

        const formData = new FormData();
        formData.append('id', usuarioId);
        formData.append('password', 'temporal123'); // Contraseña temporal

        const response = await fetch('/api/admin.php?action=actualizar_usuario', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Contraseña reseteada a "temporal123". El usuario debe cambiarla en el próximo acceso.');
        } else {
            showError('Error al resetear contraseña: ' + data.message);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Alternar estado de usuario
async function toggleUserStatus(usuarioId) {
    const usuario = usuarios.find(u => u.id === usuarioId);
    if (!usuario) return;

    const newStatus = usuario.estado === 'activo' ? 'inactivo' : 'activo';
    const action = newStatus === 'activo' ? 'activar' : 'desactivar';

    if (!confirm(`¿Estás seguro de que deseas ${action} a ${usuario.nombre} ${usuario.apellido}?`)) {
        return;
    }

    try {
        showLoading(`${action.charAt(0).toUpperCase() + action.slice(1)}ando usuario...`);

        const formData = new FormData();
        formData.append('id', usuarioId);
        formData.append('estado', newStatus);

        const response = await fetch('/api/admin.php?action=actualizar_usuario', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showSuccess(`Usuario ${action}do exitosamente`);
            loadUsuarios();
        } else {
            showError(`Error al ${action} usuario: ` + data.message);
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
        case 'formNuevoUsuario':
            createUsuario(formData);
            break;
        case 'formEditarUsuario':
            updateUsuario(formData);
            break;
    }
}

// Funciones de utilidad
function getRoleBadgeClass(rol) {
    const classes = {
        'Super Admin': 'danger',
        'Admin': 'warning',
        'Usuario': 'primary',
        'Técnico': 'info'
    };
    return classes[rol] || 'secondary';
}

function getLogBadgeClass(accion) {
    const classes = {
        'login': 'success',
        'logout': 'secondary',
        'create': 'primary',
        'update': 'warning',
        'delete': 'danger',
        'backup_created': 'info'
    };
    return classes[accion] || 'secondary';
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    return date.toLocaleString('es-CO', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    return date.toLocaleDateString('es-CO');
}

function formatNumber(number) {
    return new Intl.NumberFormat('es-CO').format(number);
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

function showLoading(message = 'Cargando...') {
    console.log('Loading:', message);
}

function hideLoading() {
    console.log('Loading hidden');
}

function showSuccess(message) {
    console.log('Success:', message);
}

function showError(message) {
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