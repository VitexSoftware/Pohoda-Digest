<?php

declare(strict_types=1);

/**
 * This file is part of the Pohoda-Digest package
 *
 * https://github.com/VitexSoftware/Pohoda-Digest
 *
 * (c) VitexSoftware. <https://vitexsoftware.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VitexSoftware\PohodaDigest;

use VitexSoftware\DigestModules\Core\ModuleRunner;
use VitexSoftware\DigestRenderer\DigestRenderer;
use VitexSoftware\PohodaDigest\DataProvider\PohodaDataProvider;
use VitexSoftware\PohodaDigest\Modules\PohodaOutcomingInvoices;
use VitexSoftware\PohodaDigest\Modules\PohodaDebtors;
use Ease\Shared;

/**
 * Pohoda Digest Generator
 * 
 * Main class for generating analytical digests from Pohoda accounting data.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class PohodaDigestor
{
    private PohodaDataProvider $dataProvider;
    private ModuleRunner $moduleRunner;
    private DigestRenderer $renderer;
    
    /**
     * Available modules
     *
     * @var array<string, \VitexSoftware\DigestModules\Core\ModuleInterface>
     */
    private array $availableModules;

    /**
     * Initialize Pohoda Digestor
     *
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(array $config = [])
    {
        // Initialize data provider
        $this->dataProvider = new PohodaDataProvider($config);
        
        // Initialize module runner
        $this->moduleRunner = new ModuleRunner($this->dataProvider);
        
        // Initialize renderer
        $this->renderer = new DigestRenderer();
        
        // Register available modules
        $this->initializeModules();
    }

    /**
     * Create Pohoda Digestor with environment configuration
     *
     * @param string|null $envFile Path to .env file
     * @return self
     */
    public static function createFromEnv(?string $envFile = null): self
    {
        if ($envFile && file_exists($envFile)) {
            Shared::init(['POHODA_URL', 'POHODA_USERNAME', 'POHODA_PASSWORD', 'POHODA_ICO'], $envFile);
        }
        
        return new self();
    }

    /**
     * Create Pohoda Digestor with specific modules
     *
     * @param array<string> $moduleNames Module names to include
     * @param array<string, mixed> $config Configuration array
     * @return self
     */
    public static function createWithModules(array $moduleNames, array $config = []): self
    {
        $digestor = new self($config);
        
        // Filter available modules
        $selectedModules = [];
        foreach ($moduleNames as $moduleName) {
            if (isset($digestor->availableModules[$moduleName])) {
                $selectedModules[$moduleName] = $digestor->availableModules[$moduleName];
            }
        }
        
        $digestor->availableModules = $selectedModules;
        
        return $digestor;
    }

    /**
     * Initialize available modules
     */
    private function initializeModules(): void
    {
        $this->availableModules = [
            'outcoming_invoices' => new PohodaOutcomingInvoices(),
            'debtors' => new PohodaDebtors(),
        ];
    }

    /**
     * Generate digest for the specified period
     *
     * @param \DatePeriod $period Time period to analyze
     * @param string $theme Theme name (bootstrap, email)
     * @return string Generated HTML
     */
    public function generateHtml(\DatePeriod $period, string $theme = 'bootstrap'): string
    {
        // Get JSON data from modules
        $jsonData = $this->getJsonData($period);
        
        // Set theme and render HTML
        $this->renderer->setTheme($theme);
        return $this->renderer->render($jsonData);
    }

    /**
     * Get JSON data for the specified period
     *
     * @param \DatePeriod $period Time period to analyze
     * @return array<string, mixed> JSON data structure
     */
    public function getJsonData(\DatePeriod $period): array
    {
        // Run modules and collect data
        $moduleData = [];
        $benchmarks = [];
        
        foreach ($this->availableModules as $moduleName => $module) {
            $startTime = microtime(true);
            $result = $module->process($this->dataProvider, $period);
            $endTime = microtime(true);
            
            $moduleData[$moduleName] = $result;
            $benchmarks[$moduleName] = [
                'start' => $startTime,
                'end' => $endTime,
                'duration' => $endTime - $startTime,
            ];
        }

        // Create digest structure
        return [
            'digest' => [
                'period' => [
                    'start' => $period->getStartDate()->format('Y-m-d'),
                    'end' => $period->getEndDate()->format('Y-m-d'),
                ],
                'provider' => $this->dataProvider->getSystemName(),
                'timestamp' => date('c'),
                'company' => $this->dataProvider->getCompanyInfo(),
            ],
            'modules' => $moduleData,
            'benchmarks' => $benchmarks,
        ];
    }

    /**
     * Save digest to file
     *
     * @param \DatePeriod $period Time period to analyze
     * @param string $filePath Output file path
     * @param string $format Output format (html, json)
     * @param string $theme Theme name (for HTML output)
     * @return bool Success status
     */
    public function saveToFile(
        \DatePeriod $period,
        string $filePath,
        string $format = 'html',
        string $theme = 'bootstrap'
    ): bool {
        try {
            $content = match ($format) {
                'json' => json_encode($this->getJsonData($period), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'html' => $this->generateHtml($period, $theme),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}")
            };
            
            return file_put_contents($filePath, $content) !== false;
            
        } catch (\Exception $e) {
            error_log("Failed to save digest: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send digest by email
     *
     * @param \DatePeriod $period Time period to analyze
     * @param string $toEmail Recipient email address
     * @param string $theme Theme name (email recommended)
     * @param array<string, mixed> $emailOptions Additional email options
     * @return bool Success status
     */
    public function sendByEmail(
        \DatePeriod $period,
        string $toEmail,
        string $theme = 'email',
        array $emailOptions = []
    ): bool {
        try {
            $html = $this->generateHtml($period, $theme);
            $companyInfo = $this->dataProvider->getCompanyInfo();
            
            $subject = $emailOptions['subject'] ?? 
                "Pohoda Digest Report - {$period->getStartDate()->format('M Y')} - {$companyInfo['name']}";
            
            $headers = [
                'From' => $emailOptions['from'] ?? 'noreply@' . gethostname(),
                'Content-Type' => 'text/html; charset=UTF-8',
                'MIME-Version' => '1.0',
            ];
            
            $headerString = '';
            foreach ($headers as $key => $value) {
                $headerString .= "{$key}: {$value}\r\n";
            }
            
            return mail($toEmail, $subject, $html, $headerString);
            
        } catch (\Exception $e) {
            error_log("Failed to send digest email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test Pohoda connection
     *
     * @return bool Connection status
     */
    public function testConnection(): bool
    {
        return $this->dataProvider->testConnection();
    }

    /**
     * Get available modules
     *
     * @return array<string> Module names
     */
    public function getAvailableModules(): array
    {
        return array_keys($this->availableModules);
    }

    /**
     * Get data provider instance
     *
     * @return PohodaDataProvider
     */
    public function getDataProvider(): PohodaDataProvider
    {
        return $this->dataProvider;
    }

    /**
     * Get renderer instance
     *
     * @return DigestRenderer
     */
    public function getRenderer(): DigestRenderer
    {
        return $this->renderer;
    }
}