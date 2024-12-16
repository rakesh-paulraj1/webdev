<?php
require 'cors.php';

require 'vendor/autoload.php';
require 'db.php';  // Include your database connection file
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secretKey = "sic"; // Define your secret key for JWT

// Sanitize input function to prevent XSS and SQL injection
function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Function to validate the admin token
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

// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["error" => "Invalid request method. Only POST is allowed."]));
}

// Ensure the content type is JSON
if (empty($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
    die(json_encode(["error" => "Content-Type must be application/json"]));
}

// Read and decode the JSON payload from the body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check if admin token is provided
if (empty($data['auth_token'])) {
    die(json_encode(["error" => "Admin token is required for editing."]));
}

$adminEmail = validateAdminToken($data['auth_token'], $secretKey); 

// Get evaluator_id from query parameters
$evaluator_id = isset($_GET['evaluator_id']) ? intval($_GET['evaluator_id']) : null;
if ($evaluator_id === null) {
    die(json_encode(["error" => "Evaluator ID is required."]));
}

// Ensure the evaluator exists and is active
$stmt = $conn->prepare("SELECT id FROM sic_qa_evaluator WHERE id = ? AND evaluator_status = 1");
$stmt->bind_param("i", $evaluator_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die(json_encode(["error" => "Evaluator ID not found or you do not have permission to edit this evaluator."]));
}

// Fields that can be updated for the idea
$fields = [
    "idea_name", "idea_description", "category", "tags", "status", "created_at", "updated_at"
];

// Prepare the SQL update query dynamically based on the provided fields
$updateFields = [];
$updateValues = [];
foreach ($fields as $field) {
    if (!empty($data[$field])) {
        $updateFields[] = "$field = ?";
        $updateValues[] = sanitizeInput($data[$field]);
    }
}

// Check if there is anything to update
if (empty($updateFields)) {
    die(json_encode(["error" => "No fields provided to update."]));
}


$updateValues[] = $evaluator_id;


$stmt = $conn->prepare("UPDATE sic_ideas SET " . implode(", ", $updateFields) . " WHERE evaluator_id = ? AND idea_id = ?");
$stmt->bind_param(str_repeat("s", count($updateValues) - 1) . "ii", ...$updateValues);

if ($stmt->execute()) {
    echo json_encode(["message" => "Idea details updated successfully!"]);
} else {
    echo json_encode(["error" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>