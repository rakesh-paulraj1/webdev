<?php
use Dotenv\Dotenv;

// Load environment variables from .env file
require 'cors.php';
// Handle preflight requests (OPTIONS method)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'vendor/autoload.php';
require 'db.php';  // Include your database connection file
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secretKey = "sic";

// Read and decode the JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input fields for signin
if (empty($data['email']) || empty($data['password'])) {
    die(json_encode(["error" => "Email and password are required for signin."]));
}

$email = htmlspecialchars(trim($data['email']));
$password = $data['password'];

// Prepare SQL query to fetch admin data
$stmt = $conn->prepare("SELECT id, password FROM admin WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die(json_encode(["error" => "Invalid email or password."]));
}

$stmt->bind_result($id, $hashedPassword);
$stmt->fetch();


if (password_verify($password, $hashedPassword)) {

    $payload = [
        "iss" => "your_issuer",
        "aud" => "your_audience",
        "iat" => time(),
        "nbf" => time(),
        "email" => $email,
        "role" => "admin"
    ];
    
    $jwt = JWT::encode($payload, $secretKey, 'HS256');
    
    setcookie("auth_token", $jwt, [
        "expires" => time() + 3600,
        "path" => "/",
        "domain" => "localhost",
        "secure" => false,
        "httponly" => false,
        "samesite" => "Lax"
    ]);

    echo json_encode([
        "message" => "Signin successful",
        "role" => "admin",
        "token" => $jwt // Return token in the response
    ]);
    exit();
}




$stmt->close();
$conn->close();
?>

