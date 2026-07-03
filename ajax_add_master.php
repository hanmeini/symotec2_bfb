<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config1.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$type = $_POST['type'] ?? '';
$name = trim($_POST['name'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nama tidak boleh kosong']);
    exit();
}

if ($type === 'dept') {
    // Check if exists
    $stmt = $conn->prepare("SELECT id FROM bagian WHERE nama_bagian = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        echo json_encode(['status' => 'success', 'id' => $row['id'], 'text' => $name]);
        exit();
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO bagian (nama_bagian) VALUES (?)");
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'id' => $stmt->insert_id, 'text' => $name]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menambah departemen']);
    }
    $stmt->close();
} elseif ($type === 'jabatan') {
    // Check if exists
    $stmt = $conn->prepare("SELECT idj FROM jabatan WHERE jabatan = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        echo json_encode(['status' => 'success', 'id' => $row['idj'], 'text' => $name]);
        exit();
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO jabatan (jabatan) VALUES (?)");
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'id' => $stmt->insert_id, 'text' => $name]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menambah jabatan']);
    }
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Type tidak valid']);
}
