<?php

require_once __DIR__ . '/init.php';

session_start();

/*
|--------------------------------------------------------------------------
| Hosting Plan Mapping
|--------------------------------------------------------------------------
*/

$plans = [
    'starter' => 1,
    'business' => 2,
    'premium' => 3,
];

$allowedCycles = [
    'monthly',
    'quarterly',
    'annually',
];

/*
|--------------------------------------------------------------------------
| Validate Plan
|--------------------------------------------------------------------------
*/

$planSlug = strtolower(trim($_GET['plan'] ?? ''));

if (!$planSlug || !isset($plans[$planSlug])) {
    die('Invalid hosting plan.');
}

$pid = (int) $plans[$planSlug];

/*
|--------------------------------------------------------------------------
| Validate Billing Cycle
|--------------------------------------------------------------------------
*/

$billingCycle = strtolower(trim($_GET['cycle'] ?? 'monthly'));

if (!in_array($billingCycle, $allowedCycles)) {
    $billingCycle = 'monthly';
}

/*
|--------------------------------------------------------------------------
| Redirect to Domain Configuration
|--------------------------------------------------------------------------
*/

header('Location: select-domain.php?plan=' . urlencode($planSlug) . '&cycle=' . urlencode($billingCycle));
exit;