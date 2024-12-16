<?php
require 'cors.php';
require 'vendor/autoload.php';
require 'db.php';
  // Include your database connection file
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$secretKey = "your_secret_key";


function ensureJsonContentType() {
    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        echo json_encode(["error" => "Content-Type must be application/json"]);
        exit;
    }
}

// Middleware to parse and validate JSON input
function getJsonInput() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["error" => "Invalid JSON format: " . json_last_error_msg()]);
        exit;
    }
    return $data;
}

// Function to check if evaluator exists by ID
function evaluatorExists($evaluator_id) {
    global $conn;
    $query = "SELECT id FROM sic_qa_evaluator WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $evaluator_id);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

// Function to update evaluator status
function updateEvaluatorStatus($evaluator_id, $status) {
    global $conn;

    // Validate evaluator existence
    if (!evaluatorExists($evaluator_id)) {
        echo json_encode(["error" => "Evaluator with ID $evaluator_id does not exist."]);
        exit;
    }

    // Update the evaluator_status
    $query = "UPDATE sic_qa_evaluator SET evaluator_status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $status, $evaluator_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Evaluator status updated successfully."]);
    } else {
        echo json_encode(["error" => "Failed to update evaluator status: " . $stmt->error]);
    }

    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureJsonContentType();
    $data = getJsonInput();

    // Check required fields
    if (!isset($data['evaluator_id']) || !isset($data['status'])) {
        echo json_encode(["error" => "Evaluator ID and status are required."]);
        exit;
    }

    // Get evaluator ID and status from the request
    $evaluator_id = intval($data['evaluator_id']);
    $status = intval($data['status']); // Set status (e.g., 1 = active, 2 = inactive, 3 = pending)

    // Update evaluator status
    updateEvaluatorStatus($evaluator_id, $status);
} else {
    echo json_encode(["error" => "Invalid request method. Only POST is allowed."]);
}

$conn->close();
?>
