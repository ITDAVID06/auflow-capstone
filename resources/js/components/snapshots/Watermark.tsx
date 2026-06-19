import React from "react";
import { statusTone } from "./StatusPill";

export function Watermark({ status }: { status: string }) {
  const tone = statusTone(status);
  const text = (status || "").toUpperCase();

  return (
    <div
      aria-hidden
      className={`pointer-events-none absolute inset-0 grid place-items-center print:opacity-100`}
      style={{ opacity: 0.06 }}
    >
      <div className={`select-none text-[7rem] font-extrabold tracking-widest ${tone.watermark}`}>
        {text === "" ? "SNAPSHOT" : text}
      </div>
    </div>
  );
}
