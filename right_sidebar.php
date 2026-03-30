<!--
  right_sidebar.php
  Drop-in replacement for the right sidebar content in feed_frontend.php.
  Replace the entire <aside class="right-sidebar">...</aside> block with:
      <?php include 'right_sidebar.php'; ?>

  Features:
  - Stories row (visual placeholders — wire to your stories system later)
  - Mini Calendar with event markers + click to add events
  - To-do list with time reminders
  - All data stored in localStorage (no extra DB needed)
  - Browser notifications for reminders (user must grant permission)
-->

<aside class="right-sidebar" id="rightSidebar">

  <!-- ── Stories ── -->
  <div class="rs-card" id="storiesCard">
    <div class="rs-card-title">Stories</div>
    <div class="rs-stories">
      <div class="rs-story rs-story-new" title="Add story" onclick="alert('Story upload coming soon!')">
        <span>+</span>
      </div>
      <div class="rs-story" style="background:linear-gradient(135deg,#f15bb5,#9b5de5)">You</div>
      <div class="rs-story" style="background:linear-gradient(135deg,#00c4ff,#0077ff)">Jane</div>
      <div class="rs-story" style="background:linear-gradient(135deg,#39d353,#00bb77)">Alex</div>
      <div class="rs-story" style="background:linear-gradient(135deg,#f5c518,#e07b00)">Sam</div>
    </div>
  </div>

  <!-- ── Calendar ── -->
  <div class="rs-card" id="calCard">
    <div class="rs-card-title">
      📅 Calendar
      <span id="calNav" style="display:flex;gap:4px;margin-left:auto;">
        <button class="rs-nav-btn" id="calPrev">‹</button>
        <button class="rs-nav-btn" id="calNext">›</button>
      </span>
    </div>
    <div id="calMonthLabel" style="text-align:center;font-weight:700;font-size:.9rem;margin-bottom:8px;color:var(--rs-acc)"></div>
    <div id="calGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;"></div>
    <div id="calEvents" style="margin-top:10px;"></div>
  </div>

  <!-- ── To-do list ── -->
  <div class="rs-card" id="todoCard">
    <div class="rs-card-title">✅ To-do</div>
    <div style="display:flex;gap:6px;margin-bottom:8px;">
      <input id="todoInput" placeholder="Add task…" style="flex:1;padding:6px 10px;border-radius:8px;border:1px solid var(--rs-bdr);background:var(--rs-inp);color:var(--rs-txt);font-size:.82rem;outline:none;">
      <input id="todoTime" type="time" style="padding:5px;border-radius:8px;border:1px solid var(--rs-bdr);background:var(--rs-inp);color:var(--rs-txt);font-size:.82rem;outline:none;">
      <button id="todoAdd" style="background:var(--rs-acc);border:none;color:#fff;border-radius:8px;padding:6px 10px;cursor:pointer;font-size:.85rem;font-weight:700;">+</button>
    </div>
    <ul id="todoList" style="list-style:none;padding:0;margin:0;max-height:220px;overflow-y:auto;"></ul>
  </div>

</aside>

<style>
/* ── CSS variables (inherit theme) ── */
:root{
  --rs-acc:#9b5de5;
  --rs-bg:#fff;
  --rs-card:#fff;
  --rs-bdr:#eee;
  --rs-txt:#333;
  --rs-sub:#888;
  --rs-inp:#f5f5f5;
  --rs-today:#9b5de5;
  --rs-event:rgba(155,93,229,.18);
}
<?php if ($is_dark ?? false): ?>
:root{
  --rs-acc:#bb86fc;
  --rs-bg:#111;
  --rs-card:#1e1e1e;
  --rs-bdr:#2e2e2e;
  --rs-txt:#eee;
  --rs-sub:#777;
  --rs-inp:#2a2a2a;
  --rs-today:#9b5de5;
  --rs-event:rgba(155,93,229,.25);
}
<?php endif; ?>

.right-sidebar{
  flex:0 0 280px;width:280px;
  padding:12px;
  background:var(--rs-bg);
  border-left:1px solid var(--rs-bdr);
  overflow-y:auto;
  display:flex;flex-direction:column;gap:12px;
}
.rs-card{
  background:var(--rs-card);
  border:1px solid var(--rs-bdr);
  border-radius:14px;
  padding:14px;
  box-shadow:0 2px 8px rgba(0,0,0,.04);
}
.rs-card-title{
  font-weight:700;font-size:.88rem;
  color:var(--rs-txt);
  margin-bottom:10px;
  display:flex;align-items:center;gap:6px;
}
.rs-nav-btn{
  background:var(--rs-inp);border:1px solid var(--rs-bdr);
  border-radius:6px;padding:2px 8px;cursor:pointer;
  color:var(--rs-acc);font-weight:700;font-size:.9rem;
}

/* Stories */
.rs-stories{display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;}
.rs-stories::-webkit-scrollbar{height:3px;}
.rs-stories::-webkit-scrollbar-thumb{background:var(--rs-acc);border-radius:3px;}
.rs-story{
  width:52px;height:52px;border-radius:50%;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-size:.72rem;font-weight:700;color:#fff;
  cursor:pointer;border:2px solid var(--rs-acc);
  background:#ccc;
}
.rs-story-new{
  background:transparent !important;
  border:2px dashed var(--rs-acc);
  color:var(--rs-acc);font-size:1.4rem;font-weight:300;
}

/* Calendar */
.cal-day-hdr{
  font-size:.68rem;font-weight:700;text-align:center;
  color:var(--rs-sub);padding:3px 0;
}
.cal-day{
  width:100%;aspect-ratio:1;border-radius:8px;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  font-size:.78rem;cursor:pointer;position:relative;
  transition:background .15s;color:var(--rs-txt);border:none;background:transparent;
}
.cal-day:hover{background:var(--rs-event);}
.cal-day.today{
  background:var(--rs-today);color:#fff;font-weight:700;
  box-shadow:0 2px 8px rgba(155,93,229,.35);
}
.cal-day.today:hover{background:var(--rs-today);}
.cal-day.other-month{color:var(--rs-sub);opacity:.5;}
.cal-day.has-event::after{
  content:'';position:absolute;bottom:3px;
  width:5px;height:5px;border-radius:50%;
  background:var(--rs-acc);
}
.cal-day.today.has-event::after{background:#fff;}
.cal-day.selected{outline:2px solid var(--rs-acc);outline-offset:1px;}

/* Event list under calendar */
.cal-event-item{
  display:flex;align-items:flex-start;gap:8px;
  padding:7px 8px;border-radius:10px;margin-bottom:5px;
  background:var(--rs-event);font-size:.78rem;color:var(--rs-txt);
}
.cal-event-item .ev-dot{
  width:8px;height:8px;border-radius:50%;
  background:var(--rs-acc);flex-shrink:0;margin-top:3px;
}
.cal-event-item .ev-text{flex:1;line-height:1.35;}
.cal-event-item .ev-del{
  background:none;border:none;color:var(--rs-sub);
  cursor:pointer;font-size:14px;padding:0;line-height:1;flex-shrink:0;
}
.cal-event-item .ev-del:hover{color:#ff6b6b;}
.cal-no-events{font-size:.78rem;color:var(--rs-sub);padding:4px 0;}

/* Event add modal */
.cal-modal-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.5);
  z-index:99999;display:flex;align-items:center;justify-content:center;
  backdrop-filter:blur(4px);
}
.cal-modal{
  background:var(--rs-card);border:1px solid var(--rs-bdr);
  border-radius:18px;padding:22px 24px;width:300px;
  box-shadow:0 16px 48px rgba(0,0,0,.25);color:var(--rs-txt);
}
.cal-modal h4{margin:0 0 14px;font-size:1rem;color:var(--rs-acc);}
.cal-modal input,.cal-modal textarea{
  width:100%;padding:8px 12px;border-radius:10px;
  border:1px solid var(--rs-bdr);background:var(--rs-inp);
  color:var(--rs-txt);font-size:.85rem;margin-bottom:8px;
  outline:none;font-family:inherit;
}
.cal-modal textarea{resize:none;height:64px;}
.cal-modal-btns{display:flex;gap:8px;margin-top:4px;}
.cal-modal-btns button{flex:1;padding:8px;border-radius:10px;border:none;
  font-weight:700;cursor:pointer;font-size:.85rem;}
.cal-save{background:var(--rs-acc);color:#fff;}
.cal-cancel{background:var(--rs-inp);color:var(--rs-txt);}

/* To-do */
#todoList li{
  display:flex;align-items:flex-start;gap:8px;
  padding:7px 6px;border-bottom:1px solid var(--rs-bdr);
  font-size:.82rem;color:var(--rs-txt);
}
#todoList li:last-child{border-bottom:none;}
#todoList li input[type=checkbox]{margin-top:2px;accent-color:var(--rs-acc);flex-shrink:0;}
#todoList li .td-text{flex:1;line-height:1.35;}
#todoList li .td-time{font-size:.7rem;color:var(--rs-acc);white-space:nowrap;}
#todoList li .td-del{background:none;border:none;color:var(--rs-sub);cursor:pointer;font-size:14px;flex-shrink:0;padding:0;}
#todoList li .td-del:hover{color:#ff6b6b;}
#todoList li.done .td-text{text-decoration:line-through;color:var(--rs-sub);}

@media(max-width:992px){.right-sidebar{display:none;}}
</style>

<script>
/* ════════════════════════════════════════════════
   CALENDAR
════════════════════════════════════════════════ */
(function(){
  const DAYS = ['Su','Mo','Tu','We','Th','Fr','Sa'];
  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

  let viewYear, viewMonth, selectedDate = null;
  const today = new Date();

  function loadEvents(){ try{ return JSON.parse(localStorage.getItem('cx_cal_events')||'{}'); }catch(e){return{};} }
  function saveEvents(ev){ localStorage.setItem('cx_cal_events',JSON.stringify(ev)); }

  function dateKey(y,m,d){ return `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`; }

  function renderCal(){
    const grid = document.getElementById('calGrid');
    const label= document.getElementById('calMonthLabel');
    if(!grid||!label) return;
    label.textContent = MONTHS[viewMonth] + ' ' + viewYear;
    grid.innerHTML = '';
    const events = loadEvents();

    // Day headers
    DAYS.forEach(d=>{ const h=document.createElement('div'); h.className='cal-day-hdr'; h.textContent=d; grid.appendChild(h); });

    const first = new Date(viewYear, viewMonth, 1).getDay();
    const daysInMonth = new Date(viewYear, viewMonth+1, 0).getDate();
    const prevDays = new Date(viewYear, viewMonth, 0).getDate();

    // Prev month filler
    for(let i=first-1;i>=0;i--){
      const btn=document.createElement('button'); btn.className='cal-day other-month';
      btn.textContent=prevDays-i; grid.appendChild(btn);
    }

    // This month
    for(let d=1;d<=daysInMonth;d++){
      const btn=document.createElement('button'); btn.className='cal-day';
      const key=dateKey(viewYear,viewMonth,d);
      const isToday=(d===today.getDate()&&viewMonth===today.getMonth()&&viewYear===today.getFullYear());
      const isSelected=(selectedDate===key);
      if(isToday) btn.classList.add('today');
      if(isSelected) btn.classList.add('selected');
      if(events[key]&&events[key].length>0) btn.classList.add('has-event');
      btn.textContent=d;
      btn.addEventListener('click',()=>{ selectedDate=key; renderCal(); showDateEvents(key); });
      grid.appendChild(btn);
    }

    // Next month filler
    const remaining = 42 - first - daysInMonth;
    for(let d=1;d<=remaining&&d<=14;d++){
      const btn=document.createElement('button'); btn.className='cal-day other-month';
      btn.textContent=d; grid.appendChild(btn);
    }

    // Show today's events by default
    if(!selectedDate) showDateEvents(dateKey(today.getFullYear(),today.getMonth(),today.getDate()));
    else showDateEvents(selectedDate);
  }

  function showDateEvents(key){
    const box = document.getElementById('calEvents');
    if(!box) return;
    const events = loadEvents();
    const list = events[key]||[];
    const [yr,mo,dy] = key.split('-');
    const label = `${parseInt(dy)} ${MONTHS[parseInt(mo)-1]} ${yr}`;

    if(list.length===0){
      box.innerHTML=`<div class="cal-no-events">No events for ${label}. <button onclick="openAddEvent('${key}')" style="background:none;border:none;color:var(--rs-acc);cursor:pointer;font-size:.78rem;font-weight:600;padding:0;">+ Add one</button></div>`;
      return;
    }

    box.innerHTML=`<div style="font-size:.72rem;font-weight:700;color:var(--rs-sub);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">${label}</div>`;
    list.forEach((ev,i)=>{
      const item=document.createElement('div'); item.className='cal-event-item';
      item.innerHTML=`<div class="ev-dot"></div><div class="ev-text"><strong>${escHtml(ev.title)}</strong>${ev.time?`<div style="font-size:.7rem;color:var(--rs-acc);">⏰ ${ev.time}</div>`:''}</div><button class="ev-del" title="Delete">✕</button>`;
      item.querySelector('.ev-del').addEventListener('click',()=>deleteEvent(key,i));
      box.appendChild(item);
    });
    const addBtn=document.createElement('button');
    addBtn.style.cssText='background:none;border:none;color:var(--rs-acc);cursor:pointer;font-size:.78rem;font-weight:600;padding:4px 0;';
    addBtn.textContent='+ Add event';
    addBtn.addEventListener('click',()=>openAddEvent(key));
    box.appendChild(addBtn);
  }

  function deleteEvent(key,index){
    const ev=loadEvents(); ev[key].splice(index,1);
    if(ev[key].length===0)delete ev[key];
    saveEvents(ev); renderCal();
  }

  function openAddEvent(key){
    // Remove existing modal
    document.getElementById('calModalOverlay')?.remove();
    const [yr,mo,dy]=key.split('-');
    const label=`${parseInt(dy)} ${MONTHS[parseInt(mo)-1]} ${yr}`;

    const overlay=document.createElement('div'); overlay.className='cal-modal-overlay'; overlay.id='calModalOverlay';
    overlay.innerHTML=`
      <div class="cal-modal">
        <h4>📅 ${label}</h4>
        <input id="calEvTitle" placeholder="Event title…" maxlength="80">
        <input id="calEvTime" type="time" placeholder="Time (optional)">
        <textarea id="calEvNote" placeholder="Note (optional)…"></textarea>
        <label style="font-size:.78rem;color:var(--rs-sub);display:flex;align-items:center;gap:6px;margin-bottom:8px;">
          <input type="checkbox" id="calEvRemind" style="accent-color:var(--rs-acc);"> Remind me on this day
        </label>
        <div class="cal-modal-btns">
          <button class="cal-cancel" id="calModalCancel">Cancel</button>
          <button class="cal-save" id="calModalSave">Save Event</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);
    document.getElementById('calEvTitle').focus();

    document.getElementById('calModalCancel').addEventListener('click',()=>overlay.remove());
    overlay.addEventListener('click',e=>{ if(e.target===overlay)overlay.remove(); });
    document.getElementById('calModalSave').addEventListener('click',()=>{
      const title=document.getElementById('calEvTitle').value.trim();
      if(!title){ document.getElementById('calEvTitle').style.border='1px solid #ff6b6b'; return; }
      const time=document.getElementById('calEvTime').value;
      const note=document.getElementById('calEvNote').value.trim();
      const remind=document.getElementById('calEvRemind').checked;

      const ev=loadEvents();
      if(!ev[key])ev[key]=[];
      ev[key].push({title,time,note,remind});
      saveEvents(ev);
      overlay.remove();
      renderCal();
      if(remind&&time) scheduleReminder(title,key,time);
    });
  }

  function scheduleReminder(title,dateKey,time){
    if(!('Notification' in window)){alert('Browser notifications not supported.');return;}
    Notification.requestPermission().then(perm=>{
      if(perm!=='granted'){alert('Please allow notifications for reminders.');return;}
      const [yr,mo,dy]=dateKey.split('-').map(Number);
      const [hr,min]=time.split(':').map(Number);
      const eventTime=new Date(yr,mo-1,dy,hr,min,0);
      const now=Date.now();
      const ms=eventTime.getTime()-now;
      if(ms<=0){new Notification('📅 Connectify Reminder',{body:`"${title}" was scheduled for today at ${time}`,icon:'favicon.ico'});return;}
      // Store reminder in localStorage so it survives page refresh
      const reminders=JSON.parse(localStorage.getItem('cx_reminders')||'[]');
      reminders.push({title,dateKey,time,fireAt:eventTime.getTime()});
      localStorage.setItem('cx_reminders',JSON.stringify(reminders));
      alert(`✅ Reminder set for ${time} on ${dateKey.split('-').reverse().join('/')}`);
    });
  }

  // Check pending reminders on load
  function checkReminders(){
    const reminders=JSON.parse(localStorage.getItem('cx_reminders')||'[]');
    const now=Date.now();
    const remaining=reminders.filter(r=>{
      if(r.fireAt<=now){
        if(Notification.permission==='granted'){
          new Notification('📅 Connectify Reminder',{body:`"${r.title}" at ${r.time}`,icon:'favicon.ico'});
        }
        return false;
      }
      return true;
    });
    localStorage.setItem('cx_reminders',JSON.stringify(remaining));
    // Check again in 1 minute
    setTimeout(checkReminders,60000);
  }

  function escHtml(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

  // Init
  viewYear=today.getFullYear(); viewMonth=today.getMonth();
  document.getElementById('calPrev').addEventListener('click',()=>{ viewMonth--; if(viewMonth<0){viewMonth=11;viewYear--;} renderCal(); });
  document.getElementById('calNext').addEventListener('click',()=>{ viewMonth++; if(viewMonth>11){viewMonth=0;viewYear++;} renderCal(); });
  renderCal();
  setTimeout(checkReminders,1000);
})();

/* ════════════════════════════════════════════════
   TO-DO LIST
════════════════════════════════════════════════ */
(function(){
  function loadTodos(){ try{ return JSON.parse(localStorage.getItem('cx_todos')||'[]'); }catch(e){return[];} }
  function saveTodos(t){ localStorage.setItem('cx_todos',JSON.stringify(t)); }

  function renderTodos(){
    const ul=document.getElementById('todoList');
    if(!ul) return;
    const todos=loadTodos();
    ul.innerHTML='';
    if(todos.length===0){
      ul.innerHTML='<li style="color:var(--rs-sub);font-size:.78rem;padding:6px 0;">No tasks yet!</li>';
      return;
    }
    todos.forEach((todo,i)=>{
      const li=document.createElement('li');
      if(todo.done)li.classList.add('done');
      li.innerHTML=`
        <input type="checkbox" ${todo.done?'checked':''}>
        <div class="td-text">${escHtml(todo.text)}</div>
        ${todo.time?`<span class="td-time">⏰ ${todo.time}</span>`:''}
        <button class="td-del" title="Remove">✕</button>`;
      li.querySelector('input').addEventListener('change',e=>{
        const t=loadTodos(); t[i].done=e.target.checked; saveTodos(t); renderTodos();
      });
      li.querySelector('.td-del').addEventListener('click',()=>{
        const t=loadTodos(); t.splice(i,1); saveTodos(t); renderTodos();
      });
      ul.appendChild(li);
    });
  }

  function addTodo(){
    const inp=document.getElementById('todoInput');
    const timeInp=document.getElementById('todoTime');
    const text=inp.value.trim();
    if(!text)return;
    const todos=loadTodos();
    const todo={text,time:timeInp.value||'',done:false,addedAt:Date.now()};
    todos.push(todo);
    saveTodos(todos);
    inp.value=''; timeInp.value='';
    renderTodos();
    if(todo.time) scheduleTodoReminder(todo);
  }

  function scheduleTodoReminder(todo){
    if(!('Notification' in window))return;
    Notification.requestPermission().then(perm=>{
      if(perm!=='granted')return;
      const todayDate=new Date();
      const [hr,min]=todo.time.split(':').map(Number);
      const fireTime=new Date(todayDate.getFullYear(),todayDate.getMonth(),todayDate.getDate(),hr,min,0);
      const ms=fireTime.getTime()-Date.now();
      if(ms>0){
        const reminders=JSON.parse(localStorage.getItem('cx_reminders')||'[]');
        reminders.push({title:'📝 '+todo.text,dateKey:'todo',time:todo.time,fireAt:fireTime.getTime()});
        localStorage.setItem('cx_reminders',JSON.stringify(reminders));
      }
    });
  }

  function escHtml(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

  document.getElementById('todoAdd').addEventListener('click',addTodo);
  document.getElementById('todoInput').addEventListener('keydown',e=>{ if(e.key==='Enter')addTodo(); });
  renderTodos();
})();
</script>