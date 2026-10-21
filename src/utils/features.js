// Features that are built but not live yet: visible in the Vite dev server, hidden in every
// production build unless switched on for the build, e.g. VITE_MEMBERSHIP_ENABLED=true npm run build
export const membershipEnabled = import.meta.env.DEV || import.meta.env.VITE_MEMBERSHIP_ENABLED === 'true';
