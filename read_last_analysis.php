<?php
$analysisFile = 'uploads/last_analysis.json';

if (!file_exists($analysisFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'No analysis file found']);
    exit;
}

$data = json_decode(file_get_contents($analysisFile), true);
if (!$data || !isset($data['timestamp'], $data['sentence'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid analysis data']);
    exit;
}

$timeDiff = time() - $data['timestamp'];
if ($timeDiff > 600) { // 600 seconds = 10 minutes
    http_response_code(204); // No Content, nothing recent to read
    exit;
}

// Return the sentence
header('Content-Type: application/json');
echo json_encode(['sentence' => $data['sentence']]);
