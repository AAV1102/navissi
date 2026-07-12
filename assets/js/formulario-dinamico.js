/**
 * WorkManager ERP - Formulario Dinámico por Tipo de Tecnología
 * Habilita/deshabilita campos según el tipo de equipo seleccionado
 */

const FormularioDinamico = {
    // Configuración de campos por tipo de tecnología
    tiposCampos: {
        'PORTATIL': {
            requeridos: ['serial', 'placa', 'marca', 'modelo', 'sistema_operativo', 'ram', 'almacenamiento', 'procesador'],
            opcionales: ['mac_wifi', 'mac_ethernet', 'anydesk', 'rustdesk', 'teamviewer', 'office_version', 'antivirus'],
            componentes: ['mouse', 'teclado', 'base_refrigerante', 'maletin', 'monitor_adicional', 'docking_station'],
            icono: 'fa-laptop',
            color: '#3498db'
        },
        'ESCRITORIO': {
            requeridos: ['serial', 'placa', 'marca', 'modelo', 'procesador', 'ram', 'almacenamiento'],
            opcionales: ['sistema_operativo', 'mac_ethernet', 'anydesk', 'rustdesk', 'teamviewer', 'office_version'],
            componentes: ['monitor', 'teclado', 'mouse', 'parlantes', 'ups'],
            icono: 'fa-desktop',
            color: '#2ecc71'
        },
        'TODO_EN_UNO': {
            requeridos: ['serial', 'placa', 'marca', 'modelo', 'procesador', 'ram', 'almacenamiento', 'tamano_pantalla'],
            opcionales: ['sistema_operativo', 'mac_ethernet', 'mac_wifi', 'anydesk', 'rustdesk'],
            componentes: ['teclado', 'mouse', 'webcam'],
            icono: 'fa-tv',
            color: '#9b59b6'
        },
        'MONITOR': {
            requeridos: ['serial', 'placa', 'marca', 'modelo', 'tamano_pantalla'],
            opcionales: ['resolucion', 'tipo_panel', 'conexiones'],
            componentes: ['cable_hdmi', 'cable_vga', 'cable_displayport'],
            icono: 'fa-display',
            color: '#e74c3c'
        },
        'IMPRESORA': {
            requeridos: ['serial', 'placa', 'marca', 'modelo', 'tipo_impresora'],
            opcionales: ['ip_impresora', 'mac_impresora', 'es_red', 'es_multifuncional', 'tipo_toner'],
            componentes: ['cable_usb', 'cable_red', 'toner_extra'],
            icono: 'fa-print',
            color: '#f39c12'
        },
        'TELEFONO': {
            requeridos: ['serial', 'placa', 'marca', 'modelo'],
            opcionales: ['imei', 'numero_linea', 'operador', 'plan_datos'],
            componentes: ['cargador', 'audifonos', 'funda'],
            icono: 'fa-mobile-screen',
            color: '#1abc9c'
        },
        'TABLET': {
            requeridos: ['serial', 'placa', 'marca', 'modelo', 'almacenamiento'],
            opcionales: ['imei', 'sistema_operativo', 'tamano_pantalla'],
            componentes: ['cargador', 'funda', 'teclado_bluetooth', 'lapiz_stylus'],
            icono: 'fa-tablet-screen-button',
            color: '#e67e22'
        },
        'SERVIDOR': {
            requeridos: ['serial', 'placa', 'marca', 'modelo', 'procesador', 'ram', 'almacenamiento'],
            opcionales: ['ip_servidor', 'sistema_operativo', 'virtualizacion', 'raid'],
            componentes: ['ups', 'rack', 'cables_red'],
            icono: 'fa-server',
            color: '#34495e'
        },
        'RED': {
            requeridos: ['serial', 'placa', 'marca', 'modelo', 'tipo_red'],
            opcionales: ['ip_equipo', 'mac_equipo', 'puertos', 'velocidad'],
            componentes: ['cables_red', 'rack', 'patch_panel'],
            icono: 'fa-network-wired',
            color: '#16a085'
        },
        'CCTV': {
            requeridos: ['serial', 'placa', 'marca', 'modelo'],
            opcionales: ['ip_camara', 'resolucion', 'tipo_camara', 'almacenamiento_dvr'],
            componentes: ['fuente_poder', 'cable_bnc', 'soporte'],
            icono: 'fa-video',
            color: '#c0392b'
        },
        'TELEMEDICINA': {
            requeridos: ['serial', 'placa', 'marca', 'modelo', 'tipo_equipo_medico'],
            opcionales: ['registro_invima', 'calibracion', 'fecha_calibracion'],
            componentes: ['sensores', 'cables', 'accesorios_medicos'],
            icono: 'fa-stethoscope',
            color: '#27ae60'
        },
        'MOBILIARIO': {
            requeridos: ['placa', 'nombre_funcional', 'ubicacion'],
            opcionales: ['marca', 'modelo', 'material', 'dimensiones'],
            componentes: [],
            icono: 'fa-chair',
            color: '#8e44ad'
        },
        'OTRO': {
            requeridos: ['placa', 'nombre_funcional'],
            opcionales: ['serial', 'marca', 'modelo', 'descripcion'],
            componentes: [],
            icono: 'fa-box',
            color: '#7f8c8d'
        }
    },

    // Estados canónicos
    estados: {
        'disponible': { label: 'Disponible', color: '#27ae60', icon: 'fa-check-circle' },
        'asignado': { label: 'Asignado', color: '#3498db', icon: 'fa-user-check' },
        'mantenimiento': { label: 'Mantenimiento', color: '#f39c12', icon: 'fa-wrench' },
        'reparacion': { label: 'Reparación', color: '#e67e22', icon: 'fa-tools' },
        'baja': { label: 'Baja', color: '#e74c3c', icon: 'fa-times-circle' }
    },

    // Campos base siempre visibles
    camposBase: ['tipo_tecnologia', 'sede', 'ubicacion', 'estado', 'disponible'],

    // Inicializar formulario
    init: function(formId) {
        this.form = document.getElementById(formId);
        if (!this.form) {
            console.error('Formulario no encontrado:', formId);
            return;
        }

        this.bindEvents();
        this.cargarSedes();
        this.cargarEmpleados();
        
        // Si hay tipo seleccionado, actualizar campos
        const tipoSelect = this.form.querySelector('[name="tipo_tecnologia"]');
        if (tipoSelect && tipoSelect.value) {
            this.actualizarCampos(tipoSelect.value);
        }
    },

    // Bindear eventos
    bindEvents: function() {
        const self = this;

        // Cambio de tipo de tecnología
        const tipoSelect = this.form.querySelector('[name="tipo_tecnologia"]');
        if (tipoSelect) {
            tipoSelect.addEventListener('change', function() {
                self.actualizarCampos(this.value);
            });
        }

        // Cambio de estado
        const estadoSelect = this.form.querySelector('[name="estado"]');
        if (estadoSelect) {
            estadoSelect.addEventListener('change', function() {
                self.validarCamposEstado(this.value);
            });
        }

        // Validación en tiempo real
        this.form.querySelectorAll('input, select, textarea').forEach(function(field) {
            field.addEventListener('blur', function() {
                self.validarCampo(this);
            });
        });

        // Submit del formulario
        this.form.addEventListener('submit', function(e) {
            e.preventDefault();
            self.guardar();
        });
    },

    // Actualizar campos según tipo de tecnología
    actualizarCampos: function(tipo) {
        const config = this.tiposCampos[tipo];
        if (!config) {
            console.warn('Tipo no configurado:', tipo);
            return;
        }

        // Ocultar todos los campos opcionales
        this.form.querySelectorAll('.campo-dinamico').forEach(function(el) {
            el.style.display = 'none';
            el.querySelector('input, select, textarea')?.removeAttribute('required');
        });

        // Mostrar campos requeridos
        config.requeridos.forEach(campo => {
            const wrapper = this.form.querySelector(`[data-campo="${campo}"]`);
            if (wrapper) {
                wrapper.style.display = 'block';
                const input = wrapper.querySelector('input, select, textarea');
                if (input) input.setAttribute('required', 'required');
            }
        });

        // Mostrar campos opcionales
        config.opcionales.forEach(campo => {
            const wrapper = this.form.querySelector(`[data-campo="${campo}"]`);
            if (wrapper) {
                wrapper.style.display = 'block';
            }
        });

        // Actualizar sección de componentes
        this.actualizarComponentes(config.componentes);

        // Actualizar icono del tipo
        const iconoTipo = this.form.querySelector('.icono-tipo');
        if (iconoTipo) {
            iconoTipo.className = `fa-solid ${config.icono} icono-tipo`;
            iconoTipo.style.color = config.color;
        }
    },

    // Actualizar lista de componentes disponibles
    actualizarComponentes: function(componentes) {
        const container = this.form.querySelector('.componentes-container');
        if (!container) return;

        container.innerHTML = '';
        
        if (componentes.length === 0) {
            container.innerHTML = '<p class="text-muted">Este tipo de equipo no tiene componentes asociados</p>';
            return;
        }

        componentes.forEach(comp => {
            const div = document.createElement('div');
            div.className = 'form-check form-check-inline';
            div.innerHTML = `
                <input class="form-check-input" type="checkbox" name="componentes[]" value="${comp}" id="comp_${comp}">
                <label class="form-check-label" for="comp_${comp}">${this.formatearNombre(comp)}</label>
            `;
            container.appendChild(div);
        });
    },

    // Validar campos según estado
    validarCamposEstado: function(estado) {
        const fechaBaja = this.form.querySelector('[name="fecha_baja"]');
        const asignadoA = this.form.querySelector('[name="asignado_a"]');
        const fechaAsignacion = this.form.querySelector('[name="fecha_asignacion"]');

        // Reset
        if (fechaBaja) fechaBaja.removeAttribute('required');
        if (asignadoA) asignadoA.removeAttribute('required');
        if (fechaAsignacion) fechaAsignacion.removeAttribute('required');

        switch(estado) {
            case 'baja':
                if (fechaBaja) {
                    fechaBaja.setAttribute('required', 'required');
                    fechaBaja.closest('.campo-dinamico').style.display = 'block';
                }
                break;
            case 'asignado':
                if (asignadoA) {
                    asignadoA.setAttribute('required', 'required');
                    asignadoA.closest('.campo-dinamico').style.display = 'block';
                }
                if (fechaAsignacion) {
                    fechaAsignacion.setAttribute('required', 'required');
                    fechaAsignacion.closest('.campo-dinamico').style.display = 'block';
                }
                break;
        }
    },

    // Validar campo individual
    validarCampo: function(field) {
        const wrapper = field.closest('.form-group, .mb-3');
        if (!wrapper) return true;

        const feedback = wrapper.querySelector('.invalid-feedback');
        
        // Limpiar estado previo
        field.classList.remove('is-valid', 'is-invalid');

        // Validar requerido
        if (field.hasAttribute('required') && !field.value.trim()) {
            field.classList.add('is-invalid');
            if (feedback) feedback.textContent = 'Este campo es requerido';
            return false;
        }

        // Validaciones específicas
        const name = field.name;
        const value = field.value.trim();

        if (name === 'serial' && value) {
            if (value.length < 3) {
                field.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'El serial debe tener al menos 3 caracteres';
                return false;
            }
        }

        if (name === 'placa' && value) {
            if (!/^[A-Z0-9-]+$/i.test(value)) {
                field.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'La placa solo puede contener letras, números y guiones';
                return false;
            }
        }

        if (name === 'ip_impresora' || name === 'ip_equipo' || name === 'ip_servidor') {
            if (value && !/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(value)) {
                field.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'Formato de IP inválido';
                return false;
            }
        }

        if (name === 'mac_wifi' || name === 'mac_ethernet' || name === 'mac_impresora') {
            if (value && !/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/.test(value)) {
                field.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'Formato de MAC inválido (XX:XX:XX:XX:XX:XX)';
                return false;
            }
        }

        field.classList.add('is-valid');
        return true;
    },

    // Cargar sedes desde API
    cargarSedes: async function() {
        const select = this.form.querySelector('[name="sede"]');
        if (!select) return;

        try {
            const response = await fetch('/api/sedes/');
            const data = await response.json();
            
            if (data.success && data.data) {
                select.innerHTML = '<option value="">Seleccione sede...</option>';
                data.data.forEach(sede => {
                    select.innerHTML += `<option value="${sede.id}">${sede.nombre}</option>`;
                });
            }
        } catch (error) {
            console.error('Error cargando sedes:', error);
        }
    },

    // Cargar empleados desde API
    cargarEmpleados: async function() {
        const select = this.form.querySelector('[name="asignado_a"]');
        if (!select) return;

        try {
            const response = await fetch('/api/empleados/');
            const data = await response.json();
            
            if (data.success && data.data) {
                select.innerHTML = '<option value="">Sin asignar</option>';
                data.data.forEach(emp => {
                    select.innerHTML += `<option value="${emp.id}">${emp.nombre} - ${emp.cargo || 'Sin cargo'}</option>`;
                });
            }
        } catch (error) {
            console.error('Error cargando empleados:', error);
        }
    },

    // Guardar formulario
    guardar: async function() {
        // Validar todos los campos
        let valido = true;
        this.form.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
            if (!this.validarCampo(field)) {
                valido = false;
            }
        });

        if (!valido) {
            this.mostrarAlerta('Por favor complete todos los campos requeridos', 'warning');
            return;
        }

        // Verificar duplicados
        const serial = this.form.querySelector('[name="serial"]')?.value;
        const placa = this.form.querySelector('[name="placa"]')?.value;
        const id = this.form.querySelector('[name="id"]')?.value;

        if (serial || placa) {
            const duplicado = await this.verificarDuplicado(serial, placa, id);
            if (duplicado) {
                this.mostrarAlerta(`Ya existe un activo con este ${duplicado.campo}: ${duplicado.valor}`, 'danger');
                return;
            }
        }

        // Preparar datos
        const formData = new FormData(this.form);
        const datos = Object.fromEntries(formData.entries());
        
        // Componentes como array
        datos.componentes = formData.getAll('componentes[]');

        // Determinar si es crear o editar
        const esEdicion = !!id;
        const url = esEdicion ? `/api/inventario/${id}` : '/api/inventario/';
        const method = esEdicion ? 'PUT' : 'POST';

        try {
            this.mostrarCargando(true);
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(datos)
            });

            const result = await response.json();

            if (result.success) {
                this.mostrarAlerta(esEdicion ? 'Activo actualizado correctamente' : 'Activo creado correctamente', 'success');
                
                // Redirigir o recargar
                setTimeout(() => {
                    if (result.data?.id) {
                        window.location.href = `/dashboard.php?module=inventario&action=ver&id=${result.data.id}`;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                this.mostrarAlerta(result.message || 'Error al guardar', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            this.mostrarAlerta('Error de conexión', 'danger');
        } finally {
            this.mostrarCargando(false);
        }
    },

    // Verificar duplicado
    verificarDuplicado: async function(serial, placa, excludeId) {
        try {
            const params = new URLSearchParams();
            if (serial) params.append('serial', serial);
            if (placa) params.append('placa', placa);
            if (excludeId) params.append('exclude_id', excludeId);

            const response = await fetch(`/api/inventario/verificar-duplicado?${params}`);
            const data = await response.json();
            
            return data.duplicado ? data : null;
        } catch (error) {
            console.error('Error verificando duplicado:', error);
            return null;
        }
    },

    // Mostrar alerta
    mostrarAlerta: function(mensaje, tipo) {
        const container = document.querySelector('.alertas-container') || document.body;
        const alerta = document.createElement('div');
        alerta.className = `alert alert-${tipo} alert-dismissible fade show`;
        alerta.innerHTML = `
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        container.prepend(alerta);

        setTimeout(() => alerta.remove(), 5000);
    },

    // Mostrar/ocultar cargando
    mostrarCargando: function(mostrar) {
        const btn = this.form.querySelector('[type="submit"]');
        if (btn) {
            btn.disabled = mostrar;
            btn.innerHTML = mostrar 
                ? '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...'
                : '<i class="fa-solid fa-save"></i> Guardar';
        }
    },

    // Formatear nombre de campo
    formatearNombre: function(nombre) {
        return nombre.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    // Cargar datos de activo existente
    cargarActivo: async function(id) {
        try {
            const response = await fetch(`/api/inventario/${id}`);
            const data = await response.json();
            
            if (data.success && data.data) {
                const activo = data.data;
                
                // Llenar campos
                Object.keys(activo).forEach(key => {
                    const field = this.form.querySelector(`[name="${key}"]`);
                    if (field) {
                        if (field.type === 'checkbox') {
                            field.checked = activo[key] == 1;
                        } else {
                            field.value = activo[key] || '';
                        }
                    }
                });

                // Actualizar campos dinámicos
                if (activo.tipo_tecnologia) {
                    this.actualizarCampos(activo.tipo_tecnologia);
                }

                // Marcar componentes
                if (activo.componentes) {
                    activo.componentes.forEach(comp => {
                        const checkbox = this.form.querySelector(`[name="componentes[]"][value="${comp}"]`);
                        if (checkbox) checkbox.checked = true;
                    });
                }
            }
        } catch (error) {
            console.error('Error cargando activo:', error);
        }
    }
};

// Exportar para uso global
window.FormularioDinamico = FormularioDinamico;
