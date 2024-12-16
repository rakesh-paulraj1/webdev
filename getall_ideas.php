`<?php
// Set CORS headers
require 'cors.php';
// Handle preflight requests (OPTIONS method)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require 'db.php';
require 'vendor/autoload.php'; // Include the JWT library

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secretKey = 'sic'; // Replace with your secret key

/**
 * Function to check and validate the JWT token and role from cookies.
 *
 * @return object Decoded JWT payload.
 */
function checkJwtCookie() {
    global $secretKey;

    // Check for 'role' in cookies
    $role = $_COOKIE['role'] ?? null;

    if (!$role) {
        echo json_encode(["error" => "Role is missing. Please log in again."]);
        exit();
    }

    // Determine the correct token key based on the role
    $tokenKey = $role === 'admin' ? 'auth_token' : ($role === 'evaluator' ? 'auth_token1' : null);

    if (!$tokenKey) {
        echo json_encode(["error" => "Invalid role specified: $role."]);
        exit();
    }

    // Check for the JWT token in cookies
    $jwt = $_COOKIE[$tokenKey] ?? null;

    if (!$jwt) {
        echo json_encode(["error" => "Missing token for role: $role."]);
        exit();
    }

    try {
        // Decode and validate the JWT
        $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

        // Ensure the role in the token matches the provided role
        if (!isset($decoded->role) || $decoded->role !== $role) {
            echo json_encode(["error" => "Role mismatch: Expected $role, got {$decoded->role}."]);
            exit();
        }

        return $decoded;
    } catch (Exception $e) {
        echo json_encode(["error" => "JWT Error: " . $e->getMessage()]);
        exit();
    }
}

// Validate the user using the middleware
$user = checkJwtCookie();
$role = $user->role;

// Fetch all ideas based on the user's role
try {
    $stmt = null;

    // Fetch ideas assigned to the evaluator if role is 'evaluator'
    if ($role === 'evaluator') {
        $stmt = $conn->prepare("SELECT * FROM sic_qa_ideas WHERE assigned_count = ?");
        $stmt->bind_param("s", $role);

    // Fetch all ideas if role is 'admin'
    } elseif ($role === 'admin') {
        $stmt = $conn->prepare("SELECT * FROM ideas");
    } else {
        echo json_encode(["error" => "Unauthorized role."]);
        exit();
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $ideas = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Return the ideas as JSON
    http_response_code(200);
    echo json_encode(["success" => true, "role" => $role, "ideas" => $ideas]);

} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch ideas - " . $e->getMessage()]);
    exit();
}
?>
