---
description: Pohoda-Digest - analytical digest generator for Stormware Pohoda accounting system
applyTo: '**'
---

# Pohoda-Digest - Copilot Instructions

## Project Overview
Pohoda-Digest is a comprehensive analytical digest generator for Stormware Pohoda accounting system:
- Uses modular architecture with DigestModules and DigestRenderer
- Connects to Pohoda via PHP-Pohoda-Connector
- Provides CLI interface and programmatic API
- Supports multiple output formats (HTML, JSON, Email)
- Demonstrates extensibility to other accounting systems

## Architecture Guidelines
- **Modular Design**: Uses DigestModules for data, DigestRenderer for output
- **Data Provider Pattern**: PohodaDataProvider implements DataProviderInterface
- **Pohoda Integration**: Uses existing PHP-Pohoda-Connector library
- **CLI Interface**: Full-featured command-line tool
- **Multiple Themes**: Bootstrap for web, Email for client compatibility

## Development Best Practices
- Use strict types: `declare(strict_types=1);`
- Follow PSR-4 autoloading standards
- Implement comprehensive error handling
- Use environment configuration (.env files)
- Provide both CLI and programmatic interfaces
- Include working examples and tests

## Key Components
1. **Data Provider** (`src/DataProvider/`):
   - PohodaDataProvider: Connects to Pohoda mServer
   - Implements DataProviderInterface for compatibility
   - Handles connection testing and data normalization

2. **Modules** (`src/Modules/`):
   - PohodaOutcomingInvoices: Invoice analysis for Pohoda
   - PohodaDebtors: Overdue receivables analysis
   - System-specific implementations of standard modules

3. **Main Classes** (`src/`):
   - PohodaDigestor: Main orchestrator class
   - Factory methods for easy instantiation
   - Email sending and file output capabilities

4. **CLI Interface** (`bin/`):
   - pohoda-digest.php: Complete command-line tool
   - Supports all features and output formats

## Module Development for Pohoda
```php
class PohodaCustomModule extends AbstractModule
{
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        // Use $provider->getData() to fetch Pohoda data
        // Process and analyze according to module purpose
        // Return structured JSON result
    }
}
```

## Data Provider Usage
```php
$provider = new PohodaDataProvider();
$invoices = $provider->getData('invoices', [
    'dateFrom' => '2024-01-01',
    'dateTo' => '2024-01-31'
]);
```

## CLI Usage Examples
```bash
# Test connection to Pohoda
php bin/pohoda-digest.php --test-connection

# Generate monthly digest
php bin/pohoda-digest.php

# Custom period with email theme
php bin/pohoda-digest.php --start=2024-01-01 --end=2024-01-31 --theme=email

# JSON export
php bin/pohoda-digest.php --format=json --output=report.json

# Send by email
php bin/pohoda-digest.php --email=manager@company.com
```

## Configuration
Environment variables (.env file):
- `POHODA_URL`: Pohoda server URL
- `POHODA_ICO`: Company identification number
- `POHODA_USERNAME`: API username
- `POHODA_PASSWORD`: API password

## Available Modules
1. **outcoming_invoices**: Invoice analysis
   - Currency breakdown and totals
   - Document type categorization
   - Top customers analysis
   - Active vs cancelled tracking

2. **debtors**: Overdue receivables
   - Age group analysis (1-30, 31-60, 61-90, 90+ days)
   - Top debtors identification
   - Currency-specific overdue amounts

## Output Formats
- **HTML (Bootstrap)**: Responsive web-friendly format
- **HTML (Email)**: Email client compatible format
- **JSON**: Raw data for integration
- **Email**: Direct email sending capability

## Integration Pattern
This project demonstrates how to:
1. Create system-specific data providers
2. Implement custom modules for different systems
3. Reuse DigestModules and DigestRenderer libraries
4. Build complete applications with CLI interfaces
5. Handle system-specific data formats and APIs

## Testing
- Mock data providers for development testing
- CLI connection testing
- Complete pipeline validation
- Error handling verification

## Extension Points
- Add new Pohoda-specific modules
- Implement additional data providers for other systems
- Create custom themes for specific requirements
- Build web interface using the same backend