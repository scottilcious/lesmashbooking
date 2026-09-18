<?php
/**
 * HTTP-level test helpers: run the real pages through PHP's built-in server
 * against the test database (config.php honours LSC_DB_DATABASE).
 *
 *   $c = t_http_login('1234', 'pw');          // cookie jar for a member session
 *   $r = t_http_post('cancel_booking.php', ['booking_id' => 5], $c);
 *   $r['status'], $r['body']
 */

declare(strict_types=1);

const T_HTTP_HOST = '127.0.0.1';
const T_HTTP_PORT = 8099;

function t_http_server(): void
{
    static $proc = null;
    if ($proc !== null) {
        return;
    }
    $root = realpath(__DIR__ . '/..');
    $env = array_merge($_ENV, getenv(), ['LSC_DB_DATABASE' => DB_TEST_DATABASE]);
    // Like production: errors go to a log, not the response (a printed warning would break redirects).
    $log = sys_get_temp_dir() . '/lsc-test-server.log';
    $cmd = [PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' . $log, '-S', T_HTTP_HOST . ':' . T_HTTP_PORT, '-t', $root];
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', sys_get_temp_dir() . '/lsc-test-server.log', 'a']], $pipes, $root, $env);
    if (!is_resource($proc)) {
        throw new RuntimeException('could not start test web server');
    }
    register_shutdown_function(function () use ($proc) { proc_terminate($proc); });
    for ($i = 0; $i < 50; $i++) {
        $s = @fsockopen(T_HTTP_HOST, T_HTTP_PORT, $errno, $errstr, 0.2);
        if ($s) { fclose($s); return; }
        usleep(100000);
    }
    throw new RuntimeException('test web server did not come up');
}

function t_http_request(string $method, string $path, array $data = [], ?string $cookieJar = null): array
{
    t_http_server();
    $ch = curl_init('http://' . T_HTTP_HOST . ':' . T_HTTP_PORT . '/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException('curl: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hsize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $hsize);
    $location = preg_match('/^Location:\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
    return ['status' => $status, 'body' => substr($raw, $hsize), 'location' => $location];
}

function t_http_get(string $path, ?string $cookieJar = null): array
{
    return t_http_request('GET', $path, [], $cookieJar);
}

function t_http_post(string $path, array $data, ?string $cookieJar = null): array
{
    return t_http_request('POST', $path, $data, $cookieJar);
}

function t_http_cookie_jar(): string
{
    return tempnam(sys_get_temp_dir(), 'lsc-cookie-');
}

/** Log a member in; returns the cookie jar path. Throws if login did not redirect to index.php. */
function t_http_login(string $memberNumber, string $password): string
{
    $jar = t_http_cookie_jar();
    $r = t_http_post('login.php', ['login_type' => 'member', 'member_number' => $memberNumber, 'password' => $password], $jar);
    if ($r['status'] !== 302 || !str_contains((string) $r['location'], 'index.php')) {
        throw new RuntimeException("login failed for $memberNumber: status {$r['status']} location {$r['location']}");
    }
    return $jar;
}

/** Log a guest in (creates the non_members row if needed); returns the cookie jar path. */
function t_http_login_guest(string $name, string $email, string $phone): string
{
    $jar = t_http_cookie_jar();
    $r = t_http_post('login.php', ['login_type' => 'non-member', 'guest_fullname' => $name, 'guest_email' => $email, 'guest_phone_number' => $phone], $jar);
    if ($r['status'] !== 302) {
        throw new RuntimeException("guest login failed: status {$r['status']}");
    }
    return $jar;
}
