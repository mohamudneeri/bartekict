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
// Helper Functions
// -------------------------------------------------------------------

function validateCartStructure() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (!isset($_SESSION['cart']['domains'])) {
        $_SESSION['cart']['domains'] = [];
    }
    if (!isset($_SESSION['cart']['products'])) {
        $_SESSION['cart']['products'] = [];
    }
}

function recalculateCartTotals() {
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
    
    return $totals;
}

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

function updateDomainFreeStatus($domain, $pid, $cycle) {
    $yearlyCycles = ['annually', 'biennially', 'triennially'];
    
    if (empty($_SESSION['cart']['domains'])) {
        return false;
    }
    
    $product = \WHMCS\Database\Capsule::table('tblproducts')
        ->where('id', $pid)
        ->select('freedomain', 'freedomaintlds')
        ->first();
    
    // Check if there's already a free domain in the cart (excluding the current domain)
    $existingFreeDomain = null;
    $existingFreeDomainIndex = null;
    foreach ($_SESSION['cart']['domains'] as $index => $domainItem) {
        if (isset($domainItem['freedomain']) && $domainItem['freedomain'] == 1) {
            if ($domainItem['domain'] !== $domain) {
                $existingFreeDomain = $domainItem['domain'];
                $existingFreeDomainIndex = $index;
                break;
            }
        }
    }
    
    $shouldBeFree = false;
    
    if ($product && $product->freedomain && in_array(strtolower($cycle), $yearlyCycles)) {
        // Parse domain TLD
        $parts = explode('.', $domain, 2);
        $tld = isset($parts[1]) ? '.' . $parts[1] : '';
        
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
        
        if (empty($allowedTlds) || in_array($domainTld, $allowedTlds, true)) {
            $shouldBeFree = true;
        }
    }
    
    // If this domain should be free but there's already another free domain,
    // don't mark it as free (keep the existing one)
    if ($shouldBeFree && $existingFreeDomain !== null) {
        $shouldBeFree = false;
        // Optionally log or trigger a notice
        error_log("Domain {$domain} qualifies for free registration but domain {$existingFreeDomain} is already free in cart");
    }
    
    // Update domain free status
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
    
    return $shouldBeFree;
}

// -------------------------------------------------------------------
// Main Functions
// -------------------------------------------------------------------

function addToCart() {
    validateCartStructure();
    
    $yearlyCycles = ['annually', 'biennially', 'triennially'];
    $domain = $_POST['domain'] ?? '';
    $type = $_POST['type'] ?? 'register';
    
    if (!$domain) {
        echo json_encode(['status' => 'error', 'message' => 'Domain is required']);
        return;
    }
    
    $parts = explode('.', $domain, 2);
    if (count($parts) < 2) {
        echo json_encode(['status' => 'invalid', 'message' => 'Invalid domain format']);
        return;
    }
    
    $sld = $parts[0];
    $tld = '.' . $parts[1];
    
    // Prevent duplicates
    foreach ($_SESSION['cart']['domains'] as $d) {
        if ($d['domain'] === $domain) {
            echo json_encode(['status' => 'exists', 'message' => 'Domain already in cart']);
            return;
        }
    }
    
    // Add domain to cart
    $_SESSION['cart']['domains'][] = [
        'type' => $type,
        'domain' => $domain,
        'sld' => $sld,
        'tld' => $tld,
        'regperiod' => 1,
        'added_at' => time()
    ];
    
    // Handle hosting plan if provided
    $pid = (int)($_POST['pid'] ?? 0);
    $slug = trim($_POST['slug'] ?? '');
    $cycle = trim($_POST['cycle'] ?? '');
    
    if ($pid > 0 && $slug !== '' && $cycle !== '') {
        // Store selected hosting plan
        $_SESSION['selected_hosting_plan'] = [
            'pid' => $pid,
            'slug' => $slug,
            'cycle' => $cycle,
            'selected_at' => time()
        ];
        
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
                'pid' => $pid,
                'billingcycle' => $cycle,
                'domain' => $domain,
                'added_at' => time()
            ];
        }
        
        // Update free domain status for this domain based on the new hosting plan
        updateDomainFreeStatus($domain, $pid, $cycle);
    } 
    // CRITICAL FIX: Check if there's already a hosting plan in cart that qualifies for free domain
    else if (!empty($_SESSION['cart']['products'])) {
        // Loop through existing hosting products to see if any qualify for free domain
        foreach ($_SESSION['cart']['products'] as $product) {
            // Check if this product offers free domain with its current billing cycle
            $productData = \WHMCS\Database\Capsule::table('tblproducts')
                ->where('id', $product['pid'])
                ->select('freedomain', 'freedomaintlds')
                ->first();
            
            if ($productData && $productData->freedomain) {
                $yearlyCycles = ['annually', 'biennially', 'triennially'];
                if (in_array(strtolower($product['billingcycle']), $yearlyCycles)) {
                    // This product qualifies for free domain
                    updateDomainFreeStatus($domain, $product['pid'], $product['billingcycle']);
                    break; // Only apply free domain from the first qualifying product
                }
            }
        }
    }
    
    // Recalculate totals
    recalculateCartTotals();
    getCartSummary();
}

function removeFromCart() {
    validateCartStructure();
    
    $type = $_POST['type'] ?? '';
    $key = $_POST['key'] ?? '';
    
    if (!$type || !$key) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing remove data'
        ]);
        return;
    }
    
    // Remove promo code when cart items change
    if (isset($_SESSION['cart']['promo'])) {
        unset($_SESSION['cart']['promo']);
    }
    
    if ($type === 'domain') {
        $domainFound = false;
        foreach ($_SESSION['cart']['domains'] as $index => $domain) {
            if ($domain['domain'] === $key) {
                unset($_SESSION['cart']['domains'][$index]);
                $domainFound = true;
                break;
            }
        }
        
        if ($domainFound) {
            $_SESSION['cart']['domains'] = array_values($_SESSION['cart']['domains']);
        }
    } elseif ($type === 'product') {
        $productFound = false;
        $productDomain = '';
        
        foreach ($_SESSION['cart']['products'] as $index => $product) {
            if ((string)$product['pid'] === (string)$key) {
                $productDomain = $product['domain'] ?? '';
                unset($_SESSION['cart']['products'][$index]);
                $productFound = true;
                break;
            }
        }
        
        if ($productFound) {
            $_SESSION['cart']['products'] = array_values($_SESSION['cart']['products']);
            
            // Remove selected hosting plan
            unset($_SESSION['selected_hosting_plan']);
            
            // Update free domain status if domain exists
            if ($productDomain && !empty($_SESSION['cart']['domains'])) {
                foreach ($_SESSION['cart']['domains'] as &$domainItem) {
                    if ($domainItem['domain'] === $productDomain) {
                        unset($domainItem['freedomain']);
                        break;
                    }
                }
            }
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid remove type'
        ]);
        return;
    }
    
    // Recalculate totals
    $totals = recalculateCartTotals();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Item removed from cart',
        'cart_count' => $totals['items_count']
    ]);
}

function clearCart() {
    // Clear ALL cart data, not just domains
    $_SESSION['cart'] = [
        'domains' => [],
        'products' => [],
        'totals' => [
            'subtotal' => 0,
            'discount' => 0,
            'grand_total' => 0,
            'items_count' => 0
        ]
    ];
    
    // Clear related session data
    unset($_SESSION['selected_hosting_plan']);
    unset($_SESSION['selected_domain']);
    unset($_SESSION['cart']['promo']);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Cart cleared successfully',
        'count' => 0
    ]);
}

function getCartItems() {
    validateCartStructure();
    
    echo json_encode([
        'status' => 'success',
        'domains' => $_SESSION['cart']['domains'],
        'products' => $_SESSION['cart']['products'],
        'totals' => $_SESSION['cart']['totals'] ?? recalculateCartTotals()
    ]);
}

function getCartCount() {
    validateCartStructure();
    $totals = $_SESSION['cart']['totals'] ?? recalculateCartTotals();
    
    echo json_encode([
        'status' => 'success',
        'count' => $totals['items_count']
    ]);
}

function getCartSummary() {
    validateCartStructure();
    $totals = recalculateCartTotals();
    
    $currency = \WHMCS\Database\Capsule::table('tblcurrencies')
        ->where('default', 1)
        ->first();
    
    if (!$currency) {
        $currency = \WHMCS\Database\Capsule::table('tblcurrencies')->first();
    }
    
    $prefix = $currency->prefix ?? '$';
    $suffix = $currency->suffix ?? '';
    $decimals = $currency->decimals ?? 2;
    
    echo json_encode([
        'status' => 'success',
        'count' => $totals['items_count'],
        'subtotal' => $totals['subtotal'],
        'subtotalFormatted' => $prefix . number_format($totals['subtotal'], $decimals) . $suffix,
        'discount' => $totals['discount'],
        'discountFormatted' => $prefix . number_format($totals['discount'], $decimals) . $suffix,
        'total' => $totals['grand_total'],
        'totalFormatted' => $prefix . number_format($totals['grand_total'], $decimals) . $suffix,
    ]);
}

function selectCartDomain() {
    $domain = $_POST['domain'] ?? '';
    
    if (!$domain) {
        echo json_encode(['status' => 'error', 'message' => 'No domain selected']);
        return;
    }
    
    // Verify domain exists in cart
    $domainExists = false;
    foreach ($_SESSION['cart']['domains'] as $d) {
        if ($d['domain'] === $domain) {
            $domainExists = true;
            break;
        }
    }
    
    if (!$domainExists) {
        echo json_encode(['status' => 'error', 'message' => 'Domain not found in cart']);
        return;
    }
    
    $_SESSION['selected_domain'] = [
        'domain' => $domain,
        'type' => 'cart',
        'selected_at' => time(),
    ];
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Domain selected',
        'domain' => $domain
    ]);
}

function setOwnDomain() {
    validateCartStructure();
    
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
        'domain' => $domain,
        'type' => 'own',
        'selected_at' => time(),
    ];
    
    // Persist hosting plan if provided
    $pid = (int)($_POST['pid'] ?? 0);
    $slug = trim($_POST['slug'] ?? '');
    $cycle = trim($_POST['cycle'] ?? '');
    
    if ($pid > 0 && $slug !== '' && $cycle !== '') {
        $_SESSION['selected_hosting_plan'] = [
            'pid' => $pid,
            'slug' => $slug,
            'cycle' => $cycle,
            'selected_at' => time()
        ];
        
        // Prevent duplicate hosting product
        $productExists = false;
        foreach ($_SESSION['cart']['products'] as $product) {
            if ((int)$product['pid'] === $pid) {
                $productExists = true;
                break;
            }
        }
        
        if (!$productExists) {
            $_SESSION['cart']['products'][] = [
                'pid' => $pid,
                'billingcycle' => $cycle,
                'domain' => $domain,
                'added_at' => time()
            ];
        }
        
        // Update free domain status
        updateDomainFreeStatus($domain, $pid, $cycle);
        recalculateCartTotals();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Own domain set',
        'domain' => $domain
    ]);
}

function setSelectedDomain() {
    validateCartStructure();
    
    $yearlyCycles = ['annually', 'biennially', 'triennially'];
    $domain = $_POST['domain'] ?? '';
    
    if (!$domain) {
        echo json_encode(['status' => 'error', 'message' => 'No domain provided']);
        return;
    }
    
    $_SESSION['selected_domain'] = [
        'domain' => $domain,
        'type' => 'cart',
        'selected_at' => time(),
    ];
    
    // Persist hosting plan if provided
    $pid = (int)($_POST['pid'] ?? 0);
    $slug = trim($_POST['slug'] ?? '');
    $cycle = trim($_POST['cycle'] ?? '');
    
    if ($pid > 0 && $slug !== '' && $cycle !== '') {
        $_SESSION['selected_hosting_plan'] = [
            'pid' => $pid,
            'slug' => $slug,
            'cycle' => $cycle,
            'selected_at' => time()
        ];
        
        // Prevent duplicate hosting product
        $productExists = false;
        foreach ($_SESSION['cart']['products'] as $product) {
            if ((int)$product['pid'] === $pid) {
                $productExists = true;
                break;
            }
        }
        
        if (!$productExists) {
            $_SESSION['cart']['products'][] = [
                'pid' => $pid,
                'billingcycle' => $cycle,
                'domain' => $domain,
                'added_at' => time()
            ];
        }
        
        // Update free domain status
        updateDomainFreeStatus($domain, $pid, $cycle);
        recalculateCartTotals();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Domain selected for cart'
    ]);
}

function getSelectedHostingPlan() {
    if (isset($_SESSION['selected_hosting_plan'])) {
        echo json_encode([
            'status' => 'success',
            'plan' => $_SESSION['selected_hosting_plan'],
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No hosting plan selected'
        ]);
    }
}

function changeBillingCycle() {
    validateCartStructure();
    
    $yearlyCycles = ['annually', 'biennially', 'triennially'];
    $productKey = $_POST['key'] ?? '';
    $newCycle = trim($_POST['cycle'] ?? '');
    
    if (!$productKey || !$newCycle) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing product key or billing cycle'
        ]);
        return;
    }
    
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
    
    // Update free domain status
    if (!empty($productData['domain'])) {
        updateDomainFreeStatus($productData['domain'], $productData['pid'], $newCycle);
    }
    
    // Update selected hosting plan
    if (isset($_SESSION['selected_hosting_plan']) && 
        (int)$_SESSION['selected_hosting_plan']['pid'] === (int)$productData['pid']) {
        $_SESSION['selected_hosting_plan']['cycle'] = $newCycle;
    }
    
    // Recalculate totals
    $totals = recalculateCartTotals();
    
    // Get formatted price
    $newPrice = getProductPrice($productData['pid'], $newCycle);
    $currency = \WHMCS\Database\Capsule::table('tblcurrencies')
        ->where('default', 1)
        ->first();
    
    if (!$currency) {
        $currency = \WHMCS\Database\Capsule::table('tblcurrencies')->first();
    }
    
    $prefix = $currency->prefix ?? '$';
    $suffix = $currency->suffix ?? '';
    $decimals = $currency->decimals ?? 2;
    $priceFormatted = $prefix . number_format($newPrice, $decimals) . $suffix;
    
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
    validateCartStructure();
    
    $domainKey = $_POST['key'] ?? '';
    $newYears = (int)($_POST['years'] ?? 0);
    
    if (!$domainKey || $newYears < 1 || $newYears > 10) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid domain or registration period'
        ]);
        return;
    }
    
    // Find and update the domain
    $domainFound = false;
    $domainData = null;
    
    foreach ($_SESSION['cart']['domains'] as &$domain) {
        if ($domain['domain'] === $domainKey) {
            $domain['regperiod'] = $newYears;
            $domainFound = true;
            $domainData = $domain;
            break;
        }
    }
    
    if (!$domainFound) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Domain not found in cart'
        ]);
        return;
    }
    
    // Recalculate totals
    $totals = recalculateCartTotals();
    
    // Get pricing
    $totalPrice = getDomainPrice($domainData);
    $pricePerYear = $totalPrice / $newYears;
    
    $currency = \WHMCS\Database\Capsule::table('tblcurrencies')
        ->where('default', 1)
        ->first();
    
    if (!$currency) {
        $currency = \WHMCS\Database\Capsule::table('tblcurrencies')->first();
    }
    
    $prefix = $currency->prefix ?? '$';
    $suffix = $currency->suffix ?? '';
    $decimals = $currency->decimals ?? 2;
    $priceFormatted = $prefix . number_format($totalPrice, $decimals) . $suffix;
    $renewPriceFormatted = $prefix . number_format($pricePerYear, $decimals) . $suffix;
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Domain registration period updated successfully',
        'years' => $newYears,
        'price' => $priceFormatted,
        'priceRaw' => $totalPrice,
        'renewPrice' => $renewPriceFormatted
    ]);
}

function applyPromoCode() {
    validateCartStructure();
    
    $promoCode = trim($_POST['promo_code'] ?? '');
    
    if (empty($promoCode)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter a promo code'
        ]);
        return;
    }
    
    $totals = recalculateCartTotals();
    
    if ($totals['subtotal'] <= 0) {
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
    
    // Check expiration
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
        foreach ($_SESSION['cart']['products'] as $product) {
            if (in_array($product['pid'], $appliesTo)) {
                $foundEligible = true;
                break;
            }
        }
        
        // Check domains
        if (!$foundEligible) {
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
    
    // Store promo code
    $_SESSION['cart']['promo'] = $promo->code;
    
    // Recalculate totals
    recalculateCartTotals();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Promo code applied successfully!'
    ]);
}

function removePromoCode() {
    validateCartStructure();
    
    if (isset($_SESSION['cart']['promo'])) {
        unset($_SESSION['cart']['promo']);
        recalculateCartTotals();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Promo code removed successfully',
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No promo code applied'
        ]);
    }
}