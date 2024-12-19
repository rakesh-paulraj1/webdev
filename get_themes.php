<?php
header('Content-Type: application/json');
require 'cors.php';

// Handle preflight requests (OPTIONS method)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require 'db.php';

// Fetch all themes from the database
try {
    // Prepare the statement to fetch themes from the e_theme table
    $stmt = $conn->prepare("SELECT * FROM e_theme");

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch all results as an associative array
    $themes = $result->fetch_all(MYSQLI_ASSOC);

    // Close the prepared statement
    $stmt->close();

    // Check if themes exist and return as JSON
    if ($themes) {
        http_response_code(200);
        echo json_encode([ "themes" => $themes ]);
    } else {
        // If no themes found
        http_response_code(404);
        echo json_encode([ "error" => "No themes found." ]);
    }

} catch (Exception $e) {
    // Log error and return a JSON error response
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([ "error" => "Failed to fetch themes - " . $e->getMessage() ]);
    exit();
}
?>
