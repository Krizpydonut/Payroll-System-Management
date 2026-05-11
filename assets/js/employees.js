/**
 * assets/js/employees.js
 * Consolidated Logic for Modern UI and API interactions
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Load Initial Data
  loadDropdowns();
  loadEmployees();

  // 2. Initialize Global Dropdown Click Handler for the Modern UI
  initModernDropdowns();
});

// ==========================================
// API CALL: Load Modal & Filter Dropdowns
// ==========================================
async function loadDropdowns() {
  try {
    const response = await fetch('../api/employees.php?action=dropdowns');
    const data = await response.json();
    
    if (data.status === 'success') {
      // A. Populate Standard Modal Selects (Add/Edit Employee)
      const deptSelect = document.getElementById('fDept');
      const posSelect = document.getElementById('fPos');
      
      deptSelect.innerHTML = '<option value="">-- Select Dept --</option>';
      posSelect.innerHTML = '<option value="">-- Select Position --</option>';

      data.departments.forEach(d => {
          deptSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
      });
      data.positions.forEach(p => {
          posSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`;
      });

      // B. Populate THE MODERN Department Filter List (The UL menu)
      const deptMenu = document.getElementById('deptDropdownMenu');
      if (deptMenu) {
        deptMenu.innerHTML = '<li class="dropdown-option active" data-value="">All Departments</li>';
        data.departments.forEach(d => {
          const li = document.createElement('li');
          li.className = 'dropdown-option';
          li.dataset.value = d.id;
          li.textContent = d.name;
          deptMenu.appendChild(li);
        });
      }
    }
  } catch (error) {
    console.error("Failed to load dropdowns:", error);
  }
}

// ==========================================
// API CALL: Load Main Employee Table
// ==========================================
async function loadEmployees() {
  const tbody = document.getElementById('empBody');
  if (!tbody) return;

  tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 20px; color: var(--text3);">Syncing with database...</td></tr>`;

  try {
    const response = await fetch('../api/employees.php');
    const employees = await response.json();
    
    // Handle cases where the API might return an error object instead of an array
    if (employees.error) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; color: var(--red);">${employees.error}</td></tr>`;
        return;
    }

    renderEmployeesTable(employees);
  } catch (error) {
    console.error("Failed to load employees:", error);
    tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; color: var(--red);">API Connection Failed.</td></tr>`;
  }
}

function renderEmployeesTable(list) {
  const tbody = document.getElementById('empBody');
  tbody.innerHTML = '';
  const counter = document.getElementById('empCount');
  if (counter) counter.textContent = `(${list.length})`;

  if (list.length === 0) {
      tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 20px;">No records found.</td></tr>`;
      return;
  }

  list.forEach(emp => {
    const tr = document.createElement('tr');
    tr.dataset.dept   = emp.dept_id;
    tr.dataset.status = emp.status;
    tr.dataset.name   = (emp.firstName + ' ' + emp.lastName).toLowerCase();
    
    const actionText = emp.status === 'active' ? 'Deactivate' : 'Activate';
    
    tr.innerHTML = `
      <td class="emp-code">${emp.code}</td>
      <td class="emp-name">${emp.firstName} ${emp.lastName}</td>
      <td>${emp.department_name || 'N/A'}</td>
      <td>${emp.position_name || 'N/A'}</td>
      <td><span class="badge badge-${emp.type === 'monthly' ? 'approved' : 'draft'}">${emp.type}</span></td>
      <td class="num">${emp.type === 'monthly' ? '₱'+Number(emp.rate).toLocaleString() : '₱'+Number(emp.rate).toLocaleString()+'/day'}</td>
      <td>${emp.hired}</td>
      <td><span class="badge badge-${emp.status === 'active' ? 'paid' : 'draft'}">${emp.status}</span></td>
      <td style="display: flex; gap: 6px; justify-content: flex-end;">
        <button class="btn-outline" style="padding:4px 10px;font-size:11px;" onclick="deactivate(${emp.id})">${actionText}</button>
        <button class="btn-outline" style="padding:4px 10px;font-size:11px;color:var(--red);border-color:var(--red);" onclick="deleteEmployee(${emp.id})">Delete</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

// ==========================================
// API CALL: Save / Insert Employee
// ==========================================
async function saveEmployee() {
  const payload = {
    action: 'add',
    first_name: document.getElementById('fFirstName').value,
    last_name: document.getElementById('fLastName').value,
    middle_name: document.getElementById('fMiddleName').value,
    gender: document.getElementById('fGender').value,
    birthdate: document.getElementById('fBirthdate').value,
    email: document.getElementById('fEmail').value,
    phone: document.getElementById('fPhone').value,
    date_hired: document.getElementById('fHired').value,
    department_id: document.getElementById('fDept').value,
    position_id: document.getElementById('fPos').value,
    employment_type: document.getElementById('fType').value,
    salary_rate: document.getElementById('fRate').value,
    address: document.getElementById('fAddress').value
  };

  if (!payload.first_name || !payload.last_name || !payload.department_id || !payload.position_id || !payload.salary_rate) {
      alert("Missing required fields (*)");
      return;
  }

  try {
    const response = await fetch('../api/employees.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
      closeEmpModal();
      alert(`Success! Generated Code: ${result.data.code}`);
      loadEmployees();
    } else {
      alert("Database Error: " + result.message);
    }
  } catch (error) {
    console.error("Save error:", error);
    alert("Server unreachable. Check if api/employees.php exists.");
  }
}

// ==========================================
// MODERN UI: Dropdown Logic
// ==========================================
function initModernDropdowns() {
  document.addEventListener('click', (e) => {
    const dropdown = e.target.closest('.custom-dropdown');
    
    // Close other dropdowns
    document.querySelectorAll('.custom-dropdown.open').forEach(d => {
      if (d !== dropdown) d.classList.remove('open');
    });

    if (!dropdown) return;

    const trigger = e.target.closest('.dropdown-trigger');
    const option = e.target.closest('.dropdown-option');

    if (trigger) dropdown.classList.toggle('open');

    if (option) {
      const hiddenInput = dropdown.querySelector('input[type="hidden"]');
      const selectedText = dropdown.querySelector('.selected-text');
      const allOptions = dropdown.querySelectorAll('.dropdown-option');

      allOptions.forEach(opt => opt.classList.remove('active'));
      option.classList.add('active');

      hiddenInput.value = option.dataset.value;
      selectedText.textContent = option.textContent;
      dropdown.classList.remove('open');

      // Trigger the local table filter
      filterEmployees();
    }
  });
}

function filterEmployees() {
    const q = document.getElementById('empSearch').value.toLowerCase();
    const dept = document.getElementById('deptFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    document.querySelectorAll('#empBody tr').forEach(tr => {
        if (!tr.dataset.name) return; 
        const nameMatch = tr.dataset.name.includes(q);
        const deptMatch = !dept || tr.dataset.dept === dept;
        const statusMatch = !status || tr.dataset.status === status;
        tr.style.display = (nameMatch && deptMatch && statusMatch) ? '' : 'none';
    });
}

// ==========================================
// MODAL CONTROLS
// ==========================================
async function deactivate(id) {
  if (!confirm(`Update status for employee ID: ${id}?`)) return;
  try {
    const response = await fetch('../api/employees.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_status', employee_id: id })
    });
    const result = await response.json();
    if (result.status === 'success') loadEmployees(); 
  } catch (error) { console.error("Update error:", error); }
}

async function deleteEmployee(id) {
  if (!confirm("Move this employee to Archive?")) return;
  try {
      const response = await fetch('../api/employees.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', employee_id: id })
      });
      const result = await response.json();
      if (result.status === 'success') loadEmployees();
  } catch (error) { console.error("Delete Error:", error); }
}

function openAddModal() {
  document.getElementById('empModalTitle').textContent = 'Add Employee';
  ['fFirstName','fLastName','fMiddleName','fEmail','fPhone','fAddress','fRate'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fGender').value = '';
  document.getElementById('fBirthdate').value = '';
  document.getElementById('fHired').value = new Date().toISOString().split('T')[0];
  document.getElementById('empModal').style.display = 'flex';
}

function closeEmpModal() { document.getElementById('empModal').style.display = 'none'; }