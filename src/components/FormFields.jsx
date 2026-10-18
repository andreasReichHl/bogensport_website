export function SectionTitle({number, children}) {
    return (
        <div className="mb-5 flex items-center gap-3">
      <span
          className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
        {number}
      </span>
            <h2 className="font-heading text-lg font-bold text-slate-900">{children}</h2>
        </div>
    );
}

export function Field({label, required, error, children}) {
    return (
        <label className="block">
      <span className="mb-2 block text-sm font-semibold text-slate-700">
        {label} {required && <span aria-hidden="true">*</span>}
      </span>
            {children}
            {error && <span className="mt-1 block text-sm text-red-700">{error}</span>}
        </label>
    );
}

export const inputClass =
    "h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-100";

export const textareaClass =
    "min-h-32 w-full rounded-md border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-100";
