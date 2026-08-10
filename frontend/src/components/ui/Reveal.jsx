import React from "react";
import { useOnScreen } from "../../hooks/useOnScreen.js";

export default function Reveal({ children, delay = 0, className = "" }) {
  const [ref, visible] = useOnScreen();
  return (
    <div
      ref={ref}
      className={className}
      style={{
        opacity: visible ? 1 : 0,
        // "none" rather than "translateY(0px)" once visible — visually
        // identical, but a live transform value (even a no-op one) creates
        // a new CSS containing block for any `position: fixed` descendant,
        // which would wrongly constrain a modal to this card's box instead
        // of the real viewport if one were ever nested inside a Reveal.
        transform: visible ? "none" : "translateY(24px)",
        transition: `opacity 0.7s cubic-bezier(.16,1,.3,1) ${delay}ms, transform 0.7s cubic-bezier(.16,1,.3,1) ${delay}ms`,
      }}
    >
      {children}
    </div>
  );
}
