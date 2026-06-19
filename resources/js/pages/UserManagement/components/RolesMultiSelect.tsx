import * as React from "react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { ChevronsUpDown } from "lucide-react";
import type { Role } from "../types";

interface RolesMultiSelectProps {
  roles: Role[];
  /**
   * Selected role IDs. Must contain values matching `Role.id`
   * (same as `UserRole.role_id` from the User type — both are the same PK).
   */
  value: number[];
  onChange: (ids: number[]) => void;
  placeholder?: string;
  id?: string;
}

export default function RolesMultiSelect({
  roles,
  value,
  onChange,
  placeholder = "Select roles",
  id,
}: RolesMultiSelectProps) {
  const [open, setOpen] = React.useState(false);
  const [q, setQ] = React.useState("");

  const labelOf = (r: Role) => r.role_name;

  const visible = React.useMemo(
    () => (q.trim() ? roles.filter((r) => labelOf(r).toLowerCase().includes(q.trim().toLowerCase())) : roles),
    [roles, q]
  );

  const toggle = (id: number) => {
    onChange(value.includes(id) ? value.filter((v) => v !== id) : [...value, id]);
  };

  const selected = roles.filter((r) => value.includes(r.id));

  const handleOpenChange = (next: boolean) => {
    setOpen(next);
    if (!next) setQ("");
  };

  const ariaLabel =
    selected.length === 0
      ? placeholder
      : `${placeholder}: ${selected.length} selected`;

  return (
    <DropdownMenu open={open} onOpenChange={handleOpenChange}>
      <DropdownMenuTrigger asChild>
        <Button
          id={id}
          type="button"
          variant="outline"
          className="w-full justify-between"
          aria-label={ariaLabel}
          aria-haspopup="listbox"
          aria-expanded={open}
        >
          {selected.length === 0 ? (
            <span className="text-muted-foreground">{placeholder}</span>
          ) : (
            <div className="flex flex-wrap gap-1">
              {selected.map((r) => (
                <Badge key={r.id} variant="secondary" className="px-2">
                  {labelOf(r)}
                </Badge>
              ))}
            </div>
          )}
          <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="start" className="w-72 p-2">
        <DropdownMenuLabel className="px-2 py-1.5">Roles</DropdownMenuLabel>
        <div className="px-2 pb-2">
          <Input
            placeholder="Search roles..."
            value={q}
            onChange={(e) => setQ(e.target.value)}
            // Prevent Radix DropdownMenu from intercepting keystrokes while typing.
            // Allow Escape through so the menu can still be dismissed.
            onKeyDown={(e) => { if (e.key !== 'Escape') e.stopPropagation(); }}
          />
        </div>
        <DropdownMenuSeparator />
        <div
          role="listbox"
          aria-multiselectable="true"
          aria-label={placeholder}
          className="max-h-64 overflow-auto pr-1"
        >
          {visible.length === 0 ? (
            <div className="px-2 py-2 text-sm text-muted-foreground">No roles found.</div>
          ) : (
            visible.map((r) => (
              <DropdownMenuCheckboxItem
                key={r.id}
                checked={value.includes(r.id)}
                onCheckedChange={() => toggle(r.id)}
                className="capitalize"
                aria-selected={value.includes(r.id)}
              >
                {labelOf(r)}
              </DropdownMenuCheckboxItem>
            ))
          )}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
