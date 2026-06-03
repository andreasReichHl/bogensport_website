import Hero from "../components/Hero.jsx";
import FormularProbetraining from "../components/FormularProbetraining.jsx";

export default function Probetraining() {
  return (
    <>
      <Hero
        title={(
          <>
            Probetraining
            <br />
            anmelden.
          </>
        )}
        subtitle="Lerne uns und den Bogensport kennen!"
        description="Melde dich ganz einfach für ein unverbindliches Probetraining bei uns an. Wir freuen uns auf dich!"
        actions={[]}
      />
      <FormularProbetraining />
    </>
  );
}
