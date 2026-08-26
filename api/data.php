<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $databasePath = getenv('DB_PATH') ?: dirname(__DIR__) . '/data/vape.sqlite';
    $database = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $database->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id VARCHAR(64) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            type VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            stock INT NOT NULL DEFAULT 0,
            sold INT NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS settings (
            name VARCHAR(100) PRIMARY KEY,
            value TEXT NOT NULL
        );'
    );

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $products = $database->query(
            'SELECT id, name, category, type, price, stock, sold FROM products ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($database->query('SELECT name, value FROM settings') as $setting) {
            $settings[$setting['name']] = json_decode($setting['value'], true);
        }

        echo json_encode([
            'initialized' => count($products) > 0,
            'products' => $products,
            'settings' => $settings
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload) || !isset($payload['products']) || !is_array($payload['products'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        exit;
    }

    $database->beginTransaction();
    $database->exec('DELETE FROM products');
    $insertProduct = $database->prepare(
        'INSERT INTO products (id, name, category, type, price, stock, sold)
         VALUES (:id, :name, :category, :type, :price, :stock, :sold)'
    );
    foreach ($payload['products'] as $product) {
        $insertProduct->execute([
            ':id' => (string)($product['id'] ?? uniqid('p_', true)),
            ':name' => (string)($product['name'] ?? ''),
            ':category' => (string)($product['category'] ?? 'Other'),
            ':type' => (string)($product['type'] ?? '50K transparent pod'),
            ':price' => max(0, (float)($product['price'] ?? 0)),
            ':stock' => max(0, (int)($product['stock'] ?? 0)),
            ':sold' => max(0, (int)($product['sold'] ?? 0))
        ]);
    }

    if (isset($payload['settings']) && is_array($payload['settings'])) {
        $database->exec('DELETE FROM settings');
        $insertSetting = $database->prepare(
            'INSERT INTO settings (name, value) VALUES (:name, :value)'
        );
        foreach ($payload['settings'] as $name => $value) {
            $insertSetting->execute([
                ':name' => (string)$name,
                ':value' => json_encode($value)
            ]);
        }
    }

    $database->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $error) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
