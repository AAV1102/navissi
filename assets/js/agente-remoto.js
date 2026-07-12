/**
 * WorkManager ERP - Agente Remoto JavaScript
 * Funcionalidades para administración remota
 */

let sesionActual = null;
let terminalActivo = false;

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function () {
    // Actualizar estado de agentes cada 30 segundos
    setInterval(actualizarEstadoAgentes, 30000);

    // Configurar eventos del terminal
    const terminalCommand = document.getElementById('terminalCommand');
    if (terminalCommand) {
        terminalCommand.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                ejecutarComandoTerminal();
            }
        });
    }
});

// Registrar nuevo agente
document.getElementById('formRegistrarAgente')?.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const datos = {
        nombre: formData.get('nombre'),
        descripcion: formData.get('descripcion'),
        ip_address: formData.get('ip_address'),
        hostname: formData.get('hostname'),
        sistema_operativo: formData.get('sistema_operativo'),
        arquitectura: formData.get('arquitectura'),
        version_agente: '1.0.0',
        configuracion: {
            auto_update: true,
            log_level: 'info',
            max_sessions: 5
        },
        credenciales: {
            admin_user: formData.get('admin_user'),
            admin_password: formData.get('admin_password')
        },
        herramientas_remotas: formData.getAll('herramientas[]'),
        permisos: formData.getAll('permisos[]')
    };

    fetch('agente-remoto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=registrar_agente&datos=' + encodeURIComponent(JSON.stringify(datos))
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Agente registrado exitosamente', 'success');
                hideModal('modalRegistrarAgente');
                location.reload();
            } else {
                showNotification('Error al registrar agente', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
});

// Iniciar sesión remota
function iniciarSesionRemota(agenteId, herramienta, tipoSesion) {
    const data = new FormData();
    data.append('action', 'iniciar_sesion');
    data.append('agente_id', agenteId);
    data.append('herramienta', herramienta);
    data.append('tipo_sesion', tipoSesion);

    fetch('agente-remoto.php', {
        method: 'POST',
        body: data
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.sesion) {
                sesionActual = data.sesion;

                if (tipoSesion === 'terminal') {
                    abrirTerminalRemoto(herramienta, agenteId);
                } else {
                    // Para escritorio remoto, mostrar instrucciones
                    mostrarInstruccionesEscritorioRemoto(herramienta, agenteId);
                }

                showNotification('Sesión iniciada exitosamente', 'success');
            } else {
                showNotification('Error al iniciar sesión remota', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// Abrir terminal remoto
function abrirTerminalRemoto(herramienta, agenteId) {
    document.getElementById('terminalTitle').textContent = `Terminal Remoto - ${herramienta}`;
    document.getElementById('terminalOutput').innerHTML = '';

    // Agregar mensaje de bienvenida
    agregarLineaTerminal('WorkManager ERP - Terminal Remoto', 'system');
    agregarLineaTerminal(`Conectado al agente ID: ${agenteId}`, 'system');
    agregarLineaTerminal(`Herramienta: ${herramienta}`, 'system');
    agregarLineaTerminal('Escriba "help" para ver comandos disponibles', 'system');
    agregarLineaTerminal('', 'system');

    terminalActivo = true;
    showModal('modalTerminalRemoto');

    // Enfocar el input del terminal
    setTimeout(() => {
        document.getElementById('terminalCommand').focus();
    }, 100);
}

// Ejecutar comando en terminal
function ejecutarComandoTerminal() {
    const commandInput = document.getElementById('terminalCommand');
    const comando = commandInput.value.trim();

    if (!comando || !sesionActual) return;

    // Mostrar comando en terminal
    agregarLineaTerminal(`C:\\> ${comando}`, 'command');

    // Limpiar input
    commandInput.value = '';

    // Comandos especiales
    if (comando.toLowerCase() === 'help') {
        mostrarAyudaTerminal();
        return;
    }

    if (comando.toLowerCase() === 'clear') {
        document.getElementById('terminalOutput').innerHTML = '';
        return;
    }

    // Ejecutar comando remoto
    const data = new FormData();
    data.append('action', 'ejecutar_comando');
    data.append('sesion_id', sesionActual.session_id);
    data.append('comando', comando);

    fetch('agente-remoto.php', {
        method: 'POST',
        body: data
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.resultado) {
                agregarLineaTerminal(data.resultado.output, 'output');
                if (data.resultado.exit_code !== 0) {
                    agregarLineaTerminal(`Código de salida: ${data.resultado.exit_code}`, 'error');
                }
            } else {
                agregarLineaTerminal('Error al ejecutar comando', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            agregarLineaTerminal('Error de conexión', 'error');
        });
}

// Agregar línea al terminal
function agregarLineaTerminal(texto, tipo = 'output') {
    const output = document.getElementById('terminalOutput');
    const linea = document.createElement('div');
    linea.className = `terminal-line terminal-${tipo}`;
    linea.textContent = texto;
    output.appendChild(linea);

    // Scroll automático
    output.scrollTop = output.scrollHeight;
}

// Mostrar ayuda del terminal
function mostrarAyudaTerminal() {
    const comandos = [
        'help - Mostrar esta ayuda',
        'clear - Limpiar pantalla',
        'systeminfo - Información del sistema',
        'ipconfig - Configuración de red',
        'tasklist - Lista de procesos',
        'dir - Listar archivos y carpetas',
        'cd [ruta] - Cambiar directorio',
        'type [archivo] - Mostrar contenido de archivo',
        'ping [host] - Hacer ping a un host',
        'netstat - Mostrar conexiones de red',
        'services.msc - Abrir servicios',
        'regedit - Abrir editor del registro',
        'msconfig - Configuración del sistema',
        'devmgmt.msc - Administrador de dispositivos'
    ];

    agregarLineaTerminal('Comandos disponibles:', 'system');
    comandos.forEach(cmd => agregarLineaTerminal(`  ${cmd}`, 'help'));
    agregarLineaTerminal('', 'system');
}

// Ejecutar comando rápido
function ejecutarComandoRapido(agenteId, comando) {
    // Primero iniciar sesión temporal
    const data = new FormData();
    data.append('action', 'iniciar_sesion');
    data.append('agente_id', agenteId);
    data.append('herramienta', 'cmd_windows');
    data.append('tipo_sesion', 'terminal');

    fetch('agente-remoto.php', {
        method: 'POST',
        body: data
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.sesion) {
                // Ejecutar comando
                const cmdData = new FormData();
                cmdData.append('action', 'ejecutar_comando');
                cmdData.append('sesion_id', data.sesion.session_id);
                cmdData.append('comando', comando);

                return fetch('agente-remoto.php', {
                    method: 'POST',
                    body: cmdData
                });
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.resultado) {
                mostrarResultadoComando(data.resultado.output);
            } else {
                showNotification('Error al ejecutar comando', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// Mostrar resultado de comando
function mostrarResultado(resultadoBase64) {
    const resultado = atob(resultadoBase64);
    mostrarResultadoComando(resultado);
}

function mostrarResultadoComando(resultado) {
    document.getElementById('resultadoComando').textContent = resultado;
    showModal('modalResultadoComando');
}

// Mostrar menú avanzado
function mostrarMenuAvanzado(agenteId) {
    const opciones = [
        { texto: 'Abrir Registro (regedit)', comando: 'regedit' },
        { texto: 'Administrador de Dispositivos', comando: 'devmgmt.msc' },
        { texto: 'Servicios', comando: 'services.msc' },
        { texto: 'Configuración del Sistema', comando: 'msconfig' },
        { texto: 'Editor de Directivas de Grupo', comando: 'gpedit.msc' },
        { texto: 'Administrador de Tareas', comando: 'taskmgr' },
        { texto: 'Monitor de Rendimiento', comando: 'perfmon' },
        { texto: 'Visor de Eventos', comando: 'eventvwr' },
        { texto: 'Configuración de Red', comando: 'ncpa.cpl' },
        { texto: 'Panel de Control', comando: 'control' }
    ];

    let menu = '<div class="advanced-menu">';
    menu += '<h4>Herramientas Avanzadas del Sistema</h4>';
    menu += '<div class="menu-grid">';

    opciones.forEach(opcion => {
        menu += `<button class="btn btn-outline-primary menu-item" onclick="ejecutarComandoRapido(${agenteId}, '${opcion.comando}')">
                    <i class="fas fa-cog"></i> ${opcion.texto}
                 </button>`;
    });

    menu += '</div>';
    menu += '<div class="menu-actions">';
    menu += '<button class="btn btn-warning" onclick="reiniciarSistema(' + agenteId + ')"><i class="fas fa-redo"></i> Reiniciar Sistema</button>';
    menu += '<button class="btn btn-info" onclick="actualizarSistema(' + agenteId + ')"><i class="fas fa-download"></i> Buscar Actualizaciones</button>';
    menu += '<button class="btn btn-success" onclick="limpiarSistema(' + agenteId + ')"><i class="fas fa-broom"></i> Limpiar Sistema</button>';
    menu += '</div>';
    menu += '</div>';

    showCustomModal('Herramientas Avanzadas', menu);
}

// Mostrar instrucciones para escritorio remoto
function mostrarInstruccionesEscritorioRemoto(herramienta, agenteId) {
    let contenido = '';

    switch (herramienta) {
        case 'rdp_windows':
            contenido = `
                <div class="remote-instructions">
                    <h4><i class="fas fa-desktop"></i> Conexión RDP</h4>
                    <p>Para conectarte por Escritorio Remoto:</p>
                    <ol>
                        <li>Abre "Conexión a Escritorio Remoto" (mstsc.exe)</li>
                        <li>Ingresa la IP del equipo remoto</li>
                        <li>Usa las credenciales de administrador configuradas</li>
                        <li>Haz clic en "Conectar"</li>
                    </ol>
                    <div class="connection-info">
                        <strong>IP:</strong> <span id="rdp-ip">Cargando...</span><br>
                        <strong>Puerto:</strong> 3389<br>
                        <strong>Usuario:</strong> <span id="rdp-user">Cargando...</span>
                    </div>
                    <button class="btn btn-primary" onclick="abrirRDP()">
                        <i class="fas fa-external-link-alt"></i> Abrir RDP
                    </button>
                </div>
            `;
            break;

        case 'tightvnc':
            contenido = `
                <div class="remote-instructions">
                    <h4><i class="fas fa-eye"></i> Conexión VNC</h4>
                    <p>Para conectarte por VNC:</p>
                    <ol>
                        <li>Abre TightVNC Viewer</li>
                        <li>Ingresa la IP:Puerto del equipo remoto</li>
                        <li>Ingresa la contraseña VNC configurada</li>
                        <li>Haz clic en "Connect"</li>
                    </ol>
                    <div class="connection-info">
                        <strong>Servidor:</strong> <span id="vnc-server">Cargando...</span><br>
                        <strong>Puerto:</strong> 5900
                    </div>
                    <button class="btn btn-info" onclick="descargarVNC()">
                        <i class="fas fa-download"></i> Descargar TightVNC
                    </button>
                </div>
            `;
            break;

        case 'rustdesk':
            contenido = `
                <div class="remote-instructions">
                    <h4><i class="fas fa-share-square"></i> Conexión RustDesk</h4>
                    <p>Para conectarte con RustDesk:</p>
                    <ol>
                        <li>Abre RustDesk</li>
                        <li>Ingresa el ID del equipo remoto</li>
                        <li>Haz clic en "Connect"</li>
                        <li>Ingresa la contraseña cuando se solicite</li>
                    </ol>
                    <div class="connection-info">
                        <strong>ID:</strong> <span id="rustdesk-id">Cargando...</span>
                    </div>
                    <button class="btn btn-success" onclick="descargarRustDesk()">
                        <i class="fas fa-download"></i> Descargar RustDesk
                    </button>
                </div>
            `;
            break;

        case 'anydesk':
            contenido = `
                <div class="remote-instructions">
                    <h4><i class="fas fa-link"></i> Conexión AnyDesk</h4>
                    <p>Para conectarte con AnyDesk:</p>
                    <ol>
                        <li>Abre AnyDesk</li>
                        <li>Ingresa el ID del equipo remoto</li>
                        <li>Haz clic en "Connect"</li>
                        <li>Acepta la conexión en el equipo remoto</li>
                    </ol>
                    <div class="connection-info">
                        <strong>ID:</strong> <span id="anydesk-id">Cargando...</span>
                    </div>
                    <button class="btn btn-warning" onclick="descargarAnyDesk()">
                        <i class="fas fa-download"></i> Descargar AnyDesk
                    </button>
                </div>
            `;
            break;
    }

    showCustomModal('Conexión Remota', contenido);

    // Cargar información del agente
    cargarInfoConexion(agenteId, herramienta);
}

// Cargar información de conexión
function cargarInfoConexion(agenteId, herramienta) {
    // Aquí cargarías la información específica del agente
    // Por ahora usamos datos de ejemplo
    setTimeout(() => {
        const elements = {
            'rdp-ip': '192.168.1.100',
            'rdp-user': 'Administrator',
            'vnc-server': '192.168.1.100:5900',
            'rustdesk-id': '123456789',
            'anydesk-id': '987654321'
        };

        Object.keys(elements).forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = elements[id];
            }
        });
    }, 500);
}

// Funciones de descarga
function descargarVNC() {
    window.open('https://www.tightvnc.com/download.php', '_blank');
}

function descargarRustDesk() {
    window.open('https://github.com/rustdesk/rustdesk/releases', '_blank');
}

function descargarAnyDesk() {
    window.open('https://anydesk.com/download', '_blank');
}

// Abrir RDP
function abrirRDP() {
    // En un entorno real, esto abriría la aplicación RDP
    showNotification('Abriendo Conexión a Escritorio Remoto...', 'info');
}

// Funciones del sistema
function reiniciarSistema(agenteId) {
    if (confirm('¿Estás seguro de que deseas reiniciar el sistema remoto?')) {
        ejecutarComandoRapido(agenteId, 'shutdown /r /t 60 /c "Reinicio programado desde WorkManager ERP"');
        showNotification('Comando de reinicio enviado (60 segundos)', 'warning');
    }
}

function actualizarSistema(agenteId) {
    if (confirm('¿Deseas buscar e instalar actualizaciones del sistema?')) {
        ejecutarComandoRapido(agenteId, 'powershell "Get-WindowsUpdate -Install -AcceptAll -AutoReboot"');
        showNotification('Buscando actualizaciones...', 'info');
    }
}

function limpiarSistema(agenteId) {
    if (confirm('¿Deseas ejecutar la limpieza del sistema?')) {
        ejecutarComandoRapido(agenteId, 'cleanmgr /sagerun:1');
        showNotification('Iniciando limpieza del sistema...', 'info');
    }
}

// Actualizar estado de agentes
function actualizarEstadoAgentes() {
    fetch('agente-remoto.php?action=get_agentes')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar indicadores de estado sin recargar la página
                data.agentes.forEach(agente => {
                    const card = document.querySelector(`[data-agente-id="${agente.id}"]`);
                    if (card) {
                        const statusBadge = card.querySelector('.status-badge');
                        if (statusBadge) {
                            statusBadge.className = `status-badge status-${agente.estado_actual}`;
                            statusBadge.textContent = agente.estado_actual.charAt(0).toUpperCase() + agente.estado_actual.slice(1);
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error actualizando estado:', error);
        });
}

// Terminar sesión
function terminarSesion(sesionId) {
    if (confirm('¿Deseas terminar esta sesión remota?')) {
        // Implementar terminación de sesión
        showNotification('Sesión terminada', 'info');
        location.reload();
    }
}

// Funciones de modal personalizadas
function showCustomModal(title, content) {
    // Crear modal dinámico
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.id = 'customModal';
    modal.innerHTML = `
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="modal-close" onclick="hideCustomModal()">&times;</button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    modal.style.display = 'flex';
}

function hideCustomModal() {
    const modal = document.getElementById('customModal');
    if (modal) {
        modal.remove();
    }
}

// Funciones de utilidad
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Cerrar dropdowns al hacer clic fuera
document.addEventListener('click', function (e) {
    if (!e.target.matches('.dropdown-toggle')) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});