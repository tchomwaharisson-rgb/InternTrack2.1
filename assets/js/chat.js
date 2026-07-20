let chatSocket = null;
let currentContactId = null;

document.addEventListener("DOMContentLoaded", function () {
  initChat();
});

function initChat() {
  // Connect to WebSocket
  const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
  const wsUrl = `${protocol}//${window.location.host}/interntrack/ws/chat.php`;

  try {
    chatSocket = new WebSocket(wsUrl);

    chatSocket.onopen = function () {
      console.log("Chat WebSocket connected");
      // Send authentication
      chatSocket.send(
        JSON.stringify({
          type: "auth",
          user_id: getCurrentUserId(),
        }),
      );
    };

    chatSocket.onmessage = function (event) {
      const data = JSON.parse(event.data);
      handleChatMessage(data);
    };

    chatSocket.onclose = function () {
      console.log("Chat WebSocket disconnected");
      // Reconnect after 5 seconds
      setTimeout(initChat, 5000);
    };

    chatSocket.onerror = function (error) {
      console.error("Chat WebSocket error:", error);
    };
  } catch (error) {
    console.error("Failed to connect to chat:", error);
  }

  // Chat contact click
  document.querySelectorAll(".chat-contact").forEach((contact) => {
    contact.addEventListener("click", function () {
      const contactId = this.dataset.contactId;
      loadChat(contactId);
    });
  });

  // Chat send message
  const sendButton = document.querySelector("#send-message");
  const messageInput = document.querySelector("#chat-input");

  if (sendButton && messageInput) {
    sendButton.addEventListener("click", function () {
      sendChatMessage();
    });

    messageInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        sendChatMessage();
      }
    });
  }
}

function getCurrentUserId() {
  return document.querySelector('meta[name="user-id"]')?.content || "";
}

function handleChatMessage(data) {
  switch (data.type) {
    case "message":
      displayChatMessage(data.message, data.sender_id);
      break;
    case "typing":
      showTypingIndicator(data.sender_id);
      break;
    case "read":
      markMessagesAsRead(data.message_ids);
      break;
    case "online":
      updateUserStatus(data.user_id, true);
      break;
    case "offline":
      updateUserStatus(data.user_id, false);
      break;
    default:
      console.log("Unknown message type:", data.type);
  }
}

function loadChat(contactId) {
  currentContactId = contactId;

  // Highlight the contact
  document.querySelectorAll(".chat-contact").forEach((el) => {
    el.classList.remove("active");
  });
  document
    .querySelector(`.chat-contact[data-contact-id="${contactId}"]`)
    ?.classList.add("active");

  // Load messages
  fetch(`/interntrack/api/chat.php?action=get_messages&contact_id=${contactId}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displayMessages(data.messages);
        markMessagesAsRead(contactId);
      }
    })
    .catch((error) => console.error("Error loading messages:", error));
}

function displayMessages(messages) {
  const container = document.querySelector(".chat-messages");
  if (!container) return;

  container.innerHTML = "";
  const currentUser = getCurrentUserId();

  messages.forEach((msg) => {
    const isSent = msg.sender_id == currentUser;
    const messageEl = createMessageElement(msg, isSent);
    container.appendChild(messageEl);
  });

  // Scroll to bottom
  container.scrollTop = container.scrollHeight;
}

function createMessageElement(message, isSent) {
  const div = document.createElement("div");
  div.className = `chat-message ${isSent ? "sent" : "received"}`;

  const content = document.createElement("div");
  content.textContent = message.message;

  const time = document.createElement("div");
  time.className = "message-time";
  time.textContent = formatTime(message.created_at);

  div.appendChild(content);
  div.appendChild(time);

  return div;
}

function displayChatMessage(message, senderId) {
  const container = document.querySelector(".chat-messages");
  if (!container) return;

  const currentUser = getCurrentUserId();
  const isSent = senderId == currentUser;

  // Check if this is from the current contact
  if (currentContactId && senderId != currentUser) {
    // Show notification
    const contactName =
      document.querySelector(
        `.chat-contact[data-contact-id="${senderId}"] .contact-name`,
      )?.textContent || "Someone";
    showToast(`New message from ${contactName}`, "info");

    // Update unread badge
    updateUnreadBadge(senderId);
  }

  const messageEl = createMessageElement(message, isSent);
  container.appendChild(messageEl);
  container.scrollTop = container.scrollHeight;
}

function sendChatMessage() {
  const input = document.querySelector("#chat-input");
  const message = input?.value?.trim();

  if (!message || !currentContactId) return;

  // Send via WebSocket
  if (chatSocket && chatSocket.readyState === WebSocket.OPEN) {
    chatSocket.send(
      JSON.stringify({
        type: "send",
        receiver_id: currentContactId,
        message: message,
      }),
    );
  }

  // Also send via HTTP as fallback
  fetch("/interntrack/api/chat.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify({
      action: "send",
      receiver_id: currentContactId,
      message: message,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        input.value = "";
        displayChatMessage(
          { message: message, created_at: new Date().toISOString() },
          getCurrentUserId(),
        );
      } else {
        showToast(data.message || "Error sending message", "error");
      }
    })
    .catch((error) => {
      console.error("Error sending message:", error);
      showToast("Error sending message", "error");
    });
}

function markMessagesAsRead(contactId) {
  fetch("/interntrack/api/chat.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify({
      action: "mark_read",
      sender_id: contactId,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Update badge
        updateUnreadBadge(contactId, true);
      }
    })
    .catch((error) => console.error("Error marking messages as read:", error));
}

function updateUnreadBadge(contactId, clear = false) {
  const badge = document.querySelector(
    `.chat-contact[data-contact-id="${contactId}"] .badge`,
  );
  if (badge) {
    if (clear) {
      badge.remove();
    } else {
      const count = parseInt(badge.textContent) || 0;
      badge.textContent = count + 1;
    }
  }
}

function updateUserStatus(userId, online) {
  const contact = document.querySelector(
    `.chat-contact[data-contact-id="${userId}"]`,
  );
  if (contact) {
    const statusEl = contact.querySelector(".contact-status");
    if (statusEl) {
      statusEl.textContent = online ? "Online" : "Offline";
      statusEl.style.color = online ? "#4CAF50" : "#9E9E9E";
    }
  }
}

function showTypingIndicator(senderId) {
  // Show typing indicator for the current contact
  if (currentContactId == senderId) {
    const container = document.querySelector(".chat-messages");
    if (container) {
      // Remove existing typing indicator
      const existing = container.querySelector(".typing-indicator");
      if (existing) existing.remove();

      const typing = document.createElement("div");
      typing.className = "chat-message received typing-indicator";
      typing.innerHTML =
        "<span>Typing</span><span>.</span><span>.</span><span>.</span>";
      container.appendChild(typing);
      container.scrollTop = container.scrollHeight;
    }
  }
}

function formatTime(datetime) {
  const date = new Date(datetime);
  const now = new Date();

  if (date.toDateString() === now.toDateString()) {
    return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  } else if (date.getTime() > now.getTime() - 7 * 24 * 60 * 60 * 1000) {
    return date.toLocaleDateString([], { weekday: "short" });
  } else {
    return date.toLocaleDateString([], { month: "short", day: "numeric" });
  }
}
