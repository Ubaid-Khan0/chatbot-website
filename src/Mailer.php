<?php

declare(strict_types=1);

namespace Nexum;

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public function sendLeadNotification(array $lead): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = Config::require('SMTP_HOST');
        $mail->Port = Config::int('SMTP_PORT', 587);
        $mail->SMTPAuth = true;
        $mail->Username = Config::require('SMTP_USERNAME');
        $mail->Password = Config::require('SMTP_PASSWORD');
        $encryption = strtolower(Config::get('SMTP_ENCRYPTION', 'tls') ?? 'tls');
        $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(Config::require('SMTP_FROM_EMAIL'), Config::get('SMTP_FROM_NAME', 'Nexum Chatbot'));
        $mail->addAddress(Config::require('LEAD_NOTIFICATION_EMAIL'));
        $mail->addReplyTo((string) $lead['email'], (string) $lead['name']);
        $mail->Subject = 'New Nexum Chatbot enquiry — ' . $lead['plan'];
        $safe = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $mail->isHTML(true);
        $mail->Body = '<h2>New chatbot enquiry</h2>'
            . '<p><strong>Name:</strong> ' . $safe($lead['name']) . '</p>'
            . '<p><strong>Company:</strong> ' . $safe($lead['company']) . '</p>'
            . '<p><strong>Email:</strong> ' . $safe($lead['email']) . '</p>'
            . '<p><strong>Plan:</strong> ' . $safe($lead['plan']) . '</p>'
            . '<p><strong>Message:</strong><br>' . nl2br($safe($lead['message'])) . '</p>';
        $mail->AltBody = "New chatbot enquiry\nName: {$lead['name']}\nCompany: {$lead['company']}\nEmail: {$lead['email']}\nPlan: {$lead['plan']}\n\n{$lead['message']}";
        $mail->send();
    }
}
