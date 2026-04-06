<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrintController extends Controller
{
    /**
     * Obtener configuración de impresoras desde archivo
     */
    private function getConfigData()
    {
        $configPath = 'print_config.json';
        $defaults = [
            'thermalPrinter' => 'POS-58',
            'normalPrinter' => 'Microsoft Print to PDF',
            'normalPaperSize' => 'Letter',
            'currency' => '$',
            'address' => [
                'Plaza las Américas, Local',
                '4B, Valle de San Javier,',
                'Pachuca'
            ],
            'defaults' => [
                'title' => 'NOTA DE VENTA',
                'footer' => 'Gracias por su compra',
                'logo' => null,
            ],
        ];

        if (Storage::disk('local')->exists($configPath)) {
            $stored = json_decode(Storage::disk('local')->get($configPath), true);

            return array_merge($defaults, $stored);
        }

        return $defaults;
    }

    /**
     * POST /api/print/thermal - Impresión térmica POS-58
     * Genera ESC/POS con mike42 (logo + Font B 42cols) y envía via Win32 API
     */
    public function printThermal(Request $request)
    {
        $config = $this->getConfigData();
        $currency = $config['currency'] ?? '$';
        $content = $request->input('content', []);
        $printerName = $request->input('printer') ?? $config['thermalPrinter'] ?? 'POS-58';

        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'pos_').'.prn';
            $fp = fopen($tmpFile, 'wb'); // Modo binario puro

            // PASO 1: Inicialización que funciona
            fwrite($fp, "\x1B\x40");     // ESC @ - Initialize
            fwrite($fp, "\x1B\x74\x00"); // ESC t 0 - PC437 (USA)

            // PASO 2: Generar y agregar LOGO (si existe)
            $logoPath = public_path('images/logo.png');
            if (file_exists($logoPath)) {
                try {
                    $logoTmp = tempnam(sys_get_temp_dir(), 'logo_').'.prn';
                    $logoConnector = new FilePrintConnector($logoTmp);
                    $logoPrinter = new Printer($logoConnector);

                    $resizedPath = $this->resizeLogo($logoPath, 384);
                    $image = EscposImage::load($resizedPath, false);
                    $logoPrinter->setJustification(Printer::JUSTIFY_CENTER);
                    $logoPrinter->bitImage($image);
                    $logoPrinter->feed(1);
                    $logoPrinter->close();

                    // Leer bytes del logo y agregarlos
                    $logoBytes = file_get_contents($logoTmp);

                    // Remover ESC @ inicial si existe (ya tenemos uno)
                    if (substr($logoBytes, 0, 2) === "\x1B\x40") {
                        $logoBytes = substr($logoBytes, 2);
                    }

                    fwrite($fp, $logoBytes);

                    @unlink($logoTmp);
                    if ($resizedPath !== $logoPath) {
                        @unlink($resizedPath);
                    }

                    // Reiniciar code page después del logo
                    fwrite($fp, "\x1B\x74\x00"); // ESC t 0 - PC437 (USA)

                    \Log::info('Logo added successfully');
                } catch (\Exception $e) {
                    \Log::error('Logo error: ' . $e->getMessage());
                    // Continuar sin logo
                }
            }

            // PASO 3: NO cambiar NADA de font o tamaño
            // Font A funciona correctamente, cualquier cambio rompe el code page
            // Dejar todo como está después del ESC t 0

            $W = 32; // Font A standard = ~32 caracteres por línea

            // PASO 4: Nuevo formato de ticket (estructura según el diseño requerido)
            $W = 25; // Ancho para separadores

            // Separador después del logo
            fwrite($fp, str_repeat('-', 25) . "\x0A");

            // Separadores y título
            fwrite($fp, str_repeat('=', $W) . "\x0A");
            fwrite($fp, $this->centerText('TICKET DE VENTA', $W) . "\x0A");
            fwrite($fp, str_repeat('=', $W) . "\x0A");

            // Dirección de la tienda (centrada, sin acentos)
            $address = $config['address'] ?? [];
            foreach ($address as $line) {
                fwrite($fp, $this->centerText($this->removeAccents($line), 32) . "\x0A");
            }
            fwrite($fp, "\x0A");

            // Folio y Fecha
            fwrite($fp, $this->centerText('Folio: ' . ($content['folio'] ?? ''), $W) . "\x0A");
            fwrite($fp, 'Fecha: ' . ($content['fecha'] ?? '') . "\x0A");
            fwrite($fp, $this->centerText(str_repeat('-', 20), $W) . "\x0A");

            // Cliente y Vendedor
            fwrite($fp, 'Cliente: ' . $this->removeAccents(mb_substr($content['cliente'] ?? '', 0, 16)) . "\x0A");
            fwrite($fp, 'Vendedor: ' . $this->removeAccents(mb_substr($content['vendedor'] ?? '', 0, 15)) . "\x0A");
            fwrite($fp, str_repeat('-', $W) . "\x0A");

            // Encabezado de productos
            fwrite($fp, 'Cant  Modelo        Monto' . "\x0A");
            fwrite($fp, str_repeat('-', $W) . "\x0A");

            // Items
            foreach ($content['items'] ?? [] as $item) {
                $cant = $item['cantidad'] ?? 1;
                $modelo = $this->removeAccents(mb_substr($item['modelo'] ?? '', 0, 12));
                $subtotal = $currency . $this->fmtN($item['subtotal'] ?? 0);
                fwrite($fp, $this->fmtItem($cant, $modelo, $subtotal, $W) . "\x0A");
            }

            fwrite($fp, str_repeat('-', $W) . "\x0A");

            // IVA (calculado al 16% si no viene en el request)
            $subtotal = floatval($content['subtotal'] ?? 0);
            $iva = $content['iva'] ?? ($subtotal * 0.16);
            fwrite($fp, $this->fmtLine('IVA:', $currency . $this->fmtN($iva), $W) . "\x0A");
            fwrite($fp, $this->fmtLine('Subtotal:', $currency . $this->fmtN($subtotal), $W) . "\x0A");
            fwrite($fp, str_repeat('-', $W) . "\x0A");

            // Metodo de pago
            $metodoPago = 'N/A';
            if (! empty($content['pagos'])) {
                $modos = [];
                foreach ($content['pagos'] as $p) {
                    if (! empty($p['modo_pago'])) {
                        $modos[] = $this->removeAccents($p['modo_pago']);
                    }
                }
                if (! empty($modos)) {
                    $metodoPago = implode(', ', $modos);
                }
            }
            fwrite($fp, 'Metodo de pago: ' . mb_substr($metodoPago, 0, 9) . "\x0A");
            fwrite($fp, "\x0A");

            // QR de facturación (primero)
            $qrPath = public_path('images/facturacionqr.png');
            if (file_exists($qrPath)) {
                try {
                    fwrite($fp, $this->centerText('Para facturacion:', 32) . "\x0A");

                    $qrTmp = tempnam(sys_get_temp_dir(), 'qr_').'.prn';
                    $qrConnector = new FilePrintConnector($qrTmp);
                    $qrPrinter = new Printer($qrConnector);

                    // QR grande: 450px
                    $qrResized = $this->resizeLogo($qrPath, 450);
                    $qrImage = EscposImage::load($qrResized, false);
                    $qrPrinter->setJustification(Printer::JUSTIFY_CENTER);
                    $qrPrinter->bitImage($qrImage);
                    $qrPrinter->feed(1);
                    $qrPrinter->close();

                    $qrBytes = file_get_contents($qrTmp);

                    // Remover ESC @ inicial si existe
                    if (substr($qrBytes, 0, 2) === "\x1B\x40") {
                        $qrBytes = substr($qrBytes, 2);
                    }

                    fwrite($fp, $qrBytes);

                    @unlink($qrTmp);
                    if ($qrResized !== $qrPath) {
                        @unlink($qrResized);
                    }

                    // Reiniciar code page después del QR
                    fwrite($fp, "\x1B\x74\x00");  // ESC t 0 - PC437
                    fwrite($fp, "\x0A");

                    \Log::info('QR image added successfully');
                } catch (\Exception $e) {
                    \Log::error('QR error: ' . $e->getMessage());
                    // Continuar sin QR
                }
            }

            fwrite($fp, "\x0A");

            // Código de barras CODE128 del folio (después del QR)
            if (! empty($content['folio'])) {
                $folioStr = (string)$content['folio'];

                // Centrar barcode
                fwrite($fp, "\x1B\x61\x01");  // ESC a 1 - Center

                // Configurar altura del barcode
                fwrite($fp, "\x1D\x68\x50");  // GS h 80 (altura 80 dots)

                // Ancho del módulo
                fwrite($fp, "\x1D\x77\x02");  // GS w 2 (ancho módulo 2)

                // HRI (Human Readable Interpretation) - mostrar texto debajo
                fwrite($fp, "\x1D\x48\x02");  // GS H 2 (mostrar HRI debajo)

                // Imprimir CODE128
                fwrite($fp, "\x1D\x6B\x49");  // GS k I (CODE128 extended)
                fwrite($fp, chr(strlen($folioStr)));  // Longitud
                fwrite($fp, $folioStr);  // Datos
                fwrite($fp, "\x0A");

                // Restaurar alineación izquierda
                fwrite($fp, "\x1B\x61\x00");  // ESC a 0 - Left
            }

            fwrite($fp, $this->centerText($this->removeAccents($content['footer'] ?? 'Gracias por su compra'), 32) . "\x0A");

            // Comandos finales
            fwrite($fp, "\x1B\x64\x03");  // ESC d 3 - Print + feed 3 lines
            fwrite($fp, "\x1D\x56\x00");  // GS V 0 - Cut paper

            fclose($fp);
            \Log::info('Binary generation with fopen/fwrite - all $ written directly');

            // Enviar usando Win32 API a través de PowerShell
            $escapedFile = str_replace('$', '`$', $tmpFile);
            $escapedFile = str_replace('"', '`"', $escapedFile);
            $escapedPrinter = str_replace('$', '`$', $printerName);
            $escapedPrinter = str_replace('"', '`"', $escapedPrinter);

            $psScript = <<<'PS'
$printerName = "%PRINTER_NAME%"
$filePath = "%FILE_PATH%"

# Win32 API para enviar RAW a la impresora
$code = @"
using System;
using System.Runtime.InteropServices;

public class RawPrint {
    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool OpenPrinter(string pPrinterName, ref IntPtr phPrinter, IntPtr pDefault);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern int StartDocPrinter(IntPtr hPrinter, int Level, ref DOCINFO pDocInfo);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, ref int dwWritten);

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Auto)]
    public struct DOCINFO {
        [MarshalAs(UnmanagedType.LPTStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPTStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPTStr)] public string pDataType;
    }

    public static bool SendRawToPrinter(string printerName, byte[] data) {
        IntPtr hPrinter = IntPtr.Zero;
        if (!OpenPrinter(printerName, ref hPrinter, IntPtr.Zero)) {
            return false;
        }

        DOCINFO di = new DOCINFO {
            pDocName = "RAW",
            pDataType = "RAW"
        };

        if (StartDocPrinter(hPrinter, 1, ref di) <= 0) {
            ClosePrinter(hPrinter);
            return false;
        }

        IntPtr pBytes = Marshal.AllocCoTaskMem(data.Length);
        Marshal.Copy(data, 0, pBytes, data.Length);

        int dwWritten = 0;
        bool bSuccess = WritePrinter(hPrinter, pBytes, data.Length, ref dwWritten);

        Marshal.FreeCoTaskMem(pBytes);
        EndDocPrinter(hPrinter);
        ClosePrinter(hPrinter);

        return bSuccess;
    }
}
"@

Add-Type -TypeDefinition $code -Language CSharp

$bytes = [System.IO.File]::ReadAllBytes($filePath)
if ([RawPrint]::SendRawToPrinter($printerName, $bytes)) {
    exit 0
} else {
    Write-Error "Failed to send to printer"
    exit 1
}
PS;

            $psFile = tempnam(sys_get_temp_dir(), 'escpos_').'.ps1';

            // Reemplazar placeholders en el script
            $psScript = str_replace('%PRINTER_NAME%', $escapedPrinter, $psScript);
            $psScript = str_replace('%FILE_PATH%', $escapedFile, $psScript);

            file_put_contents($psFile, $psScript);

            $cmd = "powershell -NoProfile -ExecutionPolicy Bypass -File \"$psFile\"";
            exec($cmd . " 2>&1", $output, $returnCode);

            @unlink($tmpFile);
            @unlink($psFile);

            if ($returnCode !== 0) {
                \Log::error('Thermal print failed', [
                    'printer' => $printerName,
                    'returnCode' => $returnCode,
                    'output' => implode("\n", $output ?? [])
                ]);
                return response()->json(['error' => 'Print failed: ' . (isset($output[0]) ? $output[0] : 'unknown')], 500);
            }

            \Log::info('Thermal print dispatched', [
                'printer' => $printerName,
                'folio'   => $content['folio'] ?? null,
            ]);

            return response()->json(['ok' => true, 'printer' => $printerName], 200);

        } catch (\Exception $e) {
            \Log::error('Thermal print error: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Centrar texto en ancho fijo
     */
    private function centerText($text, $width)
    {
        $text = mb_substr($text, 0, $width);
        $len = strlen($text);
        $padding = max(0, intdiv($width - $len, 2));

        return str_repeat(' ', $padding).$text;
    }

    /**
     * Construir HTML para PDF térmico (58mm)
     */
    private function buildThermalHTML($content)
    {
        $title = htmlspecialchars($content['title'] ?? 'NOTA DE VENTA');
        $folio = htmlspecialchars($content['folio'] ?? '');
        $fecha = htmlspecialchars($content['fecha'] ?? '');
        $cliente = mb_substr($content['cliente'] ?? '', 0, 24);
        $vendedor = mb_substr($content['vendedor'] ?? '', 0, 23);
        $subtotal = $this->fmtN($content['subtotal'] ?? 0);
        $descuento = $this->fmtN($content['descuento'] ?? 0);
        $envio = $this->fmtN($content['envio'] ?? 0);
        $total = $this->fmtN($content['total'] ?? 0);
        $footer = htmlspecialchars($content['footer'] ?? 'Gracias por su compra');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            width: 58mm;
            color: #000;
        }
        .container { padding: 2mm; }
        .title { text-align: center; font-weight: bold; font-size: 10pt; margin: 2mm 0; }
        .divider { text-align: center; margin: 2mm 0; }
        .line { margin: 1mm 0; font-size: 8pt; }
        .header { font-weight: bold; margin: 1mm 0 2mm 0; }
        .item { margin: 1mm 0; }
        .total { text-align: right; font-weight: bold; margin-top: 2mm; }
        .center { text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="title">$title</div>
    <div class="divider">================================</div>

    <div class="line">Folio: $folio</div>
    <div class="line">Fecha: $fecha</div>

    <div class="divider">--------------------------------</div>
    <div class="line">Cliente: $cliente</div>
    <div class="line">Vendedor: $vendedor</div>
    <div class="divider">--------------------------------</div>

    <div class="header">PRODUCTOS:</div>
HTML;

        foreach ($content['items'] ?? [] as $item) {
            $modelo = htmlspecialchars(mb_substr($item['modelo'] ?? '', 0, 10));
            $cant = $item['cantidad'];
            $precio = $this->fmtN($item['precio']);
            $html .= "<div class=\"item\">$modelo x$cant @ \$$precio</div>";
            if (! empty($item['nombre'])) {
                $nombre = htmlspecialchars(mb_substr($item['nombre'], 0, 30));
                $html .= "<div style=\"font-size: 7pt; margin-left: 5px;\">$nombre</div>";
            }
        }

        $html .= <<<HTML

    <div class="divider">--------------------------------</div>
    <div class="total">Subtotal: \$$subtotal</div>
HTML;

        if (($content['descuento'] ?? 0) > 0) {
            $html .= "<div class=\"total\">Descuento: -\$$descuento</div>";
        }

        if (($content['envio'] ?? 0) > 0) {
            $html .= "<div class=\"total\">Envío: \$$envio</div>";
        }

        $html .= <<<HTML
    <div class="divider">================================</div>
    <div class="total" style="font-size: 11pt;">TOTAL: \$$total</div>
    <div class="divider">================================</div>

HTML;

        if (! empty($content['pagos'])) {
            $html .= '<div class="header">PAGOS:</div>';
            foreach ($content['pagos'] as $p) {
                $modo = htmlspecialchars($p['modo_pago']);
                $monto = $this->fmtN($p['monto']);
                $html .= "<div class=\"line\">$modo: \$$monto</div>";
            }
        }

        $html .= <<<HTML

    <div class="center" style="margin-top: 3mm; font-size: 8pt;">
        $footer
    </div>
</div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * POST /api/print/normal - Impresión en carta (Letter)
     */
    public function printNormal(Request $request)
    {
        $config = $this->getConfigData();
        $content = $request->input('content', []);
        $printerName = $request->input('printer') ?? $config['normalPrinter'];

        try {
            // Generar PDF con dompdf
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('print.nota_carta', ['content' => $content])
                ->setPaper('letter', 'portrait');
            $pdfContent = $pdf->output();

            // Guardar en temp
            $tmpPath = tempnam(sys_get_temp_dir(), 'nota').'.pdf';
            file_put_contents($tmpPath, $pdfContent);

            // Enviar a impresora via PowerShell
            $escaped = str_replace("'", "''", $tmpPath);
            $printerEscaped = str_replace("'", "''", $printerName);
            shell_exec("powershell -NoProfile -Command \"Start-Process -FilePath '$escaped' -Verb Print -Wait\"");

            // Cleanup
            unlink($tmpPath);

            return response()->json(['ok' => true, 'printer' => $printerName], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/status - Estado y lista de impresoras
     */
    public function status()
    {
        $printers = [];
        $output = [];

        exec('powershell -NoProfile -Command "Get-Printer | Select-Object Name,DriverName,PortName | ConvertTo-Json"', $output);
        $json = implode('', $output);

        if (! empty($json)) {
            $raw = json_decode($json, true);
            if ($raw) {
                $printers = isset($raw[0]) ? $raw : [$raw];
            }
        }

        return response()->json([
            'ok' => true,
            'printers' => $printers,
            'config' => $this->getConfigData(),
        ], 200);
    }

    /**
     * GET /api/config - Obtener configuración actual
     */
    public function getConfig()
    {
        return response()->json($this->getConfigData(), 200);
    }

    /**
     * PUT /api/config - Actualizar configuración
     */
    public function updateConfig(Request $request)
    {
        try {
            $config = $this->getConfigData();

            if ($request->has('thermalPrinter')) {
                $config['thermalPrinter'] = $request->input('thermalPrinter');
            }
            if ($request->has('normalPrinter')) {
                $config['normalPrinter'] = $request->input('normalPrinter');
            }
            if ($request->has('normalPaperSize')) {
                $config['normalPaperSize'] = $request->input('normalPaperSize');
            }
            if ($request->has('currency')) {
                $config['currency'] = $request->input('currency');
            }
            if ($request->has('address')) {
                $config['address'] = $request->input('address');
            }
            if ($request->has('defaults')) {
                $config['defaults'] = array_merge($config['defaults'], $request->input('defaults'));
            }

            Storage::disk('local')->put('print_config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return response()->json(['ok' => true, 'config' => $config], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ────── Helpers ──────────────────────────────────────────────────────

    /**
     * Redimensionar logo a maxWidth px de ancho manteniendo proporción
     * Convierte a escala de grises (requerido para ESC/POS bitmap)
     */
    private function resizeLogo(string $srcPath, int $maxWidth): string
    {
        [$origW, $origH, $type] = getimagesize($srcPath);

        // Cargar imagen según tipo
        $src = match ($type) {
            IMAGETYPE_PNG => imagecreatefrompng($srcPath),
            IMAGETYPE_JPEG => imagecreatefromjpeg($srcPath),
            IMAGETYPE_GIF => imagecreatefromgif($srcPath),
            default => throw new \RuntimeException('Formato de imagen no soportado'),
        };

        // Calcular nuevo tamaño
        $newW = min($origW, $maxWidth);
        $newH = (int) round($origH * ($newW / $origW));

        // Crear canvas con fondo BLANCO (importante para transparencias)
        $dst = imagecreatetruecolor($newW, $newH);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($src);

        // Convertir a escala de grises con alto contraste
        // EscposImage maneja la conversión a 1-bit internamente
        imagefilter($dst, IMG_FILTER_GRAYSCALE);
        imagefilter($dst, IMG_FILTER_BRIGHTNESS, 20);
        imagefilter($dst, IMG_FILTER_CONTRAST, -60);

        $tmpPath = tempnam(sys_get_temp_dir(), 'logo_').'.png';
        imagepng($dst, $tmpPath);
        imagedestroy($dst);

        return $tmpPath;
    }

    /**
     * Construir ticket de texto para impresora térmica (58mm)
     */
    private function buildThermalTicket($content)
    {
        $lines = [];

        // Título
        $lines[] = str_repeat('=', 32);
        $lines[] = $this->centerText(strtoupper($content['title'] ?? 'NOTA DE VENTA'), 32);
        $lines[] = str_repeat('=', 32);

        // Folio y fecha
        $lines[] = 'Folio: '.($content['folio'] ?? '');
        $lines[] = 'Fecha: '.($content['fecha'] ?? '');

        // Cliente
        $lines[] = str_repeat('-', 32);
        $lines[] = 'Cliente: '.mb_substr($content['cliente'] ?? '', 0, 24);
        $lines[] = 'Vendedor: '.mb_substr($content['vendedor'] ?? '', 0, 23);
        $lines[] = str_repeat('-', 32);

        // Header productos
        $lines[] = $this->col3('MODELO', 'QTY×$', 'SUBTOTAL', 32);
        $lines[] = str_repeat('-', 32);

        // Items
        foreach ($content['items'] ?? [] as $item) {
            $modelo = mb_substr($item['modelo'] ?? '', 0, 8);
            $qtyPrice = $item['cantidad'].'x$'.$this->fmtN($item['precio']);
            $subtotal = '$'.$this->fmtN($item['subtotal'] ?? 0);
            $lines[] = $this->col3($modelo, $qtyPrice, $subtotal, 32);

            if (! empty($item['nombre'])) {
                $lines[] = '  '.mb_substr($item['nombre'], 0, 28);
            }
        }

        // Totales
        $lines[] = str_repeat('-', 32);
        $lines[] = $this->col3('Subtotal:', '', '$'.$this->fmtN($content['subtotal'] ?? 0), 32);

        if (($content['descuento'] ?? 0) > 0) {
            $lines[] = $this->col3('Descuento:', '', '-$'.$this->fmtN($content['descuento']), 32);
        }

        if (($content['envio'] ?? 0) > 0) {
            $lines[] = $this->col3('Envío:', '', '$'.$this->fmtN($content['envio']), 32);
        }

        $lines[] = str_repeat('=', 32);
        $lines[] = $this->col3('TOTAL:', '', '$'.$this->fmtN($content['total'] ?? 0), 32);
        $lines[] = str_repeat('=', 32);

        // Pagos
        if (! empty($content['pagos'])) {
            $lines[] = 'PAGOS:';
            foreach ($content['pagos'] as $p) {
                $lines[] = $p['modo_pago'].': $'.$this->fmtN($p['monto']);
            }
            $lines[] = '';
        }

        // Footer
        $lines[] = $this->centerText($content['footer'] ?? 'Gracias por su compra', 32);
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Formato numérico: 2 decimales
     */
    private function fmtN($n)
    {
        return number_format((float) ($n ?? 0), 2, '.', ',');
    }

    /**
     * Eliminar acentos y caracteres especiales del español
     */
    private function removeAccents(string $str): string
    {
        $search  = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ','ü','Ü','¡','¿'];
        $replace = ['a','e','i','o','u','A','E','I','O','U','n','N','u','U','!','?'];
        return str_replace($search, $replace, $str);
    }

    /**
     * Distribuir 3 columnas en ancho fijo
     */
    private function col3($l, $c, $r, $width)
    {
        $avail = $width - strlen($l) - strlen($r);
        $pad = max(1, intdiv($avail - strlen($c), 2));

        return $l.str_repeat(' ', $pad).$c.str_repeat(' ', $avail - $pad - strlen($c)).$r;
    }

    /**
     * Formato de línea de ítem: Cant | Modelo | Monto (3 columnas)
     * Cant: 4 chars left-aligned
     * Modelo: 12 chars left-aligned, truncated
     * Monto: right-aligned in remaining space
     */
    private function fmtItem($cant, $modelo, $monto, $width = 25)
    {
        $cant = str_pad((string)$cant, 4, ' ');
        $modelo = str_pad(mb_substr($modelo, 0, 12), 12, ' ');
        $monto = (string)$monto;

        $leftPart = $cant . '  ' . $modelo;  // 4 + 2 + 12 = 18
        $space = $width - strlen($leftPart) - strlen($monto);
        $space = max(1, $space);

        return $leftPart . str_repeat(' ', $space) . $monto;
    }

    /**
     * Formato de línea de dos columnas: Etiqueta izquierda | Valor derecha
     * Útil para IVA, Subtotal, etc.
     */
    private function fmtLine($label, $value, $width = 25)
    {
        $label = (string)$label;
        $value = (string)$value;
        $space = $width - strlen($label) - strlen($value);
        $space = max(1, $space);

        return $label . str_repeat(' ', $space) . $value;
    }

    /**
     * GET /api/test/chars - Imprimir caracteres de prueba para debug
     */
    public function testChars(Request $request)
    {
        $config = $this->getConfigData();
        $printerName = $request->input('printer') ?? $config['thermalPrinter'] ?? 'POS58 Printer';

        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_').'.prn';
            $connector = new FilePrintConnector($tmpFile);
            $printer = new Printer($connector);

            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_FONT_B);

            $printer->text("=== TEST CARACTERES ===\n\n");

            // Caracteres de dólar de diferentes código pages
            $printer->text("Caracteres de $\n");
            $printer->text("-------------\n");

            // Byte 0x24 (ASCII $)
            $printer->text("24: $\n");

            // Byte 0xA5 (Yen en algunas pages)
            $printer->text("A5: \xA5\n");

            // Symbolo de dollar Unicode (se convierte a multibyte)
            $printer->text("U+24: $\n");

            // Otros simbolos
            $printer->text("\nOtros simbolos:\n");
            $printer->text("-------------\n");
            $printer->text("Euro: \xE2\x82\xAC\n");
            $printer->text("Libra: \xC2\xA3\n");
            $printer->text("Yen: \xC2\xA5\n");
            $printer->text("Cent: \xC2\xA2\n");

            // Numeros para comparar
            $printer->text("\nNumeros referencia:\n");
            $printer->text("-------------\n");
            $printer->text("1234567890\n");
            $printer->text("ABCDEFGHIJ\n");
            $printer->text("abcdefghij\n");

            $printer->text("\n=== FIN TEST ===\n");
            $printer->feed(3);
            $printer->cut();
            $printer->close();

            // Agregar code page
            $raw = file_get_contents($tmpFile);
            $raw = "\x1B\x40\x1C\x2E\x1B\x74\x10\x1B\x52\x01".$raw;
            $raw = str_replace("\xA5", "\x24", $raw);
            file_put_contents($tmpFile, $raw);

            // Enviar
            $escapedFile = str_replace("'", "''", $tmpFile);
            $escapedPrinter = str_replace("'", "''", $printerName);

            $psScript = <<<'PS'
param($printerName, $filePath)
$code = @"
using System;
using System.Runtime.InteropServices;
public class RawPrint {
    [DllImport("winspool.drv", CharSet=CharSet.Auto, SetLastError=true)]
    public static extern bool OpenPrinter(string pPrinterName, ref IntPtr phPrinter, IntPtr pDefault);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);
    [DllImport("winspool.drv", CharSet=CharSet.Auto, SetLastError=true)]
    public static extern int StartDocPrinter(IntPtr hPrinter, int Level, ref DOCINFO pDocInfo);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, ref int dwWritten);
    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Auto)]
    public struct DOCINFO {
        [MarshalAs(UnmanagedType.LPTStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPTStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPTStr)] public string pDataType;
    }
    public static bool SendRaw(string printerName, byte[] data) {
        IntPtr hPrinter = IntPtr.Zero;
        if (!OpenPrinter(printerName, ref hPrinter, IntPtr.Zero)) return false;
        DOCINFO di = new DOCINFO { pDocName = "RAW", pDataType = "RAW" };
        if (StartDocPrinter(hPrinter, 1, ref di) <= 0) { ClosePrinter(hPrinter); return false; }
        StartPagePrinter(hPrinter);
        IntPtr pBytes = Marshal.AllocCoTaskMem(data.Length);
        Marshal.Copy(data, 0, pBytes, data.Length);
        int written = 0;
        bool ok = WritePrinter(hPrinter, pBytes, data.Length, ref written);
        Marshal.FreeCoTaskMem(pBytes);
        EndPagePrinter(hPrinter);
        EndDocPrinter(hPrinter);
        ClosePrinter(hPrinter);
        return ok;
    }
}
"@
Add-Type -TypeDefinition $code -Language CSharp
$bytes = [System.IO.File]::ReadAllBytes($filePath)
[RawPrint]::SendRaw($printerName, $bytes) | Out-Null
PS;

            $psFile = tempnam(sys_get_temp_dir(), 'testchars_').'.ps1';
            file_put_contents($psFile, $psScript);
            exec("powershell -NoProfile -ExecutionPolicy Bypass -File \"$psFile\" -printerName \"$escapedPrinter\" -filePath \"$escapedFile\"");

            @unlink($tmpFile);
            @unlink($psFile);

            return response()->json(['ok' => true, 'message' => 'Test chars printed'], 200);

        } catch (\Exception $e) {
            \Log::error('Test chars error: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
