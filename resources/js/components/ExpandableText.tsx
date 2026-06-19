import * as React from "react";
import { cn } from "@/lib/utils";

type Props = {
  text: string | null | undefined;
  lines?: number; // default 3
  className?: string;
  buttonClassName?: string;
};

/**
 * Collapsible multi-line text with a Show more/less toggle.
 * - Detects overflow so the toggle only appears when needed.
 * - Prints full text (no truncation).
 */
export default function ExpandableText({
  text,
  lines = 3,
  className,
  buttonClassName,
}: Props) {
  const content = (text ?? "").trim();
  if (!content) return <span className={cn("text-zinc-500 dark:text-zinc-400", className)}>—</span>;

  const ref = React.useRef<HTMLDivElement>(null);
  const [expanded, setExpanded] = React.useState(false);
  const [isOverflowing, setIsOverflowing] = React.useState(false);

  React.useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const check = () => {
      // Compare scrollHeight vs clientHeight to detect clamp/overflow
      setIsOverflowing(el.scrollHeight > el.clientHeight + 1);
    };

    check();
    const ro = new ResizeObserver(check);
    ro.observe(el);
    window.addEventListener("resize", check);

    // ensure we re-check when content changes
    const id = window.setTimeout(check, 0);

    return () => {
      ro.disconnect();
      window.removeEventListener("resize", check);
      window.clearTimeout(id);
    };
  }, [content, lines]);

  const clampClass = expanded ? "" : `line-clamp-${lines}`;

  return (
    <div className={cn("space-y-1", className)}>
      {/* Screen view (can clamp) */}
      <div className="print:hidden">
        <div
          ref={ref}
          className={cn(
            "whitespace-pre-wrap break-words text-[13px] text-zinc-900 dark:text-zinc-100",
            clampClass
          )}
        >
          {content}
        </div>

        {isOverflowing && (
          <button
            type="button"
            onClick={() => setExpanded((v) => !v)}
            className={cn(
              "text-xs font-medium text-[#1551f1] hover:underline",
              buttonClassName
            )}
          >
            {expanded ? "Show less" : "Show more"}
          </button>
        )}
      </div>

      {/* Print view (always full text) */}
      <div className="hidden whitespace-pre-wrap break-words text-[12px] text-zinc-900 dark:text-zinc-100 print:block">
        {content}
      </div>
    </div>
  );
}
