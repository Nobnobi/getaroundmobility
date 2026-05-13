<?php


namespace App\Controllers;
use App\Controller;
use App\Models\CustomerModel;

class CustomerController extends Controller
{
    public function index()
    {   
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }

        $role = strtolower($_SESSION['admin_role'] ?? '');
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            header('Location: /admin/orders');
            exit;
        }

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        $perPage = 10;
        $customerModel = new CustomerModel();
        $result = $customerModel->getCustomers($search, $page, $perPage);
        $customers = $result['customers'];
        $totalCustomers = $result['totalCustomers'];
        $totalPages = $result['totalPages'];
        if (method_exists($this, 'renderAdmin')) {
            $this->renderAdmin('admin/customers', [
                'customers' => $customers,
                'search' => $search,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
                'totalCustomers' => $totalCustomers
            ]);
        } else {
            require __DIR__ . '/../Views/admin/customers.php';
        }
    }

}