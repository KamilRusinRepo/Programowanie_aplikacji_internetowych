<?php

declare(strict_types=1);

$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'flashmind';
$dbUser = getenv('DB_USER') ?: 'flashmind';
$dbPassword = getenv('DB_PASSWORD') ?: 'flashmind';

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $dbName);
$dbStatus = 'Not connected';
$dbMessage = '';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query('SELECT 1');
    $stmt->fetch();

    $dbStatus = 'Connected';
    $dbMessage = 'PostgreSQL connection test passed.';
} catch (Throwable $e) {
    $dbStatus = 'Connection failed';
    $dbMessage = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlashMind - Setup Check</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #172033;
            --muted: #5f6d86;
            --ok: #1f9d55;
            --err: #c0392b;
            --border: #dce3ee;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, #e9effb 0%, var(--bg) 45%, #eef4fb 100%);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(680px, 100%);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 16px 40px rgba(22, 34, 55, 0.08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.8rem;
        }

        p {
            margin: 0;
            color: var(--muted);
        }

        .status {
            margin-top: 18px;
            display: inline-block;
            font-weight: 700;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid var(--border);
        }

        .status.ok {
            color: var(--ok);
            border-color: rgba(31, 157, 85, 0.35);
            background: rgba(31, 157, 85, 0.08);
        }

        .status.err {
            color: var(--err);
            border-color: rgba(192, 57, 43, 0.35);
            background: rgba(192, 57, 43, 0.08);
        }

        pre {
            margin-top: 16px;
            white-space: pre-wrap;
            background: #f2f6fd;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            font-size: 0.9rem;
            color: #344566;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>FlashMind setup check</h1>
    <p>PHP container is running. Status below verifies connection with PostgreSQL.</p>

    <div class="status <?= $dbStatus === 'Connected' ? 'ok' : 'err' ?>">
        Database status: <?= htmlspecialchars($dbStatus, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <?php if ($dbMessage !== ''): ?>
        <pre><?= htmlspecialchars($dbMessage, ENT_QUOTES, 'UTF-8') ?></pre>
    <?php endif; ?>
</div>
</body>
</html>
