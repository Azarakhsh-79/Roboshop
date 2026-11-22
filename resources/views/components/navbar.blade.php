<nav class="navbar" id="navbar" dir="rtl" aria-label="ناوبری اصلی">
    <div class="nav-container">

        {{-- Logo --}}
        <a href="/" class="nav-logo">
            <div class="logo-icon">
                <i class="fa-solid fa-cube"></i>
            </div>
            <span class="logo-text">RoboShop</span>
        </a>

        {{-- Desktop Links --}}
        <ul class="nav-links" id="navLinks">
            <li><a href="/" class="nav-link active">خانه</a></li>
            <li><a href="/#features" class="nav-link">ویژگی‌ها</a></li>
            <li><a href="/#blog" class="nav-link">آموزش‌ها</a></li>
            <li><a href="/#pricing" class="nav-link">تعرفه‌ها</a></li>
        </ul>

        {{-- Desktop Actions --}}
        <div class="nav-actions">
            <button type="button" class="theme-toggle" onclick="toggleTheme()" title="تغییر پوسته" aria-label="تغییر پوسته">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
            </button>

            @auth
                @php($hasBot = auth()->user()->shops()->exists())
                <div class="user-dropdown" id="userDropdown">
                    <button type="button" class="user-btn" onclick="toggleDropdown()" aria-expanded="false" aria-haspopup="true" aria-controls="dropdownPanel">
                        <div class="user-avatar">
                            {{ mb_substr(auth()->user()->first_name ?? 'ک', 0, 1) }}
                        </div>
                        <span class="user-name-text">{{ auth()->user()->first_name ?? 'حساب' }}</span>
                        <i class="fa-solid fa-chevron-down chevron-icon"></i>
                    </button>

                    <div class="dropdown-panel" id="dropdownPanel" role="menu">
                        <div class="dropdown-user-info">
                            <div class="dropdown-avatar">
                                {{ mb_substr(auth()->user()->first_name ?? 'ک', 0, 1) }}
                            </div>
                            <div>
                                <div class="dropdown-name">{{ auth()->user()->first_name ?? 'کاربر عزیز' }}</div>
                                <div class="dropdown-phone">{{ auth()->user()->phone }}</div>
                            </div>
                        </div>

                        <div class="dropdown-divider"></div>

                        <a href="{{ $hasBot ? route('panel.gateway') : route('bot.create') }}"
                            class="dropdown-action-btn" role="menuitem">
                            <i class="fa-solid {{ $hasBot ? 'fa-gauge' : 'fa-wand-magic-sparkles' }}"></i>
                            {{ $hasBot ? 'ورود به پنل مدیریت' : 'ساخت ربات جدید' }}
                        </a>

                        <a href="/admin/profile" class="dropdown-item" role="menuitem">
                            <i class="fa-solid fa-id-card"></i>
                            <span>پروفایل من</span>
                        </a>

                        <div class="dropdown-divider"></div>

                        <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit" class="dropdown-item danger" role="menuitem">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                <span>خروج از حساب</span>
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn-nav-outline">ورود</a>
                <a href="{{ route('bot.create') }}" class="btn-nav-primary">
                    <i class="fa-solid fa-rocket"></i>
                    ساخت ربات
                </a>
            @endauth
        </div>

        {{-- Hamburger --}}
        <button type="button" class="hamburger" id="hamburger" onclick="toggleMobileMenu()" aria-label="منو" aria-expanded="false" aria-controls="mobileMenu">
            <span class="ham-line"></span>
            <span class="ham-line"></span>
            <span class="ham-line"></span>
        </button>
    </div>

    {{-- Mobile Menu --}}
    <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
        <div class="mobile-menu-inner">
            <ul class="mobile-nav-links">
                <li><a href="/" class="mobile-nav-link">خانه</a></li>
                <li><a href="/#features" class="mobile-nav-link">ویژگی‌ها</a></li>
                <li><a href="/#blog" class="mobile-nav-link">آموزش‌ها</a></li>
                <li><a href="/#pricing" class="mobile-nav-link">تعرفه‌ها</a></li>
            </ul>

            <div class="mobile-divider"></div>

            @auth
                @php($hasBot = auth()->user()->shops()->exists())
                <div class="mobile-user-info">
                    <div class="mobile-user-avatar">
                        {{ mb_substr(auth()->user()->first_name ?? 'ک', 0, 1) }}
                    </div>
                    <div>
                        <div class="mobile-user-name">{{ auth()->user()->first_name ?? 'کاربر عزیز' }}</div>
                        <div class="mobile-user-phone">{{ auth()->user()->phone }}</div>
                    </div>
                </div>
                <a href="{{ $hasBot ? route('panel.gateway') : route('bot.create') }}" class="mobile-btn-primary">
                    <i class="fa-solid {{ $hasBot ? 'fa-gauge' : 'fa-wand-magic-sparkles' }}"></i>
                    {{ $hasBot ? 'ورود به پنل' : 'ساخت ربات' }}
                </a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="mobile-btn-outline">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        خروج از حساب
                    </button>
                </form>
            @else
                <a href="{{ route('bot.create') }}" class="mobile-btn-primary">
                    <i class="fa-solid fa-rocket"></i>
                    ساخت ربات رایگان
                </a>
                <a href="{{ route('login') }}" class="mobile-btn-outline">ورود / ثبت‌نام</a>
            @endauth
        </div>
    </div>
</nav>

{{-- Backdrop --}}
<div class="mobile-backdrop" id="mobileBackdrop" onclick="toggleMobileMenu()"></div>

<style>
/* ── Reset & Base ── */
*, *::before, *::after { box-sizing: border-box; }

/* ── Navbar ── */
.navbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1000;
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(0,0,0,0.06);
    transition: background 0.3s ease, box-shadow 0.3s ease;
}

.navbar.scrolled {
    background: rgba(255,255,255,0.96);
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
}

[data-theme="dark"] .navbar {
    background: rgba(15,15,25,0.85);
    border-bottom-color: rgba(255,255,255,0.06);
}
[data-theme="dark"] .navbar.scrolled {
    background: rgba(15,15,25,0.97);
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
    height: 68px;
    display: flex;
    align-items: center;
    gap: 32px;
}

/* ── Logo ── */
.nav-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    flex-shrink: 0;
}

.logo-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--primary, #6366f1), var(--primary-dark, #4f46e5));
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 15px;
    box-shadow: 0 4px 12px rgba(99,102,241,0.35);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.nav-logo:hover .logo-icon {
    transform: rotate(-8deg) scale(1.08);
    box-shadow: 0 6px 20px rgba(99,102,241,0.45);
}

.logo-text {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-primary, #111827);
    letter-spacing: -0.02em;
    transition: color 0.2s;
}

/* ── Desktop Links ── */
.nav-links {
    list-style: none;
    margin: 0; padding: 0;
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
    justify-content: center;
}

.nav-link {
    display: block;
    padding: 7px 14px;
    font-size: 0.92rem;
    font-weight: 500;
    color: var(--text-secondary, #6b7280);
    text-decoration: none;
    border-radius: 8px;
    transition: color 0.2s ease, background 0.2s ease;
    position: relative;
}
.nav-link::after {
    content: '';
    position: absolute;
    bottom: 4px; left: 50%; right: 50%;
    height: 2px;
    background: var(--primary, #6366f1);
    border-radius: 2px;
    transition: left 0.25s ease, right 0.25s ease;
}
.nav-link:hover { color: var(--primary, #6366f1); background: rgba(99,102,241,0.07); }
.nav-link:hover::after, .nav-link.active::after { left: 14px; right: 14px; }
.nav-link.active { color: var(--primary, #6366f1); font-weight: 600; }
[data-theme="dark"] .nav-link { color: #94a3b8; }
[data-theme="dark"] .nav-link:hover { color: #a5b4fc; background: rgba(165,180,252,0.08); }
[data-theme="dark"] .nav-link.active { color: #a5b4fc; }

/* ── Desktop Actions ── */
.nav-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.theme-toggle {
    width: 38px; height: 38px;
    border: 1.5px solid rgba(0,0,0,0.1);
    border-radius: 10px;
    background: transparent;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary, #6b7280);
    font-size: 15px;
    transition: all 0.2s ease;
}
.theme-toggle:hover {
    background: rgba(99,102,241,0.08);
    border-color: var(--primary, #6366f1);
    color: var(--primary, #6366f1);
    transform: rotate(20deg);
}
[data-theme="dark"] .theme-toggle { border-color: rgba(255,255,255,0.1); color: #94a3b8; }
[data-theme="dark"] .theme-toggle:hover { border-color: #a5b4fc; color: #a5b4fc; background: rgba(165,180,252,0.08); }

/* ── Nav Buttons ── */
.btn-nav-primary {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 18px;
    background: linear-gradient(135deg, var(--primary, #6366f1), var(--primary-dark, #4f46e5));
    color: #fff;
    border-radius: 10px;
    font-size: 0.88rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.25s ease;
    box-shadow: 0 3px 10px rgba(99,102,241,0.3);
    white-space: nowrap;
}
.btn-nav-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(99,102,241,0.4);
}

.btn-nav-outline {
    padding: 8px 16px;
    border: 1.5px solid rgba(0,0,0,0.12);
    border-radius: 10px;
    color: var(--text-secondary, #6b7280);
    font-size: 0.88rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.btn-nav-outline:hover {
    border-color: var(--primary, #6366f1);
    color: var(--primary, #6366f1);
    background: rgba(99,102,241,0.05);
}
[data-theme="dark"] .btn-nav-outline { border-color: rgba(255,255,255,0.12); color: #94a3b8; }
[data-theme="dark"] .btn-nav-outline:hover { border-color: #a5b4fc; color: #a5b4fc; }

/* ── User Dropdown ── */
.user-dropdown { position: relative; }

.user-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 14px 6px 10px;
    background: rgba(99,102,241,0.06);
    border: 1.5px solid rgba(99,102,241,0.15);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--text-primary, #111827);
}
.user-btn:hover { background: rgba(99,102,241,0.1); border-color: rgba(99,102,241,0.3); }
[data-theme="dark"] .user-btn { background: rgba(165,180,252,0.06); border-color: rgba(165,180,252,0.12); color: #f1f5f9; }

.user-avatar {
    width: 30px; height: 30px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
}

.user-name-text { font-size: 0.88rem; font-weight: 600; }

.chevron-icon {
    font-size: 10px;
    color: var(--text-secondary, #9ca3af);
    transition: transform 0.25s ease;
}
.user-dropdown.open .chevron-icon { transform: rotate(180deg); }

/* ── Dropdown Panel ── */
.dropdown-panel {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    width: 240px;
    background: var(--bg-elevated, #fff);
    border: 1px solid var(--border, rgba(0,0,0,0.08));
    border-radius: 16px;
    box-shadow: var(--shadow-lg, 0 16px 48px rgba(0,0,0,0.12));
    padding: 8px;
    opacity: 0;
    transform: translateY(-8px) scale(0.97);
    transform-origin: top left;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
    z-index: 100;
}
.user-dropdown.open .dropdown-panel {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: all;
}

.dropdown-user-info {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 8px;
}
.dropdown-avatar {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    flex-shrink: 0;
}
.dropdown-name { font-size: 0.9rem; font-weight: 700; color: var(--text-primary, #111827); }
.dropdown-phone { font-size: 0.78rem; color: var(--text-faint, #9ca3af); direction: ltr; margin-top: 2px; }

.dropdown-divider { height: 1px; background: var(--border, rgba(0,0,0,0.06)); margin: 4px 0; }

.dropdown-action-btn {
    display: flex; align-items: center; gap: 8px;
    width: 100%;
    padding: 10px 12px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.86rem;
    font-weight: 600;
    margin: 4px 0;
    transition: all 0.2s ease;
}
.dropdown-action-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(99,102,241,0.35); }

.dropdown-item {
    display: flex; align-items: center; gap: 9px;
    width: 100%;
    padding: 9px 12px;
    border-radius: 8px;
    font-size: 0.86rem;
    font-weight: 500;
    color: var(--text-secondary, #4b5563);
    text-decoration: none;
    background: none;
    border: none;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
    text-align: right;
}
.dropdown-item:hover { background: rgba(99,102,241,0.06); color: var(--primary, #6366f1); }
.dropdown-item.danger { color: #ef4444; }
.dropdown-item.danger:hover { background: rgba(239,68,68,0.06); color: #ef4444; }
[data-theme="dark"] .dropdown-item:hover { background: rgba(165,180,252,0.08); color: #a5b4fc; }
[data-theme="dark"] .dropdown-item.danger:hover { background: rgba(239,68,68,0.08); }

/* ── Hamburger ── */
.hamburger {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 40px; height: 40px;
    background: none;
    border: 1.5px solid rgba(0,0,0,0.1);
    border-radius: 10px;
    cursor: pointer;
    padding: 9px;
    transition: all 0.2s ease;
    margin-right: auto;
}
.hamburger:hover { border-color: var(--primary, #6366f1); background: rgba(99,102,241,0.05); }
[data-theme="dark"] .hamburger { border-color: rgba(255,255,255,0.1); }

.ham-line {
    display: block;
    width: 100%; height: 1.5px;
    background: var(--text-secondary, #6b7280);
    border-radius: 2px;
    transition: transform 0.3s ease, opacity 0.3s ease;
    transform-origin: center;
}

.hamburger.open .ham-line:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
.hamburger.open .ham-line:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.open .ham-line:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

/* ── Mobile Menu ── */
.mobile-menu {
    position: fixed;
    top: 68px; right: 0;
    width: min(320px, 90vw);
    height: calc(100dvh - 68px);
    background: var(--bg-elevated, #fff);
    border-left: 1px solid var(--border, rgba(0,0,0,0.07));
    overflow-y: auto;
    transform: translateX(100%);
    transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
    z-index: 999;
    will-change: transform;
}
.mobile-menu.open { transform: translateX(0); }

.mobile-menu-inner { padding: 24px 20px 40px; }

.mobile-nav-links {
    list-style: none;
    margin: 0; padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.mobile-nav-link {
    display: flex; align-items: center;
    padding: 12px 16px;
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-primary, #111827);
    text-decoration: none;
    border-radius: 12px;
    transition: background 0.2s, color 0.2s;
}
.mobile-nav-link:hover {
    background: rgba(99,102,241,0.07);
    color: var(--primary, #6366f1);
    padding-right: 22px;
}
[data-theme="dark"] .mobile-nav-link:hover { background: rgba(165,180,252,0.08); color: #a5b4fc; }

.mobile-divider { height: 1px; background: var(--border, rgba(0,0,0,0.06)); margin: 16px 0; }

.mobile-user-info {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px;
    background: rgba(99,102,241,0.05);
    border-radius: 14px;
    margin-bottom: 14px;
}
.mobile-user-avatar {
    width: 42px; height: 42px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 17px;
    font-weight: 700;
    flex-shrink: 0;
}
.mobile-user-name { font-size: 0.95rem; font-weight: 700; color: var(--text-primary, #111827); }
.mobile-user-phone { font-size: 0.8rem; color: var(--text-faint, #9ca3af); direction: ltr; margin-top: 2px; }

.mobile-btn-primary {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(99,102,241,0.3);
    margin-bottom: 10px;
}
.mobile-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }

.mobile-btn-outline {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%;
    padding: 12px;
    background: none;
    border: 1.5px solid var(--border, rgba(0,0,0,0.1));
    border-radius: 12px;
    color: var(--text-secondary, #6b7280);
    font-size: 0.93rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}
.mobile-btn-outline:hover { border-color: #ef4444; color: #ef4444; background: rgba(239,68,68,0.04); }

/* ── Backdrop ── */
.mobile-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px);
    z-index: 998;
    opacity: 0;
    transition: opacity 0.35s ease;
}
.mobile-backdrop.open { opacity: 1; }

/* ── Keyboard focus ── */
.nav-link:focus-visible,
.mobile-nav-link:focus-visible,
.dropdown-item:focus-visible,
.dropdown-action-btn:focus-visible,
.theme-toggle:focus-visible,
.hamburger:focus-visible,
.user-btn:focus-visible {
    outline: 2.5px solid var(--primary, #6366f1);
    outline-offset: 2px;
}

/* ── Stagger animation for mobile links ── */
@keyframes slideInRight {
    from { opacity: 0; transform: translateX(20px); }
    to   { opacity: 1; transform: translateX(0); }
}
.mobile-menu.open .mobile-nav-link {
    animation: slideInRight 0.3s ease both;
}
.mobile-menu.open .mobile-nav-links li:nth-child(1) .mobile-nav-link { animation-delay: 0.05s; }
.mobile-menu.open .mobile-nav-links li:nth-child(2) .mobile-nav-link { animation-delay: 0.10s; }
.mobile-menu.open .mobile-nav-links li:nth-child(3) .mobile-nav-link { animation-delay: 0.15s; }
.mobile-menu.open .mobile-nav-links li:nth-child(4) .mobile-nav-link { animation-delay: 0.20s; }

@media (prefers-reduced-motion: reduce) {
    .mobile-menu, .ham-line, .mobile-menu.open .mobile-nav-link, .mobile-backdrop, .dropdown-panel {
        animation: none !important;
        transition: none !important;
    }
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .nav-links, .nav-actions { display: none !important; }
    .hamburger { display: flex; }
    .nav-container { gap: 12px; }
    .mobile-backdrop { display: block; }
}
</style>

<script>
// ── Scroll effect ──
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 20);
}, { passive: true });

// ── Mobile Menu ──
function toggleMobileMenu() {
    const menu     = document.getElementById('mobileMenu');
    const backdrop = document.getElementById('mobileBackdrop');
    const ham      = document.getElementById('hamburger');
    const isOpen   = menu.classList.toggle('open');

    backdrop.classList.toggle('open', isOpen);
    ham.classList.toggle('open', isOpen);
    ham.setAttribute('aria-expanded', isOpen);
    menu.setAttribute('aria-hidden', !isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}

// ── User Dropdown ──
function toggleDropdown() {
    const dd = document.getElementById('userDropdown');
    const btn = dd?.querySelector('.user-btn');
    const isOpen = dd?.classList.toggle('open');
    btn?.setAttribute('aria-expanded', isOpen);
}

document.addEventListener('click', (e) => {
    const dd = document.getElementById('userDropdown');
    if (dd && !dd.contains(e.target)) {
        dd.classList.remove('open');
        dd.querySelector('.user-btn')?.setAttribute('aria-expanded', 'false');
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.getElementById('userDropdown')?.classList.remove('open');
        const menu = document.getElementById('mobileMenu');
        if (menu?.classList.contains('open')) toggleMobileMenu();
    }
});

// ── Active link ──
document.querySelectorAll('.nav-link, .mobile-nav-link').forEach(link => {
    if (link.href === window.location.href) link.classList.add('active');
});
</script>
