<?php declare(strict_types=1);

/**
 * Example: Advanced Configuration and Customization
 * 
 * This example demonstrates advanced features like custom modules,
 * error handling, caching, and email notifications.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use VitexSoftware\PohodaDigest\PohodaDataProvider;
use VitexSoftware\DigestModules\Core\ModuleRunner;
use VitexSoftware\DigestRenderer\EmailTheme;

// Configuration from environment or config file
$config = [
    'pohoda' => [
        'host' => $_ENV['POHODA_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['POHODA_PORT'] ?? 5435),
        'database' => $_ENV['POHODA_DATABASE'] ?? 'StwPh_12345678_2024'
    ],
    'email' => [
        'smtp_host' => $_ENV['SMTP_HOST'] ?? '',
        'smtp_port' => (int)($_ENV['SMTP_PORT'] ?? 587),
        'from_email' => $_ENV['FROM_EMAIL'] ?? 'reports@company.com',
        'to_emails' => explode(',', $_ENV['TO_EMAILS'] ?? 'manager@company.com'),
        'subject' => 'Daily Business Analytics Report'
    ],
    'cache_ttl' => (int)($_ENV['CACHE_TTL'] ?? 3600),
    'output_dir' => $_ENV['OUTPUT_DIR'] ?? __DIR__ . '/output'
];

/**
 * Custom error handler for analytics generation
 */
function handleAnalyticsError(\Throwable $e, string $context = ''): array
{
    $error = [
        'error' => true,
        'message' => $e->getMessage(),
        'context' => $context,
        'timestamp' => date('c'),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];

    // Log error (in production, use proper logging)
    error_log("Pohoda-Digest Error [$context]: " . $e->getMessage());

    return $error;
}

/**
 * Send email with error handling
 */
function sendEmailReport(array $config, string $htmlContent, array $analyticsData): bool
{
    if (empty($config['email']['smtp_host'])) {
        echo "⚠️  Email not configured - skipping email notification\n";
        return false;
    }

    try {
        $subject = sprintf('%s - %s', 
                          $config['email']['subject'], 
                          date('Y-m-d H:i'));
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $config['email']['from_email'],
            'X-Analytics-Generated: ' . $analyticsData['generated_at'] ?? date('c')
        ];

        foreach ($config['email']['to_emails'] as $email) {
            $email = trim($email);
            if (!empty($email)) {
                $success = mail($email, $subject, $htmlContent, implode("\r\n", $headers));
                if ($success) {
                    echo "📧 Report sent to: $email\n";
                } else {
                    echo "❌ Failed to send email to: $email\n";
                }
            }
        }

        return true;

    } catch (\Exception $e) {
        echo "❌ Email error: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Generate summary statistics
 */
function generateSummaryStats(array $modulesData): array
{
    $summary = [
        'total_modules' => count($modulesData),
        'successful_modules' => 0,
        'failed_modules' => 0,
        'total_processing_time' => 0,
        'key_metrics' => []
    ];

    foreach ($modulesData as $moduleName => $data) {
        if (isset($data['error'])) {
            $summary['failed_modules']++;
        } else {
            $summary['successful_modules']++;
            $summary['total_processing_time'] += $data['metadata']['processing_time'] ?? 0;

            // Extract key metrics
            if ($moduleName === 'outcoming_invoices' && isset($data['summary'])) {
                $summary['key_metrics']['total_invoices'] = $data['summary']['count'] ?? 0;
                $summary['key_metrics']['total_revenue'] = $data['summary']['total_amount'] ?? 0;
                $summary['key_metrics']['currency'] = $data['summary']['currency'] ?? 'CZK';
            }

            if ($moduleName === 'debtors' && isset($data['summary'])) {
                $summary['key_metrics']['overdue_customers'] = $data['summary']['count'] ?? 0;
                $summary['key_metrics']['total_overdue'] = $data['summary']['total_overdue'] ?? 0;
            }
        }
    }

    $summary['success_rate'] = $summary['total_modules'] > 0 
        ? ($summary['successful_modules'] / $summary['total_modules']) * 100 
        : 0;

    return $summary;
}

// Main execution
try {
    echo "🚀 Starting advanced Pohoda analytics generation...\n";
    echo "Configuration loaded from environment variables\n";

    // Ensure output directory exists
    if (!is_dir($config['output_dir'])) {
        mkdir($config['output_dir'], 0755, true);
        echo "📁 Created output directory: {$config['output_dir']}\n";
    }

    // Connect to Pohoda
    echo "\n📡 Connecting to Pohoda mServer...\n";
    $dataProvider = new PohodaDataProvider(
        $config['pohoda']['host'],
        $config['pohoda']['port'],
        $config['pohoda']['database']
    );

    if (!$dataProvider->isAvailable()) {
        throw new \RuntimeException('Pohoda mServer is not available');
    }

    echo "✅ Connected successfully!\n";
    $systemInfo = $dataProvider->getSystemInfo();

    // Run all modules with error handling
    echo "\n🔄 Running analytics modules...\n";
    $runner = new ModuleRunner($dataProvider);
    $modules = ['outcoming_invoices', 'debtors'];
    $modulesData = [];

    foreach ($modules as $moduleName) {
        echo "   Processing: $moduleName... ";
        
        try {
            $moduleData = $runner->runModule($moduleName);
            $modulesData[$moduleName] = $moduleData;
            
            echo sprintf("✅ Success (%.3fs)\n", 
                        $moduleData['metadata']['processing_time'] ?? 0);

        } catch (\Exception $e) {
            echo "❌ Failed\n";
            $modulesData[$moduleName] = handleAnalyticsError($e, "Module: $moduleName");
        }
    }

    // Generate summary statistics
    $summaryStats = generateSummaryStats($modulesData);
    echo sprintf("\n📊 Generation Summary:\n");
    echo sprintf("   - Success Rate: %.1f%% (%d/%d modules)\n", 
                $summaryStats['success_rate'],
                $summaryStats['successful_modules'],
                $summaryStats['total_modules']);
    echo sprintf("   - Total Processing Time: %.3fs\n", $summaryStats['total_processing_time']);

    if (!empty($summaryStats['key_metrics'])) {
        echo "   - Key Metrics:\n";
        foreach ($summaryStats['key_metrics'] as $metric => $value) {
            echo sprintf("     %s: %s\n", 
                        ucwords(str_replace('_', ' ', $metric)),
                        is_numeric($value) ? number_format($value, 2) : $value);
        }
    }

    // Generate reports only if we have successful modules
    if ($summaryStats['successful_modules'] > 0) {
        echo "\n🎨 Generating reports...\n";

        // Generate email report
        $emailTheme = new EmailTheme();
        $validModules = array_filter($modulesData, fn($data) => !isset($data['error']));
        $emailReport = $emailTheme->renderPage(array_values($validModules));

        // Save reports
        $timestamp = date('Y-m-d_H-i-s');
        $emailFile = "{$config['output_dir']}/pohoda_report_{$timestamp}.html";
        $jsonFile = "{$config['output_dir']}/pohoda_data_{$timestamp}.json";

        file_put_contents($emailFile, $emailReport);
        echo "✅ Email report saved: $emailFile\n";

        // Prepare complete JSON export
        $jsonData = [
            'generated_at' => date('c'),
            'system_info' => $systemInfo,
            'summary_stats' => $summaryStats,
            'modules' => $modulesData,
            'configuration' => [
                'host' => $config['pohoda']['host'],
                'database' => $config['pohoda']['database'],
                'cache_ttl' => $config['cache_ttl']
            ]
        ];

        file_put_contents($jsonFile, json_encode($jsonData, JSON_PRETTY_PRINT));
        echo "✅ JSON data saved: $jsonFile\n";

        // Send email notification
        echo "\n📧 Sending email notification...\n";
        sendEmailReport($config, $emailReport, $jsonData);

    } else {
        echo "\n⚠️  No successful modules - skipping report generation\n";
    }

    echo "\n🎉 Analytics generation completed!\n";

} catch (\Exception $e) {
    echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
    
    // Try to send error notification
    if (!empty($config['email']['smtp_host'])) {
        $errorContent = sprintf(
            '<h1>Pohoda-Digest Error</h1>
             <p><strong>Error:</strong> %s</p>
             <p><strong>File:</strong> %s:%d</p>
             <p><strong>Time:</strong> %s</p>',
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getFile()),
            $e->getLine(),
            date('Y-m-d H:i:s')
        );
        
        sendEmailReport($config, $errorContent, ['generated_at' => date('c')]);
    }
    
    exit(1);
}