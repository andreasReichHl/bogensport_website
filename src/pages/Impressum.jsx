import { mailto, siteConfig } from "../utils/siteConfig.js";

export default function Impressum() {
  return (
    <section className="bg-white px-4 py-12 text-slate-900 sm:px-6 sm:py-16">
      <div className="mx-auto max-w-4xl">
        <h1 className="mb-8 wrap-break-word font-heading text-3xl font-bold leading-tight text-[#002f5f] sm:mb-10 sm:text-4xl">
          Impressum
        </h1>

        <div className="space-y-8 font-sans text-base leading-relaxed text-slate-700">
          <section>
            <h2 className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">{siteConfig.name}</h2>
            <dl className="space-y-2">
              <div>
                <dt className="font-semibold text-slate-950">Vereinsregister</dt>
                <dd>Amtsgericht Lübeck VR 3189 HL</dd>
              </div>
              <div>
                <dt className="font-semibold text-slate-950">Gegründet</dt>
                <dd>12.2009</dd>
              </div>
            </dl>
          </section>

          <section>
            <h2 className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">Anschrift</h2>
            <address className="not-italic">
              {siteConfig.name}
              <br />
              {siteConfig.address.street}
              <br />
              {siteConfig.address.postalCode} {siteConfig.address.city}
            </address>
          </section>

          <section>
            <h2 className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">Vorstand</h2>
            <ul className="space-y-1">
              <li>1. Vorsitzender: Andreas Reich</li>
              <li>2. Vorsitzende: Svenja Petschelies</li>
              <li>Kassenwart: Rolf Pollnau</li>
            </ul>
          </section>

          <section>
            <h2 className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">Internet</h2>
            <ul className="space-y-1">
              <li>
                Internetadresse:{" "}
                <a className="text-[#002f5f] underline-offset-4 hover:underline" href={siteConfig.websiteUrl}>
                  {siteConfig.websiteLabel}
                </a>
              </li>
              <li>
                Vereins-Mailadresse:{" "}
                <a className="text-[#002f5f] underline-offset-4 hover:underline" href={mailto(siteConfig.emails.board)}>
                  {siteConfig.emails.board}
                </a>
              </li>
              <li>
                Webmaster-Mailadresse:{" "}
                <a className="text-[#002f5f] underline-offset-4 hover:underline" href={mailto(siteConfig.emails.webmaster)}>
                  {siteConfig.emails.webmaster}
                </a>
              </li>
            </ul>
          </section>

          <section>
            <h2 className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">Haftung für Links</h2>
            <p>
              Der Bad Oldesloer Bogensport hat auf seinen Seiten Links zu anderen Seiten im Internet gelegt. Wir
              erklären hiermit ausdrücklich, dass wir keinerlei Einfluss auf die Gestaltung und die Inhalte der
              gelinkten Seiten haben. Deshalb distanzieren wir uns hiermit ausdrücklich von allen Inhalten aller auf
              unserer Homepage gelinkten Seiten inklusive aller Unterseiten und machen uns diese Inhalte nicht zu
              Eigen. Diese Erklärung gilt für alle auf unserer Homepage angezeigten Links und für alle Inhalte der
              Seiten, zu denen die von uns angemeldeten Banner und Links führen. Wir übernehmen keine Verantwortung
              für Korrektheit oder Aktualität der gemachten Angaben.
            </p>
          </section>

          <section className="text-sm text-slate-500">
            <h2 className="mb-3 wrap-break-word font-heading text-base font-bold text-slate-700">Hinweis zur Sprache</h2>
            <p>
              Zur besseren Lesbarkeit wird auf dieser Internetpräsenz das generische Maskulinum verwendet. Alle
              verwendeten Personenbezeichnungen beziehen sich gleichermaßen auf alle Geschlechter.
            </p>
          </section>
        </div>
      </div>
    </section>
  );
}
