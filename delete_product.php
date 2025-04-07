<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Kết nối thất bại: ' . $conn->connect_error]));
}

// Lấy ID từ yêu cầu
$data = json_decode(file_get_contents('php://input'), true);

if ($data === null) {
    die(json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']));
}

$id = $data['id'];

if ($id === null) {
    die(json_encode(['status' => 'error', 'message' => 'ID không hợp lệ']));
}

try {
    // Xóa hàng hóa
    $sql = "UPDATE products SET is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi xóa hàng hóa']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi xóa hàng hóa: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
