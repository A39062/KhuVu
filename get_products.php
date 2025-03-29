<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Kết nối không thành công: " . $conn->connect_error);
}

// Thêm điều kiện kiểm tra code
if (isset($_GET['code'])) {
    $code = $conn->real_escape_string($_GET['code']);
    $sql = "SELECT * FROM products WHERE code = '$code'";
} else {
    // Code cũ giữ nguyên
    $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    $search_code = isset($_GET['search_code']) ? $conn->real_escape_string($_GET['search_code']) : "";
    $search_name = isset($_GET['search_name']) ? $conn->real_escape_string($_GET['search_name']) : "";
    $min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
    $max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;

    $sql = "SELECT * FROM products WHERE 1=1";

    if ($category_id > 0) {
        $sql .= " AND category_id = $category_id";
    }
    if (!empty($search_code)) {
        $sql .= " AND code LIKE '%$search_code%'";
    }
    if (!empty($search_name)) {
        $sql .= " AND name LIKE '%$search_name%'";
    }
    if ($min_price !== null) {
        $sql .= " AND sale_price >= $min_price";
    }
    if ($max_price !== null) {
        $sql .= " AND sale_price <= $max_price";
    }
}

$result = $conn->query($sql);
$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close();
echo json_encode($products);
