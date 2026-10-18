import SatzungContent from "../components/SatzungContent.jsx";
import { satzung, satzungAnchor, satzungMeta } from "../data/satzung.js";
import { satzungPdfLink } from "../utils/siteLinks.js";

export default function Satzung() {
  return (
    <section className="bg-white px-4 py-12 text-slate-900 sm:px-6 sm:py-16">
      <div className="mx-auto max-w-4xl">
        <h1 className="mb-4 wrap-break-word font-heading text-3xl font-bold leading-tight text-[#002f5f] sm:text-4xl">
          Satzung
        </h1>
        <p className="mb-8 font-sans text-slate-600 sm:mb-10">
          {satzungMeta.title} vom {satzungMeta.date}.{" "}
          <a className="text-[#002f5f] underline-offset-4 hover:underline" href={satzungPdfLink.href} download>
            Als PDF herunterladen
          </a>
        </p>

        <nav aria-label="Inhaltsverzeichnis" className="mb-10 rounded-md bg-slate-50 p-5 ring-1 ring-slate-200">
          <h2 className="mb-3 font-heading text-sm font-bold tracking-wider text-slate-950">INHALT</h2>
          <ol className="font-sans text-sm sm:columns-2 sm:gap-8">
            {satzung.map((paragraph) => (
              <li key={paragraph.number} className="break-inside-avoid py-0.5">
                <a
                  className="text-[#002f5f] underline-offset-4 hover:underline"
                  href={`#${satzungAnchor(paragraph.number)}`}
                >
                  § {paragraph.number} {paragraph.title}
                </a>
              </li>
            ))}
          </ol>
        </nav>

        <SatzungContent />

        <p className="mt-10 font-sans text-slate-700">
          {satzungMeta.place}, {satzungMeta.date}
        </p>
      </div>
    </section>
  );
}
