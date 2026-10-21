import DatenschutzContent from "../components/DatenschutzContent.jsx";

export default function Datenschutz() {
  return (
    <section className="bg-slate-50 px-4 py-12 text-slate-900 sm:px-6 sm:py-16">
      <div className="mx-auto max-w-4xl">
        <h1 className="mb-8 wrap-break-word font-heading text-3xl font-bold leading-tight text-[#002f5f] sm:mb-10 sm:text-4xl">
          Datenschutzerklärung
        </h1>

        <DatenschutzContent />
      </div>
    </section>
  );
}
