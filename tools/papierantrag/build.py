"""
Builds the paper membership form public/downloads/mitgliedsantrag.pdf: fillable on screen
(AcroForm fields) and printable for filling in by hand.

Texts and membership types come from the online application (data.php), so both forms stay in
sync. Run after changing texts.php, the fees or the easyVerein groups:

    python3 -m venv /tmp/pdf-venv && /tmp/pdf-venv/bin/pip install reportlab
    /tmp/pdf-venv/bin/python tools/papierantrag/build.py
"""

import json
import subprocess
from datetime import date
from pathlib import Path

from reportlab.lib.colors import HexColor, white
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen.canvas import Canvas
from reportlab.platypus import Paragraph

ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / 'public/downloads/mitgliedsantrag.pdf'
FONTS = ROOT / 'private/vendor/tfpdf/font/unifont'
LOGO = Path(__file__).with_name('logo.png')

WEBSITE = 'www.badoldesloer-bogensport.de'
EMAIL = 'vorstand@badoldesloer-bogensport.de'
# As in the Impressum (src/pages/Impressum.jsx).
FOOTER = [
    'Eingetragen im Vereinsregister des Amtsgerichts Lübeck unter VR 3189 HL',
    'Vorstand: 1. Vorsitzender Andreas Reich, 2. Vorsitzende Svenja Petschelies, Kassenwart Rolf Pollnau',
    'Bankverbindung: Sparkasse Holstein · IBAN DE91 2135 2240 0135 8459 49 · BIC NOLADE21HOL',
]
# From the previous paper form; not part of the online application.
CONTACT_SHARING = (
    'Freiwillig: Ich willige ein, dass meine Kontaktdaten zur einfacheren Kommunikation an die anderen '
    'Mitglieder weitergegeben werden. Die Kontaktdaten anderer Mitglieder behandle ich vertraulich und gebe '
    'sie nicht ohne deren Einwilligung an Dritte weiter.'
)
NOTES = (
    'Das Startgeld für Meisterschaften und Ligawettkämpfe des DSB (Deutscher Schützenbund) trägt der Verein, '
    'das Startgeld für sonstige Turniere der Schütze selbst. Die Teilnahme an Meisterschaften, Turnieren und '
    'anderen Wettkämpfen sowie die Benutzung der Schießanlagen ist nur nach rechtzeitiger und vollständiger '
    'Zahlung der Mitgliedsbeiträge möglich.'
)

BLUE = HexColor('#002f5f')
TEXT = HexColor('#0f172a')
MUTED = HexColor('#64748b')
LINE = HexColor('#cbd5e1')
FIELD = HexColor('#f8fafc')

PAGE_WIDTH, PAGE_HEIGHT = A4
MARGIN = 50
CONTENT_WIDTH = PAGE_WIDTH - 2 * MARGIN
FOOTER_TOP = 64
FIELD_HEIGHT = 19
LABEL_GAP = 10
GUTTER = 10

pdfmetrics.registerFont(TTFont('Inter', FONTS / 'Inter-Regular.ttf'))
pdfmetrics.registerFont(TTFont('Inter-Bold', FONTS / 'Inter-Bold.ttf'))
pdfmetrics.registerFont(TTFont('Montserrat-Bold', FONTS / 'Montserrat-Bold.ttf'))

BODY = ParagraphStyle('body', fontName='Inter', fontSize=9, leading=12.5, textColor=TEXT)
SMALL = ParagraphStyle('small', parent=BODY, fontSize=8, leading=11, textColor=MUTED)


def euro(amount):
    return f'{amount:,.2f} €'.replace(',', 'X').replace('.', ',').replace('X', '.')


class Form:
    def __init__(self, data):
        self.data = data
        self.canvas = Canvas(str(OUTPUT), pagesize=A4)
        self.canvas.setTitle('Mitgliedsantrag ' + data['club'])
        self.canvas.setAuthor(data['club'])
        self.canvas.setSubject('Beitrittserklärung und SEPA-Lastschriftmandat')
        self.page = 0
        self.y = 0

    # Page frame

    def new_page(self, title):
        if self.page:
            self.footer()
            self.canvas.showPage()

        self.page += 1
        c = self.canvas
        logo = ImageReader(str(LOGO))
        logo_width, logo_height = logo.getSize()
        height = 34
        c.drawImage(logo, MARGIN, PAGE_HEIGHT - MARGIN - height, height * logo_width / logo_height, height,
                    mask='auto')
        c.setFont('Inter', 7.5)
        c.setFillColor(MUTED)
        top = PAGE_HEIGHT - MARGIN - 6

        for index, line in enumerate([self.data['address'], WEBSITE, EMAIL]):
            c.drawRightString(PAGE_WIDTH - MARGIN, top - index * 10, line)

        c.setStrokeColor(BLUE)
        c.setLineWidth(1.2)
        c.line(MARGIN, PAGE_HEIGHT - MARGIN - 46, PAGE_WIDTH - MARGIN, PAGE_HEIGHT - MARGIN - 46)
        self.y = PAGE_HEIGHT - MARGIN - 76
        c.setFont('Montserrat-Bold', 17)
        c.setFillColor(BLUE)
        c.drawString(MARGIN, self.y, title)
        self.y -= 14

    def footer(self):
        c = self.canvas
        c.setStrokeColor(LINE)
        c.setLineWidth(0.5)
        c.line(MARGIN, FOOTER_TOP, PAGE_WIDTH - MARGIN, FOOTER_TOP)
        c.setFont('Inter', 6.5)
        c.setFillColor(MUTED)

        for index, line in enumerate(FOOTER):
            c.drawString(MARGIN, FOOTER_TOP - 11 - index * 9, line)

        c.drawRightString(PAGE_WIDTH - MARGIN, FOOTER_TOP - 11, f'Seite {self.page} · Stand {date.today():%m/%Y}')

    def need(self, height, title):
        """Starts a new page if the next block does not fit above the footer."""
        if self.y - height < FOOTER_TOP + 14:
            self.new_page(title)

    # Building blocks

    def section(self, title):
        self.y -= 20
        c = self.canvas
        c.setFont('Montserrat-Bold', 10.5)
        c.setFillColor(BLUE)
        c.drawString(MARGIN, self.y, title)
        self.y -= 5
        c.setStrokeColor(LINE)
        c.setLineWidth(0.5)
        c.line(MARGIN, self.y, PAGE_WIDTH - MARGIN, self.y)
        self.y -= 8

    def paragraph(self, text, style=BODY, x=MARGIN, width=CONTENT_WIDTH):
        paragraph = Paragraph(text, style)
        _, height = paragraph.wrap(width, PAGE_HEIGHT)
        paragraph.drawOn(self.canvas, x, self.y - height)
        self.y -= height
        return height

    def fields(self, row):
        """One row of labelled text fields: [(name, label, share of the width, extra options), ...]."""
        free = CONTENT_WIDTH - GUTTER * (len(row) - 1)
        x = MARGIN
        c = self.canvas

        for name, label, share, *options in row:
            width = free * share
            c.setFont('Inter', 7)
            c.setFillColor(MUTED)
            c.drawString(x, self.y - 7, label)
            c.acroForm.textfield(
                name=name, tooltip=label, x=x, y=self.y - LABEL_GAP - FIELD_HEIGHT, width=width,
                height=FIELD_HEIGHT, fontName='Helvetica', fontSize=10, borderColor=LINE, fillColor=FIELD,
                textColor=TEXT, borderWidth=0.6, forceBorder=True, **(options[0] if options else {}),
            )
            x += width + GUTTER

        self.y -= LABEL_GAP + FIELD_HEIGHT + 8

    def choice(self, name, label, x, y, value=None):
        """A box to tick: a radio button if value is given (one of a group), else a checkbox."""
        c = self.canvas
        size = 10
        options = dict(x=x, y=y, size=size, buttonStyle='check', borderColor=BLUE, fillColor=white,
                       textColor=BLUE, borderWidth=0.8, forceBorder=True, tooltip=label)

        if value is None:
            c.acroForm.checkbox(name=name, **options)
        else:
            c.acroForm.radio(name=name, value=value, selected=False, shape='square', **options)

    def checked_text(self, name, text, value=None, style=BODY):
        """Checkbox (or radio option) in front of a paragraph."""
        paragraph = Paragraph(text, style)
        _, height = paragraph.wrap(CONTENT_WIDTH - 18, PAGE_HEIGHT)
        self.need(height + 6, self.current_title)
        self.choice(name, text[:60], MARGIN, self.y - 11, value)
        paragraph.drawOn(self.canvas, MARGIN + 18, self.y - height)
        self.y -= height + 6

    def signature(self, suffix, label):
        """Ort/Datum field and a line to sign on, side by side."""
        c = self.canvas
        place_width = CONTENT_WIDTH * 0.38
        c.setFont('Inter', 7)
        c.setFillColor(MUTED)
        c.drawString(MARGIN, self.y - 7, 'Ort, Datum')
        c.acroForm.textfield(
            name=f'ort_datum_{suffix}', tooltip=f'Ort, Datum ({label})', x=MARGIN, y=self.y - LABEL_GAP - FIELD_HEIGHT,
            width=place_width, height=FIELD_HEIGHT, fontName='Helvetica', fontSize=10, borderColor=LINE,
            fillColor=FIELD, textColor=TEXT, borderWidth=0.6, forceBorder=True,
        )
        line_x = MARGIN + place_width + 20
        line_y = self.y - LABEL_GAP - FIELD_HEIGHT
        c.setStrokeColor(TEXT)
        c.setLineWidth(0.6)
        c.line(line_x, line_y, PAGE_WIDTH - MARGIN, line_y)
        c.drawString(line_x, line_y - 10, label)
        self.y -= LABEL_GAP + FIELD_HEIGHT + 24

    # Pages

    def application(self):
        texts = self.data['texts']
        club = self.data['club']
        self.current_title = 'Beitrittserklärung'
        self.new_page(self.current_title)
        self.paragraph(f'Hiermit beantrage ich die Mitgliedschaft im {club} Das Formular kann am Bildschirm '
                       'oder nach dem Ausdrucken von Hand ausgefüllt werden.', SMALL)

        self.section('Angaben zur Person')
        self.fields([('vorname', 'Vorname', 0.5), ('nachname', 'Nachname', 0.5)])
        top = self.y
        self.fields([('geburtsdatum', 'Geburtsdatum (TT.MM.JJJJ)', 0.3, {'maxlen': 10})])
        # Gender next to the date of birth (needed for the reports to the sports associations).
        c = self.canvas
        gender_x = MARGIN + (CONTENT_WIDTH - GUTTER) * 0.3 + GUTTER
        c.setFont('Inter', 7)
        c.setFillColor(MUTED)
        c.drawString(gender_x, top - 7, 'Geschlecht (für die Meldung an die Sportverbände)')
        c.setFont('Inter', 9)
        c.setFillColor(TEXT)

        for index, (value, label) in enumerate([('m', 'männlich'), ('w', 'weiblich'), ('d', 'divers')]):
            x = gender_x + index * 80
            self.choice('geschlecht', label, x, top - LABEL_GAP - 14.5, value)
            c.drawString(x + 15, top - LABEL_GAP - 13, label)

        self.fields([('strasse', 'Straße und Hausnummer', 1)])
        self.fields([('plz', 'PLZ', 0.2, {'maxlen': 5}), ('ort', 'Ort', 0.8)])
        self.fields([('email', 'E-Mail', 0.5), ('telefon_mobil', 'Telefon mobil', 0.25),
                     ('telefon_privat', 'Telefon privat', 0.25)])

        self.section('Mitgliedsart')
        columns = [MARGIN + 18, MARGIN + CONTENT_WIDTH * 0.55, MARGIN + CONTENT_WIDTH * 0.78]
        c.setFont('Inter', 7)
        c.setFillColor(MUTED)

        for x, heading in zip(columns, ['Bitte eine Mitgliedsart ankreuzen', 'Beitrag', 'Aufnahmegebühr']):
            c.drawString(x, self.y - 7, heading)

        self.y -= 13

        for group in self.data['groups']:
            interval = {1: 'monatlich', 3: 'vierteljährlich', 6: 'halbjährlich', 12: 'jährlich'}.get(
                group['intervalMonths'], f"alle {group['intervalMonths']} Monate")
            fee = f"{euro(group['admissionFee'])} einmalig" if group['admissionFee'] > 0 else 'entfällt'
            self.choice('mitgliedsart', group['name'], MARGIN, self.y - 12, str(group['id']))
            c.setFillColor(TEXT)
            c.setFont('Inter-Bold', 9)
            c.drawString(columns[0], self.y - 10.5, group['name'])
            c.setFont('Inter', 9)
            c.drawString(columns[1], self.y - 10.5, f"{euro(group['amount'])} {interval}")
            c.drawString(columns[2], self.y - 10.5, fee)
            self.y -= 18

        self.y -= 2
        self.paragraph('Die Beiträge werden per SEPA-Lastschrift eingezogen (Lastschriftmandat auf der letzten Seite).',
                       SMALL)

        self.section('Bei Minderjährigen: gesetzliche/r Vertreter/in')
        self.fields([('vertreter_name', 'Vorname und Nachname', 0.4), ('vertreter_email', 'E-Mail', 0.35),
                     ('vertreter_telefon', 'Telefon', 0.25)])
        self.checked_text('verwaltung', texts['verwaltung_eltern'], 'eltern')
        self.checked_text('verwaltung', texts['verwaltung_kind'], 'kind')
        self.checked_text('erziehungsberechtigt', texts['erziehungsberechtigt'])

        # Declarations, notes and signatures together on the second page.
        self.new_page(self.current_title)
        self.section('Erklärungen')
        self.checked_text('satzung', f"{texts['satzung']} Die Satzung steht unter {WEBSITE}/satzung.")
        self.checked_text('beitrag', texts['beitrag'])
        self.checked_text('datenschutz', f"{texts['datenschutz']} Die Datenschutzhinweise stehen unter "
                                         f"{WEBSITE}/datenschutz.")
        self.checked_text('fotos', texts['fotos'])
        self.checked_text('kontaktdaten', CONTACT_SHARING)

        self.need(60, self.current_title)
        self.section('Hinweise')
        self.paragraph(NOTES, SMALL)

        self.need(130, self.current_title)
        self.section('Unterschrift')
        self.signature('antrag', 'Unterschrift Antragsteller/in')
        self.signature('vertreter', 'Unterschrift gesetzliche/r Vertreter/in (bei Minderjährigen)')

    def sepa(self):
        texts = self.data['texts']
        self.current_title = 'SEPA-Lastschriftmandat'
        self.new_page(self.current_title)

        self.section('Zahlungsempfänger')
        c = self.canvas
        rows = [
            ('Name', self.data['club']),
            ('Anschrift', self.data['address']),
            ('Gläubiger-ID', self.data['creditorId']),
            ('Mandatsreferenz', 'wird nach der Aufnahme mitgeteilt'),
            ('Zahlungsart', 'Wiederkehrende Zahlungen'),
        ]

        for label, value in rows:
            c.setFont('Inter', 8)
            c.setFillColor(MUTED)
            c.drawString(MARGIN, self.y - 9, label)
            c.setFont('Inter-Bold', 9)
            c.setFillColor(TEXT)
            c.drawString(MARGIN + 110, self.y - 9, value)
            self.y -= 14

        self.section('Mandat')
        self.paragraph(texts['sepa'])
        self.y -= 4

        self.section('Zahlungspflichtige/r (Kontoinhaber/in)')
        self.fields([('kontoinhaber', 'Vorname und Nachname der Kontoinhaberin / des Kontoinhabers', 1)])
        self.fields([('konto_strasse', 'Straße und Hausnummer', 0.5), ('konto_plz', 'PLZ', 0.15, {'maxlen': 5}),
                     ('konto_ort', 'Ort', 0.35)])
        self.fields([('iban', 'IBAN', 0.62, {'maxlen': 34}), ('bic', 'BIC', 0.38, {'maxlen': 11})])
        self.fields([('kreditinstitut', 'Kreditinstitut', 1)])
        self.fields([('mitglied', 'Für die Mitgliedschaft von (Vorname und Nachname)', 1)])

        self.y -= 6
        self.signature('sepa', 'Unterschrift Kontoinhaber/in')

    def save(self):
        self.footer()
        self.canvas.save()


def main():
    data = json.loads(subprocess.run(['php', str(Path(__file__).with_name('data.php'))], check=True,
                                     capture_output=True, text=True).stdout)
    form = Form(data)
    form.application()
    form.sepa()
    form.save()
    print(f'{OUTPUT.relative_to(ROOT)} geschrieben ({form.page} Seiten)')


if __name__ == '__main__':
    main()
