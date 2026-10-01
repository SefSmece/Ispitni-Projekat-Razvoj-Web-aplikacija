<?php
// config/security.php

// Provera Origin headera (Same-Origin politika)
function checkOrigin() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = 'http://localhost';

    if ($origin && strpos($origin, $allowed) === false) {
        http_response_code(403);
        echo json_encode(['error' => 'Zabranjen pristup (Origin ne odgovara).']);
        exit;
    }
}

// Provera da li je zahtev sa ispravnog Origin-a (za POST/PUT/DELETE)
function requireSameOrigin() {
    $method = $_SERVER['REQUEST_METHOD'];
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        checkOrigin();
    }
}   