<?php
header('Content-Type: application/json');

// Enable error reporting but log to file instead of output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php-error.log');

try {
    // Kết nối tới MySQL
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "user_db";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Kiểm tra kết nối
    if ($conn->connect_error) {
        throw new Exception("Kết nối thất bại: " . $conn->connect_error);
    }

    // Lấy dữ liệu từ request
    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !$data || !isset($data['employee_code']) || !isset($data['employee_name']) ||
        !isset($data['email']) || !isset($data['password']) ||
        !isset($data['phone']) || !isset($data['role'])
    ) {
        throw new Exception("Dữ liệu không hợp lệ");
    }

    $employeeCode = $data['employee_code'];
    $employeeName = $data['employee_name'];
    $email = $data['email'];
    // Bỏ hash password, lưu trực tiếp mật khẩu gốc
    $password = $data['password'];
    $phone = $data['phone'];
    $role = $data['role'];
    $notes = isset($data['notes']) ? $data['notes'] : '';

    // Kiểm tra nếu email, mã nhân viên hoặc số điện thoại đã tồn tại
    $sql = "SELECT * FROM users WHERE email = ? OR phone = ? OR employee_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $email, $phone, $employeeCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $existingUser = $result->fetch_assoc();
        if ($existingUser['email'] === $email) {
            echo json_encode([
                "success" => false,
                "message" => "Email đã tồn tại!"
            ]);
        } elseif ($existingUser['employee_code'] === $employeeCode) {
            echo json_encode([
                "success" => false,
                "message" => "Mã nhân viên đã tồn tại!"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Số điện thoại đã tồn tại!"
            ]);
        }
    } else {
        // Thêm người dùng mới
        $sql = "INSERT INTO users (employee_code, employee_name, email, password, phone, role, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssss",
            $data['employee_code'],
            $data['employee_name'],
            $data['email'],
            $password,
            $data['phone'],
            $data['role'],
            $notes  // Đảm bảo biến này được bind
        );

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Đăng ký thành công!"
            ]);
        } else {
            throw new Exception("Đăng ký thất bại!");
        }
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
