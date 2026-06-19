import React, { useState } from "react"
import AppLayout from "@/layouts/app-layout"
import { Head, Link, router } from "@inertiajs/react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  Pencil,
  CheckCircle2,
  XCircle,
  Plus,
  Building2,
  Archive,
  ChevronLeft,
} from "lucide-react"
import { toast } from "sonner"

// ─── Types ────────────────────────────────────────────────────────────────────

type Facility = {
  id: number
  name: string
  description?: string | null
  is_active: boolean
}

interface Props {
  facilities: Facility[]
}

// ─── Page ─────────────────────────────────────────────────────────────────────

const FacilityListPage: React.FC<Props> = ({ facilities }) => {
  const [editing, setEditing] = useState<number | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState<{ name: string; description: string }>({
    name: "",
    description: "",
  })
  const [filter, setFilter] = useState<"all" | "active" | "inactive">("all")
  const [archiveTarget, setArchiveTarget] = useState<Facility | null>(null)
  const [deactivateTarget, setDeactivateTarget] = useState<Facility | null>(null)

  const handleSave = (id?: number) => {
    if (!form.name.trim()) {
      toast.error("Facility name is required")
      return
    }

    if (id) {
      router.put(
        `/admin/facilities/${id}`,
        { ...form },
        {
          onSuccess: () => {
            toast.success("Facility updated")
            setEditing(null)
            setShowForm(false)
            setForm({ name: "", description: "" })
          },
          onError: () => toast.error("Update failed"),
        },
      )
    } else {
      router.post(
        "/admin/facilities",
        { ...form },
        {
          onSuccess: () => {
            toast.success("Facility created")
            setShowForm(false)
            setForm({ name: "", description: "" })
          },
          onError: () => toast.error("Create failed"),
        },
      )
    }
  }

  const handleEdit = (facility: Facility) => {
    setEditing(facility.id)
    setForm({
      name: facility.name,
      description: facility.description ?? "",
    })
  }

  const handleToggleActive = (facility: Facility) => {
    if (facility.is_active) {
      setDeactivateTarget(facility)
      return
    }
    router.put(
      `/admin/facilities/${facility.id}/toggle`,
      { is_active: true },
      {
        onSuccess: () => toast.success("Facility activated"),
        onError: () => toast.error("Update failed"),
      },
    )
  }

  const confirmDeactivate = () => {
    if (!deactivateTarget) return
    router.put(
      `/admin/facilities/${deactivateTarget.id}/toggle`,
      { is_active: false },
      {
        onSuccess: () => {
          toast.success("Facility deactivated")
          setDeactivateTarget(null)
        },
        onError: () => toast.error("Update failed"),
      },
    )
  }

  const confirmArchive = () => {
    if (!archiveTarget) return
    router.delete(`/admin/facilities/${archiveTarget.id}`, {
      onSuccess: () => {
        toast.success("Facility archived")
        setArchiveTarget(null)
      },
      onError: (errors) => {
        const message =
          typeof errors === "object" && "message" in errors
            ? String(errors.message)
            : "Cannot archive facility with existing bookings"
        toast.error(message)
        setArchiveTarget(null)
      },
    })
  }

  const cancelEditing = () => {
    setEditing(null)
    setShowForm(false)
    setForm({ name: "", description: "" })
  }

  const filteredFacilities = facilities.filter((f) => {
    if (filter === "active") return f.is_active
    if (filter === "inactive") return !f.is_active
    return true
  })

  const filterTabs: { label: string; value: "all" | "active" | "inactive" }[] = [
    { label: "All", value: "all" },
    { label: "Active", value: "active" },
    { label: "Inactive", value: "inactive" },
  ]

  return (
    <>
      <Head title="Manage Facilities" />

      <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        {/* Back link */}
        <div>
          <Link
            href="/admin/facilities/calendar"
            className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
          >
            <ChevronLeft className="h-4 w-4" />
            Facility Dashboard
          </Link>
        </div>

        {/* Toolbar */}
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-1 rounded-md border border-border/60 bg-card p-0.5">
            {filterTabs.map((tab) => (
              <button
                key={tab.value}
                type="button"
                onClick={() => setFilter(tab.value)}
                className={`rounded px-3 py-1.5 text-xs font-medium transition-colors touch-manipulation ${
                  filter === tab.value
                    ? "bg-primary text-primary-foreground"
                    : "text-muted-foreground hover:text-foreground hover:bg-accent/50"
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>

          {editing === null && (
            <Button
              size="sm"
              variant={showForm ? "outline" : "default"}
              onClick={() => setShowForm((v) => !v)}
              className="touch-manipulation"
            >
              {showForm ? (
                "Cancel"
              ) : (
                <>
                  <Plus className="mr-1.5 h-3.5 w-3.5" />
                  Add Facility
                </>
              )}
            </Button>
          )}
        </div>

        {/* Add / Edit Form */}
        {(showForm || editing !== null) && (
          <div className="rounded-lg border border-border/60 bg-card px-5 py-5">
            <h2 className="mb-4 text-sm font-semibold text-foreground">
              {editing ? "Edit Facility" : "Add Facility"}
            </h2>
            <div className="max-w-md space-y-3">
              <Input
                placeholder="Facility name"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                onKeyDown={(e) => e.key === "Enter" && handleSave(editing ?? undefined)}
              />
              <Textarea
                placeholder="Description (optional)"
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                rows={2}
              />
              <div className="flex gap-2">
                <Button size="sm" onClick={() => handleSave(editing ?? undefined)}>
                  {editing ? "Update" : "Add Facility"}
                </Button>
                <Button size="sm" variant="outline" onClick={cancelEditing}>
                  Cancel
                </Button>
              </div>
            </div>
          </div>
        )}

        {/* Facility List */}
        <div className="overflow-hidden rounded-lg border border-border/60 bg-card">
          {/* Table header */}
          <div className="hidden sm:grid grid-cols-[minmax(0,1fr)_180px] gap-4 border-b border-border/60 bg-muted/40 px-5 py-2.5">
            <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
              Facility
            </span>
            <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground text-right">
              Actions
            </span>
          </div>

          {filteredFacilities.length === 0 ? (
            <div className="py-20 text-center">
              <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl border border-border/60 bg-muted/40">
                <Building2 className="h-5 w-5 text-muted-foreground" />
              </div>
              <p className="text-sm font-semibold text-foreground">
                No facilities found
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                {filter !== "all"
                  ? "Try adjusting your filter"
                  : "Get started by adding your first facility above"}
              </p>
            </div>
          ) : (
            <div className="divide-y divide-border/40">
              {filteredFacilities.map((f) => (
                <div
                  key={f.id}
                  className="group grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_180px] sm:items-center hover:bg-accent/30 transition-colors"
                >
                  {/* Info */}
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="text-sm font-medium text-foreground">
                        {f.name}
                      </p>
                      {!f.is_active && (
                        <span className="inline-flex items-center rounded bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                          Inactive
                        </span>
                      )}
                    </div>
                    {f.description && (
                      <p className="mt-0.5 text-xs text-muted-foreground line-clamp-1">
                        {f.description}
                      </p>
                    )}
                  </div>

                  {/* Actions */}
                  <div className="flex shrink-0 items-center justify-start gap-1 sm:justify-end sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100 transition-opacity">
                    <Button
                      size="sm"
                      variant="ghost"
                      className="h-8 px-2.5 text-muted-foreground hover:text-foreground touch-manipulation"
                      onClick={() => handleEdit(f)}
                    >
                      <Pencil className="h-3.5 w-3.5" />
                      <span className="ml-1.5 text-xs">Edit</span>
                    </Button>

                    <Button
                      size="sm"
                      variant="ghost"
                      className={`h-8 px-2.5 text-xs touch-manipulation ${
                        f.is_active
                          ? "text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                          : "text-muted-foreground hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-500/10"
                      }`}
                      onClick={() => handleToggleActive(f)}
                    >
                      {f.is_active ? (
                        <>
                          <XCircle className="h-3.5 w-3.5" />
                          <span className="ml-1.5">Deactivate</span>
                        </>
                      ) : (
                        <>
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          <span className="ml-1.5">Activate</span>
                        </>
                      )}
                    </Button>

                    <Button
                      size="sm"
                      variant="ghost"
                      className="h-8 px-2.5 text-xs text-muted-foreground hover:text-destructive hover:bg-destructive/10 touch-manipulation"
                      onClick={() => setArchiveTarget(f)}
                    >
                      <Archive className="h-3.5 w-3.5" />
                      <span className="ml-1.5">Archive</span>
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Deactivate Confirmation */}
      <Dialog open={deactivateTarget !== null} onOpenChange={() => setDeactivateTarget(null)}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>Deactivate Facility</DialogTitle>
            <DialogDescription>
              Are you sure you want to deactivate &ldquo;{deactivateTarget?.name}&rdquo;? It will
              no longer be available for booking but can be reactivated later.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" size="sm" onClick={() => setDeactivateTarget(null)}>
              Cancel
            </Button>
            <Button variant="destructive" size="sm" onClick={confirmDeactivate}>
              Deactivate
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Archive Confirmation */}
      <Dialog open={archiveTarget !== null} onOpenChange={() => setArchiveTarget(null)}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>Archive Facility</DialogTitle>
            <DialogDescription>
              Are you sure you want to archive &ldquo;{archiveTarget?.name}&rdquo;? Facilities with
              existing bookings cannot be archived.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" size="sm" onClick={() => setArchiveTarget(null)}>
              Cancel
            </Button>
            <Button variant="destructive" size="sm" onClick={confirmArchive}>
              Archive
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

FacilityListPage.displayName = "FacilityListPage"

export default FacilityListPage

;(
  FacilityListPage as React.FC<Props> & { layout?: (page: React.ReactNode) => React.ReactNode }
).layout = (page: React.ReactNode) => (
  <AppLayout title="Manage Facilities" subtitle="Add, edit, and manage facility availability">
    {page}
  </AppLayout>
)
