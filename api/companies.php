<?php
/**
 * API - Companies Management Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require dirname(__DIR__) . '/includes/db.php';

// Assert user is logged in
require_login();

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
    
    if ($action === 'list') {
        try {
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM contacts WHERE company_id = c.id) as contact_count,
                       (SELECT MAX(created_at) FROM interactions WHERE contact_id IN (SELECT id FROM contacts WHERE company_id = c.id)) as last_interaction
                FROM companies c 
                WHERE c.user_id = :user_id 
                ORDER BY c.name ASC
            ");
            $stmt->execute(['user_id' => $userId]);
            $companies = $stmt->fetchAll();
            json_response(true, 'Companies list retrieved.', ['companies' => $companies]);
        } catch (\PDOException $e) {
            error_log("Fetch Companies API Error: " . $e->getMessage());
            json_response(false, 'Unable to retrieve companies.', [], 500);
        }
    } elseif ($action === 'detail') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        try {
            $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            $company = $stmt->fetch();
            if (!$company) {
                json_response(false, 'Company not found.', [], 404);
            }
            json_response(true, 'Company detail retrieved.', ['company' => $company]);
        } catch (\PDOException $e) {
            error_log("Fetch Company Detail API Error: " . $e->getMessage());
            json_response(false, 'Unable to retrieve company detail.', [], 500);
        }
    }
} elseif ($method === 'POST') {
    // Validate CSRF token for mutations
    validate_csrf();
    
    $action = isset($_POST['action']) ? trim($_POST['action']) : 'create';
    
    if ($action === 'create') {
        $name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
        $industry = isset($_POST['industry']) ? trim(strip_tags($_POST['industry'])) : '';
        $website = isset($_POST['website']) ? trim(strip_tags($_POST['website'])) : '';
        $location = isset($_POST['location']) ? trim(strip_tags($_POST['location'])) : '';
        $leadSource = isset($_POST['lead_source']) ? trim(strip_tags($_POST['lead_source'])) : '';
        
        if (empty($name)) {
            json_response(false, 'Company name is required.', [], 400);
        }
        
        try {
            // Check uniqueness for this user
            $stmtCheck = $pdo->prepare("SELECT id FROM companies WHERE name = :name AND user_id = :user_id");
            $stmtCheck->execute(['name' => $name, 'user_id' => $userId]);
            if ($stmtCheck->fetchColumn()) {
                json_response(false, 'A company with this name already exists.', [], 400);
            }
            
            $stmtInsert = $pdo->prepare("
                INSERT INTO companies (user_id, name, industry, website, location, lead_source) 
                VALUES (:user_id, :name, :industry, :website, :location, :lead_source)
            ");
            $stmtInsert->execute([
                'user_id' => $userId,
                'name' => $name,
                'industry' => $industry !== '' ? $industry : null,
                'website' => $website !== '' ? $website : null,
                'location' => $location !== '' ? $location : null,
                'lead_source' => $leadSource !== '' ? $leadSource : null
            ]);
            
            json_response(true, 'Company created successfully.', ['id' => $pdo->lastInsertId()], 201);
        } catch (\PDOException $e) {
            error_log("Create Company API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while creating the company.', [], 500);
        }
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
        $industry = isset($_POST['industry']) ? trim(strip_tags($_POST['industry'])) : '';
        $website = isset($_POST['website']) ? trim(strip_tags($_POST['website'])) : '';
        $location = isset($_POST['location']) ? trim(strip_tags($_POST['location'])) : '';
        $leadSource = isset($_POST['lead_source']) ? trim(strip_tags($_POST['lead_source'])) : '';
        
        if ($id <= 0 || empty($name)) {
            json_response(false, 'Invalid data or empty company name.', [], 400);
        }
        
        try {
            // Enforce ownership
            $stmtOwn = $pdo->prepare("SELECT id FROM companies WHERE id = :id AND user_id = :user_id");
            $stmtOwn->execute(['id' => $id, 'user_id' => $userId]);
            if (!$stmtOwn->fetchColumn()) {
                json_response(false, 'Company not found or unauthorized.', [], 404);
            }
            
            // Check duplicate name
            $stmtCheck = $pdo->prepare("SELECT id FROM companies WHERE name = :name AND user_id = :user_id AND id != :id");
            $stmtCheck->execute(['name' => $name, 'user_id' => $userId, 'id' => $id]);
            if ($stmtCheck->fetchColumn()) {
                json_response(false, 'A company with this name already exists.', [], 400);
            }
            
            $stmtUpdate = $pdo->prepare("
                UPDATE companies 
                SET name = :name, industry = :industry, website = :website, location = :location, lead_source = :lead_source 
                WHERE id = :id AND user_id = :user_id
            ");
            $stmtUpdate->execute([
                'name' => $name,
                'industry' => $industry !== '' ? $industry : null,
                'website' => $website !== '' ? $website : null,
                'location' => $location !== '' ? $location : null,
                'lead_source' => $leadSource !== '' ? $leadSource : null,
                'id' => $id,
                'user_id' => $userId
            ]);
            
            json_response(true, 'Company updated successfully.');
        } catch (\PDOException $e) {
            error_log("Update Company API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while updating the company.', [], 500);
        }
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            json_response(false, 'Invalid company ID.', [], 400);
        }
        
        try {
            // Check ownership
            $stmtCheck = $pdo->prepare("SELECT id FROM companies WHERE id = :id AND user_id = :user_id");
            $stmtCheck->execute(['id' => $id, 'user_id' => $userId]);
            if (!$stmtCheck->fetchColumn()) {
                json_response(false, 'Company not found or unauthorized.', [], 404);
            }
            
            $stmtDel = $pdo->prepare("DELETE FROM companies WHERE id = :id AND user_id = :user_id");
            $stmtDel->execute(['id' => $id, 'user_id' => $userId]);
            json_response(true, 'Company deleted successfully.');
        } catch (\PDOException $e) {
            error_log("Delete Company API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while deleting the company.', [], 500);
        }
    } elseif ($action === 'assign_contact') {
        $companyId = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
        $contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        
        if ($contactId <= 0) {
            json_response(false, 'Invalid contact target.', [], 400);
        }
        
        try {
            // Verify contact ownership
            $stmtContact = $pdo->prepare("SELECT id, company FROM contacts WHERE id = :id AND user_id = :user_id");
            $stmtContact->execute(['id' => $contactId, 'user_id' => $userId]);
            $contact = $stmtContact->fetch();
            if (!$contact) {
                json_response(false, 'Contact not found or unauthorized.', [], 404);
            }
            
            if ($companyId > 0) {
                // Verify company ownership
                $stmtComp = $pdo->prepare("SELECT id, name FROM companies WHERE id = :id AND user_id = :user_id");
                $stmtComp->execute(['id' => $companyId, 'user_id' => $userId]);
                $company = $stmtComp->fetch();
                if (!$company) {
                    json_response(false, 'Company not found or unauthorized.', [], 404);
                }
                
                // Update contact to point to company ID and sync company text column
                $stmtUpdate = $pdo->prepare("UPDATE contacts SET company_id = :company_id, company = :company_name WHERE id = :id AND user_id = :user_id");
                $stmtUpdate->execute([
                    'company_id' => $companyId,
                    'company_name' => $company['name'],
                    'id' => $contactId,
                    'user_id' => $userId
                ]);
            } else {
                // Dissociate
                $stmtUpdate = $pdo->prepare("UPDATE contacts SET company_id = NULL, company = NULL WHERE id = :id AND user_id = :user_id");
                $stmtUpdate->execute([
                    'id' => $contactId,
                    'user_id' => $userId
                ]);
            }
            json_response(true, 'Contact company assignment updated successfully.');
        } catch (\PDOException $e) {
            error_log("Assign Contact Company API Error: " . $e->getMessage());
            json_response(false, 'An error occurred during company assignment.', [], 500);
        }
    }
} else {
    json_response(false, 'Request method not supported.', [], 405);
}
