<?php
// Bật báo cáo lỗi để debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Đảm bảo không có output nào trước khi gửi headers
ob_start();

// Thiết lập headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Xử lý OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Kết nối database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Kết nối thất bại: " . $conn->connect_error]);
    exit();
}

// Lấy và xác thực token
$headers = apache_request_headers();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($auth_header) || !preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Không có token xác thực"]);
    exit();
}

$token = $matches[1];

// Xác thực token
$authSql = "SELECT id FROM users WHERE auth_token = ?";
$authStmt = $conn->prepare($authSql);
$authStmt->bind_param("s", $token);
$authStmt->execute();
$authResult = $authStmt->get_result();

if ($authResult->num_rows != 1) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Token không hợp lệ"]);
    exit();
}

// Lấy mã nhân viên từ URL
$employee_code = isset($_GET['employee_code']) ? $_GET['employee_code'] : '';

if (empty($employee_code)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Thiếu mã nhân viên"]);
    exit();
}

// Truy vấn dữ liệu lương
try {
    // Kiểm tra dữ liệu trong bảng
    $checkSql = "SELECT COUNT(*) as count FROM employee_salaries WHERE employee_code = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $employee_code);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult->fetch_assoc();

    if ($checkRow['count'] == 0) {
        echo json_encode([]);
        exit();
    }

    // Truy vấn chính
    $sql = "SELECT es.*, u.employee_name 
            FROM employee_salaries es
            JOIN users u ON es.employee_code = u.employee_code
            WHERE es.employee_code = ?
            ORDER BY es.year DESC, es.month DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_code);
    $stmt->execute();
    $result = $stmt->get_result();

    // Debug: In ra số lượng bản ghi tìm thấy
    $rowCount = $result->num_rows;
    error_log("Số bản ghi tìm thấy: " . $rowCount);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Debug: In ra dữ liệu từng bản ghi
        error_log("Dữ liệu bản ghi: " . json_encode($row));

        // Chuyển đổi kiểu dữ liệu một cách rõ ràng
        $hourWorked = isset($row['hours_worked']) ? floatval($row['hours_worked']) : 0;
        $hourlyWage = isset($row['hourly_wage']) ? floatval($row['hourly_wage']) : 0;
        $totalSalary = isset($row['total_salary']) ? floatval($row['total_salary']) : 0;

        $data[] = [
            "employee_code" => $row['employee_code'],
            "employee_name" => $row['employee_name'],
            "month" => (int)$row['month'],
            "year" => (int)$row['year'],
            "hour_worked" => $hourWorked,
            "hourly_wage" => $hourlyWage,
            "total_salary" => $totalSalary
        ];
    }

    // Trả về dữ liệu JSON
    echo json_encode($data);
} catch (Exception $e) {
    error_log("Lỗi: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi server: " . $e->getMessage()]);
} finally {
    // Đóng các kết nối
    if (isset($stmt)) $stmt->close();
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($authStmt)) $authStmt->close();
    $conn->close();
}

// Đảm bảo không có output nào sau JSON
exit();
