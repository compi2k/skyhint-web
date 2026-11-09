<?php
declare(strict_types=1);

// Checks lang/*.php: no key defined twice, same keys in every language,
// same {placeholders} in every language. Exit code 1 on problems.
//   php bin/check-lang.php

$dir = dirname(__DIR__) . '/lang';
$files = glob("$dir/*.php");
$problems = [];
$all = [];
foreach ($files as $file) {
    $lang = basename($file, '.php');
    $src = file_get_contents($file);
    preg_match_all("/^\s*'([a-z0-9_.]+)'\s*=>/m", $src, $m);
    preg_match_all("/,[ \t]+'([a-z0-9_.]+)'[ \t]*=>/", $src, $m2);  // several entries on one line
    $keys = array_merge($m[1], $m2[1]);
    foreach (array_count_values($keys) as $k => $n) {
        if ($n > 1) {
            $problems[] = "$lang: '$k' is defined $n times (the last one silently wins)";
        }
    }
    $all[$lang] = require $file;
}
$langs = array_keys($all);
foreach ($langs as $a) {
    foreach ($langs as $b) {
        foreach (array_diff_key($all[$a], $all[$b]) as $k => $_) {
            $problems[] = "'$k' exists in $a but not in $b";
        }
    }
}
$base = $all[$langs[0]];
foreach ($base as $k => $v) {
    preg_match_all('/\{(\w+)\}/', $v, $p0);
    foreach (array_slice($langs, 1) as $l) {
        if (!isset($all[$l][$k])) {
            continue;
        }
        preg_match_all('/\{(\w+)\}/', $all[$l][$k], $p1);
        $x = $p0[1]; $y = $p1[1]; sort($x); sort($y);
        if ($x !== $y) {
            $problems[] = "'$k': placeholders differ ({$langs[0]}: " . implode(',', $x) . "; $l: " . implode(',', $y) . ')';
        }
    }
}
if ($problems) {
    echo implode("\n", $problems), "\n";
    exit(1);
}
echo 'lang files ok: ' . count($base) . ' strings in ' . implode(', ', $langs) . "\n";
