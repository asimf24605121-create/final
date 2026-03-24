<?php
require_once __DIR__ . '/../db.php';

session_start();

checkAdminAccess('super_admin');

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $platformId = (int)($_GET['platform_id'] ?? 0);

    cleanupStaleSessions($pdo);

    $now = date('Y-m-d H:i:s');
    $activeCutoff = date('Y-m-d H:i:s', strtotime('-5 minutes'));

    $where = $platformId > 0 ? "WHERE pa.platform_id = ?" : "";
    $params = $platformId > 0 ? [$activeCutoff, $platformId] : [$activeCutoff];

    $sql = "
        SELECT pa.id, pa.platform_id, pa.slot_name, pa.max_users, pa.cookie_count,
               pa.expires_at, pa.is_active, pa.created_at, pa.updated_at,
               pa.success_count, pa.fail_count, pa.health_status, pa.last_success_at, pa.last_failed_at, pa.cooldown_until,
               ((pa.success_count * 2) - (pa.fail_count * 3)) AS slot_score,
               p.name AS platform_name,
               (SELECT COUNT(*) FROM account_sessions acs
                WHERE acs.account_id = pa.id AND acs.status = 'active' AND acs.last_active >= ?) AS active_users
        FROM platform_accounts pa
        INNER JOIN platforms p ON p.id = pa.platform_id
        {$where}
        ORDER BY pa.platform_id ASC, pa.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $accounts = $stmt->fetchAll();

    foreach ($accounts as &$acct) {
        $acct['active_users'] = (int)$acct['active_users'];
        $acct['available_slots'] = max(0, (int)$acct['max_users'] - $acct['active_users']);
        $acct['success_count'] = (int)($acct['success_count'] ?? 0);
        $acct['fail_count'] = (int)($acct['fail_count'] ?? 0);
        $acct['slot_score'] = (int)($acct['slot_score'] ?? 0);
        $acct['health_status'] = $acct['health_status'] ?? 'healthy';
        $expired = false;
        if ($acct['expires_at']) {
            try {
                $expired = new DateTime($acct['expires_at']) < new DateTime($now);
            } catch (Exception $e) {}
        }
        $acct['is_expired'] = $expired;
    }
    unset($acct);

    jsonResponse(['success' => true, 'accounts' => $accounts]);
}

if ($method === 'POST') {
    validateCsrfToken();

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'create';

    if ($action === 'create') {
        $platformId = (int)($input['platform_id'] ?? 0);
        $slotName = trim($input['slot_name'] ?? '');
        $cookieData = trim($input['cookie_data'] ?? '');
        $maxUsers = max(1, min(50, (int)($input['max_users'] ?? 5)));
        $expiresAt = trim($input['expires_at'] ?? '');

        if ($platformId < 1) {
            jsonResponse(['success' => false, 'message' => 'Platform is required.'], 400);
        }

        $platform = $pdo->prepare("SELECT id, name, cookie_domain FROM platforms WHERE id = ?");
        $platform->execute([$platformId]);
        $platform = $platform->fetch();
        if (!$platform) {
            jsonResponse(['success' => false, 'message' => 'Platform not found.'], 404);
        }

        if ($slotName === '') {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM platform_accounts WHERE platform_id = ?");
            $countStmt->execute([$platformId]);
            $num = (int)$countStmt->fetchColumn() + 1;
            $slotName = "Login {$num}";
        }

        $cookieCount = 0;
        $encodedCookie = '';
        if ($cookieData !== '') {
            $parsed = parseCookieInput($cookieData);
            if (!$parsed['valid']) {
                jsonResponse(['success' => false, 'message' => $parsed['error']], 400);
            }
            $cookieCount = $parsed['count'];
            $normalizedJson = json_encode($parsed['cookies'], JSON_UNESCAPED_SLASHES);
            $encodedCookie = base64_encode($normalizedJson);
        }

        $expiresVal = null;
        if ($expiresAt !== '') {
            try {
                $dt = new DateTime($expiresAt);
                $expiresVal = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'message' => 'Invalid expiry date format.'], 400);
            }
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO platform_accounts (platform_id, slot_name, cookie_data, max_users, cookie_count, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$platformId, $slotName, $encodedCookie, $maxUsers, $cookieCount, $expiresVal, $now, $now]);
        $newId = $pdo->lastInsertId();

        logActivity($_SESSION['user_id'], "account_created: platform={$platform['name']} slot={$slotName} max_users={$maxUsers}", getClientIP());

        jsonResponse([
            'success' => true,
            'message' => "Account slot '{$slotName}' created for {$platform['name']}.",
            'account_id' => (int)$newId,
        ]);
    }

    if ($action === 'update') {
        $accountId = (int)($input['account_id'] ?? 0);
        if ($accountId < 1) {
            jsonResponse(['success' => false, 'message' => 'Account ID is required.'], 400);
        }

        $acct = $pdo->prepare("SELECT pa.*, p.name AS platform_name FROM platform_accounts pa JOIN platforms p ON p.id = pa.platform_id WHERE pa.id = ?");
        $acct->execute([$accountId]);
        $acct = $acct->fetch();
        if (!$acct) {
            jsonResponse(['success' => false, 'message' => 'Account not found.'], 404);
        }

        $slotName = trim($input['slot_name'] ?? $acct['slot_name']);
        $maxUsers = isset($input['max_users']) ? max(1, min(50, (int)$input['max_users'])) : (int)$acct['max_users'];
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : (int)$acct['is_active'];
        $cookieData = $input['cookie_data'] ?? null;
        $expiresAt = $input['expires_at'] ?? null;

        $cookieCount = (int)$acct['cookie_count'];
        $encodedCookie = $acct['cookie_data'];

        if ($cookieData !== null && trim($cookieData) !== '') {
            $parsed = parseCookieInput(trim($cookieData));
            if (!$parsed['valid']) {
                jsonResponse(['success' => false, 'message' => $parsed['error']], 400);
            }
            $cookieCount = $parsed['count'];
            $normalizedJson = json_encode($parsed['cookies'], JSON_UNESCAPED_SLASHES);
            $encodedCookie = base64_encode($normalizedJson);
        }

        $expiresVal = $acct['expires_at'];
        if ($expiresAt !== null && trim($expiresAt) !== '') {
            try {
                $dt = new DateTime(trim($expiresAt));
                $expiresVal = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {}
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE platform_accounts
            SET slot_name = ?, cookie_data = ?, max_users = ?, cookie_count = ?, expires_at = ?, is_active = ?, updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$slotName, $encodedCookie, $maxUsers, $cookieCount, $expiresVal, $isActive, $now, $accountId]);

        logActivity($_SESSION['user_id'], "account_updated: id={$accountId} platform={$acct['platform_name']} slot={$slotName}", getClientIP());

        jsonResponse([
            'success' => true,
            'message' => "Account slot '{$slotName}' updated.",
        ]);
    }

    if ($action === 'delete') {
        $accountId = (int)($input['account_id'] ?? 0);
        if ($accountId < 1) {
            jsonResponse(['success' => false, 'message' => 'Account ID is required.'], 400);
        }

        $acct = $pdo->prepare("SELECT pa.*, p.name AS platform_name FROM platform_accounts pa JOIN platforms p ON p.id = pa.platform_id WHERE pa.id = ?");
        $acct->execute([$accountId]);
        $acct = $acct->fetch();
        if (!$acct) {
            jsonResponse(['success' => false, 'message' => 'Account not found.'], 404);
        }

        $pdo->prepare("DELETE FROM account_sessions WHERE account_id = ?")->execute([$accountId]);
        $pdo->prepare("DELETE FROM platform_accounts WHERE id = ?")->execute([$accountId]);

        logActivity($_SESSION['user_id'], "account_deleted: id={$accountId} platform={$acct['platform_name']} slot={$acct['slot_name']}", getClientIP());

        jsonResponse([
            'success' => true,
            'message' => "Account slot '{$acct['slot_name']}' deleted from {$acct['platform_name']}.",
        ]);
    }

    if ($action === 'reset_stats') {
        $accountId = (int)($input['account_id'] ?? 0);
        if ($accountId < 1) {
            jsonResponse(['success' => false, 'message' => 'Account ID is required.'], 400);
        }

        $now = date('Y-m-d H:i:s');
        $pdo->prepare("
            UPDATE platform_accounts
            SET success_count = 0, fail_count = 0, health_status = 'healthy',
                last_success_at = NULL, last_failed_at = NULL, cooldown_until = NULL, updated_at = ?
            WHERE id = ?
        ")->execute([$now, $accountId]);

        logActivity($_SESSION['user_id'], "slot_stats_reset: account_id={$accountId}", getClientIP());

        jsonResponse(['success' => true, 'message' => 'Slot statistics reset successfully.']);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);

function cleanupStaleSessions(PDO $pdo): void {
    $cutoff = date('Y-m-d H:i:s', strtotime('-10 minutes'));
    $pdo->prepare("UPDATE account_sessions SET status = 'inactive' WHERE status = 'active' AND last_active < ?")->execute([$cutoff]);
}

function parseCookieInput(string $raw): array {
    $raw = trim($raw);
    $jsonData = json_decode($raw, true);
    if (is_array($jsonData) && !empty($jsonData)) {
        if (isset($jsonData[0]) && is_array($jsonData[0])) {
            return validateJsonCookies($jsonData);
        }
        if (isset($jsonData['name']) && isset($jsonData['value'])) {
            return validateJsonCookies([$jsonData]);
        }
    }
    return parsePlainCookieString($raw);
}

function validateJsonCookies(array $cookies): array {
    $valid = [];
    $errors = [];
    $earliestExpiry = null;

    foreach ($cookies as $i => $cookie) {
        if (!is_array($cookie)) { $errors[] = "Cookie #" . ($i + 1) . ": not an object"; continue; }
        $name = trim($cookie['name'] ?? '');
        if ($name === '') { $errors[] = "Cookie #" . ($i + 1) . ": missing 'name'"; continue; }

        $rawSameSite = $cookie['sameSite'] ?? 'lax';
        $sameSite = 'lax';
        if ($rawSameSite !== null) {
            $v = strtolower(trim((string)$rawSameSite));
            if ($v === 'no_restriction' || $v === 'none') $sameSite = 'none';
            elseif (in_array($v, ['strict', 'lax', 'unspecified'], true)) $sameSite = $v;
        }

        $normalized = [
            'name' => $name, 'value' => (string)($cookie['value'] ?? ''),
            'domain' => $cookie['domain'] ?? null, 'path' => $cookie['path'] ?? '/',
            'secure' => (bool)($cookie['secure'] ?? true), 'httpOnly' => (bool)($cookie['httpOnly'] ?? true),
            'sameSite' => $sameSite,
        ];
        if (isset($cookie['expirationDate']) && is_numeric($cookie['expirationDate'])) {
            $normalized['expirationDate'] = (int)$cookie['expirationDate'];
            $expDt = date('Y-m-d H:i:s', (int)$cookie['expirationDate']);
            if ($earliestExpiry === null || $expDt < $earliestExpiry) $earliestExpiry = $expDt;
        }
        $valid[] = $normalized;
    }
    if (empty($valid)) return ['valid' => false, 'error' => 'No valid cookies found. ' . implode('; ', $errors)];
    return ['valid' => true, 'cookies' => $valid, 'count' => count($valid), 'earliest_expiry' => $earliestExpiry];
}

function parsePlainCookieString(string $raw): array {
    $cookies = [];
    $pairs = array_filter(array_map('trim', explode(';', $raw)));
    $skip = ['path', 'domain', 'expires', 'max-age', 'samesite', 'secure', 'httponly'];
    foreach ($pairs as $pair) {
        $eqPos = strpos($pair, '=');
        if ($eqPos === false || $eqPos < 1) continue;
        $name = trim(substr($pair, 0, $eqPos));
        $value = trim(substr($pair, $eqPos + 1));
        if (in_array(strtolower($name), $skip, true)) continue;
        $cookies[] = ['name' => $name, 'value' => $value, 'domain' => null, 'path' => '/', 'secure' => true, 'httpOnly' => true, 'sameSite' => 'lax'];
    }
    if (empty($cookies)) return ['valid' => false, 'error' => 'No valid cookie pairs found.'];
    return ['valid' => true, 'cookies' => $cookies, 'count' => count($cookies), 'earliest_expiry' => null];
}
