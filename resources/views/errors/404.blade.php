<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#080817">
    <title>404 | صفحه پیدا نشد</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            color-scheme: dark;
            --bg: #080817;
            --panel: rgba(17, 17, 39, .72);
            --line: rgba(255, 255, 255, .09);
            --cyan: #42e8ee;
            --muted: #a6a6bd;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            min-width: 320px; min-height: 100vh; min-height: 100dvh; overflow-x: hidden;
            font-family: Vazirmatn, sans-serif; color: #f8f8ff;
            background:
                radial-gradient(ellipse at 80% 0%, rgba(100, 70, 220, .22), transparent 36rem),
                radial-gradient(ellipse at 0% 60%, rgba(0, 210, 220, .11), transparent 34rem),
                var(--bg);
        }
        a, button { -webkit-tap-highlight-color: transparent; }

        .background { position: fixed; inset: 0; overflow: hidden; pointer-events: none; }
        .grid {
            position: absolute; inset: 0; opacity: .7;
            background-image: linear-gradient(rgba(255, 255, 255, .035) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, .035) 1px, transparent 1px);
            background-size: 44px 44px;
            -webkit-mask-image: linear-gradient(to bottom, black, transparent 85%);
            mask-image: linear-gradient(to bottom, black, transparent 85%);
        }
        .blob { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .2; animation: float 12s ease-in-out infinite; }
        .blob1 { width: 340px; height: 340px; background: #8b5cf6; top: -120px; right: -120px; }
        .blob2 { width: 300px; height: 300px; background: #42e8ee; left: -100px; bottom: 40px; animation-delay: 2s; }

        .container {
            position: relative; z-index: 2; min-height: 100vh; min-height: 100dvh;
            display: flex; justify-content: center; align-items: center;
            padding: calc(24px + env(safe-area-inset-top, 0px)) 16px calc(24px + env(safe-area-inset-bottom, 0px));
        }
        .glass-card {
            width: 100%; max-width: 680px; text-align: center; padding: 44px 36px;
            background: var(--panel); border: 1px solid var(--line); border-radius: 28px;
            backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 30px 90px rgba(0, 0, 0, .45), 0 0 60px rgba(88, 90, 220, .12);
        }

        .robot {
            width: 96px; height: 96px; margin: 0 auto; display: grid; place-items: center;
            border-radius: 28px; font-size: 48px;
            background: rgba(66, 232, 238, .09); border: 1px solid rgba(66, 232, 238, .3);
            box-shadow: 0 0 50px rgba(66, 232, 238, .18);
            animation: robot 3s infinite ease-in-out;
        }
        .error-code {
            font-size: clamp(5rem, 22vw, 7.5rem); font-weight: 900; line-height: 1.2; margin: 18px 0 6px;
            background: linear-gradient(100deg, #4ce8ec 0%, #8bbcff 48%, #c4a1ff 100%);
            color: transparent; -webkit-background-clip: text; background-clip: text;
        }
        h1 { font-size: clamp(1.5rem, 5vw, 2rem); font-weight: 900; line-height: 1.6; margin-bottom: 12px; }
        p { color: var(--muted); line-height: 2.1; font-size: 1rem; }

        .path-box {
            margin: 28px 0; padding: 16px; border-radius: 16px;
            background: rgba(0, 0, 0, .28); border: 1px solid var(--line);
        }
        .path-box span { display: block; color: #8b8ba7; font-size: .85rem; margin-bottom: 8px; }
        .path-box code { display: block; direction: ltr; color: var(--cyan); word-break: break-all; font-size: .95rem; }

        .actions { display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
        .btn {
            display: inline-flex; min-height: 52px; align-items: center; justify-content: center; gap: 10px;
            padding: 13px 24px; border-radius: 15px; font-weight: 800; text-decoration: none; text-align: center;
            transition: transform .2s, filter .2s, border-color .2s;
        }
        .btn:active { transform: scale(.98); }
        .primary { color: #06131b; background: linear-gradient(105deg, #52edf0, #72b9ff); box-shadow: 0 10px 35px rgba(45, 210, 230, .19); }
        .primary:hover { filter: brightness(1.08); }
        .secondary { color: #e8e8f5; border: 1px solid rgba(255, 255, 255, .16); background: rgba(255, 255, 255, .045); }
        .secondary:hover { border-color: rgba(66, 232, 238, .55); }

        .links { margin-top: 28px; display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 6px 14px; font-size: .9rem; }
        .links a { color: #c7c7d7; text-decoration: none; min-height: 40px; display: inline-flex; align-items: center; transition: color .2s; }
        .links a:hover { color: var(--cyan); }
        .links span { color: #4a4a66; }
        :focus-visible { outline: 3px solid rgba(66, 232, 238, .85); outline-offset: 3px; }

        @@keyframes robot { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        @@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-40px); } }

        @@media (max-width: 640px) {
            .glass-card { padding: 30px 20px; border-radius: 24px; }
            .robot { width: 80px; height: 80px; font-size: 40px; border-radius: 24px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; }
            .blob { width: 220px; height: 220px; }
        }
        @@media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
    </style>
</head>

<body>

    <div class="background" aria-hidden="true">
        <div class="grid"></div>
        <div class="blob blob1"></div>
        <div class="blob blob2"></div>
    </div>

    <div class="container">
        <main class="glass-card">

            <div class="robot" aria-hidden="true">🤖</div>

            <div class="error-code">404</div>

            <h1>این صفحه رو پیدا نکردیم!</h1>

            <p>
                شاید لینک اشتباه وارد شده، صفحه حذف شده یا جای دیگه‌ای منتقل شده.
            </p>

            <div class="path-box">
                <span>مسیری که خواستی</span>
                <code id="path"></code>
            </div>

            <div class="actions">
                <a href="/" class="btn primary">🏠 صفحه اصلی</a>
                <a href="javascript:history.back()" class="btn secondary">→ بازگشت</a>
                <a href="/create-bot" class="btn secondary">✈️ ساخت فروشگاه</a>
            </div>

            <div class="links">
                <a href="/">خانه</a>
                <span>•</span>
                <a href="/view">معرفی روبوشاپ</a>
                <span>•</span>
                <a href="/plans">پلن‌ها</a>
                <span>•</span>
                <a href="/contact">پشتیبانی</a>
            </div>

        </main>
    </div>

    <script>
        document.getElementById('path').innerText = window.location.pathname;
    </script>

</body>
</html>
