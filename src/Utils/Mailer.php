<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php'; // Adjust path if needed

function isMailerDebugEnabled(): bool {
    $value = getenv('APP_DEBUG');
    if ($value === false) {
        $value = $_ENV['APP_DEBUG'] ?? '';
    }

    $value = strtolower(trim((string)$value));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function mailerEnvValue(string $key, $default = null): ?string {
    $raw = getenv($key);
    if ($raw === false) {
        $raw = $_ENV[$key] ?? $default;
    }

    if ($raw === null) {
        return null;
    }

    $value = trim((string)$raw);
    if ($value === '') {
        return is_string($default) ? trim($default) : null;
    }

    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
        $value = substr($value, 1, -1);
    }

    return trim($value);
}

function getMailerConfig(): array {
    $smtpHost = mailerEnvValue('SMTP_HOST', 'smtp.gmail.com') ?? 'smtp.gmail.com';
    $smtpUsername = mailerEnvValue('SMTP_USERNAME');
    $smtpPassword = mailerEnvValue('SMTP_PASSWORD');
    $smtpPortRaw = mailerEnvValue('SMTP_PORT', '587') ?? '587';
    $smtpPort = is_numeric($smtpPortRaw) ? (int)$smtpPortRaw : 587;
    $fromEmail = mailerEnvValue('SMTP_FROM_EMAIL', $smtpUsername ?: '');
    $fromName = mailerEnvValue('SMTP_FROM_NAME', 'Get Around Mobility') ?? 'Get Around Mobility';

    // Gmail app-passwords are often copied with spaces (xxxx xxxx xxxx xxxx).
    if ($smtpPassword !== null && stripos($smtpHost, 'gmail.com') !== false) {
        $compact = preg_replace('/\s+/', '', $smtpPassword);
        if (is_string($compact) && strlen($compact) >= 16) {
            $smtpPassword = $compact;
        }
    }

    return [
        'smtp_host' => $smtpHost,
        'smtp_username' => $smtpUsername,
        'smtp_password' => $smtpPassword,
        'smtp_port' => $smtpPort,
        'from_email' => $fromEmail,
        'from_name' => $fromName,
    ];
}

function configureMailerTransport(PHPMailer $mail, array $config): void {
    $mail->isSMTP();
    $mail->Host = (string)($config['smtp_host'] ?? 'smtp.gmail.com');
    $mail->SMTPAuth = true;
    $mail->Username = (string)($config['smtp_username'] ?? '');
    $mail->Password = (string)($config['smtp_password'] ?? '');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)($config['smtp_port'] ?? 587);
}

function buildBookingEmailTemplate(array $data = []): string {
        $esc = static function ($value): string {
                return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        };

        $fmtMoney = static function ($value): string {
                return '$' . number_format((float)$value, 2);
        };

        $fmtDate = static function ($value): string {
                if (!$value) return 'N/A';
                $ts = strtotime((string)$value);
                return $ts ? date('F j, Y', $ts) : (string)$value;
        };

        $customerName = $data['customer_name'] ?? 'Valued Customer';
        $orderId = $data['order_id'] ?? '';
        $amountDue = isset($data['amount_due']) ? (float)$data['amount_due'] : 0.0;
        $issuedAt = $data['issued_at'] ?? date('Y-m-d H:i:s');
        $pickupAt = $data['pickup_datetime'] ?? null;
        $returnAt = $data['return_datetime'] ?? null;
        $paymentMethod = $data['payment_method'] ?? '';
        $note = $data['note'] ?? 'Thank you for your business!';
        $companyName = getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'Get Around Mobility');
        $companyEmail = getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? 'support@getaroundmobility.com');

        return '
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Invoice</title>
</head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef2f6;">
        <tr>
            <td align="center" style="padding:22px 12px;">

                <table role="presentation" width="620" cellspacing="0" cellpadding="0" border="0" style="width:620px;max-width:100%;">

                    <tr>
                        <td style="padding:0 0 16px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff;border:1px solid #d7dde5;border-radius:14px;box-shadow:0 2px 6px rgba(15,23,42,0.10);">
                                <tr>
                                    <td style="padding:20px 20px 14px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td valign="top" style="width:72%;">
                                                    <div style="font-size:44px;line-height:1.05;font-weight:800;color:#111827;">' . $fmtMoney($amountDue) . '</div>
                                                    <div style="margin-top:8px;font-size:21px;font-weight:700;color:#4b5563;">Rental Invoice' . ($orderId !== '' ? ' #' . $esc($orderId) : '') . '</div>
                                                    <div style="margin-top:6px;font-size:18px;color:#4b5563;">Due ' . $esc($fmtDate($issuedAt)) . '</div>
                                                </td>
                                                <td valign="top" align="right" style="width:28%;font-size:38px;color:#d1d5db;line-height:1;">&#129534;</td>
                                            </tr>
                                        </table>

                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:16px;font-size:14px;line-height:1.4;color:#4b5563;">
                                            <tr><td style="padding:4px 0;width:90px;color:#6b7280;">To</td><td style="padding:4px 0;font-weight:700;color:#4b5563;">' . $esc($customerName) . '</td></tr>
                                            <tr><td style="padding:4px 0;color:#6b7280;">From</td><td style="padding:4px 0;font-weight:700;color:#4b5563;">' . $esc($companyName) . '</td></tr>
                                            <tr><td style="padding:4px 0;color:#6b7280;">Note</td><td style="padding:4px 0;font-weight:700;color:#4b5563;">' . $esc($note) . '</td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 0 16px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff;border:1px solid #d7dde5;border-radius:14px;box-shadow:0 2px 6px rgba(15,23,42,0.10);">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:16px;font-weight:700;color:#4b5563;margin-bottom:12px;">Invoice #Pro forms</div>
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-size:14px;line-height:1.45;color:#4b5563;">
                                            <tr>
                                                <td style="padding:8px 0;"><strong>Pickup</strong></td>
                                                <td style="padding:8px 0;text-align:right;">' . $esc($fmtDate($pickupAt)) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:8px 0;"><strong>Return</strong></td>
                                                <td style="padding:8px 0;text-align:right;">' . $esc($fmtDate($returnAt)) . '</td>
                                            </tr>
                                            ' . ($paymentMethod !== '' ? '
                                            <tr>
                                                <td style="padding:8px 0;"><strong>Payment Method</strong></td>
                                                <td style="padding:8px 0;text-align:right;">' . $esc(ucfirst((string)$paymentMethod)) . '</td>
                                            </tr>
                                            ' : '') . '
                                            <tr style="background:#f3f4f6;">
                                                <td style="padding:8px 10px;"><strong>Amount due</strong></td>
                                                <td style="padding:8px 10px;text-align:right;"><strong>' . $fmtMoney($amountDue) . '</strong></td>
                                            </tr>
                                        </table>
                                        <div style="margin-top:14px;font-size:13px;color:#6b7280;">
                                            Questions? Contact us at <a href="mailto:' . $esc($companyEmail) . '" style="color:#0c6fd6;text-decoration:none;">' . $esc($companyEmail) . '</a>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#0b3654;border-radius:0;padding:18px 20px;color:#9fb6cb;">
                            <div style="height:1px;background:#7391aa;opacity:0.7;"></div>
                            <div style="padding-top:12px;font-size:12px;">Invoice PDF is attached to this email.</div>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>';
}

function sendBookingConfirmation($toEmail, $toName, $subject, $bodyHtml, $attachments = []) {
    $mail = new PHPMailer(true);
    $config = getMailerConfig();
    $debugMailFile = null;
    if (isMailerDebugEnabled()) {
        $debugMailFile = fopen("mail-debug-log.txt", 'a');
    }

    // Only log minimal operational diagnostics in debug mode.
    if (is_resource($debugMailFile)) {
        fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] sendBookingConfirmation called\n");
    }
    if (is_resource($debugMailFile)) {
        fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] SMTP host/port loaded. host=" . ($config['smtp_host'] ?? '') . ", port=" . ($config['smtp_port'] ?? '') . "\n");
        if (!empty($config['smtp_password'])) {
            fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] SMTP password is set\n");
        } else {
            fwrite($debugMailFile, date('Y-m-d H:i:s') . " [ERROR] SMTP password is not set\n");
        }
    }
    try {
        configureMailerTransport($mail, $config);

        $fromEmail = (string)($config['from_email'] ?? '');
        $fromName = (string)($config['from_name'] ?? 'Get Around Mobility');

        // Sender & recipient
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);

        // Attachments (array of ['path' => ..., 'name' => ...])
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $att) {
                if (isset($att['path'])) {
                    if (file_exists($att['path'])) {
                        $mail->addAttachment($att['path'], $att['name'] ?? '');
                        if (is_resource($debugMailFile)) {
                            fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] Attachment added: " . $att['path'] . "\n");
                        }
                    } else {
                        if (is_resource($debugMailFile)) {
                            fwrite($debugMailFile, date('Y-m-d H:i:s') . " [ERROR] Attachment missing: " . $att['path'] . "\n");
                        }
                    }
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $mail->Body    = $bodyHtml;
        $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags((string)$bodyHtml)));

        $mail->send();
        if (is_resource($debugMailFile)) {
            fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] Email sent successfully\n");
            fclose($debugMailFile);
        }
        return true;
    } catch (Exception $e) {
        if (is_resource($debugMailFile)) {
            fwrite($debugMailFile, date('Y-m-d H:i:s') . " [ERROR] PHPMailer Exception: " . $e->getMessage() . "\nErrorInfo: " . ($mail->ErrorInfo ?? 'N/A') . "\n");
            fclose($debugMailFile);
        }
        error_log('Mailer Error: ' . $mail->ErrorInfo . ' | Exception: ' . $e->getMessage());
        return false;
    }
}

function sendContactMessageToAdmin(string $name, string $email, string $contactNumber, string $subject, string $messageHtml): bool {
    $mail = new PHPMailer(true);
    $config = getMailerConfig();

    try {
        configureMailerTransport($mail, $config);

        $fromEmail = (string)($config['from_email'] ?? '');
        $fromName = (string)($config['from_name'] ?? 'Get Around Mobility');
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($fromEmail, 'Site Admin');
        $mail->addReplyTo($email, $name);

        $mail->isHTML(true);
        $mail->Subject = $subject;

        $body = '';
        $body .= '<strong>Name:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '<br>';
        if (trim($contactNumber) !== '') {
            $body .= '<strong>Contact Number:</strong> ' . htmlspecialchars($contactNumber, ENT_QUOTES, 'UTF-8') . '<br>';
        }
        $body .= '<strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '<br>';
        $body .= '<strong>Message:</strong><br>' . $messageHtml;

        $mail->Body = $body;
        $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Contact Mailer Error: ' . $mail->ErrorInfo . ' | Exception: ' . $e->getMessage());
        return false;
    }
}