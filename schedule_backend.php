<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Xử lý OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Thêm debug log
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Action: " . $_GET['action']);
error_log("Employee code: " . $_GET['employee_code']);
error_log("Token: " . $token);


$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Kết nối database thất bại']));
}

if (!isset($_GET['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu tham số action']);
    exit;
}

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if ($_GET['action'] == 'addSchedule') {
    // Nhận dữ liệu JSON từ client
    $data = json_decode(file_get_contents('php://input'), true);

    // Kiểm tra mã nhân viên có tồn tại
    $checkEmployee = "SELECT id FROM users WHERE employee_code = ?";
    $stmt = $conn->prepare($checkEmployee);
    $stmt->bind_param("s", $data['employee_code']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Mã nhân viên không tồn tại'
        ]);
        exit;
    }

    // Thêm lịch làm mới
    $sql = "INSERT INTO work_schedules (employee_code, work_date, start_time, end_time) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssss",
        $data['employee_code'],
        $data['work_date'],
        $data['start_time'],
        $data['end_time']
    );

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Thêm lịch làm thành công'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Lỗi khi thêm lịch làm: ' . $stmt->error
        ]);
    }
} elseif ($_GET['action'] == 'getSchedules') {
    $sql = "SELECT ws.*, u.employee_name, u.email, u.is_online, u.id 
            FROM work_schedules ws 
            LEFT JOIN users u ON ws.employee_code = u.employee_code 
            ORDER BY ws.work_date DESC";

    $result = $conn->query($sql);
    $schedules = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $schedules[] = $row;
        }
        echo json_encode($schedules);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Lỗi khi lấy danh sách lịch làm: ' . $conn->error
        ]);
    }
} elseif ($_GET['action'] == 'deleteSchedule') {
    $data = json_decode(file_get_contents('php://input'), true);
    $employee_code = $data['employee_code'];

    $sql = "DELETE FROM work_schedules WHERE employee_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_code);
    $stmt->execute();

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Xóa lịch làm thành công'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Lỗi khi xóa lịch làm' . $stmt->error
        ]);
    }
} elseif ($_GET['action'] == 'updateSchedule') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Thay đổi câu truy vấn để sử dụng employee_code thay vì id
    $sql = "UPDATE work_schedules 
            SET work_date = ?, start_time = ?, end_time = ? 
            WHERE employee_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssss",
        $data['work_date'],
        $data['start_time'],
        $data['end_time'],
        $data['employee_code']
    );

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Cập nhật lịch làm thành công'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Lỗi khi cập nhật lịch làm: ' . $stmt->error
        ]);
    }
} elseif ($_GET['action'] == 'getEmployeeSchedules') {
    if (!isset($_GET['employee_code']) || empty($_GET['employee_code'])) {
        echo json_encode(['status' => 'error', 'message' => 'Thiếu hoặc sai mã nhân viên']);
        exit;
    }

    $employee_code = trim($_GET['employee_code']);
    error_log("Processing schedule request for employee: " . $employee_code);

    try {
        // Kiểm tra token
        if (!$token) {
            error_log("No token provided");
            throw new Exception('Unauthorized');
        }

        // Kiểm tra token trong database
        $checkToken = "SELECT employee_code, role FROM users WHERE auth_token = ?";
        $stmt = $conn->prepare($checkToken);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn: " . $conn->error);
        }

        $stmt->bind_param("s", $token);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn token: " . $stmt->error);
        }

        $tokenResult = $stmt->get_result();
        if ($tokenResult->num_rows != 1) {
            error_log("Invalid token - rows found: " . $tokenResult->num_rows);
            throw new Exception('Token không hợp lệ');
        }

        $userInfo = $tokenResult->fetch_assoc();
        error_log("User info from token: " . json_encode($userInfo));
        $stmt->close();

        // Lấy thông tin tên nhân viên từ bảng users
        $getUserName = "SELECT employee_name FROM users WHERE employee_code = ?";
        $stmt = $conn->prepare($getUserName);
        if (!$stmt) {
            error_log("Prepare name query failed: " . $conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn tên nhân viên: " . $conn->error);
        }

        $stmt->bind_param("s", $employee_code);
        $stmt->execute();
        $nameResult = $stmt->get_result();
        $employeeName = '';

        if ($nameResult->num_rows > 0) {
            $nameRow = $nameResult->fetch_assoc();
            $employeeName = $nameRow['employee_name'];
            error_log("Found employee name: " . $employeeName);
        } else {
            error_log("No employee found with code: " . $employee_code);
        }
        $stmt->close();

        // Kiểm tra xem có lịch làm nào cho nhân viên này không
        $checkSchedules = "SELECT COUNT(*) as count FROM work_schedules WHERE employee_code = ?";
        $stmt = $conn->prepare($checkSchedules);
        $stmt->bind_param("s", $employee_code);
        $stmt->execute();
        $countResult = $stmt->get_result();
        $countRow = $countResult->fetch_assoc();
        error_log("Number of schedules found: " . $countRow['count']);
        $stmt->close();

        // Lấy danh sách lịch làm chỉ từ bảng work_schedules
        $sql = "SELECT * FROM work_schedules 
                WHERE employee_code = ? 
                ORDER BY work_date DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare schedule query failed: " . $conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn lịch làm: " . $conn->error);
        }

        $stmt->bind_param("s", $employee_code);
        if (!$stmt->execute()) {
            error_log("Execute schedule query failed: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn lịch làm: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $schedules = [];

        while ($row = $result->fetch_assoc()) {
            $schedules[] = [
                'employee_code' => $row['employee_code'],
                'employee_name' => $employeeName, // Sử dụng tên đã lấy từ bảng users
                'work_date' => $row['work_date'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time']
            ];
        }

        error_log("Final schedules array: " . json_encode($schedules));

        echo json_encode([
            'status' => 'success',
            'data' => $schedules
        ]);
    } catch (Exception $e) {
        error_log("Error in getEmployeeSchedules: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}



$conn->close();
