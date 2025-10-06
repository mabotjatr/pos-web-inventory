<?php
// auth_check.php - Include this at the top of every protected page
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user is still active in database
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($db) {
    try {
        $query = "SELECT is_active FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user['is_active']) {
                // User is deactivated, destroy session and redirect
                session_destroy();
                header("Location: login.php?error=account_deactivated");
                exit;
            }
        } else {
            // User doesn't exist in database anymore
            session_destroy();
            header("Location: login.php?error=invalid_session");
            exit;
        }
    } catch (Exception $e) {
        // Continue with session even if check fails (for availability)
    }
}
?>