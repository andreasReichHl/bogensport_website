import {Link} from "react-router-dom";

import FadeInImage from "./FadeInImage";
import bogenplatzImage from "../assets/image_bogenplatz.png";

const defaultActions = [
    {
        label: "Probetraining anfragen",
        to: "/probetraining",
        variant: "primary",
    },
    {
        label: "Trainingszeiten ansehen",
        to: "/training",
        variant: "secondary",
    },
];

function ArrowIcon() {
    return (
        <svg
            className="mt-5 h-9 w-36 text-blue-500 sm:h-10 sm:w-44"
            viewBox="0 0 220 56"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M18 28H184"
                stroke="currentColor"
                strokeWidth="4"
                strokeLinecap="round"
            />
            <path d="M184 12L210 28L184 44L194 28L184 12Z" fill="currentColor"/>
            <path
                d="M18 28L4 14M18 28L4 42M34 28L18 10M34 28L18 46"
                stroke="currentColor"
                strokeWidth="4"
                strokeLinecap="round"
            />
        </svg>
    );
}

export default function Hero({
                                 title = (
                                     <>
                                         Zielen.
                                         <br/>
                                         Fokussieren.
                                         <br/>
                                         Treffen.
                                     </>
                                 ),
                                 subtitle = "Bogensport in Bad Oldesloe",
                                 description = "Ein Sport für jedes Alter. Konzentration, Präzision und Gemeinschaft - das findest du bei uns.",
                                 actions = defaultActions,
                                 showArrow = false,
                                 contentClassName = "max-w-xl",
                             }) {
    return (
        <section className="bg-slate-50">
            <div className="relative h-160 overflow-hidden text-white">
                <FadeInImage
                    wrapperClassName="absolute inset-0 h-full w-full"
                    src={bogenplatzImage}
                    alt=""
                    className="h-full w-full object-cover object-center"
                    aria-hidden="true"
                />
                <div
                    className="absolute inset-y-0 left-0 w-2/3 bg-linear-to-r from-black/75 via-black/35 to-transparent"
                    aria-hidden="true"/>

                <div
                    className="relative z-10 mx-auto flex h-full max-w-7xl flex-col justify-center px-5 pb-32 pt-20 sm:px-8 lg:px-12">
                    <div className={contentClassName}>
                        <h1 className="font-heading text-5xl font-bold leading-none sm:text-6xl lg:text-7xl">
                            {title}
                        </h1>
                        {showArrow ? <ArrowIcon/> : <div className="mt-5 h-0.5 w-28 bg-blue-400"/>}
                        <p className="mt-6 font-heading text-2xl font-bold">{subtitle}</p>
                        <p className="mt-4 max-w-lg text-base leading-relaxed text-white/90 sm:text-lg">
                            {description}
                        </p>

                        {actions.length > 0 ? (
                            <div className="mt-8 flex flex-col gap-4 sm:flex-row">
                                {actions.map((action) => (
                                    <Link
                                        key={action.label}
                                        to={action.to}
                                        className={
                                            action.variant === "primary"
                                                ? "inline-flex h-12 items-center justify-center gap-3 rounded-md bg-blue-600 px-6 text-sm font-bold text-white shadow-lg shadow-blue-950/30 transition hover:bg-blue-700"
                                                : "inline-flex h-12 items-center justify-center rounded-md border border-white/70 px-6 text-sm font-bold text-white transition hover:border-white hover:bg-white/10"
                                        }
                                    >
                                        {action.label}
                                        {action.variant === "primary" && <span aria-hidden="true">→</span>}
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="mt-8 h-12" aria-hidden="true"/>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
