<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // tighten this in production
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($CFG)) {
    include_once('../config.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require 'WebPushHelper.php';

$push = new WebPushHelper();

$action = $_GET['action'] ?? '';

switch ($action) {

    // Frontend calls this after user grants permission
    case 'subscribe':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['endpoint'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid subscription']);
            exit;
        }
        $push->saveSubscription($data);
        echo json_encode(['success' => true]);
        break;

    // Optional: unsubscribe
    case 'unsubscribe':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!empty($data['endpoint'])) {
            $push->removeSubscription($data['endpoint']);
        }
        echo json_encode(['success' => true]);
        break;

    // Send a notification (protect this endpoint in production!)
    case 'send':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $title = $input['title'] ?? 'Hello';
        $body  = $input['body']  ?? 'This is a test notification';
        $url   = $input['url']   ?? '/';

        $payload = [
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'icon'  => '/icon-192.png', // optional
        ];

        $reports = $push->send($payload);
        echo json_encode(['reports' => $reports]);
        break;

    // Return the public VAPID key for the frontend
    case 'vapidPublicKey':
        $vapid = json_decode($CFG->vapid, true);
        echo json_encode(['publicKey' => $vapid['publicKey']]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}