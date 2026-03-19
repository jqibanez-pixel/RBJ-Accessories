<?php
declare(strict_types=1);

function rbj_ensure_verification_schema(mysqli $conn): void
{
    $columns = [
        'sms_verified_at' => "DATETIME NULL DEFAULT NULL",
        'contact_number' => "VARCHAR(30) DEFAULT NULL",
        'is_verified' => "TINYINT(1) NOT NULL DEFAULT 0"
    ];

    foreach ($columns as $column_name => $column_sql) {
        $escaped_column = $conn->real_escape_string($column_name);
        $result = $conn->query("
            SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = '{$escaped_column}'
        ");
        $row = $result ? $result->fetch_assoc() : null;
        $exists = (int)($row['total'] ?? 0) > 0;
        if (!$exists) {
            $conn->query("ALTER TABLE users ADD COLUMN {$column_name} {$column_sql}");
        }
    }

    $conn->query("
        UPDATE users
        SET is_verified = 1
        WHERE
            sms_verified_at IS NOT NULL
            AND sms_verified_at <> '0000-00-00 00:00:00'
    ");
}

function rbj_normalize_phone_number(string $raw_phone): ?string
{
    $phone = trim($raw_phone);
    if ($phone === '') {
        return null;
    }

    $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';
    if ($phone === '') {
        return null;
    }

    if (str_starts_with($phone, '+')) {
        $digits = '+' . preg_replace('/\D/', '', substr($phone, 1));
    } else {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
    }

    if ($digits === '') {
        return null;
    }

    if (str_starts_with($digits, '09') && strlen($digits) === 11) {
        return '+63' . substr($digits, 1);
    }

    if (str_starts_with($digits, '9') && strlen($digits) === 10) {
        return '+63' . $digits;
    }

    if (str_starts_with($digits, '639') && strlen($digits) === 12) {
        return '+' . $digits;
    }

    if (str_starts_with($digits, '00639') && strlen($digits) === 14) {
        return '+' . substr($digits, 2);
    }

    if (str_starts_with($digits, '+639') && strlen($digits) === 13) {
        return $digits;
    }

    return null;
}

function rbj_mask_phone_number(string $phone): string
{
    $normalized = rbj_normalize_phone_number($phone);
    if ($normalized === null) {
        return 'your mobile number';
    }

    $tail = substr($normalized, -4);
    return '+63 ' . str_repeat('*', 3) . ' ' . str_repeat('*', 3) . ' ' . $tail;
}

function rbj_phone_for_iprog(string $phone): ?string
{
    $normalized = rbj_normalize_phone_number($phone);
    if ($normalized === null) {
        return null;
    }

    if (str_starts_with($normalized, '+63') && strlen($normalized) === 13) {
        return '0' . substr($normalized, 3);
    }

    return null;
}

function rbj_user_is_verified(array $user): bool
{
    $is_verified_flag = ((int)($user['is_verified'] ?? 0) === 1);
    $sms_verified_at = trim((string)($user['sms_verified_at'] ?? ''));
    $has_sms_verification = ($sms_verified_at !== '' && $sms_verified_at !== '0000-00-00 00:00:00');

    return $is_verified_flag && $has_sms_verification;
}
