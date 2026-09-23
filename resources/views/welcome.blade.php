<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'StockPilot') }}</title>
    <style>
        :root {
            --bg: #0f1419;
            --panel: #1a222c;
            --text: #e7ecf1;
            --muted: #8b9aab;
            --accent: #3d9cf0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", system-ui, sans-serif;
            background:
                radial-gradient(ellipse at top, #1a2a3a 0%, var(--bg) 55%),
                var(--bg);
            color: var(--text);
        }
        main {
            width: min(36rem, calc(100% - 2rem));
            padding: 2rem 2.25rem;
            background: color-mix(in srgb, var(--panel) 92%, transparent);
            border: 1px solid #2a3542;
            border-radius: 12px;
        }
        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.75rem;
            letter-spacing: -0.02em;
        }
        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }
        .tag {
            display: inline-block;
            margin-top: 1.25rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--accent);
            border: 1px solid color-mix(in srgb, var(--accent) 45%, transparent);
            background: color-mix(in srgb, var(--accent) 12%, transparent);
        }
    </style>
</head>
<body>
    <main>
        <h1>{{ config('app.name', 'StockPilot') }}</h1>
        <p>Wholesale warehouse &amp; distribution operations. Laravel + Docker skeleton is up.</p>
        <span class="tag">Phase 0 · running</span>
    </main>
</body>
</html>
