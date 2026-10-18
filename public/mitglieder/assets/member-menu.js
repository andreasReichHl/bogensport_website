(() => {
  const header = document.querySelector(".site-header");
  const toggle = document.querySelector(".site-menu-toggle");
  const menu = document.getElementById("member_site_menu");

  if (!header || !toggle || !menu) {
    return;
  }

  const closeMenu = () => {
    header.classList.remove("is-menu-open");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", "Menü öffnen");
  };

  const openMenu = () => {
    header.classList.add("is-menu-open");
    toggle.setAttribute("aria-expanded", "true");
    toggle.setAttribute("aria-label", "Menü schließen");
  };

  toggle.addEventListener("click", () => {
    if (header.classList.contains("is-menu-open")) {
      closeMenu();
      return;
    }

    openMenu();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenu();
    }
  });

  document.addEventListener("click", (event) => {
    if (!header.contains(event.target)) {
      closeMenu();
    }
  });

  menu.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", closeMenu);
  });
})();
