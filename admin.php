<?php
session_start();
$ADMIN_LOGIN = 'admin';
$ADMIN_PASS  = 'remstroy2024';
$SITE_DIR    = __DIR__;
$DATA_DIR    = $SITE_DIR . '/data';
$IMG_DIR     = $SITE_DIR . '/images';
$MEDIA_EXT   = ['jpg','jpeg','png','webp','gif','svg','mp4','webm'];
$MAX_UPLOAD  = 10 * 1024 * 1024;

if(!is_dir($DATA_DIR)) mkdir($DATA_DIR, 0755, true);

function csrf() { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrfOk() { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf']??'',$_POST['csrf']); }
function loadJson($f) { return file_exists($f) ? json_decode(file_get_contents($f), true) : []; }
function saveJson($f, $d) { file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }

// === МИГРАЦИЯ: извлечение данных из index.html при первом запуске ===
function migrateData($siteDir, $dataDir) {
    $html = file_get_contents($siteDir . '/index.html');
    // Извлечение machines
    if (!file_exists($dataDir.'/machines.json') && preg_match('/(?:const|let)\s+machines\s*=\s*\[(.*?)\];\s*$/ms', $html, $m)) {
        $js = '[' . $m[1] . ']';
        $js = preg_replace('/\/\/.*$/m', '', $js);
        $js = preg_replace("/(?<=[{,\s])(\w+)\s*:/", '"$1":', $js);
        $js = str_replace("'", '"', $js);
        $js = preg_replace('/,\s*([\]}])/s', '$1', $js);
        $data = json_decode($js, true);
        if ($data) saveJson($dataDir.'/machines.json', $data);
    }
    // Извлечение services
    if (!file_exists($dataDir.'/services.json') && preg_match('/(?:const|let)\s+services\s*=\s*\[(.*?)\];\s*$/ms', $html, $m)) {
        $js = '[' . $m[1] . ']';
        $js = preg_replace('/\/\/.*$/m', '', $js);
        $js = preg_replace("/(?<=[{,\s])(\w+)\s*:/", '"$1":', $js);
        $js = str_replace("'", '"', $js);
        $js = preg_replace('/,\s*([\]}])/s', '$1', $js);
        $data = json_decode($js, true);
        if ($data) saveJson($dataDir.'/services.json', $data);
    }
    // Контакты
    if (!file_exists($dataDir.'/contacts.json')) {
        saveJson($dataDir.'/contacts.json', [
            'phone'=>'+7 (926) 549-99-90','phoneRaw'=>'+79265499990',
            'email'=>'remstrojlogistic@yandex.ru',
            'address'=>'г. Балашиха, шоссе Энтузиастов, 32',
            'workHours'=>'Ежедневно 09:00–21:00',
            'whatsapp'=>'https://wa.me/79265499990',
            'telegram'=>'https://t.me/+79265499990'
        ]);
    }
    if (!file_exists($dataDir.'/content.json')) {
        saveJson($dataDir.'/content.json', [
            'heroTitle'=>'Аренда спецтехники<br>в Москве и области',
            'heroSubtitle'=>'Собственный автопарк. Подача техники в течение 4 часов. Работаем без выходных.',
            'footerText'=>'Надежная спецтехника для ваших задач. Работаем по Москве и Московской области 24/7.'
        ]);
    }
}

// Авторизация
if(isset($_POST['do_login'])){
    if($_POST['login']===$ADMIN_LOGIN && $_POST['pass']===$ADMIN_PASS){ $_SESSION['admin']=true; }
    else{ $_SESSION['err']='Неверный логин или пароль'; }
    header('Location: admin.php'); exit;
}
if(isset($_GET['logout'])){ session_destroy(); header('Location: admin.php'); exit; }

// API
if(!empty($_SESSION['admin'])){
    migrateData($SITE_DIR, $DATA_DIR);

    // Сохранение техники
    if(isset($_POST['save_machines']) && csrfOk()){
        $machines = json_decode($_POST['machines_data'], true);
        if($machines !== null){ saveJson($DATA_DIR.'/machines.json', $machines); $_SESSION['ok']='Техника сохранена!'; }
        else $_SESSION['err']='Ошибка данных';
        header('Location: admin.php?page=machines'); exit;
    }
    // Сохранение услуг
    if(isset($_POST['save_services']) && csrfOk()){
        $services = json_decode($_POST['services_data'], true);
        if($services !== null){ saveJson($DATA_DIR.'/services.json', $services); $_SESSION['ok']='Услуги сохранены!'; }
        else $_SESSION['err']='Ошибка данных';
        header('Location: admin.php?page=services'); exit;
    }
    // Сохранение контактов
    if(isset($_POST['save_contacts']) && csrfOk()){
        $c = ['phone'=>$_POST['phone'],'phoneRaw'=>$_POST['phoneRaw'],'email'=>$_POST['email'],
              'address'=>$_POST['address'],'workHours'=>$_POST['workHours'],
              'whatsapp'=>$_POST['whatsapp'],'telegram'=>$_POST['telegram']];
        saveJson($DATA_DIR.'/contacts.json', $c); $_SESSION['ok']='Контакты сохранены!';
        header('Location: admin.php?page=contacts'); exit;
    }
    // Сохранение контента
    if(isset($_POST['save_content']) && csrfOk()){
        $c = ['heroTitle'=>$_POST['heroTitle'],'heroSubtitle'=>$_POST['heroSubtitle'],'footerText'=>$_POST['footerText']];
        saveJson($DATA_DIR.'/content.json', $c); $_SESSION['ok']='Контент сохранён!';
        header('Location: admin.php?page=content'); exit;
    }
    // Загрузка медиа
    if(isset($_POST['upload_media']) && csrfOk() && !empty($_FILES['media'])){
        $name=basename($_FILES['media']['name']);
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(in_array($ext,$MEDIA_EXT) && $_FILES['media']['size']<=$MAX_UPLOAD){
            $name=preg_replace('/[^a-zA-Z0-9а-яА-ЯёЁ._\- ]/u','',$name);
            if(move_uploaded_file($_FILES['media']['tmp_name'],$IMG_DIR.'/'.$name)) $_SESSION['ok']='Файл загружен!';
            else $_SESSION['err']='Ошибка загрузки';
        } else $_SESSION['err']='Недопустимый файл (макс 10МБ)';
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

$categories = [
    'excavator'=>'Экскаваторы','mini_excavator'=>'Мини-экскаваторы','loader'=>'Погрузчики',
    'mini_loader'=>'Мини-погрузчики','crane'=>'Автокраны','truck'=>'Самосвалы',
    'manipulator'=>'Манипуляторы','bulldozer'=>'Бульдозеры','aerial'=>'Автовышки',
    'yamobur'=>'Ямобуры','road'=>'Дорожная','attachment'=>'Навесное','communal'=>'Коммунальная'
];
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
input,textarea,select{font-family:inherit}

.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.login-box{background:#1a1a1a;padding:40px;border-radius:16px;width:380px;max-width:100%;box-shadow:0 20px 60px rgba(0,0,0,.5);border:1px solid #2a2a2a}
.login-box h1{font-size:24px;margin-bottom:8px;color:#F7B500}
.login-box p{color:#888;font-size:14px;margin-bottom:30px}
.login-box label{display:block;font-size:13px;color:#aaa;margin-bottom:6px}
.login-box input{width:100%;padding:12px 16px;background:#111;border:1px solid #333;border-radius:8px;color:#fff;font-size:15px;margin-bottom:20px}
.login-box input:focus{outline:none;border-color:#F7B500}
.login-btn{width:100%;padding:14px;background:#F7B500;color:#000;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer}
.login-btn:hover{background:#d99f00}

.layout{display:flex;min-height:100vh}
.sb{width:240px;background:#141414;border-right:1px solid #222;padding:20px 0;display:flex;flex-direction:column;position:fixed;height:100vh;z-index:10;overflow-y:auto}
.sb .brand{padding:0 20px 20px;border-bottom:1px solid #222;margin-bottom:10px}
.sb .brand h2{color:#F7B500;font-size:16px}
.sb .brand small{color:#666;font-size:11px}
.sb nav a{display:flex;align-items:center;gap:10px;padding:11px 20px;color:#aaa;font-size:14px;font-weight:500;border-left:3px solid transparent;transition:.2s}
.sb nav a:hover,.sb nav a.act{color:#fff;background:#1c1c1c;border-left-color:#F7B500;text-decoration:none}
.sb .bot{margin-top:auto;padding:15px 20px;border-top:1px solid #222}
.sb .bot a{color:#888;font-size:13px}
.main{margin-left:240px;flex:1;padding:25px;min-height:100vh}

.alert{padding:12px 18px;border-radius:8px;margin-bottom:18px;font-size:14px;font-weight:500;animation:fi .3s}
.alert-ok{background:rgba(34,197,94,.15);color:#22c55e;border:1px solid rgba(34,197,94,.2)}
.alert-err{background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.2)}
@keyframes fi{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

.pt{font-size:24px;font-weight:700;margin-bottom:20px;color:#fff}.pt span{color:#F7B500}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:25px}
.sc{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:20px}
.sc .n{font-size:32px;font-weight:700;color:#F7B500}.sc .l{color:#888;font-size:13px;margin-top:4px}

.card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:20px;margin-bottom:15px}
.card:hover{border-color:#333}
.card h3{font-size:16px;margin-bottom:12px;color:#F7B500}
.fg{margin-bottom:12px}
.fg label{display:block;font-size:12px;color:#888;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
.fg input,.fg textarea,.fg select{width:100%;padding:10px 14px;background:#111;border:1px solid #333;border-radius:8px;color:#fff;font-size:14px}
.fg input:focus,.fg textarea:focus,.fg select:focus{outline:none;border-color:#F7B500}
.fg textarea{resize:vertical;min-height:60px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}

.btn{background:#F7B500;color:#000;padding:12px 28px;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:.3s}
.btn:hover{background:#d99f00;transform:translateY(-1px)}
.btn-sm{padding:8px 16px;font-size:13px;border-radius:6px}
.btn-red{background:#ef4444;color:#fff}.btn-red:hover{background:#dc2626}
.btn-green{background:#22c55e;color:#fff}.btn-green:hover{background:#16a34a}
.btn-outline{background:transparent;border:1px solid #F7B500;color:#F7B500}.btn-outline:hover{background:#F7B500;color:#000}

.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
.toolbar .cnt{color:#888;font-size:14px;margin-left:auto}
.search{padding:10px 16px;background:#111;border:1px solid #333;border-radius:8px;color:#fff;font-size:14px;width:250px}
.search:focus{outline:none;border-color:#F7B500}

.tbl{width:100%;border-collapse:collapse}
.tbl th{text-align:left;padding:10px 12px;border-bottom:1px solid #2a2a2a;color:#888;font-size:12px;text-transform:uppercase}
.tbl td{padding:10px 12px;border-bottom:1px solid #1f1f1f;font-size:14px;vertical-align:middle}
.tbl tr:hover td{background:#1a1a1a}
.tbl .img-prev{width:60px;height:45px;object-fit:cover;border-radius:6px;background:#222}
.tbl input,.tbl select{padding:6px 10px;background:#111;border:1px solid #2a2a2a;border-radius:6px;color:#fff;font-size:13px;width:100%}
.tbl input:focus,.tbl select:focus{outline:none;border-color:#F7B500}

.media-upload{background:#1a1a1a;border:2px dashed #333;border-radius:12px;padding:30px;text-align:center;margin-bottom:25px}
.media-upload:hover{border-color:#F7B500}
.media-upload input[type=file]{display:none}
.media-upload label{cursor:pointer;color:#F7B500;font-weight:600;font-size:16px}
.upload-btn{margin-top:15px}
.mg{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.mc{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:10px;overflow:hidden;position:relative;transition:.3s}
.mc:hover{border-color:#F7B500;transform:translateY(-2px)}
.mc img{width:100%;height:140px;object-fit:cover;display:block}
.mc .mi{padding:10px}
.mc .mn{font-size:11px;color:#ccc;word-break:break-all;margin-bottom:4px}
.mc .ms{font-size:11px;color:#666}
.mc .md{position:absolute;top:6px;right:6px;background:rgba(239,68,68,.9);color:#fff;border:none;width:26px;height:26px;border-radius:50%;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;opacity:0;transition:.2s}
.mc:hover .md{opacity:1}

.spec-row{display:flex;gap:8px;margin-bottom:6px;align-items:center}
.spec-row input{flex:1;padding:6px 8px;background:#111;border:1px solid #2a2a2a;border-radius:6px;color:#fff;font-size:13px}
.spec-row button{background:none;border:none;color:#ef4444;cursor:pointer;font-size:16px;padding:0 4px}

@media(max-width:768px){
    .sb{position:fixed;left:-240px;transition:.3s}.sb.open{left:0}
    .main{margin-left:0}
    .mob{display:block!important}
    .row,.row3{grid-template-columns:1fr}
}
.mob{display:none;position:fixed;top:12px;left:12px;z-index:100;background:#F7B500;color:#000;border:none;width:40px;height:40px;border-radius:10px;font-size:20px;cursor:pointer}
</style>
</head>
<body>
<?php if(empty($_SESSION['admin'])): ?>
<div class="login-wrap">
<div class="login-box">
    <h1>🔐 Админ-панель</h1>
    <p>РЕМСТРОЙЛОГИСТИК — управление сайтом</p>
    <?php if($err): ?><div class="alert alert-err"><?=htmlspecialchars($err)?></div><?php endif ?>
    <form method="POST">
        <label>Логин</label><input type="text" name="login" required placeholder="admin">
        <label>Пароль</label><input type="password" name="pass" required placeholder="••••••••">
        <button type="submit" name="do_login" class="login-btn">Войти</button>
    </form>
</div>
</div>
<?php else: ?>
<button class="mob" onclick="document.querySelector('.sb').classList.toggle('open')">☰</button>
<div class="layout">
<aside class="sb">
    <div class="brand"><h2>⚙️ Админ</h2><small>РЕМСТРОЙЛОГИСТИК</small></div>
    <nav>
        <a href="?page=dashboard" class="<?=$page=='dashboard'?'act':''?>">📊 Дашборд</a>
        <a href="?page=machines" class="<?=$page=='machines'?'act':''?>">🚜 Техника</a>
        <a href="?page=services" class="<?=$page=='services'?'act':''?>">🔧 Услуги</a>
        <a href="?page=contacts" class="<?=$page=='contacts'?'act':''?>">📞 Контакты</a>
        <a href="?page=content" class="<?=$page=='content'?'act':''?>">🏠 Главная</a>
        <a href="?page=media" class="<?=$page=='media'?'act':''?>">🖼️ Медиа</a>
    </nav>
    <div class="bot">
        <a href="index.html" target="_blank">← На сайт</a><br><br>
        <a href="?logout=1">🚪 Выйти</a>
    </div>
</aside>
<main class="main">
<?php if($ok): ?><div class="alert alert-ok"><?=htmlspecialchars($ok)?></div><?php endif ?>
<?php if($err): ?><div class="alert alert-err"><?=htmlspecialchars($err)?></div><?php endif ?>

<?php
// ==================== ДАШБОРД ====================
if($page=='dashboard'):
    $machines=loadJson($DATA_DIR.'/machines.json');
    $services=loadJson($DATA_DIR.'/services.json');
    $imgs=is_dir($IMG_DIR)?array_filter(scandir($IMG_DIR),fn($f)=>is_file("$IMG_DIR/$f")):[];
    $imgSize=0; foreach($imgs as $i) $imgSize+=filesize("$IMG_DIR/$i");
?>
<h1 class="pt">📊 <span>Дашборд</span></h1>
<div class="stats">
    <div class="sc"><div class="n"><?=count($machines)?></div><div class="l">Единиц техники</div></div>
    <div class="sc"><div class="n"><?=count($services)?></div><div class="l">Услуг</div></div>
    <div class="sc"><div class="n"><?=count($imgs)?></div><div class="l">Изображений</div></div>
    <div class="sc"><div class="n"><?=round($imgSize/1024/1024,1)?> МБ</div><div class="l">Размер медиа</div></div>
</div>
<div class="card">
    <h3>Быстрые действия</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
        <a href="?page=machines" class="btn btn-sm btn-outline">🚜 Редактировать технику</a>
        <a href="?page=services" class="btn btn-sm btn-outline">🔧 Редактировать услуги</a>
        <a href="?page=contacts" class="btn btn-sm btn-outline">📞 Контакты</a>
        <a href="?page=media" class="btn btn-sm btn-outline">🖼️ Загрузить фото</a>
    </div>
</div>

<?php
// ==================== ТЕХНИКА ====================
elseif($page=='machines'):
    $machines=loadJson($DATA_DIR.'/machines.json');
?>
<h1 class="pt">🚜 <span>Техника</span> <small style="font-size:14px;color:#666">(<?=count($machines)?> ед.)</small></h1>

<form method="POST" id="machinesForm">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<input type="hidden" name="machines_data" id="machinesData">

<div class="toolbar">
    <input type="text" class="search" placeholder="🔍 Поиск по названию..." id="machineSearch" oninput="filterMachines()">
    <select id="catFilter" onchange="filterMachines()" style="padding:10px;background:#111;border:1px solid #333;border-radius:8px;color:#fff;font-size:14px">
        <option value="">Все категории</option>
        <?php foreach($categories as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach ?>
    </select>
    <button type="button" class="btn btn-sm btn-green" onclick="addMachine()">＋ Добавить</button>
    <button type="submit" name="save_machines" class="btn btn-sm" onclick="prepareSave()">💾 Сохранить всё</button>
</div>

<div id="machinesList"></div>
</form>

<script>
let machines = <?=json_encode($machines, JSON_UNESCAPED_UNICODE)?>;
const cats = <?=json_encode($categories, JSON_UNESCAPED_UNICODE)?>;

function renderMachines() {
    const s = document.getElementById('machineSearch').value.toLowerCase();
    const cf = document.getElementById('catFilter').value;
    const list = document.getElementById('machinesList');
    let html = '';
    machines.forEach((m, i) => {
        if (s && !m.title.toLowerCase().includes(s)) return;
        if (cf && m.category !== cf) return;
        let specsHtml = '';
        (m.specs||[]).forEach((sp, si) => {
            specsHtml += `<div class="spec-row">
                <input placeholder="Параметр" value="${sp.name||''}" onchange="machines[${i}].specs[${si}].name=this.value">
                <input placeholder="Значение" value="${sp.value||''}" onchange="machines[${i}].specs[${si}].value=this.value">
                <button type="button" onclick="machines[${i}].specs.splice(${si},1);renderMachines()">✕</button>
            </div>`;
        });
        html += `<div class="card" data-idx="${i}">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
                <h3 style="margin:0">${m.title}</h3>
                <button type="button" class="btn btn-sm btn-red" onclick="if(confirm('Удалить ${m.title}?')){machines.splice(${i},1);renderMachines()}">🗑</button>
            </div>
            <div class="row">
                <div class="fg"><label>Название</label><input value="${m.title}" onchange="machines[${i}].title=this.value"></div>
                <div class="fg"><label>Цена (за смену)</label><input value="${m.price}" onchange="machines[${i}].price=this.value"></div>
            </div>
            <div class="row">
                <div class="fg"><label>Категория</label>
                    <select onchange="machines[${i}].category=this.value">
                        ${Object.entries(cats).map(([k,v])=>`<option value="${k}" ${m.category===k?'selected':''}>${v}</option>`).join('')}
                    </select>
                </div>
                <div class="fg"><label>Фото (путь)</label><input value="${m.image}" onchange="machines[${i}].image=this.value"></div>
            </div>
            <div class="fg"><label>Описание</label><textarea onchange="machines[${i}].desc=this.value">${m.desc||''}</textarea></div>
            <div class="fg"><label>Характеристики</label>
                ${specsHtml}
                <button type="button" class="btn btn-sm btn-outline" style="margin-top:6px" onclick="if(!machines[${i}].specs)machines[${i}].specs=[];machines[${i}].specs.push({name:'',value:''});renderMachines()">＋ Параметр</button>
            </div>
        </div>`;
    });
    list.innerHTML = html || '<div class="card" style="text-align:center;color:#666">Ничего не найдено</div>';
}
function filterMachines() { renderMachines(); }
function addMachine() {
    const id = machines.length ? Math.max(...machines.map(m=>typeof m.id==='number'?m.id:0))+1 : 1;
    machines.unshift({id:id, category:'excavator', title:'Новая техника', price:'0 ₽', image:'images/', desc:'', specs:[]});
    renderMachines();
    window.scrollTo(0, document.getElementById('machinesList').offsetTop);
}
function prepareSave() { document.getElementById('machinesData').value = JSON.stringify(machines); }
renderMachines();
</script>

<?php
// ==================== УСЛУГИ ====================
elseif($page=='services'):
    $services=loadJson($DATA_DIR.'/services.json');
?>
<h1 class="pt">🔧 <span>Услуги</span> <small style="font-size:14px;color:#666">(<?=count($services)?>)</small></h1>

<form method="POST" id="servicesForm">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<input type="hidden" name="services_data" id="servicesData">

<div class="toolbar">
    <button type="button" class="btn btn-sm btn-green" onclick="addService()">＋ Добавить услугу</button>
    <button type="submit" name="save_services" class="btn btn-sm" onclick="prepareServicesSave()">💾 Сохранить всё</button>
</div>

<div id="servicesList"></div>
</form>

<script>
let services = <?=json_encode($services, JSON_UNESCAPED_UNICODE)?>;
const scats = <?=json_encode($categories, JSON_UNESCAPED_UNICODE)?>;

function renderServices() {
    const list = document.getElementById('servicesList');
    let html = '';
    services.forEach((s, i) => {
        const catOpts = Object.entries(scats).map(([k,v])=>{
            const checked = (s.categories||[]).includes(k) ? 'checked' : '';
            return `<label style="display:inline-flex;align-items:center;gap:4px;margin-right:10px;font-size:12px;color:#ccc;cursor:pointer"><input type="checkbox" ${checked} onchange="toggleCat(${i},'${k}',this.checked)">${v}</label>`;
        }).join('');
        html += `<div class="card">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
                <h3 style="margin:0">${s.title}</h3>
                <button type="button" class="btn btn-sm btn-red" onclick="if(confirm('Удалить?')){services.splice(${i},1);renderServices()}">🗑</button>
            </div>
            <div class="fg"><label>Название</label><input value="${s.title}" onchange="services[${i}].title=this.value"></div>
            <div class="fg"><label>Описание</label><textarea onchange="services[${i}].desc=this.value">${s.desc||''}</textarea></div>
            <div class="fg"><label>Используемая техника</label><textarea onchange="services[${i}].techDesc=this.value">${s.techDesc||''}</textarea></div>
            <div class="fg"><label>Категории техники</label><div style="margin-top:6px">${catOpts}</div></div>
        </div>`;
    });
    list.innerHTML = html;
}
function toggleCat(i, cat, on) {
    if (!services[i].categories) services[i].categories = [];
    if (on && !services[i].categories.includes(cat)) services[i].categories.push(cat);
    if (!on) services[i].categories = services[i].categories.filter(c=>c!==cat);
}
function addService() {
    const id = 's' + (services.length+1);
    services.unshift({id:id, title:'Новая услуга', categories:[], desc:'', techDesc:''});
    renderServices();
}
function prepareServicesSave() { document.getElementById('servicesData').value = JSON.stringify(services); }
renderServices();
</script>

<?php
// ==================== КОНТАКТЫ ====================
elseif($page=='contacts'):
    $c=loadJson($DATA_DIR.'/contacts.json');
?>
<h1 class="pt">📞 <span>Контакты</span></h1>
<form method="POST">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card">
    <h3>Контактная информация</h3>
    <div class="row">
        <div class="fg"><label>Телефон (отображаемый)</label><input type="text" name="phone" value="<?=htmlspecialchars($c['phone']??'')?>"></div>
        <div class="fg"><label>Телефон (для ссылки, без пробелов)</label><input type="text" name="phoneRaw" value="<?=htmlspecialchars($c['phoneRaw']??'')?>"></div>
    </div>
    <div class="fg"><label>Email</label><input type="email" name="email" value="<?=htmlspecialchars($c['email']??'')?>"></div>
    <div class="fg"><label>Адрес</label><input type="text" name="address" value="<?=htmlspecialchars($c['address']??'')?>"></div>
    <div class="fg"><label>Время работы</label><input type="text" name="workHours" value="<?=htmlspecialchars($c['workHours']??'')?>"></div>
    <div class="row">
        <div class="fg"><label>WhatsApp ссылка</label><input type="text" name="whatsapp" value="<?=htmlspecialchars($c['whatsapp']??'')?>"></div>
        <div class="fg"><label>Telegram ссылка</label><input type="text" name="telegram" value="<?=htmlspecialchars($c['telegram']??'')?>"></div>
    </div>
    <div style="margin-top:15px"><button type="submit" name="save_contacts" class="btn">💾 Сохранить контакты</button></div>
</div>
</form>

<?php
// ==================== КОНТЕНТ ====================
elseif($page=='content'):
    $c=loadJson($DATA_DIR.'/content.json');
?>
<h1 class="pt">🏠 <span>Главная страница</span></h1>
<form method="POST">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card">
    <h3>Баннер (Hero)</h3>
    <div class="fg"><label>Заголовок (можно &lt;br&gt;)</label><input type="text" name="heroTitle" value="<?=htmlspecialchars($c['heroTitle']??'')?>"></div>
    <div class="fg"><label>Подзаголовок</label><input type="text" name="heroSubtitle" value="<?=htmlspecialchars($c['heroSubtitle']??'')?>"></div>
</div>
<div class="card">
    <h3>Футер</h3>
    <div class="fg"><label>Текст в подвале</label><textarea name="footerText"><?=htmlspecialchars($c['footerText']??'')?></textarea></div>
</div>
<div style="margin-top:15px"><button type="submit" name="save_content" class="btn">💾 Сохранить</button></div>
</form>

<?php
// ==================== МЕДИА ====================
elseif($page=='media'):
    $imgs=is_dir($IMG_DIR)?array_filter(scandir($IMG_DIR),fn($f)=>is_file("$IMG_DIR/$f")&&in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),$MEDIA_EXT)):[];
?>
<h1 class="pt">🖼️ <span>Медиа-файлы</span> <small style="font-size:14px;color:#666">(<?=count($imgs)?>)</small></h1>
<form method="POST" enctype="multipart/form-data" class="media-upload">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <label for="mf">📂 Нажмите, чтобы выбрать файл (JPG, PNG, WEBP, макс 10МБ)</label><br><br>
    <input type="file" name="media" id="mf" accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.mp4,.webm" onchange="document.getElementById('fn').textContent=this.files[0]?.name||''">
    <div id="fn" style="color:#888;margin-top:10px;font-size:13px"></div>
    <button type="submit" name="upload_media" class="btn btn-sm upload-btn">⬆️ Загрузить</button>
</form>
<div class="mg">
<?php foreach($imgs as $f):
    $sz=filesize("$IMG_DIR/$f"); $ext=strtolower(pathinfo($f,PATHINFO_EXTENSION)); $isVid=in_array($ext,['mp4','webm']);
?>
<div class="mc">
    <?php if($isVid): ?>
        <video src="images/<?=htmlspecialchars($f)?>" style="width:100%;height:140px;object-fit:cover" muted></video>
    <?php else: ?>
        <img src="images/<?=htmlspecialchars($f)?>" alt="<?=htmlspecialchars($f)?>" loading="lazy">
    <?php endif ?>
    <div class="mi">
        <div class="mn"><?=htmlspecialchars($f)?></div>
        <div class="ms"><?=round($sz/1024,1)?> КБ</div>
    </div>
    <form method="POST" style="display:inline" onsubmit="return confirm('Удалить <?=htmlspecialchars($f)?>?')">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <input type="hidden" name="del" value="<?=htmlspecialchars($f)?>">
        <button type="submit" name="delete_media" class="md">✕</button>
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
