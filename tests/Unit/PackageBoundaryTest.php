<?php

it('does not depend on any host application namespace', function () {
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src')) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        if (preg_match('/^use App\\\\|\\\\App\\\\|App\\\\[A-Z]/m', file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});
