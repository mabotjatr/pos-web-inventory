<?php
require_once 'auth_check.php';

// Only allow admin and manager roles
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
    header("Location: index.php");
    exit;
}

require_once 'config/database.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_product') {
        $barcode = trim($_POST['barcode']);
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : 1;
        $stock_quantity = intval($_POST['stock_quantity']);
        $image_path = $_POST['image_path'] ?? '';
        
        if (empty($barcode) || empty($name) || $price <= 0 || $category_id <= 0) {
            $error = "Please fill in all required fields (Barcode, Name, Price).";
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            try {
                // Check if barcode already exists
                $checkQuery = "SELECT id FROM products WHERE barcode = :barcode";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':barcode', $barcode);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $error = "A product with this barcode already exists.";
                } else {
                    // Get the category name for the selected category_id
                    $category_name = 'Uncategorized';
                    if ($category_id > 0) {
                        $catQuery = "SELECT name FROM categories WHERE id = :category_id";
                        $catStmt = $db->prepare($catQuery);
                        $catStmt->bindParam(':category_id', $category_id);
                        $catStmt->execute();
                        $categoryData = $catStmt->fetch(PDO::FETCH_ASSOC);
                        $category_name = $categoryData['name'] ?? 'Uncategorized';
                    }

                    // Insert new product with both category_id and category name
                    $insertQuery = "INSERT INTO products 
                                (barcode, name, price, category, category_id, stock_quantity, image_path, local_id) 
                                VALUES (:barcode, :name, :price, :category, :category_id, :stock_quantity, :image_path, :local_id)";

                    $insertStmt = $db->prepare($insertQuery);
                    $insertStmt->bindParam(':barcode', $barcode);
                    $insertStmt->bindParam(':name', $name);
                    $insertStmt->bindParam(':price', $price);
                    $insertStmt->bindParam(':category', $category_name);
                    $insertStmt->bindParam(':category_id', $category_id);
                    $insertStmt->bindParam(':stock_quantity', $stock_quantity);
                    $insertStmt->bindParam(':image_path', $image_path);
                    
                    // Generate a local_id (you might want to sync this with your POS)
                    $local_id = time(); // Temporary ID
                    $insertStmt->bindParam(':local_id', $local_id);
                    
                    if ($insertStmt->execute()) {
                        $success = "Product '$name' added successfully! (Category: $category_name)";
                    } else {
                        $error = "Failed to add product. Please try again.";
                    }
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_stock') {
        $product_id = intval($_POST['product_id']);
        $new_stock = intval($_POST['new_stock']);
        
        if ($new_stock < 0) {
            $error = "Stock quantity cannot be negative.";
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            try {
                // Get current stock for history
                $currentQuery = "SELECT stock_quantity, name FROM products WHERE id = :id";
                $currentStmt = $db->prepare($currentQuery);
                $currentStmt->bindParam(':id', $product_id);
                $currentStmt->execute();
                $currentProduct = $currentStmt->fetch(PDO::FETCH_ASSOC);
                
                // Update stock
                $updateQuery = "UPDATE products SET stock_quantity = :stock WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':stock', $new_stock);
                $updateStmt->bindParam(':id', $product_id);
                
                if ($updateStmt->execute()) {
                    // Log stock change
                    $historyQuery = "INSERT INTO stock_history 
                                    (product_id, old_stock, new_stock, change_type, notes, changed_by) 
                                    VALUES (:product_id, :old_stock, :new_stock, 'ADJUSTMENT', :notes, :changed_by)";
                    $historyStmt = $db->prepare($historyQuery);
                    $historyStmt->bindParam(':product_id', $product_id);
                    $historyStmt->bindParam(':old_stock', $currentProduct['stock_quantity']);
                    $historyStmt->bindParam(':new_stock', $new_stock);
                    $notes = "Manual stock adjustment by " . $_SESSION['full_name'];
                    $historyStmt->bindParam(':notes', $notes);
                    $historyStmt->bindParam(':changed_by', $_SESSION['username']);
                    $historyStmt->execute();
                    
                    $success = "Stock updated successfully for '{$currentProduct['name']}'!";
                } else {
                    $error = "Failed to update stock.";
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get all products and categories for display
$database = new Database();
$db = $database->getConnection();

$products = [];
$categories = [];

if ($db) {
    try {
        // Get all products - now we can get category directly from products table
        $productsQuery = "SELECT p.*, c.name as category_name
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         ORDER BY p.name ASC";
        $productsStmt = $db->prepare($productsQuery);
        $productsStmt->execute();
        $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all categories
        $categoriesQuery = "SELECT * FROM categories ORDER BY name ASC";
        $categoriesStmt = $db->prepare($categoriesQuery);
        $categoriesStmt->execute();
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Error loading data: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .low-stock { background-color: #fff3cd; }
        .critical-stock { background-color: #f8d7da; }
        .out-of-stock { background-color: #f5c6cb; }
        .card { transition: transform 0.2s; }
        .card:hover { transform: translateY(-2px); }
        .nav-tabs .nav-link.active { font-weight: bold; }
        .stock-badge { font-size: 0.8em; }
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
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    <span class="badge bg-secondary"><?php echo ucfirst($_SESSION['role']); ?></span>
                </span>
                <a href="index.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-chart-bar"></i> Dashboard
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-boxes"></i> Product Management</h2>
                <p class="text-muted">Add new products and manage inventory stock levels</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabs for different management sections -->
        <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab">
                    <i class="fas fa-plus-circle"></i> Add New Product
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="manage-tab" data-bs-toggle="tab" data-bs-target="#manage" type="button" role="tab">
                    <i class="fas fa-edit"></i> Manage Stock
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="view-tab" data-bs-toggle="tab" data-bs-target="#view" type="button" role="tab">
                    <i class="fas fa-list"></i> View All Products
                </button>
            </li>
        </ul>

        <div class="tab-content" id="managementTabsContent">
            <!-- Add New Product Tab -->
            <div class="tab-pane fade show active" id="add" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Product</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="add_product">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="barcode" class="form-label">Barcode *</label>
                                            <input type="text" class="form-control" id="barcode" name="barcode" 
                                                   value="<?php echo $_POST['barcode'] ?? ''; ?>" required>
                                            <div class="form-text">Unique product barcode</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Product Name *</label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo $_POST['name'] ?? ''; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="price" class="form-label">Price (R) *</label>
                                            <input type="number" class="form-control" id="price" name="price" 
                                                   step="0.01" min="0.01" value="<?php echo $_POST['price'] ?? ''; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="category_id" class="form-label">Category</label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">-- Select Category --</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>" 
                                                        <?php echo (($_POST['category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Category is required</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="stock_quantity" class="form-label">Initial Stock</label>
                                            <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" 
                                                   min="0" value="<?php echo $_POST['stock_quantity'] ?? 0; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="image_path" class="form-label">Product Image</label>
                                            <select class="form-select" id="image_path" name="image_path">
                                                <option value="">-- No Image --</option>
                                                <option value="/images/steak.png" <?php echo (($_POST['image_path'] ?? '') == '/images/steak.png') ? 'selected' : ''; ?>>🍖 Steak & Pap</option>
                                                <option value="/images/chicken.png" <?php echo (($_POST['image_path'] ?? '') == '/images/chicken.png') ? 'selected' : ''; ?>>🍗 Chicken & Pap</option>
                                                <option value="/images/coke.png" <?php echo (($_POST['image_path'] ?? '') == '/images/coke.png') ? 'selected' : ''; ?>>🥤 Coke</option>
                                                <option value="/images/fanta.png" <?php echo (($_POST['image_path'] ?? '') == '/images/fanta.png') ? 'selected' : ''; ?>>🥤 Fanta Orange</option>
                                                <option value="/images/sprite.png" <?php echo (($_POST['image_path'] ?? '') == '/images/sprite.png') ? 'selected' : ''; ?>>🥤 Sprite</option>
                                                <option value="/images/stone.png" <?php echo (($_POST['image_path'] ?? '') == '/images/stone.png') ? 'selected' : ''; ?>>🥤 Stone</option>
                                                <option value="/images/heineken.jpg" <?php echo (($_POST['image_path'] ?? '') == '/images/heineken.jpg') ? 'selected' : ''; ?>>🍺 Heineken</option>
                                                <option value="/images/heineken.jpg" <?php echo (($_POST['image_path'] ?? '') == '/images/heineken.jpg') ? 'selected' : ''; ?>>🍺 Savanna</option>
                                                <option value="/images/heineken.jpg" <?php echo (($_POST['image_path'] ?? '') == '/images/heineken.jpg') ? 'selected' : ''; ?>>🍺 Black Label</option>
                                                <option value="/images/heineken.jpg" <?php echo (($_POST['image_path'] ?? '') == '/images/heineken.jpg') ? 'selected' : ''; ?>>🍺 Amostel Lager</option>
                                                <option value="/images/heineken.jpg" <?php echo (($_POST['image_path'] ?? '') == '/images/heineken.jpg') ? 'selected' : ''; ?>>🍺 Castle Lager</option>
                                                <option value="/images/heineken.jpg" <?php echo (($_POST['image_path'] ?? '') == '/images/heineken.jpg') ? 'selected' : ''; ?>>🍺 Castle Lite</option>
                                                <option value="/images/RG_menthol.png" <?php echo (($_POST['image_path'] ?? '') == '/images/RG_menthol.png') ? 'selected' : ''; ?>>🚬 RG Menthol</option>
                                                <option value="/images/camel-cigarettes.png" <?php echo (($_POST['image_path'] ?? '') == '/images/camel-cigarettes.png') ? 'selected' : ''; ?>>🚬 Camel</option>
                                                <option value="/images/default-product.png" <?php echo (($_POST['image_path'] ?? '') == '/images/default-product.png') ? 'selected' : ''; ?>>📦 Default Product</option>
                                                <option value="custom">-- Custom Path --</option>
                                            </select>
                                            <div class="form-text">Select product image (prevents errors)</div>
                                            
                                            <!-- Custom image path input (hidden by default) -->
                                            <div id="customImageContainer" class="mt-2" style="display: none;">
                                                <label for="custom_image_path" class="form-label">Custom Image Path</label>
                                                <input type="text" class="form-control" id="custom_image_path" name="custom_image_path" 
                                                    placeholder="/images/your-image.png">
                                                <div class="form-text">Enter custom image path</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save"></i> Add Product
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Quick Tips</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-check text-success"></i> Barcode must be unique</li>
                                    <li class="mb-2"><i class="fas fa-check text-success"></i> Price should include tax</li>
                                    <li class="mb-2"><i class="fas fa-check text-success"></i> Set initial stock to 0 if not available</li>
                                    <li class="mb-2"><i class="fas fa-check text-success"></i> Image path is relative to your POS system</li>
                                    <li><i class="fas fa-sync text-warning"></i> Products will sync with POS on next sync</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manage Stock Tab -->
            <div class="tab-pane fade" id="manage" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Manage Product Stock</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($products)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No products found. Add some products first.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Barcode</th>
                                            <th>Category</th>
                                            <th>Current Stock</th>
                                            <th>Price</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): 
                                            $stockClass = '';
                                            if ($product['stock_quantity'] == 0) {
                                                $stockClass = 'out-of-stock';
                                            } elseif ($product['stock_quantity'] <= 2) {
                                                $stockClass = 'critical-stock';
                                            } elseif ($product['stock_quantity'] <= 5) {
                                                $stockClass = 'low-stock';
                                            }
                                        ?>
                                        <tr class="<?php echo $stockClass; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['barcode']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $product['stock_quantity'] == 0 ? 'danger' : 
                                                         ($product['stock_quantity'] <= 2 ? 'warning' : 
                                                         ($product['stock_quantity'] <= 5 ? 'info' : 'success')); 
                                                ?> stock-badge">
                                                    <?php echo $product['stock_quantity']; ?>
                                                </span>
                                            </td>
                                            <td>R <?php echo number_format($product['price'], 2); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#stockModal"
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                        data-current-stock="<?php echo $product['stock_quantity']; ?>">
                                                    <i class="fas fa-edit"></i> Adjust Stock
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- View All Products Tab -->
            <div class="tab-pane fade" id="view" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> All Products (<?php echo count($products); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($products)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No products available.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Barcode</th>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): 
                                            $stockClass = '';
                                            if ($product['stock_quantity'] == 0) {
                                                $stockClass = 'out-of-stock';
                                            } elseif ($product['stock_quantity'] <= 2) {
                                                $stockClass = 'critical-stock';
                                            } elseif ($product['stock_quantity'] <= 5) {
                                                $stockClass = 'low-stock';
                                            }
                                        ?>
                                        <tr class="<?php echo $stockClass; ?>">
                                            <td><?php echo $product['id']; ?></td>
                                            <td><code><?php echo htmlspecialchars($product['barcode']); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($product['category'] ?? $product['category'] ?? 'Uncategorized'); ?></td>
                                            <td>R <?php echo number_format($product['price'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $product['stock_quantity'] == 0 ? 'danger' : 
                                                         ($product['stock_quantity'] <= 2 ? 'warning' : 
                                                         ($product['stock_quantity'] <= 5 ? 'info' : 'success')); 
                                                ?>">
                                                    <?php echo $product['stock_quantity']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $product['last_sync'] ?? 'Never'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="stockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="product_id" id="modalProductId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="modalProductName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="text" class="form-control" id="modalCurrentStock" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="new_stock" class="form-label">New Stock Quantity</label>
                            <input type="number" class="form-control" id="new_stock" name="new_stock" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize the stock modal
        var stockModal = document.getElementById('stockModal');
        stockModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var productId = button.getAttribute('data-product-id');
            var productName = button.getAttribute('data-product-name');
            var currentStock = button.getAttribute('data-current-stock');
            
            document.getElementById('modalProductId').value = productId;
            document.getElementById('modalProductName').value = productName;
            document.getElementById('modalCurrentStock').value = currentStock;
            document.getElementById('new_stock').value = currentStock;
        });
   
        // Handle custom image path selection
        document.getElementById('image_path').addEventListener('change', function() {
            const customContainer = document.getElementById('customImageContainer');
            const customInput = document.getElementById('custom_image_path');
            
            if (this.value === 'custom') {
                customContainer.style.display = 'block';
                customInput.required = true;
            } else {
                customContainer.style.display = 'none';
                customInput.required = false;
                // Set the custom input value to the selected option
                customInput.value = this.value;
            }
        });

        // Form submission handler to use custom value if provided
        document.querySelector('form').addEventListener('submit', function(e) {
            const imageSelect = document.getElementById('image_path');
            const customInput = document.getElementById('custom_image_path');
            
            if (imageSelect.value === 'custom' && customInput.value.trim() !== '') {
                // Create a hidden input to submit the custom value
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'image_path';
                hiddenInput.value = customInput.value.trim();
                this.appendChild(hiddenInput);
                
                // Disable the original select to avoid conflict
                imageSelect.disabled = true;
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const imageSelect = document.getElementById('image_path');
            const customContainer = document.getElementById('customImageContainer');
            const customInput = document.getElementById('custom_image_path');
            
            // If custom was previously selected, show the input
            if (imageSelect.value === 'custom') {
                customContainer.style.display = 'block';
            }
            
            // Set custom input value if it exists from previous submission
            <?php if (isset($_POST['custom_image_path']) && !empty($_POST['custom_image_path'])): ?>
                customInput.value = '<?php echo $_POST['custom_image_path']; ?>';
            <?php endif; ?>
        });
</script>
</body>
</html>