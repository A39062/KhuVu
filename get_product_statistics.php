<?php
header('Content-Type: application/json');

// Kết nối đến database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$categoryId = isset($_GET['category_id']) ? $_GET['category_id'] : '';
$importDate = isset($_GET['import_date']) ? $_GET['import_date'] : '';

// Truy vấn lấy dữ liệu từ product_import_logs, kết hợp với products
$sql = "SELECT pil.id, pil.product_id, pil.code, pil.name, pil.quantity_added, pil.import_date, p.cost_price
        FROM product_import_logs pil
        JOIN products p ON pil.product_id = p.id
        WHERE p.is_active = 1";

if ($categoryId) {
    $sql .= " AND p.category_id = " . $conn->real_escape_string($categoryId);
}

if ($importDate) {
    $sql .= " AND DATE(pil.import_date) = '" . $conn->real_escape_string($importDate) . "'";
}

$result = $conn->query($sql);

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);

$conn->close();
