import React from "react";

interface SectionProps {
  title: string;
  children: React.ReactNode;
  className?: string;
  dataTour?: string;
}

export const Section: React.FC<SectionProps> = ({ title, children, className, dataTour }) => (
  <div className={`space-y-4 ${className ?? ""}`} data-tour={dataTour}>
    <h2 className="text-sm max-[420px]:text-[12px] font-semibold tracking-wider uppercase text-muted-foreground/70">{title}</h2>
    {children}
  </div>
);
