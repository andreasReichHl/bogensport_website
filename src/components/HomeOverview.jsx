import {Link} from "react-router-dom";

import UpcomingEvents from "./UpcomingEvents.jsx";
import {useGalleryImages} from "../utils/useGalleryImages.js";

const infoItems = [
    {
        title: "Training im Sommer",
        text: "Montag & Freitag ab 17:00 Uhr",
        subtext: "Am Kurpark 2, Bad Oldesloe",
        icon: (
            <path d="M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        ),
    },
    {
        title: "Training im Winter",
        text: (
            <>
                Montag & Freitag ab 20:00 Uhr,
                <br/>
                Samstag ab 13:00 Uhr
            </>
        ),
        subtext: "Ida-Ehre-Schule, Bad Oldesloe",
        icon: (
            <>
                <path d="M4 21V9l8-5 8 5v12"/>
                <path d="M9 21v-7h6v7"/>
                <path d="M8 10h.01"/>
                <path d="M12 10h.01"/>
                <path d="M16 10h.01"/>
            </>
        ),
    },
    {
        title: "Probetraining",
        text: "3 Tage Bogensport kennenlernen",
        subtext: "Für 20 EUR inkl. Leihmaterial",
        icon: (
            <>
                <path d="M8 2v4"/>
                <path d="M16 2v4"/>
                <path d="M3 10h18"/>
                <path d="M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/>
                <path d="m8 16 2.5 2.5L16 13"/>
            </>
        ),
    },
    {
        title: "Für alle",
        text: "Jugendliche, Erwachsene",
        subtext: "und Wiedereinsteiger",
        icon: (
            <path
                d="M12 21a9 9 0 1 0-9-9 9 9 0 0 0 9 9Zm0-4a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0-4a1 1 0 1 0-1-1 1 1 0 0 0 1 1Zm0-1 5-5"/>
        ),
    },
];

function InfoIcon({children}) {
    return (
        <svg
            className="h-9 w-9 shrink-0 text-blue-500"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {children}
        </svg>
    );
}

export default function HomeOverview() {
    const {galleryImages, isLoading} = useGalleryImages();
    const previewImages = galleryImages.slice(0, 5);

    return (
        <section className="bg-slate-50 pt-5">
            <div className="relative z-20 mx-auto max-w-8xl px-5 sm:px-8 lg:px-12">
                <div
                    className="grid overflow-hidden rounded-lg border border-slate-200 text-slate-900 shadow-2xl shadow-slate-400/50 md:grid-cols-2 lg:grid-cols-4">
                    {infoItems.map((item, index) => (
                        <article
                            key={item.title}
                            className={`relative flex gap-5 p-6 ${index > 0 ? "border-t border-slate-200 md:border-t-0" : ""} ${index === 2 ? "md:border-t lg:border-t-0" : ""}`}
                        >
                            {index > 0 && (
                                <span
                                    className="pointer-events-none absolute bottom-6 left-0 top-6 hidden w-px bg-slate-200 md:block"
                                    aria-hidden="true"
                                />
                            )}
                            <InfoIcon>{item.icon}</InfoIcon>
                            <div>
                                <h2 className="font-heading text-base font-bold">{item.title}</h2>
                                <p className="mt-2 text-sm font-semibold leading-relaxed text-slate-900">{item.text}</p>
                                <p className="mt-3 text-sm leading-relaxed text-slate-900">{item.subtext}</p>
                            </div>
                        </article>
                    ))}
                </div>
            </div>

            <div id="training"
                 className="mx-auto grid max-w-7xl gap-8 px-5 py-12 sm:px-8 lg:grid-cols-[1.1fr_0.9fr] lg:px-12 lg:py-16">
                <section>
                    <p className="font-heading text-sm font-bold uppercase tracking-wide text-blue-700">Über unseren
                        Verein</p>
                    <h2 className="mt-4 font-heading text-3xl font-bold leading-tight text-slate-900 sm:text-4xl">
                        Gemeinsam mehr erreichen.
                    </h2>
                    <div className="mt-5 h-0.5 w-20 bg-blue-600"/>
                    <p className="mt-6 max-w-2xl text-base leading-relaxed text-slate-700">
                        Bad Oldesloer Bogensport e.V. verbindet konzentriertes Training mit einer offenen Gemeinschaft.
                        Ob Einstieg,
                        Wiedereinstieg oder regelmäßiges Training - auf unserem Platz findest du Raum für Fortschritt.
                    </p>
                    <Link
                        to="/verein"
                        className="mt-8 inline-flex h-11 items-center justify-center gap-3 rounded-md bg-blue-600 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700"
                    >
                        Mehr über uns
                        <span aria-hidden="true">→</span>
                    </Link>
                </section>

                <aside className="rounded-lg border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/70">
                    <p className="font-heading text-sm font-bold uppercase tracking-wide text-blue-700">Aktuelle
                        Termine</p>
                    <UpcomingEvents limit={2} showAllLink/>
                </aside>
            </div>

            <section className="mx-auto max-w-7xl px-5 pb-14 sm:px-8 lg:px-12 lg:pb-20">
                <div className="mb-5 flex items-end justify-between gap-6">
                    <div>
                        <p className="font-heading text-sm font-bold uppercase tracking-wide text-blue-700">
                            Galerie
                        </p>
                        <h2 className="mt-3 font-heading text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">
                            Einblicke in unseren Verein
                        </h2>
                    </div>
                    <Link
                        to="/gallerie"
                        className="hidden shrink-0 text-sm font-bold text-[#002f5f] transition hover:text-blue-700 sm:inline-flex"
                    >
                        Zur Gallerie <span aria-hidden="true">→</span>
                    </Link>
                </div>

                {!isLoading && previewImages.length > 0 && (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {previewImages.map((image) => (
                            <Link
                                key={image.src}
                                to="/gallerie"
                                className="group block overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-slate-200"
                                aria-label="Zur Gallerie"
                            >
                                <img
                                    src={image.src}
                                    alt={image.alt}
                                    loading="lazy"
                                    className="aspect-[4/3] w-full object-cover transition duration-300 group-hover:scale-105"
                                />
                            </Link>
                        ))}
                    </div>
                )}

                <Link
                    to="/gallerie"
                    className="mt-5 inline-flex text-sm font-bold text-[#002f5f] transition hover:text-blue-700 sm:hidden"
                >
                    Zur Gallerie <span aria-hidden="true">→</span>
                </Link>
            </section>
        </section>
    );
}
