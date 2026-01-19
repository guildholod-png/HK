<?php

function send_mail(string $to, string $subject, string $body, array $attachments = [], array $config = []): bool
{
    $from = $config['mail_from'] ?? 'no-reply@example.com';
    $boundary = '=_boundary_' . md5((string)microtime(true));
    $headers = [];
    $headers[] = 'From: ' . $from;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $body . "\r\n";

    foreach ($attachments as $attachment) {
        if (!file_exists($attachment['path'])) {
            continue;
        }
        $content = chunk_split(base64_encode(file_get_contents($attachment['path'])));
        $filename = $attachment['name'] ?? basename($attachment['path']);
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: application/octet-stream; name=\"{$filename}\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
        $message .= $content . "\r\n";
    }

    $message .= "--{$boundary}--";

    $sent = mail($to, $subject, $message, implode("\r\n", $headers));

    $logEntry = date('c') . " | To: {$to} | Subject: {$subject}\n";
    $logEntry .= $body . "\n";
    if (!empty($attachments)) {
        $logEntry .= 'Attachments: ' . implode(', ', array_map(fn($item) => $item['name'] ?? basename($item['path']), $attachments)) . "\n";
    }
    $logEntry .= "---\n";
    file_put_contents(__DIR__ . '/storage/email.log', $logEntry, FILE_APPEND);

    return $sent;
}

function notify_many(array $emails, string $subject, string $body, array $attachments = [], array $config = []): void
{
    $unique = array_unique(array_filter($emails));
    foreach ($unique as $email) {
        send_mail($email, $subject, $body, $attachments, $config);
    }
}
