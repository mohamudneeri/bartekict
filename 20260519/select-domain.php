<?php

define("CLIENTAREA", true);

require __DIR__ . '/init.php';

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

$ca = new ClientArea();

$ca->setPageTitle('Domain Configuration - BARTEK ICT');
$ca->initPage();

/*
|--------------------------------------------------------------------------
| Hosting Plan Mapping
|--------------------------------------------------------------------------
*/

$planSlug = $_GET['plan'] ?? 'starter';
$cycle = $_GET['cycle'] ?? 'monthly';

$plans = [
    'starter'  => 1,
    'business' => 2,
    'premium'  => 3,
];

$allowedCycles = ['monthly', 'quarterly', 'annually'];

if (!in_array($cycle, $allowedCycles)) {
    $cycle = 'monthly';
}

$pid = $plans[$planSlug] ?? 1;

/*
|--------------------------------------------------------------------------
| Store Hosting Selection
|--------------------------------------------------------------------------
*/

$_SESSION['selected_hosting_plan'] = [
    'pid'   => $pid,
    'slug'  => $planSlug,
    'cycle' => $cycle,
];

/*
|--------------------------------------------------------------------------
| Product Details
|--------------------------------------------------------------------------
*/

$product = Capsule::table('tblproducts')
    ->where('id', $pid)
    ->first();

$productFeatures = [];
$productPricing = [];

if ($product) {
    // Get features
    if (!empty($product->description)) {
        $description = str_replace(
            ['<br>', '<br/>', '<br />'],
            "\n",
            $product->description
        );
    
        $features = explode("\n", $description);
    
        foreach ($features as $feature) {
            $feature = trim(strip_tags(
                $feature,
                '<strong><em><b><i><span>'
            ));
    
            if (!empty($feature)) {
                $productFeatures[] = $feature;
            }
        }
    }
    
    // Get pricing
    $pricing = Capsule::table('tblpricing')
        ->where('type', 'product')
        ->where('relid', $pid)
        ->where('currency', 1)
        ->first();

    if ($pricing) {
        $productPricing = [
            'monthly'   => $pricing->monthly,
            'quarterly' => $pricing->quarterly,
            'annually'  => $pricing->annually,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Domains In Cart
|--------------------------------------------------------------------------
*/

$cartDomains = [];

if (!empty($_SESSION['cart']['domains'])) {
    foreach ($_SESSION['cart']['domains'] as $domain) {
        if (!empty($domain['domain'])) {
            $cartDomains[] = [
                'domain' => $domain['domain'],
                'type'   => $domain['type'] ?? 'register',
            ];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Eligible TLDs
|--------------------------------------------------------------------------
*/

$tldPricing = localAPI('GetTLDPricing');

$registerTlds = [];
$transferTlds = [];

if (
    isset($tldPricing['result']) &&
    $tldPricing['result'] === 'success' &&
    !empty($tldPricing['pricing'])
) {
    foreach ($tldPricing['pricing'] as $tld => $pricing) {
        // Check register pricing - array key '1' for 1 year registration
        if (
            isset($pricing['register'][1]) &&
            is_numeric($pricing['register'][1]) &&
            $pricing['register'][1] >= 0
        ) {
            $registerTlds[] = '.' . $tld;
        }
    
        // Check transfer pricing - array key '1' for 1 year transfer
        if (
            isset($pricing['transfer'][1]) &&
            is_numeric($pricing['transfer'][1]) &&
            $pricing['transfer'][1] >= 0
        ) {
            $transferTlds[] = '.' . $tld;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Assign Template Variables
|--------------------------------------------------------------------------
*/

$ca->assign('selectedPlan', [
    'id'          => $product->id ?? 0,
    'name'        => $product->name ?? 'Hosting Plan',
    'slug'        => $planSlug,
    'cycle'       => ucfirst($cycle),
    'cycle_key'   => $cycle,
    'description' => $product->short_description ?? '',
    'image'       => "https://bartekict.com/wp-content/uploads/2026/04/HP0{$pid}-1.png",
    'features'    => $productFeatures,
    'price'       => $productPricing[$cycle] ?? '0.00',
]);

$ca->assign('cartDomains', $cartDomains);
$ca->assign('hasCartDomains', count($cartDomains) > 0);

$ca->assign('registerTlds', $registerTlds);
$ca->assign('transferTlds', $transferTlds);

// Additional useful variables
$ca->assign('planSlug', $planSlug);
$ca->assign('cycleParam', $cycle);
$ca->assign('pid', $pid);

$ca->setTemplate('custom_select_domain');

$ca->output();