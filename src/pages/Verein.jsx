import { Link } from "react-router-dom";
import { membershipEnabled } from "../utils/features.js";
import Hero from "../components/Hero.jsx";
import { BulletList, ContentSection } from "../components/ContentSection.jsx";

const focusItems = [
    "die Förderung und Ausübung des Bogensports als Freizeit- und Wettkampfsport",
    // "die aktive Jugendarbeit und Förderung des Nachwuchses",
    "die Teilnahme am Meisterschaftsprogramm des Deutschen Schützenbundes",
    "die Organisation und Durchführung von Wettkämpfen",
];

const competitionItems = [
    "Kreis-, Landes- und Deutsche Meisterschaften",
    "Wettbewerbe des Deutschen Schützenbundes",
    "Ligawettkämpfe des Norddeutschen Schützenbundes in Schleswig-Holstein",
];

const communityItems = [
    "ein freundschaftlicher und respektvoller Umgang",
    "gegenseitige Unterstützung und Hilfsbereitschaft",
    "gemeinsame Aktivitäten und Austausch",
    "Freude am Bogensport",
];

export default function Verein() {
    return (
        <>
            <Hero
                title={(
                    <>
                        Unser
                        <br/>
                        Verein.
                    </>
                )}
                subtitle="Bad Oldesloer Bogensport e.V."
                description="Gemeinschaft, Training und Begeisterung für den Bogensport in Bad Oldesloe."
                actions={[]}
            />

            <main className="bg-slate-50">
                <div className="relative max-w-8xl px-5 pb-16 sm:px-8 lg:px-12 lg:pb-24">
                    <article
                        className="p-6 sm:p-10">
                        <div className="space-y-10">
                            <ContentSection title="Wer sind wir?">
                                <p>
                                    Am 2. Oktober 2009 trafen sich engagierte Menschen aus dem Bogensport aus Schleswig-Holstein und
                                    gründeten den
                                    Verein Bad Oldesloer Bogensport e.V. (BOBS). Zu den Gründungsmitgliedern zählte eine
                                    Gruppe engagierter Personen. Bereits im Dezember 2009 wurde der Verein offiziell in
                                    das Vereinsregister eingetragen.
                                </p>
                                <p>
                                    Seit unserer Gründung verfolgen wir ein klares Ziel: die Förderung und Ausübung des
                                    Bogensports -
                                    sowohl im Freizeitbereich als auch im Wettkampfsport.
                                </p>
                            </ContentSection>

                            <ContentSection title="Unser Verein">
                                <p>Der Bad Oldesloer Bogensport e.V. ist ein reiner Bogensportverein. Im Mittelpunkt
                                    stehen bei uns:</p>
                                <BulletList items={focusItems}/>
                                <p>
                                    Grundlage unserer Vereinsarbeit ist unsere{" "}
                                    <Link className="text-[#002f5f] underline underline-offset-4 hover:no-underline" to="/satzung">
                                        Satzung
                                    </Link>
                                    .
                                </p>
                                <p>
                                    Unser sportlicher Schwerpunkt liegt im Bereich des FITA-orientierten Schießens mit
                                    Recurve-, Compound-
                                    und Blankbogen.
                                </p>
                                <p>
                                    Bereits kurz nach der Gründung wuchs der Verein auf rund 30 Mitglieder. Darunter
                                    finden sich sowohl
                                    erfahrene Bogenschützen mit jahrzehntelanger Praxis als auch Einsteiger, die ihre
                                    Begeisterung für den
                                    Sport erst bei uns entdecken.
                                </p>
                            </ContentSection>

                            <ContentSection title="Sport und Engagement">
                                <p>
                                    Unsere Mitglieder sind sowohl im Leistungs- als auch im Breitensport aktiv. Viele
                                    nehmen regelmäßig an
                                    Wettkämpfen teil, darunter:
                                </p>
                                <BulletList items={competitionItems}/>
                                <p>An den Ligawettkämpfen nehmen wir bereits seit der Saison 2011 teil.</p>
                                <p>
                                    Gleichzeitig bieten wir auch allen, die den Bogensport als Ausgleich zum Alltag
                                    betreiben möchten, ein
                                    ideales Umfeld. Bei uns findet jeder seinen Platz - unabhängig von Erfahrung oder
                                    Leistungsanspruch.
                                </p>
                            </ContentSection>

                            <ContentSection title="Gemeinschaft und Vereinsleben">
                                <p>
                                    Neben dem sportlichen Aspekt spielt bei uns das Miteinander eine zentrale Rolle.
                                    Auch wenn
                                    Bogenschießen grundsätzlich eine Individualsportart ist, verstehen wir uns als
                                    Gemeinschaft und Team.
                                </p>
                                <p>Was uns auszeichnet:</p>
                                <BulletList items={communityItems}/>
                                <p>
                                    Wir sind überzeugt, dass sportlicher Erfolg und persönliche Entwicklung nur dort
                                    entstehen, wo auch das
                                    Zwischenmenschliche stimmt.
                                </p>
                            </ContentSection>

                            {membershipEnabled && (
                                <ContentSection title="Mitglied werden">
                                    <p>
                                        Du möchtest Teil unseres Vereins werden? Den Mitgliedsantrag kannst du bequem online
                                        stellen.
                                    </p>
                                    <Link
                                        to="/mitglied-werden"
                                        className="inline-flex h-11 items-center justify-center gap-3 rounded-md bg-blue-600 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700"
                                    >
                                        Mitglied werden
                                        <span aria-hidden="true">→</span>
                                    </Link>
                                </ContentSection>
                            )}
                        </div>
                    </article>
                </div>
            </main>
        </>
    );
}
