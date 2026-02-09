<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1220">
    <title>{{ config('app.name') }} | Offline</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            display: grid;
            min-height: 100vh;
            place-items: center;
            padding: 24px;
            background: radial-gradient(1200px 700px at 20% 10%, rgba(59,130,246,0.18), transparent 55%),
                        radial-gradient(900px 600px at 85% 80%, rgba(34,197,94,0.14), transparent 55%),
                        #0b1220;
            color: rgba(255,255,255,0.92);
        }
        .card {
            width: min(520px, 100%);
            border-radius: 16px;
            padding: 20px 18px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(10px);
        }
        h1 { margin: 0 0 8px; font-size: 20px; letter-spacing: 0.2px; }
        p { margin: 0 0 14px; line-height: 1.35; color: rgba(255,255,255,0.78); }
        button {
            appearance: none;
            border: 0;
            border-radius: 12px;
            padding: 10px 12px;
            font-weight: 600;
            background: rgba(59,130,246,0.92);
            color: #fff;
        }
        button:active { transform: translateY(1px); }
    </style>
</head>
<body>
    <main class="card">
        <h1>You're offline</h1>
        <p>Claude Runner needs an internet connection for admin data. Reconnect, then try again.</p>
        <button type="button" onclick="location.reload()">Retry</button>
    </main>
</body>
</html>

