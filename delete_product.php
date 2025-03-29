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
$id = $data['id'];

// Xóa hàng hóa
$sql = "DELETE FROM products WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi xóa hàng hóa']);
}

$stmt->close();
$conn->close();
