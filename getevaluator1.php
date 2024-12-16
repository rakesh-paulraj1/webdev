<?php
require 'cors.php';
// Handle preflight requests (OPTIONS method)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'vendor/autoload.php';
require 'db.php'; // Include your database connection file
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Secret key for JWT
$secretKey = "sic";

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Sanitize user input to prevent XSS attacks.
 */
function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Middleware to validate JWT from cookies.
 */
function checkJwtCookie() {
    global $secretKey;

    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
            // Decode the JWT
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

            // Ensure the user is an admin
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

/**
 * Fetch evaluator details by ID.
 */
function getEvaluatorById($id) {
    global $conn;

    $query = "SELECT id, first_name, last_name, email, phone_number, city, gender, college_name, designation, 
                     knowledge_domain, theme_preference_1, theme_preference_2, theme_preference_3, role_interested, 
                     evaluator_status, expertise_in_startup_value_chain, alternate_email, alternate_phone_number,total_experience,languages_known, state 
              FROM sic_qa_evaluator 
              WHERE id = ? AND delete_status = 0";

    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode([
            "error" => "Failed to prepare SQL query.",
            "sql_error" => $conn->error
        ]);
        exit();
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        http_response_code(500);
        echo json_encode([
            "error" => "Failed to execute SQL query.",
            "sql_error" => $conn->error
        ]);
        exit();
    }

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return null;
    }
}

// Ensure the user is an admin
$decodedUser = checkJwtCookie();

// Parse JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate and sanitize `evaluator_id`
$evaluatorId = isset($input['evaluator_id']) ? sanitizeInput((int)$input['evaluator_id']) : null;

if (!$evaluatorId || $evaluatorId <= 0) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(["error" => "Missing or invalid evaluator_id."]);
    exit();
}

// Fetch evaluator details
$evaluator = getEvaluatorById($evaluatorId);

if ($evaluator) {
    echo json_encode([
        "status" => "success",
        "evaluator" => $evaluator,
    ]);
} else {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(["error" => "Evaluator not found."]);
}
?>
