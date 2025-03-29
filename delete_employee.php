<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

// Kiểm tra dữ liệu đầu vào
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['id'])) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Missing employee ID'
    ]));
}

$id = intval($data['id']); // Chuyển đổi ID thành số nguyên

// Kết nối database
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Connection failed: ' . $conn->connect_error
    ]));
}

// Xóa nhân viên
$sql = "DELETE FROM nhanvien WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Employee deleted successfully'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Delete failed: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
