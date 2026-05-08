<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php'; // Adjust path if needed

function sendBookingConfirmation($toEmail, $toName, $subject, $bodyHtml, $attachments = []) {
    $mail = new PHPMailer(true);
    $debugMailFile = fopen("mail-debug-log.txt", 'a');
    // Log recipient and SMTP config (mask password)
    fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] sendBookingConfirmation called. To: $toEmail, Name: $toName\n");
    $smtpHost = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
    $smtpUsername = getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? null);
    $smtpPassword = getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? null);
    $smtpPort = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587);
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? ($smtpUsername));
    $fromName = getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'Get Around Mobility');
    fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] SMTP config: host=$smtpHost, username=$smtpUsername, port=$smtpPort, fromEmail=$fromEmail, fromName=$fromName\n");
    if ($smtpPassword) {
        fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] SMTP password is set (masked)\n");
    } else {
        fwrite($debugMailFile, date('Y-m-d H:i:s') . " [ERROR] SMTP password is NOT set!\n");
    }
    try {
        // SMTP config from environment variables (getenv or $_ENV fallback)
        $smtpHost = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
        $smtpUsername = getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? null);
        $smtpPassword = getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? null);
        $smtpPort = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587);
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? ($smtpUsername));
        $fromName = getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'Get Around Mobility');

        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort;

        // Sender & recipient
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);

        // Attachments (array of ['path' => ..., 'name' => ...])
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $att) {
                if (isset($att['path'])) {
                    if (file_exists($att['path'])) {
                        $mail->addAttachment($att['path'], $att['name'] ?? '');
                        fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] Attachment added: " . $att['path'] . "\n");
                    } else {
                        fwrite($debugMailFile, date('Y-m-d H:i:s') . " [ERROR] Attachment missing: " . $att['path'] . "\n");
                    }
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;

        $mail->send();
        fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] Email sent successfully to $toEmail\n");
        fclose($debugMailFile);
        return true;
    } catch (Exception $e) {
        fwrite($debugMailFile, date('Y-m-d H:i:s') . " [ERROR] PHPMailer Exception: " . $e->getMessage() . "\nErrorInfo: " . ($mail->ErrorInfo ?? 'N/A') . "\n");
        fclose($debugMailFile);
        error_log('Mailer Error: ' . $mail->ErrorInfo . ' | Exception: ' . $e->getMessage());
        return false;
    }
}