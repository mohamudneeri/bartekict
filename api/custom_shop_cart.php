<?php

require_once __DIR__ . '/../../init.php';
session_start();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'login':
        handleLogin();
        break;
    
    case 'place_order':
        handlePlaceOrder();
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

// ──────────────────────────────────
// LOGIN
// ──────────────────────────────────

function handleLogin() {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
        return;
    }

    try {
        $postData = [
            'email' => $email,
            'password2' => $password,
        ];

        $results = localAPI('ValidateLogin', $postData);

        if ($results['result'] === 'success') {

            // Create WHMCS login session
            $_SESSION['uid'] = $results['userid'];

            echo json_encode([
                'status' => 'success',
                'message' => 'Login successful',
            ]);

            return;
        }

        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid email or password'
        ]);

    } catch (Exception $e) {

        echo json_encode([
            'status' => 'error',
            'message' => 'Login failed: ' . $e->getMessage()
        ]);
    }
}


// ──────────────────────────────────
// PLACE ORDER
// ──────────────────────────────────
 
function handlePlaceOrder() {
    $steps     = [];
    $payMethod = trim($_POST['pay_method'] ?? '');
    $isLogged  = isset($_SESSION['uid']) && (int) $_SESSION['uid'] > 0;

    // 1. Process payment (pre-authorization)
    if ($payMethod !== 'waafi') {
        echo json_encode(['status' => 'error', 'message' => 'Only Waafi payment is supported in this version.']);
        return;
    }

    $waafiPhone = preg_replace('/\D/', '', $_POST['waafi_phone'] ?? '');
    if (empty($waafiPhone)) {
        echo json_encode(['status' => 'error', 'message' => 'Waafi phone number is required.']);
        return;
    }

    // Calculate order total from cart (you might have a helper function)
    $totalAmount = calculateCartTotal();

    // Generate a unique reference for this transaction (e.g., invoice ID / order ID)
    $referenceId = 'PRE_' . time() . '_' . bin2hex(random_bytes(4));

    $preauthResponse = callWaafiPreauthorization($waafiPhone, $totalAmount, $referenceId);

    if (!$preauthResponse['success']) {
        echo json_encode([
            'status'  => 'error',
            'steps'   => ['Payment pre-authorization failed.'],
            'message' => $preauthResponse['message']
        ]);
        return;
    }

    $waafiTransactionId = $preauthResponse['transactionId'];
    $steps[] = 'Payment pre-authorization successful. Funds reserved.';

    // 2. Validate / create customer account ----------
    $clientId = null;

    if ($isLogged) {
        $clientId = (int) $_SESSION['uid'];
        $steps[]  = 'Account verified.';
    } else {
        $firstname   = trim($_POST['firstname']   ?? '');
        $lastname    = trim($_POST['lastname']    ?? '');
        $email       = trim($_POST['email']       ?? '');
        $phonenumber = trim($_POST['phonenumber'] ?? '');
        $address1    = trim($_POST['address1']    ?? '');
        $city        = trim($_POST['city']        ?? '');
        $state       = trim($_POST['state']       ?? '');
        $postcode    = trim($_POST['postcode']    ?? '');
        $country     = trim($_POST['country']     ?? '');
        $password    = $_POST['password']         ?? '';

        $required = compact('firstname','lastname','email','phonenumber','address1','city','state','country','password');
        foreach ($required as $field => $val) {
            if ($val === '') {
                // Payment already on hold – we must release it
                cancelWaafiPreauthorization($waafiTransactionId);
                echo json_encode(['status' => 'error', 'message' => "Missing required field: $field"]);
                return;
            }
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            cancelWaafiPreauthorization($waafiTransactionId);
            echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
            return;
        }

        $steps[] = 'Creating your account…';

        try {
            $result = localAPI('AddClient', [
                'firstname'   => $firstname,
                'lastname'    => $lastname,
                'email'       => $email,
                'phonenumber' => $phonenumber,
                'address1'    => $address1,
                'city'        => $city,
                'state'       => $state,
                'postcode'    => $postcode,
                'country'     => $country,
                'password2'   => $password,
            ]);

            if ($result['result'] !== 'success') {
                cancelWaafiPreauthorization($waafiTransactionId);
                echo json_encode([
                    'status'  => 'error',
                    'steps'   => $steps,
                    'message' => $result['message'] ?? 'Failed to create account.',
                ]);
                return;
            }

            $clientId = (int) $result['clientid'];
            $_SESSION['uid'] = $clientId;
            $steps[] = 'Account created successfully.';
        } catch (Exception $e) {
            cancelWaafiPreauthorization($waafiTransactionId);
            echo json_encode(['status' => 'error', 'steps' => $steps, 'message' => 'Account creation failed: ' . $e->getMessage()]);
            return;
        }
    }

    // 3. Add order ----------
    $steps[] = 'Building your order…';

    $cartDomains  = $_SESSION['cart']['domains']  ?? [];
    $cartProducts = $_SESSION['cart']['products'] ?? [];

    if (empty($cartDomains) && empty($cartProducts)) {
        cancelWaafiPreauthorization($waafiTransactionId);
        echo json_encode(['status' => 'error', 'steps' => $steps, 'message' => 'Your cart is empty.']);
        return;
    }

    try {
        $orderData = [
            'clientid'      => $clientId,
            'paymentmethod' => 'waafi', // WHMCS gateway module name
            'priceoverride' => 0,
        ];

        // Products / hosting
        $pids          = [];
        $billingcycles = [];
        foreach ($cartProducts as $product) {
            $pids[]          = (int) $product['pid'];
            $billingcycles[] = $product['billingcycle'];
        }
        if (!empty($pids)) {
            $orderData['pid']          = $pids;
            $orderData['billingcycle'] = $billingcycles;
        }

        // Domains
        $domains    = [];
        $regperiods = [];
        $dnsManagement = [];
        foreach ($cartDomains as $domain) {
            $domains[] = $domain['domain'];
            $regperiods[] = (int) ($domain['regperiod'] ?? 1);
            $dnsManagement[] = 1;
        }
        if (!empty($domains)) {
            $orderData['domain']        = $domains;
            $orderData['regperiod']     = $regperiods;
            $orderData['dnsmanagement'] = $dnsManagement;
        }

        // Promo code
        if (!empty($_SESSION['cart']['promo'])) {
            $orderData['promocode'] = $_SESSION['cart']['promo'];
        }

        $steps[] = 'Submitting order…';

        $orderResult = localAPI('AddOrder', $orderData);

        if ($orderResult['result'] !== 'success') {
            cancelWaafiPreauthorization($waafiTransactionId);
            echo json_encode([
                'status'  => 'error',
                'steps'   => $steps,
                'message' => $orderResult['message'] ?? 'Failed to place order.',
            ]);
            return;
        }

        $orderId = (int) $orderResult['orderid'];
        $steps[] = 'Order created (ID: ' . $orderId . ').';
    } catch (Exception $e) {
        cancelWaafiPreauthorization($waafiTransactionId);
        echo json_encode(['status' => 'error', 'steps' => $steps, 'message' => 'Order failed: ' . $e->getMessage()]);
        return;
    }

    // 4. Commit the payment ----------
    $commitResponse = commitWaafiPreauthorization($waafiTransactionId);
    if (!$commitResponse['success']) {
        // Order is created but payment not captured – mark as pending or manual review
        // You may also set invoice status to “Unpaid” and log an admin alert.
        $steps[] = 'Warning: Order created but payment capture failed. Please contact support.';
        // Do not delete cart or destroy session here – admin can review.
        echo json_encode([
            'status'   => 'error',
            'steps'    => $steps,
            'message'  => 'Payment capture failed after order creation: ' . $commitResponse['message'],
            'order_id' => $orderId,
        ]);
        return;
    }

    $steps[] = 'Payment captured successfully.';

    // 5. Finalise (clear cart, optional invoice accept)
    // AcceptOrder may mark invoices as paid if the gateway module supports it
    localAPI('AcceptOrder', ['orderid' => $orderId]);

    unset($_SESSION['cart']);

    $steps[] = 'Order complete! Redirecting…';

    echo json_encode([
        'status'   => 'success',
        'steps'    => $steps,
        'redirect' => '/vieworder.php?id=' . $orderId,
    ]);
}

function callWaafiPreauthorization($phoneNumber, $amount, $referenceId) {
    // Fill these from your Waafi merchant dashboard
    $merchantUid = 'YOUR_MERCHANT_UID';
    $apiUserId   = 'YOUR_API_USER_ID';
    $apiKey      = 'YOUR_API_KEY';
    $apiUrl      = 'https://sandbox.waafipay.com/asm/query'; // or production URL

    $payload = [
        'schemaVersion' => '1.0',
        'requestId'     => generateUuid(),
        'timestamp'     => date('Y-m-d H:i:s'),
        'channelName'   => 'WEB',
        'serviceName'   => 'API_PREAUTHORIZE',
        'merchantUid'   => $merchantUid,
        'apiUserId'     => $apiUserId,
        'apiKey'        => $apiKey,
        'payerInfo' => [
            'accountNo' => $phoneNumber,
        ],
        'paymentMethod' => 'MWALLET_ACCOUNT',
        'transactionInfo' => [
            'amount'        => (float) $amount,
            'currency'      => 'USD',   // change to your currency (SOS, etc.)
            'referenceId'   => $referenceId,
            'description'   => 'Order pre-authorization from ' . $_SERVER['HTTP_HOST'],
        ],
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'message' => "HTTP $httpCode – " . substr($response, 0, 200)];
    }

    $data = json_decode($response, true);
    if (isset($data['responseCode']) && $data['responseCode'] === '200') {
        return [
            'success'       => true,
            'transactionId' => $data['transactionId'] ?? null,
            'message'       => $data['responseMessage'] ?? 'Pre-authorization successful',
        ];
    }

    return [
        'success' => false,
        'message' => $data['responseMessage'] ?? 'Pre-authorization failed'
    ];
}

function commitWaafiPreauthorization($transactionId) {
    // Similar call with serviceName = 'API_PREAUTHORIZE_COMMIT'
    // … (implement using the same API endpoint, same auth headers)
    // Return ['success' => true/false, 'message' => …]
}

function cancelWaafiPreauthorization($transactionId) {
    // Similar call with serviceName = 'API_PREAUTHORIZE_CANCEL'
    // … (implement to release funds on failure)
}

function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function calculateCartTotal() {
    // Ensure cart structure exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (!isset($_SESSION['cart']['domains'])) {
        $_SESSION['cart']['domains'] = [];
    }
    if (!isset($_SESSION['cart']['products'])) {
        $_SESSION['cart']['products'] = [];
    }
    
    $totals = [
        'subtotal' => 0,
        'discount' => 0,
        'grand_total' => 0,
        'items_count' => 0
    ];
    
    // Calculate product totals
    if (!empty($_SESSION['cart']['products'])) {
        foreach ($_SESSION['cart']['products'] as $product) {
            $price = getProductPrice($product['pid'], $product['billingcycle']);
            $totals['subtotal'] += $price;
            $totals['items_count']++;
        }
    }
    
    // Calculate domain totals (excluding free domains)
    if (!empty($_SESSION['cart']['domains'])) {
        foreach ($_SESSION['cart']['domains'] as $domain) {
            // Skip free domains
            if (isset($domain['freedomain']) && $domain['freedomain'] == 1) {
                $totals['items_count']++;
                continue;
            }
            
            $price = getDomainPrice($domain);
            $totals['subtotal'] += $price;
            $totals['items_count']++;
        }
    }
    
    // Apply promo discount
    if (isset($_SESSION['cart']['promo']) && !empty($_SESSION['cart']['promo'])) {
        $discount = calculatePromoDiscount($_SESSION['cart']['promo'], $totals['subtotal']);
        $totals['discount'] = min($discount, $totals['subtotal']);
        $totals['grand_total'] = $totals['subtotal'] - $totals['discount'];
    } else {
        $totals['grand_total'] = $totals['subtotal'];
    }
    
    // Store totals in session for consistency
    $_SESSION['cart']['totals'] = $totals;
    
    return $totals['grand_total'];
}

// Helper function to get product price (copied from custom_cart_manage.php)
function getProductPrice($pid, $cycle) {
    $pricing = \WHMCS\Database\Capsule::table('tblpricing')
        ->where('type', 'product')
        ->where('relid', $pid)
        ->first();
    
    if ($pricing && isset($pricing->$cycle)) {
        return (float)$pricing->$cycle;
    }
    return 0;
}

// Helper function to get domain price (copied from custom_cart_manage.php)
function getDomainPrice($domain) {
    $tld = ltrim($domain['tld'], '.');
    $type = $domain['type'] ?? 'register';
    $years = $domain['regperiod'] ?? 1;
    
    $pricing = localAPI('GetTLDPricing');
    
    if (isset($pricing['pricing'][$tld][$type])) {
        $priceData = $pricing['pricing'][$tld][$type];
        if (is_array($priceData)) {
            $pricePerYear = (float)($priceData[$years] ?? $priceData[1] ?? 0);
        } else {
            $pricePerYear = (float)$priceData;
        }
        return $pricePerYear * $years;
    }
    
    return 0;
}

// Helper function to calculate promo discount (copied from custom_cart_manage.php)
function calculatePromoDiscount($promoCode, $subtotal) {
    $promo = \WHMCS\Database\Capsule::table('tblpromotions')
        ->whereRaw('LOWER(code) = ?', [strtolower($promoCode)])
        ->first();
    
    if (!$promo) {
        return 0;
    }
    
    if ($promo->type == 'Percentage' || $promo->type == 'percentage') {
        return $subtotal * (floatval($promo->value) / 100);
    } else {
        return floatval($promo->value);
    }
}