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

namespace VitexSoftware\PohodaDigest;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$shared = \Ease\Shared::instanced();
$conffile = (isset($argv) && \is_array($argv) && \array_key_exists(1, $argv) && file_exists($argv[1]))
    ? $argv[1]
    : dirname(__DIR__) . '/.env';

if (file_exists($conffile)) {
    $shared->loadConfig($conffile, true);
}

\Ease\Locale::singleton(null, dirname(__DIR__) . '/i18n', 'pohoda-digest');
