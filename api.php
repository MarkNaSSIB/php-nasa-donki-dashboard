<?php
require_once 'functions.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'flares':
        $data = fetch_nasa('FLR', ['startDate' => date('Y-m-d', strtotime('-7 days'))]);
        echo json_encode($data);
        break;
    case 'cmes':
        $data = fetch_nasa('CME', ['startDate' => date('Y-m-d', strtotime('-7 days'))]);
        echo json_encode($data);
        break;
    case 'radiation':
        $data = fetch_nasa('RBE', ['startDate' => date('Y-m-d', strtotime('-7 days'))]);
        echo json_encode($data);
        break;
    default:
        echo json_encode(['error' => 'invalid_action']);
}
