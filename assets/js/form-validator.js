/**
 * Form Validator - WorkManager ERP
 * =================================
 * Sistema de validación de formularios del lado del cliente
 */

class FormValidator {
    constructor(formSelector, options = {}) {
        this.form = typeof formSelector === 'string' 
            ? document.querySelector(formSelector) 
            : formSelector;
        
        if (!this.form) {
            console.error('FormValidator: Formulario no encontrado');
            return;
        }
        
        this.options = {
            validateOnBlur: true,
            validateOnInput: false,
            showSuccessState: true,
            scrollToError: true,
            errorClass: 'is-invalid',
            successClass: 'is-valid',
            errorMessageClass: 'invalid-feedback',
            ...options
        };
        
        this.rules = {};
        this.errors = {};
        this.customValidators = {};
        
        this.init();
    }
    
    /**
     * Inicializar validador
     */
    init() {
        // Prevenir envío por defecto
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
        
        // Validación en blur
        if (this.options.validateOnBlur) {
            this.form.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('blur', () => this.validateField(field));
            });
        }
        
        // Validación en input
        if (this.options.validateOnInput) {
            this.form.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('input', () => this.validateField(field));
            });
        }
    }
    
    /**
     * Definir reglas de validación
     */
    setRules(rules) {
        this.rules = rules;
        return this;
    }
    
    /**
     * Añadir validador personalizado
     */
    addValidator(name, fn, message) {
        this.customValidators[name] = { fn, message };
        return this;
    }
    
    /**
     * Validar todo el formulario
     */
    validate() {
        this.errors = {};
        let isValid = true;
        
        // Validar campos con reglas definidas
        for (const [fieldName, fieldRules] of Object.entries(this.rules)) {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field && !this.validateField(field, fieldRules)) {
                isValid = false;
            }
        }
        
        // Validar campos con atributos HTML5
        this.form.querySelectorAll('[required], [pattern], [minlength], [maxlength], [min], [max], [type="email"]').forEach(field => {
            if (!this.rules[field.name] && !this.validateField(field)) {
                isValid = false;
            }
        });
        
        // Scroll al primer error
        if (!isValid && this.options.scrollToError) {
            const firstError = this.form.querySelector('.' + this.options.errorClass);
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
        
        return isValid;
    }
    
    /**
     * Validar un campo específico
     */
    validateField(field, rules = null) {
        const fieldName = field.name;
        const value = this.getFieldValue(field);
        rules = rules || this.rules[fieldName] || '';
        
        // Limpiar estado anterior
        this.clearFieldState(field);
        
        // Parsear reglas
        const ruleList = typeof rules === 'string' ? rules.split('|').filter(r => r) : rules;
        
        // Validar atributos HTML5 primero
        if (!this.validateHTML5(field, value)) {
            return false;
        }
        
        // Validar reglas personalizadas
        for (const rule of ruleList) {
            const [ruleName, ...params] = rule.split(':');
            const paramList = params.join(':').split(',');
            
            if (!this.applyRule(field, value, ruleName, paramList)) {
                return false;
            }
        }
        
        // Marcar como válido
        if (this.options.showSuccessState && value) {
            field.classList.add(this.options.successClass);
        }
        
        return true;
    }
    
    /**
     * Obtener valor del campo
     */
    getFieldValue(field) {
        if (field.type === 'checkbox') {
            return field.checked;
        }
        if (field.type === 'radio') {
            const checked = this.form.querySelector(`[name="${field.name}"]:checked`);
            return checked ? checked.value : '';
        }
        if (field.type === 'file') {
            return field.files;
        }
        return field.value.trim();
    }
    
    /**
     * Validar atributos HTML5
     */
    validateHTML5(field, value) {
        // Required
        if (field.hasAttribute('required') && !value) {
            this.setError(field, 'Este campo es obligatorio');
            return false;
        }
        
        if (!value) return true; // Si está vacío y no es required, pasar
        
        // Email
        if (field.type === 'email' && !this.isValidEmail(value)) {
            this.setError(field, 'Ingrese un correo electrónico válido');
            return false;
        }
        
        // Pattern
        if (field.hasAttribute('pattern')) {
            const pattern = new RegExp(field.getAttribute('pattern'));
            if (!pattern.test(value)) {
                this.setError(field, field.getAttribute('title') || 'El formato no es válido');
                return false;
            }
        }
        
        // Minlength
        if (field.hasAttribute('minlength')) {
            const min = parseInt(field.getAttribute('minlength'));
            if (value.length < min) {
                this.setError(field, `Mínimo ${min} caracteres`);
                return false;
            }
        }
        
        // Maxlength
        if (field.hasAttribute('maxlength')) {
            const max = parseInt(field.getAttribute('maxlength'));
            if (value.length > max) {
                this.setError(field, `Máximo ${max} caracteres`);
                return false;
            }
        }
        
        // Min (números)
        if (field.hasAttribute('min') && field.type === 'number') {
            const min = parseFloat(field.getAttribute('min'));
            if (parseFloat(value) < min) {
                this.setError(field, `El valor mínimo es ${min}`);
                return false;
            }
        }
        
        // Max (números)
        if (field.hasAttribute('max') && field.type === 'number') {
            const max = parseFloat(field.getAttribute('max'));
            if (parseFloat(value) > max) {
                this.setError(field, `El valor máximo es ${max}`);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Aplicar una regla de validación
     */
    applyRule(field, value, ruleName, params) {
        // Validadores personalizados
        if (this.customValidators[ruleName]) {
            const validator = this.customValidators[ruleName];
            if (!validator.fn(value, params, field)) {
                this.setError(field, validator.message);
                return false;
            }
            return true;
        }
        
        // Validadores integrados
        const validators = {
            required: () => {
                if (!value) {
                    this.setError(field, 'Este campo es obligatorio');
                    return false;
                }
                return true;
            },
            
            email: () => {
                if (!this.isValidEmail(value)) {
                    this.setError(field, 'Ingrese un correo electrónico válido');
                    return false;
                }
                return true;
            },
            
            min: () => {
                const min = parseInt(params[0]);
                if (value.length < min) {
                    this.setError(field, `Mínimo ${min} caracteres`);
                    return false;
                }
                return true;
            },
            
            max: () => {
                const max = parseInt(params[0]);
                if (value.length > max) {
                    this.setError(field, `Máximo ${max} caracteres`);
                    return false;
                }
                return true;
            },
            
            numeric: () => {
                if (!/^\d+$/.test(value)) {
                    this.setError(field, 'Solo se permiten números');
                    return false;
                }
                return true;
            },
            
            phone: () => {
                const phone = value.replace(/[^0-9]/g, '');
                if (phone.length < 7 || phone.length > 15) {
                    this.setError(field, 'Ingrese un teléfono válido');
                    return false;
                }
                return true;
            },
            
            cedula: () => {
                const cedula = value.replace(/[^0-9]/g, '');
                if (cedula.length < 6 || cedula.length > 12) {
                    this.setError(field, 'Ingrese una cédula válida');
                    return false;
                }
                return true;
            },
            
            confirmed: () => {
                const confirmField = this.form.querySelector(`[name="${field.name}_confirmation"]`);
                if (!confirmField || value !== confirmField.value) {
                    this.setError(field, 'La confirmación no coincide');
                    return false;
                }
                return true;
            },
            
            url: () => {
                try {
                    new URL(value);
                    return true;
                } catch {
                    this.setError(field, 'Ingrese una URL válida');
                    return false;
                }
            },
            
            date: () => {
                const date = new Date(value);
                if (isNaN(date.getTime())) {
                    this.setError(field, 'Ingrese una fecha válida');
                    return false;
                }
                return true;
            },
            
            in: () => {
                if (!params.includes(value)) {
                    this.setError(field, `El valor debe ser uno de: ${params.join(', ')}`);
                    return false;
                }
                return true;
            },
            
            alpha: () => {
                if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(value)) {
                    this.setError(field, 'Solo se permiten letras');
                    return false;
                }
                return true;
            },
            
            alphanumeric: () => {
                if (!/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/.test(value)) {
                    this.setError(field, 'Solo se permiten letras y números');
                    return false;
                }
                return true;
            }
        };
        
        if (validators[ruleName]) {
            return validators[ruleName]();
        }
        
        return true; // Regla desconocida, pasar
    }
    
    /**
     * Validar email
     */
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    /**
     * Establecer error en un campo
     */
    setError(field, message) {
        field.classList.add(this.options.errorClass);
        field.classList.remove(this.options.successClass);
        
        // Crear mensaje de error
        let errorDiv = field.parentElement.querySelector('.' + this.options.errorMessageClass);
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = this.options.errorMessageClass;
            field.parentElement.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        this.errors[field.name] = message;
    }
    
    /**
     * Limpiar estado de un campo
     */
    clearFieldState(field) {
        field.classList.remove(this.options.errorClass);
        field.classList.remove(this.options.successClass);
        
        const errorDiv = field.parentElement.querySelector('.' + this.options.errorMessageClass);
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
        
        delete this.errors[field.name];
    }
    
    /**
     * Limpiar todos los errores
     */
    clearAllErrors() {
        this.form.querySelectorAll('.' + this.options.errorClass).forEach(field => {
            this.clearFieldState(field);
        });
        this.errors = {};
    }
    
    /**
     * Obtener errores
     */
    getErrors() {
        return this.errors;
    }
    
    /**
     * Verificar si hay errores
     */
    hasErrors() {
        return Object.keys(this.errors).length > 0;
    }
}

// Función helper para uso rápido
function validateForm(formSelector, rules = {}) {
    const validator = new FormValidator(formSelector);
    validator.setRules(rules);
    return validator.validate();
}

// Exportar para uso con módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { FormValidator, validateForm };
}
