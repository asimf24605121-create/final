<?php
ob_start();

if (empty(getenv('REPLIT_DEV_DOMAIN')) && file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$_isReplitEnv = !empty(getenv('REPLIT_DEV_DOMAIN'));
if (session_status() === PHP_SESSION_NONE) {
    if ($_isReplitEnv) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => true,
            'httponly'  => true,
            'samesite'  => 'None',
        ]);
    } else {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly'  => true,
            'samesite'  => 'Lax',
        ]);
    }
}

$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

function _corsOriginAllowed(string $origin): bool {
    if ($origin === '') return false;

    $parsed = parse_url($origin);
    $host   = $parsed['host'] ?? '';

    $replitDomain = getenv('REPLIT_DEV_DOMAIN') ?: '';
    if ($replitDomain !== '' && $host === $replitDomain) {
        return true;
    }

    $extra = array_filter(array_map('trim', explode(',', getenv('ALLOWED_ORIGINS') ?: '')));
    if (in_array($origin, $extra, true)) {
        return true;
    }

    return false;
}

if (_corsOriginAllowed($requestOrigin)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(204);
    exit;
}

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $driver = getenv('DB_DRIVER') ?: 'sqlite';

    try {
        if ($driver === 'mysql') {
            $host   = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'shared_access_db';
            $user   = getenv('DB_USER') ?: 'root';
            $pass   = getenv('DB_PASS') ?: '';
            $dsn    = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $opts   = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            $pdo = new PDO($dsn, $user, $pass, $opts);
        } else {
            $dataDir = __DIR__ . '/data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            $pdo = new PDO('sqlite:' . $dataDir . '/shared_access.sqlite', null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
            initSQLite($pdo);
        }
    } catch (PDOException $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }

    return $pdo;
}

function initSQLite(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        username       TEXT    NOT NULL UNIQUE,
        password_hash  TEXT    NOT NULL,
        role           TEXT    NOT NULL DEFAULT 'user' CHECK(role IN ('admin','user')),
        is_active      INTEGER NOT NULL DEFAULT 1,
        device_id      TEXT    NULL DEFAULT NULL,
        last_login_ip  TEXT    NULL DEFAULT NULL,
        created_at     TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS platforms (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        name         TEXT    NOT NULL,
        logo_url     TEXT    NULL DEFAULT NULL,
        bg_color_hex TEXT    NOT NULL DEFAULT '#1e293b',
        is_active    INTEGER NOT NULL DEFAULT 1
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cookie_vault (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        platform_id   INTEGER NOT NULL REFERENCES platforms(id) ON DELETE CASCADE,
        cookie_string TEXT    NOT NULL,
        expires_at    TEXT    NULL DEFAULT NULL,
        updated_at    TEXT    NOT NULL DEFAULT (datetime('now')),
        cookie_count  INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_subscriptions (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        platform_id INTEGER NOT NULL REFERENCES platforms(id) ON DELETE CASCADE,
        start_date  TEXT    NOT NULL,
        end_date    TEXT    NOT NULL,
        is_active   INTEGER NOT NULL DEFAULT 1
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
        action     TEXT    NOT NULL,
        ip_address TEXT    NULL DEFAULT NULL,
        created_at TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT    NOT NULL,
        attempted_at TEXT  NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        session_token   TEXT    NOT NULL,
        device_id       TEXT    NOT NULL,
        ip_address      TEXT    NULL DEFAULT NULL,
        user_agent      TEXT    NULL DEFAULT NULL,
        device_type     TEXT    NOT NULL DEFAULT 'desktop',
        browser         TEXT    NOT NULL DEFAULT 'Unknown',
        os              TEXT    NOT NULL DEFAULT 'Unknown',
        status          TEXT    NOT NULL DEFAULT 'active' CHECK(status IN ('active','inactive')),
        last_activity   TEXT    NOT NULL DEFAULT (datetime('now')),
        created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_history (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        ip_address  TEXT    NULL DEFAULT NULL,
        user_agent  TEXT    NULL DEFAULT NULL,
        device_type TEXT    NOT NULL DEFAULT 'desktop',
        browser     TEXT    NOT NULL DEFAULT 'Unknown',
        os          TEXT    NOT NULL DEFAULT 'Unknown',
        action      TEXT    NOT NULL DEFAULT 'login' CHECK(action IN ('login','logout','force_logout','blocked')),
        created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pricing_plans (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        platform_id  INTEGER NOT NULL REFERENCES platforms(id) ON DELETE CASCADE,
        duration_key TEXT    NOT NULL CHECK(duration_key IN ('1_week','1_month','6_months','1_year')),
        shared_price REAL    NOT NULL DEFAULT 0,
        private_price REAL   NOT NULL DEFAULT 0,
        UNIQUE(platform_id, duration_key)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_config (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        platform_id   INTEGER NOT NULL REFERENCES platforms(id) ON DELETE CASCADE,
        shared_number TEXT    NOT NULL DEFAULT '',
        private_number TEXT   NOT NULL DEFAULT '',
        UNIQUE(platform_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
        platform_id  INTEGER NOT NULL REFERENCES platforms(id) ON DELETE CASCADE,
        username     TEXT    NOT NULL DEFAULT '',
        duration_key TEXT    NOT NULL,
        account_type TEXT    NOT NULL DEFAULT 'shared' CHECK(account_type IN ('shared','private')),
        price        REAL    NOT NULL DEFAULT 0,
        status       TEXT    NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
        whatsapp_msg TEXT    NULL DEFAULT NULL,
        created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
        updated_at   TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $uCols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('admin_level', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN admin_level TEXT NULL DEFAULT NULL");
        $pdo->exec("UPDATE users SET admin_level = 'super_admin' WHERE role = 'admin'");
    }
    if (!in_array('name', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN name TEXT NULL DEFAULT NULL");
    }
    if (!in_array('email', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT NULL DEFAULT NULL");
    }
    if (!in_array('phone', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT NULL DEFAULT NULL");
    }
    if (!in_array('expiry_date', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN expiry_date TEXT NULL DEFAULT NULL");
    }
    if (!in_array('country', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN country TEXT NULL DEFAULT NULL");
    }
    if (!in_array('city', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN city TEXT NULL DEFAULT NULL");
    }
    if (!in_array('gender', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN gender TEXT NULL DEFAULT NULL");
    }
    if (!in_array('profile_image', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_image TEXT NULL DEFAULT NULL");
    }
    if (!in_array('profile_completed', $uCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_completed INTEGER NOT NULL DEFAULT 0");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        platform_name TEXT    NOT NULL,
        message       TEXT    NOT NULL,
        status        TEXT    NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','resolved')),
        created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        title      TEXT    NOT NULL,
        message    TEXT    NOT NULL,
        type       TEXT    NOT NULL DEFAULT 'popup',
        status     TEXT    NOT NULL DEFAULT 'active' CHECK(status IN ('active','inactive')),
        start_time TEXT    DEFAULT NULL,
        end_time   TEXT    DEFAULT NULL,
        created_at TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $cols = $pdo->query("PRAGMA table_info(announcements)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('type', $cols)) {
        $pdo->exec("ALTER TABLE announcements ADD COLUMN type TEXT NOT NULL DEFAULT 'popup'");
    }
    if (!in_array('start_time', $cols)) {
        $pdo->exec("ALTER TABLE announcements ADD COLUMN start_time TEXT DEFAULT NULL");
    }
    if (!in_array('end_time', $cols)) {
        $pdo->exec("ALTER TABLE announcements ADD COLUMN end_time TEXT DEFAULT NULL");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NULL REFERENCES users(id) ON DELETE CASCADE,
        title      TEXT    NOT NULL,
        message    TEXT    NOT NULL,
        type       TEXT    NOT NULL DEFAULT 'info' CHECK(type IN ('info','success','warning')),
        is_read    INTEGER NOT NULL DEFAULT 0,
        created_at TEXT    NOT NULL DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_un_user ON user_notifications(user_id, is_read)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_accounts (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        platform_id     INTEGER NOT NULL REFERENCES platforms(id) ON DELETE CASCADE,
        slot_name       TEXT    NOT NULL DEFAULT 'Login 1',
        cookie_data     TEXT    NOT NULL DEFAULT '',
        max_users       INTEGER NOT NULL DEFAULT 5,
        cookie_count    INTEGER NOT NULL DEFAULT 0,
        expires_at      TEXT    NULL DEFAULT NULL,
        is_active       INTEGER NOT NULL DEFAULT 1,
        success_count   INTEGER NOT NULL DEFAULT 0,
        fail_count      INTEGER NOT NULL DEFAULT 0,
        last_success_at TEXT    NULL DEFAULT NULL,
        last_failed_at  TEXT    NULL DEFAULT NULL,
        health_status   TEXT    NOT NULL DEFAULT 'healthy',
        cooldown_until  TEXT    NULL DEFAULT NULL,
        created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
        updated_at      TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $cols = $pdo->query("PRAGMA table_info(platform_accounts)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('success_count', $cols)) {
        $pdo->exec("ALTER TABLE platform_accounts ADD COLUMN success_count INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE platform_accounts ADD COLUMN fail_count INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE platform_accounts ADD COLUMN last_success_at TEXT NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE platform_accounts ADD COLUMN last_failed_at TEXT NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE platform_accounts ADD COLUMN health_status TEXT NOT NULL DEFAULT 'healthy'");
    }
    if (!in_array('cooldown_until', $cols)) {
        $pdo->exec("ALTER TABLE platform_accounts ADD COLUMN cooldown_until TEXT NULL DEFAULT NULL");
    }
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pa_platform ON platform_accounts(platform_id, is_active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pa_scoring ON platform_accounts(platform_id, is_active, health_status, success_count, fail_count)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS account_sessions (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id    INTEGER NOT NULL REFERENCES platform_accounts(id) ON DELETE CASCADE,
        user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        platform_id   INTEGER NOT NULL REFERENCES platforms(id) ON DELETE CASCADE,
        status        TEXT    NOT NULL DEFAULT 'active' CHECK(status IN ('active','inactive')),
        device_type   TEXT    NOT NULL DEFAULT 'desktop',
        last_active   TEXT    NOT NULL DEFAULT (datetime('now')),
        created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_as_account ON account_sessions(account_id, status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_as_user ON account_sessions(user_id, platform_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_as_status ON account_sessions(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_as_last_active ON account_sessions(last_active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_as_active_lookup ON account_sessions(account_id, status, last_active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_as_user_status ON account_sessions(user_id, platform_id, status)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT    NOT NULL,
        email      TEXT    NOT NULL,
        message    TEXT    NOT NULL,
        is_read    INTEGER NOT NULL DEFAULT 0,
        created_at TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token      TEXT    NOT NULL UNIQUE,
        expires_at TEXT    NOT NULL,
        used       INTEGER NOT NULL DEFAULT 0,
        created_at TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempt_logs (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        username     TEXT    NOT NULL DEFAULT '',
        ip_address   TEXT    NOT NULL DEFAULT '',
        user_agent   TEXT    NULL DEFAULT NULL,
        device_type  TEXT    NOT NULL DEFAULT 'desktop',
        browser      TEXT    NOT NULL DEFAULT 'Unknown',
        os           TEXT    NOT NULL DEFAULT 'Unknown',
        status       TEXT    NOT NULL DEFAULT 'failed' CHECK(status IN ('success','failed','blocked','disabled')),
        reason       TEXT    NULL DEFAULT NULL,
        created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
    )");

    $cols = $pdo->query("PRAGMA table_info(cookie_vault)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('cookie_count', $cols, true)) {
        $pdo->exec("ALTER TABLE cookie_vault ADD COLUMN cookie_count INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('slot', $cols, true)) {
        $pdo->exec("ALTER TABLE cookie_vault ADD COLUMN slot INTEGER NOT NULL DEFAULT 1");
    }

    $platCols = $pdo->query("PRAGMA table_info(platforms)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('cookie_domain', $platCols, true)) {
        $pdo->exec("ALTER TABLE platforms ADD COLUMN cookie_domain TEXT NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE platforms ADD COLUMN login_url TEXT NULL DEFAULT NULL");

        $domainMap = [
            'Netflix'    => ['.netflix.com',    'https://www.netflix.com/'],
            'Spotify'    => ['.spotify.com',    'https://open.spotify.com/'],
            'Disney+'    => ['.disneyplus.com', 'https://www.disneyplus.com/'],
            'ChatGPT'    => ['.openai.com',     'https://chat.openai.com/'],
            'Canva'      => ['.canva.com',      'https://www.canva.com/'],
            'Udemy'      => ['.udemy.com',      'https://www.udemy.com/'],
            'Coursera'   => ['.coursera.org',   'https://www.coursera.org/'],
            'Skillshare' => ['.skillshare.com', 'https://www.skillshare.com/'],
            'Grammarly'  => ['.grammarly.com',  'https://app.grammarly.com/'],
        ];
        $upd = $pdo->prepare("UPDATE platforms SET cookie_domain = ?, login_url = ? WHERE name = ?");
        foreach ($domainMap as $name => [$domain, $url]) {
            $upd->execute([$domain, $url, $name]);
        }
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        $adminHash = password_hash('W@$!@$!m1009388', PASSWORD_BCRYPT);
        $userHash  = password_hash('password',  PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, is_active, admin_level, name, email, phone) VALUES (?, ?, ?, 1, ?, ?, ?, ?)");
        $stmt->execute(['asimf24605121@gmail.com',    $adminHash, 'admin', 'super_admin', 'Asim (Owner)', 'asimf24605121@gmail.com', '']);
        $stmt->execute(['john_doe', $userHash,  'user',  null, 'John Doe', 'john@example.com', '+1234567890']);

        $stmt2 = $pdo->prepare("INSERT INTO platforms (name, logo_url, bg_color_hex, is_active, cookie_domain, login_url) VALUES (?, ?, ?, 1, ?, ?)");
        $stmt2->execute(['Netflix',    'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg', '#e50914', '.netflix.com',    'https://www.netflix.com/']);
        $stmt2->execute(['Spotify',    'https://upload.wikimedia.org/wikipedia/commons/2/26/Spotify_logo_with_text.svg', '#1db954', '.spotify.com',    'https://open.spotify.com/']);
        $stmt2->execute(['Disney+',    'https://upload.wikimedia.org/wikipedia/commons/3/3e/Disney%2B_logo.svg', '#0063e5', '.disneyplus.com', 'https://www.disneyplus.com/']);
        $stmt2->execute(['ChatGPT',    'https://upload.wikimedia.org/wikipedia/commons/0/04/ChatGPT_logo.svg', '#10a37f', '.openai.com',     'https://chat.openai.com/']);
        $stmt2->execute(['Canva',      'https://upload.wikimedia.org/wikipedia/commons/0/08/Canva_icon_2021.svg', '#7d2ae8', '.canva.com',      'https://www.canva.com/']);
        $stmt2->execute(['Udemy',      'https://upload.wikimedia.org/wikipedia/commons/e/e3/Udemy_logo.svg', '#a435f0', '.udemy.com',      'https://www.udemy.com/']);
        $stmt2->execute(['Coursera',   'https://upload.wikimedia.org/wikipedia/commons/9/97/Coursera-Logo_600x600.svg', '#0056d2', '.coursera.org',   'https://www.coursera.org/']);
        $stmt2->execute(['Skillshare', 'https://upload.wikimedia.org/wikipedia/commons/2/2e/Skillshare_logo.svg', '#00ff84', '.skillshare.com', 'https://www.skillshare.com/']);
        $stmt2->execute(['Grammarly',  'https://upload.wikimedia.org/wikipedia/commons/a/a0/Grammarly_Logo.svg', '#15c39a', '.grammarly.com',  'https://app.grammarly.com/']);

        $stmtC = $pdo->prepare("INSERT INTO cookie_vault (platform_id, cookie_string, expires_at) VALUES (?, ?, datetime('now', '+30 days'))");
        $stmtC->execute([1, base64_encode('NetflixId=sample_cookie_value_here; nfvdid=sample_device_id;')]);
        $stmtC->execute([2, base64_encode('sp_dc=sample_spotify_cookie_here; sp_key=sample_key;')]);
        $stmtC->execute([3, base64_encode('disney_token=sample_disney_cookie; dss_id=sample_dss;')]);
        $stmtC->execute([4, base64_encode('chatgpt_session=sample_chatgpt_cookie; cf_clearance=sample;')]);
        $stmtC->execute([5, base64_encode('canva_session=sample_canva_cookie; csrf=sample_token;')]);

        $stmtS = $pdo->prepare("INSERT INTO user_subscriptions (user_id, platform_id, start_date, end_date, is_active) VALUES (?, ?, date('now'), ?, 1)");
        $stmtS->execute([2, 1, date('Y-m-d', strtotime('+30 days'))]);
        $stmtS->execute([2, 2, date('Y-m-d', strtotime('+15 days'))]);
        $stmtS->execute([2, 3, date('Y-m-d', strtotime('+22 days'))]);
        $stmtS->execute([2, 4, date('Y-m-d', strtotime('+45 days'))]);
        $stmtS->execute([2, 5, date('Y-m-d', strtotime('+7 days'))]);

        $stmtPr = $pdo->prepare("INSERT OR IGNORE INTO pricing_plans (platform_id, duration_key, shared_price, private_price) VALUES (?, ?, ?, ?)");
        $defaultPricing = [
            [1, '1_week', 1.79, 2.99], [1, '1_month', 4.79, 7.99], [1, '6_months', 20.99, 34.99], [1, '1_year', 35.99, 59.99],
            [2, '1_week', 1.19, 1.99], [2, '1_month', 2.99, 4.99], [2, '6_months', 14.99, 24.99], [2, '1_year', 26.99, 44.99],
            [3, '1_week', 1.49, 2.49], [3, '1_month', 4.19, 6.99], [3, '6_months', 17.99, 29.99], [3, '1_year', 29.99, 49.99],
            [4, '1_week', 2.39, 3.99], [4, '1_month', 5.99, 9.99], [4, '6_months', 26.99, 44.99], [4, '1_year', 47.99, 79.99],
            [5, '1_week', 1.19, 1.99], [5, '1_month', 3.59, 5.99], [5, '6_months', 17.99, 29.99], [5, '1_year', 29.99, 49.99],
            [6, '1_week', 1.49, 2.49], [6, '1_month', 3.99, 6.99], [6, '6_months', 18.99, 31.99], [6, '1_year', 32.99, 54.99],
            [7, '1_week', 1.49, 2.49], [7, '1_month', 3.99, 6.99], [7, '6_months', 18.99, 31.99], [7, '1_year', 32.99, 54.99],
            [8, '1_week', 1.19, 1.99], [8, '1_month', 2.99, 4.99], [8, '6_months', 14.99, 24.99], [8, '1_year', 26.99, 44.99],
            [9, '1_week', 1.79, 2.99], [9, '1_month', 4.79, 7.99], [9, '6_months', 22.99, 37.99], [9, '1_year', 39.99, 66.99],
        ];
        foreach ($defaultPricing as $pr) {
            $stmtPr->execute($pr);
        }

        $stmtWa = $pdo->prepare("INSERT OR IGNORE INTO whatsapp_config (platform_id, shared_number, private_number) VALUES (?, ?, ?)");
        for ($pid = 1; $pid <= 9; $pid++) {
            $stmtWa->execute([$pid, '1234567890', '1234567890']);
        }
    }
}

function jsonResponse(array $data, int $statusCode = 200): void {
    ob_end_clean();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getClientIP(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

function generateCsrfToken(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? json_decode(file_get_contents('php://input'), true)['csrf_token']
        ?? '';

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonResponse(['success' => false, 'message' => 'Invalid or missing CSRF token.'], 403);
    }
}

function checkRateLimit(string $ip, int $maxAttempts = 5, int $windowMinutes = 15): void {
    $pdo = getPDO();
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));

    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < ?")->execute([$cutoff]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at >= ?");
    $stmt->execute([$ip, $cutoff]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $maxAttempts) {
        jsonResponse(['success' => false, 'message' => 'Too many login attempts. Please try again later.'], 429);
    }
}

function recordLoginAttempt(string $ip): void {
    $pdo = getPDO();
    $pdo->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)")->execute([$ip]);
}

function clearLoginAttempts(string $ip): void {
    $pdo = getPDO();
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
}

function logActivity(int $userId, string $action, string $ip = ''): void {
    if ($ip === '') {
        $ip = getClientIP();
    }
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $action, $ip]);
}

function autoExpireSubscriptions(): void {
    $pdo = getPDO();
    $today = date('Y-m-d');
    $pdo->prepare("UPDATE user_subscriptions SET is_active = 0 WHERE is_active = 1 AND end_date < ?")->execute([$today]);
}

function parseUserAgent(string $ua): array {
    $result = ['device_type' => 'desktop', 'browser' => 'Unknown', 'os' => 'Unknown'];

    if (preg_match('/Mobile|Android.*Mobile|iPhone|iPod/i', $ua)) {
        $result['device_type'] = 'mobile';
    } elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
        $result['device_type'] = 'tablet';
    }

    if (preg_match('/Edg[e\/]?\s*(\d+)/i', $ua)) {
        $result['browser'] = 'Edge';
    } elseif (preg_match('/OPR\/|Opera/i', $ua)) {
        $result['browser'] = 'Opera';
    } elseif (preg_match('/Chrome\/(\d+)/i', $ua)) {
        $result['browser'] = 'Chrome';
    } elseif (preg_match('/Firefox\/(\d+)/i', $ua)) {
        $result['browser'] = 'Firefox';
    } elseif (preg_match('/Safari\/(\d+)/i', $ua) && !preg_match('/Chrome/i', $ua)) {
        $result['browser'] = 'Safari';
    }

    if (preg_match('/Windows NT/i', $ua)) {
        $result['os'] = 'Windows';
    } elseif (preg_match('/Mac OS X/i', $ua)) {
        $result['os'] = 'macOS';
    } elseif (preg_match('/Linux/i', $ua) && !preg_match('/Android/i', $ua)) {
        $result['os'] = 'Linux';
    } elseif (preg_match('/Android/i', $ua)) {
        $result['os'] = 'Android';
    } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
        $result['os'] = 'iOS';
    } elseif (preg_match('/CrOS/i', $ua)) {
        $result['os'] = 'Chrome OS';
    }

    return $result;
}

function createUserSession(int $userId, string $deviceId, string $ip, string $ua): string {
    $pdo = getPDO();
    $parsed = parseUserAgent($ua);
    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, device_id, ip_address, user_agent, device_type, browser, os) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $token, $deviceId, $ip, $ua, $parsed['device_type'], $parsed['browser'], $parsed['os']]);

    return $token;
}

function getActiveSessionCount(int $userId): int {
    $pdo = getPDO();
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . SESSION_INACTIVITY_TIMEOUT_MINUTES . ' minutes'));
    $pdo->prepare("UPDATE user_sessions SET status = 'inactive', logout_reason = 'Session expired due to inactivity' WHERE user_id = ? AND status = 'active' AND last_activity < ? AND logout_reason IS NULL")->execute([$userId, $cutoff]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function deactivateUserSession(string $sessionToken, string $reason = 'Logged out'): void {
    $pdo = getPDO();
    $pdo->prepare("UPDATE user_sessions SET status = 'inactive', logout_reason = ? WHERE session_token = ?")->execute([$reason, $sessionToken]);
}

function deactivateAllUserSessions(int $userId, ?string $exceptToken = null, string $reason = 'Logged out'): int {
    $pdo = getPDO();
    if ($exceptToken) {
        $stmt = $pdo->prepare("UPDATE user_sessions SET status = 'inactive', logout_reason = ? WHERE user_id = ? AND status = 'active' AND session_token != ?");
        $stmt->execute([$reason, $userId, $exceptToken]);
    } else {
        $stmt = $pdo->prepare("UPDATE user_sessions SET status = 'inactive', logout_reason = ? WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$reason, $userId]);
    }
    return $stmt->rowCount();
}

function touchSession(string $sessionToken): void {
    $pdo = getPDO();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE user_sessions SET last_activity = ? WHERE session_token = ? AND status = 'active'")->execute([$now, $sessionToken]);
}

const SESSION_INACTIVITY_TIMEOUT_MINUTES = 30;

function validateSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized.', 'session_expired' => true, 'logout_reason' => 'no_session'], 401);
    }
    $sessionToken = $_SESSION['session_token'] ?? null;
    if (!$sessionToken) {
        session_destroy();
        jsonResponse(['success' => false, 'message' => 'Invalid session.', 'session_expired' => true, 'logout_reason' => 'invalid_session'], 401);
    }
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, status, last_activity, logout_reason FROM user_sessions WHERE session_token = ?");
    $stmt->execute([$sessionToken]);
    $sess = $stmt->fetch();
    if (!$sess || $sess['status'] !== 'active') {
        $reason = $sess['logout_reason'] ?? 'Session has been terminated';
        session_destroy();
        jsonResponse(['success' => false, 'message' => $reason, 'session_expired' => true, 'logout_reason' => $reason], 401);
    }
    $lastActivity = strtotime($sess['last_activity']);
    $timeout = SESSION_INACTIVITY_TIMEOUT_MINUTES * 60;
    if ($lastActivity && (time() - $lastActivity) > $timeout) {
        $pdo->prepare("UPDATE user_sessions SET status = 'inactive', logout_reason = 'Session expired due to inactivity' WHERE session_token = ?")->execute([$sessionToken]);
        logLoginHistory((int)$_SESSION['user_id'], 'logout', getClientIP(), $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        session_destroy();
        jsonResponse(['success' => false, 'message' => 'Session expired due to inactivity.', 'session_expired' => true, 'logout_reason' => 'Session expired due to inactivity'], 401);
    }
    touchSession($sessionToken);
}

function getActiveSessionByDeviceType(int $userId, string $deviceType): ?array {
    $pdo = getPDO();
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . SESSION_INACTIVITY_TIMEOUT_MINUTES . ' minutes'));
    $pdo->prepare("UPDATE user_sessions SET status = 'inactive', logout_reason = 'Session expired due to inactivity' WHERE user_id = ? AND status = 'active' AND last_activity < ? AND logout_reason IS NULL")->execute([$userId, $cutoff]);
    $stmt = $pdo->prepare("SELECT id, session_token, device_id, ip_address, browser, os FROM user_sessions WHERE user_id = ? AND device_type = ? AND status = 'active' ORDER BY last_activity DESC LIMIT 1");
    $stmt->execute([$userId, $deviceType]);
    return $stmt->fetch() ?: null;
}

function logLoginAttempt(string $username, string $ip, string $ua, string $status, ?string $reason = null): void {
    $pdo = getPDO();
    $parsed = parseUserAgent($ua);
    $stmt = $pdo->prepare("INSERT INTO login_attempt_logs (username, ip_address, user_agent, device_type, browser, os, status, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$username, $ip, $ua, $parsed['device_type'], $parsed['browser'], $parsed['os'], $status, $reason]);
}

const PERMANENT_SUPER_ADMIN_EMAIL = 'asimf24605121@gmail.com';

function isPermanentSuperAdmin(int $userId): bool {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT email, admin_level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user && $user['email'] === PERMANENT_SUPER_ADMIN_EMAIL && $user['admin_level'] === 'super_admin';
}

function checkAdminAccess(string $requiredLevel = 'super_admin'): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    validateSession();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized.', 'session_expired' => true, 'logout_reason' => 'Unauthorized'], 401);
    }
    $level = $_SESSION['admin_level'] ?? 'manager';
    if ($requiredLevel === 'super_admin' && $level !== 'super_admin') {
        jsonResponse(['success' => false, 'message' => 'Access denied. Super Admin privileges required.'], 403);
    }
    if ($requiredLevel === 'admin' && $level === 'manager') {
        jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
    }
}

function getAdminLevel(): string {
    return $_SESSION['admin_level'] ?? 'manager';
}

function logLoginHistory(int $userId, string $action, string $ip, string $ua): void {
    $pdo = getPDO();
    $parsed = parseUserAgent($ua);
    $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, device_type, browser, os, action) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $ip, $ua, $parsed['device_type'], $parsed['browser'], $parsed['os'], $action]);
}
