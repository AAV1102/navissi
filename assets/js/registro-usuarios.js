/**
 * WorkManager ERP - Registro de Usuarios JavaScript
 * Funcionalidad para gestión de solicitudes de registro
 */

class RegistroUsuariosManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadPendingSolicitudes();
    }

    bindEvents() {
        // Formulario de nueva solicitud
        document.getElementById('formNuevaSolicitud')?.addEventListener('submit', (e) => {
            this.handleNuevaSolicitud(e);
        });

        // Auto-refresh cada 30 segundos
        setInterval(() => {
            this.loadPendingSolicitudes();
        }, 30000);
    }

    handleNuevaSolicitud(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';

        fetch('registro-usuarios.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess('Solicitud creada exitosamente');
                    hideModal('modalNuevaSolicitud');
                    e.target.reset();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showError(data.message || 'Error creando solicitud');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('Error de conexión');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
    }

    aprobarSolicitud(solicitudId) {
        const comentarios = prompt('Comentarios de aprobación (opcional):');
        if (comentarios === null) return; // Usuario canceló

        if (!confirm('¿Estás seguro de que deseas aprobar esta solicitud?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'aprobar_solicitud');
        formData.append('solicitud_id', solicitudId);
        formData.append('comentarios', comentarios);

        this.procesarSolicitud(formData, 'aprobada');
    }

    rechazarSolicitud(solicitudId) {
        const motivo = prompt('Motivo del rechazo (requerido):');
        if (!motivo || motivo.trim() === '') {
            alert('Debes proporcionar un motivo para el rechazo');
            return;
        }

        if (!confirm('¿Estás seguro de que deseas rechazar esta solicitud?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'rechazar_solicitud');
        formData.append('solicitud_id', solicitudId);
        formData.append('motivo', motivo);

        this.procesarSolicitud(formData, 'rechazada');
    }

    procesarSolicitud(formData, accion) {
        fetch('registro-usuarios.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess(`Solicitud ${accion} exitosamente`);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showError(data.message || `Error ${accion === 'aprobada' ? 'aprobando' : 'rechazando'} solicitud`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('Error de conexión');
            });
    }

    verDetalleSolicitud(solicitudId) {
        // Obtener detalles de la solicitud
        fetch(`registro-usuarios.php?action=get_solicitud&id=${solicitudId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showDetalleSolicitud(data.solicitud);
                } else {
                    this.showError('Error cargando detalles de la solicitud');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('Error de conexión');
            });
    }

    showDetalleSolicitud(solicitud) {
        const modal = document.getElementById('modalDetalleSolicitud');
        if (!modal) {
            this.createDetalleModal();
        }

        // Llenar datos del modal
        document.getElementById('detalleNombre').textContent = `${solicitud.nombre} ${solicitud.apellido}`;
        document.getElementById('detalleEmail').textContent = solicitud.email;
        document.getElementById('detalleTelefono').textContent = solicitud.telefono || 'No especificado';
        document.getElementById('detalleCedula').textContent = solicitud.cedula || 'No especificado';
        document.getElementById('detalleCargo').textContent = solicitud.cargo || 'No especificado';
        document.getElementById('detalleDepartamento').textContent = solicitud.departamento || 'No especificado';
        document.getElementById('detalleModulo').textContent = solicitud.modulo_solicitado;
        document.getElementById('detalleJustificacion').textContent = solicitud.justificacion || 'Sin justificación';
        document.getElementById('detalleFecha').textContent = new Date(solicitud.fecha_solicitud).toLocaleString();

        // Configurar botones de acción
        const btnAprobar = document.getElementById('btnAprobarDetalle');
        const btnRechazar = document.getElementById('btnRechazarDetalle');

        btnAprobar.onclick = () => {
            hideModal('modalDetalleSolicitud');
            this.aprobarSolicitud(solicitud.id);
        };

        btnRechazar.onclick = () => {
            hideModal('modalDetalleSolicitud');
            this.rechazarSolicitud(solicitud.id);
        };

        showModal('modalDetalleSolicitud');
    }

    createDetalleModal() {
        const modalHTML = `
            <div class="modal" id="modalDetalleSolicitud">
                <div class="modal-content modal-lg">
                    <div class="modal-header">
                        <h3>Detalle de Solicitud</h3>
                        <button class="modal-close" onclick="hideModal('modalDetalleSolicitud')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="detail-grid">
                            <div class="detail-section">
                                <h4>Información Personal</h4>
                                <div class="detail-item">
                                    <label>Nombre Completo:</label>
                                    <span id="detalleNombre"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Email:</label>
                                    <span id="detalleEmail"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Teléfono:</label>
                                    <span id="detalleTelefono"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Cédula:</label>
                                    <span id="detalleCedula"></span>
                                </div>
                            </div>
                            
                            <div class="detail-section">
                                <h4>Información Laboral</h4>
                                <div class="detail-item">
                                    <label>Cargo:</label>
                                    <span id="detalleCargo"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Departamento:</label>
                                    <span id="detalleDepartamento"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Módulo Solicitado:</label>
                                    <span id="detalleModulo" class="badge badge-info"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Fecha de Solicitud:</label>
                                    <span id="detalleFecha"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section full-width">
                            <h4>Justificación</h4>
                            <div class="justification-box">
                                <p id="detalleJustificacion"></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="hideModal('modalDetalleSolicitud')">
                            Cerrar
                        </button>
                        <button type="button" class="btn btn-danger" id="btnRechazarDetalle">
                            <i class="fas fa-times"></i> Rechazar
                        </button>
                        <button type="button" class="btn btn-success" id="btnAprobarDetalle">
                            <i class="fas fa-check"></i> Aprobar
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Agregar estilos para el modal de detalle
        const styles = `
            <style>
                .detail-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 30px;
                    margin-bottom: 20px;
                }
                
                .detail-section {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                }
                
                .detail-section.full-width {
                    grid-column: 1 / -1;
                }
                
                .detail-section h4 {
                    margin: 0 0 15px 0;
                    color: #333;
                    border-bottom: 2px solid #007bff;
                    padding-bottom: 5px;
                }
                
                .detail-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 10px;
                    padding: 8px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                
                .detail-item:last-child {
                    border-bottom: none;
                    margin-bottom: 0;
                }
                
                .detail-item label {
                    font-weight: 600;
                    color: #495057;
                    margin-right: 10px;
                }
                
                .detail-item span {
                    color: #212529;
                    text-align: right;
                    flex: 1;
                }
                
                .justification-box {
                    background: white;
                    padding: 15px;
                    border-radius: 6px;
                    border: 1px solid #dee2e6;
                    min-height: 80px;
                }
                
                .justification-box p {
                    margin: 0;
                    line-height: 1.5;
                    color: #495057;
                }
                
                @media (max-width: 768px) {
                    .detail-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
        `;

        document.head.insertAdjacentHTML('beforeend', styles);
    }

    loadPendingSolicitudes() {
        // Actualizar contador de solicitudes pendientes
        fetch('registro-usuarios.php?action=get_pending_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const counter = document.getElementById('pendingCounter');
                    if (counter) {
                        counter.textContent = data.count;
                        counter.style.display = data.count > 0 ? 'inline' : 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading pending count:', error);
            });
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        // Crear notificación temporal
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            ${message}
        `;

        // Agregar estilos si no existen
        if (!document.getElementById('notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 5px;
                    color: white;
                    font-weight: 600;
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                    max-width: 400px;
                }
                .notification-success { background: #28a745; }
                .notification-error { background: #dc3545; }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(notification);

        // Remover después de 5 segundos
        setTimeout(() => {
            notification.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }
}

// Funciones globales para compatibilidad
function aprobarSolicitud(id) {
    window.registroManager.aprobarSolicitud(id);
}

function rechazarSolicitud(id) {
    window.registroManager.rechazarSolicitud(id);
}

function verDetalleSolicitud(id) {
    window.registroManager.verDetalleSolicitud(id);
}

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function () {
    window.registroManager = new RegistroUsuariosManager();
});