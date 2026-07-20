document.addEventListener("DOMContentLoaded", function () {
  initDashboardCharts();
  initAutoRefresh();
});

function initDashboardCharts() {
  // Initialize charts if Chart.js is loaded
  if (typeof Chart !== "undefined") {
    // Weekly hours chart
    const weeklyCtx = document.getElementById("weekly-chart");
    if (weeklyCtx) {
      new Chart(weeklyCtx, {
        type: "bar",
        data: {
          labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
          datasets: [
            {
              label: "Hours Worked",
              data: getWeeklyData(),
              backgroundColor: "rgba(239, 83, 80, 0.6)",
              borderColor: "#EF5350",
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              max: 8,
            },
          },
        },
      });
    }

    // Monthly hours chart
    const monthlyCtx = document.getElementById("monthly-chart");
    if (monthlyCtx) {
      new Chart(monthlyCtx, {
        type: "line",
        data: {
          labels: getMonthLabels(),
          datasets: [
            {
              label: "Hours Worked",
              data: getMonthlyData(),
              backgroundColor: "rgba(239, 83, 80, 0.1)",
              borderColor: "#EF5350",
              borderWidth: 2,
              fill: true,
              tension: 0.4,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
            },
          },
        },
      });
    }
  }
}

function getWeeklyData() {
  // This data should come from the server
  const dataElement = document.getElementById("weekly-data");
  return dataElement
    ? JSON.parse(dataElement.textContent)
    : [0, 0, 0, 0, 0, 0, 0];
}

function getMonthlyData() {
  const dataElement = document.getElementById("monthly-data");
  return dataElement ? JSON.parse(dataElement.textContent) : Array(30).fill(0);
}

function getMonthLabels() {
  const labels = [];
  const today = new Date();
  for (let i = 29; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    labels.push(date.getDate());
  }
  return labels;
}

function initAutoRefresh() {
  // Auto-refresh dashboard data every minute
  setInterval(() => {
    refreshDashboardData();
  }, 60000);
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

      // Update status
      const statusBadge = document.querySelector(".status-badge");
      const newStatusBadge = doc.querySelector(".status-badge");
      if (statusBadge && newStatusBadge) {
        statusBadge.className = newStatusBadge.className;
        statusBadge.textContent = newStatusBadge.textContent;
      }
    })
    .catch((error) => console.error("Error refreshing dashboard:", error));
}
