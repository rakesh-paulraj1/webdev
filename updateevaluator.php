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

// Function to validate the evaluator token
function checkJwtCookie() {
    global $secretKey;

    // Check if the auth token is stored in the cookie
    if (isset($_COOKIE['auth_token1'])) {
        $jwt = $_COOKIE['auth_token1'];

        try {
            // Decode the JWT token to verify and check the role
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

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

// Validate the evaluator token via the cookie
$evaluator = checkJwtCookie(); 

// Get evaluator_id from the request payload
$evaluator_id = isset($data['id']) ? intval($data['id']) : null;
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

// Fields that can be updated for the evaluator
$fields = [
    "first_name", "last_name", "email", "gender", 
    "alternate_email", "phone_number", "alternate_phone_number", 
    "college_name", "designation", "total_experience", 
    "city", "state", "knowledge_domain", "theme_preference_1", 
    "theme_preference_2", "theme_preference_3", 
    "expertise_in_startup_value_chain", "role_interested"
];

// Prepare the SQL update query dynamically based on the provided fields
$updateFields = [];
$updateValues = [];
foreach ($fields as $field) {
    if (isset($data[$field])) {
        $updateFields[] = "$field = ?";
        $updateValues[] = sanitizeInput($data[$field]);
    }
}

// Check if there is anything to update
if (empty($updateFields)) {
    die(json_encode(["error" => "No fields provided to update."]));
}

// Add evaluator_id to the update query
$updateValues[] = $evaluator_id;

// Prepare and execute the update query
$updateQuery = "UPDATE sic_qa_evaluator SET " . implode(", ", $updateFields) . " WHERE id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param(str_repeat("s", count($updateValues) - 1) . "i", ...$updateValues);

// After successfully updating the evaluator details
if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Profile updated successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error updating profile."
    ]);
}


$stmt->close();
$conn->close();
?>
