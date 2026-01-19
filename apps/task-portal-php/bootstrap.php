<?php
session_start();

date_default_timezone_set('Europe/Moscow');

$config = [
    'db_path' => __DIR__ . '/storage/app.db',
    'upload_dir' => __DIR__ . '/uploads',
    'executor_email' => 'holod@smirnovvit.ru',
    'executor_name' => 'Виталий Смирнов',
    'mail_from' => 'no-reply@example.com',
];

if (!is_dir($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0775, true);
}

$db = new PDO('sqlite:' . $config['db_path']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    department TEXT NOT NULL,
    phone TEXT NOT NULL,
    supervisor_name TEXT NOT NULL,
    supervisor_email TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT "manager",
    created_at TEXT NOT NULL
)');

$db->exec('CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    request_number TEXT NOT NULL,
    topic TEXT NOT NULL,
    description TEXT NOT NULL,
    urgency TEXT NOT NULL,
    urgency_comment TEXT NOT NULL,
    department TEXT NOT NULL,
    manager_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT "Новая",
    object_name TEXT NOT NULL,
    FOREIGN KEY(manager_id) REFERENCES users(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    path TEXT NOT NULL,
    FOREIGN KEY(task_id) REFERENCES tasks(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    unit TEXT NOT NULL,
    base_rate REAL NOT NULL
)');

$db->exec('CREATE TABLE IF NOT EXISTS pricing_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    description TEXT NOT NULL,
    quantity REAL NOT NULL,
    unit TEXT NOT NULL,
    base_rate REAL NOT NULL,
    complexity_coeff REAL NOT NULL,
    urgency_coeff REAL NOT NULL,
    total REAL NOT NULL,
    FOREIGN KEY(task_id) REFERENCES tasks(id)
)');

function ensure_demo_user(PDO $db): void
{
    $email = 'smirnovvit@vk.com';
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $stmt = $db->prepare('INSERT INTO users (full_name, email, password_hash, department, phone, supervisor_name, supervisor_email, created_at)
        VALUES (:full_name, :email, :password_hash, :department, :phone, :supervisor_name, :supervisor_email, :created_at)');
    $stmt->execute([
        ':full_name' => 'Ольга Смирнова',
        ':email' => $email,
        ':password_hash' => password_hash('demo1234', PASSWORD_DEFAULT),
        ':department' => 'ОП',
        ':phone' => '+7 900 000-00-00',
        ':supervisor_name' => 'Руководитель отдела',
        ':supervisor_email' => 'supervisor@example.com',
        ':created_at' => date('Y-m-d H:i:s'),
    ]);
}

ensure_demo_user($db);

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function require_login()
{
    if (!current_user()) {
        header('Location: /index.php?page=login');
        exit;
    }
}

function format_request_number(PDO $db, string $department): string
{
    $month = (int)date('m');
    $year = date('y');
    $start = date('Y-m-01 00:00:00');
    $end = date('Y-m-t 23:59:59');

    $stmt = $db->prepare('SELECT COUNT(*) FROM tasks WHERE created_at BETWEEN :start AND :end');
    $stmt->execute([':start' => $start, ':end' => $end]);
    $count = (int)$stmt->fetchColumn();

    $sequence = $count + 1;
    return sprintf('Заявка №%d-%02d/%s-%s', $sequence, $month, $year, $department);
}

function dept_short_name(string $department): string
{
    $map = [
        'ОП' => 'ОП',
        'ОКК' => 'ОКК',
        'ТО' => 'ТО',
    ];
    return $map[$department] ?? $department;
}

function format_money(float $value): string
{
    return number_format($value, 2, ',', ' ');
}

function flash(string $message, string $type = 'info')
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function get_flash()
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
