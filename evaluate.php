<?php
header("Access-Control-Allow-Origin: http://localhost:5173"); // Replace with your frontend URL
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'vendor/autoload.php'; // Include JWT library (e.g., Firebase JWT)
require 'db.php'; // Include database connection

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Define your secret key for JWT
$secretKey = "sic";

// Middleware function to validate the admin or evaluator session using cookies
function checkJwtCookie() {
    global $secretKey;

    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

            // Allow only admin and evaluator roles
            if (!isset($decoded->role) || !in_array($decoded->role, ['admin', 'evaluator'])) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(["error" => "You are not authorized to perform this action."]);
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

// Validate the user using the middleware
$user = checkJwtCookie();

// Get JSON input and decode it
$input = json_decode(file_get_contents("php://input"), true);

// Extract data from the request
$idea_id = $input['idea_id'] ?? null;
$evaluator_id = $input['evaluator_id'] ?? null;
$novelty_score = $input['novelty_score'] ?? null;
$usefulness_score = $input['usefulness_score'] ?? null;
$feasability_score = $input['feasability_score'] ?? null;
$scalability_score = $input['scalability_score'] ?? null;
$sustainability_score = $input['sustainability_score'] ?? null;
$comment = $input['comment'] ?? null;
$score = $input['score'] ?? null;
$status = $input['status'] ?? null;

// Check if required fields are present
if (empty($idea_id) || empty($evaluator_id)) {
    http_response_code(400);
    echo json_encode(["error" => "Idea ID and Evaluator ID are required."]);
    exit;
}

// Check if the idea_id exists in the ideas table
$stmt_check_idea = $conn->prepare("SELECT COUNT(*) FROM ideas WHERE id = ?");
$stmt_check_idea->bind_param("i", $idea_id);
$stmt_check_idea->execute();
$stmt_check_idea->bind_result($idea_count);
$stmt_check_idea->fetch();
$stmt_check_idea->free_result();

if ($idea_count == 0) {
    http_response_code(404);
    echo json_encode(["error" => "Invalid idea_id: $idea_id. The idea does not exist."]);
    exit;
}

// Check if the evaluator_id exists in the evaluator table
$stmt_check_evaluator = $conn->prepare("SELECT COUNT(*) FROM evaluator WHERE id = ?");
$stmt_check_evaluator->bind_param("i", $evaluator_id);
$stmt_check_evaluator->execute();
$stmt_check_evaluator->bind_result($evaluator_count);
$stmt_check_evaluator->fetch();
$stmt_check_evaluator->free_result();

if ($evaluator_count == 0) {
    http_response_code(404);
    echo json_encode(["error" => "Invalid evaluator_id: $evaluator_id. The evaluator does not exist."]);
    exit;
}

try {

    $stmt_check_evaluation_count = $conn->prepare("
        SELECT COUNT(*) 
        FROM idea_evaluators 
        WHERE idea_id = ? AND score IS NOT NULL
    ");
    $stmt_check_evaluation_count->bind_param("i", $idea_id);
    $stmt_check_evaluation_count->execute();
    $stmt_check_evaluation_count->bind_result($evaluation_count);
    $stmt_check_evaluation_count->fetch();
    $stmt_check_evaluation_count->free_result();

   
    if ($evaluation_count === 3) {
     
        $stmt_check_scores = $conn->prepare("
            SELECT COUNT(*) 
            FROM idea_evaluators 
            WHERE idea_id = ? AND score > 35
        ");
        $stmt_check_scores->bind_param("i", $idea_id);
        $stmt_check_scores->execute();
        $stmt_check_scores->bind_result($high_score_count);
        $stmt_check_scores->fetch();
        $stmt_check_scores->free_result();

        // Update the state of the idea based on the number of high scores
        $state = ($high_score_count >= 2) ? 1 : 0;

        $stmt_update_state = $conn->prepare("UPDATE ideas SET state = ? WHERE id = ?");
        $stmt_update_state->bind_param("ii", $state, $idea_id);
        $stmt_update_state->execute();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update idea state: " . $e->getMessage()]);
}

?>
