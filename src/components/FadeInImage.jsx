import { useEffect, useRef, useState } from "react";

export default function FadeInImage({ src, alt = "", wrapperClassName = "", className = "", ...imgProps }) {
  const [isLoaded, setIsLoaded] = useState(false);
  const imgRef = useRef(null);

  useEffect(() => {
    setIsLoaded(Boolean(imgRef.current?.complete));
  }, [src]);

  // The skeleton overlay just needs a non-static positioning context. Only default to
  // "relative" when wrapperClassName doesn't already set one (e.g. "absolute inset-0" for a
  // full-bleed background) - Tailwind's compiled CSS orders position utilities such that a
  // plain "relative" would otherwise win the cascade over a caller-supplied "absolute".
  const hasPositionUtility = /\b(?:static|relative|absolute|fixed|sticky)\b/.test(wrapperClassName);
  const positionClass = hasPositionUtility ? "" : "relative";

  return (
    <div className={`${positionClass} overflow-hidden ${wrapperClassName}`}>
      {!isLoaded && <div className="absolute inset-0 animate-pulse bg-slate-200" aria-hidden="true" />}
      <img
        ref={imgRef}
        src={src}
        alt={alt}
        onLoad={() => setIsLoaded(true)}
        className={`transition-opacity duration-500 ${isLoaded ? "opacity-100" : "opacity-0"} ${className}`}
        {...imgProps}
      />
    </div>
  );
}
