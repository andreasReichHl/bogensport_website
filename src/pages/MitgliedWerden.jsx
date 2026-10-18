import Hero from "../components/Hero.jsx";
import FormularMitgliedschaft from "../components/FormularMitgliedschaft.jsx";

export default function MitgliedWerden() {
  return (
    <>
      <Hero
        title={(
          <>
            Mitglied
            <br />
            werden.
          </>
        )}
        subtitle="Werde Teil des Bad Oldesloer Bogensport e.V."
        description="Beantrage deine Mitgliedschaft ganz einfach online. Wir freuen uns auf dich!"
        actions={[]}
      />
      <FormularMitgliedschaft />
    </>
  );
}
