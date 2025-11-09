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

namespace VitexSoftware\PohodaDigest\Modules;

use VitexSoftware\DigestModules\Core\AbstractModule;
use VitexSoftware\DigestModules\Core\DataProviderInterface;

/**
 * Pohoda Outcoming Invoices Analysis Module
 * 
 * Analyzes issued invoices data from Pohoda accounting system,
 * providing comprehensive statistics and breakdowns.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class PohodaOutcomingInvoices extends AbstractModule
{
    protected string $moduleName = 'outcoming_invoices';
    protected string $heading = 'Outcoming Invoices';
    protected string $description = 'Analysis of issued invoices including totals, document types, and currency breakdown';

    /**
     * Process data for the given period using the provided data source
     *
     * @param DataProviderInterface $provider Data source provider
     * @param \DatePeriod $period Time period to analyze
     * @return array<string, mixed> Structured data array with module results
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        $periodArray = [
            'start' => $period->getStartDate()->format('Y-m-d'),
            'end' => $period->getEndDate()->format('Y-m-d'),
        ];
        
        return $this->execute($periodArray, $provider);
    }

    /**
     * Execute the module analysis
     *
     * @param array<string, mixed> $period Time period for analysis
     * @param DataProviderInterface $dataProvider Data provider instance
     * @return array<string, mixed> Analysis results
     */
    public function execute(array $period, DataProviderInterface $dataProvider): array
    {
        $startTime = microtime(true);
        
        try {
            // Get invoice data from Pohoda
            $invoices = $dataProvider->getData('invoices', [
                'dateFrom' => $period['start'],
                'dateTo' => $period['end'],
            ]);

            // Perform analysis
            $analysis = $this->analyzeInvoices($invoices);
            
            $executionTime = microtime(true) - $startTime;
            
            return [
                'module_name' => $this->moduleName,
                'heading' => $this->heading,
                'description' => $this->description,
                'period' => $period,
                'success' => true,
                'data' => $analysis,
                'metadata' => [
                    'timestamp' => date('c'),
                    'provider' => $dataProvider->getSystemName(),
                    'processing_time' => $executionTime,
                    'records_processed' => count($invoices),
                ],
            ];
            
        } catch (\Exception $e) {
            return [
                'module_name' => $this->moduleName,
                'heading' => $this->heading,
                'description' => $this->description,
                'period' => $period,
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
                'metadata' => [
                    'timestamp' => date('c'),
                    'provider' => $dataProvider->getSystemName(),
                    'processing_time' => microtime(true) - $startTime,
                ],
            ];
        }
    }

    /**
     * Analyze invoice data and calculate statistics
     *
     * @param array<int, array<string, mixed>> $invoices Invoice records
     * @return array<string, mixed> Analysis results
     */
    private function analyzeInvoices(array $invoices): array
    {
        if (empty($invoices)) {
            return [
                'summary' => [
                    'total_count' => 0,
                    'active_count' => 0,
                    'cancelled_count' => 0,
                    'document_types_count' => 0,
                    'currencies' => [],
                ],
                'totals_by_currency' => [],
                'by_document_type' => [],
                'by_status' => [],
                'top_customers' => [],
            ];
        }

        // Initialize analysis arrays
        $totalsByCurrency = [];
        $byDocumentType = [];
        $byStatus = [];
        $customerTotals = [];
        $currencies = [];
        $documentTypes = [];
        
        $totalCount = count($invoices);
        $activeCount = 0;
        $cancelledCount = 0;

        // Process each invoice
        foreach ($invoices as $invoice) {
            $amount = (float) ($invoice['amount'] ?? 0);
            $currency = $invoice['currency'] ?? 'CZK';
            $documentType = $invoice['document_type'] ?? 'FAKTURA';
            $state = $invoice['state'] ?? 'active';
            $partnerName = $invoice['partner_name'] ?? 'Unknown';

            // Track currencies
            if (!in_array($currency, $currencies, true)) {
                $currencies[] = $currency;
            }

            // Track document types
            if (!in_array($documentType, $documentTypes, true)) {
                $documentTypes[] = $documentType;
            }

            // Count by status
            if ($state === 'cancelled') {
                $cancelledCount++;
            } else {
                $activeCount++;
            }

            // Skip cancelled invoices from financial totals
            if ($state === 'cancelled') {
                continue;
            }

            // Totals by currency
            if (!isset($totalsByCurrency[$currency])) {
                $totalsByCurrency[$currency] = 0.0;
            }
            $totalsByCurrency[$currency] += $amount;

            // Totals by document type
            if (!isset($byDocumentType[$documentType])) {
                $byDocumentType[$documentType] = [
                    'count' => 0,
                    'totals' => [],
                ];
            }
            $byDocumentType[$documentType]['count']++;
            
            if (!isset($byDocumentType[$documentType]['totals'][$currency])) {
                $byDocumentType[$documentType]['totals'][$currency] = 0.0;
            }
            $byDocumentType[$documentType]['totals'][$currency] += $amount;

            // Customer totals for top customers analysis
            if (!isset($customerTotals[$partnerName])) {
                $customerTotals[$partnerName] = [
                    'name' => $partnerName,
                    'count' => 0,
                    'total' => 0.0,
                    'currency' => $currency,
                ];
            }
            $customerTotals[$partnerName]['count']++;
            $customerTotals[$partnerName]['total'] += $amount;
        }

        // Format currency totals with proper formatting
        $formattedTotalsByCurrency = [];
        foreach ($totalsByCurrency as $currency => $total) {
            $formattedTotalsByCurrency[$currency] = $this->formatCurrency($total, $currency);
        }

        // Format document type totals
        $formattedByDocumentType = [];
        foreach ($byDocumentType as $type => $data) {
            $formattedTotals = [];
            foreach ($data['totals'] as $currency => $total) {
                $formattedTotals[$currency] = $this->formatCurrency($total, $currency);
            }
            
            $formattedByDocumentType[$type] = [
                'count' => $data['count'],
                'totals' => $formattedTotals,
            ];
        }

        // Get top 10 customers by total amount
        usort($customerTotals, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        $topCustomers = array_slice($customerTotals, 0, 10);

        // Format top customers
        $formattedTopCustomers = [];
        foreach ($topCustomers as $customer) {
            $formattedTopCustomers[] = [
                'name' => $customer['name'],
                'count' => $customer['count'],
                'total' => $this->formatCurrency($customer['total'], $customer['currency']),
            ];
        }

        return [
            'summary' => [
                'total_count' => $totalCount,
                'active_count' => $activeCount,
                'cancelled_count' => $cancelledCount,
                'document_types_count' => count($documentTypes),
                'currencies' => $currencies,
            ],
            'totals_by_currency' => $formattedTotalsByCurrency,
            'by_document_type' => $formattedByDocumentType,
            'by_status' => [
                'active' => $activeCount,
                'cancelled' => $cancelledCount,
            ],
            'top_customers' => $formattedTopCustomers,
        ];
    }
}