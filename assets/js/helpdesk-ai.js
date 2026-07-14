/**
 * JavaScript para Mesa de Ayuda Inteligente
 * Maneja análisis de IA, subida de archivos y generación de soluciones
 */

class HelpdeskAI {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupFileUpload();
        this.loadRecentTickets();
    }

    init() {
        // Los endpoints reales viven en la raíz del proyecto; este script también
        // se carga desde /modules, por eso resolvemos la ruta según la página.
        this.apiBase = window.location.pathname.includes('/modules/') ? '../' : './';
        this.uploadedFiles = [];
        this.currentTicketId = null;
        this.analysisInProgress = false;
    }

    setupEventListeners() {
        // Formulario principal
        const form = document.getElementById('ai-ticket-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitTicket();
            });
        }

        // Botones de acción
        document.getElementById('mark-resolved')?.addEventListener('click', () => {
            this.markAsResolved();
        });

        document.getElementById('escalate-ticket')?.addEventListener('click', () => {
            this.escalateToHuman();
        });

        document.getElementById('add-to-knowledge')?.addEventListener('click', () => {
            this.addToKnowledgeBase();
        });

        // Actualización en tiempo real de estadísticas
        this.startStatsUpdates();
    }

    setupFileUpload() {
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');
        const filePreview = document.getElementById('file-preview');

        if (!uploadArea || !fileInput) return;

        // Click para seleccionar archivos
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');

            const files = Array.from(e.dataTransfer.files);
            this.handleFiles(files);
        });

        // Selección de archivos
        fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            this.handleFiles(files);
        });
    }

    handleFiles(files) {
        const allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const maxSize = 50 * 1024 * 1024; // 50MB

        files.forEach(file => {
            if (!allowedTypes.includes(file.type)) {
                this.showNotification(`Tipo de archivo no soportado: ${file.name}`, 'error');
                return;
            }

            if (file.size > maxSize) {
                this.showNotification(`Archivo muy grande: ${file.name}`, 'error');
                return;
            }

            this.uploadedFiles.push(file);
            this.addFilePreview(file);
        });
    }

    addFilePreview(file) {
        const preview = document.getElementById('file-preview');
        const fileDiv = document.createElement('div');
        fileDiv.className = 'file-preview-item d-inline-block m-2';

        let icon = 'fas fa-file';
        if (file.type.startsWith('image/')) icon = 'fas fa-image';
        else if (file.type.startsWith('video/')) icon = 'fas fa-video';
        else if (file.type.includes('pdf')) icon = 'fas fa-file-pdf';

        fileDiv.innerHTML = `
            <div class="text-center p-2 border rounded">
                <i class="${icon} fa-2x text-primary mb-2"></i>
                <div class="small">${file.name}</div>
                <div class="small text-muted">${this.formatFileSize(file.size)}</div>
                <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        preview.appendChild(fileDiv);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    async submitTicket() {
        if (this.analysisInProgress) return;

        const formData = new FormData();

        // Datos del formulario
        formData.append('reporter_name', document.getElementById('reporter-name').value);
        formData.append('department', document.getElementById('department').value);
        formData.append('problem_description', document.getElementById('problem-description').value);
        formData.append('priority', document.getElementById('priority').value);

        // Archivos adjuntos
        this.uploadedFiles.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        try {
            this.analysisInProgress = true;
            this.showAnalysisPanel();

            // Enviar datos
            const response = await fetch(`${this.apiBase}api_reportar_problema.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    reporter_name: document.getElementById('reporter-name')?.value || '',
                    reporter_email: document.getElementById('reporter-email')?.value || '',
                    descripcion: document.getElementById('problem-description')?.value || '',
                    prioridad: document.getElementById('priority')?.value || 'MEDIA',
                    usuario_windows: document.getElementById('reporter-name')?.value || ''
                })
            });

            const result = await response.json();

            if (result.ok) {
                this.currentTicketId = result.ticket_id;
                this.displayResults({
                    ticket_id: result.ticket_id,
                    diagnosis: {category: result.categoria || 'SOPORTE', problem_type: result.estado || 'ABIERTO', confidence: result.resuelto ? 100 : 0, severity: 'MEDIA'},
                    solution: {steps: result.mensaje ? [{title: 'Resultado', description: result.mensaje, estimated_time: ''}] : [], estimated_total_time: '', difficulty: ''},
                    assigned_technician: result.asignado_a || 'Mesa de Ayuda (pendiente de asignación)'
                });
                this.showResults();
            } else {
                throw new Error(result.error || 'Error procesando ticket');
            }

        } catch (error) {
            this.showNotification('Error enviando ticket: ' + error.message, 'error');
            this.hideAnalysisPanel();
        } finally {
            this.analysisInProgress = false;
        }
    }

    showAnalysisPanel() {
        const panel = document.getElementById('ai-analysis-panel');
        if (panel) {
            panel.style.display = 'block';
            this.startAnalysisAnimation();
        }
    }

    hideAnalysisPanel() {
        const panel = document.getElementById('ai-analysis-panel');
        if (panel) {
            panel.style.display = 'none';
        }
    }

    startAnalysisAnimation() {
        const progressBar = document.getElementById('analysis-progress');
        if (progressBar) progressBar.style.width = '100%';
        const status = document.getElementById('ai-analysis-status');
        if (status) status.textContent = 'Procesando en NAVISSI…';
    }

    async processAIAnalysis(ticketData) {
        try {
            // El análisis real lo ejecuta api_reportar_problema.php mediante
            // ia_triage_ticket(). No se generan diagnósticos ficticios en el cliente.
            if (!ticketData || !ticketData.ok) throw new Error('Respuesta inválida del servidor');
            return;

        } catch (error) {
            console.error('Error en análisis IA:', error);
            this.showNotification('Error en análisis de IA', 'error');
        }
    }

    showResults() {
        document.getElementById('ai-analysis-panel').style.display = 'none';
        document.getElementById('ai-results-panel').style.display = 'block';
    }

    displayResults(results) {
        // Mostrar diagnóstico
        const diagnosisDiv = document.getElementById('ai-diagnosis');
        if (diagnosisDiv) {
            diagnosisDiv.innerHTML = `
                <div class="alert alert-info">
                    <h6><i class="fas fa-stethoscope me-2"></i>Diagnóstico de IA</h6>
                    <p><strong>Categoría:</strong> ${results.diagnosis.category}</p>
                    <p><strong>Tipo de Problema:</strong> ${results.diagnosis.problem_type}</p>
                    <p><strong>Severidad:</strong> ${results.diagnosis.severity}</p>
                    <p><strong>Confianza:</strong> <span class="badge bg-success">${results.diagnosis.confidence}%</span></p>
                </div>
            `;
        }

        // Mostrar solución
        const solutionDiv = document.getElementById('ai-solution');
        if (solutionDiv) {
            let stepsHTML = '<h6><i class="fas fa-list-ol me-2"></i>Solución Paso a Paso</h6>';

            results.solution.steps.forEach((step, index) => {
                stepsHTML += `
                    <div class="solution-step">
                        <h6>Paso ${index + 1}: ${step.title}</h6>
                        <p>${step.description}</p>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>Tiempo estimado: ${step.estimated_time}
                        </small>
                    </div>
                `;
            });

            stepsHTML += `
                <div class="alert alert-success mt-3">
                    <strong>Tiempo total estimado:</strong> ${results.solution.estimated_total_time} | 
                    <strong>Dificultad:</strong> ${results.solution.difficulty}
                </div>
            `;

            solutionDiv.innerHTML = stepsHTML;
        }

        // Actualizar información del ticket
        document.getElementById('ticket-id').textContent = results.ticket_id;
        document.getElementById('ai-confidence').textContent = results.diagnosis.confidence + '%';
        document.getElementById('problem-category').textContent = results.diagnosis.category;
        document.getElementById('assigned-tech').textContent = results.assigned_technician;
    }

    async markAsResolved() {
        if (!this.currentTicketId) return;

        try {
            const response = await fetch(`${this.apiBase}modules/ticket_detalle.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ticket_id: this.currentTicketId,
                    resolution_method: 'ai_solution'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Ticket marcado como resuelto', 'success');
                this.resetForm();
                this.loadRecentTickets();
            } else {
                throw new Error(result.error);
            }

        } catch (error) {
            this.showNotification('Error marcando ticket como resuelto: ' + error.message, 'error');
        }
    }

    async escalateToHuman() {
        if (!this.currentTicketId) return;

        try {
            const response = await fetch(`${this.apiBase}modules/ticket_detalle.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ticket_id: this.currentTicketId,
                    reason: 'user_request'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Ticket escalado a técnico humano', 'info');
                this.resetForm();
                this.loadRecentTickets();
            } else {
                throw new Error(result.error);
            }

        } catch (error) {
            this.showNotification('Error escalando ticket: ' + error.message, 'error');
        }
    }

    async addToKnowledgeBase() {
        if (!this.currentTicketId) return;

        try {
            this.showNotification('La base de conocimiento se administra desde el detalle del ticket.', 'info');
            return;
            /*const response = await fetch(`${this.apiBase}knowledge-add.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ticket_id: this.currentTicketId,
                    add_to_knowledge: true
                })
            });*/

            const result = await response.json();

            if (result.success) {
                this.showNotification('Solución agregada a la base de conocimiento', 'success');
            } else {
                throw new Error(result.error);
            }

        } catch (error) {
            this.showNotification('Error agregando a base de conocimiento: ' + error.message, 'error');
        }
    }

    async loadRecentTickets() {
        try {
            // Este panel es opcional; el listado oficial está en Mesa de Ayuda.
            const response = await fetch(`${this.apiBase}modules/mesa_ayuda.php`, {headers: {'Accept': 'application/json'}});
            const result = await response.json();

            if (result.success) {
                this.displayRecentTickets(result.tickets);
            }

        } catch (error) {
            console.error('Error cargando tickets:', error);
        }
    }

    displayRecentTickets(tickets) {
        const tbody = document.getElementById('tickets-tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        tickets.forEach(ticket => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${ticket.id}</td>
                <td>${ticket.reporter_name}</td>
                <td>
                    <div class="text-truncate" style="max-width: 200px;" title="${ticket.problem_description}">
                        ${ticket.problem_description}
                    </div>
                </td>
                <td>
                    <span class="badge ${this.getConfidenceBadgeClass(ticket.ai_confidence)}">
                        ${ticket.ai_confidence}%
                    </span>
                </td>
                <td>
                    <span class="badge ${this.getStatusBadgeClass(ticket.status)}">
                        ${this.getStatusText(ticket.status)}
                    </span>
                </td>
                <td>${ticket.assigned_technician || 'No asignado'}</td>
                <td>${this.formatDate(ticket.created_at)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="helpdeskAI.viewTicket('${ticket.id}')">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    getConfidenceBadgeClass(confidence) {
        if (confidence >= 90) return 'bg-success';
        if (confidence >= 70) return 'bg-warning';
        return 'bg-danger';
    }

    getStatusBadgeClass(status) {
        const classes = {
            'open': 'bg-primary',
            'in_progress': 'bg-warning',
            'resolved': 'bg-success',
            'escalated': 'bg-info',
            'closed': 'bg-secondary'
        };
        return classes[status] || 'bg-secondary';
    }

    getStatusText(status) {
        const texts = {
            'open': 'Abierto',
            'in_progress': 'En Progreso',
            'resolved': 'Resuelto',
            'escalated': 'Escalado',
            'closed': 'Cerrado'
        };
        return texts[status] || status;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    viewTicket(ticketId) {
        // Implementar vista detallada del ticket
        console.log('Ver ticket:', ticketId);
    }

    resetForm() {
        document.getElementById('ai-ticket-form').reset();
        document.getElementById('file-preview').innerHTML = '';
        document.getElementById('ai-analysis-panel').style.display = 'none';
        document.getElementById('ai-results-panel').style.display = 'none';
        this.uploadedFiles = [];
        this.currentTicketId = null;
    }

    startStatsUpdates() {
        // Actualizar estadísticas cada 30 segundos
        setInterval(() => {
            this.updateStats();
        }, 30000);
    }

    async updateStats() {
        try {
            const response = await fetch(`${this.apiBase}helpdesk-stats.php`);
            const result = await response.json();

            if (result.success) {
                // Actualizar estadísticas en la interfaz
                // Implementar según necesidades específicas
            }

        } catch (error) {
            console.error('Error actualizando estadísticas:', error);
        }
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    showNotification(message, type = 'info') {
        // Reutilizar función de notificaciones del sistema principal
        if (window.aiCoreSystem) {
            window.aiCoreSystem.showNotification(message, type);
        } else {
            alert(message); // Fallback
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.helpdeskAI = new HelpdeskAI();
});
