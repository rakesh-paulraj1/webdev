<?php

require 'cors.php';
// Handle preflight requests (OPTIONS method)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include ('db.php');
require 'vendor/autoload.php';
require 'db.php';  // Include your database connection file
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secretKey = "sic";

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Middleware function to validate the admin session using cookies
function checkJwtCookie() {
    global $secretKey;

   
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


// Function to fetch common statistics
function getCommonStatistics() {
    global $conn; // Ensure you're referring to the global $conn object

    // Query to get the common statistics with accurate counts for ideas and evaluators
    $query = "
        SELECT 
            (SELECT COUNT(*) FROM e_ideas) AS ideas_registered,  -- Total ideas across all evaluators
            (SELECT COUNT(*) FROM e_evaluator WHERE delete_status = 0) AS total_evaluators,  -- Total evaluators where delete_status is 0 (active)
            (SELECT COUNT(*) FROM e_ideas WHERE status_id = 3) AS pending_ideas,  -- Ideas with pending verification
            (SELECT COUNT(*) FROM e_evaluator WHERE evaluator_status = 3) AS pending_evaluators  -- Evaluators with status 0 (pending)";

    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        // Log and display detailed error information
        die(json_encode([
            "error" => "Failed to prepare SQL query.",
            "sql_error" => $conn->error
        ]));
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return null;
    }
}


// Function to fetch evaluators
function getEvaluators() {
    global $conn;

    $query = "SELECT id, first_name, last_name, email,evaluator_status, phone_number, city, state FROM e_evaluator WHERE delete_status = 0";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        // Log and display detailed error information
        die(json_encode([
            "error" => "Failed to prepare SQL query.",
            "sql_error" => $conn->error
        ]));
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $evaluators = [];
        while ($row = $result->fetch_assoc()) {
            $evaluators[] = $row;
        }
        return $evaluators;
    } else {
        return [];
    }
}

// Check JWT cookie for valid admin user
$decodedUser = checkJwtCookie();

// Fetch common statistics (Total ideas, evaluators, pending verifications)
$commonStatistics = getCommonStatistics();

// Fetch all evaluators
$evaluators = getEvaluators();

// Return common statistics and evaluators as JSON response
echo json_encode([
    "status" => "success",
    "common_statistics" => $commonStatistics,
    "evaluators" => $evaluators,
]);
?>


