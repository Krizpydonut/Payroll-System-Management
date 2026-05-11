// assets/js/employees.js

document.addEventListener('DOMContentLoaded', () => {
    loadDropdowns();
    loadEmployees();
    initModernDropdowns();
});

function validatePHPhone(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (!val.startsWith('09') && val.length > 0) { val = '09' + val.replace(/^0+/, ''); }
    input.value = val.substring(0, 11);
}

async function loadDropdowns() {
    try {
        const response = await fetch('../api/employees.php?action=dropdowns');
        const data = await response.json();
        if (data.status === 'success') {
            const fDept = document.getElementById('fDept');
            const fPos = document.getElementById('fPos');
            const deptMenu = document.getElementById('deptDropdownMenu');

            fDept.innerHTML = '<option value="">-- Select Dept --</option>';
            fPos.innerHTML = '<option value="">-- Select Position --</option>';

            data.departments.forEach(d => {
                fDept.innerHTML += `<option value="${d.id}">${d.name}</option>`;
                if (deptMenu) deptMenu.innerHTML += `<li class="dropdown-option" data-value="${d.id}">${d.name}</li>`;
            });
            data.positions.forEach(p => {
                fPos.innerHTML += `<option value="${p.id}">${p.name}</option>`;
            });
        }
    } catch (e) { console.error("Dropdown load failed", e); }
}

async function loadEmployees() {
    const tbody = document.getElementById('empBody');
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:20px;">Synchronizing...</td></tr>`;
    try {
        const response = await fetch('../api/employees.php');
        const list = await response.json();
        tbody.innerHTML = '';
        
        const counter = document.getElementById('empCount');
        if (counter) counter.textContent = `(${list.length})`;

        list.forEach(emp => {
            const tr = document.createElement('tr');
            tr.dataset.name = `${emp.first_name} ${emp.middle_name} ${emp.last_name}`.toLowerCase();
            tr.dataset.dept = emp.department_id;
            tr.dataset.status = emp.status;

            const actionText = emp.status === 'active' ? 'Deactivate' : 'Activate';

            tr.innerHTML = `
                <td>${emp.code}</td>
                <td><strong>${emp.first_name} ${emp.last_name}</strong></td>
                <td>${emp.department_name || 'N/A'}</td>
                <td>${emp.position_name || 'N/A'}</td>
                <td><span class="badge badge-draft">${emp.employment_type}</span></td>
                <td class="num">₱${Number(emp.rate).toLocaleString()}</td>
                <td>${emp.date_hired}</td>
                <td><span class="badge badge-${emp.status === 'active' ? 'paid' : 'draft'}">${emp.status}</span></td>
                <td style="text-align:right;">
                    <button class="btn-outline" style="padding:4px 8px;font-size:11px;" onclick="toggleStatus(${emp.employee_id})">${actionText}</button>
                    <button class="btn-outline" style="padding:4px 8px;font-size:11px;color:var(--red);border-color:var(--red);" onclick="deleteEmployee(${emp.employee_id})">Delete</button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) { tbody.innerHTML = '<tr><td colspan="9">Connection Failed.</td></tr>'; }
}

async function saveEmployee() {
    console.log("Save process started...");

    const payload = {
        action: 'add',
        first_name: document.getElementById('fFirstName').value.trim(),
        last_name: document.getElementById('fLastName').value.trim(),
        middle_name: document.getElementById('fMiddleName').value.trim(),
        gender: document.getElementById('fGender').value,
        birthdate: document.getElementById('fBirthdate').value,
        email: document.getElementById('fEmail').value,
        phone: document.getElementById('fPhone').value.trim(),
        date_hired: document.getElementById('fHired').value,
        department_id: document.getElementById('fDept').value,
        position_id: document.getElementById('fPos').value,
        employment_type: document.getElementById('fType').value,
        rate: document.getElementById('fRate').value,
        address: document.getElementById('fAddress').value
    };

    console.log("Collected payload:", payload);

    if (!payload.first_name || !payload.last_name || !payload.rate) {
        alert("Missing required fields!"); return;
    }

    try {
        const res = await fetch('../api/employees.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload) 
        });
        
        const result = await res.json();
        console.log("Server response:", result);

        if (result.status === 'success') {
            alert(result.message);
            closeEmpModal();
            loadEmployees();
        } else {
            alert("Error: " + result.message);
        }
    } catch (e) {
        console.error("Critical connection error:", e);
        alert("Could not reach the server.");
    }
}

function initModernDropdowns() {
    document.addEventListener('click', (e) => {
        const dropdown = e.target.closest('.custom-dropdown');
        if (!dropdown) return;
        if (e.target.closest('.dropdown-trigger')) dropdown.classList.toggle('open');
        if (e.target.closest('.dropdown-option')) {
            const opt = e.target.closest('.dropdown-option');
            dropdown.querySelector('input[type="hidden"]').value = opt.dataset.value;
            dropdown.querySelector('.selected-text').textContent = opt.textContent;
            dropdown.classList.remove('open');
            filterEmployees();
        }
    });
}

function filterEmployees() {
    const q = document.getElementById('empSearch').value.toLowerCase();
    const dept = document.getElementById('deptFilter').value;
    document.querySelectorAll('#empBody tr').forEach(tr => {
        const match = tr.dataset.name.includes(q) && (!dept || tr.dataset.dept == dept);
        tr.style.display = match ? '' : 'none';
    });
}

async function toggleStatus(id) {
    await fetch('../api/employees.php', { method: 'POST', body: JSON.stringify({ action: 'toggle_status', employee_id: id }) });
    loadEmployees();
}

async function deleteEmployee(id) {
    if (confirm("Move to archive?")) {
        await fetch('../api/employees.php', { method: 'POST', body: JSON.stringify({ action: 'delete', employee_id: id }) });
        loadEmployees();
    }
}

function openAddModal() { 
    document.getElementById('fHired').value = new Date().toISOString().split('T')[0];
    document.getElementById('fPhone').value = '09';
    document.getElementById('empModal').style.display = 'flex'; 
}
function closeEmpModal() { document.getElementById('empModal').style.display = 'none'; }