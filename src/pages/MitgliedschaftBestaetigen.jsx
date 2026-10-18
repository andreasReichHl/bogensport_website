import {useEffect, useState} from "react";
import {Link, useSearchParams} from "react-router-dom";
import Hero from "../components/Hero.jsx";
import Notice from "../components/Notice.jsx";
import {
    confirmMembershipApplication,
    fetchMembershipConfirmation,
    formatEuro,
    formatInterval,
} from "../utils/membership.js";

const dateTimeFormatter = new Intl.DateTimeFormat("de-DE", {dateStyle: "medium", timeStyle: "short"});

function Summary({application}) {
    const {group} = application;

    return (
        <dl className="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-[auto_1fr]">
            <dt className="font-semibold text-slate-900">Antragsnummer</dt>
            <dd>{application.reference}</dd>
            <dt className="font-semibold text-slate-900">Antragsteller/in</dt>
            <dd>{application.applicantName}</dd>
            <dt className="font-semibold text-slate-900">Mitgliedsart</dt>
            <dd>{group.name}</dd>
            {group.amount !== null && (
                <>
                    <dt className="font-semibold text-slate-900">Beitrag</dt>
                    <dd>{formatEuro(group.amount)} {formatInterval(group.intervalMonths)}</dd>
                </>
            )}
            <dt className="font-semibold text-slate-900">Aufnahmegebühr</dt>
            <dd>{group.admissionFee > 0 ? `${formatEuro(group.admissionFee)} einmalig` : "entfällt"}</dd>
            <dt className="font-semibold text-slate-900">Lastschrift von</dt>
            <dd>{application.ibanMasked}</dd>
        </dl>
    );
}

function ConfirmationContent({token}) {
    const [application, setApplication] = useState(null);
    const [state, setState] = useState(token ? "loading" : "invalid");

    useEffect(() => {
        if (!token) {
            return;
        }

        fetchMembershipConfirmation(token).then((result) => {
            if (result.status === "pending" || result.status === "confirmed") {
                setApplication(result);
            }

            setState(result.status);
        });
    }, [token]);

    async function handleConfirm() {
        setState("confirming");
        const result = await confirmMembershipApplication(token);

        setState(result.status === "already_confirmed" ? "confirmed" : result.status);
    }

    if (state === "loading") {
        return <p className="text-sm text-slate-500">Antrag wird geladen ...</p>;
    }

    if (state === "invalid") {
        return (
            <Notice title="Link ungültig oder abgelaufen" tone="error">
                <p>
                    Dieser Bestätigungslink ist ungültig oder abgelaufen. Unbestätigte Anträge werden aus
                    Datenschutzgründen nach Ablauf der Frist gelöscht.
                </p>
                <p>
                    Du kannst den Antrag einfach{" "}
                    <Link className="font-semibold text-blue-700 hover:underline" to="/mitglied-werden">
                        erneut stellen
                    </Link>
                    .
                </p>
            </Notice>
        );
    }

    if (state === "error") {
        return (
            <Notice title="Bestätigung gerade nicht möglich" tone="error">
                <p>Bitte versuche es später erneut. Der Link bleibt bis zum Ablauf der Frist gültig.</p>
            </Notice>
        );
    }

    if (state === "confirmed") {
        return (
            <Notice title="Antrag eingereicht – vielen Dank!">
                <p>
                    Der Mitgliedsantrag{application ? ` ${application.reference}` : ""} ist bestätigt und beim Vorstand
                    eingegangen. Eine Zusammenfassung als PDF kommt per E-Mail.
                </p>
                <p>
                    Über die Aufnahme entscheidet der Vorstand. Mit Annahme des Antrags beginnt die vorläufige
                    Mitgliedschaft (§ 4 der Satzung).
                </p>
            </Notice>
        );
    }

    return (
        <div className="space-y-6">
            <div className="rounded-lg border border-slate-200 bg-white p-5 text-sm text-slate-700 sm:p-6">
                <h2 className="font-heading text-lg font-bold text-[#002f5f]">Dein Antrag</h2>
                <Summary application={application}/>
            </div>
            <p className="text-sm text-slate-700">
                {application.isMinor
                    ? "Mit der Bestätigung reichen Sie den Antrag als Erziehungsberechtigte/r verbindlich ein. "
                    : "Mit der Bestätigung reichst du den Antrag verbindlich ein. "}
                Gespeichert werden dabei Zeitpunkt, IP-Adresse und Browser der Bestätigung als Nachweis. Der Link ist
                gültig bis {dateTimeFormatter.format(new Date(application.expiresAt))} Uhr.
            </p>
            <button
                type="button"
                onClick={handleConfirm}
                disabled={state === "confirming"}
                className="font-heading flex h-11 w-full items-center justify-center gap-3 rounded-md bg-blue-600 px-8 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-400"
            >
                {state === "confirming" ? "Wird bestätigt ..." : "Antrag verbindlich bestätigen"}
                {state !== "confirming" && <span aria-hidden="true">→</span>}
            </button>
        </div>
    );
}

export default function MitgliedschaftBestaetigen() {
    const [searchParams] = useSearchParams();
    const token = searchParams.get("token") ?? "";

    return (
        <>
            <Hero
                title={(
                    <>
                        Antrag
                        <br/>
                        bestätigen.
                    </>
                )}
                subtitle="Online-Mitgliedsantrag"
                description="Ein Klick noch – dann ist dein Antrag beim Vorstand."
                actions={[]}
            />
            <section className="bg-slate-50">
                <div className="mx-auto max-w-3xl px-5 pt-10 pb-14 sm:px-8 sm:pt-14 lg:pb-20">
                    <ConfirmationContent key={token} token={token}/>
                </div>
            </section>
        </>
    );
}
