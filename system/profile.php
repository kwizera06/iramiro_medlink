<?php
// profile.php — User Profile (view info + change password)
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/database.php';

// Get logged-in user data
$userId = $_SESSION['user_id'] ?? null;
$profileData = null;
$userRoles = [];

if ($userId) {
    $stmt = $pdo->prepare("
        SELECT u.*, d.name AS department_name 
        FROM users u 
        LEFT JOIN departments d ON d.id = u.department_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $profileData = $stmt->fetch();

    // Get roles
    $roleStmt = $pdo->prepare("
        SELECT r.name FROM user_roles ur 
        JOIN roles r ON r.id = ur.role_id 
        WHERE ur.user_id = ?
    ");
    $roleStmt->execute([$userId]);
    $userRoles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fallback for demo/no session
if (!$profileData) {
    $profileData = [
        'id' => 0,
        'first_name' => $userData['first_name'] ?? 'System',
        'last_name' => $userData['last_name'] ?? 'Administrator',
        'email' => 'admin@medlink.rw',
        'username' => 'admin',
        'phone' => '—',
        'gender' => '—',
        'role' => 'admin',
        'department_name' => 'Administration',
        'employee_id' => $userData['employee_id'] ?? 'EMP-ADMIN-001',
        'date_of_birth' => null,
        'address' => null,
        'last_login' => null,
        'created_at' => null,
        'profile_picture' => null
    ];
    $userRoles = ['Administrator'];
}
?>

<div style="max-width:800px;margin:0 auto;">
  <!-- Page Header -->
  <div style="margin-bottom:24px;">
    <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">My Profile</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">View your profile information, change your picture and password</p>
  </div>

  <!-- Profile Info Card -->
  <div style="background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);margin-bottom:24px;">
    <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid var(--gray-200);">
      <label id="pr-avatar" title="Change profile picture" style="position:relative;flex-shrink:0;width:80px;height:80px;border-radius:50%;background:var(--primary-color);display:flex;align-items:center;justify-content:center;color:white;font-size:28px;font-weight:700;overflow:hidden;cursor:pointer;">
        <?php if (!empty($profileData['profile_picture'])): ?>
        <img src="<?php echo htmlspecialchars($profileData['profile_picture']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
        <?php else: echo strtoupper(substr($profileData['first_name'],0,1).substr($profileData['last_name'],0,1)); endif; ?>
        <span class="pr-avatar-overlay"><i class="fas fa-camera"></i></span>
        <?php if ($userId): ?><input type="file" id="pr-avatar-input" aria-label="Change profile picture" accept="image/jpeg,image/png,image/webp,image/gif" onchange="uploadAvatar(this)" style="position:absolute;width:1px;height:1px;opacity:0;"><?php endif; ?>
      </label>
      <style>
        .pr-avatar-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);font-size:20px;opacity:0;transition:opacity .15s ease-out;}
        #pr-avatar:hover .pr-avatar-overlay,#pr-avatar:focus-within .pr-avatar-overlay,#pr-avatar.loading .pr-avatar-overlay{opacity:1;}
      </style>
      <div>
        <h3 style="font-size:20px;font-weight:600;color:var(--gray-900);"><?php echo htmlspecialchars($profileData['first_name'].' '.$profileData['last_name']); ?></h3>
        <p style="color:var(--gray-500);font-size:14px;margin-top:2px;"><?php echo htmlspecialchars(implode(', ', $userRoles) ?: $profileData['role']); ?></p>
        <?php if(!empty($profileData['department_name'])): ?>
        <p style="color:var(--gray-400);font-size:13px;margin-top:2px;"><i class="fas fa-building" style="margin-right:4px;"></i><?php echo htmlspecialchars($profileData['department_name']); ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Email</label>
        <p style="font-size:14px;color:var(--gray-800);"><?php echo htmlspecialchars($profileData['email'] ?? '—'); ?></p>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Username</label>
        <p style="font-size:14px;color:var(--gray-800);"><?php echo htmlspecialchars($profileData['username'] ?? '—'); ?></p>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Phone</label>
        <p style="font-size:14px;color:var(--gray-800);"><?php echo htmlspecialchars($profileData['phone'] ?? '—'); ?></p>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Employee ID</label>
        <p style="font-size:14px;color:var(--primary-color);font-family:monospace;"><?php echo htmlspecialchars($profileData['employee_id'] ?? '—'); ?></p>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Gender</label>
        <p style="font-size:14px;color:var(--gray-800);"><?php echo ucfirst(htmlspecialchars($profileData['gender'] ?? '—')); ?></p>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Date of Birth</label>
        <p style="font-size:14px;color:var(--gray-800);"><?php echo $profileData['date_of_birth'] ? date('d M Y', strtotime($profileData['date_of_birth'])) : '—'; ?></p>
      </div>
      <div style="grid-column:1/-1;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Address</label>
        <p style="font-size:14px;color:var(--gray-800);"><?php echo htmlspecialchars($profileData['address'] ?? '—'); ?></p>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Last Login</label>
        <p style="font-size:14px;color:var(--gray-800);"><?php echo $profileData['last_login'] ? date('d M Y H:i', strtotime($profileData['last_login'])) : '—'; ?></p>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Account Created</label>
        <p style="font-size:14px;color:var(--gray-800);"><?php echo $profileData['created_at'] ? date('d M Y', strtotime($profileData['created_at'])) : '—'; ?></p>
      </div>
    </div>
  </div>

  <!-- Change Password Card -->
  <div style="background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);">
    <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);margin-bottom:4px;"><i class="fas fa-lock" style="color:var(--warning-color);margin-right:8px;"></i>Change Password</h3>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:20px;">Enter your current password and choose a new one.</p>

    <div id="pwd-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;"></div>

    <form id="pwd-form" onsubmit="changePassword(event)">
      <div style="display:grid;gap:14px;max-width:400px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Current Password *</label>
          <input id="pwd-current" type="password" required style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">New Password *</label>
          <input id="pwd-new" type="password" required minlength="6" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
          <p style="font-size:11px;color:var(--gray-400);margin-top:4px;">Minimum 6 characters</p>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Confirm New Password *</label>
          <input id="pwd-confirm" type="password" required minlength="6" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div style="padding-top:6px;">
          <button type="submit" id="pwd-btn" style="padding:11px 24px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;">
            <i class="fas fa-key"></i> Update Password
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
async function changePassword(e){
  e.preventDefault();
  const current=document.getElementById('pwd-current').value;
  const newPwd=document.getElementById('pwd-new').value;
  const confirm=document.getElementById('pwd-confirm').value;
  const al=document.getElementById('pwd-alert');
  const btn=document.getElementById('pwd-btn');
  al.style.display='none';

  if(newPwd.length<6){showAlert('New password must be at least 6 characters.','error');return;}
  if(newPwd!==confirm){showAlert('New passwords do not match.','error');return;}

  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Updating...';
  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=settings/users&action=change-password',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({current_password:current,new_password:newPwd})
    });
    const j=await r.json();
    if(j.success){
      showAlert('✓ Password changed successfully!','success');
      document.getElementById('pwd-form').reset();
    }else{
      showAlert(j.message||'Failed to change password.','error');
    }
  }catch(err){showAlert('Request failed. Please try again.','error');}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-key"></i> Update Password';}
}

async function uploadAvatar(input){
  const file=input.files[0]; if(!file) return;
  if(file.size>3*1024*1024){alert('Image too large (max 3MB).');input.value='';return;}
  const box=document.getElementById('pr-avatar'), icon=box.querySelector('.pr-avatar-overlay i');
  box.classList.add('loading'); icon.className='fas fa-spinner fa-spin';
  const fd=new FormData(); fd.append('avatar',file);
  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=settings/users&action=upload-avatar',{method:'POST',body:fd});
    const txt=await r.text();
    let j; try{ j=JSON.parse(txt); }catch(_){ j={success:false,message:'Server error ('+r.status+'): '+txt.replace(/<[^>]+>/g,' ').trim().slice(0,300)}; }
    if(!j.success){alert(j.message||'Upload failed.');return;}
    const src=j.data.profile_picture;
    // Same picture in the profile card and the header avatar, replacing the initials if there were no picture yet
    [box, document.getElementById('hdr-user-avatar')].forEach(el=>{
      if(!el) return;
      let img=el.querySelector('img');
      if(!img){
        [...el.childNodes].filter(n=>n.nodeType===3).forEach(n=>n.remove());
        img=document.createElement('img'); img.alt=''; img.style.cssText='width:100%;height:100%;object-fit:cover;';
        el.prepend(img);
      }
      img.src=src;
    });
  }catch(e){alert('Upload failed. Please try again. ('+e.message+')');}
  finally{box.classList.remove('loading'); icon.className='fas fa-camera'; input.value='';}
}

function showAlert(msg,type){
  const al=document.getElementById('pwd-alert');
  al.textContent=msg;
  al.style.display='block';
  if(type==='success'){al.style.background='#d1fae5';al.style.color='#065f46';al.style.border='1px solid #a7f3d0';}
  else{al.style.background='#fef2f2';al.style.color='#991b1b';al.style.border='1px solid #fecaca';}
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
