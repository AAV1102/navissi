/**
 * WorkManager ERP - JavaScript para Documentación
 * Funcionalidades para gestión y visualización de documentos
 */

// Variables globales
let currentTheme = localStorage.getItem('doc-theme') || 'light';
let searchTimeout;

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function () {
    // Aplicar tema guardado
    applyTheme(currentTheme);

    // Configurar búsqueda en tiempo real
    const searchInput = document.getElementById('searchDocs');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                buscarDocumentacion();
            }, 300);
        });

        // Búsqueda con Enter
        searchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                buscarDocumentacion();
            }
        });
    }

    // Generar tabla de contenidos si estamos en una página de documento
    if (document.querySelector('.doc-article')) {
        generateTableOfContents();
    }
});

// Filtrar por categoría
function filtrarCategoria(categoria) {
    const url = new URL(window.location);
    if (categoria) {
        url.searchParams.set('categoria', categoria);
    } else {
        url.searchParams.delete('categoria');
    }
    window.location.href = url.toString();
}

// Buscar documentación
function buscarDocumentacion() {
    const query = document.getElementById('searchDocs').value.trim();

    if (query.length < 2) {
        // Si la búsqueda es muy corta, mostrar todos los documentos
        location.reload();
        return;
    }

    fetch(`documentacion.php?action=buscar&q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarResultadosBusqueda(data.documentos, query);
            } else {
                showNotification('Error en la búsqueda', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// Mostrar resultados de búsqueda
function mostrarResultadosBusqueda(documentos, query) {
    const docsGrid = document.getElementById('docsGrid');

    if (documentos.length === 0) {
        docsGrid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search fa-3x"></i>
                <h3>No se encontraron resultados</h3>
                <p>No hay documentos que coincidan con "${query}"</p>
                <button class="btn btn-primary" onclick="limpiarBusqueda()">
                    <i class="fas fa-times"></i> Limpiar Búsqueda
                </button>
            </div>
        `;
        return;
    }

    // Agrupar por categoría
    const categorias = {};
    documentos.forEach(doc => {
        if (!categorias[doc.categoria]) {
            categorias[doc.categoria] = [];
        }
        categorias[doc.categoria].push(doc);
    });

    let html = `<div class="search-results-header">
        <h2><i class="fas fa-search"></i> Resultados para "${query}" (${documentos.length})</h2>
        <button class="btn btn-secondary" onclick="limpiarBusqueda()">
            <i class="fas fa-times"></i> Limpiar
        </button>
    </div>`;

    Object.keys(categorias).forEach(categoria => {
        const docs = categorias[categoria];
        html += `
            <div class="category-section">
                <div class="category-header" style="border-left-color: ${docs[0].categoria_color || '#007bff'};">
                    <h2>
                        <i class="${docs[0].categoria_icono || 'fas fa-folder'}" 
                           style="color: ${docs[0].categoria_color || '#007bff'};"></i>
                        ${categoria} (${docs.length})
                    </h2>
                </div>
                <div class="docs-category-grid">
        `;

        docs.forEach(doc => {
            html += crearTarjetaDocumento(doc, query);
        });

        html += `
                </div>
            </div>
        `;
    });

    docsGrid.innerHTML = html;
}

// Crear tarjeta de documento
function crearTarjetaDocumento(doc, highlightQuery = null) {
    let titulo = doc.titulo;
    let descripcion = doc.descripcion;

    // Resaltar términos de búsqueda
    if (highlightQuery) {
        const regex = new RegExp(`(${highlightQuery})`, 'gi');
        titulo = titulo.replace(regex, '<mark>$1</mark>');
        descripcion = descripcion.replace(regex, '<mark>$1</mark>');
    }

    let tagsHtml = '';
    if (doc.tags) {
        const tags = doc.tags.split(',');
        tagsHtml = '<div class="doc-tags">';
        tags.forEach(tag => {
            let tagText = tag.trim();
            if (highlightQuery) {
                const regex = new RegExp(`(${highlightQuery})`, 'gi');
                tagText = tagText.replace(regex, '<mark>$1</mark>');
            }
            tagsHtml += `<span class="tag">${tagText}</span>`;
        });
        tagsHtml += '</div>';
    }

    return `
        <div class="doc-card">
            <div class="doc-header">
                <div class="doc-icon" style="background-color: ${doc.categoria_color || '#007bff'};">
                    <i class="${doc.categoria_icono || 'fas fa-file-alt'}"></i>
                </div>
                <div class="doc-info">
                    <h3>${titulo}</h3>
                    <p>${descripcion}</p>
                </div>
            </div>
            
            <div class="doc-meta">
                ${tagsHtml}
                <div class="doc-dates">
                    <small>
                        <i class="fas fa-calendar"></i> 
                        Actualizado: ${new Date(doc.fecha_modificacion).toLocaleDateString('es-ES')}
                    </small>
                </div>
            </div>
            
            <div class="doc-actions">
                <a href="?action=ver&id=${doc.id}" class="btn btn-primary" target="_blank">
                    <i class="fas fa-eye"></i> Ver Documento
                </a>
                <a href="${doc.archivo_md.replace(/.*\//, '../../')}" class="btn btn-outline-secondary" download>
                    <i class="fas fa-download"></i> Descargar MD
                </a>
                <button class="btn btn-outline-info" onclick="compartirDocumento(${doc.id})">
                    <i class="fas fa-share"></i> Compartir
                </button>
            </div>
        </div>
    `;
}

// Limpiar búsqueda
function limpiarBusqueda() {
    document.getElementById('searchDocs').value = '';
    location.reload();
}

// Regenerar documentación
function regenerarDocumentacion() {
    if (!confirm('¿Deseas regenerar toda la documentación? Esto puede tomar unos momentos.')) {
        return;
    }

    showNotification('Regenerando documentación...', 'info');

    fetch('documentacion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=regenerar'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Documentación regenerada exitosamente', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification('Error al regenerar documentación', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// Compartir documento
function compartirDocumento(docId) {
    const baseUrl = window.location.origin + window.location.pathname;
    const shareUrl = `${baseUrl}?action=ver&id=${docId}`;
    const embedCode = `<iframe src="${shareUrl}" width="100%" height="600" frameborder="0"></iframe>`;

    document.getElementById('shareUrl').value = shareUrl;
    document.getElementById('embedCode').value = embedCode;

    showModal('modalCompartir');
}

// Copiar enlace
function copiarEnlace() {
    const shareUrl = document.getElementById('shareUrl');
    shareUrl.select();
    shareUrl.setSelectionRange(0, 99999);

    try {
        document.execCommand('copy');
        showNotification('Enlace copiado al portapapeles', 'success');
    } catch (err) {
        // Fallback para navegadores modernos
        navigator.clipboard.writeText(shareUrl.value).then(() => {
            showNotification('Enlace copiado al portapapeles', 'success');
        }).catch(() => {
            showNotification('Error al copiar enlace', 'error');
        });
    }
}

// Copiar código de inserción
function copiarCodigo() {
    const embedCode = document.getElementById('embedCode');
    embedCode.select();
    embedCode.setSelectionRange(0, 99999);

    try {
        document.execCommand('copy');
        showNotification('Código copiado al portapapeles', 'success');
    } catch (err) {
        navigator.clipboard.writeText(embedCode.value).then(() => {
            showNotification('Código copiado al portapapeles', 'success');
        }).catch(() => {
            showNotification('Error al copiar código', 'error');
        });
    }
}

// Generar tabla de contenidos
function generateTableOfContents() {
    const tocContainer = document.getElementById('tableOfContents');
    if (!tocContainer) return;

    const headers = document.querySelectorAll('.doc-article h1, .doc-article h2, .doc-article h3, .doc-article h4');
    if (headers.length === 0) {
        tocContainer.innerHTML = '<p>No hay encabezados en este documento</p>';
        return;
    }

    let tocHtml = '<ul>';
    headers.forEach((header, index) => {
        const id = `header-${index}`;
        header.id = id;

        const level = parseInt(header.tagName.charAt(1));
        const text = header.textContent;
        const className = `toc-h${level}`;

        tocHtml += `<li><a href="#${id}" class="${className}">${text}</a></li>`;
    });
    tocHtml += '</ul>';

    tocContainer.innerHTML = tocHtml;

    // Smooth scroll para los enlaces del TOC
    tocContainer.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Alternar modo oscuro
function toggleDarkMode() {
    currentTheme = currentTheme === 'light' ? 'dark' : 'light';
    applyTheme(currentTheme);
    localStorage.setItem('doc-theme', currentTheme);
}

// Aplicar tema
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);

    const darkModeBtn = document.querySelector('[onclick="toggleDarkMode()"]');
    if (darkModeBtn) {
        const icon = darkModeBtn.querySelector('i');
        if (theme === 'dark') {
            icon.className = 'fas fa-sun';
            darkModeBtn.innerHTML = '<i class="fas fa-sun"></i> Modo Claro';
        } else {
            icon.className = 'fas fa-moon';
            darkModeBtn.innerHTML = '<i class="fas fa-moon"></i> Modo Oscuro';
        }
    }
}

// Funciones de utilidad para modales
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

// Función de notificaciones
function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
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
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                padding: 1rem;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
                z-index: 1000;
                display: flex;
                align-items: center;
                gap: 1rem;
                min-width: 300px;
                animation: slideIn 0.3s ease;
            }
            
            .notification-success { border-left: 4px solid #28a745; }
            .notification-error { border-left: 4px solid #dc3545; }
            .notification-warning { border-left: 4px solid #ffc107; }
            .notification-info { border-left: 4px solid #17a2b8; }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                flex: 1;
            }
            
            .notification-close {
                background: none;
                border: none;
                color: #6c757d;
                cursor: pointer;
                padding: 0.25rem;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }

    // Agregar al DOM
    document.body.appendChild(notification);

    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function getNotificationIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// Funciones para mejorar la experiencia de lectura
document.addEventListener('DOMContentLoaded', function () {
    // Agregar botón de "volver arriba" en documentos largos
    if (document.querySelector('.doc-article')) {
        addBackToTopButton();
    }

    // Mejorar navegación con teclado
    document.addEventListener('keydown', function (e) {
        // Ctrl/Cmd + K para enfocar búsqueda
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('searchDocs');
            if (searchInput) {
                searchInput.focus();
            }
        }

        // Escape para cerrar modales
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal[style*="flex"]');
            modals.forEach(modal => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
        }
    });
});

// Agregar botón de volver arriba
function addBackToTopButton() {
    const button = document.createElement('button');
    button.innerHTML = '<i class="fas fa-arrow-up"></i>';
    button.className = 'back-to-top';
    button.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });

    // Estilos del botón
    const styles = `
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: var(--doc-primary);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
    `;

    if (!document.getElementById('back-to-top-styles')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'back-to-top-styles';
        styleSheet.textContent = styles;
        document.head.appendChild(styleSheet);
    }

    document.body.appendChild(button);

    // Mostrar/ocultar botón según scroll
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            button.classList.add('visible');
        } else {
            button.classList.remove('visible');
        }
    });
}