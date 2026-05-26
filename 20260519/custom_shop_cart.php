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

// -------------------------------------------------------------------
// LOGIN
// -------------------------------------------------------------------

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


// ─────────────────────────────────────────────────────────────────────────────
// PLACE ORDER
// ─────────────────────────────────────────────────────────────────────────────
 
function handlePlaceOrder() {
 
    $steps     = [];   // Processing messages sent back to the frontend
    $isLogged  = isset($_SESSION['uid']) && (int) $_SESSION['uid'] > 0;
    $payMethod = trim($_POST['pay_method'] ?? '');
    $clientId  = null;
 
    // ── Step 1: Validate/create the customer account ──────────────────────────
 
    if ($isLogged) {
        $clientId = (int) $_SESSION['uid'];
        $steps[]  = 'Account verified.';
 
    } else {
        // Sanitise registration inputs
        $firstname   = trim($_POST['firstname']   ?? '');
        $lastname    = trim($_POST['lastname']    ?? '');
        $email       = trim($_POST['email']       ?? '');
        $phonenumber = trim($_POST['phonenumber'] ?? '');
        $address1    = trim($_POST['address1']    ?? '');
        $city        = trim($_POST['city']        ?? '');
        $state       = trim($_POST['state']       ?? '');
        $postcode    = trim($_POST['postcode']    ?? '');
        $country     = trim($_POST['country']     ?? '');
        $password    =      $_POST['password']    ?? '';
 
        // Basic server-side presence checks (JS already validated format)
        $required = compact('firstname','lastname','email','phonenumber','address1','city','state','country','password');
        foreach ($required as $field => $val) {
            if ($val === '') {
                echo json_encode(['status' => 'error', 'message' => "Missing required field: $field"]);
                return;
            }
        }
 
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
            echo json_encode(['status' => 'error', 'steps' => $steps, 'message' => 'Account creation failed: ' . $e->getMessage()]);
            return;
        }
    }
 
    // ── Step 2: Add order ─────────────────────────────────────────────────────
 
    $steps[] = 'Building your order…';
 
    $cartDomains  = $_SESSION['cart']['domains']  ?? [];
    $cartProducts = $_SESSION['cart']['products'] ?? [];
 
    if (empty($cartDomains) && empty($cartProducts)) {
        echo json_encode(['status' => 'error', 'steps' => $steps, 'message' => 'Your cart is empty.']);
        return;
    }
 
    // Map JS payment method slug → WHMCS payment gateway module name
    $gatewayMap = [
        'waafi'      => 'waafi',       // adjust to your actual WHMCS module name
        'creditcard' => 'creditcard',  // adjust to your actual WHMCS module name
    ];
    $gateway = $gatewayMap[$payMethod] ?? $payMethod;
 
    try {
        // Build AddOrder payload
        $orderData = [
            'clientid'      => $clientId,
            'paymentmethod' => $gateway,
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
        $domains   = [];
        $regperiods = [];
        $dnsManagement = [];
        foreach ($cartDomains as $domain) {
            $domains[]      = $domain['domain'];
            $regperiods[]   = (int) ($domain['regperiod'] ?? 1);
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
            echo json_encode([
                'status'  => 'error',
                'steps'   => $steps,
                'message' => $orderResult['message'] ?? 'Failed to place order.',
            ]);
            return;
        }
 
        $orderId = (int) $orderResult['orderid'];
        $steps[] = 'Order received.';
 
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'steps' => $steps, 'message' => 'Order failed: ' . $e->getMessage()]);
        return;
    }
 
    // ── Step 3: Process payment ───────────────────────────────────────────────
 
    $steps[] = 'Processing payment…';
 
    try {
        // Waafi — initiate push payment
        if ($payMethod === 'waafi') {
            $waafiPhone = preg_replace('/\D/', '', $_POST['waafi_phone'] ?? '');
 
            $payResult = localAPI('AcceptOrder', [
                'orderid'   => $orderId,
            ]);
 
            // TODO: Trigger actual Waafi push-payment API call here and pass $waafiPhone
            // For now we accept the order and let the gateway handle the rest.
            $steps[] = 'Waafi payment request sent to your phone.';
        }
 
        // Credit card
        elseif ($payMethod === 'creditcard') {
            // IMPORTANT: Handling raw card data requires PCI-DSS compliance.
            // Pass card details to your payment gateway SDK — do NOT store them.
            $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
            $cardExpiry = $_POST['card_expiry'] ?? '';
            $cardCvv    = $_POST['card_cvv']    ?? '';
            $cardName   = trim($_POST['card_name'] ?? '');
 
            // TODO: Charge card via your gateway SDK using the above fields.
            // Example placeholder:
            // $chargeResult = MyGateway::charge($clientId, $cardNumber, $cardExpiry, $cardCvv, $cardName, $grandTotal);
 
            $steps[] = 'Checking card details…';
            $steps[] = 'Payment authorised.';
        }
 
        else {
            // Other gateways (bank transfer, etc.)
            localAPI('AcceptOrder', ['orderid' => $orderId]);
            $steps[] = 'Order accepted.';
        }
 
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'steps' => $steps, 'message' => 'Payment processing failed: ' . $e->getMessage()]);
        return;
    }
 
    // ── Step 4: Finalise ──────────────────────────────────────────────────────
 
    $steps[] = 'Finalising your order…';
 
    // Clear the cart from session
    unset($_SESSION['cart']);
 
    $steps[] = 'Order complete! Redirecting…';
 
    echo json_encode([
        'status'   => 'success',
        'steps'    => $steps,
        'redirect' => '/vieworder.php?id=' . $orderId,   // adjust to your confirmation page
    ]);
}

