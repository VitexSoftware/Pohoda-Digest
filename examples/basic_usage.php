<?php declare(strict_types=1);

/**
 * Example: Basic Pohoda-Digest Usage
 * 
 * This example shows how to use Pohoda-Digest to generate analytics reports
 * from your Money S3/Pohoda accounting system.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use VitexSoftware\PohodaDigest\PohodaDataProvider;
use VitexSoftware\DigestModules\Core\ModuleRunner;
use VitexSoftware\DigestRenderer\BootstrapTheme;
use VitexSoftware\DigestRenderer\EmailTheme;

// Configuration - adjust these values for your Pohoda installation
$config = [
    'host' => 'localhost',
    'port' => 5435,
    'database' => 'StwPh_12345678_2024', // Your Pohoda database name
    'username' => '',  // If authentication is required
    'password' => ''   // If authentication is required
];

try {
    // Step 1: Connect to Pohoda
    echo "📡 Connecting to Pohoda mServer...\n";
    $dataProvider = new PohodaDataProvider(
        $config['host'],
        $config['port'], 
        $config['database']
    );

    // Test connection
    if (!$dataProvider->isAvailable()) {
        throw new \RuntimeException('Cannot connect to Pohoda mServer. Check your configuration.');
    }

    echo "✅ Connected to Pohoda successfully!\n";
    $systemInfo = $dataProvider->getSystemInfo();
    echo "📊 System: {$systemInfo['system']} v{$systemInfo['version']}\n";

    // Step 2: Run analytics modules
    echo "\n🔄 Running analytics modules...\n";
    $runner = new ModuleRunner($dataProvider);

    // Get invoice analysis
    echo "📈 Analyzing outcoming invoices...\n";
    $invoiceData = $runner->runModule('outcoming_invoices');
    echo sprintf("   Found %d invoices, total: %s %s\n", 
                $invoiceData['summary']['count'],
                number_format($invoiceData['summary']['total_amount'], 2),
                $invoiceData['summary']['currency']);

    // Get debtor analysis  
    echo "💰 Analyzing overdue receivables...\n";
    $debtorData = $runner->runModule('debtors');
    echo sprintf("   Found %d overdue customers, total: %s %s\n",
                $debtorData['summary']['count'] ?? 0,
                number_format($debtorData['summary']['total_overdue'] ?? 0, 2),
                $debtorData['summary']['currency'] ?? 'CZK');

    // Step 3: Generate HTML reports
    echo "\n🎨 Generating HTML reports...\n";

    // Bootstrap theme for modern web display
    $bootstrapTheme = new BootstrapTheme();
    $webReport = $bootstrapTheme->renderPage([$invoiceData, $debtorData]);
    
    file_put_contents(__DIR__ . '/output/web_report.html', $webReport);
    echo "✅ Web report saved to: output/web_report.html\n";

    // Email theme for email notifications
    $emailTheme = new EmailTheme();
    $emailReport = $emailTheme->renderPage([$invoiceData, $debtorData]);
    
    file_put_contents(__DIR__ . '/output/email_report.html', $emailReport);
    echo "✅ Email report saved to: output/email_report.html\n";

    // Step 4: Save JSON data for API or further processing
    $jsonData = [
        'generated_at' => date('c'),
        'system_info' => $systemInfo,
        'modules' => [
            'outcoming_invoices' => $invoiceData,
            'debtors' => $debtorData
        ]
    ];

    file_put_contents(__DIR__ . '/output/analytics_data.json', 
                     json_encode($jsonData, JSON_PRETTY_PRINT));
    echo "✅ JSON data saved to: output/analytics_data.json\n";

    echo "\n🎉 Analytics generation completed successfully!\n";
    echo "Open the HTML files in your browser to view the reports.\n";

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}