<?php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$database = "user_db";

$conn = new mysqli($servername, $username, $password, $database);

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if ($token) {
    $sql = "SELECT id, email, role, is_online FROM users WHERE auth_token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode([
            'loggedIn' => true,
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'is_online' => $user['is_online']
        ]);
        exit;
    }
}

echo json_encode(['loggedIn' => false]);
$conn->close();
