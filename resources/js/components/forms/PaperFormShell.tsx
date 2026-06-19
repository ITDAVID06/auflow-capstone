import * as React from "react";
import { cn } from "@/lib/utils";

type Props = {
  orgName: string;
  systemName?: string;
  logoSrc?: string;
  className?: string;
  actions?: React.ReactNode;
  children: React.ReactNode;
};

/**
 * Neutral (black in dark mode) paper shell used by request forms.
 * - No blue: uses zinc scale in dark mode
 * - Keeps the public API identical to your existing usage
 */
export default function PaperFormShell({
  orgName,
  systemName,
  logoSrc,
  className,
  actions,
  children,
}: Props) {
  return (
    <div
      className={cn(
        "rounded-2xl border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700",
        className
      )}
    >
      {/* Masthead */}
      <div
        className={cn(
          "rounded-t-2xl border-b px-5 py-4",
          "bg-white dark:bg-gray-900",
          "border-gray-200 dark:border-gray-700"
        )}
      >
        <div className="flex items-center gap-3">
          {logoSrc ? (
            <img
              src={logoSrc}
              alt="Logo"
              className="h-8 w-8 rounded-md object-contain ring-1 ring-gray-200 dark:ring-gray-700"
            />
          ) : null}
          <div className="leading-tight">
            <div className="text-sm font-semibold text-gray-900 dark:text-gray-100">
              {orgName}
            </div>
            {systemName ? (
              <div className="text-[11px] text-gray-500 dark:text-gray-400">
                {systemName}
              </div>
            ) : null}
          </div>
        </div>
      </div>

      {/* Body */}
      <div className="px-5 py-4">{children}</div>

      {/* Actions */}
      {actions ? (
        <div className="border-t px-5 py-4 border-gray-200 dark:border-gray-700">
          {actions}
        </div>
      ) : null}
    </div>
  );
}
