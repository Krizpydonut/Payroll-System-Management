document.addEventListener('DOMContentLoaded', () => {
  loadDropdowns();
  loadEmployees();
});

// ==========================================
// API CALL: Load Modal Dropdowns
// ==========================================
async function loadDropdowns() {
  try {
    const response = await fetch('../api/employees.php?action=dropdowns');
    const data = await response.json();
    
    if (data.status === 'success') {
      const deptSelect = document.getElementById('fDept');
      const posSelect = document.getElementById('fPos');
      const filterDept = document.getElementById('deptFilter');
      
      deptSelect.innerHTML = '<option value="">-- Select Dept --</option>';
      posSelect.innerHTML = '<option value="">-- Select Position --</option>';

      data.departments.forEach(d => {
          deptSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
          filterDept.innerHTML += `<option value="${d.id}">${d.name}</option>`;
      });
      
      data.positions.forEach(p => {
          posSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`;
      });
    }
  } catch (error) {
    console.error("Failed to load dropdowns:", error);
  }
}

// ==========================================
// API CALL: Load Employee Table
// ==========================================
async function loadEmployees() {
  const tbody = document.getElementById('empBody');
  tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 20px; color: var(--text3);">Loading data from database...</td></tr>`;

  try {
    const response = await fetch('../api/employees.php');
    const employees = await response.json();
    renderEmployeesTable(employees);
  } catch (error) {
    console.error("Failed to load employees:", error);
    tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; color: var(--red);">Error connecting to database API.</td></tr>`;
  }
}

function renderEmployeesTable(list) {
  const tbody = document.getElementById('empBody');
  tbody.innerHTML = '';
  document.getElementById('empCount').textContent = `(${list.length})`;

  if (list.length === 0) {
      tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; color: var(--text3);">No employees found.</td></tr>`;
      return;
  }

  list.forEach(emp => {
    const tr = document.createElement('tr');
    tr.dataset.dept   = emp.dept_id;
    tr.dataset.status = emp.status;
    tr.dataset.name   = (emp.firstName + ' ' + emp.lastName).toLowerCase();
    
    // Set Deactivate button text
    const actionText = emp.status === 'active' ? 'Deactivate' : 'Activate';
    
    tr.innerHTML = `
      <td class="emp-code">${emp.code}</td>
      <td class="emp-name">${emp.firstName} ${emp.lastName}</td>
      <td>${emp.department_name || 'N/A'}</td>
      <td>${emp.position_name || 'N/A'}</td>
      <td><span class="badge badge-${emp.type === 'monthly' ? 'approved' : 'draft'}">${emp.type}</span></td>
      <td class="num">${emp.type === 'monthly' ? '₱'+emp.rate+'/mo' : '₱'+emp.rate+'/day'}</td>
      <td>${emp.hired}</td>
      <td><span class="badge badge-${emp.status === 'active' ? 'paid' : 'draft'}">${emp.status}</span></td>
      
      <!-- THE FIX: Flex container for side-by-side Action buttons -->
      <td style="display: flex; gap: 6px; justify-content: flex-end;">
        <button class="btn-outline" style="padding:4px 10px;font-size:11px;color:var(--text);border-color:var(--border2);" onclick="deactivate(${emp.id})">
          ${actionText}
        </button>
        <button class="btn-outline" style="padding:4px 10px;font-size:11px;color:var(--red);border-color:var(--red);" onclick="deleteEmployee(${emp.id})">
          Delete
        </button>
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

  if (!payload.first_name || !payload.last_name || !payload.department_id || !payload.position_id || !payload.salary_rate || !payload.gender || !payload.birthdate) {
      alert("Please fill in all required fields (*)");
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
      alert("Error: " + result.message);
    }
  } catch (error) {
    console.error("Error saving employee:", error);
    alert("Failed to connect to the server.");
  }
}

// ==========================================
// API CALL: Toggle Active/Inactive
// ==========================================
async function deactivate(id) {
  if (!confirm(`Are you sure you want to change the status for employee ID: ${id}?`)) return;

  try {
    const response = await fetch('../api/employees.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'toggle_status',
        employee_id: id
      })
    });
    
    const result = await response.json();
    if (result.status === 'success') {
      loadEmployees(); 
    } else {
      alert("Error: " + result.message);
    }
  } catch (error) {
    console.error("Error updating status:", error);
  }
}

// ==========================================
// API CALL: Soft Delete Employee
// ==========================================
async function deleteEmployee(id) {
  if (!confirm("Are you sure you want to delete this employee? Their records will be moved to the Archive.")) {
      return;
  }

  try {
      const response = await fetch('../api/employees.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', employee_id: id })
      });
      
      const result = await response.json();

      if (result.status === 'success') {
          loadEmployees(); // Reload the table to remove the deleted employee
      } else {
          alert("Error: " + (result.message || "Failed to delete."));
      }
  } catch (error) {
      console.error("Delete Error:", error);
      alert("Error connecting to server.");
  }
}

// ==========================================
// UI Interactions
// ==========================================
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

function openAddModal() {
  document.getElementById('empModalTitle').textContent = 'Add Employee';
  ['fFirstName','fLastName','fMiddleName','fEmail','fPhone','fAddress','fRate'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fGender').value = '';
  document.getElementById('fBirthdate').value = '';
  document.getElementById('fHired').value = new Date().toISOString().split('T')[0];
  document.getElementById('empModal').style.display = 'flex';
}

function closeEmpModal() { document.getElementById('empModal').style.display = 'none'; }
document.getElementById('empModal').addEventListener('click', function(e){ if(e.target===this) closeEmpModal(); });