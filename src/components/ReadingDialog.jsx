import {useId, useRef} from "react";
import {createPortal} from "react-dom";

/**
 * Link-style button that opens a longer text (Satzung, Datenschutzerklärung) in a modal
 * dialog, so the reader stays on the page and keeps everything already typed into a form.
 * Closes via button, Esc or backdrop. startAt: id of an element to open the text at.
 */
export default function ReadingDialog({label, title, startAt, children}) {
    const dialogRef = useRef(null);
    const bodyRef = useRef(null);
    const titleId = useId();

    function open() {
        dialogRef.current?.showModal();

        const body = bodyRef.current;
        const target = startAt ? body?.querySelector(`#${startAt}`) : null;

        if (body) {
            // Relative to the scroll area - scrollIntoView would also honour the page's scroll-mt.
            body.scrollTop = target
                ? body.scrollTop + target.getBoundingClientRect().top - body.getBoundingClientRect().top - 16
                : 0;
        }
    }

    function close() {
        dialogRef.current?.close();
    }

    return (
        <>
            <button type="button" className="font-semibold text-blue-700 hover:underline" onClick={open}>
                {label}
            </button>
            {/* Portal: the trigger often sits inside a checkbox <label> - clicks inside the dialog
                must not toggle that checkbox. */}
            {createPortal(<dialog
                ref={dialogRef}
                aria-labelledby={titleId}
                className="m-auto max-h-[85vh] w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-lg bg-white p-0 shadow-xl backdrop:bg-slate-900/60"
                onClick={(event) => {
                    // A click on the dialog element itself (not its content) is a click on the backdrop.
                    if (event.target === dialogRef.current) {
                        close();
                    }
                }}
            >
                <div className="flex max-h-[85vh] flex-col">
                    <div className="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                        <h2 id={titleId} className="font-heading text-lg font-bold text-[#002f5f]">
                            {title}
                        </h2>
                        <button
                            type="button"
                            onClick={close}
                            className="shrink-0 rounded-md px-2 text-2xl leading-none text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                            aria-label="Schließen"
                        >
                            ×
                        </button>
                    </div>
                    <div ref={bodyRef} className="overflow-y-auto px-5 py-5 sm:px-6">
                        {children}
                    </div>
                    <div className="border-t border-slate-200 px-5 py-4 text-right sm:px-6">
                        <button
                            type="button"
                            onClick={close}
                            className="font-heading inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-6 text-sm font-bold text-white transition hover:bg-blue-700"
                        >
                            Schließen
                        </button>
                    </div>
                </div>
            </dialog>, document.body)}
        </>
    );
}
