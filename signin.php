<?php

require 'cors.php';
// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


require 'vendor/autoload.php';
require 'db.php'; 
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secretKey = "sic";

// Read and decode the JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input fields
if (empty($data['email']) || empty($data['password'])) {
    die(json_encode(["error" => "Email and password are required for signin."]));
}

$email = htmlspecialchars(trim($data['email']));
$password = $data['password'];

// Define a function to generate the JWT token
function generateToken($email, $role, $id, $secretKey) {
    $payload = [
        "iss" => "your_issuer",
        "aud" => "your_audience",
        "iat" => time(),
        "nbf" => time(),
        "email" => $email,
        "role" => $role,
        "id" => $id
    ];
    return JWT::encode($payload, $secretKey, 'HS256');
}
$expirationTime = time() + (30 * 24 * 60 * 60); 
// Check credentials in the evaluator table
$stmt = $conn->prepare("SELECT id, password FROM e_evaluator WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $hashedPassword);
    $stmt->fetch();

    if (password_verify($password, $hashedPassword)) {
        $jwt = generateToken($email, "evaluator", $id, $secretKey);

        setcookie("auth_token", $jwt, [
            'expires' => $expirationTime,
    'path' => '/',
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict'
        ]);

        echo json_encode(["message" => "success", "role" => "evaluator", "id" => $id]);
        $stmt->close();
        $conn->close();
        exit();
    } else {
        die(json_encode(["error" => "Invalid email or password."]));
    }
}


$stmt = $conn->prepare("SELECT id, password FROM e_admin WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $hashedPassword);
    $stmt->fetch();

    if (password_verify($password, $hashedPassword)) {
        $jwt = generateToken($email, "admin", $id, $secretKey);

        setcookie("auth_token", $jwt, [
            'expires' => $expirationTime,
    'path' => '/',
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict'
        ]);

        echo json_encode(["message" => "success", "role" => "admin", "id" => $id]);
        $stmt->close();
        $conn->close();
        exit();
    } else {
        die(json_encode(["error" => "Invalid email or password."]));
    }
}

echo json_encode(["error" => "Invalid email or password."]);

$stmt->close();
$conn->close();
?>
