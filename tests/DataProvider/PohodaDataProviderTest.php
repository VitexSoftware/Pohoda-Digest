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

namespace Test\VitexSoftware\PohodaDigest\DataProvider;

use PHPUnit\Framework\TestCase;
use VitexSoftware\DigestModules\Core\DataProviderInterface;
use VitexSoftware\PohodaDigest\DataProvider\PohodaDataProvider;

/**
 * Tests for PohodaDataProvider normalization and filter logic.
 *
 * Private normalization helpers are tested via reflection because they contain
 * the core field-mapping logic that must stay consistent with DataProviderInterface.
 */
class PohodaDataProviderTest extends TestCase
{
    private PohodaDataProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new PohodaDataProvider([
            'url'      => 'http://localhost',
            'username' => 'test',
            'password' => 'test',
            'ico'      => '12345678',
        ]);
    }

    private function callPrivate(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod(PohodaDataProvider::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->provider, ...$args);
    }

    // ── Public interface ─────────────────────────────────────────────────────

    public function testGetSystemName(): void
    {
        $this->assertSame('pohoda', $this->provider->getSystemName());
    }

    public function testGetSupportedEntities(): void
    {
        $entities = $this->provider->getSupportedEntities();

        $this->assertContains(DataProviderInterface::ENTITY_OUTCOMING_INVOICES, $entities);
        $this->assertContains(DataProviderInterface::ENTITY_INCOMING_INVOICES, $entities);
        $this->assertContains(DataProviderInterface::ENTITY_BANK_STATEMENTS, $entities);
        $this->assertContains(DataProviderInterface::ENTITY_CONTACTS, $entities);
        $this->assertContains(DataProviderInterface::ENTITY_PRODUCTS, $entities);
    }

    public function testSupportsFeature(): void
    {
        $this->assertTrue($this->provider->supportsFeature('date_filtering'));
        $this->assertTrue($this->provider->supportsFeature('payment_status'));
        $this->assertFalse($this->provider->supportsFeature('nonexistent'));
    }

    public function testFormatDate(): void
    {
        $this->assertSame('2024-06-15', $this->provider->formatDate(new \DateTime('2024-06-15')));
    }

    public function testGetCompanyInfo(): void
    {
        $info = $this->provider->getCompanyInfo();

        $this->assertSame('12345678', $info['ico']);
        $this->assertSame('Pohoda', $info['system']);
    }

    // ── normalizeInvoice ─────────────────────────────────────────────────────

    public function testNormalizeInvoiceFieldKeys(): void
    {
        $raw = [
            'number'      => 'FAK2024001',
            'date'        => '2024-01-15',
            'dateDue'     => '2024-02-15',
            'homeCurrency' => ['priceNone' => 12000.0],
            'partnerIdentity' => ['address' => ['name' => 'Test s.r.o.', 'email' => 'test@example.com']],
            'invoiceType' => 'issuedInvoice',
            'state'       => 'active',
        ];

        $result = $this->callPrivate('normalizeInvoice', $raw, 'issuedInvoice');

        $this->assertSame('FAK2024001', $result[DataProviderInterface::FIELD_CODE]);
        $this->assertSame('2024-01-15', $result[DataProviderInterface::FIELD_DATE]);
        $this->assertSame('2024-02-15', $result[DataProviderInterface::FIELD_DUE_DATE]);
        $this->assertSame('Test s.r.o.', $result[DataProviderInterface::FIELD_COMPANY]);
        $this->assertSame(12000.0, $result[DataProviderInterface::FIELD_TOTAL_AMOUNT]);
        $this->assertSame('test@example.com', $result[DataProviderInterface::FIELD_CONTACT_EMAIL]);
        $this->assertFalse($result[DataProviderInterface::FIELD_CANCELLED]);
        $this->assertSame(DataProviderInterface::DOCUMENT_TYPE_INVOICE, $result[DataProviderInterface::FIELD_DOCUMENT_TYPE]);
    }

    public function testNormalizeInvoiceCancelledState(): void
    {
        $raw    = ['state' => 'cancelled', 'homeCurrency' => []];
        $result = $this->callPrivate('normalizeInvoice', $raw, 'issuedInvoice');

        $this->assertTrue($result[DataProviderInterface::FIELD_CANCELLED]);
    }

    public function testNormalizeInvoicePaymentStatusPaid(): void
    {
        $raw    = ['homeCurrency' => ['priceNone' => 1000.0], 'paidAmount' => 1000.0];
        $result = $this->callPrivate('normalizeInvoice', $raw, 'issuedInvoice');

        $this->assertSame(DataProviderInterface::PAYMENT_STATUS_PAID, $result[DataProviderInterface::FIELD_PAYMENT_STATUS]);
    }

    public function testNormalizeInvoicePaymentStatusPartial(): void
    {
        $raw    = ['homeCurrency' => ['priceNone' => 1000.0], 'paidAmount' => 400.0];
        $result = $this->callPrivate('normalizeInvoice', $raw, 'issuedInvoice');

        $this->assertSame(DataProviderInterface::PAYMENT_STATUS_PARTIAL, $result[DataProviderInterface::FIELD_PAYMENT_STATUS]);
    }

    public function testNormalizeInvoicePaymentStatusUnpaid(): void
    {
        $raw    = ['homeCurrency' => ['priceNone' => 1000.0]];
        $result = $this->callPrivate('normalizeInvoice', $raw, 'issuedInvoice');

        $this->assertSame(DataProviderInterface::PAYMENT_STATUS_UNPAID, $result[DataProviderInterface::FIELD_PAYMENT_STATUS]);
    }

    // ── normalizeBank ────────────────────────────────────────────────────────

    public function testNormalizeBankIncoming(): void
    {
        $raw    = ['homeCurrency' => ['priceNone' => 5000.0], 'date' => '2024-01-10'];
        $result = $this->callPrivate('normalizeBank', $raw);

        $this->assertSame(5000.0, $result[DataProviderInterface::FIELD_TOTAL_AMOUNT]);
        $this->assertSame(DataProviderInterface::DIRECTION_INCOMING, $result[DataProviderInterface::FIELD_DIRECTION]);
    }

    public function testNormalizeBankOutgoing(): void
    {
        $raw    = ['homeCurrency' => ['priceNone' => -3000.0], 'date' => '2024-01-10'];
        $result = $this->callPrivate('normalizeBank', $raw);

        $this->assertSame(3000.0, $result[DataProviderInterface::FIELD_TOTAL_AMOUNT]);
        $this->assertSame(DataProviderInterface::DIRECTION_OUTGOING, $result[DataProviderInterface::FIELD_DIRECTION]);
    }

    // ── normalizeContact ─────────────────────────────────────────────────────

    public function testNormalizeContact(): void
    {
        $raw    = [
            'id'       => 'C001',
            'identity' => ['address' => [
                'name'   => 'ACME Corp',
                'email'  => 'acme@example.com',
                'phone'  => '+420123456789',
                'city'   => 'Praha',
                'street' => 'Václavské náměstí 1',
            ]],
        ];
        $result = $this->callPrivate('normalizeContact', $raw);

        $this->assertSame('ACME Corp', $result[DataProviderInterface::FIELD_NAME]);
        $this->assertSame('acme@example.com', $result[DataProviderInterface::FIELD_EMAIL]);
        $this->assertSame('+420123456789', $result[DataProviderInterface::FIELD_PHONE]);
        $this->assertSame('Praha', $result[DataProviderInterface::FIELD_CITY]);
    }

    // ── normalizeProduct ─────────────────────────────────────────────────────

    public function testNormalizeProduct(): void
    {
        $raw    = ['stockHeader' => ['code' => 'P001', 'name' => 'Widget', 'purchasingPrice' => 50.0, 'sellingPrice' => 100.0]];
        $result = $this->callPrivate('normalizeProduct', $raw);

        $this->assertSame('P001', $result[DataProviderInterface::FIELD_CODE]);
        $this->assertSame('Widget', $result[DataProviderInterface::FIELD_NAME]);
        $this->assertSame(50.0, $result[DataProviderInterface::FIELD_BUY_PRICE]);
        $this->assertSame(100.0, $result[DataProviderInterface::FIELD_SELL_PRICE]);
    }

    // ── normalizeDocumentType ────────────────────────────────────────────────

    public function testNormalizeDocumentTypeInvoice(): void
    {
        $this->assertSame(
            DataProviderInterface::DOCUMENT_TYPE_INVOICE,
            $this->callPrivate('normalizeDocumentType', 'issuedInvoice'),
        );
        $this->assertSame(
            DataProviderInterface::DOCUMENT_TYPE_INVOICE,
            $this->callPrivate('normalizeDocumentType', 'receivedInvoice'),
        );
    }

    public function testNormalizeDocumentTypeProforma(): void
    {
        $this->assertSame(
            DataProviderInterface::DOCUMENT_TYPE_PROFORMA,
            $this->callPrivate('normalizeDocumentType', 'advanceInvoice'),
        );
    }

    public function testNormalizeDocumentTypeCreditNote(): void
    {
        $this->assertSame(
            DataProviderInterface::DOCUMENT_TYPE_CREDIT_NOTE,
            $this->callPrivate('normalizeDocumentType', 'creditNote'),
        );
    }

    // ── parseCurrency ────────────────────────────────────────────────────────

    public function testParseCurrencyFromArray(): void
    {
        $this->assertSame('EUR', $this->callPrivate('parseCurrency', ['currency' => ['ids' => 'EUR']]));
    }

    public function testParseCurrencyDefaultsCzk(): void
    {
        $this->assertSame('CZK', $this->callPrivate('parseCurrency', []));
    }

    // ── buildConditions post-filters ─────────────────────────────────────────

    public function testBuildConditionsDatePeriod(): void
    {
        $period = new \DatePeriod(new \DateTime('2024-01-01'), new \DateInterval('P1M'), new \DateTime('2024-02-01'));

        [$filter, $postFilters] = $this->callPrivate('buildConditions', [
            DataProviderInterface::FILTER_DATE_PERIOD => [
                'column' => DataProviderInterface::DATE_COLUMN_ISSUE_DATE,
                'period' => $period,
            ],
        ]);

        $this->assertSame('2024-01-01', $filter['dateFrom']);
        $this->assertSame('2024-02-01', $filter['dateTo']);
        $this->assertCount(0, $postFilters);
    }

    public function testBuildConditionsOverduePostFilter(): void
    {
        [$filter, $postFilters] = $this->callPrivate('buildConditions', [
            DataProviderInterface::FILTER_OVERDUE => true,
        ]);

        $this->assertCount(1, $postFilters);

        $pastDue = [DataProviderInterface::FIELD_DUE_DATE => '2020-01-01'];
        $future  = [DataProviderInterface::FIELD_DUE_DATE => '2099-12-31'];

        $this->assertTrue($postFilters[0]($pastDue));
        $this->assertFalse($postFilters[0]($future));
    }

    public function testBuildConditionsCancelledPostFilter(): void
    {
        [$filter, $postFilters] = $this->callPrivate('buildConditions', [
            DataProviderInterface::FILTER_CANCELLED => false,
        ]);

        $this->assertCount(1, $postFilters);

        $active    = [DataProviderInterface::FIELD_CANCELLED => false];
        $cancelled = [DataProviderInterface::FIELD_CANCELLED => true];

        $this->assertTrue($postFilters[0]($active));
        $this->assertFalse($postFilters[0]($cancelled));
    }

    public function testBuildConditionsPaymentStatusUnpaidOrPartial(): void
    {
        [$filter, $postFilters] = $this->callPrivate('buildConditions', [
            DataProviderInterface::FILTER_PAYMENT_STATUS => DataProviderInterface::PAYMENT_STATUS_UNPAID_OR_PARTIAL,
        ]);

        $this->assertCount(1, $postFilters);
        $fn = $postFilters[0];

        $this->assertTrue($fn([DataProviderInterface::FIELD_PAYMENT_STATUS => DataProviderInterface::PAYMENT_STATUS_UNPAID]));
        $this->assertTrue($fn([DataProviderInterface::FIELD_PAYMENT_STATUS => DataProviderInterface::PAYMENT_STATUS_PARTIAL]));
        $this->assertFalse($fn([DataProviderInterface::FIELD_PAYMENT_STATUS => DataProviderInterface::PAYMENT_STATUS_PAID]));
    }

    public function testBuildConditionsDirectionPostFilter(): void
    {
        [$filter, $postFilters] = $this->callPrivate('buildConditions', [
            DataProviderInterface::FILTER_PAYMENT_DIRECTION => DataProviderInterface::DIRECTION_INCOMING,
        ]);

        $fn = $postFilters[0];

        $this->assertTrue($fn([DataProviderInterface::FIELD_DIRECTION => DataProviderInterface::DIRECTION_INCOMING]));
        $this->assertFalse($fn([DataProviderInterface::FIELD_DIRECTION => DataProviderInterface::DIRECTION_OUTGOING]));
    }
}
