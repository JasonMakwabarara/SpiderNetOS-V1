<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? 'What can you do?';

$apiKey = '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';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful AI assistant. Be concise.'],
        ['role' => 'user', 'content' => $message]
    ],
    'max_tokens' => 200
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['error' => 'Curl error: ' . $error]);
    exit;
}

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $aiReply = $data['choices'][0]['message']['content'] ?? 'No response';
    echo json_encode(['result' => ['response' => $aiReply]]);
} else {
    echo json_encode(['error' => 'OpenAI API error: ' . $httpCode, 'response' => $response]);
}