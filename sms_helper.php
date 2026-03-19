<?php
declare(strict_types=1);

/**
 * Send and verify OTP through IPROG SMS.
 *
 * @return array{ok:bool,message:string}
 */
function rbj_iprog_api_request(string $endpoint, array $payload): array
{
    $enabled = rbj_env_bool('RBJ_SMS_ENABLED', false);
    if (!$enabled) {
        return ['ok' => false, 'message' => 'SMS is disabled.'];
    }

    $api_token = trim((string)rbj_env('RBJ_IPROG_API_TOKEN', ''));
    $base_url = rtrim(trim((string)rbj_env('RBJ_IPROG_BASE_URL', 'https://sms.iprogtech.com/api/v1')), '/');

    if ($api_token === '') {
        return ['ok' => false, 'message' => 'IPROG API token is missing.'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'cURL is required for SMS sending.'];
    }

    $request_payload = http_build_query(array_merge(['api_token' => $api_token], $payload));
    $url = $base_url . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $request_payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $status_code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'message' => $curl_error !== '' ? $curl_error : 'SMS request failed.'];
    }

    $decoded = json_decode($response, true);
    if ($status_code < 200 || $status_code >= 300) {
        $message = is_array($decoded) ? trim((string)($decoded['message'] ?? '')) : '';
        return ['ok' => false, 'message' => $message !== '' ? $message : ('IPROG returned HTTP ' . $status_code . '.')];
    }

    if (!is_array($decoded)) {
        return ['ok' => false, 'message' => 'Invalid response from IPROG SMS.'];
    }

    $raw_status = $decoded['status'] ?? null;
    $status = strtolower(trim((string)$raw_status));
    $message = trim((string)($decoded['message'] ?? ''));
    $is_success = $raw_status === true || $status === 'success' || $status === '200' || (is_int($raw_status) && $raw_status === 200);

    return [
        'ok' => $is_success,
        'message' => $message !== '' ? $message : ($is_success ? 'Request completed successfully.' : 'IPROG request failed.'),
        'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : []
    ];
}

function rbj_send_sms_otp(string $to_phone): array
{
    $iprog_phone = rbj_phone_for_iprog($to_phone);
    if ($iprog_phone === null) {
        return ['ok' => false, 'message' => 'Invalid mobile number for IPROG OTP.'];
    }

    $message_template = trim((string)rbj_env(
        'RBJ_IPROG_OTP_MESSAGE',
        'RBJ Accessories verification code: :otp. It is valid for 5 minutes. Do not share this code with anyone.'
    ));

    if ($message_template !== '' && strpos($message_template, ':otp') === false) {
        return ['ok' => false, 'message' => 'IPROG OTP message template must contain :otp.'];
    }

    $payload = ['phone_number' => $iprog_phone];
    if ($message_template !== '') {
        $payload['message'] = $message_template;
    }

    return rbj_iprog_api_request('otp/send_otp', $payload);
}

function rbj_verify_sms_otp(string $to_phone, string $otp): array
{
    $iprog_phone = rbj_phone_for_iprog($to_phone);
    if ($iprog_phone === null) {
        return ['ok' => false, 'message' => 'Invalid mobile number for IPROG OTP verification.'];
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
        return ['ok' => false, 'message' => 'OTP must be a 6-digit code.'];
    }

    return rbj_iprog_api_request('otp/verify_otp', [
        'phone_number' => $iprog_phone,
        'otp' => $otp
    ]);
}
