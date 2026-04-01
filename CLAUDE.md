# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Print Management Service** — a Laravel 10 application that provides APIs for managing thermal (POS-58) and normal printers. It handles receipt generation, configuration management, and supports both ESC/POS format (for thermal printers) and PDF generation (for standard printers).

**Key features:**
- Thermal printer support via ESC/POS protocol (`mike42/escpos-php`)
- PDF/normal printer support via DomPDF
- Printer configuration management (stored in JSON)
- Receipt formatting with logos, items, totals, and customizable text
- Sales ticket printing in Spanish

## Architecture

### Backend Stack
- **Framework:** Laravel 10 with Sanctum authentication
- **Database:** MySQL (configurable via .env)
- **Key Libraries:**
  - `mike42/escpos-php` — ESC/POS printer control for thermal printers
  - `barryvdh/laravel-dompdf` — PDF generation for standard printers
  - `guzzlehttp/guzzle` — HTTP client

### Frontend Stack
- **Build Tool:** Vite 5 with laravel-vite-plugin
- **Framework:** Vanilla JavaScript/Axios (no modern SPA framework detected)
- **Entry Points:** `resources/js/app.js`, `resources/css/app.css`

### Core Components
- **PrintController** (`app/Http/Controllers/PrintController.php`) — Handles all print operations:
  - `POST /api/print/thermal` — Thermal printer printing with ESC/POS formatting
  - `POST /api/print/normal` — Normal/PDF printer printing
  - `GET /api/config` — Retrieve printer configuration
  - `PUT /api/config` — Update printer configuration
  - `GET /api/status` — Get printer status
- **Configuration** — Stored in `storage/app/print_config.json` (or defaults in-memory)
- **Routing:** API routes under `/routes/api.php`, web routes under `/routes/web.php`

### Database
Standard Laravel tables:
- `users` — User authentication
- `personal_access_tokens` — Sanctum API tokens
- `password_reset_tokens` — Password reset
- `failed_jobs` — Job queue failures

No custom application models/tables—this is a lightweight service.

## Common Commands

### Setup & Installation
```bash
composer install              # Install PHP dependencies
npm install                   # Install JavaScript dependencies (if needed)
cp .env.example .env          # Create .env file
php artisan key:generate      # Generate APP_KEY
php artisan migrate           # Run database migrations
```

### Development
```bash
php artisan serve             # Start Laravel dev server (http://localhost:8000)
npm run dev                   # Start Vite dev server with hot reload
```

### Build & Production
```bash
npm run build                 # Build frontend assets with Vite
php artisan config:cache      # Cache configuration (production)
```

### Testing
```bash
php artisan test              # Run all tests (PHPUnit)
php artisan test --filter=MethodName  # Run a specific test
php artvendor/bin/pint        # Format code with Laravel Pint (PSR-12 standard)
```

### Database
```bash
php artisan migrate           # Run migrations
php artisan migrate:fresh     # Reset database and re-run migrations
php artisan seed              # Run seeders
```

## Configuration

### Environment Variables (.env)
Key variables for print functionality:
- `APP_ENV` — Set to `local` for development, `production` for production
- `APP_DEBUG` — Set to `true` in development, `false` in production
- `DB_*` — Database connection details (MySQL by default)
- `LOG_LEVEL` — Logging verbosity (debug, info, warning, error)

### Printer Configuration (print_config.json)
The application reads/writes printer settings from `storage/app/print_config.json`:
```json
{
  "thermalPrinter": "POS-58",
  "normalPrinter": "Microsoft Print to PDF",
  "normalPaperSize": "Letter",
  "currency": "$",
  "defaults": {
    "title": "NOTA DE VENTA",
    "footer": "Gracias por su compra",
    "logo": null
  }
}
```
Updates via `PUT /api/config` endpoint.

## Key Implementation Details

### Thermal Printing (ESC/POS)
- Uses `mike42/escpos-php` library to generate ESC/POS byte sequences
- Writes to a temporary `.prn` file via `FilePrintConnector`
- Output sent to Windows printer via native APIs
- Supports:
  - Logo insertion (resized to 384px width, converted to B&W)
  - Font selection (Font B = 42 characters per line on POS-58)
  - Text alignment (center, left, right)
  - Emphasis/bold text
  - Item formatting with columns (model, quantity, price)
  - Currency symbol customization

### Normal/PDF Printing
- Uses `barryvdh/laravel-dompdf` for PDF generation
- Supports multiple paper sizes (Letter, A4, etc.)
- Can be printed to physical printers or saved as PDF

### Logo Handling
- Expects logo at `public/images/logo.png`
- Automatically resized and converted to B&W for thermal printing
- Uses temporary file cleanup to avoid disk bloat

## Testing & Code Quality

- **PHPUnit** configured in `phpunit.xml`
- Test directories:
  - `tests/Unit/` — Unit tests
  - `tests/Feature/` — Feature/integration tests
- **Laravel Pint** for code formatting (PSR-12 standard)
- Tests use array cache driver and sync queues (see phpunit.xml for full config)

## Security Notes

- Routes under `/api/print/*` require no authentication (designed for local-only use)
- `/api/user` requires Sanctum authentication
- CSRF protection enabled on web routes
- Never commit `.env` files or sensitive credentials

## Deployment Considerations

1. Ensure printer hardware (POS-58 or configured thermal printer) is available on deployment target
2. Configure Windows printer names in `print_config.json` for target environment
3. Storage directory must be writable (`storage/app/`, `storage/logs/`)
4. Database must be migrated before first run
5. Frontend assets must be built (`npm run build`) before deployment
