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


header("Access-Control-Allow-Origin: http://localhost:5173");  // Allow your frontend's origin
header("Access-Control-Allow-Credentials: true");              // Allow credentials (if needed)
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");         // Allowed methods
header("Access-Control-Allow-Headers: Content-Type, Authorization");  // Allowed headers

// Include database connection
include 'db.php';

// Read the input data
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

// Debugging: Log raw input and parsed data
file_put_contents("php_debug_log.txt", $rawInput . PHP_EOL, FILE_APPEND);
file_put_contents("php_debug_log.txt", print_r($data, true) . PHP_EOL, FILE_APPEND);

// Extract idea_id and evaluator_id from the JSON input
$idea_id = $data['idea_id'] ?? null;
$evaluator_id = $data['evaluator_id'] ?? null;

if (empty($idea_id) || empty($evaluator_id)) {
    echo json_encode(["error" => "Idea ID and Evaluator ID are required."]);
    exit;
}

// Prepare the SQL query to fetch the details from idea_evaluators, evaluator, and ideas
$stmt = $conn->prepare(
    "SELECT 
        ie.evaluator_id, 
        CONCAT(e.first_name, ' ', e.last_name) AS evaluator_name, 
        ie.score, 
        ie.evaluator_comments,
        ie.noveltyScore AS novelty_score,
        ie.usefullness AS usefulness_score,
        ie.feasability AS feasibility_score,
        ie.scalability AS scalability_score,
        ie.sustainability AS sustainability_score,
        i.idea_title AS idea_title,
        i.idea_description AS idea_description,
        i.student_name AS student_name,
        i.school AS school,
        i.type AS idea_type

     FROM idea_evaluators ie
     JOIN evaluator e ON ie.evaluator_id = e.id
     JOIN ideas i ON ie.idea_id = i.id
     WHERE ie.idea_id = ? AND ie.evaluator_id = ?"
);
$stmt->bind_param("ii", $idea_id, $evaluator_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if there are any results
if ($result->num_rows > 0) {
    $details = $result->fetch_assoc();
    echo json_encode(["success" => "Data retrieved successfully.", "data" => $details]);
} else {
    echo json_encode(["error" => "No details found for the given idea_id and evaluator_id."]);
}

$stmt->close();
$conn->close();
?>
