<?php
// Dynamically handle Access-Control-Allow-Origin
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';

// Define allowed origins (add your frontend URLs here)
$allowedOrigins = [
    'http://localhost:5173',  // Example frontend for development
    'http://example.com',     // Example production URL
];

// Check if the origin is allowed or fallback to '*'
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *"); // Allow all (use with caution in production)
}

// Always set credentials
header("Access-Control-Allow-Credentials: true");

// Define allowed methods and headers
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
