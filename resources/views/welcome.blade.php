<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monkits Print Server - Configuración</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 800px;
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            background: #10b981;
            color: white;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section:last-child {
            border-bottom: none;
        }

        .section h2 {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #555;
            margin-bottom: 6px;
        }

        select, input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
        }

        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e3a8a;
            border-left: 4px solid #3b82f6;
        }

        .printers-list {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
            font-size: 12px;
            color: #666;
            max-height: 200px;
            overflow-y: auto;
        }

        .printer-item {
            padding: 8px;
            margin-bottom: 6px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }

        .printer-name {
            font-weight: 600;
            color: #333;
        }

        .printer-info {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #f3f4f6;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .config-display {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 12px;
            color: #333;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🖨️ Monkits Print Server</h1>
        <p class="subtitle">Configuración de impresoras térmicas y normales</p>
        <div class="status-badge">✓ Servidor activo</div>

        <div id="alert-container"></div>

        <!-- Sección 1: Configuración de Impresoras -->
        <div class="section">
            <h2>⚙️ Configuración de Impresoras</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="thermalPrinter">Impresora Térmica (POS-58)</label>
                    <select id="thermalPrinter">
                        <option value="">Cargando...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="normalPrinter">Impresora Normal (Carta)</label>
                    <select id="normalPrinter">
                        <option value="">Cargando...</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="currency">Símbolo de Moneda</label>
                <input type="text" id="currency" placeholder="$" maxlength="3" style="width: 80px;">
            </div>
            <div class="form-group">
                <label>Impresoras Disponibles</label>
                <div class="printers-list" id="printersList">Cargando...</div>
            </div>
            <button class="btn-primary" onclick="saveConfig()">💾 Guardar Configuración</button>
        </div>

        <!-- Sección 2: Valores por Defecto -->
        <div class="section">
            <h2>📋 Valores por Defecto</h2>
            <div class="form-group">
                <label for="defaultTitle">Título</label>
                <input type="text" id="defaultTitle" placeholder="NOTA DE VENTA">
            </div>
            <div class="form-group">
                <label for="defaultFooter">Pie de página</label>
                <textarea id="defaultFooter" placeholder="Gracias por su compra" rows="3"></textarea>
            </div>
        </div>

        <!-- Sección 3: Pruebas -->
        <div class="section">
            <h2>🧪 Pruebas de Impresión</h2>
            <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Envía un documento de prueba a cada impresora para verificar que funciona correctamente.</p>
            <div class="btn-group">
                <button class="btn-secondary" onclick="testPrint('thermal')">🧾 Probar Térmica</button>
                <button class="btn-secondary" onclick="testPrint('normal')">📄 Probar Normal</button>
                <button class="btn-secondary" onclick="testChars()">🔤 Test Caracteres</button>
                <button class="btn-secondary" onclick="refreshStatus()">🔄 Actualizar Estado</button>
            </div>
        </div>

        <!-- Sección 4: Información del Servidor -->
        <div class="section">
            <h2>ℹ️ Configuración Actual</h2>
            <div class="config-display" id="configDisplay">Cargando...</div>
        </div>

        <!-- Sección 5: API Reference -->
        <div class="section">
            <h2>📚 Referencia API</h2>
            <p style="color: #666; font-size: 13px; margin-bottom: 10px;">Endpoints disponibles para el cliente Monkits:</p>
            <ul style="color: #666; font-size: 12px; margin-left: 20px;">
                <li><code>POST /api/print/thermal</code> — Imprimir en térmica</li>
                <li><code>POST /api/print/normal</code> — Imprimir en carta</li>
                <li><code>GET /api/status</code> — Lista de impresoras y config</li>
                <li><code>GET /api/config</code> — Configuración actual</li>
                <li><code>PUT /api/config</code> — Actualizar configuración</li>
            </ul>
        </div>
    </div>

    <script>
        const API_BASE = '/api';

        // Función para mostrar alertas
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alertContainer.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        // Cargar estado al iniciar
        async function loadStatus() {
            try {
                const res = await fetch(API_BASE + '/status');
                const data = await res.json();

                if (!res.ok) throw new Error(data.error || 'Error al cargar estado');

                // Llenar selectores de impresoras
                const printers = data.printers || [];
                const config = data.config || {};

                // Actualizar lista visual de impresoras
                const printersList = document.getElementById('printersList');
                if (printers.length === 0) {
                    printersList.innerHTML = '<p style="color: #999;">No se encontraron impresoras instaladas</p>';
                } else {
                    printersList.innerHTML = printers.map(p => `
                        <div class="printer-item">
                            <div class="printer-name">${p.Name || 'Unknown'}</div>
                            <div class="printer-info">Driver: ${p.DriverName || 'N/A'} | Puerto: ${p.PortName || 'N/A'}</div>
                        </div>
                    `).join('');
                }

                // Llenar selectores
                const thermalSelect = document.getElementById('thermalPrinter');
                const normalSelect = document.getElementById('normalPrinter');

                thermalSelect.innerHTML = '<option value="">Seleccionar...</option>' + printers.map(p =>
                    `<option value="${p.Name}">${p.Name}</option>`
                ).join('');

                normalSelect.innerHTML = '<option value="">Seleccionar...</option>' + printers.map(p =>
                    `<option value="${p.Name}">${p.Name}</option>`
                ).join('');

                // Preseleccionar valores actuales
                if (config.thermalPrinter) thermalSelect.value = config.thermalPrinter;
                if (config.normalPrinter) normalSelect.value = config.normalPrinter;

                // Cargar valores por defecto
                document.getElementById('defaultTitle').value = config.defaults?.title || 'NOTA DE VENTA';
                document.getElementById('defaultFooter').value = config.defaults?.footer || 'Gracias por su compra';
                document.getElementById('currency').value = config.currency || '$';

                // Mostrar configuración actual
                document.getElementById('configDisplay').textContent = JSON.stringify(config, null, 2);

            } catch (error) {
                showAlert('Error al cargar impresoras: ' + error.message, 'error');
            }
        }

        // Guardar configuración
        async function saveConfig() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Guardando...';

            try {
                const payload = {
                    thermalPrinter: document.getElementById('thermalPrinter').value,
                    normalPrinter: document.getElementById('normalPrinter').value,
                    currency: document.getElementById('currency').value,
                    defaults: {
                        title: document.getElementById('defaultTitle').value,
                        footer: document.getElementById('defaultFooter').value
                    }
                };

                const res = await fetch(API_BASE + '/config', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error al guardar');

                showAlert('✓ Configuración guardada exitosamente', 'success');
                document.getElementById('configDisplay').textContent = JSON.stringify(data.config, null, 2);

            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '💾 Guardar Configuración';
            }
        }

        // Prueba de impresión
        async function testPrint(type) {
            const samplePayload = {
                type: 'nota_venta',
                content: {
                    title: 'NOTA DE VENTA',
                    folio: 'TEST-001',
                    fecha: new Date().toISOString().split('T')[0],
                    cliente: 'Cliente Prueba',
                    vendedor: 'Sistema',
                    items: [
                        { modelo: 'TEST01', nombre: 'Producto de Prueba', cantidad: 1, precio: 100, subtotal: 100 }
                    ],
                    subtotal: 100,
                    descuento: 0,
                    tipo_descuento: 'monto',
                    envio: 0,
                    total: 100,
                    pagos: [{ modo_pago: 'Prueba', monto: 100 }],
                    footer: 'Documento de Prueba',
                    logo: null
                }
            };

            try {
                const endpoint = type === 'thermal' ? '/print/thermal' : '/print/normal';
                const res = await fetch(API_BASE + endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(samplePayload)
                });

                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error en prueba');

                showAlert(`✓ Trabajo de ${type === 'thermal' ? 'impresión térmica' : 'impresión normal'} enviado a: ${data.printer}`, 'success');

            } catch (error) {
                showAlert('Error en prueba: ' + error.message, 'error');
            }
        }

        // Prueba de caracteres
        async function testChars() {
            try {
                const res = await fetch(API_BASE + '/test/chars', {
                    method: 'GET'
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error en test');

                showAlert('✓ Test de caracteres enviado', 'success');
            } catch (error) {
                showAlert('Error en test chars: ' + error.message, 'error');
            }
        }

        // Actualizar estado
        async function refreshStatus() {
            await loadStatus();
            showAlert('✓ Estado actualizado', 'success');
        }

        // Cargar estado al iniciar la página
        document.addEventListener('DOMContentLoaded', loadStatus);
    </script>
</body>
</html>
