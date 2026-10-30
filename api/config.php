<?php
/* ============================================================
   RESTAURANT SYSTEM - CENTRAL CONFIGURATION
   Every page reads settings from this file, so you only
   change values here in one place.

   Works BOTH on Vercel and on your local XAMPP:
   - On Vercel: secrets come from Environment Variables
     (Project -> Settings -> Environment Variables).
   - Locally: create config.local.php beside this file with:
       <?php $CFG['pass'] = 'your-database-password';
     That file is NEVER committed to GitHub (.gitignore).
   ============================================================ */

$CFG = array(
    'host' => getenv('DB_HOST') ?: 'mysql-32836f18-ramanshah440-ac77.f.aivencloud.com',
    'port' => getenv('DB_PORT') ?: '18867',
    'user' => getenv('DB_USER') ?: 'avnadmin',
    'pass' => getenv('DB_PASS') ?: '',
    'name' => getenv('DB_NAME') ?: 'defaultdb',
);

// Local-only override (kept out of git via .gitignore)
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

define('DB_HOST', $CFG['host']);
define('DB_PORT', $CFG['port']);
define('DB_USER', $CFG['user']);
define('DB_PASS', $CFG['pass']);
define('DB_NAME', $CFG['name']);
define('DB_NAME_USERS', 'login'); // staff accounts live here

// --- Public address of this system ---------------------------
// The URL that goes inside the QR codes, e.g. https://your-app.vercel.app
$CFG['public_url'] = getenv('PUBLIC_URL') ?: ($CFG['public_url'] ?? '');
define('PUBLIC_URL', $CFG['public_url']);

// --- Secret key for creating STAFF accounts -------------------
// Staff are NOT allowed to register themselves. You (the owner)
// create accounts by opening:  register.php?key=RAMAN-SPICE-7391
$CFG['setup_key'] = getenv('SETUP_KEY') ?: ($CFG['setup_key'] ?? 'RAMAN-SPICE-7391');
define('SETUP_KEY', $CFG['setup_key']);

/* ---------- Database helpers (do not edit below) ---------- */

// Connect to the orders/history database (defaultdb)
function db_orders() {
    return db_connect(DB_NAME);
}

// Connect to the staff accounts database (login)
function db_users() {
    return db_connect(DB_NAME_USERS);
}

function db_connect($db) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $port = (int)DB_PORT;

    if (stripos(DB_HOST, 'aivencloud') !== false) {
        // Cloud MySQL (Aiven) requires TLS - request SSL for remote hosts
        $con = mysqli_init();
        $ok  = @mysqli_real_connect($con, DB_HOST, DB_USER, DB_PASS, $db, $port, null, MYSQLI_CLIENT_SSL);
    } else {
        // Plain local connection (your XAMPP MariaDB)
        $con = @new mysqli(DB_HOST, DB_USER, DB_PASS, $db, $port);
        $ok  = !($con->connect_error);
    }

    if (!$ok || ($con->connect_error ?? false)) {
        $err = is_object($con) ? ($con->connect_error ?: mysqli_connect_error()) : mysqli_connect_error();
        die('Database connection failed: ' . htmlspecialchars((string)$err));
    }
    return $con;
}
