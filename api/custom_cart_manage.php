<?php

require_once __DIR__ . '/../../init.php';
session_start();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'add':
        addToCart();
        break;

    case 'remove':
        removeFromCart();
        break;

    case 'list':
        getCartItems();
        break;

    case 'count':
        getCartCount();
        break;

    case 'clear':
        clearCart();
        break;

    case 'get':
        getCartSummary();
        break;

    case 'select_cart_domain':
        selectCartDomain();
        break;

    case 'set_own_domain':
        setOwnDomain();
        break;

    case 'set_selected_domain':
        setSelectedDomain();
        break;

    case 'get_selected_hosting_plan':
        getSelectedHostingPlan();
        break;

    // case 'get_cart_domains':
    //     getCartDomains();
    //     break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

// -------------------------------------------------------------------
// Existing functions
// -------------------------------------------------------------------

function addToCart() {
    $domain = $_POST['domain'] ?? '';
    $type   = $_POST['type']   ?? 'register';

    if (!$domain) {
        echo json_encode(['status' => 'error']);
        return;
    }

    $parts = explode('.', $domain, 2);
    if (count($parts) < 2) {
        echo json_encode(['status' => 'invalid']);
        return;
    }

    $sld = $parts[0];
    $tld = '.' . $parts[1];

    if (!isset($_SESSION['cart']['domains'])) {
        $_SESSION['cart']['domains'] = [];
    }

    // Prevent duplicates
    foreach ($_SESSION['cart']['domains'] as $d) {
        if ($d['domain'] === $domain) {
            echo json_encode(['status' => 'exists']);
            return;
        }
    }

    $_SESSION['cart']['domains'][] = [
        'type'      => $type,
        'domain'    => $domain,
        'sld'       => $sld,
        'tld'       => $tld,
        'regperiod' => 1,
    ];

    /*
     * If the caller passes a hosting plan (pid + slug + cycle), save it to
     * session so checkout can pick it up alongside the domain.
     * All three fields must be present to avoid accidentally clearing a plan
     * that was stored earlier in the checkout flow.
     */
    /* $pid   = trim($_POST['pid']   ?? '');
    $slug  = trim($_POST['slug']  ?? '');
    $cycle = trim($_POST['cycle'] ?? '');

    if ($pid !== '' && $slug !== '' && $cycle !== '') {
        $_SESSION['selected_hosting_plan'] = [
            'pid'   => $pid,
            'slug'  => $slug,
            'cycle' => $cycle,
        ];
    } */
    
    $pid   = (int) ($_POST['pid'] ?? 0);
    $slug  = trim($_POST['slug'] ?? '');
    $cycle = trim($_POST['cycle'] ?? '');

    if ($pid > 0 && $slug !== '' && $cycle !== '') {
        // Store selected hosting plan
        $_SESSION['selected_hosting_plan'] = [
            'pid'   => $pid,
            'slug'  => $slug,
            'cycle' => $cycle,
        ];

        // Initialize products cart
        if (!isset($_SESSION['cart']['products'])) {
            $_SESSION['cart']['products'] = [];
        }

        // Prevent duplicate hosting product
        $productExists = false;

        foreach ($_SESSION['cart']['products'] as $product) {
            if ((int)$product['pid'] === $pid) {
                $productExists = true;
                break;
            }
        }

        // Add hosting product
        if (!$productExists) {
            $_SESSION['cart']['products'][] = [
                'pid'      => $pid,
                'billingcycle' => $cycle,
                'domain'   => $domain,
            ];
        }
    }
    getCartSummary();
}

function removeFromCart() {
    $domain = $_POST['domain'] ?? '';

    if (!$domain || empty($_SESSION['cart']['domains'])) {
        echo json_encode(['status' => 'error']);
        return;
    }

    foreach ($_SESSION['cart']['domains'] as $key => $d) {
        if ($d['domain'] === $domain) {
            unset($_SESSION['cart']['domains'][$key]);
        }
    }

    $_SESSION['cart']['domains'] = array_values($_SESSION['cart']['domains']);

    echo json_encode([
        'status' => 'success',
        'count'  => count($_SESSION['cart']['domains']),
    ]);
}

function clearCart() {
    $_SESSION['cart']['domains'] = [];
    echo json_encode(['status' => 'success', 'count' => 0]);
}

function getCartItems() {
    $items = $_SESSION['cart']['domains'] ?? [];
    echo json_encode(['status' => 'success', 'items' => $items]);
}

function getCartCount() {
    $count = isset($_SESSION['cart']['domains']) ? count($_SESSION['cart']['domains']) : 0;
    echo json_encode(['status' => 'success', 'count' => $count]);
}

function getCartSummary() {
    $items = $_SESSION['cart']['domains'] ?? [];
    $total = 0.00;

    if (!empty($items)) {
        $pricing  = localAPI('GetTLDPricing');
        $currency = localAPI('GetCurrencies', ['id' => 1]);

        foreach ($items as $item) {
            $tld       = ltrim($item['tld'], '.');
            $priceData = null;

            if ($item['type'] === 'register') {
                $priceData = $pricing['pricing'][$tld]['register'] ?? null;
            } elseif ($item['type'] === 'transfer') {
                $priceData = $pricing['pricing'][$tld]['transfer'] ?? null;
            }

            if (is_array($priceData)) {
                $price = $priceData[1] ?? reset($priceData) ?? 0;
            } else {
                $price = $priceData ?? 0;
            }

            $total += (float)$price * $item['regperiod'];
        }
    }

    $prefix  = $currency['currencies'][0]['prefix']   ?? '$';
    $suffix  = $currency['currencies'][0]['suffix']   ?? '';
    $decimals = $currency['currencies'][0]['decimals'] ?? 2;

    $totalFormatted = $prefix . number_format($total, $decimals) . $suffix;

    echo json_encode([
        'status'         => 'success',
        'count'          => count($items),
        'total'          => $total,
        'totalFormatted' => $totalFormatted,
    ]);
}

function selectCartDomain() {
    $domain = $_POST['domain'] ?? '';

    if (!$domain) {
        echo json_encode(['status' => 'error', 'message' => 'No domain selected']);
        return;
    }

    $_SESSION['selected_domain'] = [
        'domain'      => $domain,
        'type'        => 'cart',
        'selected_at' => time(),
    ];

    echo json_encode(['status' => 'success', 'message' => 'Domain selected', 'domain' => $domain]);
}

function setOwnDomain() {
    $domain = trim($_POST['domain'] ?? '');

    if (!$domain) {
        echo json_encode(['status' => 'error', 'message' => 'No domain provided']);
        return;
    }

    // Strip www.
    $domain = preg_replace('/^www\./', '', $domain);

    if (!preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)\.[a-z]{2,}$/i', $domain)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid domain format']);
        return;
    }

    $_SESSION['selected_domain'] = [
        'domain'      => $domain,
        'type'        => 'own',
        'selected_at' => time(),
    ];

    // Persist hosting plan if provided alongside the domain selection.
    $pid   = (int) ($_POST['pid'] ?? 0);
    $slug  = trim($_POST['slug'] ?? '');
    $cycle = trim($_POST['cycle'] ?? '');

    if ($pid > 0 && $slug !== '' && $cycle !== '') {

        $_SESSION['selected_hosting_plan'] = [
            'pid'   => $pid,
            'slug'  => $slug,
            'cycle' => $cycle,
        ];

        if (!isset($_SESSION['cart']['products'])) {
            $_SESSION['cart']['products'] = [];
        }

        $productExists = false;

        foreach ($_SESSION['cart']['products'] as $product) {
            if ((int)$product['pid'] === $pid) {
                $productExists = true;
                break;
            }
        }

        if (!$productExists) {
            $_SESSION['cart']['products'][] = [
                'pid'           => $pid,
                'billingcycle'  => $cycle,
                'domain'        => $domain,
            ];
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Own domain set', 'domain' => $domain]);
}

function setSelectedDomain() {
    $domain = $_POST['domain'] ?? '';

    if (!$domain) {
        echo json_encode(['status' => 'error', 'message' => 'No domain provided']);
        return;
    }

    $_SESSION['selected_domain'] = [
        'domain'      => $domain,
        'type'        => 'cart',
        'selected_at' => time(),
    ];

    // Persist hosting plan if provided alongside the domain selection.
    $pid   = (int) ($_POST['pid'] ?? 0);
    $slug  = trim($_POST['slug'] ?? '');
    $cycle = trim($_POST['cycle'] ?? '');

    if ($pid > 0 && $slug !== '' && $cycle !== '') {

        $_SESSION['selected_hosting_plan'] = [
            'pid'   => $pid,
            'slug'  => $slug,
            'cycle' => $cycle,
        ];

        if (!isset($_SESSION['cart']['products'])) {
            $_SESSION['cart']['products'] = [];
        }

        $productExists = false;

        foreach ($_SESSION['cart']['products'] as $product) {
            if ((int)$product['pid'] === $pid) {
                $productExists = true;
                break;
            }
        }

        if (!$productExists) {
            $_SESSION['cart']['products'][] = [
                'pid'           => $pid,
                'billingcycle'  => $cycle,
                'domain'        => $domain,
            ];
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Domain selected for cart']);
}

function getSelectedHostingPlan() {
    if (isset($_SESSION['selected_hosting_plan'])) {
        echo json_encode([
            'status' => 'success',
            'plan'   => $_SESSION['selected_hosting_plan'],
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'No hosting plan selected']);
}

/**
 * Returns the list of domain names currently in the session cart.
 * The select-domain page uses this to populate the cart-domain dropdown.
 *
 * Response shape:
 * {
 *   "status": "success",
 *   "domains": [
 *     { "domain": "example.com", "type": "register" },
 *     { "domain": "mysite.net",  "type": "transfer" }
 *   ]
 * }
 */
/* function getCartDomains() {
    $items   = $_SESSION['cart']['domains'] ?? [];
    $domains = [];

    foreach ($items as $item) {
        $domains[] = [
            'domain' => $item['domain'],
            'type'   => $item['type'],
        ];
    }

    echo json_encode([
        'status'  => 'success',
        'domains' => $domains,
    ]);
} */