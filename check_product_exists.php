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
$code = $data['code'];
$name = $data['name'];
$excludeCode = $data['excludeCode'] ?? null;

$stmt = $conn->prepare("SELECT id FROM products WHERE (code = ? OR name = ?) AND is_active = 1" . ($excludeCode ? " AND code != ?" : ""));
if ($excludeCode) {
    $stmt->bind_param("sss", $code, $name, $excludeCode);
} else {
    $stmt->bind_param("ss", $code, $name);
}

$stmt->execute();
$result = $stmt->get_result();

echo json_encode(['exists' => $result->num_rows > 0]);

$stmt->close();
$conn->close();
