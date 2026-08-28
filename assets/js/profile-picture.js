// assets/js/profile-picture.js
document.addEventListener("DOMContentLoaded", function () {
  initProfilePictureUpload();
});

function initProfilePictureUpload() {
  const uploadBtn = document.getElementById("uploadProfileBtn");
  const fileInput = document.getElementById("profilePictureInput");
  const removeBtn = document.getElementById("removeProfileBtn");
  const previewImg = document.getElementById("profilePreview");
  const uploadForm = document.getElementById("profileUploadForm");
  const progressBar = document.getElementById("uploadProgress");

  if (uploadBtn && fileInput) {
    uploadBtn.addEventListener("click", function () {
      fileInput.click();
    });
  }

  if (fileInput) {
    fileInput.addEventListener("change", function () {
      if (this.files && this.files[0]) {
        const file = this.files[0];

        // Validate file type
        const allowedTypes = [
          "image/jpeg",
          "image/png",
          "image/gif",
          "image/webp",
        ];
        if (!allowedTypes.includes(file.type)) {
          showToast(
            "Invalid file type. Please upload JPG, PNG, GIF, or WEBP.",
            "error",
          );
          this.value = "";
          return;
        }

        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
          showToast("File too large. Maximum size is 5MB.", "error");
          this.value = "";
          return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function (e) {
          if (previewImg) {
            previewImg.src = e.target.result;
          }
        };
        reader.readAsDataURL(file);

        // Upload the file
        uploadProfilePicture(file);
      }
    });
  }

  if (removeBtn) {
    removeBtn.addEventListener("click", function () {
      if (confirm("Are you sure you want to remove your profile picture?")) {
        removeProfilePicture();
      }
    });
  }
}

function uploadProfilePicture(file) {
  const formData = new FormData();
  formData.append("action", "upload_profile_picture");
  formData.append("profile_picture", file);

  // Show loading state
  const uploadBtn = document.getElementById("uploadProfileBtn");
  const originalText = uploadBtn.innerHTML;
  uploadBtn.innerHTML = "⏳ Uploading...";
  uploadBtn.disabled = true;

  fetch("/interntrack/api/upload.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showToast("Profile picture uploaded successfully!", "success");
        // Update profile picture display
        const previewImg = document.getElementById("profilePreview");
        if (previewImg) {
          previewImg.src = data.url + "?t=" + new Date().getTime();
        }
        // Update avatar in header
        updateHeaderAvatar(data.url);
        // Show remove button
        const removeBtn = document.getElementById("removeProfileBtn");
        if (removeBtn) {
          removeBtn.style.display = "inline-block";
        }
        // Reload page after delay
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(data.message || "Upload failed", "error");
        // Revert preview
        const previewImg = document.getElementById("profilePreview");
        if (previewImg) {
          previewImg.src =
            previewImg.dataset.default ||
            "/interntrack/assets/images/default-avatar.png";
        }
      }
    })
    .catch((error) => {
      showToast("Error uploading file", "error");
      console.error("Upload error:", error);
    })
    .finally(() => {
      uploadBtn.innerHTML = originalText;
      uploadBtn.disabled = false;
      // Reset file input
      document.getElementById("profilePictureInput").value = "";
    });
}

function removeProfilePicture() {
  const removeBtn = document.getElementById("removeProfileBtn");
  removeBtn.innerHTML = "⏳ Removing...";
  removeBtn.disabled = true;

  const formData = new FormData();
  formData.append("action", "remove_profile_picture");

  fetch("/interntrack/api/upload.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showToast("Profile picture removed successfully", "success");
        // Reset to default avatar
        const previewImg = document.getElementById("profilePreview");
        if (previewImg) {
          previewImg.src =
            previewImg.dataset.default ||
            "/interntrack/assets/images/default-avatar.png";
        }
        // Hide remove button
        removeBtn.style.display = "none";
        // Update header avatar
        updateHeaderAvatar(null);
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(data.message || "Failed to remove profile picture", "error");
      }
    })
    .catch((error) => {
      showToast("Error removing profile picture", "error");
      console.error("Remove error:", error);
    })
    .finally(() => {
      removeBtn.innerHTML = "🗑️ Remove";
      removeBtn.disabled = false;
    });
}

function updateHeaderAvatar(imageUrl) {
  const avatar = document.querySelector(".user-avatar");
  if (avatar) {
    if (imageUrl) {
      avatar.innerHTML = `<img src="${imageUrl}" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
    } else {
      // Reset to initials
      const name = document.querySelector(".user-name")?.textContent || "User";
      const initials = name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase();
      avatar.innerHTML = initials;
    }
  }
}

// Toast notification helper
function showToast(message, type = "info") {
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
  }, 5000);
}
