<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

// Kết nối tới cơ sở dữ liệu
$conn = new mysqli($servername, $username, $password, $database);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy dữ liệu từ yêu cầu POST
$data = json_decode(file_get_contents("php://input"), true);
$employee_code = $data['employee_code'];
$name = $data['name'];
$phone = $data['phone'];
$identity = $data['identity'];
$debt = $data['debt'];
$notes = $data['notes'];

// Thêm nhân viên
$sql = "INSERT INTO nhanvien (employee_code, name, phone, identity, debt, notes) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssds", $employee_code, $name, $phone, $identity, $debt, $notes);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
