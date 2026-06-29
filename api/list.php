<?php
// api/list.php — returns a list of developer tips as JSON

session_start();

header('Content-Type: application/json');

// Track how many times the tips have been fetched this session
if (!isset($_SESSION['tips_fetched'])) {
    $_SESSION['tips_fetched'] = 0;
}
$_SESSION['tips_fetched']++;

$tips = [
    [
        "id"       => 1,
        "category" => "Debugging",
        "tip"      => "Read the error message. The whole thing. It usually tells you exactly what went wrong."
    ],
    [
        "id"       => 2,
        "category" => "Code Quality",
        "tip"      => "Name variables after what they hold, not what type they are. \$userName beats \$strInput every time."
    ],
    [
        "id"       => 3,
        "category" => "Mindset",
        "tip"      => "It's not working is not a bug report. Reproduce the problem first, then describe it precisely."
    ],
    [
        "id"       => 4,
        "category" => "Git",
        "tip"      => "Commit early, commit often. A short commit message beats a 400-line uncommitted file."
    ],
    [
        "id"       => 5,
        "category" => "Performance",
        "tip"      => "Don't optimise before you measure. Slow code you understand beats fast code you don't."
    ],
    [
        "id"       => 6,
        "category" => "Learning",
        "tip"      => "Type the code yourself. Copy-paste teaches your clipboard, not your brain."
    ],
    [
        "id"       => 7,
        "category" => "Debugging",
        "tip"      => "var_dump() is your friend. Add it everywhere, then remove it everywhere."
    ],
];

// Optional: filter by category via ?category=Debugging
$filterCategory = isset($_GET['category']) ? trim($_GET['category']) : null;

if ($filterCategory !== null) {
    $filtered = array_values(array_filter($tips, function ($t) use ($filterCategory) {
        return strcasecmp($t['category'], $filterCategory) === 0;
    }));

    if (empty($filtered)) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error"   => "No tips found for category: " . htmlspecialchars($filterCategory),
        ]);
        exit;
    }

    echo json_encode([
        "success"       => true,
        "category"      => $filterCategory,
        "count"         => count($filtered),
        "tips"          => $filtered,
        "session_fetch" => $_SESSION['tips_fetched'],
    ]);
    exit;
}

echo json_encode([
    "success"       => true,
    "count"         => count($tips),
    "tips"          => $tips,
    "session_fetch" => $_SESSION['tips_fetched'],
]);