<?php
/**
 * Audit log: writing, monthly rotation and reading.
 *
 * Files are `app-YYYY-MM.log.php`, one per month, each starting with a
 * `<?php exit; ?>` guard line. The guard matters: the log directory sits inside
 * the web root on this hosting, and a plain `.log` file is served to anyone who
 * asks for it. Naming the file `.php` means a direct request is handed to PHP,
 * which exits immediately and returns nothing, on nginx and Apache alike.
 * The reader skips that first line.
 *
 * Set LOG_DIRECTORY in config.local.php to an absolute path outside the web
 * root if the hosting allows it; that is stronger than the guard line.
 *
 * The legacy single `app.log` is still read if present, so nothing is lost
 * before `scripts/migrate-logs.php` has been run.
 */

declare(strict_types=1);

const LSC_LOG_GUARD = '<?php exit; ?>';

function lsc_log_dir(): string
{
    $dir = defined('LOG_DIRECTORY') && LOG_DIRECTORY !== '' ? LOG_DIRECTORY : __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return rtrim($dir, '/');
}

function lsc_log_month_file(string $ym): string
{
    return lsc_log_dir() . '/app-' . $ym . '.log.php';
}

function lsc_log_legacy_file(): string
{
    return lsc_log_dir() . '/app.log';
}

/** Append one entry to the current month's file, creating it with the guard line. */
function lsc_log_write(array $entry): void
{
    $ym   = substr((string) ($entry['timestamp'] ?? date('Y-m-d')), 0, 7);
    $path = lsc_log_month_file($ym);
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";

    if (!file_exists($path)) {
        // Guard + first entry in one write so the file is never briefly unguarded.
        file_put_contents($path, LSC_LOG_GUARD . "\n" . $line, LOCK_EX);
        @chmod($path, 0640);
        return;
    }
    file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

/** Months that have a log file, newest first, as 'YYYY-MM'. */
function lsc_log_months(): array
{
    $months = [];
    foreach (glob(lsc_log_dir() . '/app-*.log.php') ?: [] as $f) {
        if (preg_match('/app-(\d{4}-\d{2})\.log\.php$/', $f, $m)) {
            $months[] = $m[1];
        }
    }
    if (file_exists(lsc_log_legacy_file())) {
        $months[] = 'legacy';
    }
    rsort($months);
    return $months;
}

/** Decode one stored line, or null if it is the guard or not valid JSON. */
function lsc_log_decode_line(string $line): ?array
{
    $line = trim($line);
    if ($line === '' || strncmp($line, '<?php', 5) === 0) {
        return null;
    }
    $d = json_decode($line, true);
    return is_array($d) ? $d : null;
}

/** Which files cover the requested window. Empty dates mean "the newest month only". */
function lsc_log_files_for(string $startDate, string $endDate, string $month = ''): array
{
    $dir = lsc_log_dir();
    if ($month === 'legacy') {
        return file_exists(lsc_log_legacy_file()) ? [lsc_log_legacy_file()] : [];
    }
    if ($month !== '') {
        return file_exists(lsc_log_month_file($month)) ? [lsc_log_month_file($month)] : [];
    }

    $all = lsc_log_months();
    if ($startDate === '' && $endDate === '') {
        $newest = $all[0] ?? '';
        return $newest === '' ? [] : lsc_log_files_for('', '', $newest);
    }

    $from = $startDate !== '' ? substr($startDate, 0, 7) : '0000-00';
    $to   = $endDate !== '' ? substr($endDate, 0, 7) : '9999-99';
    $files = [];
    foreach ($all as $ym) {
        if ($ym === 'legacy') {
            $files[] = lsc_log_legacy_file();          // span unknown, always included when filtering
        } elseif ($ym >= $from && $ym <= $to) {
            $files[] = lsc_log_month_file($ym);
        }
    }
    return $files;
}

/**
 * Read and filter entries.
 *
 * $opts: search, start_date, end_date, month, page, limit
 * Returns ['entries' => [], 'total' => int, 'page' => int, 'pages' => int, 'months' => [], 'month' => string]
 */
function lsc_log_query(array $opts = []): array
{
    $search = trim((string) ($opts['search'] ?? ''));
    $start  = trim((string) ($opts['start_date'] ?? ''));
    $end    = trim((string) ($opts['end_date'] ?? ''));
    $month  = trim((string) ($opts['month'] ?? ''));
    $limit  = max(1, (int) ($opts['limit'] ?? 50));
    $page   = max(1, (int) ($opts['page'] ?? 1));

    $matches = [];
    foreach (lsc_log_files_for($start, $end, $month) as $file) {
        $fh = @fopen($file, 'r');
        if ($fh === false) {
            continue;
        }
        while (($line = fgets($fh)) !== false) {
            $e = lsc_log_decode_line($line);
            if ($e === null) {
                continue;
            }
            if ($start !== '' || $end !== '') {
                $d = substr((string) ($e['timestamp'] ?? ''), 0, 10);
                if ($start !== '' && $d < $start) { continue; }
                if ($end !== '' && $d > $end) { continue; }
            }
            if ($search !== ''
                && stripos((string) ($e['action'] ?? ''), $search) === false
                && stripos((string) ($e['username'] ?? ''), $search) === false
                && stripos((string) ($e['description'] ?? ''), $search) === false) {
                continue;
            }
            $matches[] = $e;
        }
        fclose($fh);
    }

    usort($matches, static fn($a, $b) => strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? '')));

    $total = count($matches);
    return [
        'entries' => array_slice($matches, ($page - 1) * $limit, $limit),
        'total'   => $total,
        'page'    => $page,
        'pages'   => (int) ceil($total / $limit),
        'months'  => lsc_log_months(),
        'month'   => $month,
    ];
}
