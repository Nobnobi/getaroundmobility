<?php
namespace App\Models;

use App\Utils\Database;


use PDO;

class ProductModel{
        // Update a general product (no sale_type change)
        public function updateProduct($product_id, $data){
            $stmt = $this->db->prepare("UPDATE products SET product_name=?, product_category_id=?, price=?, description=?, image_url=? WHERE product_id=?");
            $stmt->execute([
                $data['product_name'],
                $data['product_category_id'],
                $data['price'],
                $data['description'],
                $data['image_url'],
                $product_id
            ]);
        }
    
    private $db;

    public function __construct()
    {
        // Inject PDO instance into model
        $this->db = Database::getInstance();
    }

    /**
     * Get featured products, optionally filtered by scooter availability for a given pickup/return datetime
     * @param string|null $pickupDatetime (Y-m-d H:i:s)
     * @param string|null $returnDatetime (Y-m-d H:i:s)
     * @return array
     */
    public function getFeaturedProducts($pickupDatetime = null, $returnDatetime = null)
    {
        $sql = "
            SELECT 
                p.*, 
                c.category_name,
                (
                    SELECT COUNT(*) 
                    FROM scooters s 
                    WHERE s.product_id = p.product_id
                ) AS scooter_count,
                v.variation_id AS featured_variation_id,
                v.variation_name AS featured_variation_name,
                v.price AS featured_variation_price
            FROM products p
            JOIN categories c ON p.product_category_id = c.category_id
            LEFT JOIN product_variations v ON v.variation_id = p.featured_variation_id
            WHERE p.featured_slot IS NOT NULL
            ORDER BY p.featured_slot ASC
        ";
        $stmt = $this->db->query($sql);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach active variations to each product and check base scooter stock
        foreach ($products as &$product) {
            $product['variations'] = [];
            $totalStock = 0;
            // If a featured_variation_id is set, only check that variation
            if (!empty($product['featured_variation_id'])) {
                $varStmt = $this->db->prepare("SELECT variation_id, variation_name, price FROM product_variations WHERE variation_id = ? AND is_active = 1 LIMIT 1");
                $varStmt->execute([$product['featured_variation_id']]);
                $variation = $varStmt->fetch(PDO::FETCH_ASSOC);
                if ($variation) {
                    // Check stock for this variation only
                    if ($pickupDatetime && $returnDatetime) {
                        $stockStmt = $this->db->prepare("
                            SELECT COUNT(*) FROM scooters s
                            WHERE s.product_id = ? AND s.variation_id = ? AND s.status = 'available'
                            AND NOT EXISTS (
                                SELECT 1 FROM reservations r
                                WHERE r.scooter_id = s.scooter_id
                                AND r.status IN ('pending', 'confirmed', 'paid')
                                AND r.pickup_datetime < ?
                                AND r.return_datetime > ?
                            )
                        ");
                        $stockStmt->execute([
                            $product['product_id'],
                            $variation['variation_id'],
                            $returnDatetime,
                            $pickupDatetime
                        ]);
                    } else {
                        $stockStmt = $this->db->prepare("SELECT COUNT(*) FROM scooters WHERE product_id = ? AND variation_id = ? AND status = 'available'");
                        $stockStmt->execute([$product['product_id'], $variation['variation_id']]);
                    }
                    $variation['stock'] = (int)$stockStmt->fetchColumn();
                    $totalStock += $variation['stock'];
                    if ($variation['stock'] > 0) {
                        $product['variations'][] = $variation;
                        // Set featured variation details for display
                        $product['featured_variation_id'] = $variation['variation_id'];
                        $product['featured_variation_name'] = $variation['variation_name'];
                        $product['featured_variation_price'] = $variation['price'];
                    } else {
                        // If the selected variation is not available, clear featured details
                        $product['featured_variation_id'] = null;
                        $product['featured_variation_name'] = null;
                        $product['featured_variation_price'] = null;
                    }
                } else {
                    // Variation not found or inactive
                    $product['featured_variation_id'] = null;
                    $product['featured_variation_name'] = null;
                    $product['featured_variation_price'] = null;
                }
                // For admin-selected variation, do NOT check base product stock
                $product['base_stock'] = 0;
                $product['total_stock'] = $totalStock;
            } else {
                // No featured variation: fallback to all available variations and base scooters
                $varStmt = $this->db->prepare("SELECT variation_id, variation_name, price FROM product_variations WHERE product_id = ? AND is_active = 1 ORDER BY price ASC, variation_id ASC");
                $varStmt->execute([$product['product_id']]);
                $variations = $varStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($variations as &$variation) {
                    if ($pickupDatetime && $returnDatetime) {
                        $stockStmt = $this->db->prepare("
                            SELECT COUNT(*) FROM scooters s
                            WHERE s.product_id = ? AND s.variation_id = ? AND s.status = 'available'
                            AND NOT EXISTS (
                                SELECT 1 FROM reservations r
                                WHERE r.scooter_id = s.scooter_id
                                AND r.status IN ('pending', 'confirmed', 'paid')
                                AND r.pickup_datetime < ?
                                AND r.return_datetime > ?
                            )
                        ");
                        $stockStmt->execute([
                            $product['product_id'],
                            $variation['variation_id'],
                            $returnDatetime,
                            $pickupDatetime
                        ]);
                    } else {
                        $stockStmt = $this->db->prepare("SELECT COUNT(*) FROM scooters WHERE product_id = ? AND variation_id = ? AND status = 'available'");
                        $stockStmt->execute([$product['product_id'], $variation['variation_id']]);
                    }
                    $variation['stock'] = (int)$stockStmt->fetchColumn();
                    if ($variation['stock'] > 0) {
                        $product['variations'][] = $variation;
                        $totalStock += $variation['stock'];
                    }
                }
                unset($variation);
                // Check base product stock (scooters not tied to a variation)
                if ($pickupDatetime && $returnDatetime) {
                    $baseStockStmt = $this->db->prepare("
                        SELECT COUNT(*) FROM scooters s
                        WHERE s.product_id = ? AND s.variation_id IS NULL AND s.status = 'available'
                        AND NOT EXISTS (
                            SELECT 1 FROM reservations r
                            WHERE r.scooter_id = s.scooter_id
                            AND r.status IN ('pending', 'confirmed', 'paid')
                            AND r.pickup_datetime < ?
                            AND r.return_datetime > ?
                        )
                    ");
                    $baseStockStmt->execute([
                        $product['product_id'],
                        $returnDatetime,
                        $pickupDatetime
                    ]);
                } else {
                    $baseStockStmt = $this->db->prepare("SELECT COUNT(*) FROM scooters WHERE product_id = ? AND variation_id IS NULL AND status = 'available'");
                    $baseStockStmt->execute([$product['product_id']]);
                }
                $baseStock = (int)$baseStockStmt->fetchColumn();
                $totalStock += $baseStock;
                $product['base_stock'] = $baseStock;
                $product['total_stock'] = $totalStock;
                // Fallback: If no featured_variation_id, get the lowest active variation
                if (empty($product['featured_variation_id']) && !empty($product['variations'])) {
                    $firstVar = $product['variations'][0];
                    $product['featured_variation_id'] = $firstVar['variation_id'];
                    $product['featured_variation_name'] = $firstVar['variation_name'];
                    $product['featured_variation_price'] = $firstVar['price'];
                }
            }
        }
        unset($product);

        // If pickup/return datetime provided, filter for available scooters
        if ($pickupDatetime && $returnDatetime) {
            $filtered = [];
            foreach ($products as $product) {
                // If admin selected a variation, only include if that variation is available
                if (!empty($product['featured_variation_id'])) {
                    if (!empty($product['variations'])) {
                        $filtered[] = $product;
                    }
                } else {
                    // No featured variation: include if any variation or base scooter is available
                    if (!empty($product['variations']) || (!empty($product['base_stock']) && $product['base_stock'] > 0)) {
                        $filtered[] = $product;
                    }
                }
            }
            return $filtered;
        }
        return $products;
    }


    public function getProductList($filters = [])
    {
        // Fetch all categories
        $catStmt = $this->db->query("SELECT category_name FROM categories ORDER BY category_name ASC");
        $categories = $catStmt->fetchAll(\PDO::FETCH_COLUMN);

        // Get filter and pagination from GET
        $selectedCategory = $filters['category'] ?? null;
        $priceOrder = $filters['price_order'] ?? '';
        $orderBy = "p.product_id DESC"; // Default

        if ($priceOrder === '1') {
            $orderBy = "p.price DESC";
        } elseif ($priceOrder === '2') {
            $orderBy = "p.price ASC";
        }
        $page = $filters['page'] ?? 1;
        $perPage = 9;
        $offset = ($page - 1) * $perPage;

        // Build product query
        $params = [];
        $where = "WHERE 1=1";
        if ($selectedCategory && $selectedCategory !== 'all') {
            $where .= " AND c.category_name = ?";
            $params[] = $selectedCategory;
        }
        // Only filter for available scooters if available_only is true
        // Show all products, regardless of scooter stock or availability
        // If available_only is false, do NOT add any EXISTS clause for scooters. Show all products regardless of scooter status.
        // Show all products, even those without variations

        // Get total count for pagination
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM products p
             JOIN categories c ON p.product_category_id = c.category_id
             $where"
        );
        $countStmt->execute($params);
        $totalProducts = $countStmt->fetchColumn();
        $totalPages = ceil($totalProducts / $perPage);

        // Fetch products for current page
        $stmt = $this->db->prepare(
            "SELECT 
                p.product_id, p.product_name, p.price, p.description, p.image_url, 
                c.category_name
             FROM products p
             JOIN categories c ON p.product_category_id = c.category_id
             $where
             ORDER BY $orderBy
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);
        $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // For each product, fetch all active variations and calculate available stock from scooters table
        foreach ($products as &$product) {
            // Get all active variations
            $varStmt = $this->db->prepare("SELECT variation_id, variation_name, price FROM product_variations WHERE product_id = ? AND is_active = 1 ORDER BY price ASC, variation_id ASC");
            $varStmt->execute([$product['product_id']]);
            $variations = $varStmt->fetchAll(\PDO::FETCH_ASSOC);
            $totalStock = 0;
            foreach ($variations as &$variation) {
                // Count available scooters for this variation
                $stockStmt = $this->db->prepare("SELECT COUNT(*) FROM scooters WHERE product_id = ? AND variation_id = ? AND status = 'available'");
                $stockStmt->execute([$product['product_id'], $variation['variation_id']]);
                $variation['stock'] = (int)$stockStmt->fetchColumn();
                $totalStock += $variation['stock'];
            }
            unset($variation);
            $product['variations'] = $variations;
            $product['total_stock'] = $totalStock;
        }
        unset($product);

        return [
            'products' => $products,
            'categories' => $categories,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'selected_category' => $selectedCategory,
            'price_order' => $priceOrder
        ];
    }

    public function getSearch($filters){
        $pickup = $filters['pickup'] ?? '';
        $return = $filters['return'] ?? '';

        // Normalize to 'Y-m-d H:i:s'
        if ($pickup) $pickup = date('Y-m-d H:i:s', strtotime($pickup));
        if ($return) $return = date('Y-m-d H:i:s', strtotime($return));

        // Fetch all categories for the filter dropdown
        $catStmt = $this->db->query("SELECT category_id AS id, category_name FROM categories ORDER BY category_name ASC");
        $categories = $catStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get filter and pagination from $filters array
        $query = isset($filters['query']) ? trim($filters['query']) : '';
        $selectedCategory = isset($filters['category']) ? $filters['category'] : '';
        $priceOrder = isset($filters['price_order']) ? $filters['price_order'] : '';
        $weight = isset($filters['weight']) ? $filters['weight'] : '';
        $availableOnly = isset($filters['available_only']) ? $filters['available_only'] : true;
        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $perPage = 9;
        $offset = ($page - 1) * $perPage;

        // Build WHERE clause
        $where = "1=1";
        $params = [];

        if ($query !== '') {
            $where .= " AND (p.product_name LIKE :q OR p.description LIKE :q OR c.category_name LIKE :q)";
            $params[':q'] = "%$query%";
        }
        if ($selectedCategory !== '') {
            $where .= " AND c.category_id = :cat";
            $params[':cat'] = $selectedCategory;
        }
        if ($weight !== '') {
            $where .= " AND p.price <= :weight";
            $params[':weight'] = $weight;
        }

        // Only filter for available scooters if checkbox is checked
        if ($availableOnly && $pickup && $return) {
            $where .= " AND EXISTS (
                SELECT 1 FROM scooters s
                WHERE s.product_id = p.product_id
                AND s.status = 'available'
                AND NOT EXISTS (
                    SELECT 1 FROM reservations r
                    WHERE r.scooter_id = s.scooter_id
                    AND r.status IN ('pending', 'confirmed', 'paid')
                    AND r.pickup_datetime < :return
                    AND r.return_datetime > :pickup
                )
            )";
            $params[':return'] = $return;
            $params[':pickup'] = $pickup;
        } else if ($availableOnly) {
            $where .= " AND EXISTS (SELECT 1 FROM scooters s WHERE s.product_id = p.product_id AND s.status = 'available')";
        }
        // Exclude products for sale
        $where .= " AND (p.sale_type IS NULL OR p.sale_type = 'rental')";
        // Only show products with at least one active variation
        // $where .= " AND EXISTS (SELECT 1 FROM product_variations v WHERE v.product_id = p.product_id AND v.is_active = 1)";

        // Order by
        $orderBy = "p.product_id DESC";
        if ($priceOrder === '1') {
            $orderBy = "p.price DESC";
        } elseif ($priceOrder === '2') {
            $orderBy = "p.price ASC";
        }

        // Get total count for pagination
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM products p
            JOIN categories c ON p.product_category_id = c.category_id
            WHERE $where"
        );
        $countStmt->execute($params);
        $total_products = $countStmt->fetchColumn();
        $total_pages = ceil($total_products / $perPage);
        $current_page = $page;

        // Fetch products for current page
        $params_for_select = $params;

        $productStmt = $this->db->prepare(
            "SELECT 
                p.product_id, p.product_name, p.price, p.description, p.image_url, 
                c.category_name,
                (SELECT SUM(v.stock) FROM product_variations v WHERE v.product_id = p.product_id AND v.is_active = 1) AS total_stock
            FROM products p
            JOIN categories c ON p.product_category_id = c.category_id
            WHERE $where
            ORDER BY $orderBy
            LIMIT $perPage OFFSET $offset"
        );
        $productStmt->execute($params_for_select);
        $products = $productStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Attach active variations to each product (for search results page)
        foreach ($products as &$product) {
            $varStmt = $this->db->prepare("SELECT variation_id, variation_name, price FROM product_variations WHERE product_id = ? AND is_active = 1 ORDER BY price ASC, variation_id ASC");
            $varStmt->execute([$product['product_id']]);
            $variations = $varStmt->fetchAll(\PDO::FETCH_ASSOC);
            $totalStock = 0;
            if (!empty($variations)) {
                foreach ($variations as &$variation) {
                    // Calculate available stock for this variation, considering reservations if pickup/return are set
                    if (!empty($pickup) && !empty($return)) {
                        $stockStmt = $this->db->prepare("
                            SELECT COUNT(*) FROM scooters s
                            WHERE s.product_id = ? AND s.variation_id = ? AND s.status = 'available'
                            AND NOT EXISTS (
                                SELECT 1 FROM reservations r
                                WHERE r.scooter_id = s.scooter_id
                                AND r.status IN ('pending', 'confirmed', 'paid')
                                AND r.pickup_datetime < ?
                                AND r.return_datetime > ?
                            )
                        ");
                        $stockStmt->execute([
                            $product['product_id'],
                            $variation['variation_id'],
                            $return,
                            $pickup
                        ]);
                    } else {
                        $stockStmt = $this->db->prepare("SELECT COUNT(*) FROM scooters WHERE product_id = ? AND variation_id = ? AND status = 'available'");
                        $stockStmt->execute([$product['product_id'], $variation['variation_id']]);
                    }
                    $variation['stock'] = (int)$stockStmt->fetchColumn();
                    $totalStock += $variation['stock'];
                }
                unset($variation);
            } else {
                // No variations: count available scooters for the product
                if (!empty($pickup) && !empty($return)) {
                    $stockStmt = $this->db->prepare("
                        SELECT COUNT(*) FROM scooters s
                        WHERE s.product_id = ? AND s.status = 'available'
                        AND NOT EXISTS (
                            SELECT 1 FROM reservations r
                            WHERE r.scooter_id = s.scooter_id
                            AND r.status IN ('pending', 'confirmed', 'paid')
                            AND r.pickup_datetime < ?
                            AND r.return_datetime > ?
                        )
                    ");
                    $stockStmt->execute([
                        $product['product_id'],
                        $return,
                        $pickup
                    ]);
                } else {
                    $stockStmt = $this->db->prepare("SELECT COUNT(*) FROM scooters WHERE product_id = ? AND status = 'available'");
                    $stockStmt->execute([$product['product_id']]);
                }
                $totalStock = (int)$stockStmt->fetchColumn();
            }
            $product['variations'] = $variations;
            $product['total_stock'] = $totalStock;
        }
        unset($product);

        return [
            'categories' => $categories,
            'products' => $products,
            'total_pages' => $total_pages,
            'current_page' => $current_page,
            'selected_category' => $selectedCategory,
            'price_order' => $priceOrder,
            'total_products' => $total_products,
            // Add other filter values as needed
        ];
    }

    public function getProductsForSale()
    {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                (SELECT COUNT(*) FROM scooters s WHERE s.product_id = p.product_id AND s.status = 'available') AS available_scooter_count
            FROM products p
            WHERE p.sale_type = 'sale'
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCategories(){
        $stmt = $this->db->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addProductForSale($data){
        // Check if 'is_available' exists in the products table by describing the table once (static cache)
        static $hasIsAvailable = null;
        if ($hasIsAvailable === null) {
            $cols = $this->db->query("DESCRIBE products")->fetchAll(PDO::FETCH_COLUMN);
            $hasIsAvailable = in_array('is_available', $cols);
        }
        if ($hasIsAvailable) {
            $stmt = $this->db->prepare("INSERT INTO products (product_name, product_category_id, price, description, image_url, is_available, sale_type) VALUES (?, ?, ?, ?, ?, ?, 'sale')");
            $stmt->execute([
                $data['product_name'],
                $data['product_category_id'],
                $data['price'],
                $data['description'],
                $data['image_url'],
                $data['is_available'] ?? 1
            ]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO products (product_name, product_category_id, price, description, image_url, sale_type) VALUES (?, ?, ?, ?, ?, 'sale')");
            $stmt->execute([
                $data['product_name'],
                $data['product_category_id'],
                $data['price'],
                $data['description'],
                $data['image_url']
            ]);
        }
    }

    public function updateProductForSale($product_id, $data){
        $stmt = $this->db->prepare("UPDATE products SET product_name=?, product_category_id=?, price=?, description=?, image_url=?, sale_type='sale' WHERE product_id=?");
        $stmt->execute([
            $data['product_name'],
            $data['product_category_id'],
            $data['price'],
            $data['description'],
            $data['image_url'],
            $product_id
        ]);
    }


    public function deleteProduct($product_id){
        $stmt = $this->db->prepare("DELETE FROM products WHERE product_id=?");
        $stmt->execute([$product_id]);
    }

     /**
     * Get all active variations for a product
     */
    public function getVariationsByProductId($product_id)
    {
        $sql = "SELECT variation_id, variation_name, price, stock, is_active
                FROM product_variations
                WHERE product_id = ? AND is_active = 1
                ORDER BY variation_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch all products with basic info for admin new order
    public function getAllProductsBasic()
    {
        $stmt = $this->db->query("
            SELECT 
                p.product_id, 
                p.product_name, 
                p.image_url, 
                p.price,
                CASE
                    WHEN p.sale_type IS NULL OR p.sale_type = '' THEN 'rental'
                    ELSE p.sale_type
                END AS sale_type,
                (SELECT COUNT(*) FROM scooters s WHERE s.product_id = p.product_id AND s.status = 'available') AS scooter_count
            FROM products p
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAvailabilityCountsForWindow($pickupDatetime = null, $returnDatetime = null)
    {
        if ($pickupDatetime && $returnDatetime) {
            $pickup = date('Y-m-d H:i:00', strtotime($pickupDatetime));
            $return = date('Y-m-d H:i:00', strtotime($returnDatetime));

            $stmt = $this->db->prepare("
                SELECT
                    p.product_id,
                    COUNT(s.scooter_id) AS scooter_count
                FROM products p
                LEFT JOIN scooters s
                    ON s.product_id = p.product_id
                    AND s.status = 'available'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM reservations r
                        WHERE r.scooter_id = s.scooter_id
                          AND r.status IN ('pending', 'confirmed', 'paid')
                          AND r.pickup_datetime < ?
                          AND r.return_datetime > ?
                    )
                GROUP BY p.product_id
            ");
            $stmt->execute([$return, $pickup]);
        } else {
            $stmt = $this->db->query("
                SELECT
                    p.product_id,
                    COUNT(s.scooter_id) AS scooter_count
                FROM products p
                LEFT JOIN scooters s
                    ON s.product_id = p.product_id
                    AND s.status = 'available'
                GROUP BY p.product_id
            ");
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $availability = [];
        foreach ($rows as $row) {
            $availability[(int)$row['product_id']] = (int)$row['scooter_count'];
        }

        return $availability;
    }

    // Get rental prices for a list of product IDs (for homepage featured products)
    public function getRentalPricesForProducts($productIds) {
        if (empty($productIds)) return [];
        $in = str_repeat('?,', count($productIds) - 1) . '?';
        $sql = "SELECT product_id, variation_id, days, price FROM rental_prices WHERE product_id IN ($in)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($productIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $pid = $row['product_id'];
            // If variation_id is null (base product), use string 'null' for JS compatibility
            $vid = is_null($row['variation_id']) ? 'null' : $row['variation_id'];
            $days = $row['days'];
            $price = $row['price'];
            if (!isset($result[$pid])) $result[$pid] = [];
            if (!isset($result[$pid][$vid])) $result[$pid][$vid] = [];
            $result[$pid][$vid][$days] = $price;
        }
        return $result;
    }

    /**
     * Get all rental products with scooter count for admin
     */
    public function getAllProducts() {
        $stmt = $this->db->query("SELECT p.*, (SELECT COUNT(*) FROM scooters s WHERE s.product_id = p.product_id) AS scooter_count FROM products p WHERE p.sale_type = 'rental' ORDER BY p.product_id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add a general product (no is_available, no sale_type)
    public function addProduct($data) {
        $stmt = $this->db->prepare("INSERT INTO products (product_name, product_category_id, price, description, image_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['product_name'],
            $data['product_category_id'],
            $data['price'],
            $data['description'],
            $data['image_url']
        ]);
    }

    // Get all variations grouped by product_id
    public function getAllVariationsGrouped() {
        $stmt = $this->db->query("SELECT * FROM product_variations WHERE is_active = 1");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];
        foreach ($rows as $row) {
            $pid = $row['product_id'];
            if (!isset($grouped[$pid])) $grouped[$pid] = [];
            $grouped[$pid][] = $row;
        }
        return $grouped;
    }

    /**
     * Get all products with their active variations and stock
     */
    public function getAllProductsWithVariations() {
        $products = $this->getAllProductsBasic();
        $variationsGrouped = $this->getAllVariationsGrouped();
        foreach ($products as &$product) {
            $pid = $product['product_id'];
            $product['variations'] = [];
            if (isset($variationsGrouped[$pid])) {
                foreach ($variationsGrouped[$pid] as $var) {
                    // Get stock for this variation
                    $stockStmt = $this->db->prepare("SELECT COUNT(*) FROM scooters WHERE product_id = ? AND variation_id = ? AND status = 'available'");
                    $stockStmt->execute([$pid, $var['variation_id']]);
                    $var['stock'] = (int)$stockStmt->fetchColumn();
                    $product['variations'][] = $var;
                }
            }
            // For products without variations, get total stock
            $stockStmt = $this->db->prepare("SELECT COUNT(*) FROM scooters WHERE product_id = ? AND status = 'available'");
            $stockStmt->execute([$pid]);
            $product['total_stock'] = (int)$stockStmt->fetchColumn();
        }
        unset($product);
        return $products;
    }

    public function getDb() {
        return $this->db;
    }
    
}