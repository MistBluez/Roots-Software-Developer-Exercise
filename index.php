<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Todo List View</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
  font-family: 'Segoe UI', Tahoma, sans-serif;
  background-color: #f8f9fa;
  padding: 20px;
}
.card {
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.item-done {
  text-decoration: line-through;
  color: gray;
}
.todo-title {
  font-weight: 600;
  font-size: 1.1rem;
}
.todo-meta {
  font-size: 0.85rem;
  color: #6c757d;
}
</style>
</head>
<body>
<div class="container">
  <h2 class="mb-4">📝 Todo List view</h2>

  <!-- Filter Card -->
  <div class="card mb-3 p-3">
    <div class="row g-2 align-items-center">
      <div class="col-md-4">
        <select id="filter" class="form-select">
          <option value="all">Todo list (All)</option>
          <option value="uncomplete">Todo list uncomplete</option>
          <option value="complete">Todo list complete</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-outline-primary" onclick="loadTodos()">
          <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
      </div>
      <div class="col text-end">
        <button class="btn btn-success" onclick="showCreate()">+ สร้าง Todo ใหม่</button>
      </div>
    </div>
  </div>

  <div id="list"></div>

  <!-- Create form -->
  <div id="createBox" class="card p-4" style="display:none">
    <h4 class="mb-3">สร้าง Todo ใหม่</h4>
    <div class="mb-3">
      <label class="form-label">ชื่อ:</label>
      <input id="c_name" class="form-control">
    </div>
    <button id="showTagsBtn" class="btn btn-sm btn-success mb-2">+ เพิ่ม Tag</button>
    <div id="tagsContainer" style="display:none;">
      <select id="c_tags" class="form-select" multiple>
        <option value="Work">Work</option>
        <option value="Event">Event</option>
        <option value="Life achievement">Life achievement</option>
      </select>
    </div>
    <div class="row g-3 mt-2">
      <div class="col-md-6">
        <label class="form-label">วันเริ่มต้น:</label>
        <input type="date" id="c_start" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label">วันสิ้นสุด:</label>
        <input type="date" id="c_end" class="form-control">
      </div>
    </div>
    <div class="mt-3">
      <button class="btn btn-primary" onclick="createTodo()">บันทึก</button>
      <button class="btn btn-secondary" onclick="hideCreate()">ยกเลิก</button>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const api = (data, method='POST') => {
  return fetch('api.php', { method, body: data })
    .then(r=>r.json());
}

function loadTodos(){
  const f = document.getElementById('filter').value;
  fetch('api.php?action=get_todos&filter='+encodeURIComponent(f))
    .then(r=>r.json()).then(res=>{
      if(!res.ok){ alert(res.error||'error'); return; }
      renderList(res.todos);
    });
}
function renderList(todos){
  const out = document.getElementById('list');
  out.innerHTML = '';
  todos.forEach(t=>{
    const card = document.createElement('div');
    card.className = 'card mb-3 p-3';
    card.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <div class="todo-title">${escapeHtml(t.name)}</div>
          <div class="todo-meta">[${t.tags}] ${t.start_date} → ${t.end_date}</div>
        </div>
        <span class="badge ${t.status === 'complete' ? 'bg-success' : (t.status === 'in_progress' ? 'bg-warning' : 'bg-secondary')}">
          ${t.status}
        </span>
      </div>
      <div id="todo_${t.id}_content"></div>
    `;
    out.appendChild(card);
    renderTodoContent(t);
  });
}

function renderTodoContent(t){
  const ct = document.getElementById('todo_'+t.id+'_content');
  ct.innerHTML = '';

  const part = document.createElement('div');
  part.innerHTML = `<small class="text-muted">ผู้เข้าร่วม: ${t.participants.map(p=>escapeHtml(p.user_name)).join(', ')}</small>
    <div class="input-group input-group-sm mt-1" style="max-width:300px;">
      <input class="form-control" placeholder="ชื่อผู้ใช้งาน" id="pname_${t.id}"> 
      <button class="btn btn-outline-secondary" onclick="addParticipant(${t.id})">เพิ่ม</button>
    </div>`;
  ct.appendChild(part);

  const itemsBox = document.createElement('div');
  itemsBox.classList.add('mt-3');
  itemsBox.innerHTML = '<strong>รายการย่อย</strong>';
  const list = document.createElement('div');
  list.classList.add('mt-2');

  t.items.forEach(it=>{
    const div = document.createElement('div');
    div.className = `mb-1 ${t.status === 'complete' && it.done ? 'item-done' : ''}`;

    let checkbox = '';
    if (t.status === 'in_progress') {
      checkbox = `<input type="checkbox" onchange="toggleItem(${it.id})"> `;
    } else if (t.status === 'complete') {
      checkbox = `<input type="checkbox" ${it.done ? 'checked' : ''} disabled> `;
    }

    div.innerHTML = `${checkbox}<span>${escapeHtml(it.name)}</span> <small class="text-muted">${escapeHtml(it.description||'')}</small>`;
    list.appendChild(div);
  });

  if (t.status !== 'complete') {
    const inline = document.createElement('div');
    inline.className = 'input-group input-group-sm mt-2';
    inline.innerHTML = `
      <input id="ni_${t.id}" class="form-control" placeholder="ชื่อรายการย่อย"> 
      <input id="nd_${t.id}" class="form-control" placeholder="คำอธิบาย"> 
      <button class="btn btn-outline-primary" onclick="addItem(${t.id})">เพิ่ม</button>`;
    list.appendChild(inline);
  }

  itemsBox.appendChild(list);
  ct.appendChild(itemsBox);

  const actions = document.createElement('div');
  actions.className = 'mt-3';
  let btns = '';
  if (t.status === 'draft') {
    btns += `<button class="btn btn-sm btn-warning me-2" onclick="changeStatus(${t.id},'in_progress')">เริ่มทำ</button>`;
  }
  if (t.status !== 'complete') {
    const allDone = t.items.length > 0 && t.items.every(i=>i.done == 1);
    if (allDone) {
      btns += `<button class="btn btn-sm btn-success" onclick="changeStatus(${t.id},'complete')">ทำเสร็จ</button>`;
    }
  }
  actions.innerHTML = btns;
  ct.appendChild(actions);
}

function showCreate(){ document.getElementById('createBox').style.display='block'; }
function hideCreate(){ document.getElementById('createBox').style.display='none'; }

function createTodo(){
  const fd = new FormData();
  fd.append('action','create_todo');
  fd.append('name', document.getElementById('c_name').value);
  fd.append('tags', document.getElementById('c_tags').value);
  fd.append('start_date', document.getElementById('c_start').value);
  fd.append('end_date', document.getElementById('c_end').value);
  api(fd).then(r=>{
    if(!r.ok) alert(r.error||'error'); else { hideCreate(); loadTodos(); }
  });
}

function addItem(todoId){
  const fd = new FormData();
  fd.append('action','add_item');
  fd.append('todo_id', todoId);
  fd.append('name', document.getElementById('ni_'+todoId).value);
  fd.append('description', document.getElementById('nd_'+todoId).value);
  api(fd).then(r=>{
    if(!r.ok) alert(r.error||'error'); else loadTodos();
  });
}

function toggleItem(itemId){
  const fd = new FormData();
  fd.append('action','toggle_item_done');
  fd.append('id', itemId);
  api(fd).then(r=>{
    if(!r.ok) alert(r.error||'error'); else loadTodos();
  });
}

function changeStatus(todoId, target){
  const fd = new FormData();
  fd.append('action','change_status');
  fd.append('id', todoId);
  fd.append('target', target);
  api(fd).then(r=>{
    if(!r.ok) alert(r.error||'error'); else loadTodos();
  });
}

function addParticipant(todoId){
  const name = document.getElementById('pname_'+todoId).value;
  const fd = new FormData();
  fd.append('action','add_participant');
  fd.append('todo_id', todoId);
  fd.append('user_name', name);
  api(fd).then(r=>{
    if(!r.ok) alert(r.error||'error'); else loadTodos();
  });
}

function escapeHtml(s){ if(!s) return ''; return s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }

window.onload = function(){ loadTodos(); }
</script>

<!-- Choices.js -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>

<script>
let choicesInstance;
document.getElementById('showTagsBtn').addEventListener('click', function(){
  document.getElementById('tagsContainer').style.display = 'block';
  if (!choicesInstance) {
    choicesInstance = new Choices('#c_tags', {
      removeItemButton: true,
      duplicateItemsAllowed: false,
      addItemText: (value) => `กด Enter เพื่อเพิ่ม Tag: "${value}"`,
      addItemFilter: (value) => value.trim() !== ''
    });
  }
});
</script>
</body>
</html>
