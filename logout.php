<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Ho_Chi_Minh');
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

// Kết nối tới database
$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed']));
}

// Lấy token từ header
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';
session_start();
header('Content-Type: application/json');

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


// Lấy thông tin người dùng từ token
$sql = "SELECT * FROM users WHERE auth_token = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

// Sửa phần xử lý đăng xuất trong logout.php
if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();

    // Cập nhật trạng thái offline và xóa token
    $updateSql = "UPDATE users SET is_online = 0, auth_token = NULL WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $user['id']);
    $updateStmt->execute();

    // Chỉ cập nhật logout_time cho user
    if ($user['role'] == 'user') {
        $currentTime = date('Y-m-d H:i:s');

        // Lấy giờ kết thúc từ lịch làm việc
        $scheduleSQL = "SELECT end_time FROM work_schedules 
                       WHERE employee_code = ? 
                       AND work_date = CURDATE()";
        $scheduleStmt = $conn->prepare($scheduleSQL);
        $scheduleStmt->bind_param("s", $user['employee_code']);
        $scheduleStmt->execute();
        $scheduleResult = $scheduleStmt->get_result();

        if ($scheduleResult->num_rows > 0) {
            $schedule = $scheduleResult->fetch_assoc();
            $endTime = $schedule['end_time'];

            // Nếu thời gian đăng xuất lớn hơn giờ kết thúc
            if (strtotime($currentTime) > strtotime(date('Y-m-d') . ' ' . $endTime)) {
                $currentTime = date('Y-m-d') . ' ' . $endTime;
            }
        }

        $updateTimeSql = "UPDATE work_times 
                      SET logout_time = ? 
                      WHERE employee_code = ? 
                      AND DATE(login_time) = CURDATE() 
                      AND (logout_time IS NULL OR logout_time = '')
                      ORDER BY login_time DESC 
                      LIMIT 1";
        $updateTimeStmt = $conn->prepare($updateTimeSql);
        $updateTimeStmt->bind_param("ss", $currentTime, $user['employee_code']);

        if (!$updateTimeStmt->execute()) {
            error_log("Logout time update error: " . $conn->error);
        }
    }

    session_destroy();
    echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
}


if ($token) {
    // Cập nhật trạng thái offline và xóa token trong database
    $sql = "UPDATE users SET is_online = 0, auth_token = NULL WHERE auth_token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
}

// Xóa session
session_unset();
session_destroy();

// Đóng kết nối
$conn->close();

// Trả về response và kết thúc script
echo json_encode(['success' => true]);
exit();
