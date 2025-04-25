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

// Kiểm tra sản phẩm có tồn tại chưa
$stmt = $conn->prepare("SELECT id, quantity, initial_quantity, sale_price, cost_price FROM products WHERE code = ? AND name = ? AND is_active = 1");
$stmt->bind_param("ss", $code, $name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // ✅ Sản phẩm đã tồn tại
    $product = $result->fetch_assoc();
    $product_id = $product['id'];
    $new_quantity = $product['quantity'] + $quantity;
    $new_initial_quantity = $product['initial_quantity'] + $quantity;

    // Kiểm tra giá có thay đổi không
    $update_price = ($product['sale_price'] != $sale_price || $product['cost_price'] != $cost_price);

    if ($update_price) {
        // Có thay đổi giá
        $update_stmt = $conn->prepare("UPDATE products SET sale_price = ?, cost_price = ?, quantity = ?, initial_quantity = ? WHERE id = ?");
        $update_stmt->bind_param("ddiii", $sale_price, $cost_price, $new_quantity, $new_initial_quantity, $product_id);
    } else {
        // Không thay đổi giá
        $update_stmt = $conn->prepare("UPDATE products SET quantity = ?, initial_quantity = ? WHERE id = ?");
        $update_stmt->bind_param("iii", $new_quantity, $new_initial_quantity, $product_id);
    }

    $update_stmt->execute();
    $update_stmt->close();

    // Ghi log nhập hàng
    $log_stmt = $conn->prepare("INSERT INTO product_import_logs (product_id, code, name, quantity_added, import_date) VALUES (?, ?, ?, ?, NOW())");
    $log_stmt->bind_param("issi", $product_id, $code, $name, $quantity);
    $log_stmt->execute();
    $log_stmt->close();

    echo json_encode([
        'status' => 'success',
        'message' => 'Đã cập nhật số lượng' . ($update_price ? ' và giá' : '') . ' sản phẩm, ghi log nhập hàng.',
        'product_id' => $product_id
    ]);
} else {
    // ✅ Sản phẩm chưa có → thêm mới
    $insert_stmt = $conn->prepare("INSERT INTO products (category_id, code, name, sale_price, cost_price, quantity, initial_quantity, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $insert_stmt->bind_param("issddii", $category_id, $code, $name, $sale_price, $cost_price, $quantity, $quantity);
    $insert_stmt->execute();
    $new_product_id = $insert_stmt->insert_id;
    $insert_stmt->close();

    // Ghi log nhập hàng
    $log_stmt = $conn->prepare("INSERT INTO product_import_logs (product_id, code, name, quantity_added, import_date) VALUES (?, ?, ?, ?, NOW())");
    $log_stmt->bind_param("issi", $new_product_id, $code, $name, $quantity);
    $log_stmt->execute();
    $log_stmt->close();

    echo json_encode([
        'status' => 'success',
        'message' => 'Đã thêm sản phẩm mới và ghi log nhập hàng.',
        'product_id' => $new_product_id
    ]);
}

$conn->close();
