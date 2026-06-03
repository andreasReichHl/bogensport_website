import Hero from "../components/Hero.jsx";
import UpcomingEvents from "../components/UpcomingEvents.jsx";

export default function Termine() {
  return (
    <>
      <Hero
        title={(
          <>
            Aktuelle
            <br />
            Termine.
          </>
        )}
        subtitle="Turniere, Meisterschaften und Vereinsveranstaltungen"
        description="Hier findest du aktuelle und vergangene Termine aus unserem öffentlichen Vereinskalender."
        actions={[]}
      />

      <main className="bg-slate-50">
        <div className="relative max-w-8xl px-5 pb-16 sm:px-8 lg:px-12 lg:pb-24">
          <article className="p-6 sm:p-10">
            <h1 className="font-heading text-3xl font-bold text-slate-900">Alle Termine</h1>
            <div className="mt-6 max-w-4xl">
              <UpcomingEvents includePast />
            </div>
          </article>
        </div>
      </main>
    </>
  );
}
