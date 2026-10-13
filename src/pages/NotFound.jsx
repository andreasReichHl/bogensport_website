import { Link } from "react-router-dom";

export default function NotFound() {
  return (
    <section className="flex min-h-[60vh] items-center justify-center bg-white px-5 py-16 text-center sm:px-8">
      <div>
        <p className="font-heading text-sm font-bold uppercase tracking-wide text-blue-700">404</p>
        <h1 className="mt-4 font-heading text-3xl font-bold leading-tight text-[#002f5f] sm:text-4xl">
          Seite nicht gefunden
        </h1>
        <p className="mt-4 text-base leading-relaxed text-slate-700">
          Die aufgerufene Seite existiert nicht oder wurde verschoben.
        </p>
        <Link
          className="mt-8 inline-flex h-12 items-center justify-center rounded-md bg-blue-600 px-6 text-sm font-bold text-white shadow-lg shadow-blue-950/30 transition hover:bg-blue-700"
          to="/"
        >
          Zur Startseite
        </Link>
      </div>
    </section>
  );
}
