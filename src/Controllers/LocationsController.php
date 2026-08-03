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

    private function normalizeHotelAuditPayload(array $fields): array {
        return [
            'name' => trim((string)($fields['name'] ?? '')),
            'address1' => trim((string)($fields['address1'] ?? '')),
            'address2' => trim((string)($fields['address2'] ?? '')),
            'state' => trim((string)($fields['state'] ?? '')),
            'zip' => trim((string)($fields['zip'] ?? '')),
            'delivery_fee' => is_numeric($fields['delivery_fee'] ?? null)
                ? round(max(0, (float)$fields['delivery_fee']), 2)
                : 0.0,
        ];
    }

    private function normalizePickupAuditPayload(array $fields): array {
        return [
            'name' => trim((string)($fields['name'] ?? '')),
            'address' => trim((string)($fields['address'] ?? '')),
        ];
    }

    public function index() {
        $this->ensureAdminSession();

        $hotelSearch = trim((string)($_GET['hotel_search'] ?? ''));
        $hotelPage = max(1, (int)($_GET['hotel_page'] ?? 1));
        $hotelPerPage = 20;
        $hotelTotalCount = $this->partnerHotelModel->countFiltered($hotelSearch);
        $hotelTotalPages = max(1, (int)ceil($hotelTotalCount / $hotelPerPage));
        if ($hotelPage > $hotelTotalPages) {
            $hotelPage = $hotelTotalPages;
        }

        $partnerHotels = $this->partnerHotelModel->getPaginated($hotelSearch, $hotelPage, $hotelPerPage);
        $pickupLocations = $this->pickupLocationModel->getAll();
        $this->renderAdmin('admin/locations', compact(
            'partnerHotels',
            'pickupLocations',
            'hotelSearch',
            'hotelPage',
            'hotelPerPage',
            'hotelTotalCount',
            'hotelTotalPages'
        ));
    }
    public function handlePost() {
        $this->ensureAdminSession();
        $this->ensureManagePermission();

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $tab = $_POST['tab'] ?? '';
        $hotelSearchContext = trim((string)($_POST['hotel_search_context'] ?? ''));
        $hotelPageContext = max(1, (int)($_POST['hotel_page_context'] ?? 1));
        if ($tab === 'hotels') {
            // Update existing hotels
            if (!empty($_POST['hotels']) && is_array($_POST['hotels'])) {
                foreach ($_POST['hotels'] as $id => $fields) {
                    if ($id === 'new') continue;
                    $hotelId = (int)$id;
                    $before = $this->partnerHotelModel->getById($hotelId);
                    if ($this->partnerHotelModel->update($hotelId, $fields)) {
                        $after = $this->partnerHotelModel->getById($hotelId);
                        $beforeAudit = $before ? $this->normalizeHotelAuditPayload($before) : null;
                        $afterAudit = $after ? $this->normalizeHotelAuditPayload($after) : $this->normalizeHotelAuditPayload((array)$fields);
                        if ($beforeAudit !== $afterAudit) {
                            $this->logAdminAction('partner_hotel_updated', 'partner_hotel', $hotelId, [
                                'before' => $beforeAudit,
                                'after' => $afterAudit,
                            ]);
                        }
                    }
                }
            }
            // Add new hotels
            if (!empty($_POST['hotels']['new']['name'])) {
                $names = $_POST['hotels']['new']['name'];
                $address1s = $_POST['hotels']['new']['address1'] ?? [];
                $address2s = $_POST['hotels']['new']['address2'] ?? [];
                $states = $_POST['hotels']['new']['state'] ?? [];
                $zips = $_POST['hotels']['new']['zip'] ?? [];
                $deliveryFees = $_POST['hotels']['new']['delivery_fee'] ?? [];
                for ($i = 0; $i < count($names); $i++) {
                    if (trim($names[$i]) !== '') {
                        $payload = [
                            'name' => $names[$i],
                            'address1' => $address1s[$i] ?? '',
                            'address2' => $address2s[$i] ?? '',
                            'state' => $states[$i] ?? '',
                            'zip' => $zips[$i] ?? '',
                            'delivery_fee' => $deliveryFees[$i] ?? 0
                        ];
                        if ($this->partnerHotelModel->add($payload)) {
                            $hotelId = $this->partnerHotelModel->lastInsertId();
                            $this->logAdminAction('partner_hotel_added', 'partner_hotel', $hotelId, [
                                'hotel' => $this->normalizeHotelAuditPayload($payload),
                            ]);
                        }
                    }
                }
            }
            // Delete hotels
            if (!empty($_POST['deleted_ids'])) {
                $ids = explode(',', $_POST['deleted_ids']);
                foreach ($ids as $id) {
                    $id = trim($id);
                    if ($id !== '') {
                        $hotelId = (int)$id;
                        $before = $this->partnerHotelModel->getById($hotelId);
                        if ($this->partnerHotelModel->delete($hotelId)) {
                            $this->logAdminAction('partner_hotel_deleted', 'partner_hotel', $hotelId, [
                                'hotel' => $before ? $this->normalizeHotelAuditPayload($before) : null,
                            ]);
                        }
                    }
                }
            }
        } elseif ($tab === 'pickups') {
            // Update existing pickups
            if (!empty($_POST['pickups']) && is_array($_POST['pickups'])) {
                foreach ($_POST['pickups'] as $id => $fields) {
                    if ($id === 'new') continue;
                    $pickupId = (int)$id;
                    $before = $this->pickupLocationModel->getById($pickupId);
                    if ($this->pickupLocationModel->update($pickupId, $fields)) {
                        $after = $this->pickupLocationModel->getById($pickupId);
                        $beforeAudit = $before ? $this->normalizePickupAuditPayload($before) : null;
                        $afterAudit = $after ? $this->normalizePickupAuditPayload($after) : $this->normalizePickupAuditPayload((array)$fields);
                        if ($beforeAudit !== $afterAudit) {
                            $this->logAdminAction('pickup_location_updated', 'pickup_location', $pickupId, [
                                'before' => $beforeAudit,
                                'after' => $afterAudit,
                            ]);
                        }
                    }
                }
            }
            // Add new pickups
            if (!empty($_POST['pickups']['new']['name'])) {
                $names = $_POST['pickups']['new']['name'];
                $addresses = $_POST['pickups']['new']['address'] ?? [];
                for ($i = 0; $i < count($names); $i++) {
                    if (trim($names[$i]) !== '') {
                        $payload = [
                            'name' => $names[$i],
                            'address' => $addresses[$i] ?? ''
                        ];
                        if ($this->pickupLocationModel->add($payload)) {
                            $pickupId = $this->pickupLocationModel->lastInsertId();
                            $this->logAdminAction('pickup_location_added', 'pickup_location', $pickupId, [
                                'pickup_location' => $this->normalizePickupAuditPayload($payload),
                            ]);
                        }
                    }
                }
            }
            // Delete pickups
            if (!empty($_POST['deleted_ids'])) {
                $ids = explode(',', $_POST['deleted_ids']);
                foreach ($ids as $id) {
                    $id = trim($id);
                    if ($id !== '') {
                        $pickupId = (int)$id;
                        $before = $this->pickupLocationModel->getById($pickupId);
                        if ($this->pickupLocationModel->delete($pickupId)) {
                            $this->logAdminAction('pickup_location_deleted', 'pickup_location', $pickupId, [
                                'pickup_location' => $before ? $this->normalizePickupAuditPayload($before) : null,
                            ]);
                        }
                    }
                }
            }
        }
        $redirectTab = $tab === 'pickups' ? 'pickups' : 'hotels';
        $redirectParams = ['tab' => $redirectTab];
        if ($redirectTab === 'hotels') {
            if ($hotelSearchContext !== '') {
                $redirectParams['hotel_search'] = $hotelSearchContext;
            }
            $redirectParams['hotel_page'] = $hotelPageContext;
        }
        header('Location: /admin/locations?' . http_build_query($redirectParams));
        exit;
    }
}
