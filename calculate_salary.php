<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kiểm tra token
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    }
}

if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Kết nối database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Kiểm tra token hợp lệ
$sql = "SELECT * FROM users WHERE auth_token = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
    exit;
}

$user = $result->fetch_assoc();

$employeeCode = $_GET['employee_code'];
$month = $_GET['month'];

if (empty($employeeCode) || empty($month)) {
    echo json_encode(['status' => 'error', 'message' => 'Mã nhân viên và tháng không được để trống.']);
    exit;
}

// Lấy tổng số giờ làm việc của nhân viên trong tháng
$sql = "SELECT login_time, logout_time FROM work_times 
        WHERE employee_code = ? AND MONTH(login_time) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $employeeCode, $month);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn work_times: ' . $conn->error]);
    exit;
}

$totalSeconds = 0;
while ($row = $result->fetch_assoc()) {
    $loginTime = strtotime($row['login_time']);
    $logoutTime = strtotime($row['logout_time']);
    $totalSeconds += ($logoutTime - $loginTime);
}

$totalHours = round($totalSeconds / 3600, 2); // Chuyển đổi thành giờ và làm tròn

// Lấy mức lương theo giờ của nhân viên từ bảng nhanvien1
$sql = "SELECT luong FROM nhanvien1 WHERE ma_nhan_vien = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $employeeCode);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn nhanvien1: ' . $conn->error]);
    exit;
}

if ($result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông tin nhân viên.']);
    exit;
}

$employee = $result->fetch_assoc();
$hourlyRate = $employee['luong'];

// Check if $hourlyRate is numeric
if (!is_numeric($hourlyRate)) {
    echo json_encode(['status' => 'error', 'message' => 'Mức lương không hợp lệ.']);
    exit;
}

// Tính tổng lương
$totalSalary = $totalHours * $hourlyRate;

echo json_encode(['status' => 'success', 'total_salary' => $totalSalary]);

$conn->close();
