<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'user_db');

// Check connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Fetch latest 4 days of daily revenue
$query = "
    SELECT DATE(o.created_at) AS day, SUM(o.total_amount) AS revenue
    FROM orders o
    GROUP BY day
    ORDER BY day DESC
    LIMIT 4
";
$result = $conn->query($query);

// Check for query error
if (!$result) {
    die(json_encode(['status' => 'error', 'message' => 'Query failed: ' . $conn->error]));
}

$labels = [];
$revenue = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['day'];
    $revenue[] = $row['revenue'];
}

// Đảo ngược để hiển thị từ ngày cũ đến mới
$labels = array_reverse($labels);
$revenue = array_reverse($revenue);

// Output as JSON
echo json_encode([
    'labels' => $labels,
    'revenue' => $revenue
]);

// Close connection
$conn->close();
