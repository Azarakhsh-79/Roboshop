<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 FORBIDDEN - Security Mainframe</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;600;700&family=Courier+Prime:wght@400;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'IBM Plex Sans Arabic', 'Courier Prime', monospace;
            background: #0a0a0f;
            color: #00ff41;
            overflow-x: hidden;
            position: relative;
            min-height: 100vh;
        }
        
        /* Animated Grid Background */
        .grid-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(0, 255, 65, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 65, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
            z-index: 0;
        }
        
        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        /* Scanline Effect */
        .scanline {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to bottom,
                transparent 50%,
                rgba(0, 255, 65, 0.02) 51%
            );
            background-size: 100% 4px;
            animation: scan 8s linear infinite;
            pointer-events: none;
            z-index: 999;
        }
        
        @keyframes scan {
            0% { background-position: 0 0; }
            100% { background-position: 0 100%; }
        }
        
        /* Glitch Effect */
        .glitch-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }
        
        .glitch {
            position: absolute;
            width: 100%;
            height: 2px;
            background: rgba(255, 0, 0, 0.5);
            animation: glitchAnimation 3s infinite;
        }
        
        @keyframes glitchAnimation {
            0%, 100% { 
                top: 20%; 
                opacity: 0;
            }
            10% { 
                top: 20%; 
                opacity: 1;
            }
            20% { 
                top: 60%; 
                opacity: 0;
            }
            30% { 
                top: 60%; 
                opacity: 1;
            }
            40% { 
                top: 80%; 
                opacity: 0;
            }
        }
        
        /* Terminal Container */
        .terminal-container {
            position: relative;
            z-index: 10;
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        /* Terminal Header */
        .terminal-header {
            background: rgba(20, 20, 30, 0.9);
            border: 2px solid #ff0040;
            border-bottom: none;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 0 30px rgba(255, 0, 64, 0.3);
        }
        
        .terminal-title {
            font-family: 'Courier Prime', monospace;
            color: #ff0040;
            font-size: 14px;
            font-weight: 700;
            text-shadow: 0 0 10px rgba(255, 0, 64, 0.8);
        }
        
        .terminal-buttons {
            display: flex;
            gap: 8px;
        }
        
        .terminal-btn {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-red { background: #ff5555; box-shadow: 0 0 10px rgba(255, 85, 85, 0.5); }
        .btn-yellow { background: #ffaa00; box-shadow: 0 0 10px rgba(255, 170, 0, 0.5); }
        .btn-green { background: #00ff41; box-shadow: 0 0 10px rgba(0, 255, 65, 0.5); }
        
        /* Terminal Body */
        .terminal-body {
            background: rgba(10, 10, 15, 0.95);
            border: 2px solid #ff0040;
            border-top: none;
            padding: 30px;
            min-height: 500px;
            box-shadow: 
                0 0 30px rgba(255, 0, 64, 0.3),
                inset 0 0 100px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
        }
        
        /* Command Prompt */
        .prompt {
            color: #00ff41;
            font-family: 'Courier Prime', monospace;
            margin-bottom: 20px;
            font-size: 16px;
            text-shadow: 0 0 5px rgba(0, 255, 65, 0.5);
        }
        
        .cursor {
            display: inline-block;
            width: 10px;
            height: 18px;
            background: #00ff41;
            animation: blink 1s infinite;
            margin-left: 2px;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }
        
        /* Error Container */
        .error-container {
            margin: 30px 0;
        }
        
        .error-code {
            font-size: 120px;
            font-weight: 700;
            color: #ff0040;
            text-align: center;
            margin: 20px 0;
            text-shadow: 
                0 0 20px rgba(255, 0, 64, 0.8),
                0 0 40px rgba(255, 0, 64, 0.5);
            animation: errorPulse 2s ease-in-out infinite;
            letter-spacing: 10px;
        }
        
        @keyframes errorPulse {
            0%, 100% { 
                transform: scale(1);
                filter: brightness(1);
            }
            50% { 
                transform: scale(1.05);
                filter: brightness(1.3);
            }
        }
        
        /* System Messages */
        .system-message {
            font-family: 'Courier Prime', monospace;
            font-size: 14px;
            line-height: 1.8;
            margin: 10px 0;
            opacity: 0;
            animation: fadeIn 0.5s forwards;
        }
        
        .system-message:nth-child(1) { animation-delay: 0.5s; }
        .system-message:nth-child(2) { animation-delay: 1s; }
        .system-message:nth-child(3) { animation-delay: 1.5s; }
        .system-message:nth-child(4) { animation-delay: 2s; }
        .system-message:nth-child(5) { animation-delay: 2.5s; }
        .system-message:nth-child(6) { animation-delay: 3s; }
        
        @keyframes fadeIn {
            to { opacity: 1; }
        }
        
        .chevron {
            color: #00ff41;
            margin-left: 10px;
        }
        
        .text-warning {
            color: #ffaa00;
        }
        
        .text-error {
            color: #ff0040;
            font-weight: 700;
            text-shadow: 0 0 10px rgba(255, 0, 64, 0.5);
        }
        
        .text-success {
            color: #00ff41;
        }
        
        /* Persian Error Message */
        .persian-error {
            text-align: center;
            font-size: 28px;
            color: #ff0040;
            margin: 30px 0;
            padding: 20px;
            border: 2px solid rgba(255, 0, 64, 0.3);
            background: rgba(255, 0, 64, 0.05);
            text-shadow: 0 0 15px rgba(255, 0, 64, 0.6);
            animation: warningFlash 2s infinite;
        }
        
        @keyframes warningFlash {
            0%, 100% { 
                border-color: rgba(255, 0, 64, 0.3);
                background: rgba(255, 0, 64, 0.05);
            }
            50% { 
                border-color: rgba(255, 0, 64, 0.8);
                background: rgba(255, 0, 64, 0.15);
            }
        }
        
        /* Warning Box */
        .warning-box {
            background: rgba(255, 0, 64, 0.1);
            border: 2px solid #ff0040;
            border-right: 5px solid #ff0040;
            padding: 20px;
            margin: 30px 0;
            position: relative;
            overflow: hidden;
        }
        
        .warning-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 0, 64, 0.1), transparent);
            animation: warningSlide 2s infinite;
        }
        
        @keyframes warningSlide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .warning-title {
            color: #ff0040;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        /* IP Logger */
        .ip-logger {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #00ff41;
            padding: 15px;
            margin: 20px 0;
            font-family: 'Courier Prime', monospace;
            position: relative;
        }
        
        .ip-line {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            opacity: 0;
            animation: fadeIn 0.3s forwards;
        }
        
        .ip-line:nth-child(1) { animation-delay: 3.5s; }
        .ip-line:nth-child(2) { animation-delay: 3.7s; }
        .ip-line:nth-child(3) { animation-delay: 3.9s; }
        .ip-line:nth-child(4) { animation-delay: 4.1s; }
        
        /* Return Button */
        .return-btn {
            display: inline-block;
            margin-top: 30px;
            padding: 15px 40px;
            background: linear-gradient(135deg, #00ff41, #00cc33);
            color: #0a0a0f;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            border: 2px solid #00ff41;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 0 20px rgba(0, 255, 65, 0.3);
        }
        
        .return-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .return-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .return-btn:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 0 30px rgba(0, 255, 65, 0.6),
                0 10px 30px rgba(0, 0, 0, 0.5);
        }
        
        .return-btn span {
            position: relative;
            z-index: 1;
        }
        
        /* Loading Bar */
        .loading-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 0, 64, 0.2);
            margin: 20px 0;
            position: relative;
            overflow: hidden;
        }
        
        .loading-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff0040, #ff4080);
            width: 0;
            animation: loading 3s ease-in-out forwards;
            box-shadow: 0 0 10px rgba(255, 0, 64, 0.8);
        }
        
        @keyframes loading {
            0% { width: 0; }
            100% { width: 100%; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .error-code {
                font-size: 80px;
            }
            
            .persian-error {
                font-size: 20px;
            }
            
            .system-message {
                font-size: 12px;
            }
            
            .terminal-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Grid Background -->
    <div class="grid-background"></div>
    
    <!-- Scanline Effect -->
    <div class="scanline"></div>
    
    <!-- Glitch Effects -->
    <div class="glitch-container">
        <div class="glitch" style="animation-delay: 0s;"></div>
        <div class="glitch" style="animation-delay: 1s;"></div>
        <div class="glitch" style="animation-delay: 2s;"></div>
    </div>
    
    <!-- Terminal Container -->
    <div class="terminal-container">
        <!-- Terminal Header -->
        <div class="terminal-header">
            <div class="terminal-title">[ root@security_mainframe ~ ]# ACCESS_CONTROL_SYSTEM</div>
            <div class="terminal-buttons">
                <div class="terminal-btn btn-red"></div>
                <div class="terminal-btn btn-yellow"></div>
                <div class="terminal-btn btn-green"></div>
            </div>
        </div>
        
        <!-- Terminal Body -->
        <div class="terminal-body">
            <div class="prompt">root@security_mainframe:~# authenticate_user<span class="cursor"></span></div>
            
            <!-- Error Code -->
            <div class="error-code">403</div>
            
            <div class="loading-bar">
                <div class="loading-fill"></div>
            </div>
            
            <!-- Persian Error -->
            <div class="persian-error">
                ⚠ خطای سیستمی: دسترسی شما به این منبع غیرمجاز است ⚠
            </div>
            
            <!-- System Messages -->
            <div class="error-container">
                <div class="system-message">
                    <span class="chevron">></span> <span class="text-success">Initiating request protocol...</span> <span class="text-warning">v4.2.9</span>
                </div>
                <div class="system-message">
                    <span class="chevron">></span> <span class="text-success">Verifying user credentials and clearance level...</span>
                </div>
                <div class="system-message">
                    <span class="chevron">></span> <span class="text-warning">Scanning authorization tokens...</span>
                </div>
                <div class="system-message">
                    <span class="chevron">></span> <span class="text-error">CRITICAL ERROR: PERMISSION DENIED. FIREWALL TRIGGERED.</span>
                </div>
                <div class="system-message">
                    <span class="chevron">></span> <span class="text-error">System status: Locked. Your IP has been logged.</span>
                </div>
            </div>
            
            <!-- Warning Box -->
            <div class="warning-box">
                <div class="warning-title">⚡ SECURITY ALERT ⚡</div>
                <div style="position: relative; z-index: 1;">
                    Unauthorized access attempt detected. This incident has been recorded and reported to the system administrator. All activity from your network address is now under surveillance.
                </div>
            </div>
            
            <!-- IP Logger -->
            <div class="ip-logger">
                <div class="ip-line">
                    <span style="color: #ffaa00;">► IP ADDRESS:</span>
                    <span id="ip-address" style="color: #ff0040;">***.***.***.***</span>
                </div>
                <div class="ip-line">
                    <span style="color: #ffaa00;">► TIMESTAMP:</span>
                    <span id="timestamp" style="color: #00ff41;">--</span>
                </div>
                <div class="ip-line">
                    <span style="color: #ffaa00;">► LOCATION:</span>
                    <span style="color: #00ff41;">TRACKING...</span>
                </div>
                <div class="ip-line">
                    <span style="color: #ffaa00;">► STATUS:</span>
                    <span style="color: #ff0040;">BLOCKED & LOGGED</span>
                </div>
            </div>
            
            <!-- Return Button -->
            <div style="text-align: center; margin-top: 40px;">
                <a href="/" class="return-btn">
                    <span>◄ بازگشت به پایگاه (صفحه اصلی)</span>
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Simulate IP and timestamp
        document.addEventListener('DOMContentLoaded', function() {
            // Generate fake IP
            setTimeout(() => {
                const ip = Array(4).fill(0).map(() => Math.floor(Math.random() * 256)).join('.');
                document.getElementById('ip-address').textContent = ip;
            }, 4200);
            
            // Show timestamp
            setTimeout(() => {
                const now = new Date();
                const timestamp = now.toISOString().replace('T', ' ').split('.')[0] + ' UTC';
                document.getElementById('timestamp').textContent = timestamp;
            }, 4400);
            
            // Add random glitch effects
            setInterval(() => {
                if (Math.random() > 0.95) {
                    document.body.style.transform = `translate(${Math.random() * 4 - 2}px, ${Math.random() * 4 - 2}px)`;
                    setTimeout(() => {
                        document.body.style.transform = 'translate(0, 0)';
                    }, 50);
                }
            }, 100);
        });
    </script>
</body>
</html>