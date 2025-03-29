<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $conn = new mysqli('localhost', 'root', '', 'user_db');

    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }

    // Cập nhật câu query để lấy tên sản phẩm từ bảng products
    $query = "SELECT o.id, o.created_at, o.total_amount, o.discount, 
              MAX(oi.payment_method) as payment_method,
              GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names,
              DATE(o.created_at) as date_created,
              TIME(o.created_at) as time_created
              FROM orders o 
              LEFT JOIN order_items oi ON o.id = oi.order_id
              LEFT JOIN products p ON oi.product_id = p.id";

    // Thêm điều kiện WHERE
    $whereAdded = false;

    if (isset($_GET['filter_type'])) {
        switch ($_GET['filter_type']) {
            case 'date':
                if (isset($_GET['date'])) {
                    $date = $conn->real_escape_string($_GET['date']);
                    $query .= " WHERE DATE(o.created_at) = '$date'";
                    $whereAdded = true;
                }
                break;
            case 'month':
                if (isset($_GET['month'])) {
                    $month = $conn->real_escape_string($_GET['month']);
                    $query .= " WHERE DATE_FORMAT(o.created_at, '%Y-%m') = '$month'";
                    $whereAdded = true;
                }
                break;
        }
    }

    $query .= " GROUP BY o.id, o.created_at, o.total_amount, o.discount ORDER BY o.created_at DESC";

    // Thêm log để debug
    error_log("Query: " . $query);

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }

    echo json_encode($invoices);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
