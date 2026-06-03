const calendarUrl =
  import.meta.env.VITE_CALENDAR_ICS_URL ||
  "https://nx92511.your-storageshare.de/remote.php/dav/public-calendars/5JpmMnqbqQrMCyYL?export";
const proxyUrls = [
  `https://api.codetabs.com/v1/proxy?quest=${encodeURIComponent(calendarUrl)}`,
  `https://api.allorigins.win/get?url=${encodeURIComponent(calendarUrl)}`,
];

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

function decodeBase64DataUri(dataUri) {
  const base64Marker = ";base64,";
  const base64Index = dataUri.indexOf(base64Marker);

  if (base64Index === -1) {
    return decodeURIComponent(dataUri.split(",", 2)[1] || "");
  }

  const binaryString = atob(dataUri.slice(base64Index + base64Marker.length));
  const bytes = Uint8Array.from(binaryString, (character) => character.charCodeAt(0));

  return new TextDecoder("utf-8").decode(bytes);
}

async function fetchCalendarText() {
  const urls = [calendarUrl, ...proxyUrls];
  let lastError = null;

  for (const url of urls) {
    try {
      const response = await fetch(url);

      if (!response.ok) {
        throw new Error(`Calendar request failed with status ${response.status}`);
      }

      const contentType = response.headers.get("content-type") || "";

      if (contentType.includes("application/json")) {
        const data = await response.json();

        if (typeof data.contents !== "string") {
          throw new Error("Calendar proxy returned invalid data");
        }

        if (data.contents.startsWith("data:")) {
          return decodeBase64DataUri(data.contents);
        }

        return data.contents;
      }

      return response.text();
    } catch (error) {
      lastError = error;
    }
  }

  throw lastError || new Error("Calendar request failed");
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
