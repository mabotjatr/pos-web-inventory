<?php
// api/get-products.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start output buffering to catch any accidental output
ob_start();

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get the raw POST data
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        if ($input && isset($input['sync_key'])) {
            // Enhanced authentication
            $valid_keys = [
                'your_secret_sync_key_2024',
                'backup_sync_key_2024'
            ];
            
            if (!in_array($input['sync_key'], $valid_keys)) {
                throw new Exception('Invalid sync key');
            }
            
            // Include database configuration
            $configPath = __DIR__ . '/../config/database.php';
            if (!file_exists($configPath)) {
                throw new Exception('Database configuration not found');
            }
            
            include $configPath;
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                throw new Exception('Database connection failed');
            }

            // Get all products from web database
            $query = "SELECT p.id, p.local_id, p.barcode, p.name, p.price, 
                            COALESCE(c.name, 'Uncategorized') as category, 
                            p.stock_quantity, p.image_path 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    ORDER BY p.id ASC";
                    
            $stmt = $db->prepare($query);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['message'] = "Retrieved " . count($products) . " products";
            $response['products'] = $products;
            $response['timestamp'] = date('Y-m-d H:i:s');
            
            // Log the pull sync
            $logQuery = "INSERT INTO sync_logs (sync_type, records_processed, status) 
                         VALUES ('PULL', :processed, 'SUCCESS')";
            $logStmt = $db->prepare($logQuery);
            $processed = count($products);
            $logStmt->bindParam(':processed', $processed);
            $logStmt->execute();
            
        } else {
            throw new Exception('Invalid request or missing sync key');
        }
    } else {
        throw new Exception('Only POST requests allowed');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    // Log failed pull if we have database connection
    if (isset($db)) {
        try {
            $logQuery = "INSERT INTO sync_logs (sync_type, records_processed, status, error_message) 
                         VALUES ('PULL', 0, 'FAILED', :error)";
            $logStmt = $db->prepare($logQuery);
            $errorMsg = $e->getMessage();
            $logStmt->bindParam(':error', $errorMsg);
            $logStmt->execute();
        } catch (Exception $logException) {
            // Ignore log errors
        }
    }
}

// Clear any accidental output
ob_end_clean();

// Ensure we only output JSON
echo json_encode($response);
exit;
?>