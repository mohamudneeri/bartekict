<?php
require_once __DIR__ . '/../../init.php';
use WHMCS\Database\Capsule;

session_start();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'verify_auth') {
    verifyAuthCode();
    exit;
}

checkTransferEligibility();

function checkTransferEligibility() {

    $domain = trim($_POST['domain'] ?? '');

    if (!$domain) {
        echo json_encode(['status' => 'error', 'message' => 'No domain provided']);
        exit;
    }

    // Validate format
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$/', $domain)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid domain format']);
        exit;
    }

    $parts = explode('.', $domain, 2);
    $sld = $parts[0];
    $tld = '.' . $parts[1];
    $cleanTld = ltrim($tld, '.');

    /*
    |--------------------------------------------------------------------------
    | 1. Check WHOIS (Registered or Not)
    |--------------------------------------------------------------------------
    */
    $whois = localAPI('DomainWhois', ['domain' => $domain]);
    
    $registered = ($whois['status'] !== 'available');

    // CASE 1: Domain is NOT registered at all
    if (!$registered) {
        echo json_encode([
            'status' => 'success',
            'domain' => $domain,
            'registered' => false,
            'inWhmcs' => false,
            'eligible' => false,
            'lockStatus' => null,
            'within60DayLock' => null,
            'unlocked' => null,
            'needsAuthCode' => false,
            'transferPrice' => getTransferPrice($cleanTld),
            'inCart' => false,
            'reasons' => ['not_registered']
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Check if domain exists in WHMCS
    |--------------------------------------------------------------------------
    */
    $inWhmcs = Capsule::table('tbldomains')
        ->where('domain', $domain)
        ->exists();

    // CASE 2: Domain is registered in our WHMCS account
    if ($inWhmcs) {
        echo json_encode([
            'status' => 'success',
            'domain' => $domain,
            'registered' => true,
            'inWhmcs' => true,
            'eligible' => false,
            'lockStatus' => null,
            'within60DayLock' => null,
            'unlocked' => null,
            'needsAuthCode' => false,
            'transferPrice' => getTransferPrice($cleanTld),
            'inCart' => false,
            'reasons' => ['already_in_whmcs']
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Extract WHOIS Info from HTML/Text response
    |--------------------------------------------------------------------------
    */
    $registrationDate = null;
    $expiryDate = null;
    $isUnlocked = true;
    $currentRegistrar = 'Unknown';
    $lockStatuses = [];
    
    // Parse the WHOIS HTML response
    $whoisText = $whois['whois'] ?? '';
    
    if (!empty($whoisText)) {
        // Clean up HTML entities
        $whoisText = str_replace(['<br />', '<br>', '<br/>'], "\n", $whoisText);
        $whoisText = strip_tags($whoisText);
        
        // Split into lines
        $lines = explode("\n", $whoisText);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Extract Creation Date
            if (preg_match('/Creation Date:\s*(.+?)$/i', $line, $matches)) {
                $registrationDate = strtotime(trim($matches[1]));
            }
            // Alternative format
            elseif (preg_match('/Created On:\s*(.+?)$/i', $line, $matches)) {
                $registrationDate = strtotime(trim($matches[1]));
            }
            
            // Extract Expiry Date
            if (preg_match('/Registry Expiry Date:\s*(.+?)$/i', $line, $matches)) {
                $expiryDate = strtotime(trim($matches[1]));
            }
            // Alternative format
            elseif (preg_match('/Expiration Date:\s*(.+?)$/i', $line, $matches)) {
                $expiryDate = strtotime(trim($matches[1]));
            }
            
            // Extract Registrar
            if (preg_match('/Registrar:\s*(.+?)$/i', $line, $matches)) {
                $currentRegistrar = trim($matches[1]);
            }
            
            // Extract Domain Status (This is the key part!)
            if (preg_match('/Domain Status:\s*(.+?)$/i', $line, $matches)) {
                $statusLine = trim($matches[1]);
                
                // Extract status code (e.g., "clientTransferProhibited")
                if (preg_match('/([a-zA-Z]+TransferProhibited)/i', $statusLine, $statusMatch)) {
                    $statusCode = strtolower($statusMatch[1]);
                    $lockStatuses[] = $statusCode;
                    
                    // Check if this status prevents transfer
                    if (strpos($statusCode, 'transferprohibited') !== false) {
                        $isUnlocked = false;
                    }
                }
            }
        }
    }
    
    // Also check for status in the raw API response if available
    if (isset($whois['status']) && is_string($whois['status'])) {
        if (stripos($whois['status'], 'transferprohibited') !== false) {
            $isUnlocked = false;
            $lockStatuses[] = strtolower($whois['status']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 4. 60-Day Lock Check
    |--------------------------------------------------------------------------
    */
    $within60DayLock = false;

    if ($registrationDate) {
        $days = (time() - $registrationDate) / 86400;
        $within60DayLock = ($days < 60);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Determine Eligibility
    |--------------------------------------------------------------------------
    */
    $eligible = true;
    $reasons = [];

    if ($within60DayLock) {
        $eligible = false;
        $reasons[] = '60_day_lock';
    }

    if (!$isUnlocked) {
        $eligible = false;
        $reasons[] = 'locked';
    }

    // Auth code will be verified separately
    $needsAuthCode = $eligible;

    // Check if already in cart
    $inCart = checkIfInCart($domain);

    /*
    |--------------------------------------------------------------------------
    | Final Response
    |--------------------------------------------------------------------------
    */
    $response = [
        'status' => 'success',
        'domain' => $domain,

        // Core checks
        'registered' => true,
        'inWhmcs' => false,
        'within60DayLock' => $within60DayLock,
        'unlocked' => $isUnlocked,
        'lockStatus' => $isUnlocked ? 'unlocked' : 'locked',
        'currentRegistrar' => $currentRegistrar,
        'creationDate' => $registrationDate ? date('Y-m-d', $registrationDate) : null,
        'expiryDate' => $expiryDate ? date('Y-m-d', $expiryDate) : null,

        // Auth related
        'needsAuthCode' => $needsAuthCode,
        'authCodeValid' => null,

        // Price and cart
        'transferPrice' => getTransferPrice($cleanTld),
        'inCart' => $inCart,

        // Final decision
        'eligible' => $eligible,
        'reasons' => $reasons
    ];
    
    // Add debug info (remove in production)
    if (!empty($lockStatuses)) {
        $response['debug_lock_statuses'] = $lockStatuses;
    }
    
    echo json_encode($response);
}

/*
|--------------------------------------------------------------------------
| AUTH CODE VALIDATION
|--------------------------------------------------------------------------
*/
function verifyAuthCode() {

    $domain = trim($_POST['domain'] ?? '');
    $authCode = trim($_POST['auth_code'] ?? '');

    if (!$domain || !$authCode) {
        echo json_encode([
            'status' => 'error',
            'verified' => false,
            'message' => 'Missing domain or auth code'
        ]);
        exit;
    }

    // Basic format validation
    if (strlen($authCode) < 5) {
        echo json_encode([
            'status' => 'error',
            'verified' => false,
            'message' => 'Invalid auth code format'
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | AUTH CODE VALIDATION
    | Note: Most registrars don't support validating EPP codes without initiating transfer
    |--------------------------------------------------------------------------
    */
    try {
        // Attempt to validate via registrar API if supported
        $result = localAPI('DomainTransfer', [
            'domain' => $domain,
            'eppcode' => $authCode,
            'action' => 'check'
        ]);
        
        if (isset($result['result']) && $result['result'] === 'success') {
            echo json_encode([
                'status' => 'success',
                'verified' => true,
                'message' => 'Authorization code verified successfully'
            ]);
        } else {
            // For most registrars, we can only validate during actual transfer
            // So we'll accept the code format and validate during transfer
            echo json_encode([
                'status' => 'success',
                'verified' => true,
                'message' => 'Authorization code accepted',
                'warning' => 'Final validation will occur during transfer process'
            ]);
        }
    } catch (Exception $e) {
        // If validation API fails, still accept the code
        // The actual validation will happen when transfer is initiated
        echo json_encode([
            'status' => 'success',
            'verified' => true,
            'message' => 'Authorization code accepted'
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
function getTransferPrice($tld) {
    $prices = [
        'com' => 12.99,
        'net' => 12.99,
        'org' => 12.99,
        'io' => 35.99,
        'co' => 25.99
    ];
    
    return $prices[strtolower($tld)] ?? 15.99;
}

function checkIfInCart($domain) {
    if (isset($_SESSION['cart_domains']) && in_array($domain, $_SESSION['cart_domains'])) {
        return true;
    }
    
    return false;
}