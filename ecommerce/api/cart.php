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

function verifyToken() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer ') !== 0) return null;

    $token = substr($auth, 7);
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[2]));

    $secret = 'moj_tajni_kljuc_2024';
    $expected = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $secret, true);

    if (!hash_equals($expected, $signature)) return null;

    $data = json_decode($payload, true);
    if (!$data || $data['exp'] < time()) return null;

    return $data;
}

$user = verifyToken();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Niste autentifikovani.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);

// ---------- GET: Prikazi korpu ----------
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT ci.id, ci.product_id, ci.quantity, p.name, p.price, p.stock
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.user_id = :uid
    ");
    $stmt->execute([':uid' => $user['id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ---------- POST: Dodaj u korpu ----------
if ($method === 'POST') {
    if (empty($input['product_id']) || !is_numeric($input['product_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'product_id je obavezan.']);
        exit;
    }
    $qty = (int)($input['quantity'] ?? 1);
    if ($qty < 1) $qty = 1;

    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = :pid");
    $stmt->execute([':pid' => (int)$input['product_id']]);
    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Proizvod ne postoji.']);
        exit;
    }

    if ($product['stock'] < $qty) {
        http_response_code(400);
        echo json_encode(['error' => 'Nedovoljno zalihe.']);
        exit;
    }

    // Proveri da li je već u korpi
    $stmt = $pdo->prepare("SELECT quantity FROM cart_items WHERE user_id = :uid AND product_id = :pid");
    $stmt->execute([':uid' => $user['id'], ':pid' => (int)$input['product_id']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $newQty = $existing['quantity'] + $qty;
        if ($newQty > $product['stock']) {
            http_response_code(400);
            echo json_encode(['error' => 'Nedovoljno zalihe.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = :qty WHERE user_id = :uid AND product_id = :pid");
        $stmt->execute([':qty' => $newQty, ':uid' => $user['id'], ':pid' => (int)$input['product_id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (:uid, :pid, :qty)");
        $stmt->execute([':uid' => $user['id'], ':pid' => (int)$input['product_id'], ':qty' => $qty]);
    }

    echo json_encode(['success' => true]);
    exit;
}

// ---------- PUT: Izmeni količinu ----------
if ($method === 'PUT') {
    if (empty($input['cart_id']) || !is_numeric($input['cart_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'cart_id je obavezan.']);
        exit;
    }
    $qty = (int)($input['quantity'] ?? 1);
    if ($qty < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Količina mora biti >= 1.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE cart_items SET quantity = :qty WHERE id = :cid AND user_id = :uid");
    $stmt->execute([':qty' => $qty, ':cid' => (int)$input['cart_id'], ':uid' => $user['id']]);

    echo json_encode(['success' => true]);
    exit;
}

// ---------- DELETE: Ukloni iz korpe ----------
if ($method === 'DELETE') {
    if (empty($input['cart_id']) || !is_numeric($input['cart_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'cart_id je obavezan.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = :cid AND user_id = :uid");
    $stmt->execute([':cid' => (int)$input['cart_id'], ':uid' => $user['id']]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Nevalidan zahtev.']);   