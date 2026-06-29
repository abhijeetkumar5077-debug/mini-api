<?php
// index.php — front page that calls our own API endpoints

session_start();

// ── Helper: call a local endpoint and decode JSON ─────────────────────────────
function callEndpoint(string $url): array {
    $opts = [
        'http' => [
            'timeout'        => 5,
            'ignore_errors'  => true,
        ],
    ];
    $context = stream_context_create($opts);
    $raw     = file_get_contents($url, false, $context);

    if ($raw === false) {
        throw new RuntimeException('Could not reach the API endpoint. Check the server is running.');
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('API returned invalid JSON: ' . json_last_error_msg());
    }

    return ['data' => $data, 'raw' => $raw];
}

// ── Detect whether we're running locally or on a host ────────────────────────
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir      = dirname($_SERVER['SCRIPT_NAME']);
$dir      = rtrim($dir, '/');
$base     = $protocol . '://' . $host . $dir;

// ── Handle the greet form submission ─────────────────────────────────────────
$greetResult  = null;
$greetError   = null;
$greetRaw     = '';
$submittedName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $submittedName = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');

    try {
        if ($submittedName === '') {
            throw new InvalidArgumentException('Please enter your name before submitting.');
        }
        $url = $base . '/api/greet.php?name=' . urlencode($_POST['name']);
        $res = callEndpoint($url);
        $greetResult = $res['data'];
        $greetRaw    = $res['raw'];
    } catch (InvalidArgumentException $e) {
        $greetError = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    } catch (RuntimeException $e) {
        $greetError = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    } catch (Exception $e) {
        $greetError = 'Unexpected error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}

// ── Always load tips on page load ─────────────────────────────────────────────
$tips      = [];
$tipsError = null;
$tipsRaw   = '';

try {
    $url = $base . '/api/list.php';
    $res = callEndpoint($url);
    if (!empty($res['data']['tips'])) {
        $tips = $res['data']['tips'];
    }
    $tipsRaw = $res['raw'];
} catch (RuntimeException $e) {
    $tipsError = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} catch (Exception $e) {
    $tipsError = 'Unexpected error loading tips: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

// ── Build score bar width ─────────────────────────────────────────────────────
$scoreWidth = 0;
if ($greetResult && isset($greetResult['dev_score'])) {
    $scoreWidth = (int) $greetResult['dev_score'];
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dev Wisdom · Mini API</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!-- student: register@example.com -->
</head>
<body>

<div class="site-wrap">

    <!-- ── Header ─────────────────────────────────────────────── -->
    <header class="site-header">
        <div class="wordmark">&lt;<span>dev</span>wisdom<span>/&gt;</span></div>
        <div class="tagline">// a php mini-api · week 04 project</div>
    </header>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- SECTION 1 — Greet endpoint (form → POST → API → display) -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <section>
        <div class="endpoint-badge">
            <span class="method">GET</span>
            api/greet.php?name=<em>YourName</em>
        </div>

        <div class="card">
            <div class="card-title">// greet endpoint</div>
            <h2>Who's coding today?</h2>
            <p style="color:var(--muted);font-size:0.88rem;margin-bottom:18px;">
                Enter your name and the API will return a personalised developer greeting as JSON — which this page then decodes and renders.
            </p>

            <form method="POST" action="">
                <div class="form-row">
                    <input
                        type="text"
                        name="name"
                        id="nameInput"
                        placeholder="e.g. Abhijeet"
                        maxlength="50"
                        value="<?= $submittedName ?>"
                        autocomplete="off"
                    >
                    <button type="submit">Ask the API →</button>
                </div>
            </form>

            <!-- Error -->
            <?php if ($greetError): ?>
                <div class="result-block">
                    <div class="error-msg">⚠ <?= $greetError ?></div>
                </div>
            <?php endif; ?>

            <!-- Success result -->
            <?php if ($greetResult && !empty($greetResult['success'])): ?>
                <div class="result-block">
                    <div class="greet-result">
                        <div class="message">"<?= htmlspecialchars($greetResult['message'], ENT_QUOTES, 'UTF-8') ?>"</div>

                        <div class="meta-row">
                            <div class="meta-pill">name: <strong><?= htmlspecialchars($greetResult['name'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div class="meta-pill">visit #<strong><?= (int) $greetResult['visit'] ?></strong> this session</div>
                            <div class="meta-pill">dev_score: <strong><?= (int) $greetResult['dev_score'] ?>/100</strong></div>
                            <div class="meta-pill">at: <strong><?= htmlspecialchars($greetResult['timestamp'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                        </div>

                        <div class="score-bar-wrap">
                            <div class="score-label">dev score</div>
                            <div class="score-bar">
                                <div class="score-bar-fill" style="width:<?= $scoreWidth ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Raw JSON toggle -->
                    <div class="json-toggle" onclick="toggleJson('greetJson', this)">
                        <span class="caret">▶</span> show raw json
                    </div>
                    <div class="json-viewer hidden" id="greetJson"><?= syntaxHighlight($greetRaw) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- SECTION 2 — List endpoint (server-side call on page load) -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <section>
        <div class="endpoint-badge">
            <span class="method">GET</span>
            api/list.php
        </div>

        <div class="card">
            <div class="card-title">// list endpoint · <?= count($tips) ?> tips fetched</div>
            <h2>Developer tips</h2>
            <p style="color:var(--muted);font-size:0.88rem;margin-bottom:20px;">
                Loaded server-side via <code style="font-family:var(--font-mono);color:var(--accent)">file_get_contents()</code> on page load, decoded with <code style="font-family:var(--font-mono);color:var(--accent)">json_decode()</code>, and rendered below.
            </p>

            <?php if ($tipsError): ?>
                <div class="error-msg">⚠ <?= $tipsError ?></div>
            <?php elseif (empty($tips)): ?>
                <div class="error-msg">No tips returned from the API.</div>
            <?php else: ?>
                <ul class="tips-list">
                    <?php foreach ($tips as $tip): ?>
                        <li class="tip-item">
                            <div class="tip-num"><?= (int) $tip['id'] ?></div>
                            <div class="tip-body">
                                <div class="tip-cat"><?= htmlspecialchars($tip['category'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="tip-text"><?= htmlspecialchars($tip['tip'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Raw JSON toggle -->
            <?php if ($tipsRaw): ?>
                <div class="json-toggle" onclick="toggleJson('tipsJson', this)" style="margin-top:20px;">
                    <span class="caret">▶</span> show raw json
                </div>
                <div class="json-viewer hidden" id="tipsJson"><?= syntaxHighlight($tipsRaw) ?></div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ── Footer ─────────────────────────────────────────────── -->
    <footer class="site-footer">
        <span>dev_wisdom mini_api · week 04 php project</span>
        <span>endpoints: /api/list.php &nbsp;·&nbsp; /api/greet.php</span>
    </footer>

</div>

<script>
function toggleJson(id, toggle) {
    const el = document.getElementById(id);
    const caret = toggle.querySelector('.caret');
    if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
        caret.textContent = '▼';
        toggle.innerHTML = toggle.innerHTML.replace('show raw json', 'hide raw json');
    } else {
        el.classList.add('hidden');
        caret.textContent = '▶';
        toggle.innerHTML = toggle.innerHTML.replace('hide raw json', 'show raw json');
    }
}
</script>

</body>
</html>
<?php

// ── JSON syntax highlighter ────────────────────────────────────────────────────
function syntaxHighlight(string $json): string {
    // Pretty-print first
    $decoded = json_decode($json);
    $pretty  = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $escaped = htmlspecialchars($pretty, ENT_QUOTES, 'UTF-8');

    // Colour keys, strings, numbers, booleans
    $escaped = preg_replace(
        '/(&quot;[^&]+&quot;)\s*:/',
        '<span class="j-key">$1</span>:',
        $escaped
    );
    $escaped = preg_replace(
        '/:\s*(&quot;[^&]*&quot;)/',
        ': <span class="j-str">$1</span>',
        $escaped
    );
    $escaped = preg_replace(
        '/:\s*(\d+)/',
        ': <span class="j-num">$1</span>',
        $escaped
    );
    $escaped = preg_replace(
        '/:\s*(true|false|null)/',
        ': <span class="j-bool">$1</span>',
        $escaped
    );

    return $escaped;
}