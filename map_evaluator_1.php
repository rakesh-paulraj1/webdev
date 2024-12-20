<?php
require 'cors.php';
include 'db.php';

// Get JSON input and decode it
$input = json_decode(file_get_contents("php://input"), true);

// Extract data from the request
$idea_id = $input['idea_id'] ?? null;
$evaluator_id = $input['evaluator_id'] ?? null;  
$score = $input['score'] ?? null;
$evaluator_comments = $input['evaluator_comments'] ?? null;

// Check if required fields are present
if (empty($idea_id) || empty($evaluator_id)) {
    echo json_encode(["error" => "Idea ID and Evaluator ID are required."]);
    exit;
}

// Check if the idea_id exists in the ideas table
$stmt_check_idea = $conn->prepare("SELECT COUNT(*) FROM e_ideas WHERE id = ?");
$stmt_check_idea->bind_param("i", $idea_id);
$stmt_check_idea->execute();
$stmt_check_idea->bind_result($idea_count);
$stmt_check_idea->fetch();
$stmt_check_idea->free_result();

if ($idea_count == 0) {
    echo json_encode(["error" => "Invalid idea_id: $idea_id. The idea does not exist."]);
    exit;
}

// Check if the evaluator_id exists in the evaluator table
$stmt_check_evaluator = $conn->prepare("SELECT COUNT(*) FROM e_evaluator WHERE id = ?");
$stmt_check_evaluator->bind_param("i", $evaluator_id);
$stmt_check_evaluator->execute();
$stmt_check_evaluator->bind_result($evaluator_count);
$stmt_check_evaluator->fetch();
$stmt_check_evaluator->free_result();

if ($evaluator_count == 0) {
    echo json_encode(["error" => "Invalid evaluator_id: $evaluator_id. The evaluator does not exist."]);
    exit;
}

// Check if the idea already has evaluators
$stmt_check_existing_evaluators = $conn->prepare("SELECT COUNT(*) FROM e_idea_evaluators WHERE idea_id = ?");
$stmt_check_existing_evaluators->bind_param("i", $idea_id);
$stmt_check_existing_evaluators->execute();
$stmt_check_existing_evaluators->bind_result($evaluator_exists);
$stmt_check_existing_evaluators->fetch();
$stmt_check_existing_evaluators->free_result();

// Start transaction
$conn->autocommit(false);

try {
    // If no evaluators exist for the idea, update the idea status to 2
    if ($evaluator_exists == 0) {
        $stmt_update_status = $conn->prepare("UPDATE e_ideas SET status_id = 2 WHERE id = ?");
        $stmt_update_status->bind_param("i", $idea_id);
        $stmt_update_status->execute();
    }

    // Insert data into idea_evaluators table for the single evaluator
    $stmt = $conn->prepare("INSERT INTO e_idea_evaluators (idea_id, evaluator_id, score, evaluator_comments) VALUES (?, ?, ?, ?)");
    // If score or evaluator_comments is null, bind them as such
    $stmt->bind_param("iiis", $idea_id, $evaluator_id, $score, $evaluator_comments);
    $stmt->execute();

    // Commit transaction
    $conn->commit();
    echo json_encode(["success" => "Evaluator successfully mapped to the idea."]);

} catch (Exception $e) {
    // Rollback if something goes wrong
    $conn->rollback();
    echo json_encode(["error" => "Failed to map evaluator: " . $e->getMessage()]);
} finally {
    // End transaction mode
    $conn->autocommit(true);
}
?>