<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db.php';
require 'vendor/autoload.php';
// Allow CORS
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST");
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Read the raw POST data (JSON)
$data = json_decode(file_get_contents('php://input'), true);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Check if the incoming data is an array and has the expected structure
if (isset($data) && is_array($data)) {
    // Iterate over each idea entry
    foreach ($data as $idea) {
        // Ensure that the expected fields are present for each idea
        if (isset($idea['student_name'], $idea['school'], $idea['idea_title'], $idea['theme_id'], $idea['type'], $idea['idea_description'], $idea['evaluator_id_1'], $idea['evaluator_id_2'], $idea['evaluator_id_3'])) {
            // Get the data from the current idea
            $student_name = $idea['student_name'];
            $school = $idea['school'];
            $idea_title = $idea['idea_title'];
            $theme_id = $idea['theme_id'];
            $type = $idea['type'];
            $idea_description = $idea['idea_description'];
            $evaluator_id_1 = $idea['evaluator_id_1'];
            $evaluator_id_2 = $idea['evaluator_id_2'];
            $evaluator_id_3 = $idea['evaluator_id_3'];

            $status_id = 3; // Default status for new idea

            // Insert the idea into the database
            $insertStmt = $conn->prepare("INSERT INTO ideas (student_name, school, idea_title, status_id, theme_id, type, idea_description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("sssiiss", $student_name, $school, $idea_title, $status_id, $theme_id, $type, $idea_description);

            if ($insertStmt->execute()) {
                $idea_id = $insertStmt->insert_id; // Get the auto-generated idea_id

                // Insert the individual evaluator IDs into the idea_evaluators table
                $insertEvaluatorStmt = $conn->prepare("INSERT INTO idea_evaluators (idea_id, evaluator_id) VALUES (?, ?)");

                // Insert evaluator_id_1, evaluator_id_2, and evaluator_id_3 if they are provided
                if (!empty($evaluator_id_1)) {
                    $insertEvaluatorStmt->bind_param("ii", $idea_id, $evaluator_id_1);
                    if (!$insertEvaluatorStmt->execute()) {
                        echo json_encode(['error' => 'Error assigning evaluator 1 to the idea.']);
                        exit;
                    }
                }

                if (!empty($evaluator_id_2)) {
                    $insertEvaluatorStmt->bind_param("ii", $idea_id, $evaluator_id_2);
                    if (!$insertEvaluatorStmt->execute()) {
                        echo json_encode(['error' => 'Error assigning evaluator 2 to the idea.']);
                        exit;
                    }
                }

                if (!empty($evaluator_id_3)) {
                    $insertEvaluatorStmt->bind_param("ii", $idea_id, $evaluator_id_3);
                    if (!$insertEvaluatorStmt->execute()) {
                        echo json_encode(['error' => 'Error assigning evaluator 3 to the idea.']);
                        exit;
                    }
                }

                // Update the status of the idea to 2 (Assigned) after adding evaluators
                $updateStatusStmt = $conn->prepare("UPDATE ideas SET status_id = 2 WHERE id = ?");
                $updateStatusStmt->bind_param("i", $idea_id);
                $updateStatusStmt->execute();
            } else {
                echo json_encode(['error' => 'Error inserting idea into the database.']);
                exit;
            }
        } else {
            echo json_encode(['error' => 'Missing required fields in the idea data.']);
            exit;
        }
    }

    // Success response after processing all ideas
    echo json_encode(['success' => 'All ideas registered and evaluators assigned successfully.']);

} else {
    echo json_encode(['error' => 'Invalid data format or missing data.']);
}
?>
