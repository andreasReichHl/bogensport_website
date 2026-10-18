import {useState} from "react";
import {Field, inputClass, SectionTitle, textareaClass} from "./FormFields.jsx";

const experienceLevels = ["Keine Erfahrung", "Ein wenig Erfahrung", "Fortgeschritten"];
const formspreeEndpoint = import.meta.env.VITE_FORMSPREE_ENDPOINT || "";

function getFormValue(formData, key) {
    return formData.get(key)?.toString().trim() || "-";
}

export default function FormularProbetraining() {
    const [participant, setParticipant] = useState("self");
    const [age, setAge] = useState("");
    const [submissionState, setSubmissionState] = useState("idle");

    async function handleSubmit(event) {
        event.preventDefault();

        if (!formspreeEndpoint) {
            setSubmissionState("error");
            window.alert("Formspree ist noch nicht konfiguriert. Bitte VITE_FORMSPREE_ENDPOINT setzen.");
            return;
        }

        const form = event.currentTarget;
        const formData = new FormData(form);
        const email = getFormValue(formData, "E-Mail");
        const subject = "Neue Anfrage für ein Probetraining";
        const message = [
            "Neue Anfrage für ein Probetraining",
            "",
            "Persönliche Daten",
            `Vorname: ${getFormValue(formData, "Vorname")}`,
            `Nachname: ${getFormValue(formData, "Nachname")}`,
            `Alter: ${getFormValue(formData, "Alter")}`,
            `E-Mail: ${email}`,
            `Telefon: ${getFormValue(formData, "Telefon")}`,
            "",
            "Teilnehmer",
            `Anmeldung: ${getFormValue(formData, "Teilnehmer")}`,
            `Name des Erziehungsberechtigten: ${getFormValue(formData, "Name des Erziehungsberechtigten")}`,
            "",
            "Erfahrung & Ausrüstung",
            `Erfahrung im Bogensport: ${getFormValue(formData, "Erfahrung im Bogensport")}`,
            `Eigene Ausrüstung vorhanden: ${getFormValue(formData, "Eigene Ausrüstung vorhanden")}`,
            "",
            "Weitere Hinweise",
            `Nachricht / Fragen: ${getFormValue(formData, "Nachricht / Fragen")}`,
            `Gesundheitliche Hinweise: ${getFormValue(formData, "Gesundheitliche Hinweise")}`,
            "",
            "Zustimmung",
            "Datenschutzerklärung akzeptiert: Ja",
            "Kontaktaufnahme erlaubt: Ja",
        ].join("\n");

        const payload = new FormData();

        payload.append("email", email);
        payload.append("_replyto", email);
        payload.append("_subject", subject);
        payload.append("Alter", getFormValue(formData, "Alter"));
        payload.append("message", message);

        setSubmissionState("submitting");

        try {
            const response = await fetch(formspreeEndpoint, {
                method: "POST",
                body: payload,
                headers: {
                    Accept: "application/json",
                },
            });

            if (!response.ok) {
                throw new Error("Formspree request failed");
            }

            setSubmissionState("success");
            form.reset();
            setParticipant("self");
            setAge("");
        } catch {
            setSubmissionState("error");
        }
    }

    return (
        <section id="probetraining" className="bg-slate-50">
            <div className="relative z-20 mx-auto max-w-8xl px-5 pb-14 sm:px-8 lg:px-12 lg:pb-20">
                <form
                    onSubmit={handleSubmit}
                    className="relative z-10 p-5 sm:p-8">
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
                                <div className="space-y-5">
                                    <Field label="Nachricht / Fragen">
                                        <textarea className={textareaClass} name="Nachricht / Fragen"
                                                  placeholder="Deine Nachricht an uns ..."/>
                                    </Field>
                                    <Field label="Gesundheitliche Hinweise (optional)">
                    <textarea
                        className={textareaClass}
                        name="Gesundheitliche Hinweise"
                        placeholder="z. B. Allergien, körperliche Einschränkungen ..."
                    />
                                    </Field>
                                </div>
                            </section>

                            <section className="border-t border-slate-200 pt-8">
                                <SectionTitle number="5">Zustimmung</SectionTitle>
                                <div className="space-y-4 text-sm text-slate-700">
                                    <label className="flex items-start gap-3">
                                        <input className="mt-1 h-5 w-5 shrink-0 accent-blue-600" type="checkbox"
                                               required/>
                                        <span>
                      Ich akzeptiere die{" "}
                                            <a className="font-semibold text-blue-700 hover:underline"
                                               href="/datenschutz">
                        Datenschutzerklärung
                      </a>
                      . *
                    </span>
                                    </label>
                                    <label className="flex items-start gap-3">
                                        <input className="mt-1 h-5 w-5 shrink-0 accent-blue-600" type="checkbox"
                                               required/>
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
                        {submissionState === "success" && (
                            <p className="mt-4 text-sm font-semibold text-green-700">
                                Deine Anfrage wurde gesendet.
                            </p>
                        )}
                        {submissionState === "error" && (
                            <p className="mt-4 text-sm font-semibold text-red-700">
                                Die Anfrage konnte nicht gesendet werden. Bitte versuche es später erneut.
                            </p>
                        )}
                        <p className="mt-4 text-sm text-slate-500">* Pflichtfelder</p>
                    </div>
                </form>
            </div>
        </section>
    );
}
