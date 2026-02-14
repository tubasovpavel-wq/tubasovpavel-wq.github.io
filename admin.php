<?php
session_start();

// === НАСТРОЙКИ ===
$ADMIN_LOGIN = 'admin';
$ADMIN_PASS  = 'remstroy2024';
$SITE_DIR    = __DIR__;
$IMG_DIR     = $SITE_DIR . '/images';
$ALLOWED_EXT = ['html','css','php','txt','xml','json','js'];
$MEDIA_EXT   = ['jpg','jpeg','png','webp','gif','svg','mp4','webm'];
$MAX_UPLOAD  = 10 * 1024 * 1024; // 10MB

// === CSRF ===
function csrf() { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrfOk() { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf']??'',$_POST['csrf']); }

// === АВТОРИЗАЦИЯ ===
if(isset($_POST['do_login'])){
    if($_POST['login']===$ADMIN_LOGIN && $_POST['pass']===$ADMIN_PASS){ $_SESSION['admin']=true; }
    else{ $_SESSION['err']='Неверный логин или пароль'; }
    header('Location: admin.php'); exit;
}
if(isset($_GET['logout'])){ session_destroy(); header('Location: admin.php'); exit; }

// === API (для авторизованных) ===
if(!empty($_SESSION['admin'])){
    // Сохранение файла
    if(isset($_POST['save_file']) && csrfOk()){
        $f=realpath($SITE_DIR.'/'.basename($_POST['file']));
        if($f && str_starts_with($f,$SITE_DIR) && basename($f)!=='admin.php'){
            $ext=strtolower(pathinfo($f,PATHINFO_EXTENSION));
            if(in_array($ext,$ALLOWED_EXT)){ file_put_contents($f,$_POST['content']); $_SESSION['ok']='Файл сохранён!'; }
            else $_SESSION['err']='Недопустимый тип файла';
        } else $_SESSION['err']='Недопустимый файл';
        header('Location: admin.php?page=editor&file='.urlencode(basename($_POST['file']))); exit;
    }
    // Загрузка медиа
    if(isset($_POST['upload_media']) && csrfOk() && !empty($_FILES['media'])){
        $tmp=$_FILES['media']['tmp_name']; $name=basename($_FILES['media']['name']);
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(in_array($ext,$MEDIA_EXT) && $_FILES['media']['size']<=$MAX_UPLOAD){
            $name=preg_replace('/[^a-zA-Z0-9а-яА-ЯёЁ._\- ]/u','',$name);
            if(move_uploaded_file($tmp,$IMG_DIR.'/'.$name)) $_SESSION['ok']='Файл загружен!';
            else $_SESSION['err']='Ошибка загрузки';
        } else $_SESSION['err']='Недопустимый файл или превышен размер (макс 10МБ)';
        header('Location: admin.php?page=media'); exit;
    }
    // Удаление медиа
    if(isset($_POST['delete_media']) && csrfOk()){
        $f=realpath($IMG_DIR.'/'.basename($_POST['del']));
        if($f && str_starts_with($f,realpath($IMG_DIR))){ @unlink($f); $_SESSION['ok']='Удалено!'; }
        header('Location: admin.php?page=media'); exit;
    }
}

$page=$_GET['page']??'dashboard';
$err=$_SESSION['err']??''; unset($_SESSION['err']);
$ok=$_SESSION['ok']??''; unset($_SESSION['ok']);
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Админ-панель | РЕМСТРОЙЛОГИСТИК</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Roboto',sans-serif;background:#0f0f0f;color:#e0e0e0;min-height:100vh}
a{color:#F7B500;text-decoration:none}a:hover{text-decoration:underline}

/* LOGIN */
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.login-box{background:#1a1a1a;padding:40px;border-radius:16px;width:380px;max-width:100%;box-shadow:0 20px 60px rgba(0,0,0,.5);border:1px solid #2a2a2a}
.login-box h1{font-size:24px;margin-bottom:8px;color:#F7B500}
.login-box p{color:#888;font-size:14px;margin-bottom:30px}
.login-box label{display:block;font-size:13px;color:#aaa;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.login-box input[type=text],.login-box input[type=password]{width:100%;padding:12px 16px;background:#111;border:1px solid #333;border-radius:8px;color:#fff;font-size:15px;margin-bottom:20px;transition:.3s}
.login-box input:focus{outline:none;border-color:#F7B500;box-shadow:0 0 0 3px rgba(247,181,0,.15)}
.login-btn{width:100%;padding:14px;background:#F7B500;color:#000;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;transition:.3s;text-transform:uppercase;letter-spacing:1px}
.login-btn:hover{background:#d99f00;transform:translateY(-2px)}

/* LAYOUT */
.layout{display:flex;min-height:100vh}
.sidebar-a{width:260px;background:#141414;border-right:1px solid #222;padding:25px 0;display:flex;flex-direction:column;position:fixed;height:100vh;z-index:10}
.sidebar-a .brand{padding:0 25px 25px;border-bottom:1px solid #222;margin-bottom:15px}
.sidebar-a .brand h2{color:#F7B500;font-size:18px}
.sidebar-a .brand small{color:#666;font-size:12px}
.sidebar-a nav a{display:flex;align-items:center;gap:12px;padding:12px 25px;color:#aaa;font-size:15px;font-weight:500;transition:.2s;border-left:3px solid transparent}
.sidebar-a nav a:hover,.sidebar-a nav a.act{color:#fff;background:#1c1c1c;border-left-color:#F7B500;text-decoration:none}
.sidebar-a .bottom{margin-top:auto;padding:20px 25px;border-top:1px solid #222}
.sidebar-a .bottom a{color:#888;font-size:13px}
.main-area{margin-left:260px;flex:1;padding:30px;min-height:100vh}

/* ALERT */
.alert{padding:12px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;font-weight:500;animation:fadeIn .3s}
.alert-ok{background:rgba(34,197,94,.15);color:#22c55e;border:1px solid rgba(34,197,94,.2)}
.alert-err{background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.2)}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

/* DASHBOARD */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:30px}
.stat-card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:25px;transition:.3s}
.stat-card:hover{border-color:#F7B500;transform:translateY(-3px);box-shadow:0 10px 30px rgba(247,181,0,.1)}
.stat-card .num{font-size:36px;font-weight:700;color:#F7B500}
.stat-card .lbl{color:#888;font-size:14px;margin-top:5px}
.file-list{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;overflow:hidden}
.file-list h3{padding:20px;border-bottom:1px solid #2a2a2a;font-size:16px}
.file-row{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-bottom:1px solid #1f1f1f;transition:.15s}
.file-row:hover{background:#1f1f1f}
.file-row .fname{font-weight:500;font-size:14px}
.file-row .fsize{color:#666;font-size:13px}
.file-row .fedit{background:#F7B500;color:#000;padding:6px 16px;border-radius:6px;font-size:13px;font-weight:600}
.file-row .fedit:hover{background:#d99f00;text-decoration:none}

/* EDITOR */
.editor-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px}
.editor-head h2{font-size:22px}
.save-btn{background:#F7B500;color:#000;padding:12px 30px;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:.3s}
.save-btn:hover{background:#d99f00;transform:translateY(-2px)}
.code-area{width:100%;min-height:600px;background:#111;color:#c9d1d9;border:1px solid #2a2a2a;border-radius:12px;padding:20px;font-family:'Courier New',monospace;font-size:14px;line-height:1.6;resize:vertical;tab-size:4}
.code-area:focus{outline:none;border-color:#F7B500}

/* MEDIA */
.media-upload{background:#1a1a1a;border:2px dashed #333;border-radius:12px;padding:30px;text-align:center;margin-bottom:30px;transition:.3s}
.media-upload:hover{border-color:#F7B500}
.media-upload input[type=file]{display:none}
.media-upload label{cursor:pointer;color:#F7B500;font-weight:600;font-size:16px}
.upload-btn{background:#F7B500;color:#000;padding:10px 25px;border:none;border-radius:8px;font-weight:700;cursor:pointer;margin-top:15px;transition:.3s}
.upload-btn:hover{background:#d99f00}
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:15px}
.media-card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:10px;overflow:hidden;transition:.3s;position:relative}
.media-card:hover{border-color:#F7B500;transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.3)}
.media-card img{width:100%;height:160px;object-fit:cover;display:block}
.media-card .mc-info{padding:12px}
.media-card .mc-name{font-size:12px;color:#ccc;word-break:break-all;margin-bottom:8px}
.media-card .mc-size{font-size:11px;color:#666}
.mc-del{position:absolute;top:8px;right:8px;background:rgba(239,68,68,.9);color:#fff;border:none;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;opacity:0;transition:.2s}
.media-card:hover .mc-del{opacity:1}

.page-title{font-size:28px;font-weight:700;margin-bottom:25px;color:#fff}
.page-title span{color:#F7B500}

@media(max-width:768px){
    .sidebar-a{position:fixed;left:-260px;transition:.3s}.sidebar-a.open{left:0}
    .main-area{margin-left:0}
    .mob-toggle{display:block!important}
}
.mob-toggle{display:none;position:fixed;top:15px;left:15px;z-index:100;background:#F7B500;color:#000;border:none;width:44px;height:44px;border-radius:10px;font-size:22px;cursor:pointer}
</style>
</head>
<body>
<?php if(empty($_SESSION['admin'])): ?>
<!-- ===== LOGIN ===== -->
<div class="login-wrap">
<div class="login-box">
    <h1>🔐 Админ-панель</h1>
    <p>РЕМСТРОЙЛОГИСТИК — управление сайтом</p>
    <?php if($err): ?><div class="alert alert-err"><?=htmlspecialchars($err)?></div><?php endif ?>
    <form method="POST">
        <label>Логин</label>
        <input type="text" name="login" required autocomplete="username" placeholder="admin">
        <label>Пароль</label>
        <input type="password" name="pass" required autocomplete="current-password" placeholder="••••••••">
        <button type="submit" name="do_login" class="login-btn">Войти</button>
    </form>
</div>
</div>
<?php else: ?>
<!-- ===== ADMIN ===== -->
<button class="mob-toggle" onclick="document.querySelector('.sidebar-a').classList.toggle('open')">☰</button>
<div class="layout">
<aside class="sidebar-a">
    <div class="brand"><h2>⚙️ Админ</h2><small>РЕМСТРОЙЛОГИСТИК</small></div>
    <nav>
        <a href="admin.php?page=dashboard" class="<?=$page=='dashboard'?'act':''?>">📊 Дашборд</a>
        <a href="admin.php?page=editor" class="<?=$page=='editor'?'act':''?>">📝 Редактор файлов</a>
        <a href="admin.php?page=media" class="<?=$page=='media'?'act':''?>">🖼️ Медиа-файлы</a>
    </nav>
    <div class="bottom">
        <a href="index.html" target="_blank">← На сайт</a><br><br>
        <a href="admin.php?logout=1">🚪 Выйти</a>
    </div>
</aside>
<main class="main-area">
<?php if($ok): ?><div class="alert alert-ok"><?=htmlspecialchars($ok)?></div><?php endif ?>
<?php if($err): ?><div class="alert alert-err"><?=htmlspecialchars($err)?></div><?php endif ?>

<?php
// ===== DASHBOARD =====
if($page=='dashboard'):
    $files=array_filter(scandir($SITE_DIR),fn($f)=>is_file("$SITE_DIR/$f")&&in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),$ALLOWED_EXT)&&$f!=='admin.php');
    $imgs=is_dir($IMG_DIR)?array_filter(scandir($IMG_DIR),fn($f)=>is_file("$IMG_DIR/$f")):[];
    $imgSize=0; foreach($imgs as $i) $imgSize+=filesize("$IMG_DIR/$i");
?>
<h1 class="page-title">📊 <span>Дашборд</span></h1>
<div class="stats">
    <div class="stat-card"><div class="num"><?=count($files)?></div><div class="lbl">Файлов сайта</div></div>
    <div class="stat-card"><div class="num"><?=count($imgs)?></div><div class="lbl">Изображений</div></div>
    <div class="stat-card"><div class="num"><?=round($imgSize/1024/1024,1)?> МБ</div><div class="lbl">Размер медиа</div></div>
</div>
<div class="file-list">
    <h3>Файлы сайта</h3>
    <?php foreach($files as $f): $sz=filesize("$SITE_DIR/$f"); ?>
    <div class="file-row">
        <span class="fname"><?=htmlspecialchars($f)?></span>
        <span class="fsize"><?=round($sz/1024,1)?> КБ</span>
        <a href="admin.php?page=editor&file=<?=urlencode($f)?>" class="fedit">Редактировать</a>
    </div>
    <?php endforeach ?>
</div>

<?php
// ===== EDITOR =====
elseif($page=='editor'):
    $files=array_filter(scandir($SITE_DIR),fn($f)=>is_file("$SITE_DIR/$f")&&in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),$ALLOWED_EXT)&&$f!=='admin.php');
    $cur=$_GET['file']??'';
    $content='';
    if($cur && in_array($cur,$files)){ $content=file_get_contents("$SITE_DIR/$cur"); }
?>
<h1 class="page-title">📝 <span>Редактор файлов</span></h1>
<div class="file-list" style="margin-bottom:25px">
    <h3>Выберите файл</h3>
    <?php foreach($files as $f): ?>
    <div class="file-row">
        <span class="fname" style="<?=$f==$cur?'color:#F7B500;font-weight:700':''?>"><?=htmlspecialchars($f)?></span>
        <a href="admin.php?page=editor&file=<?=urlencode($f)?>" class="fedit"><?=$f==$cur?'✏️ Открыт':'Открыть'?></a>
    </div>
    <?php endforeach ?>
</div>
<?php if($cur && in_array($cur,$files)): ?>
<form method="POST">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <input type="hidden" name="file" value="<?=htmlspecialchars($cur)?>">
    <div class="editor-head">
        <h2>Редактирование: <span style="color:#F7B500"><?=htmlspecialchars($cur)?></span></h2>
        <button type="submit" name="save_file" class="save-btn">💾 Сохранить</button>
    </div>
    <textarea name="content" class="code-area" spellcheck="false"><?=htmlspecialchars($content)?></textarea>
</form>
<?php endif ?>

<?php
// ===== MEDIA =====
elseif($page=='media'):
    $imgs=is_dir($IMG_DIR)?array_filter(scandir($IMG_DIR),fn($f)=>is_file("$IMG_DIR/$f")&&in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),$MEDIA_EXT)):[];
?>
<h1 class="page-title">🖼️ <span>Медиа-файлы</span> <small style="font-size:14px;color:#666">(<?=count($imgs)?> файлов)</small></h1>
<form method="POST" enctype="multipart/form-data" class="media-upload">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <label for="mediaFile">📂 Выберите файл для загрузки (JPG, PNG, WEBP, GIF, MP4, макс 10МБ)</label><br><br>
    <input type="file" name="media" id="mediaFile" accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.mp4,.webm" onchange="document.getElementById('fname').textContent=this.files[0]?.name||''">
    <div id="fname" style="color:#888;margin-top:10px;font-size:13px"></div>
    <button type="submit" name="upload_media" class="upload-btn">⬆️ Загрузить</button>
</form>
<div class="media-grid">
<?php foreach($imgs as $f):
    $sz=filesize("$IMG_DIR/$f");
    $ext=strtolower(pathinfo($f,PATHINFO_EXTENSION));
    $isVid=in_array($ext,['mp4','webm']);
?>
<div class="media-card">
    <?php if($isVid): ?>
        <video src="images/<?=htmlspecialchars($f)?>" style="width:100%;height:160px;object-fit:cover" muted></video>
    <?php else: ?>
        <img src="images/<?=htmlspecialchars($f)?>" alt="<?=htmlspecialchars($f)?>" loading="lazy">
    <?php endif ?>
    <div class="mc-info">
        <div class="mc-name"><?=htmlspecialchars($f)?></div>
        <div class="mc-size"><?=round($sz/1024,1)?> КБ</div>
    </div>
    <form method="POST" style="display:inline" onsubmit="return confirm('Удалить <?=htmlspecialchars($f)?>?')">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <input type="hidden" name="del" value="<?=htmlspecialchars($f)?>">
        <button type="submit" name="delete_media" class="mc-del">✕</button>
    </form>
</div>
<?php endforeach ?>
</div>
<?php endif ?>

</main>
</div>
<?php endif ?>
</body>
</html>
