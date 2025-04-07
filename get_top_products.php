<?php
header('Content-Type: application/json');

// Kết nối DB
$conn = new mysqli('localhost', 'root', '', 'user_db');
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Lấy tháng từ query string (ví dụ: 2024-04)
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Truy vấn top 10 sản phẩm bán chạy trong tháng
$query = "
    SELECT 
        p.name, 
        SUM(oi.quantity) AS total_sold
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE DATE_FORMAT(o.created_at, '%Y-%m') = ?
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10;
";

// Chuẩn bị và bind tham số
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $month);
$stmt->execute();
$result = $stmt->get_result();

$topProducts = [];
while ($row = $result->fetch_assoc()) {
    $topProducts[] = $row;
}

echo json_encode($topProducts);

$stmt->close();
$conn->close();
