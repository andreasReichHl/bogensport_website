export function ContentSection({ title, children }) {
  return (
    <section className="border-t border-slate-200 pt-8 first:border-t-0 first:pt-0">
      <h2 className="font-heading text-2xl font-bold text-slate-900">{title}</h2>
      <div className="mt-5 space-y-5 text-base leading-relaxed text-slate-700">{children}</div>
    </section>
  );
}

export function BulletList({ items }) {
  return (
    <ul className="space-y-3">
      {items.map((item) => (
        <li key={item} className="flex gap-3">
          <span className="mt-2 h-2 w-2 shrink-0 rounded-full bg-blue-600" aria-hidden="true" />
          <span>{item}</span>
        </li>
      ))}
    </ul>
  );
}
