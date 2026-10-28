<?php
/**
 * PhilSMS test script
 * -------------------
 * 1. Put your API token below (Dashboard -> API token).
 * 2. Put YOUR OWN mobile number as the recipient.
 * 3. Run in browser (http://localhost/philsms_test.php) or CLI: php philsms_test.php
 *
 * Docs: https://app.philsms.com/developers/documentation
 */

// ===================== CONFIG =====================
$API_TOKEN = '5656|JaJ96VOIUdy5SRERcl8fZZ70R4JVBK8PCvZ6Wu2q02d967ac';
$SENDER_ID = 'PhilSMS';              // use your approved sender ID; "PhilSMS" is the docs' default
$RECIPIENT = '09567661468';          // your own number: 09XXXXXXXXX or 639XXXXXXXXX
$MESSAGE   = 'Hello! This is a test message from my system.';
// ==================================================

const PHILSMS_URL = 'https://dashboard.philsms.com/api/v3/sms/send';
/**
 * Convert 09XXXXXXXXX / +639XXXXXXXXX / 9XXXXXXXXX to 639XXXXXXXXX
 */
function normalizePhNumber(string $number): string
{
    $number = preg_replace('/\D+/', '', $number);   // keep digits only
    if (strpos($number, '63') === 0) {
        return $number;
    }
    if (strpos($number, '0') === 0) {
        return '63' . substr($number, 1);
    }
    return '63' . $number;
}

/**
 * Send an SMS. Returns an array: ['ok' => bool, 'http' => int, 'body' => mixed, 'error' => string|null]
 */
function sendSms(string $token, string $senderId, string $recipient, string $message): array
{
    $payload = [
        'recipient' => normalizePhNumber($recipient),
        'sender_id' => $senderId,
        'type'      => 'plain',
        'message'   => $message,
    ];

    $ch = curl_init(PHILSMS_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'http' => $http, 'body' => null, 'error' => 'cURL error: ' . $curlErr];
    }

    $body = json_decode($response, true);
    $ok   = $http >= 200 && $http < 300 && is_array($body) && ($body['status'] ?? '') === 'success';

    return [
        'ok'    => $ok,
        'http'  => $http,
        'body'  => $body ?? $response,
        'error' => $ok ? null : ($body['message'] ?? 'Request failed'),
    ];
}

// ---------- Run the test ----------
$result = sendSms($API_TOKEN, $SENDER_ID, $RECIPIENT, $MESSAGE);

header('Content-Type: text/plain; charset=utf-8');
echo $result['ok'] ? "SUCCESS: SMS request accepted.\n" : "FAILED: " . $result['error'] . "\n";
echo "HTTP status: " . $result['http'] . "\n\n";
echo "Raw response:\n";
print_r($result['body']);
echo "\n";