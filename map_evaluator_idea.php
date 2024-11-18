<?php
// Handle the preflight (OPTIONS) request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: http://localhost:5173");  // Allow your frontend's origin
    header("Access-Control-Allow-Credentials: true");              // Allow credentials (if needed)
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");    // Allowed methods
    header("Access-Control-Allow-Headers: Content-Type, Authorization");  // Allowed headers
    header("Access-Control-Max-Age: 86400"); // Cache preflight request for 24 hours
    exit;  // End the script after handling the OPTIONS request
}

// The following headers will be applied to the actual request
header("Access-Control-Allow-Origin: http://localhost:5173");  // Allow your frontend's origin
header("Access-Control-Allow-Credentials: true");              // Allow credentials (if needed)
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");    // Allowed methods
header("Access-Control-Allow-Headers: Content-Type, Authorization");  // Allowed headers

// Include database connection
include 'db.php';

// Get JSON input and decode it
$input = json_decode(file_get_contents("php://input"), true);

// Extract data from the request
$idea_id = $input['idea_id'] ?? null;
$evaluator_ids = $input['evaluator_ids'] ?? null;  // Array of evaluator IDs
$score = $input['score'] ?? null;
$evaluator_comments = $input['evaluator_comments'] ?? null;

// Check if required fields are present
if (empty($idea_id) || empty($evaluator_ids)) {
    echo json_encode(["error" => "Idea ID and Evaluator IDs are required."]);
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

// Start transaction
$conn->autocommit(false);

try {
    // Loop through the evaluator IDs and insert each one
    foreach ($evaluator_ids as $evaluator_id) {
        // Check if the evaluator_id exists in the evaluator table
        $stmt_check_evaluator = $conn->prepare("SELECT COUNT(*) FROM evaluator WHERE id = ?");
        $stmt_check_evaluator->bind_param("i", $evaluator_id);
        $stmt_check_evaluator->execute();
        $stmt_check_evaluator->bind_result($evaluator_count);
        $stmt_check_evaluator->fetch();
        $stmt_check_evaluator->free_result();

        if ($evaluator_count == 0) {
            echo json_encode(["error" => "Invalid evaluator_id: $evaluator_id. The evaluator does not exist."]);
            exit;
        }

        // Insert data into idea_evaluators table for each evaluator
        $stmt = $conn->prepare("INSERT INTO idea_evaluators (idea_id, evaluator_id, score, evaluator_comments) VALUES (?, ?, ?, ?)");
        // If score or evaluator_comments is null, bind them as such
        $stmt->bind_param("iiis", $idea_id, $evaluator_id, $score, $evaluator_comments);
        $stmt->execute();
    }
    
    // Now update the idea_status to 2 (assuming 2 means "evaluated")
    $stmt_update_status = $conn->prepare("UPDATE ideas SET status_id = 2 WHERE id = ?");
    $stmt_update_status->bind_param("i", $idea_id);
    $stmt_update_status->execute();
    
    // Commit transaction
    $conn->commit();
    echo json_encode(["success" => "Evaluators successfully mapped to the idea and idea status updated."]);

} catch (Exception $e) {
    // Rollback if something goes wrong
    $conn->rollback();
    echo json_encode(["error" => "Failed to map evaluators and update idea status: " . $e->getMessage()]);
} finally {
    // End transaction mode
    $conn->autocommit(true);
}
?>
