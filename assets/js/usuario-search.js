/**
 * WORKMANAGER ERP - SISTEMA DE BÚSQUEDA DE USUARIOS
 * Archivo: assets/js/usuario-search.js
 * Descripción: JavaScript para búsqueda inteligente de usuarios con creación automática
 * 
 * Desarrollado por: Anderson Ayala Vera
 * Email: sistemas2@integralips.com.co
 * Versión: 2.0
 * Fecha: 24 de Diciembre de 2025
 */

class UsuarioSearch {
    constructor(inputElement, options = {}) {
        this.input = typeof inputElement === 'string' ? document.getElementById(inputElement) : inputElement;
        this.options = {
            placeholder: 'Buscar por cédula, nombre o email...',
            minLength: 2,
            delay: 300,
            showCreateOption: true,
            onSelect: null,
            onCreate: null,
            apiUrl: '/api/buscar-usuario.php',
            ...options
        };

        this.selectedUser = null;
        this.searchTimeout = null;
        this.isLoading = false;

        this.init();
    }

    init() {
        if (!this.input) {
            console.error('UsuarioSearch: Input element not found');
            return;
        }

        this.setupInput();
        this.createDropdown();
        this.bindEvents();
    }

    setupInput() {
        this.input.setAttribute('autocomplete', 'off');
        this.input.setAttribute('placeholder', this.options.placeholder);
        this.input.classList.add('usuario-search-input');

        // Crear contenedor si no existe
        if (!this.input.parentElement.classList.contains('usuario-search-container')) {
            const container = document.createElement('div');
            container.className = 'usuario-search-container';
            this.input.parentElement.insertBefore(container, this.input);
            container.appendChild(this.input);
        }

        this.container = this.input.parentElement;
    }

    createDropdown() {
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'usuario-search-dropdown';
        this.dropdown.style.display = 'none';
        this.container.appendChild(this.dropdown);
    }

    bindEvents() {
        // Búsqueda en tiempo real
        this.input.addEventListener('input', (e) => {
            const query = e.target.value.trim();

            if (query.length >= this.options.minLength) {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.search(query);
                }, this.options.delay);
            } else {
                this.hideDropdown();
            }
        });

        // Navegación con teclado
        this.input.addEventListener('keydown', (e) => {
            if (this.dropdown.style.display === 'block') {
                const items = this.dropdown.querySelectorAll('.dropdown-item');
                const activeItem = this.dropdown.querySelector('.dropdown-item.active');
                let activeIndex = Array.from(items).indexOf(activeItem);

                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        activeIndex = Math.min(activeIndex + 1, items.length - 1);
                        this.setActiveItem(items[activeIndex]);
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        activeIndex = Math.max(activeIndex - 1, 0);
                        this.setActiveItem(items[activeIndex]);
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (activeItem) {
                            activeItem.click();
                        }
                        break;
                    case 'Escape':
                        this.hideDropdown();
                        break;
                }
            }
        });

        // Ocultar dropdown al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.hideDropdown();
            }
        });

        // Limpiar selección al cambiar el input manualmente
        this.input.addEventListener('input', () => {
            if (this.selectedUser && this.input.value !== this.selectedUser.nombre_completo) {
                this.selectedUser = null;
                this.triggerChange();
            }
        });
    }

    async search(query) {
        if (this.isLoading) return;

        this.isLoading = true;
        this.showLoading();

        try {
            const response = await fetch(`${this.options.apiUrl}?action=search&q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success) {
                this.showResults(data.usuarios, data.suggestions, query);
            } else {
                this.showError(data.error);
            }
        } catch (error) {
            console.error('Error en búsqueda:', error);
            this.showError('Error de conexión');
        } finally {
            this.isLoading = false;
        }
    }

    showLoading() {
        this.dropdown.innerHTML = '<div class="dropdown-loading">Buscando...</div>';
        this.dropdown.style.display = 'block';
    }

    showResults(usuarios, suggestions, query) {
        this.dropdown.innerHTML = '';

        if (usuarios.length > 0) {
            // Mostrar usuarios encontrados
            const section = document.createElement('div');
            section.className = 'dropdown-section';
            section.innerHTML = '<div class="dropdown-header">Usuarios encontrados</div>';

            usuarios.forEach(usuario => {
                const item = this.createUserItem(usuario);
                section.appendChild(item);
            });

            this.dropdown.appendChild(section);
        }

        if (suggestions.length > 0) {
            // Mostrar sugerencias
            const section = document.createElement('div');
            section.className = 'dropdown-section';
            section.innerHTML = '<div class="dropdown-header">Sugerencias</div>';

            suggestions.forEach(suggestion => {
                const item = this.createSuggestionItem(suggestion, query);
                section.appendChild(item);
            });

            this.dropdown.appendChild(section);
        }

        if (usuarios.length === 0 && suggestions.length === 0) {
            this.showNoResults(query);
        }

        this.dropdown.style.display = 'block';
    }

    createUserItem(usuario) {
        const item = document.createElement('div');
        item.className = 'dropdown-item user-item';
        item.innerHTML = `
            <div class="user-info">
                <div class="user-name">${this.escapeHtml(usuario.nombre_completo)}</div>
                <div class="user-details">
                    <span class="user-cedula">CC: ${this.escapeHtml(usuario.cedula)}</span>
                    ${usuario.email ? `<span class="user-email">${this.escapeHtml(usuario.email)}</span>` : ''}
                    ${usuario.cargo ? `<span class="user-cargo">${this.escapeHtml(usuario.cargo)}</span>` : ''}
                </div>
                ${usuario.sede_nombre ? `<div class="user-sede">${this.escapeHtml(usuario.sede_nombre)}</div>` : ''}
            </div>
        `;

        item.addEventListener('click', () => {
            this.selectUser(usuario);
        });

        return item;
    }

    createSuggestionItem(suggestion, query) {
        const item = document.createElement('div');
        item.className = 'dropdown-item suggestion-item';

        if (suggestion.type === 'create') {
            item.innerHTML = `
                <div class="suggestion-info">
                    <i class="fas fa-plus-circle"></i>
                    <span>${this.escapeHtml(suggestion.text)}</span>
                </div>
            `;

            item.addEventListener('click', () => {
                this.showCreateForm(suggestion.cedula);
            });
        } else {
            item.innerHTML = `
                <div class="suggestion-info">
                    <i class="fas fa-search"></i>
                    <span>${this.escapeHtml(suggestion.text)}</span>
                </div>
            `;

            item.addEventListener('click', () => {
                this.input.value = suggestion.cedula;
                this.search(suggestion.cedula);
            });
        }

        return item;
    }

    showNoResults(query) {
        let html = '<div class="dropdown-no-results">No se encontraron usuarios</div>';

        if (this.options.showCreateOption && /^\d{6,12}$/.test(query)) {
            html += `
                <div class="dropdown-item suggestion-item" onclick="this.parentElement.usuarioSearch.showCreateForm('${query}')">
                    <div class="suggestion-info">
                        <i class="fas fa-plus-circle"></i>
                        <span>Crear usuario con cédula: ${query}</span>
                    </div>
                </div>
            `;
        }

        this.dropdown.innerHTML = html;
        this.dropdown.usuarioSearch = this; // Para acceder desde onclick
        this.dropdown.style.display = 'block';
    }

    showError(error) {
        this.dropdown.innerHTML = `<div class="dropdown-error">Error: ${this.escapeHtml(error)}</div>`;
        this.dropdown.style.display = 'block';
    }

    hideDropdown() {
        this.dropdown.style.display = 'none';
    }

    selectUser(usuario) {
        this.selectedUser = usuario;
        this.input.value = usuario.nombre_completo;
        this.hideDropdown();
        this.triggerChange();

        if (this.options.onSelect) {
            this.options.onSelect(usuario);
        }
    }

    showCreateForm(cedula = '') {
        const modal = this.createModal();
        const form = this.createCreateForm(cedula);
        modal.querySelector('.modal-body').appendChild(form);
        document.body.appendChild(modal);

        // Mostrar modal
        setTimeout(() => modal.classList.add('show'), 10);

        // Focus en primer campo
        const firstInput = form.querySelector('input');
        if (firstInput) firstInput.focus();
    }

    createModal() {
        const modal = document.createElement('div');
        modal.className = 'usuario-modal';
        modal.innerHTML = `
            <div class="modal-backdrop"></div>
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Crear Nuevo Usuario</h5>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body"></div>
                </div>
            </div>
        `;

        // Cerrar modal
        modal.querySelector('.modal-close').addEventListener('click', () => {
            this.closeModal(modal);
        });

        modal.querySelector('.modal-backdrop').addEventListener('click', () => {
            this.closeModal(modal);
        });

        return modal;
    }

    createCreateForm(cedula) {
        const form = document.createElement('form');
        form.className = 'usuario-create-form';
        form.innerHTML = `
            <div class="form-row">
                <div class="form-group">
                    <label>Cédula *</label>
                    <input type="text" name="cedula" value="${this.escapeHtml(cedula)}" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>Apellido</label>
                    <input type="text" name="apellido">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Cargo</label>
                    <input type="text" name="cargo">
                </div>
                <div class="form-group">
                    <label>Departamento</label>
                    <input type="text" name="departamento">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.usuario-modal').remove()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Usuario</button>
            </div>
        `;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.createUser(form);
        });

        return form;
    }

    async createUser(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Creando...';
        submitBtn.disabled = true;

        try {
            const response = await fetch(this.options.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'create', ...data })
            });

            const result = await response.json();

            if (result.success) {
                this.selectUser(result.usuario);
                this.closeModal(form.closest('.usuario-modal'));

                if (this.options.onCreate) {
                    this.options.onCreate(result.usuario);
                }

                this.showNotification('Usuario creado exitosamente', 'success');
            } else {
                this.showNotification(result.error, 'error');
            }
        } catch (error) {
            console.error('Error creando usuario:', error);
            this.showNotification('Error de conexión', 'error');
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }

    closeModal(modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }

    setActiveItem(item) {
        // Remover active de todos los items
        this.dropdown.querySelectorAll('.dropdown-item').forEach(i => {
            i.classList.remove('active');
        });

        // Agregar active al item seleccionado
        if (item) {
            item.classList.add('active');
            item.scrollIntoView({ block: 'nearest' });
        }
    }

    triggerChange() {
        const event = new CustomEvent('usuarioChange', {
            detail: { usuario: this.selectedUser }
        });
        this.input.dispatchEvent(event);
    }

    showNotification(message, type = 'info') {
        // Crear notificación simple
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => notification.classList.add('show'), 10);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Métodos públicos
    getSelectedUser() {
        return this.selectedUser;
    }

    setSelectedUser(usuario) {
        this.selectedUser = usuario;
        this.input.value = usuario ? usuario.nombre_completo : '';
        this.triggerChange();
    }

    clear() {
        this.selectedUser = null;
        this.input.value = '';
        this.hideDropdown();
        this.triggerChange();
    }

    focus() {
        this.input.focus();
    }
}

// Función helper para inicializar búsqueda de usuarios
function initUsuarioSearch(selector, options = {}) {
    const elements = typeof selector === 'string' ? document.querySelectorAll(selector) : [selector];
    const instances = [];

    elements.forEach(element => {
        if (element && !element.usuarioSearch) {
            const instance = new UsuarioSearch(element, options);
            element.usuarioSearch = instance;
            instances.push(instance);
        }
    });

    return instances.length === 1 ? instances[0] : instances;
}

// Exportar para uso global
window.UsuarioSearch = UsuarioSearch;
window.initUsuarioSearch = initUsuarioSearch;