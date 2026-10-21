<?php

/**
 * PDFs of a confirmed membership application (tFPDF, UTF-8 with the site fonts Inter and Montserrat):
 * the application itself and, as a separate document, the SEPA direct debit mandate. Both carry the
 * same checksum, which covers the whole application.
 *
 * Rendered on demand from the stored (encrypted) data - the PDFs themselves are never stored,
 * so they always show exactly what the signature covers.
 */

require_once __DIR__ . '/signature.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/../vendor/tfpdf/tfpdf.php';

const MEMBERSHIP_PDF_BLUE = [0, 47, 95];
const MEMBERSHIP_PDF_GREEN = [21, 128, 61];
const MEMBERSHIP_PDF_GREY = [100, 116, 139];
const MEMBERSHIP_PDF_LABEL_WIDTH = 55;

class MembershipApplicationPdf extends tFPDF
{
    public string $footerText = '';

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Inter', '', 7.5);
        $this->SetTextColor(...MEMBERSHIP_PDF_GREY);
        $this->Cell(0, 4, $this->footerText, 0, 0, 'L');
        $this->Cell(0, 4, 'Seite ' . $this->PageNo() . ' von {nb}', 0, 0, 'R');
    }

    public function sectionTitle(string $title): void
    {
        $this->Ln(3);
        $this->SetFont('Inter', 'B', 11);
        $this->SetTextColor(...MEMBERSHIP_PDF_BLUE);
        $this->Cell(0, 7, $title, 'B', 1);
        $this->Ln(1.5);
        $this->SetTextColor(0, 0, 0);
    }

    public function row(string $label, string $value): void
    {
        $this->SetFont('Inter', 'B', 9.5);
        $this->Cell(MEMBERSHIP_PDF_LABEL_WIDTH, 5, $label);
        $this->SetFont('Inter', '', 9.5);
        $this->MultiCell(0, 5, $value !== '' ? $value : '–', 0, 'L');
    }

    public function paragraph(string $text, float $size = 9): void
    {
        $this->SetFont('Inter', '', $size);
        $this->MultiCell(0, 4.6, $text, 0, 'L');
    }

    public function checkItem(bool $checked, string $text): void
    {
        $this->SetFont('Inter', 'B', 9.5);
        $this->Cell(8, 4.6, $checked ? '[x]' : '[ ]');
        $this->SetFont('Inter', '', 9);
        $this->MultiCell(0, 4.6, $text, 0, 'L');
        $this->Ln(1.5);
    }
}

function membership_format_datetime(?string $dbTime): string
{
    if ($dbTime === null) {
        return '';
    }

    return (new DateTimeImmutable($dbTime, membership_timezone()))->format('d.m.Y, H:i') . ' Uhr';
}

function membership_format_date(string $isoDate): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);

    return $date ? $date->format('d.m.Y') : $isoDate;
}

function membership_format_iban(string $iban): string
{
    return trim(chunk_split($iban, 4, ' '));
}

/**
 * Starts a document with the club header and the box proving submission and confirmation.
 */
function membership_pdf_start(array $row, string $title): MembershipApplicationPdf
{
    $signature = $row['signature'] ?? membership_compute_signature($row);

    $pdf = new MembershipApplicationPdf('P', 'mm', 'A4');
    $pdf->AddFont('Inter', '', 'Inter-Regular.ttf', true);
    $pdf->AddFont('Inter', 'B', 'Inter-Bold.ttf', true);
    $pdf->AddFont('Montserrat', 'B', 'Montserrat-Bold.ttf', true);
    $pdf->AddFont('DejaVuMono', '', 'DejaVuSansMono.ttf', true);
    $pdf->SetTitle("{$title} {$row['reference']}", true);
    $pdf->SetAuthor(MEMBERSHIP_CLUB_NAME, true);
    $pdf->SetCreator('Online-Mitgliedsantrag ' . MEMBERSHIP_CLUB_NAME, true);
    $pdf->SetMargins(20, 18, 20);
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->AliasNbPages();
    $pdf->footerText = "Antrag {$row['reference']} · Prüfsumme " . substr($signature, 0, 16) . '…';
    $pdf->AddPage();

    // Header
    $pdf->SetFont('Montserrat', 'B', 17);
    $pdf->SetTextColor(...MEMBERSHIP_PDF_BLUE);
    $pdf->Cell(0, 9, MEMBERSHIP_CLUB_NAME, 0, 1);
    $pdf->SetFont('Inter', '', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 7, $title, 0, 1);
    $pdf->Ln(3);

    // Status box: what this document proves.
    $boxTop = $pdf->GetY();
    $pdf->SetLeftMargin(24);
    $pdf->SetY($boxTop + 2.5);
    $pdf->SetFont('Inter', 'B', 11);
    $pdf->SetTextColor(...MEMBERSHIP_PDF_GREEN);
    $pdf->Cell(0, 6, 'EINGEREICHT – per E-Mail bestätigt', 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->row('Antragsnummer', $row['reference']);
    $pdf->row('Abgesendet am', membership_format_datetime($row['submitted_at']));
    $pdf->row('Bestätigt am', membership_format_datetime($row['confirmed_at']));
    $pdf->row('Bestätigt von IP-Adresse', (string)$row['confirmed_ip']);
    $pdf->row('Bestätigt über E-Mail', membership_confirmation_email($row['data']));
    $pdf->SetLeftMargin(20);
    $pdf->Ln(2);
    $pdf->SetDrawColor(...MEMBERSHIP_PDF_GREEN);
    $pdf->SetLineWidth(0.6);
    $pdf->Rect(20, $boxTop, 170, $pdf->GetY() - $boxTop);
    $pdf->SetLineWidth(0.2);
    $pdf->SetDrawColor(...MEMBERSHIP_PDF_GREY);
    $pdf->Ln(3);

    return $pdf;
}

function membership_pdf_finish(MembershipApplicationPdf $pdf, array $row): string
{
    // Keep the section in one piece: start a new page if it would be split.
    if ($pdf->GetY() > 297 - 22 - 34) {
        $pdf->AddPage();
    }

    $pdf->sectionTitle('Prüfsumme');
    $pdf->paragraph('Automatisch erstellt und ohne Unterschrift gültig. Die Prüfsumme (HMAC-SHA256) wird aus allen '
        . 'Angaben des Antrags einschließlich SEPA-Lastschriftmandat und dem Bestätigungsnachweis gebildet und steht '
        . 'gleichlautend auf beiden Dokumenten. Damit kann der Verein prüfen, ob die Angaben unverändert sind.', 8.5);
    $pdf->Ln(2);
    $pdf->SetFont('DejaVuMono', '', 9.5);
    $pdf->MultiCell(0, 5, membership_format_signature($row['signature'] ?? membership_compute_signature($row)), 0, 'L');

    return $pdf->Output('S');
}

function membership_render_application_pdf(array $row): string
{
    $data = $row['data'];
    $applicant = $data['applicant'];
    $guardian = $data['guardian'];
    $group = $data['group'];
    $genders = ['w' => 'weiblich', 'm' => 'männlich', 'd' => 'divers'];

    $pdf = membership_pdf_start($row, 'Online-Mitgliedsantrag (Beitrittserklärung)');
    $pdf->SetTextColor(...MEMBERSHIP_PDF_GREY);
    $pdf->paragraph('Dies ist die Bestätigung über die Einreichung des Antrags, noch keine Aufnahmebestätigung. Über die '
        . 'Aufnahme entscheidet der Vorstand; mit Annahme des Antrags beginnt die vorläufige Mitgliedschaft '
        . '(§ 4 Abs. 5 der Satzung).', 8.5);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->sectionTitle('Mitgliedsart');
    $pdf->row('Mitgliedsart', $group['name']);

    if ($group['amount'] !== null) {
        $pdf->row('Beitrag', membership_format_euro($group['amount']) . ' ' . membership_format_interval($group['intervalMonths']));
    }

    $pdf->row('Aufnahmegebühr', $group['admissionFee'] > 0 ? membership_format_euro($group['admissionFee']) . ' einmalig' : 'entfällt');
    $pdf->row('Zahlungsweise', 'SEPA-Lastschrift (siehe separates SEPA-Lastschriftmandat)');

    $pdf->sectionTitle('Antragsteller/in');
    $pdf->row('Name', trim("{$applicant['salutation']} {$applicant['firstName']} {$applicant['lastName']}"));
    $pdf->row('Geburtsdatum', membership_format_date($applicant['dateOfBirth']));
    $pdf->row('Geschlecht', $genders[$applicant['gender']] ?? $applicant['gender']);
    $pdf->row('Anschrift', "{$applicant['street']}, {$applicant['zip']} {$applicant['city']}");
    $pdf->row('E-Mail', $applicant['email']);
    $pdf->row('Mobil', $applicant['phone']);
    $pdf->row('Telefon privat', $applicant['privatePhone'] ?? '');

    if ($guardian !== null) {
        $pdf->sectionTitle('Erziehungsberechtigte/r');
        $pdf->row('Name', "{$guardian['firstName']} {$guardian['lastName']}");
        $pdf->row('E-Mail', $guardian['email']);
        $pdf->row('Telefon', $guardian['phone']);
        $pdf->row('Verwaltung', $guardian['managedByText']);
    }

    $pdf->sectionTitle('Erklärungen');

    foreach ($data['consents'] as $key => $consent) {
        if ($key !== 'sepa') {
            $pdf->checkItem($consent['accepted'], $consent['text']);
        }
    }

    $pdf->SetTextColor(...MEMBERSHIP_PDF_GREY);
    $pdf->paragraph(
        'Zugrunde liegende Fassungen: Satzung vom ' . membership_format_date($data['satzungVersion'])
        . ', Erklärungstexte Version ' . $data['textsVersion'] . '.',
        8.5
    );
    $pdf->SetTextColor(0, 0, 0);

    return membership_pdf_finish($pdf, $row);
}

function membership_render_sepa_pdf(array $row): string
{
    $data = $row['data'];
    $applicant = $data['applicant'];
    $bank = $data['bank'];

    $pdf = membership_pdf_start($row, 'SEPA-Lastschriftmandat');

    $pdf->sectionTitle('Zahlungsempfänger');
    $pdf->row('Name', MEMBERSHIP_CLUB_NAME);
    $pdf->row('Gläubiger-ID', membership_config()['creditor_id'] ?: '[Gläubiger-ID folgt]');
    $pdf->row('Mandatsreferenz', 'wird nach der Aufnahme mitgeteilt');
    $pdf->row('Zahlungsart', 'Wiederkehrende Zahlungen');

    $pdf->sectionTitle('Zahlungspflichtige/r');
    $pdf->row('Kontoinhaber/in', $bank['accountHolder']);
    $pdf->row('IBAN', membership_format_iban($bank['iban']));
    $pdf->row('BIC', $bank['bic']);
    $pdf->row('Für die Mitgliedschaft von', "{$applicant['firstName']} {$applicant['lastName']}, "
        . "{$applicant['street']}, {$applicant['zip']} {$applicant['city']}");

    $pdf->sectionTitle('Mandat');
    $pdf->paragraph($data['consents']['sepa']['text']);
    $pdf->Ln(1.5);
    $pdf->row('Erteilt am', membership_format_datetime($row['confirmed_at']) . ' (durch Bestätigung des Antrags)');

    return membership_pdf_finish($pdf, $row);
}

/**
 * Both documents as mail attachments: application first, then the SEPA mandate.
 *
 * @return list<array{content: string, filename: string, type: string}>
 */
function membership_render_pdfs(array $row): array
{
    return [
        [
            'content' => membership_render_application_pdf($row),
            'filename' => membership_application_pdf_filename($row['reference']),
            'type' => 'application/pdf',
        ],
        [
            'content' => membership_render_sepa_pdf($row),
            'filename' => membership_sepa_pdf_filename($row['reference']),
            'type' => 'application/pdf',
        ],
    ];
}

function membership_application_pdf_filename(string $reference): string
{
    return "Mitgliedsantrag-{$reference}.pdf";
}

function membership_sepa_pdf_filename(string $reference): string
{
    return "SEPA-Lastschriftmandat-{$reference}.pdf";
}
