<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'user_db');

// Check connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Fetch daily revenue
$query = "
    SELECT DATE(hoadon.date) AS day, SUM(hoadon_items.total_amount) AS revenue
    FROM hoadon
    JOIN hoadon_items ON hoadon.id = hoadon_items.hoadon_id
    GROUP BY day
    ORDER BY day
";
$result = $conn->query($query);

$labels = [];
$revenue = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['day'];
    $revenue[] = $row['revenue'];
}

// Output as JSON
echo json_encode([
    'labels' => $labels,
    'revenue' => $revenue
]);

// Close connection
$conn->close();
