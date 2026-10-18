const currencyFormatter = new Intl.NumberFormat("de-DE", { style: "currency", currency: "EUR" });

export function formatEuro(amount) {
  return currencyFormatter.format(amount);
}

export function formatInterval(months) {
  switch (months) {
    case 1:
      return "monatlich";
    case 3:
      return "vierteljährlich";
    case 6:
      return "halbjährlich";
    case 12:
      return "jährlich";
    default:
      return `alle ${months} Monate`;
  }
}

/**
 * Age in full years on the given day - same rule as membership_age_on() on the server.
 * Returns null for an empty or invalid date.
 */
export function ageOn(dateOfBirth, today = new Date()) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateOfBirth);

  if (!match) {
    return null;
  }

  const [year, month, day] = match.slice(1).map(Number);
  let age = today.getFullYear() - year;

  if (today.getMonth() + 1 < month || (today.getMonth() + 1 === month && today.getDate() < day)) {
    age -= 1;
  }

  return age >= 0 ? age : null;
}

export async function fetchMembershipForm() {
  const response = await fetch("/beitritt-formular.php", { cache: "no-store" });

  if (!response.ok) {
    throw new Error("Membership form config unavailable");
  }

  return response.json();
}

/**
 * @returns {Promise<{status: "ok"} | {status: "invalid", errors: object} | {status: "rate_limited"} | {status: "error"}>}
 */
export async function submitMembershipApplication(payload) {
  let response;

  try {
    response = await fetch("/beitritt-absenden.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(payload),
    });
  } catch {
    return { status: "error" };
  }

  if (response.status === 201) {
    return { status: "ok" };
  }

  if (response.status === 422) {
    const body = await response.json().catch(() => ({}));
    return { status: "invalid", errors: body.errors ?? {} };
  }

  if (response.status === 429) {
    return { status: "rate_limited" };
  }

  return { status: "error" };
}
