<?php

require_once __DIR__ . '/vendor/autoload.php'; 

require 'cors.php';
// Handle pre-flight OPTIONS request (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include necessary files
include 'db.php';
include 'vendor/autoload.php'; // Ensure to include JWT library

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

$secretKey = 'sic'; // Replace this with your actual secret key

function checkJwtCookie() {
    global $secretKey;

    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
            if (empty($secretKey)) {
                header("HTTP/1.1 500 Internal Server Error");
                echo json_encode(["error" => "Secret key is not defined."]);
                exit();
            }

            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

            if (!isset($decoded->role) || $decoded->role !== 'admin') {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(["error" => "You are not an admin."]);
                exit();
            }

            return $decoded;
        } catch (Exception $e) {
            header("HTTP/1.1 401 Unauthorized");
            echo json_encode(["error" => "Unauthorized - " . $e->getMessage()]);
            exit();
        }
    } else {
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode(["error" => "Unauthorized - No token provided."]);
        exit();
    }
}

checkJwtCookie();

$input = json_decode(file_get_contents("php://input"), true);

// Ensure input is an array
if (!is_array($input)) {
    echo json_encode(["error" => "Invalid input. Expected an array of ideas."]);
    exit();
}

$status_id = 3; // Default status: Pending
$conn->autocommit(false);

try {
    $stmt = $conn->prepare("INSERT INTO e_ideas (student_name, school, idea_title, status_id, theme_id, type, idea_description) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($input as $idea) {
        // Validate fields
        $student_name = $idea['student_name'] ?? null;
        $school = $idea['school'] ?? null;
        $idea_title = $idea['idea_title'] ?? null;
        $theme_id = $idea['theme_id'] ?? null;
        $type = $idea['type'] ?? null;
        $idea_description = $idea['idea_description'] ?? null;

        if (empty($student_name) || empty($school) || empty($idea_title) || empty($theme_id) || empty($type) || empty($idea_description)) {
            throw new Exception("All idea registration fields are required for every idea.");
        }

        $stmt->bind_param("sssiiss", $student_name, $school, $idea_title, $status_id, $theme_id, $type, $idea_description);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(["success" => "All ideas registered successfully."]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["error" => "Failed to register ideas: "]);
} finally {
    $conn->autocommit(true);
}
