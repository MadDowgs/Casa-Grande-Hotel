<?php
/* PAYMENT API (System 2). Endpoint: POST /charges  (called by the Hotel API, never by the browser)
   URL: http://127.0.0.1:8003/api.php/charges        Header: X-API-Key: pay-key-456
   Methods: card (Visa / Mastercard / JCB / Amex issued by PH banks), gcash, maya */
header('Content-Type: application/json');
define('PAYMENT_KEY', getenv('PAYMENT_API_KEY') ?: 'pay-key-456');
const BANKS = ['BDO Unibank', 'BPI', 'Metrobank', 'Security Bank', 'UnionBank', 'Landbank', 'PNB', 'RCBC', 'China Bank',
               'EastWest Bank', 'PSBank', 'Maybank Philippines', 'GoTyme Bank', 'Other bank'];
function out($code, $data) { http_response_code($code); echo json_encode($data); exit; }

if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== PAYMENT_KEY) out(401, ['error' => 'Invalid or missing API key']);
if (trim($_SERVER['PATH_INFO'] ?? '', '/') !== 'charges') out(404, ['error' => 'Unknown endpoint']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'Use POST /charges']);

$b = json_decode(file_get_contents('php://input'), true);
if (!is_array($b)) out(400, ['error' => 'Request body must be valid JSON']);
foreach (['reference', 'amount', 'currency', 'method'] as $k)
    if (!isset($b[$k]) || $b[$k] === '') out(400, ['error' => "Missing required field: $k"]);
if (!is_numeric($b['amount']) || $b['amount'] <= 0) out(400, ['error' => 'Amount must be greater than 0']);
if ($b['currency'] !== 'PHP') out(400, ['error' => 'Only PHP is supported']);

function luhn($n) {
    $sum = 0; $alt = false;
    for ($i = strlen($n) - 1; $i >= 0; $i--) { $d = (int)$n[$i]; if ($alt) { $d *= 2; if ($d > 9) $d -= 9; } $sum += $d; $alt = !$alt; }
    return $sum % 10 === 0;
}
function brand_of($n) {
    if (preg_match('/^4/', $n)) return 'Visa';
    if (preg_match('/^(5[1-5]\d{2}|222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/', $n)) return 'Mastercard';
    if (preg_match('/^35(2[89]|[3-8]\d)/', $n)) return 'JCB';
    if (preg_match('/^3[47]/', $n)) return 'Amex';
    return null;
}
function norm_mobile($s) {
    $s = preg_replace('/[\s\-()]/', '', (string)$s);
    return preg_match('/^(\+63|63)(9\d{9})$/', $s, $m) ? '0' . $m[2] : $s;
}

$method = $b['method'];
if ($method === 'card') {
    $card  = preg_replace('/\D/', '', $b['card_number'] ?? '');
    $brand = brand_of($card);
    if (!$brand) out(400, ['error' => 'Unsupported card. We accept Visa, Mastercard, JCB and Amex.']);
    if (strlen($card) !== ($brand === 'Amex' ? 15 : 16) || !luhn($card)) out(400, ['error' => 'Invalid card number']);
    if (!in_array($b['bank'] ?? '', BANKS, true)) out(400, ['error' => 'Select the bank that issued the card']);
    if (!preg_match('/^[\p{L}][\p{L} .\'-]{1,59}$/u', trim($b['card_holder'] ?? ''))) out(400, ['error' => 'Enter the name on the card']);
    if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $b['expiry'] ?? '', $m)) out(400, ['error' => 'Expiry must be in MM/YY format']);
    $y = 2000 + (int)$m[2]; $mo = (int)$m[1]; $cy = (int)date('Y'); $cm = (int)date('n');
    if ($y < $cy || ($y === $cy && $mo < $cm) || $y > $cy + 20) out(400, ['error' => 'Card has expired or the expiry date is invalid']);
    if (!preg_match($brand === 'Amex' ? '/^\d{4}$/' : '/^\d{3}$/', $b['cvv'] ?? '')) out(400, ['error' => 'Invalid security code (CVV)']);
    $details = $b['bank'] . ' ' . $brand . ' **** ' . substr($card, -4);
    if ($card === '4000000000000002') out(402, ['status' => 'DECLINED', 'message' => 'Card declined by the issuing bank', 'details' => $details]);
} elseif ($method === 'gcash' || $method === 'maya') {
    $mob = norm_mobile($b['mobile_number'] ?? '');
    if (!preg_match('/^09\d{9}$/', $mob)) out(400, ['error' => 'Enter a valid Philippine mobile number (09XXXXXXXXX)']);
    $details = ($method === 'gcash' ? 'GCash ' : 'Maya ') . substr($mob, 0, 4) . '***' . substr($mob, -4);
    if (substr($mob, -4) === '0000') out(402, ['status' => 'DECLINED', 'message' => 'Insufficient wallet balance', 'details' => $details]);
} else {
    out(400, ['error' => 'Unsupported payment method']);
}

out(201, ['transaction_id' => 'TXN-' . strtoupper(bin2hex(random_bytes(4))), 'reference' => $b['reference'],
          'status' => 'APPROVED', 'method' => $method, 'details' => $details, 'message' => 'Payment approved']);
