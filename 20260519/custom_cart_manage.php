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

    case 'change_billing_cycle':
        changeBillingCycle();
        break;
    
    case 'change_domain_years':
        changeDomainYears();
        break;
    
    case 'apply_promo':
        applyPromoCode();
        break;
    
    case 'remove_promo':
        removePromoCode();
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

// -------------------------------------------------------------------
// Functions
// -------------------------------------------------------------------

function addToCart() {
    $yearlyCycles = ['annually', 'biennially', 'triennially'];

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
                'pid'          => $pid,
                'billingcycle' => $cycle,
                'domain'       => $domain,
            ];
        }

        // Check if this product includes a free domain
        $planFreeDomain = false;
        $product = \WHMCS\Database\Capsule::table('tblproducts')
            ->where('id', $pid)
            ->select('freedomain', 'freedomaintlds')
            ->first();

        if ($product && $product->freedomain && in_array(strtolower($cycle), $yearlyCycles)) {
            // Check if the domain's TLD is in the allowed free TLDs list
            $allowedTldsRaw = $product->freedomaintlds ?? '';
            $allowedTlds = [];
            
            if (!empty($allowedTldsRaw)) {
                $allowedTlds = array_filter(array_map(
                    function ($t) {
                        return strtolower(ltrim(trim($t), '.'));
                    },
                    explode(',', $allowedTldsRaw)
                ));
            }
            
            $domainTld = strtolower(ltrim($tld, '.'));
            
            // If no TLD restriction exists, allow all
            if (
                empty($allowedTlds) ||
                in_array($domainTld, $allowedTlds, true)
            ) {
                $planFreeDomain = true;
            }
        }

        // Mark the domain entry as free if applicable
        if ($planFreeDomain) {
            foreach ($_SESSION['cart']['domains'] as &$d) {
                if ($d['domain'] === $domain) {
                    $d['freedomain'] = 1;
                    break;
                }
            }
            unset($d);
        }
    }
    getCartSummary();
}

function changeBillingCycle() {
    $yearlyCycles = ['annually', 'biennially', 'triennially'];
    
    $productKey = $_POST['key'] ?? '';
    $newCycle = trim($_POST['cycle'] ?? '');

    // Validation
    if (!$productKey || !$newCycle) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing product key or billing cycle'
        ]);
        return;
    }

    // Validate cycle is valid
    $validCycles = ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'];
    if (!in_array($newCycle, $validCycles)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid billing cycle selected'
        ]);
        return;
    }

    // Update product in session cart
    $productFound = false;
    $productData = null;
    foreach ($_SESSION['cart']['products'] as &$product) {
        if ((string)$product['pid'] === (string)$productKey) {
            $product['billingcycle'] = $newCycle;
            $productFound = true;
            $productData = $product;
            break;
        }
    }

    if (!$productFound) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Product not found in cart'
        ]);
        return;
    }

    // Fetch product details
    $pid = (int)$productData['pid'];
    $productInfo = \WHMCS\Database\Capsule::table('tblproducts')
        ->where('id', $pid)
        ->select('id', 'name', 'freedomain', 'freedomaintlds')
        ->first();

    // Get pricing for new cycle - FIXED: Use the cycle column name directly
    $pricing = \WHMCS\Database\Capsule::table('tblpricing')
        ->where('type', 'product')
        ->where('relid', $pid)
        ->first();

    // Get the price for the selected cycle
    $newPrice = 0;
    if ($pricing && isset($pricing->$newCycle)) {
        $newPrice = (float)$pricing->$newCycle;
    }

    // Format price
    $currency = \WHMCS\Database\Capsule::table('tblcurrencies')
        ->where('default', 1)
        ->first();
    
    if (!$currency) {
        $currency = \WHMCS\Database\Capsule::table('tblcurrencies')
            ->first();
    }
    
    $prefix = $currency->prefix ?? '$';
    $suffix = $currency->suffix ?? '';
    $decimals = $currency->decimals ?? 2;
    $priceFormatted = $prefix . number_format($newPrice, $decimals) . $suffix;

    // Handle free domain eligibility
    $domain = $productData['domain'] ?? '';
    if ($domain && !empty($_SESSION['cart']['domains'])) {
        $shouldBeFree = false;

        // Free domain only with yearly cycles
        if ($productInfo && $productInfo->freedomain && in_array($newCycle, $yearlyCycles)) {
            // Check TLD restrictions if applicable
            $parts = explode('.', $domain, 2);
            $tld = '.' . ($parts[1] ?? '');
            
            $allowedTldsRaw = $productInfo->freedomaintlds ?? '';
            $allowedTlds = [];
            
            if (!empty($allowedTldsRaw)) {
                $allowedTlds = array_filter(array_map(
                    function ($t) {
                        return strtolower(ltrim(trim($t), '.'));
                    },
                    explode(',', $allowedTldsRaw)
                ));
            }
            
            $domainTld = strtolower(ltrim($tld, '.'));
            
            // If no TLD restriction exists, allow all
            if (empty($allowedTlds) || in_array($domainTld, $allowedTlds, true)) {
                $shouldBeFree = true;
            }
        }

        // Update domain free flag
        foreach ($_SESSION['cart']['domains'] as &$domainItem) {
            if ($domainItem['domain'] === $domain) {
                if ($shouldBeFree) {
                    $domainItem['freedomain'] = 1;
                } else {
                    unset($domainItem['freedomain']);
                }
                break;
            }
        }
    }

    // Update selected hosting plan session
    if (isset($_SESSION['selected_hosting_plan']) && 
        (int)$_SESSION['selected_hosting_plan']['pid'] === $pid) {
        $_SESSION['selected_hosting_plan']['cycle'] = $newCycle;
    }

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Billing cycle updated successfully',
        'cycle' => $newCycle,
        'price' => $priceFormatted,
        'priceRaw' => $newPrice,
        'renewPrice' => $priceFormatted
    ]);
}

function changeDomainYears() {
    $domainKey = $_POST['key'] ?? '';
    $newYears = (int)($_POST['years'] ?? 0);
    
    // Validation
    if (!$domainKey || $newYears < 1 || $newYears > 10) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid domain or registration period'
        ]);
        return;
    }
    
    // Find and update the domain in session cart
    $domainFound = false;
    $domainData = null;
    
    if (!empty($_SESSION['cart']['domains'])) {
        foreach ($_SESSION['cart']['domains'] as &$domain) {
            if ($domain['domain'] === $domainKey) {
                $domain['regperiod'] = $newYears;
                $domainFound = true;
                $domainData = $domain;
                break;
            }
        }
    }
    
    if (!$domainFound) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Domain not found in cart'
        ]);
        return;
    }
    
    // Get pricing for the domain
    $tld = ltrim($domainData['tld'], '.');
    $type = $domainData['type'] ?? 'register';
    
    // Fetch pricing from WHMCS
    $pricing = localAPI('GetTLDPricing');
    $currencies = localAPI('GetCurrencies', ['id' => 1]);
    
    $pricePerYear = 0;
    if (isset($pricing['pricing'][$tld][$type])) {
        $priceData = $pricing['pricing'][$tld][$type];
        if (is_array($priceData)) {
            $pricePerYear = (float)($priceData[$newYears] ?? $priceData[1] ?? reset($priceData) ?? 0);
        } else {
            $pricePerYear = (float)$priceData;
        }
    }
    
    // Calculate total price
    $totalPrice = $pricePerYear * $newYears;
    
    // Format price
    $prefix = $currencies['currencies'][0]['prefix'] ?? '$';
    $suffix = $currencies['currencies'][0]['suffix'] ?? '';
    $decimals = $currencies['currencies'][0]['decimals'] ?? 2;
    $priceFormatted = $prefix . number_format($totalPrice, $decimals) . $suffix;
    $renewPriceFormatted = $prefix . number_format($pricePerYear, $decimals) . $suffix;
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Domain registration period updated successfully',
        'years' => $newYears,
        'price' => $priceFormatted,
        'priceRaw' => $totalPrice,
        'renewPrice' => $renewPriceFormatted
    ]);
}

function removeFromCart() {
    $type = $_POST['type'] ?? '';
    $key  = $_POST['key'] ?? '';

    if (!$type || !$key) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Missing remove data'
        ]);
        return;
    }
    
    unset($_SESSION['cart']['promo']);

    // REMOVE DOMAIN

    if ($type === 'domain') {

        if (!empty($_SESSION['cart']['domains'])) {

            foreach ($_SESSION['cart']['domains'] as $index => $domain) {

                if ($domain['domain'] === $key) {
                    unset($_SESSION['cart']['domains'][$index]);
                }
            }

            $_SESSION['cart']['domains'] = array_values(
                $_SESSION['cart']['domains']
            );
        }
        
        session_write_close();

        echo json_encode([
            'status' => 'success'
        ]);

        return;
    }

    // REMOVE PRODUCT

    if ($type === 'product') {

        if (!empty($_SESSION['cart']['products'])) {

            foreach ($_SESSION['cart']['products'] as $index => $product) {


                if ((string)$product['pid'] === (string)$key) {

                    $productDomain = $product['domain'] ?? '';

                    // Remove hosting product
                    unset($_SESSION['cart']['products'][$index]);

                    // Remove selected hosting plan
                    unset($_SESSION['selected_hosting_plan']);

                    if (
                        $productDomain &&
                        !empty($_SESSION['cart']['domains'])
                    ) {

                        foreach ($_SESSION['cart']['domains'] as &$domainItem) {

                            if (
                                $domainItem['domain'] === $productDomain
                            ) {

                                // Remove free domain flag
                                unset($domainItem['freedomain']);

                                break;
                            }
                        }

                        unset($domainItem);

                        // Clean null items if used
                        $_SESSION['cart']['domains'] = array_values(
                            array_filter(
                                $_SESSION['cart']['domains']
                            )
                        );
                    }

                    break;
                }
            }

            $_SESSION['cart']['products'] = array_values(
                $_SESSION['cart']['products']
            );
        }
    
        session_write_close();

        echo json_encode([
            'status' => 'success'
        ]);

        return;
    }
    
    session_write_close();
    
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid remove type'
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
    $yearlyCycles = ['annually', 'biennially', 'triennially'];

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
                'pid'          => $pid,
                'billingcycle' => $cycle,
                'domain'       => $domain,
            ];
        }

        // Check if this product includes a free domain
        $planFreeDomain = false;
        $parts = explode('.', $domain, 2);
        $tld = '.' . ($parts[1] ?? '');

        $product = \WHMCS\Database\Capsule::table('tblproducts')
            ->where('id', $pid)
            ->select('freedomain', 'freedomaintlds')
            ->first();

        if ($product && $product->freedomain && in_array(strtolower($cycle), $yearlyCycles)) {
            // Check if the domain's TLD is in the allowed free TLDs list
            $allowedTldsRaw = $product->freedomaintlds ?? '';
            $allowedTlds = [];
            
            if (!empty($allowedTldsRaw)) {
                $allowedTlds = array_filter(array_map(
                    function ($t) {
                        return strtolower(ltrim(trim($t), '.'));
                    },
                    explode(',', $allowedTldsRaw)
                ));
            }
            
            $domainTld = strtolower(ltrim($tld, '.'));
            
            // If no TLD restriction exists, allow all
            if (
                empty($allowedTlds) ||
                in_array($domainTld, $allowedTlds, true)
            ) {
                $planFreeDomain = true;
            }
        }

        // Mark the domain entry as free if applicable
        if ($planFreeDomain) {
            foreach ($_SESSION['cart']['domains'] as &$d) {
                if ($d['domain'] === $domain) {
                    $d['freedomain'] = 1;
                    break;
                }
            }
            unset($d);
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Own domain set', 'domain' => $domain]);
}

function setSelectedDomain() {
    $yearlyCycles = ['annually', 'biennially', 'triennially'];

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
                'pid'          => $pid,
                'billingcycle' => $cycle,
                'domain'       => $domain,
            ];
        }

        // Check if this product includes a free domain
        $planFreeDomain = false;
        $parts = explode('.', $domain, 2);
        $tld = '.' . ($parts[1] ?? '');
        
        $product = \WHMCS\Database\Capsule::table('tblproducts')
            ->where('id', $pid)
            ->select('freedomain', 'freedomaintlds')
            ->first();

        if ($product && $product->freedomain && in_array(strtolower($cycle), $yearlyCycles)) {
            // Check if the domain's TLD is in the allowed free TLDs list
            $allowedTldsRaw = $product->freedomaintlds ?? '';
            $allowedTlds = [];
            
            if (!empty($allowedTldsRaw)) {
                $allowedTlds = array_filter(array_map(
                    function ($t) {
                        return strtolower(ltrim(trim($t), '.'));
                    },
                    explode(',', $allowedTldsRaw)
                ));
            }
            
            $domainTld = strtolower(ltrim($tld, '.'));
            
            // If no TLD restriction exists, allow all
            if (
                empty($allowedTlds) ||
                in_array($domainTld, $allowedTlds, true)
            ) {
                $planFreeDomain = true;
            }
        }

        // Mark the domain entry as free if applicable
        if ($planFreeDomain) {
            foreach ($_SESSION['cart']['domains'] as &$d) {
                if ($d['domain'] === $domain) {
                    $d['freedomain'] = 1;
                    break;
                }
            }
            unset($d);
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

function applyPromoCode() {
    $promoCode = trim($_POST['promo_code'] ?? '');
    
    if (empty($promoCode)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter a promo code'
        ]);
        return;
    }
    
    // Get current cart totals first
    $cartData = getCartTotals();
    $subtotal = $cartData['subtotal'];
    
    // Check if cart is empty
    if ($subtotal <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot apply promo code to empty cart'
        ]);
        return;
    }
    
    // Find promo code
    $promo = \WHMCS\Database\Capsule::table('tblpromotions')
    ->whereRaw('LOWER(code) = ?', [strtolower($promoCode)])
    ->first();
    
    if (!$promo) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or expired promo code'
        ]);
        return;
    }
    
    // Check expiration - handle 0000-00-00 as no expiration
    $today = date('Y-m-d');
    $expirationDate = $promo->expirationdate;
    
    if ($expirationDate && $expirationDate != '0000-00-00' && $expirationDate < $today) {
        echo json_encode([
            'status' => 'error',
            'message' => 'This promo code has expired'
        ]);
        return;
    }
    
    // Check new signups only
    if ($promo->newsignups == 1) {
        $userId = $_SESSION['uid'] ?? 0;
        
        if ($userId > 0) {
            $activeServices = \WHMCS\Database\Capsule::table('tblhosting')
                ->where('userid', $userId)
                ->whereIn('domainstatus', ['Active', 'Pending', 'Suspended'])
                ->exists();
            
            if ($activeServices) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'This promo code is for new customers only'
                ]);
                return;
            }
        }
    }
    
    // Check applies to
    if ($promo->appliesto) {
        $appliesTo = explode(',', $promo->appliesto);
        $foundEligible = false;
        
        // Check products
        if (!empty($_SESSION['cart']['products'])) {
            foreach ($_SESSION['cart']['products'] as $product) {
                if (in_array($product['pid'], $appliesTo)) {
                    $foundEligible = true;
                    break;
                }
            }
        }
        
        // Check domains
        if (!$foundEligible && !empty($_SESSION['cart']['domains'])) {
            foreach ($_SESSION['cart']['domains'] as $domain) {
                $tld = ltrim($domain['tld'], '.');
                $domainCheck = 'D.' . $tld;
                if (in_array($domainCheck, $appliesTo)) {
                    $foundEligible = true;
                    break;
                }
            }
        }
        
        if (!$foundEligible) {
            echo json_encode([
                'status' => 'error',
                'message' => 'This promo code does not apply to items in your cart'
            ]);
            return;
        }
    }
    
    // Calculate discount
    $discount = 0;
    $discountType = $promo->type;
    $discountValue = (float)$promo->value;
    
    if ($discountType == 'Percentage' || $discountType == 'percentage') {
        $discount = $subtotal * ($discountValue / 100);
    } else {
        // Fixed amount
        $discount = min($discountValue, $subtotal);
    }
    
    // Store the string (WHMCS Standard way):
    $_SESSION['cart']['promo'] = $promo->code;
    
    // Return success
    echo json_encode([
        'status' => 'success',
        'message' => 'Promo code applied!'
    ]);
}

function removePromoCode() {
    if (isset($_SESSION['cart']['promo'])) {
        unset($_SESSION['cart']['promo']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Promo code removed',
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No promo code found'
        ]);
    }
}

// Helper function to get cart totals
function getCartTotals() {
    $subtotal = 0;
    $totalSavings = 0;
    
    // Calculate product totals
    if (!empty($_SESSION['cart']['products'])) {
        foreach ($_SESSION['cart']['products'] as $product) {
            $pid = $product['pid'];
            $cycle = $product['billingcycle'];
            
            $pricing = \WHMCS\Database\Capsule::table('tblpricing')
                ->where('type', 'product')
                ->where('relid', $pid)
                ->first();
            
            if ($pricing && isset($pricing->$cycle)) {
                $price = (float)$pricing->$cycle;
                $subtotal += $price;
            }
        }
    }
    
    // Calculate domain totals
    if (!empty($_SESSION['cart']['domains'])) {
        $pricing = localAPI('GetTLDPricing');
        
        foreach ($_SESSION['cart']['domains'] as $domain) {
            $tld = ltrim($domain['tld'], '.');
            $type = $domain['type'] ?? 'register';
            $years = $domain['regperiod'] ?? 1;
            
            // Skip free domains
            if (isset($domain['freedomain']) && $domain['freedomain'] == 1) {
                continue;
            }
            
            $pricePerYear = 0;
            if (isset($pricing['pricing'][$tld][$type])) {
                $priceData = $pricing['pricing'][$tld][$type];
                if (is_array($priceData)) {
                    $pricePerYear = (float)($priceData[$years] ?? $priceData[1] ?? 0);
                } else {
                    $pricePerYear = (float)$priceData;
                }
                $subtotal += $pricePerYear * $years;
            }
        }
    }
    
    // Apply promo discount - FIXED: Handle string format
    $grandTotal = $subtotal;
    $promoDiscount = 0;
    
    if (isset($_SESSION['cart']['promo'])) {
        $promoCode = $_SESSION['cart']['promo'];
        
        // Handle both string and array formats (for backward compatibility)
        if (is_array($promoCode)) {
            // Old array format
            $promoDiscount = $promoCode['discount'] ?? 0;
        } else {
            // New string format - need to calculate discount
            $promo = \WHMCS\Database\Capsule::table('tblpromotions')
                ->whereRaw('LOWER(code) = ?', [strtolower($promoCode)])
                ->first();
            
            if ($promo) {
                if ($promo->type == 'fixed') {
                    $promoDiscount = floatval($promo->value);
                } elseif ($promo->type == 'Percentage' || $promo->type == 'percentage') {
                    $promoDiscount = $subtotal * (floatval($promo->value) / 100);
                }
                // Ensure discount doesn't exceed subtotal
                if ($promoDiscount > $subtotal) $promoDiscount = $subtotal;
            }
        }
        
        $grandTotal = $subtotal - $promoDiscount;
        if ($grandTotal < 0) $grandTotal = 0;
    }
    
    return [
        'subtotal' => $subtotal,
        'grand_total' => $grandTotal,
        'discount' => $promoDiscount,
        'total_savings' => $totalSavings
    ];
}