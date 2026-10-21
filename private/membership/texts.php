<?php

/**
 * Wording of all declarations the applicant has to agree to.
 *
 * Single source of truth: the form fetches these texts from beitritt-formular.php, and the
 * exact same texts (with their version) are stored with each application and printed on the
 * PDF. Any change to a text MUST bump MEMBERSHIP_TEXTS_VERSION, so it stays provable which
 * wording an applicant agreed to.
 */

require_once __DIR__ . '/config.php';

const MEMBERSHIP_TEXTS_VERSION = '2026-10-18-7';
const MEMBERSHIP_CLUB_NAME = 'Bad Oldesloer Bogensport e.V.';
// Postal address as in the Impressum (src/utils/siteConfig.js), shown in the mail footer.
const MEMBERSHIP_CLUB_ADDRESS = 'Postfach 11 02, 23831 Bad Oldesloe';

function membership_texts(): array
{
    $creditorId = membership_config()['creditor_id'] ?: '[Gläubiger-ID folgt]';

    return [
        'version' => MEMBERSHIP_TEXTS_VERSION,
        'satzung' => 'Ich habe die Satzung des ' . MEMBERSHIP_CLUB_NAME . ' gelesen und erkenne sie mit meinem '
            . 'Beitritt an (§ 4 Abs. 6 der Satzung).',
        'beitrag' => 'Mir ist bekannt, dass mit Beginn der vorläufigen Mitgliedschaft die Beitragspflicht beginnt '
            . '(§ 4 Abs. 7 der Satzung). Ferner ist die festgesetzte Aufnahmegebühr zu zahlen. Die Höhe von Beitrag '
            . 'und Aufnahmegebühr richtet sich nach der gewählten Mitgliedsart.',
        'datenschutz' => 'Ich habe die Datenschutzhinweise zur Kenntnis genommen. Meine Angaben werden zur Durchführung '
            . 'der Mitgliedschaft verarbeitet und in der Mitgliederverwaltung des Vereins gespeichert.',
        'sepa' => 'Ich ermächtige den ' . MEMBERSHIP_CLUB_NAME . ' (Gläubiger-Identifikationsnummer ' . $creditorId
            . '), Zahlungen von meinem Konto mittels Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, '
            . 'die vom ' . MEMBERSHIP_CLUB_NAME . ' auf mein Konto gezogenen Lastschriften einzulösen. '
            . 'Hinweis: Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des '
            . 'belasteten Betrages verlangen. Es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen. '
            . 'Die Mandatsreferenz wird mir gesondert mitgeteilt.',
        'erziehungsberechtigt' => 'Ich bin gesetzliche/r Vertreter/in des minderjährigen Antragstellers. Ich stimme dem '
            . 'Beitritt zu und handle als allein Sorgeberechtigte/r oder im Einverständnis mit dem anderen '
            . 'sorgeberechtigten Elternteil. Mir ist bekannt, dass ich für die Beiträge hafte.',
        // How a minor's membership is managed in easyVerein (one of the two is chosen).
        'verwaltung_eltern' => 'Ich bin bereits Mitglied oder habe meinen eigenen Mitgliedsantrag bereits gestellt. '
            . 'Die Mitgliedschaft meines Kindes/Jugendlichen soll zusammen mit meiner Mitgliedschaft verwaltet werden (unter '
            . 'meiner angegebenen E-Mail-Adresse).',
        'verwaltung_kind' => 'Mein Kind/Jugendliche/r wird allein angemeldet und erhält eine eigenständige Mitgliedschaft.',
        'fotos' => 'Freiwillig: Ich bin damit einverstanden, dass Fotos von mir bzw. meinem Kind/Jugendlichen, die bei '
            . 'Vereinsveranstaltungen entstehen, auf der Website und in sozialen Medien des Vereins veröffentlicht '
            . 'werden. Diese Einwilligung kann ich jederzeit für die Zukunft widerrufen.',
    ];
}
