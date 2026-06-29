<?php
// api/greet.php — reads ?name= from the URL and returns a JSON greeting

session_start();

header('Content-Type: application/json');

// Track greetings in the session
if (!isset($_SESSION['greeted_names'])) {
    $_SESSION['greeted_names'] = [];
}

// Validate input
if (empty($_GET['name'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => "Please provide a name via ?name=YourName",
    ]);
    exit;
}

// Sanitise — strip tags, trim whitespace
$rawName = trim(strip_tags($_GET['name']));

if (strlen($rawName) < 1 || strlen($rawName) > 50) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "error"   => "Name must be between 1 and 50 characters.",
    ]);
    exit;
}

// Safe name for display (no HTML injection)
$safeName = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');

// Track in session
$_SESSION['greeted_names'][] = $safeName;
$visitCount = count(array_keys(
    array_count_values($_SESSION['greeted_names']),
    $safeName
));

// Pick a greeting flavour based on visit count
$messages = [
    "Hey {name}! Ready to write some PHP that doesn't break at 3 AM?",
    "Welcome back, {name}. The semicolons have been missing you.",
    "Oh, {name} again. PHP is starting to recognise your coding style.",
    "{name}, you're becoming a regular. The server is warming up just for you.",
    "At this point, {name}, you might as well move in. There's a cot next to the web root.",
];

$index   = min(max($visitCount - 1, 0), count($messages) - 1);
$message = str_replace('{name}', $safeName, $messages[$index]);

// Build a fun "dev score" from the name (just for entertainment)
$devScore = (array_sum(array_map('ord', str_split(strtolower($rawName)))) % 100) + 1;

echo json_encode([
    "success"    => true,
    "name"       => $safeName,
    "message"    => $message,
    "dev_score"  => $devScore,
    "visit"      => $visitCount,
    "timestamp"  => date('Y-m-d H:i:s'),
]);