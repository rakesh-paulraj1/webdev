<?php
require 'cors.php';

// Handle preflight requests (OPTIONS method)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'vendor/autoload.php';
require 'db.php';  
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secretKey = "sic";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function checkJwtCookie() {
    global $secretKey;

    // Check if the auth token is stored in the cookie
    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
            // Decode the JWT token to verify and check the role
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

            // Check if the role is 'admin'
            if (!isset($decoded->role) || $decoded->role !== 'admin') {
                // Return a 403 Forbidden status and a custom message
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(["error" => "You are not an admin."]);
                exit();
            }

            // Return the decoded JWT data if role is 'admin'
            return $decoded;

        } catch (Exception $e) {
            // If the JWT verification fails, return a 401 Unauthorized status with the error message
            header("HTTP/1.1 401 Unauthorized");
            echo json_encode(["error" => "Unauthorized - " . $e->getMessage()]);
            exit();
        }
    } else {
        // If the auth token is missing, return a 401 Unauthorized status with a message
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode(["error" => "Unauthorized - No token provided."]);
        exit();
    }
}

// Get JSON input and decode it
$input = json_decode(file_get_contents("php://input"), true);

// Extract the idea ID from the input
$idea_id = $input['idea_id'] ?? null;

// Check if the idea ID is provided
if (empty($idea_id)) {
    echo json_encode(["error" => "Idea ID is required to approve the idea."]);
    exit;
}

// Check JWT cookie for valid admin user
$decodedUser = checkJwtCookie();

// Start transaction
$conn->autocommit(false);

try {
    // Check if the idea exists and has a status of 3 (Pending)
    $stmt = $conn->prepare("SELECT status_id FROM sic_qc_ideas WHERE id = ? AND status_id = 3");
    $stmt->bind_param("i", $idea_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        echo json_encode(["error" => "Idea not found or is not pending for approval."]);
        exit;
    }

    // Update the status of the idea to 1 (Active)
    $stmt = $conn->prepare("UPDATE sic_qc_ideas SET status_id = 1 WHERE id = ?");
    $stmt->bind_param("i", $idea_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();
    echo json_encode(["success" => "Idea approved successfully."]);

} catch (Exception $e) {
    // Rollback if something goes wrong
    $conn->rollback();
    echo json_encode(["error" => "Failed to approve idea: " . $e->getMessage()]);
} finally {
    // End transaction mode
    $conn->autocommit(true);
}
?>
