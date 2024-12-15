<?php

require_once __DIR__ . '/vendor/autoload.php'; 

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle pre-flight OPTIONS request (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include necessary files
include 'db.php'; 
include 'vendor/autoload.php'; // Ensure to include JWT library

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Define your secret key here or include it from a config file
$secretKey = 'sic'; // Replace this with your actual secret key

// Middleware function to validate the admin session using cookies
function checkJwtCookie() {
    global $secretKey;

    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
            // Ensure the $secretKey is defined
            if (empty($secretKey)) {
                header("HTTP/1.1 500 Internal Server Error");
                echo json_encode(["error" => "Secret key is not defined."]);
                exit();
            }

            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

            if (!isset($decoded->role) || $decoded->role !== 'admin') {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(["error" => "You are not an admin."]);
                exit();
            }

            return $decoded;
        } catch (Exception $e) {
            header("HTTP/1.1 401 Unauthorized");
            echo json_encode(["error" => "Unauthorized - " . $e->getMessage()]);
            exit();
        }
    } else {
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode(["error" => "Unauthorized - No token provided."]);
        exit();
    }
}

// Call the checkJwtCookie function to validate the cookie
$adminData = checkJwtCookie();

// Fetch all evaluators from the database
$sql = "SELECT * FROM evaluator";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Store evaluators in an array
    $evaluators = [];
    while ($row = $result->fetch_assoc()) {
        $evaluators[] = $row;
    }

    echo json_encode(["success" => "Evaluators retrieved successfully.", "evaluators" => $evaluators]);
} else {
    echo json_encode(["error" => "No evaluators found."]);
}

?>
