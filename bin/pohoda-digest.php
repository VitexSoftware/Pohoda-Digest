#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Pohoda Digest CLI Tool
 *
 * Command-line interface for generating analytical digests from Pohoda data
 */

namespace VitexSoftware\PohodaDigest\CLI;

require_once dirname(__DIR__) . '/vendor/autoload.php';

use VitexSoftware\PohodaDigest\PohodaDigestor;
use Ease\Functions;

/**
 * CLI Application for Pohoda Digest
 */
class PohodaDigestCli
{
    private array $options;
    private array $defaultOptions = [
        'start' => null,
        'end' => null,
        'theme' => 'bootstrap',
        'format' => 'html',
        'output' => null,
        'email' => null,
        'modules' => ['outcoming_invoices', 'debtors'],
        'env' => '.env',
        'help' => false,
        'test-connection' => false,
    ];

    public function __construct()
    {
        $this->options = $this->parseArguments();
    }

    /**
     * Run the CLI application
     */
    public function run(): int
    {
        try {
            // Show help if requested
            if ($this->options['help']) {
                $this->showHelp();
                return 0;
            }

            // Test connection if requested
            if ($this->options['test-connection']) {
                return $this->testConnection();
            }

            // Generate digest
            return $this->generateDigest();

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Test Pohoda connection
     */
    private function testConnection(): int
    {
        $this->info("Testing Pohoda connection...");
        
        $envFile = $this->getEnvFile();
        $digestor = PohodaDigestor::createFromEnv($envFile);
        
        if ($digestor->testConnection()) {
            $this->success("✓ Connection successful");
            
            // Show company info
            $companyInfo = $digestor->getDataProvider()->getCompanyInfo();
            $this->info("Company: " . $companyInfo['name']);
            $this->info("Server: " . $companyInfo['server_url']);
            
            return 0;
        } else {
            $this->error("✗ Connection failed");
            return 1;
        }
    }

    /**
     * Generate digest
     */
    private function generateDigest(): int
    {
        $this->info("Generating Pohoda digest...");
        
        // Create digestor
        $envFile = $this->getEnvFile();
        $digestor = PohodaDigestor::createWithModules($this->options['modules']);
        
        // Test connection first
        if (!$digestor->testConnection()) {
            $this->error("Cannot connect to Pohoda. Please check your configuration.");
            return 1;
        }

        // Create time period
        $period = $this->createPeriod();
        $this->info("Period: {$period->getStartDate()->format('Y-m-d')} to {$period->getEndDate()->format('Y-m-d')}");

        // Generate content
        if ($this->options['format'] === 'json') {
            $content = json_encode($digestor->getJsonData($period), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->info("Generated JSON data");
        } else {
            $content = $digestor->generateHtml($period, $this->options['theme']);
            $this->info("Generated HTML with theme: " . $this->options['theme']);
        }

        // Output or save
        if ($this->options['output']) {
            if ($digestor->saveToFile($period, $this->options['output'], $this->options['format'], $this->options['theme'])) {
                $this->success("✓ Saved to: " . $this->options['output']);
            } else {
                $this->error("✗ Failed to save file");
                return 1;
            }
        } else {
            echo $content;
        }

        // Send email if requested
        if ($this->options['email']) {
            $this->info("Sending email to: " . $this->options['email']);
            if ($digestor->sendByEmail($period, $this->options['email'], 'email')) {
                $this->success("✓ Email sent successfully");
            } else {
                $this->error("✗ Failed to send email");
                return 1;
            }
        }

        return 0;
    }

    /**
     * Create time period from options
     */
    private function createPeriod(): \DatePeriod
    {
        $start = $this->options['start'] ? new \DateTime($this->options['start']) : new \DateTime('first day of this month');
        $end = $this->options['end'] ? new \DateTime($this->options['end']) : new \DateTime('last day of this month');
        
        return new \DatePeriod($start, new \DateInterval('P1D'), $end->add(new \DateInterval('P1D')));
    }

    /**
     * Get environment file path
     */
    private function getEnvFile(): string
    {
        $envFile = $this->options['env'];
        
        if (!file_exists($envFile)) {
            throw new \RuntimeException("Environment file not found: {$envFile}");
        }
        
        return $envFile;
    }

    /**
     * Parse command line arguments
     */
    private function parseArguments(): array
    {
        $options = $this->defaultOptions;
        $args = $_SERVER['argv'] ?? [];
        
        for ($i = 1; $i < count($args); $i++) {
            $arg = $args[$i];
            
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $key = $parts[0];
                $value = $parts[1] ?? true;
                
                switch ($key) {
                    case 'start':
                    case 'end':
                        $options[$key] = $value;
                        break;
                        
                    case 'theme':
                        if (!in_array($value, ['bootstrap', 'email'], true)) {
                            throw new \InvalidArgumentException("Invalid theme: {$value}");
                        }
                        $options[$key] = $value;
                        break;
                        
                    case 'format':
                        if (!in_array($value, ['html', 'json'], true)) {
                            throw new \InvalidArgumentException("Invalid format: {$value}");
                        }
                        $options[$key] = $value;
                        break;
                        
                    case 'output':
                    case 'email':
                    case 'env':
                        $options[$key] = $value;
                        break;
                        
                    case 'modules':
                        $options[$key] = explode(',', $value);
                        break;
                        
                    case 'help':
                    case 'test-connection':
                        $options[$key] = true;
                        break;
                        
                    default:
                        throw new \InvalidArgumentException("Unknown option: --{$key}");
                }
            }
        }
        
        return $options;
    }

    /**
     * Show help information
     */
    private function showHelp(): void
    {
        echo <<<HELP
Pohoda Digest CLI Tool

Usage:
  php pohoda-digest.php [options]

Options:
  --start=YYYY-MM-DD      Start date (default: first day of current month)
  --end=YYYY-MM-DD        End date (default: last day of current month)
  --theme=THEME           Output theme: bootstrap, email (default: bootstrap)
  --format=FORMAT         Output format: html, json (default: html)
  --output=FILE           Save to file instead of stdout
  --email=EMAIL           Send digest by email
  --modules=LIST          Comma-separated list of modules (default: outcoming_invoices,debtors)
  --env=FILE              Environment file path (default: .env)
  --test-connection       Test Pohoda connection and exit
  --help                  Show this help message

Examples:
  # Generate digest for current month
  php pohoda-digest.php

  # Generate digest for specific period
  php pohoda-digest.php --start=2024-01-01 --end=2024-01-31

  # Generate email-compatible HTML
  php pohoda-digest.php --theme=email

  # Save JSON to file
  php pohoda-digest.php --format=json --output=digest.json

  # Send by email
  php pohoda-digest.php --email=manager@company.com --theme=email

  # Test connection
  php pohoda-digest.php --test-connection

Available Modules:
  - outcoming_invoices    Analysis of issued invoices
  - debtors              Overdue receivables analysis

Environment Configuration:
  Copy .env.example to .env and configure:
  - POHODA_URL            Pohoda server URL
  - POHODA_ICO           Company identification number
  - POHODA_USERNAME      API username
  - POHODA_PASSWORD      API password

HELP;
    }

    /**
     * Print info message
     */
    private function info(string $message): void
    {
        if (php_sapi_name() === 'cli') {
            echo "[INFO] {$message}\n";
        }
    }

    /**
     * Print success message
     */
    private function success(string $message): void
    {
        if (php_sapi_name() === 'cli') {
            echo "[SUCCESS] {$message}\n";
        }
    }

    /**
     * Print error message
     */
    private function error(string $message): void
    {
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, "[ERROR] {$message}\n");
        }
    }
}

// Run CLI application
try {
    $app = new PohodaDigestCli();
    exit($app->run());
} catch (\Exception $e) {
    fwrite(STDERR, "Fatal error: " . $e->getMessage() . "\n");
    exit(1);
}