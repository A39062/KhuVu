<?php
// Cấu hình error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('error_log', 'php_errors.log');

// Đặt header một lần duy nhất
header('Content-Type: application/json');

// Kết nối database
$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

try {
    $conn = new mysqli($servername, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Kết nối thất bại: " . $conn->connect_error);
    }

    // Đọc và kiểm tra input
    $input = file_get_contents("php://input");
    if (empty($input)) {
        throw new Exception("Không nhận được dữ liệu");
    }

    $data = json_decode($input, true);
    if (!$data || !isset($data['id'])) {
        throw new Exception("Thiếu ID tài khoản");
    }

    $id = intval($data['id']);
    if ($id <= 0) {
        throw new Exception("ID không hợp lệ");
    }

    // Bắt đầu transaction
    $conn->begin_transaction();

    // Lấy employee_code của nhân viên cần xóa
    $sql_get_code = "SELECT employee_code FROM users WHERE id = ?";
    $stmt_get_code = $conn->prepare($sql_get_code);
    if (!$stmt_get_code) {
        throw new Exception("Lỗi prepare statement: " . $conn->error);
    }

    $stmt_get_code->bind_param("i", $id);
    $stmt_get_code->execute();
    $result = $stmt_get_code->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Không tìm thấy nhân viên với ID này");
    }

    $row = $result->fetch_assoc();
    $employee_code = $row['employee_code'];
    $stmt_get_code->close();

    // Xóa dữ liệu liên quan trong bảng work_times
    $sql_delete_work_times = "DELETE FROM work_times WHERE employee_code = ?";
    $stmt_delete_work_times = $conn->prepare($sql_delete_work_times);
    if (!$stmt_delete_work_times) {
        throw new Exception("Lỗi prepare statement: " . $conn->error);
    }

    $stmt_delete_work_times->bind_param("s", $employee_code);
    $stmt_delete_work_times->execute();
    $stmt_delete_work_times->close();

    // Xóa nhân viên
    $sql_delete_user = "DELETE FROM users WHERE id = ?";
    $stmt_delete_user = $conn->prepare($sql_delete_user);
    if (!$stmt_delete_user) {
        throw new Exception("Lỗi prepare statement: " . $conn->error);
    }

    $stmt_delete_user->bind_param("i", $id);

    if (!$stmt_delete_user->execute()) {
        throw new Exception("Lỗi thực thi query: " . $stmt_delete_user->error);
    }

    if ($stmt_delete_user->affected_rows > 0) {
        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Xóa tài khoản thành công'
        ]);
    } else {
        throw new Exception("Không thể xóa tài khoản");
    }
} catch (Exception $e) {
    // Rollback transaction nếu có lỗi
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }

    error_log("Delete account error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt_delete_user)) {
        $stmt_delete_user->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
