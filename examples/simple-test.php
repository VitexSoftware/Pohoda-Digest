<?php

declare(strict_types=1);

/**
 * Simple Pohoda Digest Test
 *
 * Tests the complete pipeline with Pohoda data
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use VitexSoftware\PohodaDigest\PohodaDigestor;

echo "=== Pohoda Digest Test ===\n\n";

try {
    echo "1. Creating Pohoda digestor...\n";
    $digestor = PohodaDigestor::createFromEnv(dirname(__DIR__) . '/.env');
    
    echo "2. Testing connection...\n";
    if (!$digestor->testConnection()) {
        throw new Exception("Cannot connect to Pohoda server");
    }
    echo "   ✓ Connection successful\n";
    
    // Show company info
    $companyInfo = $digestor->getDataProvider()->getCompanyInfo();
    echo "   Company: {$companyInfo['name']}\n";
    echo "   Server: {$companyInfo['server_url']}\n\n";
    
    echo "3. Creating time period (current month)...\n";
    $start = new DateTime('first day of this month');
    $end = new DateTime('last day of this month');
    $period = new DatePeriod($start, new DateInterval('P1D'), $end->add(new DateInterval('P1D')));
    echo "   Period: {$start->format('Y-m-d')} to {$end->format('Y-m-d')}\n\n";
    
    echo "4. Running data collection modules...\n";
    $jsonData = $digestor->getJsonData($period);
    echo "   ✓ Modules executed successfully\n";
    echo "   Available modules: " . implode(', ', array_keys($jsonData['modules'])) . "\n\n";
    
    echo "5. JSON Output Summary:\n";
    echo "   Company: {$jsonData['digest']['company']['name']}\n";
    echo "   Provider: {$jsonData['digest']['provider']}\n";
    echo "   Modules processed: " . count($jsonData['modules']) . "\n";
    
    // Show module results summary
    foreach ($jsonData['modules'] as $moduleName => $moduleData) {
        echo "   - {$moduleName}: " . ($moduleData['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        if ($moduleData['success'] && isset($moduleData['metadata']['records_processed'])) {
            echo "     Records processed: {$moduleData['metadata']['records_processed']}\n";
        }
    }
    echo "\n";
    
    echo "6. Generating HTML with Bootstrap theme...\n";
    $html = $digestor->generateHtml($period, 'bootstrap');
    echo "   ✓ HTML generated successfully (" . strlen($html) . " characters)\n\n";
    
    echo "7. Testing JSON output...\n";
    $jsonOutput = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonOutput) {
        echo "   ✓ JSON serialization successful (" . strlen($jsonOutput) . " characters)\n";
        
        // Show first 500 characters of JSON
        echo "\nJSON Preview (first 500 chars):\n";
        echo substr($jsonOutput, 0, 500) . "...\n\n";
    }
    
    echo "=== Test completed successfully ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}