<?php

/*
 * Loads every class of omnisense/omnibridge with Composer's autoloader alone,
 * in a PHP process of its own, and prints what was loaded (BareTest).
 *
 *   php tests/Fixtures/bare.php <vendor/autoload.php>
 */

require $argv[1];
$autoloaded = get_included_files();
$symbols = [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()];

$src = dirname(__DIR__, 2).'/src';
$loaded = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $file) {
    $class = 'Omnibridge\\'.str_replace('/', '\\', substr($file->getPathname(), strlen($src) + 1, -4));
    if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
        $loaded[] = $class;
    }
}
sort($loaded);

echo json_encode([
    'loaded' => $loaded,
    'symbols' => array_values(array_diff([...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], $symbols)),
    'files' => array_values(array_diff(get_included_files(), $autoloaded)),
]);
