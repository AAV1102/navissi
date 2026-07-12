/**
 * Sistema de Accesibilidad
 * WorkManager ERP - Integral IPS
 * Desarrollado por: Anderson Ayala Vera
 */

// Variables de accesibilidad
let fontSize = 100;

// Cargar preferencias guardadas
document.addEventListener('DOMContentLoaded', function() {
    loadAccessibilitySettings();
});

// Toggle panel de accesibilidad
function toggleAccessibilityPanel() {
    const panel = document.getElementById('accessibilityPanel');
    panel.classList.toggle('show');
    
    // Cerrar panel de idioma si está abierto
    const langPanel = document.getElementById('languagePanel');
    if (langPanel) {
        langPanel.classList.remove('show');
    }
}

// Toggle panel de idioma
function toggleLanguagePanel() {
    const panel = document.getElementById('languagePanel');
    panel.classList.toggle('show');
    
    // Cerrar panel de accesibilidad si está abierto
    const accPanel = document.getElementById('accessibilityPanel');
    if (accPanel) {
        accPanel.classList.remove('show');
    }
}

// Aumentar tamaño de fuente
function increaseFontSize() {
    if (fontSize < 150) {
        fontSize += 10;
        applyFontSize();
        saveSetting('fontSize', fontSize);
    }
}

// Disminuir tamaño de fuente
function decreaseFontSize() {
    if (fontSize > 80) {
        fontSize -= 10;
        applyFontSize();
        saveSetting('fontSize', fontSize);
    }
}

// Aplicar tamaño de fuente
function applyFontSize() {
    document.documentElement.style.fontSize = fontSize + '%';
    document.getElementById('fontSize').textContent = fontSize + '%';
}

// Toggle alto contraste
function toggleHighContrast() {
    const isEnabled = document.getElementById('highContrast').checked;
    document.body.classList.toggle('high-contrast', isEnabled);
    saveSetting('highContrast', isEnabled);
}

// Toggle escala de grises
function toggleGrayscale() {
    const isEnabled = document.getElementById('grayscale').checked;
    document.body.classList.toggle('grayscale', isEnabled);
    saveSetting('grayscale', isEnabled);
}

// Toggle navegación por teclado
function toggleKeyboardNav() {
    const isEnabled = document.getElementById('keyboardNav').checked;
    document.body.classList.toggle('keyboard-nav', isEnabled);
    saveSetting('keyboardNav', isEnabled);
}

// Restablecer accesibilidad
function resetAccessibility() {
    fontSize = 100;
    applyFontSize();
    
    document.getElementById('highContrast').checked = false;
    document.getElementById('grayscale').checked = false;
    document.getElementById('keyboardNav').checked = false;
    
    document.body.classList.remove('high-contrast', 'grayscale', 'keyboard-nav');
    
    localStorage.removeItem('accessibility');
}

// Guardar configuración
function saveSetting(key, value) {
    let settings = JSON.parse(localStorage.getItem('accessibility') || '{}');
    settings[key] = value;
    localStorage.setItem('accessibility', JSON.stringify(settings));
}

// Cargar configuraciones guardadas
function loadAccessibilitySettings() {
    const settings = JSON.parse(localStorage.getItem('accessibility') || '{}');
    
    if (settings.fontSize) {
        fontSize = settings.fontSize;
        applyFontSize();
    }
    
    if (settings.highContrast) {
        document.getElementById('highContrast').checked = true;
        document.body.classList.add('high-contrast');
    }
    
    if (settings.grayscale) {
        document.getElementById('grayscale').checked = true;
        document.body.classList.add('grayscale');
    }
    
    if (settings.keyboardNav) {
        document.getElementById('keyboardNav').checked = true;
        document.body.classList.add('keyboard-nav');
    }
}

// Cerrar paneles al hacer clic fuera
document.addEventListener('click', function(e) {
    const accPanel = document.getElementById('accessibilityPanel');
    const langPanel = document.getElementById('languagePanel');
    
    if (!e.target.closest('.accessibility-panel') && !e.target.closest('[onclick*="toggleAccessibilityPanel"]')) {
        accPanel?.classList.remove('show');
    }
    
    if (!e.target.closest('.language-panel') && !e.target.closest('[onclick*="toggleLanguagePanel"]')) {
        langPanel?.classList.remove('show');
    }
});

// Navegación por teclado mejorada
document.addEventListener('keydown', function(e) {
    // Esc para cerrar paneles
    if (e.key === 'Escape') {
        document.getElementById('accessibilityPanel')?.classList.remove('show');
        document.getElementById('languagePanel')?.classList.remove('show');
    }
    
    // Ctrl+Alt+A para abrir accesibilidad
    if (e.ctrlKey && e.altKey && e.key === 'a') {
        e.preventDefault();
        toggleAccessibilityPanel();
    }
    
    // Ctrl+Alt+L para abrir idiomas
    if (e.ctrlKey && e.altKey && e.key === 'l') {
        e.preventDefault();
        toggleLanguagePanel();
    }
});
