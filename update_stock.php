<?php
header('Content-Type: application/json');

try {
    $conn = new mysqli('localhost', 'root', '', 'user_db');
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Bắt đầu transaction
    $conn->begin_transaction();

    // Nhận dữ liệu từ request
    $data = json_decode(file_get_contents('php://input'), true);

    // Tạo đơn hàng mới
    $stmt = $conn->prepare("INSERT INTO orders (total_amount, discount, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("dd", $data['total_amount'], $data['discount']);

    if (!$stmt->execute()) {
        throw new Exception("Error creating order: " . $stmt->error);
    }

    $orderId = $conn->insert_id;

    // Thêm từng sản phẩm vào order_items và cập nhật số lượng trong kho
    foreach ($data['items'] as $item) {
        // Kiểm tra số lượng tồn kho
        $checkStock = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
        $checkStock->bind_param("i", $item['product_id']);
        $checkStock->execute();
        $result = $checkStock->get_result();
        $currentStock = $result->fetch_assoc();

        if (!$currentStock || $currentStock['quantity'] < $item['quantity']) {
            throw new Exception("Insufficient stock for product ID: " . $item['product_id']);
        }

        // Thêm vào order_items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, payment_method) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiids", $orderId, $item['product_id'], $item['quantity'], $item['price'], $data['payment_method']);

        if (!$stmt->execute()) {
            throw new Exception("Error adding order item: " . $stmt->error);
        }

        // Cập nhật số lượng trong kho
        $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
        $stmt->bind_param("ii", $item['quantity'], $item['product_id']);

        if (!$stmt->execute()) {
            throw new Exception("Error updating stock: " . $stmt->error);
        }
    }

    // Commit transaction nếu mọi thứ OK
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Order created successfully',
        'order_id' => $orderId
    ]);
} catch (Exception $e) {
    // Rollback nếu có lỗi
    if (isset($conn)) {
        $conn->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
