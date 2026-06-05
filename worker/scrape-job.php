<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use ManhwaPortal\Controllers\ApiController;

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$jobId = (string) ($argv[1] ?? '');
if ($jobId === '') {
    fwrite(STDERR, "Job ID kosong.\n");
    exit(1);
}

(new ApiController())->runScrapeJob($jobId);
