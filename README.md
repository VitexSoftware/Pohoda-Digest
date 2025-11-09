# Pohoda-Digest

Analytical digest generator for Stormware Pohoda accounting system using modular architecture.

## Features

- **Modular architecture** - Uses DigestModules for data collection and DigestRenderer for output
- **Multiple output formats** - HTML, Email-compatible HTML, JSON
- **Comprehensive analytics** - Invoice analysis, debtor reports, financial summaries
- **Themed output** - Bootstrap responsive theme, Email-compatible theme
- **Easy integration** - Works with existing Pohoda mServer installations

## Installation

```bash
composer install
cp .env.example .env
# Edit .env with your Pohoda connection details
```

## Configuration

Copy the `.env.example` file to `.env` and configure:

```env
POHODA_URL=https://your-pohoda-server.example.com
POHODA_ICO=12345678
POHODA_USERNAME=api
POHODA_PASSWORD=your-api-password
```

## Usage

### Command Line Interface

```bash
# Generate digest for current month
php bin/pohoda-digest.php

# Generate digest for specific period
php bin/pohoda-digest.php --start=2024-01-01 --end=2024-01-31

# Generate with specific theme
php bin/pohoda-digest.php --theme=email

# Output JSON only
php bin/pohoda-digest.php --format=json

# Send by email
php bin/pohoda-digest.php --email=admin@company.com
```

### Programmatic Usage

```php
use VitexSoftware\PohodaDigest\PohodaDigestor;

// Create digestor with Pohoda connection
$digestor = new PohodaDigestor();

// Generate digest for period
$period = [
    'start' => '2024-01-01',
    'end' => '2024-01-31'
];

// Get JSON data
$jsonData = $digestor->getJsonData($period);

// Generate HTML with Bootstrap theme
$html = $digestor->generateHtml($period, 'bootstrap');

// Send by email
$digestor->sendByEmail($period, 'manager@company.com', 'email');
```

## Available Modules

- **OutcomingInvoices** - Analysis of issued invoices
- **Debtors** - Overdue receivables analysis
- **BankTransactions** - Bank activity summary
- **TaxSummary** - VAT and tax overview

## Output Themes

- **Bootstrap** - Modern responsive HTML for web viewing
- **Email** - Compatible with email clients, inline CSS

## Requirements

- PHP 8.1 or higher
- Pohoda mServer installation
- Valid Pohoda API credentials

## License

MIT License