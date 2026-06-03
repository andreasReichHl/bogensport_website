import {Link} from "react-router-dom";

import {BulletList, ContentSection} from "../components/ContentSection.jsx";
import Hero from "../components/Hero.jsx";

const summerTimes = ["Montag und Freitag ab 17:00 Uhr"];
const winterTimes = ["Montag und Freitag ab 20:00 Uhr", "Samstag ab 13:00 Uhr"];
const organizationItems = [
    "ausreichend Material vorhanden ist",
    "ein Trainer vor Ort ist",
    "wir uns Zeit für dich nehmen können",
];

export default function Training() {
    return (
        <>
            <Hero
                title={(
                    <>
                        Training.
                        <br/>
                        Wann und wo?
                    </>
                )}
                subtitle="Sommer, Winter und Probetraining"
                description="Unser Training findet je nach Jahreszeit an unterschiedlichen Orten statt."
                actions={[]}
            />

            <main className="bg-slate-50">
                <div className="relative max-w-8xl px-5 pb-16 sm:px-8 lg:px-12 lg:pb-24">
                    <article className="p-6 sm:p-10">
                        <div className="space-y-10">
                            <ContentSection title="Training - wann und wo?">
                                <p>Unser Training findet je nach Jahreszeit an unterschiedlichen Orten statt.</p>
                            </ContentSection>

                            <ContentSection title="Sommertraining">
                                <p>In den Sommermonaten trainieren wir auf unserem Außengelände:</p>
                                <p>
                                    <span
                                        className="font-heading font-bold text-[#002f5f]">Am Kurpark 2 in Bad Oldesloe</span>
                                    <br/>
                                    ehemaliger Hockeyplatz
                                </p>
                                <div>
                                    <p className="font-heading font-bold text-slate-900">Trainingszeiten:</p>
                                    <div className="mt-3">
                                        <BulletList items={summerTimes}/>
                                    </div>
                                </div>
                                <p>
                                    Hier stehen uns ausreichend Platz und Distanzen zur Verfügung, sodass - je nach
                                    Altersklasse und
                                    Leistungsstand - Entfernungen von 10 bis zu 70 Metern geschossen werden können.
                                </p>
                            </ContentSection>

                            <ContentSection title="Wintertraining">
                                <p>In den Wintermonaten trainieren wir in der Halle der:</p>
                                <p>
                                    <span className="font-heading font-bold text-[#002f5f]">Ida-Ehre-Schule in Bad Oldesloe</span>
                                    <br/>
                                    Olivet-Allee 4-6, 23843 Bad Oldesloe
                                </p>
                                <div>
                                    <p className="font-heading font-bold text-slate-900">Trainingszeiten:</p>
                                    <div className="mt-3">
                                        <BulletList items={winterTimes}/>
                                    </div>
                                </div>
                                <p>
                                    In der Halle trainieren wir auf die für die Hallensaison typische Wettkampfdistanz
                                    von 18 Metern.
                                </p>
                            </ContentSection>

                            <ContentSection title="Probetraining">
                                <p>
                                    Du möchtest den Bogensport ausprobieren? Dann melde dich gerne bei uns für ein
                                    Probetraining an.
                                </p>
                                <p>
                                    Für die ersten Trainingseinheiten stellen wir dir die komplette Ausrüstung zur
                                    Verfügung. Gerade für
                                    Einsteiger ist es sinnvoll, nicht direkt einen eigenen Bogen zu kaufen, da sich
                                    Technik, Zuggewicht und
                                    persönliche Anforderungen schnell verändern. Ein vorschneller Kauf führt oft dazu,
                                    dass der Bogen nicht
                                    lange optimal passt.
                                </p>
                                <p>
                                    Wir unterstützen dich deshalb gerne bei der Auswahl eines passenden Leihbogens und
                                    beraten dich später
                                    auch beim Kauf deiner eigenen Ausrüstung.
                                </p>
                            </ContentSection>

                            <ContentSection title="Organisation & Ablauf">
                                <p>
                                    Damit wir dein Probetraining optimal vorbereiten können, ist eine vorherige
                                    Anmeldung erforderlich. So
                                    stellen wir sicher, dass:
                                </p>
                                <BulletList items={organizationItems}/>
                                <p>
                                    Für das Probetraining erheben wir einen Kostenbeitrag von 20 €. Dafür hast du die
                                    Möglichkeit, an
                                    drei Trainingstagen zu den vereinbarten Zeiten teilzunehmen und den Bogensport
                                    kennenzulernen.
                                </p>
                                <p>
                                    Solltest du dich danach für eine Mitgliedschaft entscheiden, wird der Betrag auf die
                                    Aufnahmegebühr
                                    angerechnet.
                                </p>
                            </ContentSection>

                            <ContentSection title="Einfach vorbeikommen">
                                <p>
                                    Du bist dir noch nicht sicher und möchtest erstmal nur zuschauen? Kein Problem -
                                    auch das ist jederzeit
                                    möglich.
                                </p>
                                <p>Komm gerne vorbei, lerne uns kennen und verschaffe dir einen ersten Eindruck.</p>
                                <p>Wir freuen uns auf dich!</p>
                                <Link
                                    to="/probetraining"
                                    className="inline-flex h-11 items-center justify-center gap-3 rounded-md bg-blue-600 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700"
                                >
                                    Probetraining anfragen
                                    <span aria-hidden="true">→</span>
                                </Link>
                            </ContentSection>
                        </div>
                    </article>
                </div>
            </main>
        </>
    );
}
