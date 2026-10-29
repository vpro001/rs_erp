<?php
// api/rollback_document.php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
if (!$user) {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
$document_id = sanitize($input['document_id'] ?? '');
$version_id = sanitize($input['version_id'] ?? '');

if (empty($document_id) || empty($version_id)) {
    echo json_encode(["success" => false, "error" => "Required parameters missing."]);
    exit();
}

try {
    // 1. Fetch current live document
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->bind_param("s", $document_id);
    $stmt->execute();
    $live_doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$live_doc) {
        echo json_encode(["success" => false, "error" => "Document not found."]);
        exit();
    }
    
    // Clients may only roll back their own documents; students have no vault access
    if ($user['role'] === 'CLIENT') {
        $stmt = $conn->prepare("SELECT 1 FROM clients WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ss", $live_doc['client_id'], $user['id']);
        $stmt->execute();
        $owns = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$owns) {
            echo json_encode(["success" => false, "error" => "Unauthorized access."]);
            exit();
        }
    } elseif (!in_array($user['role'], ['ADMIN', 'MANAGER', 'STAFF'], true)) {
        echo json_encode(["success" => false, "error" => "Unauthorized access."]);
        exit();
    }

    // 2. Fetch the target rollback version
    $stmt = $conn->prepare("SELECT * FROM document_versions WHERE id = ? AND document_id = ?");
    $stmt->bind_param("ss", $version_id, $document_id);
    $stmt->execute();
    $rollback_ver = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$rollback_ver) {
        echo json_encode(["success" => false, "error" => "Target version not found."]);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Insert the current live version as a historical version
    $new_version_id = generate_uuid();
    $change_summary = "Rolled back to version " . $rollback_ver['version_number'];
    
    $stmt = $conn->prepare("INSERT INTO document_versions (id, document_id, version_number, file_path, file_size, uploaded_by, uploaded_at, change_summary) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisssss", $new_version_id, $live_doc['id'], $live_doc['current_version'], $live_doc['file_path'], $live_doc['file_size'], $live_doc['uploaded_by'], $live_doc['uploaded_at'], $change_summary);
    $stmt->execute();
    $stmt->close();
    
    // Update live document with the target rollback version details
    $stmt = $conn->prepare("UPDATE documents 
                            SET file_path = ?, file_size = ?, uploaded_by = ?, uploaded_at = ?, current_version = ? 
                            WHERE id = ?");
    $stmt->bind_param("ssssis", $rollback_ver['file_path'], $rollback_ver['file_size'], $rollback_ver['uploaded_by'], $rollback_ver['uploaded_at'], $rollback_ver['version_number'], $document_id);
    $stmt->execute();
    $stmt->close();
    
    // Remove the target rollback version from document_versions table so it's now live
    $stmt = $conn->prepare("DELETE FROM document_versions WHERE id = ?");
    $stmt->bind_param("s", $version_id);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    log_activity("Rolled back document version", ["document" => $live_doc['file_name'], "version" => $rollback_ver['version_number']]);
    
    echo json_encode(["success" => true]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode(["success" => false, "error" => "Execution failed: " . $e->getMessage()]);
}
