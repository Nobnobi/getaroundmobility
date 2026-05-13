<?php
// src/Controllers/LocationsController.php
namespace App\Controllers;

use App\Controller;
use App\Models\PartnerHotelModel;
use App\Models\PickupLocationModel;

class LocationsController extends Controller {
    private $partnerHotelModel;
    private $pickupLocationModel;

    private function ensureAdminSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }
    }

    private function ensureManagePermission(): void {
        $role = strtolower($_SESSION['admin_role'] ?? '');
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            header('Location: /admin/locations');
            exit;
        }
    }

    public function __construct() {
        $this->partnerHotelModel = new PartnerHotelModel();
        $this->pickupLocationModel = new PickupLocationModel();
    }
    public function index() {
        $this->ensureAdminSession();

        $partnerHotels = $this->partnerHotelModel->getAll();
        $pickupLocations = $this->pickupLocationModel->getAll();
        $this->renderAdmin('admin/locations', compact('partnerHotels', 'pickupLocations'));
    }
    public function handlePost() {
        $this->ensureAdminSession();
        $this->ensureManagePermission();

        $tab = $_POST['tab'] ?? '';
        if ($tab === 'hotels') {
            // Update existing hotels
            if (!empty($_POST['hotels']) && is_array($_POST['hotels'])) {
                foreach ($_POST['hotels'] as $id => $fields) {
                    if ($id === 'new') continue;
                    $this->partnerHotelModel->update($id, $fields);
                }
            }
            // Add new hotels
            if (!empty($_POST['hotels']['new']['name'])) {
                $names = $_POST['hotels']['new']['name'];
                $address1s = $_POST['hotels']['new']['address1'] ?? [];
                $address2s = $_POST['hotels']['new']['address2'] ?? [];
                $states = $_POST['hotels']['new']['state'] ?? [];
                $zips = $_POST['hotels']['new']['zip'] ?? [];
                for ($i = 0; $i < count($names); $i++) {
                    if (trim($names[$i]) !== '') {
                        $this->partnerHotelModel->add([
                            'name' => $names[$i],
                            'address1' => $address1s[$i] ?? '',
                            'address2' => $address2s[$i] ?? '',
                            'state' => $states[$i] ?? '',
                            'zip' => $zips[$i] ?? ''
                        ]);
                    }
                }
            }
            // Delete hotels
            if (!empty($_POST['deleted_ids'])) {
                $ids = explode(',', $_POST['deleted_ids']);
                foreach ($ids as $id) {
                    $id = trim($id);
                    if ($id !== '') {
                        $this->partnerHotelModel->delete($id);
                    }
                }
            }
        } elseif ($tab === 'pickups') {
            // Update existing pickups
            if (!empty($_POST['pickups']) && is_array($_POST['pickups'])) {
                foreach ($_POST['pickups'] as $id => $fields) {
                    if ($id === 'new') continue;
                    $this->pickupLocationModel->update($id, $fields);
                }
            }
            // Add new pickups
            if (!empty($_POST['pickups']['new']['name'])) {
                $names = $_POST['pickups']['new']['name'];
                $addresses = $_POST['pickups']['new']['address'] ?? [];
                for ($i = 0; $i < count($names); $i++) {
                    if (trim($names[$i]) !== '') {
                        $this->pickupLocationModel->add([
                            'name' => $names[$i],
                            'address' => $addresses[$i] ?? ''
                        ]);
                    }
                }
            }
            // Delete pickups
            if (!empty($_POST['deleted_ids'])) {
                $ids = explode(',', $_POST['deleted_ids']);
                foreach ($ids as $id) {
                    $id = trim($id);
                    if ($id !== '') {
                        $this->pickupLocationModel->delete($id);
                    }
                }
            }
        }
        $redirectTab = $tab === 'pickups' ? 'pickups' : 'hotels';
        header('Location: /admin/locations?tab=' . $redirectTab);
        exit;
    }
}
