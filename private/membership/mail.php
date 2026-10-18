<?php

/**
 * Mail delivery for the online membership application, via SMTP (PHPMailer).
 *
 * Locally the example config points at Mailpit (docker-compose, web UI on
 * http://localhost:8025), so no real mails go out while testing.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/texts.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class MembershipMailException extends RuntimeException
{
}

/**
 * Sends a plain-text mail. $attachments: list of ['content' => string, 'filename' => string,
 * 'type' => string].
 */
function membership_send_mail(
    string $to,
    string $subject,
    string $body,
    array $attachments = [],
    ?string $replyTo = null
): void {
    $config = membership_config()['mail'];

    if (empty($config['smtp_host']) || empty($config['from'])) {
        throw new MembershipMailException('membership.mail is not configured (smtp_host / from)');
    }

    $mailer = new PHPMailer(true);

    try {
        $mailer->isSMTP();
        $mailer->Host = (string)$config['smtp_host'];
        $mailer->Port = (int)($config['smtp_port'] ?? 587);
        $mailer->SMTPSecure = (string)($config['smtp_secure'] ?? PHPMailer::ENCRYPTION_STARTTLS);
        // Only an explicitly empty "smtp_secure" (Mailpit locally) disables TLS.
        $mailer->SMTPAutoTLS = $mailer->SMTPSecure !== '';

        if (!empty($config['smtp_user'])) {
            $mailer->SMTPAuth = true;
            $mailer->Username = (string)$config['smtp_user'];
            $mailer->Password = (string)($config['smtp_password'] ?? '');
        }

        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setLanguage('de', __DIR__ . '/../vendor/phpmailer/language/');
        $mailer->setFrom((string)$config['from'], (string)($config['from_name'] ?? ''));
        $mailer->addAddress($to);

        if ($replyTo !== null) {
            $mailer->addReplyTo($replyTo);
        }

        $mailer->Subject = $subject;
        $mailer->Body = $body;
        $mailer->isHTML(false);

        foreach ($attachments as $attachment) {
            $mailer->addStringAttachment(
                $attachment['content'],
                $attachment['filename'],
                PHPMailer::ENCODING_BASE64,
                $attachment['type']
            );
        }

        $mailer->send();
    } catch (PHPMailerException $error) {
        throw new MembershipMailException('Sending mail failed: ' . $error->getMessage(), 0, $error);
    }
}

function membership_site_url(string $path): string
{
    return rtrim(membership_config()['site_url'], '/') . $path;
}

function membership_format_euro(float $amount): string
{
    return number_format($amount, 2, ',', '.') . ' €';
}

function membership_format_interval(int $months): string
{
    return match ($months) {
        1 => 'monatlich',
        3 => 'vierteljährlich',
        6 => 'halbjährlich',
        12 => 'jährlich',
        default => "alle {$months} Monate",
    };
}

function membership_group_summary(array $group): string
{
    $fee = $group['admissionFee'] > 0 ? membership_format_euro($group['admissionFee']) . ' einmalig' : 'entfällt';
    $amount = $group['amount'] !== null
        ? membership_format_euro($group['amount']) . ' ' . membership_format_interval($group['intervalMonths'])
        : '-';

    return "Mitgliedsart: {$group['name']}\nBeitrag: {$amount}\nAufnahmegebühr: {$fee}";
}

function membership_send_confirmation_mail(array $application, string $reference, string $token, DateTimeImmutable $expiresAt): void
{
    $applicant = $application['applicant'];
    $applicantName = "{$applicant['firstName']} {$applicant['lastName']}";
    $link = membership_site_url('/mitglied-werden/bestaetigen?token=' . rawurlencode($token));
    $groupSummary = membership_group_summary($application['group']);
    $expires = $expiresAt->format('d.m.Y \u\m H:i \U\h\r');
    $club = MEMBERSHIP_CLUB_NAME;

    // Minors: the mail goes to the legal guardian, addressed formally ("Sie").
    if ($application['isMinor']) {
        $guardian = $application['guardian'];
        $body = <<<TEXT
            Hallo {$guardian['firstName']} {$guardian['lastName']},

            für {$applicantName} wurde ein Antrag auf Mitgliedschaft im {$club} gestellt und Sie wurden als
            Erziehungsberechtigte/r angegeben.

            Antragsnummer: {$reference}
            Antragsteller/in: {$applicantName}
            {$groupSummary}

            Bitte bestätigen Sie den Antrag bis zum {$expires} über diesen Link:

            {$link}

            Erst mit Ihrer Bestätigung ist der Antrag eingereicht. Danach erhalten Sie eine Zusammenfassung als PDF,
            und der Vorstand entscheidet über die Aufnahme.

            Falls Sie diesen Antrag nicht kennen, ignorieren Sie diese E-Mail einfach - ohne Bestätigung werden alle
            Angaben automatisch gelöscht.

            Viele Grüße
            {$club}
            TEXT;
    } else {
        $body = <<<TEXT
            Hallo {$applicantName},

            vielen Dank für deinen Antrag auf Mitgliedschaft im {$club}.

            Antragsnummer: {$reference}
            {$groupSummary}

            Bitte bestätige den Antrag bis zum {$expires} über diesen Link:

            {$link}

            Erst mit deiner Bestätigung ist der Antrag eingereicht. Danach erhältst du eine Zusammenfassung als PDF,
            und der Vorstand entscheidet über die Aufnahme.

            Falls der Antrag nicht von dir stammt, ignoriere diese E-Mail einfach - ohne Bestätigung werden alle
            Angaben automatisch gelöscht.

            Viele Grüße
            {$club}
            TEXT;
    }

    membership_send_mail(
        membership_confirmation_email($application),
        "Bitte bestätigen: Mitgliedsantrag {$reference}",
        $body
    );
}
