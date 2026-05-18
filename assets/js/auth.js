/* ============================================================
   GradeMS — Auth JavaScript (login.php)
   ============================================================ */

'use strict';

const AUTH_DEMO = {
  admin:   { name:'Dr. John Admin',   email:'admin@school.edu',    initials:'JA' },
  teacher: { name:'Prof. Ana Reyes',  email:'ana.reyes@school.edu', initials:'AR' },
  student: { name:'Maria Santos',     email:'maria.s@school.edu',   initials:'MS' },
};

let selectedRole = 'admin';

function selectRole(role, btn) {
  selectedRole = role;
  document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('login-email').value = AUTH_DEMO[role].email;
}

function doLogin() {
  const email = document.getElementById('login-email').value.trim();
  const pass  = document.getElementById('login-pass').value.trim();
  const err   = document.getElementById('auth-error');

  if (!email || !pass) {
    err.textContent = 'Please enter your email and password.';
    err.classList.add('show');
    return;
  }
  err.classList.remove('show');

  // Store session data for the app
  session.role = selectedRole;
  session.user = AUTH_DEMO[selectedRole];

  // Show the app, hide auth
  document.getElementById('auth-page').style.display = 'none';
  document.getElementById('app').style.display = 'flex';

  buildSidebar();

  const startPage = {
    admin:   'dashboard',
    teacher: 'my-subjects',
    student: 'student-dashboard',
  };
  showPage(startPage[selectedRole]);
}

function doLogout() {
  document.getElementById('app').style.display  = 'none';
  document.getElementById('auth-page').style.display = 'flex';
  // Reset role tabs to admin
  document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
  document.querySelector('.role-tab').classList.add('active');
  selectedRole = 'admin';
  document.getElementById('login-email').value = AUTH_DEMO.admin.email;
}

// Allow Enter key on login form
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('login-pass').addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
  });
  document.getElementById('login-email').addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
  });
});
