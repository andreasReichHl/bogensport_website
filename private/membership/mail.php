<?php

/**
 * Mail delivery for the online membership application, via SMTP (PHPMailer).
 *
 * Every mail is built from blocks (see membership_mail_html()) and goes out as HTML in the look
 * of the website plus a plain-text version of the same content.
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

const MEMBERSHIP_MAIL_LOGO = __DIR__ . '/assets/mail-header.png';

class MembershipMailException extends RuntimeException
{
}

/**
 * Sends a mail. $mail: ['title' => string, 'blocks' => list (see membership_mail_html()),
 * 'preview' => ?string (inbox preview line), 'signature' => bool (default true)].
 * $attachments: list of ['content' => string, 'filename' => string, 'type' => string].
 */
function membership_send_mail(
    string $to,
    string $subject,
    array $mail,
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
        $mailer->isHTML(true);
        $mailer->Body = membership_mail_html($subject, $mail);
        $mailer->AltBody = membership_mail_text($mail);
        // Embedded instead of loaded from the website: shows up even where remote images are blocked.
        $mailer->addEmbeddedImage(MEMBERSHIP_MAIL_LOGO, 'logo', 'logo.png', PHPMailer::ENCODING_BASE64, 'image/png');

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

function membership_mail_escape(string $text): string
{
    return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), false);
}

/**
 * Footer links: label => URL.
 */
function membership_mail_footer_links(): array
{
    return [
        'Website' => membership_site_url('/'),
        'Impressum' => membership_site_url('/impressum'),
        'Datenschutz' => membership_site_url('/datenschutz'),
    ];
}

/**
 * Plain-text version of a mail, same content as the HTML version.
 */
function membership_mail_text(array $mail): string
{
    $parts = [];

    foreach ($mail['blocks'] as $block) {
        $parts[] = match ($block[0]) {
            'text', 'note' => wordwrap($block[1], 76),
            'facts' => implode("\n", array_map(
                static fn(string $label, string $value): string => "{$label}: {$value}",
                array_keys($block[1]),
                $block[1]
            )),
            'list' => implode("\n", array_map(static fn(string $item): string => "- {$item}", $block[1])),
            'button' => "{$block[1]}:\n{$block[2]}",
            'code' => preg_replace('/^/m', '    ', $block[1]),
        };
    }

    if ($mail['signature'] ?? true) {
        $parts[] = "Viele Grüße\nDer Vorstand";
    }

    $links = [];

    foreach (membership_mail_footer_links() as $label => $url) {
        $links[] = "{$label}: {$url}";
    }

    $parts[] = "-- \n" . MEMBERSHIP_CLUB_NAME . ', ' . MEMBERSHIP_CLUB_ADDRESS . "\n" . implode("\n", $links);

    return implode("\n\n", $parts) . "\n";
}

/**
 * HTML version of a mail in the look of the website: the logo bar of the navigation, a title,
 * the content blocks and a footer with the legal links. Blocks:
 *   ['text', string]                     paragraph (\n = line break)
 *   ['note', string]                     small grey paragraph
 *   ['facts', [label => value, …]]       grey box with key data
 *   ['list', [string, …]]                bullet list
 *   ['button', label, url]               call to action, with the plain link below it
 *   ['code', string]                     monospace block (shell commands, error messages)
 *
 * Inter/Montserrat are loaded from the website; clients that block web fonts (e.g. Gmail,
 * Outlook) fall back to their system sans-serif. The logo (logo bar of the navigation, rendered
 * from src/assets/bobs_logo_white_old.svg and Montserrat at 3x) is embedded, so it looks the same
 * everywhere.
 */
function membership_mail_html(string $subject, array $mail): string
{
    $siteUrl = rtrim(membership_config()['site_url'], '/');
    $sans = "Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $heading = "Montserrat,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $mono = 'ui-monospace,Menlo,Consolas,monospace';
    $content = '';

    foreach ($mail['blocks'] as $block) {
        $content .= match ($block[0]) {
            'text' => '<p style="margin:0 0 16px">' . membership_mail_escape($block[1]) . '</p>',
            'note' => '<p style="margin:0 0 16px;font-size:13px;line-height:1.5;color:#64748b">'
                . membership_mail_escape($block[1]) . '</p>',
            'facts' => membership_mail_html_facts($block[1]),
            'list' => '<ul style="margin:0 0 16px;padding-left:20px">' . implode('', array_map(
                static fn(string $item): string => '<li style="margin:0 0 4px">' . membership_mail_escape($item) . '</li>',
                $block[1]
            )) . '</ul>',
            'button' => membership_mail_html_button($block[1], $block[2]),
            'code' => '<pre style="margin:0 0 16px;padding:12px 14px;background:#f1f5f9;border-radius:6px;'
                . "font-family:{$mono};font-size:13px;line-height:1.5;color:#0f172a;white-space:pre-wrap;"
                . 'word-break:break-all">' . htmlspecialchars($block[1], ENT_QUOTES, 'UTF-8') . '</pre>',
        };
        $content .= "\n";
    }

    if ($mail['signature'] ?? true) {
        $content .= '<p style="margin:24px 0 0">Viele Grüße<br>Der Vorstand</p>';
    }

    $links = [];

    foreach (membership_mail_footer_links() as $label => $url) {
        $links[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="color:#475569;text-decoration:underline">'
            . $label . '</a>';
    }

    $title = membership_mail_escape($mail['title']);
    $documentTitle = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $preview = htmlspecialchars((string)($mail['preview'] ?? ''), ENT_QUOTES, 'UTF-8');
    $club = htmlspecialchars(MEMBERSHIP_CLUB_NAME, ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars(MEMBERSHIP_CLUB_ADDRESS, ENT_QUOTES, 'UTF-8');
    $footerLinks = implode(' &nbsp;·&nbsp; ', $links);
    $siteHome = htmlspecialchars($siteUrl . '/', ENT_QUOTES, 'UTF-8');

    return <<<HTML
        <!DOCTYPE html>
        <html lang="de">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>{$documentTitle}</title>
        <style>
        @font-face{font-family:Inter;src:url("{$siteUrl}/assets/fonts/Inter-Regular.woff2") format("woff2");font-weight:400;font-style:normal}
        @font-face{font-family:Inter;src:url("{$siteUrl}/assets/fonts/Inter-SemiBold.woff2") format("woff2");font-weight:600;font-style:normal}
        @font-face{font-family:Montserrat;src:url("{$siteUrl}/assets/fonts/Montserrat-VariableFont_wght.ttf") format("truetype");font-weight:100 900;font-style:normal}
        </style>
        </head>
        <body style="margin:0;padding:0;background:#f1f5f9">
        <div style="display:none;max-height:0;overflow:hidden;opacity:0">{$preview}</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9">
        <tr><td align="center" style="padding:32px 12px">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px">
        <tr><td style="background:#002f5f;border-radius:12px 12px 0 0;padding:20px 32px">
        <a href="{$siteHome}" style="text-decoration:none"><img src="cid:logo" width="228" height="44" alt="{$club}" style="display:block;border:0;color:#ffffff;font-family:{$heading};font-size:16px;font-weight:700"></a>
        </td></tr>
        <tr><td style="background:#ffffff;border-radius:0 0 12px 12px;padding:32px;color:#334155;font-family:{$sans};font-size:15px;line-height:1.6">
        <h1 style="margin:0 0 20px;font-family:{$heading};font-size:22px;line-height:1.3;font-weight:700;color:#002f5f">{$title}</h1>
        {$content}
        </td></tr>
        <tr><td align="center" style="padding:24px 16px 0;font-family:{$sans};font-size:12px;line-height:1.6;color:#64748b">
        {$club} &nbsp;·&nbsp; {$address}<br>
        {$footerLinks}
        </td></tr>
        </table>
        </td></tr>
        </table>
        </body>
        </html>
        HTML;
}

function membership_mail_html_facts(array $facts): string
{
    $rows = '';

    foreach ($facts as $label => $value) {
        $rows .= '<tr><td style="padding:6px 16px 6px 0;vertical-align:top;font-size:13px;color:#64748b;white-space:nowrap">'
            . membership_mail_escape((string)$label) . '</td>'
            . '<td style="padding:6px 0;vertical-align:top;color:#0f172a;font-weight:600">'
            . membership_mail_escape((string)$value) . '</td></tr>';
    }

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;'
        . 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px"><tr><td style="padding:12px 20px">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5">'
        . $rows . '</table></td></tr></table>';
}

function membership_mail_html_button(string $label, string $url): string
{
    $href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    // align="center" plus auto margins: centred in Outlook as well as in browser-based clients.
    return '<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:28px auto 12px"><tr>'
        . '<td align="center" style="background:#002f5f;border-radius:8px">'
        . "<a href=\"{$href}\" style=\"display:inline-block;padding:12px 28px;color:#ffffff;font-weight:600;"
        . 'font-size:15px;text-decoration:none">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>'
        . '</td></tr></table>'
        . '<p style="margin:0 0 32px;font-size:12px;line-height:1.5;color:#64748b;text-align:center">Falls der Button nicht funktioniert, '
        . "öffne diesen Link:<br><a href=\"{$href}\" style=\"color:#1d4ed8;word-break:break-all\">{$href}</a></p>";
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

/**
 * Membership type and fees for a 'facts' block.
 */
function membership_group_facts(array $group): array
{
    return [
        'Mitgliedsart' => $group['name'],
        'Beitrag' => $group['amount'] !== null
            ? membership_format_euro($group['amount']) . ' ' . membership_format_interval($group['intervalMonths'])
            : '–',
        'Aufnahmegebühr' => $group['admissionFee'] > 0
            ? membership_format_euro($group['admissionFee']) . ' einmalig'
            : 'entfällt',
    ];
}

function membership_send_confirmation_mail(array $application, string $reference, string $token, DateTimeImmutable $expiresAt): void
{
    $applicant = $application['applicant'];
    $applicantName = "{$applicant['firstName']} {$applicant['lastName']}";
    $link = membership_site_url('/mitglied-werden/bestaetigen?token=' . rawurlencode($token));
    $expires = $expiresAt->format('d.m.Y \u\m H:i \U\h\r');

    // Minors: the mail goes to the legal guardian, addressed formally ("Sie").
    if ($application['isMinor']) {
        $guardian = $application['guardian'];
        $mail = [
            'title' => 'Bitte bestätigen Sie den Mitgliedsantrag',
            'preview' => "Bitte bestätigen Sie den Mitgliedsantrag für {$applicantName} bis zum {$expires}.",
            'blocks' => [
                ['text', "Guten Tag {$guardian['firstName']} {$guardian['lastName']},"],
                ['text', "für {$applicantName} wurde ein Mitgliedsantrag gestellt, und Sie wurden als "
                    . "Erziehungsberechtigte/r angegeben. Bitte bestätigen Sie den Antrag bis zum {$expires} – "
                    . 'erst dann ist er eingereicht.'],
                ['button', 'Antrag bestätigen', $link],
                ['facts', ['Antragsnummer' => $reference, 'Antragsteller/in' => $applicantName]
                    + membership_group_facts($application['group'])],
                ['text', 'Nach der Bestätigung erhalten Sie den Antrag und das SEPA-Lastschriftmandat als PDF. '
                    . 'Über die Aufnahme entscheidet der Vorstand.'],
                ['note', 'Sie kennen diesen Antrag nicht? Dann ignorieren Sie diese E-Mail einfach – ohne '
                    . 'Bestätigung werden alle Angaben automatisch gelöscht.'],
            ],
        ];
    } else {
        $mail = [
            'title' => 'Bitte bestätige deinen Mitgliedsantrag',
            'preview' => "Bitte bestätige deinen Mitgliedsantrag bis zum {$expires}.",
            'blocks' => [
                ['text', "Hallo {$applicant['firstName']},"],
                ['text', "vielen Dank für deinen Mitgliedsantrag! Bitte bestätige ihn bis zum {$expires} – erst "
                    . 'dann ist er eingereicht.'],
                ['button', 'Antrag bestätigen', $link],
                ['facts', ['Antragsnummer' => $reference] + membership_group_facts($application['group'])],
                ['text', 'Nach der Bestätigung erhältst du deinen Antrag und das SEPA-Lastschriftmandat als PDF. '
                    . 'Über die Aufnahme entscheidet der Vorstand.'],
                ['note', 'Du hast keinen Antrag gestellt? Dann ignoriere diese E-Mail einfach – ohne Bestätigung '
                    . 'werden alle Angaben automatisch gelöscht.'],
            ],
        ];
    }

    membership_send_mail(
        membership_confirmation_email($application),
        "Bitte bestätigen: Mitgliedsantrag {$reference}",
        $mail
    );
}
