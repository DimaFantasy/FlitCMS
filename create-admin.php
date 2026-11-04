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
$base = rtrim(dirname($self), '/\\') . '/';
/** Первая буква имени для аватара (работает и без mbstring) */
function initial($text): string
{
    return preg_match('/./us', trim((string)$text), $m) ? strtoupper($m[0]) : '?';
}
$flashClass = ['ok' => 'success', 'error' => 'danger'][$flash[0] ?? ''] ?? 'info';
?><!DOCTYPE html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Первый запуск - управление пользователями</title>
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
        <strong>Внимание! После завершения настройки удалите файл create-admin.php в целях безопасности!</strong>
    </div>
    <form method="post" class="m-0" data-confirm="Удалить create-admin.php с сервера? Это действие необратимо." data-confirm-title="Удаление create-admin.php" data-confirm-btn="Удалить файл">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="delete_file">
        <button type="submit" class="btn btn-danger btn-sm fw-bold">Удалить файл</button>
    </form>
</div>

<!-- Шапка -->
<nav class="navbar navbar-expand-lg shadow bg-body-tertiary" id="navbar">
    <div class="container-fluid px-4">
        <span class="navbar-brand">Flit CMS &middot; Первый запуск</span>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-secondary">Хранилище: <?= h($cmsConfig['storage']) ?></span>
        </div>
    </div>
</nav>

<div class="d-flex flex-column flex-lg-row">

    <!-- Боковая панель -->
    <div class="p-3 bg-body-tertiary border-end" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-reset" href="#" data-bs-toggle="modal" data-bs-target="#createModal">Добавить пользователя</a></li>
            <li class="nav-item"><a class="nav-link text-reset" href="<?= h($base) ?>">Главная страница сайта</a></li>
            <li class="nav-item"><a class="nav-link text-reset" href="<?= h($base) ?>?page=login.htm">Вход на сайт</a></li>
        </ul>
        <hr>
        <div class="small text-body-secondary">
            <div class="fw-semibold mb-1">Порядок действий</div>
            <ol class="ps-3 mb-0">
                <li>Создайте администратора</li>
                <li>Войдите на сайт</li>
                <li>Удалите этот файл</li>
            </ol>
        </div>
    </div>

    <!-- Основной контент -->
    <div class="flex-grow-1">
        <div class="container-fluid p-4">
            <h2 class="mb-1">Пользователи</h2>
            <p class="text-body-secondary">Создание, изменение и удаление пользователей и администраторов без входа в систему.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= h($flashClass) ?> alert-dismissible fade show" role="alert">
                    <?= h($flash[1]) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                </div>
            <?php endif; ?>

            <?php if ($admins === 0): ?>
                <div class="alert alert-warning"><strong>Администраторов пока нет.</strong> Создайте первого кнопкой «Добавить пользователя» и выберите роль «Администратор».</div>
            <?php else: ?>
                <div class="alert alert-info">Администратор создан. Войдите на сайт и <strong>удалите create-admin.php</strong> (кнопка в красном баннере вверху).</div>
            <?php endif; ?>

            <!-- Статистика -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3"><div class="card border-0 bg-primary-subtle h-100"><div class="card-body"><div class="stat-number"><?= count($users) ?></div><div class="text-body-secondary">Всего пользователей</div></div></div></div>
                <div class="col-6 col-xl-3"><div class="card border-0 bg-danger-subtle h-100"><div class="card-body"><div class="stat-number"><?= $admins ?></div><div class="text-body-secondary">Администраторов</div></div></div></div>
                <div class="col-6 col-xl-3"><div class="card border-0 bg-success-subtle h-100"><div class="card-body"><div class="stat-number"><?= count($users) - $admins ?></div><div class="text-body-secondary">Обычных пользователей</div></div></div></div>
                <div class="col-6 col-xl-3"><div class="card border-0 bg-secondary-subtle h-100"><div class="card-body"><div class="stat-number text-uppercase"><?= h($cmsConfig['storage']) ?></div><div class="text-body-secondary">Хранилище данных</div></div></div></div>
            </div>

            <!-- Список -->
            <div class="card rounded shadow-none" id="users">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold">Все пользователи (<?= count($users) ?>)</span>
                    <div class="d-flex gap-2">
                        <input type="search" id="userSearch" class="form-control form-control-sm" placeholder="Поиск по email или имени" style="min-width:220px">
                        <button type="button" class="btn btn-success btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#createModal">+ Добавить пользователя</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th class="ps-3">Пользователь</th><th>Роль</th><th>Права доступа</th><th>Пароль</th><th class="text-end pe-3">Действия</th></tr>
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
                                            <div class="fw-semibold"><?= h($u['name'] ?? '') ?><?= $isMe ? ' <span class="badge text-bg-light border">вы</span>' : '' ?></div>
                                            <div class="small text-body-secondary"><?= h($u['email'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge <?= $isAdm ? 'text-bg-danger' : 'text-bg-primary' ?>"><?= h($roleLabel($u['role'] ?? '')) ?></span></td>
                                <td>
                                    <?php foreach (($isAdm ? ['Админ-панель', 'Пользователи', 'Настройки', 'Журнал', 'Переводы'] : ['Сайт', 'Профиль']) as $perm): ?>
                                        <span class="badge chip bg-body-secondary text-body border"><?= h($perm) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><span class="small text-body-secondary"><?= strpos((string)($u['password'] ?? ''), '$2y$') === 0 ? 'bcrypt, хеш скрыт' : 'нестандартный хеш' ?></span></td>
                                <td class="text-end pe-3 text-nowrap">
                                    <button type="button" class="btn btn-outline-secondary btn-sm js-edit"
                                            data-email="<?= h($u['email'] ?? '') ?>" data-name="<?= h($u['name'] ?? '') ?>" data-role="<?= h(strtolower((string)($u['role'] ?? 'user'))) ?>">Изменить</button>
                                    <form method="post" class="d-inline" data-confirm="Удалить пользователя «<?= h($u['email'] ?? '') ?>»? Это действие необратимо." data-confirm-title="Удаление пользователя" data-confirm-btn="Удалить">
                                        <input type="hidden" name="_token" value="<?= h($token) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="email" value="<?= h($u['email'] ?? '') ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="noUsers" class="text-center text-body-secondary py-4 <?= $users ? 'd-none' : '' ?>"><?= $users ? 'Ничего не найдено.' : 'Пользователей пока нет.' ?></div>
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
                <label class="form-check-label" id="themeLabel" for="themeSwitch">Светлая тема</label>
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
        <div class="invalid-feedback">Введите корректный email.</div>
    </div>
    <div class="mb-3">
        <label for="<?= $prefix ?>Name" class="form-label">Имя</label>
        <input type="text" class="form-control" id="<?= $prefix ?>Name" name="name" required maxlength="100" autocomplete="off">
        <div class="invalid-feedback">Укажите имя (до 100 символов).</div>
    </div>
    <div class="mb-3">
        <label for="<?= $prefix ?>Password" class="form-label"><?= $passwordRequired ? 'Пароль' : 'Новый пароль' ?> <span class="text-body-secondary small"><?= $passwordRequired ? '(8-72 символа)' : '(оставьте пустым, чтобы не менять)' ?></span></label>
        <div class="input-group has-validation">
            <input type="password" class="form-control" id="<?= $prefix ?>Password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?= $passwordRequired ? 'required' : '' ?>>
            <button class="btn btn-outline-secondary js-toggle-pass" type="button" data-target="<?= $prefix ?>Password" title="Показать / скрыть">Показать</button>
            <button class="btn btn-outline-secondary js-gen-pass" type="button" data-target="<?= $prefix ?>Password" title="Сгенерировать надёжный пароль">Сгенерировать</button>
            <div class="invalid-feedback">Пароль должен содержать от 8 до 72 символов.</div>
        </div>
    </div>
    <div class="mb-1">
        <label for="<?= $prefix ?>Role" class="form-label">Права доступа</label>
        <select class="form-select js-role" id="<?= $prefix ?>Role" name="role">
            <option value="user">Пользователь</option>
            <option value="admin">Администратор</option>
        </select>
        <div class="form-text js-role-help">Обычный доступ: сайт и личный профиль.</div>
    </div>
<?php } ?>

<!-- Модальное окно: создание -->
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_token" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title" id="createModalLabel">Добавить пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body"><?php userFields('create', true); ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-success">Создать</button>
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
                <h5 class="modal-title" id="editModalLabel">Изменить пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body"><?php userFields('edit', false); ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно: подтверждение -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="confirmTitle">Подтверждение</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
            <div class="modal-body" id="confirmText"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmOk">Удалить</button>
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

    // --- Тема (как в админке) ---
    var sw = $('#themeSwitch'), label = $('#themeLabel');
    function applyTheme(t) {
        document.documentElement.setAttribute('data-bs-theme', t);
        label.textContent = t === 'dark' ? 'Тёмная тема' : 'Светлая тема';
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
        if (rows.length) { none.textContent = 'Ничего не найдено.'; none.classList.toggle('d-none', shown > 0); }
    });

    // --- Пароль: показать / сгенерировать ---
    $$('.js-toggle-pass').forEach(function (b) {
        b.addEventListener('click', function () {
            var i = document.getElementById(b.dataset.target);
            var show = i.type === 'password'; i.type = show ? 'text' : 'password'; b.textContent = show ? 'Скрыть' : 'Показать';
        });
    });
    $$('.js-gen-pass').forEach(function (b) {
        b.addEventListener('click', function () {
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789-_!@#$%', out = '', buf = new Uint32Array(16);
            crypto.getRandomValues(buf);
            buf.forEach(function (n) { out += chars[n % chars.length]; });
            var i = document.getElementById(b.dataset.target); i.value = out; i.type = 'text';
            var t = $('.js-toggle-pass[data-target="' + b.dataset.target + '"]'); if (t) t.textContent = 'Скрыть';
        });
    });

    // --- Подсказка по роли ---
    var help = { user: 'Обычный доступ: сайт и личный профиль.', admin: 'Полный доступ: админ-панель, пользователи, настройки, журнал, переводы.' };
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
        $('#editModalLabel').textContent = 'Изменить: ' + email;
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
            $('#confirmTitle').textContent = f.dataset.confirmTitle || 'Подтверждение';
            $('#confirmText').textContent = f.dataset.confirm;
            $('#confirmOk').textContent = f.dataset.confirmBtn || 'Подтвердить';
            bootstrap.Modal.getOrCreateInstance($('#confirmModal')).show();
        });
    });
    $('#confirmOk').addEventListener('click', function () { if (pending) { var f = pending; pending = null; f.submit(); } });
})();
</script>
</body>
</html>
