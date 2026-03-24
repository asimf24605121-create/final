<?php
require_once __DIR__ . '/../db.php';
$pdo = getPDO();

$now = date("Y-m-d H:i:s");
echo "Seeding comprehensive test data...\n\n";

$userPass = password_hash("UserPass@123", PASSWORD_BCRYPT);
$users = [
    ["sarah_parker",   "Sarah Parker",     "sarah.parker@gmail.com",     "+1-555-0101", "US", "New York",    "female"],
    ["mike_johnson",   "Mike Johnson",     "mike.j@outlook.com",         "+44-7700-900","UK", "London",      "male"],
    ["emma_wilson",    "Emma Wilson",      "emma.w@yahoo.com",           "+61-412-345", "AU", "Sydney",      "female"],
    ["alex_chen",      "Alex Chen",        "alex.chen@proton.me",        "+86-138-0001","CN", "Shanghai",    "male"],
    ["fatima_ahmed",   "Fatima Ahmed",     "fatima.a@gmail.com",         "+971-50-123", "AE", "Dubai",       "female"],
    ["carlos_garcia",  "Carlos Garcia",    "carlos.g@hotmail.com",       "+34-612-345", "ES", "Madrid",      "male"],
    ["yuki_tanaka",    "Yuki Tanaka",      "yuki.t@docomo.ne.jp",        "+81-90-1234", "JP", "Tokyo",       "female"],
    ["david_miller",   "David Miller",     "david.m@gmail.com",          "+1-555-0202", "US", "Los Angeles", "male"],
    ["priya_sharma",   "Priya Sharma",     "priya.s@rediffmail.com",     "+91-98765",   "IN", "Mumbai",      "female"],
    ["james_brown",    "James Brown",      "james.b@icloud.com",         "+1-555-0303", "US", "Chicago",     "male"],
    ["lisa_nguyen",    "Lisa Nguyen",      "lisa.n@gmail.com",           "+84-912-345", "VN", "Ho Chi Minh", "female"],
    ["omar_hassan",    "Omar Hassan",      "omar.h@outlook.sa",          "+966-55-123", "SA", "Riyadh",      "male"],
    ["anna_kowalski",  "Anna Kowalski",    "anna.k@wp.pl",              "+48-512-345", "PL", "Warsaw",      "female"],
    ["ryan_otoole",    "Ryan O'Toole",     "ryan.o@gmail.com",           "+353-87-123", "IE", "Dublin",      "male"],
    ["maria_santos",   "Maria Santos",     "maria.s@gmail.com",          "+55-11-98765","BR", "São Paulo",   "female"],
];

$stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password_hash, role, is_active, name, email, phone, country, city, gender, profile_completed, created_at) VALUES (?, ?, 'user', 1, ?, ?, ?, ?, ?, ?, 1, ?)");

$createdDays = [45, 38, 32, 28, 25, 22, 18, 15, 12, 40, 8, 6, 5, 3, 1];
foreach ($users as $i => $u) {
    $created = date("Y-m-d H:i:s", strtotime("-{$createdDays[$i]} days"));
    $stmt->execute([$u[0], $userPass, $u[1], $u[2], $u[3], $u[4], $u[5], $u[6], $created]);
}
$pdo->prepare("UPDATE users SET is_active = 0 WHERE username = 'james_brown'")->execute();
echo "  15 users added\n";

$mgrPass = password_hash("Manager@123", PASSWORD_BCRYPT);
$pdo->prepare("INSERT OR IGNORE INTO users (username, password_hash, role, is_active, admin_level, name, email, created_at) VALUES (?, ?, 'admin', 1, 'manager', ?, ?, ?)")
    ->execute(["manager_ali", $mgrPass, "Ali (Manager)", "ali.manager@clearorbit.com", date("Y-m-d H:i:s", strtotime("-20 days"))]);
echo "  Manager admin added\n";

$allUsers = $pdo->query("SELECT id, username FROM users WHERE role='user'")->fetchAll();
$platforms = $pdo->query("SELECT id, name FROM platforms WHERE is_active=1")->fetchAll();

$slotStmt = $pdo->prepare("INSERT INTO platform_accounts (platform_id, slot_name, cookie_data, max_users, cookie_count, is_active, success_count, fail_count, last_success_at, last_failed_at, health_status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");

foreach ($platforms as $p) {
    $pid = $p['id'];
    $name = $p['name'];
    $existing = $pdo->prepare("SELECT COUNT(*) FROM platform_accounts WHERE platform_id=?");
    $existing->execute([$pid]);
    if ((int)$existing->fetchColumn() >= 3) continue;

    $slots = [
        ["Login 1", 5, rand(30,80), rand(0,5),  "healthy"],
        ["Login 2", 5, rand(20,60), rand(1,8),  "healthy"],
        ["Login 3", 3, rand(10,40), rand(5,15), rand(0,1) ? "degraded" : "healthy"],
    ];
    foreach ($slots as $s) {
        $lastSuccess = date("Y-m-d H:i:s", strtotime("-" . rand(1,120) . " minutes"));
        $lastFail = $s[3] > 3 ? date("Y-m-d H:i:s", strtotime("-" . rand(30,300) . " minutes")) : null;
        $health = $s[4];
        if ($s[3] > 10) $health = "unhealthy";
        $cookie = base64_encode("{$name}_session=test_cookie_" . bin2hex(random_bytes(8)) . ";");
        $created = date("Y-m-d H:i:s", strtotime("-" . rand(10,50) . " days"));
        $slotStmt->execute([$pid, $s[0], $cookie, $s[1], rand(5,25), 1, $s[2], $s[3], $lastSuccess, $lastFail, $health, $created, $now]);
    }
}
echo "  Platform login slots added\n";

$subStmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, platform_id, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?)");
foreach ($allUsers as $u) {
    $uid = $u['id'];
    $numSubs = rand(2, 5);
    $platIds = array_column($platforms, 'id');
    shuffle($platIds);
    $assignedPlats = array_slice($platIds, 0, $numSubs);
    foreach ($assignedPlats as $pid) {
        $startDays = rand(1, 30);
        $durations = [7, 30, 180, 365];
        $dur = $durations[array_rand($durations)];
        $start = date("Y-m-d", strtotime("-{$startDays} days"));
        $end = date("Y-m-d", strtotime("-{$startDays} days +{$dur} days"));
        $isActive = (strtotime($end) > time()) ? 1 : 0;
        $subStmt->execute([$uid, $pid, $start, $end, $isActive]);
    }
}
echo "  Subscriptions distributed\n";

$payStmt = $pdo->prepare("INSERT INTO payments (user_id, platform_id, username, duration_key, account_type, price, status, whatsapp_msg, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
$statuses = ["pending","pending","pending","approved","approved","approved","approved","rejected"];
$durationKeys = ["1_week","1_month","6_months","1_year"];
$types = ["shared","shared","shared","private"];
$prices = ["1_week" => [1.79, 2.99], "1_month" => [4.79, 7.99], "6_months" => [20.99, 34.99], "1_year" => [35.99, 59.99]];

foreach ($allUsers as $idx => $u) {
    if ($idx >= 12) break;
    $uid = $u['id'];
    $uname = $u['username'];
    $pid = $platforms[array_rand($platforms)]['id'];
    $dur = $durationKeys[array_rand($durationKeys)];
    $type = $types[array_rand($types)];
    $status = $statuses[array_rand($statuses)];
    $price = $type === "shared" ? $prices[$dur][0] : $prices[$dur][1];
    $created = date("Y-m-d H:i:s", strtotime("-" . rand(0, 20) . " days -" . rand(0,23) . " hours"));
    $msg = "Hi, I want to subscribe to {$type} access for " . str_replace("_"," ",$dur) . ". My username is {$uname}.";
    $payStmt->execute([$uid, $pid, $uname, $dur, $type, $price, $status, $msg, $created, $created]);
}
echo "  12 payments added\n";

$ticketStmt = $pdo->prepare("INSERT INTO support_tickets (user_id, platform_name, message, status, created_at) VALUES (?,?,?,?,?)");
$ticketData = [
    ["Netflix",    "I cannot access Netflix after cookie injection. Getting redirected to login page.",                "pending"],
    ["Spotify",    "Spotify keeps logging me out every 10 minutes. The cookies seem to expire too quickly.",           "pending"],
    ["ChatGPT",    "ChatGPT Plus features are not available even though my subscription is active.",                   "resolved"],
    ["Canva",      "Canva Pro features not showing. I see the free version instead.",                                 "pending"],
    ["Netflix",    "Getting 'Account on hold' message when trying to use Netflix.",                                   "resolved"],
    ["Udemy",      "Cannot download course materials. Access seems limited to viewing only.",                          "pending"],
    ["Coursera",   "Certificate download not working with shared access. Is this expected?",                          "resolved"],
    ["Grammarly",  "Grammarly extension not detecting the premium account cookies.",                                  "pending"],
];
foreach ($ticketData as $idx => $t) {
    $uid = $allUsers[min($idx, count($allUsers)-1)]['id'];
    $created = date("Y-m-d H:i:s", strtotime("-" . rand(0, 15) . " days -" . rand(0,12) . " hours"));
    $ticketStmt->execute([$uid, $t[0], $t[1], $t[2], $created]);
}
echo "  8 support tickets added\n";

$annStmt = $pdo->prepare("INSERT INTO announcements (title, message, type, status, start_time, end_time, created_at) VALUES (?,?,?,?,?,?,?)");
$announcements = [
    ["Scheduled Maintenance", "We will be performing scheduled maintenance on March 28, 2026 from 2:00 AM to 4:00 AM UTC. Some services may be temporarily unavailable.", "popup", "active", date("Y-m-d H:i:s", strtotime("+3 days")), date("Y-m-d H:i:s", strtotime("+4 days"))],
    ["New Platforms Added!", "We have added Grammarly and Skillshare to our platform lineup! Check your dashboard for access.", "notification", "active", date("Y-m-d H:i:s", strtotime("-2 days")), date("Y-m-d H:i:s", strtotime("+30 days"))],
    ["Holiday Sale - 30% Off", "Get 30% off on all yearly subscriptions! Use code SPRING2026 when contacting us. Valid until April 15.", "popup", "active", date("Y-m-d H:i:s"), date("Y-m-d H:i:s", strtotime("+21 days"))],
    ["Extension Update Required", "Please update your ClearOrbit Chrome Extension to v2.5 for improved cookie injection and security.", "notification", "inactive", null, null],
];
foreach ($announcements as $a) {
    $created = date("Y-m-d H:i:s", strtotime("-" . rand(1,10) . " days"));
    $annStmt->execute([$a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $created]);
}
echo "  4 announcements added\n";

$conStmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message, is_read, created_at) VALUES (?,?,?,?,?)");
$contacts = [
    ["Ahmed Al-Rashid",   "ahmed.r@gmail.com",       "I am interested in bulk access for my team of 10 people. Do you offer enterprise pricing?", 0],
    ["Sophie Martin",     "sophie.m@outlook.com",     "How secure is the cookie sharing method? I want to understand the privacy implications.", 1],
    ["Raj Patel",         "raj.p@yahoo.com",          "Can I get a refund for my Netflix subscription? The access stopped working after 2 days.", 0],
    ["Elena Volkov",      "elena.v@mail.ru",          "Do you support Hulu or Amazon Prime? I would like to see those platforms added.", 0],
    ["Tom Williams",      "tom.w@protonmail.com",     "Your Chrome extension is not working on Brave browser. Any fix for this?", 1],
    ["Aisha Khan",        "aisha.k@gmail.com",        "I love the service! Quick question - can I switch from shared to private mid-subscription?", 0],
    ["Lucas Fischer",     "lucas.f@gmx.de",           "Payment was sent via WhatsApp 3 days ago but my subscription is still not activated.", 0],
    ["Nina Petrova",      "nina.p@yandex.ru",         "Is there a mobile app or does the Chrome extension work on Android?", 1],
];
foreach ($contacts as $c) {
    $created = date("Y-m-d H:i:s", strtotime("-" . rand(0, 14) . " days -" . rand(0, 23) . " hours"));
    $conStmt->execute([$c[0], $c[1], $c[2], $c[3], $created]);
}
echo "  8 contact messages added\n";

$notifStmt = $pdo->prepare("INSERT INTO user_notifications (user_id, title, message, type, is_read, created_at) VALUES (?,?,?,?,?,?)");
$notifTemplates = [
    ["Subscription Activated",     "Your {PLAT} subscription is now active! Access it from your dashboard.", "success"],
    ["Subscription Expiring Soon", "Your {PLAT} subscription expires in 3 days. Consider extending it.",      "warning"],
    ["New Platform Available",     "{PLAT} has been added to ClearOrbit! Check it out.",                        "info"],
    ["Password Changed",           "Your password was changed successfully. If this wasn't you, contact support.", "info"],
    ["Slot Health Alert",          "The {PLAT} slot you were using has been degraded. You may be reassigned.", "warning"],
    ["Welcome to ClearOrbit!",     "Welcome! Complete your profile to unlock all features.",                   "success"],
];
foreach ($allUsers as $u) {
    $numNotifs = rand(2, 5);
    for ($n = 0; $n < $numNotifs; $n++) {
        $tmpl = $notifTemplates[array_rand($notifTemplates)];
        $plat = $platforms[array_rand($platforms)]['name'];
        $msg = str_replace("{PLAT}", $plat, $tmpl[1]);
        $title = str_replace("{PLAT}", $plat, $tmpl[0]);
        $isRead = rand(0, 1);
        $created = date("Y-m-d H:i:s", strtotime("-" . rand(0, 20) . " days -" . rand(0, 23) . " hours"));
        $notifStmt->execute([$u['id'], $title, $msg, $tmpl[2], $isRead, $created]);
    }
}
echo "  User notifications added\n";

$lhStmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, device_type, browser, os, action, created_at) VALUES (?,?,?,?,?,?,?,?)");
$browsers = ["Chrome 122","Firefox 124","Safari 17","Edge 122","Chrome 121","Opera 108"];
$oses = ["Windows 11","macOS 14","Ubuntu 22","iOS 17","Android 14","Windows 10"];
$deviceTypes = ["desktop","desktop","desktop","mobile","mobile","tablet"];
$actions = ["login","login","login","login","logout","force_logout"];

foreach ($allUsers as $u) {
    $numLogins = rand(3, 8);
    for ($l = 0; $l < $numLogins; $l++) {
        $ip = rand(10,223) . "." . rand(0,255) . "." . rand(0,255) . "." . rand(1,254);
        $browser = $browsers[array_rand($browsers)];
        $os = $oses[array_rand($oses)];
        $dt = $deviceTypes[array_rand($deviceTypes)];
        $action = $actions[array_rand($actions)];
        $ua = "Mozilla/5.0 ({$os}) {$browser}";
        $created = date("Y-m-d H:i:s", strtotime("-" . rand(0, 30) . " days -" . rand(0,23) . " hours -" . rand(0,59) . " minutes"));
        $lhStmt->execute([$u['id'], $ip, $ua, $dt, $browser, $os, $action, $created]);
    }
}
echo "  Login history added\n";

$laStmt = $pdo->prepare("INSERT INTO login_attempt_logs (username, ip_address, user_agent, device_type, browser, os, status, reason, created_at) VALUES (?,?,?,?,?,?,?,?,?)");
$attemptData = [
    ["unknown_hacker",           "185.220.101.42", "failed",   "Invalid credentials"],
    ["admin",                    "103.25.67.89",   "failed",   "Invalid credentials"],
    ["test123",                  "45.33.32.156",   "failed",   "User not found"],
    ["asimf24605121@gmail.com",  "127.0.0.1",      "success",  null],
    ["sarah_parker",             "73.162.45.100",  "success",  null],
    ["brute_force_attempt",      "185.220.101.42", "blocked",  "Rate limit exceeded"],
    ["mike_johnson",             "82.132.248.70",  "success",  null],
    ["disabled_account",         "192.168.1.50",   "disabled", "Account deactivated"],
    ["emma_wilson",              "203.219.180.44", "success",  null],
    ["sql_injection_try",        "45.33.32.156",   "failed",   "Invalid credentials"],
    ["admin OR 1=1",             "185.220.101.42", "failed",   "Invalid credentials"],
    ["carlos_garcia",            "88.27.163.55",   "success",  null],
];
foreach ($attemptData as $a) {
    $browser = $browsers[array_rand($browsers)];
    $os = $oses[array_rand($oses)];
    $dt = $deviceTypes[array_rand($deviceTypes)];
    $ua = "Mozilla/5.0 ({$os}) {$browser}";
    $created = date("Y-m-d H:i:s", strtotime("-" . rand(0, 10) . " days -" . rand(0,23) . " hours"));
    $laStmt->execute([$a[0], $a[1], $ua, $dt, $browser, $os, $a[2], $a[3], $created]);
}
echo "  Login attempt logs added\n";

$alStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, created_at) VALUES (?,?,?,?)");
$adminId = $pdo->query("SELECT id FROM users WHERE username='asimf24605121@gmail.com'")->fetchColumn();
$activityTemplates = [
    "Admin logged in",
    "Added new user: maria_santos",
    "Toggled platform Netflix to inactive",
    "Toggled platform Netflix to active",
    "Approved payment #5 for sarah_parker",
    "Updated pricing for ChatGPT",
    "Created announcement: Holiday Sale - 30% Off",
    "Granted subscription: mike_johnson → Spotify (1 month)",
    "Resolved support ticket #3",
    "Updated WhatsApp config for Netflix",
    "Killed suspicious session for user fatima_ahmed",
    "Added platform account Login 3 for Canva",
    "Extended subscription for emma_wilson → ChatGPT (+30 days)",
    "Reviewed contact message from Ahmed Al-Rashid",
    "Rejected payment #8 for james_brown (inactive account)",
];
foreach ($allUsers as $u) {
    $alStmt->execute([$u['id'], "User logged in", rand(10,223).".".rand(0,255).".".rand(0,255).".".rand(1,254), date("Y-m-d H:i:s", strtotime("-".rand(0,5)." days"))]);
    $alStmt->execute([$u['id'], "Accessed platform " . $platforms[array_rand($platforms)]['name'], rand(10,223).".".rand(0,255).".".rand(0,255).".".rand(1,254), date("Y-m-d H:i:s", strtotime("-".rand(0,5)." days"))]);
}
foreach ($activityTemplates as $action) {
    $alStmt->execute([$adminId, $action, "127.0.0.1", date("Y-m-d H:i:s", strtotime("-".rand(0,14)." days -".rand(0,23)." hours"))]);
}
echo "  Activity logs added\n";

$asStmt = $pdo->prepare("INSERT INTO account_sessions (account_id, user_id, platform_id, status, device_type, last_active, created_at) VALUES (?,?,?,?,?,?,?)");
$slots = $pdo->query("SELECT id, platform_id FROM platform_accounts WHERE is_active=1 LIMIT 10")->fetchAll();
foreach ($allUsers as $idx => $u) {
    if ($idx >= 8) break;
    $slot = $slots[$idx % count($slots)];
    $dt = $deviceTypes[array_rand($deviceTypes)];
    $active = date("Y-m-d H:i:s", strtotime("-" . rand(1,30) . " minutes"));
    $status = rand(0,3) > 0 ? "active" : "inactive";
    $asStmt->execute([$slot['id'], $u['id'], $slot['platform_id'], $status, $dt, $active, date("Y-m-d H:i:s", strtotime("-" . rand(1,120) . " minutes"))]);
}
echo "  Account sessions added\n";

$waStmt = $pdo->prepare("UPDATE whatsapp_config SET shared_number=?, private_number=? WHERE platform_id=?");
$waNumbers = [
    [1, "+1-555-0100", "+1-555-0101"],
    [2, "+1-555-0200", "+1-555-0201"],
    [4, "+1-555-0400", "+1-555-0401"],
    [5, "+1-555-0500", "+1-555-0501"],
    [6, "+1-555-0600", "+1-555-0601"],
    [7, "+1-555-0700", "+1-555-0701"],
];
foreach ($waNumbers as $w) {
    $waStmt->execute([$w[1], $w[2], $w[0]]);
}
echo "  WhatsApp config updated\n";

echo "\n=== FINAL DATA COUNTS ===\n";
$tables = ["users","platforms","user_subscriptions","cookie_vault","platform_accounts","account_sessions","activity_logs","login_attempt_logs","payments","support_tickets","announcements","contact_messages","user_notifications","login_history","whatsapp_config","pricing_plans"];
foreach ($tables as $t) {
    $c = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "  {$t}: {$c}\n";
}
echo "\nAll test data seeded successfully!\n";
