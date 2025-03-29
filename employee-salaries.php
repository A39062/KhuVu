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
$conn->set_charset("utf8");

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

// Xác thực token
$authSql = "SELECT id FROM users WHERE auth_token = ?";
$authStmt = $conn->prepare($authSql);
$authStmt->bind_param("s", $token);
$authStmt->execute();
$authResult = $authStmt->get_result();

if ($authResult->num_rows != 1) {
    echo json_encode(["success" => false, "message" => "Token không hợp lệ"]);
    exit;
}

// Lấy tham số từ URL
$employee_code = isset($_GET['employee_code']) ? $_GET['employee_code'] : '';
$month = isset($_GET['month']) ? $_GET['month'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : '';

if (empty($employee_code) || empty($month) || empty($year)) {
    echo json_encode(["success" => false, "message" => "Thiếu tham số"]);
    exit;
}

// Truy vấn dữ liệu lương
$sql = "SELECT es.*, u.employee_name 
        FROM employee_salaries es
        JOIN users u ON es.employee_code = u.employee_code
        WHERE es.employee_code = ? AND es.month = ? AND es.year = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $employee_code, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode([
        "success" => true,
        "employee_code" => $data['employee_code'],
        "employee_name" => $data['employee_name'],
        "month" => (int)$data['month'],
        "year" => (int)$data['year'],
        "hours_worked" => floatval($data['hours_worked']),
        "hourly_wage" => floatval($data['hourly_wage']),
        "total_salary" => floatval($data['total_salary'])
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy dữ liệu lương cho tháng $month/$year"
    ]);
}

$stmt->close();
$authStmt->close();
$conn->close();
