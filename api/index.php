<?php
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = rtrim($request, '/');

switch ($request) {
    case '':
    case '/':
    case '/costumer':
        require __DIR__ . '/../costumer.php';
        break;
    case '/order':
    case '/qr':
        require __DIR__ . '/../qr.php';
        break;
    case '/history':
        require __DIR__ . '/../history.php';
        break;
    case '/waiter':
        require __DIR__ . '/../waiter.php';
        break;
    default:
        http_response_code(404);
        echo "404 Page Not Found";
        break;
}
