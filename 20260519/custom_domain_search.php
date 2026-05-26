<?php
require_once __DIR__ . '/../../init.php';
session_start();

header('Content-Type: application/json');

$domain = trim($_POST['domain'] ?? '');
if (empty($domain)) {
    echo json_encode(['status' => 'error', 'message' => 'No domain provided']);
    exit;
}

// Split domain into SLD and TLD
$parts = explode('.', $domain, 2);
if (count($parts) < 2) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid domain format']);
    exit;
}
$sld = $parts[0];
$tld = '.' . $parts[1];
$cleanTld = ltrim($tld, '.');

// Check availability
$whois = localAPI('DomainWhois', ['domain' => $domain]);
$status = ($whois['status'] === 'available') ? 'available' : 'unavailable';

// Get pricing
$pricing = localAPI('GetTLDPricing');
$registerPrice = '0.00';
$transferPrice = '0.00';

if (isset($pricing['pricing'][$cleanTld])) {
    $regPrices = $pricing['pricing'][$cleanTld]['register'] ?? [];
    $transPrices = $pricing['pricing'][$cleanTld]['transfer'] ?? [];
    
    // Get first currency price (simplified)
    $registerPrice = is_array($regPrices) ? reset($regPrices) : $regPrices;
    $transferPrice = is_array($transPrices) ? reset($transPrices) : $transPrices;
}

// Generate suggestions (available alternative TLDs)
$suggestions = [];
$popularTlds = ['.com', '.net', '.org', '.online', '.info', '.biz', '.shop', '.vip'];

foreach ($popularTlds as $sugTld) {
    if ($sugTld === $tld) continue; // skip exact same TLD
    
    $suggestDomain = $sld . $sugTld;
    $check = localAPI('DomainWhois', ['domain' => $suggestDomain]);
    
    if ($check['status'] === 'available') {
        $cleanSugTld = ltrim($sugTld, '.');
        $sugPrice = $pricing['pricing'][$cleanSugTld]['register'] ?? null;
        $price = is_array($sugPrice) ? reset($sugPrice) : ($sugPrice ?? '0.00');
        
        $suggestions[] = [
            'domain' => $suggestDomain,
            'price' => number_format($price, 2)
        ];
        
        if (count($suggestions) >= 6) break; // limit suggestions
    }
}

$inCart = false;
if (isset($_SESSION['cart']['domains'])) {
    foreach ($_SESSION['cart']['domains'] as $cartItem) {
        if ($cartItem['domain'] === $domain) {
            $inCart = true;
            break;
        }
    }
}

// Also check suggestions for cart status
$suggestionsWithCartStatus = [];
foreach ($suggestions as $suggestion) {
    $suggestionInCart = false;
    if (isset($_SESSION['cart']['domains'])) {
        foreach ($_SESSION['cart']['domains'] as $cartItem) {
            if ($cartItem['domain'] === $suggestion['domain']) {
                $suggestionInCart = true;
                break;
            }
        }
    }
    $suggestionsWithCartStatus[] = [
        'domain' => $suggestion['domain'],
        'price' => $suggestion['price'],
        'inCart' => $suggestionInCart
    ];
}

echo json_encode([
    'status' => $status,
    'domain' => $domain,
    'inCart' => $inCart,
    'registerPrice' => number_format($registerPrice, 2),
    'transferPrice' => number_format($transferPrice, 2),
    'suggestions' => $suggestionsWithCartStatus
]);