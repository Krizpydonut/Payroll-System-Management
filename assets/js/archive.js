// assets/js/archive.js
let selectedIds = new Set();

// UI helper to show status messages
function showToast(msg, color = 'green') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:24px;right:24px;background:var(--bg2);border:1px solid var(--${color});color:var(--${color});padding:12px 20px;border-radius:8px;font-family:var(--font-mono);font-size:12.5px;z-index:9999;box-shadow:0 8px 30px rgba(0,0,0,.4);`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// Fetch all archived (is_deleted=1) records from the API
async function loadArchive() {
    const tbody = document.getElementById('archiveBody');
    if (!tbody) return;

    try {
        const response = await fetch('../api/archive.php');
        const result = await response.json();
        
        tbody.innerHTML = '';
        selectedIds.clear();
        updateBulkUI();
        document.getElementById('selectAll').checked = false;

        if (result.status === 'success' && result.data.length > 0) {
            result.data.forEach(emp => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="checkbox-col"><input type="checkbox" class="row-checkbox" value="${emp.employee_id}" onclick="toggleSelect(${emp.employee_id}, this)"></td>
                    <td class="emp-code" style="text-decoration: line-through; color: var(--text3);">${emp.employee_code}</td>
                    <td class="emp-name" style="font-weight:600;">${emp.full_name}</td>
                    <td>${emp.department_name || 'N/A'}</td>
                    <td>${emp.position_name || 'N/A'}</td>
                    <td>${new Date(emp.deleted_at).toLocaleString()}</td>
                    <td style="text-align: right;">
                        <button class="btn-outline" style="border-color:var(--green); color:var(--green); padding:4px 8px; font-size:11px;" onclick="restoreSingle(${emp.employee_id}, '${emp.full_name}')">Restore</button>
                        <button class="btn-outline" style="border-color:var(--red); color:var(--red); padding:4px 8px; font-size:11px; margin-left:5px;" onclick="wipeSingle(${emp.employee_id}, '${emp.full_name}')">Wipe</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px; color: var(--text3);">Archive is empty.</td></tr>`;
        }
    } catch (error) { console.error(error); }
}

// Single record actions with Name confirmation
async function restoreSingle(id, name) {
    if(confirm(`Restore record for '${name}'?`)) executeAction('restore', [id]);
}

async function wipeSingle(id, name) {
    // Dynamic warning including the employee's name
    if(confirm(`PERMANENTLY wipe '${name}' from the database? This action is irreversible!`)) {
        executeAction('hard_delete', [id]);
    }
}

// Bulk action logic
window.bulkRestore = () => {
    if(confirm(`Restore ${selectedIds.size} selected employees?`)) executeAction('restore', Array.from(selectedIds));
};

window.bulkWipe = () => {
    if(confirm(`PERMANENTLY wipe ${selectedIds.size} records? This cannot be undone!`)) executeAction('hard_delete', Array.from(selectedIds));
};

// Selection logic
function toggleSelect(id, checkbox) {
    if (checkbox.checked) selectedIds.add(id);
    else selectedIds.delete(id);
    updateBulkUI();
}

function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
        if (master.checked) selectedIds.add(parseInt(cb.value));
        else selectedIds.delete(parseInt(cb.value));
    });
    updateBulkUI();
}

function updateBulkUI() {
    const bar = document.getElementById('bulkActions');
    bar.style.display = selectedIds.size > 0 ? 'flex' : 'none';
}

// API execution
async function executeAction(action, ids) {
    try {
        const response = await fetch('../api/archive.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, employee_ids: ids })
        });
        const result = await response.json();
        if (result.status === 'success') {
            showToast(result.message, action === 'restore' ? "green" : "red");
            loadArchive();
        }
    } catch (e) { showToast("Operation failed.", "red"); }
}

document.addEventListener('DOMContentLoaded', loadArchive);