export const siteConfig = {
  name: "Bad Oldesloer Bogensport e.V.",
  shortName: "Bogensport Bad Oldesloe e.V.",
  brandLines: ["BAD OLDESLOER", "BOGENSPORT e.V."],
  domain: "badoldesloer-bogensport.de",
  websiteUrl: "https://www.badoldesloer-bogensport.de",
  websiteLabel: "www.badoldesloer-bogensport.de",
  address: {
    street: "Postfach 11 02",
    postalCode: "23831",
    city: "Bad Oldesloe",
  },
  emails: {
    info: "info@badoldesloer-bogensport.de",
    board: "vorstand@badoldesloer-bogensport.de",
    webmaster: "webmaster@badoldesloer-bogensport.de",
  },
};

export function mailto(email) {
  return `mailto:${email}`;
}
