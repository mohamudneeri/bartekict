<?php

define("CLIENTAREA", true);

require __DIR__ . '/init.php';

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

// use WHMCS\Config\Setting;
use WHMCS\Utility\Country;

$ca = new ClientArea();
$ca->setPageTitle('Cart - BARTEK ICT');
$ca->initPage();

// ───────────────────────────────
// CART DATA
// ───────────────────────────────

$cartDomains  = $_SESSION['cart']['domains']  ?? [];
$cartProducts = $_SESSION['cart']['products'] ?? [];

$hasItems = !empty($cartDomains) || !empty($cartProducts);

// ───────────────────────────────
// AUTH STATE
// ───────────────────────────────

$isLoggedIn = isset($_SESSION['uid']) && (int) $_SESSION['uid'] > 0;
$loggedUser = [];

if ($isLoggedIn) {

    $clientId = (int) $_SESSION['uid'];

    $client = Capsule::table('tblclients')
        ->where('id', $clientId)
        ->select(
            'firstname',
            'lastname',
            'email',
            'address1',
            'city',
            'state',
            'country'
        )
        ->first();

    if ($client) {
        $countries = new Country();
        $countryList = $countries->getCountryNameArray();
    
        $countryCode = strtoupper($client->country);
    
        $countryName = $countryList[$countryCode] ?? $countryCode;
    
        $loggedUser = [
            'fullName'  => trim($client->firstname . ' ' . $client->lastname),
            'email'     => $client->email,
            'address'   => $client->address1,
            'city'      => $client->city,
            'state'     => $client->state,
            'country'   => $countryName,
        ];
    }
}


function getProductDetails($pid, $billingcycle) {
    try {
        $product = Capsule::table('tblproducts')
            ->where('id', $pid)
            ->select('name')
            ->first();
        
        if (!$product) {
            return null;
        }
        
        // Get pricing for the selected billing cycle
        $pricing = Capsule::table('tblpricing')
            ->where('type', 'product')
            ->where('relid', $pid)
            ->first();
        
        $price = 0;
        $renewPrice = 0;
        
        if ($pricing) {
            switch ($billingcycle) {
                case 'monthly':
                    $price = $pricing->monthly ?? 0;
                    $renewPrice = $pricing->monthly ?? 0;
                    break;
                case 'quarterly':
                    $price = $pricing->quarterly ?? 0;
                    $renewPrice = $pricing->quarterly ?? 0;
                    break;
                case 'annually':
                    $price = $pricing->annually ?? 0;
                    $renewPrice = $pricing->annually ?? 0;
                    break;
                case 'biennially':
                    $price = $pricing->biennially ?? 0;
                    $renewPrice = $pricing->biennially ?? 0;
                    break;
                default:
                    $price = $pricing->monthly ?? 0;
                    $renewPrice = $pricing->monthly ?? 0;
            }
        }
        
        return [
            'planName' => $product->name,
            'price' => number_format($price, 2),
            'renewPrice' => number_format($renewPrice, 2),
            'billingcycle' => $billingcycle
        ];
    } catch (Exception $e) {
        return null;
    }
}

function getDomainDetails($domainName, $regperiod = 1) {
    try {
        // Get TLD from domain
        $parts = explode('.', $domainName);
        $tld = '.' . end($parts);
        $cleanTld = ltrim($tld, '.');
        
        // Get domain pricing
        $pricing = localAPI('GetTLDPricing');
        
        $price = 0;
        $renewPrice = 0;
        
        if (isset($pricing['pricing'][$cleanTld])) {
            // Get register prices
            $regPrices = $pricing['pricing'][$cleanTld]['register'] ?? [];
            $renewPrices = $pricing['pricing'][$cleanTld]['renew'] ?? [];
            
            // Get first currency price (simplified - use default currency)
            if (is_array($regPrices)) {
                $price = reset($regPrices);
            } else {
                $price = $regPrices;
            }
            
            if (is_array($renewPrices)) {
                $renewPrice = reset($renewPrices);
            } else {
                $renewPrice = $renewPrices;
            }
            
            // Calculate total for multiple years
            if ($regperiod > 1 && $price) {
                $price = $price * $regperiod;
            }
        }
        
        return [
            'price' => number_format($price, 2),
            'renewPrice' => number_format($renewPrice, 2),
            'years' => $regperiod,
            'tld' => $tld
        ];
    } catch (Exception $e) {
        return [
            'price' => '0.00',
            'renewPrice' => '0.00',
            'years' => $regperiod
        ];
    }
}

// Enrich cart products and calculate subtotal
$enrichedProducts = [];
$subtotal = 0;

foreach ($cartProducts as $product) {
    $details = getProductDetails($product['pid'], $product['billingcycle']);
    if ($details) {
        $enrichedProduct = array_merge($product, $details);
        $enrichedProducts[] = $enrichedProduct;
        $subtotal += floatval($details['price']);
    } else {
        // Fallback data if product not found
        $enrichedProduct = array_merge($product, [
            'planName' => '',
            'price' => '0.00',
            'renewPrice' => '0.00'
        ]);
        $enrichedProducts[] = $enrichedProduct;
        $subtotal += 0;
    }
}

// Enrich cart domains and add to subtotal
$enrichedDomains = [];
foreach ($cartDomains as $domain) {
    $regperiod = $domain['regperiod'] ?? 1;
    $details = getDomainDetails($domain['domain'], $regperiod);
    
    // Determine domain action type
    $actionType = $domain['type'] ?? 'register';
    $actionText = 'Domain Registration';
    $isFree     = false;

    if ($actionType == 'transfer') {
        $actionText = 'Domain Transfer';
        // For transfers, get transfer price instead
        $cleanTld = ltrim($details['tld'] ?? '', '.');
        $pricing = localAPI('GetTLDPricing');

        if (isset($pricing['pricing'][$cleanTld]['transfer'])) {
            $transPrices = $pricing['pricing'][$cleanTld]['transfer'];
            $transferPrice = is_array($transPrices) ? reset($transPrices) : $transPrices;
            $details['price'] = number_format($transferPrice, 2);
        }

        // Free transfer bundled with a hosting plan
        if (!empty($domain['freedomain'])) {
            $details['price'] = '0.00';
            $isFree = true;
        }
    } elseif ($actionType == 'existing') {
        $actionText = 'Existing Domain';
        // Existing domains don't have a price
        $details['price'] = '0.00';
    } elseif ($actionType == 'free') {
        // Explicitly marked as a free domain bundled with a hosting plan
        $actionText = 'Free Domain Registration';
        $details['price'] = '0.00';
        $isFree = true;
    } elseif (!empty($domain['freedomain'])) {
        // register type but flagged free by the hosting product
        $details['price'] = '0.00';
        $isFree = true;
    }

    $enrichedDomain = array_merge($domain, $details, [
        'actionType' => $actionType,
        'actionText' => $actionText,
        'isFree'     => $isFree,
    ]);
    $enrichedDomains[] = $enrichedDomain;
    $subtotal += floatval($details['price']);
}

// Get applied promo code
$promoCode = null;
$promoDiscount = 0;

// Check the session for a promo code
if (isset($_SESSION['cart']['promo'])) {
    $promoCode = $_SESSION['cart']['promo'] ?? null;
}

// 2. Re-Calculate the discount from scratch using the DB

if ($promoCode) {
    // Fetch fresh data from DB to ensure the promo still applies
    $promo = Capsule::table('tblpromotions')
        ->whereRaw('LOWER(code) = ?', [strtolower($promoCode)])
        ->first();

    if ($promo) {
        // Check expiration (handle '0000-00-00')
        $today = date('Y-m-d');
        if ($promo->expirationdate && $promo->expirationdate != '0000-00-00' && $promo->expirationdate < $today) {
            // Expired: clear it from session
            unset($_SESSION['cart']['promo']);
            $promoCode = null;
        } else {
            // Calculate discount based on CURRENT subtotal ($subtotal)
            if ($promo->type == 'fixed' || $promo->type == 'Fixed Amount') {
                $promoDiscount = floatval($promo->value);
            } elseif ($promo->type == 'Percentage' || $promo->type == 'percentage') {
                $promoDiscount = $subtotal * (floatval($promo->value) / 100);
            }
            // Ensure discount doesn't exceed subtotal
            if ($promoDiscount > $subtotal) $promoDiscount = $subtotal;
        }
    } else {
        // Promo code not found in DB anymore, remove it
        unset($_SESSION['cart']['promo']);
        $promoCode = null;
    }
    
}

// ───────────────────────────────
// TOTALS CALCULATION
// ───────────────────────────────

$grandTotal = $subtotal - $promoDiscount;
if ($grandTotal < 0) $grandTotal = 0;

$countries = [];
$countryHelper = new Country();
$countryList   = $countryHelper->getCountryNameArray();

foreach ($countryList as $countryCode => $countryName) {
    $countries[] = [
        'code' => $countryCode,
        'name' => $countryName,
    ];
}


// print_r($isLoggedIn);
// print_r("<br />");
// print_r($loggedUser);
// exit;

// ───────────────────────────────
// TEMPLATE
// ───────────────────────────────

$ca->assign('hasItems', $hasItems);
$ca->assign('isLoggedIn', $isLoggedIn);
$ca->assign('loggedUser', $loggedUser);
$ca->assign('cartDomains', $enrichedDomains);
$ca->assign('cartProducts', $enrichedProducts);
$ca->assign('countries', $countries);
$ca->assign('promoCode', $promoCode);

$ca->assign('subtotal', number_format($subtotal, 2));
$ca->assign('promoDiscount', number_format($promoDiscount, 2));
$ca->assign('grandTotal', number_format($grandTotal, 2));

$ca->setTemplate('custom_shopping_cart');
$ca->output();