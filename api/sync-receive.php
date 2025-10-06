<?php
// api/sync-receive.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input && isset($input['products']) && isset($input['sync_key'])) {
        // Enhanced authentication
        $valid_keys = [
            'your_secret_sync_key_2024',
            'backup_sync_key_2024'
        ];
        
        if (!in_array($input['sync_key'], $valid_keys)) {
            $response['message'] = 'Invalid sync key';
            echo json_encode($response);
            exit;
        }
        
        include '../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            $response['message'] = 'Database connection failed';
            echo json_encode($response);
            exit;
        }
        
        try {
            $db->beginTransaction();
            $processed = 0;
            $updated = 0;
            $added = 0;
            
            foreach ($input['products'] as $product) {
                // Check if product exists by local_id
                $checkQuery = "SELECT id, stock_quantity FROM products WHERE local_id = :local_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':local_id', $product['productId']);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    // Update existing product
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    $updateQuery = "UPDATE products SET 
                        name = :name, 
                        price = :price, 
                        category = :category,
                        stock_quantity = :stock_quantity,
                        barcode = :barcode,
                        image_path = :image_path,
                        last_sync = NOW()
                    WHERE local_id = :local_id";
                    
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':local_id', $product['productId']);
                    $updateStmt->bindParam(':barcode', $product['barcode']);
                    $updateStmt->bindParam(':name', $product['name']);
                    $updateStmt->bindParam(':price', $product['price']);
                    $updateStmt->bindParam(':category', $product['category']);
                    $updateStmt->bindParam(':stock_quantity', $product['stockQuantity']);
                    $updateStmt->bindParam(':image_path', $product['imagePath']);
                    $updateStmt->execute();
                    
                    // Log stock change if different
                    if ($existing['stock_quantity'] != $product['stockQuantity']) {
                        $historyQuery = "INSERT INTO stock_history 
                                        (product_id, old_stock, new_stock, change_type, notes, changed_by) 
                                        VALUES (:product_id, :old_stock, :new_stock, 'SALE', :notes, 'POS System')";
                        $historyStmt = $db->prepare($historyQuery);
                        $historyStmt->bindParam(':product_id', $existing['id']);
                        $historyStmt->bindParam(':old_stock', $existing['stock_quantity']);
                        $historyStmt->bindParam(':new_stock', $product['stockQuantity']);
                        $notes = "Stock updated via POS sync";
                        $historyStmt->bindParam(':notes', $notes);
                        $historyStmt->execute();
                    }
                    
                    $updated++;
                } else {
                    // Insert new product
                    $insertQuery = "INSERT INTO products 
                        (local_id, barcode, name, price, category, stock_quantity, image_path, last_sync) 
                        VALUES (:local_id, :barcode, :name, :price, :category, :stock_quantity, :image_path, NOW())";
                    
                    $insertStmt = $db->prepare($insertQuery);
                    $insertStmt->bindParam(':local_id', $product['productId']);
                    $insertStmt->bindParam(':barcode', $product['barcode']);
                    $insertStmt->bindParam(':name', $product['name']);
                    $insertStmt->bindParam(':price', $product['price']);
                    $insertStmt->bindParam(':category', $product['category']);
                    $insertStmt->bindParam(':stock_quantity', $product['stockQuantity']);
                    $insertStmt->bindParam(':image_path', $product['imagePath']);
                    $insertStmt->execute();
                    
                    $added++;
                }
                
                $processed++;
            }
            
            // Log sync
            $logQuery = "INSERT INTO sync_logs (sync_type, records_processed, status) 
                         VALUES ('PUSH', :processed, 'SUCCESS')";
            $logStmt = $db->prepare($logQuery);
            $logStmt->bindParam(':processed', $processed);
            $logStmt->execute();
            
            $db->commit();
            
            $response['success'] = true;
            $response['message'] = "Sync completed: $processed products processed ($added added, $updated updated)";
            $response['processed'] = $processed;
            $response['added'] = $added;
            $response['updated'] = $updated;
            
        } catch (Exception $e) {
            $db->rollBack();
            
            // Log failed sync
            $logQuery = "INSERT INTO sync_logs (sync_type, records_processed, status, error_message) 
                         VALUES ('PUSH', 0, 'FAILED', :error)";
            $logStmt = $db->prepare($logQuery);
            $errorMsg = $e->getMessage();
            $logStmt->bindParam(':error', $errorMsg);
            $logStmt->execute();
            
            $response['message'] = 'Sync failed: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Invalid data format or missing sync key';
    }
} else {
    $response['message'] = 'Only POST requests allowed';
}

echo json_encode($response);
?>