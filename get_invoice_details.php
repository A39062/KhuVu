<?php
header('Content-Type: application/json');

$invoiceId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$conn = new mysqli('localhost', 'root', '', 'user_db');

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Cập nhật query để lấy thông tin hóa đơn và tên sản phẩm
$invoiceQuery = $conn->prepare("
    SELECT o.id, o.created_at as date, o.total_amount, o.discount, 
           MAX(oi.payment_method) as payment_method,
           GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.id = ? 
    GROUP BY o.id, o.created_at, o.total_amount, o.discount
    LIMIT 1
");
$invoiceQuery->bind_param("i", $invoiceId);
$invoiceQuery->execute();
$invoiceResult = $invoiceQuery->get_result();
$invoice = $invoiceResult->fetch_assoc();

// Fetch invoice items với tên sản phẩm từ bảng products
$itemsQuery = $conn->prepare("
    SELECT 
        p.name, 
        oi.quantity, 
        oi.price,
        oi.payment_method
    FROM 
        order_items oi 
    JOIN 
        products p ON oi.product_id = p.id 
    WHERE 
        oi.order_id = ?
");
$itemsQuery->bind_param("i", $invoiceId);
$itemsQuery->execute();
$itemsResult = $itemsQuery->get_result();

$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode([
    'invoice' => $invoice,
    'items' => $items
]);

$conn->close();
