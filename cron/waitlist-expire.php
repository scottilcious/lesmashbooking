<?php
/**
 * Expire stale waitlist offers and pass the court to the next member.
 *
 * Run every 5 minutes from cron:
 *   (every 5 minutes)  /usr/bin/php /path/to/site/cron/waitlist-expire.php >> /path/to/site/logs/cron.log 2>&1
 *
 * The member waitlist page also runs this sweep lazily, so the cron only
 * guarantees timeliness when nobody is browsing.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/waitlist-service.php';

$n = lsc_waitlist_expire_stale($pdo);
echo date('Y-m-d H:i:s') . " expired $n waitlist offer(s)\n";
