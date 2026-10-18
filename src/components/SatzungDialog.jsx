import {useRef} from "react";
import {createPortal} from "react-dom";
import SatzungContent from "./SatzungContent.jsx";
import {satzungMeta} from "../data/satzung.js";

/**
 * "Satzung lesen" link that opens the Satzung in a modal dialog, so the reader stays on the
 * page (and keeps everything already typed into a form). Closes via button, Esc or backdrop.
 */
export default function SatzungDialog({children = "Satzung lesen"}) {
    const dialogRef = useRef(null);

    function close() {
        dialogRef.current?.close();
    }

    return (
        <>
            <button
                type="button"
                className="font-semibold text-blue-700 hover:underline"
                onClick={() => dialogRef.current?.showModal()}
            >
                {children}
            </button>
            {/* Portal: the trigger often sits inside a checkbox <label> - clicks inside the dialog
                must not toggle that checkbox. */}
            {createPortal(<dialog
                ref={dialogRef}
                aria-labelledby="satzung-dialog-title"
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
                        <h2 id="satzung-dialog-title" className="font-heading text-lg font-bold text-[#002f5f]">
                            {satzungMeta.title}
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
                    <div className="overflow-y-auto px-5 py-5 sm:px-6">
                        <SatzungContent headingLevel="h3"/>
                        <p className="mt-8 font-sans text-sm text-slate-500">
                            {satzungMeta.place}, {satzungMeta.date}
                        </p>
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
