<?php
require_once __DIR__ . '/../db.php';

session_start();
checkAdminAccess('super_admin');

$pdo = getPDO();
autoExpireSubscriptions();

$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
$twentyFourHoursAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
$fiveMinAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
$fortyEightHours = date('Y-m-d H:i:s', strtotime('+48 hours'));
$twentyFourHoursFromNow = date('Y-m-d H:i:s', strtotime('+24 hours'));
$sevenDaysFromNow = date('Y-m-d', strtotime('+7 days'));

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

$stmtNewUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= ?");
$stmtNewUsers->execute([$twentyFourHoursAgo]);
$newUsersToday = (int)$stmtNewUsers->fetchColumn();

$stmtActiveUsers = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE status = 'active' AND last_activity >= ?");
$stmtActiveUsers->execute([$fiveMinAgo]);
$activeUsers = (int)$stmtActiveUsers->fetchColumn();

$stmtLiveSessions = $pdo->prepare("SELECT COUNT(*) FROM account_sessions WHERE status = 'active' AND last_active >= ?");
$stmtLiveSessions->execute([$fiveMinAgo]);
$liveSessions = (int)$stmtLiveSessions->fetchColumn();

$stmtActiveSubs = $pdo->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE is_active = 1 AND end_date >= ?");
$stmtActiveSubs->execute([$today]);
$activeSubs = (int)$stmtActiveSubs->fetchColumn();

$stmtNewSubs = $pdo->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE is_active = 1 AND start_date >= ?");
$stmtNewSubs->execute([$twentyFourHoursAgo]);
$newSubsToday = (int)$stmtNewSubs->fetchColumn();

$totalSlots = (int)$pdo->query("SELECT COUNT(*) FROM platform_accounts WHERE is_active = 1")->fetchColumn();
$stmtUsedSlots = $pdo->prepare("SELECT COUNT(DISTINCT account_id) FROM account_sessions WHERE status = 'active' AND last_active >= ?");
$stmtUsedSlots->execute([$fiveMinAgo]);
$usedSlots = (int)$stmtUsedSlots->fetchColumn();
$slotUtilization = $totalSlots > 0 ? round(($usedSlots / $totalSlots) * 100) : 0;

$stmtExpiringSubs = $pdo->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE is_active = 1 AND end_date BETWEEN ? AND ?");
$stmtExpiringSubs->execute([$today, $twentyFourHoursFromNow]);
$expiringSubs24h = (int)$stmtExpiringSubs->fetchColumn();

$stmtExpiring7d = $pdo->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE is_active = 1 AND end_date BETWEEN ? AND ?");
$stmtExpiring7d->execute([$today, $sevenDaysFromNow]);
$expiringSubs7d = (int)$stmtExpiring7d->fetchColumn();

$expiringCookies = (int)$pdo->prepare("SELECT COUNT(*) FROM cookie_vault WHERE expires_at IS NOT NULL AND expires_at <= ?")->execute([$fortyEightHours]) ? $pdo->prepare("SELECT COUNT(*) FROM cookie_vault WHERE expires_at IS NOT NULL AND expires_at <= ?") : null;
$stmtEC = $pdo->prepare("SELECT COUNT(*) FROM cookie_vault WHERE expires_at IS NOT NULL AND expires_at <= ?");
$stmtEC->execute([$fortyEightHours]);
$expiringCookies = (int)$stmtEC->fetchColumn();

$stmtErrors = $pdo->prepare("SELECT COUNT(*) FROM login_attempt_logs WHERE status IN ('failed','blocked') AND created_at >= ?");
$stmtErrors->execute([$oneHourAgo]);
$errorsLastHour = (int)$stmtErrors->fetchColumn();

$stmtFailedLogins24 = $pdo->prepare("SELECT COUNT(*) FROM login_attempt_logs WHERE status = 'failed' AND created_at >= ?");
$stmtFailedLogins24->execute([$twentyFourHoursAgo]);
$failedLogins24h = (int)$stmtFailedLogins24->fetchColumn();

$stmtInactiveUsers = $pdo->prepare("
    SELECT COUNT(*) FROM users u 
    WHERE u.role = 'user' AND u.is_active = 1 
    AND NOT EXISTS (
        SELECT 1 FROM user_sessions us WHERE us.user_id = u.id AND us.last_activity >= ?
    )
");
$stmtInactiveUsers->execute([date('Y-m-d H:i:s', strtotime('-7 days'))]);
$inactiveUsers7d = (int)$stmtInactiveUsers->fetchColumn();

$totalPlatforms = (int)$pdo->query("SELECT COUNT(*) FROM platforms WHERE is_active = 1")->fetchColumn();

$stmtPlatformLoad = $pdo->prepare("
    SELECT 
        p.id,
        p.name,
        p.logo_url,
        (SELECT COUNT(*) FROM platform_accounts pa WHERE pa.platform_id = p.id AND pa.is_active = 1) AS total_slots,
        (SELECT COUNT(DISTINCT acs.account_id) FROM account_sessions acs 
         JOIN platform_accounts pa2 ON pa2.id = acs.account_id 
         WHERE pa2.platform_id = p.id AND acs.status = 'active' AND acs.last_active >= ?) AS active_slots,
        (SELECT COUNT(*) FROM user_subscriptions us WHERE us.platform_id = p.id AND us.is_active = 1 AND us.end_date >= ?) AS active_subs
    FROM platforms p
    WHERE p.is_active = 1
    ORDER BY p.name
");
$stmtPlatformLoad->execute([$fiveMinAgo, $today]);
$platformLoad = $stmtPlatformLoad->fetchAll();

foreach ($platformLoad as &$pl) {
    $pl['total_slots'] = (int)$pl['total_slots'];
    $pl['active_slots'] = (int)$pl['active_slots'];
    $pl['active_subs'] = (int)$pl['active_subs'];
    $pl['usage_pct'] = $pl['total_slots'] > 0 ? round(($pl['active_slots'] / $pl['total_slots']) * 100) : 0;
    if ($pl['usage_pct'] >= 85) $pl['pressure'] = 'overloaded';
    elseif ($pl['usage_pct'] >= 50) $pl['pressure'] = 'moderate';
    elseif ($pl['active_slots'] > 0) $pl['pressure'] = 'stable';
    else $pl['pressure'] = 'idle';
}
unset($pl);

$slotIntelligence = $pdo->query("
    SELECT 
        pa.id,
        p.name AS platform_name,
        pa.slot_name AS label,
        pa.health_status,
        pa.success_count,
        pa.fail_count,
        pa.last_success_at,
        pa.last_failed_at,
        pa.cooldown_until,
        pa.is_active,
        CASE WHEN (pa.success_count + pa.fail_count) > 0 
            THEN ROUND((CAST(pa.success_count AS FLOAT) / (pa.success_count + pa.fail_count)) * 100) 
            ELSE 100 END AS success_rate,
        (pa.success_count * 2) - (pa.fail_count * 3) AS score
    FROM platform_accounts pa
    JOIN platforms p ON p.id = pa.platform_id
    WHERE pa.is_active = 1
    ORDER BY score DESC
")->fetchAll();

$bestSlot = !empty($slotIntelligence) ? $slotIntelligence[0] : null;
$worstSlot = !empty($slotIntelligence) ? end($slotIntelligence) : null;

$recentEvents = $pdo->query("
    SELECT al.action, al.ip_address, al.created_at, u.username
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 30
")->fetchAll();

$alerts = [];

if ($expiringSubs24h > 0) {
    $alerts[] = ['type' => 'danger', 'message' => "$expiringSubs24h subscription(s) expiring in 24 hours"];
}
if ($expiringSubs7d > 3) {
    $alerts[] = ['type' => 'warning', 'message' => "$expiringSubs7d subscription(s) expiring within 7 days"];
}

foreach ($platformLoad as $pl) {
    if ($pl['pressure'] === 'overloaded') {
        $alerts[] = ['type' => 'danger', 'message' => "{$pl['name']} slots are overloaded ({$pl['usage_pct']}% used)"];
    }
    if ($pl['total_slots'] > 0 && ($pl['total_slots'] - $pl['active_slots']) <= 1) {
        $alerts[] = ['type' => 'warning', 'message' => "{$pl['name']} will be full soon — consider adding more slots"];
    }
}

if ($expiringCookies > 0) {
    $alerts[] = ['type' => 'warning', 'message' => "$expiringCookies cookie(s) expiring within 48 hours"];
}

if ($inactiveUsers7d > 5) {
    $alerts[] = ['type' => 'info', 'message' => "$inactiveUsers7d users inactive for 7+ days"];
}

if ($errorsLastHour > 10) {
    $alerts[] = ['type' => 'danger', 'message' => "High error rate: $errorsLastHour failed attempts in the last hour"];
}

$unhealthySlots = 0;
foreach ($slotIntelligence as $s) {
    if ($s['health_status'] === 'unhealthy') $unhealthySlots++;
}
if ($unhealthySlots > 0) {
    $alerts[] = ['type' => 'warning', 'message' => "$unhealthySlots slot(s) in unhealthy state"];
}

$systemHealth = 'healthy';
foreach ($alerts as $a) {
    if ($a['type'] === 'danger') { $systemHealth = 'critical'; break; }
    if ($a['type'] === 'warning') $systemHealth = 'warning';
}

$pendingPayments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();

jsonResponse([
    'success' => true,
    'system_health' => $systemHealth,
    'server_time' => $now,
    'kpi' => [
        'total_users' => $totalUsers,
        'new_users_today' => $newUsersToday,
        'active_users' => $activeUsers,
        'live_sessions' => $liveSessions,
        'active_subs' => $activeSubs,
        'new_subs_today' => $newSubsToday,
        'slot_utilization' => $slotUtilization,
        'total_slots' => $totalSlots,
        'used_slots' => $usedSlots,
        'total_platforms' => $totalPlatforms,
        'expiring_subs_24h' => $expiringSubs24h,
        'expiring_subs_7d' => $expiringSubs7d,
        'expiring_cookies' => $expiringCookies,
        'errors_last_hour' => $errorsLastHour,
        'failed_logins_24h' => $failedLogins24h,
        'inactive_users_7d' => $inactiveUsers7d,
        'pending_payments' => $pendingPayments,
    ],
    'platform_load' => $platformLoad,
    'slot_intelligence' => [
        'slots' => array_slice($slotIntelligence, 0, 20),
        'best_slot' => $bestSlot,
        'worst_slot' => $worstSlot,
        'unhealthy_count' => $unhealthySlots,
    ],
    'recent_events' => $recentEvents,
    'alerts' => $alerts,
]);
