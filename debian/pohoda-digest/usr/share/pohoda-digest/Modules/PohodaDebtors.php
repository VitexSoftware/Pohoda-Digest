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
 * Pohoda Debtors Analysis Module
 * 
 * Analyzes overdue receivables and debtor data from Pohoda accounting system.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class PohodaDebtors extends AbstractModule
{
    protected string $moduleName = 'debtors';
    protected string $heading = 'Debtors Analysis';
    protected string $description = 'Analysis of overdue receivables and customer payment behavior';
    protected array $requiredFeatures = ['overdue_tracking'];

    /**
     * Process data for the given period using the provided data source
     *
     * @param DataProviderInterface $provider Data source provider
     * @param \DatePeriod $period Time period to analyze
     * @return array<string, mixed> Structured data array with module results
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        $startTime = microtime(true);
        
        try {
            // Get overdue invoices as of the end date
            $asOfDate = $period->getEndDate()->format('Y-m-d');
            
            $overdueInvoices = $provider->getData('overdue_invoices', [
                'asOfDate' => $asOfDate,
            ]);

            // Perform analysis
            $analysis = $this->analyzeDebtors($overdueInvoices, $asOfDate);
            
            $executionTime = microtime(true) - $startTime;
            
            return $this->createResult(
                $period,
                true,
                $analysis,
                [
                    'provider' => $provider->getSystemName(),
                    'processing_time' => $executionTime,
                    'records_processed' => count($overdueInvoices),
                    'as_of_date' => $asOfDate,
                ]
            );
            
        } catch (\Exception $e) {
            return $this->createResult(
                $period,
                false,
                [],
                [
                    'provider' => $provider->getSystemName(),
                    'processing_time' => microtime(true) - $startTime,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Analyze overdue invoices and calculate debtor statistics
     *
     * @param array<int, array<string, mixed>> $overdueInvoices Overdue invoice records
     * @param string $asOfDate Analysis date
     * @return array<string, mixed> Analysis results
     */
    private function analyzeDebtors(array $overdueInvoices, string $asOfDate): array
    {
        if (empty($overdueInvoices)) {
            return [
                'summary' => [
                    'total_overdue_count' => 0,
                    'total_overdue_amount' => [],
                    'currencies' => [],
                    'debtor_count' => 0,
                ],
                'by_currency' => [],
                'by_age_groups' => [],
                'top_debtors' => [],
                'aged_analysis' => [],
            ];
        }

        // Initialize analysis arrays
        $totalsByCurrency = [];
        $debtorTotals = [];
        $currencies = [];
        $ageGroups = [
            '1-30' => ['count' => 0, 'totals' => []],
            '31-60' => ['count' => 0, 'totals' => []],
            '61-90' => ['count' => 0, 'totals' => []],
            '90+' => ['count' => 0, 'totals' => []],
        ];

        // Process each overdue invoice
        foreach ($overdueInvoices as $invoice) {
            $amount = (float) ($invoice['amount'] ?? 0);
            $currency = $invoice['currency'] ?? 'CZK';
            $partnerName = $invoice['partner_name'] ?? 'Unknown';
            $dueDate = $invoice['due_date'] ?? '';

            // Track currencies
            if (!in_array($currency, $currencies, true)) {
                $currencies[] = $currency;
            }

            // Calculate overdue days
            $overdueDays = $this->calculateOverdueDays($dueDate, $asOfDate);
            $ageGroup = $this->getAgeGroup($overdueDays);

            // Totals by currency
            if (!isset($totalsByCurrency[$currency])) {
                $totalsByCurrency[$currency] = 0.0;
            }
            $totalsByCurrency[$currency] += $amount;

            // Age group analysis
            $ageGroups[$ageGroup]['count']++;
            if (!isset($ageGroups[$ageGroup]['totals'][$currency])) {
                $ageGroups[$ageGroup]['totals'][$currency] = 0.0;
            }
            $ageGroups[$ageGroup]['totals'][$currency] += $amount;

            // Debtor totals
            if (!isset($debtorTotals[$partnerName])) {
                $debtorTotals[$partnerName] = [
                    'name' => $partnerName,
                    'invoice_count' => 0,
                    'total_amount' => 0.0,
                    'currency' => $currency,
                    'oldest_overdue_days' => 0,
                    'invoices' => [],
                ];
            }
            
            $debtorTotals[$partnerName]['invoice_count']++;
            $debtorTotals[$partnerName]['total_amount'] += $amount;
            $debtorTotals[$partnerName]['oldest_overdue_days'] = max(
                $debtorTotals[$partnerName]['oldest_overdue_days'],
                $overdueDays
            );
            
            $debtorTotals[$partnerName]['invoices'][] = [
                'number' => $invoice['number'] ?? '',
                'amount' => $this->formatCurrency($amount, $currency),
                'due_date' => $dueDate,
                'overdue_days' => $overdueDays,
            ];
        }

        // Format currency totals
        $formattedTotalsByCurrency = [];
        foreach ($totalsByCurrency as $currency => $total) {
            $formattedTotalsByCurrency[$currency] = $this->formatCurrency($total, $currency);
        }

        // Format age groups
        $formattedAgeGroups = [];
        foreach ($ageGroups as $group => $data) {
            $formattedTotals = [];
            foreach ($data['totals'] as $currency => $total) {
                $formattedTotals[$currency] = $this->formatCurrency($total, $currency);
            }
            
            $formattedAgeGroups[$group] = [
                'count' => $data['count'],
                'totals' => $formattedTotals,
            ];
        }

        // Get top 20 debtors by amount
        usort($debtorTotals, function ($a, $b) {
            return $b['total_amount'] <=> $a['total_amount'];
        });
        $topDebtors = array_slice($debtorTotals, 0, 20);

        // Format top debtors
        $formattedTopDebtors = [];
        foreach ($topDebtors as $debtor) {
            $formattedTopDebtors[] = [
                'name' => $debtor['name'],
                'invoice_count' => $debtor['invoice_count'],
                'total_amount' => $this->formatCurrency($debtor['total_amount'], $debtor['currency']),
                'oldest_overdue_days' => $debtor['oldest_overdue_days'],
                'invoices' => array_slice($debtor['invoices'], 0, 5), // Show max 5 invoices
            ];
        }

        // Calculate total overdue amount (main currency)
        $mainCurrency = $currencies[0] ?? 'CZK';
        $totalOverdueAmount = $totalsByCurrency[$mainCurrency] ?? 0.0;

        return [
            'summary' => [
                'total_overdue_count' => count($overdueInvoices),
                'total_overdue_amount' => $this->formatCurrency($totalOverdueAmount, $mainCurrency),
                'currencies' => $currencies,
                'debtor_count' => count($debtorTotals),
                'as_of_date' => $asOfDate,
            ],
            'by_currency' => $formattedTotalsByCurrency,
            'by_age_groups' => $formattedAgeGroups,
            'top_debtors' => $formattedTopDebtors,
        ];
    }

    /**
     * Calculate overdue days between due date and as-of date
     *
     * @param string $dueDate Due date (YYYY-MM-DD)
     * @param string $asOfDate Analysis date (YYYY-MM-DD)
     * @return int Number of overdue days
     */
    private function calculateOverdueDays(string $dueDate, string $asOfDate): int
    {
        try {
            $due = new \DateTime($dueDate);
            $asOf = new \DateTime($asOfDate);
            $diff = $asOf->diff($due);
            
            return $diff->invert ? $diff->days : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get age group for overdue days
     *
     * @param int $overdueDays Number of overdue days
     * @return string Age group key
     */
    private function getAgeGroup(int $overdueDays): string
    {
        if ($overdueDays <= 30) {
            return '1-30';
        } elseif ($overdueDays <= 60) {
            return '31-60';
        } elseif ($overdueDays <= 90) {
            return '61-90';
        } else {
            return '90+';
        }
    }
}