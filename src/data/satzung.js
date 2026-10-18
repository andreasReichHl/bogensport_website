// Wortlaut der Satzung vom 02.10.2009 (Quelle: public/downloads/satzung.pdf).
// Änderungen nur nach Beschluss der Mitgliederversammlung (§ 15) übernehmen.
//
// Aufbau je Paragraph:
//   intro:   Einleitungssatz vor der Aufzählung
//   items:   nummerierte Absätze; entweder String oder { text, sub, after }
//            sub:   Unterpunkte a), b), c) …
//            after: Folgesätze, die zum Absatz gehören (eigene Zeile)
//   text:    Fließtext ohne Nummerierung
//   outro:   Text nach der Aufzählung

export const satzungMeta = {
  title: "Satzung des Bad Oldesloer Bogensport (BOBS) e.V.",
  place: "Bad Oldesloe",
  date: "02.10.2009",
  version: "2009-10-02",
};

export const satzung = [
  {
    number: 1,
    title: "Name, Sitz und Geschäftsjahr",
    items: [
      "Der Verein führt den Namen Bad Oldesloer Bogensport (BOBS).",
      "Er hat seinen Sitz in Bad Oldesloe und ist in das Vereinsregister des Amtsgerichts Lübeck eingetragen.",
      "Nach Eintragung in das Vereinsregister trägt der Verein den Zusatz „e.V.“ – Bad Oldesloer Bogensport e.V. (BOBS)",
      "Das Geschäftsjahr ist das Kalenderjahr.",
    ],
  },
  {
    number: 2,
    title: "Zweck des Vereins",
    items: [
      "Förderung und Ausübung des Bogensport als Freizeit + Wettkampfsport.",
      "Förderung und Ausübung der sportlichen Jugendarbeit.",
      "Teilnahme am Meisterschaftsprogramm des Deutschen Schützenbundes.",
      "Teilnahme und Durchführung von Wettkämpfen.",
    ],
  },
  {
    number: 3,
    title: "Gemeinnützigkeit – Steuerbegünstigung",
    items: [
      "Der Verein ist politisch, weltanschaulich und konfessionell neutral.",
      "Der Verein verfolgt ausschließlich und unmittelbar gemeinnützige Zwecke.",
      "Der Verein ist selbstlos tätig. Seinem ideellen Zweck ist die zur Erreichung des Vereinszwecks erforderliche eigenwirtschaftliche Betätigung untergeordnet.",
      "Haushaltsmittel aus dem Verein dürfen nur für satzungsgemäße Zwecke verwendet werden. Die Mitglieder erhalten keine Zuwendungen aus seinen Mitteln. Niemand darf durch Ausgaben, die dem Zwecke des Vereins fremd sind oder durch eine unverhältnismäßig hohe Verfügung begünstigt werden.",
      "Sämtliche Mitglieder der Organe des Vereins üben ihre Tätigkeit ehrenamtlich aus.",
      "Jeder – die Satzung ändernde – Beschluss mit hausrechtlichem Inhalt muss vor Einreichung beim Registeramt dem zuständigen Finanzamt vorgelegt werden.",
    ],
  },
  {
    number: 4,
    title: "Mitgliedschaft",
    items: [
      "Mitglied kann jede natürliche voll geschäftsfähige Person werden. Einen Aufnahmeanspruch gibt es nicht.",
      {
        text: "Der Verein besteht aus ordentlichen Mitgliedern, jugendlichen Mitgliedern und passiven Mitgliedern.",
        sub: [
          "Ordentliche Mitglieder sind Mitglieder, die den Bogensport aktiv betreiben und die zu Beginn des laufenden Geschäftsjahres das 18. Lebensjahr vollendet haben.",
          "Jugendliche Mitglieder sind Mitglieder, die den Bogensport aktiv betreiben und die zu Beginn des laufenden Geschäftsjahres das 8. Lebensjahr, aber noch nicht das 18. Lebensjahr vollendet haben.",
          "Passive Mitglieder sind Mitglieder die nicht aktiv am Trainings- und Wettkampfbetrieb teilnehmen, aber die Interessen des Vereins in besonderem Maße fördern. Auch für passive Mitglieder besteht Beitragspflicht.",
        ],
      },
      "Die Beitrittserklärung muss schriftlich erfolgen.",
      "Die Aufnahme wird durch Aushändigung eines Mitgliedsausweises des Bad Oldesloer Bogensports bestätigt.",
      "Mit Annahme des Aufnahmeantrages erfolgt die vorläufige Mitgliedschaft. Nach 3 Monaten erfolgt die endgültige Aufnahme durch den Vorstand.",
      "Durch seine Beitrittserklärung erkennt das Mitglied die Satzung an.",
      "Mit Beginn der vorläufigen Mitgliedschaft beginnt die Beitragspflicht gemäß der Beitragsordnung. Ferner ist die festgesetzte Aufnahmegebühr zu zahlen.",
    ],
  },
  {
    number: 5,
    title: "Rechte der Mitglieder",
    intro: "Die Mitglieder sind berechtigt,",
    items: [
      "durch Ausübung des Stimmrechtes an den Beratungen und Beschlussfassungen der Mitgliedsversammlungen teilzunehmen,",
      "an den vom Verein durchgeführten Veranstaltungen und Wettkämpfen teilzunehmen,",
      "den Bogensport aktiv auszuüben.",
    ],
  },
  {
    number: 6,
    title: "Pflichten der Mitglieder",
    intro: "Die Mitglieder sind verpflichtet,",
    items: [
      "die Satzung des Vereins anzuerkennen, die Beschlüsse der Mitgliederversammlung zu befolgen,",
      "nicht gegen die Interessen des Vereins zu handeln,",
      "sich sportlich fair und kameradschaftlich zu verhalten,",
      "die durch den Beschluss der Mitgliederversammlung festgelegten Beiträge und die Aufnahmegebühr zu entrichten (gemäß der Beitragsordnung),",
      "an den sportlichen Veranstaltungen nach Kräften mitzuwirken,",
      "jeden Anschriftenwechsel dem Vorstand mitzuteilen,",
      "jegliches Vereinseigentum pfleglich zu behandeln.",
      "Bei jugendlichen Sportlern verpflichten sich die Erziehungsberechtigten, die Betreuung des Jugendlichen in auswärtigen Wettkämpfen zu übernehmen und nicht ausschließlich dem Verein zu überlassen.",
    ],
  },
  {
    number: 7,
    title: "Beendigung der Mitgliedschaft",
    items: [
      "Die Mitgliedschaft endet durch Tod, Austritt, Ausschluss oder Auflösung des Vereins.",
      "Die Mitgliedschaft kann unter Einhaltung einer Frist von 8 Wochen zum Ende eines Kalenderjahres gekündigt werden. Die Kündigung ist dem Vorstand schriftlich einzureichen.",
      "Der Ausschluss eines Mitgliedes kann erfolgen, wenn es durch schuldhaftes Verhalten gegen die in § 6 aufgeführten Pflichten verstößt.",
      "Über den Ausschluss entscheidet der Vorstand unter Berücksichtigung einer 3 wöchigen Einspruchsfrist. Nach Ablauf dieser Frist ist der Ausschluss wirksam.",
      "Mit Beendigung der Mitgliedschaft erlöschen alle Ansprüche aus dem Mitgliedschaftsverhältnis, unbeschadet des Anspruchs des Vereins auf rückständige Beitragsforderungen. Eine Rückgewähr von Beiträgen, Sacheinlagen oder Spenden ist ausgeschlossen. Vereinseigentum ist unverzüglich dem Vorstand zurückzugeben.",
    ],
  },
  {
    number: 8,
    title: "Beiträge und Aufnahmegebühr",
    items: [
      {
        text: "Der Verein erhebt Beiträge in folgender Form:",
        sub: ["Aufnahmegebühr", "Jahresbeitrag"],
        after: [
          "Die Beiträge sind fällig am 01.01. des für sie geltenden Geschäftsjahres. Bei Abbuchung ist jedes Mitglied verpflichtet, für ausreichende Deckung des Abbuchungskontos zu sorgen. Entstehende Kosten durch Nichtdeckung trägt das Mitglied.",
        ],
      },
      "Die Höhe der o.g. Beiträge und die Form der Zahlung werden von der Mitgliederversammlung bestimmt.",
      "Neu eingetretene Mitglieder sind erst dann berechtigt, an den Veranstaltungen des Vereins teilzunehmen, wenn sie ihre Aufnahmegebühr und die fälligen Beiträge vollständig entrichtet haben.",
      "Der Vorstand kann in begründeten Einzelfällen eine Verminderung oder Befreiung von Beiträgen aus Absatz 1 aus sozialen Gründen beschließen.",
    ],
  },
  {
    number: 9,
    title: "Organe des Vereins",
    items: [
      "Der geschäftsführende Vorstand",
      "Die Mitgliederversammlung",
    ],
    outro: "Sämtliche Organe des Vereins üben ihre Tätigkeit ehrenamtlich aus. Es darf an kein Mitglied ein Gewinnanteil, Zuwendungen, unverhältnismäßig hohe Vergütungen oder ähnliches gezahlt werden.",
  },
  {
    number: 10,
    title: "Vorstand",
    items: [
      {
        text: "Der Vorstand (§ 26 BGB) besteht aus",
        sub: ["dem 1. Vorsitzenden,", "dem 2. Vorsitzenden,", "dem Kassenwart."],
      },
      "Der Verein wird gerichtlich und außergerichtlich jeweils durch zwei Mitglieder des Vorstandes vertreten. Mindestens eines dieser Mitglieder muss der 1. Vorsitzende oder der stellvertretende Vorsitzende sein.",
      "Die Vorstandsämter des 1. Vorsitzenden und des 2. Vorsitzenden können nicht gleichzeitig das Amt des Schatzmeisters in sich vereinen.",
      "Dem Vorstand obliegt die Verwaltung des Vereinsvermögens. Er ist im Übrigen für alle Vereinsbelange zuständig, die nicht in die Zuständigkeit anderer Vereinsorgane fallen.",
      "Der Kassenwart verwaltet die Vereinskasse und führt Buch über die Einnahmen und Ausgaben. Zahlungsanweisungen bedürfen der Unterschrift des Kassenwartes. In dringenden Fällen (wesentliche Vereinsverpflichtungen) kann bei längerer Verhinderung des Kassenwarts der 1. oder der 2. Vorsitzende Zahlungsanweisungen unterschreiben.",
      "Der Vorstand wird von der Mitgliederversammlung auf die Dauer von 2 Jahren gewählt, eine Wiederwahl ist möglich. Er bleibt bis zur Bestellung eines neuen Vorstandes im Amt.",
      {
        text: "Der Vorstand fasst seine Beschlüsse in Vorstandssitzungen, die vom 1. Vorsitzenden oder vom stellvertretenden Vorsitzenden einberufen und geleitet werden.",
        after: ["Der Vorstand ist beschlussfähig, wenn mindestens 2 Vorstandsmitglieder anwesend sind."],
      },
      {
        text: "Der Vorstand fasst alle Beschlüsse mit einfacher Mehrheit der abgegebenen Stimmen.",
        after: ["Bei Stimmgleichheit entscheidet der Sitzungsleiter."],
      },
      "Über die Vorstandssitzungen wird ein Protokoll geführt, das vom Schriftführer und vom Sitzungsleiter unterzeichnet wird.",
    ],
  },
  {
    number: 11,
    title: "Die Mitgliederversammlung",
    items: [
      "Die ordentliche Mitgliederversammlung (Jahreshauptversammlung) ist einmal jährlich, im 1. Kalendervierteljahr einzuberufen.",
      "Jederzeit kann eine außerordentliche Mitgliederversammlung einberufen werden.",
      "Die Mitgliederversammlung wird vom 1. Vorsitzenden oder vom stellvertretenden Vorsitzenden einberufen und geleitet.",
      {
        text: "Die Einladung zur Mitgliederversammlung erfolgt schriftlich oder per Email unter Einhaltung einer Frist von 14 Tagen.",
        after: [
          "Die Frist beginnt mit dem Tag der Absendung der Einladung an die letztbekannte Mitgliedsanschrift. Die einladende Person darf ihre Unterschrift unter der Einladung durch Faksimile oder durch Ablichtung ersetzen.",
        ],
      },
      {
        text: "Die Einladung muss mindestens enthalten:",
        sub: [
          "Die Tagesordnung,",
          "bei Beschlussfassung den Gegenstand des Beschlusses,",
          "Tagungsort, Tagungszeit.",
        ],
      },
      "Beschlussfähig ist jede ordnungsgemäß berufene Mitgliederversammlung, und zwar ohne Rücksicht auf die Zahl der erschienenen Mitglieder.",
      "Anträge zur ordentlichen Mitgliederversammlung müssen spätestens bis Ende Januar beim Vorstand schriftlich eingereicht werden.",
      "Über die Mitgliederversammlung wird vom Schriftführer ein Protokoll geführt, das vom Schriftführer und vom Sitzungsleiter unterzeichnet wird.",
    ],
  },
  {
    number: 12,
    title: "Aufgaben der Mitgliederversammlung",
    items: [
      "Wahl des Vorstands.",
      "Wahl von zwei Kassenprüfern, die nicht dem Vorstand angehören dürfen. Die Kassenprüfer werden für zwei Jahre gewählt. Sie haben das Recht, die Vereinskasse und die Buchführung jederzeit zu prüfen. Über die Prüfung der gesamten Buch- und Kassenführung haben sie der Mitgliederversammlung Bericht zu erstatten.",
      "Die Entgegennahme des Jahres- und Kassenberichts des Vorstandes, des Prüfungsberichts der Kassenprüfer und Erteilung der Entlastung des Vorstands.",
      "Genehmigung des Haushaltsplans.",
      "Festlegung der Höhe und Art der Zahlung der Mitgliederbeiträge.",
      "Die Beschlussfassung über Satzungsänderungen.",
      "Beschlussfassung über die Auflösung des Vereins.",
    ],
  },
  {
    number: 13,
    title: "Beschlussfassung der Mitgliederversammlung",
    items: [
      "Die Mitgliederversammlung fasst ihre Beschlüsse mit einfacher Mehrheit der abgegebenen gültigen Stimmen. Eine Vertretung in der Stimmabgabe ist unzulässig.",
      "Die Beschlussfassung erfolgt durch offene Abstimmung, soweit nicht gesetzliche Bestimmungen oder die Satzung dem entgegenstehen.",
      "Geheime Abstimmung ist möglich auf Antrag eines der stimmberechtigten anwesender Mitglieder.",
      "Für die Wahl der Vorstandsmitglieder und der Kassenprüfer ist die einfache Mehrheit der abgegebenen Stimmen erforderlich. Bei Stimmengleichheit ist ein zweiter Wahlgang erforderlich. Im zweiten Wahlgang ist gewählt, wer die meisten gültigen Stimmen auf sich vereinen kann. Ergibt der zweite Wahlgang Stimmgleichheit, so entscheidet das Los.",
    ],
  },
  {
    number: 14,
    title: "Beschlüsse",
    items: [
      "Die Beschlüsse des Vorstandes und der Mitgliederversammlung sind schriftlich abzufassen und vom jeweiligen Sitzungsleiter und vom Schriftführer zu unterzeichnen.",
    ],
  },
  {
    number: 15,
    title: "Satzungsänderung",
    items: [
      "Eine Änderung der Satzung kann nur durch die Mitgliederversammlung beschlossen werden.",
      "Bei der Einladung ist die Angabe des zu ändernden Paragraphen der Satzung und der Beschlussvorschlag in der Tagesordnung bekannt zu geben.",
      "Ein Beschluss bedarf einer Mehrheit von zwei Drittel der abgegebenen Stimmen.",
    ],
  },
  {
    number: 16,
    title: "Vermögen",
    items: [
      "Alle Beiträge, Einnahmen, Mittel und bewegliche und unbewegliche Sachen des Vereins werden ausschließlich zur Erreichung des Vereinszwecks verwendet.",
    ],
  },
  {
    number: 17,
    title: "Haftung",
    items: [
      "Der Verein übernimmt keinerlei Haftung für die in seinen Räumen und auf seinen sonstigen Trainings und Wettkampfstätten gelagerten Sachen, die Privateigentum oder Privatbesitz seiner Mitglieder oder von Gästen sind.",
      "Die Teilnahme an sämtlichen sportlichen Aktivitäten des Vereins geschieht auf eigene Gefahr. Insoweit wird auf die Versicherungen der zuständigen Sportdachverbände (Kreissportbund, Landessportbund, Deutscher Sportbund) verwiesen.",
    ],
  },
  {
    number: 18,
    title: "Datenschutz",
    text: "Personenbezogene Daten der Mitglieder werden im Verein gespeichert und nur zum Zwecke der Meldungen an übergeordnete Verbände und zur Teilnahme an Wettkämpfen weitergegeben.",
  },
  {
    number: 19,
    title: "Vereinsauflösung",
    items: [
      "Die Auflösung des Vereins erfolgt durch Beschluss der Mitgliederversammlung, wobei drei Viertel der abgegebenen Stimmen für die Auflösung stimmen müssen.",
      "Die Mitgliederversammlung ernennt zur Abwicklung der Geschäfte drei gemeinsam vertretungsberechtigte Liquidatoren.",
      "Bei Auflösung des Vereins, bei seinem Erlöschen oder bei Wegfall seines bisherigen Zwecks fällt das Vermögen des Vereins an die Stadt Bad Oldesloe, und ist unmittelbar und ausschließlich für die Sportförderung bestimmt.",
    ],
  },
  {
    number: 20,
    title: "Inkrafttreten",
    text: "Mit der Annahme und Eintragung der Satzung in das Vereinsregister der Stadt Lübeck tritt die Satzung in Kraft.",
  },
];

export function satzungAnchor(number) {
  return `paragraph-${number}`;
}
