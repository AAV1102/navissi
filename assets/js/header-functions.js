// Header Button Functions for WorkManager ERP
// These functions power the header buttons: notifications, language, accessibility, AI

// Notifications
function loadNotifications() {
    fetch('api/notifications/get.php')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('notificationsContainer');
            const badge = document.getElementById('notificationBadge');
            const count = document.getElementById('notifCount');

            if (data.success && data.notifications && data.notifications.length > 0) {
                container.innerHTML = data.notifications.map(n => `
                    <div class="p-3 border-bottom notification-item" onclick="markAsRead(${n.id})">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas ${n.icon || 'fa-bell'} text-${n.type || 'primary'}"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1 small fw-bold">${n.title}</h6>
                                <p class="mb-1 small text-muted">${n.message}</p>
                                <small class="text-muted">${n.time}</small>
                            </div>
                        </div>
                    </div>
                `).join('');
                count.textContent = data.notifications.length;
                badge.style.display = 'block';
            } else {
                container.innerHTML = `
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-bell-slash d-block mb-2 opacity-25"></i>
                        <small>No hay notificaciones</small>
                    </div>
                `;
                count.textContent = '0';
                badge.style.display = 'none';
            }
        })
        .catch(e => console.warn('Error loading notifications:', e));
}

function markAsRead(id) {
    fetch('api/notifications/mark-read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).then(() => loadNotifications());
}

// Language Selector
function changeLanguage(lang) {
    fetch('dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=change_language&language=${lang}`
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(`Idioma cambiado a ${lang.toUpperCase()}`, 'success');
                setTimeout(() => location.reload(), 1000);
            }
        })
        .catch(e => console.warn('Error changing language:', e));
}

// Accessibility Toggle
let accessibilityMode = false;
function toggleAccessibility() {
    accessibilityMode = !accessibilityMode;
    const body = document.body;

    if (accessibilityMode) {
        body.classList.add('accessibility-mode');
        body.style.fontSize = '1.2em';
        body.style.lineHeight = '1.8';
        showToast('Modo accesibilidad activado', 'info');
    } else {
        body.classList.remove('accessibility-mode');
        body.style.fontSize = '';
        body.style.lineHeight = '';
        showToast('Modo accesibilidad desactivado', 'info');
    }
}

// AI Panel Toggle
let aiPanelOpen = false;
function toggleAIPanel() {
    aiPanelOpen = !aiPanelOpen;
    let panel = document.getElementById('aiPanel');

    if (!panel) {
        // Create AI Panel if it doesn't exist
        panel = document.createElement('div');
        panel.id = 'aiPanel';
        panel.className = 'ai-panel';
        panel.innerHTML = `
            <div class="ai-panel-header">
                <h5><i class="fas fa-robot me-2"></i>Asistente IA</h5>
                <button class="btn-close" onclick="toggleAIPanel()"></button>
            </div>
            <div class="ai-panel-body">
                <div id="aiChat" class="ai-chat-messages"></div>
                <div class="ai-input-container">
                    <input type="text" class="form-control" id="aiInput" placeholder="Pregunta algo...">
                    <button class="btn btn-primary" onclick="sendAIMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(panel);
    }

    if (aiPanelOpen) {
        panel.classList.add('open');
    } else {
        panel.classList.remove('open');
    }
}

function sendAIMessage() {
    const input = document.getElementById('aiInput');
    const message = input.value.trim();

    if (!message) return;

    const chatDiv = document.getElementById('aiChat');
    chatDiv.innerHTML += `<div class="ai-message user">${message}</div>`;
    input.value = '';

    // Simulate AI response
    setTimeout(() => {
        chatDiv.innerHTML += `<div class="ai-message bot">Estoy aquí para ayudarte. Esta función está en desarrollo.</div>`;
        chatDiv.scrollTop = chatDiv.scrollHeight;
    }, 500);
}

// Toast Notification System
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    setTimeout(() => toast.remove(), 5000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

console.log('✅ Header functions loaded: notifications, language, accessibility, AI panel');
