<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monkits Print Server</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:           #0e0e10;
            --surface:      #16161a;
            --surface-2:    #1e1e26;
            --border:       #2c2c3a;
            --border-light: #3a3a4e;
            --accent:       #f5a623;
            --accent-dim:   rgba(245,166,35,0.12);
            --accent-glow:  rgba(245,166,35,0.28);
            --text:         #d0d0c8;
            --text-dim:     #88889a;
            --text-muted:   #48485e;
            --ok:           #3dd68c;
            --err:          #f05050;
            --info:         #4fa8d5;
            --font-head:    'Barlow Condensed', sans-serif;
            --font-mono:    'IBM Plex Mono', monospace;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-mono);
            background-color: var(--bg);
            background-image: radial-gradient(circle, #24242f 1px, transparent 1px);
            background-size: 28px 28px;
            min-height: 100vh;
            color: var(--text);
        }

        /* ── HEADER ─────────────────────────────── */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(14,14,16,0.94);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            height: 56px;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            font-family: var(--font-head);
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--text);
        }
        .brand em { color: var(--accent); font-style: normal; }

        .status-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-head);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--ok);
        }
        .dot {
            width: 7px; height: 7px;
            background: var(--ok);
            border-radius: 50%;
            box-shadow: 0 0 6px var(--ok);
            animation: blink 2.2s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.35} }

        /* ── TOASTS ──────────────────────────────── */
        #toasts {
            position: fixed;
            top: 68px;
            right: 20px;
            z-index: 999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: none;
        }
        .toast {
            padding: 11px 16px;
            border-left: 3px solid;
            font-size: 11px;
            letter-spacing: 0.04em;
            background: var(--surface-2);
            min-width: 240px;
            max-width: 320px;
            animation: slideIn .18s ease-out;
            box-shadow: 0 6px 24px rgba(0,0,0,.5);
        }
        @keyframes slideIn {
            from { transform: translateX(16px); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }
        .toast.ok   { border-color: var(--ok);   color: var(--ok); }
        .toast.err  { border-color: var(--err);  color: var(--err); }
        .toast.info { border-color: var(--info); color: var(--info); }

        /* ── PAGE ────────────────────────────────── */
        .page {
            max-width: 960px;
            margin: 0 auto;
            padding: 28px 24px 72px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        /* ── PANEL ───────────────────────────────── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 22px 24px;
        }

        .panel-title {
            font-family: var(--font-head);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-title::before {
            content: '';
            width: 3px;
            height: 14px;
            background: var(--accent);
            flex-shrink: 0;
        }
        .panel-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ── GRID ────────────────────────────────── */
        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        /* ── FIELDS ──────────────────────────────── */
        .field { margin-bottom: 14px; }
        .field:last-of-type { margin-bottom: 0; }

        label {
            display: block;
            font-size: 10px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 6px;
        }

        select,
        input[type="text"],
        textarea {
            width: 100%;
            padding: 9px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            font-family: var(--font-mono);
            font-size: 12px;
            border-radius: 0;
            appearance: none;
            -webkit-appearance: none;
            transition: border-color .15s, box-shadow .15s;
        }
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2388889a'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 28px;
        }
        select:focus,
        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-dim);
        }
        textarea { resize: vertical; }

        .col-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* ── BUTTONS ─────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 20px;
            font-family: var(--font-head);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            border-radius: 0;
            transition: background .15s, color .15s, border-color .15s;
        }
        .btn-primary {
            background: var(--accent);
            color: #000;
            width: 100%;
            margin-top: 18px;
        }
        .btn-primary:hover  { background: #ffc14a; }
        .btn-primary:disabled {
            background: var(--border);
            color: var(--text-muted);
            cursor: not-allowed;
        }
        .btn-ghost {
            background: transparent;
            border: 1px solid var(--border-light);
            color: var(--text-dim);
        }
        .btn-ghost:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .btn-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* ── SPINNER ─────────────────────────────── */
        .spin {
            display: inline-block;
            width: 11px; height: 11px;
            border: 2px solid rgba(0,0,0,.2);
            border-top-color: #000;
            border-radius: 50%;
            animation: rot .65s linear infinite;
        }
        @keyframes rot { to { transform: rotate(360deg); } }

        /* ── PRINTERS LIST ───────────────────────── */
        .printers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 8px;
        }
        .pc {
            background: var(--bg);
            border: 1px solid var(--border);
            padding: 10px 13px;
        }
        .pc-name {
            font-size: 12px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 3px;
        }
        .pc-info {
            font-size: 10px;
            color: var(--text-muted);
            letter-spacing: 0.03em;
        }

        /* ── CODE BLOCK ──────────────────────────── */
        .code-block {
            background: var(--bg);
            border: 1px solid var(--border);
            padding: 16px;
            font-size: 11px;
            color: var(--text-dim);
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
            line-height: 1.75;
            max-height: 260px;
            overflow-y: auto;
        }

        /* ── API TABLE ───────────────────────────── */
        .api-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .api-table td {
            padding: 7px 10px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .api-table tr:last-child td { border-bottom: none; }
        .api-table td:first-child { white-space: nowrap; width: 1%; }
        .api-table td:last-child  { color: var(--text-muted); padding-left: 16px; }
        .tag {
            font-size: 9px;
            letter-spacing: .1em;
            padding: 2px 5px;
            border-radius: 2px;
            margin-right: 6px;
            font-family: var(--font-head);
            font-weight: 700;
        }
        .tag-post { background: rgba(61,214,140,.12); color: var(--ok); }
        .tag-get  { background: rgba(79,168,213,.12); color: var(--info); }
        .tag-put  { background: rgba(245,166,35,.12); color: var(--accent); }
        .endpoint { color: var(--text-dim); }

        /* ── RESPONSIVE ──────────────────────────── */
        @media (max-width: 620px) {
            .row-2, .col-2 { grid-template-columns: 1fr; }
            .header { padding: 0 16px; }
            .page { padding: 16px 14px 56px; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="brand">Monkits <em>Print</em> Server</div>
    <div class="status-pill"><div class="dot"></div>Sistema activo</div>
</header>

<div id="toasts"></div>

<main class="page">

    <!-- ── Impresoras ── -->
    <div class="row-2">

        <div class="panel">
            <div class="panel-title">Impresora Térmica</div>
            <div class="field">
                <label>Nombre de impresora</label>
                <select id="thermalPrinter"><option value="">Cargando...</option></select>
            </div>
            <div class="field">
                <label>Símbolo de moneda</label>
                <input type="text" id="currency" placeholder="$" maxlength="3" style="width:72px">
            </div>
            <button class="btn btn-primary" onclick="saveConfig(event)">Guardar Térmica</button>
        </div>

        <div class="panel">
            <div class="panel-title">Impresora Normal</div>
            <div class="field">
                <label>Nombre de impresora</label>
                <select id="normalPrinter"><option value="">Cargando...</option></select>
            </div>
            <div class="col-2">
                <div class="field">
                    <label>Tamaño de papel</label>
                    <select id="normalPaperSize">
                        <option value="Letter">Carta (Letter)</option>
                        <option value="A4">A4</option>
                        <option value="Legal">Legal</option>
                        <option value="A5">A5</option>
                    </select>
                </div>
                <div class="field">
                    <label>Calidad</label>
                    <select id="normalPrintQuality">
                        <option value="-1">Alta</option>
                        <option value="-2">Media</option>
                        <option value="-3">Baja</option>
                        <option value="-4">Borrador</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" onclick="saveConfig(event)">Guardar Normal</button>
        </div>

    </div>

    <!-- ── Impresoras disponibles ── -->
    <div class="panel">
        <div class="panel-title">Impresoras Disponibles</div>
        <div class="printers-grid" id="printersList">Cargando...</div>
    </div>

    <!-- ── Defaults + Pruebas ── -->
    <div class="row-2">

        <div class="panel">
            <div class="panel-title">Valores por Defecto</div>
            <div class="field">
                <label>Título del ticket</label>
                <input type="text" id="defaultTitle" placeholder="NOTA DE VENTA">
            </div>
            <div class="field">
                <label>Pie de página</label>
                <textarea id="defaultFooter" placeholder="Gracias por su compra" rows="3"></textarea>
            </div>
            <button class="btn btn-primary" onclick="saveConfig(event)">Guardar Defaults</button>
        </div>

        <div class="panel">
            <div class="panel-title">Pruebas de Impresión</div>
            <div class="btn-row">
                <button class="btn btn-ghost" onclick="testPrint('thermal')">Test Térmica</button>
                <button class="btn btn-ghost" onclick="testPrint('normal')">Test Normal</button>
                <button class="btn btn-ghost" onclick="testChars()">Test Chars</button>
                <button class="btn btn-ghost" onclick="refreshStatus()">Actualizar</button>
            </div>

            <div class="panel-title" style="margin-top:24px;">API Reference</div>
            <table class="api-table">
                <tr>
                    <td><span class="tag tag-post">POST</span><span class="endpoint">/api/print/thermal</span></td>
                    <td>Térmica ESC/POS</td>
                </tr>
                <tr>
                    <td><span class="tag tag-post">POST</span><span class="endpoint">/api/print/normal</span></td>
                    <td>Impresora normal</td>
                </tr>
                <tr>
                    <td><span class="tag tag-get">GET</span><span class="endpoint">/api/status</span></td>
                    <td>Estado e impresoras</td>
                </tr>
                <tr>
                    <td><span class="tag tag-get">GET</span><span class="endpoint">/api/config</span></td>
                    <td>Configuración actual</td>
                </tr>
                <tr>
                    <td><span class="tag tag-put">PUT</span><span class="endpoint">/api/config</span></td>
                    <td>Actualizar config</td>
                </tr>
            </table>
        </div>

    </div>

    <!-- ── Config JSON ── -->
    <div class="panel">
        <div class="panel-title">Configuración Actual</div>
        <div class="code-block" id="configDisplay">Cargando...</div>
    </div>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
    const API_BASE = '/api';

    function showAlert(message, type = 'ok') {
        const map = { success: 'ok', error: 'err', info: 'info', ok: 'ok', err: 'err' };
        const cls = map[type] || 'ok';
        const container = document.getElementById('toasts');
        const el = document.createElement('div');
        el.className = `toast ${cls}`;
        el.textContent = message;
        container.appendChild(el);
        setTimeout(() => {
            el.style.transition = 'opacity .25s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 280);
        }, 4000);
    }

    async function loadStatus() {
        try {
            const res = await fetch(API_BASE + '/status');
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error al cargar estado');

            const printers = data.printers || [];
            const config   = data.config   || {};

            // Lista de impresoras
            const list = document.getElementById('printersList');
            list.innerHTML = printers.length === 0
                ? '<p style="color:var(--text-muted);font-size:12px">No se encontraron impresoras instaladas</p>'
                : printers.map(p => `
                    <div class="pc">
                        <div class="pc-name">${p.Name || 'Unknown'}</div>
                        <div class="pc-info">${p.DriverName || 'N/A'} · ${p.PortName || 'N/A'}</div>
                    </div>`).join('');

            // Selectores
            const opts = '<option value="">Seleccionar...</option>' +
                printers.map(p => `<option value="${p.Name}">${p.Name}</option>`).join('');
            document.getElementById('thermalPrinter').innerHTML = opts;
            document.getElementById('normalPrinter').innerHTML  = opts;

            if (config.thermalPrinter) document.getElementById('thermalPrinter').value = config.thermalPrinter;
            if (config.normalPrinter)  document.getElementById('normalPrinter').value  = config.normalPrinter;

            document.getElementById('defaultTitle').value        = config.defaults?.title  || 'NOTA DE VENTA';
            document.getElementById('defaultFooter').value       = config.defaults?.footer || 'Gracias por su compra';
            document.getElementById('currency').value            = config.currency         || '$';
            document.getElementById('normalPaperSize').value     = config.normalPaperSize  || 'Letter';
            document.getElementById('normalPrintQuality').value  = config.normalPrintQuality ?? -1;

            document.getElementById('configDisplay').textContent = JSON.stringify(config, null, 2);

        } catch (err) {
            showAlert('Error al cargar: ' + err.message, 'err');
        }
    }

    async function saveConfig(evt) {
        const btn      = evt.target;
        const original = btn.textContent;
        btn.disabled   = true;
        btn.innerHTML  = '<span class="spin"></span> Guardando...';

        try {
            const payload = {
                thermalPrinter:    document.getElementById('thermalPrinter').value,
                normalPrinter:     document.getElementById('normalPrinter').value,
                currency:          document.getElementById('currency').value,
                normalPaperSize:   document.getElementById('normalPaperSize').value,
                normalPrintQuality: parseInt(document.getElementById('normalPrintQuality').value),
                defaults: {
                    title:  document.getElementById('defaultTitle').value,
                    footer: document.getElementById('defaultFooter').value,
                }
            };

            const res  = await fetch(API_BASE + '/config', {
                method:  'PUT',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error al guardar');

            showAlert('Configuración guardada', 'ok');
            document.getElementById('configDisplay').textContent = JSON.stringify(data.config, null, 2);

        } catch (err) {
            showAlert('Error: ' + err.message, 'err');
        } finally {
            btn.disabled  = false;
            btn.textContent = original;
        }
    }

    async function testPrint(type) {
        if (type === 'normal') { await testPrintNormal(); return; }

        try {
            const res = await fetch(API_BASE + '/print/thermal', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'nota_venta',
                    content: {
                        title: 'NOTA DE VENTA', folio: 'TEST-001',
                        fecha: new Date().toISOString().split('T')[0],
                        cliente: 'Cliente Prueba', vendedor: 'Sistema',
                        items: [{ modelo: 'TEST01', nombre: 'Producto de Prueba', cantidad: 1, precio: 100, subtotal: 100 }],
                        subtotal: 100, descuento: 0, tipo_descuento: 'monto',
                        envio: 0, total: 100,
                        pagos: [{ modo_pago: 'Prueba', monto: 100 }],
                        footer: 'Documento de Prueba', logo: null,
                    }
                }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error en prueba');
            showAlert('Enviado a: ' + data.printer, 'ok');
        } catch (err) {
            showAlert('Error: ' + err.message, 'err');
        }
    }

    async function testPrintNormal() {
        try {
            const res = await fetch(API_BASE + '/print/normal', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    content: {
                        title: 'NOTA DE VENTA',
                        folio: 'TEST-001',
                        fecha: new Date().toISOString().split('T')[0],
                        cliente: 'Cliente Prueba',
                        vendedor: 'Sistema',
                        items: [
                            { modelo: 'TEST01', nombre: 'Producto de Prueba', cantidad: 1, precio: 100, subtotal: 100 },
                            { modelo: 'TEST02', nombre: 'Otro Producto', cantidad: 2, precio: 50, subtotal: 100 }
                        ],
                        subtotal: 200,
                        descuento: 0,
                        envio: 0,
                        total: 200,
                        pagos: [{ modo_pago: 'Efectivo', monto: 200 }],
                        footer: 'Documento de Prueba'
                    }
                }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error en prueba');
            showAlert('Enviado a: ' + data.printer, 'ok');
        } catch (err) {
            showAlert('Error: ' + err.message, 'err');
        }
    }

    async function testChars() {
        try {
            const res  = await fetch(API_BASE + '/test/chars');
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error en test');
            showAlert('Test de caracteres enviado', 'ok');
        } catch (err) {
            showAlert('Error: ' + err.message, 'err');
        }
    }

    async function refreshStatus() {
        await loadStatus();
        showAlert('Estado actualizado', 'info');
    }

    document.addEventListener('DOMContentLoaded', loadStatus);
</script>
</body>
</html>
