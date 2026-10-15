async function fetchEventsFromFeed() {
  const response = await fetch("/termine-feed.php", { cache: "no-store" });

  if (!response.ok) {
    throw new Error(`Calendar request failed with status ${response.status}`);
  }

  const data = await response.json();

  return Array.isArray(data.events) ? data.events : [];
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
  };
}

export async function fetchUpcomingEvents() {
  const events = await fetchCalendarEvents();
  const today = new Date();

  today.setHours(0, 0, 0, 0);

  return events.filter((event) => event.startDate >= today);
}

export async function fetchCalendarEvents() {
  const rawEvents = await fetchEventsFromFeed();

  return rawEvents
    .map(parseEvent)
    .filter((event) => event.startDate && !Number.isNaN(event.startDate.getTime()))
    .sort((firstEvent, secondEvent) => firstEvent.startDate - secondEvent.startDate);
}
