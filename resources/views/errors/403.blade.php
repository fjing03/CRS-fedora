<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-bg, #1c1e1f);
            color: var(--color-on-bg, #e2e4e6);
        }
        .light body, body { background: var(--color-bg); color: var(--color-on-bg); }
        .error-card {
            text-align: center;
            padding: 48px 40px;
            background: var(--color-surface, #2a2c2e);
            border: 1px solid var(--color-outline, rgba(159,168,179,0.2));
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            max-width: 400px;
            width: 90%;
        }
        .error-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            background: var(--color-error-container, rgba(230,148,144,0.2));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-icon svg { width: 32px; height: 32px; fill: var(--color-error, #E69490); }
        .error-code {
            font-size: 48px;
            font-weight: 700;
            color: var(--color-error, #E69490);
            margin-bottom: 8px;
        }
        .error-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .error-message {
            font-size: 14px;
            color: var(--color-on-surface-variant, #c4c9ce);
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .btn-back {
            display: inline-block;
            padding: 10px 24px;
            background: var(--color-primary, #8DB5E6);
            color: var(--color-on-primary, #0B294C);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn-back:hover { opacity: 0.85; }
        .light .error-card {
            background: var(--color-surface);
            border-color: var(--color-outline);
            box-shadow: 0 4px 16px rgba(0,0,0,0.09);
        }
    </style>
</head>
<body class="{{ request()->cookie('login_type') === 'staff' ? '' : 'light' }}">
    <div class="error-card">
        <div class="error-icon">
            <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM9 8V6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9z"/></svg>
        </div>
        <div class="error-code">403</div>
        <div class="error-title">Access Denied</div>
        <div class="error-message">You don't have permission to access this page.</div>
        <a href="/" class="btn-back">Go Back</a>
    </div>
</body>
</html>
