<?php
// filepath: c:\xampp\htdocs\Myproject4\calculate_total_cost_price.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Lấy ngày hiện tại
$today = date("Y-m-d");

// Lấy danh sách tất cả các category ID từ bảng product_categories
$sql_categories = "SELECT id FROM product_categories";
$result_categories = $conn->query($sql_categories);

if ($result_categories->num_rows > 0) {
    while ($row_category = $result_categories->fetch_assoc()) {
        $category_id = $row_category['id'];

        // Tính toán total_cost_price cho category này và ngày hôm nay
        $sql_cost_price = "SELECT SUM(cost_price * quantity) AS total_cost
                           FROM products
                           WHERE category_id = $category_id
                             AND is_active = 1
                             AND DATE(created_at) = '$today'";

        $result_cost_price = $conn->query($sql_cost_price);
        $total_cost_price = 0;
        if ($result_cost_price && $result_cost_price->num_rows > 0) {
            $row_cost_price = $result_cost_price->fetch_assoc();
            $total_cost_price = $row_cost_price['total_cost'] ? $row_cost_price['total_cost'] : 0;
        }

        // Cập nhật bảng product_categories
        $sql_update = "UPDATE product_categories
                       SET total_cost_price = $total_cost_price
                       WHERE id = $category_id";

        if ($conn->query($sql_update) === TRUE) {
            echo "Category ID $category_id updated successfully<br>";
        } else {
            echo "Error updating Category ID $category_id: " . $conn->error . "<br>";
        }
    }
} else {
    echo "No categories found in product_categories table";
}

$conn->close();
