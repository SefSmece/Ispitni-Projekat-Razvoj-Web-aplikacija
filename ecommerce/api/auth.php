<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
requireSameOrigin();   

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);

// ---------- REGISTER ----------
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'register') {

    // Validacija
    $errors = [];

    if (empty($input['username'])) {
        $errors[] = 'Username je obavezan.';
    } elseif (strlen($input['username']) < 3 || strlen($input['username']) > 50) {
        $errors[] = 'Username mora imati 3-50 karaktera.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $input['username'])) {
        $errors[] = 'Username može sadržati samo slova, brojeve i _.';
    }

    if (empty($input['email'])) {
        $errors[] = 'Email je obavezan.';
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email nije u ispravnom formatu.';
    }

    if (empty($input['password'])) {
        $errors[] = 'Lozinka je obavezna.';
    } elseif (strlen($input['password']) < 6) {
        $errors[] = 'Lozinka mora imati minimalno 6 karaktera.';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    // Provera da li username/email već postoji
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $input['username'], ':email' => $input['email']]);

    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Username ili email već postoji.']);
        exit;
    }

    // Insert
    $hashed = password_hash($input['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
    $stmt->execute([
        ':username' => $input['username'],
        ':email'    => $input['email'],
        ':password' => $hashed,
    ]);

    echo json_encode(['success' => true, 'message' => 'Registracija uspešna.']);
    exit;
}

// ---------- LOGIN ----------
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'login') {

    $errors = [];

    if (empty($input['username'])) {
        $errors[] = 'Username je obavezan.';
    }
    if (empty($input['password'])) {
        $errors[] = 'Lozinka je obavezna.';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = :username");
    $stmt->execute([':username' => $input['username']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($input['password'], $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Pogrešan username ili lozinka.']);
        exit;
    }

    // Generiši JWT
    $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'id'       => $user['id'],
        'username' => $user['username'],
        'role'     => $user['role'],
        'exp'      => time() + 3600,  // token važi 1 sat
    ]));
    $secret   = 'moj_tajni_kljuc_2024';
    $signature = hash_hmac('sha256', "$header.$payload", $secret, true);
    $signature = base64_encode($signature);

    $token = "$header.$payload.$signature";

    echo json_encode([
        'success' => true,
        'token'   => $token,
        'user'    => ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']],
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Nevalidan zahtev.']);   