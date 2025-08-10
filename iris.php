<?php
$apiKey = 'AIzaSyCIJXsraLbtk8seDBXWn7DJeEIkmBBN-8U';

// Read the raw POST body (JSON)
$rawBody = file_get_contents("php://input");
$data = json_decode($rawBody, true);

// Extract base64 image string
$imageData = $data['image'] ?? '';

if (empty($imageData)) {
    http_response_code(400);
    echo json_encode(['error' => 'No image data received']);
    exit;
}

// Create uploads folder if it doesn't exist
if (!is_dir('uploads')) mkdir('uploads', 0755, true);

// Log the received raw Base64 for debugging
$logFile = 'uploads/images_log.txt';
file_put_contents(
    $logFile,
    "----- " . date('Y-m-d H:i:s') . " -----\n" . $imageData . "\n\n",
    FILE_APPEND
);

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

// Send to Google Vision API
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

// =====================
// New part to store last analysis with timestamp
$responseData = json_decode($response, true);

if ($httpCode == 200 && isset($responseData['responses'][0])) {
    $labels = $responseData['responses'][0]['labelAnnotations'] ?? [];
    $textAnnotation = $responseData['responses'][0]['textAnnotations'][0]['description'] ?? '';

    // Combine label descriptions
    $labelDescriptions = [];
    foreach ($labels as $label) {
        $labelDescriptions[] = $label['description'];
    }
    $combinedLabels = implode(', ', $labelDescriptions);

    // Combine into one sentence
    $sentence = "Detected labels: " . $combinedLabels;
    if (!empty($textAnnotation)) {
        $sentence .= ". Text detected: " . $textAnnotation;
    }

    // Store sentence with timestamp in a file
    $analysisFile = 'uploads/last_analysis.json';
    $dataToStore = [
        'timestamp' => time(),
        'sentence' => $sentence
    ];
    file_put_contents($analysisFile, json_encode($dataToStore));
}
