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
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
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
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'getWorkTimes':
        try {
            // Lấy tham số tháng và mã nhân viên nếu có
            $month = isset($_GET['month']) ? $_GET['month'] : null;
            $employeeCode = isset($_GET['employee_code']) ? $_GET['employee_code'] : null;

            $sql = "SELECT 
                wt.employee_code, 
                wt.employee_name,
                DATE_FORMAT(wt.login_time,'%d-%m-%Y %H:%i:%s') as login_time,
                DATE_FORMAT(wt.logout_time,'%d-%m-%Y %H:%i:%s') as logout_time,
                MONTH(wt.login_time) as month
            FROM work_times wt 
            WHERE 1=1";

            // Thêm điều kiện lọc nếu có
            if ($month) {
                $sql .= " AND MONTH(wt.login_time) = " . intval($month);
            }
            if ($employeeCode) {
                $sql .= " AND wt.employee_code = '" . $conn->real_escape_string($employeeCode) . "'";
            }

            $sql .= " ORDER BY wt.login_time DESC";

            $result = $conn->query($sql);

            if (!$result) {
                throw new Exception($conn->error);
            }

            $workTimes = [];
            while ($row = $result->fetch_assoc()) {
                $workTimes[] = $row;
            }

            echo json_encode(['status' => 'success', 'data' => $workTimes]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'updateMonthColumn':
        try {
            $sql = "UPDATE work_times SET month = MONTH(login_time) WHERE month IS NULL OR month = 0";
            $result = $conn->query($sql);

            if (!$result) {
                throw new Exception($conn->error);
            }

            $affectedRows = $conn->affected_rows;
            echo json_encode(['status' => 'success', 'message' => "Đã cập nhật $affectedRows bản ghi"]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'calculateSalary':
        try {
            $month = isset($_GET['month']) ? $_GET['month'] : null;
            $employeeCode = isset($_GET['employee_code']) ? $_GET['employee_code'] : null;

            if (!$month || !$employeeCode) {
                throw new Exception('Missing required parameters');
            }

            // Thêm JOIN với bảng users để lấy tên nhân viên
            $sql = "SELECT 
            w.employee_code,
            u.employee_name as employee_name, 
            SEC_TO_TIME(
                SUM(TIME_TO_SEC(w.logout_time)) - SUM(TIME_TO_SEC(w.login_time))
            ) as total_time,
            ROUND(
                (SUM(TIME_TO_SEC(w.logout_time)) - SUM(TIME_TO_SEC(w.login_time))) / 3600,
                2
            ) as total_hours,
            SEC_TO_TIME(SUM(TIME_TO_SEC(w.login_time))) as total_login_time,
            SEC_TO_TIME(SUM(TIME_TO_SEC(w.logout_time))) as total_logout_time
        FROM work_times w
        LEFT JOIN users u ON w.employee_code = u.employee_code
        WHERE w.employee_code = ? 
        AND MONTH(w.login_time) = ? 
        AND w.logout_time IS NOT NULL
        GROUP BY w.employee_code, u.employee_name";  // Cũng cần thay đổi ở đây

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $employeeCode, $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'employee_name' => $row['employee_name'],
                        'total_hours' => floatval($row['total_hours']),
                        'total_time' => $row['total_time'],
                        'total_login_time' => $row['total_login_time'],
                        'total_logout_time' => $row['total_logout_time']
                    ]
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'employee_name' => 'Không tìm thấy',
                        'total_hours' => 0,
                        'total_time' => '00:00:00',
                        'total_login_time' => '00:00:00',
                        'total_logout_time' => '00:00:00'
                    ]
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
}

$conn->close();
