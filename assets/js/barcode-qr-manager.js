/**
 * Gestor de Códigos de Barras y QR - WorkManager ERP
 * Sistema responsivo para móviles y escritorio
 * Desarrollado por: Anderson Ayala Vera
 */

class BarcodeQRManager {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupResponsiveHandlers();
    }

    init() {
        // Configuración inicial
        this.isMobile = window.innerWidth <= 768;
        this.scanner = null;
        this.currentEquipo = null;

        // Cargar librerías necesarias
        this.loadLibraries();
    }

    loadLibraries() {
        // Cargar QuaggaJS para lectura de códigos de barras
        if (!window.Quagga) {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js';
            script.onload = () => this.initScanner();
            document.head.appendChild(script);
        }

        // Cargar QRCode.js para generación de QR
        if (!window.QRCode) {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.3/qrcode.min.js';
            document.head.appendChild(script);
        }
    }

    setupEventListeners() {
        // Botón para escanear código
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-scan-barcode')) {
                this.startBarcodeScanner();
            }

            if (e.target.matches('.btn-generate-qr')) {
                const equipoId = e.target.dataset.equipoId;
                const tipoTabla = e.target.dataset.tipoTabla;
                this.generateQRCode(equipoId, tipoTabla);
            }

            if (e.target.matches('.btn-print-label')) {
                const equipoId = e.target.dataset.equipoId;
                const tipoTabla = e.target.dataset.tipoTabla;
                this.printLabel(equipoId, tipoTabla);
            }

            if (e.target.matches('.btn-search-barcode')) {
                this.searchByBarcode();
            }
        });

        // Input de búsqueda por código
        document.addEventListener('input', (e) => {
            if (e.target.matches('#barcode-search-input')) {
                this.handleBarcodeInput(e.target.value);
            }
        });

        // Manejo de Enter en búsqueda
        document.addEventListener('keypress', (e) => {
            if (e.target.matches('#barcode-search-input') && e.key === 'Enter') {
                this.searchByBarcode();
            }
        });
    }

    setupResponsiveHandlers() {
        // Detectar cambios de orientación en móviles
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                this.isMobile = window.innerWidth <= 768;
                this.adjustLayoutForDevice();
            }, 100);
        });

        // Detectar cambios de tamaño de ventana
        window.addEventListener('resize', () => {
            this.isMobile = window.innerWidth <= 768;
            this.adjustLayoutForDevice();
        });
    }

    adjustLayoutForDevice() {
        const barcodeContainers = document.querySelectorAll('.barcode-container');

        barcodeContainers.forEach(container => {
            if (this.isMobile) {
                container.classList.add('mobile-layout');
                container.classList.remove('desktop-layout');
            } else {
                container.classList.add('desktop-layout');
                container.classList.remove('mobile-layout');
            }
        });
    }

    startBarcodeScanner() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            this.showAlert('Tu dispositivo no soporta acceso a la cámara', 'warning');
            return;
        }

        // Crear modal para el escáner
        const modal = this.createScannerModal();
        document.body.appendChild(modal);

        // Mostrar modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // Inicializar escáner
        this.initQuaggaScanner();
    }

    createScannerModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'barcode-scanner-modal';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-barcode"></i> Escanear Código de Barras
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="scanner-container">
                            <div id="scanner-viewport" class="scanner-viewport">
                                <video id="scanner-video" autoplay muted playsinline></video>
                                <div class="scanner-overlay">
                                    <div class="scanner-line"></div>
                                </div>
                            </div>
                            <div class="scanner-instructions">
                                <p><i class="fas fa-info-circle"></i> Apunta la cámara hacia el código de barras</p>
                                <p class="text-muted">Mantén el código dentro del área de escaneo</p>
                            </div>
                        </div>
                        <div id="scanner-result" class="scanner-result d-none">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <strong>Código detectado:</strong> <span id="detected-code"></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" id="use-detected-code" disabled>
                            <i class="fas fa-search"></i> Buscar Equipo
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Limpiar escáner al cerrar modal
        modal.addEventListener('hidden.bs.modal', () => {
            this.stopScanner();
            modal.remove();
        });

        return modal;
    }

    initQuaggaScanner() {
        const config = {
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#scanner-viewport'),
                constraints: {
                    width: this.isMobile ? 320 : 640,
                    height: this.isMobile ? 240 : 480,
                    facingMode: this.isMobile ? "environment" : "user"
                }
            },
            decoder: {
                readers: [
                    "code_128_reader",
                    "ean_reader",
                    "ean_8_reader",
                    "code_39_reader",
                    "code_39_vin_reader",
                    "codabar_reader",
                    "upc_reader",
                    "upc_e_reader",
                    "i2of5_reader"
                ]
            },
            locate: true,
            locator: {
                patchSize: this.isMobile ? "small" : "medium",
                halfSample: true
            }
        };

        Quagga.init(config, (err) => {
            if (err) {
                console.error('Error inicializando escáner:', err);
                this.showAlert('Error al inicializar el escáner de códigos', 'error');
                return;
            }
            Quagga.start();
        });

        // Manejar códigos detectados
        Quagga.onDetected((result) => {
            const code = result.codeResult.code;
            this.handleDetectedCode(code);
        });
    }

    handleDetectedCode(code) {
        document.getElementById('detected-code').textContent = code;
        document.getElementById('scanner-result').classList.remove('d-none');
        document.getElementById('use-detected-code').disabled = false;

        // Auto-buscar después de 2 segundos
        setTimeout(() => {
            this.searchEquipoByCode(code);
        }, 2000);
    }

    stopScanner() {
        if (Quagga) {
            Quagga.stop();
        }
    }

    generateQRCode(equipoId, tipoTabla) {
        // Obtener datos del equipo
        fetch(`api/equipos/get-equipo-data.php?id=${equipoId}&tipo=${tipoTabla}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showQRModal(data.equipo);
                } else {
                    this.showAlert('Error obteniendo datos del equipo', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showAlert('Error de conexión', 'error');
            });
    }

    showQRModal(equipo) {
        const modal = this.createQRModal(equipo);
        document.body.appendChild(modal);

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // Generar QR
        this.renderQRCode(equipo);

        // Limpiar al cerrar
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    createQRModal(equipo) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'qr-modal';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-qrcode"></i> Código QR - ${equipo.codigo_barras_individual || equipo.codigo_barras_unificado}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="qr-container text-center">
                                    <div id="qr-code-display"></div>
                                    <p class="mt-2 text-muted">Escanea para ver detalles</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="equipo-info">
                                    <h6><i class="fas fa-info-circle"></i> Información del Equipo</h6>
                                    <div class="info-item">
                                        <strong>Código:</strong> ${equipo.codigo_barras_individual || equipo.codigo_barras_unificado}
                                    </div>
                                    <div class="info-item">
                                        <strong>Tipo:</strong> ${equipo.tipo_equipo}
                                    </div>
                                    <div class="info-item">
                                        <strong>Marca:</strong> ${equipo.marca || 'N/A'}
                                    </div>
                                    <div class="info-item">
                                        <strong>Modelo:</strong> ${equipo.modelo || 'N/A'}
                                    </div>
                                    <div class="info-item">
                                        <strong>Ubicación:</strong> ${equipo.ubicacion || 'N/A'}
                                    </div>
                                    <div class="info-item">
                                        <strong>Estado:</strong> 
                                        <span class="badge bg-${this.getStatusColor(equipo.estado)}">${equipo.estado}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cerrar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimir QR
                        </button>
                        <button type="button" class="btn btn-success" onclick="barcodeQRManager.downloadQR('${equipo.codigo_barras_individual || equipo.codigo_barras_unificado}')">
                            <i class="fas fa-download"></i> Descargar
                        </button>
                    </div>
                </div>
            </div>
        `;
        return modal;
    }

    renderQRCode(equipo) {
        const qrData = {
            codigo: equipo.codigo_barras_individual || equipo.codigo_barras_unificado,
            tipo: equipo.tipo_equipo,
            marca: equipo.marca || '',
            modelo: equipo.modelo || '',
            ubicacion: equipo.ubicacion || '',
            estado: equipo.estado,
            url: `${window.location.origin}/modules/sistemas/equipos.php?codigo=${equipo.codigo_barras_individual || equipo.codigo_barras_unificado}`
        };

        QRCode.toCanvas(document.getElementById('qr-code-display'), JSON.stringify(qrData), {
            width: this.isMobile ? 200 : 300,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#FFFFFF'
            }
        });
    }

    downloadQR(codigo) {
        const canvas = document.querySelector('#qr-code-display canvas');
        if (canvas) {
            const link = document.createElement('a');
            link.download = `QR_${codigo}.png`;
            link.href = canvas.toDataURL();
            link.click();
        }
    }

    printLabel(equipoId, tipoTabla) {
        // Abrir ventana de impresión de etiqueta
        const printWindow = window.open(`modules/sistemas/print-label.php?id=${equipoId}&tipo=${tipoTabla}`, '_blank', 'width=800,height=600');

        // Marcar como impresa cuando se cierre la ventana
        printWindow.addEventListener('beforeunload', () => {
            this.markLabelAsPrinted(equipoId, tipoTabla);
        });
    }

    markLabelAsPrinted(equipoId, tipoTabla) {
        fetch('api/equipos/mark-label-printed.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                equipo_id: equipoId,
                tipo_tabla: tipoTabla
            })
        });
    }

    searchByBarcode() {
        const input = document.getElementById('barcode-search-input');
        const codigo = input.value.trim();

        if (!codigo) {
            this.showAlert('Ingresa un código para buscar', 'warning');
            return;
        }

        this.searchEquipoByCode(codigo);
    }

    searchEquipoByCode(codigo) {
        // Mostrar loading
        this.showLoading(true);

        fetch(`api/equipos/search-by-code.php?codigo=${encodeURIComponent(codigo)}`)
            .then(response => response.json())
            .then(data => {
                this.showLoading(false);

                if (data.success && data.equipo) {
                    this.showEquipoDetails(data.equipo);
                } else {
                    this.showAlert('No se encontró ningún equipo con ese código', 'warning');
                }
            })
            .catch(error => {
                this.showLoading(false);
                console.error('Error:', error);
                this.showAlert('Error de conexión', 'error');
            });
    }

    showEquipoDetails(equipo) {
        // Crear modal con detalles del equipo
        const modal = this.createEquipoDetailsModal(equipo);
        document.body.appendChild(modal);

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    createEquipoDetailsModal(equipo) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-desktop"></i> ${equipo.codigo_barras_individual || equipo.codigo_barras_unificado}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${this.renderEquipoDetailsContent(equipo)}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cerrar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="barcodeQRManager.generateQRCode('${equipo.id}', '${equipo.tipo_tabla}')">
                            <i class="fas fa-qrcode"></i> Generar QR
                        </button>
                        <button type="button" class="btn btn-success" onclick="barcodeQRManager.printLabel('${equipo.id}', '${equipo.tipo_tabla}')">
                            <i class="fas fa-print"></i> Imprimir Etiqueta
                        </button>
                    </div>
                </div>
            </div>
        `;
        return modal;
    }

    renderEquipoDetailsContent(equipo) {
        return `
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-info-circle"></i> Información General</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-group">
                                        <label>Código de Barras:</label>
                                        <div class="barcode-display">${equipo.codigo_barras_individual || equipo.codigo_barras_unificado}</div>
                                    </div>
                                    <div class="info-group">
                                        <label>Tipo de Equipo:</label>
                                        <span>${equipo.tipo_equipo}</span>
                                    </div>
                                    <div class="info-group">
                                        <label>Marca:</label>
                                        <span>${equipo.marca || 'N/A'}</span>
                                    </div>
                                    <div class="info-group">
                                        <label>Modelo:</label>
                                        <span>${equipo.modelo || 'N/A'}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-group">
                                        <label>Serial:</label>
                                        <span>${equipo.serial || 'N/A'}</span>
                                    </div>
                                    <div class="info-group">
                                        <label>Estado:</label>
                                        <span class="badge bg-${this.getStatusColor(equipo.estado)}">${equipo.estado}</span>
                                    </div>
                                    <div class="info-group">
                                        <label>Sede:</label>
                                        <span>${equipo.sede_nombre || 'N/A'}</span>
                                    </div>
                                    <div class="info-group">
                                        <label>Ubicación:</label>
                                        <span>${equipo.ubicacion || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-qrcode"></i> Código QR</h6>
                        </div>
                        <div class="card-body text-center">
                            <div id="mini-qr-${equipo.id}"></div>
                            <small class="text-muted">Escanea para acceso rápido</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    getStatusColor(estado) {
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

    handleBarcodeInput(value) {
        // Validar formato mientras se escribe
        const isValid = /^[A-Z]{3}-(AGRU-)?[A-Z]{3,5}-\d{3}$/.test(value);
        const input = document.getElementById('barcode-search-input');

        if (value && !isValid) {
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
        }
    }

    showAlert(message, type = 'info') {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';

        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(alert);

        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    showLoading(show) {
        let loader = document.getElementById('barcode-loader');

        if (show && !loader) {
            loader = document.createElement('div');
            loader.id = 'barcode-loader';
            loader.className = 'position-fixed d-flex align-items-center justify-content-center';
            loader.style.cssText = 'top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;';
            loader.innerHTML = `
                <div class="spinner-border text-light" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            `;
            document.body.appendChild(loader);
        } else if (!show && loader) {
            loader.remove();
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.barcodeQRManager = new BarcodeQRManager();
});