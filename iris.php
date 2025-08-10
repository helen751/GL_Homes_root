<?php
$apiKey = 'AIzaSyCIJXsraLbtk8seDBXWn7DJeEIkmBBN-8U';

$imageData = $_POST['image'] ?? '';

if (empty($imageData)) {
    http_response_code(400);
    echo json_encode(['error' => 'No image data received']);
    exit;
}

// Log the received (possibly URL-encoded) data
$logFile = 'uploads/images_log.txt';
if (!is_dir('uploads')) mkdir('uploads', 0755, true);
file_put_contents($logFile, "----- " . date('Y-m-d H:i:s') . " -----\n" . $imageData . "\n\n", FILE_APPEND);

// Decode URL-encoded Base64 if necessary
$imageData = urldecode($imageData);

// Prepare request body for Google Vision
$requestBody = [
    "requests" => [
        [
            "image" => [
                "content" => $imageData
            ],
            "features" => [
                ["type" => "LABEL_DETECTION", "maxResults" => 2],
                ["type" => "TEXT_DETECTION", "maxResults" => 1]
            ]
        ]
    ]
];

$url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;
?>
