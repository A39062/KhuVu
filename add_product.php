<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]));
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

// Kiểm tra sản phẩm trùng tên hoặc mã
$check_stmt = $conn->prepare("SELECT code, name FROM products WHERE (code = ? OR name = ?) AND is_active = 1");
$check_stmt->bind_param("ss", $code, $name);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $existing = $check_result->fetch_assoc();
    // Nếu chỉ trùng tên hoặc chỉ trùng mã
    if ($existing['code'] !== $code || $existing['name'] !== $name) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Sản phẩm với tên hoặc mã này đã tồn tại'
        ]);
        $check_stmt->close();
        $conn->close();
        exit;
    }
}
$check_stmt->close();

// Kiểm tra sản phẩm trùng cả mã và tên
$stmt = $conn->prepare("SELECT id, quantity, initial_quantity, sale_price, cost_price 
                       FROM products 
                       WHERE code = ? AND name = ? AND is_active = 1");
$stmt->bind_param("ss", $code, $name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Nếu trùng cả mã và tên
    $product = $result->fetch_assoc();

    // Tính tổng số lượng mới
    $new_quantity = $product['quantity'] + $quantity;
    $new_initial_quantity = $product['initial_quantity'] + $quantity;

    // Cập nhật thông tin sản phẩm
    $update_stmt = $conn->prepare("UPDATE products SET 
        sale_price = ?,
        cost_price = ?,
        quantity = ?,
        initial_quantity = ?
        WHERE id = ?");

    $update_stmt->bind_param(
        "ddiii",
        $sale_price,
        $cost_price,
        $new_quantity,
        $new_initial_quantity,
        $product['id']
    );

    if ($update_stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Đã cập nhật giá và thêm số lượng cho sản phẩm đã tồn tại',
            'updated' => true
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $conn->error]);
    }
    $update_stmt->close();
} else {
    // Nếu không trùng, thêm sản phẩm mới
    $insert_stmt = $conn->prepare("INSERT INTO products 
        (category_id, code, name, sale_price, cost_price, quantity, initial_quantity, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)");

    $insert_stmt->bind_param(
        "issddii",
        $category_id,
        $code,
        $name,
        $sale_price,
        $cost_price,
        $quantity,
        $quantity
    );

    if ($insert_stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Thêm sản phẩm mới thành công']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $conn->error]);
    }
    $insert_stmt->close();
}

$stmt->close();
$conn->close();
