<?php
$apiKey = 'AIzaSyCIJXsraLbtk8seDBXWn7DJeEIkmBBN-8U';

// Get the image data from POST
$imageData = $_POST['image'] ?? '';

if (empty($imageData)) {
    http_response_code(400);
    echo json_encode(['error' => 'No image data received']);
    exit;
}

// Trim whitespace or newlines (sometimes present)
$imageData = trim($imageData);

// Optional: Validate if base64 is valid by decoding
$decodedImage = base64_decode($imageData, true);
if ($decodedImage === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid base64 image data']);
    exit;
}

// Prepare request body for Google Vision API with raw base64 (no URL encoding)
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

// Forward Google Vision API response and HTTP status code
http_response_code($httpCode);
echo $response;
?>
