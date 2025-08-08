<?php
$apiKey = 'AIzaSyCkBToW6JV7krIZE-pjf97Ho0ILm3dDWJ8';  // <-- Replace with your actual API key
$imagePath = 'test.jpg'; 

// Read the image file and encode it to base64
$imageData = base64_encode(file_get_contents($imagePath));

// Prepare the request payload
$requestBody = [
    "requests" => [
        [
            "image" => [
                "content" => $imageData
            ],
            "features" => [
                [
                    "type" => "LABEL_DETECTION",
                    "maxResults" => 5
                ]
            ]
        ]
    ]
];

// Google Vision API endpoint with your API key
$url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $apiKey;

// Initialize cURL
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

// Execute the request
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
} else {
    // Decode and pretty print the JSON response
    $jsonResponse = json_decode($response, true);
    echo json_encode($jsonResponse, JSON_PRETTY_PRINT);
}

curl_close($ch);
?>
