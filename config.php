<?php
/* ============================================================
   RESTAURANT SYSTEM - CENTRAL CONFIGURATION
   Every page reads settings from this file, so you only
   change values here in one place.
   ============================================================ */

// --- MySQL (your phpMyAdmin) --------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'datasave');      // orders + history live here
define('DB_NAME_USERS', 'login');   // staff accounts live here

// --- Public address of this system ---------------------------
// This is the URL that goes inside the QR codes.
// Copy the trycloudflare.com URL printed by start-tunnel.bat here.
define('PUBLIC_URL', 'https://districts-paper-clients-don.trycloudflare.com');

// --- Secret key for creating STAFF accounts -------------------
// Waiters are NOT allowed to register themselves. You (the owner)
// create accounts by opening:  register.php?key=RAMAN-SPICE-7391
// Change this key after you finish creating all staff accounts.
define('SETUP_KEY', 'RAMAN-SPICE-7391');

/* ---------- Database helpers (do not edit below) ---------- */

// Connect to the orders/history database (datasave)
function db_orders() {
    return db_connect(DB_NAME);
}

// Connect to the staff accounts database (login)
function db_users() {
    return db_connect(DB_NAME_USERS);
}

function db_connect($db) {
    $con = @new mysqli(DB_HOST, DB_USER, DB_PASS, $db);
    if ($con->connect_error) {
        die('Database connection failed: ' . htmlspecialchars($con->connect_error));
    }
    return $con;
}
