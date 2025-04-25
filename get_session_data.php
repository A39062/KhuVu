<?php
// filepath: c:\xampp\htdocs\Myproject4\get_session_data.php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

$conn = new mysqli($servername, $username, $password, $database);

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

// Kiểm tra xem token có hợp lệ không
if ($token) {
    $sql = "SELECT id FROM users WHERE auth_token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Lấy thông tin isWorkDay từ session
        $isWorkDay = isset($_SESSION['isWorkDay']) ? $_SESSION['isWorkDay'] : false;

        // Trả về dữ liệu dưới dạng JSON
        echo json_encode(['isWorkDay' => $isWorkDay]);
        exit;
    }
}

// Nếu token không hợp lệ, trả về false
echo json_encode(['isWorkDay' => false]);
$conn->close();
