<div class="json-viewer-container" dir="ltr">
    <div class="json-viewer-header">
        <div class="json-info">
            <span class="json-badge">JSON Response</span>
            <span class="json-timestamp">{{ now()->format('Y-m-d H:i:s') }}</span>
        </div>
        <button 
            type="button" 
            class="copy-button"
            onclick="copyJsonToClipboard(this)"
            title="کپی کردن"
        >
            <svg class="copy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            <span class="copy-text">کپی</span>
        </button>
    </div>
    
    <pre class="json-content"><code class="json-code" id="jsonCode">{{ $getState() ?? '{}' }}</code></pre>
</div>

<style>
    .json-viewer-container {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(148, 163, 184, 0.1);
    }

    .json-viewer-header {
        background: rgba(30, 41, 59, 0.6);
        backdrop-filter: blur(10px);
        padding: 14px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    }

    .json-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .json-badge {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }

    .json-timestamp {
        font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
        font-size: 12px;
        color: #94a3b8;
    }

    .copy-button {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
        border: 1px solid rgba(59, 130, 246, 0.3);
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .copy-button:hover {
        background: rgba(59, 130, 246, 0.25);
        border-color: rgba(59, 130, 246, 0.5);
        transform: translateY(-1px);
    }

    .copy-button:active {
        transform: translateY(0);
    }

    .copy-button.copied {
        background: rgba(34, 197, 94, 0.15);
        border-color: rgba(34, 197, 94, 0.3);
        color: #4ade80;
    }

    .copy-icon {
        width: 16px;
        height: 16px;
    }

    .json-content {
        margin: 0;
        padding: 24px;
        background: transparent;
        max-height: 500px;
        overflow: auto;
        scrollbar-width: thin;
        scrollbar-color: #475569 #1e293b;
    }

    .json-content::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .json-content::-webkit-scrollbar-track {
        background: #1e293b;
        border-radius: 4px;
    }

    .json-content::-webkit-scrollbar-thumb {
        background: #475569;
        border-radius: 4px;
    }

    .json-content::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }

    .json-code {
        font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
        font-size: 13px;
        line-height: 1.7;
        color: #e2e8f0;
        display: block;
        white-space: pre;
        word-wrap: normal;
        overflow-wrap: normal;
    }

    /* JSON Syntax Highlighting */
    .json-code {
        counter-reset: line;
    }
</style>

<script>
    function copyJsonToClipboard(button) {
        const code = document.getElementById('jsonCode');
        const text = code.textContent;
        
        navigator.clipboard.writeText(text).then(() => {
            const originalText = button.querySelector('.copy-text').textContent;
            const copyText = button.querySelector('.copy-text');
            
            button.classList.add('copied');
            copyText.textContent = '✓ کپی شد';
            
            setTimeout(() => {
                button.classList.remove('copied');
                copyText.textContent = originalText;
            }, 2000);
        }).catch(err => {
            console.error('خطا در کپی کردن:', err);
        });
    }

    // Syntax highlighting for JSON
    document.addEventListener('DOMContentLoaded', function() {
        const codeElement = document.getElementById('jsonCode');
        if (codeElement) {
            let text = codeElement.textContent;
            
            // Highlight JSON syntax
            text = text
                .replace(/("(?:\\.|[^"\\])*")\s*:/g, '<span style="color: #7dd3fc;">$1</span>:')
                .replace(/:\s*("(?:\\.|[^"\\])*")/g, ': <span style="color: #86efac;">$1</span>')
                .replace(/:\s*(\d+)/g, ': <span style="color: #fbbf24;">$1</span>')
                .replace(/:\s*(true|false)/g, ': <span style="color: #fb923c;">$1</span>')
                .replace(/:\s*(null)/g, ': <span style="color: #f87171;">$1</span>')
                .replace(/([{}[\]])/g, '<span style="color: #c084fc; font-weight: bold;">$1</span>');
            
            codeElement.innerHTML = text;
        }
    });
</script>