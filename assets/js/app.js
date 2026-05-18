/* ============================================================
   GradeMS — Main Application JavaScript
   Handles: navigation, page rendering, UI interactions
   ============================================================ */

'use strict';

/* ── MOCK DATA (replace with PHP/fetch calls in production) ── */
const DATA = {
  users: [
    { id:1, name:'Dr. John Admin',   email:'admin@school.edu',    role:'Admin',   status:'Active',   created:'Jan 2024' },
    { id:2, name:'Prof. Ana Reyes',  email:'ana.reyes@school.edu',role:'Teacher', status:'Active',   created:'Jan 2024' },
    { id:3, name:'Prof. Mark Torres',email:'mark.t@school.edu',   role:'Teacher', status:'Active',   created:'Feb 2024' },
    { id:4, name:'Maria Santos',     email:'maria.s@school.edu',  role:'Student', status:'Active',   created:'Jun 2024' },
    { id:5, name:'Juan Dela Cruz',   email:'juan.dc@school.edu',  role:'Student', status:'Active',   created:'Jun 2024' },
    { id:6, name:'Ana Lim',          email:'ana.lim@school.edu',  role:'Student', status:'Inactive', created:'Jun 2024' },
    { id:7, name:'Carlo Ramos',      email:'carlo.r@school.edu',  role:'Student', status:'Active',   created:'Jun 2024' },
  ],
  students: [
    { num:'2024-0001', name:'Maria Santos',   course:'BSCS', year:'2nd Year', section:'A' },
    { num:'2024-0002', name:'Juan Dela Cruz', course:'BSIT', year:'1st Year', section:'B' },
    { num:'2024-0003', name:'Ana Lim',        course:'BSCS', year:'3rd Year', section:'A' },
    { num:'2024-0004', name:'Carlo Ramos',    course:'BSEd', year:'2nd Year', section:'C' },
    { num:'2024-0005', name:'Rina Flores',    course:'BSA',  year:'1st Year', section:'A' },
    { num:'2024-0006', name:'Leo Bautista',   course:'BSCS', year:'4th Year', section:'B' },
    { num:'2024-0007', name:'Joy Mendoza',    course:'BSIT', year:'3rd Year', section:'A' },
  ],
  subjects: [
    { code:'MATH101', name:'Mathematics 101',   units:3, teacher:'Prof. Ana Reyes',  enrolled:35 },
    { code:'PHY201',  name:'Physics 201',        units:4, teacher:'Prof. Mark Torres',enrolled:28 },
    { code:'ENG101',  name:'English 101',        units:3, teacher:'Dr. Lisa Garcia', enrolled:40 },
    { code:'CS101',   name:'CS Fundamentals',    units:3, teacher:'Prof. Ana Reyes', enrolled:33 },
    { code:'HIST101', name:'Philippine History', units:3, teacher:'Prof. Jay Santos',enrolled:45 },
  ],
  enrollments: [
    { student:'Maria Santos',   subject:'Mathematics 101', sem:'1st Sem 2024-25', status:'Active' },
    { student:'Juan Dela Cruz', subject:'Physics 201',     sem:'1st Sem 2024-25', status:'Active' },
    { student:'Ana Lim',        subject:'English 101',     sem:'1st Sem 2024-25', status:'Active' },
    { student:'Carlo Ramos',    subject:'CS Fundamentals', sem:'1st Sem 2024-25', status:'Active' },
    { student:'Rina Flores',    subject:'Mathematics 101', sem:'1st Sem 2024-25', status:'Active' },
  ],
  gradesAdmin: [
    { student:'Maria Santos',   subject:'Mathematics 101', mid:88, fin:92, final:90.0, remarks:'Passed',     locked:false },
    { student:'Juan Dela Cruz', subject:'Physics 201',     mid:72, fin:68, final:70.0, remarks:'Failed',     locked:false },
    { student:'Ana Lim',        subject:'English 101',     mid:95, fin:97, final:96.0, remarks:'Passed',     locked:true  },
    { student:'Carlo Ramos',    subject:'CS Fundamentals', mid:80, fin:null,final:null,remarks:'Incomplete', locked:false },
    { student:'Rina Flores',    subject:'Mathematics 101', mid:60, fin:62, final:61.0, remarks:'Failed',     locked:false },
    { student:'Leo Bautista',   subject:'Mathematics 101', mid:91, fin:89, final:90.0, remarks:'Passed',     locked:true  },
    { student:'Joy Mendoza',    subject:'CS Fundamentals', mid:85, fin:88, final:86.5, remarks:'Passed',     locked:false },
  ],
  gradeEntry: [
    { num:'2024-0001', name:'Maria Santos',   mid:88, fin:92   },
    { num:'2024-0002', name:'Juan Dela Cruz', mid:72, fin:68   },
    { num:'2024-0003', name:'Ana Lim',        mid:95, fin:97   },
    { num:'2024-0004', name:'Carlo Ramos',    mid:80, fin:null },
    { num:'2024-0005', name:'Rina Flores',    mid:60, fin:62   },
    { num:'2024-0006', name:'Leo Bautista',   mid:91, fin:89   },
  ],
  studentGrades: [
    { subject:'Mathematics 101',   units:3, mid:88, fin:92,   final:90.0, status:'Passed'     },
    { subject:'Physics 201',        units:4, mid:85, fin:88,   final:86.5, status:'Passed'     },
    { subject:'English 101',        units:3, mid:92, fin:95,   final:93.5, status:'Passed'     },
    { subject:'CS Fundamentals',    units:3, mid:91, fin:89,   final:90.0, status:'Passed'     },
    { subject:'Philippine History', units:3, mid:78, fin:null, final:null, status:'Incomplete' },
  ],
  audit: [
    { action:'Grade updated — Maria Santos · MATH101 · Final: 90',  user:'Prof. Ana Reyes', time:'2 min ago',  color:'#4f9eff' },
    { action:'Student account created — Joy Mendoza',                 user:'Dr. John Admin',  time:'15 min ago', color:'#4ade80' },
    { action:'Grade locked — ENG101 · All students',                  user:'Dr. John Admin',  time:'1 hr ago',   color:'#fbbf24' },
    { action:'New enrollment — Carlo Ramos → CS Fundamentals',        user:'Dr. John Admin',  time:'2 hr ago',   color:'#2dd4bf' },
    { action:'Login — Prof. Mark Torres',                              user:'System',          time:'3 hr ago',   color:'#a78bfa' },
    { action:'Grade updated — Rina Flores · MATH101 · Final: 61',    user:'Prof. Ana Reyes', time:'4 hr ago',   color:'#4f9eff' },
    { action:'Subject created — Philippine History',                   user:'Dr. John Admin',  time:'Yesterday',  color:'#4ade80' },
    { action:'Password reset — Ana Lim',                               user:'Dr. John Admin',  time:'Yesterday',  color:'#fb7185' },
  ],
};

/* ── SESSION ── */
let session = {
  role: 'admin',
  user: { name:'Dr. John Admin', email:'admin@school.edu', initials:'JA' },
};

const ROLE_COLORS = { admin:'#a78bfa', teacher:'#4f9eff', student:'#2dd4bf' };
const ROLE_GRADIENTS = {
  admin:   'linear-gradient(135deg,#a78bfa,#7c3aed)',
  teacher: 'linear-gradient(135deg,#4f9eff,#2563eb)',
  student: 'linear-gradient(135deg,#2dd4bf,#0f766e)',
};

/* ── NAV DEFINITIONS ── */
const NAV_CONFIG = {
  admin: [
    { label:'OVERVIEW',    items:[{ icon:'🏠', text:'Dashboard',    page:'dashboard'    }] },
    { label:'MANAGEMENT',  items:[
      { icon:'👥', text:'Users',       page:'users'       },
      { icon:'🎓', text:'Students',    page:'students'    },
      { icon:'📚', text:'Subjects',    page:'subjects'    },
      { icon:'📅', text:'Semesters',   page:'semesters'   },
      { icon:'📝', text:'Enrollment',  page:'enrollment'  },
    ]},
    { label:'GRADES',      items:[
      { icon:'📊', text:'Grade Records', page:'grades-admin' },
      { icon:'📈', text:'Reports',        page:'reports'      },
    ]},
    { label:'SYSTEM',      items:[
      { icon:'🔗', text:'JSON API',  page:'api'     },
      { icon:'🕵️', text:'Audit Log', page:'audit'   },
      { icon:'👤', text:'Profile',   page:'profile' },
    ]},
  ],
  teacher: [
    { label:'TEACHING',  items:[
      { icon:'📚', text:'My Subjects',    page:'my-subjects'      },
      { icon:'📊', text:'Manage Grades',  page:'manage-grades'    },
      { icon:'📈', text:'My Reports',     page:'teacher-reports'  },
    ]},
    { label:'ACCOUNT',   items:[
      { icon:'🔗', text:'JSON API', page:'api'     },
      { icon:'👤', text:'Profile',  page:'profile' },
    ]},
  ],
  student: [
    { label:'ACADEMIC', items:[
      { icon:'🏠', text:'Dashboard', page:'student-dashboard' },
      { icon:'📋', text:'My Grades', page:'my-grades'         },
    ]},
    { label:'ACCOUNT',  items:[
      { icon:'👤', text:'Profile', page:'profile' }
    ]},
  ],
};

/* ──────────────────────────────────────────────────────────────
   NAVIGATION
   ────────────────────────────────────────────────────────────── */
function buildSidebar() {
  const sections = NAV_CONFIG[session.role];

  // avatar + user info
  const av = document.getElementById('user-avatar-sidebar');
  av.textContent = session.user.initials;
  av.style.background = ROLE_GRADIENTS[session.role];
  document.getElementById('sidebar-name').textContent = session.user.name;
  document.getElementById('sidebar-role').textContent =
    session.role.charAt(0).toUpperCase() + session.role.slice(1);

  // nav links
  let html = '';
  sections.forEach(s => {
    html += `<div class="sidebar-section">
      <div class="sidebar-label">${s.label}</div>`;
    s.items.forEach(item => {
      html += `<div class="nav-item" data-page="${item.page}"
        onclick="showPage('${item.page}')">
        <span class="nav-icon">${item.icon}</span>${item.text}
      </div>`;
    });
    html += '</div>';
  });
  document.getElementById('nav-container').innerHTML = html;
}

function showPage(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));

  const el = document.getElementById('page-' + page);
  if (el) {
    el.classList.add('active');
    // call page-specific renderer
    const renderers = {
      'dashboard':        renderDashboard,
      'users':            renderUsers,
      'students':         renderStudents,
      'subjects':         renderSubjects,
      'enrollment':       renderEnrollment,
      'grades-admin':     renderGradesAdmin,
      'audit':            renderAudit,
      'my-subjects':      renderTeacherSubjects,
      'manage-grades':    renderGradeEntry,
      'student-dashboard':renderStudentDash,
      'my-grades':        renderMyGrades,
      'reports':          renderReports,
      'teacher-reports':  renderTeacherChart,
      'profile':          renderProfile,
    };
    if (renderers[page]) renderers[page]();
  }

  // highlight active nav item
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const active = document.querySelector(`.nav-item[data-page="${page}"]`);
  if (active) active.classList.add('active');
}

/* ──────────────────────────────────────────────────────────────
   DASHBOARD (Admin)
   ────────────────────────────────────────────────────────────── */
function renderDashboard() {
  const h = new Date().getHours();
  const greetWord = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  document.getElementById('dashboard-greeting').textContent =
    greetWord + ', ' + session.user.name.split(' ')[0] + ' 👋';

  document.getElementById('dashboard-stats').innerHTML = `
    <div class="stat-card blue">  <div class="stat-label">Total Students</div><div class="stat-value">248</div><div class="stat-sub">↑ 12 this month</div>  <div class="stat-icon">🎓</div></div>
    <div class="stat-card purple"><div class="stat-label">Teachers</div>       <div class="stat-value">18</div> <div class="stat-sub">Across 6 departments</div><div class="stat-icon">👩‍🏫</div></div>
    <div class="stat-card teal">  <div class="stat-label">Subjects</div>       <div class="stat-value">32</div> <div class="stat-sub">Active this semester</div><div class="stat-icon">📚</div></div>
    <div class="stat-card green"> <div class="stat-label">Pass Rate</div>      <div class="stat-value">82%</div><div class="stat-sub">↑ 4% from last sem</div> <div class="stat-icon">✅</div></div>
    <div class="stat-card amber"> <div class="stat-label">Grades Pending</div> <div class="stat-value">47</div> <div class="stat-sub">Awaiting submission</div><div class="stat-icon">⏳</div></div>
    <div class="stat-card rose">  <div class="stat-label">At Risk</div>        <div class="stat-value">12</div> <div class="stat-sub">Below passing grade</div><div class="stat-icon">⚠️</div></div>
  `;
  renderGradeChart('grade-chart');
  renderAtRisk();
  renderAuditItems('recent-audit', DATA.audit.slice(0, 4));
}

function renderGradeChart(containerId) {
  const bars = [
    { lbl:'MATH101', total:35, col:'#4f9eff' },
    { lbl:'PHY201',  total:28, col:'#a78bfa' },
    { lbl:'ENG101',  total:40, col:'#2dd4bf' },
    { lbl:'CS101',   total:33, col:'#fbbf24' },
    { lbl:'HIST101', total:45, col:'#4ade80' },
  ];
  const max = 50;
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = bars.map(b => {
    const h = Math.round((b.total / max) * 120);
    return `<div class="chart-bar-col">
      <div class="chart-bar" style="background:${b.col};height:${h}px;opacity:0.85">
        <span>${b.total}</span>
      </div>
      <div class="chart-lbl">${b.lbl}</div>
    </div>`;
  }).join('');
}

function renderAtRisk() {
  const list = [
    { name:'Juan Dela Cruz', subject:'Physics 201',     grade:70 },
    { name:'Rina Flores',    subject:'Mathematics 101', grade:61 },
    { name:'Cris Manalo',    subject:'CS Fundamentals', grade:68 },
    { name:'Beth Cruz',      subject:'Physics 201',     grade:72 },
  ];
  const el = document.getElementById('at-risk-list');
  if (!el) return;
  el.innerHTML = list.map(s => `
    <div style="padding:12px 24px;display:flex;align-items:center;justify-content:space-between;
      border-bottom:1px solid rgba(255,255,255,0.04)">
      <div>
        <div style="font-size:13.5px;font-weight:500;color:var(--text-primary)">${s.name}</div>
        <div style="font-size:12px;color:var(--text-muted)">${s.subject}</div>
      </div>
      <div style="text-align:right">
        <div style="font-size:16px;font-weight:700;color:var(--accent-rose)">${s.grade}</div>
        <div style="font-size:10px;color:var(--text-muted)">Final Grade</div>
      </div>
    </div>`).join('');
}

function renderAuditItems(containerId, items) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = items.map(a => `
    <div class="log-item">
      <div class="log-dot" style="background:${a.color}"></div>
      <div class="log-content">
        <div class="log-action">${a.action}</div>
        <div class="log-meta">${a.user} · ${a.time}</div>
      </div>
    </div>`).join('');
}

/* ──────────────────────────────────────────────────────────────
   ADMIN TABLES
   ────────────────────────────────────────────────────────────── */
function renderUsers() {
  document.getElementById('users-tbody').innerHTML = DATA.users.map(u => `
    <tr>
      <td>${u.name}</td>
      <td class="text-secondary font-mono fs-13">${u.email}</td>
      <td><span class="badge badge-${u.role.toLowerCase()}">${u.role}</span></td>
      <td><span class="badge badge-${u.status.toLowerCase()}">${u.status}</span></td>
      <td class="text-muted">${u.created}</td>
      <td>
        <div style="display:flex;gap:6px">
          <button class="btn btn-glass btn-sm btn-icon" onclick="showToast('Edit user — connect to PHP','info')">✏️</button>
          <button class="btn btn-rose  btn-sm btn-icon" onclick="showToast('Delete user — connect to PHP','error')">🗑</button>
        </div>
      </td>
    </tr>`).join('');
}

function renderStudents() {
  document.getElementById('students-tbody').innerHTML = DATA.students.map(s => `
    <tr>
      <td class="font-mono fs-13">${s.num}</td>
      <td>${s.name}</td>
      <td><span class="badge badge-student">${s.course}</span></td>
      <td class="text-secondary">${s.year}</td>
      <td class="text-muted">${s.section}</td>
      <td>
        <div style="display:flex;gap:6px">
          <button class="btn btn-glass btn-sm btn-icon" onclick="showToast('Edit student — connect to PHP','info')">✏️</button>
          <button class="btn btn-rose  btn-sm btn-icon" onclick="showToast('Delete student — connect to PHP','error')">🗑</button>
        </div>
      </td>
    </tr>`).join('');
}

function renderSubjects() {
  document.getElementById('subjects-tbody').innerHTML = DATA.subjects.map(s => `
    <tr>
      <td class="font-mono text-accent-teal fs-13">${s.code}</td>
      <td>${s.name}</td>
      <td class="text-secondary">${s.units} units</td>
      <td class="text-secondary">${s.teacher}</td>
      <td><span class="badge badge-active">${s.enrolled}</span></td>
      <td>
        <div style="display:flex;gap:6px">
          <button class="btn btn-glass btn-sm btn-icon" onclick="showToast('Edit subject — connect to PHP','info')">✏️</button>
          <button class="btn btn-rose  btn-sm btn-icon" onclick="showToast('Delete subject — connect to PHP','error')">🗑</button>
        </div>
      </td>
    </tr>`).join('');
}

function renderEnrollment() {
  document.getElementById('enroll-tbody').innerHTML = DATA.enrollments.map(e => `
    <tr>
      <td>${e.student}</td>
      <td class="text-secondary">${e.subject}</td>
      <td class="text-muted">${e.sem}</td>
      <td><span class="badge badge-active">${e.status}</span></td>
      <td>
        <button class="btn btn-rose btn-sm"
          onclick="showToast('Unenrolled — connect to PHP','error')">Remove</button>
      </td>
    </tr>`).join('');
}

function renderGradesAdmin() {
  document.getElementById('grades-admin-tbody').innerHTML = DATA.gradesAdmin.map(g => `
    <tr>
      <td>${g.student}</td>
      <td class="text-secondary">${g.subject}</td>
      <td class="text-secondary">${g.mid  ?? '—'}</td>
      <td class="text-secondary">${g.fin  ?? '—'}</td>
      <td class="${g.final ? 'text-accent-blue fw-600' : 'text-muted'}">${g.final ?? '—'}</td>
      <td><span class="badge badge-${g.remarks.toLowerCase()}">${g.remarks}</span></td>
      <td>${g.locked
        ? '<span class="badge badge-locked">🔒 Locked</span>'
        : '<button class="btn btn-glass btn-sm" onclick="showToast(\'Grade locked\',\'info\')">Lock</button>'
      }</td>
    </tr>`).join('');
}

function renderAudit() {
  renderAuditItems('audit-list', DATA.audit);
}

/* ──────────────────────────────────────────────────────────────
   TEACHER PAGES
   ────────────────────────────────────────────────────────────── */
function renderTeacherSubjects() {
  const mySubjects = [
    { code:'MATH101', name:'Mathematics 101', units:3, students:35, graded:28, color:'#4f9eff' },
    { code:'CS101',   name:'CS Fundamentals', units:3, students:33, graded:30, color:'#a78bfa' },
  ];
  document.getElementById('teacher-subjects-grid').innerHTML = mySubjects.map(s => {
    const pct = Math.round((s.graded / s.students) * 100);
    return `<div class="glass-card" style="padding:24px;cursor:pointer"
        onclick="openSubjectGrades('${s.code}','${s.name}')">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div style="font-size:22px;font-weight:700;font-family:var(--font-mono);color:${s.color}">${s.code}</div>
        <span class="badge badge-active">${s.units} units</span>
      </div>
      <div style="font-size:15px;font-weight:600;margin-bottom:4px">${s.name}</div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:16px">1st Semester · AY 2024–2025</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Grade Entry Progress</div>
      <div class="grade-bar">
        <div class="grade-fill" style="width:${pct}%;background:${s.color}"></div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:11px;color:var(--text-muted)">
        <span>${s.graded} graded</span><span>${s.students} total</span>
      </div>
      <button class="btn btn-glass w-full" style="margin-top:16px;justify-content:center">
        Open Grade Sheet →
      </button>
    </div>`;
  }).join('');
}

function openSubjectGrades(code, name) {
  document.getElementById('manage-grades-title').textContent = 'Grades — ' + name;
  document.getElementById('manage-grades-sub').textContent =
    code + ' · 1st Semester · AY 2024–2025';
  showPage('manage-grades');
}

function renderGradeEntry() {
  document.getElementById('grade-entry-tbody').innerHTML = DATA.gradeEntry.map((s, i) => {
    const mid   = s.mid  ?? '';
    const fin   = s.fin  ?? '';
    const final = (s.mid != null && s.fin != null)
      ? ((s.mid * 0.5) + (s.fin * 0.5)).toFixed(1) : null;
    const rem   = final != null ? (parseFloat(final) >= 75 ? 'Passed' : 'Failed') : 'Incomplete';
    const remCls= rem === 'Passed' ? 'badge-passed' : rem === 'Failed' ? 'badge-failed' : 'badge-incomplete';
    const gCol  = final != null
      ? (parseFloat(final) >= 75 ? 'var(--accent-green)' : 'var(--accent-rose)')
      : 'var(--accent-amber)';
    return `<tr>
      <td class="font-mono fs-13">${s.num}</td>
      <td>${s.name}</td>
      <td><input class="form-input" style="width:70px;padding:6px 10px;font-size:13px"
          type="number" min="0" max="100" value="${mid}" placeholder="0–100"
          onchange="recalcRow(this,${i})"></td>
      <td><input class="form-input" style="width:70px;padding:6px 10px;font-size:13px"
          type="number" min="0" max="100" value="${fin}" placeholder="0–100"
          onchange="recalcRow(this,${i})"></td>
      <td id="fg-${i}" class="fw-600" style="color:${gCol}">${final ?? '—'}</td>
      <td id="rem-${i}"><span class="badge ${remCls}">${rem}</span></td>
      <td id="bar-${i}">
        ${final != null
          ? `<div class="grade-bar" style="width:80px">
               <div class="grade-fill" style="width:${Math.min(parseFloat(final),100)}%;background:${gCol}"></div>
             </div>` : ''}
      </td>
    </tr>`;
  }).join('');
}

function recalcRow(input, idx) {
  const row    = input.closest('tr');
  const inputs = row.querySelectorAll('input[type=number]');
  const mid    = parseFloat(inputs[0].value);
  const fin    = parseFloat(inputs[1].value);
  const fgCell  = document.getElementById('fg-'  + idx);
  const remCell = document.getElementById('rem-' + idx);
  const barCell = document.getElementById('bar-' + idx);

  if (!isNaN(mid) && !isNaN(fin)) {
    const fg     = ((mid * 0.5) + (fin * 0.5)).toFixed(1);
    const passed = parseFloat(fg) >= 75;
    const col    = passed ? 'var(--accent-green)' : 'var(--accent-rose)';
    fgCell.textContent  = fg;
    fgCell.style.color  = col;
    remCell.innerHTML   = `<span class="badge ${passed ? 'badge-passed' : 'badge-failed'}">${passed ? 'Passed' : 'Failed'}</span>`;
    barCell.innerHTML   = `<div class="grade-bar" style="width:80px">
      <div class="grade-fill" style="width:${Math.min(parseFloat(fg),100)}%;background:${col}"></div>
    </div>`;
  } else {
    fgCell.textContent  = '—';
    fgCell.style.color  = 'var(--text-muted)';
    remCell.innerHTML   = '<span class="badge badge-incomplete">Incomplete</span>';
    barCell.innerHTML   = '';
  }
}

function saveAllGrades() { showToast('All grades saved successfully!', 'success'); }
function lockAllGrades()  { showToast('All grades locked. No further edits allowed.', 'info'); }

/* ──────────────────────────────────────────────────────────────
   STUDENT PAGES
   ────────────────────────────────────────────────────────────── */
function renderStudentDash() {
  document.getElementById('student-welcome').textContent =
    'Welcome back, ' + session.user.name.split(' ')[0] + ' 👋';

  document.getElementById('student-grades-preview').innerHTML =
    DATA.studentGrades.map(g => {
      const remCls = g.status === 'Passed' ? 'badge-passed'
                   : g.status === 'Failed' ? 'badge-failed' : 'badge-incomplete';
      return `<tr>
        <td>${g.subject}</td>
        <td class="text-muted">${g.units}</td>
        <td class="text-secondary">${g.mid  ?? '—'}</td>
        <td class="text-secondary">${g.fin  ?? '—'}</td>
        <td class="${g.final ? 'text-accent-blue fw-600' : 'text-muted'}">${g.final ?? '—'}</td>
        <td><span class="badge ${remCls}">${g.status}</span></td>
      </tr>`;
    }).join('');
}

function renderMyGrades() {
  document.getElementById('my-grades-tbody').innerHTML =
    DATA.studentGrades.map(g => {
      const remCls = g.status === 'Passed' ? 'badge-passed'
                   : g.status === 'Failed' ? 'badge-failed' : 'badge-incomplete';
      return `<tr>
        <td>${g.subject}</td>
        <td class="text-muted">${g.units}</td>
        <td class="text-secondary">${g.mid  ?? '—'}</td>
        <td class="text-secondary">${g.fin  ?? '—'}</td>
        <td class="${g.final ? 'text-accent-blue fw-600' : 'text-muted'}">${g.final ?? '—'}</td>
        <td><span class="badge ${remCls}">${g.status}</span></td>
      </tr>`;
    }).join('');
}

/* ──────────────────────────────────────────────────────────────
   REPORTS
   ────────────────────────────────────────────────────────────── */
function renderReports() {
  const bars = [
    { lbl:'MATH101', pass:28, fail:5  },
    { lbl:'PHY201',  pass:19, fail:7  },
    { lbl:'ENG101',  pass:36, fail:2  },
    { lbl:'CS101',   pass:25, fail:6  },
  ];
  const max = 40;
  const el = document.getElementById('reports-chart');
  if (el) {
    el.innerHTML = bars.map(b => {
      const hp = Math.round((b.pass / max) * 160);
      const hf = Math.round((b.fail / max) * 160);
      return `<div class="chart-bar-col">
        <div style="display:flex;gap:3px;align-items:flex-end">
          <div class="chart-bar" style="background:#4ade80;opacity:0.8;height:${hp}px;flex:1;border-radius:4px 4px 0 0"><span>${b.pass}</span></div>
          <div class="chart-bar" style="background:#fb7185;opacity:0.8;height:${hf}px;flex:1;border-radius:4px 4px 0 0"><span>${b.fail}</span></div>
        </div>
        <div class="chart-lbl">${b.lbl}</div>
      </div>`;
    }).join('');
  }

  const gpa = document.getElementById('gpa-dist');
  if (gpa) {
    gpa.innerHTML = [
      { range:"Dean's List (GPA ≤ 1.75)", count:23,  color:'#a78bfa', pct:30 },
      { range:"Good Standing (≤ 2.50)",   count:140, color:'#4ade80', pct:70 },
      { range:"At Risk (> 2.50)",         count:72,  color:'#fbbf24', pct:40 },
      { range:"Failed",                    count:13,  color:'#fb7185', pct:15 },
    ].map(d => `
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span style="font-size:12px;color:var(--text-secondary)">${d.range}</span>
          <span style="font-size:12px;font-weight:600;color:${d.color}">${d.count}</span>
        </div>
        <div class="grade-bar">
          <div class="grade-fill" style="width:${d.pct}%;background:${d.color}"></div>
        </div>
      </div>`).join('');
  }
}

function renderTeacherChart() {
  const bars = [
    { lbl:'90–100', count:8,  col:'#4ade80' },
    { lbl:'80–89',  count:14, col:'#4f9eff' },
    { lbl:'75–79',  count:10, col:'#fbbf24' },
    { lbl:'65–74',  count:5,  col:'#fb7185' },
    { lbl:'<65',    count:2,  col:'#f87171' },
  ];
  const max = 14;
  const el = document.getElementById('teacher-chart');
  if (!el) return;
  el.innerHTML = bars.map(b => {
    const h = Math.round((b.count / max) * 140);
    return `<div class="chart-bar-col">
      <div class="chart-bar" style="background:${b.col};opacity:0.8;height:${h}px">
        <span>${b.count}</span>
      </div>
      <div class="chart-lbl">${b.lbl}</div>
    </div>`;
  }).join('');
}

/* ──────────────────────────────────────────────────────────────
   PROFILE
   ────────────────────────────────────────────────────────────── */
function renderProfile() {
  const av = document.getElementById('profile-avatar-display');
  if (av) { av.textContent = session.user.initials; av.style.background = ROLE_GRADIENTS[session.role]; }
  setText('profile-fullname',    session.user.name);
  setText('profile-role-display', session.role.charAt(0).toUpperCase() + session.role.slice(1));
  setText('profile-email-display', session.user.email);
  const parts = session.user.name.split(' ');
  setVal('prof-first', parts[0]);
  setVal('prof-last',  parts[parts.length - 1]);
  setVal('prof-email', session.user.email);
}

function saveProfile() { showToast('Profile updated successfully', 'success'); }

/* ──────────────────────────────────────────────────────────────
   API DEMO
   ────────────────────────────────────────────────────────────── */
function runApiDemo() {
  document.getElementById('api-response').innerHTML =
    '<span style="color:var(--text-muted)">⏳ Sending request...</span>';

  setTimeout(() => {
    const resp = {
      status: 200,
      message: "OK",
      student: { id:5, name:"Maria Santos", student_number:"2024-0001", course:"BSCS", year_level:"2nd Year" },
      semester: "1st Semester AY 2024–2025",
      grades: DATA.studentGrades.map(g => ({
        subject:     g.subject,
        units:       g.units,
        midterm:     g.mid,
        finals:      g.fin,
        final_grade: g.final,
        remarks:     g.status,
      })),
      gpa: 1.68,
      standing: "Dean's List",
    };
    document.getElementById('api-response').innerHTML = syntaxHighlight(JSON.stringify(resp, null, 2));
  }, 600);
}

function syntaxHighlight(json) {
  return json.replace(
    /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
    m => {
      let cls = 'color:#f9a8d4';
      if (/^"/.test(m))      cls = /:$/.test(m) ? 'color:#93c5fd' : 'color:#86efac';
      else if (/true|false/.test(m)) cls = 'color:#fde68a';
      else if (/null/.test(m))       cls = 'color:#94a3b8';
      else                           cls = 'color:#c4b5fd';
      return `<span style="${cls}">${m}</span>`;
    }
  );
}

/* ──────────────────────────────────────────────────────────────
   ENROLLMENT — CLASS LIST
   ────────────────────────────────────────────────────────────── */
function loadClassList(val) {
  const el = document.getElementById('class-list-content');
  if (!val) {
    el.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted)">Select a subject above to view its class list</div>';
    return;
  }
  el.innerHTML = `<table>
    <thead><tr><th>Student No.</th><th>Name</th><th>Course</th><th>Year</th></tr></thead>
    <tbody>${DATA.students.slice(0, 4).map(s =>
      `<tr>
        <td class="font-mono fs-13">${s.num}</td>
        <td>${s.name}</td>
        <td>${s.course}</td>
        <td>${s.year}</td>
      </tr>`
    ).join('')}</tbody>
  </table>`;
}

/* ──────────────────────────────────────────────────────────────
   MODAL HELPERS
   ────────────────────────────────────────────────────────────── */
function showModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function closeSaveModal(id, msg) {
  closeModal(id);
  showToast(msg, 'success');
}

// close on backdrop click
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
  });
  // close on Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
      document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  });
});

/* ──────────────────────────────────────────────────────────────
   TABLE FILTER / SEARCH
   ────────────────────────────────────────────────────────────── */
function filterTable(input, tableId) {
  const q = input.value.toLowerCase();
  document.querySelectorAll(`#${tableId} tbody tr`).forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function filterTableByRole(sel, tableId) {
  const q = sel.value.toLowerCase();
  document.querySelectorAll(`#${tableId} tbody tr`).forEach(r => {
    r.style.display = !q || r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function filterAudit(input) {
  const q = input.value.toLowerCase();
  document.querySelectorAll('#audit-list .log-item').forEach(item => {
    item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

/* ──────────────────────────────────────────────────────────────
   TABS
   ────────────────────────────────────────────────────────────── */
function switchTab(btn, targetId) {
  const parent = btn.closest('.tabs');
  parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  // hide all sibling panels (direct siblings of .tabs)
  let sibling = parent.nextElementSibling;
  while (sibling) { sibling.style.display = 'none'; sibling = sibling.nextElementSibling; }
  document.getElementById(targetId).style.display = '';
}

/* ──────────────────────────────────────────────────────────────
   TOAST
   ────────────────────────────────────────────────────────────── */
let _toastTimer;
function showToast(msg, type = 'info') {
  const icons = { success:'✅', error:'❌', info:'ℹ️' };
  const t = document.getElementById('toast');
  t.innerHTML = `<span>${icons[type] || 'ℹ️'}</span> ${msg}`;
  t.className = `toast ${type} show`;
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}

/* ──────────────────────────────────────────────────────────────
   CRUD ACTION STUBS (wire to PHP fetch calls)
   ────────────────────────────────────────────────────────────── */
function addUser() {
  closeModal('modal-add-user');
  showToast('User account created successfully', 'success');
  renderUsers();
}
function addStudent() {
  closeModal('modal-add-student');
  showToast('Student record added successfully', 'success');
  renderStudents();
}
function addSubject() {
  closeModal('modal-add-subject');
  showToast('Subject created and teacher assigned', 'success');
  renderSubjects();
}
function enrollStudent() {
  closeModal('modal-enroll');
  showToast('Student enrolled successfully', 'success');
  renderEnrollment();
}

/* ──────────────────────────────────────────────────────────────
   UTILS
   ────────────────────────────────────────────────────────────── */
function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function setVal(id, val)  { const el = document.getElementById(id); if (el) el.value = val; }
