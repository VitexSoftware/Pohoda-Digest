---
description: Pohoda-Digest - analytics and reporting tool for Money S3/Pohoda accounting system
applyTo: '**'
---

# Pohoda-Digest - Copilot Instructions

## Project Overview
Pohoda-Digest is a **complete analytics solution** for Money S3/Pohoda accounting system:
- **Modular Architecture**: Built on DigestModules (data) + DigestRenderer (HTML) libraries
- **Pohoda Integration**: Connects via mServer using existing PHP-Pohoda-Connector
- **Production Ready**: Full CLI interface, cron jobs, configuration management  
- **Multi-Format Output**: HTML reports, JSON data, email notifications
- **Debian Packaged**: Professional distribution with `pohoda-digest` package

## 🏗️ System Architecture
This is a **complete application** that demonstrates the full ecosystem:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Pohoda/mServer │◄──┤ PohodaDataProvider│ ◄──┤  DigestModules  │
│                 │    │                  │    │                 │
│ • XML/SQL API   │    │ • Data Adapter   │    │ • Analytics     │
│ • Business Data │    │ • Connection Mgmt│    │ • JSON Output   │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                                        │
                                                        ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│    HTML Report  │ ◄──┤ DigestRenderer   │ ◄──┤  Pohoda-Digest  │
│                 │    │                  │    │                 │
│ • Bootstrap UI  │    │ • Theme Engine   │    │ • CLI Interface │
│ • Email Format  │    │ • HTML Generator │    │ • Cron Jobs     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

## Architecture Guidelines
- **Modular Design**: Uses DigestModules for data, DigestRenderer for output
- **Data Provider Pattern**: PohodaDataProvider implements DataProviderInterface
- **Pohoda Integration**: Uses existing PHP-Pohoda-Connector library
- **CLI Interface**: Full-featured command-line tool
- **Multiple Themes**: Bootstrap for web, Email for client compatibility

## 📋 Development Standards

### Core Coding Guidelines
- **PHP 8.1+**: Use modern PHP features and strict types: `declare(strict_types=1);`
- **PSR-12**: Follow PHP-FIG coding standards for consistency
- **Type Safety**: Include type hints for all parameters and return types
- **Documentation**: PHPDoc blocks for all public methods and classes
- **Testing**: PHPUnit tests for all new functionality
- **Internationalization**: Use `_()` functions for translatable strings

### Development Best Practices
- **Code Comments**: Write in English using complete sentences and proper grammar
- **Variable Names**: Use meaningful names that describe their purpose
- **Constants**: Avoid magic numbers/strings; define constants instead
- **Exception Handling**: Always provide meaningful error messages
- **Commit Messages**: Use imperative mood and keep them concise
- **Security**: Ensure code is secure and doesn't expose sensitive information
- **Compatibility**: Maintain compatibility with latest PHP and library versions
- **Environment Configuration**: Use .env files for all configuration
- **CLI Interface**: Provide comprehensive command-line options with help

### Code Quality Requirements
- **Syntax Validation**: After every PHP file edit, run `php -l filename.php` for syntax checking
- **Error Handling**: Implement comprehensive try-catch blocks with meaningful error messages
- **JSON Output**: Support `--format json` option for all CLI commands and operations
- **Testing Requirements**: PHPUnit integration with comprehensive test coverage
- **Performance**: Optimize for production use with large datasets

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

## 🌐 MultiFlexi Integration

### Schema Compliance
All MultiFlexi files must conform to official schemas:

- **Application Config** (`multiflexi/*.app.json`): 
  https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.app.schema.json

- **Report Output** (generated reports):
  https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.report.schema.json

### Application Definition Pattern
```json
{
    "name": "Pohoda-Digest",
    "description": "Analytics and reporting for Pohoda",
    "version": "1.0.0",
    "vendor": "vitexsoftware",
    "homepage": "https://github.com/VitexSoftware/Pohoda-Digest",
    "requirements": {
        "php": ">=8.4",
        "pohoda-connector": "dev-main"
    }
}
```
