/* ============================================================
   APP.JS — Main Application JS
   Navigation · UI · Modals · Toasts · Tables · Charts
   School Grade Management System
   ============================================================ */

"use strict";

/* ══════════════════════════════════════════════════════════════
   1. DOM READY — BOOT
══════════════════════════════════════════════════════════════ */
document.addEventListener("DOMContentLoaded", () => {
  App.init();
});

/* ══════════════════════════════════════════════════════════════
   2. CORE APP CONTROLLER
══════════════════════════════════════════════════════════════ */
const App = {
  // ── State ──────────────────────────────────────────────────
  state: {
    sidebarCollapsed: false,
    mobileSidebarOpen: false,
    activeDropdown: null,
  },

  // ── Boot ───────────────────────────────────────────────────
  init() {
    Sidebar.init();
    Topbar.init();
    Modal.init();
    Toast.init();
    Dropdown.init();
    Tabs.init();
    Table.init();
    Tooltip.init();
    GradeUI.init();
    Charts.init();
    this.initMobileOverlay();
    this.initPageAnimations();
    this.restoreSidebarState();
  },

  // ── Mobile Overlay ──────────────────────────────────────────
  initMobileOverlay() {
    let overlay = document.querySelector(".mobile-overlay");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.className = "mobile-overlay";
      document.body.appendChild(overlay);
    }

    overlay.addEventListener("click", () => {
      Sidebar.closeMobile();
    });
  },

  // ── Page Animations ─────────────────────────────────────────
  initPageAnimations() {
    // Stagger stat cards
    const statCards = document.querySelectorAll(".stat-card");
    statCards.forEach((card, i) => {
      card.style.opacity = "0";
      card.style.transform = "translateY(16px)";
      setTimeout(
        () => {
          card.style.transition = "all 0.40s ease";
          card.style.opacity = "1";
          card.style.transform = "translateY(0)";
        },
        i * 80 + 100,
      );
    });

    // Stagger glass cards
    const glassCards = document.querySelectorAll(".glass-card");
    glassCards.forEach((card, i) => {
      card.style.opacity = "0";
      card.style.transform = "translateY(12px)";
      setTimeout(
        () => {
          card.style.transition = "all 0.40s ease";
          card.style.opacity = "1";
          card.style.transform = "translateY(0)";
        },
        i * 60 + 150,
      );
    });
  },

  // ── Restore Sidebar State ───────────────────────────────────
  restoreSidebarState() {
    const collapsed = localStorage.getItem("sgms_sidebar_collapsed") === "true";
    if (collapsed) {
      Sidebar.collapse(false); // no animation on initial load
    }
  },
};

/* ══════════════════════════════════════════════════════════════
   3. SIDEBAR MODULE
══════════════════════════════════════════════════════════════ */
const Sidebar = {
  el: null,
  toggleBtn: null,
  mainContent: null,
  topbar: null,

  init() {
    this.el = document.querySelector(".sidebar");
    this.toggleBtn = document.querySelector(".sidebar-toggle");
    this.mainContent = document.querySelector(".main-content");
    this.topbar = document.querySelector(".topbar");

    if (!this.el) return;

    this.bindToggle();
    this.bindMobileMenu();
    this.setActiveNavItem();
  },

  // ── Desktop Toggle ──────────────────────────────────────────
  bindToggle() {
    if (!this.toggleBtn) return;

    this.toggleBtn.addEventListener("click", () => {
      const isCollapsed = this.el.classList.contains("collapsed");
      isCollapsed ? this.expand() : this.collapse();
    });
  },

  collapse(animate = true) {
    if (!this.el) return;

    if (!animate) {
      this.el.style.transition = "none";
      if (this.mainContent) this.mainContent.style.transition = "none";
      if (this.topbar) this.topbar.style.transition = "none";
    }

    this.el.classList.add("collapsed");
    if (this.mainContent) this.mainContent.classList.add("sidebar-collapsed");
    if (this.topbar) this.topbar.classList.add("sidebar-collapsed");
    if (this.toggleBtn) {
      this.toggleBtn.classList.add("collapsed");
      this.toggleBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    }

    localStorage.setItem("sgms_sidebar_collapsed", "true");
    App.state.sidebarCollapsed = true;

    if (!animate) {
      requestAnimationFrame(() => {
        this.el.style.transition = "";
        if (this.mainContent) this.mainContent.style.transition = "";
        if (this.topbar) this.topbar.style.transition = "";
      });
    }
  },

  expand() {
    if (!this.el) return;

    this.el.classList.remove("collapsed");
    if (this.mainContent)
      this.mainContent.classList.remove("sidebar-collapsed");
    if (this.topbar) this.topbar.classList.remove("sidebar-collapsed");
    if (this.toggleBtn) {
      this.toggleBtn.classList.remove("collapsed");
      this.toggleBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    }

    localStorage.setItem("sgms_sidebar_collapsed", "false");
    App.state.sidebarCollapsed = false;
  },

  // ── Mobile Toggle ───────────────────────────────────────────
  bindMobileMenu() {
    const mobileBtn = document.querySelector(".topbar-mobile-menu");
    if (!mobileBtn) return;

    mobileBtn.addEventListener("click", () => {
      App.state.mobileSidebarOpen ? this.closeMobile() : this.openMobile();
    });
  },

  openMobile() {
    if (!this.el) return;
    this.el.classList.add("mobile-open");
    document.querySelector(".mobile-overlay")?.classList.add("active");
    document.body.style.overflow = "hidden";
    App.state.mobileSidebarOpen = true;
  },

  closeMobile() {
    if (!this.el) return;
    this.el.classList.remove("mobile-open");
    document.querySelector(".mobile-overlay")?.classList.remove("active");
    document.body.style.overflow = "";
    App.state.mobileSidebarOpen = false;
  },

  // ── Active Nav Item ─────────────────────────────────────────
  setActiveNavItem() {
    const currentPath = window.location.pathname;
    const currentFile = currentPath.split("/").pop() || "index.php";

    const navItems = document.querySelectorAll(".nav-item");
    navItems.forEach((item) => {
      const href = item.getAttribute("href") || "";
      const itemFile = href.split("/").pop();

      if (itemFile && itemFile === currentFile) {
        item.classList.add("active");
      } else if (
        href &&
        currentPath.includes(href.replace("../", "").replace(".php", ""))
      ) {
        item.classList.add("active");
      }
    });
  },
};

/* ══════════════════════════════════════════════════════════════
   4. TOPBAR MODULE
══════════════════════════════════════════════════════════════ */
const Topbar = {
  init() {
    this.bindNotifications();
    this.bindUserMenu();
  },

  bindNotifications() {
    const notifBtn = document.getElementById("notif-btn");
    if (!notifBtn) return;

    notifBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      // Notification panel toggle can be extended
    });
  },

  bindUserMenu() {
    const userBtn = document.getElementById("user-menu-btn");
    const userMenu = document.getElementById("user-dropdown");
    if (!userBtn || !userMenu) return;

    userBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      userMenu.classList.toggle("open");
    });

    document.addEventListener("click", () => {
      userMenu.classList.remove("open");
    });
  },
};

/* ══════════════════════════════════════════════════════════════
   5. MODAL MODULE
══════════════════════════════════════════════════════════════ */
const Modal = {
  activeModal: null,

  init() {
    // Close on overlay click
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("modal-overlay")) {
        this.close(e.target);
      }
    });

    // Close on Escape
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.activeModal) {
        this.close(this.activeModal);
      }
    });

    // Bind all open triggers
    document.querySelectorAll("[data-modal-open]").forEach((trigger) => {
      trigger.addEventListener("click", () => {
        const id = trigger.dataset.modalOpen;
        this.open(id);
      });
    });

    // Bind all close triggers
    document
      .querySelectorAll("[data-modal-close], .modal-close")
      .forEach((btn) => {
        btn.addEventListener("click", () => {
          const overlay = btn.closest(".modal-overlay");
          if (overlay) this.close(overlay);
        });
      });
  },

  // ── Open ────────────────────────────────────────────────────
  open(idOrElement, options = {}) {
    let overlay;

    if (typeof idOrElement === "string") {
      overlay = document.getElementById(idOrElement);
    } else {
      overlay = idOrElement;
    }

    if (!overlay) return;

    overlay.classList.add("active");
    document.body.style.overflow = "hidden";
    this.activeModal = overlay;

    // Focus first input
    setTimeout(() => {
      const firstInput = overlay.querySelector(
        "input:not([type=hidden]), textarea, select",
      );
      if (firstInput) firstInput.focus();
    }, 300);

    // onOpen callback
    if (typeof options.onOpen === "function") {
      options.onOpen(overlay);
    }
  },

  // ── Close ────────────────────────────────────────────────────
  close(overlay) {
    if (!overlay) return;

    overlay.classList.remove("active");
    document.body.style.overflow = "";

    if (this.activeModal === overlay) {
      this.activeModal = null;
    }

    // Clear forms inside modal
    const forms = overlay.querySelectorAll("form");
    forms.forEach((form) => {
      if (form.dataset.clearOnClose !== "false") {
        // Don't reset — let PHP handle repopulation
      }
    });
  },

  // ── Confirm Dialog ───────────────────────────────────────────
  confirm(options = {}) {
    const {
      title = "Are you sure?",
      message = "This action cannot be undone.",
      confirm = "Confirm",
      cancel = "Cancel",
      type = "danger",
      onConfirm,
      onCancel,
    } = options;

    // Remove existing confirm modal
    document.getElementById("confirm-modal")?.remove();

    const iconMap = { danger: "⚠️", warning: "⚠️", info: "ℹ️", success: "✅" };

    const html = `
      <div class="modal-overlay" id="confirm-modal">
        <div class="modal modal-sm">
          <div class="modal-header">
            <div class="modal-title">
              <div class="modal-icon" style="background: var(--${type}-bg); color: var(--${type})">
                ${iconMap[type] || "⚠️"}
              </div>
              ${title}
            </div>
          </div>
          <div class="modal-body">
            <p style="color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6;">
              ${message}
            </p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" id="confirm-cancel-btn">${cancel}</button>
            <button class="btn btn-${type}" id="confirm-ok-btn">${confirm}</button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML("beforeend", html);

    const modal = document.getElementById("confirm-modal");
    const confirmBtn = document.getElementById("confirm-ok-btn");
    const cancelBtn = document.getElementById("confirm-cancel-btn");

    setTimeout(() => this.open(modal), 10);

    confirmBtn.addEventListener("click", () => {
      this.close(modal);
      modal.remove();
      if (typeof onConfirm === "function") onConfirm();
    });

    cancelBtn.addEventListener("click", () => {
      this.close(modal);
      modal.remove();
      if (typeof onCancel === "function") onCancel();
    });

    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        this.close(modal);
        modal.remove();
        if (typeof onCancel === "function") onCancel();
      }
    });
  },
};

/* ══════════════════════════════════════════════════════════════
   6. TOAST MODULE
══════════════════════════════════════════════════════════════ */
const Toast = {
  container: null,
  queue: [],
  MAX: 5,
  DURATION: 4500,

  init() {
    this.container = document.querySelector(".toast-container");
    if (!this.container) {
      this.container = document.createElement("div");
      this.container.className = "toast-container";
      document.body.appendChild(this.container);
    }

    // Auto-show PHP flash messages
    this.showFlashMessages();
  },

  show(options = {}) {
    const {
      type = "info",
      title = "",
      message = "",
      duration = this.DURATION,
    } = typeof options === "string"
      ? { type: "info", message: options }
      : options;

    // Limit max toasts
    const existing = this.container.querySelectorAll(".toast");
    if (existing.length >= this.MAX) {
      this.remove(existing[0]);
    }

    const iconMap = {
      success: "fa-check-circle",
      error: "fa-times-circle",
      warning: "fa-exclamation-triangle",
      info: "fa-info-circle",
    };

    const titleMap = {
      success: title || "Success",
      error: title || "Error",
      warning: title || "Warning",
      info: title || "Info",
    };

    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <span class="toast-icon"><i class="fas ${iconMap[type] || iconMap.info}"></i></span>
      <div class="toast-content">
        <div class="toast-title">${titleMap[type]}</div>
        <div class="toast-message">${message}</div>
      </div>
      <span class="toast-close"><i class="fas fa-times"></i></span>
    `;

    this.container.appendChild(toast);

    // Close button
    toast.querySelector(".toast-close").addEventListener("click", () => {
      this.remove(toast);
    });

    // Auto dismiss
    const timer = setTimeout(() => this.remove(toast), duration);

    // Pause on hover
    toast.addEventListener("mouseenter", () => clearTimeout(timer));
    toast.addEventListener("mouseleave", () => {
      setTimeout(() => this.remove(toast), 1500);
    });

    return toast;
  },

  remove(toast) {
    if (!toast || !toast.parentNode) return;
    toast.classList.add("removing");
    setTimeout(() => toast.remove(), 320);
  },

  // Convenience methods
  success(message, title = "") {
    return this.show({ type: "success", message, title });
  },
  error(message, title = "") {
    return this.show({ type: "error", message, title });
  },
  warning(message, title = "") {
    return this.show({ type: "warning", message, title });
  },
  info(message, title = "") {
    return this.show({ type: "info", message, title });
  },

  // Show PHP-generated flash messages from data attributes
  showFlashMessages() {
    const flashEl = document.getElementById("flash-messages");
    if (!flashEl) return;

    const messages = flashEl.dataset;

    if (messages.success) this.success(messages.success);
    if (messages.error) this.error(messages.error);
    if (messages.warning) this.warning(messages.warning);
    if (messages.info) this.info(messages.info);
  },
};

/* ══════════════════════════════════════════════════════════════
   7. DROPDOWN MODULE
══════════════════════════════════════════════════════════════ */
const Dropdown = {
  init() {
    document.querySelectorAll(".dropdown").forEach((dropdown) => {
      const trigger =
        dropdown.querySelector("[data-dropdown-toggle]") ||
        dropdown.querySelector(".topbar-btn");
      const menu = dropdown.querySelector(".dropdown-menu");

      if (!trigger || !menu) return;

      trigger.addEventListener("click", (e) => {
        e.stopPropagation();

        // Close others
        document.querySelectorAll(".dropdown-menu.open").forEach((m) => {
          if (m !== menu) m.classList.remove("open");
        });

        menu.classList.toggle("open");
        App.state.activeDropdown = menu.classList.contains("open")
          ? menu
          : null;
      });
    });

    // Close on outside click
    document.addEventListener("click", () => {
      document
        .querySelectorAll(".dropdown-menu.open")
        .forEach((m) => m.classList.remove("open"));
      App.state.activeDropdown = null;
    });
  },
};

/* ══════════════════════════════════════════════════════════════
   8. TABS MODULE
══════════════════════════════════════════════════════════════ */
const Tabs = {
  init() {
    document.querySelectorAll(".tabs").forEach((tabGroup) => {
      const buttons = tabGroup.querySelectorAll(".tab-btn");
      const contents = document.querySelectorAll(".tab-content");

      buttons.forEach((btn) => {
        btn.addEventListener("click", () => {
          const target = btn.dataset.tab;
          if (!target) return;

          // Update buttons
          buttons.forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");

          // Update content panels
          contents.forEach((panel) => {
            panel.classList.toggle("active", panel.id === target);
          });

          // Persist tab selection
          if (tabGroup.dataset.persist) {
            localStorage.setItem(
              `sgms_tab_${tabGroup.dataset.persist}`,
              target,
            );
          }
        });
      });

      // Restore persisted tab
      if (tabGroup.dataset.persist) {
        const saved = localStorage.getItem(
          `sgms_tab_${tabGroup.dataset.persist}`,
        );
        if (saved) {
          const savedBtn = tabGroup.querySelector(`[data-tab="${saved}"]`);
          if (savedBtn) savedBtn.click();
        }
      }
    });
  },
};

/* ══════════════════════════════════════════════════════════════
   9. TABLE MODULE
══════════════════════════════════════════════════════════════ */
const Table = {
  init() {
    this.initSearch();
    this.initSort();
    this.initPerPage();
    this.initSelectAll();
  },

  // ── Search Filter ────────────────────────────────────────────
  initSearch() {
    document.querySelectorAll("[data-table-search]").forEach((input) => {
      const tableId = input.dataset.tableSearch;
      const table = document.getElementById(tableId);
      if (!table) return;

      let debounceTimer;

      input.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          this.filterTable(table, input.value);
        }, 250);
      });
    });
  },

  filterTable(table, query) {
    const rows = table.querySelectorAll("tbody tr");
    const lowerQ = query.toLowerCase().trim();
    let visible = 0;

    rows.forEach((row) => {
      const text = row.textContent.toLowerCase();
      const matches = !lowerQ || text.includes(lowerQ);
      row.style.display = matches ? "" : "none";
      if (matches) visible++;
    });

    // Update count display
    const countEl = document.querySelector(`[data-table-count]`);
    if (countEl) countEl.textContent = visible;

    // Show empty state
    const emptyEl = table
      .closest(".table-wrapper")
      ?.querySelector(".table-empty");
    if (emptyEl) emptyEl.style.display = visible === 0 ? "block" : "none";
  },

  // ── Column Sorting ───────────────────────────────────────────
  initSort() {
    document.querySelectorAll(".data-table thead th.sortable").forEach((th) => {
      th.addEventListener("click", () => {
        const table = th.closest(".data-table");
        const colIdx = Array.from(th.parentElement.children).indexOf(th);
        const asc = !th.classList.contains("sort-asc");

        // Reset other headers
        table.querySelectorAll("th").forEach((h) => {
          h.classList.remove("sort-asc", "sort-desc");
        });

        th.classList.add(asc ? "sort-asc" : "sort-desc");
        this.sortTable(table, colIdx, asc);
      });
    });
  },

  sortTable(table, colIdx, ascending) {
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr"));

    rows.sort((a, b) => {
      const aText = a.children[colIdx]?.textContent.trim() || "";
      const bText = b.children[colIdx]?.textContent.trim() || "";

      const aNum = parseFloat(aText.replace(/[^0-9.-]/g, ""));
      const bNum = parseFloat(bText.replace(/[^0-9.-]/g, ""));

      if (!isNaN(aNum) && !isNaN(bNum)) {
        return ascending ? aNum - bNum : bNum - aNum;
      }

      return ascending
        ? aText.localeCompare(bText)
        : bText.localeCompare(aText);
    });

    rows.forEach((row) => tbody.appendChild(row));
  },

  // ── Per Page Selector ────────────────────────────────────────
  initPerPage() {
    document.querySelectorAll("[data-per-page]").forEach((select) => {
      const tableId = select.dataset.perPage;
      const table = document.getElementById(tableId);
      if (!table) return;

      select.addEventListener("change", () => {
        const perPage = parseInt(select.value);
        const rows = table.querySelectorAll("tbody tr");

        rows.forEach((row, i) => {
          row.style.display = i < perPage || perPage === -1 ? "" : "none";
        });
      });
    });
  },

  // ── Select All Checkbox ─────────────────────────────────────
  initSelectAll() {
    const selectAll = document.getElementById("select-all");
    if (!selectAll) return;

    selectAll.addEventListener("change", () => {
      const checkboxes = document.querySelectorAll(".row-checkbox");
      checkboxes.forEach((cb) => {
        cb.checked = selectAll.checked;
        cb.closest("tr")?.classList.toggle("selected", selectAll.checked);
      });
      this.updateBulkActions();
    });

    document.querySelectorAll(".row-checkbox").forEach((cb) => {
      cb.addEventListener("change", () => {
        cb.closest("tr")?.classList.toggle("selected", cb.checked);
        this.updateSelectAll();
        this.updateBulkActions();
      });
    });
  },

  updateSelectAll() {
    const selectAll = document.getElementById("select-all");
    const checkboxes = document.querySelectorAll(".row-checkbox");
    const checked = document.querySelectorAll(".row-checkbox:checked");

    if (!selectAll) return;
    selectAll.indeterminate =
      checked.length > 0 && checked.length < checkboxes.length;
    selectAll.checked =
      checked.length === checkboxes.length && checkboxes.length > 0;
  },

  updateBulkActions() {
    const checked = document.querySelectorAll(".row-checkbox:checked");
    const bulkPanel = document.getElementById("bulk-actions");
    if (!bulkPanel) return;

    bulkPanel.style.display = checked.length > 0 ? "flex" : "none";

    const countEl = bulkPanel.querySelector(".bulk-count");
    if (countEl) countEl.textContent = `${checked.length} selected`;
  },

  getSelectedIds() {
    return Array.from(document.querySelectorAll(".row-checkbox:checked"))
      .map((cb) => cb.value)
      .filter(Boolean);
  },
};

/* ══════════════════════════════════════════════════════════════
   10. TOOLTIP MODULE
══════════════════════════════════════════════════════════════ */
const Tooltip = {
  init() {
    // CSS-only tooltips via [data-tooltip] — no JS needed
    // This module is reserved for dynamic tooltips if needed
  },
};

/* ══════════════════════════════════════════════════════════════
   11. GRADE UI MODULE
══════════════════════════════════════════════════════════════ */
const GradeUI = {
  init() {
    this.animateGPARings();
    this.initLiveGradeCalc();
    this.initGradeInputValidation();
    this.colorizeGradeScores();
  },

  // ── Animate GPA Ring ────────────────────────────────────────
  animateGPARings() {
    document.querySelectorAll(".gpa-ring").forEach((ring) => {
      const fill = ring.querySelector(".gpa-ring-fill");
      const valueEl = ring.querySelector(".gpa-value");
      if (!fill || !valueEl) return;

      const gpa = parseFloat(ring.dataset.gpa || 0);
      const maxGpa = parseFloat(ring.dataset.maxGpa || 4.0);
      const radius = parseFloat(fill.getAttribute("r") || 54);
      const circumference = 2 * Math.PI * radius;

      fill.style.strokeDasharray = circumference;
      fill.style.strokeDashoffset = circumference;

      // Color based on GPA
      const pct = gpa / maxGpa;
      let color;
      if (pct >= 0.85) color = "#00d4aa";
      else if (pct >= 0.7) color = "#54a0ff";
      else if (pct >= 0.5) color = "#ff9f43";
      else color = "#ff6b6b";

      fill.style.stroke = color;

      // Animate on enter
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              const offset = circumference - pct * circumference;
              fill.style.strokeDashoffset = offset;

              // Count up GPA value
              this.countUp(valueEl, 0, gpa, 1200, 2);
              observer.unobserve(ring);
            }
          });
        },
        { threshold: 0.3 },
      );

      observer.observe(ring);
    });
  },

  countUp(el, from, to, duration, decimals = 0) {
    const start = performance.now();
    const range = to - from;

    const step = (timestamp) => {
      const elapsed = timestamp - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); // ease out cubic
      const value = from + range * eased;

      el.textContent = value.toFixed(decimals);

      if (progress < 1) requestAnimationFrame(step);
    };

    requestAnimationFrame(step);
  },

  // ── Live Grade Calculator ───────────────────────────────────
  initLiveGradeCalc() {
    const gradeRows = document.querySelectorAll("[data-grade-row]");
    if (!gradeRows.length) return;

    gradeRows.forEach((row) => {
      const inputs = row.querySelectorAll(".grade-input[data-weight]");
      inputs.forEach((input) => {
        input.addEventListener("input", () => this.calculateRowGrade(row));
      });
      this.calculateRowGrade(row); // initial calc
    });

    this.recalcAllRows();
  },

  calculateRowGrade(row) {
    const inputs = row.querySelectorAll(".grade-input[data-weight]");
    const finalEl = row.querySelector(".final-grade-display");
    const remarkEl = row.querySelector(".grade-remark");

    if (!finalEl) return;

    let total = 0;
    let weight = 0;
    let valid = true;

    inputs.forEach((input) => {
      const val = parseFloat(input.value);
      const w = parseFloat(input.dataset.weight || 1);

      if (input.value.trim() === "") return; // skip empty

      if (isNaN(val) || val < 0 || val > 100) {
        input.classList.add("is-invalid");
        valid = false;
        return;
      }

      input.classList.remove("is-invalid");
      total += val * w;
      weight += w;
    });

    if (!valid || weight === 0) {
      finalEl.textContent = "—";
      finalEl.className = "final-grade-display";
      if (remarkEl) remarkEl.textContent = "";
      return;
    }

    const final = total / weight;
    finalEl.textContent = final.toFixed(2);

    // Apply color class
    finalEl.className =
      "final-grade-display grade-score " + this.getGradeClass(final);

    // Remark
    if (remarkEl) {
      remarkEl.textContent = this.getGradeRemark(final);
    }

    // Highlight changed row
    row.style.background = "rgba(108, 99, 255, 0.06)";
    clearTimeout(row._highlightTimer);
    row._highlightTimer = setTimeout(() => {
      row.style.background = "";
    }, 1200);
  },

  recalcAllRows() {
    document.querySelectorAll("[data-grade-row]").forEach((row) => {
      this.calculateRowGrade(row);
    });
  },

  getGradeClass(score) {
    if (score >= 90) return "excellent";
    if (score >= 80) return "good";
    if (score >= 70) return "average";
    if (score >= 60) return "poor";
    return "failed";
  },

  getGradeRemark(score) {
    if (score >= 90) return "Excellent";
    if (score >= 80) return "Good";
    if (score >= 75) return "Satisfactory";
    if (score >= 70) return "Fair";
    if (score >= 60) return "Needs Improvement";
    return "Failed";
  },

  // ── Grade Input Validation ───────────────────────────────────
  initGradeInputValidation() {
    document.querySelectorAll(".grade-input").forEach((input) => {
      input.addEventListener("input", () => {
        const val = parseFloat(input.value);
        const min = parseFloat(input.min || 0);
        const max = parseFloat(input.max || 100);

        if (input.value === "") {
          input.classList.remove("is-invalid", "is-valid");
          return;
        }

        if (isNaN(val) || val < min || val > max) {
          input.classList.add("is-invalid");
          input.classList.remove("is-valid");
        } else {
          input.classList.add("is-valid");
          input.classList.remove("is-invalid");
        }
      });

      // Prevent non-numeric input
      input.addEventListener("keypress", (e) => {
        if (
          !/[\d.]/.test(e.key) &&
          !["Backspace", "Tab", "Enter", "ArrowLeft", "ArrowRight"].includes(
            e.key,
          )
        ) {
          e.preventDefault();
        }
      });
    });
  },

  // ── Colorize Grade Scores ────────────────────────────────────
  colorizeGradeScores() {
    document.querySelectorAll(".grade-score[data-score]").forEach((el) => {
      const score = parseFloat(el.dataset.score);
      if (!isNaN(score)) {
        el.classList.add(this.getGradeClass(score));
      }
    });
  },
};

/* ══════════════════════════════════════════════════════════════
   12. CHARTS MODULE (Chart.js wrapper)
══════════════════════════════════════════════════════════════ */
const Charts = {
  defaults: {
    fontFamily: "Inter, sans-serif",
    color: "rgba(255,255,255,0.65)",
    gridColor: "rgba(255,255,255,0.06)",
    borderColor: "rgba(255,255,255,0.08)",
  },

  instances: {},

  init() {
    if (typeof Chart === "undefined") return;

    this.setGlobalDefaults();
    this.initDashboardCharts();
    this.initReportCharts();
    this.initStudentCharts();
  },

  setGlobalDefaults() {
    Chart.defaults.font.family = this.defaults.fontFamily;
    Chart.defaults.color = this.defaults.color;
    Chart.defaults.plugins.legend.labels.color = this.defaults.color;
    Chart.defaults.plugins.tooltip.backgroundColor = "rgba(19,19,45,0.95)";
    Chart.defaults.plugins.tooltip.borderColor = "rgba(255,255,255,0.10)";
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.titleColor = "#fff";
    Chart.defaults.plugins.tooltip.bodyColor = "rgba(255,255,255,0.70)";
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.scale.grid.color = this.defaults.gridColor;
    Chart.defaults.scale.border.color = this.defaults.borderColor;
    Chart.defaults.scale.ticks.color = this.defaults.color;
  },

  // ── Dashboard Charts ─────────────────────────────────────────
  initDashboardCharts() {
    this.makeGradeDistributionChart();
    this.makeEnrollmentTrendChart();
    this.makePassFailChart();
    this.makeTopSubjectsChart();
  },

  makeGradeDistributionChart() {
    const canvas = document.getElementById("grade-distribution-chart");
    if (!canvas) return;

    const data = JSON.parse(canvas.dataset.chartData || "{}");
    const labels = data.labels || [
      "90-100",
      "80-89",
      "70-79",
      "60-69",
      "Below 60",
    ];
    const values = data.values || [0, 0, 0, 0, 0];

    this.instances["gradeDistribution"] = new Chart(canvas, {
      type: "bar",
      data: {
        labels,
        datasets: [
          {
            label: "Students",
            data: values,
            backgroundColor: [
              "rgba(0,212,170,0.75)",
              "rgba(84,160,255,0.75)",
              "rgba(255,159,67,0.75)",
              "rgba(255,107,107,0.75)",
              "rgba(108,99,255,0.75)",
            ],
            borderColor: [
              "#00d4aa",
              "#54a0ff",
              "#ff9f43",
              "#ff6b6b",
              "#6c63ff",
            ],
            borderWidth: 1.5,
            borderRadius: 6,
            borderSkipped: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => ` ${ctx.parsed.y} students`,
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
          },
        },
      },
    });
  },

  makeEnrollmentTrendChart() {
    const canvas = document.getElementById("enrollment-trend-chart");
    if (!canvas) return;

    const data = JSON.parse(canvas.dataset.chartData || "{}");
    const labels = data.labels || [];
    const values = data.values || [];

    this.instances["enrollmentTrend"] = new Chart(canvas, {
      type: "line",
      data: {
        labels,
        datasets: [
          {
            label: "Enrollments",
            data: values,
            borderColor: "#6c63ff",
            backgroundColor: "rgba(108,99,255,0.12)",
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: "#6c63ff",
            pointBorderColor: "#fff",
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
        },
        scales: {
          y: { beginAtZero: true },
        },
      },
    });
  },

  makePassFailChart() {
    const canvas = document.getElementById("pass-fail-chart");
    if (!canvas) return;

    const data = JSON.parse(canvas.dataset.chartData || "{}");
    const passed = data.passed || 0;
    const failed = data.failed || 0;

    this.instances["passFail"] = new Chart(canvas, {
      type: "doughnut",
      data: {
        labels: ["Passed", "Failed"],
        datasets: [
          {
            data: [passed, failed],
            backgroundColor: ["rgba(0,212,170,0.80)", "rgba(255,107,107,0.80)"],
            borderColor: ["#00d4aa", "#ff6b6b"],
            borderWidth: 2,
            hoverOffset: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "70%",
        plugins: {
          legend: {
            position: "bottom",
            labels: { padding: 16, usePointStyle: true, pointStyleWidth: 10 },
          },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const total = passed + failed;
                const pct =
                  total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                return ` ${ctx.parsed} (${pct}%)`;
              },
            },
          },
        },
      },
    });
  },

  makeTopSubjectsChart() {
    const canvas = document.getElementById("top-subjects-chart");
    if (!canvas) return;

    const data = JSON.parse(canvas.dataset.chartData || "{}");
    const labels = data.labels || [];
    const averages = data.averages || [];

    this.instances["topSubjects"] = new Chart(canvas, {
      type: "bar",
      data: {
        labels,
        datasets: [
          {
            label: "Average Grade",
            data: averages,
            backgroundColor: "rgba(108,99,255,0.70)",
            borderColor: "#6c63ff",
            borderWidth: 1.5,
            borderRadius: 6,
            borderSkipped: false,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
        },
        scales: {
          x: {
            min: 0,
            max: 100,
            ticks: { callback: (v) => v + "%" },
          },
        },
      },
    });
  },

  // ── Report Charts ────────────────────────────────────────────
  initReportCharts() {
    this.makeGPATrendChart();
    this.makeSubjectComparisonChart();
  },

  makeGPATrendChart() {
    const canvas = document.getElementById("gpa-trend-chart");
    if (!canvas) return;

    const data = JSON.parse(canvas.dataset.chartData || "{}");
    const labels = data.labels || [];
    const gpas = data.gpas || [];

    this.instances["gpaTrend"] = new Chart(canvas, {
      type: "line",
      data: {
        labels,
        datasets: [
          {
            label: "Average GPA",
            data: gpas,
            borderColor: "#00d4aa",
            backgroundColor: "rgba(0,212,170,0.10)",
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: "#00d4aa",
            pointBorderColor: "#fff",
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
        },
        scales: {
          y: { min: 0, max: 4 },
        },
      },
    });
  },

  makeSubjectComparisonChart() {
    const canvas = document.getElementById("subject-comparison-chart");
    if (!canvas) return;

    const data = JSON.parse(canvas.dataset.chartData || "{}");
    const labels = data.labels || [];
    const avg = data.averages || [];
    const highest = data.highest || [];
    const lowest = data.lowest || [];

    this.instances["subjectComparison"] = new Chart(canvas, {
      type: "bar",
      data: {
        labels,
        datasets: [
          {
            label: "Average",
            data: avg,
            backgroundColor: "rgba(108,99,255,0.70)",
            borderColor: "#6c63ff",
            borderWidth: 1.5,
            borderRadius: 4,
          },
          {
            label: "Highest",
            data: highest,
            backgroundColor: "rgba(0,212,170,0.50)",
            borderColor: "#00d4aa",
            borderWidth: 1.5,
            borderRadius: 4,
          },
          {
            label: "Lowest",
            data: lowest,
            backgroundColor: "rgba(255,107,107,0.50)",
            borderColor: "#ff6b6b",
            borderWidth: 1.5,
            borderRadius: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: "top" },
        },
        scales: {
          y: { min: 0, max: 100 },
        },
      },
    });
  },

  // ── Student Charts ───────────────────────────────────────────
  initStudentCharts() {
    this.makeStudentGradeRadar();
    this.makeStudentProgressChart();
  },

  makeStudentGradeRadar() {
    const canvas = document.getElementById("student-radar-chart");
    if (!canvas) return;

    const data = JSON.parse(canvas.dataset.chartData || "{}");
    const labels = data.labels || [];
    const scores = data.scores || [];

    this.instances["studentRadar"] = new Chart(canvas, {
      type: "radar",
      data: {
        labels,
        datasets: [
          {
            label: "Grade (%)",
            data: scores,
            borderColor: "#6c63ff",
            backgroundColor: "rgba(108,99,255,0.15)",
            borderWidth: 2,
            pointBackgroundColor: "#6c63ff",
            pointBorderColor: "#fff",
            pointBorderWidth: 2,
            pointRadius: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          r: {
            min: 0,
            max: 100,
            ticks: {
              stepSize: 20,
              backdropColor: "transparent",
            },
            grid: { color: "rgba(255,255,255,0.08)" },
            angleLines: { color: "rgba(255,255,255,0.08)" },
            pointLabels: {
              color: "rgba(255,255,255,0.65)",
              font: { size: 11 },
            },
          },
        },
        plugins: {
          legend: { display: false },
        },
      },
    });
  },

  makeStudentProgressChart() {
    const canvas = document.getElementById("student-progress-chart");
    if (!canvas) return;

    const data = JSON.parse(canvas.dataset.chartData || "{}");
    const labels = data.labels || [];
    const gpas = data.gpas || [];

    this.instances["studentProgress"] = new Chart(canvas, {
      type: "line",
      data: {
        labels,
        datasets: [
          {
            label: "GPA",
            data: gpas,
            borderColor: "#ff6b9d",
            backgroundColor: "rgba(255,107,157,0.10)",
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: "#ff6b9d",
            pointBorderColor: "#fff",
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
        },
        scales: {
          y: { min: 0, max: 4 },
        },
      },
    });
  },

  // ── Destroy & Rebuild ────────────────────────────────────────
  destroy(key) {
    if (this.instances[key]) {
      this.instances[key].destroy();
      delete this.instances[key];
    }
  },

  destroyAll() {
    Object.keys(this.instances).forEach((key) => this.destroy(key));
  },
};

/* ══════════════════════════════════════════════════════════════
   13. API HELPER
══════════════════════════════════════════════════════════════ */
const API = {
  baseUrl: "../api/grades.php",

  async request(endpoint, options = {}) {
    const token =
      document.querySelector('meta[name="api-token"]')?.content || "";

    const defaults = {
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        "X-Requested-With": "XMLHttpRequest",
      },
    };

    const config = { ...defaults, ...options };
    config.headers = { ...defaults.headers, ...(options.headers || {}) };

    try {
      const res = await fetch(endpoint || this.baseUrl, config);

      if (res.status === 401) {
        Toast.error("Session expired. Please log in again.");
        setTimeout(() => (window.location.href = "../auth/login.php"), 2000);
        return null;
      }

      if (res.status === 403) {
        Toast.error("Access denied.");
        return null;
      }

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.message || `HTTP ${res.status}`);
      }

      return data;
    } catch (err) {
      console.error("[API Error]", err);
      Toast.error(err.message || "A network error occurred.");
      return null;
    }
  },

  get(endpoint) {
    return this.request(endpoint, { method: "GET" });
  },
  post(endpoint, body) {
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(body),
    });
  },
  put(endpoint, body) {
    return this.request(endpoint, {
      method: "PUT",
      body: JSON.stringify(body),
    });
  },
  delete(endpoint) {
    return this.request(endpoint, { method: "DELETE" });
  },
};

/* ══════════════════════════════════════════════════════════════
   14. FORM HELPERS
══════════════════════════════════════════════════════════════ */
const FormHelper = {
  // Serialize form to plain object
  serialize(form) {
    const data = {};
    new FormData(form).forEach((value, key) => {
      if (data[key] !== undefined) {
        if (!Array.isArray(data[key])) data[key] = [data[key]];
        data[key].push(value);
      } else {
        data[key] = value;
      }
    });
    return data;
  },

  // Populate form fields from object
  populate(form, data) {
    Object.entries(data).forEach(([key, value]) => {
      const field = form.querySelector(`[name="${key}"]`);
      if (!field) return;

      if (field.type === "checkbox") {
        field.checked = Boolean(value);
      } else if (field.type === "radio") {
        const radio = form.querySelector(`[name="${key}"][value="${value}"]`);
        if (radio) radio.checked = true;
      } else {
        field.value = value ?? "";
      }
    });
  },

  // Reset validation states
  resetValidation(form) {
    form.querySelectorAll(".is-invalid, .is-valid").forEach((el) => {
      el.classList.remove("is-invalid", "is-valid");
    });
    form.querySelectorAll(".form-error").forEach((el) => el.remove());
  },

  // Add loading state to submit button
  setSubmitLoading(form, loading = true) {
    const btn = form.querySelector('[type="submit"]');
    if (!btn) return;

    if (loading) {
      btn.dataset.originalText = btn.innerHTML;
      btn.innerHTML = '<span class="spinner"></span> Processing...';
      btn.disabled = true;
    } else {
      btn.innerHTML = btn.dataset.originalText || "Submit";
      btn.disabled = false;
    }
  },
};

/* ══════════════════════════════════════════════════════════════
   15. UTILITY HELPERS
══════════════════════════════════════════════════════════════ */
const Utils = {
  // Debounce
  debounce(fn, delay = 300) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  },

  // Throttle
  throttle(fn, limit = 300) {
    let inThrottle;
    return (...args) => {
      if (!inThrottle) {
        fn(...args);
        inThrottle = true;
        setTimeout(() => (inThrottle = false), limit);
      }
    };
  },

  // Format numbers
  formatNumber(n) {
    return Number(n).toLocaleString();
  },

  // Format GPA
  formatGPA(gpa) {
    return parseFloat(gpa || 0).toFixed(2);
  },

  // Get initials from name
  getInitials(name = "") {
    return name
      .split(" ")
      .slice(0, 2)
      .map((w) => w[0] || "")
      .join("")
      .toUpperCase();
  },

  // Copy text to clipboard
  async copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      Toast.success("Copied to clipboard!");
    } catch {
      Toast.error("Could not copy to clipboard.");
    }
  },

  // Format date to readable
  formatDate(dateStr) {
    if (!dateStr) return "—";
    const d = new Date(dateStr);
    return d.toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  },

  // Get grade letter
  getGradeLetter(score) {
    if (score >= 93) return "A";
    if (score >= 90) return "A-";
    if (score >= 87) return "B+";
    if (score >= 83) return "B";
    if (score >= 80) return "B-";
    if (score >= 77) return "C+";
    if (score >= 73) return "C";
    if (score >= 70) return "C-";
    if (score >= 60) return "D";
    return "F";
  },

  // Escape HTML
  escapeHtml(str = "") {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return String(str).replace(/[&<>"']/g, (m) => map[m]);
  },
};

/* ══════════════════════════════════════════════════════════════
   16. CSV EXPORT HELPER
══════════════════════════════════════════════════════════════ */
const CSVExport = {
  fromTable(tableId, filename = "export.csv") {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = table.querySelectorAll("tr");
    const csvRows = [];

    rows.forEach((row) => {
      const cells = row.querySelectorAll("th, td");
      const data = Array.from(cells).map((cell) => {
        let text = cell.textContent.trim().replace(/\s+/g, " ");
        // Escape commas and quotes
        if (text.includes(",") || text.includes('"')) {
          text = `"${text.replace(/"/g, '""')}"`;
        }
        return text;
      });
      csvRows.push(data.join(","));
    });

    this.download(csvRows.join("\n"), filename);
  },

  fromData(data = [], filename = "export.csv") {
    if (!data.length) return;

    const headers = Object.keys(data[0]);
    const rows = [
      headers.join(","),
      ...data.map((row) =>
        headers
          .map((h) => {
            let val = String(row[h] ?? "").replace(/"/g, '""');
            return val.includes(",") ? `"${val}"` : val;
          })
          .join(","),
      ),
    ];

    this.download(rows.join("\n"), filename);
  },

  download(content, filename) {
    const blob = new Blob(["\uFEFF" + content], {
      type: "text/csv;charset=utf-8;",
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
    Toast.success(`${filename} downloaded!`);
  },
};

/* ══════════════════════════════════════════════════════════════
   17. PRINT HELPER
══════════════════════════════════════════════════════════════ */
const PrintHelper = {
  print(elementId) {
    const el = document.getElementById(elementId);
    if (!el) {
      window.print();
      return;
    }

    const clone = el.cloneNode(true);
    const win = window.open("", "_blank");
    win.document.write(`
      <html>
        <head>
          <title>Print</title>
          <style>
            body { font-family: Arial, sans-serif; color: #000; background: #fff; padding: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
            th { background: #f0f0f0; }
            .badge, .btn { display: none; }
            @media print { body { print-color-adjust: exact; } }
          </style>
        </head>
        <body>${clone.outerHTML}</body>
      </html>
    `);
    win.document.close();
    win.focus();
    setTimeout(() => {
      win.print();
      win.close();
    }, 500);
  },
};

/* ══════════════════════════════════════════════════════════════
   18. GLOBAL EXPOSE (for inline PHP onclick handlers)
══════════════════════════════════════════════════════════════ */
window.App = App;
window.Modal = Modal;
window.Toast = Toast;
window.API = API;
window.GradeUI = GradeUI;
window.Charts = Charts;
window.Table = Table;
window.FormHelper = FormHelper;
window.Utils = Utils;
window.CSVExport = CSVExport;
window.PrintHelper = PrintHelper;


/* ══════════════════════════════════════════════════════════════
   GLOBAL ALIASES
   Some pages call showModal/closeModal/filterTable as globals.
   Map them to the Modal module and Table module.
══════════════════════════════════════════════════════════════ */

function showModal(id) {
  Modal.open(id);
}

function closeModal(id) {
  const el = typeof id === 'string' ? document.getElementById(id) : id;
  Modal.close(el);
}

function filterTable(input, tableId) {
  const table = typeof tableId === 'string'
    ? document.getElementById(tableId)
    : tableId;
  if (table) Table.filterTable(table, input.value);
}

/* ── Sidebar: also target #sidebar and #main IDs ── */
document.addEventListener('DOMContentLoaded', () => {
  // Patch Sidebar module to also find #sidebar and #main
  const sidebarEl = document.querySelector('.sidebar') || document.getElementById('sidebar');
  const mainEl    = document.querySelector('.main-content') || document.getElementById('main');
  const topbarEl  = document.querySelector('.topbar');

  if (sidebarEl && !Sidebar.el) {
    Sidebar.el          = sidebarEl;
    Sidebar.mainContent = mainEl;
    Sidebar.topbar      = topbarEl;
    Sidebar.bindToggle();
    Sidebar.bindMobileMenu();
    Sidebar.setActiveNavItem();
  }

  // Flash messages via data attributes (for pages using div#flash-messages)
  Toast.showFlashMessages();
});
