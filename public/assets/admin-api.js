(() => {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = $('meta[name="csrf-token"]')?.content || '', role = document.body.dataset.adminRole || 'viewer', canEdit = ['admin', 'editor'].includes(role);
  let leads = [], plans = [], content = {};
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const api = async (url, options = {}) => {
    options.headers = {...(options.headers || {}), Accept:'application/json'};
    if (options.body && !(options.body instanceof FormData)) options.headers['Content-Type'] = 'application/json';
    if (!['GET','HEAD'].includes(options.method || 'GET')) options.headers['X-CSRF-Token'] = csrf;
    const response = await fetch(url, options), data = await response.json().catch(() => ({}));
    if (response.status === 401) { location.href = '/admin/login'; throw new Error('Session expired.'); }
    if (!response.ok) throw new Error(data.error || 'Request failed.');
    return data;
  };
  const toast = (message, error = false) => { let el = $('.admin-toast'); if (!el) { el = document.createElement('div'); el.className = 'admin-toast'; document.body.append(el); } el.textContent = message; el.classList.toggle('error', error); el.classList.add('show'); setTimeout(() => el.classList.remove('show'), 3000); };
  const initials = name => String(name).split(' ').map(x => x[0]).join('').slice(0,2).toUpperCase();
  const age = value => { const m = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 60000)); return m < 60 ? `${m}m ago` : m < 1440 ? `${Math.floor(m/60)}h ago` : `${Math.floor(m/1440)}d ago`; };

  const logout = document.createElement('button'); logout.className = 'admin-logout'; logout.type = 'button'; logout.textContent = 'Log out'; $('.top-actions')?.append(logout);
  logout.addEventListener('click', async () => { await api('/admin/logout', {method:'POST', body:'{}'}); location.href = '/admin/login'; });
  if ($('.avatar')) $('.avatar').textContent = initials(document.body.dataset.adminName || 'Admin');
  if ($('.welcome-row h2')) $('.welcome-row h2').textContent = `Good to see you, ${document.body.dataset.adminName || 'Admin'}.`;

  function switchView(name) {
    $$('[data-view-panel]').forEach(p => { const active = p.dataset.viewPanel === name; p.hidden = !active; p.classList.toggle('active', active); });
    $$('[data-view]').forEach(b => b.classList.toggle('active', b.dataset.view === name));
    $('[data-view-title]').textContent = name === 'database' ? 'Team access' : name[0].toUpperCase() + name.slice(1);
    $('#sidebar')?.classList.remove('open'); $('.menu-toggle')?.setAttribute('aria-expanded','false'); if (name === 'leads') renderLeads();
  }
  $$('[data-view]').forEach(b => b.addEventListener('click', () => switchView(b.dataset.view)));
  $('[data-open-leads]')?.addEventListener('click', () => switchView('leads'));
  $('.menu-toggle')?.addEventListener('click', e => { const open = $('#sidebar').classList.toggle('open'); e.currentTarget.setAttribute('aria-expanded', String(open)); });

  function renderDashboard(data) {
    $('[data-metric-total]').textContent = data.total; $('[data-metric-followup]').textContent = data.followup; $('[data-metric-qualified]').textContent = data.qualified; $('[data-metric-won]').textContent = data.won;
    $$('[data-lead-count]').forEach(el => el.textContent = data.total);
    const total = Math.max(1, data.total);
    $('[data-plan-mix]').innerHTML = Object.entries(data.plan_mix).map(([name,count]) => `<div class="mix-row"><span>${esc(name)}</span><div class="mix-track"><i style="width:${Math.round(count/total*100)}%"></i></div><b>${Math.round(count/total*100)}%</b></div>`).join('');
    $('[data-recent-leads]').innerHTML = data.recent.map(l => `<div class="compact-lead"><div class="lead-person"><span class="lead-avatar">${esc(initials(l.name))}</span><div><b>${esc(l.name)}</b><small>${esc(l.company)} · ${age(l.created_at)}</small></div></div><span class="plan-pill">${esc(l.plan)}</span><span class="status-pill" data-status="${esc(l.status)}">${esc(l.status)}</span></div>`).join('') || '<p class="admin-empty">No enquiries yet.</p>';
    const values = [2,4,3,6,5,8,Math.max(2,data.followup)], max = Math.max(...values);
    $('[data-chart]').innerHTML = values.map((v,i) => `<div class="bar-column"><i style="height:${v/max*88}%"></i><small>${['Thu','Fri','Sat','Sun','Mon','Tue','Wed'][i]}</small></div>`).join('');
  }
  function renderLeads() {
    const search = ($('[data-search]')?.value || '').toLowerCase(), status = $('[data-status-filter]')?.value, plan = $('[data-plan-filter]')?.value;
    const list = leads.filter(l => (status === 'all' || l.status === status) && (plan === 'all' || l.plan === plan) && `${l.name} ${l.company} ${l.email}`.toLowerCase().includes(search));
    $('[data-lead-table]').innerHTML = list.map(l => `<tr><td><div class="lead-person"><span class="lead-avatar">${esc(initials(l.name))}</span><div><b>${esc(l.name)}</b><small>${esc(l.company)}<br>${esc(l.email)}</small></div></div></td><td><span class="plan-pill">${esc(l.plan)}</span></td><td>${age(l.created_at)}</td><td><select data-status-id="${l.id}" ${canEdit?'':'disabled'}>${['New','Contacted','Qualified','Won','Archived'].map(v => `<option ${v===l.status?'selected':''}>${v}</option>`).join('')}</select></td><td>${canEdit?`<button class="row-action" data-archive="${l.id}">Archive</button>`:'View only'}</td></tr>`).join('');
    $('[data-empty]').hidden = list.length > 0;
    $$('[data-status-id]').forEach(s => s.addEventListener('change', () => updateLead(s.dataset.statusId, s.value))); $$('[data-archive]').forEach(b => b.addEventListener('click', () => updateLead(b.dataset.archive,'Archived')));
  }
  async function updateLead(id,status) { try { await api(`/api/admin/leads/${id}`, {method:'PATCH',body:JSON.stringify({status})}); await load(); toast('Lead status updated.'); } catch (e) { toast(e.message,true); } }
  ['[data-search]','[data-status-filter]','[data-plan-filter]'].forEach(s => $(s)?.addEventListener(s==='[data-search]'?'input':'change',renderLeads));
  const dialog = $('[data-lead-dialog]'); $$('[data-add-lead]').forEach(b => canEdit ? b.addEventListener('click',()=>dialog.showModal()) : b.hidden = true);
  $('[data-lead-form]')?.addEventListener('submit', async e => { if (e.submitter?.value === 'cancel') return; e.preventDefault(); try { await api('/api/admin/leads',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(e.currentTarget)))}); e.currentTarget.reset(); dialog.close(); await load(); toast('Lead added.'); } catch (x) { toast(x.message,true); } });
  $('[data-export]')?.addEventListener('click', () => { const q = v => `"${String(v ?? '').replaceAll('"','""')}"`, rows = [['Name','Company','Email','Plan','Status','Message','Created'],...leads.map(l=>[l.name,l.company,l.email,l.plan,l.status,l.message,l.created_at])], blob = new Blob([rows.map(r=>r.map(q).join(',')).join('\n')],{type:'text/csv'}), link=document.createElement('a'); link.href=URL.createObjectURL(blob); link.download='nexum-leads.csv'; link.click(); URL.revokeObjectURL(link.href); });

  function renderPlans() { $('[data-pricing-editor]').innerHTML = plans.map((p,i) => `<article class="price-edit-card"><h3>${esc(p.name)}</h3><label>Starting price (PKR)<input type="number" min="0" data-plan-index="${i}" data-field="starting_price_pkr" value="${Number(p.starting_price_pkr||0)}" ${canEdit?'':'disabled'}></label><label>Price suffix<input data-plan-index="${i}" data-field="price_suffix" value="${esc(p.price_suffix||'')}" ${canEdit?'':'disabled'}></label><label>Description<textarea data-plan-index="${i}" data-field="description" ${canEdit?'':'disabled'}>${esc(p.description)}</textarea></label></article>`).join(''); if (!canEdit) $('[data-save-pricing]').hidden = true; }
  $('[data-save-pricing]')?.addEventListener('click', async () => { $$('[data-plan-index]').forEach(x => plans[Number(x.dataset.planIndex)][x.dataset.field] = x.dataset.field === 'starting_price_pkr' ? Number(x.value) : x.value); try { plans=(await api('/api/admin/plans',{method:'PUT',body:JSON.stringify({plans})})).data; renderPlans(); toast('Pricing saved.'); } catch(e){toast(e.message,true);} });
  function renderContent(){ $$('[data-content]').forEach(x => { x.value=content[x.dataset.content]||''; x.disabled=!canEdit; }); if(!canEdit)$('[data-save-content]').hidden=true; }
  $('[data-save-content]')?.addEventListener('click',async()=>{ $$('[data-content]').forEach(x=>content[x.dataset.content]=x.value); try{content=(await api('/api/admin/content',{method:'PUT',body:JSON.stringify({content})})).data;toast('Website content saved.');}catch(e){toast(e.message,true);} });

  async function team(){ const nav=$('[data-view="database"]'),panel=$('[data-view-panel="database"]'); if(role!=='admin'){nav?.remove();panel?.remove();return;} nav.innerHTML='<span>♙</span> Team access'; panel.innerHTML=`<div class="section-heading"><div><p class="eyebrow">Administration</p><h2>Team access</h2><p>Create secure accounts and assign the minimum access each teammate needs.</p></div></div><div class="database-grid"><form class="panel team-form" data-user-form><h3>Add team member</h3><label>Name<input name="name" minlength="2" required></label><label>Email<input name="email" type="email" required></label><label>Role<select name="role"><option value="viewer">Viewer</option><option value="editor">Editor</option><option value="admin">Admin</option></select></label><label>Temporary password<input name="password" type="password" minlength="12" required></label><button class="primary-action">Create account</button></form><article class="panel"><div class="panel-head"><div><span>Current accounts</span><h3>Users</h3></div></div><div class="team-list" data-user-list></div></article></div><article class="security-note"><b>Role guide</b><p>Admins manage everything, editors manage leads, pricing, and content, and viewers have read-only access.</p></article>`; const result=await api('/api/admin/users'); $('[data-user-list]').innerHTML=result.data.map(u=>`<div class="team-user"><div><b>${esc(u.name)}</b><small>${esc(u.email)}</small></div><span class="plan-pill">${esc(u.role)}</span><span class="status-pill" data-status="${u.active?'Qualified':'Archived'}">${u.active?'Active':'Disabled'}</span></div>`).join(''); $('[data-user-form]').addEventListener('submit',async e=>{e.preventDefault();try{await api('/api/admin/users',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(e.currentTarget)))});e.currentTarget.reset();toast('Team account created.');await team();}catch(x){toast(x.message,true);}}); }
  async function load(){ const [d,l,p,c]=await Promise.all([api('/api/admin/dashboard'),api('/api/admin/leads'),api('/api/admin/plans'),api('/api/admin/content')]); leads=l.items;plans=p.data;content=c.data;renderDashboard(d.data);renderLeads();renderPlans();renderContent(); }
  Promise.all([load(),team()]).catch(e=>toast(e.message,true));
})();
