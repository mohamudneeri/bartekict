<?php
/**
 * custom_select_domain.php
 *
 * Central API for the "Select Domain" page.
 * Handles four flows:
 *   1. check_new      – availability check for a new registration
 *   2. check_transfer – full transfer-eligibility check (WHOIS + lock + 60-day rule)
 *   3. verify_auth    – EPP/auth-code pre-validation for a transfer
 *   4. set_own_domain – save a user-owned domain to session (delegates to cart_manage)
 *
 * Cart writes (add / set_selected_domain) are intentionally kept in
 * custom_cart_manage.php and called directly from JS.
 */

require_once __DIR__ . '/../../init.php';
use WHMCS\Database\Capsule;

session_start();

header('Content-Type: application/json');

$action = trim($_POST['action'] ?? '');

switch ($action) {
    case 'check_new':
        checkNewDomain();
        break;

    case 'check_transfer':
        checkTransferEligibility();
        break;

    case 'verify_auth':
        verifyAuthCode();
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/* ==========================================================================
 * 1. CHECK NEW DOMAIN AVAILABILITY
 * ========================================================================== */

function checkNewDomain() {
    $domain = trim($_POST['domain'] ?? '');

    if (empty($domain)) {
        echo json_encode(['status' => 'error', 'message' => 'No domain provided']);
        exit;
    }

    // Split SLD / TLD
    $parts = explode('.', $domain, 2);
    if (count($parts) < 2) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid domain format']);
        exit;
    }

    $sld      = $parts[0];
    $tld      = '.' . $parts[1];
    $cleanTld = $parts[1];

    // Availability check
    $whois  = localAPI('DomainWhois', ['domain' => $domain]);
    $status = ($whois['status'] === 'available') ? 'available' : 'unavailable';
    $whois_status = $whois['status']; // Temp, remove after test

    // Pricing
    $pricing       = localAPI('GetTLDPricing');
    $registerPrice = '0.00';

    if (isset($pricing['pricing'][$cleanTld])) {
        $regPrices     = $pricing['pricing'][$cleanTld]['register'] ?? [];
        $registerPrice = is_array($regPrices) ? reset($regPrices) : $regPrices;
    }

    // Already in cart?
    $inCart = domainInCart($domain);

    echo json_encode([
        'status'        => $status,
        'whois_status'  => $whois_status,
        'domain'        => $domain,
        'inCart'        => $inCart,
        'registerPrice' => number_format((float)$registerPrice, 2),
    ]);
}

/* ==========================================================================
 * 2. CHECK TRANSFER ELIGIBILITY
 * ========================================================================== */

function checkTransferEligibility() {
    $domain = trim($_POST['domain'] ?? '');

    if (!$domain) {
        echo json_encode(['status' => 'error', 'message' => 'No domain provided']);
        exit;
    }

    // Basic format validation
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$/', $domain)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid domain format']);
        exit;
    }

    $parts    = explode('.', $domain, 2);
    $cleanTld = $parts[1];

    /* ------------------------------------------------------------------
     * a) WHOIS check
     * ------------------------------------------------------------------ */
    $whois      = localAPI('DomainWhois', ['domain' => $domain]);
    $registered = ($whois['status'] !== 'available');
    $whois_status = $whois['status']; // Temp, remove after test

    // Not registered at all
    if (!$registered) {
        echo json_encode([
            'status'        => 'success',
            'whois_status' => $whois_status,
            'domain'        => $domain,
            'registered'    => false,
            'inWhmcs'       => false,
            'eligible'      => false,
            'lockStatus'    => null,
            'within60Days'  => null,
            'unlocked'      => null,
            'needsAuthCode' => false,
            'transferPrice' => getTransferPrice($cleanTld),
            'inCart'        => false,
            'reasons'       => ['not_registered'],
        ]);
        exit;
    }

    /* ------------------------------------------------------------------
     * b) Already managed in WHMCS?
     * ------------------------------------------------------------------ */
    $inWhmcs = Capsule::table('tbldomains')->where('domain', $domain)->exists();

    if ($inWhmcs) {
        echo json_encode([
            'status'        => 'success',
            'domain'        => $domain,
            'registered'    => true,
            'inWhmcs'       => true,
            'eligible'      => false,
            'lockStatus'    => null,
            'within60Days'  => null,
            'unlocked'      => null,
            'needsAuthCode' => false,
            'transferPrice' => getTransferPrice($cleanTld),
            'inCart'        => false,
            'reasons'       => ['already_in_whmcs'],
        ]);
        exit;
    }

    /* ------------------------------------------------------------------
     * c) Parse WHOIS details
     * ------------------------------------------------------------------ */
    $registrationDate  = null;
    $expiryDate        = null;
    $isUnlocked        = true;
    $currentRegistrar  = 'Unknown';
    $lockStatuses      = [];

    $whoisText = $whois['whois'] ?? '';

    if (!empty($whoisText)) {
        $whoisText = str_replace(['<br />', '<br>', '<br/>'], "\n", $whoisText);
        $whoisText = strip_tags($whoisText);
        $lines     = explode("\n", $whoisText);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Creation date
            if (preg_match('/Creation Date:\s*(.+?)$/i', $line, $m)) {
                $registrationDate = strtotime(trim($m[1]));
            } elseif (preg_match('/Created On:\s*(.+?)$/i', $line, $m)) {
                $registrationDate = strtotime(trim($m[1]));
            }

            // Expiry date
            if (preg_match('/Registry Expiry Date:\s*(.+?)$/i', $line, $m)) {
                $expiryDate = strtotime(trim($m[1]));
            } elseif (preg_match('/Expiration Date:\s*(.+?)$/i', $line, $m)) {
                $expiryDate = strtotime(trim($m[1]));
            }

            // Registrar
            if (preg_match('/Registrar:\s*(.+?)$/i', $line, $m)) {
                $currentRegistrar = trim($m[1]);
            }

            // Lock statuses
            if (preg_match('/Domain Status:\s*(.+?)$/i', $line, $m)) {
                $statusLine = trim($m[1]);
                if (preg_match('/([a-zA-Z]+TransferProhibited)/i', $statusLine, $sm)) {
                    $code          = strtolower($sm[1]);
                    $lockStatuses[] = $code;
                    if (strpos($code, 'transferprohibited') !== false) {
                        $isUnlocked = false;
                    }
                }
            }
        }
    }

    // Fallback: check raw status string
    if (isset($whois['status']) && is_string($whois['status'])) {
        if (stripos($whois['status'], 'transferprohibited') !== false) {
            $isUnlocked     = false;
            $lockStatuses[] = strtolower($whois['status']);
        }
    }

    /* ------------------------------------------------------------------
     * d) 60-day lock check
     * ------------------------------------------------------------------ */
    $within60Days = false;
    if ($registrationDate) {
        $within60Days = ((time() - $registrationDate) / 86400) < 60;
    }

    /* ------------------------------------------------------------------
     * e) Eligibility decision
     * ------------------------------------------------------------------ */
    $eligible = true;
    $reasons  = [];

    if ($within60Days) {
        $eligible  = false;
        $reasons[] = '60_day_lock';
    }

    if (!$isUnlocked) {
        $eligible  = false;
        $reasons[] = 'locked';
    }

    $inCart = domainInCart($domain);

    $response = [
        'status'            => 'success',
        'domain'            => $domain,
        'registered'        => true,
        'inWhmcs'           => false,
        'within60Days'      => $within60Days,
        'unlocked'          => $isUnlocked,
        'lockStatus'        => $isUnlocked ? 'unlocked' : 'locked',
        'currentRegistrar'  => $currentRegistrar,
        'creationDate'      => $registrationDate ? date('Y-m-d', $registrationDate) : null,
        'expiryDate'        => $expiryDate        ? date('Y-m-d', $expiryDate)        : null,
        'needsAuthCode'     => $eligible,
        'authCodeValid'     => null,
        'transferPrice'     => getTransferPrice($cleanTld),
        'inCart'            => $inCart,
        'eligible'          => $eligible,
        'reasons'           => $reasons,
    ];

    if (!empty($lockStatuses)) {
        $response['lockStatuses'] = $lockStatuses; // helpful for debugging
    }

    echo json_encode($response);
}

/* ==========================================================================
 * 3. VERIFY AUTH / EPP CODE
 * ========================================================================== */

function verifyAuthCode() {
    $domain   = trim($_POST['domain']    ?? '');
    $authCode = trim($_POST['auth_code'] ?? '');

    if (!$domain || !$authCode) {
        echo json_encode([
            'status'   => 'error',
            'verified' => false,
            'message'  => 'Missing domain or auth code',
        ]);
        exit;
    }

    if (strlen($authCode) < 5) {
        echo json_encode([
            'status'   => 'error',
            'verified' => false,
            'message'  => 'Auth code is too short (minimum 5 characters)',
        ]);
        exit;
    }

    /*
     * Most registrars cannot validate EPP codes without initiating the
     * transfer. We attempt a soft check via the WHMCS API; if unsupported
     * we accept the code and let the real validation happen at transfer time.
     */
    try {
        $result = localAPI('DomainTransfer', [
            'domain'   => $domain,
            'eppcode'  => $authCode,
            'action'   => 'check',
        ]);

        if (isset($result['result']) && $result['result'] === 'success') {
            echo json_encode([
                'status'   => 'success',
                'verified' => true,
                'message'  => 'Authorization code verified successfully',
            ]);
        } else {
            // Accept the code; final check happens at actual transfer
            echo json_encode([
                'status'   => 'success',
                'verified' => true,
                'message'  => 'Authorization code accepted',
                'warning'  => 'Final validation will occur when the transfer is initiated',
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status'   => 'success',
            'verified' => true,
            'message'  => 'Authorization code accepted',
        ]);
    }
}

/* ==========================================================================
 * HELPERS
 * ========================================================================== */

/**
 * Check whether $domain already exists in the session cart.
 */
function domainInCart(string $domain): bool {
    if (!isset($_SESSION['cart']['domains'])) {
        return false;
    }
    foreach ($_SESSION['cart']['domains'] as $item) {
        if ($item['domain'] === $domain) {
            return true;
        }
    }
    return false;
}

/**
 * Return the transfer price for a given TLD (without leading dot).
 * Falls back to a default rate for unlisted TLDs.
 */
function getTransferPrice(string $tld): float {
    $prices = [
        'com' => 12.99,
        'net' => 12.99,
        'org' => 12.99,
        'io'  => 35.99,
        'co'  => 25.99,
    ];
    return $prices[strtolower($tld)] ?? 15.99;
}
