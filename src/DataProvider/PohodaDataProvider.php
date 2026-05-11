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

use Ease\Shared;
use mServer\Bank;
use mServer\Client;
use mServer\Invoice;
use VitexSoftware\DigestModules\Core\DataProviderInterface;

/**
 * Pohoda Data Provider
 *
 * Fetches data from Pohoda via the PHP-Pohoda-Connector (mServer) and normalizes
 * all returned records to the neutral field schema defined by DataProviderInterface.
 *
 * Filter conditions (FILTER_* constants) that Pohoda does not support server-side
 * are applied as in-memory post-filters after fetching.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class PohodaDataProvider implements DataProviderInterface
{
    private array $connectionInfo;

    /**
     * @param array<string, mixed> $config Connection config: url, username, password, ico
     */
    public function __construct(array $config = [])
    {
        if (empty($config)) {
            Shared::init(['POHODA_URL', 'POHODA_USERNAME', 'POHODA_PASSWORD', 'POHODA_ICO']);
            $config = [
                'url'      => Shared::cfg('POHODA_URL'),
                'username' => Shared::cfg('POHODA_USERNAME'),
                'password' => Shared::cfg('POHODA_PASSWORD'),
                'ico'      => Shared::cfg('POHODA_ICO'),
            ];
        }

        $this->connectionInfo = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function getData(string $entity, array $conditions = [], array $columns = []): array
    {
        [$pohodaFilter, $postFilters] = $this->buildConditions($conditions);

        $raw = match ($entity) {
            DataProviderInterface::ENTITY_OUTCOMING_INVOICES =>
                $this->fetchInvoices($pohodaFilter, 'issuedInvoice'),

            DataProviderInterface::ENTITY_INCOMING_INVOICES =>
                $this->fetchInvoices($pohodaFilter, 'receivedInvoice'),

            DataProviderInterface::ENTITY_BANK_STATEMENTS =>
                $this->fetchBankTransactions($pohodaFilter),

            DataProviderInterface::ENTITY_CONTACTS =>
                $this->fetchContacts($pohodaFilter),

            DataProviderInterface::ENTITY_PRODUCTS =>
                $this->fetchProducts($pohodaFilter),

            DataProviderInterface::ENTITY_REMINDERS,
            DataProviderInterface::ENTITY_ORDERS => [],

            default => [],
        };

        // Apply post-filters (conditions Pohoda can't filter server-side)
        foreach ($postFilters as $filter) {
            $raw = array_values(array_filter($raw, $filter));
        }

        $limit = isset($conditions[DataProviderInterface::FILTER_LIMIT])
            ? (int) $conditions[DataProviderInterface::FILTER_LIMIT]
            : 0;

        return $limit > 0 ? \array_slice($raw, 0, $limit) : $raw;
    }

    /**
     * {@inheritDoc}
     */
    public function getSystemName(): string
    {
        return 'pohoda';
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedEntities(): array
    {
        return [
            DataProviderInterface::ENTITY_OUTCOMING_INVOICES,
            DataProviderInterface::ENTITY_INCOMING_INVOICES,
            DataProviderInterface::ENTITY_BANK_STATEMENTS,
            DataProviderInterface::ENTITY_CONTACTS,
            DataProviderInterface::ENTITY_PRODUCTS,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function supportsFeature(string $feature): bool
    {
        return \in_array($feature, [
            'date_filtering', 'payment_status', 'document_types',
            'multi_currency', 'overdue_tracking',
        ], true);
    }

    /**
     * {@inheritDoc}
     */
    public function getCompanyInfo(): array
    {
        return [
            'name'       => $this->connectionInfo['ico'] ?? 'Pohoda Company',
            'ico'        => $this->connectionInfo['ico'] ?? '',
            'system'     => 'Pohoda',
            'server_url' => $this->connectionInfo['url'] ?? '',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function formatDate(\DateTime $date): string
    {
        return $date->format('Y-m-d');
    }

    /**
     * {@inheritDoc}
     */
    public function formatDatePeriod(string $column, \DatePeriod $period): string
    {
        $start = $this->formatDate($period->getStartDate());
        $end   = $this->formatDate($period->getEndDate());

        return "dateFrom={$start}&dateTo={$end}";
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Translate neutral FILTER_* conditions into Pohoda API filter array
     * and a list of callables for post-filtering.
     *
     * @param array<string, mixed> $conditions Neutral filter conditions
     * @return array{array<string, mixed>, array<callable>} [pohodaFilter, postFilters]
     */
    private function buildConditions(array $conditions): array
    {
        $filter      = [];
        $postFilters = [];

        foreach ($conditions as $key => $value) {
            switch ($key) {
                case DataProviderInterface::FILTER_DATE_PERIOD:
                    if (is_array($value) && isset($value['period']) && $value['period'] instanceof \DatePeriod) {
                        $filter['dateFrom'] = $this->formatDate($value['period']->getStartDate());
                        $filter['dateTo']   = $this->formatDate($value['period']->getEndDate());
                    }
                    break;

                case DataProviderInterface::FILTER_CANCELLED:
                    $want = (bool) $value;
                    $postFilters[] = static fn (array $r): bool =>
                        ($r[DataProviderInterface::FIELD_CANCELLED] ?? false) === $want;
                    break;

                case DataProviderInterface::FILTER_OVERDUE:
                    if ($value) {
                        $today = date('Y-m-d');
                        $postFilters[] = static function (array $r) use ($today): bool {
                            $due = $r[DataProviderInterface::FIELD_DUE_DATE] ?? '';
                            return $due !== '' && $due < $today;
                        };
                    }
                    break;

                case DataProviderInterface::FILTER_PAYMENT_STATUS:
                    $want = (string) $value;
                    $postFilters[] = static function (array $r) use ($want): bool {
                        $status = $r[DataProviderInterface::FIELD_PAYMENT_STATUS] ?? '';
                        return match ($want) {
                            DataProviderInterface::PAYMENT_STATUS_UNPAID_OR_PARTIAL =>
                                $status === DataProviderInterface::PAYMENT_STATUS_UNPAID
                                || $status === DataProviderInterface::PAYMENT_STATUS_PARTIAL,
                            default => $status === $want,
                        };
                    };
                    break;

                case DataProviderInterface::FILTER_PAYMENT_DIRECTION:
                    $want = (string) $value;
                    $postFilters[] = static fn (array $r): bool =>
                        ($r[DataProviderInterface::FIELD_DIRECTION] ?? '') === $want;
                    break;

                case DataProviderInterface::FILTER_MATCHED:
                    $want = (bool) $value;
                    $postFilters[] = static fn (array $r): bool =>
                        ($r[DataProviderInterface::FIELD_MATCHED] ?? false) === $want;
                    break;

                case DataProviderInterface::FILTER_ACCOUNTED:
                    $want = (bool) $value;
                    $postFilters[] = static fn (array $r): bool =>
                        ($r[DataProviderInterface::FIELD_ACCOUNTED] ?? false) === $want;
                    break;

                case DataProviderInterface::FILTER_MISSING_EMAIL:
                    if ($value) {
                        $postFilters[] = static fn (array $r): bool =>
                            ($r[DataProviderInterface::FIELD_EMAIL] ?? '') === '';
                    }
                    break;

                case DataProviderInterface::FILTER_MISSING_PHONE:
                    if ($value) {
                        $postFilters[] = static fn (array $r): bool =>
                            ($r[DataProviderInterface::FIELD_PHONE] ?? '') === '';
                    }
                    break;

                case DataProviderInterface::FILTER_EXCLUDE_DOCUMENT_TYPE:
                    $exclude = (string) $value;
                    $postFilters[] = static fn (array $r): bool =>
                        ($r[DataProviderInterface::FIELD_DOCUMENT_TYPE] ?? '') !== $exclude;
                    break;

                case DataProviderInterface::FILTER_DOCUMENT_TYPE:
                    $want = (string) $value;
                    $postFilters[] = static fn (array $r): bool =>
                        ($r[DataProviderInterface::FIELD_DOCUMENT_TYPE] ?? '') === $want;
                    break;

                case DataProviderInterface::FILTER_LIMIT:
                    // Handled after post-filtering in getData()
                    break;
            }
        }

        return [$filter, $postFilters];
    }

    /**
     * Fetch and normalize invoices from Pohoda.
     *
     * @param array<string, mixed> $filter Pohoda API filter
     * @param string $invoiceType Pohoda invoice type ('issuedInvoice' or 'receivedInvoice')
     * @return array<array<string, mixed>>
     */
    private function fetchInvoices(array $filter, string $invoiceType = 'issuedInvoice'): array
    {
        try {
            $client = new Invoice();
            $raw    = $client->loadFromPohoda($filter);

            if (!\is_array($raw)) {
                return [];
            }

            return array_map(
                fn (array $inv) => $this->normalizeInvoice($inv, $invoiceType),
                $raw,
            );
        } catch (\Exception $e) {
            error_log('Pohoda invoice fetch error: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Fetch and normalize bank transactions from Pohoda.
     *
     * @param array<string, mixed> $filter Pohoda API filter
     * @return array<array<string, mixed>>
     */
    private function fetchBankTransactions(array $filter): array
    {
        try {
            $client = new Bank();
            $raw    = $client->loadFromPohoda($filter);

            if (!\is_array($raw)) {
                return [];
            }

            return array_map($this->normalizeBank(...), $raw);
        } catch (\Exception $e) {
            error_log('Pohoda bank transactions fetch error: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Fetch and normalize contacts from Pohoda.
     *
     * @param array<string, mixed> $filter Pohoda API filter
     * @return array<array<string, mixed>>
     */
    private function fetchContacts(array $filter): array
    {
        try {
            $client = new \mServer\Addressbook();
            $raw    = $client->loadFromPohoda($filter);

            if (!\is_array($raw)) {
                return [];
            }

            return array_map($this->normalizeContact(...), $raw);
        } catch (\Exception $e) {
            error_log('Pohoda contacts fetch error: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Fetch and normalize products from Pohoda.
     *
     * @param array<string, mixed> $filter Pohoda API filter
     * @return array<array<string, mixed>>
     */
    private function fetchProducts(array $filter): array
    {
        try {
            $client = new \mServer\Stock();
            $raw    = $client->loadFromPohoda($filter);

            if (!\is_array($raw)) {
                return [];
            }

            return array_map($this->normalizeProduct(...), $raw);
        } catch (\Exception $e) {
            error_log('Pohoda products fetch error: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Normalize a raw Pohoda invoice record to neutral FIELD_* schema.
     *
     * @param array<string, mixed> $inv Raw Pohoda invoice
     * @return array<string, mixed>
     */
    private function normalizeInvoice(array $inv, string $invoiceType): array
    {
        $homeCurrency    = $inv['homeCurrency'] ?? [];
        $foreignCurrency = $inv['foreignCurrency'] ?? [];
        $address         = $inv['partnerIdentity']['address'] ?? $inv['address'] ?? [];
        $state           = strtolower((string) ($inv['state'] ?? 'active'));

        $totalHome    = $this->parseAmount($homeCurrency);
        $totalForeign = $this->parseAmount($foreignCurrency);
        $remaining    = $this->parseRemainingAmount($inv);

        $paymentStatus = $this->normalizePaymentStatus($inv);

        return [
            DataProviderInterface::FIELD_CODE                     => (string) ($inv['number'] ?? $inv['numberRequested'] ?? ''),
            DataProviderInterface::FIELD_DATE                     => (string) ($inv['date'] ?? $inv['dateAccounting'] ?? ''),
            DataProviderInterface::FIELD_DUE_DATE                 => (string) ($inv['dateDue'] ?? $inv['dueDate'] ?? ''),
            DataProviderInterface::FIELD_COMPANY                  => (string) ($address['name'] ?? ''),
            DataProviderInterface::FIELD_TOTAL_AMOUNT             => $totalHome,
            DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN     => $totalForeign,
            DataProviderInterface::FIELD_REMAINING_AMOUNT         => $remaining,
            DataProviderInterface::FIELD_REMAINING_AMOUNT_FOREIGN => 0.0,
            DataProviderInterface::FIELD_DEPOSIT_AMOUNT           => 0.0,
            DataProviderInterface::FIELD_DEPOSIT_AMOUNT_FOREIGN   => 0.0,
            DataProviderInterface::FIELD_CURRENCY                 => $this->parseCurrency($homeCurrency ?: $foreignCurrency),
            DataProviderInterface::FIELD_CANCELLED                => $state === 'cancelled',
            DataProviderInterface::FIELD_DOCUMENT_TYPE            => $this->normalizeDocumentType((string) ($inv['invoiceType'] ?? $inv['documentType'] ?? $invoiceType)),
            DataProviderInterface::FIELD_PAYMENT_STATUS           => $paymentStatus,
            DataProviderInterface::FIELD_MAIL_STATUS              => DataProviderInterface::MAIL_STATUS_EMPTY,
            DataProviderInterface::FIELD_DESCRIPTION              => (string) ($inv['text'] ?? $inv['note'] ?? ''),
            DataProviderInterface::FIELD_CONTACT_EMAIL            => (string) ($address['email'] ?? ''),
            DataProviderInterface::FIELD_DEDUCTION_STATUS         => DataProviderInterface::DEDUCTION_STATUS_NONE,
            DataProviderInterface::FIELD_FIRST_REMINDER_DATE      => '',
            DataProviderInterface::FIELD_SECOND_REMINDER_DATE     => '',
            DataProviderInterface::FIELD_PRE_LITIGATION_DATE      => '',
            DataProviderInterface::FIELD_ITEMS                    => [],
        ];
    }

    /**
     * Normalize a raw Pohoda bank transaction to neutral FIELD_* schema.
     *
     * @param array<string, mixed> $tx Raw Pohoda bank transaction
     * @return array<string, mixed>
     */
    private function normalizeBank(array $tx): array
    {
        $homeCurrency = $tx['homeCurrency'] ?? [];
        $address      = $tx['partnerIdentity']['address'] ?? $tx['address'] ?? [];
        $amount       = $this->parseAmount($homeCurrency);

        return [
            DataProviderInterface::FIELD_CODE                 => (string) ($tx['number'] ?? ''),
            DataProviderInterface::FIELD_DATE                 => (string) ($tx['date'] ?? $tx['dateStatement'] ?? ''),
            DataProviderInterface::FIELD_COMPANY              => (string) ($address['name'] ?? ''),
            DataProviderInterface::FIELD_BANK_ACCOUNT         => (string) ($tx['account'] ?? ''),
            DataProviderInterface::FIELD_TOTAL_AMOUNT         => abs($amount),
            DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN => abs($this->parseAmount($tx['foreignCurrency'] ?? [])),
            DataProviderInterface::FIELD_CURRENCY             => $this->parseCurrency($homeCurrency),
            DataProviderInterface::FIELD_DIRECTION            => $amount >= 0
                ? DataProviderInterface::DIRECTION_INCOMING
                : DataProviderInterface::DIRECTION_OUTGOING,
            DataProviderInterface::FIELD_CANCELLED            => false,
            DataProviderInterface::FIELD_DESCRIPTION          => (string) ($tx['text'] ?? $tx['note'] ?? ''),
            DataProviderInterface::FIELD_ACCOUNTED            => (bool) ($tx['accountingDocument'] ?? false),
            DataProviderInterface::FIELD_MATCHED              => (bool) ($tx['paired'] ?? false),
        ];
    }

    /**
     * Normalize a raw Pohoda contact to neutral FIELD_* schema.
     *
     * @param array<string, mixed> $contact Raw Pohoda contact
     * @return array<string, mixed>
     */
    private function normalizeContact(array $contact): array
    {
        $address = $contact['identity']['address'] ?? $contact['address'] ?? [];

        return [
            DataProviderInterface::FIELD_CODE   => (string) ($contact['id'] ?? ''),
            DataProviderInterface::FIELD_NAME   => (string) ($address['name'] ?? ''),
            DataProviderInterface::FIELD_EMAIL  => (string) ($address['email'] ?? ''),
            DataProviderInterface::FIELD_PHONE  => (string) ($address['phone'] ?? $address['mobilPhone'] ?? ''),
            DataProviderInterface::FIELD_STREET => (string) ($address['street'] ?? ''),
            DataProviderInterface::FIELD_CITY   => (string) ($address['city'] ?? ''),
        ];
    }

    /**
     * Normalize a raw Pohoda stock item to neutral FIELD_* schema.
     *
     * @param array<string, mixed> $product Raw Pohoda stock item
     * @return array<string, mixed>
     */
    private function normalizeProduct(array $product): array
    {
        $header = $product['stockHeader'] ?? $product;

        return [
            DataProviderInterface::FIELD_CODE       => (string) ($header['code'] ?? ''),
            DataProviderInterface::FIELD_NAME       => (string) ($header['name'] ?? ''),
            DataProviderInterface::FIELD_BUY_PRICE  => (float) ($header['purchasingPrice'] ?? 0),
            DataProviderInterface::FIELD_SELL_PRICE => (float) ($header['sellingPrice'] ?? 0),
        ];
    }

    /**
     * Parse monetary amount from Pohoda currency structure.
     *
     * @param array<string, mixed> $currencyData
     */
    private function parseAmount(array $currencyData): float
    {
        return (float) ($currencyData['priceNone']
            ?? $currencyData['price']
            ?? $currencyData['amount']
            ?? 0);
    }

    /**
     * Calculate remaining (unpaid) amount from Pohoda invoice.
     *
     * @param array<string, mixed> $inv
     */
    private function parseRemainingAmount(array $inv): float
    {
        $total = $this->parseAmount($inv['homeCurrency'] ?? []);
        $paid  = (float) ($inv['paidAmount'] ?? $inv['paid'] ?? 0);

        return max(0.0, $total - $paid);
    }

    /**
     * Parse currency code from Pohoda currency structure.
     *
     * @param array<string, mixed> $currencyData
     */
    private function parseCurrency(array $currencyData): string
    {
        $code = $currencyData['currency']['ids']
            ?? $currencyData['currency']
            ?? '';

        return (is_string($code) && $code !== '') ? $code : 'CZK';
    }

    /**
     * Derive neutral payment status from Pohoda invoice data.
     *
     * @param array<string, mixed> $inv
     */
    private function normalizePaymentStatus(array $inv): string
    {
        $total     = $this->parseAmount($inv['homeCurrency'] ?? []);
        $paid      = (float) ($inv['paidAmount'] ?? $inv['paid'] ?? 0);
        $remaining = max(0.0, $total - $paid);

        if ($remaining <= 0.0) {
            return DataProviderInterface::PAYMENT_STATUS_PAID;
        }

        if ($paid > 0.0) {
            return DataProviderInterface::PAYMENT_STATUS_PARTIAL;
        }

        return DataProviderInterface::PAYMENT_STATUS_UNPAID;
    }

    /**
     * Map Pohoda invoice type to neutral document type.
     */
    private function normalizeDocumentType(string $pohodaType): string
    {
        return match (strtolower($pohodaType)) {
            'advanceinvoice', 'proforma', 'deposit' => DataProviderInterface::DOCUMENT_TYPE_PROFORMA,
            'creditnote', 'credit', 'dobropis'      => DataProviderInterface::DOCUMENT_TYPE_CREDIT_NOTE,
            'issuedinvoice', 'receivedinvoice',
            'faktura', 'invoice'                    => DataProviderInterface::DOCUMENT_TYPE_INVOICE,
            default                                 => $pohodaType,
        };
    }
}
