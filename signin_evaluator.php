<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'vendor/autoload.php';
require 'db.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Read and decode the JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input fields for signin
if (empty($data['email']) || empty($data['password'])) {
    die(json_encode(["error" => "Email and password are required for signin."]));
}

$email = htmlspecialchars(trim($data['email']));
$password = $data['password'];
// Prepare and execute the query
$stmt = $conn->prepare("SELECT id, password, evaluator_status FROM evaluator WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($id, $hashedPassword, $evaluatorStatus);
$stmt->fetch();

// Check if the evaluator is approved
if ($evaluatorStatus == 3) {
    die(json_encode(["error" => "You are not approved yet. Please wait for verification."]));
}

// Verify the password if evaluator is approved
if (password_verify($password, $hashedPassword)) {
    $payload = [
        'iss' => 'your_website.com',
        'aud' => 'your_website.com',
        'iat' => time(),
        'exp' => time() + (60 * 60), // 1 hour expiration
        'email' => $email,
        'role' => 'evaluator'
    ];

    // Generate JWT token
    $jwt = JWT::encode($payload, 'sic', 'HS256');

    // Set JWT token as cookie
    setcookie("auth_token1", $jwt, [
        "expires" => time() + 3600,
        "path" => "/",
        "domain" => "localhost",
        "secure" => false,
        "httponly" => false,
        "samesite" => "Lax"
    ]);

    setcookie("role", "evaluator", [
        "expires" => time() + 3600,
        "path" => "/",
        "domain" => "localhost",
        "secure" => false,
        "httponly" => false,
        "samesite" => "Lax"
    ]);

    // Send response with token and evaluator ID
    echo json_encode([
        "message" => "Signin successful!",
        "role" => "evaluator",
        "id" => $id,
        "token" => $jwt
    ]);
} else {
    echo json_encode(["error" => "Invalid password."]);
}

?>