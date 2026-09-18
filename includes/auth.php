<?php
/**
 * Session identity and access control.
 *
 * Every page and AJAX handler must take the caller's identity from the session,
 * never from a form field or query string. Use:
 *
 *   $me = lsc_require_login();   // any logged-in member, guest or admin; else 403 (or redirect)
 *   $me = lsc_require_member();  // club member or admin (has a members row)
 *   $me = lsc_require_guest();   // non-member
 *   $me = lsc_require_admin();   // admin only
 *
 * $me = ['id' => int, 'type' => string, 'is_admin' => bool, 'is_guest' => bool, 'name' => string]
 *
 * $mode: 'die' (AJAX handlers: plain text + HTTP status) or 'redirect' (pages: send to login).
 */

declare(strict_types=1);

function lsc_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function lsc_current_user(): ?array
{
    lsc_session_start();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $type = strtolower(trim((string) ($_SESSION['member_type'] ?? '')));
    return [
        'id'       => (int) $_SESSION['user_id'],
        'type'     => $type,
        'is_admin' => $type === 'admin',
        'is_guest' => $type === 'non-member',
        'name'     => (string) ($_SESSION['member_fullname'] ?? ''),
    ];
}

function lsc_deny(string $mode, int $status, string $message): void
{
    if ($mode === 'redirect') {
        header('Location: login.php');
        exit;
    }
    http_response_code($status);
    exit($message);
}

function lsc_require_login(string $mode = 'die'): array
{
    $me = lsc_current_user();
    if ($me === null) {
        lsc_deny($mode, 401, 'Unauthorized access.');
    }
    return $me;
}

function lsc_require_admin(string $mode = 'die'): array
{
    $me = lsc_require_login($mode);
    if (!$me['is_admin']) {
        lsc_deny($mode, 403, 'Admin only.');
    }
    return $me;
}

function lsc_require_member(string $mode = 'die'): array
{
    $me = lsc_require_login($mode);
    if ($me['is_guest']) {
        lsc_deny($mode, 403, 'Members only.');
    }
    return $me;
}

function lsc_require_guest(string $mode = 'die'): array
{
    $me = lsc_require_login($mode);
    if (!$me['is_guest']) {
        lsc_deny($mode, 403, 'Guest accounts only.');
    }
    return $me;
}
