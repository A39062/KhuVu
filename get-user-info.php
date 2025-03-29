<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Kết nối thất bại: " . $conn->connect_error]));
}

// Lấy token từ header
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($auth_header) || !preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    echo json_encode(["success" => false, "message" => "Không có token xác thực"]);
    exit;
}

$token = $matches[1];

// Tìm người dùng dựa trên token
$sql = "SELECT id, email, employee_code, employee_name, role FROM users WHERE auth_token = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();

    echo json_encode([
        "success" => true,
        "id" => $user['id'],
        "email" => $user['email'],
        "employee_code" => $user['employee_code'],
        "name" => $user['employee_name'],
        "role" => $user['role']
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Token không hợp lệ hoặc đã hết hạn"
    ]);
}

$stmt->close();
$conn->close();
