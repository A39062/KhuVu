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

    // Kiểm tra vai trò của người dùng
    if ($user['role'] == 'user') {
        $currentDate = date('Y-m-d');

        // Kiểm tra xem có lịch làm việc cho ngày hôm nay không
        $scheduleSql = "SELECT work_date FROM work_schedules WHERE employee_code = ? AND work_date = ?";
        $scheduleStmt = $conn->prepare($scheduleSql);
        $scheduleStmt->bind_param("ss", $user['employee_code'], $currentDate);
        $scheduleStmt->execute();
        $scheduleResult = $scheduleStmt->get_result();

        if ($scheduleResult->num_rows > 0) {
            // Lấy thông tin lịch trình (bao gồm giờ bắt đầu và giờ kết thúc)
            $scheduleSql = "SELECT work_date, start_time, end_time FROM work_schedules WHERE employee_code = ? AND work_date = ?";
            $scheduleStmt = $conn->prepare($scheduleSql);
            $scheduleStmt->bind_param("ss", $user['employee_code'], $currentDate);
            $scheduleStmt->execute();
            $scheduleResult = $scheduleStmt->get_result();

            if ($scheduleResult->num_rows > 0) {
                $schedule = $scheduleResult->fetch_assoc();

                $startTime = $schedule['start_time'];
                $endTime = $schedule['end_time'];

                // Lấy giờ hiện tại
                $currentTime = date('H:i:s');

                // Kiểm tra xem giờ hiện tại có nằm trong khoảng thời gian làm việc không
                if ($currentTime >= $startTime && $currentTime <= $endTime) {
                    // Nếu có lịch làm việc và đúng giờ, cho phép đăng nhập
                    $redirect = "thanhtoan.html";
                    $hasScheduleToday = true;

                    // Cập nhật trạng thái online và token
                    $updateSql = "UPDATE users SET auth_token = ?, is_online = 1 WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("si", $token, $user['id']);
                    $updateStmt->execute();

                    // Tạo bản ghi giờ làm việc
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
                        $currentTime = date('Y-m-d H:i:s');
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
                        if ($row['logout_time'] !== null && $row['logout_time'] !== '') {
                            $currentTime = date('Y-m-d H:i:s');
                            $insertTimeSql = "INSERT INTO work_times (employee_code, employee_name, login_time) VALUES (?, ?, ?)";
                            $insertTimeStmt = $conn->prepare($insertTimeSql);
                            $insertTimeStmt->bind_param("sss", $user['employee_code'], $user['employee_name'], $currentTime);

                            if (!$insertTimeStmt->execute()) {
                                error_log("Login time insert error: " . $conn->error);
                                echo json_encode(["success" => false, "message" => "Lỗi khi tạo bản ghi giờ làm việc."]);
                                exit;
                            }
                            $updateMonthSql = "UPDATE work_times SET month = MONTH(login_time) WHERE employee_code = ? AND DATE(login_time) = CURDATE()";
                            $updateMonthStmt = $conn->prepare($updateMonthSql);
                            $updateMonthStmt->bind_param("s", $user['employee_code']);
                            $updateMonthStmt->execute();
                        } else {
                            error_log("User đã có bản ghi giờ làm việc trong ngày hôm nay và chưa đăng xuất.");
                        }
                    }
                } else {
                    // Nếu không đúng giờ
                    $redirect = "salary_result.html";
                    $hasScheduleToday = false;

                    // Vẫn cập nhật token, nhưng không cập nhật trạng thái online
                    $updateSql = "UPDATE users SET auth_token = ? , is_online = 0 WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("si", $token, $user['id']);
                    $updateStmt->execute();
                }
            } else {
                $redirect = "salary_result.html";
                $hasScheduleToday = false;

                // Vẫn cập nhật token, nhưng không cập nhật trạng thái online
                $updateSql = "UPDATE users SET auth_token = ? , is_online = 0 WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("si", $token, $user['id']);
                $updateStmt->execute();
            }
        } else {
            // Nếu không có lịch làm việc
            $redirect = "salary_result.html";
            $hasScheduleToday = false;

            // Vẫn cập nhật token, nhưng không cập nhật trạng thái online
            $updateSql = "UPDATE users SET auth_token = ?, is_online = 0 WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("si", $token, $user['id']);
            $updateStmt->execute();
        }
    } else {
        // Nếu là admin, cho phép đăng nhập mà không cần kiểm tra ngày
        $redirect = "tongquan.html";
        $hasScheduleToday = true;

        $updateSql = "UPDATE users SET auth_token = ?, is_online = 1 WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $token, $user['id']);
        $updateStmt->execute();
    }

    echo json_encode([
        "success" => true,
        "redirect" => $redirect,
        "token" => $token,
        "role" => $user['role'],
        "email" => $user['email'],
        "employee_code" => $user['employee_code'],
        "name" => $user['employee_name'],
        "hasScheduleToday" => $hasScheduleToday
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Email/số điện thoại hoặc mật khẩu không đúng!"
    ]);
}

$stmt->close();
$conn->close();
