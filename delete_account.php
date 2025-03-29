<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Kết nối thất bại: ' . $conn->connect_error
    ]));
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['id'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Thiếu ID tài khoản'
    ]));
}

$id = intval($data['id']);

$sql = "DELETE FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Xóa tài khoản thành công'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Xóa tài khoản thất bại: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
