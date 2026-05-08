<?php

namespace App\Controllers;
use App\Controller;
use App\Models\CategoryModel;

class CategoryController extends Controller {
    public function create() {
        $this->requireAdminOrSuperadmin();
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getAllCategories();
        if (method_exists($this, 'renderAdmin')) {
            $this->renderAdmin('admin/category-form', [ 'categories' => $categories ]);
        } else {
            require __DIR__ . '/../Views/admin/category-form.php';
        }
    }


    public function store() {
        $this->requireAdminOrSuperadmin();
        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
        $categoryModel = new CategoryModel();
        $name = htmlspecialchars(trim($_POST['category_name'] ?? ''));
        $categoryModel->addCategory($name);
        $success = true;
        $successType = 'add';
        $categories = $categoryModel->getAllCategories();
        if (method_exists($this, 'renderAdmin')) {
            $this->renderAdmin('admin/category-form', [
                'categories' => $categories,
                'success' => $success,
                'successType' => $successType
            ]);
        } else {
            require __DIR__ . '/../Views/admin/category-form.php';
        }
    }

    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getAllCategories();
        if (method_exists($this, 'renderAdmin')) {
            $this->renderAdmin('admin/categories', [ 'categories' => $categories ]);
        } else {
            require __DIR__ . '/../Views/admin/categories.php';
        }
    }

    public function delete() {
        $this->requireAdminOrSuperadmin();
        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
        $categoryModel = new CategoryModel();
        $id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $deletedName = '';
        if ($id) {
            $deletedName = $categoryModel->getCategoryName($id);
            $categoryModel->deleteCategory($id);
        }
        $success = $deletedName ? $deletedName : false;
        $successType = 'delete';
        $categories = $categoryModel->getAllCategories();
        if (method_exists($this, 'renderAdmin')) {
            $this->renderAdmin('admin/category-form', [
                'categories' => $categories,
                'success' => $success,
                'successType' => $successType
            ]);
        } else {
            require __DIR__ . '/../Views/admin/category-form.php';
        }
    }

    public function save() {
        $this->requireAdminOrSuperadmin();
        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
        $categoryModel = new CategoryModel();
        // Handle deletions
        if (!empty($_POST['deleted_ids'])) {
            $ids = explode(',', $_POST['deleted_ids']);
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id) {
                    $categoryModel->deleteCategory($id);
                }
            }
        }
        // Update existing categories
        if (!empty($_POST['category_name'])) {
            foreach ($_POST['category_name'] as $id => $name) {
                if ($id !== 'new') {
                    $id = intval($id);
                    $name = htmlspecialchars(trim($name));
                    $categoryModel->updateCategory($id, $name);
                }
            }
        }
        // Add new categories
        if (!empty($_POST['category_name']['new'])) {
            foreach ($_POST['category_name']['new'] as $newName) {
                $newName = htmlspecialchars(trim($newName));
                if (!empty($newName)) {
                    $categoryModel->addCategory($newName);
                }
            }
        }
        $this->index();
    }

    private function requireAdminOrSuperadmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $role = strtolower($_SESSION['admin_role'] ?? '');
        if ($role !== 'admin' && $role !== 'superadmin') {
            header('Location: /admin/categories');
            exit;
        }
    }

}