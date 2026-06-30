<?php
// index.php — front page that calls our own API endpoints

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_history'])) {
    unset($_SESSION['greet_history']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

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
$greetResult    = null;
$greetError     = null;
$greetRaw       = '';
$submittedName  = '';
$greetLatencyMs = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $submittedName = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');

    try {
        if ($submittedName === '') {
            throw new InvalidArgumentException('Please enter your name before submitting.');
        }
        $url   = $base . '/api/greet.php?name=' . urlencode($_POST['name']);
        $start = microtime(true);
        $res   = callEndpoint($url);
        $greetLatencyMs = round((microtime(true) - $start) * 1000);
        $greetResult = $res['data'];
        $greetRaw    = $res['raw'];

        // Keep a short rolling history of greetings for this session
        if (!empty($greetResult['success'])) {
            if (!isset($_SESSION['greet_history']) || !is_array($_SESSION['greet_history'])) {
                $_SESSION['greet_history'] = [];
            }
            array_unshift($_SESSION['greet_history'], [
                'name'      => $greetResult['name']      ?? $submittedName,
                'dev_score' => $greetResult['dev_score'] ?? 0,
                'timestamp' => $greetResult['timestamp'] ?? date('c'),
            ]);
            $_SESSION['greet_history'] = array_slice($_SESSION['greet_history'], 0, 5);
        }
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

// ── Build score bar width + a friendly label for it ───────────────────────────
$scoreWidth = 0;
$scoreLabel = '';
if ($greetResult && isset($greetResult['dev_score'])) {
    $scoreWidth = (int) $greetResult['dev_score'];
    $scoreLabel = match (true) {
        $scoreWidth >= 90 => 'Legendary 🏆',
        $scoreWidth >= 70 => 'Senior dev energy 🚀',
        $scoreWidth >= 50 => 'Solid mid-level 💪',
        $scoreWidth >= 25 => 'Junior on the rise 🌱',
        default           => 'Just getting started ✨',
    };
}

// ── A few quick stats about the loaded tips, for a nicer header strip ─────────
$tipCategories = [];
foreach ($tips as $t) {
    if (!empty($t['category'])) {
        $tipCategories[$t['category']] = true;
    }
}
$tipCategoryCount = count($tipCategories);
$tipCategoryList  = array_keys($tipCategories);
sort($tipCategoryList);

$greetHistory = $_SESSION['greet_history'] ?? [];

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dev Wisdom · Mini API</title>
    <meta name="description" content="A small PHP mini-API project: a greet endpoint and a tips endpoint, consumed and rendered live on this page.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">


    <!--My email address: abhijeetkumar5077@gmail.com -->


</head>
<body>

<div class="site-wrap">

    <!-- ── Header ─────────────────────────────────────────────── -->
    <header class="site-header">
        <div class="header-top">
            <div class="wordmark">&lt;<span>dev</span>wisdom<span>/&gt;</span></div>
            <button id="themeToggle" class="theme-toggle" type="button" title="Toggle light / dark theme" aria-label="Toggle theme">
                <span class="theme-icon">🌙</span>
            </button>
        </div>
        <div class="tagline">// a php mini-api · week 04 project</div>

        <div class="header-stats">
            <div class="stat-chip"><span class="stat-num"><?= count($tips) ?></span><span class="stat-text">tips loaded</span></div>
            <div class="stat-chip"><span class="stat-num"><?= $tipCategoryCount ?></span><span class="stat-text">categories</span></div>
            <div class="stat-chip"><span class="stat-num">2</span><span class="stat-text">live endpoints</span></div>
        </div>
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

            <form method="POST" action="" id="greetForm">
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
                    <button type="submit" id="greetSubmit">
                        <span class="btn-label">Ask the API →</span>
                        <span class="btn-spinner hidden" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="char-counter"><span id="charCount">0</span>/50</div>
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
                            <?php if ($greetLatencyMs !== null): ?>
                                <div class="meta-pill">latency: <strong><?= (int) $greetLatencyMs ?>ms</strong></div>
                            <?php endif; ?>
                        </div>

                        <div class="score-bar-wrap">
                            <div class="score-label">dev score &middot; <?= $scoreLabel ?></div>
                            <div class="score-bar">
                                <div class="score-bar-fill" data-width="<?= $scoreWidth ?>" style="width:0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Raw JSON toggle -->
                    <div class="json-toolbar">
                        <div class="json-toggle" onclick="toggleJson('greetJson', this)">
                            <span class="caret">▶</span> show raw json
                        </div>
                        <button type="button" class="copy-btn" onclick="copyJson('greetJson', this)">copy</button>
                    </div>
                    <div class="json-viewer hidden" id="greetJson"><?= syntaxHighlight($greetRaw) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($greetHistory)): ?>
                <div class="history-block">
                    <div class="history-title">recent greetings this session</div>
                    <ul class="history-list">
                        <?php foreach ($greetHistory as $h): ?>
                            <li class="history-item">
                                <span class="history-name"><?= htmlspecialchars($h['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="history-score"><?= (int) $h['dev_score'] ?>/100</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="POST" action="?clear_history=1" class="history-clear-form">
                        <button type="submit" name="clear_history" value="1" class="clear-history-btn">clear history</button>
                    </form>
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
            <p style="color:var(--muted);font-size:0.88rem;margin-bottom:16px;">
                Loaded server-side via <code style="font-family:var(--font-mono);color:var(--accent)">file_get_contents()</code> on page load, decoded with <code style="font-family:var(--font-mono);color:var(--accent)">json_decode()</code>, and rendered below.
            </p>

            <?php if (!empty($tips)): ?>
                <input
                    type="text"
                    id="tipFilter"
                    class="tip-filter"
                    placeholder="🔍 Filter tips by keyword or category…"
                    autocomplete="off"
                >

                <div class="filter-toolbar">
                    <div class="category-pills" id="categoryPills">
                        <button type="button" class="pill active" data-cat="">All</button>
                        <?php foreach ($tipCategoryList as $cat): ?>
                            <button type="button" class="pill" data-cat="<?= htmlspecialchars(strtolower($cat), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <label class="sort-control">
                        sort:
                        <select id="tipSort">
                            <option value="id">default</option>
                            <option value="category">category</option>
                            <option value="length">tip length</option>
                        </select>
                    </label>
                </div>
            <?php endif; ?>

            <?php if ($tipsError): ?>
                <div class="error-msg">⚠ <?= $tipsError ?></div>
            <?php elseif (empty($tips)): ?>
                <div class="error-msg">No tips returned from the API.</div>
            <?php else: ?>
                <ul class="tips-list" id="tipsList">
                    <?php foreach ($tips as $tip): ?>
                        <li class="tip-item"
                            data-search="<?= htmlspecialchars(strtolower($tip['category'] . ' ' . $tip['tip']), ENT_QUOTES, 'UTF-8') ?>"
                            data-category="<?= htmlspecialchars(strtolower($tip['category']), ENT_QUOTES, 'UTF-8') ?>"
                            data-len="<?= strlen($tip['tip']) ?>"
                            data-id="<?= (int) $tip['id'] ?>">
                            <div class="tip-num"><?= (int) $tip['id'] ?></div>
                            <div class="tip-body">
                                <div class="tip-cat"><?= htmlspecialchars($tip['category'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="tip-text"><?= htmlspecialchars($tip['tip'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="no-results hidden" id="noTipResults">No tips match your filter.</div>
            <?php endif; ?>

            <!-- Raw JSON toggle -->
            <?php if ($tipsRaw): ?>
                <div class="json-toolbar" style="margin-top:20px;">
                    <div class="json-toggle" onclick="toggleJson('tipsJson', this)">
                        <span class="caret">▶</span> show raw json
                    </div>
                    <button type="button" class="copy-btn" onclick="copyJson('tipsJson', this)">copy</button>
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

<button id="backToTop" class="back-to-top hidden" type="button" aria-label="Back to top">↑</button>

<script>
// ── Toast notifications ───────────────────────────────────────────────────────
function showToast(message) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 2200);
}

// ── JSON viewer toggle ─────────────────────────────────────────────────────
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

// ── Copy raw JSON to clipboard ───────────────────────────────────────────────
function copyJson(id, btn) {
    const el = document.getElementById(id);
    const text = el.innerText;
    navigator.clipboard.writeText(text).then(() => {
        const original = btn.textContent;
        btn.textContent = 'copied ✓';
        showToast('JSON copied to clipboard');
        setTimeout(() => { btn.textContent = original; }, 1500);
    });
}

// ── Animate the dev score bar in on load ─────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    const fill = document.querySelector('.score-bar-fill');
    if (fill) {
        const target = fill.dataset.width || 0;
        requestAnimationFrame(() => {
            fill.style.transition = 'width 900ms cubic-bezier(.22,1,.36,1)';
            fill.style.width = target + '%';
        });
    }

    // Character counter for the name input
    const nameInput = document.getElementById('nameInput');
    const charCount = document.getElementById('charCount');
    if (nameInput && charCount) {
        const update = () => { charCount.textContent = nameInput.value.length; };
        nameInput.addEventListener('input', update);
        update();
    }

    // Loading state on greet form submit
    const greetForm = document.getElementById('greetForm');
    const greetSubmit = document.getElementById('greetSubmit');
    if (greetForm && greetSubmit) {
        greetForm.addEventListener('submit', () => {
            greetSubmit.disabled = true;
            greetSubmit.querySelector('.btn-label').classList.add('hidden');
            greetSubmit.querySelector('.btn-spinner').classList.remove('hidden');
        });
    }

    // Live filter for the tips list
    const tipFilter = document.getElementById('tipFilter');
    const tipsList  = document.getElementById('tipsList');
    const noResults = document.getElementById('noTipResults');
    const pills     = document.querySelectorAll('#categoryPills .pill');
    let activeCategory = '';

    function applyTipFilters() {
        if (!tipsList) return;
        const q = (tipFilter ? tipFilter.value.trim().toLowerCase() : '');
        let visibleCount = 0;
        tipsList.querySelectorAll('.tip-item').forEach(item => {
            const matchesText = item.dataset.search.includes(q);
            const matchesCat  = !activeCategory || item.dataset.category === activeCategory;
            const visible = matchesText && matchesCat;
            item.classList.toggle('hidden', !visible);
            if (visible) visibleCount++;
        });
        if (noResults) noResults.classList.toggle('hidden', visibleCount !== 0);
    }

    if (tipFilter) {
        tipFilter.addEventListener('input', applyTipFilters);
    }

    if (pills.length) {
        pills.forEach(pill => {
            pill.addEventListener('click', () => {
                pills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                activeCategory = pill.dataset.cat || '';
                applyTipFilters();
            });
        });
    }

    // Sort control for the tips list
    const tipSort = document.getElementById('tipSort');
    if (tipSort && tipsList) {
        tipSort.addEventListener('change', () => {
            const items = Array.from(tipsList.querySelectorAll('.tip-item'));
            const mode = tipSort.value;
            items.sort((a, b) => {
                if (mode === 'category') {
                    return a.dataset.category.localeCompare(b.dataset.category);
                }
                if (mode === 'length') {
                    return Number(a.dataset.len) - Number(b.dataset.len);
                }
                return Number(a.dataset.id) - Number(b.dataset.id);
            });
            items.forEach(item => tipsList.appendChild(item));
        });
    }

    // Keyboard shortcut: press "/" to focus the tip filter
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement !== tipFilter && document.activeElement !== nameInput) {
            e.preventDefault();
            if (tipFilter) tipFilter.focus();
        }
    });

    // Toast on successful greet result present at load (i.e. after a POST)
    <?php if ($greetResult && !empty($greetResult['success'])): ?>
        showToast('Greeting received from the API 👋');
    <?php elseif ($greetError): ?>
        showToast('<?= addslashes($greetError) ?>');
    <?php endif; ?>

    // Back-to-top button
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        window.addEventListener('scroll', () => {
            backToTop.classList.toggle('hidden', window.scrollY < 400);
        });
        backToTop.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Theme toggle (persists for the tab session only — no localStorage assumptions broken)
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const isLight = document.body.classList.toggle('theme-light');
            themeToggle.querySelector('.theme-icon').textContent = isLight ? '☀️' : '🌙';
        });
    }
});
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