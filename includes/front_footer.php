<footer>
    <div class="container">
        <div class="row gy-4">

            <div class="col-lg-4">
                <div class="footer-logo-text">SLGTI</div>
                <div class="footer-tagline">Sri Lanka German Training Institute<br>Kilinochchi, Sri Lanka</div>
                <div class="d-flex gap-2 mt-3">
                    <a href="#" class="social-link" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    <a href="#" class="social-link" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-link" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2 offset-lg-1">
                <div class="footer-col-title">Quick Links</div>
                <a href="#home" class="footer-link">Home</a>
                <a href="#courses" class="footer-link">Departments</a>
                <a href="#about" class="footer-link">About Us</a>
                <a href="#contact" class="footer-link">Contact</a>
            </div>

            <div class="col-6 col-lg-2">
                <div class="footer-col-title">Resources</div>
                <a href="#" class="footer-link">Student Handbook</a>
                <a href="#" class="footer-link">Academic Calendar</a>
                <a href="#" class="footer-link">NVQ Framework</a>
                <a href="#" class="footer-link">IT Support</a>
            </div>



        </div>

        <hr class="footer-divider">

        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <span class="footer-copy">
                &copy; <?php echo $current_year; ?> Sri Lanka German Training Institute. All rights reserved.
            </span>
            <div class="d-flex gap-3">
                <a href="#" class="footer-link" style="display:inline;">Privacy Policy</a>
                <a href="#" class="footer-link" style="display:inline;">Terms of Use</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="includes/js/front.js"></script>

<!-- ── Front Page AI Chatbox ── -->
<style>
    #pub-chatbox {
        position: fixed;
        bottom: 90px;
        right: 20px;
        width: 350px;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        font-family: 'DM Sans', 'Segoe UI', sans-serif;
        z-index: 9999;
        display: none;
        flex-direction: column;
        max-height: 520px;
        overflow: hidden;
    }

    #pub-chatbox.open {
        display: flex;
        animation: pubChatIn .25s ease;
    }

    @keyframes pubChatIn {
        from {
            opacity: 0;
            transform: translateY(16px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    #pub-chat-header {
        background: linear-gradient(135deg, #0a2d6e, #1456c8);
        color: #fff;
        padding: 14px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        font-size: 15px;
        border-radius: 14px 14px 0 0;
    }

    #pub-chat-header .pub-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    #pub-chat-header .pub-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
    }

    #pub-chat-close {
        border: none;
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #pub-chat-close:hover {
        background: rgba(255, 255, 255, 0.35);
    }

    #pub-chat-suggestions {
        padding: 10px 12px;
        border-bottom: 1px solid #e8edf5;
        background: #f8faff;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .pub-chip {
        background: #e8f0fe;
        color: #1456c8;
        padding: 5px 11px;
        border-radius: 14px;
        font-size: 12px;
        cursor: pointer;
        border: none;
        transition: background .2s;
    }

    .pub-chip:hover {
        background: #c7d9fc;
    }

    #pub-chat-messages {
        flex: 1;
        padding: 14px;
        overflow-y: auto;
        background: #f9fafb;
    }

    #pub-chat-messages::-webkit-scrollbar {
        width: 5px;
    }

    #pub-chat-messages::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 4px;
    }

    .pub-msg {
        margin-bottom: 10px;
    }

    .pub-msg .pub-bubble {
        padding: 10px 14px;
        border-radius: 16px;
        display: inline-block;
        max-width: 82%;
        font-size: 13.5px;
        line-height: 1.5;
    }

    .pub-msg.user {
        text-align: right;
    }

    .pub-msg.user .pub-bubble {
        background: #1456c8;
        color: #fff;
    }

    .pub-msg.ai .pub-bubble {
        background: #f0f4ff;
        color: #1e293b;
        border: 1px solid #dde5f5;
    }

    #pub-typing {
        display: none;
        padding: 8px 14px;
    }

    #pub-typing.active {
        display: block;
    }

    #pub-typing span {
        width: 7px;
        height: 7px;
        background: #1456c8;
        display: inline-block;
        border-radius: 50%;
        margin: 0 2px;
        animation: pubDot 1.3s infinite;
    }

    #pub-typing span:nth-child(2) {
        animation-delay: .2s;
    }

    #pub-typing span:nth-child(3) {
        animation-delay: .4s;
    }

    @keyframes pubDot {

        0%,
        60%,
        100% {
            transform: translateY(0);
        }

        30% {
            transform: translateY(-8px);
        }
    }

    #pub-chat-input-area {
        display: flex;
        padding: 10px;
        border-top: 1px solid #e5e7eb;
        background: #fff;
        border-radius: 0 0 14px 14px;
    }

    #pub-chat-input {
        flex: 1;
        border: 1.5px solid #dde5f5;
        border-radius: 20px;
        padding: 8px 14px;
        font-size: 13.5px;
        outline: none;
        font-family: inherit;
    }

    #pub-chat-input:focus {
        border-color: #1456c8;
    }

    #pub-chat-send {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: none;
        margin-left: 8px;
        background: linear-gradient(135deg, #0a2d6e, #1456c8);
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        transition: transform .2s;
    }

    #pub-chat-send:hover {
        transform: scale(1.08);
    }

    #pub-toggle-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, #0a2d6e, #1456c8);
        color: #fff;
        cursor: pointer;
        z-index: 9999;
        box-shadow: 0 4px 16px rgba(10, 45, 110, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform .2s;
    }

    #pub-toggle-btn:hover {
        transform: scale(1.08);
    }

    #pub-toggle-btn .pub-notif {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 12px;
        height: 12px;
        background: #ef4444;
        border-radius: 50%;
        border: 2px solid #fff;
    }

    @media(max-width:480px) {
        #pub-chatbox {
            width: 95%;
            right: 10px;
            bottom: 80px;
        }
    }
</style>

<!-- Toggle button -->
<button id="pub-toggle-btn" title="Chat with SLGTI Assistant">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        <circle cx="9" cy="10" r="1" fill="white" />
        <circle cx="15" cy="10" r="1" fill="white" />
        <path d="M9 14s1 1 3 1 3-1 3-1" />
    </svg>
    <span class="pub-notif"></span>
</button>

<!-- Chatbox -->
<div id="pub-chatbox">
    <div id="pub-chat-header">
        <div class="pub-header-left">
            <div class="pub-avatar">
                <img src="Image/SLGTI.jpg" alt="SLGTI" style="width:34px;height:34px;border-radius:50%;object-fit:cover;">
            </div>
            <div>
                <div style="font-size:14px;font-weight:700;">SLGTI Assistant</div>
                <div style="font-size:11px;opacity:.8;">Ask me anything about SLGTI</div>
            </div>
        </div>
        <button id="pub-chat-close">✕</button>
    </div>

    <div id="pub-chat-suggestions">
        <button class="pub-chip">About SLGTI</button>
        <button class="pub-chip">Courses offered</button>
        <button class="pub-chip">How to apply?</button>
        <button class="pub-chip">Course duration</button>
        <button class="pub-chip">NVQ certificate</button>
        <button class="pub-chip">Contact info</button>
        <button class="pub-chip">Student login help</button>
        <button class="pub-chip">Course fees</button>
    </div>

    <div id="pub-chat-messages"></div>
    <div id="pub-typing"><span></span><span></span><span></span></div>

    <div id="pub-chat-input-area">
        <input type="text" id="pub-chat-input" placeholder="Ask about SLGTI...">
        <button id="pub-chat-send">➤</button>
    </div>
</div>

<script>
    (function() {
        const box = document.getElementById('pub-chatbox');
        const togBtn = document.getElementById('pub-toggle-btn');
        const closeBtn = document.getElementById('pub-chat-close');
        const input = document.getElementById('pub-chat-input');
        const sendBtn = document.getElementById('pub-chat-send');
        const msgs = document.getElementById('pub-chat-messages');
        const typing = document.getElementById('pub-typing');
        const notif = togBtn.querySelector('.pub-notif');

        togBtn.onclick = () => {
            box.classList.toggle('open');
            if (box.classList.contains('open')) {
                notif.style.display = 'none';
                input.focus();
            }
        };
        closeBtn.onclick = () => box.classList.remove('open');
        sendBtn.onclick = send;
        input.addEventListener('keypress', e => {
            if (e.key === 'Enter') send();
        });

        document.querySelectorAll('.pub-chip').forEach(c => {
            c.onclick = () => {
                input.value = c.textContent;
                send();
            };
        });

        addMsg('ai', "👋 Hi! I'm the SLGTI Assistant. Ask me about courses, admission, the attendance system, or anything about SLGTI.");

        function send() {
            const txt = input.value.trim();
            if (!txt) return;
            addMsg('user', txt);
            input.value = '';
            typing.classList.add('active');

            fetch('includes/public_chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'message=' + encodeURIComponent(txt)
                })
                .then(r => r.text())
                .then(data => {
                    typing.classList.remove('active');
                    addMsg('ai', data);
                })
                .catch(() => {
                    typing.classList.remove('active');
                    addMsg('ai', '⚠ Connection error. Please try again.');
                });
        }

        function addMsg(who, text) {
            const d = document.createElement('div');
            d.className = 'pub-msg ' + who;
            d.innerHTML = `<div class="pub-bubble">${text}</div>`;
            msgs.appendChild(d);
            msgs.scrollTop = msgs.scrollHeight;
        }
    })();
</script>

</body>

</html>