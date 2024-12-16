<?php
require 'cors.php';
require 'vendor/autoload.php';
require 'db.php';

function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["error" => "Invalid request method. Only POST is allowed."]));
}

// Ensure content type is JSON
if (empty($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
    die(json_encode(["error" => "Content-Type must be application/json"]));
}

// Read and decode the JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Ensure name, email, and password are provided for signup
if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
    die(json_encode(["error" => "Name, email, and password are required for signup."]));
}

$name = sanitizeInput($data['name']);
$email = sanitizeInput($data['email']);
$password = password_hash($data['password'], PASSWORD_BCRYPT);

// Check if email already exists in the admin table
$stmt = $conn->prepare("SELECT id FROM sic_qa_admin WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    die(json_encode(["error" => "Email is already registered."]));
}

// Insert new user into the admin table
$stmt = $conn->prepare("INSERT INTO sic_qa_admin (name, email, password, delete_status, created_at) VALUES (?, ?, ?, 0, NOW())");
$stmt->bind_param("sss", $name, $email, $password);

if ($stmt->execute()) {
    echo json_encode(["message" => "User registered successfully in the admin table."]);
} else {
    echo json_encode(["error" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

