import * as React from "react";
import { Tooltip, TooltipContent, TooltipTrigger, TooltipProvider } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

type Props = {
  text: string;
  /** If provided, applies multi-line clamp; otherwise single-line ellipsis */
  lineClamp?: number;
  className?: string;
  tooltipClassName?: string;
};

/** Dark-mode aware tooltip that shows only when the text is actually truncated. */
export default function TruncateTooltip({ text, lineClamp, className, tooltipClassName }: Props) {
  const ref = React.useRef<HTMLSpanElement>(null);
  const [showTip, setShowTip] = React.useState(false);

  React.useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const check = () => {
      // Detect both horizontal (single-line) and vertical (multi-line clamp) overflow
      const overflow = el.scrollWidth > el.clientWidth || el.scrollHeight > el.clientHeight;
      setShowTip(overflow);
    };

    // Initial + responsive checks
    check();
    const ro = new ResizeObserver(check);
    ro.observe(el);
    window.addEventListener("resize", check);
    return () => {
      ro.disconnect();
      window.removeEventListener("resize", check);
    };
  }, [text, lineClamp]);

  const clampedClass = lineClamp ? `line-clamp-${lineClamp}` : "truncate";

  const content = (
    <span
      ref={ref}
      // parent containers in flex rows must have min-w-0; you already added it on the header wrapper
      className={cn("block max-w-full break-all overflow-hidden", clampedClass, className)}
      // Native title for keyboard users + fallback if JS/tooltips fail
      title={text}
      aria-label={text}
    >
      {text}
    </span>
  );

  if (!showTip) return content;

  return (
    <TooltipProvider delayDuration={150}>
      <Tooltip>
        <TooltipTrigger asChild>{content}</TooltipTrigger>
        <TooltipContent side="top" className={cn("max-w-sm break-words", tooltipClassName)}>
          {text}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}
