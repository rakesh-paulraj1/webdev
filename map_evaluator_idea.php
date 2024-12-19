<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db.php'; // Database connection
require 'vendor/autoload.php';

require 'cors.php';
// Read the raw POST data (JSON)
$data = json_decode(file_get_contents('php://input'), true);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Ensure that the expected fields are present in the incoming data
if (isset($data['idea_id'], $data['evaluator_ids']) && is_array($data['evaluator_ids'])) {
    $idea_id = $data['idea_id'];
    $evaluator_ids = $data['evaluator_ids'];

    // Prepare the statement for inserting evaluator mappings
    $insertEvaluatorStmt = $conn->prepare("INSERT INTO e_idea_evaluators (idea_id, evaluator_id) VALUES (?, ?)");

    foreach ($evaluator_ids as $evaluator_id) {
        if (!empty($evaluator_id)) {
            $insertEvaluatorStmt->bind_param("ii", $idea_id, $evaluator_id);
            if (!$insertEvaluatorStmt->execute()) {
                echo json_encode(['error' => 'Error assigning evaluators to the idea.']);
                exit;
            }
        }
    }

    // Update the idea's status to 2 (Assigned) after mapping evaluators
    $updateStatusStmt = $conn->prepare("UPDATE e_ideas SET status_id = 2 WHERE id = ?");
    $updateStatusStmt->bind_param("i", $idea_id);
    if (!$updateStatusStmt->execute()) {
        echo json_encode(['error' => 'Error updating idea status.']);
        exit;
    }

    // Success response after successfully mapping evaluators
    echo json_encode(['success' => 'Evaluators assigned successfully']);
} else {
    echo json_encode(['error' => 'Missing required fields or invalid data format.']);
}
?>
