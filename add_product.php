<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
    exit;
}

$category_id = intval($data['category_id']);
$code = $data['code'];
$name = $data['name'];
$sale_price = floatval($data['sale_price']);
$cost_price = floatval($data['cost_price']);
$quantity = intval($data['quantity']);

// Kiểm tra xem có sản phẩm nào đang active (is_active = 1) trùng code hoặc name không
$stmt = $conn->prepare("SELECT id FROM products WHERE (code = ? OR name = ?) AND is_active = 1 LIMIT 1");
$stmt->bind_param("ss", $code, $name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Nếu đã có sản phẩm active trùng code hoặc name, báo lỗi
    echo json_encode(['status' => 'error', 'message' => 'A product with this code or name is already active']);
} else {
    // Thêm bản ghi mới với is_active = 1, bất kể có trùng với is_active = 0 hay không
    $stmt = $conn->prepare("INSERT INTO products (category_id, code, name, sale_price, cost_price, quantity, is_active) 
                            VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("issddi", $category_id, $code, $name, $sale_price, $cost_price, $quantity);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'New product added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $conn->error]);
    }
}

$stmt->close();
$conn->close();
