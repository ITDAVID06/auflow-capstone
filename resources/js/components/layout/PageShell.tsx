import * as React from "react";

type PageShellProps = {
  title?: string;
  description?: string;
  actions?: React.ReactNode;
  children: React.ReactNode;
};

export default function PageShell({ title, description, actions, children }: PageShellProps) {
  return (
    <div className="w-full mx-auto px-6 md:px-10 pt-6 md:pt-8 pb-10">
      {(title || description || actions) && (
        <header className="mb-4">
          <div className="flex items-start justify-between gap-4">
            <div>
              {title && <h1 className="text-xl font-semibold tracking-tight">{title}</h1>}
              {description && <p className="text-sm text-muted-foreground mt-1">{description}</p>}
            </div>
            {actions ? <div className="shrink-0">{actions}</div> : null}
          </div>
        </header>
      )}
      {children}
    </div>
  );
}
