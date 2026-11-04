<?php
/**
 * create-admin.php - панель управления пользователями для ПЕРВОГО ЗАПУСКА.
 *
 * Работает без входа в систему: нужна, чтобы создать первого администратора.
 * ПОСЛЕ НАСТРОЙКИ ФАЙЛ НУЖНО УДАЛИТЬ (кнопка в красном баннере или вручную):
 * пока он лежит на сервере, любой посетитель может создать себе администратора.
 */
define('BOARD', true);
define('BOARD_ROOT', __DIR__);
chdir(__DIR__);

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

require_once 'includes/class.logger.php';
require_once 'includes/class.user.php';
require_once 'includes/class.database.php';
$cmsConfig = require 'config/config.inc.php';

try {
    if ($cmsConfig['storage'] === 'mysql') {
        $storage   = new MysqlTableStorage($cmsConfig);
        $tableName = $cmsConfig['mysql']['UsersTableName'];
    } else {
        $storage   = new JsonTableStorage($cmsConfig['json']);
        $tableName = $cmsConfig['json']['UsersTableName'];
    }
    $user = new User($storage, $tableName, $cmsConfig['sessionKey'], $cmsConfig['lifeTime'], $cmsConfig['userCookeName']);
} catch (Throwable $e) {
    logMessage('create-admin.php: ' . $e->getMessage(), 'ERROR');
    http_response_code(503);
    exit('Хранилище недоступно. Проверьте config/config.inc.php и журнал log/app.log.');
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$self = $_SERVER['SCRIPT_NAME'] ?? 'create-admin.php';
if (empty($_SESSION['ca_token'])) {
    $_SESSION['ca_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['ca_token'];

function flash(string $type, string $text): void { $_SESSION['ca_flash'] = [$type, $text]; }
function back(string $self, string $query = ''): void { header('Location: ' . $self . $query); exit; }

/** Проверка полей формы; возвращает текст ошибки или null */
function validateFields(string $email, string $name, string $role, string $password, bool $passwordRequired): ?string
{
    if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Некорректный email.';
    if ($name === '' || textLength($name) > 100) return 'Имя обязательно (до 100 символов).';
    if (!in_array($role, User::ROLES, true)) return 'Недопустимая роль.';
    if (($passwordRequired || $password !== '') && (strlen($password) < 8 || strlen($password) > 72)) {
        return 'Пароль должен содержать от 8 до 72 символов.';
    }
    return null;
}

// ---------------- Обработка действий ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, (string)($_POST['_token'] ?? ''))) {
        http_response_code(403);
        exit('Недействительный токен формы. Обновите страницу.');
    }
    $action = (string)($_POST['action'] ?? '');
    $email  = strtolower(trim((string)($_POST['email'] ?? '')));
    $name   = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)($_POST['name'] ?? '')));
    $role   = strtolower(trim((string)($_POST['role'] ?? 'user')));
    $pass   = (string)($_POST['password'] ?? '');

    try {
        if ($action === 'create') {
            if ($err = validateFields($email, $name, $role, $pass, true)) {
                flash('error', $err);
                back($self);
            }
            $status = $user->register(['email' => $email, 'password' => $pass, 'name' => $name, 'role' => $role], false);
            if ($status === RegistrationStatus::SUCCESS_WITHOUT_LOGIN) {
                logMessage("create-admin.php: создан пользователь $email ($role)", 'WARNING');
                flash('ok', "Создан: $email (" . ($role === 'admin' ? 'администратор' : 'пользователь') . ').');
            } elseif ($status === RegistrationStatus::EMAIL_EXISTS) {
                flash('error', 'Пользователь с таким email уже существует.');
            } else {
                flash('error', 'Не удалось создать пользователя (см. log/app.log).');
            }
            back($self);
        }

        if ($action === 'update') {
            $orig = $user->getUserByEmail(trim((string)($_POST['orig_email'] ?? '')));
            if (!$orig) {
                flash('error', 'Пользователь не найден.');
                back($self);
            }
            if ($err = validateFields($email, $name, $role, $pass, false)) {
                flash('error', $err);
                back($self, '?edit=' . rawurlencode($orig['email']));
            }
            if (strtolower($orig['email']) !== $email && $user->getUserByEmail($email)) {
                flash('error', 'Другой пользователь уже использует этот email.');
                back($self, '?edit=' . rawurlencode($orig['email']));
            }
            $upd = ['email' => $email, 'name' => $name, 'role' => $role];
            if ($pass !== '') {
                $upd['password'] = $pass; // User::updateRecord хеширует сам
            }
            $user->updateRecord(['email' => $orig['email']], $upd);
            logMessage("create-admin.php: изменён пользователь {$orig['email']} -> $email ($role)", 'WARNING');
            flash('ok', "Сохранено: $email.");
            back($self);
        }

        if ($action === 'delete') {
            $rec = $user->getUserByEmail(trim((string)($_POST['email'] ?? '')));
            if ($rec && $user->deleteUser($rec['email'])) {
                logMessage("create-admin.php: удалён пользователь {$rec['email']}", 'WARNING');
                flash('ok', "Удалён: {$rec['email']}.");
            } else {
                flash('error', 'Не удалось удалить пользователя.');
            }
            back($self);
        }

        if ($action === 'delete_file') {
            if (@unlink(__FILE__)) {
                logMessage('create-admin.php удалён', 'WARNING');
                $base = rtrim(dirname($self), '/\\');
                header('Location: ' . $base . '/');
                exit;
            }
            flash('error', 'Не удалось удалить файл автоматически (нет прав). Удалите create-admin.php вручную через FTP/файловый менеджер.');
            back($self);
        }
    } catch (Throwable $e) {
        logMessage('create-admin.php: ' . $e->getMessage(), 'ERROR');
        flash('error', 'Внутренняя ошибка (подробности в log/app.log).');
        back($self);
    }
    back($self);
}

// ---------------- Данные для страницы ----------------
$flash = $_SESSION['ca_flash'] ?? null;
unset($_SESSION['ca_flash']);

$users = array_values($user->getAllUsers());
usort($users, fn($a, $b) => [strtolower((string)($a['role'] ?? '')) === 'admin' ? 0 : 1, strtolower((string)$a['email'])]
                          <=> [strtolower((string)($b['role'] ?? '')) === 'admin' ? 0 : 1, strtolower((string)$b['email'])]);
$admins = count(array_filter($users, fn($u) => strtolower((string)($u['role'] ?? '')) === 'admin'));

$editing = null;
if (isset($_GET['edit']) && is_string($_GET['edit'])) {
    $editing = $user->getUserByEmail($_GET['edit']);
}
$current = $user->getCurrentUser();
$roleLabel = fn($r) => strtolower((string)$r) === 'admin' ? 'Администратор' : 'Пользователь';
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Управление пользователями - первый запуск</title>
<style>
    :root { --bg:#f4f6fb; --card:#fff; --text:#1f2937; --muted:#6b7280; --line:#e5e7eb; --primary:#2563eb; --danger:#dc2626; --ok:#15803d; }
    @media (prefers-color-scheme: dark) { :root { --bg:#0f172a; --card:#1e293b; --text:#e5e7eb; --muted:#94a3b8; --line:#334155; } }
    * { box-sizing: border-box; }
    body { margin:0; background:var(--bg); color:var(--text); font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
    .warn { position:sticky; top:0; z-index:10; background:#b91c1c; color:#fff; padding:12px 20px; display:flex; gap:16px; align-items:center; justify-content:space-between; flex-wrap:wrap; box-shadow:0 2px 8px rgba(0,0,0,.3); }
    .warn strong { font-size:16px; }
    .warn form { margin:0; }
    .warn button { background:#fff; color:#b91c1c; border:0; padding:8px 16px; border-radius:6px; font-weight:700; cursor:pointer; }
    .wrap { max-width:1100px; margin:0 auto; padding:24px 16px 60px; }
    h1 { font-size:24px; margin:0 0 4px; } .sub { color:var(--muted); margin:0 0 20px; }
    .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:20px; }
    .stat, .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:16px; }
    .stat b { display:block; font-size:26px; } .stat span { color:var(--muted); font-size:13px; }
    .card { margin-bottom:20px; } .card h2 { margin:0 0 14px; font-size:18px; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; align-items:end; }
    label { display:block; font-size:13px; color:var(--muted); margin-bottom:4px; }
    input, select { width:100%; padding:9px 10px; border:1px solid var(--line); border-radius:6px; background:var(--bg); color:var(--text); font:inherit; }
    .btn { display:inline-block; padding:9px 16px; border:0; border-radius:6px; background:var(--primary); color:#fff; font:inherit; cursor:pointer; text-decoration:none; }
    .btn.sec { background:transparent; color:var(--text); border:1px solid var(--line); } .btn.red { background:var(--danger); } .btn.sm { padding:5px 10px; font-size:13px; }
    .msg { padding:12px 16px; border-radius:8px; margin-bottom:16px; } .msg.ok { background:#dcfce7; color:#14532d; } .msg.error { background:#fee2e2; color:#7f1d1d; } .msg.note { background:#fef3c7; color:#78350f; }
    .tablewrap { overflow-x:auto; } table { width:100%; border-collapse:collapse; } th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); white-space:nowrap; }
    th { color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
    .badge { padding:2px 9px; border-radius:99px; font-size:12px; font-weight:600; } .badge.admin { background:#fee2e2; color:#991b1b; } .badge.user { background:#dbeafe; color:#1e40af; }
    .actions { display:flex; gap:6px; } .actions form { margin:0; } code { font-size:12px; color:var(--muted); }
</style>
</head>
<body>
<div class="warn">
    <strong>Внимание! После завершения настройки удалите файл create-admin.php в целях безопасности!</strong>
    <form method="post" onsubmit="return confirm('Удалить create-admin.php с сервера? Это действие необратимо.');">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="delete_file">
        <button type="submit">Удалить файл</button>
    </form>
</div>

<div class="wrap">
    <h1>Управление пользователями</h1>
    <p class="sub">Панель первого запуска: создание, изменение и удаление пользователей и администраторов без входа в систему.</p>

    <?php if ($flash): ?><div class="msg <?= h($flash[0]) ?>"><?= h($flash[1]) ?></div><?php endif; ?>
    <?php if ($admins === 0): ?><div class="msg note">В системе пока нет ни одного администратора. Создайте его в форме ниже.</div><?php endif; ?>

    <div class="stats">
        <div class="stat"><b><?= count($users) ?></b><span>Всего пользователей</span></div>
        <div class="stat"><b><?= $admins ?></b><span>Администраторов</span></div>
        <div class="stat"><b><?= count($users) - $admins ?></b><span>Обычных пользователей</span></div>
        <div class="stat"><b><?= h($cmsConfig['storage']) ?></b><span>Хранилище данных</span></div>
    </div>

    <div class="card">
        <h2><?= $editing ? 'Редактирование: ' . h($editing['email']) : 'Новый пользователь' ?></h2>
        <form method="post" autocomplete="off">
            <input type="hidden" name="_token" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?><input type="hidden" name="orig_email" value="<?= h($editing['email']) ?>"><?php endif; ?>
            <div class="grid">
                <div><label for="email">Email</label><input id="email" type="email" name="email" required maxlength="255" value="<?= h($editing['email'] ?? '') ?>"></div>
                <div><label for="name">Имя</label><input id="name" name="name" required maxlength="100" value="<?= h($editing['name'] ?? '') ?>"></div>
                <div>
                    <label for="password"><?= $editing ? 'Новый пароль (пусто - не менять)' : 'Пароль (8-72 символа)' ?></label>
                    <input id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
                </div>
                <div>
                    <label for="role">Права доступа</label>
                    <select id="role" name="role">
                        <?php foreach (User::ROLES as $r): ?>
                            <option value="<?= h($r) ?>" <?= strtolower((string)($editing['role'] ?? 'user')) === $r ? 'selected' : '' ?>><?= h($roleLabel($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button class="btn" type="submit"><?= $editing ? 'Сохранить' : 'Создать' ?></button>
                    <?php if ($editing): ?><a class="btn sec" href="<?= h($self) ?>">Отмена</a><?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Все пользователи (<?= count($users) ?>)</h2>
        <div class="tablewrap">
        <table>
            <thead><tr><th>#</th><th>Email</th><th>Имя</th><th>Роль</th><th>Права</th><th>Пароль</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $i => $u):
                $isAdm = strtolower((string)($u['role'] ?? '')) === 'admin'; ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= h($u['email'] ?? '') ?><?= ($current && strtolower((string)$current['email']) === strtolower((string)$u['email'])) ? ' <code>(вы)</code>' : '' ?></td>
                    <td><?= h($u['name'] ?? '') ?></td>
                    <td><span class="badge <?= $isAdm ? 'admin' : 'user' ?>"><?= h($roleLabel($u['role'] ?? '')) ?></span></td>
                    <td><?= $isAdm ? 'Полный доступ: админ-панель, пользователи, настройки, журнал' : 'Обычный доступ: сайт и профиль' ?></td>
                    <td><code><?= strpos((string)($u['password'] ?? ''), '$2y$') === 0 ? 'bcrypt (хеш скрыт)' : 'нестандартный хеш' ?></code></td>
                    <td class="actions">
                        <a class="btn sec sm" href="<?= h($self) ?>?edit=<?= h(rawurlencode((string)$u['email'])) ?>">Изменить</a>
                        <form method="post" onsubmit="return confirm('Удалить этого пользователя?');">
                            <input type="hidden" name="_token" value="<?= h($token) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="email" value="<?= h($u['email']) ?>">
                            <button class="btn red sm" type="submit">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="7" style="color:var(--muted)">Пользователей пока нет.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
