<?php
// Đặt header để đảm bảo trả về JSON
header('Content-Type: application/json');

// Tắt hiển thị lỗi PHP để tránh trả về HTML thay vì JSON
error_reporting(0);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

// Đọc dữ liệu JSON từ request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Kiểm tra dữ liệu đầu vào
if (!isset($data['name']) || empty($data['name'])) {
    echo json_encode(['error' => 'Missing or empty category name']);
    exit;
}

$name = trim($data['name']); // Loại bỏ khoảng trắng thừa

try {
    // Sử dụng prepared statement để tránh SQL injection
    // Thay đổi từ categories thành product_categories
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM product_categories WHERE LOWER(name) = LOWER(?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $name);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }

    $row = $result->fetch_assoc();
    echo json_encode(['exists' => ($row['count'] > 0)]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
