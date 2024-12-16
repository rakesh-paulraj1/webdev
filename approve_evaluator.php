<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'cors.php';

require 'vendor/autoload.php';
require 'db.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$secretKey = "sic"; // Define your secret key for JWT

// Middleware function to validate the admin session using cookies
function checkJwtCookie() {
    global $secretKey;

    if (isset($_COOKIE['auth_token'])) {
        $jwt = $_COOKIE['auth_token'];

        try {
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

// Retrieve the evaluator_id from URL parameters (GET)
if (isset($_GET['evaluator_id'])) {
    $evaluator_id = $_GET['evaluator_id'];
} else {
    die(json_encode(["error" => "Evaluator ID is required for approval."]));
}

// Get the admin's email from the session (cookie)
$adminEmail = checkJwtCookie(); // Validate the admin session using cookies

// Check if the evaluator exists and is pending approval
$stmt = $conn->prepare("SELECT id FROM sic_qa_evaluator WHERE id = ? AND evaluator_status = 3"); // 3 means pending
$stmt->bind_param("i", $evaluator_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["error" => "Evaluator ID not found or already approved."]);
} else {
    // Approve the evaluator
    $stmt = $conn->prepare("UPDATE sic_qa_evaluator SET evaluator_status = 1 WHERE id = ?");
    $stmt->bind_param("i", $evaluator_id);

    if ($stmt->execute()) {
        // Fetch the evaluator's email
        $stmt = $conn->prepare("SELECT email FROM sic_qa_evaluator WHERE id = ?");
        $stmt->bind_param("i", $evaluator_id);
        $stmt->execute();
        $stmt->bind_result($evaluatorEmail);
        $stmt->fetch();

        // Send email with sign-in link
        $emailResult = sendEmail($evaluatorEmail);
        if ($emailResult !== true) {
            echo json_encode(["error" => "Email could not be sent."]);
        } else {
            echo json_encode(["message" => "Evaluator approved successfully! An email has been sent to the evaluator.", "evaluator_id" => $evaluator_id]);
        }
    } else {
        // Log the database error
        error_log("Error executing SQL for approving evaluator: " . $stmt->error);
        echo json_encode(["error" => "Error approving evaluator. Please try again later."]);
    }
}

$stmt->close();
$conn->close();

// Function to send email with sign-in link
function sendEmail($evaluatorEmail) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Set the SMTP server to Gmail
        $mail->SMTPAuth   = true; // Enable SMTP authentication
        $mail->Username   = 'tharanikkumar6@gmail.com'; // SMTP username (Your Gmail address)
        $mail->Password   = 'srze tvqy enbt imqc'; // App password generated for Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = 587; // Port for TLS

        // Recipients
        $mail->setFrom('tharanikkumar6@gmail.com', 'Admin'); // Sender's email and name
        $mail->addAddress($evaluatorEmail); // Recipient's email

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = 'Your Account has been Approved';
        $mail->Body    = 'Dear Evaluator, <br><br>This email confirms that your account has been approved.<br><br>Please click the link below to log in:<br><a href="http://localhost/webdev/signin_evaluator.php">Log in to your account</a><br><br>Regards,<br>Admin Team';

        // Send email
        if ($mail->send()) {
            return true;
        } else {
            // Log email sending error
            error_log("Mailer Error: " . $mail->ErrorInfo);
            return false;
        }

    } catch (Exception $e) {
        // Log the exception message
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
