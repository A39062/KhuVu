<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Ho_Chi_Minh');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Kết nối thất bại: " . $conn->connect_error]));
}

$data = json_decode(file_get_contents("php://input"), true);
$emailOrPhone = $data['emailOrPhone'];
$password = $data['password'];

$sql = "SELECT * FROM users WHERE (email = ? OR phone = ?) AND password = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $emailOrPhone, $emailOrPhone, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();

    $token = bin2hex(random_bytes(32));
    $updateSql = "UPDATE users SET auth_token = ?, is_online = 1 WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $token, $user['id']);
    $updateStmt->execute();

    if ($user['role'] == 'user') {
        $currentTime = date('Y-m-d H:i:s');

        // Kiểm tra xem đã có bản ghi nào cho ngày hôm nay chưa
        $checkSql = "SELECT id, logout_time FROM work_times 
                     WHERE employee_code = ? 
                     AND DATE(login_time) = CURDATE()
                     ORDER BY login_time DESC
                     LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $user['employee_code']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows == 0) {
            // Nếu chưa có bản ghi nào, tạo một bản ghi mới
            $insertTimeSql = "INSERT INTO work_times (employee_code, employee_name, login_time) VALUES (?, ?, ?)";
            $insertTimeStmt = $conn->prepare($insertTimeSql);
            $insertTimeStmt->bind_param("sss", $user['employee_code'], $user['employee_name'], $currentTime);

            if (!$insertTimeStmt->execute()) {
                error_log("Login time insert error: " . $conn->error);
                echo json_encode(["success" => false, "message" => "Lỗi khi tạo bản ghi giờ làm việc."]);
                exit;
            }
        } else {
            $row = $checkResult->fetch_assoc();
            // Nếu đã có bản ghi và logout_time đã được cập nhật, tạo bản ghi mới
            if ($row['logout_time'] !== null && $row['logout_time'] !== '') {
                $insertTimeSql = "INSERT INTO work_times (employee_code, employee_name, login_time) VALUES (?, ?, ?)";
                $insertTimeStmt = $conn->prepare($insertTimeSql);
                $insertTimeStmt->bind_param("sss", $user['employee_code'], $user['employee_name'], $currentTime);

                if (!$insertTimeStmt->execute()) {
                    error_log("Login time insert error: " . $conn->error);
                    echo json_encode(["success" => false, "message" => "Lỗi khi tạo bản ghi giờ làm việc."]);
                    exit;
                }
                // Cập nhật cột month từ login_time
                $updateMonthSql = "UPDATE work_times SET month = MONTH(login_time) WHERE employee_code = ? AND DATE(login_time) = CURDATE()";
                $updateMonthStmt = $conn->prepare($updateMonthSql);
                $updateMonthStmt->bind_param("s", $user['employee_code']);
                $updateMonthStmt->execute();
            } else {
                // Nếu đã có bản ghi và logout_time chưa được cập nhật, không tạo bản ghi mới
                error_log("User đã có bản ghi giờ làm việc trong ngày hôm nay và chưa đăng xuất.");
            }
        }
    }

    echo json_encode([
        "success" => true,
        "redirect" => ($user['role'] == 'admin') ? "tongquan.html" : "thanhtoan.html",
        "token" => $token,
        "role" => $user['role'],
        "email" => $user['email'],
        "employee_code" => $user['employee_code'],
        "name" => $user['employee_name']
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Email/số điện thoại hoặc mật khẩu không đúng!"
    ]);
}

$stmt->close();
$conn->close();
