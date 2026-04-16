<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Venta</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 10.1pt;
            color: #000;
            line-height: 1.4;
        }

        @page {
            size: letter;
            margin: 13.8mm 12.9mm;
        }

        .container {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* Encabezado */
        .header {
            text-align: center;
            margin-bottom: 18.4pt;
            border-bottom: 2px solid #000;
            padding-bottom: 9.2pt;
        }

        .header h1 {
            font-size: 16.6pt;
            font-weight: bold;
            margin-bottom: 4.6pt;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            font-size: 9.2pt;
            margin-top: 9.2pt;
        }

        .header-info div {
            flex: 1;
        }

        /* Cliente */
        .client-info {
            margin-bottom: 13.8pt;
            padding: 7.36pt;
            background-color: #f5f5f5;
            border: 1px solid #ccc;
        }

        .client-info p {
            margin: 2.76pt 0;
            font-size: 9.2pt;
        }

        /* Tabla de productos */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 13.8pt;
            font-size: 9.2pt;
        }

        thead {
            background-color: #000;
            color: #fff;
        }

        thead th {
            padding: 7.36pt;
            text-align: left;
            font-weight: bold;
            font-size: 9.2pt;
            border: 1px solid #000;
        }

        tbody td {
            padding: 5.52pt 7.36pt;
            border-bottom: 1px solid #ddd;
            font-size: 9.2pt;
        }

        tbody tr:last-child td {
            border-bottom: 2px solid #000;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Sección de producto */
        .item-name {
            font-size: 8.28pt;
            color: #666;
            font-style: italic;
        }

        /* Totales */
        .totals {
            width: 50%;
            margin-left: auto;
            margin-bottom: 13.8pt;
            font-size: 10.1pt;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 4.6pt 0;
            border-bottom: 1px solid #ddd;
        }

        .totals-row.total {
            font-weight: bold;
            border-bottom: 2px solid #000;
            font-size: 11.04pt;
            padding: 7.36pt 0;
            margin-top: 4.6pt;
        }

        .totals-row span:first-child {
            flex: 1;
        }

        .totals-row span:last-child {
            width: 73.6pt;
            text-align: right;
        }

        /* Pagos */
        .payments {
            margin-bottom: 13.8pt;
            padding: 7.36pt;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }

        .payments-title {
            font-weight: bold;
            margin-bottom: 4.6pt;
            font-size: 9.2pt;
        }

        .payment-item {
            display: flex;
            justify-content: space-between;
            font-size: 9.2pt;
            margin: 2.76pt 0;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 18.4pt;
            padding-top: 9.2pt;
            border-top: 1px solid #000;
            font-size: 8.28pt;
            color: #666;
        }

        @media print {
            @page {
                size: letter;
                margin: 13.8mm 12.9mm;
            }
        }
    </style>
</head>
<body>
    <div class="container">
    {{-- Escala del PDF: {{ $scale ?? 1.0 }} --}}
        <!-- Encabezado -->
        <div class="header">
            <h1>{{ strtoupper($content['title'] ?? 'NOTA DE VENTA') }}</h1>
            <div class="header-info">
                <div>
                    <strong>Folio:</strong> {{ $content['folio'] ?? '' }}
                </div>
                <div style="text-align: center;">
                    <strong>Fecha:</strong> {{ $content['fecha'] ?? '' }}
                </div>
                <div style="text-align: right;">
                    <strong>Vendedor:</strong> {{ $content['vendedor'] ?? '' }}
                </div>
            </div>
        </div>

        <!-- Información del cliente -->
        <div class="client-info">
            <p><strong>Cliente:</strong> {{ $content['cliente'] ?? 'N/A' }}</p>
        </div>

        <!-- Tabla de productos -->
        <table>
            <thead>
                <tr>
                    <th>Modelo</th>
                    <th>Descripción</th>
                    <th style="text-align: center;">Cantidad</th>
                    <th class="text-right">Precio</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($content['items'] ?? [] as $item)
                    <tr>
                        <td>{{ $item['modelo'] ?? '' }}</td>
                        <td>
                            {{ $item['nombre'] ?? '' }}
                        </td>
                        <td class="text-center">{{ $item['cantidad'] ?? 0 }}</td>
                        <td class="text-right">${{ number_format($item['precio'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right"><strong>${{ number_format($item['subtotal'] ?? 0, 2, '.', ',') }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Sin productos</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Totales -->
        <div class="totals">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span>${{ number_format($content['subtotal'] ?? 0, 2, '.', ',') }}</span>
            </div>
            @if(($content['descuento'] ?? 0) > 0)
                <div class="totals-row">
                    <span>Descuento:</span>
                    <span>-${{ number_format($content['descuento'] ?? 0, 2, '.', ',') }}</span>
                </div>
            @endif
            @if(($content['envio'] ?? 0) > 0)
                <div class="totals-row">
                    <span>Envío:</span>
                    <span>${{ number_format($content['envio'] ?? 0, 2, '.', ',') }}</span>
                </div>
            @endif
            <div class="totals-row total">
                <span>TOTAL:</span>
                <span>${{ number_format($content['total'] ?? 0, 2, '.', ',') }}</span>
            </div>
        </div>

        <!-- Pagos -->
        @if(!empty($content['pagos']))
            <div class="payments">
                <div class="payments-title">Formas de Pago</div>
                @foreach($content['pagos'] as $pago)
                    <div class="payment-item">
                        <span>{{ $pago['modo_pago'] ?? 'N/A' }}:</span>
                        <span>${{ number_format($pago['monto'] ?? 0, 2, '.', ',') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>{{ $content['footer'] ?? 'Gracias por su compra' }}</p>
        </div>
    </div>
</body>
</html>
