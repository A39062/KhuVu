<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'user_db');

// Check connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Fetch the top 10 best-selling products
$query = "
    SELECT p.name, SUM(hi.quantity) AS total_sold
    FROM hoadon_items hi
    JOIN products p ON hi.product_id = p.id
    GROUP BY p.name
    ORDER BY total_sold DESC
    LIMIT 10
";

$result = $conn->query($query);

$topProducts = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $topProducts[] = $row;
    }
}

// Output as JSON
echo json_encode($topProducts);

// Close connection
$conn->close();
