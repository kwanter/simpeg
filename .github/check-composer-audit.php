<?php

use Simpeg\Ci\ComposerAuditPolicy;

require_once __DIR__.'/ComposerAuditPolicy.php';

if ($argc !== 3 || ! ctype_digit($argv[2])) {
    fwrite(STDERR, "Usage: php .github/check-composer-audit.php <report.json> <audit-exit-status>\n");
    exit(2);
}

try {
    $json = file_get_contents($argv[1]);

    if ($json === false) {
        throw new RuntimeException('The Composer audit report cannot be read.');
    }

    (new ComposerAuditPolicy)->check($json, (int) $argv[2]);

    fwrite(STDOUT, "Composer audit policy passed: no advisories reported.\n");
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
