function knowieToggleChat() {
    const chatWindow = document.querySelector(".knowie-wp-chatbot .chat");
    chatWindow.classList.toggle('show');
    if(chatWindow.classList.contains('show')){
        document.querySelector(".knowie-wp-chatbot .custom-chatbot").style.zIndex = '9999';
    }
    else{
        document.querySelector(".knowie-wp-chatbot .custom-chatbot").style.zIndex = '9998';
    }
}

function knowieOnFormSubmit(event) {
    event.preventDefault();
    const button = document.querySelector('.knowie-wp-chatbot #knowie-submit-btn');
    const messageInput = document.querySelector('.knowie-wp-chatbot #knowie-message');
    if(button.disabled) return;

    const message = messageInput.value.trim();
    if (message !== '') {
        knowieAddMessage('user', message);
        messageInput.value = '';
        knowieAddTypingAnimation('ai');

        jQuery.ajax({
            type: 'POST',
            url: knowieWPChatbot.ajax_url,
            data: {
                action: 'knowie_chatbot',
                message: message,
                nonce: knowieWPChatbot.nonce
            },
            success: function(response) {
                if (response.success) {
                    knowieReplaceTypingAnimationWithMessage('ai', response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    }
}

function knowieAddMessage(sender, message) {
    const chatMessagesContainer = document.querySelector('.knowie-wp-chatbot .chat__messages');
    const messageContainer = document.createElement('div');
    messageContainer.classList.add('chat__messages__' + sender);
    const messageDiv = document.createElement('div');
    messageDiv.innerHTML = `<p>${message}</p>`;
    messageContainer.appendChild(messageDiv);
    chatMessagesContainer.appendChild(messageContainer);
    chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
}

function knowieAddTypingAnimation(sender) {
    const chatMessagesContainer = document.querySelector('.knowie-wp-chatbot .chat__messages');
    const typingContainer = document.createElement('div');
    typingContainer.classList.add('chat__messages__' + sender);
    const typingAnimationDiv = document.createElement('div');
    typingAnimationDiv.classList.add('typing-animation');
    typingAnimationDiv.innerHTML = `
        <div>
           
        </div>
        <p>
            <svg height="16" width="40" style="max-height: 20px;">
                <circle class="dot" cx="10" cy="8" r="3" style="fill:grey;" />
                <circle class="dot" cx="20" cy="8" r="3" style="fill:grey;" />
                <circle class="dot" cx="30" cy="8" r="3" style="fill:grey;" />
            </svg>
        </p>
    `;
    typingContainer.appendChild(typingAnimationDiv);
    chatMessagesContainer.appendChild(typingContainer);
    chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
}

function knowieReplaceTypingAnimationWithMessage(sender, message) {
    const chatMessagesContainer = document.querySelector('.knowie-wp-chatbot .chat__messages');
    const typingContainer = document.querySelector('.knowie-wp-chatbot .chat__messages__' + sender + ':last-child');
    if (typingContainer) {
        typingContainer.innerHTML = `<p>${message}</p>`;
    }
}
