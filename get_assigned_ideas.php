<?php
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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function checkJwtCookie() {
    global $secretKey;

    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

            if (!isset($decoded->role) || $decoded->role !== 'evaluator') {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(["error" => "You are not an evaluator."]);
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

function getIdeasByEvaluatorId($evaluatorId) {
    global $conn;

    $query = "
    SELECT i.*,ie.status
    FROM e_ideas i
    INNER JOIN e_idea_evaluators ie ON i.id = ie.idea_id
    WHERE ie.evaluator_id = ?
";

    $stmt = $conn->prepare($query);

    // Check if the query was prepared successfully
    if ($stmt === false) {
        die(json_encode([ 
            "error" => "Failed to prepare SQL query.", 
            "sql_error" => $conn->error 
        ]));
    }

    // Bind the evaluator ID as a parameter
    $stmt->bind_param('i', $evaluatorId);

    // Execute the query
    $stmt->execute();

    // Get the result set
    $result = $stmt->get_result();

    // Fetch all ideas assigned to the evaluator
    $ideas = [];
    while ($row = $result->fetch_assoc()) {
        $ideas[] = $row;
    }

    return $ideas;
}


// Check JWT cookie for valid admin user
$decodedUser = checkJwtCookie();

// Get evaluator ID from request query parameters
$evaluatorId = isset($_GET['evaluator_id']) ? (int) $_GET['evaluator_id'] : null;

// Debugging step: log the evaluator_id
error_log("Received evaluator_id: " . $evaluatorId);


if ($evaluatorId) {
    // Fetch the evaluator by ID
    $ideas = getIdeasByEvaluatorId($evaluatorId);

    error_log("Fetched evaluator: " . json_encode($ideas));

    if ($ideas) {
        echo json_encode([
            "status" => "success",
            "ideas" => $ideas,
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Ideas not found."
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "No evaluator  ID provided."
    ]);
}

?>
