// dashboard.js — Merged System Logic (Dashboard, Archive, and Authentication)

let deptPieChart = null; // Global to handle chart refreshes

// ============================================================
// 1. SHARED UTILITIES
// ============================================================
function fmt(n) { 
    return '₱ ' + Number(n).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}); 
}

function showToast(msg, color = 'green') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:24px;right:24px;background:var(--bg2, #fff);border:1px solid var(--${color}, ${color});color:var(--${color}, ${color});padding:12px 20px;border-radius:8px;font-family:var(--font-mono, monospace);font-size:12.5px;z-index:9999;box-shadow:0 8px 30px rgba(0,0,0,.4);animation:fadeIn .2s;`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ============================================================
// 2. DASHBOARD LOGIC
// ============================================================
async function loadDashboardData() {
    const periodSelect = document.getElementById('periodSelect');
    const period = periodSelect ? periodSelect.value : '';
    
    if (!period) {
        resetDashboard();
        return;
    }

    try {
        const tbody = document.getElementById('payrollBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="12" style="text-align: center; padding: 20px; color: var(--text3, #888);">Loading data from database...</td></tr>`;
        
        const response = await fetch(`api/dashboard.php?period=${period}`);
        const data = await response.json();

        if (data.error) {
            console.error(data.error); showToast(data.error, "red"); resetDashboard(); return;
        }

        if (document.getElementById('kpiEmployees')) {
            document.getElementById('kpiEmployees').textContent = data.kpis.employees || 0;
            document.getElementById('kpiEmpSub').textContent = `${data.kpis.active_employees || 0} active in DB`;
            document.getElementById('kpiGross').textContent = fmt(data.kpis.gross || 0);
            document.getElementById('kpiDed').textContent = fmt(data.kpis.deductions || 0);
            document.getElementById('kpiNet').textContent = fmt(data.kpis.net || 0);
        }

        renderPayrollTable(data.payrollData || []);
        renderDeptPieChart(data); // Calling new pie chart logic
        renderActivity(data);

    } catch (error) {
        console.error("Dashboard error:", error);
        showToast("Error connecting to database API.", "red");
        resetDashboard();
    }
}

function renderPayrollTable(list) {
    const tbody = document.getElementById('payrollBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    list.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${row.employee_code}</td>
          <td>${row.full_name}</td>
          <td>${row.department_name}</td>
          <td class="num">${row.total_days}</td>
          <td class="num">${row.total_ot_hours}</td>
          <td class="num">${fmt(row.basic_pay)}</td>
          <td class="num">${fmt(row.ot_pay)}</td>
          <td class="num">${fmt(row.total_bonuses)}</td>
          <td class="num" style="font-weight:600">${fmt(row.gross_salary)}</td>
          <td class="num" style="color:var(--red)">(${fmt(row.total_deductions)})</td>
          <td class="num" style="color:var(--green);font-weight:600">${fmt(row.net_salary)}</td>
          <td><span class="badge badge-${row.status}">${row.status}</span></td>
        `;
        tbody.appendChild(tr);
    });
}

function renderDeptPieChart(apiResponse) {
    const canvas = document.getElementById('deptPieChart');
    if (!canvas) return;

    // Use the aggregated deptData directly from dashboard.php
    const labels = apiResponse.deptData.map(item => item.department_name);
    const totals = apiResponse.deptData.map(item => item.total_net);

    if (deptPieChart) { deptPieChart.destroy(); }

    deptPieChart = new Chart(canvas, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: totals,
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'],
                borderColor: '#1e293b',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#94a3b8', font: { family: 'Inter' } } }
            }
        }
    });
}

function renderActivity(data) {
    const list = document.getElementById('activityList');
    if (list) list.innerHTML = `<li style="padding:12px 20px;">✔ Payroll batch processed for ${data.payrollData.length} records.</li>`;
}

function resetDashboard() {
    const fields = ['kpiEmployees', 'kpiGross', 'kpiDed', 'kpiNet'];
    fields.forEach(id => { if(document.getElementById(id)) document.getElementById(id).textContent = '--'; });
}

// ============================================================
// 3. ARCHIVE PAGE LOGIC
// ============================================================
async function loadArchive() {
    try {
        const apiPath = document.getElementById('archiveBody') ? '../api/archive.php' : 'api/archive.php';
        const response = await fetch(apiPath);
        const result = await response.json();
        const tbody = document.getElementById('archiveBody');
        if (!tbody) return; 
        tbody.innerHTML = '';
        if (result.status === 'success' && result.data.length > 0) {
            result.data.forEach(emp => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${emp.employee_code}</td><td>${emp.full_name}</td><td>${emp.department_name}</td><td>${new Date(emp.deleted_at).toLocaleString()}</td>
                <td><button onclick="restoreEmployee(${emp.employee_id})">Restore</button></td>`;
                tbody.appendChild(tr);
            });
        }
    } catch (e) { console.error("Archive Error", e); }
}

document.addEventListener('DOMContentLoaded', () => {
    const periodSelect = document.getElementById('periodSelect');
    if(periodSelect) periodSelect.addEventListener('change', loadDashboardData);
    if (document.getElementById('archiveBody')) { loadArchive(); }
});