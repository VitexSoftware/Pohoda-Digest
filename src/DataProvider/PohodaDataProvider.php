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

namespace VitexSoftware\PohodaDigest\DataProvider;

use VitexSoftware\DigestModules\Core\DataProviderInterface;
use mServer\Client;
use mServer\Invoice;
use mServer\Bank;
use mServer\UserList;
use Ease\Shared;

/**
 * Pohoda Data Provider
 * 
 * Implements DataProviderInterface for accessing Pohoda accounting data
 * through the PHP-Pohoda-Connector.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class PohodaDataProvider implements DataProviderInterface
{
    private Client $client;
    private Invoice $invoiceClient;
    private Bank $bankClient;
    private array $connectionInfo;

    /**
     * Initialize Pohoda data provider
     *
     * @param array<string, mixed> $config Configuration array with connection details
     */
    public function __construct(array $config = [])
    {
        // Load configuration from environment or provided config
        if (empty($config)) {
            Shared::init(['POHODA_URL', 'POHODA_USERNAME', 'POHODA_PASSWORD', 'POHODA_ICO']);
            $config = [
                'url' => Shared::cfg('POHODA_URL'),
                'username' => Shared::cfg('POHODA_USERNAME'),
                'password' => Shared::cfg('POHODA_PASSWORD'),
                'ico' => Shared::cfg('POHODA_ICO'),
            ];
        } else {
            // Set environment variables for Pohoda connector
            if (isset($config['url'])) {
                Shared::cfg('POHODA_URL', $config['url']);
            }
            if (isset($config['username'])) {
                Shared::cfg('POHODA_USERNAME', $config['username']);
            }
            if (isset($config['password'])) {
                Shared::cfg('POHODA_PASSWORD', $config['password']);
            }
            if (isset($config['ico'])) {
                Shared::cfg('POHODA_ICO', $config['ico']);
            }
        }

        $this->connectionInfo = $config;
        $this->initializeClients();
    }

    /**
     * Initialize Pohoda client connections
     */
    private function initializeClients(): void
    {
        $this->client = new Client();
        $this->invoiceClient = new Invoice();
        $this->bankClient = new Bank();
    }

    /**
     * Configure individual client with connection details
     *
     * @param Client $client Client to configure
     */
    private function configureClient(Client $client): void
    {
        // Pohoda clients are configured via global Shared configuration
        // No additional configuration needed here
    }

    /**
     * Get data from the accounting system
     *
     * @param string $entity Entity type (invoices, customers, etc.)
     * @param array<string, mixed> $conditions Query conditions
     * @param array<string> $columns Columns to retrieve
     * @return array<array<string, mixed>> Raw data from the system
     */
    public function getData(string $entity, array $conditions = [], array $columns = []): array
    {
        switch ($entity) {
            case 'invoices':
                $dateFrom = $conditions['dateFrom'] ?? date('Y-m-01');
                $dateTo = $conditions['dateTo'] ?? date('Y-m-t');
                return $this->getInvoices($dateFrom, $dateTo, $conditions);
                
            case 'overdue_invoices':
                $asOfDate = $conditions['asOfDate'] ?? date('Y-m-d');
                return $this->getOverdueInvoices($asOfDate, $conditions);
                
            case 'bank_transactions':
                $dateFrom = $conditions['dateFrom'] ?? date('Y-m-01');
                $dateTo = $conditions['dateTo'] ?? date('Y-m-t');
                return $this->getBankTransactions($dateFrom, $dateTo, $conditions);
                
            default:
                return [];
        }
    }

    /**
     * Get system name/type
     *
     * @return string Name of the accounting system
     */
    public function getSystemName(): string
    {
        return 'pohoda';
    }

    /**
     * Get supported entities
     *
     * @return array<string> List of supported entity types
     */
    public function getSupportedEntities(): array
    {
        return ['invoices', 'overdue_invoices', 'bank_transactions', 'customers', 'suppliers'];
    }

    /**
     * Check if provider supports a specific feature
     *
     * @param string $feature Feature name
     * @return bool Whether the feature is supported
     */
    public function supportsFeature(string $feature): bool
    {
        $supportedFeatures = [
            'invoice_analysis',
            'overdue_tracking',
            'bank_transactions',
            'multi_currency',
            'date_filtering',
        ];
        
        return in_array($feature, $supportedFeatures, true);
    }

    /**
     * Format date for the accounting system
     *
     * @param \DateTime $date Date to format
     * @return string Formatted date string
     */
    public function formatDate(\DateTime $date): string
    {
        return $date->format('Y-m-d');
    }

    /**
     * Format date period condition for queries
     *
     * @param string $column Date column name
     * @param \DatePeriod $period Time period
     * @return string Formatted condition
     */
    public function formatDatePeriod(string $column, \DatePeriod $period): string
    {
        $start = $period->getStartDate()->format('Y-m-d');
        $end = $period->getEndDate()->format('Y-m-d');
        
        return "{$column} >= '{$start}' AND {$column} <= '{$end}'";
    }

    /**
     * Get company information
     *
     * @return array<string, mixed> Company data
     */
    public function getCompanyInfo(): array
    {
        return [
            'name' => $this->connectionInfo['ico'] . ' Company', // Pohoda doesn't directly expose company name in API
            'ico' => $this->connectionInfo['ico'],
            'system' => 'Pohoda',
            'server_url' => $this->connectionInfo['url'],
        ];
    }

    /**
     * Get invoices for the specified period
     *
     * @param string $dateFrom Start date (YYYY-MM-DD)
     * @param string $dateTo End date (YYYY-MM-DD)
     * @param array<string, mixed> $filters Additional filters
     * @return array<int, array<string, mixed>> Invoice records
     */
    public function getInvoices(string $dateFrom, string $dateTo, array $filters = []): array
    {
        // Prepare date filter for Pohoda API
        $pohodaFilter = [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];

        // Add additional filters
        if (isset($filters['types'])) {
            $pohodaFilter['documentTypes'] = $filters['types'];
        }

        try {
            // Load invoices from Pohoda
            $invoiceData = $this->invoiceClient->loadFromPohoda($pohodaFilter);
            
            if (!$invoiceData || !is_array($invoiceData)) {
                return [];
            }

            // Convert Pohoda invoice format to standardized format
            return $this->normalizeInvoiceData($invoiceData);
            
        } catch (\Exception $e) {
            // Log error but don't fail completely
            error_log("Pohoda invoice fetch error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get overdue invoices
     *
     * @param string $asOfDate Date to check overdue status (YYYY-MM-DD)
     * @param array<string, mixed> $filters Additional filters
     * @return array<int, array<string, mixed>> Overdue invoice records
     */
    public function getOverdueInvoices(string $asOfDate, array $filters = []): array
    {
        // Get all unpaid invoices up to the specified date
        $pohodaFilter = [
            'dateTo' => $asOfDate,
            'paid' => false,
            'dueDate' => '<' . $asOfDate,  // Due date before as-of date
        ];

        try {
            $invoiceData = $this->invoiceClient->loadFromPohoda($pohodaFilter);
            
            if (!$invoiceData || !is_array($invoiceData)) {
                return [];
            }

            // Filter for truly overdue invoices and normalize
            $overdueInvoices = array_filter($invoiceData, function ($invoice) use ($asOfDate) {
                $dueDate = $invoice['dueDate'] ?? $invoice['dateDue'] ?? null;
                return $dueDate && $dueDate < $asOfDate;
            });

            return $this->normalizeInvoiceData($overdueInvoices);
            
        } catch (\Exception $e) {
            error_log("Pohoda overdue invoices fetch error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get bank transactions for the specified period
     *
     * @param string $dateFrom Start date (YYYY-MM-DD)
     * @param string $dateTo End date (YYYY-MM-DD)
     * @param array<string, mixed> $filters Additional filters
     * @return array<int, array<string, mixed>> Bank transaction records
     */
    public function getBankTransactions(string $dateFrom, string $dateTo, array $filters = []): array
    {
        $pohodaFilter = [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];

        if (isset($filters['account_ids'])) {
            $pohodaFilter['accountIds'] = $filters['account_ids'];
        }

        try {
            $bankData = $this->bankClient->loadFromPohoda($pohodaFilter);
            
            if (!$bankData || !is_array($bankData)) {
                return [];
            }

            return $this->normalizeBankData($bankData);
            
        } catch (\Exception $e) {
            error_log("Pohoda bank transactions fetch error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Test the connection to Pohoda
     *
     * @return bool True if connection is successful
     */
    public function testConnection(): bool
    {
        try {
            // Try to create a simple client and test basic connection
            $testClient = new UserList();
            $result = $testClient->loadFromPohoda();
            
            // If we get any response (even empty), connection is working
            return true;
        } catch (\Exception $e) {
            error_log("Pohoda connection test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Normalize invoice data to standard format
     *
     * @param array<int, array<string, mixed>> $invoiceData Raw Pohoda invoice data
     * @return array<int, array<string, mixed>> Normalized invoice data
     */
    private function normalizeInvoiceData(array $invoiceData): array
    {
        $normalized = [];

        foreach ($invoiceData as $invoice) {
            $normalized[] = [
                'id' => $invoice['id'] ?? $invoice['number'] ?? uniqid(),
                'number' => $invoice['number'] ?? $invoice['numberRequested'] ?? '',
                'date' => $invoice['date'] ?? $invoice['dateAccounting'] ?? '',
                'due_date' => $invoice['dateDue'] ?? $invoice['dueDate'] ?? '',
                'amount' => $this->parseAmount($invoice['homeCurrency'] ?? $invoice['foreignCurrency'] ?? []),
                'currency' => $this->parseCurrency($invoice['homeCurrency'] ?? $invoice['foreignCurrency'] ?? []),
                'document_type' => $invoice['invoiceType'] ?? $invoice['documentType'] ?? 'INVOICE',
                'partner_name' => $invoice['partnerIdentity']['address']['name'] ?? 
                                $invoice['address']['name'] ?? 'Unknown',
                'partner_ico' => $invoice['partnerIdentity']['address']['ico'] ?? 
                                $invoice['address']['ico'] ?? '',
                'state' => $invoice['state'] ?? 'active',
                'raw_data' => $invoice, // Keep original for debugging
            ];
        }

        return $normalized;
    }

    /**
     * Normalize bank transaction data to standard format
     *
     * @param array<int, array<string, mixed>> $bankData Raw Pohoda bank data
     * @return array<int, array<string, mixed>> Normalized bank data
     */
    private function normalizeBankData(array $bankData): array
    {
        $normalized = [];

        foreach ($bankData as $transaction) {
            $normalized[] = [
                'id' => $transaction['id'] ?? uniqid(),
                'date' => $transaction['date'] ?? $transaction['dateStatement'] ?? '',
                'amount' => $this->parseAmount($transaction['homeCurrency'] ?? []),
                'currency' => $this->parseCurrency($transaction['homeCurrency'] ?? []),
                'description' => $transaction['text'] ?? $transaction['note'] ?? '',
                'account' => $transaction['account'] ?? '',
                'type' => $transaction['symVar'] ?? 'transfer',
                'raw_data' => $transaction,
            ];
        }

        return $normalized;
    }

    /**
     * Parse amount from Pohoda currency structure
     *
     * @param array<string, mixed> $currencyData Pohoda currency data
     * @return float Parsed amount
     */
    private function parseAmount(array $currencyData): float
    {
        if (isset($currencyData['priceNone'])) {
            return (float) $currencyData['priceNone'];
        }
        if (isset($currencyData['price'])) {
            return (float) $currencyData['price'];
        }
        if (isset($currencyData['amount'])) {
            return (float) $currencyData['amount'];
        }
        
        return 0.0;
    }

    /**
     * Parse currency from Pohoda currency structure
     *
     * @param array<string, mixed> $currencyData Pohoda currency data
     * @return string Currency code
     */
    private function parseCurrency(array $currencyData): string
    {
        return $currencyData['currency']['ids'] ?? 
               $currencyData['currency'] ?? 
               'CZK'; // Default for Czech Pohoda installations
    }
}