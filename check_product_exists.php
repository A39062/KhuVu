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
if ((!isset($data['name']) || empty($data['name'])) && (!isset($data['code']) || empty($data['code']))) {
    echo json_encode(['error' => 'Missing or empty product name or code']);
    exit;
}

$name = isset($data['name']) ? trim($data['name']) : '';
$code = isset($data['code']) ? trim($data['code']) : '';
$excludeCode = isset($data['excludeCode']) ? trim($data['excludeCode']) : null;

try {
    // Xây dựng câu truy vấn SQL dựa trên dữ liệu đầu vào
    $sql = "SELECT COUNT(*) as count FROM products WHERE ";
    $params = [];
    $types = "";

    // Nếu có cả name và code
    if (!empty($name) && !empty($code)) {
        $sql .= "(LOWER(name) = LOWER(?) OR code = ?)";
        $params[] = $name;
        $params[] = $code;
        $types .= "ss";
    }
    // Nếu chỉ có name
    else if (!empty($name)) {
        $sql .= "LOWER(name) = LOWER(?)";
        $params[] = $name;
        $types .= "s";
    }
    // Nếu chỉ có code
    else if (!empty($code)) {
        $sql .= "code = ?";
        $params[] = $code;
        $types .= "s";
    }

    // Nếu đang sửa sản phẩm, loại trừ chính nó
    if ($excludeCode) {
        $sql .= " AND code != ?";
        $params[] = $excludeCode;
        $types .= "s";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

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
