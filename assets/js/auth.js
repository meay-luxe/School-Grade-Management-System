/* ============================================================
   AUTH.JS — Login / Logout / Role Switching
   School Grade Management System
   ============================================================ */

"use strict";

/* ══════════════════════════════════════════════════════════════
   1. DOM READY
══════════════════════════════════════════════════════════════ */
document.addEventListener("DOMContentLoaded", () => {
  AuthUI.init();
});

/* ══════════════════════════════════════════════════════════════
   2. AUTH UI CONTROLLER
══════════════════════════════════════════════════════════════ */
const AuthUI = {
  // ── State ──────────────────────────────────────────────────
  state: {
    selectedRole: "admin",
    isLoading: false,
    showPassword: false,
  },

  // Demo credentials mapped by role
  demoCredentials: {
    admin: { username: "admin", password: "admin123" },
    teacher: { username: "teacher", password: "teacher123" },
    student: { username: "student", password: "student123" },
  },

  // ── Init ───────────────────────────────────────────────────
  init() {
    this.bindRoleTabs();
    this.bindPasswordToggle();
    this.bindFormSubmit();
    this.bindDemoItems();
    this.initParticles();
    this.restoreRemembered();
    this.focusFirstInput();
  },

  // ── Role Tab Switching ──────────────────────────────────────
  bindRoleTabs() {
    const tabs = document.querySelectorAll(".role-tab");
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        const role = tab.dataset.role;
        if (!role || role === this.state.selectedRole) return;

        // Update active tab
        tabs.forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");

        this.state.selectedRole = role;
        this.updateRoleHint(role);
        this.fillDemoCredentials(role);

        // Update hidden role input if present
        const roleInput = document.getElementById("role-input");
        if (roleInput) roleInput.value = role;

        // Animate form
        const form = document.querySelector(".auth-form");
        if (form) {
          form.style.opacity = "0";
          form.style.transform = "translateY(8px)";
          setTimeout(() => {
            form.style.transition = "all 0.25s ease";
            form.style.opacity = "1";
            form.style.transform = "translateY(0)";
          }, 80);
        }
      });
    });
  },

  updateRoleHint(role) {
    const hints = {
      admin: "🔐 Administrator access",
      teacher: "📚 Teacher portal",
      student: "🎓 Student portal",
    };

    const hintEl = document.getElementById("role-hint");
    if (hintEl) {
      hintEl.textContent = hints[role] || "";
    }
  },

  // ── Password Toggle ─────────────────────────────────────────
  bindPasswordToggle() {
    const toggle = document.querySelector(".password-toggle");
    const input = document.getElementById("password");
    if (!toggle || !input) return;

    toggle.addEventListener("click", () => {
      this.state.showPassword = !this.state.showPassword;
      input.type = this.state.showPassword ? "text" : "password";
      toggle.innerHTML = this.state.showPassword
        ? '<i class="fas fa-eye-slash"></i>'
        : '<i class="fas fa-eye"></i>';
    });
  },

  // ── Form Submission ─────────────────────────────────────────
  bindFormSubmit() {
    const form = document.getElementById("login-form");
    if (!form) return;

    form.addEventListener("submit", (e) => {
      if (!this.validateForm(form)) {
        e.preventDefault();
        return;
      }

      // Handle remember me
      const remember = document.getElementById("remember");
      const username = document.getElementById("username");
      if (remember && username) {
        if (remember.checked) {
          localStorage.setItem("sgms_remembered_user", username.value);
        } else {
          localStorage.removeItem("sgms_remembered_user");
        }
      }

      this.setLoading(true);
      // Form submits normally to PHP — no preventDefault needed
    });
  },

  // ── Client-Side Validation ──────────────────────────────────
  validateForm(form) {
    let valid = true;

    const username = document.getElementById("username");
    const password = document.getElementById("password");

    this.clearErrors();

    if (!username || !username.value.trim()) {
      this.showFieldError(username, "Username is required");
      valid = false;
    }

    if (!password || !password.value.trim()) {
      this.showFieldError(password, "Password is required");
      valid = false;
    } else if (password.value.length < 4) {
      this.showFieldError(password, "Password is too short");
      valid = false;
    }

    // Shake card if invalid
    if (!valid) {
      const card = document.querySelector(".auth-card");
      if (card) {
        card.style.animation = "none";
        card.offsetHeight; // reflow
        card.style.animation = "shake 0.40s ease";
      }
    }

    return valid;
  },

  showFieldError(input, message) {
    if (!input) return;

    input.classList.add("is-invalid");

    // Insert error message after input wrap
    const wrap = input.closest(".input-icon-wrap") || input.parentElement;
    const existing = wrap.querySelector(".form-error");
    if (existing) existing.remove();

    const error = document.createElement("span");
    error.className = "form-error";
    error.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
    wrap.insertAdjacentElement("afterend", error);

    input.addEventListener(
      "input",
      () => {
        input.classList.remove("is-invalid");
        const err = wrap.nextElementSibling;
        if (err && err.classList.contains("form-error")) err.remove();
      },
      { once: true },
    );
  },

  clearErrors() {
    document.querySelectorAll(".form-error").forEach((el) => el.remove());
    document
      .querySelectorAll(".is-invalid")
      .forEach((el) => el.classList.remove("is-invalid"));
  },

  // ── Loading State ───────────────────────────────────────────
  setLoading(state) {
    this.state.isLoading = state;

    const btn = document.querySelector(".auth-submit-btn");
    if (!btn) return;

    if (state) {
      btn.classList.add("loading");
      btn.innerHTML = `
        <span class="btn-spinner"></span>
        <span>Signing in...</span>
      `;
      btn.disabled = true;
    } else {
      btn.classList.remove("loading");
      btn.innerHTML = `
        <i class="fas fa-sign-in-alt"></i>
        <span>Sign In</span>
      `;
      btn.disabled = false;
    }
  },

  // ── Demo Credentials ────────────────────────────────────────
  bindDemoItems() {
    const items = document.querySelectorAll(".demo-item");
    if (!items.length) return;

    items.forEach((item) => {
      item.addEventListener("click", () => {
        const role = item.dataset.role;
        if (!role) return;

        // Switch role tab
        const tab = document.querySelector(`.role-tab[data-role="${role}"]`);
        if (tab) tab.click();

        // Fill credentials
        this.fillDemoCredentials(role);

        // Visual feedback
        item.style.background = "rgba(108, 99, 255, 0.18)";
        setTimeout(() => {
          item.style.background = "";
        }, 500);
      });
    });
  },

  fillDemoCredentials(role) {
    const creds = this.demoCredentials[role];
    const username = document.getElementById("username");
    const password = document.getElementById("password");

    if (!creds || !username || !password) return;

    // Animate fill
    this.typeValue(username, creds.username);
    setTimeout(() => this.typeValue(password, creds.password), 200);
  },

  typeValue(input, value) {
    if (!input) return;
    input.value = "";
    input.focus();

    let i = 0;
    const interval = setInterval(() => {
      input.value += value[i];
      i++;
      if (i >= value.length) {
        clearInterval(interval);
        input.classList.add("is-valid");
        setTimeout(() => input.classList.remove("is-valid"), 1200);
      }
    }, 40);
  },

  // ── Remember Me ─────────────────────────────────────────────
  restoreRemembered() {
    const remembered = localStorage.getItem("sgms_remembered_user");
    if (!remembered) return;

    const username = document.getElementById("username");
    const remember = document.getElementById("remember");

    if (username) username.value = remembered;
    if (remember) remember.checked = true;
  },

  // ── Focus First Input ────────────────────────────────────────
  focusFirstInput() {
    setTimeout(() => {
      const username = document.getElementById("username");
      if (username && !username.value) {
        username.focus();
      }
    }, 400);
  },

  // ── Particle Background ─────────────────────────────────────
  initParticles() {
    const container = document.querySelector(".auth-particles");
    if (!container) return;

    const count = window.innerWidth < 768 ? 8 : 15;
    const colors = ["#6c63ff", "#00d4aa", "#ff6b9d", "#54a0ff", "#ff9f43"];

    for (let i = 0; i < count; i++) {
      const particle = document.createElement("div");
      particle.className = "auth-particle";

      const size = Math.random() * 6 + 3;
      const duration = Math.random() * 12 + 8;
      const delay = Math.random() * 10;
      const left = Math.random() * 100;
      const color = colors[Math.floor(Math.random() * colors.length)];

      particle.style.cssText = `
        width: ${size}px;
        height: ${size}px;
        left: ${left}%;
        bottom: -20px;
        background: ${color};
        animation-duration: ${duration}s;
        animation-delay: -${delay}s;
      `;

      container.appendChild(particle);
    }
  },
};

/* ══════════════════════════════════════════════════════════════
   3. SHAKE ANIMATION (injected)
══════════════════════════════════════════════════════════════ */
(function injectShakeStyle() {
  const style = document.createElement("style");
  style.textContent = `
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%       { transform: translateX(-8px); }
      40%       { transform: translateX( 8px); }
      60%       { transform: translateX(-5px); }
      80%       { transform: translateX( 5px); }
    }
  `;
  document.head.appendChild(style);
})();
