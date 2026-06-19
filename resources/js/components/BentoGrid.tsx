import { type ReactNode } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { cn } from "@/lib/utils";

// ─── Grid container ──────────────────────────────────────────────────────────

export function BentoGrid({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                "grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4",
                className,
            )}
        >
            {children}
        </div>
    );
}

// ─── Column / row span maps ───────────────────────────────────────────────────
// Full string literals are required so Tailwind's JIT includes these classes.

const COL_SPAN: Record<1 | 2 | 3 | 4, string> = {
    1: "",
    2: "sm:col-span-2 xl:col-span-2",
    3: "sm:col-span-2 xl:col-span-3",
    4: "sm:col-span-2 xl:col-span-4",
};

const ROW_SPAN: Record<1 | 2, string> = {
    1: "",
    2: "xl:row-span-2",
};

// ─── Card wrapper ─────────────────────────────────────────────────────────────

interface BentoCardProps {
    children: ReactNode;
    /** How many grid columns this card spans (at xl breakpoint). */
    colSpan?: 1 | 2 | 3 | 4;
    /** How many grid rows this card spans (at xl breakpoint only). */
    rowSpan?: 1 | 2;
    className?: string;
    /** Entrance animation stagger delay in seconds. */
    delay?: number;
}

export function BentoCard({
    children,
    colSpan = 1,
    rowSpan = 1,
    className,
    delay = 0,
}: BentoCardProps) {
    const shouldReduce = useReducedMotion();

    return (
        <motion.div
            initial={shouldReduce ? false : { opacity: 0, y: 10 }}
            animate={shouldReduce ? {} : { opacity: 1, y: 0 }}
            transition={{
                duration: 0.35,
                delay: shouldReduce ? 0 : delay,
                ease: [0.25, 0.46, 0.45, 0.94],
            }}
            className={cn(COL_SPAN[colSpan], ROW_SPAN[rowSpan], className)}
        >
            {children}
        </motion.div>
    );
}
