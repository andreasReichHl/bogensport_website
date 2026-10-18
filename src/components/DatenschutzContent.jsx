import { mailto, siteConfig } from "../utils/siteConfig.js";

/**
 * Text of the Datenschutzerklärung - shared by the /datenschutz page and the dialog in the
 * membership form. headingLevel: level of the section headings (subheadings one below).
 */
export default function DatenschutzContent({ headingLevel = 2 }) {
  const Heading = `h${headingLevel}`;
  const Subheading = `h${headingLevel + 1}`;

  return (
    <div className="space-y-8 font-sans text-base leading-relaxed text-slate-700">
      <section>
        <p className="mb-4">
          Verantwortliche Stelle im Sinne der Datenschutzgesetze, insbesondere der EU-Datenschutzgrundverordnung
          (DSGVO), ist:
        </p>
        <address className="not-italic">
          {siteConfig.name}
          <br />
          {siteConfig.address.street}
          <br />
          {siteConfig.address.postalCode} {siteConfig.address.city}
        </address>
        <p className="mt-4">Vertreten durch den Vorstand gemäß § 26 BGB</p>
        <p className="mt-4">
          E-Mail:{" "}
          <a className="text-[#002f5f] underline-offset-4 hover:underline" href={mailto(siteConfig.emails.board)}>
            {siteConfig.emails.board}
          </a>
        </p>
      </section>

      <section>
        <Heading className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">Ihre Betroffenenrechte</Heading>
        <p className="mb-4">
          Unter den angegebenen Kontaktdaten können Sie jederzeit folgende Rechte ausüben:
        </p>
        <ul className="list-disc space-y-2 pl-5">
          <li>Auskunft über Ihre bei uns gespeicherten Daten und deren Verarbeitung (Art. 15 DSGVO),</li>
          <li>Berichtigung unrichtiger personenbezogener Daten (Art. 16 DSGVO),</li>
          <li>Löschung Ihrer bei uns gespeicherten Daten (Art. 17 DSGVO),</li>
          <li>
            Einschränkung der Datenverarbeitung, sofern wir Ihre Daten aufgrund gesetzlicher Pflichten noch nicht
            löschen dürfen (Art. 18 DSGVO),
          </li>
          <li>Widerspruch gegen die Verarbeitung Ihrer Daten bei uns (Art. 21 DSGVO) und</li>
          <li>
            Datenübertragbarkeit, sofern Sie in die Datenverarbeitung eingewilligt haben oder einen Vertrag mit uns
            abgeschlossen haben (Art. 20 DSGVO).
          </li>
        </ul>
        <p className="mt-4">
          Sofern Sie uns eine Einwilligung erteilt haben, können Sie diese jederzeit mit Wirkung für die Zukunft
          widerrufen.
        </p>
        <p className="mt-4">
          Sie können sich jederzeit mit einer Beschwerde an eine Aufsichtsbehörde wenden, z. B. an die zuständige
          Aufsichtsbehörde des Bundeslandes Ihres Wohnsitzes oder an die für uns als verantwortliche Stelle
          zuständige Behörde.
        </p>
        <p className="mt-4">
          Für Schleswig-Holstein ist das Unabhängige Landeszentrum für Datenschutz Schleswig-Holstein (ULD)
          zuständig. Eine Liste der Aufsichtsbehörden (für den nichtöffentlichen Bereich) mit Anschrift finden Sie
          unter:{" "}
          <a
            className="text-[#002f5f] underline-offset-4 hover:underline"
            href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html"
          >
            bfdi.bund.de
          </a>
        </p>
      </section>

      <section>
        <Heading className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">
          Erfassung allgemeiner Informationen beim Besuch unserer Website
        </Heading>
        <Subheading className="mb-2 font-heading text-lg font-semibold text-slate-950">Art und Zweck der Verarbeitung</Subheading>
        <p>
          Wenn Sie auf unsere Website zugreifen, werden automatisch Informationen allgemeiner Natur erfasst. Diese
          Informationen (Server-Logfiles) können insbesondere die aufgerufene Seite, Datum und Uhrzeit des Zugriffs,
          die übertragene Datenmenge, die IP-Adresse, den verwendeten Browser, das verwendete Betriebssystem, die
          zuvor besuchte Seite sowie den anfragenden Provider umfassen.
        </p>
        <p className="mt-4">Sie werden insbesondere zu folgenden Zwecken verarbeitet:</p>
        <ul className="mt-3 list-disc space-y-2 pl-5">
          <li>Sicherstellung eines problemlosen Verbindungsaufbaus der Website,</li>
          <li>Sicherstellung einer reibungslosen Nutzung unserer Website,</li>
          <li>Auswertung der Systemsicherheit und -stabilität sowie</li>
          <li>zu weiteren administrativen Zwecken.</li>
        </ul>
        <p className="mt-4">
          Wir verwenden Ihre Daten nicht, um Rückschlüsse auf Ihre Person zu ziehen. Informationen dieser Art werden
          von uns gegebenenfalls statistisch ausgewertet, um unseren Internetauftritt und die dahinterstehende
          Technik zu optimieren.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Rechtsgrundlage</Subheading>
        <p>
          Die Verarbeitung erfolgt gemäß Art. 6 Abs. 1 lit. f DSGVO auf Basis unseres berechtigten Interesses an
          der Verbesserung der Stabilität und Funktionalität unserer Website.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Empfänger</Subheading>
        <p>
          Empfänger der Daten ist insbesondere unser Hosting-Anbieter, der die Website technisch bereitstellt und
          Server-Logfiles verarbeitet. Eine Weitergabe an weitere Empfänger erfolgt nur, soweit dies gesetzlich
          erlaubt oder erforderlich ist.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Speicherdauer</Subheading>
        <p>
          Die Daten werden gelöscht, sobald diese für den Zweck der Erhebung nicht mehr erforderlich sind. Dies ist
          für die Daten, die der Bereitstellung der Webseite dienen, grundsätzlich der Fall, wenn die jeweilige
          Sitzung beendet ist.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">
          Bereitstellung vorgeschrieben oder erforderlich
        </Subheading>
        <p>
          Die Bereitstellung der vorgenannten personenbezogenen Daten ist weder gesetzlich noch vertraglich
          vorgeschrieben. Ohne die IP-Adresse ist jedoch der Dienst und die Funktionsfähigkeit unserer Website nicht
          gewährleistet. Zudem können einzelne Dienste und Services nicht verfügbar oder eingeschränkt sein. Aus
          diesem Grund ist ein Widerspruch ausgeschlossen.
        </p>
      </section>

      <section>
        <Heading className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">
          Mitgliederverwaltung mit easyVerein
        </Heading>
        <Subheading className="mb-2 font-heading text-lg font-semibold text-slate-950">Was ist easyVerein?</Subheading>
        <p>
          Für die Verwaltung unserer Mitglieder nutzen wir{" "}
          <a
            className="text-[#002f5f] underline underline-offset-4 hover:no-underline"
            href="https://easyverein.com/"
            target="_blank"
            rel="noopener noreferrer"
          >
            easyVerein
          </a>
          , eine Online-Software für Vereine. Damit führen wir unsere Mitgliederliste, verwalten Mitgliedschaften
          und Beiträge, ziehen Beiträge per SEPA-Lastschrift ein und pflegen unsere Vereinstermine. Anbieter ist die
          SD Software-Design GmbH, Basler Landstraße 8, 79111 Freiburg im Breisgau.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Welche Daten und wozu</Subheading>
        <p>
          Gespeichert werden die Daten, die für die Mitgliedschaft erforderlich sind, insbesondere Name, Anschrift,
          Geburtsdatum, Kontaktdaten, Mitgliedsart, Bankverbindung für den Lastschrifteinzug sowie bei
          Minderjährigen die Angaben der Erziehungsberechtigten. Rechtsgrundlage ist die Durchführung der
          Mitgliedschaft gemäß Art. 6 Abs. 1 lit. b DSGVO.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Auftragsverarbeitung</Subheading>
        <p>
          easyVerein verarbeitet die Daten ausschließlich in unserem Auftrag und nach unseren Weisungen. Mit dem
          Anbieter besteht ein Vertrag zur Auftragsverarbeitung nach Art. 28 DSGVO. Die Server von easyVerein
          stehen nach Angaben des Anbieters ausschließlich in Deutschland.
        </p>
        <p className="mt-4">
          Wenn Sie auf unserer Website einen Termin in Ihren Kalender übernehmen oder unseren Terminkalender
          abonnieren, werden die Kalenderdaten direkt von easyVerein abgerufen; dabei wird Ihre IP-Adresse an
          easyVerein übermittelt.
        </p>
        <p className="mt-4">
          Weitere Informationen:{" "}
          <a
            className="text-[#002f5f] underline underline-offset-4 hover:no-underline"
            href="https://easyverein.com/vereinssoftware/sicherheit-und-datenschutz/"
            target="_blank"
            rel="noopener noreferrer"
          >
            Sicherheit und Datenschutz bei easyVerein
          </a>{" "}
          und{" "}
          <a
            className="text-[#002f5f] underline underline-offset-4 hover:no-underline"
            href="https://software-design.de/agentur/datenschutz?domain=easyverein.com"
            target="_blank"
            rel="noopener noreferrer"
          >
            Datenschutzerklärung des Anbieters
          </a>
          .
        </p>
      </section>

      <section id="online-mitgliedsantrag" className="scroll-mt-28">
        <Heading className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">
          Online-Mitgliedsantrag
        </Heading>
        <Subheading className="mb-2 font-heading text-lg font-semibold text-slate-950">Art und Zweck der Verarbeitung</Subheading>
        <p>
          Wenn Sie über unsere Website die Mitgliedschaft beantragen, verarbeiten wir die Angaben aus dem Formular:
          Name, Anschrift, Geburtsdatum, Geschlecht, E-Mail-Adresse, Telefonnummern (mobil und privat), die gewählte Mitgliedsart,
          die Bankverbindung für das SEPA-Lastschriftmandat sowie bei Minderjährigen Name und Kontaktdaten der
          Erziehungsberechtigten. Zweck ist die Bearbeitung des Antrags und die Durchführung der Mitgliedschaft.
        </p>
        <p className="mt-4">
          Den Antrag müssen Sie über einen Link bestätigen, den wir an die angegebene E-Mail-Adresse senden
          (Double-Opt-in). Als Nachweis, dass der Antrag von Ihnen stammt, speichern wir dabei Zeitpunkt,
          IP-Adresse und Browserkennung des Absendens und der Bestätigung. Nach der Bestätigung erhalten Sie und
          der Vorstand eine Zusammenfassung als PDF. Eine Prüfsumme auf dem PDF belegt, dass die Angaben nicht
          nachträglich verändert wurden.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Rechtsgrundlage</Subheading>
        <p>
          Die Verarbeitung der Antragsdaten erfolgt zur Durchführung vorvertraglicher Maßnahmen und der
          Mitgliedschaft gemäß Art. 6 Abs. 1 lit. b DSGVO. Die Speicherung der Nachweisdaten (Zeitpunkt,
          IP-Adresse, Browserkennung) beruht auf unserem berechtigten Interesse, die Abgabe der Beitrittserklärung
          und die Erteilung des Lastschriftmandats nachweisen zu können (Art. 6 Abs. 1 lit. f DSGVO). Die
          freiwillige Einwilligung zur Veröffentlichung von Fotos beruht auf Art. 6 Abs. 1 lit. a DSGVO und kann
          jederzeit mit Wirkung für die Zukunft widerrufen werden.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Empfänger</Subheading>
        <p>
          Die Angaben erhalten der Vorstand und – nach der Bestätigung – unsere Mitgliederverwaltung easyVerein
          (siehe oben), wo der Vorstand über die Aufnahme entscheidet. Die E-Mails versenden wir über das
          E-Mail-Postfach unseres Vereins bei unserem Hosting-Anbieter.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Speicherdauer</Subheading>
        <p>
          Auf dem Server unserer Website werden die Antragsdaten verschlüsselt gespeichert. Nicht bestätigte
          Anträge können nur innerhalb von 48 Stunden bestätigt werden; danach löschen wir sie, spätestens nach
          3 Tagen. Bestätigte Anträge werden an easyVerein übertragen; die Kopie auf dem
          Server unserer Website löschen wir, sobald der Vorstand über den Antrag entschieden hat, spätestens
          90 Tage nach der Übertragung. In easyVerein speichern wir die Daten für
          die Dauer der Mitgliedschaft und darüber hinaus, soweit gesetzliche Aufbewahrungspflichten bestehen.
          Wird ein Antrag abgelehnt, löschen wir die Daten, soweit keine Aufbewahrungspflichten entgegenstehen.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Bereitstellung vorgeschrieben oder erforderlich</Subheading>
        <p>
          Die mit * gekennzeichneten Angaben sind für die Mitgliedschaft erforderlich; ohne sie können wir den
          Antrag nicht bearbeiten. Die Einwilligung zur Veröffentlichung von Fotos ist freiwillig und hat keinen
          Einfluss auf die Aufnahme.
        </p>
      </section>

      <section>
        <Heading className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">
          Kontaktaufnahme per E-Mail
        </Heading>
        <Subheading className="mb-2 font-heading text-lg font-semibold text-slate-950">Art und Zweck der Verarbeitung</Subheading>
        <p>
          Wenn Sie uns per E-Mail kontaktieren, verarbeiten wir die von Ihnen übermittelten personenbezogenen Daten,
          insbesondere Ihre E-Mail-Adresse, Ihren Namen, den Inhalt Ihrer Nachricht und gegebenenfalls weitere von
          Ihnen angegebene Kontaktdaten. Die Verarbeitung erfolgt zum Zweck der Bearbeitung Ihrer Anfrage und der
          anschließenden Kommunikation mit Ihnen.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Rechtsgrundlage</Subheading>
        <p>
          Rechtsgrundlage ist unser berechtigtes Interesse an der Bearbeitung und Beantwortung Ihrer Anfrage gemäß
          Art. 6 Abs. 1 lit. f DSGVO. Sofern Ihre Anfrage auf den Abschluss oder die Durchführung einer
          Mitgliedschaft, eines Trainingsangebots oder einer sonstigen Vereinbarung gerichtet ist, erfolgt die
          Verarbeitung zusätzlich auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Empfänger</Subheading>
        <p>
          Eine Weitergabe Ihrer Daten an Dritte erfolgt nur, soweit dies zur Bearbeitung Ihrer Anfrage erforderlich
          ist oder eine gesetzliche Verpflichtung besteht.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">Speicherdauer</Subheading>
        <p>
          Wir löschen Ihre Anfrage, sobald diese abschließend bearbeitet wurde und keine gesetzlichen
          Aufbewahrungspflichten entgegenstehen.
        </p>
        <Subheading className="mb-2 mt-5 font-heading text-lg font-semibold text-slate-950">
          Bereitstellung vorgeschrieben oder erforderlich
        </Subheading>
        <p>
          Die Bereitstellung Ihrer personenbezogenen Daten erfolgt freiwillig. Ohne die erforderlichen Angaben
          können wir Ihre Anfrage jedoch nicht oder nur eingeschränkt bearbeiten.
        </p>
      </section>

      <section>
        <Heading className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">
          Cookies, Tracking und externe Dienste
        </Heading>
        <p>
          Unsere Website verwendet derzeit keine Analyse- oder Tracking-Tools, keine Social-Media-Plugins und keine
          extern eingebundenen Schriftarten. Die verwendeten Schriftarten werden lokal vom Server unserer Website
          geladen.
        </p>
        <p className="mt-4">
          Auf den öffentlichen Seiten setzen wir keine Cookies zu Analyse-, Tracking- oder Marketingzwecken ein.
          Ein Cookie-Banner ist hierfür nicht erforderlich.
        </p>
        <p className="mt-4">
          Für den geschützten Mitgliederbereich kann nach dem Login ein technisch notwendiges Authentifizierungs-
          Cookie gesetzt werden. Dieses Cookie dient ausschließlich dazu, angemeldete Mitglieder während der Nutzung
          des Mitgliederbereichs wiederzuerkennen und den Login-Status bereitzustellen. Ohne dieses Cookie kann der
          Mitgliederbereich nicht genutzt werden.
        </p>
        <p className="mt-4">
          Soweit technisch notwendige Daten beim Aufruf oder bei der Nutzung geschützter Bereiche verarbeitet
          werden, erfolgt dies ausschließlich zur Bereitstellung, Sicherheit und Funktionsfähigkeit unserer Website.
        </p>
      </section>

      <section>
        <Heading className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">
          Änderung unserer Datenschutzbestimmungen
        </Heading>
        <p>
          Wir behalten uns vor, diese Datenschutzerklärung anzupassen, damit sie stets den aktuellen rechtlichen
          Anforderungen entspricht oder um Änderungen unserer Leistungen in der Datenschutzerklärung umzusetzen,
          z. B. bei der Einführung neuer Services. Für Ihren erneuten Besuch gilt dann die neue Datenschutzerklärung.
        </p>
      </section>

      <section>
        <Heading className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">Fragen an den Datenschutzbeauftragten</Heading>
        <p>
          Wenn Sie Fragen zum Datenschutz haben, schreiben Sie uns bitte eine E-Mail oder wenden Sie sich direkt an
          die für den Datenschutz verantwortliche Person in unserer Organisation:
        </p>
        <p className="mt-4">Der Vorstand des {siteConfig.name}</p>
      </section>

      <section className="text-sm text-slate-500">
        <Heading className="mb-3 wrap-break-word font-heading text-base font-bold text-slate-700">Hinweis zur Sprache</Heading>
        <p>
          Zur besseren Lesbarkeit wird auf dieser Internetpräsenz das generische Maskulinum verwendet. Alle
          verwendeten Personenbezeichnungen beziehen sich gleichermaßen auf alle Geschlechter.
        </p>
      </section>
    </div>
  );
}
