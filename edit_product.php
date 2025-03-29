<?php
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    // Thay đổi thông tin kết nối database theo cấu hình của bạn
    $pdo = new PDO("mysql:host=localhost;dbname=user_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cập nhật sản phẩm dựa trên code
    $stmt = $pdo->prepare("UPDATE products SET 
        category_id = ?, 
        name = ?, 
        sale_price = ?, 
        cost_price = ?, 
        quantity = ? 
        WHERE code = ?");

    $result = $stmt->execute([
        $data['category_id'],
        $data['name'],
        $data['sale_price'],
        $data['cost_price'],
        $data['quantity'],
        $data['code']
    ]);

    if ($result) {
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception('Không thể cập nhật sản phẩm');
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
