<?php
// Handle the preflight (OPTIONS) request
require 'cors.php';
include 'db.php';

// Get the idea_id from query parameters
$idea_id = $_GET['idea_id'] ?? null;  // Retrieve the idea_id from the query string

if (empty($idea_id)) {
    echo json_encode(["error" => "Idea ID is required."]);
    exit;
}

// Prepare the SQL query to fetch the mapped evaluators for the given idea_id
$stmt = $conn->prepare(
    "SELECT ie.evaluator_id, CONCAT(e.first_name, ' ', e.last_name) AS evaluator_name, ie.score, ie.evaluator_comments
     FROM e_idea_evaluators ie
     JOIN e_evaluator e ON ie.evaluator_id = e.id
     WHERE ie.idea_id = ?"
);
$stmt->bind_param("i", $idea_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if there are any results
if ($result->num_rows > 0) {
    $evaluators = [];
    while ($row = $result->fetch_assoc()) {
        $evaluators[] = $row;
    }
    echo json_encode(["success" => "Data retrieved successfully.", "data" => $evaluators]);
} else {
    echo json_encode(["error" => "No evaluators found for the given idea_id."]);
}

$stmt->close();
$conn->close();
?>
