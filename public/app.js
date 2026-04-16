const state = {
  token: localStorage.getItem('token') || '',
  leads: [],
  logs: [],
  metrics: null,
  queue: { queued: [], active: [] }
};

const el = {
  loginView: document.querySelector('#loginView'),
  dashboardView: document.querySelector('#dashboardView'),
  loginForm: document.querySelector('#loginForm'),
  logoutBtn: document.querySelector('#logoutBtn'),
  metrics: document.querySelector('#metrics'),
  addLeadForm: document.querySelector('#addLeadForm'),
  csvForm: document.querySelector('#csvForm'),
  csvFile: document.querySelector('#csvFile'),
  searchInput: document.querySelector('#searchInput'),
  statusFilter: document.querySelector('#statusFilter'),
  cityFilter: document.querySelector('#cityFilter'),
  applyFilter: document.querySelector('#applyFilter'),
  leadTableBody: document.querySelector('#leadTableBody'),
  selectAll: document.querySelector('#selectAll'),
  startBulkBtn: document.querySelector('#startBulkBtn'),
  queueInfo: document.querySelector('#queueInfo'),
  configForm: document.querySelector('#configForm'),
  callLogsBody: document.querySelector('#callLogsBody'),
  transcriptDialog: document.querySelector('#transcriptDialog'),
  transcriptText: document.querySelector('#transcriptText'),
  closeTranscript: document.querySelector('#closeTranscript')
};

const metricsTemplate = [
  ['Total Leads', 'totalLeads'],
  ['Calls Today', 'callsToday'],
  ['Connected Calls', 'connectedCalls'],
  ['Hot Leads', 'hotLeads'],
  ['Conversion Rate', 'conversionRate']
];

async function api(path, options = {}) {
  const headers = { ...(options.headers || {}) };
  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }
  if (state.token) headers.Authorization = `Bearer ${state.token}`;

  const response = await fetch(`/api.php?route=${path}`, { ...options, headers });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || 'Request failed');
  }
  return data;
}

function renderAuth() {
  const hasToken = Boolean(state.token);
  el.loginView.classList.toggle('hidden', hasToken);
  el.dashboardView.classList.toggle('hidden', !hasToken);
}

function renderMetrics() {
  if (!state.metrics) return;
  el.metrics.innerHTML = metricsTemplate.map(([label, key]) => `
    <article class="metric">
      <div>${label}</div>
      <div class="num">${key === 'conversionRate' ? `${state.metrics[key]}%` : state.metrics[key]}</div>
    </article>
  `).join('');
}

function renderLeads() {
  el.leadTableBody.innerHTML = state.leads.map((lead) => `
    <tr>
      <td><input type="checkbox" class="lead-checkbox" value="${lead.id}" /></td>
      <td>${lead.name}</td>
      <td>${lead.phone}</td>
      <td>${lead.city}</td>
      <td><span class="status-pill">${lead.status}</span></td>
      <td><button class="call-btn" data-id="${lead.id}">Call</button></td>
    </tr>
  `).join('');
}

function renderLogs() {
  el.callLogsBody.innerHTML = state.logs.map((log) => `
    <tr>
      <td>${log.lead?.name || 'Unknown'}<br/><small>${log.lead?.phone || ''}</small></td>
      <td><span class="status-pill">${log.status}</span></td>
      <td>${log.duration}</td>
      <td>${log.attempt}</td>
      <td>${log.summary}</td>
      <td><button class="secondary view-transcript" data-transcript="${encodeURIComponent(log.transcript)}">View</button></td>
    </tr>
  `).join('');
}

function renderQueue() {
  el.queueInfo.textContent = `Queued: ${state.queue.queued.length} | Active: ${state.queue.active.length}`;
}

async function refreshData() {
  const params = new URLSearchParams();
  if (el.statusFilter.value) params.set('status', el.statusFilter.value);
  if (el.cityFilter.value) params.set('city', el.cityFilter.value);
  if (el.searchInput.value) params.set('q', el.searchInput.value);

  const [metrics, leads, logs, config, queue] = await Promise.all([
    api('dashboard/metrics'),
    api(params.toString() ? `leads&${params.toString()}` : 'leads'),
    api('calls/logs'),
    api('config'),
    api('calls/queue')
  ]);

  state.metrics = metrics;
  state.leads = leads;
  state.logs = logs;
  state.queue = queue;

  el.configForm.languageStyle.value = config.languageStyle;
  el.configForm.tone.value = config.tone;
  el.configForm.openingScript.value = config.openingScript;
  el.configForm.questionFlow.value = config.questionFlow.join('\n');
  el.configForm.closingStatement.value = config.closingStatement;

  renderMetrics();
  renderLeads();
  renderLogs();
  renderQueue();
}

el.loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = new FormData(el.loginForm);
  try {
    const data = await api('auth/login', {
      method: 'POST',
      body: JSON.stringify({ username: form.get('username'), password: form.get('password') })
    });
    state.token = data.token;
    localStorage.setItem('token', data.token);
    renderAuth();
    await refreshData();
  } catch (error) {
    alert(error.message);
  }
});

el.logoutBtn.addEventListener('click', async () => {
  try { if (state.token) await api('auth/logout', { method: 'POST' }); } catch (e) {}
  state.token = '';
  localStorage.removeItem('token');
  renderAuth();
});

el.addLeadForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = new FormData(el.addLeadForm);
  try {
    await api('leads', {
      method: 'POST',
      body: JSON.stringify({
        name: form.get('name'),
        phone: form.get('phone'),
        city: form.get('city')
      })
    });
    el.addLeadForm.reset();
    await refreshData();
  } catch (error) {
    alert(error.message);
  }
});

el.csvForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  if (!el.csvFile.files.length) return;

  const form = new FormData();
  form.append('file', el.csvFile.files[0]);

  try {
    await api('leads/upload-csv', { method: 'POST', body: form, headers: {} });
    el.csvForm.reset();
    await refreshData();
  } catch (error) {
    alert(error.message);
  }
});

el.applyFilter.addEventListener('click', refreshData);

el.startBulkBtn.addEventListener('click', async () => {
  const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map((cb) => cb.value);
  if (!selected.length) {
    alert('Select at least one lead.');
    return;
  }

  try {
    await api('calls/bulk-start', {
      method: 'POST',
      body: JSON.stringify({ leadIds: selected })
    });
    await refreshData();
  } catch (error) {
    alert(error.message);
  }
});

el.selectAll.addEventListener('change', () => {
  document.querySelectorAll('.lead-checkbox').forEach((cb) => {
    cb.checked = el.selectAll.checked;
  });
});

el.leadTableBody.addEventListener('click', async (e) => {
  const callBtn = e.target.closest('.call-btn');
  if (!callBtn) return;

  try {
    await api('calls/start', { method: 'POST', body: JSON.stringify({ leadId: Number(callBtn.dataset.id) }) });
    await refreshData();
  } catch (error) {
    alert(error.message);
  }
});

el.configForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = new FormData(el.configForm);

  try {
    await api('config', {
      method: 'PUT',
      body: JSON.stringify({
        languageStyle: form.get('languageStyle'),
        tone: form.get('tone'),
        openingScript: form.get('openingScript'),
        questionFlow: String(form.get('questionFlow')).split('\n').map((x) => x.trim()).filter(Boolean),
        closingStatement: form.get('closingStatement')
      })
    });
    await refreshData();
  } catch (error) {
    alert(error.message);
  }
});

el.callLogsBody.addEventListener('click', (e) => {
  const btn = e.target.closest('.view-transcript');
  if (!btn) return;
  el.transcriptText.textContent = decodeURIComponent(btn.dataset.transcript);
  el.transcriptDialog.showModal();
});

el.closeTranscript.addEventListener('click', () => el.transcriptDialog.close());

renderAuth();
if (state.token) {
  refreshData().catch(() => {
    state.token = '';
    localStorage.removeItem('token');
    renderAuth();
  });
}

setInterval(() => {
  if (state.token) refreshData().catch(() => {});
}, 4000);
