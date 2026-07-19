const basePath = import.meta.env.BASE_URL;

function withBasePath(path) {
  return `${basePath}${path.replace(/^\//, '')}`;
}

export const mainNavigationLinks = [
  { label: "Startseite", to: "/" },
  { label: "Verein", to: "/verein" },
  { label: "Training", to: "/training" },
  { label: "Termine", to: "/termine" },
];

export const trialTrainingLink = { label: "Probetraining anfragen", to: "/probetraining" };

export const memberAreaLink = { label: "Mitgliederbereich", href: withBasePath("/mitglieder/") };

export const footerNavigationLinks = [...mainNavigationLinks, trialTrainingLink, memberAreaLink];

export const downloadLinks = [
  { label: "Mitgliedsantrag", href: withBasePath("/downloads/mitgliedsantrag.pdf") },
  { label: "Satzung", href: withBasePath("/downloads/satzung.pdf") },
];
