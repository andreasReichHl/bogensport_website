import { useEffect, useRef, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import logo from "../assets/bobs_logo_white_old.svg";
import { siteConfig } from "../utils/siteConfig.js";
import { downloadLinks, mainNavigationLinks, trialTrainingLink } from "../utils/siteLinks.js";

export default function Navbar() {
  const [isOpen, setIsOpen] = useState(false);
  const [isDownloadsOpen, setIsDownloadsOpen] = useState(false);
  const location = useLocation();
  const downloadsRef = useRef(null);

  useEffect(() => {
    function handleDocumentClick(event) {
      if (downloadsRef.current && !downloadsRef.current.contains(event.target)) {
        setIsDownloadsOpen(false);
      }
    }

    function handleEscape(event) {
      if (event.key === "Escape") {
        setIsDownloadsOpen(false);
      }
    }

    document.addEventListener("mousedown", handleDocumentClick);
    document.addEventListener("keydown", handleEscape);

    return () => {
      document.removeEventListener("mousedown", handleDocumentClick);
      document.removeEventListener("keydown", handleEscape);
    };
  }, []);

  function isActiveLink(link) {
    if (link.to === "/") {
      return location.pathname === "/" && location.hash === "";
    }

    return `${location.pathname}${location.hash}` === link.to;
  }

  return (
    <nav className="font-heading relative z-50 bg-[#002f5f] px-4 py-3 text-white shadow-lg sm:px-6 sm:py-4">
      <div className="flex items-center justify-between gap-4">
        <Link to="/" className="flex items-center gap-2 sm:gap-3" aria-label="Zur Startseite">
          <span className="block h-9 w-9 shrink-0 overflow-hidden sm:h-10 sm:w-10 lg:h-11 lg:w-11">
            <img
              src={logo}
              alt={siteConfig.name}
              className="h-full w-auto max-w-none"
            />
          </span>
          <div className="flex flex-col text-base leading-tight sm:text-lg">
              <div className="text-sm font-bold tracking-wide">{siteConfig.brandLines[0]}</div>
              <div className="text-lg tracking-wide">{siteConfig.brandLines[1]}</div>
          </div>
        </Link>

        <div className="hidden items-center gap-8 text-sm sm:flex">
          {mainNavigationLinks.map((link) => {
            const isActive = isActiveLink(link);

            return (
              <Link
                key={link.label}
                className={`transition hover:text-blue-300 ${isActive ? "text-blue-300 underline decoration-2 underline-offset-8" : ""}`}
                to={link.to}
                aria-current={isActive ? "page" : undefined}
              >
                {link.label}
              </Link>
            );
          })}
          <div className="relative" ref={downloadsRef}>
            <button
              type="button"
              className="inline-flex items-center gap-2 transition hover:text-blue-300"
              aria-expanded={isDownloadsOpen}
              aria-haspopup="menu"
              onClick={() => setIsDownloadsOpen((open) => !open)}
            >
              Downloads
              <span
                className={`text-xs transition ${isDownloadsOpen ? "rotate-180" : ""}`}
                aria-hidden="true"
              >
                ▾
              </span>
            </button>
            {isDownloadsOpen && (
              <div
                className="absolute right-0 top-full mt-4 w-56 rounded-md bg-white py-2 text-sm text-slate-900 shadow-xl ring-1 ring-slate-200"
                role="menu"
              >
                {downloadLinks.map((download) => (
                  <a
                    key={download.label}
                    href={download.href}
                    download
                    className="block px-4 py-2 transition hover:bg-blue-50 hover:text-blue-700"
                    role="menuitem"
                    onClick={() => setIsDownloadsOpen(false)}
                  >
                    {download.label}
                  </a>
                ))}
              </div>
            )}
          </div>
          <Link
            className="transition hover:text-blue-300"
            to={trialTrainingLink.to}
          >
            {trialTrainingLink.label}
          </Link>
        </div>

        <button
          type="button"
          className="flex h-10 w-10 items-center justify-center rounded text-white sm:hidden"
          aria-label={isOpen ? "Menü schließen" : "Menü öffnen"}
          aria-expanded={isOpen}
          onClick={() => setIsOpen((open) => !open)}
        >
          <span className="relative h-5 w-6">
            <span
              className={`absolute left-0 top-0 h-0.5 w-6 bg-current transition ${isOpen ? "translate-y-2 rotate-45" : ""}`}
            />
            <span
              className={`absolute left-0 top-2 h-0.5 w-6 bg-current transition ${isOpen ? "opacity-0" : ""}`}
            />
            <span
              className={`absolute left-0 top-4 h-0.5 w-6 bg-current transition ${isOpen ? "-translate-y-2 -rotate-45" : ""}`}
            />
          </span>
        </button>
      </div>

      <div
        className={`${isOpen ? "flex" : "hidden"} absolute left-0 right-0 top-full z-50 flex-col bg-[#00264d]/95 px-4 py-3 text-sm shadow-2xl ring-1 ring-white/10 backdrop-blur sm:hidden`}
      >
        {mainNavigationLinks.map((link, index) => {
          const isActive = isActiveLink(link);

          return (
            <Link
              key={link.label}
              className={`${index < mainNavigationLinks.length - 1 ? "border-b border-white/10" : ""} py-3 transition hover:text-blue-300 ${isActive ? "text-blue-300 underline decoration-2 underline-offset-4" : ""}`}
              to={link.to}
              aria-current={isActive ? "page" : undefined}
              onClick={() => setIsOpen(false)}
            >
              {link.label}
            </Link>
          );
        })}
        <div className="border-t border-white/10 py-3">
          <button
            type="button"
            className="flex w-full items-center justify-between transition hover:text-blue-300"
            aria-expanded={isDownloadsOpen}
            onClick={() => setIsDownloadsOpen((open) => !open)}
          >
            Downloads
            <span
              className={`text-xs transition ${isDownloadsOpen ? "rotate-180" : ""}`}
              aria-hidden="true"
            >
              ▾
            </span>
          </button>
          {isDownloadsOpen && (
            <div className="mt-3 flex flex-col rounded-md bg-white/5">
              {downloadLinks.map((download) => (
                <a
                  key={download.label}
                  href={download.href}
                  download
                  className="px-3 py-2 text-white/85 transition hover:text-blue-300"
                  onClick={() => {
                    setIsDownloadsOpen(false);
                    setIsOpen(false);
                  }}
                >
                  {download.label}
                </a>
              ))}
            </div>
          )}
        </div>
        <Link
          className="border-t border-white/10 py-3 transition hover:text-blue-300"
          to={trialTrainingLink.to}
          onClick={() => setIsOpen(false)}
        >
          {trialTrainingLink.label}
        </Link>
      </div>
    </nav>
  );
}
