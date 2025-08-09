<?php
$apiKey = 'AIzaSyCIJXsraLbtk8seDBXWn7DJeEIkmBBN-8U';
$imageData = $_POST['image'];

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
curl_close($ch);

echo $response;
?>
