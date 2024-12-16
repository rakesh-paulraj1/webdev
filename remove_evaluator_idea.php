<?php
// Handle the preflight (OPTIONS) request
require 'cors.php';
// Include database connection
include 'db.php';

// Get JSON input and decode it
$input = json_decode(file_get_contents("php://input"), true);

// Extract data from the request
$idea_id = $input['idea_id'] ?? null;
$evaluator_id = $input['evaluator_id'] ?? null;

// Check if required fields are present
if (empty($idea_id) || empty($evaluator_id)) {
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
    echo json_encode(["error" => "Invalid idea_id: $idea_id. The idea does not exist."]);
    exit;
}

// Check if the evaluator_id exists in the evaluator table
$stmt_check_evaluator = $conn->prepare("SELECT COUNT(*) FROM sic_qa_evaluator WHERE id = ?");
$stmt_check_evaluator->bind_param("i", $evaluator_id);
$stmt_check_evaluator->execute();
$stmt_check_evaluator->bind_result($evaluator_count);
$stmt_check_evaluator->fetch();
$stmt_check_evaluator->free_result();

if ($evaluator_count == 0) {
    echo json_encode(["error" => "Invalid evaluator_id: $evaluator_id. The evaluator does not exist."]);
    exit;
}

// Start transaction
$conn->autocommit(false);

try {
    // Delete the evaluator from the idea_evaluators table
    $stmt_delete = $conn->prepare("DELETE FROM sic_qa_idea_evaluators WHERE idea_id = ? AND evaluator_id = ?");
    $stmt_delete->bind_param("ii", $idea_id, $evaluator_id);
    $stmt_delete->execute();

    // Check if the record was deleted successfully
    if ($stmt_delete->affected_rows > 0) {
        // Check if there are any remaining evaluators for the idea
        $stmt_check_remaining = $conn->prepare("SELECT COUNT(*) FROM sic_qa_idea_evaluators WHERE idea_id = ?");
        $stmt_check_remaining->bind_param("i", $idea_id);
        $stmt_check_remaining->execute();
        $stmt_check_remaining->bind_result($remaining_evaluators);
        $stmt_check_remaining->fetch();
        $stmt_check_remaining->free_result();

        // If no evaluators remain, update the status of the idea to 3
        if ($remaining_evaluators == 0) {
            $stmt_update_status = $conn->prepare("UPDATE sic_qa_ideas SET status_id = 3 WHERE id = ?");
            $stmt_update_status->bind_param("i", $idea_id);
            $stmt_update_status->execute();
        }

        // Commit transaction
        $conn->commit();
        echo json_encode(["success" => "Evaluator successfully removed from the idea."]);

    } else {
        echo json_encode(["error" => "No matching evaluator found for this idea."]);
    }
} catch (Exception $e) {
    // Rollback if something goes wrong
    $conn->rollback();
    echo json_encode(["error" => "Failed to remove evaluator: " . $e->getMessage()]);
} finally {
    // End transaction mode
    $conn->autocommit(true);
}
?>
