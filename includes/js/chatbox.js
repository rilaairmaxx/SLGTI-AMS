document.addEventListener("DOMContentLoaded", function () {

    const chatbox = document.getElementById("chatbox");
    const toggleBtn = document.getElementById("chat-toggle-btn");
    const closeBtn = document.getElementById("chat-close");
    const input = document.getElementById("chat-input");
    const sendBtn = document.getElementById("chat-send-btn");
    const messages = document.getElementById("chat-messages");
    const typing = document.getElementById("typing-indicator");

    toggleBtn.onclick = () => {
        chatbox.classList.toggle("open");
        input.focus();
    };

    closeBtn.onclick = () => chatbox.classList.remove("open");

    sendBtn.onclick = sendMessage;

    input.addEventListener("keypress", function (e) {
        if (e.key === "Enter") sendMessage();
    });

    document.querySelectorAll(".suggestion-chip").forEach(chip => {
        chip.onclick = () => {
            input.value = chip.textContent;
            sendMessage();
        };
    });

    addMessage("ai", "Hello 👋 I'm your AI Assistant. Ask about your attendance.");

    function sendMessage() {
        let message = input.value.trim();
        if (message === "") return;

        if (message.match(/\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i) || message.startsWith('data:image') || message.includes('[image]')) {
            hideTyping();
            addMessage("ai", "⚠ Sorry, I can only process text questions. Please ask about your attendance, courses, or students using words only.");
            return;
        }

        addMessage("user", message);
        input.value = "";
        showTyping();

        fetch("includes/ai_chat.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "message=" + encodeURIComponent(message)
        })
            .then(res => res.text())
            .then(data => {
                hideTyping();
                if (data.includes("does not support image") || data.includes("Cannot read") || data.includes("model does not")) {
                    addMessage("ai", "⚠ Sorry, I can only process text questions. Please ask about your attendance, courses, or students using words only.");
                } else {
                    addMessage("ai", data);
                }
            })
            .catch(() => {
                hideTyping();
                addMessage("ai", "⚠ Error connecting to server. Please try again.");
            });
    }

    function addMessage(sender, text) {
        let msg = document.createElement("div");
        msg.className = "chat-message " + sender;
        msg.innerHTML = `<div class="message-bubble">${text}</div>`;
        messages.appendChild(msg);
        messages.scrollTop = messages.scrollHeight;
    }

    function showTyping() { typing.classList.add("active"); }
    function hideTyping() { typing.classList.remove("active"); }

});