import ReadingDialog from "./ReadingDialog.jsx";
import DatenschutzContent from "./DatenschutzContent.jsx";

/**
 * "Datenschutzerklärung" link that opens it in a modal dialog - by default at the section on
 * the online membership application.
 */
export default function DatenschutzDialog({children = "Datenschutzerklärung", startAt = "online-mitgliedsantrag"}) {
    return (
        <ReadingDialog label={children} title="Datenschutzerklärung" startAt={startAt}>
            <DatenschutzContent headingLevel={3}/>
        </ReadingDialog>
    );
}
