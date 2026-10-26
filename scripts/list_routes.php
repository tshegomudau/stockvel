<?php

declare(strict_types=1);

$file = dirname(__DIR__) . '/routes/web.php';
$src = file_get_contents($file) ?: '';
preg_match_all(
    '/\$router->(get|post|put|patch|delete)\(\s*[\'"]([^\'"]+)[\'"]/m',
    $src,
    $matches,
    PREG_SET_ORDER
);
foreach ($matches as $m) {
    echo strtoupper($m[1]) . "\t" . $m[2] . PHP_EOL;
}
echo 'TOTAL=' . count($matches) . PHP_EOL;
