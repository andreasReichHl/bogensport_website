export default function Notice({title, children, tone = "info"}) {
    const toneClass = tone === "error"
        ? "border-red-200 bg-red-50"
        : "border-blue-200 bg-blue-50";

    return (
        <aside className={`rounded-lg border p-5 ${toneClass}`}>
            <div className="flex gap-4">
                <span
                    className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 border-blue-600 text-lg font-bold text-blue-700">
                    i
                </span>
                <div>
                    <h3 className="font-heading text-lg font-bold text-[#002f5f]">{title}</h3>
                    <div className="mt-2 space-y-2 font-sans text-sm leading-relaxed text-slate-700">{children}</div>
                </div>
            </div>
        </aside>
    );
}
