<?php

declare(strict_types=1);

// CI policy for the PHP security releases of October 21, 2026.
$minimum = match (PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION) {
    '8.3' => '8.3.36',
    '8.4' => '8.4.27',
    '8.5' => '8.5.12',
    default => null,
};

if ($minimum === null || version_compare(PHP_VERSION, $minimum, '<')) {
    fwrite(STDERR, 'Unsupported or unpatched CI runtime: PHP '.PHP_VERSION.PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'PHP '.PHP_VERSION.' meets the CI security baseline '.$minimum.PHP_EOL);
