<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../reports.php';

$page = $_GET['page'] ?? 'home';
$flash = get_flash();

$departments = ['ОП', 'ОКК', 'ТО'];
$urgencies = ['срочно', 'не срочно'];
$coefficients = [1, 1.5, 2];

if ($page === 'logout') {
    session_destroy();
    header('Location: /index.php');
    exit;
}

if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department = $_POST['department'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $supervisorName = trim($_POST['supervisor_name'] ?? '');
    $supervisorEmail = trim($_POST['supervisor_email'] ?? '');

    if ($fullName && $email && $password && $department && $phone && $supervisorName && $supervisorEmail) {
        $stmt = $db->prepare('INSERT INTO users (full_name, email, password_hash, department, phone, supervisor_name, supervisor_email, created_at)
            VALUES (:full_name, :email, :password_hash, :department, :phone, :supervisor_name, :supervisor_email, :created_at)');
        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':department' => $department,
            ':phone' => $phone,
            ':supervisor_name' => $supervisorName,
            ':supervisor_email' => $supervisorEmail,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
        flash('Регистрация выполнена. Теперь войдите в систему.', 'success');
        header('Location: /index.php?page=login');
        exit;
    }
    flash('Пожалуйста, заполните все поля.', 'error');
}

if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = $user;
        flash('Добро пожаловать!', 'success');
        header('Location: /index.php');
        exit;
    }
    flash('Неверный логин или пароль.', 'error');
}

if ($page === 'task-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $user = current_user();
    $topic = trim($_POST['topic'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $urgency = $_POST['urgency'] ?? '';
    $urgencyComment = trim($_POST['urgency_comment'] ?? '');
    $department = $_POST['department'] ?? $user['department'];
    $objectName = trim($_POST['object_name'] ?? '');

    if ($topic && $description && $urgency && $urgencyComment && $department && $objectName) {
        $deptShort = dept_short_name($department);
        $requestNumber = format_request_number($db, $deptShort);
        $stmt = $db->prepare('INSERT INTO tasks (created_at, request_number, topic, description, urgency, urgency_comment, department, manager_id, object_name)
            VALUES (:created_at, :request_number, :topic, :description, :urgency, :urgency_comment, :department, :manager_id, :object_name)');
        $stmt->execute([
            ':created_at' => date('Y-m-d H:i:s'),
            ':request_number' => $requestNumber,
            ':topic' => $topic,
            ':description' => $description,
            ':urgency' => $urgency,
            ':urgency_comment' => $urgencyComment,
            ':department' => $department,
            ':manager_id' => $user['id'],
            ':object_name' => $objectName,
        ]);
        $taskId = (int)$db->lastInsertId();

        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['name'] as $index => $name) {
                $tmpName = $_FILES['attachments']['tmp_name'][$index];
                $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                $destination = $config['upload_dir'] . '/' . $safeName;
                if (move_uploaded_file($tmpName, $destination)) {
                    $stmt = $db->prepare('INSERT INTO attachments (task_id, filename, path) VALUES (:task_id, :filename, :path)');
                    $stmt->execute([
                        ':task_id' => $taskId,
                        ':filename' => $name,
                        ':path' => $destination,
                    ]);
                }
            }
        }

        $subject = $topic . ' — ' . $requestNumber;
        $body = "Новая заявка создана.\n\n";
        $body .= "Номер: {$requestNumber}\n";
        $body .= "Тема: {$topic}\n";
        $body .= "Описание: {$description}\n";
        $body .= "Срочность: {$urgency} (коэффициент 1,5)\n";
        $body .= "Комментарий: {$urgencyComment}\n";
        $body .= "Менеджер: {$user['full_name']}\n";
        $body .= "Отдел: {$department}\n";
        $body .= "Объект: {$objectName}\n";

        notify_many(
            [$config['executor_email'], $user['email'], $user['supervisor_email']],
            $subject,
            $body,
            [],
            $config
        );

        flash('Заявка отправлена и зарегистрирована.', 'success');
        header('Location: /index.php');
        exit;
    }
    flash('Заполните все поля задачи.', 'error');
}

if ($page === 'task-accept' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $taskId = (int)($_POST['task_id'] ?? 0);
    $stmt = $db->prepare('SELECT tasks.*, users.full_name, users.email, users.supervisor_email FROM tasks JOIN users ON users.id = tasks.manager_id WHERE tasks.id = :id');
    $stmt->execute([':id' => $taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($task) {
        $db->prepare('UPDATE tasks SET status = :status WHERE id = :id')->execute([
            ':status' => 'Принято в работу',
            ':id' => $taskId,
        ]);
        $subject = 'Принято в работу — ' . $task['request_number'];
        $body = "Заявка принята в работу.\n\n";
        $body .= "Номер: {$task['request_number']}\n";
        $body .= "Тема: {$task['topic']}\n";
        notify_many([
            $config['executor_email'],
            $task['email'],
            $task['supervisor_email'],
        ], $subject, $body, [], $config);
        flash('Статус обновлен.', 'success');
    }
    header('Location: /index.php');
    exit;
}

if ($page === 'template-add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $title = trim($_POST['title'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $baseRate = (float)($_POST['base_rate'] ?? 0);
    if ($title && $unit && $baseRate > 0) {
        $stmt = $db->prepare('INSERT INTO templates (title, unit, base_rate) VALUES (:title, :unit, :base_rate)');
        $stmt->execute([
            ':title' => $title,
            ':unit' => $unit,
            ':base_rate' => $baseRate,
        ]);
        flash('Шаблон добавлен.', 'success');
    } else {
        flash('Заполните все поля шаблона.', 'error');
    }
    header('Location: /index.php?page=pricing&task_id=' . (int)($_POST['task_id'] ?? 0));
    exit;
}

if ($page === 'pricing-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $taskId = (int)($_POST['task_id'] ?? 0);
    $items = $_POST['items'] ?? [];

    $db->prepare('DELETE FROM pricing_items WHERE task_id = :task_id')->execute([':task_id' => $taskId]);

    foreach ($items as $item) {
        $description = trim($item['description'] ?? '');
        $quantity = (float)($item['quantity'] ?? 0);
        $unit = trim($item['unit'] ?? '');
        $baseRate = (float)($item['base_rate'] ?? 0);
        $complexity = (float)($item['complexity_coeff'] ?? 1);
        $urgency = (float)($item['urgency_coeff'] ?? 1);

        if ($description && $quantity > 0 && $unit && $baseRate > 0) {
            $total = $quantity * $baseRate * $complexity * $urgency;
            $stmt = $db->prepare('INSERT INTO pricing_items (task_id, description, quantity, unit, base_rate, complexity_coeff, urgency_coeff, total)
                VALUES (:task_id, :description, :quantity, :unit, :base_rate, :complexity_coeff, :urgency_coeff, :total)');
            $stmt->execute([
                ':task_id' => $taskId,
                ':description' => $description,
                ':quantity' => $quantity,
                ':unit' => $unit,
                ':base_rate' => $baseRate,
                ':complexity_coeff' => $complexity,
                ':urgency_coeff' => $urgency,
                ':total' => $total,
            ]);
        }
    }

    flash('Расчет сохранен.', 'success');
    header('Location: /index.php?page=pricing&task_id=' . $taskId);
    exit;
}

if ($page === 'send-work' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $taskId = (int)($_POST['task_id'] ?? 0);
    $stmt = $db->prepare('SELECT tasks.*, users.full_name, users.email, users.supervisor_email FROM tasks JOIN users ON users.id = tasks.manager_id WHERE tasks.id = :id');
    $stmt->execute([':id' => $taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    $itemsStmt = $db->prepare('SELECT * FROM pricing_items WHERE task_id = :task_id');
    $itemsStmt->execute([':task_id' => $taskId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $attachments = [];
    $fileStmt = $db->prepare('SELECT * FROM attachments WHERE task_id = :task_id');
    $fileStmt->execute([':task_id' => $taskId]);
    foreach ($fileStmt->fetchAll(PDO::FETCH_ASSOC) as $file) {
        $attachments[] = ['path' => $file['path'], 'name' => $file['filename']];
    }

    if ($task && $items) {
        $xlsPath = __DIR__ . '/../storage/task-' . $taskId . '-work.xls';
        $rows = [];
        $totalSum = 0;
        foreach ($items as $item) {
            $rows[] = [
                $item['description'],
                $item['quantity'],
                $item['unit'],
                $item['base_rate'],
                $item['complexity_coeff'],
                $item['urgency_coeff'],
                $item['total'],
            ];
            $totalSum += $item['total'];
        }
        $rows[] = ['Итого', '', '', '', '', '', $totalSum];
        generate_xls($xlsPath, $rows, ['Работа', 'Кол-во', 'Ед.', 'Базовая ставка', 'Коэф. сложности', 'Коэф. срочности', 'Стоимость']);
        $attachments[] = ['path' => $xlsPath, 'name' => basename($xlsPath)];

        $subject = 'Выполненная работа — ' . $task['request_number'];
        $body = "Отправлена выполненная работа.\n\n";
        $body .= "Заявка: {$task['request_number']}\n";
        $body .= "Менеджер: {$task['full_name']}\n";
        $body .= "Отдел: {$task['department']}\n";
        $body .= "Объект: {$task['object_name']}\n";
        $body .= "Сумма: " . format_money($totalSum) . "\n";

        notify_many([
            $config['executor_email'],
            $task['email'],
            $task['supervisor_email'],
        ], $subject, $body, $attachments, $config);

        $db->prepare('UPDATE tasks SET status = :status WHERE id = :id')->execute([
            ':status' => 'Выполнено',
            ':id' => $taskId,
        ]);

        flash('Работа отправлена и статус обновлен.', 'success');
    } else {
        flash('Нельзя отправить работу без расчетов.', 'error');
    }
    header('Location: /index.php');
    exit;
}

if ($page === 'report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    if ($start && $end) {
        $stmt = $db->prepare('SELECT tasks.*, users.full_name FROM tasks JOIN users ON users.id = tasks.manager_id WHERE tasks.status = :status AND tasks.created_at BETWEEN :start AND :end');
        $stmt->execute([
            ':status' => 'Выполнено',
            ':start' => $start . ' 00:00:00',
            ':end' => $end . ' 23:59:59',
        ]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($tasks as $task) {
            $sumStmt = $db->prepare('SELECT SUM(total) FROM pricing_items WHERE task_id = :task_id');
            $sumStmt->execute([':task_id' => $task['id']]);
            $sum = (float)$sumStmt->fetchColumn();
            $rows[] = [
                $task['created_at'],
                $task['request_number'],
                $task['topic'],
                $task['department'],
                $task['full_name'],
                $task['urgency'],
                $sum,
            ];
        }

        $timestamp = date('YmdHis');
        $xlsPath = __DIR__ . '/../storage/report-' . $timestamp . '.xls';
        $pdfPath = __DIR__ . '/../storage/report-' . $timestamp . '.pdf';

        generate_xls($xlsPath, $rows, ['Дата', 'Номер', 'Тема', 'Отдел', 'Менеджер', 'Срочность', 'Сумма']);
        generate_pdf($pdfPath, 'Отчет по выполненным заявкам', $rows, ['Дата', 'Номер', 'Тема', 'Отдел', 'Менеджер', 'Срочность', 'Сумма']);

        $_SESSION['report_files'] = [
            'xls' => basename($xlsPath),
            'pdf' => basename($pdfPath),
        ];
        flash('Отчет сформирован.', 'success');
    } else {
        flash('Выберите период отчета.', 'error');
    }
    header('Location: /index.php?page=report');
    exit;
}

$user = current_user();

$tasks = [];
if ($user) {
    $stmt = $db->query('SELECT tasks.*, users.full_name FROM tasks JOIN users ON users.id = tasks.manager_id ORDER BY tasks.created_at DESC');
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$templates = $db->query('SELECT * FROM templates ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);

$reportFiles = $_SESSION['report_files'] ?? null;
unset($_SESSION['report_files']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Портал заявок</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="logo">Портал заявок</div>
        <nav>
            <?php if ($user): ?>
                <a href="/index.php">Дашборд</a>
                <a href="/index.php?page=task">Новая заявка</a>
                <a href="/index.php?page=report">Отчет</a>
                <a href="/index.php?page=logout">Выйти</a>
            <?php else: ?>
                <a href="/index.php?page=login">Войти</a>
                <a href="/index.php?page=register">Регистрация</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['type']) ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($page === 'login'): ?>
            <section class="card">
                <h1>Вход</h1>
                <form method="post" action="/index.php?page=login" class="form">
                    <label>Email
                        <input type="email" name="email" required>
                    </label>
                    <label>Пароль
                        <input type="password" name="password" required>
                    </label>
                    <button type="submit" class="btn">Войти</button>
                </form>
            </section>
        <?php elseif ($page === 'register'): ?>
            <section class="card">
                <h1>Регистрация сотрудника</h1>
                <form method="post" action="/index.php?page=register" class="form">
                    <label>ФИО
                        <input type="text" name="full_name" required>
                    </label>
                    <label>Email
                        <input type="email" name="email" required>
                    </label>
                    <label>Пароль
                        <input type="password" name="password" required>
                    </label>
                    <label>Отдел
                        <select name="department" required>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= htmlspecialchars($department) ?>"><?= htmlspecialchars($department) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Телефон
                        <input type="text" name="phone" required>
                    </label>
                    <label>ФИО руководителя
                        <input type="text" name="supervisor_name" required>
                    </label>
                    <label>Email руководителя
                        <input type="email" name="supervisor_email" required>
                    </label>
                    <button type="submit" class="btn">Зарегистрироваться</button>
                </form>
            </section>
        <?php elseif ($page === 'task'): ?>
            <?php require_login(); ?>
            <section class="card">
                <h1>Новая заявка</h1>
                <form method="post" action="/index.php?page=task-create" class="form" enctype="multipart/form-data">
                    <label>Название объекта
                        <input type="text" name="object_name" required>
                    </label>
                    <label>Отдел
                        <select name="department" required>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= htmlspecialchars($department) ?>" <?= $user && $user['department'] === $department ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($department) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Тема задачи
                        <input type="text" name="topic" required>
                    </label>
                    <label>Описание задачи
                        <textarea name="description" rows="4" required></textarea>
                    </label>
                    <label>Срочность
                        <div class="radio-group">
                            <?php foreach ($urgencies as $urgency): ?>
                                <label>
                                    <input type="radio" name="urgency" value="<?= htmlspecialchars($urgency) ?>" required>
                                    <?= htmlspecialchars($urgency) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small>Срочность влияет на коэффициент стоимости 1,5.</small>
                    </label>
                    <label>Комментарий по срочности
                        <input type="text" name="urgency_comment" required>
                    </label>
                    <label>Вложения
                        <input type="file" name="attachments[]" multiple>
                    </label>
                    <button type="submit" class="btn">Отправить заявку</button>
                </form>
            </section>
        <?php elseif ($page === 'pricing'): ?>
            <?php require_login(); ?>
            <?php
                $taskId = (int)($_GET['task_id'] ?? 0);
                $stmt = $db->prepare('SELECT tasks.*, users.full_name, users.department as manager_department FROM tasks JOIN users ON users.id = tasks.manager_id WHERE tasks.id = :id');
                $stmt->execute([':id' => $taskId]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);

                $itemsStmt = $db->prepare('SELECT * FROM pricing_items WHERE task_id = :task_id');
                $itemsStmt->execute([':task_id' => $taskId]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if ($task): ?>
                <section class="card">
                    <h1>Сформировать цену: <?= htmlspecialchars($task['request_number']) ?></h1>
                    <div class="task-meta">
                        <div><strong>Тема:</strong> <?= htmlspecialchars($task['topic']) ?></div>
                        <div><strong>Менеджер:</strong> <?= htmlspecialchars($task['full_name']) ?></div>
                        <div><strong>Отдел:</strong> <?= htmlspecialchars($task['department']) ?></div>
                        <div><strong>Объект:</strong> <?= htmlspecialchars($task['object_name']) ?></div>
                    </div>
                    <form method="post" action="/index.php?page=pricing-save" class="form" id="pricing-form">
                        <input type="hidden" name="task_id" value="<?= $taskId ?>">
                        <div id="items-container">
                            <?php if ($items): ?>
                                <?php foreach ($items as $index => $item): ?>
                                    <div class="item-row">
                                        <select class="template-select">
                                            <option value="">Шаблон</option>
                                            <?php foreach ($templates as $template): ?>
                                                <option value="<?= $template['id'] ?>"
                                                    data-title="<?= htmlspecialchars($template['title']) ?>"
                                                    data-unit="<?= htmlspecialchars($template['unit']) ?>"
                                                    data-rate="<?= htmlspecialchars($template['base_rate']) ?>">
                                                    <?= htmlspecialchars($template['title']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="items[<?= $index ?>][description]" value="<?= htmlspecialchars($item['description']) ?>" placeholder="Описание" required>
                                        <input type="number" step="0.1" name="items[<?= $index ?>][quantity]" value="<?= htmlspecialchars($item['quantity']) ?>" placeholder="Кол-во" required>
                                        <input type="text" name="items[<?= $index ?>][unit]" value="<?= htmlspecialchars($item['unit']) ?>" placeholder="Ед." required>
                                        <input type="number" step="0.01" name="items[<?= $index ?>][base_rate]" value="<?= htmlspecialchars($item['base_rate']) ?>" placeholder="Базовая ставка" required>
                                        <select name="items[<?= $index ?>][complexity_coeff]">
                                            <?php foreach ($coefficients as $coeff): ?>
                                                <option value="<?= $coeff ?>" <?= (float)$item['complexity_coeff'] === (float)$coeff ? 'selected' : '' ?>><?= $coeff ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="items[<?= $index ?>][urgency_coeff]">
                                            <?php foreach ($coefficients as $coeff): ?>
                                                <option value="<?= $coeff ?>" <?= (float)$item['urgency_coeff'] === (float)$coeff ? 'selected' : '' ?>><?= $coeff ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="item-row">
                                    <select class="template-select">
                                        <option value="">Шаблон</option>
                                        <?php foreach ($templates as $template): ?>
                                            <option value="<?= $template['id'] ?>"
                                                data-title="<?= htmlspecialchars($template['title']) ?>"
                                                data-unit="<?= htmlspecialchars($template['unit']) ?>"
                                                data-rate="<?= htmlspecialchars($template['base_rate']) ?>">
                                                <?= htmlspecialchars($template['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="items[0][description]" placeholder="Описание" required>
                                    <input type="number" step="0.1" name="items[0][quantity]" placeholder="Кол-во" required>
                                    <input type="text" name="items[0][unit]" placeholder="Ед." required>
                                    <input type="number" step="0.01" name="items[0][base_rate]" placeholder="Базовая ставка" required>
                                    <select name="items[0][complexity_coeff]">
                                        <?php foreach ($coefficients as $coeff): ?>
                                            <option value="<?= $coeff ?>"><?= $coeff ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="items[0][urgency_coeff]">
                                        <?php foreach ($coefficients as $coeff): ?>
                                            <option value="<?= $coeff ?>"><?= $coeff ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn secondary" id="add-row">Добавить работу</button>
                        <button type="submit" class="btn">Сохранить расчет</button>
                    </form>
                </section>

                <section class="card">
                    <h2>Шаблоны работ</h2>
                    <form method="post" action="/index.php?page=template-add" class="form">
                        <input type="hidden" name="task_id" value="<?= $taskId ?>">
                        <label>Название
                            <input type="text" name="title" required>
                        </label>
                        <label>Единица (часы/шт)
                            <input type="text" name="unit" required>
                        </label>
                        <label>Базовая ставка
                            <input type="number" step="0.01" name="base_rate" required>
                        </label>
                        <button type="submit" class="btn">Добавить шаблон</button>
                    </form>
                </section>
            <?php else: ?>
                <p>Задача не найдена.</p>
            <?php endif; ?>
        <?php elseif ($page === 'report'): ?>
            <?php require_login(); ?>
            <section class="card">
                <h1>Отчет по выполненным заявкам</h1>
                <form method="post" action="/index.php?page=report" class="form">
                    <label>Начало периода
                        <input type="date" name="start_date" required>
                    </label>
                    <label>Конец периода
                        <input type="date" name="end_date" required>
                    </label>
                    <button type="submit" class="btn">Сформировать</button>
                </form>
                <?php if ($reportFiles): ?>
                    <div class="report-links">
                        <a href="/download.php?file=<?= urlencode($reportFiles['xls']) ?>" class="btn secondary">Скачать XLS</a>
                        <a href="/download.php?file=<?= urlencode($reportFiles['pdf']) ?>" class="btn secondary">Скачать PDF</a>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <?php if (!$user): ?>
                <section class="card">
                    <h1>Система заявок</h1>
                    <p>Для постановки задач необходимо зарегистрироваться и авторизоваться.</p>
                </section>
            <?php else: ?>
                <section class="card">
                    <h1>Дашборд заявок</h1>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Номер</th>
                                    <th>Тема</th>
                                    <th>Срочность</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($task['created_at']) ?></td>
                                        <td><?= htmlspecialchars($task['request_number']) ?></td>
                                        <td><?= htmlspecialchars($task['topic']) ?></td>
                                        <td><?= htmlspecialchars($task['urgency']) ?></td>
                                        <td><?= htmlspecialchars($task['status']) ?></td>
                                        <td>
                                            <div class="actions">
                                                <form method="post" action="/index.php?page=task-accept">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                    <button type="submit" class="btn small" <?= $task['status'] === 'Принято в работу' ? 'disabled' : '' ?>>Принять в работу</button>
                                                </form>
                                                <a class="btn small secondary" href="/index.php?page=pricing&task_id=<?= $task['id'] ?>">Сформировать цену</a>
                                                <form method="post" action="/index.php?page=send-work">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                    <button type="submit" class="btn small" <?= $task['status'] === 'Выполнено' ? 'disabled' : '' ?>>Отправить выполненную работу</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>
        const addRowButton = document.getElementById('add-row');
        if (addRowButton) {
            addRowButton.addEventListener('click', () => {
                const container = document.getElementById('items-container');
                const rows = container.querySelectorAll('.item-row');
                const newIndex = rows.length;
                const template = rows[0].cloneNode(true);

                template.querySelectorAll('input').forEach(input => {
                    const name = input.getAttribute('name');
                    if (name) {
                        input.setAttribute('name', name.replace(/items\[\d+\]/, `items[${newIndex}]`));
                        input.value = '';
                    }
                });

                template.querySelectorAll('select').forEach(select => {
                    const name = select.getAttribute('name');
                    if (name) {
                        select.setAttribute('name', name.replace(/items\[\d+\]/, `items[${newIndex}]`));
                    }
                    select.selectedIndex = 0;
                });

                container.appendChild(template);
            });
        }

        const attachTemplateListener = select => {
            select.addEventListener('change', event => {
                const option = event.target.selectedOptions[0];
                if (!option || !option.dataset.title) {
                    return;
                }
                const row = event.target.closest('.item-row');
                row.querySelector('input[name*=\"description\"]').value = option.dataset.title;
                row.querySelector('input[name*=\"unit\"]').value = option.dataset.unit;
                row.querySelector('input[name*=\"base_rate\"]').value = option.dataset.rate;
            });
        };

        document.querySelectorAll('.template-select').forEach(attachTemplateListener);

        if (addRowButton) {
            const container = document.getElementById('items-container');
            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.classList && node.classList.contains('item-row')) {
                            const select = node.querySelector('.template-select');
                            if (select) {
                                attachTemplateListener(select);
                            }
                        }
                    });
                });
            });
            observer.observe(container, { childList: true });
        }
    </script>
</body>
</html>
