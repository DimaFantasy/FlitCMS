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
require_once 'includes/class.translator.php';
$cmsConfig = require 'config/config.inc.php';

// Язык: кука из конфигурации, но только из списка validLanguages (как на сайте)
$validLangs = array_values(array_filter(array_map('trim', explode(',', (string)$cmsConfig['validLanguages']))));
$activeLang = (string)($_COOKIE[$cmsConfig['langCookieName']] ?? '');
if (!in_array($activeLang, $validLangs, true)) {
    $activeLang = $cmsConfig['defaultLanguage'];
}
Translator::configure(
    $cmsConfig['langDir'],
    $cmsConfig['missingTranslationsFileName'],
    $cmsConfig['validLanguages'],
    $activeLang
);
function t($key) { return Translator::translate($key); }
/** Перевод, экранированный для вывода в HTML */
function e($key): string { return htmlspecialchars(t($key), ENT_QUOTES, 'UTF-8'); }

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
    exit(t('Storage unavailable. Check config/config.inc.php and the log/app.log journal.'));
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
    if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return t('Invalid email address');
    if ($name === '' || textLength($name) > 100) return t('Name is required (up to 100 characters).');
    if (!in_array($role, User::ROLES, true)) return t('Invalid role.');
    if (($passwordRequired || $password !== '') && (strlen($password) < 8 || strlen($password) > 72)) {
        return t('Password must be between 8 and 72 characters');
    }
    return null;
}

// ---------------- Обработка действий ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, (string)($_POST['_token'] ?? ''))) {
        http_response_code(403);
        exit(t('Invalid form token. Refresh the page.'));
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
                flash('ok', sprintf(t('Created: %s (%s).'), $email, $role === 'admin' ? t('Administrator') : t('Regular user')));
            } elseif ($status === RegistrationStatus::EMAIL_EXISTS) {
                flash('error', t('A user with this email already exists.'));
            } else {
                flash('error', t('Failed to create user (see log/app.log).'));
            }
            back($self);
        }

        if ($action === 'update') {
            $orig = $user->getUserByEmail(trim((string)($_POST['orig_email'] ?? '')));
            if (!$orig) {
                flash('error', t('User not found.'));
                back($self);
            }
            if ($err = validateFields($email, $name, $role, $pass, false)) {
                flash('error', $err);
                back($self, '?edit=' . rawurlencode($orig['email']));
            }
            if (strtolower($orig['email']) !== $email && $user->getUserByEmail($email)) {
                flash('error', t('Another user already uses this email.'));
                back($self, '?edit=' . rawurlencode($orig['email']));
            }
            $upd = ['email' => $email, 'name' => $name, 'role' => $role];
            if ($pass !== '') {
                $upd['password'] = $pass; // User::updateRecord хеширует сам
            }
            $user->updateRecord(['email' => $orig['email']], $upd);
            logMessage("create-admin.php: изменён пользователь {$orig['email']} -> $email ($role)", 'WARNING');
            flash('ok', sprintf(t('Saved: %s.'), $email));
            back($self);
        }

        if ($action === 'delete') {
            $rec = $user->getUserByEmail(trim((string)($_POST['email'] ?? '')));
            if ($rec && $user->deleteUser($rec['email'])) {
                logMessage("create-admin.php: удалён пользователь {$rec['email']}", 'WARNING');
                flash('ok', sprintf(t('Deleted: %s.'), $rec['email']));
            } else {
                flash('error', t('Failed to delete user.'));
            }
            back($self);
        }

        if ($action === 'impersonate') {
            $rec = $user->getUserByEmail(trim((string)($_POST['email'] ?? '')));
            if (!$rec) {
                flash('error', t('User not found.'));
                back($self);
            }
            // Вход под выбранным пользователем без пароля (setSession() в User приватный - пишем сессию так же)
            session_regenerate_id(true);
            $_SESSION[$cmsConfig['sessionKey']] = [
                'email' => $rec['email'],
                'name'  => $rec['name'],
                'role'  => $rec['role'],
                'pv'    => hash('sha256', (string)($rec['password'] ?? '')), // как в User::setSession()
            ];
            logMessage("create-admin.php: вход под пользователем {$rec['email']}", 'WARNING');
            flash('ok', sprintf(t('Now you are working as: %s (%s).'), $rec['name'], $rec['email']));
            back($self);
        }

        if ($action === 'logout') {
            $user->logout();
            flash('ok', t('You have logged out.'));
            back($self);
        }

        if ($action === 'delete_file') {
            if (@unlink(__FILE__)) {
                logMessage('create-admin.php удалён', 'WARNING');
                $base = rtrim(dirname($self), '/\\');
                header('Location: ' . $base . '/');
                exit;
            }
            flash('error', t('Could not delete the file automatically (no permission). Delete create-admin.php manually via FTP or a file manager.'));
            back($self);
        }
    } catch (Throwable $e) {
        logMessage('create-admin.php: ' . $e->getMessage(), 'ERROR');
        flash('error', t('Internal error (details in log/app.log).'));
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
$roleLabel = fn($r) => strtolower((string)$r) === 'admin' ? t('Administrator') : t('Regular user');
$base = rtrim(dirname($self), '/\\') . '/';
/** Первая буква имени для аватара (работает и без mbstring) */
function initial($text): string
{
    return preg_match('/./us', trim((string)$text), $m) ? strtoupper($m[0]) : '?';
}
$flashClass = ['ok' => 'success', 'error' => 'danger'][$flash[0] ?? ''] ?? 'info';
?><!DOCTYPE html>
<html lang="<?= h($activeLang) ?>" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e('First run - user management') ?></title>
    <link rel="icon" type="image/png" href="<?= h($base) ?>favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        // Тема - та же, что в админке (localStorage 'theme'); ставим до отрисовки, чтобы не мигало
        try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'light'); } catch (e) {}
    </script>
    <style>
        #warnBanner { z-index: 1050; }
        #sidebar { min-width: 230px; }
        .avatar { width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; flex: none; }
        .stat-number { font-size: 1.9rem; font-weight: 700; line-height: 1.1; }
        .chip { font-weight: 400; }
        @media (min-width: 992px) { #sidebar { min-height: calc(100vh - 56px); } }
    </style>
</head>
<body>

<!-- Постоянное предупреждение -->
<div id="warnBanner" class="alert alert-danger rounded-0 border-0 border-bottom border-danger mb-0 sticky-top d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 px-4" role="alert">
    <div class="d-flex align-items-center gap-2">
        <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
        <strong><?= e('Warning! After setup is complete, delete the create-admin.php file for security!') ?></strong>
    </div>
    <form method="post" class="m-0" data-confirm="<?= e('Delete create-admin.php from the server? This action cannot be undone.') ?>" data-confirm-title="<?= e('Deleting create-admin.php') ?>" data-confirm-btn="<?= e('Delete file') ?>">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="delete_file">
        <button type="submit" class="btn btn-danger btn-sm fw-bold"><?= e('Delete file') ?></button>
    </form>
</div>

<!-- Шапка -->
<nav class="navbar navbar-expand-lg shadow bg-body-tertiary" id="navbar">
    <div class="container-fluid px-4">
        <span class="navbar-brand">Flit CMS &middot; <?= e('First run') ?></span>
        <div class="d-flex align-items-center gap-2">
            <?php if ($current): ?>
                <span class="badge text-bg-<?= strtolower((string)$current['role']) === 'admin' ? 'danger' : 'primary' ?>"><?= e('You') ?>: <?= h($current['name']) ?> (<?= h($current['email']) ?>) &middot; <?= h($roleLabel($current['role'])) ?></span>
                <form method="post" class="m-0">
                    <input type="hidden" name="_token" value="<?= h($token) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><?= e('Log out') ?></button>
                </form>
            <?php else: ?>
                <span class="badge text-bg-warning"><?= e('You are not logged in') ?></span>
            <?php endif; ?>
            <span class="badge text-bg-secondary"><?= e('Storage') ?>: <?= h($cmsConfig['storage']) ?></span>
            <?php if (count($validLangs) > 1): ?>
            <div class="btn-group" role="group">
                <?php foreach ($validLangs as $lc): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary js-lang<?= $lc === $activeLang ? ' active' : '' ?>" data-lang="<?= h($lc) ?>" title="<?= e('Switch to') ?> <?= h(strtoupper($lc)) ?>"<?= $lc === $activeLang ? ' disabled' : '' ?>><?= h(strtoupper($lc)) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="d-flex flex-column flex-lg-row">

    <!-- Боковая панель -->
    <div class="p-3 bg-body-tertiary border-end" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-reset" href="#" data-bs-toggle="modal" data-bs-target="#createModal"><?= e('Add user') ?></a></li>
            <li class="nav-item"><a class="nav-link text-reset" href="<?= h($base) ?>"><?= e('Site home page') ?></a></li>
            <?php if ($current && strtolower((string)$current['role']) === 'admin'): ?>
            <li class="nav-item"><a class="nav-link text-reset" href="<?= h($base) ?>?page=index.htm&amp;isAdmin=true"><?= e('Administrator panel') ?></a></li>
            <?php endif; ?>
        </ul>
        <hr>
        <div class="small text-body-secondary">
            <div class="fw-semibold mb-1"><?= e('Order of actions') ?></div>
            <ol class="ps-3 mb-0">
                <li><?= e('Create an administrator') ?></li>
                <li><?= e('Log in to the site') ?></li>
                <li><?= e('Delete this file') ?></li>
            </ol>
        </div>
    </div>

    <!-- Основной контент -->
    <div class="flex-grow-1">
        <div class="container-fluid p-4">
            <h2 class="mb-1"><?= e('Users') ?></h2>
            <p class="text-body-secondary"><?= e('Create, edit and delete users and administrators without logging in.') ?></p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= h($flashClass) ?> alert-dismissible fade show" role="alert">
                    <?= h($flash[1]) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e('Close') ?>"></button>
                </div>
            <?php endif; ?>

            <?php if ($admins === 0): ?>
                <div class="alert alert-warning"><strong><?= e('No administrators yet.') ?></strong> <?= e('Create the first one with the “Add user” button and choose the “Administrator” role.') ?></div>
            <?php else: ?>
                <div class="alert alert-info"><?= t('Administrator created. Log in to the site and <strong>delete create-admin.php</strong> (the button in the red banner at the top).') ?></div>
            <?php endif; ?>

            <!-- Статистика -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3"><div class="card border-0 bg-primary-subtle h-100"><div class="card-body"><div class="stat-number"><?= count($users) ?></div><div class="text-body-secondary"><?= e('Total users') ?></div></div></div></div>
                <div class="col-6 col-xl-3"><div class="card border-0 bg-danger-subtle h-100"><div class="card-body"><div class="stat-number"><?= $admins ?></div><div class="text-body-secondary"><?= e('Administrators') ?></div></div></div></div>
                <div class="col-6 col-xl-3"><div class="card border-0 bg-success-subtle h-100"><div class="card-body"><div class="stat-number"><?= count($users) - $admins ?></div><div class="text-body-secondary"><?= e('Regular users') ?></div></div></div></div>
                <div class="col-6 col-xl-3"><div class="card border-0 bg-secondary-subtle h-100"><div class="card-body"><div class="stat-number text-uppercase"><?= h($cmsConfig['storage']) ?></div><div class="text-body-secondary"><?= e('Data storage') ?></div></div></div></div>
            </div>

            <!-- Список -->
            <div class="card rounded shadow-none" id="users">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold"><?= e('All users') ?> (<?= count($users) ?>)</span>
                    <div class="d-flex gap-2">
                        <input type="search" id="userSearch" class="form-control form-control-sm" placeholder="<?= e('Search by email or name') ?>" style="min-width:220px">
                        <button type="button" class="btn btn-success btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#createModal">+ <?= e('Add user') ?></button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th class="ps-3"><?= e('Account') ?></th><th><?= e('Role') ?></th><th><?= e('Access rights') ?></th><th><?= e('Password') ?></th><th class="text-end pe-3"><?= e('Actions') ?></th></tr>
                        </thead>
                        <tbody id="usersBody">
                        <?php foreach ($users as $u):
                            $isAdm = strtolower((string)($u['role'] ?? '')) === 'admin';
                            $isMe  = $current && strtolower((string)$current['email']) === strtolower((string)$u['email']); ?>
                            <tr data-search="<?= h(strtolower(($u['email'] ?? '') . ' ' . ($u['name'] ?? ''))) ?>">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar <?= $isAdm ? 'bg-danger-subtle text-danger-emphasis' : 'bg-primary-subtle text-primary-emphasis' ?>"><?= h(initial($u['name'] ?? '')) ?></span>
                                        <div>
                                            <div class="fw-semibold"><?= h($u['name'] ?? '') ?><?= $isMe ? ' <span class="badge text-bg-light border">' . e('You') . '</span>' : '' ?></div>
                                            <div class="small text-body-secondary"><?= h($u['email'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge <?= $isAdm ? 'text-bg-danger' : 'text-bg-primary' ?>"><?= h($roleLabel($u['role'] ?? '')) ?></span></td>
                                <td>
                                    <?php foreach (($isAdm ? [t('Admin panel'), t('Users'), t('Settings'), t('Log'), t('Translations')] : [t('Site'), t('Profile')]) as $perm): ?>
                                        <span class="badge chip bg-body-secondary text-body border"><?= h($perm) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><span class="small text-body-secondary"><?= strpos((string)($u['password'] ?? ''), '$2y$') === 0 ? e('bcrypt, hash hidden') : e('non-standard hash') ?></span></td>
                                <td class="text-end pe-3 text-nowrap">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="_token" value="<?= h($token) ?>">
                                        <input type="hidden" name="action" value="impersonate">
                                        <input type="hidden" name="email" value="<?= h($u['email'] ?? '') ?>">
                                        <button type="submit" class="btn btn-outline-primary btn-sm"<?= $isMe ? ' disabled' : '' ?>><?= e('Become this user') ?></button>
                                    </form>
                                    <button type="button" class="btn btn-outline-secondary btn-sm js-edit"
                                            data-email="<?= h($u['email'] ?? '') ?>" data-name="<?= h($u['name'] ?? '') ?>" data-role="<?= h(strtolower((string)($u['role'] ?? 'user'))) ?>"><?= e('Edit') ?></button>
                                    <form method="post" class="d-inline" data-confirm="<?= h(sprintf(t('Delete user “%s”? This action cannot be undone.'), $u['email'] ?? '')) ?>" data-confirm-title="<?= e('Deleting user') ?>" data-confirm-btn="<?= e('Delete') ?>">
                                        <input type="hidden" name="_token" value="<?= h($token) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="email" value="<?= h($u['email'] ?? '') ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><?= e('Delete') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="noUsers" class="text-center text-body-secondary py-4 <?= $users ? 'd-none' : '' ?>"><?= $users ? e('Nothing found.') : e('No users yet.') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Подвал -->
<footer id="footer" class="text-center py-4 shadow mt-4 bg-body-tertiary">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <p class="mb-0">&copy; Flit CMS</p>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="themeSwitch">
                <label class="form-check-label" id="themeLabel" for="themeSwitch"><?= e('Light theme') ?></label>
            </div>
        </div>
    </div>
</footer>

<?php
/** Поля формы пользователя (одинаковые для создания и изменения) */
function userFields(string $prefix, bool $passwordRequired): void { ?>
    <div class="mb-3">
        <label for="<?= $prefix ?>Email" class="form-label">Email</label>
        <input type="email" class="form-control" id="<?= $prefix ?>Email" name="email" required maxlength="255" autocomplete="off">
        <div class="invalid-feedback"><?= e('Enter a valid email.') ?></div>
    </div>
    <div class="mb-3">
        <label for="<?= $prefix ?>Name" class="form-label"><?= e('Name') ?></label>
        <input type="text" class="form-control" id="<?= $prefix ?>Name" name="name" required maxlength="100" autocomplete="off">
        <div class="invalid-feedback"><?= e('Enter a name (up to 100 characters).') ?></div>
    </div>
    <div class="mb-3">
        <label for="<?= $prefix ?>Password" class="form-label"><?= $passwordRequired ? e('Password') : e('New password') ?> <span class="text-body-secondary small"><?= $passwordRequired ? e('(8-72 characters)') : e('(leave empty to keep unchanged)') ?></span></label>
        <div class="input-group has-validation">
            <input type="password" class="form-control" id="<?= $prefix ?>Password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?= $passwordRequired ? 'required' : '' ?>>
            <button class="btn btn-outline-secondary js-toggle-pass" type="button" data-target="<?= $prefix ?>Password" title="<?= e('Show / hide') ?>"><?= e('Show') ?></button>
            <button class="btn btn-outline-secondary js-gen-pass" type="button" data-target="<?= $prefix ?>Password" title="<?= e('Generate a strong password') ?>"><?= e('Generate') ?></button>
            <div class="invalid-feedback"><?= e('Password must be between 8 and 72 characters') ?></div>
        </div>
    </div>
    <div class="mb-1">
        <label for="<?= $prefix ?>Role" class="form-label"><?= e('Access rights') ?></label>
        <select class="form-select js-role" id="<?= $prefix ?>Role" name="role">
            <option value="user"><?= e('Regular user') ?></option>
            <option value="admin"><?= e('Administrator') ?></option>
        </select>
        <div class="form-text js-role-help"><?= e('Regular access: the site and personal profile.') ?></div>
    </div>
<?php } ?>

<!-- Модальное окно: создание -->
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_token" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title" id="createModalLabel"><?= e('Add user') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e('Close') ?>"></button>
            </div>
            <div class="modal-body"><?php userFields('create', true); ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e('Cancel') ?></button>
                <button type="submit" class="btn btn-success"><?= e('Create') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно: изменение -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true" <?= $editing ? 'data-auto-open="1"' : '' ?>>
    <div class="modal-dialog">
        <form method="post" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_token" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="orig_email" id="editOrig" value="<?= h($editing['email'] ?? '') ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel"><?= e('Edit user') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e('Close') ?>"></button>
            </div>
            <div class="modal-body"><?php userFields('edit', false); ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e('Cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= e('Save') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно: подтверждение -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="confirmTitle"><?= e('Confirmation') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e('Close') ?>"></button></div>
            <div class="modal-body" id="confirmText"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e('Cancel') ?></button>
                <button type="button" class="btn btn-danger" id="confirmOk"><?= e('Delete') ?></button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var hasBs = !!window.bootstrap;
    var T = <?= json_encode([
        'dark' => t('Dark theme'), 'light' => t('Light theme'), 'none' => t('Nothing found.'),
        'show' => t('Show'), 'hide' => t('Hide'),
        'helpUser' => t('Regular access: the site and personal profile.'),
        'helpAdmin' => t('Full access: admin panel, users, settings, log, translations.'),
        'edit' => t('Edit: %s'), 'confirmTitle' => t('Confirmation'), 'confirmBtn' => t('Confirm'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var LANG_COOKIE = <?= json_encode($cmsConfig['langCookieName']) ?>;

    // --- Язык (как на сайте: кука + перезагрузка) ---
    $$('.js-lang').forEach(function (b) {
        b.addEventListener('click', function () {
            document.cookie = LANG_COOKIE + '=' + encodeURIComponent(b.dataset.lang) + ';path=/';
            window.location.reload();
        });
    });

    // --- Тема (как в админке) ---
    var sw = $('#themeSwitch'), label = $('#themeLabel');
    function applyTheme(t) {
        document.documentElement.setAttribute('data-bs-theme', t);
        label.textContent = t === 'dark' ? T.dark : T.light;
    }
    var saved = 'light'; try { saved = localStorage.getItem('theme') || 'light'; } catch (e) {}
    sw.checked = saved === 'dark'; applyTheme(saved);
    sw.addEventListener('change', function () {
        var t = sw.checked ? 'dark' : 'light'; applyTheme(t);
        try { localStorage.setItem('theme', t); } catch (e) {}
    });

    // --- Поиск ---
    var search = $('#userSearch'), rows = $$('#usersBody tr'), none = $('#noUsers');
    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase(), shown = 0;
        rows.forEach(function (r) { var ok = r.getAttribute('data-search').indexOf(q) !== -1; r.classList.toggle('d-none', !ok); if (ok) shown++; });
        if (rows.length) { none.textContent = T.none; none.classList.toggle('d-none', shown > 0); }
    });

    // --- Пароль: показать / сгенерировать ---
    $$('.js-toggle-pass').forEach(function (b) {
        b.addEventListener('click', function () {
            var i = document.getElementById(b.dataset.target);
            var show = i.type === 'password'; i.type = show ? 'text' : 'password'; b.textContent = show ? T.hide : T.show;
        });
    });
    $$('.js-gen-pass').forEach(function (b) {
        b.addEventListener('click', function () {
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789-_!@#$%', out = '', buf = new Uint32Array(16);
            crypto.getRandomValues(buf);
            buf.forEach(function (n) { out += chars[n % chars.length]; });
            var i = document.getElementById(b.dataset.target); i.value = out; i.type = 'text';
            var t = $('.js-toggle-pass[data-target="' + b.dataset.target + '"]'); if (t) t.textContent = T.hide;
        });
    });

    // --- Подсказка по роли ---
    var help = { user: T.helpUser, admin: T.helpAdmin };
    $$('.js-role').forEach(function (sel) {
        var upd = function () { sel.parentNode.querySelector('.js-role-help').textContent = help[sel.value] || ''; };
        sel.addEventListener('change', upd); upd();
    });

    // --- Изменение: заполняем форму ---
    function fillEdit(email, name, role) {
        $('#editOrig').value = email; $('#editEmail').value = email; $('#editName').value = name;
        $('#editPassword').value = ''; $('#editPassword').type = 'password';
        $('#editRole').value = role === 'admin' ? 'admin' : 'user';
        $('#editRole').dispatchEvent(new Event('change'));
        $('#editModalLabel').textContent = T.edit.replace('%s', email);
    }
    $$('.js-edit').forEach(function (b) {
        b.addEventListener('click', function () {
            fillEdit(b.dataset.email, b.dataset.name, b.dataset.role);
            if (hasBs) new bootstrap.Modal($('#editModal')).show();
        });
    });
    var em = $('#editModal');
    if (em.dataset.autoOpen && hasBs) {
        var row = $$('.js-edit').filter(function (b) { return b.dataset.email.toLowerCase() === $('#editOrig').value.toLowerCase(); })[0];
        if (row) { fillEdit(row.dataset.email, row.dataset.name, row.dataset.role); new bootstrap.Modal(em).show(); }
    }

    // --- Проверка форм и защита от двойной отправки ---
    $$('form.needs-validation').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!f.checkValidity()) { e.preventDefault(); e.stopPropagation(); f.classList.add('was-validated'); return; }
            var btn = f.querySelector('button[type=submit]'); if (btn) { btn.disabled = true; }
        });
    });

    // --- Подтверждение опасных действий ---
    var pending = null;
    $$('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!hasBs) { if (confirm(f.dataset.confirm)) f.submit(); return; }
            pending = f;
            $('#confirmTitle').textContent = f.dataset.confirmTitle || T.confirmTitle;
            $('#confirmText').textContent = f.dataset.confirm;
            $('#confirmOk').textContent = f.dataset.confirmBtn || T.confirmBtn;
            bootstrap.Modal.getOrCreateInstance($('#confirmModal')).show();
        });
    });
    $('#confirmOk').addEventListener('click', function () { if (pending) { var f = pending; pending = null; f.submit(); } });
})();
</script>
</body>
</html>
