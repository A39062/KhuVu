<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

// Kết nối tới cơ sở dữ liệu
$conn = new mysqli($servername, $username, $password, $database);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => "Kết nối thất bại: " . $conn->connect_error]));
}

// Lấy dữ liệu từ yêu cầu POST
$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'];

// Kiểm tra xem ID có hợp lệ không
if (!isset($id) || !is_numeric($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ID không hợp lệ']);
    $conn->close();
    exit();
}

try {
    // Bắt đầu transaction
    $conn->begin_transaction();

    // 1. Đầu tiên xóa các mục trong order_items liên quan đến sản phẩm của nhóm hàng này
    $sql = "DELETE FROM order_items WHERE product_id IN (SELECT id FROM products WHERE category_id = ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Lỗi chuẩn bị câu lệnh xóa order items: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception('Lỗi khi xóa order items: ' . $stmt->error);
    }
    $stmt->close();

    // 2. Xóa các mục trong hoadon_items liên quan đến sản phẩm của nhóm hàng này
    $sql = "DELETE FROM hoadon_items WHERE product_id IN (SELECT id FROM products WHERE category_id = ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Lỗi chuẩn bị câu lệnh xóa hoadon items: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception('Lỗi khi xóa hoadon items: ' . $stmt->error);
    }
    $stmt->close();

    // 3. Sau đó xóa tất cả sản phẩm thuộc nhóm hàng
    $sql = "DELETE FROM products WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Lỗi chuẩn bị câu lệnh xóa sản phẩm: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception('Lỗi khi xóa sản phẩm: ' . $stmt->error);
    }
    $stmt->close();

    // 4. Cuối cùng xóa nhóm hàng
    $sql = "DELETE FROM product_categories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Lỗi chuẩn bị câu lệnh xóa nhóm hàng: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception('Lỗi khi xóa nhóm hàng: ' . $stmt->error);
    }
    $stmt->close();

    // Commit transaction nếu mọi thứ OK
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Đã xóa nhóm hàng và tất cả dữ liệu liên quan']);
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
