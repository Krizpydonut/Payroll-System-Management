// assets/js/archive.js

console.log("Archive script initialized!");

function showToast(msg, color = 'green') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:24px;right:24px;background:var(--bg2, #fff);border:1px solid var(--${color}, ${color});color:var(--${color}, ${color});padding:12px 20px;border-radius:8px;font-family:var(--font-mono, monospace);font-size:12.5px;z-index:9999;box-shadow:0 8px 30px rgba(0,0,0,.4);animation:fadeIn .2s;`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

async function loadArchive() {
    const tbody = document.getElementById('archiveBody');
    if (!tbody) return;

    try {
        console.log("Fetching archive data...");
        // Fetch from the API
        const response = await fetch('../api/archive.php');
        
        // If the PHP file is missing (404), throw an error immediately
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const result = await response.json();
        console.log("Archive data received:", result);
        
        tbody.innerHTML = '';

        // Catch backend SQL/PHP errors specifically
        if (result.status === 'error') {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 20px; color: var(--red);">Server Error: ${result.message}</td></tr>`;
            return;
        }

        if (result.status === 'success' && result.data && result.data.length > 0) {
            result.data.forEach(emp => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="emp-code" style="color: var(--text3); text-decoration: line-through;">${emp.employee_code}</td>
                    <td class="emp-name" style="font-weight: 600; color: var(--text);">${emp.full_name}</td>
                    <td>${emp.department_name || 'N/A'}</td>
                    <td>${emp.position_name || 'N/A'}</td>
                    <td style="color: var(--text2);">${new Date(emp.deleted_at).toLocaleString()}</td>
                    <td style="text-align: right;">
                        <button class="btn-outline" style="border-color: var(--green); color: var(--green); margin-right: 5px; padding: 4px 10px; font-size: 11px;" onclick="restoreEmployee(${emp.employee_id})">↻ Restore</button>
                        <button class="btn-outline" style="border-color: var(--red); color: var(--red); padding: 4px 10px; font-size: 11px;" onclick="hardDeleteEmployee(${emp.employee_id})">✖ Wipe</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 20px; color: var(--text3);">Archive is empty. No deleted records found.</td></tr>`;
        }
    } catch (error) { 
        console.error("Network Fetch Error:", error);
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 20px; color: var(--red);">Failed to connect to API. Is api/archive.php created?</td></tr>`;
    }
}

async function restoreEmployee(id) {
    if (!confirm("Are you sure you want to restore this employee to active status?")) return;
    try {
        const response = await fetch('../api/archive.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ action: 'restore', employee_id: id }) 
        });
        const result = await response.json();
        if (result.status === 'success') { 
            showToast(result.message, "green"); 
            loadArchive(); 
        } else { 
            showToast(result.message, "red"); 
        }
    } catch (error) { showToast("Connection error.", "red"); }
}

async function hardDeleteEmployee(id) {
    if (!confirm("WARNING: This will PERMANENTLY wipe the employee and ALL their past payslips, attendance, and records. This cannot be undone. Proceed?")) return;
    try {
        const response = await fetch('../api/archive.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ action: 'hard_delete', employee_id: id }) 
        });
        const result = await response.json();
        if (result.status === 'success') { 
            showToast(result.message, "green"); 
            loadArchive(); 
        } else { 
            showToast(result.message, "red"); 
        }
    } catch (error) { showToast("Connection error.", "red"); }
}

// Trigger the load sequence when the page opens
document.addEventListener('DOMContentLoaded', loadArchive);