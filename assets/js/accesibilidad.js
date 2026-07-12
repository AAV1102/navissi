/**
 * WorkManager ERP - JavaScript para Accesibilidad Universal
 * Funcionalidades para configuración de accesibilidad y adaptabilidad
 */

// Variables globales
let speechSynthesis = window.speechSynthesis;
let currentVoice = null;
let isScreenReaderActive = false;
let isVoiceCommandsActive = false;
let recognition = null;
let readingGuide = null;

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function () {
    initializeAccessibility();
    loadVoices();
    setupKeyboardNavigation();
    setupRangeUpdaters();

    // Aplicar configuración guardada
    applyCurrentSettings();
});

// Inicializar funcionalidades de accesibilidad
function initializeAccessibility() {
    // Crear enlace de "Saltar al contenido"
    createSkipLink();

    // Configurar navegación por teclado
    setupKeyboardShortcuts();

    // Inicializar reconocimiento de voz si está disponible
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        initializeVoiceRecognition();
    }

    // Configurar guía de lectura
    setupReadingGuide();

    // Aplicar configuración de accesibilidad desde CSS
    loadAccessibilityCSS();
}

// Crear enlace de "Saltar al contenido"
function createSkipLink() {
    const skipLink = document.createElement('a');
    skipLink.href = '#main-content';
    skipLink.textContent = 'Saltar al contenido principal';
    skipLink.className = 'skip-link';
    skipLink.style.cssText = `
        position: absolute;
        top: -40px;
        left: 6px;
        background: #000;
        color: #fff;
        padding: 8px;
        text-decoration: none;
        z-index: 10000;
        border-radius: 4px;
    `;

    skipLink.addEventListener('focus', function () {
        this.style.top = '6px';
    });

    skipLink.addEventListener('blur', function () {
        this.style.top = '-40px';
    });

    document.body.insertBefore(skipLink, document.body.firstChild);

    // Asegurar que el contenido principal tenga ID
    const mainContent = document.querySelector('.main-content');
    if (mainContent && !mainContent.id) {
        mainContent.id = 'main-content';
    }
}

// Configurar atajos de teclado
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function (e) {
        // Alt + M: Abrir menú principal
        if (e.altKey && e.key === 'm') {
            e.preventDefault();
            const menuToggle = document.querySelector('.sidebar-toggle');
            if (menuToggle) menuToggle.click();
        }

        // Alt + S: Enfocar búsqueda
        if (e.altKey && e.key === 's') {
            e.preventDefault();
            const searchInput = document.querySelector('input[type="search"], input[placeholder*="buscar"], input[placeholder*="Buscar"]');
            if (searchInput) searchInput.focus();
        }

        // Alt + H: Ir al inicio
        if (e.altKey && e.key === 'h') {
            e.preventDefault();
            window.location.href = '/dashboard.php';
        }

        // F1: Ayuda contextual
        if (e.key === 'F1') {
            e.preventDefault();
            showContextualHelp();
        }

        // Ctrl + /: Lista de atajos
        if (e.ctrlKey && e.key === '/') {
            e.preventDefault();
            showKeyboardShortcuts();
        }

        // Escape: Cerrar modales
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

// Configurar navegación por teclado mejorada
function setupKeyboardNavigation() {
    // Hacer todos los elementos interactivos accesibles por teclado
    const interactiveElements = document.querySelectorAll('button, a, input, select, textarea, [tabindex]');

    interactiveElements.forEach(element => {
        // Agregar indicadores visuales de foco
        element.addEventListener('focus', function () {
            this.style.outline = '3px solid #007bff';
            this.style.outlineOffset = '2px';

            // Leer elemento si el lector de pantalla está activo
            if (isScreenReaderActive) {
                readElement(this);
            }
        });

        element.addEventListener('blur', function () {
            this.style.outline = '';
            this.style.outlineOffset = '';
        });

        // Navegación con Enter en elementos que no son botones
        if (element.tagName !== 'BUTTON' && element.tagName !== 'INPUT') {
            element.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && this.onclick) {
                    e.preventDefault();
                    this.click();
                }
            });
        }
    });
}

// Configurar actualizadores de rangos
function setupRangeUpdaters() {
    // Magnificación
    const magnificationRange = document.getElementById('magnification');
    if (magnificationRange) {
        magnificationRange.addEventListener('input', function () {
            document.getElementById('magnificationValue').textContent = (this.value * 100) + '%';
        });
    }

    // Velocidad de habla
    const speechRateRange = document.getElementById('speechRate');
    if (speechRateRange) {
        speechRateRange.addEventListener('input', function () {
            document.getElementById('speechRateValue').textContent = this.value + 'x';
        });
    }

    // Tono de voz
    const speechPitchRange = document.getElementById('speechPitch');
    if (speechPitchRange) {
        speechPitchRange.addEventListener('input', function () {
            document.getElementById('speechPitchValue').textContent = this.value;
        });
    }

    // Volumen
    const speechVolumeRange = document.getElementById('speechVolume');
    if (speechVolumeRange) {
        speechVolumeRange.addEventListener('input', function () {
            document.getElementById('speechVolumeValue').textContent = (this.value * 100) + '%';
        });
    }
}

// Cargar voces disponibles
function loadVoices() {
    const voiceSelect = document.getElementById('voiceSelect');
    if (!voiceSelect) return;

    function populateVoices() {
        const voices = speechSynthesis.getVoices();
        voiceSelect.innerHTML = '<option value="">Voz del sistema</option>';

        voices.forEach(voice => {
            const option = document.createElement('option');
            option.value = voice.name;
            option.textContent = `${voice.name} (${voice.lang})`;
            voiceSelect.appendChild(option);
        });
    }

    populateVoices();
    speechSynthesis.addEventListener('voiceschanged', populateVoices);
}

// Aplicar perfil predefinido
function applyProfile(profileType) {
    const profiles = {
        elderly: {
            font_size: 'large',
            high_contrast: true,
            magnification: 1.2,
            simplified_interface: true,
            reduce_motion: true,
            reading_guide: true
        },
        visual_impaired: {
            screen_reader: true,
            keyboard_navigation: true,
            high_contrast: true,
            audio_descriptions: true,
            voice_commands: true
        },
        motor_impaired: {
            keyboard_navigation: true,
            reduce_motion: true,
            magnification: 1.3,
            simplified_interface: true
        },
        cognitive: {
            simplified_interface: true,
            reading_guide: true,
            reduce_motion: true,
            font_size: 'large',
            captions: true
        },
        child_friendly: {
            font_size: 'large',
            magnification: 1.1,
            simplified_interface: true,
            captions: true
        }
    };

    const profile = profiles[profileType];
    if (!profile) return;

    // Aplicar configuración del perfil
    Object.keys(profile).forEach(key => {
        const element = document.querySelector(`[name="${key}"]`);
        if (element) {
            if (element.type === 'checkbox') {
                element.checked = profile[key];
            } else {
                element.value = profile[key];
            }
        }
    });

    // Aplicar cambios inmediatamente
    previewChanges();

    showNotification(`Perfil "${getProfileName(profileType)}" aplicado`, 'success');
}

// Obtener nombre del perfil
function getProfileName(profileType) {
    const names = {
        elderly: 'Adulto Mayor',
        visual_impaired: 'Discapacidad Visual',
        motor_impaired: 'Discapacidad Motriz',
        cognitive: 'Apoyo Cognitivo',
        child_friendly: 'Niños'
    };
    return names[profileType] || profileType;
}

// Vista previa de cambios
function previewChanges() {
    const config = getCurrentConfig();

    // Aplicar cambios temporalmente
    applyVisualChanges(config);

    // Mostrar panel de vista previa
    const previewPanel = document.getElementById('previewPanel');
    if (previewPanel) {
        previewPanel.style.display = 'block';
        previewPanel.scrollIntoView({ behavior: 'smooth' });
    }
}

// Obtener configuración actual del formulario
function getCurrentConfig() {
    const forms = ['visualForm', 'navigationForm', 'audioForm', 'languageForm'];
    const config = {};

    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                if (form.querySelector(`[name="${key}"]`).type === 'checkbox') {
                    config[key] = form.querySelector(`[name="${key}"]`).checked;
                } else {
                    config[key] = value;
                }
            }
        }
    });

    return config;
}

// Aplicar cambios visuales
function applyVisualChanges(config) {
    const root = document.documentElement;

    // Tamaño de fuente
    const fontSizes = {
        small: '14px',
        normal: '16px',
        large: '18px',
        'extra-large': '22px'
    };
    root.style.setProperty('--base-font-size', fontSizes[config.font_size] || '16px');

    // Magnificación
    if (config.magnification) {
        root.style.setProperty('--magnification', config.magnification);
        document.body.style.zoom = config.magnification;
    }

    // Alto contraste
    if (config.high_contrast) {
        document.body.classList.add('high-contrast');
    } else {
        document.body.classList.remove('high-contrast');
    }

    // Modo oscuro
    if (config.dark_mode) {
        document.body.classList.add('dark-mode');
    } else {
        document.body.classList.remove('dark-mode');
    }

    // Reducir movimiento
    if (config.reduce_motion) {
        document.body.classList.add('reduce-motion');
    } else {
        document.body.classList.remove('reduce-motion');
    }

    // Interfaz simplificada
    if (config.simplified_interface) {
        document.body.classList.add('simplified-interface');
    } else {
        document.body.classList.remove('simplified-interface');
    }

    // Guía de lectura
    if (config.reading_guide) {
        enableReadingGuide();
    } else {
        disableReadingGuide();
    }
}

// Configurar guía de lectura
function setupReadingGuide() {
    readingGuide = document.createElement('div');
    readingGuide.className = 'reading-guide';
    readingGuide.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: #ff0000;
        z-index: 9999;
        pointer-events: none;
        display: none;
    `;
    document.body.appendChild(readingGuide);
}

// Habilitar guía de lectura
function enableReadingGuide() {
    if (!readingGuide) return;

    readingGuide.style.display = 'block';

    document.addEventListener('mousemove', updateReadingGuide);
    document.addEventListener('focus', updateReadingGuideForFocus, true);
}

// Deshabilitar guía de lectura
function disableReadingGuide() {
    if (!readingGuide) return;

    readingGuide.style.display = 'none';

    document.removeEventListener('mousemove', updateReadingGuide);
    document.removeEventListener('focus', updateReadingGuideForFocus, true);
}

// Actualizar posición de guía de lectura
function updateReadingGuide(e) {
    if (readingGuide) {
        readingGuide.style.top = e.clientY + 'px';
    }
}

// Actualizar guía de lectura para elementos enfocados
function updateReadingGuideForFocus(e) {
    if (readingGuide && e.target) {
        const rect = e.target.getBoundingClientRect();
        readingGuide.style.top = (rect.top + rect.height / 2) + 'px';
    }
}

// Alternar lector de pantalla
function toggleScreenReader() {
    const checkbox = document.querySelector('[name="screen_reader"]');
    isScreenReaderActive = checkbox.checked;

    const voiceSettings = document.getElementById('voiceSettings');
    if (voiceSettings) {
        voiceSettings.style.display = isScreenReaderActive ? 'block' : 'none';
    }

    if (isScreenReaderActive) {
        speak('Lector de pantalla activado');
        enableScreenReader();
    } else {
        speak('Lector de pantalla desactivado');
        disableScreenReader();
    }
}

// Habilitar lector de pantalla
function enableScreenReader() {
    // Leer elementos al enfocarlos
    document.addEventListener('focus', handleFocusForScreenReader, true);

    // Leer cambios en el DOM
    const observer = new MutationObserver(handleDOMChanges);
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true
    });
}

// Deshabilitar lector de pantalla
function disableScreenReader() {
    document.removeEventListener('focus', handleFocusForScreenReader, true);
    speechSynthesis.cancel();
}

// Manejar foco para lector de pantalla
function handleFocusForScreenReader(e) {
    if (isScreenReaderActive) {
        readElement(e.target);
    }
}

// Leer elemento
function readElement(element) {
    let text = '';

    // Determinar qué leer según el tipo de elemento
    switch (element.tagName.toLowerCase()) {
        case 'button':
            text = `Botón: ${element.textContent || element.value || element.title}`;
            break;
        case 'a':
            text = `Enlace: ${element.textContent || element.title}`;
            break;
        case 'input':
            const label = document.querySelector(`label[for="${element.id}"]`);
            const labelText = label ? label.textContent : element.placeholder || element.name;
            text = `Campo ${element.type}: ${labelText}`;
            if (element.value) {
                text += `, valor: ${element.value}`;
            }
            break;
        case 'select':
            const selectLabel = document.querySelector(`label[for="${element.id}"]`);
            text = `Lista desplegable: ${selectLabel ? selectLabel.textContent : element.name}`;
            if (element.selectedOptions.length > 0) {
                text += `, seleccionado: ${element.selectedOptions[0].textContent}`;
            }
            break;
        case 'h1':
        case 'h2':
        case 'h3':
        case 'h4':
        case 'h5':
        case 'h6':
            text = `Encabezado nivel ${element.tagName.charAt(1)}: ${element.textContent}`;
            break;
        default:
            text = element.textContent || element.title || element.alt;
    }

    if (text.trim()) {
        speak(text);
    }
}

// Función de síntesis de voz
function speak(text, options = {}) {
    if (!speechSynthesis) return;

    // Cancelar habla anterior
    speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(text);

    // Aplicar configuración de voz
    const voiceSelect = document.getElementById('voiceSelect');
    const speechRate = document.getElementById('speechRate');
    const speechPitch = document.getElementById('speechPitch');
    const speechVolume = document.getElementById('speechVolume');

    if (voiceSelect && voiceSelect.value) {
        const voices = speechSynthesis.getVoices();
        const selectedVoice = voices.find(voice => voice.name === voiceSelect.value);
        if (selectedVoice) {
            utterance.voice = selectedVoice;
        }
    }

    if (speechRate) utterance.rate = parseFloat(speechRate.value);
    if (speechPitch) utterance.pitch = parseFloat(speechPitch.value);
    if (speechVolume) utterance.volume = parseFloat(speechVolume.value);

    // Aplicar opciones adicionales
    Object.assign(utterance, options);

    speechSynthesis.speak(utterance);
}

// Probar voz
function testVoice() {
    speak('Esta es una prueba de la configuración de voz. ¿Puedes escucharme claramente?');
}

// Alternar comandos de voz
function toggleVoiceCommands() {
    const checkbox = document.querySelector('[name="voice_commands"]');
    isVoiceCommandsActive = checkbox.checked;

    if (isVoiceCommandsActive) {
        enableVoiceCommands();
        speak('Comandos de voz activados. Di "ayuda" para ver los comandos disponibles.');
    } else {
        disableVoiceCommands();
        speak('Comandos de voz desactivados');
    }
}

// Inicializar reconocimiento de voz
function initializeVoiceRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return;

    recognition = new SpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = false;
    recognition.lang = 'es-ES';

    recognition.onresult = function (event) {
        const command = event.results[event.results.length - 1][0].transcript.toLowerCase().trim();
        processVoiceCommand(command);
    };

    recognition.onerror = function (event) {
        console.error('Error de reconocimiento de voz:', event.error);
    };
}

// Habilitar comandos de voz
function enableVoiceCommands() {
    if (recognition) {
        recognition.start();
    }
}

// Deshabilitar comandos de voz
function disableVoiceCommands() {
    if (recognition) {
        recognition.stop();
    }
}

// Procesar comando de voz
function processVoiceCommand(command) {
    console.log('Comando de voz:', command);

    // Comandos básicos
    if (command.includes('ayuda')) {
        speak('Comandos disponibles: ir al inicio, abrir menú, buscar, guardar configuración, cerrar');
    } else if (command.includes('inicio')) {
        window.location.href = '/dashboard.php';
    } else if (command.includes('menú')) {
        const menuToggle = document.querySelector('.sidebar-toggle');
        if (menuToggle) menuToggle.click();
    } else if (command.includes('buscar')) {
        const searchInput = document.querySelector('input[type="search"]');
        if (searchInput) searchInput.focus();
    } else if (command.includes('guardar')) {
        saveAllSettings();
    } else if (command.includes('cerrar')) {
        closeAllModals();
    } else {
        speak('Comando no reconocido. Di "ayuda" para ver los comandos disponibles.');
    }
}

// Cambiar idioma
function changeLanguage() {
    const languageSelect = document.getElementById('systemLanguage');
    const selectedLanguage = languageSelect.value;

    // Cargar traducciones del idioma seleccionado
    fetch('accesibilidad.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_translations&language=${selectedLanguage}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                applyTranslations(data.translations);
                showNotification('Idioma cambiado exitosamente', 'success');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al cambiar idioma', 'error');
        });
}

// Aplicar traducciones
function applyTranslations(translations) {
    // Aplicar traducciones a elementos con atributo data-translate
    document.querySelectorAll('[data-translate]').forEach(element => {
        const key = element.getAttribute('data-translate');
        if (translations[key]) {
            element.textContent = translations[key];
        }
    });

    // Guardar traducciones en localStorage para uso futuro
    localStorage.setItem('translations', JSON.stringify(translations));
}

// Guardar toda la configuración
function saveAllSettings() {
    const config = getCurrentConfig();

    // Guardar configuración de accesibilidad
    fetch('accesibilidad.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_accessibility&config=${encodeURIComponent(JSON.stringify(config))}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Guardar configuración de voz
                const voiceSettings = getVoiceSettings();
                return fetch('accesibilidad.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_voice_settings&settings=${encodeURIComponent(JSON.stringify(voiceSettings))}`
                });
            } else {
                throw new Error('Error al guardar configuración de accesibilidad');
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Configuración guardada exitosamente', 'success');
                applyCurrentSettings();
            } else {
                throw new Error('Error al guardar configuración de voz');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al guardar configuración', 'error');
        });
}

// Obtener configuración de voz
function getVoiceSettings() {
    const voiceForm = document.getElementById('voiceSettings');
    if (!voiceForm) return {};

    const formData = new FormData(voiceForm);
    const settings = {};

    for (let [key, value] of formData.entries()) {
        const element = voiceForm.querySelector(`[name="${key}"]`);
        if (element && element.type === 'checkbox') {
            settings[key] = element.checked;
        } else {
            settings[key] = value;
        }
    }

    return settings;
}

// Aplicar configuración actual
function applyCurrentSettings() {
    const config = getCurrentConfig();
    applyVisualChanges(config);

    // Cargar CSS de accesibilidad personalizado
    loadAccessibilityCSS();
}

// Cargar CSS de accesibilidad
function loadAccessibilityCSS() {
    // Remover CSS anterior si existe
    const existingLink = document.getElementById('accessibility-css');
    if (existingLink) {
        existingLink.remove();
    }

    // Crear nuevo enlace CSS
    const link = document.createElement('link');
    link.id = 'accessibility-css';
    link.rel = 'stylesheet';
    link.href = 'accesibilidad.php?action=accessibility_css&t=' + Date.now();
    document.head.appendChild(link);
}

// Probar configuración de accesibilidad
function testAccessibility() {
    speak('Iniciando prueba de accesibilidad');

    // Probar lector de pantalla
    setTimeout(() => {
        speak('Probando lector de pantalla. Este texto debería ser leído en voz alta.');
    }, 1000);

    // Probar navegación por teclado
    setTimeout(() => {
        speak('Prueba la navegación por teclado usando la tecla Tab para moverte entre elementos.');
    }, 3000);

    // Mostrar información de la prueba
    showNotification('Prueba de accesibilidad iniciada. Escucha las instrucciones de voz.', 'info');
}

// Restaurar configuración predeterminada
function resetToDefaults() {
    if (!confirm('¿Estás seguro de que deseas restaurar la configuración predeterminada?')) {
        return;
    }

    // Restaurar valores por defecto en formularios
    document.querySelectorAll('form').forEach(form => {
        form.reset();
    });

    // Aplicar cambios
    previewChanges();

    showNotification('Configuración restaurada a valores predeterminados', 'info');
}

// Exportar configuración
function exportSettings() {
    const config = getCurrentConfig();
    const voiceSettings = getVoiceSettings();

    const exportData = {
        accessibility: config,
        voice: voiceSettings,
        timestamp: new Date().toISOString(),
        version: '1.0'
    };

    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = 'workmanager-accessibility-config.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    showNotification('Configuración exportada exitosamente', 'success');
}

// Importar configuración
function importSettings() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';

    input.onchange = function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            try {
                const importData = JSON.parse(e.target.result);

                if (importData.accessibility) {
                    applyImportedConfig(importData.accessibility);
                }

                if (importData.voice) {
                    applyImportedVoiceSettings(importData.voice);
                }

                previewChanges();
                showNotification('Configuración importada exitosamente', 'success');

            } catch (error) {
                console.error('Error al importar:', error);
                showNotification('Error al importar configuración', 'error');
            }
        };
        reader.readAsText(file);
    };

    input.click();
}

// Aplicar configuración importada
function applyImportedConfig(config) {
    Object.keys(config).forEach(key => {
        const element = document.querySelector(`[name="${key}"]`);
        if (element) {
            if (element.type === 'checkbox') {
                element.checked = config[key];
            } else {
                element.value = config[key];
            }
        }
    });
}

// Aplicar configuración de voz importada
function applyImportedVoiceSettings(settings) {
    Object.keys(settings).forEach(key => {
        const element = document.querySelector(`[name="${key}"]`);
        if (element) {
            if (element.type === 'checkbox') {
                element.checked = settings[key];
            } else {
                element.value = settings[key];
            }
        }
    });
}

// Ocultar vista previa
function hidePreview() {
    const previewPanel = document.getElementById('previewPanel');
    if (previewPanel) {
        previewPanel.style.display = 'none';
    }
}

// Abrir herramienta de traducción
function openTranslationTool() {
    showModal('modalTranslation');
}

// Cargar traducciones para editar
function loadTranslations() {
    const languageSelect = document.getElementById('translationLanguage');
    const language = languageSelect.value;

    if (!language) {
        showNotification('Selecciona un idioma', 'warning');
        return;
    }

    fetch(`accesibilidad.php?action=get_translations&language=${language}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTranslationEditor(data.translations, language);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al cargar traducciones', 'error');
        });
}

// Mostrar editor de traducciones
function displayTranslationEditor(translations, language) {
    const editor = document.getElementById('translationEditor');
    let html = '<div class="translation-pairs">';

    Object.keys(translations).forEach(key => {
        html += `
            <div class="translation-pair">
                <label>${key}</label>
                <input type="text" value="${translations[key]}" data-key="${key}" class="form-control">
            </div>
        `;
    });

    html += '</div>';
    html += `<button class="btn btn-success mt-3" onclick="saveTranslations('${language}')">
                <i class="fas fa-save"></i> Guardar Traducciones
             </button>`;

    editor.innerHTML = html;
}

// Mostrar ayuda contextual
function showContextualHelp() {
    const helpText = `
        Ayuda de Accesibilidad:
        
        • Use Tab para navegar entre elementos
        • Use Enter para activar botones y enlaces
        • Use las flechas para navegar en listas
        • Use Escape para cerrar ventanas modales
        • Use Alt + M para abrir el menú
        • Use Alt + S para buscar
        • Use F1 para esta ayuda
    `;

    speak(helpText);
    showNotification('Ayuda de accesibilidad leída por voz', 'info');
}

// Mostrar lista de atajos de teclado
function showKeyboardShortcuts() {
    const shortcuts = [
        'Alt + M: Abrir menú principal',
        'Alt + S: Enfocar búsqueda',
        'Alt + H: Ir al inicio',
        'F1: Ayuda contextual',
        'Ctrl + /: Lista de atajos',
        'Escape: Cerrar modales',
        'Tab: Navegar elementos',
        'Enter: Activar elemento'
    ];

    speak('Atajos de teclado disponibles: ' + shortcuts.join('. '));
}

// Cerrar todos los modales
function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
    });
    document.body.style.overflow = 'auto';
}

// Manejar cambios en el DOM para lector de pantalla
function handleDOMChanges(mutations) {
    if (!isScreenReaderActive) return;

    mutations.forEach(mutation => {
        if (mutation.type === 'childList') {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    // Leer notificaciones nuevas
                    if (node.classList && node.classList.contains('notification')) {
                        const text = node.textContent;
                        if (text) {
                            speak('Notificación: ' + text);
                        }
                    }
                }
            });
        }
    });
}

// Funciones de utilidad
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Enfocar primer elemento interactivo
        const firstFocusable = modal.querySelector('button, input, select, textarea, [tabindex]');
        if (firstFocusable) {
            setTimeout(() => firstFocusable.focus(), 100);
        }
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function showNotification(message, type = 'info') {
    // Crear notificación visual
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

    // Estilos de notificación
    notification.style.cssText = `
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
    `;

    document.body.appendChild(notification);

    // Leer notificación si el lector de pantalla está activo
    if (isScreenReaderActive) {
        speak('Notificación: ' + message);
    }

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