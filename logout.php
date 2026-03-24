<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ClearOrbit &mdash; Logged Out</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        *{box-sizing:border-box}
        body{background:linear-gradient(135deg,#F0EEFF 0%,#E8F0FE 40%,#F5F3FF 100%);font-family:'Inter',system-ui,-apple-system,sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem}

        .glass-card{background:rgba(255,255,255,.78);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.55);border-radius:1.25rem;box-shadow:0 8px 32px rgba(99,102,241,.08),0 1px 4px rgba(0,0,0,.04)}

        .gradient-text{background:linear-gradient(135deg,#6C5CE7,#4F46E5);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

        .logo-icon{width:3rem;height:3rem;border-radius:1rem;background:linear-gradient(135deg,#6C5CE7,#4F46E5);color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(108,92,231,.3)}

        .reason-box{padding:1rem 1.25rem;border-radius:.875rem;font-size:.8125rem;font-weight:500;display:flex;align-items:flex-start;gap:.75rem;line-height:1.5}
        .reason-warning{background:rgba(254,242,242,.9);border:1px solid rgba(252,165,165,.4);color:#991B1B}
        .reason-success{background:rgba(236,253,245,.9);border:1px solid rgba(167,243,208,.4);color:#065F46}
        .reason-info{background:rgba(239,246,255,.9);border:1px solid rgba(147,197,253,.4);color:#1E40AF}

        .btn-gradient{background:linear-gradient(135deg,#6C5CE7,#4F46E5);color:#fff;border:none;padding:.8125rem 1.5rem;border-radius:.875rem;font-size:.9375rem;font-weight:600;cursor:pointer;transition:all .3s cubic-bezier(.4,0,.2,1);box-shadow:0 4px 16px rgba(108,92,231,.3);width:100%;display:flex;align-items:center;justify-content:center;gap:.5rem;text-decoration:none}
        .btn-gradient:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(108,92,231,.4);filter:brightness(1.06)}

        @keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        .animate-in{animation:fadeInUp .6s ease forwards}
        .animate-in-d1{animation:fadeInUp .6s ease .1s forwards;opacity:0}
        .animate-in-d2{animation:fadeInUp .6s ease .2s forwards;opacity:0}
    </style>
</head>
<body>
    <div class="animate-in" style="text-align:center;margin-bottom:1.5rem">
        <div class="logo-icon" style="margin:0 auto 1rem">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <h1 class="gradient-text" style="font-size:1.75rem;font-weight:800;letter-spacing:-.025em">ClearOrbit</h1>
        <p style="color:#6B7280;font-size:.8125rem;margin-top:.25rem">Premium SaaS Access Platform</p>
    </div>

    <div class="glass-card animate-in-d1" style="width:100%;max-width:440px;padding:2rem">
        <div id="logoutContent" style="display:flex;flex-direction:column;gap:1.25rem;text-align:center">
            <div id="iconContainer" style="margin:0 auto">
                <div id="successIcon" style="width:4rem;height:4rem;border-radius:50%;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center">
                    <svg width="28" height="28" fill="none" stroke="#10B981" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div id="warningIcon" style="width:4rem;height:4rem;border-radius:50%;background:rgba(239,68,68,.1);display:none;align-items:center;justify-content:center">
                    <svg width="28" height="28" fill="none" stroke="#EF4444" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
            </div>

            <div>
                <h2 id="logoutTitle" style="font-size:1.25rem;font-weight:700;color:#111827;margin-bottom:.375rem">Logged Out Successfully</h2>
                <p id="logoutSubtitle" style="font-size:.8125rem;color:#6B7280">You have been safely logged out of your account.</p>
            </div>

            <div id="reasonBox" style="display:none"></div>

            <a href="index.html" class="btn-gradient" style="margin-top:.5rem">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                Sign In Again
            </a>
        </div>
    </div>

    <div class="animate-in-d2" style="margin-top:1.5rem;display:flex;align-items:center;gap:.5rem;color:#9CA3AF;font-size:.75rem">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <span>256-bit Encrypted &bull; Secure Session</span>
    </div>

<script>
const REASON_MAP = {
    'Another device login detected': {
        title: 'Logged Out — Another Device',
        subtitle: 'Your session was ended because another device signed into your account.',
        type: 'warning',
        icon: 'warning'
    },
    'Session expired due to inactivity': {
        title: 'Session Expired',
        subtitle: 'Your session expired due to inactivity. Please sign in again to continue.',
        type: 'info',
        icon: 'warning'
    },
    'Session terminated by admin': {
        title: 'Session Terminated',
        subtitle: 'An administrator has ended your session.',
        type: 'warning',
        icon: 'warning'
    },
    'Account disabled by admin': {
        title: 'Account Disabled',
        subtitle: 'Your account has been disabled by an administrator. Contact support for assistance.',
        type: 'warning',
        icon: 'warning'
    },
    'Password was reset': {
        title: 'Password Changed',
        subtitle: 'Your password was recently changed. Please sign in with your new password.',
        type: 'info',
        icon: 'warning'
    },
    'Logged out from another device': {
        title: 'Logged Out Remotely',
        subtitle: 'You were logged out from another device that you control.',
        type: 'info',
        icon: 'warning'
    },
    'Device lock reset by admin': {
        title: 'Device Reset',
        subtitle: 'Your device lock was reset by an administrator. Please sign in again.',
        type: 'info',
        icon: 'warning'
    },
    'Logged out successfully': {
        title: 'Logged Out Successfully',
        subtitle: 'You have been safely logged out of your account.',
        type: 'success',
        icon: 'success'
    }
};

function init() {
    const params = new URLSearchParams(window.location.search);
    let reason = params.get('reason') || sessionStorage.getItem('logout_reason') || '';
    sessionStorage.removeItem('logout_reason');

    if (!reason || reason === 'manual') {
        return;
    }

    const config = REASON_MAP[reason] || {
        title: 'Logged Out',
        subtitle: reason,
        type: 'warning',
        icon: 'warning'
    };

    document.getElementById('logoutTitle').textContent = config.title;
    document.getElementById('logoutSubtitle').textContent = config.subtitle;

    if (config.icon === 'warning') {
        document.getElementById('successIcon').style.display = 'none';
        document.getElementById('warningIcon').style.display = 'flex';
    }

    if (reason !== 'Logged out successfully') {
        const reasonBox = document.getElementById('reasonBox');
        reasonBox.style.display = 'block';
        const typeClass = config.type === 'warning' ? 'reason-warning' : config.type === 'info' ? 'reason-info' : 'reason-success';
        const iconSvg = config.type === 'warning'
            ? '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>'
            : '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        reasonBox.className = 'reason-box ' + typeClass;
        reasonBox.innerHTML = iconSvg + '<span>' + escHtml(reason) + '</span>';
    }
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

init();
</script>
</body>
</html>
