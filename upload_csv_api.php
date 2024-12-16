<?php

require 'cors.php';
include('db.php');  // Ensure this file contains your database connection logic.

ini_set('display_errors', 1);
error_reporting(E_ALL);


if (isset($_FILES['file'])) {
    $file = $_FILES['file'];

    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Error uploading the file.']);
        exit;
    }

    // Validate file type (allow only CSV files)
    $allowedTypes = ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/plain'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only CSV files are allowed.']);
        exit;
    }

    // Set the upload directory (ensure this is writable)
    $uploadDirectory = '/Applications/XAMPP/xamppfiles/htdocs/uploads/';
    $filePath = $uploadDirectory . basename($file['name']);

    // Move the file to the server directory
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode(['success' => false, 'message' => 'Error moving the file.']);
        exit;
    }

    // Process the CSV file
    if (($csvFile = fopen($filePath, 'r')) !== FALSE) {
        $header = fgetcsv($csvFile);  // Skip the header row
        $response = [];

        while (($data = fgetcsv($csvFile)) !== FALSE) {
            $student_name = $data[0];
            $school = $data[1];
            $idea_title = $data[2];
            $status_id = $data[3];
            $theme_id = $data[4];
            $type = $data[5];
            $idea_description = $data[6];
            $idea_id = $data[7];  // This might be empty if the idea doesn't have an ID yet.
            $evaluator_id = $data[8];

            // Read the additional score fields and set default values if not provided
            $novelity_score = isset($data[9]) ? (float)$data[9] : 0;  // Ensure float value
            $usefulness_score = isset($data[10]) ? (float)$data[10] : 0;
            $feasability_score = isset($data[11]) ? (float)$data[11] : 0;  // Corrected spelling
            $scalability_score = isset($data[12]) ? (float)$data[12] : 0;
            $sustainability_score = isset($data[13]) ? (float)$data[13] : 0;
            $evaluator_comment = isset($data[14]) ? $data[14] : '';

            // If no idea_id is provided, create the idea first.
            if (empty($idea_id)) {
                // Insert the idea into the database first
                $insertStmt = $conn->prepare("INSERT INTO ideas (student_name, school, idea_title, status_id, theme_id, type, idea_description, assigned_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $assigned_count = 0;  // Initially assigned_count is set to 0
                $insertStmt->bind_param("sssiisss", $student_name, $school, $idea_title, $status_id, $theme_id, $type, $idea_description, $assigned_count);
                $insertStmt->execute();

                // Get the newly inserted idea's ID
                $idea_id = $insertStmt->insert_id;

                // Optionally, store the response if needed.
                $response[] = [
                    'student_name' => $student_name,
                    'idea_title' => $idea_title,
                    'status_id' => $status_id,
                    'view' => 'View link',
                    'action' => 'Created'
                ];
            }

            // Now, map the idea_id with the evaluator_id in the idea_evaluators table
            $stmt = $conn->prepare("INSERT INTO idea_evaluators (idea_id, evaluator_id, novelity_score, usefulness_score, feasability_score, scalability_score, sustainability_score, evaluator_comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiddddds", $idea_id, $evaluator_id, $novelity_score, $usefulness_score, $feasability_score, $scalability_score, $sustainability_score, $evaluator_comment);
            $stmt->execute();

            // After successfully mapping the evaluator, update the assigned_count and idea status
            $updateStmt = $conn->prepare("UPDATE ideas SET assigned_count = 1, status_id = 1 WHERE id = ?");
            $updateStmt->bind_param("i", $idea_id);
            $updateStmt->execute();

            // Retrieve and return the updated idea info
            $ideaQuery = $conn->prepare("SELECT student_name, idea_title, status_id FROM ideas WHERE id = ?");
            $ideaQuery->bind_param("i", $idea_id);
            $ideaQuery->execute();
            $ideaResult = $ideaQuery->get_result();
            $idea = $ideaResult->fetch_assoc();

            $response[] = [
                'student_name' => $idea['student_name'],
                'idea_title' => $idea['idea_title'],
                'view' => 'View link',  // Can replace with actual URL if needed
                'action' => 'Assigned',
                'status_id' => 1
            ];
        }

        fclose($csvFile);

        echo json_encode([
            'success' => true,
            'message' => 'File processed successfully!',
            'data' => $response
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error reading the CSV file.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
}
?>
