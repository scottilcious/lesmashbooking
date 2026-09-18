<?php
/**
 * Member password storage.
 *
 * Passwords are stored encrypted (AES-256-GCM) with a key that lives only in
 * config.local.php on the server, never in git or the database. This lets
 * admins view and change a member's password at reception while keeping the
 * database column unreadable on its own.
 *
 * Stored format:  enc1:<base64(iv . tag . ciphertext)>
 * Anything without the prefix is a legacy plaintext value; it still verifies,
 * and database/migrate-encrypt-passwords.php converts it.
 *
 * Always go through these functions. Never compare member_password in SQL.
 */

declare(strict_types=1);

const LSC_PW_PREFIX = 'enc1:';
const LSC_PW_CIPHER = 'aes-256-gcm';

function lsc_password_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    if (!defined('PASSWORD_ENCRYPTION_KEY') || !is_string(PASSWORD_ENCRYPTION_KEY)) {
        throw new RuntimeException('PASSWORD_ENCRYPTION_KEY is not defined in config.local.php');
    }
    $raw = hex2bin(PASSWORD_ENCRYPTION_KEY);
    if ($raw === false || strlen($raw) !== 32) {
        throw new RuntimeException('PASSWORD_ENCRYPTION_KEY must be 64 hex characters (32 bytes). Generate one with: php -r "echo bin2hex(random_bytes(32));"');
    }
    $key = $raw;
    return $key;
}

function lsc_password_is_encrypted(?string $stored): bool
{
    return is_string($stored) && strncmp($stored, LSC_PW_PREFIX, strlen(LSC_PW_PREFIX)) === 0;
}

function lsc_password_encrypt(string $plain): string
{
    $iv  = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, LSC_PW_CIPHER, lsc_password_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException('Password encryption failed');
    }
    return LSC_PW_PREFIX . base64_encode($iv . $tag . $cipher);
}

/** Returns the plaintext password, or null if the stored value is empty or cannot be decrypted. */
function lsc_password_decrypt(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return null;
    }
    if (!lsc_password_is_encrypted($stored)) {
        return $stored; // legacy plaintext
    }
    $blob = base64_decode(substr($stored, strlen(LSC_PW_PREFIX)), true);
    if ($blob === false || strlen($blob) < 28) {
        return null;
    }
    $iv     = substr($blob, 0, 12);
    $tag    = substr($blob, 12, 16);
    $cipher = substr($blob, 28);
    $plain  = openssl_decrypt($cipher, LSC_PW_CIPHER, lsc_password_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function lsc_password_verify(string $input, ?string $stored): bool
{
    $plain = lsc_password_decrypt($stored);
    if ($plain === null || $input === '') {
        return false;
    }
    return hash_equals($plain, $input);
}

/**
 * Value to write to members.member_password from a form field.
 * Empty input keeps the existing stored value (so an edit form that leaves the box blank does not wipe the password).
 */
function lsc_password_for_storage(string $input, ?string $existingStored = null): ?string
{
    if ($input === '') {
        return $existingStored;
    }
    // If the form posted back the already-encrypted blob unchanged, keep it as is.
    if (lsc_password_is_encrypted($input)) {
        return $input;
    }
    return lsc_password_encrypt($input);
}
