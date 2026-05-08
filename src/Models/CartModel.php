<?php
namespace App\Models;
use App\Utils\Database;
use PDO;

class CartModel
{   
    private $db;
    public function __construct()
    {
        // Inject PDO instance into model
        $this->db = Database::getInstance();
    }

    public function getCart()
    {
        return $_SESSION['cart'] ?? [];
    }

    public function addToCart($item)
    {
        $_SESSION['cart'][] = $item;
    }

    public function removeFromCart($productId)
    {
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($productId) {
            return $item['id'] != $productId;
        });
    }

    public function clearCart()
    {
        $_SESSION['cart'] = [];
    }

    public function getTotal()
    {
        $cart = $this->getCart();
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['qty'];
        }
        return $total;
    }
}