// VITE_CALENDAR_ICS_URL is an escape hatch for pointing at an ICS feed directly instead of
// going through the same-origin calendar-proxy.php. If it's set to a cross-origin URL, that
// URL must itself allow CORS (or be routed through a same-origin proxy) - we deliberately
// don't fall back to free public CORS-proxy services here (no SLA/rate-limit guarantees, and
// routing calendar data through an unrelated third party isn't something to do silently).
const calendarUrl =
  import.meta.env.VITE_CALENDAR_ICS_URL ||
  "/calendar-proxy.php";

function unfoldIcsLines(icsText) {
  return icsText.replace(/\r?\n[ \t]/g, "").split(/\r?\n/);
}

function getPropertyName(line) {
  return line.split(":", 1)[0].split(";", 1)[0];
}

function getPropertyValue(line) {
  const separatorIndex = line.indexOf(":");

  if (separatorIndex === -1) {
    return "";
  }

  return line.slice(separatorIndex + 1);
}

function decodeIcsText(value) {
  return value
    .replace(/\\n/g, "\n")
    .replace(/\\,/g, ",")
    .replace(/\\;/g, ";")
    .replace(/\\\\/g, "\\");
}

function extractFirstUrl(value) {
  return value.match(/https?:\/\/\S+/)?.[0] || "";
}

function removeUrls(value) {
  return value.replace(/https?:\/\/\S+/g, "").trim();
}

function parseIcsDate(value) {
  if (!value) {
    return null;
  }

  const datePart = value.slice(0, 8);
  const year = Number(datePart.slice(0, 4));
  const month = Number(datePart.slice(4, 6)) - 1;
  const day = Number(datePart.slice(6, 8));

  if (!year || month < 0 || !day) {
    return null;
  }

  if (value.includes("T")) {
    const hour = Number(value.slice(9, 11));
    const minute = Number(value.slice(11, 13));

    return new Date(year, month, day, hour, minute);
  }

  return new Date(year, month, day);
}

function parseEvent(lines) {
  const event = {};

  lines.forEach((line) => {
    const property = getPropertyName(line);
    const value = getPropertyValue(line);

    if (property === "UID") {
      event.id = value;
    }

    if (property === "SUMMARY") {
      event.title = decodeIcsText(value);
    }

    if (property === "DESCRIPTION") {
      const description = decodeIcsText(value);

      event.link = extractFirstUrl(description);
      event.description = removeUrls(description);
    }

    if (property === "LOCATION") {
      event.location = decodeIcsText(value);
    }

    if (property === "DTSTART") {
      event.startDate = parseIcsDate(value);
    }

    if (property === "DTEND") {
      event.endDate = parseIcsDate(value);
    }
  });

  return event;
}

function parseIcsEvents(icsText) {
  const lines = unfoldIcsLines(icsText);
  const events = [];
  let currentEvent = null;

  lines.forEach((line) => {
    if (line === "BEGIN:VEVENT") {
      currentEvent = [];
      return;
    }

    if (line === "END:VEVENT") {
      if (currentEvent) {
        events.push(parseEvent(currentEvent));
      }

      currentEvent = null;
      return;
    }

    if (currentEvent) {
      currentEvent.push(line);
    }
  });

  return events;
}

async function fetchCalendarText() {
  const response = await fetch(calendarUrl);

  if (!response.ok) {
    throw new Error(`Calendar request failed with status ${response.status}`);
  }

  return response.text();
}

export function formatEventDate(date) {
  if (!date) {
    return "";
  }

  return new Intl.DateTimeFormat("de-DE", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  }).format(date);
}

export async function fetchUpcomingEvents() {
  const events = await fetchCalendarEvents();
  const today = new Date();

  today.setHours(0, 0, 0, 0);

  return events.filter((event) => event.startDate >= today);
}

export async function fetchCalendarEvents() {
  const icsText = await fetchCalendarText();

  return parseIcsEvents(icsText)
    .filter((event) => event.startDate)
    .sort((firstEvent, secondEvent) => firstEvent.startDate - secondEvent.startDate);
}
