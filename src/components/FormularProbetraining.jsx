import {useEffect, useRef, useState} from "react";
import {Link} from "react-router-dom";
import DatenschutzDialog from "./DatenschutzDialog.jsx";
import Notice from "./Notice.jsx";
import {Field, inputClass, SectionTitle, textareaClass} from "./FormFields.jsx";

const experienceLevels = ["Keine Erfahrung", "Ein wenig Erfahrung", "Fortgeschritten"];
const errorLabels = {
    firstName: "Vorname",
    lastName: "Nachname",
    age: "Alter",
    email: "E-Mail",
    guardianName: "Name des Erziehungsberechtigten",
    message: "Nachricht / Fragen",
    experience: "Erfahrung im Bogensport",
    ownEquipment: "Eigene Ausrüstung",
    consents: "Zustimmung",
};

function getFormValue(formData, key) {
    return formData.get(key)?.toString().trim() ?? "";
}

export default function FormularProbetraining() {
    const [participant, setParticipant] = useState("self");
    const [age, setAge] = useState("");
    const [submissionState, setSubmissionState] = useState("idle");
    const [errors, setErrors] = useState({});
    // Spam check on the server: bots submit right after loading, people need a while.
    const shownAt = useRef(0);
    const successRef = useRef(null);
    const feedbackRef = useRef(null);

    useEffect(() => {
        shownAt.current = Date.now();
    }, []);

    // Bring the result into view - the button sits at the end of a long form.
    useEffect(() => {
        if (submissionState === "success") {
            successRef.current?.scrollIntoView({behavior: "smooth", block: "start"});
        } else if (["invalid", "rate_limited", "error"].includes(submissionState)) {
            feedbackRef.current?.scrollIntoView({behavior: "smooth", block: "center"});
        }
    }, [submissionState]);

    async function handleSubmit(event) {
        event.preventDefault();

        const formData = new FormData(event.currentTarget);
        const ownEquipment = getFormValue(formData, "Eigene Ausrüstung vorhanden");

        setSubmissionState("submitting");
        setErrors({});

        try {
            const response = await fetch("/probetraining-absenden.php", {
                method: "POST",
                headers: {"Content-Type": "application/json", Accept: "application/json"},
                body: JSON.stringify({
                    firstName: getFormValue(formData, "Vorname"),
                    lastName: getFormValue(formData, "Nachname"),
                    age: Number(getFormValue(formData, "Alter")),
                    email: getFormValue(formData, "E-Mail"),
                    phone: getFormValue(formData, "Telefon"),
                    forChild: participant === "child",
                    guardianName: getFormValue(formData, "Name des Erziehungsberechtigten"),
                    experience: getFormValue(formData, "Erfahrung im Bogensport"),
                    ownEquipment: ownEquipment === "" ? null : ownEquipment === "Ja",
                    message: getFormValue(formData, "Nachricht / Fragen"),
                    consentPrivacy: formData.get("consentPrivacy") === "on",
                    consentContact: formData.get("consentContact") === "on",
                    website: getFormValue(formData, "website"),
                    fillTimeMs: Date.now() - shownAt.current,
                }),
            });

            if (response.status === 201) {
                setSubmissionState("success");
                setParticipant("self");
                setAge("");
                return;
            }

            if (response.status === 422) {
                const body = await response.json().catch(() => ({}));
                setErrors(body.errors ?? {});
                setSubmissionState("invalid");
                return;
            }

            setSubmissionState(response.status === 429 ? "rate_limited" : "error");
        } catch {
            setSubmissionState("error");
        }
    }

    if (submissionState === "success") {
        return (
            <section id="probetraining" ref={successRef} className="scroll-mt-4 bg-slate-50">
                <div className="mx-auto max-w-3xl px-5 pt-10 pb-14 sm:px-8 sm:pt-14 lg:pb-20">
                    <Notice title="Vielen Dank – deine Anfrage ist angekommen">
                        <p>
                            Wir haben deine Anfrage für ein Probetraining erhalten und melden uns in den nächsten Tagen
                            per E-Mail oder Telefon bei dir, um einen Termin abzustimmen.
                        </p>
                        <p>Eine automatische Bestätigungs-E-Mail verschicken wir nicht – wir melden uns persönlich.</p>
                    </Notice>
                    <div className="mt-6 flex flex-wrap items-center gap-x-6 gap-y-4">
                        <Link
                            to="/"
                            className="font-heading inline-flex h-11 items-center justify-center gap-3 rounded-md bg-blue-600 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700"
                        >
                            Zur Startseite <span aria-hidden="true">→</span>
                        </Link>
                        <button
                            type="button"
                            onClick={() => setSubmissionState("idle")}
                            className="text-sm font-semibold text-blue-700 hover:underline"
                        >
                            Weitere Anfrage senden
                        </button>
                    </div>
                </div>
            </section>
        );
    }

    return (
        <section id="probetraining" className="bg-slate-50">
            <div className="relative z-20 mx-auto max-w-8xl px-5 pb-14 sm:px-8 lg:px-12 lg:pb-20">
                <form
                    onSubmit={handleSubmit}
                    className="relative z-10 p-5 sm:p-8">
                    {/* Honeypot: hidden from people, bots tend to fill it. */}
                    <div className="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                        <label>
                            Website
                            <input name="website" tabIndex={-1} autoComplete="off"/>
                        </label>
                    </div>
                    <div className="grid gap-8 lg:grid-cols-[1.45fr_1fr] lg:divide-x lg:divide-slate-200">
                        <div className="space-y-8 lg:pr-8">
                            <section>
                                <SectionTitle number="1">Persönliche Daten</SectionTitle>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field label="Vorname" required>
                                        <input className={inputClass} name="Vorname" placeholder="Vorname" required/>
                                    </Field>
                                    <Field label="Nachname" required>
                                        <input className={inputClass} name="Nachname" placeholder="Nachname" required/>
                                    </Field>
                                    <Field label="Alter" required>
                                        <input
                                            className={inputClass}
                                            name="Alter"
                                            type="number"
                                            min="1"
                                            max="120"
                                            inputMode="numeric"
                                            placeholder="z. B. 12"
                                            value={age}
                                            onChange={(event) => setAge(event.target.value)}
                                            required
                                        />
                                    </Field>
                                    <Field label="E-Mail" required>
                                        <input className={inputClass} name="E-Mail" type="email"
                                               placeholder="beispiel@domain.de" required/>
                                    </Field>
                                    <Field label="Telefon">
                                        <input className={inputClass} name="Telefon" type="tel"
                                               placeholder="z. B. 0176 12345678"/>
                                    </Field>
                                </div>
                            </section>

                            <section className="border-t border-slate-200 pt-8">
                                <SectionTitle number="2">Teilnehmer</SectionTitle>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="flex items-center gap-3 text-sm font-medium text-slate-700">
                                        <input
                                            className="h-5 w-5 accent-blue-600"
                                            type="radio"
                                            name="Teilnehmer"
                                            value="Ich melde mich selbst an"
                                            checked={participant === "self"}
                                            onChange={() => setParticipant("self")}
                                        />
                                        Ich melde mich selbst an
                                    </label>
                                    <label className="flex items-center gap-3 text-sm font-medium text-slate-700">
                                        <input
                                            className="h-5 w-5 accent-blue-600"
                                            type="radio"
                                            name="Teilnehmer"
                                            value="Ich melde mein Kind an"
                                            checked={participant === "child"}
                                            onChange={() => setParticipant("child")}
                                        />
                                        Ich melde mein Kind an
                                    </label>
                                </div>
                                <div className="mt-5">
                                    <Field label="Name des Erziehungsberechtigten" required={participant === "child"}>
                                        <input
                                            className={`${inputClass} disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400`}
                                            name="Name des Erziehungsberechtigten"
                                            placeholder="Vor- und Nachname"
                                            disabled={participant === "self"}
                                            required={participant === "child"}
                                        />
                                    </Field>
                                </div>
                            </section>

                            <section className="border-t border-slate-200 pt-8">
                                <SectionTitle number="3">Erfahrung & Ausrüstung</SectionTitle>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field label="Erfahrung im Bogensport" required>
                                        <select className={inputClass} name="Erfahrung im Bogensport" required defaultValue="">
                                            <option value="" disabled>
                                                Bitte auswählen
                                            </option>
                                            {experienceLevels.map((level) => (
                                                <option key={level}>{level}</option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field label="Eigene Ausrüstung vorhanden?" required>
                                        <div className="flex h-11 items-center gap-8">
                                            <label
                                                className="flex items-center gap-3 text-sm font-medium text-slate-700">
                                                <input className="h-5 w-5 accent-blue-600" type="radio" name="Eigene Ausrüstung vorhanden"
                                                       value="Ja"
                                                       required/>
                                                Ja
                                            </label>
                                            <label
                                                className="flex items-center gap-3 text-sm font-medium text-slate-700">
                                                <input className="h-5 w-5 accent-blue-600" type="radio" name="Eigene Ausrüstung vorhanden"
                                                       value="Nein"
                                                       required/>
                                                Nein
                                            </label>
                                        </div>
                                    </Field>
                                </div>
                            </section>
                        </div>

                        <div className="space-y-8 lg:pl-8">
                            <section>
                                <SectionTitle number="4">Weitere Hinweise</SectionTitle>
                                <Field
                                    label="Nachricht / Fragen"
                                    hint="Gesundheitliche Punkte (z. B. Allergien, körperliche Einschränkungen) bitte nicht hier eintragen – sprich uns dazu gern beim Probetraining direkt an."
                                >
                                    <textarea className={textareaClass} name="Nachricht / Fragen"
                                              placeholder="Deine Nachricht an uns ..."/>
                                </Field>
                            </section>

                            <section className="border-t border-slate-200 pt-8">
                                <SectionTitle number="5">Zustimmung</SectionTitle>
                                <div className="space-y-4 text-sm text-slate-700">
                                    <label className="flex items-start gap-3">
                                        <input className="mt-1 h-5 w-5 shrink-0 accent-blue-600" type="checkbox"
                                               name="consentPrivacy" required/>
                                        <span>
                      Ich akzeptiere die{" "}
                                            <DatenschutzDialog startAt={null}/>
                      . *
                    </span>
                                    </label>
                                    <label className="flex items-start gap-3">
                                        <input className="mt-1 h-5 w-5 shrink-0 accent-blue-600" type="checkbox"
                                               name="consentContact" required/>
                                        <span>
                      Ich bin damit einverstanden, dass der Verein mich zur Terminabstimmung kontaktieren darf. *
                                        </span>
                                    </label>
                                </div>
                            </section>

                            <aside className="rounded-lg border border-blue-200 bg-blue-50 p-5">
                                <div className="flex gap-4">
                  <span
                      className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 border-blue-600 text-lg font-bold text-blue-700">
                    i
                  </span>
                                    <div>
                                        <h3 className="font-heading text-lg font-bold text-[#002f5f]">Hinweis</h3>
                                        <p className="mt-2 font-sans text-sm leading-relaxed text-slate-700">
                                            Deine Anfrage ist unverbindlich. Wir melden uns bei dir, um alles Weitere zu
                                            besprechen.
                                        </p>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>

                    <div className="mt-12">
                        <button
                            type="submit"
                            disabled={submissionState === "submitting"}
                            className="font-heading flex h-11 w-full items-center justify-center gap-3 rounded-md bg-blue-600 px-8 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                        >
                            {submissionState === "submitting" ? "Wird gesendet ..." : "Probetraining anfragen"}
                            {submissionState !== "submitting" && <span aria-hidden="true">→</span>}
                        </button>
                        <div ref={feedbackRef}>
                            {submissionState === "invalid" && (
                                <div className="mt-4 text-sm text-red-700" role="alert">
                                    <p className="font-semibold">Bitte prüfe deine Angaben:</p>
                                    <ul className="mt-1 list-disc pl-5">
                                        {Object.entries(errors).map(([key, message]) => (
                                            <li key={key}>{errorLabels[key] ?? key}: {message}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {submissionState === "rate_limited" && (
                                <p className="mt-4 text-sm font-semibold text-red-700">
                                    Es wurden zu viele Anfragen gesendet. Bitte versuche es später erneut.
                                </p>
                            )}
                            {submissionState === "error" && (
                                <p className="mt-4 text-sm font-semibold text-red-700">
                                    Die Anfrage konnte nicht gesendet werden. Bitte versuche es später erneut.
                                </p>
                            )}
                        </div>
                        <p className="mt-4 text-sm text-slate-500">* Pflichtfelder</p>
                    </div>
                </form>
            </div>
        </section>
    );
}
