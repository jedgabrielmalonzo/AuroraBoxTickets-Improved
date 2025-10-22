class EnhancedChatbot {
    constructor() {
        this.isOpen = false;
        this.isTyping = false;
        this.messageHistory = [];
        this.init();
    }

    init() {
        this.btn = document.getElementById('chatbot-btn');
        this.box = document.getElementById('chatbot-box');
        this.input = document.getElementById('chat-input');
        this.sendBtn = document.getElementById('send-btn');
        this.chatWindow = document.getElementById('chat-window');
        this.typingIndicator = document.querySelector('.typing-indicator');

        this.attachEventListeners();
    }

    attachEventListeners() {
        // Toggle chat
        this.btn.addEventListener('click', () => this.toggleChat());

        // Send message on Enter or button click
        this.input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        this.sendBtn.addEventListener('click', () => this.sendMessage());

        // Quick actions
        document.querySelectorAll('.quick-action').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const message = e.target.getAttribute('data-message');
                this.input.value = message;
                this.sendMessage();
            });
        });

        // Input validation
        this.input.addEventListener('input', () => {
            const hasText = this.input.value.trim().length > 0;
            this.sendBtn.disabled = !hasText || this.isTyping;
        });

        // Close chat when clicking outside
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.box.contains(e.target) && !this.btn.contains(e.target)) {
                this.toggleChat();
            }
        });
    }

    toggleChat() {
        this.isOpen = !this.isOpen;
        this.box.style.display = this.isOpen ? 'flex' : 'none';
        this.btn.classList.toggle('active', this.isOpen);
        
        if (this.isOpen) {
            this.input.focus();
            this.scrollToBottom();
        }
    }

    async sendMessage() {
        const message = this.input.value.trim();
        if (!message || this.isTyping) return;

        // Add user message
        this.addMessage('user', message);
        this.input.value = '';
        this.sendBtn.disabled = true;

        // Show typing indicator
        this.showTyping();

        try {
            const response = await this.getBotResponseFromServer(message);
            this.hideTyping();
            this.addMessage('bot', response);
        } catch (error) {
            this.hideTyping();
            this.addMessage('bot', '❌ Sorry, I encountered an error. Please try again.', true);
        }

        this.sendBtn.disabled = false;
    }

    async getBotResponseFromServer(message) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "chatbot/chatbot.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            
            xhr.onload = function() {
                try {
                    const response = JSON.parse(this.responseText);
                    resolve(response.reply);
                } catch (e) {
                    reject(new Error('Invalid response format'));
                }
            };
            
            xhr.onerror = function() {
                reject(new Error('Network error'));
            };
            
            xhr.send("message=" + encodeURIComponent(message));
        });
    }

    addMessage(sender, text, isError = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;

        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.textContent = sender === 'user' ? '👤' : '🤖';

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        if (isError) bubble.classList.add('error-message');
        
        // Handle HTML content safely
        if (text.includes('<b>') || text.includes('<br>')) {
            bubble.innerHTML = text;
        } else {
            bubble.textContent = text;
        }

        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

        messageDiv.appendChild(avatar);
        const bubbleContainer = document.createElement('div');
        bubbleContainer.appendChild(bubble);
        bubbleContainer.appendChild(time);
        messageDiv.appendChild(bubbleContainer);

        // Remove welcome message if it exists
        const welcome = this.chatWindow.querySelector('.welcome-message');
        if (welcome) welcome.remove();

        this.chatWindow.appendChild(messageDiv);
        this.scrollToBottom();
    }

    showTyping() {
        this.isTyping = true;
        this.typingIndicator.style.display = 'flex';
        this.scrollToBottom();
    }

    hideTyping() {
        this.isTyping = false;
        this.typingIndicator.style.display = 'none';
    }

    scrollToBottom() {
        setTimeout(() => {
            this.chatWindow.scrollTop = this.chatWindow.scrollHeight;
        }, 100);
    }
}

// Initialize the enhanced chatbot when the page loads
document.addEventListener('DOMContentLoaded', () => {
    new EnhancedChatbot();
});