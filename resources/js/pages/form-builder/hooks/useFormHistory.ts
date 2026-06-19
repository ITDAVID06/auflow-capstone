import { useRef, useState, useCallback, useEffect } from "react";
import type { FormField } from "@/types/form";

const MAX_HISTORY = 50;

interface HistoryEntry {
  fields: FormField[];
  timestamp: number;
}

/**
 * Undo / Redo support for the form builder fields array.
 *
 * Usage:
 *   const { pushState, undo, redo, canUndo, canRedo } = useFormHistory(fields, setFields);
 *
 * Call `pushState(fields)` after every meaningful user action (add, delete, reorder, edit).
 * The hook also listens for Ctrl+Z / Ctrl+Shift+Z keyboard shortcuts.
 */
export function useFormHistory(
  fields: FormField[],
  setFields: (fields: FormField[]) => void
) {
  const pastRef = useRef<HistoryEntry[]>([]);
  const futureRef = useRef<HistoryEntry[]>([]);
  const [canUndo, setCanUndo] = useState(false);
  const [canRedo, setCanRedo] = useState(false);

  const syncState = useCallback(() => {
    setCanUndo(pastRef.current.length > 0);
    setCanRedo(futureRef.current.length > 0);
  }, []);

  /**
   * Record the current fields snapshot before a change is applied.
   * Call BEFORE updating fields (e.g., before setForm).
   */
  const pushState = useCallback(
    (currentFields: FormField[]) => {
      pastRef.current = [
        ...pastRef.current.slice(-(MAX_HISTORY - 1)),
        { fields: structuredClone(currentFields), timestamp: Date.now() },
      ];
      // Any new action clears the redo stack
      futureRef.current = [];
      syncState();
    },
    [syncState]
  );

  const undo = useCallback(() => {
    if (pastRef.current.length === 0) return;
    const previous = pastRef.current.pop()!;

    // Push current state to future
    futureRef.current.push({
      fields: structuredClone(fields),
      timestamp: Date.now(),
    });

    setFields(previous.fields);
    syncState();
  }, [fields, setFields, syncState]);

  const redo = useCallback(() => {
    if (futureRef.current.length === 0) return;
    const next = futureRef.current.pop()!;

    // Push current state to past
    pastRef.current.push({
      fields: structuredClone(fields),
      timestamp: Date.now(),
    });

    setFields(next.fields);
    syncState();
  }, [fields, setFields, syncState]);

  // Keyboard shortcuts
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const isMod = e.ctrlKey || e.metaKey;
      if (!isMod) return;

      if (e.key === "z" && !e.shiftKey) {
        e.preventDefault();
        undo();
      } else if ((e.key === "z" && e.shiftKey) || e.key === "y") {
        e.preventDefault();
        redo();
      }
    };

    window.addEventListener("keydown", handler);
    return () => window.removeEventListener("keydown", handler);
  }, [undo, redo]);

  const clearHistory = useCallback(() => {
    pastRef.current = [];
    futureRef.current = [];
    syncState();
  }, [syncState]);

  return { pushState, undo, redo, canUndo, canRedo, clearHistory };
}
