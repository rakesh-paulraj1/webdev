<?php
require 'cors.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secretKey = 'sic';

// Function to check JWT and ensure admin role
function checkJwtCookie() {
    global $secretKey;

    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
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

// Validate the admin using the middleware
checkJwtCookie();

// Fetch all ideas
$stmt = $conn->prepare("SELECT * FROM e_ideas");
$stmt->execute();
$result = $stmt->get_result();

// Check if any ideas were returned
$ideas = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// If no ideas found
if (empty($ideas)) {
    echo json_encode(["error" => "No ideas found."]);
    exit();
}

// Return the ideas as JSON
echo json_encode(["success" => true, "ideas" => $ideas]);
?>
