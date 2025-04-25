<?php
// filepath: c:\xampp\htdocs\Myproject4\get_product_statistics.php
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
$orderDate = isset($_GET['order_date']) ? $_GET['order_date'] : '';

$sql = "SELECT p.name, p.cost_price, p.initial_quantity, 
               SUM(p.cost_price * p.initial_quantity) AS total_cost_price,
               SUM(p.initial_quantity) AS total_quantity
        FROM products p
        WHERE p.is_active = 1";

if ($categoryId) {
    $sql .= " AND p.category_id = " . $categoryId;
}

if ($orderDate) {
    $sql .= " AND DATE(p.created_at) = '" . $conn->real_escape_string($orderDate) . "'";
}

$sql .= " GROUP BY p.name, p.cost_price, p.initial_quantity";

$result = $conn->query($sql);

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);

$conn->close();
