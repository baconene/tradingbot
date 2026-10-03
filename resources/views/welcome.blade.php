<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1220">
    <title>{{ config('app.name', 'Astra') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
               font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
               color: #e5e7eb; background: #0b1220; }
        main { width: 100%; max-width: 600px; padding: clamp(28px, 7vw, 60px);
               border: 1px solid #26364c; border-radius: 20px; background: #111d30; }
        .eyebrow { color: #8ccdb6; font-size: 12px; font-weight: 700; letter-spacing: .18em; }
        h1 { font-size: clamp(32px, 7vw, 52px); letter-spacing: -.04em; margin: 20px 0 14px; }
        p { color: #b6c5d7; line-height: 1.7; }
        .status { display: inline-flex; align-items: center; gap: 10px; margin-top: 18px;
                  padding: 12px 16px; border: 1px solid #365c54; border-radius: 10px; color: #a9e3c7; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #8ccdb6; }
    </style>
</head>
<body>
    <main>
        <div class="eyebrow">ASTRA / CLEAN FOUNDATION</div>
        <h1>A fresh start.</h1>
        <p>The Laravel application has been reset. Previous trading, predictions, charts and execution code are not part of this build.</p>
        <div class="status"><span class="dot" aria-hidden="true"></span>Laravel application ready · Trading disabled</div>
    </main>
</body>
</html>
