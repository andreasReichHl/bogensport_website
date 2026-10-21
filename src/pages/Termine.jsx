import { useEffect, useState } from "react";
import { CalendarSync } from "lucide-react";

import Hero from "../components/Hero.jsx";
import UpcomingEvents from "../components/UpcomingEvents.jsx";
import { fetchCalendarSubscribeUrl } from "../utils/calendar.js";

export default function Termine() {
  const [subscribeUrl, setSubscribeUrl] = useState("");

  useEffect(() => {
    let isMounted = true;

    fetchCalendarSubscribeUrl()
      .then((url) => {
        if (isMounted) {
          setSubscribeUrl(url);
        }
      })
      .catch(() => {});

    return () => {
      isMounted = false;
    };
  }, []);

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
        <div className="relative mx-auto max-w-7xl px-5 pb-16 sm:px-8 lg:px-12 lg:pb-24">
          <article className="p-6 sm:p-10">
            <div className="flex flex-wrap items-center justify-between gap-4">
              <h1 className="font-heading text-3xl font-bold text-slate-900">Alle Termine</h1>
              {subscribeUrl && (
                <a
                  href={subscribeUrl}
                  className="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-blue-600 px-4 text-sm font-bold text-blue-700 transition hover:bg-blue-50"
                >
                  <CalendarSync className="h-4 w-4" aria-hidden="true" />
                  Kalender abonnieren
                </a>
              )}
            </div>
            <div className="mt-6 max-w-4xl">
              <UpcomingEvents includePast />
            </div>
          </article>
        </div>
      </main>
    </>
  );
}
