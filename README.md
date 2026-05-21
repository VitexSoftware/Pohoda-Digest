# Pohoda-Digest

![Pohoda-Digest](pohoda-digest.svg)

Periodical business digest for [Stormware Pohoda](https://www.stormware.cz/pohoda/) accounting system. Collects data via the [PHP-Pohoda-Connector](https://github.com/Spoje-NET/php-pohoda-connector) (mServer), runs analytics modules from [vitexsoftware/digest-modules](https://github.com/VitexSoftware/DigestModules), and outputs Markdown, HTML, or PDF reports.

## Architecture

```
vitexsoftware/pohoda-digest  (this package)
  src/DataProvider/PohodaDataProvider.php   — Pohoda mServer → neutral schema
  src/PohodaDigestor.php                    — wires provider + modules + renderer
  src/Mailer.php                            — sends digest by email

vitexsoftware/digest-modules
  src/Modules/*.php                         — system-agnostic analytics modules

vitexsoftware/digest-renderer
  src/                                      — Markdown/HTML/PDF rendering
```

`PohodaDataProvider` (namespace `VitexSoftware\PohodaDigest\DataProvider`) implements `DataProviderInterface` from `vitexsoftware/digest-modules`. It translates neutral `FILTER_*` conditions into Pohoda mServer API calls, applies post-filters for conditions the API cannot handle server-side, and normalizes raw mServer records to the neutral `FIELD_*` schema.

## Features

* **System-agnostic modules** — uses the same analytics modules as AbraFlexi-Digest
* **Multiple output formats** — Markdown, HTML, PDF (via DigestRenderer)
* **Email delivery** — Symfony Mailer integration
* **MultiFlexi ready** — runs as a MultiFlexi application

## Installation

```bash
composer require vitexsoftware/pohoda-digest
```

Or via Debian package:

```shell
wget -qO- https://repo.vitexsoftware.com/KEY.gpg | sudo tee /etc/apt/trusted.gpg.d/vitexsoftware.gpg
echo "deb [signed-by=/etc/apt/trusted.gpg.d/vitexsoftware.gpg] https://repo.vitexsoftware.com $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
sudo apt update
sudo apt install pohoda-digest
```

After installation:

* `pohoda-daydigest` — digest for one day
* `pohoda-weekdigest` — digest for one week
* `pohoda-monthdigest` — digest for one month
* `pohoda-yeardigest` — digest for one year
* `pohoda-alltimedigest` — digest for all time

## Configuration

Create `/etc/pohoda/.env` (or pass path as first CLI argument):

```env
POHODA_URL="http://your-pohoda-server:58002"
POHODA_ICO="12345678"
POHODA_USERNAME="api"
POHODA_PASSWORD="your-password"

EASE_LOGGER="syslog|mail|console"
DIGEST_MAILTO="info@yourdomain.net"
DIGEST_FROM="noreply@yourdomain.net"
OUTPUT_FORMAT="html"
DIGEST_SAVETO="/var/tmp/"
```

## Programmatic usage

```php
<?php
use VitexSoftware\DigestModules\Core\ModuleRunner;
use VitexSoftware\DigestModules\Modules\Debtors;
use VitexSoftware\DigestModules\Modules\OutcomingInvoices;
use VitexSoftware\PohodaDigest\DataProvider\PohodaDataProvider;

$provider = new PohodaDataProvider([
    'url'      => 'http://pohoda.example.com:58002',
    'username' => 'api',
    'password' => 'secret',
    'ico'      => '12345678',
]);

$runner = new ModuleRunner($provider);
$runner->addModule('outcoming_invoices', new OutcomingInvoices());
$runner->addModule('debtors', new Debtors());

$period = new \DatePeriod(
    new \DateTime('first day of last month'),
    new \DateInterval('P1M'),
    new \DateTime('first day of this month'),
);

$result = $runner->run($period);

// Or use the high-level PohodaDigestor:
use VitexSoftware\PohodaDigest\PohodaDigestor;

$digestor = new PohodaDigestor('Monthly Digest');
$digestor->registerModules('monthly');
echo $digestor->generate($period, 'html');
```

## Available modules

All modules from `vitexsoftware/digest-modules` are supported:

* `OutcomingInvoices` — issued invoices in the period
* `IncomingInvoices` — received invoices in the period
* `IncomingPayments` — bank receipts in the period
* `OutcomingPayments` — bank outflows in the period
* `Debtors` — all overdue unpaid receivables
* `UnmatchedInvoices` — issued invoices not matched to a payment
* `UnmatchedPayments` — bank movements not matched to an invoice
* `WaitingIncome` — proforma invoices awaiting settlement
* `WaitingPayments` — invoices awaiting payment
* `NewCustomers` — contacts created in the period
* `Reminds` — invoices with pending payment reminders
* `BestSellers` — top products/services sold in the period
* `WithoutEmail` — contacts missing an email address
* `WithoutTel` — contacts missing a phone number
* `AllTime\PurchasePriceLowerThanSales` — products sold below purchase price

## MultiFlexi Integration

```bash
sudo apt install multiflexi-pohoda-digest
```

Supported environment variables per app:

| Variable | Description |
|---|---|
| `POHODA_URL` | Pohoda mServer URL |
| `POHODA_ICO` | Company IČO |
| `POHODA_USERNAME` | API username |
| `POHODA_PASSWORD` | API password |
| `THEME` | Report theme (`bootstrap` or `email`) |
| `DIGEST_MAILTO` | Send digest to this address |
| `OUTPUT_FORMAT` | `html`, `md`, or `pdf` |
| `DIGEST_SAVETO` | Save output file to this path |

## See also

* [vitexsoftware/digest-modules](https://github.com/VitexSoftware/DigestModules) — analytics modules
* [vitexsoftware/digest-renderer](https://github.com/VitexSoftware/DigestRenderer) — rendering engine
* [vitexsoftware/abraflexi-digest](https://github.com/VitexSoftware/AbraFlexi-Digest) — AbraFlexi variant

## License

MIT
