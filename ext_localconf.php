<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Cache backing the ingest rate limiter: short-lived, fixed-window counters
// keyed by a hash of the client IP (no plaintext IP is stored). Uses the
// default backend; operators may point it at a faster one.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['neoblack_webmcp'] ??= [];
