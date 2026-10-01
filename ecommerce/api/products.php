<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
requireSameOrigin();   

// JWT verifikacija
function verifyToken() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer ') !== 0) return null;

    $token = substr($auth, 7);
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    $header  = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[2]));

    $secret = 'moj_tajni_kljuc_2024';
    $expected = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $secret, true);

    if (!hash_equals($expected, $signature)) return null;

    $data = json_decode($payload, true);
    if (!$data || $data['exp'] < time()) return null;

    return $data;
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);

// ---------- GET: Lista proizvoda ----------
if ($method === 'GET') {

    $search = $_GET['search'] ?? '';

    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT id, name, description, price, stock, category FROM products WHERE name LIKE :search OR description LIKE :search2 ORDER BY name");
        $stmt->execute([
            ':search'  => '%' . $search . '%',
            ':search2' => '%' . $search . '%'
        ]);
    } else {
        $stmt = $pdo->query("SELECT id, name, description, price, stock, category FROM products ORDER BY name");
    }

    echo json_encode($stmt->fetchAll());
    exit;
}

// Ovdje dalje treba token (POST/PUT/DELETE)
$user = verifyToken();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Niste autentifikovani.']);
    exit;
}

// ---------- POST: Dodaj proizvod (samo admin) ----------
if ($method === 'POST') {

    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Nemate dozvolu.']);
        exit;
    }

    $errors = [];
    if (empty($input['name']) || strlen($input['name']) < 2) $errors[] = 'Naziv mora imati min. 2 karaktera.';
    if (!isset($input['price']) || !is_numeric($input['price']) || $input['price'] < 0) $errors[] = 'Cena mora biti broj >= 0.';
    if (!isset($input['stock']) || !is_numeric($input['stock']) || $input['stock'] < 0) $errors[] = 'Zaliha mora biti ceo broj >= 0.';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, category) VALUES (:name, :desc, :price, :stock, :cat)");
    $stmt->execute([
        ':name'   => $input['name'],
        ':desc'   => $input['description'] ?? '',
        ':price'  => $input['price'],
        ':stock'  => (int)$input['stock'],
        ':cat'    => $input['category'] ?? 'Opste'
    ]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

// ---------- PUT: Izmeni proizvod (samo admin) ----------
if ($method === 'PUT') {

    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Nemate dozvolu.']);
        exit;
    }

    if (empty($input['id']) || !is_numeric($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID je obavezan.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE products SET name = :name, description = :desc, price = :price, stock = :stock, category = :cat WHERE id = :id");
    $stmt->execute([
        ':name'  => $input['name'],
        ':desc'  => $input['description'] ?? '',
        ':price' => $input['price'],
        ':stock' => (int)$input['stock'],
        ':cat'   => $input['category'] ?? 'Opste',
        ':id'    => (int)$input['id']
    ]);

    echo json_encode(['success' => true]);
    exit;
}

// ---------- DELETE: Obriši proizvod (samo admin) ----------
if ($method === 'DELETE') {

    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Nemate dozvolu.']);
        exit;
    }

    if (empty($input['id']) || !is_numeric($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID je obavezan.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => (int)$input['id']]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Nevalidan zahtev.']);   