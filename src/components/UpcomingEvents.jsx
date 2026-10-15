import { useEffect, useState } from "react";
import { Link } from "react-router-dom";

import { fetchCalendarEvents, fetchUpcomingEvents } from "../utils/calendar.js";

const eventDayFormatter = new Intl.DateTimeFormat("de-DE", { day: "2-digit" });
const eventMonthFormatter = new Intl.DateTimeFormat("de-DE", { month: "short" });

function isPastEvent(event) {
  const today = new Date();

  today.setHours(0, 0, 0, 0);

  return event.startDate < today;
}

function sortEventsForOverview(events) {
  const upcomingEvents = events.filter((event) => !isPastEvent(event));
  const pastEvents = events
    .filter(isPastEvent)
    .sort((firstEvent, secondEvent) => secondEvent.startDate - firstEvent.startDate);

  return [...upcomingEvents, ...pastEvents];
}

function closestYear(years, targetYear) {
  return years.reduce((closest, year) => (
    Math.abs(year - targetYear) < Math.abs(closest - targetYear) ? year : closest
  ), years[0]);
}

export default function UpcomingEvents({ includePast = false, limit, showAllLink = false }) {
  const [events, setEvents] = useState([]);
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [status, setStatus] = useState("loading");

  useEffect(() => {
    let isMounted = true;

    async function loadEvents() {
      try {
        const calendarEvents = includePast
          ? sortEventsForOverview(await fetchCalendarEvents())
          : await fetchUpcomingEvents();

        if (isMounted) {
          const limitedEvents = limit ? calendarEvents.slice(0, limit) : calendarEvents;
          setEvents(limitedEvents);
          setStatus("success");

          if (includePast && limitedEvents.length > 0) {
            const availableYears = limitedEvents.map((event) => event.startDate.getFullYear());

            setSelectedYear((currentSelection) => (
              availableYears.includes(currentSelection)
                ? currentSelection
                : closestYear(availableYears, currentSelection)
            ));
          }
        }
      } catch {
        if (isMounted) {
          setStatus("error");
        }
      }
    }

    loadEvents();

    return () => {
      isMounted = false;
    };
  }, [includePast, limit]);

  if (status === "loading") {
    return <p className="mt-5 text-sm text-slate-600">Termine werden geladen ...</p>;
  }

  if (status === "error") {
    return <p className="mt-5 text-sm text-red-700">Termine konnten nicht geladen werden.</p>;
  }

  const visibleEvents = includePast
    ? events.filter((event) => event.startDate.getFullYear() === selectedYear)
    : events;
  const yearOptions = [...new Set(events.map((event) => event.startDate.getFullYear()))].sort(
    (firstYear, secondYear) => firstYear - secondYear
  );

  if (visibleEvents.length === 0 && !includePast) {
    return <p className="mt-5 text-sm text-slate-600">Aktuell sind keine kommenden Termine eingetragen.</p>;
  }

  return (
    <div className="mt-5 space-y-5">
      {includePast && (
        <div className="flex flex-wrap gap-2">
          {yearOptions.map((year) => (
            <button
              key={year}
              type="button"
              onClick={() => setSelectedYear(year)}
              className={`h-10 rounded-md px-4 text-sm font-bold transition ${
                selectedYear === year
                  ? "bg-blue-600 text-white"
                  : "bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100"
              }`}
            >
              {year}
            </button>
          ))}
        </div>
      )}

      {visibleEvents.length === 0 && (
        <p className="text-sm text-slate-600">Für {selectedYear} sind keine Termine eingetragen.</p>
      )}

      {visibleEvents.map((event) => {
        const pastEvent = isPastEvent(event);
        const eventKey = event.id || `${event.title}-${event.startDate.toISOString()}`;

        return (
          <article
            key={eventKey}
            className={`flex gap-5 border-t border-slate-200 pt-5 first:border-t-0 first:pt-0 ${
              pastEvent ? "opacity-55" : ""
            }`}
          >
            <div className={`font-heading min-w-20 text-center ${pastEvent ? "text-slate-500" : "text-[#002f5f]"}`}>
              <div className="text-3xl font-bold">{eventDayFormatter.format(event.startDate)}</div>
              <div className="text-xs font-bold uppercase">{eventMonthFormatter.format(event.startDate)}</div>
            </div>
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h3 className="font-heading text-base font-bold text-slate-900">{event.title}</h3>
                {pastEvent && (
                  <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-600">
                    Vergangen
                  </span>
                )}
              </div>
              {event.location && (
                <p className="mt-2 text-sm leading-relaxed text-slate-600">{event.location}</p>
              )}
              {event.description && (
                <div
                  className="mt-2 text-sm leading-relaxed text-slate-600"
                  dangerouslySetInnerHTML={{ __html: event.description }}
                />
              )}
              {event.attachmentUrl && (
                <a
                  href={event.attachmentUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-3 inline-flex text-sm font-bold text-blue-700 transition hover:text-blue-900 hover:underline"
                >
                  Ausschreibung
                </a>
              )}
            </div>
          </article>
        );
      })}

      {showAllLink && (
        <Link
          to="/termine"
          className="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-blue-600 px-5 text-sm font-bold text-white transition hover:bg-blue-700"
        >
          Alle Termine ansehen
          <span aria-hidden="true">→</span>
        </Link>
      )}
    </div>
  );
}
