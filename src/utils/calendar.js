async function fetchFeedData() {
  const response = await fetch("/termine-feed.php", { cache: "no-store" });

  if (!response.ok) {
    throw new Error(`Calendar request failed with status ${response.status}`);
  }

  return response.json();
}

function parseEvent(rawEvent) {
  return {
    id: rawEvent.id,
    title: rawEvent.title || "",
    startDate: rawEvent.start ? new Date(rawEvent.start) : null,
    endDate: rawEvent.end ? new Date(rawEvent.end) : null,
    allDay: Boolean(rawEvent.allDay),
    location: rawEvent.location || "",
    description: rawEvent.description || "",
    attachmentUrl: rawEvent.attachmentUrl || "",
    // Deliberately a plain https:// link, not webcal://: webcal: signals an ongoing calendar
    // *subscription* (a live-updating feed), which is the wrong semantics for a one-off single
    // event - and since every event has its own ics_link, it would leave one permanent
    // subscribed calendar per event a visitor ever added. A plain https:// link to a
    // single-event .ics is a one-time "add this event?" import instead.
    icsUrl: rawEvent.icsUrl || "",
  };
}

export async function fetchUpcomingEvents() {
  const events = await fetchCalendarEvents();
  const today = new Date();

  today.setHours(0, 0, 0, 0);

  return events.filter((event) => event.startDate >= today);
}

export async function fetchCalendarEvents() {
  const data = await fetchFeedData();
  const rawEvents = Array.isArray(data.events) ? data.events : [];

  return rawEvents
    .map(parseEvent)
    .filter((event) => event.startDate && !Number.isNaN(event.startDate.getTime()))
    .sort((firstEvent, secondEvent) => firstEvent.startDate - secondEvent.startDate);
}

// Calendar-level subscription feed (webcal://) - distinct from an individual event's icsUrl.
// Subscribing creates one live, auto-updating calendar entry for all public Turniere events,
// rather than a one-time import of a single event.
export async function fetchCalendarSubscribeUrl() {
  const data = await fetchFeedData();

  return data.subscribeUrl || "";
}
