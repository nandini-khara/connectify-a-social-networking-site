<!DOCTYPE html>
<html lang="en">
<head>
  <!-- sign_uppage.php -->
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connectify – Sign Up</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --primary:#6a1b9a; --primary-dark:#4a0072;
      --success:#2e7d32; --success-bg:#e8f5e9; --success-bdr:#a5d6a7;
      --error:#c62828;   --error-bg:#ffebee;   --error-bdr:#ef9a9a;
      --text:#1a1a2e; --muted:#6b7280; --border:#e0e0e0; --white:#fff;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{
      font-family:'DM Sans',sans-serif;
      background:linear-gradient(135deg,#e8d5f5 0%,#fce4ec 100%);
      min-height:100vh; display:flex; justify-content:center;
      align-items:flex-start; padding:2rem 1rem; overflow-y:auto;
    }
    .card{
      background:var(--white); width:100%; max-width:440px;
      border-radius:24px; padding:2.5rem 2.25rem;
      box-shadow:0 24px 48px rgba(106,27,154,.13);
      margin-top:1rem; animation:rise .45s ease both;
    }
    @keyframes rise{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

    /* ── Brand ── */
    .brand{text-align:center;margin-bottom:1.6rem;}
    .brand-name{font-family:'Playfair Display',serif;font-size:2rem;color:var(--primary);}
    .brand-sub{font-size:.78rem;color:var(--muted);letter-spacing:.5px;margin-top:2px;}

    h2{text-align:center;font-size:1.1rem;font-weight:600;color:var(--text);margin-bottom:1.4rem;}

    /* ── Alerts ── */
    .alert{
      display:flex;gap:.5rem;align-items:flex-start;
      padding:.75rem 1rem;border-radius:12px;font-size:.855rem;
      line-height:1.5;margin-bottom:1.2rem;animation:rise .3s ease;
    }
    .alert-error  {background:var(--error-bg);  color:var(--error);  border:1px solid var(--error-bdr);}
    .alert-success{background:var(--success-bg);color:var(--success);border:1px solid var(--success-bdr);}
    .alert-icon{font-size:1rem;flex-shrink:0;margin-top:1px;}

    /* ── Fields ── */
    .field{margin-bottom:1rem;text-align:left;}
    .field label{display:block;font-weight:600;font-size:.855rem;color:var(--text);margin-bottom:.28rem;}
    .field input[type=text],.field input[type=email],
    .field input[type=password],.field input[type=date]{
      width:100%;padding:.62rem .85rem;
      border:1.5px solid var(--border);border-radius:14px;
      font-family:inherit;font-size:.9rem;color:var(--text);
      background:#fafafa;outline:none;
      transition:border-color .2s,box-shadow .2s,background .2s;
    }
    .field input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(106,27,154,.08);background:#fff;}
    .field input.is-ok {border-color:var(--success);background:#f8fff8;}
    .field input.is-err{border-color:var(--error);  background:#fff8f8;}
    .hint{font-size:.76rem;margin-top:4px;display:block;min-height:1em;}
    .hint.ok {color:var(--success);}
    .hint.err{color:var(--error);}
    .hint.chk{color:var(--muted);font-style:italic;}

    /* ── Gender ── */
    .gender-row{display:flex;gap:1.2rem;flex-wrap:wrap;margin-top:.3rem;}
    .gender-opt{display:flex;align-items:center;gap:.38rem;font-size:.875rem;cursor:pointer;}
    .gender-opt input{accent-color:var(--primary);width:16px;height:16px;cursor:pointer;}

    /* ── Password strength ── */
    .str-bar{display:flex;gap:3px;margin-top:6px;}
    .str-bar span{height:4px;flex:1;border-radius:4px;background:var(--border);transition:background .3s;}
    .str-lbl{font-size:.73rem;color:var(--muted);margin-top:3px;}

    /* ── Show password ── */
    .show-pwd{display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:var(--muted);margin-top:.5rem;cursor:pointer;user-select:none;}
    .show-pwd input{accent-color:var(--primary);cursor:pointer;}

    .divider{height:1px;background:var(--border);margin:1.1rem 0;}

    /* ── Button ── */
    .btn{
      width:100%;padding:.8rem;
      background:linear-gradient(135deg,var(--primary),#9c27b0);
      color:#fff;border:none;border-radius:14px;
      font-family:inherit;font-weight:700;font-size:.95rem;
      cursor:pointer;margin-top:.4rem;
      transition:opacity .2s,transform .15s;overflow:hidden;
    }
    .btn:hover{opacity:.91;} .btn:active{transform:scale(.98);}
    .btn:disabled{opacity:.6;cursor:not-allowed;}

    /* ── Spinner ── */
    .spinner{
      display:none;width:18px;height:18px;margin:0 auto;
      border:2.5px solid rgba(255,255,255,.35);
      border-top-color:#fff;border-radius:50%;
      animation:spin .7s linear infinite;
    }
    @keyframes spin{to{transform:rotate(360deg)}}

    /* ── Footer ── */
    .footer-link{text-align:center;margin-top:1.2rem;font-size:.83rem;color:var(--muted);}
    .footer-link a{color:var(--primary);font-weight:600;text-decoration:none;}
    .footer-link a:hover{text-decoration:underline;}
  </style>
</head>
<body>
<div class="card">

  <!-- Brand -->
  <div class="brand">
    <div class="brand-name">Connectify</div>
    <div class="brand-sub">Connect · Share · Belong</div>
  </div>
  <h2>Create Your Account</h2>

  <!-- Server-side error shown via GET param (original behaviour) -->
  <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error">
      <span class="alert-icon">⚠️</span>
      <span><?php echo htmlspecialchars($_GET['error']); ?></span>
    </div>
  <?php endif; ?>

  <form id="signup-form" action="sendcode1.php" method="POST" novalidate>

    <div class="field">
      <label for="fullname">Full Name</label>
      <input type="text" id="fullname" name="full_name" placeholder="Your full name" required/>
    </div>

    <div class="field">
      <label>Gender</label>
      <div class="gender-row">
        <label class="gender-opt"><input type="radio" name="gender" value="male"   required/> Male</label>
        <label class="gender-opt"><input type="radio" name="gender" value="female"/> Female</label>
        <label class="gender-opt"><input type="radio" name="gender" value="other"/> Other</label>
      </div>
    </div>

    <div class="field">
      <label for="dob">Date of Birth</label>
      <input type="date" id="dob" name="dob" required/>
    </div>

    <div class="divider"></div>

    <div class="field">
      <label for="phone">Phone Number</label>
      <input type="text" id="phone" name="phone_number" placeholder="10-digit number" maxlength="10" required/>
      <span class="hint" id="phone-hint"></span>
    </div>

    <div class="field">
      <label for="email">Email ID</label>
      <input type="email" id="email" name="email_id" placeholder="you@example.com" required/>
      <span class="hint" id="email-hint"></span>
    </div>

    <div class="field">
      <label for="username">Username</label>
      <input type="text" id="username" name="user_name" placeholder="Choose a unique username" required/>
      <span class="hint" id="username-hint"></span>
    </div>

    <div class="divider"></div>

    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             placeholder="8–12 chars, include number &amp; symbol" required/>
      <div class="str-bar">
        <span id="s1"></span><span id="s2"></span><span id="s3"></span><span id="s4"></span>
      </div>
      <div class="str-lbl" id="str-lbl"></div>
    </div>

    <div class="field">
      <label for="confirm-password">Confirm Password</label>
      <input type="password" id="confirm-password" name="confirm_password" placeholder="Re-enter password" required/>
      <span class="hint" id="cpwd-hint"></span>
      <label class="show-pwd">
        <input type="checkbox" id="show-pwd"/> Show passwords
      </label>
    </div>

    <button type="submit" class="btn" id="submit-btn">
      <span id="btn-lbl">Sign Up</span>
      <div class="spinner" id="spinner"></div>
    </button>
  </form>

  <div class="footer-link">Already have an account? <a href="login.php">Log in</a></div>
</div>

<script>
  /* ── Live duplicate checks ── */
  const st = { phone:null, email:null, username:null };

  function hint(id,msg,type){
    const el=document.getElementById(id);
    el.textContent=msg; el.className='hint '+(type||'');
  }
  function mark(el,type){
    el.classList.remove('is-ok','is-err');
    if(type==='ok')  el.classList.add('is-ok');
    if(type==='err') el.classList.add('is-err');
  }
  const timers={};
  function debounce(k,fn,ms=520){clearTimeout(timers[k]);timers[k]=setTimeout(fn,ms);}

  function ajaxCheck(type,val,el,hintId,stKey){
    hint(hintId,'Checking…','chk');
    fetch('check_duplicate.php?type='+type+'&value='+encodeURIComponent(val))
      .then(r=>r.json())
      .then(d=>{
        if(d.exists){hint(hintId,d.message,'err');mark(el,'err');st[stKey]=false;}
        else        {hint(hintId,d.message,'ok'); mark(el,'ok'); st[stKey]=true; }
      })
      .catch(()=>hint(hintId,'',''));
  }

  /* Phone */
  document.getElementById('phone').addEventListener('input',function(){
    const v=this.value.trim();
    if(!v){hint('phone-hint','','');st.phone=null;mark(this,'');return;}
    if(/^\d{10}$/.test(v)){hint('phone-hint','✅ Looks good.','ok');mark(this,'ok');st.phone=true;}
    else                  {hint('phone-hint','❌ Must be exactly 10 digits.','err');mark(this,'err');st.phone=false;}
  });

  /* Email */
  document.getElementById('email').addEventListener('input',function(){
    const v=this.value.trim();
    if(!v){hint('email-hint','','');st.email=null;mark(this,'');return;}
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)){
      hint('email-hint','❌ Invalid email format.','err');mark(this,'err');st.email=false;return;
    }
    debounce('email',()=>ajaxCheck('email',v,this,'email-hint','email'));
  });

  /* Username */
  document.getElementById('username').addEventListener('input',function(){
    const v=this.value.trim();
    if(!v){hint('username-hint','','');st.username=null;mark(this,'');return;}
    debounce('username',()=>ajaxCheck('username',v,this,'username-hint','username'));
  });

  /* Password strength */
  const SC=['','#ef5350','#ffa726','#66bb6a','#2e7d32'];
  const SL=['','Weak','Fair','Good','Strong'];
  function pwdStrength(p){
    let s=0;
    if(p.length>=8)s++;if(/[A-Z]/.test(p))s++;
    if(/[0-9]/.test(p))s++;if(/[!@#$%^&*]/.test(p))s++;
    return s;
  }
  document.getElementById('password').addEventListener('input',function(){
    const s=pwdStrength(this.value);
    for(let i=1;i<=4;i++)
      document.getElementById('s'+i).style.background=i<=s?SC[s]:'var(--border)';
    document.getElementById('str-lbl').textContent=this.value?'Strength: '+SL[s]:'';
    checkCpwd();
  });

  /* Confirm password */
  function checkCpwd(){
    const p=document.getElementById('password').value;
    const c=document.getElementById('confirm-password').value;
    const el=document.getElementById('confirm-password');
    if(!c){hint('cpwd-hint','','');mark(el,'');return;}
    if(p===c){hint('cpwd-hint','✅ Passwords match.','ok');     mark(el,'ok');}
    else     {hint('cpwd-hint','❌ Passwords do not match.','err');mark(el,'err');}
  }
  document.getElementById('confirm-password').addEventListener('input',checkCpwd);

  /* Show / hide password */
  document.getElementById('show-pwd').addEventListener('change',function(){
    const t=this.checked?'text':'password';
    document.getElementById('password').type=t;
    document.getElementById('confirm-password').type=t;
  });

  /* ── Form submit validation ── */
  document.getElementById('signup-form').addEventListener('submit',function(e){
    const phone =document.getElementById('phone').value.trim();
    const email =document.getElementById('email').value.trim();
    const user  =document.getElementById('username').value.trim();
    const pwd   =document.getElementById('password').value;
    const cpwd  =document.getElementById('confirm-password').value;
    const gender=document.querySelector('input[name=gender]:checked');
    const errs  =[];

    if(!gender) errs.push('Please select your gender.');
    if(!phone)  errs.push('Phone number is required.');
    if(!email)  errs.push('Email address is required.');
    if(!user)   errs.push('Username is required.');
    if(!pwd)    errs.push('Password is required.');
    if(!cpwd)   errs.push('Please confirm your password.');

    if(phone&&st.phone===false)    errs.push('Phone must be exactly 10 digits.');
    if(email&&st.email===false)    errs.push('This email is already registered or invalid.');
    if(user&&st.username===false)  errs.push('This username is already taken.');

    if(phone&&st.phone===null&&!/^\d{10}$/.test(phone))
      errs.push('Phone must be exactly 10 digits.');
    if(email&&st.email===null&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
      errs.push('Invalid email format.');

    const pwdOk=/^(?=.*[0-9])(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,12}$/.test(pwd);
    if(pwd&&!pwdOk) errs.push('Password: 8–12 chars, at least one number and one symbol (!@#$%^&*).');
    if(pwd&&cpwd&&pwd!==cpwd) errs.push('Passwords do not match.');

    if(errs.length){
      e.preventDefault();
      document.querySelectorAll('.alert').forEach(a=>a.remove());
      const div=document.createElement('div');
      div.className='alert alert-error';
      div.innerHTML='<span class="alert-icon">⚠️</span><span>'+errs.map(x=>'• '+x).join('<br>')+'</span>';
      document.querySelector('h2').insertAdjacentElement('afterend',div);
      div.scrollIntoView({behavior:'smooth',block:'nearest'});
      return;
    }

    /* Show spinner on valid submit */
    document.getElementById('btn-lbl').style.display='none';
    document.getElementById('spinner').style.display='block';
    document.getElementById('submit-btn').disabled=true;
  });

  /* Clean up error GET param from URL */
  if(window.location.search.includes('error=')){
    const url=new URL(window.location);
    url.searchParams.delete('error');
    window.history.replaceState({},document.title,url.pathname);
  }
</script>
</body>
</html>