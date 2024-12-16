<?php
require 'cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
require 'vendor/autoload.php';
require 'db.php';
$secretKey = "sic";

function checkJwtCookie() {
    global $secretKey;

    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

            if (!isset($decoded->role) || !in_array($decoded->role, ['admin', 'evaluator'])) {
                http_response_code(403);
                echo json_encode(["error" => "You are not authorized to perform this action."]);
                exit();
            }

            return $decoded;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized - " . $e->getMessage()]);
            exit();
        }
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized - No token provided."]);
        exit();
    }
}


$user = checkJwtCookie();


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
$status = $input['status'] ?? null;
$score = $input['score'] ?? null;

// Validate required fields
if (empty($idea_id) || empty($evaluator_id)) {
    http_response_code(400);
    echo json_encode(["error" => "Idea ID and Evaluator ID are required."]);
    exit();
}


try {
   
    $stmt_update_evaluator = $conn->prepare(
        "UPDATE sic_qa_idea_evaluators 
        SET noveltyScore = ?, usefullness = ?, feasability = ?, scalability = ?, 
            sustainability = ?, evaluator_comments = ?, score = ?, status = ? 
        WHERE idea_id = ? AND evaluator_id = ?"
    );
    $stmt_update_evaluator->bind_param(
        "iiiiisssii",
        $novelty_score, $usefulness_score, $feasability_score, $scalability_score,
        $sustainability_score, $comment, $score, $status, $idea_id, $evaluator_id
    );
    $stmt_update_evaluator->execute();

    if ($stmt_update_evaluator->affected_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "No matching record found to update scores."]);
        exit();
    }
    $stmt_update_evaluator->close();

    
    $stmt_check_evaluation_count = $conn->prepare(
        "SELECT COUNT(*) FROM sic_qa_idea_evaluators WHERE idea_id = ? AND score IS NOT NULL"
    );
    $stmt_check_evaluation_count->bind_param("i", $idea_id);
    $stmt_check_evaluation_count->execute();
    $stmt_check_evaluation_count->bind_result($evaluation_count);
    $stmt_check_evaluation_count->fetch();
    $stmt_check_evaluation_count->close();

    if ($evaluation_count == 3) {
        
        $stmt_check_scores = $conn->prepare(
            "SELECT COUNT(*) FROM sic_qa_idea_evaluators WHERE idea_id = ? AND score > 35"
        );
        $stmt_check_scores->bind_param("i", $idea_id);
        $stmt_check_scores->execute();
        $stmt_check_scores->bind_result($high_score_count);
        $stmt_check_scores->fetch();
        $stmt_check_scores->close();

      
        $state = ($high_score_count >= 2) ? 1 : 0;

        $stmt_update_state = $conn->prepare("UPDATE sic_qa_ideas SET state = ? WHERE id = ?");
        $stmt_update_state->bind_param("ii", $state, $idea_id);
        $stmt_update_state->execute();
        $stmt_update_state->close();
    }

    http_response_code(200);
    echo json_encode(["success" => "success"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update idea or evaluations: " . $e->getMessage()]);
    exit();
}
?>
