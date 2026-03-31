<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link rel="stylesheet" href="includes/css/chatbox.css">

<button id="chat-toggle-btn" title="Open AI Assistant">
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        <circle cx="9" cy="10" r="1" fill="white"></circle>
        <circle cx="15" cy="10" r="1" fill="white"></circle>
        <path d="M9 14s1 1 3 1 3-1 3-1"></path>
    </svg>
</button>

<div id="chatbox">

    <div id="chat-header">
        <h4>AI Assistant <?php echo isset($_SESSION['role']) ? '(' . ucfirst($_SESSION['role']) . ')' : ''; ?></h4>
        <button id="chat-close">&times;</button>
    </div>

    <!-- Quick-tap suggestion chips for common questions -->
    <div id="chat-suggestions">
        <?php
        $role = $_SESSION['role'] ?? 'student';
        
        if ($role === 'student') {
            echo '<div class="suggestion-chip">What\'s my attendance percentage?</div>';
            echo '<div class="suggestion-chip">How many classes did I miss?</div>';
            echo '<div class="suggestion-chip">Show low attendance subjects</div>';
            echo '<div class="suggestion-chip">Show my courses</div>';
        } elseif ($role === 'lecturer') {
            echo '<div class="suggestion-chip">Show my courses</div>';
            echo '<div class="suggestion-chip">Total students in my courses</div>';
            echo '<div class="suggestion-chip">Show low attendance students</div>';
            echo '<div class="suggestion-chip">What\'s the attendance rate?</div>';
        } elseif ($role === 'admin') {
            echo '<div class="suggestion-chip">Total students</div>';
            echo '<div class="suggestion-chip">Total courses</div>';
            echo '<div class="suggestion-chip">Total lecturers</div>';
            echo '<div class="suggestion-chip">Show overall attendance</div>';
            echo '<div class="suggestion-chip">Show low attendance students</div>';
            echo '<div class="suggestion-chip">Active users</div>';
        }
        ?>
    </div>

    <!-- Chat messages appear here dynamically -->
    <div id="chat-messages"></div>

    <!-- Animated dots shown while AI is responding -->
    <div id="typing-indicator">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <div id="chat-input-area">
        <input type="text" id="chat-input" placeholder="Ask me anything...">
        <button id="chat-send-btn">➤</button>
    </div>

</div>

<script src="includes/js/chatbox.js"></script>