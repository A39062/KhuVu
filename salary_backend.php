<?php
header('Content-Type: application/json'); // Ensure the response is JSON

$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

// Kết nối tới cơ sở dữ liệu
$conn = new mysqli($servername, $username, $password, $database);

// Kiểm tra kết nối
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Kết nối thất bại: ' . $conn->connect_error]);
    exit();
}

// Kiểm tra action được yêu cầu
if (!isset($_GET['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Không có hành động được chỉ định']);
    exit();
}

$action = $_GET['action'];

if ($action === 'updateWage') {
    $data = json_decode(file_get_contents("php://input"), true);
    $employee_code = $data['employee_code'];
    $hourly_wage = $data['hourly_wage'];

    $sql = "INSERT INTO salaries (employee_code, hourly_wage) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE hourly_wage = VALUES(hourly_wage)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param("sd", $employee_code, $hourly_wage);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }

    $stmt->close();
} elseif ($action === 'calculateSalary') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['month']) || !isset($data['year'])) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin tháng hoặc năm']);
            exit();
        }

        $month = $data['month'];
        $year = $data['year'];
        $employee_code = isset($data['employee_code']) ? $data['employee_code'] : null;

        // Truy vấn để tính lương
        // Trong phần calculateSalary, thay đổi SQL query:
        $sql = "SELECT 
            w.employee_code,
            u.employee_name,
            MONTH(w.work_date) as month,
            YEAR(w.work_date) as year,
            COUNT(DISTINCT DATE(w.work_date)) as days_worked,
            SUM(TIMESTAMPDIFF(HOUR, w.login_time, w.logout_time)) as total_hours,
            s.hourly_wage
            FROM work_times w
            LEFT JOIN users u ON w.employee_code = u.employee_code
            LEFT JOIN salaries s ON w.employee_code = s.employee_code
            WHERE MONTH(w.work_date) = ? AND YEAR(w.work_date) = ?";

        if ($employee_code) {
            $sql .= " AND w.employee_code = ?";
        }

        $sql .= " GROUP BY w.employee_code, n.name, MONTH(w.work_date), YEAR(w.work_date)";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Lỗi chuẩn bị câu lệnh: ' . $conn->error);
        }

        if ($employee_code) {
            $stmt->bind_param("iis", $month, $year, $employee_code);
        } else {
            $stmt->bind_param("ii", $month, $year);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $salaries = [];
        while ($row = $result->fetch_assoc()) {
            // Tính tổng lương
            $total_hours = $row['total_hours'] ?? 0;
            $hourly_wage = $row['hourly_wage'] ?? 0;
            $total_salary = $total_hours * $hourly_wage;

            $salaries[] = [
                'employee_code' => $row['employee_code'],
                'employee_name' => $row['employee_name'],
                'month' => $row['month'],
                'year' => $row['year'],
                'days_worked' => $row['days_worked'],
                'hours_worked' => $total_hours,
                'hourly_wage' => $hourly_wage,
                'total_salary' => $total_salary
            ];
        }

        if (empty($salaries)) {
            // Trả về mảng rỗng nếu không có dữ liệu
            echo json_encode([]);
        } else {
            echo json_encode($salaries);
        }

        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} elseif ($action === 'saveSalary') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);

        if (
            !isset($data['employee_code']) || !isset($data['month']) || !isset($data['total_hours']) ||
            !isset($data['hourly_wage']) || !isset($data['total_salary'])
        ) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin lương']);
            exit();
        }

        // Kiểm tra xem bảng employee_salaries có tồn tại không
        $tableExists = $conn->query("SHOW TABLES LIKE 'employee_salaries'");
        if ($tableExists->num_rows == 0) {
            // Tạo bảng nếu chưa tồn tại
            $createTable = "CREATE TABLE employee_salaries (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                employee_code VARCHAR(50) NOT NULL,
                employee_name VARCHAR(100),
                month INT(2) NOT NULL,
                year INT(4) NOT NULL,
                hours_worked DECIMAL(10,2) NOT NULL,
                hourly_wage DECIMAL(10,2) NOT NULL,
                total_salary DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (employee_code, month, year)
            )";
            $conn->query($createTable);
        }

        // Lấy tên nhân viên
        $stmt = $conn->prepare("SELECT employee_name FROM users WHERE employee_code = ?");
        $stmt->bind_param("s", $data['employee_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee_name = '';
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $employee_name = $row['employee_name'];
        }
        $stmt->close();

        // Lưu hoặc cập nhật thông tin lương
        $year = date('Y');
        $sql = "INSERT INTO employee_salaries 
                (employee_code, employee_name, month, year, hours_worked, hourly_wage, total_salary) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                hours_worked = VALUES(hours_worked),
                hourly_wage = VALUES(hourly_wage),
                total_salary = VALUES(total_salary)";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Lỗi chuẩn bị câu lệnh: ' . $conn->error);
        }

        $stmt->bind_param(
            "ssiiddd",
            $data['employee_code'],
            $employee_name,
            $data['month'],
            $year,
            $data['total_hours'],
            $data['hourly_wage'],
            $data['total_salary']
        );

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Đã lưu thông tin lương thành công']);
        } else {
            throw new Exception('Lỗi khi lưu thông tin lương: ' . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} elseif ($action === 'getSalaries') {
    try {
        $month = isset($_GET['month']) ? $_GET['month'] : null;
        $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $employee_code = isset($_GET['employee_code']) ? $_GET['employee_code'] : null;

        // Kiểm tra xem bảng employee_salaries có tồn tại không
        $tableExists = $conn->query("SHOW TABLES LIKE 'employee_salaries'");
        if ($tableExists->num_rows == 0) {
            echo json_encode([]);
            exit();
        }

        $sql = "SELECT * FROM employee_salaries WHERE 1=1";
        $params = [];
        $types = "";

        if ($month) {
            $sql .= " AND month = ?";
            $params[] = $month;
            $types .= "i";
        }

        if ($year) {
            $sql .= " AND year = ?";
            $params[] = $year;
            $types .= "i";
        }

        if ($employee_code) {
            $sql .= " AND employee_code = ?";
            $params[] = $employee_code;
            $types .= "s";
        }

        $sql .= " ORDER BY month DESC, employee_code ASC";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Lỗi chuẩn bị câu lệnh: ' . $conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $salaries = [];
        while ($row = $result->fetch_assoc()) {
            $salaries[] = $row;
        }

        echo json_encode($salaries);

        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ']);
}

$conn->close();
