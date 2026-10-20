// ===== Utilidades generales =====
const API = window.BASE_URL + '/reservas';
const mensaje = document.getElementById('mensaje');

// Muestra un mensaje: tipo = 'exito' | 'error' | 'cargando'
function mostrarMensaje(texto, tipo) {
    mensaje.textContent = texto;   // textContent evita inyectar HTML (XSS)
    mensaje.className = 'mensaje ' + tipo;
}

function ocultarMensaje() {
    mensaje.className = 'mensaje oculto';
}

// Llamada a la API con manejo de errores de red y de respuestas 400/500
async function llamarApi(url, opciones = {}) {
    opciones.headers = Object.assign(
        { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        opciones.headers
    );

    let respuesta;
    try {
        respuesta = await fetch(url, opciones);
    } catch (e) {
        // No hubo respuesta: servidor caído o sin conexión
        throw { mensaje: 'No se pudo conectar con el servidor. Revisa tu conexión.' };
    }

    let cuerpo = null;
    try {
        cuerpo = await respuesta.json();
    } catch (e) {
        cuerpo = null;
    }

    if (!respuesta.ok) {
        throw {
            estado: respuesta.status,
            mensaje: (cuerpo && cuerpo.error) || 'Error inesperado (' + respuesta.status + ')',
            errores: (cuerpo && cuerpo.errores) || {}
        };
    }
    return cuerpo;
}

// Escapa texto antes de meterlo en innerHTML (protección XSS)
function esc(texto) {
    const div = document.createElement('div');
    div.textContent = texto == null ? '' : String(texto);
    return div.innerHTML;
}

function formatearFecha(iso) {
    const [a, m, d] = iso.split('-');
    return d + '/' + m + '/' + a;
}

function formatearImporte(valor) {
    return Number(valor).toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' €';
}


// ===== Resumen: cuenta las reservas por estado =====
function actualizarResumen(reservas) {
    const contar = estado => reservas.filter(r => r.estado === estado).length;
    const poner = (id, valor) => {
        const el = document.getElementById(id);
        if (el) el.textContent = valor;
    };
    poner('res-total', reservas.length);
    poner('res-pendiente', contar('pendiente'));
    poner('res-confirmada', contar('confirmada'));
    poner('res-cancelada', contar('cancelada'));
}

// ===== Pintar la tabla =====
function pintarTabla(reservas) {
    actualizarResumen(reservas);   // actualiza las tarjetas de resumen

    const tbody = document.getElementById('tabla-reservas');
    if (!tbody) return;

    if (reservas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8">No hay reservas.</td></tr>';
        return;
    }

    tbody.innerHTML = reservas.map(r => `
        <tr>
            <td>${esc(r.id)}</td>
            <td>${esc(r.nombre_huesped)}</td>
            <td>${esc(r.nombre_alojamiento)}</td>
            <td>${formatearFecha(r.fecha_entrada)}</td>
            <td>${formatearFecha(r.fecha_salida)}</td>
            <td><span class="estado ${esc(r.estado)}">${esc(r.estado)}</span></td>
            <td>${formatearImporte(r.importe)}</td>
            <td>
                <a class="boton secundario" href="${window.BASE_URL}/reservas/${esc(r.id)}">Ver</a>
                ${r.estado !== 'cancelada'
                    ? `<button type="button" class="boton peligro btn-cancelar" data-id="${esc(r.id)}">Cancelar</button>`
                    : ''}
            </td>
        </tr>
    `).join('');
}

// ===== 1) Filtrar sin recargar =====
async function filtrar() {
    const form = document.getElementById('form-filtros');
    const params = new URLSearchParams();
    new FormData(form).forEach((valor, clave) => {
        if (valor !== '') params.append(clave, valor);
    });

    mostrarMensaje('Cargando reservas...', 'cargando');
    try {
        const resp = await llamarApi(API + '?' + params.toString());
        pintarTabla(resp.datos);
        ocultarMensaje();
    } catch (e) {
        mostrarMensaje(e.mensaje, 'error');
    }
}

// ===== 2) Crear reserva =====
function limpiarErrores(form) {
    form.querySelectorAll('.error-campo').forEach(el => el.textContent = '');
    form.querySelectorAll('.invalido').forEach(el => el.classList.remove('invalido'));
}

function ponerError(form, campo, texto) {
    const contenedor = form.querySelector('.error-campo[data-campo="' + campo + '"]');
    if (contenedor) contenedor.textContent = texto;
    const input = form.elements[campo];
    if (input) input.classList.add('invalido');
}

// Validación en el navegador (la del servidor sigue siendo la que manda)
function validarCliente(datos) {
    const errores = {};
    ['nombre_huesped', 'email_huesped', 'nombre_alojamiento', 'fecha_entrada', 'fecha_salida', 'importe']
        .forEach(c => {
            if (!datos[c] || String(datos[c]).trim() === '') errores[c] = 'El campo es obligatorio';
        });

    if (!errores.email_huesped && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.email_huesped)) {
        errores.email_huesped = 'El email no es válido';
    }
    if (!errores.fecha_entrada && !errores.fecha_salida && datos.fecha_salida <= datos.fecha_entrada) {
        errores.fecha_salida = 'La salida debe ser posterior a la entrada';
    }
    if (!errores.importe && Number(datos.importe) <= 0) {
        errores.importe = 'El importe debe ser mayor que 0';
    }
    return errores;
}

async function crearReserva(evento) {
    evento.preventDefault();
    const form = evento.target;
    limpiarErrores(form);

    const datos = Object.fromEntries(new FormData(form).entries());

    const errores = validarCliente(datos);
    if (Object.keys(errores).length > 0) {
        Object.entries(errores).forEach(([c, t]) => ponerError(form, c, t));
        mostrarMensaje('Revisa los campos marcados.', 'error');
        return;
    }

    const boton = form.querySelector('button[type="submit"]');
    boton.disabled = true;
    mostrarMensaje('Creando reserva...', 'cargando');

    try {
        await llamarApi(API, { method: 'POST', body: JSON.stringify(datos) });
        form.reset();
        mostrarMensaje('Reserva creada correctamente.', 'exito');
        await refrescarListado();
    } catch (e) {
        // Errores de validación que devuelve el servidor (400)
        Object.entries(e.errores || {}).forEach(([c, t]) => ponerError(form, c, t));
        mostrarMensaje(e.mensaje, 'error');
    } finally {
        boton.disabled = false;
    }
}

// Vuelve a pedir el listado respetando los filtros actuales
async function refrescarListado() {
    const mensajeActual = { texto: mensaje.textContent, clase: mensaje.className };
    const params = new URLSearchParams();
    new FormData(document.getElementById('form-filtros')).forEach((v, k) => {
        if (v !== '') params.append(k, v);
    });
    try {
        const resp = await llamarApi(API + '?' + params.toString());
        pintarTabla(resp.datos);
        mensaje.textContent = mensajeActual.texto;
        mensaje.className = mensajeActual.clase;
    } catch (e) {
        mostrarMensaje(e.mensaje, 'error');
    }
}

// ===== 3) Cancelar reserva (con confirmación) =====
async function cancelarReserva(id) {
    if (!window.confirm('¿Seguro que quieres cancelar la reserva #' + id + '?')) return;

    mostrarMensaje('Cancelando reserva...', 'cargando');
    try {
        await llamarApi(API + '/' + id + '/estado', {
            method: 'PUT',
            body: JSON.stringify({ estado: 'cancelada' })
        });
        mostrarMensaje('Reserva cancelada.', 'exito');

        if (document.getElementById('tabla-reservas')) {
            await refrescarListado();       // estamos en el listado
        } else {
            window.location.reload();       // estamos en el detalle
        }
    } catch (e) {
        mostrarMensaje(e.mensaje, 'error');
    }
}

// ===== Conectar eventos (sin JS inline en el HTML) =====
document.addEventListener('DOMContentLoaded', () => {
    const formFiltros = document.getElementById('form-filtros');
    if (formFiltros) {
        formFiltros.addEventListener('submit', e => { e.preventDefault(); filtrar(); });
        formFiltros.addEventListener('reset', () => setTimeout(filtrar, 0));
    }

    const formCrear = document.getElementById('form-crear');
    if (formCrear) formCrear.addEventListener('submit', crearReserva);

    // Delegación de eventos: funciona también con filas pintadas después por JS
    document.addEventListener('click', e => {
        const boton = e.target.closest('.btn-cancelar');
        if (boton) cancelarReserva(boton.dataset.id);
    });
});