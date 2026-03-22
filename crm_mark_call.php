<?php
/**
 * POST (JSON): mark CRM lead as Call done when admin taps Call (before tel: opens).
 * Body: cms_csrf, crm_submission_id
 */
require_once __DIR__ . '/cms_core.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'err' => 'method']);
    exit;
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'auth']);
    exit;
}

if (!cms_verify_csrf_post()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'csrf']);
    exit;
}

$sid = (string) ($_POST['crm_submission_id'] ?? '');
$ok = cms_crm_update_submission_status($sid, 'done');
echo json_encode(['ok' => $ok]);
exit;
