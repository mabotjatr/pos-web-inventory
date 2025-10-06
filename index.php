<?php
    require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .low-stock { background-color: #fff3cd; }
        .critical-stock { background-color: #f8d7da; }
        .out-of-stock { background-color: #f5c6cb; }
        .card { transition: transform 0.2s; }
        .card:hover { transform: translateY(-2px); }
        .error-card { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
      <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-warehouse"></i> Inventory Management
        </a>
        <div class="navbar-nav ms-auto">
            <span class="navbar-text user-welcome me-3">
                <i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                <span class="badge bg-secondary"><?php echo ucfirst($_SESSION['role']); ?></span>
            </span>
            
            <!-- Add Manage Products to navbar -->
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
            <a href="products.php" class="btn btn-outline-light btn-sm me-2">
                <i class="fas fa-boxes"></i> Manage Products
            </a>
            <?php endif; ?>
            
            <a href="logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</nav>

    <div class="container mt-4">
        <?php
        // Check if database is connected
        include 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            echo '
            <div class="alert alert-danger">
                <h4><i class="fas fa-database"></i> Database Connection Error</h4>
                <p>Could not connect to database. Please check:</p>
                <ul>
                    <li>MySQL is running in XAMPP</li>
                    <li>Database "web_inventory" exists</li>
                    <li>Username and password in config/database.php are correct</li>
                </ul>
                <a href="http://localhost/phpmyadmin" class="btn btn-warning">Open phpMyAdmin</a>
            </div>';
        } else {
        ?>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5><i class="fas fa-boxes"></i> Total Products</h5>
                        <h2><?php echo getTotalProducts(); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5><i class="fas fa-exclamation-triangle"></i> Low Stock</h5>
                        <h2><?php echo getLowStockCount(); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5><i class="fas fa-times-circle"></i> Out of Stock</h5>
                        <h2><?php echo getOutOfStockCount(); ?></h2>
                    </div>
                </div>
            </div>
            <!-- Sync Status Card -->
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-secondary h-100">
                    <div class="card-body">
                        <h5><i class="fas fa-cloud-upload-alt"></i> Last Sync</h5>
                        <h6><?php echo getLastSyncTime(); ?></h6>
                        <small><?php echo getLastSyncStatus(); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-list"></i> Product Inventory</h4>
            </div>
            <div class="card-body">
                <?php
                $products = getProducts();
                if (empty($products)) {
                    echo '<div class="alert alert-info">No products found. Sync data from your POS system.</div>';
                } else {
                ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Barcode</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Last Updated</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php displayProducts($products); ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </div>
        
        <?php } // End database connection check ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// PHP functions for the dashboard

function getTotalProducts() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) return "Error";
    
    try {
        $query = "SELECT COUNT(*) as total FROM products";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    } catch (Exception $e) {
        return "Error";
    }
}

function getLowStockCount() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) return "Error";
    
    try {
        $query = "SELECT COUNT(*) as total FROM products WHERE stock_quantity BETWEEN 1 AND 5";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    } catch (Exception $e) {
        return "Error";
    }
}

function getOutOfStockCount() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) return "Error";
    
    try {
        $query = "SELECT COUNT(*) as total FROM products WHERE stock_quantity = 0";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    } catch (Exception $e) {
        return "Error";
    }
}

function getLastSyncTime() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) return "Never";
    
    try {
        $query = "SELECT sync_time FROM sync_logs WHERE status = 'SUCCESS' ORDER BY sync_time DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['sync_time'] : 'Never';
    } catch (Exception $e) {
        return 'Never';
    }
}

function getProducts() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) return [];
    
    try {
        $query = "SELECT * FROM products ORDER BY stock_quantity ASC, name ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function displayProducts($products) {
    foreach ($products as $row) {
        $stockClass = '';
        $statusBadge = '';
        
        if ($row['stock_quantity'] == 0) {
            $stockClass = 'out-of-stock';
            $statusBadge = '<span class="badge bg-danger">Out of Stock</span>';
        } elseif ($row['stock_quantity'] <= 2) {
            $stockClass = 'critical-stock';
            $statusBadge = '<span class="badge bg-warning">Critical</span>';
        } elseif ($row['stock_quantity'] <= 5) {
            $stockClass = 'low-stock';
            $statusBadge = '<span class="badge bg-info">Low</span>';
        } else {
            $statusBadge = '<span class="badge bg-success">Good</span>';
        }
        
        echo "<tr class='$stockClass'>";
        echo "<td><strong>{$row['name']}</strong></td>";
        echo "<td>{$row['barcode']}</td>";
        echo "<td>{$row['category']}</td>";
        echo "<td>R " . number_format($row['price'], 2) . "</td>";
        echo "<td><span class='badge bg-" . getStockBadgeColor($row['stock_quantity']) . "'>" . $row['stock_quantity'] . "</span></td>";
        echo "<td>" . $row['last_sync'] . "</td>";
        echo "<td>$statusBadge</td>";
        echo "</tr>";
    }
}

function getStockBadgeColor($stock) {
    if ($stock == 0) return 'danger';
    if ($stock <= 2) return 'warning';
    if ($stock <= 5) return 'info';
    return 'success';
}

function getLastSyncStatus() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) return "Unknown";
    
    try {
        $query = "SELECT status, records_processed FROM sync_logs ORDER BY sync_time DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $status = $row['status'];
            $records = $row['records_processed'];
            $badge = $status == 'SUCCESS' ? 'success' : 'danger';
            return "<span class='badge bg-$badge'>$status</span> ($records records)";
        }
        return "Never synced";
    } catch (Exception $e) {
        return "Error";
    }
}
?>