import logoWhite from "../assets/bobs_logo_white_old.svg";
import { Link } from "react-router-dom";

import { mailto, siteConfig } from "../utils/siteConfig.js";
import { downloadLinks, footerNavigationLinks } from "../utils/siteLinks.js";

function Icon({ children }) {
  return (
    <span className="flex h-5 w-5 shrink-0 items-center justify-center text-blue-400">
      {children}
    </span>
  );
}

export default function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="font-heading bg-[#002f5f] text-white">
      <div className="mx-auto grid max-w-7xl gap-10 px-6 py-9 sm:grid-cols-2 lg:grid-cols-[1.4fr_0.9fr_0.9fr_1.3fr] lg:gap-12">
        <div>
          <div className="mb-4 flex items-center gap-3">
            <span className="block h-12 w-12 shrink-0 overflow-hidden">
              <img
                src={logoWhite}
                alt={siteConfig.shortName}
                className="h-full w-auto max-w-none"
              />
            </span>
            <div className="leading-tight">
              <div className="text-sm font-bold tracking-wide">{siteConfig.brandLines[0]}</div>
              <div className="text-lg tracking-wide">{siteConfig.brandLines[1]}</div>
            </div>
          </div>
          <p className="max-w-64 text-sm leading-relaxed text-white/85">
            Bogensport in Bad Oldesloe - konzentriert, präzise, gemeinsam.
          </p>
        </div>

        <div className="lg:border-l lg:border-white/15 lg:pl-10">
          <h2 className="mb-4 text-sm font-bold tracking-wider">NAVIGATION</h2>
          <ul className="space-y-1 text-sm text-white/85">
            {footerNavigationLinks.map((link) => (
              <li key={link.label}>
                <Link to={link.to} className="transition hover:text-blue-300">
                  {link.label}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        <div className="lg:border-l lg:border-white/15 lg:pl-10">
          <h2 className="mb-4 text-sm font-bold tracking-wider">DOWNLOADS</h2>
          <ul className="space-y-1 text-sm text-white/85">
            {downloadLinks.map((download) => (
              <li key={download.label}>
                <a
                  href={download.href}
                  download
                  className="transition hover:text-blue-300"
                >
                  {download.label}
                </a>
              </li>
            ))}
          </ul>
        </div>

        <div className="lg:border-l lg:border-white/15 lg:pl-10">
          <h2 className="mb-4 text-sm font-bold tracking-wider">KONTAKT</h2>
          <ul className="space-y-3 text-sm text-white/85">
            <li className="flex gap-3">
              <Icon>
                <svg viewBox="0 0 24 24" className="h-5 w-5" aria-hidden="true">
                  <path d="M12 21s6-5.2 6-11a6 6 0 0 0-12 0c0 5.8 6 11 6 11Z" fill="currentColor" />
                  <circle cx="12" cy="10" r="2" fill="#020617" />
                </svg>
              </Icon>
              <span>{siteConfig.address.street}</span>
            </li>
            <li className="flex gap-3">
              <Icon>
                <svg viewBox="0 0 24 24" className="h-5 w-5" aria-hidden="true">
                  <path d="M4 10h16v10H4V10Zm2-6h12v4H6V4Z" fill="none" stroke="currentColor" strokeWidth="2" />
                </svg>
              </Icon>
              <span>{siteConfig.address.postalCode} {siteConfig.address.city}</span>
            </li>
            <li className="flex gap-3">
              <Icon>
                <svg viewBox="0 0 24 24" className="h-5 w-5" aria-hidden="true">
                  <path d="M4 6h16v12H4V6Zm0 1 8 6 8-6" fill="none" stroke="currentColor" strokeWidth="2" />
                </svg>
              </Icon>
              <a href={mailto(siteConfig.emails.info)} className="transition hover:text-blue-300">
                {siteConfig.emails.info}
              </a>
            </li>
          </ul>
        </div>
      </div>

      <div className="border-t border-white/10">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-4 text-xs text-white/65 sm:flex-row sm:items-center sm:justify-between">
          <p>© {currentYear} Bogensport Bad Oldesloe e.V.</p>
          <div className="flex gap-8">
            <Link to="/impressum" className="transition hover:text-blue-300">Impressum</Link>
            <Link to="/datenschutz" className="transition hover:text-blue-300">Datenschutz</Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
