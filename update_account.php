<?php
header('Content-Type: application/json');

// Kết nối database
$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Kết nối thất bại: ' . $conn->connect_error]));
}

try {
    // Nhận dữ liệu từ request
    $data = json_decode(file_get_contents('php://input'), true);

    // Kiểm tra dữ liệu bắt buộc
    if (
        !isset($data['id']) || !isset($data['employee_code']) || !isset($data['employee_name']) ||
        !isset($data['email']) || !isset($data['phone']) || !isset($data['role'])
    ) {
        throw new Exception("Thiếu thông tin bắt buộc");
    }

    // Kiểm tra xem email và mã nhân viên có bị trùng không (trừ chính bản ghi đang sửa)
    $checkSql = "SELECT id FROM users WHERE (email = ? OR employee_code = ? OR phone = ?) AND id != ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("sssi", $data['email'], $data['employee_code'], $data['phone'], $data['id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        throw new Exception("Email, mã nhân viên hoặc số điện thoại đã tồn tại");
    }

    // Nếu có mật khẩu mới thì cập nhật cả mật khẩu
    if (!empty($data['password'])) {
        $sql = "UPDATE users SET 
                employee_code = ?,
                employee_name = ?,
                email = ?,
                phone = ?,
                password = ?,
                role = ?,
                notes = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssi",
            $data['employee_code'],
            $data['employee_name'],
            $data['email'],
            $data['phone'],
            $data['password'],
            $data['role'],
            $data['notes'],
            $data['id']
        );
    } else {
        // Nếu không có mật khẩu mới thì chỉ cập nhật các thông tin khác
        $sql = "UPDATE users SET 
                employee_code = ?,
                employee_name = ?,
                email = ?,
                phone = ?,
                role = ?,
                notes = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssi",
            $data['employee_code'],
            $data['employee_name'],
            $data['email'],
            $data['phone'],
            $data['role'],
            $data['notes'],
            $data['id']
        );
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật thành công'
        ]);
    } else {
        throw new Exception("Lỗi khi cập nhật: " . $stmt->error);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Đóng kết nối
if (isset($stmt)) $stmt->close();
if (isset($checkStmt)) $checkStmt->close();
$conn->close();
