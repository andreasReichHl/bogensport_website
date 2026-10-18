import { satzung, satzungAnchor } from "../data/satzung.js";

const subLetters = "abcdefghijklmnopqrstuvwxyz";

function SatzungItem({ item }) {
  if (typeof item === "string") {
    return <li>{item}</li>;
  }

  return (
    <li>
      {item.text}
      {item.sub && (
        <ol className="mt-2 space-y-1 pl-1">
          {item.sub.map((subItem, index) => (
            <li key={subItem} className="flex gap-2">
              <span className="shrink-0 text-slate-500">{subLetters[index]})</span>
              <span>{subItem}</span>
            </li>
          ))}
        </ol>
      )}
      {item.after?.map((paragraph) => (
        <p key={paragraph} className="mt-2">{paragraph}</p>
      ))}
    </li>
  );
}

export default function SatzungContent({ headingLevel = "h2" }) {
  const Heading = headingLevel;

  return (
    <div className="space-y-8 font-sans text-base leading-relaxed text-slate-700">
      {satzung.map((paragraph) => (
        <section key={paragraph.number} id={satzungAnchor(paragraph.number)} className="scroll-mt-28">
          <Heading className="mb-3 wrap-break-word font-heading text-lg font-bold text-slate-950 sm:text-xl">
            § {paragraph.number} {paragraph.title}
          </Heading>

          {paragraph.text && <p>{paragraph.text}</p>}
          {paragraph.intro && <p className="mb-2">{paragraph.intro}</p>}
          {paragraph.items && (
            <ol className="list-decimal space-y-2 pl-6 marker:text-slate-500">
              {paragraph.items.map((item) => (
                <SatzungItem key={typeof item === "string" ? item : item.text} item={item} />
              ))}
            </ol>
          )}
          {paragraph.outro && <p className="mt-3">{paragraph.outro}</p>}
        </section>
      ))}
    </div>
  );
}
