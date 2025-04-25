<?php
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['code']) || !isset($data['sale_price']) || !isset($data['cost_price'])) {
        throw new Exception('Thiếu thông tin cần thiết');
    }

    $pdo = new PDO("mysql:host=localhost;dbname=user_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Chỉ cập nhật giá bán và giá vốn
    $stmt = $pdo->prepare("UPDATE products SET 
        sale_price = ?, 
        cost_price = ? 
        WHERE code = ?");

    $result = $stmt->execute([
        $data['sale_price'],
        $data['cost_price'],
        $data['code']
    ]);

    if ($result) {
        echo json_encode(['status' => 'success', 'message' => 'Cập nhật giá thành công']);
    } else {
        throw new Exception('Không thể cập nhật sản phẩm');
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
