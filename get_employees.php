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

// Lấy dữ liệu nhân viên
$sql = "SELECT employee_code, name, phone, identity, debt, notes FROM nhanvien";
$result = $conn->query($sql);

$employees = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

echo json_encode($employees);

$conn->close();
