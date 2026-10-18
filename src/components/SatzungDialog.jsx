import ReadingDialog from "./ReadingDialog.jsx";
import SatzungContent from "./SatzungContent.jsx";
import {satzungMeta} from "../data/satzung.js";

/**
 * "Satzung lesen" link that opens the Satzung in a modal dialog.
 */
export default function SatzungDialog({children = "Satzung lesen"}) {
    return (
        <ReadingDialog label={children} title={satzungMeta.title}>
            <SatzungContent headingLevel="h3"/>
            <p className="mt-8 font-sans text-sm text-slate-500">
                {satzungMeta.place}, {satzungMeta.date}
            </p>
        </ReadingDialog>
    );
}
