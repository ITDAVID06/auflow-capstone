import React, { useEffect, useMemo, useState } from "react";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import axios from "axios";
import { FormBuilderState } from "../types/formBuilderTypes";
import { listFormPermissionsApi, listFormCategoriesApi } from "../api/formBuilderApi";

type Perm = { id: number; permission_name: string; resource: string; action: string };
type Category = { id: number; name: string };

const AUDIENCE_TO_ACTION: Record<string, string> = {
  student: "student-access",
  staff: "staff-access",
  public: "public-access",
  hidden: "",
};

const ADD_NEW = "__add_new__";

export function SettingsPanel({
  form,
  setForm,
  disabled = false,
}: {
  form: FormBuilderState;
  setForm: React.Dispatch<React.SetStateAction<FormBuilderState>>;
  disabled?: boolean;
}) {
  const [perms, setPerms] = useState<Perm[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [showAddCat, setShowAddCat] = useState(false);
  const [newCategory, setNewCategory] = useState("");
  const [addCatError, setAddCatError] = useState<string | null>(null);

  useEffect(() => {
    listFormPermissionsApi()
      .then((res) => setPerms(res.data ?? []))
      .catch(() => setPerms([]));
    listFormCategoriesApi()
      .then((res) => setCategories(res.data ?? []))
      .catch(() => setCategories([]));
  }, []);

  const actionToId = useMemo(() => {
    const map: Record<string, number> = {};
    perms.forEach((p) => (map[p.action] = p.id));
    return map;
  }, [perms]);

  const currentAudience = useMemo(() => {
    if (!form.permissions || form.permissions.length === 0) {
      return form.id ? "hidden" : "public";
    }
    const pid = form.permissions[0];
    const perm = perms.find((p) => p.id === pid);
    if (!perm) return "hidden";
    if (perm.action === "student-access") return "student";
    if (perm.action === "staff-access") return "staff";
    if (perm.action === "public-access") return "public";
    return "hidden";
  }, [form.permissions, perms]);

  const setAudience = (val: string) => {
    const action = AUDIENCE_TO_ACTION[val];
    if (!action) {
      setForm((f) => ({ ...f, permissions: [] }));
      return;
    }
    const id = actionToId[action];
    setForm((f) => ({ ...f, permissions: id ? [id] : [] }));
  };

  useEffect(() => {
    if (form.id) return;
    if ((form.permissions?.length ?? 0) > 0) return;

    const publicPermissionId = actionToId["public-access"];
    if (!publicPermissionId) return;

    setForm((f) => {
      if (f.id || (f.permissions?.length ?? 0) > 0) {
        return f;
      }

      return {
        ...f,
        permissions: [publicPermissionId],
      };
    });
  }, [form.id, form.permissions, actionToId, setForm]);

  const onCategoryChange = (val: string) => {
    if (val === ADD_NEW) {
      setNewCategory("");
      setShowAddCat(true);
      return;
    }
    setForm((f) => ({ ...f, form_category_id: val ? parseInt(val, 10) : null }));
  };

  const confirmAddCategory = async () => {
    const name = newCategory.trim();
    if (!name) return;
    setAddCatError(null);

    try {
      const res = await axios.post("/admin/forms/categories", {
        name,
        slug: name.toLowerCase().replace(/\s+/g, "-"),
      });
      const created: Category = res.data;
      setCategories((prev) => [...prev, created]);
      setForm((f) => ({ ...f, form_category_id: created.id }));
      setShowAddCat(false);
    } catch {
      setAddCatError("Could not save category. Please try again.");
    }
  };

  return (
    <>
      <div className="mb-4">
        <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
          Global Settings
        </h3>
      </div>

      <div className="space-y-6">
        <div className="space-y-2">
          <p className="text-xs font-medium text-foreground/80">Visible To</p>
          <Select value={currentAudience} onValueChange={setAudience} disabled={disabled}>
            <SelectTrigger className="h-8 text-sm">
              <SelectValue placeholder="Select audience" />
            </SelectTrigger>
              <SelectContent>
                <SelectItem value="student">Student</SelectItem>
                <SelectItem value="staff">Staff</SelectItem>
                <SelectItem value="public">Public (Everyone)</SelectItem>
                <SelectItem value="hidden">Hidden</SelectItem>
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              Sets a single audience permission. “Hidden” clears the permission.
            </p>
          </div>

        <div className="space-y-2">
          <p className="text-xs font-medium text-foreground/80">Category</p>
          <Select
            value={form.form_category_id ? String(form.form_category_id) : ""}
            onValueChange={onCategoryChange}
            disabled={disabled}
          >
            <SelectTrigger className="h-8 text-sm">
                <SelectValue placeholder="Select category" />
              </SelectTrigger>
              <SelectContent>
                {categories.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>
                    {c.name}
                  </SelectItem>
                ))}
                <SelectItem value={ADD_NEW}>+ Add new category…</SelectItem>
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              Used to organize forms.
            </p>
          </div>

        <div className="space-y-2">
          <p className="text-xs font-medium text-foreground/80">Submission Limit</p>
          <Input
            type="number"
            min={1}
            placeholder="Unlimited"
            value={form.submission_limit}
            onChange={(e) => setForm((f) => ({ ...f, submission_limit: e.target.value }))}
            disabled={disabled}
            className="h-8 text-sm"
          />
          <p className="text-xs text-muted-foreground">Leave blank for no limit.</p>
        </div>
      </div>

      <Dialog open={showAddCat} onOpenChange={(open) => { setShowAddCat(open); if (!open) setAddCatError(null); }}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Add Category</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Input
              autoFocus
              placeholder="e.g., Admissions, Finance, IT"
              value={newCategory}
              onChange={(e) => setNewCategory(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && confirmAddCategory()}
            />
            {addCatError && (
              <p className="text-xs text-destructive">{addCatError}</p>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setShowAddCat(false); setAddCatError(null); }}>
              Cancel
            </Button>
            <Button onClick={confirmAddCategory}>Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
