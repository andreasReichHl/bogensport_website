import {useEffect, useState} from "react";
import {Link} from "react-router-dom";
import {Field, inputClass, SectionTitle} from "./FormFields.jsx";
import Notice from "./Notice.jsx";
import SatzungDialog from "./SatzungDialog.jsx";
import {satzungMeta} from "../data/satzung.js";
import {ageOn, fetchMembershipForm, formatEuro, formatInterval, submitMembershipApplication} from "../utils/membership.js";
import {downloadLinks} from "../utils/siteLinks.js";

const emptyValues = {
    groupId: "",
    applicant: {
        salutation: "",
        firstName: "",
        lastName: "",
        dateOfBirth: "",
        gender: "",
        street: "",
        zip: "",
        city: "",
        email: "",
        phone: "",
    },
    guardian: {firstName: "", lastName: "", email: "", phone: ""},
    bank: {accountHolder: "", iban: "", bic: ""},
    consents: {satzung: false, beitrag: false, datenschutz: false, sepa: false, erziehungsberechtigt: false, fotos: false},
    website: "",
};

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Whether all required fields are filled and all required declarations ticked - only enables
 * the submit button. The server validates everything again (incl. IBAN check digits).
 */
function isFormComplete(values, isMinor) {
    const {applicant, guardian, bank, consents} = values;
    const filled = (...fields) => fields.every((field) => field.trim() !== "");

    return filled(values.groupId, applicant.firstName, applicant.lastName, applicant.dateOfBirth, applicant.gender,
            applicant.street, applicant.city, bank.accountHolder, bank.iban)
        && /^\d{5}$/.test(applicant.zip)
        && (isMinor ? applicant.email === "" || emailPattern.test(applicant.email) : emailPattern.test(applicant.email))
        && (!isMinor || (filled(guardian.firstName, guardian.lastName) && emailPattern.test(guardian.email)))
        && consents.satzung && consents.beitrag && consents.datenschutz && consents.sepa
        && (!isMinor || consents.erziehungsberechtigt);
}

const paperApplicationLink = downloadLinks.find((link) => link.label === "Mitgliedsantrag");

function Consent({checked, onChange, required, error, children}) {
    return (
        <div>
            <label className="flex items-start gap-3">
                <input
                    className="mt-1 h-5 w-5 shrink-0 accent-blue-600"
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    required={required}
                />
                <span>
                    {children}
                    {required && " *"}
                </span>
            </label>
            {error && <p className="mt-1 pl-8 text-sm text-red-700">{error}</p>}
        </div>
    );
}

function GroupOption({group, checked, onSelect}) {
    return (
        <label
            className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition ${
                checked ? "border-blue-600 bg-blue-50 ring-2 ring-blue-100" : "border-slate-300 bg-white hover:border-blue-400"
            }`}
        >
            <input
                className="mt-1 h-5 w-5 shrink-0 accent-blue-600"
                type="radio"
                name="groupId"
                value={group.id}
                checked={checked}
                onChange={onSelect}
                required
            />
            <span className="text-sm text-slate-700">
                <span className="block font-semibold text-slate-900">{group.name}</span>
                {group.amount !== null && (
                    <span className="block">Beitrag: {formatEuro(group.amount)} {formatInterval(group.intervalMonths)}</span>
                )}
                <span className="block">
                    Aufnahmegebühr: {group.admissionFee > 0 ? `${formatEuro(group.admissionFee)} einmalig` : "entfällt"}
                </span>
                {group.maxAge !== null && <span className="block text-slate-500">bis {group.maxAge} Jahre</span>}
            </span>
        </label>
    );
}

function PaperApplicationHint() {
    return (
        <p>
            Du kannst den{" "}
            <a className="font-semibold text-blue-700 hover:underline" href={paperApplicationLink.href} download>
                Mitgliedsantrag als PDF
            </a>{" "}
            herunterladen und ausgefüllt beim Training abgeben.
        </p>
    );
}

export default function FormularMitgliedschaft() {
    const [config, setConfig] = useState(null);
    const [configState, setConfigState] = useState("loading");
    const [values, setValues] = useState(emptyValues);
    const [errors, setErrors] = useState({});
    const [submissionState, setSubmissionState] = useState("idle");

    useEffect(() => {
        fetchMembershipForm()
            .then((data) => {
                setConfig(data);
                setConfigState("ready");
            })
            .catch(() => setConfigState("error"));
    }, []);

    const age = ageOn(values.applicant.dateOfBirth);
    const isMinor = age !== null && age < 18;
    const selectedGroup = config?.groups.find((group) => String(group.id) === values.groupId) ?? null;
    const confirmationEmail = isMinor ? values.guardian.email : values.applicant.email;
    const canSubmit = isFormComplete(values, isMinor) && submissionState !== "submitting";

    function setValue(section, key, value) {
        setValues((current) => ({...current, [section]: {...current[section], [key]: value}}));
    }

    function bindInput(section, key) {
        return {
            value: values[section][key],
            onChange: (event) => setValue(section, key, event.target.value),
        };
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmissionState("submitting");
        setErrors({});

        const result = await submitMembershipApplication({
            ...values,
            groupId: Number(values.groupId),
            guardian: isMinor ? values.guardian : null,
            satzungVersion: satzungMeta.version,
            textsVersion: config.texts.version,
        });

        if (result.status === "ok") {
            setSubmissionState("success");
            window.scrollTo({top: 0, behavior: "smooth"});
            return;
        }

        if (result.status === "invalid") {
            setErrors(result.errors);
        }

        setSubmissionState(result.status);
    }

    if (configState === "loading") {
        return (
            <section className="bg-slate-50 px-5 pt-10 pb-14 sm:px-8 sm:pt-14 lg:px-12">
                <p className="p-5 text-sm text-slate-500 sm:p-8">Formular wird geladen ...</p>
            </section>
        );
    }

    if (configState === "error" || config.groups.length === 0) {
        return (
            <section className="bg-slate-50">
                <div className="mx-auto max-w-3xl px-5 pt-10 pb-14 sm:px-8 sm:pt-14 lg:pb-20">
                    <Notice title="Online-Beitritt derzeit nicht möglich">
                        <p>Das Beitrittsformular ist gerade nicht verfügbar. Bitte versuche es später erneut.</p>
                        <PaperApplicationHint/>
                    </Notice>
                </div>
            </section>
        );
    }

    if (submissionState === "success") {
        return (
            <section className="bg-slate-50">
                <div className="mx-auto max-w-3xl px-5 pt-10 pb-14 sm:px-8 sm:pt-14 lg:pb-20">
                    <Notice title="Fast geschafft – bitte E-Mail bestätigen">
                        <p>
                            Wir haben eine E-Mail an <strong>{confirmationEmail}</strong> geschickt. Bitte klicke auf den
                            Link in der E-Mail und bestätige den Antrag innerhalb von {config.confirmTtlHours} Stunden.
                        </p>
                        <p>
                            Erst mit der Bestätigung ist dein Antrag eingereicht. Danach erhältst du eine Bestätigung
                            als PDF, und der Vorstand entscheidet über die Aufnahme.
                        </p>
                        <p>Keine E-Mail erhalten? Bitte schau auch im Spam-Ordner nach.</p>
                    </Notice>
                </div>
            </section>
        );
    }

    const texts = config.texts;

    return (
        <section id="mitglied-werden" className="bg-slate-50">
            <div className="relative z-20 mx-auto max-w-8xl px-5 pb-14 sm:px-8 lg:px-12 lg:pb-20">
                <form onSubmit={handleSubmit} className="relative z-10 p-5 sm:p-8">
                    {/* Honeypot: hidden from people, bots tend to fill it. */}
                    <div className="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                        <label>
                            Website
                            <input
                                tabIndex={-1}
                                autoComplete="off"
                                value={values.website}
                                onChange={(event) => setValues((current) => ({...current, website: event.target.value}))}
                            />
                        </label>
                    </div>

                    <div className="grid gap-8 lg:grid-cols-[1.45fr_1fr] lg:divide-x lg:divide-slate-200">
                        <div className="space-y-8 lg:pr-8">
                            <section>
                                <SectionTitle number="1">Mitgliedsart</SectionTitle>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {config.groups.map((group) => (
                                        <GroupOption
                                            key={group.id}
                                            group={group}
                                            checked={values.groupId === String(group.id)}
                                            onSelect={() => setValues((current) => ({...current, groupId: String(group.id)}))}
                                        />
                                    ))}
                                </div>
                                {errors.groupId && <p className="mt-2 text-sm text-red-700">{errors.groupId}</p>}
                            </section>

                            <section className="border-t border-slate-200 pt-8">
                                <SectionTitle number="2">Persönliche Daten</SectionTitle>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field label="Anrede" error={errors["applicant.salutation"]}>
                                        <select className={inputClass} {...bindInput("applicant", "salutation")}>
                                            <option value="">Keine Angabe</option>
                                            <option>Herr</option>
                                            <option>Frau</option>
                                        </select>
                                    </Field>
                                    <Field label="Geschlecht" required error={errors["applicant.gender"]}>
                                        <select className={inputClass} required {...bindInput("applicant", "gender")}>
                                            <option value="" disabled>Bitte auswählen</option>
                                            <option value="w">weiblich</option>
                                            <option value="m">männlich</option>
                                            <option value="d">divers</option>
                                        </select>
                                    </Field>
                                    <Field label="Vorname" required error={errors["applicant.firstName"]}>
                                        <input className={inputClass} autoComplete="given-name" required
                                               {...bindInput("applicant", "firstName")}/>
                                    </Field>
                                    <Field label="Nachname" required error={errors["applicant.lastName"]}>
                                        <input className={inputClass} autoComplete="family-name" required
                                               {...bindInput("applicant", "lastName")}/>
                                    </Field>
                                    <Field label="Geburtsdatum" required error={errors["applicant.dateOfBirth"]}>
                                        <input className={inputClass} type="date" autoComplete="bday" required
                                               {...bindInput("applicant", "dateOfBirth")}/>
                                    </Field>
                                    <Field label="Telefon" error={errors["applicant.phone"]}>
                                        <input className={inputClass} type="tel" autoComplete="tel"
                                               placeholder="z. B. 0176 12345678" {...bindInput("applicant", "phone")}/>
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <Field label="Straße und Hausnummer" required error={errors["applicant.street"]}>
                                            <input className={inputClass} autoComplete="street-address" required
                                                   {...bindInput("applicant", "street")}/>
                                        </Field>
                                    </div>
                                    <Field label="PLZ" required error={errors["applicant.zip"]}>
                                        <input className={inputClass} autoComplete="postal-code" inputMode="numeric"
                                               pattern="\d{5}" maxLength={5} required {...bindInput("applicant", "zip")}/>
                                    </Field>
                                    <Field label="Ort" required error={errors["applicant.city"]}>
                                        <input className={inputClass} autoComplete="address-level2" required
                                               {...bindInput("applicant", "city")}/>
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <Field label={isMinor ? "E-Mail (optional)" : "E-Mail"} required={!isMinor}
                                               error={errors["applicant.email"]}>
                                            <input className={inputClass} type="email" autoComplete="email"
                                                   placeholder="beispiel@domain.de" required={!isMinor}
                                                   {...bindInput("applicant", "email")}/>
                                        </Field>
                                    </div>
                                </div>
                            </section>

                            {isMinor && (
                                <section className="border-t border-slate-200 pt-8">
                                    <SectionTitle number="2a">Erziehungsberechtigte/r</SectionTitle>
                                    <p className="mb-5 text-sm text-slate-600">
                                        Für Minderjährige stellt ein/e Erziehungsberechtigte/r den Antrag. Die
                                        Bestätigungs-E-Mail geht an die hier angegebene Adresse.
                                    </p>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <Field label="Vorname" required error={errors["guardian.firstName"]}>
                                            <input className={inputClass} required {...bindInput("guardian", "firstName")}/>
                                        </Field>
                                        <Field label="Nachname" required error={errors["guardian.lastName"]}>
                                            <input className={inputClass} required {...bindInput("guardian", "lastName")}/>
                                        </Field>
                                        <Field label="E-Mail" required error={errors["guardian.email"]}>
                                            <input className={inputClass} type="email" required
                                                   {...bindInput("guardian", "email")}/>
                                        </Field>
                                        <Field label="Telefon" error={errors["guardian.phone"]}>
                                            <input className={inputClass} type="tel" {...bindInput("guardian", "phone")}/>
                                        </Field>
                                    </div>
                                </section>
                            )}

                            <section className="border-t border-slate-200 pt-8">
                                <SectionTitle number="3">SEPA-Lastschriftmandat</SectionTitle>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="sm:col-span-2">
                                        <Field label="Kontoinhaber/in" required error={errors["bank.accountHolder"]}>
                                            <input className={inputClass} autoComplete="name" required
                                                   {...bindInput("bank", "accountHolder")}/>
                                        </Field>
                                    </div>
                                    <Field label="IBAN" required error={errors["bank.iban"]}>
                                        <input className={inputClass} placeholder="DE00 0000 0000 0000 0000 00"
                                               autoComplete="off" spellCheck={false} required {...bindInput("bank", "iban")}/>
                                    </Field>
                                    <Field label="BIC (optional)" error={errors["bank.bic"]}>
                                        <input className={inputClass} autoComplete="off" spellCheck={false}
                                               {...bindInput("bank", "bic")}/>
                                    </Field>
                                </div>
                                <div className="mt-5 text-sm text-slate-700">
                                    <Consent
                                        checked={values.consents.sepa}
                                        onChange={(checked) => setValue("consents", "sepa", checked)}
                                        required
                                        error={errors["consents.sepa"]}
                                    >
                                        {texts.sepa}
                                    </Consent>
                                </div>
                            </section>
                        </div>

                        <div className="space-y-8 lg:pl-8">
                            <section>
                                <SectionTitle number="4">Erklärungen</SectionTitle>
                                <div className="space-y-4 text-sm text-slate-700">
                                    <Consent
                                        checked={values.consents.satzung}
                                        onChange={(checked) => setValue("consents", "satzung", checked)}
                                        required
                                        error={errors["consents.satzung"]}
                                    >
                                        {texts.satzung}{" "}
                                        <SatzungDialog/>
                                    </Consent>
                                    <Consent
                                        checked={values.consents.beitrag}
                                        onChange={(checked) => setValue("consents", "beitrag", checked)}
                                        required
                                        error={errors["consents.beitrag"]}
                                    >
                                        {texts.beitrag}
                                    </Consent>
                                    <Consent
                                        checked={values.consents.datenschutz}
                                        onChange={(checked) => setValue("consents", "datenschutz", checked)}
                                        required
                                        error={errors["consents.datenschutz"]}
                                    >
                                        {texts.datenschutz}{" "}
                                        <Link className="font-semibold text-blue-700 hover:underline" to="/datenschutz"
                                              target="_blank">
                                            Datenschutzerklärung
                                        </Link>
                                    </Consent>
                                    {isMinor && (
                                        <Consent
                                            checked={values.consents.erziehungsberechtigt}
                                            onChange={(checked) => setValue("consents", "erziehungsberechtigt", checked)}
                                            required
                                            error={errors["consents.erziehungsberechtigt"]}
                                        >
                                            {texts.erziehungsberechtigt}
                                        </Consent>
                                    )}
                                    <Consent
                                        checked={values.consents.fotos}
                                        onChange={(checked) => setValue("consents", "fotos", checked)}
                                    >
                                        {texts.fotos}
                                    </Consent>
                                </div>
                            </section>

                            {selectedGroup && (
                                <aside className="rounded-lg border border-slate-200 bg-white p-5 text-sm text-slate-700">
                                    <h3 className="font-heading text-lg font-bold text-[#002f5f]">Deine Kosten</h3>
                                    <dl className="mt-3 space-y-2">
                                        <div className="flex justify-between gap-4">
                                            <dt>Aufnahmegebühr (einmalig)</dt>
                                            <dd className="font-semibold text-slate-900">
                                                {selectedGroup.admissionFee > 0 ? formatEuro(selectedGroup.admissionFee) : "entfällt"}
                                            </dd>
                                        </div>
                                        {selectedGroup.amount !== null && (
                                            <div className="flex justify-between gap-4">
                                                <dt>Beitrag ({formatInterval(selectedGroup.intervalMonths)})</dt>
                                                <dd className="font-semibold text-slate-900">{formatEuro(selectedGroup.amount)}</dd>
                                            </div>
                                        )}
                                    </dl>
                                    <p className="mt-3 text-slate-500">Beides wird per SEPA-Lastschrift eingezogen.</p>
                                </aside>
                            )}

                            <Notice title="So geht es weiter">
                                <p>
                                    Nach dem Absenden erhältst du eine E-Mail mit einem Bestätigungslink. Erst mit der
                                    Bestätigung ist der Antrag eingereicht.
                                </p>
                                <p>
                                    Über die Aufnahme entscheidet der Vorstand. Mit Annahme des Antrags beginnt die
                                    vorläufige Mitgliedschaft (§ 4 der Satzung).
                                </p>
                            </Notice>
                        </div>
                    </div>

                    <div className="mt-12">
                        {Object.keys(errors).length > 0 && (
                            <p className="mb-4 text-sm font-semibold text-red-700">
                                {errors.form ?? "Bitte prüfe die markierten Angaben."}
                            </p>
                        )}
                        <button
                            type="submit"
                            disabled={!canSubmit}
                            className="font-heading flex h-11 w-full items-center justify-center gap-3 rounded-md bg-blue-600 px-8 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                        >
                            {submissionState === "submitting" ? "Wird gesendet ..." : "Mitgliedschaft verbindlich beantragen"}
                            {submissionState !== "submitting" && <span aria-hidden="true">→</span>}
                        </button>
                        {!canSubmit && submissionState !== "submitting" && (
                            <p className="mt-4 text-sm text-slate-500">
                                Bitte fülle alle Pflichtfelder aus und bestätige die Pflicht-Erklärungen, um den Antrag
                                abzusenden.
                            </p>
                        )}
                        {submissionState === "rate_limited" && (
                            <p className="mt-4 text-sm font-semibold text-red-700">
                                Es wurden zu viele Anträge gesendet. Bitte versuche es später erneut.
                            </p>
                        )}
                        {submissionState === "error" && (
                            <p className="mt-4 text-sm font-semibold text-red-700">
                                Der Antrag konnte nicht gesendet werden. Bitte versuche es später erneut.
                            </p>
                        )}
                        <p className="mt-4 text-sm text-slate-500">* Pflichtfelder</p>
                    </div>
                </form>
            </div>
        </section>
    );
}
