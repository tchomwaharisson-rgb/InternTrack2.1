document.addEventListener("DOMContentLoaded", function () {
  // Initialize tooltips
  initTooltips();

  // Initialize notifications
  initNotifications();

  // Initialize language switcher
  initLanguageSwitcher();

  // Initialize theme toggle
  initThemeToggle();

  // Initialize sidebar toggle for mobile
  initSidebarToggle();

  // Initialize dropdowns
  initDropdowns();

  // Initialize modals
  initModals();

  // Initialize forms
  initForms();

  // Initialize auto-refresh for dashboard
  initAutoRefresh();
});

// Tooltips
function initTooltips() {
  document.querySelectorAll("[data-tooltip]").forEach((el) => {
    el.addEventListener("mouseenter", function (e) {
      const tooltip = document.createElement("div");
      tooltip.className = "tooltip";
      tooltip.textContent = this.dataset.tooltip;
      document.body.appendChild(tooltip);

      const rect = this.getBoundingClientRect();
      tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + "px";
      tooltip.style.left =
        rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + "px";

      this.addEventListener("mouseleave", function () {
        tooltip.remove();
      });
    });
  });
}

// Notifications
function initNotifications() {
  const notificationBtn = document.querySelector(".notification-btn");
  if (notificationBtn) {
    notificationBtn.addEventListener("click", function () {
      const dropdown = this.querySelector(".dropdown-menu");
      if (dropdown) {
        dropdown.classList.toggle("show");
      }
      // Mark notifications as read
      markNotificationsAsRead();
    });
  }

  // Close notifications on click outside
  document.addEventListener("click", function (e) {
    if (!e.target.closest(".notification-btn")) {
      document
        .querySelectorAll(".notification-btn .dropdown-menu")
        .forEach((el) => {
          el.classList.remove("show");
        });
    }
  });
}

function markNotificationsAsRead() {
  fetch("/api/notifications/mark-read", {
    method: "POST",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const badge = document.querySelector(".notification-badge");
        if (badge) {
          badge.textContent = "0";
          badge.style.display = "none";
        }
      }
    })
    .catch((error) =>
      console.error("Error marking notifications as read:", error),
    );
}

function getSettingsApiUrl() {
  const path = window.location.pathname || "/";
  return path.includes("/interntrack") ? "/interntrack/api/settings.php" : "/api/settings.php";
}

function sendSettingsRequest(action, payload) {
  return fetch(getSettingsApiUrl(), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify({ action, ...payload }),
  }).then(async (response) => {
    const contentType = response.headers.get("content-type") || "";
    if (contentType.includes("application/json")) {
      return response.json();
    }

    const text = await response.text();
    throw new Error(text || `Request failed with status ${response.status}`);
  });
}

function getLanguageFromButton(button) {
  const explicitLang = button.dataset.lang || button.getAttribute("data-lang");
  if (explicitLang) {
    return explicitLang.toLowerCase();
  }

  const onclick = button.getAttribute("onclick") || "";
  const match = onclick.match(/switchLanguage\(\s*["']([a-z]{2})["']\s*\)/i);
  if (match) {
    return match[1].toLowerCase();
  }

  const label = (button.textContent || "").trim().toLowerCase();
  if (label.includes("fr")) {
    return "fr";
  }
  if (label.includes("en")) {
    return "en";
  }

  return null;
}

// Language Switcher
function initLanguageSwitcher() {
  document.querySelectorAll(".language-switcher button").forEach((btn) => {
    btn.addEventListener("click", function () {
      const lang = getLanguageFromButton(this);
      if (!lang) {
        return;
      }

      document
        .querySelectorAll(".language-switcher button")
        .forEach((b) => b.classList.remove("active"));
      this.classList.add("active");

      // Save language preference
      sendSettingsRequest("language", { language: lang })
        .then((data) => {
          if (data.success) {
            location.reload();
          } else {
            console.error("Language change failed:", data.message);
          }
        })
        .catch((error) => console.error("Error changing language:", error));
    });
  });
}

// Theme Toggle
function initThemeToggle() {
  const themeToggle = document.querySelector(".theme-toggle");
  if (themeToggle) {
    themeToggle.addEventListener("click", function () {
      const currentTheme =
        document.documentElement.getAttribute("data-theme") || "light";
      const newTheme = currentTheme === "light" ? "dark" : "light";

      // Save theme preference
      sendSettingsRequest("theme", { theme: newTheme })
        .then((data) => {
          if (data.success) {
            document.documentElement.setAttribute("data-theme", newTheme);
            updateThemeStyles(newTheme);
          } else {
            console.error("Theme change failed:", data.message);
          }
        })
        .catch((error) => console.error("Error changing theme:", error));
    });
  }
}

function updateThemeStyles(theme) {
  if (theme === "dark") {
    document.querySelector('link[href*="dark-mode.css"]').disabled = false;
    document.querySelector(".theme-toggle").textContent = "☀️";
  } else {
    document.querySelector('link[href*="dark-mode.css"]').disabled = true;
    document.querySelector(".theme-toggle").textContent = "🌙";
  }
}

// Sidebar Toggle
function initSidebarToggle() {
  const sidebarToggle = document.querySelector(".sidebar-toggle");
  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", function () {
      document.querySelector(".sidebar").classList.toggle("open");
    });
  }

  // Close sidebar on outside click on mobile
  document.addEventListener("click", function (e) {
    if (window.innerWidth <= 768) {
      const sidebar = document.querySelector(".sidebar");
      const toggle = document.querySelector(".sidebar-toggle");
      if (
        sidebar &&
        sidebar.classList.contains("open") &&
        !sidebar.contains(e.target) &&
        !toggle.contains(e.target)
      ) {
        sidebar.classList.remove("open");
      }
    }
  });
}

// Dropdowns
function initDropdowns() {
  document.querySelectorAll(".dropdown-toggle").forEach((toggle) => {
    toggle.addEventListener("click", function (e) {
      e.stopPropagation();
      const dropdown = this.parentElement;
      dropdown.classList.toggle("show");
    });
  });

  document.addEventListener("click", function () {
    document.querySelectorAll(".dropdown.show").forEach((el) => {
      el.classList.remove("show");
    });
  });
}

// Modals
function initModals() {
  document.querySelectorAll("[data-modal-open]").forEach((btn) => {
    btn.addEventListener("click", function () {
      const modalId = this.dataset.modalOpen;
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add("show");
        document.body.style.overflow = "hidden";
      }
    });
  });

  document
    .querySelectorAll(".modal-close, [data-modal-close]")
    .forEach((btn) => {
      btn.addEventListener("click", function () {
        const modal = this.closest(".modal-overlay");
        if (modal) {
          modal.classList.remove("show");
          document.body.style.overflow = "";
        }
      });
    });

  document.querySelectorAll(".modal-overlay").forEach((overlay) => {
    overlay.addEventListener("click", function (e) {
      if (e.target === this) {
        this.classList.remove("show");
        document.body.style.overflow = "";
      }
    });
  });
}

// Forms
function initForms() {
  document.querySelectorAll("form[data-validate]").forEach((form) => {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (validateForm(this)) {
        // Submit form via AJAX or normal submit
        this.submit();
      }
    });
  });

  // Password confirmation validation
  document.querySelectorAll("[data-confirm-password]").forEach((input) => {
    input.addEventListener("input", function () {
      const password = document.getElementById(this.dataset.confirmPassword);
      if (password) {
        if (this.value !== password.value) {
          this.setCustomValidity("Passwords do not match");
        } else {
          this.setCustomValidity("");
        }
      }
    });
  });
}

function validateForm(form) {
  const inputs = form.querySelectorAll(
    "input[required], select[required], textarea[required]",
  );
  let isValid = true;

  inputs.forEach((input) => {
    if (!input.value.trim()) {
      input.classList.add("error");
      isValid = false;
    } else {
      input.classList.remove("error");
    }
  });

  // Email validation
  const emailInputs = form.querySelectorAll('input[type="email"]');
  emailInputs.forEach((input) => {
    if (input.value && !isValidEmail(input.value)) {
      input.classList.add("error");
      isValid = false;
    }
  });

  if (!isValid) {
    showToast("Please fill in all required fields correctly", "error");
  }

  return isValid;
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Toast notifications
function showToast(message, type = "info", duration = 5000) {
  const container = document.querySelector(".toast-container");
  if (!container) {
    const newContainer = document.createElement("div");
    newContainer.className = "toast-container";
    document.body.appendChild(newContainer);
  }

  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  toast.textContent = message;

  document.querySelector(".toast-container").appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = "0";
    setTimeout(() => {
      toast.remove();
    }, 300);
  }, duration);
}

// Auto Refresh
function initAutoRefresh() {
  const refreshInterval = 60000; // 1 minute
  setInterval(() => {
    // Refresh dashboard data if on dashboard
    if (window.location.pathname.includes("/dashboard")) {
      refreshDashboardData();
    }
  }, refreshInterval);
}

function refreshDashboardData() {
  fetch(window.location.href, {
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => response.text())
    .then((html) => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      // Update stats
      document.querySelectorAll(".stat-value").forEach((el, index) => {
        const newEl = doc.querySelectorAll(".stat-value")[index];
        if (newEl) {
          el.textContent = newEl.textContent;
        }
      });
    })
    .catch((error) => console.error("Error refreshing dashboard:", error));
}

// Utility functions
function formatDate(date) {
  return new Date(date).toLocaleDateString();
}

function formatTime(time) {
  return new Date("1970-01-01T" + time).toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatDateTime(datetime) {
  return new Date(datetime).toLocaleString();
}

function timeAgo(date) {
  const seconds = Math.floor((new Date() - new Date(date)) / 1000);
  const intervals = {
    year: 31536000,
    month: 2592000,
    week: 604800,
    day: 86400,
    hour: 3600,
    minute: 60,
  };

  for (const [unit, secondsInUnit] of Object.entries(intervals)) {
    const interval = Math.floor(seconds / secondsInUnit);
    if (interval >= 1) {
      return interval + " " + unit + (interval > 1 ? "s" : "") + " ago";
    }
  }
  return "just now";
}
