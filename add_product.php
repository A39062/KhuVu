<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "user_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$data = json_decode(file_get_contents('php://input'), true);
$category_id = intval($data['category_id']);
$code = $conn->real_escape_string($data['code']);
$name = $conn->real_escape_string($data['name']);
$sale_price = floatval($data['sale_price']);
$cost_price = floatval($data['cost_price']);
$quantity = intval($data['quantity']);

$sql = "INSERT INTO products (category_id, code, name, sale_price, cost_price, quantity) 
        VALUES ('$category_id', '$code', '$name', '$sale_price', '$cost_price', '$quantity')";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}

$conn->close();
