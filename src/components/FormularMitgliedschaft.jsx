import {useEffect, useRef, useState} from "react";
import {Field, inputClass, SectionTitle} from "./FormFields.jsx";
import Notice from "./Notice.jsx";
import DatenschutzDialog from "./DatenschutzDialog.jsx";
import SatzungDialog from "./SatzungDialog.jsx";
import {satzungMeta} from "../data/satzung.js";
import {
    ageOn,
    fetchMembershipForm,
    formatEuro,
    formatInterval,
    isValidBic,
    isValidIban,
    submitMembershipApplication,
} from "../utils/membership.js";
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
        privatePhone: "",
    },
    guardian: {firstName: "", lastName: "", email: "", phone: ""},
    managedBy: "",
    bank: {accountHolder: "", iban: "", bic: ""},
    consents: {satzung: false, beitrag: false, datenschutz: false, sepa: false, erziehungsberechtigt: false, fotos: false},
    website: "",
};

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Short names for the "still missing" list above the submit button, in form order.
const fieldLabels = {
    groupId: "Mitgliedsart",
    "applicant.salutation": "Anrede",
    "applicant.gender": "Geschlecht",
    "applicant.firstName": "Vorname",
    "applicant.lastName": "Nachname",
    "applicant.dateOfBirth": "Geburtsdatum",
    "applicant.street": "Straße und Hausnummer",
    "applicant.zip": "PLZ",
    "applicant.city": "Ort",
    "applicant.email": "E-Mail",
    "applicant.phone": "Mobil",
    "applicant.privatePhone": "Telefon privat",
    managedBy: "Verwaltung der Mitgliedschaft",
    "guardian.firstName": "Vorname Erziehungsberechtigte/r",
    "guardian.lastName": "Nachname Erziehungsberechtigte/r",
    "guardian.email": "E-Mail Erziehungsberechtigte/r",
    "guardian.phone": "Telefon Erziehungsberechtigte/r",
    "bank.accountHolder": "Kontoinhaber/in",
    "bank.iban": "IBAN",
    "bank.bic": "BIC",
    "consents.sepa": "SEPA-Lastschriftmandat",
    "consents.satzung": "Erklärung zur Satzung",
    "consents.beitrag": "Erklärung zu Beitrag und Aufnahmegebühr",
    "consents.datenschutz": "Erklärung zum Datenschutz",
    "consents.erziehungsberechtigt": "Erklärung der Erziehungsberechtigten",
};

/**
 * Checks the form with the same rules and messages as membership_validate_application() on the
 * server, so people see what is missing before sending. The server still validates everything.
 */
function validateForm(values, {age, isMinor, guardianRequired, selectedGroup, minimumAge}) {
    const {applicant, guardian, bank, consents} = values;
    const errors = {};
    const blank = (value) => value.trim() === "";

    if (selectedGroup === null) {
        errors.groupId = "Bitte eine Mitgliedsart auswählen.";
    } else if (age !== null && selectedGroup.maxAge !== null && age >= selectedGroup.maxAge) {
        errors.groupId = `Die Mitgliedsart „${selectedGroup.name}“ ist nur bis ${selectedGroup.maxAge} Jahre möglich.`;
    }

    if (applicant.gender === "") {
        errors["applicant.gender"] = "Bitte Geschlecht auswählen (wird für die Meldung an die Sportverbände benötigt).";
    }

    if (blank(applicant.firstName)) errors["applicant.firstName"] = "Vorname fehlt.";
    if (blank(applicant.lastName)) errors["applicant.lastName"] = "Nachname fehlt.";

    if (age === null || age > 120) {
        errors["applicant.dateOfBirth"] = "Bitte ein gültiges Geburtsdatum angeben.";
    } else if (age < minimumAge) {
        errors["applicant.dateOfBirth"] = `Eine Mitgliedschaft ist ab ${minimumAge} Jahren möglich.`;
    }

    if (blank(applicant.street)) errors["applicant.street"] = "Straße und Hausnummer fehlt.";
    if (!/^\d{5}$/.test(applicant.zip.trim())) errors["applicant.zip"] = "Bitte eine fünfstellige Postleitzahl angeben.";
    if (blank(applicant.city)) errors["applicant.city"] = "Ort fehlt.";

    if (!blank(applicant.email) && !emailPattern.test(applicant.email.trim())) {
        errors["applicant.email"] = "Bitte eine gültige E-Mail-Adresse angeben.";
    } else if (blank(applicant.email) && !isMinor) {
        errors["applicant.email"] = "E-Mail-Adresse fehlt.";
    }

    if (guardianRequired) {
        if (values.managedBy === "") {
            errors.managedBy = "Bitte auswählen, wie die Mitgliedschaft verwaltet werden soll.";
        }

        if (blank(guardian.firstName)) errors["guardian.firstName"] = "Vorname des/der Erziehungsberechtigten fehlt.";
        if (blank(guardian.lastName)) errors["guardian.lastName"] = "Nachname des/der Erziehungsberechtigten fehlt.";

        if (!emailPattern.test(guardian.email.trim())) {
            errors["guardian.email"] = "Bitte eine gültige E-Mail-Adresse des/der Erziehungsberechtigten angeben.";
        }
    }

    if (blank(bank.accountHolder)) errors["bank.accountHolder"] = "Kontoinhaber/in fehlt.";
    if (!isValidIban(bank.iban)) errors["bank.iban"] = "Bitte eine gültige IBAN angeben.";

    if (!blank(bank.bic) && !isValidBic(bank.bic)) {
        errors["bank.bic"] = "Bitte eine gültige BIC angeben oder das Feld leer lassen.";
    }

    const requiredConsents = ["sepa", "satzung", "beitrag", "datenschutz", ...(guardianRequired ? ["erziehungsberechtigt"] : [])];

    for (const consent of requiredConsents) {
        if (!consents[consent]) {
            errors[`consents.${consent}`] = "Bitte bestätigen.";
        }
    }

    return errors;
}

function scrollToField(key) {
    const element = document.querySelector(`[data-field="${key}"]`);

    if (!element) {
        return;
    }

    element.scrollIntoView({behavior: "smooth", block: "center"});
    element.querySelector("input, select")?.focus({preventScroll: true});
}

const paperApplicationLink = downloadLinks.find((link) => link.label === "Mitgliedsantrag");

function Consent({field, checked, onChange, required, error, children}) {
    return (
        <div data-field={field}>
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
    const [serverErrors, setServerErrors] = useState({});
    // Field messages show up only after the first send attempt, then update live while typing.
    const [attempted, setAttempted] = useState(false);
    const [submissionState, setSubmissionState] = useState("idle");
    const successRef = useRef(null);

    useEffect(() => {
        fetchMembershipForm()
            .then((data) => {
                setConfig(data);
                setConfigState("ready");
            })
            .catch(() => setConfigState("error"));
    }, []);

    // The success notice replaces the form, so scroll to it once it is rendered.
    useEffect(() => {
        if (submissionState === "success") {
            successRef.current?.scrollIntoView({behavior: "smooth", block: "start"});
        }
    }, [submissionState]);

    const age = ageOn(values.applicant.dateOfBirth);
    const isMinor = age !== null && age < 18;
    const selectedGroup = config?.groups.find((group) => String(group.id) === values.groupId) ?? null;
    // The guardian part shows up for minors and as soon as an age-limited group (Kinder &
    // Jugendliche) is picked - even before the date of birth is entered.
    const needsGuardian = isMinor || (selectedGroup?.maxAge ?? null) !== null;
    // An adult who picked an age-limited group gets the group error instead of guardian errors.
    const guardianRequired = isMinor || (age === null && needsGuardian);
    const confirmationEmail = needsGuardian ? values.guardian.email : values.applicant.email;
    const clientErrors = config
        ? validateForm(values, {age, isMinor, guardianRequired, selectedGroup, minimumAge: config.minimumAge})
        : {};
    const errors = attempted ? {...serverErrors, ...clientErrors} : serverErrors;
    const missingFields = Object.keys(errors).filter((key) => key in fieldLabels);

    function changeValues(update) {
        setValues(update);
        // The server's answer refers to the old values; only a "form" message stays.
        setServerErrors((current) => (current.form ? {form: current.form} : {}));
    }

    function setValue(section, key, value) {
        changeValues((current) => ({...current, [section]: {...current[section], [key]: value}}));
    }

    function bindInput(section, key) {
        return {
            value: values[section][key],
            onChange: (event) => setValue(section, key, event.target.value),
        };
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setAttempted(true);

        const firstError = Object.keys(fieldLabels).find((key) => key in clientErrors);

        if (firstError) {
            setSubmissionState("idle");
            scrollToField(firstError);
            return;
        }

        setSubmissionState("submitting");
        setServerErrors({});

        const result = await submitMembershipApplication({
            ...values,
            groupId: Number(values.groupId),
            guardian: needsGuardian ? values.guardian : null,
            managedBy: needsGuardian ? values.managedBy : null,
            satzungVersion: satzungMeta.version,
            textsVersion: config.texts.version,
        });

        if (result.status === "ok") {
            setSubmissionState("success");
            return;
        }

        if (result.status === "invalid") {
            setServerErrors(result.errors);
            const firstServerError = Object.keys(fieldLabels).find((key) => key in result.errors);

            if (firstServerError) {
                scrollToField(firstServerError);
            }
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
            <section ref={successRef} className="scroll-mt-4 bg-slate-50">
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
                <form onSubmit={handleSubmit} noValidate className="relative z-10 p-5 sm:p-8">
                    {/* Honeypot: hidden from people, bots tend to fill it. */}
                    <div className="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                        <label>
                            Website
                            <input
                                tabIndex={-1}
                                autoComplete="off"
                                value={values.website}
                                onChange={(event) => changeValues((current) => ({...current, website: event.target.value}))}
                            />
                        </label>
                    </div>

                    <div className="grid gap-8 lg:grid-cols-[1.45fr_1fr] lg:divide-x lg:divide-slate-200">
                        <div className="space-y-8 lg:pr-8">
                            <section data-field="groupId">
                                <SectionTitle number="1">Mitgliedsart</SectionTitle>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {config.groups.map((group) => (
                                        <GroupOption
                                            key={group.id}
                                            group={group}
                                            checked={values.groupId === String(group.id)}
                                            onSelect={() => changeValues((current) => ({...current, groupId: String(group.id)}))}
                                        />
                                    ))}
                                </div>
                                {errors.groupId && <p className="mt-2 text-sm text-red-700">{errors.groupId}</p>}
                            </section>

                            <section className="border-t border-slate-200 pt-8">
                                <SectionTitle number="2">Persönliche Daten</SectionTitle>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field field="applicant.salutation" label="Anrede" error={errors["applicant.salutation"]}>
                                        <select className={inputClass} {...bindInput("applicant", "salutation")}>
                                            <option value="">Keine Angabe</option>
                                            <option>Herr</option>
                                            <option>Frau</option>
                                        </select>
                                    </Field>
                                    <Field field="applicant.gender" label="Geschlecht" required error={errors["applicant.gender"]}>
                                        <select className={inputClass} required {...bindInput("applicant", "gender")}>
                                            <option value="" disabled>Bitte auswählen</option>
                                            <option value="w">weiblich</option>
                                            <option value="m">männlich</option>
                                            <option value="d">divers</option>
                                        </select>
                                    </Field>
                                    <Field field="applicant.firstName" label="Vorname" required error={errors["applicant.firstName"]}>
                                        <input className={inputClass} autoComplete="given-name" required
                                               {...bindInput("applicant", "firstName")}/>
                                    </Field>
                                    <Field field="applicant.lastName" label="Nachname" required error={errors["applicant.lastName"]}>
                                        <input className={inputClass} autoComplete="family-name" required
                                               {...bindInput("applicant", "lastName")}/>
                                    </Field>
                                    <Field field="applicant.dateOfBirth" label="Geburtsdatum" required error={errors["applicant.dateOfBirth"]}>
                                        <input className={inputClass} type="date" autoComplete="bday" required
                                               {...bindInput("applicant", "dateOfBirth")}/>
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <Field field="applicant.street" label="Straße und Hausnummer" required error={errors["applicant.street"]}>
                                            <input className={inputClass} autoComplete="street-address" required
                                                   {...bindInput("applicant", "street")}/>
                                        </Field>
                                    </div>
                                    <Field field="applicant.zip" label="PLZ" required error={errors["applicant.zip"]}>
                                        <input className={inputClass} autoComplete="postal-code" inputMode="numeric"
                                               pattern="\d{5}" maxLength={5} required {...bindInput("applicant", "zip")}/>
                                    </Field>
                                    <Field field="applicant.city" label="Ort" required error={errors["applicant.city"]}>
                                        <input className={inputClass} autoComplete="address-level2" required
                                               {...bindInput("applicant", "city")}/>
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <Field field="applicant.email" label={isMinor ? "E-Mail (optional)" : "E-Mail"} required={!isMinor}
                                               error={errors["applicant.email"]}
                                               hint="Du kannst dieselbe E-Mail-Adresse für mehrere Familienmitglieder verwenden.">
                                            <input className={inputClass} type="email" autoComplete="email"
                                                   placeholder="beispiel@domain.de" required={!isMinor}
                                                   {...bindInput("applicant", "email")}/>
                                        </Field>
                                    </div>
                                    <Field field="applicant.phone" label="Mobil" error={errors["applicant.phone"]}>
                                        <input className={inputClass} type="tel" autoComplete="tel"
                                               placeholder="z. B. 0176 12345678" {...bindInput("applicant", "phone")}/>
                                    </Field>
                                    <Field field="applicant.privatePhone" label="Telefon privat" error={errors["applicant.privatePhone"]}>
                                        <input className={inputClass} type="tel" autoComplete="home tel"
                                               placeholder="z. B. 04531 123456" {...bindInput("applicant", "privatePhone")}/>
                                    </Field>
                                </div>
                            </section>

                            {needsGuardian && (
                                <section className="border-t border-slate-200 pt-8">
                                    <SectionTitle number="2a">Erziehungsberechtigte/r</SectionTitle>
                                    <p className="mb-5 text-sm text-slate-600">
                                        Für Minderjährige stellt ein/e Erziehungsberechtigte/r den Antrag. Die
                                        Bestätigungs-E-Mail geht an die hier angegebene Adresse.
                                    </p>
                                    <p className="mb-5 text-sm text-slate-600">
                                        Du möchtest selbst auch Mitglied werden? Dann stelle bitte zuerst deinen eigenen
                                        Antrag und danach den Antrag für dein Kind bzw. deine/n Jugendliche/n – mit derselben
                                        E-Mail-Adresse.
                                    </p>
                                    <fieldset data-field="managedBy" className="mb-5 space-y-3 text-sm text-slate-700">
                                        <legend className="mb-3 font-semibold">
                                            Wie soll die Mitgliedschaft verwaltet werden? *
                                        </legend>
                                        {["eltern", "kind"].map((option) => (
                                            <label key={option} className="flex items-start gap-3">
                                                <input
                                                    className="mt-0.5 h-5 w-5 shrink-0 accent-blue-600"
                                                    type="radio"
                                                    name="managedBy"
                                                    value={option}
                                                    checked={values.managedBy === option}
                                                    onChange={() => changeValues((current) => ({...current, managedBy: option}))}
                                                    required
                                                />
                                                <span>{texts[`verwaltung_${option}`]}</span>
                                            </label>
                                        ))}
                                        {errors.managedBy && <p className="text-red-700">{errors.managedBy}</p>}
                                    </fieldset>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <Field field="guardian.firstName" label="Vorname" required error={errors["guardian.firstName"]}>
                                            <input className={inputClass} required {...bindInput("guardian", "firstName")}/>
                                        </Field>
                                        <Field field="guardian.lastName" label="Nachname" required error={errors["guardian.lastName"]}>
                                            <input className={inputClass} required {...bindInput("guardian", "lastName")}/>
                                        </Field>
                                        <Field
                                            field="guardian.email"
                                            label="E-Mail"
                                            hint={values.managedBy === "eltern"
                                                ? "Die Adresse, unter der du Mitglied bist oder deinen eigenen Antrag gestellt hast."
                                                : undefined}
                                            required
                                            error={errors["guardian.email"]}
                                        >
                                            <input className={inputClass} type="email" required
                                                   {...bindInput("guardian", "email")}/>
                                        </Field>
                                        <Field field="guardian.phone" label="Telefon" error={errors["guardian.phone"]}>
                                            <input className={inputClass} type="tel" {...bindInput("guardian", "phone")}/>
                                        </Field>
                                    </div>
                                </section>
                            )}

                            <section className="border-t border-slate-200 pt-8">
                                <SectionTitle number="3">SEPA-Lastschriftmandat</SectionTitle>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="sm:col-span-2">
                                        <Field field="bank.accountHolder" label="Kontoinhaber/in" required error={errors["bank.accountHolder"]}>
                                            <input className={inputClass} autoComplete="name" required
                                                   {...bindInput("bank", "accountHolder")}/>
                                        </Field>
                                    </div>
                                    <Field field="bank.iban" label="IBAN" required error={errors["bank.iban"]}>
                                        <input className={inputClass} placeholder="DE00 0000 0000 0000 0000 00"
                                               autoComplete="off" spellCheck={false} required {...bindInput("bank", "iban")}/>
                                    </Field>
                                    <Field field="bank.bic" label="BIC (optional)" error={errors["bank.bic"]}>
                                        <input className={inputClass} autoComplete="off" spellCheck={false}
                                               {...bindInput("bank", "bic")}/>
                                    </Field>
                                </div>
                                <div className="mt-5 text-sm text-slate-700">
                                    <Consent
                                        field="consents.sepa"
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
                                        field="consents.satzung"
                                        checked={values.consents.satzung}
                                        onChange={(checked) => setValue("consents", "satzung", checked)}
                                        required
                                        error={errors["consents.satzung"]}
                                    >
                                        {texts.satzung}{" "}
                                        <SatzungDialog/>
                                    </Consent>
                                    <Consent
                                        field="consents.beitrag"
                                        checked={values.consents.beitrag}
                                        onChange={(checked) => setValue("consents", "beitrag", checked)}
                                        required
                                        error={errors["consents.beitrag"]}
                                    >
                                        {texts.beitrag}
                                    </Consent>
                                    <Consent
                                        field="consents.datenschutz"
                                        checked={values.consents.datenschutz}
                                        onChange={(checked) => setValue("consents", "datenschutz", checked)}
                                        required
                                        error={errors["consents.datenschutz"]}
                                    >
                                        {texts.datenschutz}{" "}
                                        <DatenschutzDialog/>
                                    </Consent>
                                    {needsGuardian && (
                                        <Consent
                                            field="consents.erziehungsberechtigt"
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

                            <Notice title="Lieber auf Papier?">
                                <PaperApplicationHint/>
                            </Notice>
                        </div>
                    </div>

                    <div className="mt-12">
                        {errors.form && <p className="mb-4 text-sm font-semibold text-red-700">{errors.form}</p>}
                        {missingFields.length > 0 && (
                            <div className="mb-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                                <p className="font-semibold">Bitte prüfe noch folgende Angaben:</p>
                                <ul className="mt-2 list-disc space-y-1 pl-5">
                                    {missingFields.map((key) => (
                                        <li key={key}>
                                            <button type="button" className="text-left underline hover:text-red-950"
                                                    onClick={() => scrollToField(key)}>
                                                {fieldLabels[key]}
                                            </button>
                                            {" – "}{errors[key]}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <button
                            type="submit"
                            disabled={submissionState === "submitting"}
                            className="font-heading flex h-11 w-full items-center justify-center gap-3 rounded-md bg-blue-600 px-8 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                        >
                            {submissionState === "submitting" ? "Wird gesendet ..." : "Mitgliedschaft verbindlich beantragen"}
                            {submissionState !== "submitting" && <span aria-hidden="true">→</span>}
                        </button>
                        {submissionState === "duplicate" && (
                            <p className="mt-4 text-sm font-semibold text-red-700">
                                Für diese Person liegt bereits ein Antrag vor. Bitte prüfe dein Postfach (auch den
                                Spam-Ordner) auf die Bestätigungs-E-Mail.
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
