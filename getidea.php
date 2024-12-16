<?php
require 'cors.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secretKey = 'sic';


function checkJwtCookie() {
    global $secretKey;

    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

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


if (!isset($_GET['idea_id'])) {
    echo json_encode(["error" => "Idea ID is required in the query parameters."]);
    exit();
}

$idea_id = $_GET['idea_id'];


$stmt = $conn->prepare("SELECT * FROM e_ideas WHERE id = ?");
$stmt->bind_param("i", $idea_id);
$stmt->execute();
$result = $stmt->get_result();
$idea = $result->fetch_assoc();
$stmt->close();


if (!$idea) {
    echo json_encode(["error" => "Idea not found."]);
    exit();
}


echo json_encode(["success" => true, "idea" => $idea]);
?>
