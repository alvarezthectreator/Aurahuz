<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/autoload.php';

function aurahuzEmailTemplate(string $eyebrow, string $title, string $content, string $footer = 'Aurahuz · Clean rituals for every day'): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#f5f7f3;color:#23372c;font-family:Arial,Helvetica,sans-serif"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7f3;padding:32px 16px"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:440px;background:#fff;border:1px solid #e1e8e1;border-radius:16px;overflow:hidden"><tr><td style="background:#0d4b34;padding:28px 22px;color:#fff"><div style="font-size:20px;font-weight:700;letter-spacing:-.3px">Aurahuz</div><div style="margin-top:14px;font-size:10px;font-weight:700;letter-spacing:1.4px;color:#a8d7ad">' . htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') . '</div></td></tr><tr><td style="padding:28px 22px 24px"><h1 style="margin:0 0 14px;font-size:20px;line-height:1.3;color:#26382d">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><div style="font-size:13px;line-height:1.7;color:#68766d">' . $content . '</div></td></tr><tr><td style="border-top:1px solid #edf0ec;padding:16px 22px;font-size:11px;color:#849087">' . htmlspecialchars($footer, ENT_QUOTES, 'UTF-8') . '</td></tr></table></td></tr></table></body></html>';
}

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