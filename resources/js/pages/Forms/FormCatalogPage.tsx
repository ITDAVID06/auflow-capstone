import { useEffect, useRef, useState } from "react";
import { Head, router, Link } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import EmptyState from "@/components/EmptyState";
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from "@/components/ui/select";
import {
  Search,
  Lock,
  ChevronRight,
  FileText,
  Plus,
  LifeBuoy,
  SlidersHorizontal,
  Loader2,
  ChevronLeft,
} from "lucide-react";

type FormRow = {
  id: number;
  form_name: string;
  description?: string | null;
  version?: number | null;
  submission_limit_reached?: boolean | number | string | null;
  form_category_id?: number | null;
};

type Category = { id: number; name: string };

interface Props {
  forms: FormRow[];
  viewRouteName: string;
  indexRouteName?: string;
  categories?: Category[];
  filters?: {
    search?: string;
    category_id?: string | number | null;
  };
}

export default function FormCatalogPage({
  forms,
  viewRouteName,
  indexRouteName = "user.forms",
  categories = [],
  filters,
}: Props) {
  const initialSearch = filters?.search ?? "";
  const initialCategory = String(filters?.category_id ?? "all");

  const [q, setQ] = useState(initialSearch);
  const [selectedCategory, setSelectedCategory] = useState<string>(initialCategory);
  const [pending, setPending] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const applyFilters = (search: string, category: string) => {
    setPending(true);
    router.get(
      route(indexRouteName),
      {
        search: search || undefined,
        category_id: category !== "all" ? category : undefined,
      },
      {
        replace: true,
        preserveState: true,
        preserveScroll: true,
        only: ["forms"],
        onFinish: () => setPending(false),
      }
    );
  };

  const onCategoryChange = (val: string) => {
    setSelectedCategory(val);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    applyFilters(q, val);
  };

  const onSearchChange = (val: string) => {
    setQ(val);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => applyFilters(val, selectedCategory), 300);
  };

  // Cleanup debounce on unmount
  useEffect(() => () => { if (debounceRef.current) clearTimeout(debounceRef.current); }, []);

  const buildShowUrl = (id: number): string => {
    try {
      const urlish = route(viewRouteName, { id });
      if (typeof urlish === "string") return urlish;
      if (urlish && typeof urlish === "object" && "toString" in urlish)
        return (urlish as { toString: () => string }).toString();
      return `/user/forms/${id}`;
    } catch {
      return `/user/forms/${id}`;
    }
  };

  const availableCount = forms.filter(
    (f) =>
      f.submission_limit_reached !== true &&
      f.submission_limit_reached !== 1 &&
      f.submission_limit_reached !== "1"
  ).length;

  return (
    <AppLayout
      title="Request Forms"
      subtitle="Browse and submit your document requests"
    >
      <Head title="Request Forms" />

      <div className="mx-auto w-full max-w-[1600px] px-4 py-5 sm:px-6 sm:py-6 lg:px-8 space-y-5">
        {/* ── Back Navigation ──────────────────────────────────────────────── */}
        <div>
          <Link
            href={route(`${indexRouteName.split('.')[0]}.index`)}
            className="group inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground motion-safe:transition-colors"
          >
            <ChevronLeft className="h-4 w-4 motion-safe:transition-transform motion-safe:group-hover:-translate-x-0.5" />
            Back to Dashboard
          </Link>
        </div>

        {/* ── Toolbar ──────────────────────────────────────────────── */}
        <div className="flex flex-col sm:flex-row sm:items-center gap-3">
          {/* Search */}
          <div className="relative flex-1 sm:max-w-sm group">
            {pending ? (
              <Loader2 className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-primary animate-spin pointer-events-none" />
            ) : (
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/60 group-focus-within:text-primary motion-safe:transition-colors pointer-events-none" />
            )}
            <Input
              type="text"
              placeholder="Search forms..."
              value={q}
              onChange={(e) => onSearchChange(e.target.value)}
              className="pl-9 h-9 sm:h-10 text-sm"
              aria-label="Search forms"
            />
          </div>

          {/* Category filter */}
          <div className="flex items-center gap-1.5 text-muted-foreground">
            <SlidersHorizontal className="h-4 w-4 flex-shrink-0" />
            <Select value={selectedCategory} onValueChange={onCategoryChange}>
              <SelectTrigger
                className="h-9 sm:h-10 w-[160px] text-sm"
                aria-label="Filter by category"
              >
                <SelectValue placeholder="All Categories" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Categories</SelectItem>
                {categories.map((cat) => (
                  <SelectItem key={cat.id} value={String(cat.id)}>
                    {cat.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Spacer */}
          <div className="hidden sm:flex-1 sm:block" />

          {/* Count badge */}
          <div className="hidden sm:flex items-center gap-2 text-sm text-muted-foreground">
            <span className="font-medium tabular-nums text-foreground">{availableCount}</span>
            <span>{availableCount === 1 ? "form available" : "forms available"}</span>
          </div>
        </div>

        {/* ── Grid ─────────────────────────────────────────────────── */}
        <div aria-busy={pending} aria-live="polite">
        {forms.length === 0 ? (
          <div className="py-12">
            <EmptyState
              icon={<Search className="h-6 w-6" />}
              title="No forms found"
              message={
                q
                  ? `No forms match "${q}". Try adjusting your search or filter.`
                  : "No forms match your current filter criteria."
              }
              action={
                q ? (
                  <Button variant="outline" size="sm" onClick={() => onSearchChange("")}>
                    Clear search
                  </Button>
                ) : undefined
              }
            />
          </div>
        ) : (
          <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {forms.map((form) => {
              const limitReached =
                form.submission_limit_reached === true ||
                form.submission_limit_reached === 1 ||
                form.submission_limit_reached === "1";

              const to = buildShowUrl(form.id);

              return (
                <button
                  key={form.id}
                  type="button"
                  aria-label={`Open form: ${form.form_name}`}
                  disabled={limitReached}
                  onClick={() => !limitReached && router.visit(to, { preserveScroll: true })}
                  className={`group text-left w-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-xl ${
                    limitReached ? "cursor-not-allowed" : "cursor-pointer"
                  }`}
                >
                  <div
                    className={`
                      relative h-full flex flex-col rounded-xl border bg-card overflow-hidden
                      motion-safe:transition-colors
                      ${limitReached
                        ? "opacity-60 border-border/50"
                        : "border-border/50 hover:border-border/80 hover:bg-accent/20"
                      }
                    `}
                  >
                    {/* Locked overlay */}
                    {limitReached && (
                      <div className="absolute inset-0 z-10 bg-background/90 backdrop-blur-[2px] flex items-center justify-center rounded-xl">
                        <div className="flex flex-col items-center gap-2 text-center px-6">
                          <div className="flex items-center justify-center w-10 h-10 rounded-full bg-muted">
                            <Lock className="h-5 w-5 text-muted-foreground" />
                          </div>
                          <p className="text-sm font-semibold text-foreground">Limit Reached</p>
                          <p className="text-xs text-muted-foreground leading-relaxed">
                            This form is no longer accepting submissions
                          </p>
                        </div>
                      </div>
                    )}

                    {/* Card body */}
                    <div className="flex flex-col flex-1 p-5 gap-3">
                      {/* Header row */}
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex items-center justify-center w-9 h-9 rounded-lg bg-primary/10 flex-shrink-0 group-hover:bg-primary/15 motion-safe:transition-colors">
                          <FileText className="h-4 w-4 text-primary" />
                        </div>
                        {!limitReached && (
                          <ChevronRight className="h-4 w-4 text-muted-foreground/50 group-hover:text-primary motion-safe:transition-colors mt-0.5 flex-shrink-0" />
                        )}
                      </div>

                      {/* Title */}
                      <div className="space-y-1.5">
                        <h3 className="font-semibold text-sm leading-snug text-foreground group-hover:text-primary motion-safe:transition-colors line-clamp-2">
                          {form.form_name}
                        </h3>
                        {form.version && (
                          <span className="text-[10px] px-1.5 py-0.5 font-medium text-muted-foreground border border-border/60 rounded-full">
                            v{form.version}
                          </span>
                        )}
                      </div>

                      {/* Description */}
                      <p className="flex-1 text-xs text-muted-foreground leading-relaxed line-clamp-3">
                        {form.description || "No description available"}
                      </p>

                      {/* Footer */}
                      {!limitReached && (
                        <div className="pt-3 border-t border-border/50 flex items-center justify-between">
                          <span className="text-xs font-medium text-muted-foreground group-hover:text-primary motion-safe:transition-colors">
                            Open form
                          </span>
                          <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary/10 group-hover:bg-primary/20 motion-safe:transition-colors">
                            <Plus className="h-3 w-3 text-primary" />
                          </span>
                        </div>
                      )}
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        )}
        </div>

        {/* ── Help banner ──────────────────────────────────────────── */}
        <div className="flex items-start gap-4 p-4 sm:p-5 rounded-xl bg-card border border-border/50">
          <span className="flex items-center justify-center w-9 h-9 rounded-lg bg-primary/10 flex-shrink-0">
            <LifeBuoy className="h-4 w-4 text-primary" />
          </span>
          <div className="space-y-0.5">
            <p className="text-sm font-semibold text-foreground">Need assistance?</p>
            <p className="text-xs text-muted-foreground leading-relaxed">
              Contact support if you need help selecting or completing a form. We're here to help you through the process.
            </p>
          </div>
        </div>

      </div>
    </AppLayout>
  );
}
