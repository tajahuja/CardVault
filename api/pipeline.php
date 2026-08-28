<?php
/**
 * API - Sales Opportunities & Pipeline Endpoint
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

if ($method === 'POST') {
    // Validate CSRF token for mutations
    validate_csrf();
    
    $action = isset($_POST['action']) ? trim($_POST['action']) : 'create';
    
    if ($action === 'create') {
        $name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
        $contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        $value = isset($_POST['value']) ? floatval($_POST['value']) : 0.00;
        $probability = isset($_POST['probability']) ? intval($_POST['probability']) : 0;
        $expectedClose = isset($_POST['expected_close_date']) ? trim(strip_tags($_POST['expected_close_date'])) : null;
        $stage = isset($_POST['stage']) ? trim(strip_tags($_POST['stage'])) : 'New Lead';
        
        if (empty($name) || $contactId <= 0) {
            json_response(false, 'Deal name and Contact are required.', [], 400);
        }
        
        // Assert stage value is valid
        $validStages = ['New Lead', 'Contacted', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];
        if (!in_array($stage, $validStages)) {
            $stage = 'New Lead';
        }
        
        try {
            // Verify contact ownership and retrieve company_id
            $stmtC = $pdo->prepare("SELECT company_id, full_name FROM contacts WHERE id = :id AND user_id = :user_id");
            $stmtC->execute(['id' => $contactId, 'user_id' => $userId]);
            $contact = $stmtC->fetch();
            
            if (!$contact) {
                json_response(false, 'Contact not found or unauthorized.', [], 404);
            }
            
            $companyId = $contact['company_id'];
            
            $stmtInsert = $pdo->prepare("
                INSERT INTO opportunities (user_id, contact_id, company_id, name, stage, value, probability, expected_close_date) 
                VALUES (:user_id, :contact_id, :company_id, :name, :stage, :value, :probability, :expected_close_date)
            ");
            $stmtInsert->execute([
                'user_id' => $userId,
                'contact_id' => $contactId,
                'company_id' => $companyId,
                'name' => $name,
                'stage' => $stage,
                'value' => $value,
                'probability' => $probability,
                'expected_close_date' => $expectedClose !== '' ? $expectedClose : null
            ]);
            
            $oppId = $pdo->lastInsertId();
            
            // Log interaction
            $stmtLog = $pdo->prepare("
                INSERT INTO interactions (contact_id, user_id, type, description) 
                VALUES (:contact_id, :user_id, 'Status Change', :desc)
            ");
            $stmtLog->execute([
                'contact_id' => $contactId,
                'user_id' => $userId,
                'desc' => "New Opportunity Deal Created: '$name' at stage '$stage'. Value: ₹" . number_format($value, 2)
            ]);
            
            json_response(true, 'Opportunity created successfully.', ['id' => $oppId], 201);
        } catch (\PDOException $e) {
            error_log("Create Opportunity API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while creating the opportunity.', [], 500);
        }
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
        $value = isset($_POST['value']) ? floatval($_POST['value']) : 0.00;
        $probability = isset($_POST['probability']) ? intval($_POST['probability']) : 0;
        $expectedClose = isset($_POST['expected_close_date']) ? trim(strip_tags($_POST['expected_close_date'])) : null;
        $stage = isset($_POST['stage']) ? trim(strip_tags($_POST['stage'])) : '';
        
        if ($id <= 0 || empty($name)) {
            json_response(false, 'Invalid ID or empty deal name.', [], 400);
        }
        
        try {
            // Verify ownership
            $stmtOwn = $pdo->prepare("SELECT * FROM opportunities WHERE id = :id AND user_id = :user_id");
            $stmtOwn->execute(['id' => $id, 'user_id' => $userId]);
            $opp = $stmtOwn->fetch();
            if (!$opp) {
                json_response(false, 'Opportunity not found or unauthorized.', [], 404);
            }
            
            $oldStage = $opp['stage'];
            $newStage = ($stage !== '') ? $stage : $oldStage;
            
            $stmtUpdate = $pdo->prepare("
                UPDATE opportunities 
                SET name = :name, stage = :stage, value = :value, probability = :probability, expected_close_date = :expected_close_date 
                WHERE id = :id AND user_id = :user_id
            ");
            $stmtUpdate->execute([
                'name' => $name,
                'stage' => $newStage,
                'value' => $value,
                'probability' => $probability,
                'expected_close_date' => $expectedClose !== '' ? $expectedClose : null,
                'id' => $id,
                'user_id' => $userId
            ]);
            
            // Log interaction if stage changed
            if ($oldStage !== $newStage) {
                $stmtLog = $pdo->prepare("
                    INSERT INTO interactions (contact_id, user_id, type, description) 
                    VALUES (:contact_id, :user_id, 'Status Change', :desc)
                ");
                $stmtLog->execute([
                    'contact_id' => $opp['contact_id'],
                    'user_id' => $userId,
                    'desc' => "Opportunity Deal '$name' moved from stage '$oldStage' to '$newStage'."
                ]);
            }
            
            json_response(true, 'Opportunity updated successfully.');
        } catch (\PDOException $e) {
            error_log("Update Opportunity API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while updating the opportunity.', [], 500);
        }
    } elseif ($action === 'move') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $stage = isset($_POST['stage']) ? trim(strip_tags($_POST['stage'])) : '';
        
        if ($id <= 0 || empty($stage)) {
            json_response(false, 'Invalid move parameters.', [], 400);
        }
        
        $validStages = ['New Lead', 'Contacted', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];
        if (!in_array($stage, $validStages)) {
            json_response(false, 'Invalid pipeline stage.', [], 400);
        }
        
        try {
            // Verify ownership
            $stmtOwn = $pdo->prepare("SELECT id, contact_id, name, stage FROM opportunities WHERE id = :id AND user_id = :user_id");
            $stmtOwn->execute(['id' => $id, 'user_id' => $userId]);
            $opp = $stmtOwn->fetch();
            if (!$opp) {
                json_response(false, 'Opportunity not found or unauthorized.', [], 404);
            }
            
            $oldStage = $opp['stage'];
            if ($oldStage !== $stage) {
                $stmtUpdate = $pdo->prepare("UPDATE opportunities SET stage = :stage WHERE id = :id AND user_id = :user_id");
                $stmtUpdate->execute(['stage' => $stage, 'id' => $id, 'user_id' => $userId]);
                
                // Log interaction in contact timeline
                $stmtLog = $pdo->prepare("
                    INSERT INTO interactions (contact_id, user_id, type, description) 
                    VALUES (:contact_id, :user_id, 'Status Change', :desc)
                ");
                $stmtLog->execute([
                    'contact_id' => $opp['contact_id'],
                    'user_id' => $userId,
                    'desc' => "Kanban Move: Deal '{$opp['name']}' moved from '{$oldStage}' to '{$stage}'."
                ]);
            }
            
            json_response(true, 'Opportunity stage moved successfully.');
        } catch (\PDOException $e) {
            error_log("Move Opportunity API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while moving the opportunity.', [], 500);
        }
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            json_response(false, 'Invalid opportunity ID.', [], 400);
        }
        
        try {
            // Verify ownership
            $stmtCheck = $pdo->prepare("SELECT id, name, contact_id FROM opportunities WHERE id = :id AND user_id = :user_id");
            $stmtCheck->execute(['id' => $id, 'user_id' => $userId]);
            $opp = $stmtCheck->fetch();
            if (!$opp) {
                json_response(false, 'Opportunity not found or unauthorized.', [], 404);
            }
            
            $stmtDel = $pdo->prepare("DELETE FROM opportunities WHERE id = :id AND user_id = :user_id");
            $stmtDel->execute(['id' => $id, 'user_id' => $userId]);
            
            // Log interaction
            $stmtLog = $pdo->prepare("
                INSERT INTO interactions (contact_id, user_id, type, description) 
                VALUES (:contact_id, :user_id, 'Status Change', :desc)
            ");
            $stmtLog->execute([
                'contact_id' => $opp['contact_id'],
                'user_id' => $userId,
                'desc' => "Opportunity Deal '{$opp['name']}' was deleted."
            ]);
            
            json_response(true, 'Opportunity deleted successfully.');
        } catch (\PDOException $e) {
            error_log("Delete Opportunity API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while deleting the opportunity.', [], 500);
        }
    }
} else {
    json_response(false, 'Request method not supported.', [], 405);
}
