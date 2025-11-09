<?php

declare(strict_types=1);

/**
 * Simple Pohoda Digest Test with Mock Data
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use VitexSoftware\PohodaDigest\PohodaDigestor;
use VitexSoftware\PohodaDigest\DataProvider\PohodaDataProvider;

echo "=== Pohoda Digest Test with Mock Data ===\n\n";

try {
    echo "1. Setting mock environment configuration...\n";
    
    // Set mock environment variables to prevent errors
    putenv('POHODA_URL=mock://pohoda.local');
    putenv('POHODA_USERNAME=mock');
    putenv('POHODA_PASSWORD=mock');
    putenv('POHODA_ICO=12345678');
    
    echo "2. Creating mock Pohoda data provider...\n";
    
    // Create a mock data provider for testing
    class MockPohodaDataProvider extends PohodaDataProvider {
        public function testConnection(): bool {
            return true;
        }
        
        public function getCompanyInfo(): array {
            return [
                'name' => 'Mock Pohoda Company Ltd.',
                'ico' => '12345678',
                'system' => 'Pohoda (Mock)',
                'server_url' => 'mock://pohoda.local',
            ];
        }
        
        public function getData(string $entity, array $conditions = [], array $columns = []): array {
            switch ($entity) {
                case 'invoices':
                    return [
                        [
                            'id' => 'MOCK001',
                            'number' => 'FAV001',
                            'date' => '2024-11-01',
                            'due_date' => '2024-11-15',
                            'amount' => 25000.0,
                            'currency' => 'CZK',
                            'document_type' => 'FAKTURA',
                            'partner_name' => 'ABC Company s.r.o.',
                            'partner_ico' => '87654321',
                            'state' => 'active',
                        ],
                        [
                            'id' => 'MOCK002',
                            'number' => 'FAV002',
                            'date' => '2024-11-05',
                            'due_date' => '2024-11-20',
                            'amount' => 15000.0,
                            'currency' => 'CZK',
                            'document_type' => 'FAKTURA',
                            'partner_name' => 'XYZ Trading a.s.',
                            'partner_ico' => '11223344',
                            'state' => 'active',
                        ],
                    ];
                    
                case 'overdue_invoices':
                    return [
                        [
                            'id' => 'MOCK003',
                            'number' => 'FAV003',
                            'date' => '2024-09-01',
                            'due_date' => '2024-09-15',
                            'amount' => 8000.0,
                            'currency' => 'CZK',
                            'document_type' => 'FAKTURA',
                            'partner_name' => 'Overdue Client Ltd.',
                            'partner_ico' => '99887766',
                            'state' => 'active',
                        ],
                    ];
                    
                default:
                    return [];
            }
        }
    }
    
    // Create digestor with mock provider
    $mockProvider = new MockPohodaDataProvider();
    $digestor = new \VitexSoftware\PohodaDigest\PohodaDigestor();
    
    // Replace the data provider with our mock
    $reflection = new ReflectionClass($digestor);
    $providerProperty = $reflection->getProperty('dataProvider');
    $providerProperty->setAccessible(true);
    $providerProperty->setValue($digestor, $mockProvider);
    
    echo "3. Testing connection...\n";
    if ($digestor->testConnection()) {
        echo "   ✓ Connection successful (mock)\n";
    } else {
        throw new Exception("Mock connection failed");
    }
    
    // Show company info
    $companyInfo = $mockProvider->getCompanyInfo();
    echo "   Company: {$companyInfo['name']}\n";
    echo "   System: {$companyInfo['system']}\n\n";
    
    echo "4. Creating time period (November 2024)...\n";
    $start = new DateTime('2024-11-01');
    $end = new DateTime('2024-11-30');
    $period = new DatePeriod($start, new DateInterval('P1D'), $end->add(new DateInterval('P1D')));
    echo "   Period: {$start->format('Y-m-d')} to {$end->format('Y-m-d')}\n\n";
    
    echo "5. Running data collection modules...\n";
    $jsonData = $digestor->getJsonData($period);
    echo "   ✓ Modules executed successfully\n";
    echo "   Available modules: " . implode(', ', array_keys($jsonData['modules'])) . "\n\n";
    
    echo "6. JSON Output Summary:\n";
    echo "   Company: {$jsonData['digest']['company']['name']}\n";
    echo "   Provider: {$jsonData['digest']['provider']}\n";
    echo "   Modules processed: " . count($jsonData['modules']) . "\n";
    
    // Show module results summary
    foreach ($jsonData['modules'] as $moduleName => $moduleData) {
        echo "   - {$moduleName}: " . ($moduleData['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        if ($moduleData['success'] && isset($moduleData['metadata']['records_processed'])) {
            echo "     Records processed: {$moduleData['metadata']['records_processed']}\n";
        }
        if ($moduleData['success'] && isset($moduleData['data']['summary'])) {
            $summary = $moduleData['data']['summary'];
            if (isset($summary['total_count'])) {
                echo "     Total records: {$summary['total_count']}\n";
            }
            if (isset($summary['total_overdue_count'])) {
                echo "     Overdue records: {$summary['total_overdue_count']}\n";
            }
        }
    }
    echo "\n";
    
    echo "7. Generating HTML with Bootstrap theme...\n";
    $html = $digestor->generateHtml($period, 'bootstrap');
    echo "   ✓ HTML generated successfully (" . strlen($html) . " characters)\n";
    
    // Show first 500 characters of HTML
    echo "\nHTML Preview (first 500 chars):\n";
    echo substr($html, 0, 500) . "...\n\n";
    
    echo "8. Testing JSON output...\n";
    $jsonOutput = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonOutput) {
        echo "   ✓ JSON serialization successful (" . strlen($jsonOutput) . " characters)\n";
    }
    
    echo "\n=== Test completed successfully ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}