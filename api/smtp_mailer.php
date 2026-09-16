<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/autoload.php';

final class SmtpMailer
{
    private array $config;
    private string $lastCode = 'MAIL_NOT_ATTEMPTED';

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function errorCode(): string
    {
        return $this->lastCode;
    }

    public function send(string $recipient, string $subject, string $html, string $text, array $attachments = []): bool
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || $this->config['host'] === '' || $this->config['username'] === '' || $this->config['password'] === '' || $this->config['from_email'] === '') {
            $this->lastCode = 'MAIL_CONFIG_INCOMPLETE';
            return false;
        }
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) $this->config['host'];
            $mail->Port = (int) $this->config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = (string) $this->config['username'];
            $mail->Password = (string) $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom((string) $this->config['from_email'], (string) $this->config['from_name']);
            $mail->addAddress($recipient);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;
            foreach ($attachments as $attachment) {
                if (is_file($attachment['path']) && is_readable($attachment['path'])) $mail->addAttachment($attachment['path'], $attachment['name']);
            }
            $mail->send();
            $this->lastCode = 'MAIL_SENT';
            return true;
        } catch (Exception $exception) {
            $this->lastCode = 'MAIL_SMTP_SEND_FAILED';
            error_log('Aurahuz mail error: ' . $exception->getMessage());
            return false;
        }
    }
}