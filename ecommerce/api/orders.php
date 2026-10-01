<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

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

// ---------- GET: Moje porudžbine ----------
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT o.id, o.total, o.status, o.created_at
        FROM orders o
        WHERE o.user_id = :uid
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([':uid' => $user['id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ---------- POST: Napravi porudžbinu ----------
if ($method === 'POST') {

    // Dobij iteme iz korpe
    $stmt = $pdo->prepare("
        SELECT ci.product_id, ci.quantity, p.price, p.stock
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.user_id = :uid
    ");
    $stmt->execute([':uid' => $user['id']]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['error' => 'Korpa je prazna.']);
        exit;
    }

    $total = 0;
    foreach ($items as $item) {
        if ($item['stock'] < $item['quantity']) {
            http_response_code(400);
            echo json_encode(['error' => 'Nedovoljno zalihe za proizvod ID ' . $item['product_id'] . '.']);
            exit;
        }
        $total += $item['price'] * $item['quantity'];
    }

    // Kreni u transakciju
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total) VALUES (:uid, :total)");
        $stmt->execute([':uid' => $user['id'], ':total' => $total]);
        $orderId = $pdo->lastInsertId();

        foreach ($items as $item) {
            // Dodaj u order_items
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (:oid, :pid, :qty, :price)");
            $stmt->execute([
                ':oid'   => $orderId,
                ':pid'   => $item['product_id'],
                ':qty'   => $item['quantity'],
                ':price' => $item['price']
            ]);

            // Smanji zalihe
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :pid");
            $stmt->execute([':qty' => $item['quantity'], ':pid' => $item['product_id']]);
        }

        // Isprazni korpu
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = :uid");
        $stmt->execute([':uid' => $user['id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'order_id' => $orderId, 'total' => $total]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Greška pri kreiranju porudžbine.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Nevalidan zahtev.']);   