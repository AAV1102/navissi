/**
 * JS for Module: evaluaciones
 * Auto-generated for Atera integration
 */

document.addEventListener('DOMContentLoaded', function() {
    loadevaluacionesData();
});

function refreshevaluaciones() {
    loadevaluacionesData();
}

function loadevaluacionesData() {
    const tableBody = document.getElementById('table-body-evaluaciones');
    const tableHeader = document.getElementById('table-header-evaluaciones');

    if(!tableBody) return;

    tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';

    fetch('../../api/evaluaciones/crud.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderTable(data.data, tableHeader, tableBody);
            } else {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${data.error || 'Error cargando datos'}</td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error de conexión</td></tr>';
        });
}

function renderTable(data, header, body) {
    if (!data || data.length === 0) {
        body.innerHTML = '<tr><td colspan="100%" class="text-center py-4 text-muted">No hay datos disponibles</td></tr>';
        header.innerHTML = '<th>Estado</th>';
        return;
    }

    // Dynamic Headers
    const keys = Object.keys(data[0]);
    header.innerHTML = keys.map(k => `<th class="text-uppercase small fw-bold">${k}</th>`).join('') + '<th>ACCIONES</th>';

    // Dynamic Rows
    body.innerHTML = data.map(item => {
        const cells = keys.map(k => `<td>${item[k]}</td>`).join('');
        return `<tr>${cells}<td>
            <button class="btn btn-sm btn-link text-primary"><i class="fas fa-edit"></i></button>
            <button class="btn btn-sm btn-link text-danger"><i class="fas fa-trash"></i></button>
        </td></tr>`;
    }).join('');
}

function filterevaluaciones() {
    const input = document.getElementById('searchevaluaciones');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('table-evaluaciones');
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let visible = false;
        const tds = tr[i].getElementsByTagName('td');
        for (let j = 0; j < tds.length; j++) {
            if (tds[j] && tds[j].innerText.toLowerCase().indexOf(filter) > -1) {
                visible = true;
                break;
            }
        }
        tr[i].style.display = visible ? '' : 'none';
    }
}