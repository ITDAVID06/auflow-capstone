import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { toast } from "sonner";
import { Camera, Eye, EyeOff, Loader2 } from "lucide-react";
import RolesMultiSelect from "./RolesMultiSelect";
import type { User, Role, UserStatusOption } from "../types";

type FormData = {
  username: string;
  email: string;
  password: string;

  first_name: string;
  middle_name: string;
  last_name: string;

  student_id: string;
  employee_id: string;

  phone: string;
  address: string;
  date_of_birth: string;
  gender: string | "";

  user_status_id: number | "";

  role_ids: number[];
  profile_picture: File | null;
};

interface UserFormProps {
  user: User | null;
  roles: Role[];
  statuses: UserStatusOption[];
  method?: "post" | "put";
  onSubmit: () => void;
  onClose: () => void;
  onDirtyChange?: (dirty: boolean) => void;
}

export default function UserForm({
  user,
  roles,
  statuses,
  method = "post",
  onSubmit,
  onClose,
  onDirtyChange,
}: UserFormProps) {
  // Password visibility state
  const [showPassword, setShowPassword] = useState(false);
  const [showLeaveConfirm, setShowLeaveConfirm] = useState(false);
  const [avatarPreviewUrl, setAvatarPreviewUrl] = useState<string | null>(null);

  // Get today's date for max date restriction
  const today = new Date().toISOString().split("T")[0];

  const {
    data,
    setData,
    post,
    put,
    processing,
    reset,
    errors,
    transform,
    isDirty,
  } = useForm<FormData>({
    username: user?.name ?? "",
    email: user?.email ?? "",
    password: "",

    first_name:  user?.profile?.first_name  ?? "",
    middle_name: user?.profile?.middle_name ?? "",
    last_name:   user?.profile?.last_name   ?? "",

    student_id:  user?.profile?.student_id  ?? "",
    employee_id: user?.profile?.employee_id ?? "",

    phone:        user?.profile?.phone        ?? "",
    address:      user?.profile?.address      ?? "",
    date_of_birth:user?.profile?.date_of_birth?? "",
    gender:      (user?.profile?.gender as any) ?? "",

    user_status_id: user?.status?.id ?? "",

    role_ids: (user?.roles ?? []).map((r) => r.role_id),
    profile_picture: null,
  });

  // Notify parent when dirty state changes
  useEffect(() => {
    onDirtyChange?.(isDirty);
  }, [isDirty]);

  // Cleanup avatar preview URL on unmount or when it changes
  useEffect(() => {
    return () => { if (avatarPreviewUrl) URL.revokeObjectURL(avatarPreviewUrl); };
  }, [avatarPreviewUrl]);

  // Live avatar preview handler
  const handleAvatarChange = (file: File | null) => {
    if (avatarPreviewUrl) URL.revokeObjectURL(avatarPreviewUrl);
    setAvatarPreviewUrl(file ? URL.createObjectURL(file) : null);
    setData("profile_picture", file);
  };

  // Derived display values for the sidebar preview
  const liveInitials =
    ((data.first_name?.[0] ?? '') + (data.last_name?.[0] ?? '')).toUpperCase() ||
    (data.username?.[0] ?? '').toUpperCase() ||
    '?';
  const liveFullName =
    [data.first_name, data.last_name].filter(Boolean).join(' ') ||
    data.username ||
    '';
  const displayAvatarUrl =
    avatarPreviewUrl ??
    user?.profile?.profile_picture_url ??
    (user?.profile?.profile_picture
      ? user.profile.profile_picture.startsWith('http') ||
        user.profile.profile_picture.startsWith('/')
        ? user.profile.profile_picture
        : `/storage/${user.profile.profile_picture}`
      : undefined);

  const requestClose = () => {
    if (isDirty) {
      setShowLeaveConfirm(true);
    } else {
      onClose();
    }
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();

    // Coerce empty strings to null & drop blank password on PUT
    transform((d) => {
      const out: any = { ...d };

      ["user_status_id"].forEach((k) => {
        if (out[k] === "") out[k] = null;
      });

      if (method === "put" && (!out.password || out.password.trim() === "")) {
        delete out.password;
      }

      if (out.profile_picture === null) delete out.profile_picture;

      return out;
    });

    if (method === "put" && user) {
      put(route('user-management.users.update', user.account_id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
          toast.success("User updated successfully");
          onSubmit();
          reset("password");
        },
        onFinish: () => transform((x) => x),
      });
    } else {
      post(route('user-management.users.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
          toast.success("User created successfully");
          onSubmit();
          reset("password");
        },
        onFinish: () => transform((x) => x),
      });
    }
  };

  return (
    <form onSubmit={submit}>
      <div className="flex flex-col lg:flex-row gap-6 pb-24">

        {/* ── Left sidebar ── */}
        <aside className="lg:w-56 xl:w-64 shrink-0 space-y-4">

          {/* Avatar upload + live preview */}
          <div className="flex flex-col items-center rounded-xl border border-border/70 bg-card/50 px-4 py-5 text-center">
            <div className="relative">
              <Avatar className="h-20 w-20 ring-2 ring-border/60">
                <AvatarImage src={displayAvatarUrl} alt="" />
                <AvatarFallback className="text-lg font-semibold">{liveInitials}</AvatarFallback>
              </Avatar>
              <label
                htmlFor="profile_picture"
                className="absolute -bottom-1 -right-1 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-border/60 bg-background text-muted-foreground motion-safe:transition-colors hover:bg-accent hover:text-foreground"
                aria-label="Upload profile picture"
              >
                <Camera className="h-3.5 w-3.5" aria-hidden="true" />
              </label>
            </div>
            <input
              id="profile_picture"
              type="file"
              accept="image/*"
              className="sr-only"
              onChange={(e) => handleAvatarChange(e.target.files?.[0] ?? null)}
            />
            <div className="mt-3 min-w-0 w-full">
              <p
                className="text-sm font-semibold text-foreground truncate"
                title={liveFullName || undefined}
              >
                {liveFullName || <span className="italic text-muted-foreground">No name yet</span>}
              </p>
              <p className="text-xs text-muted-foreground truncate">
                {data.username ? `@${data.username}` : <span className="italic">No username</span>}
              </p>
            </div>
            <p className="mt-2 text-[11px] text-muted-foreground/70">JPG, PNG or GIF · max 2 MB</p>
            {data.profile_picture && (
              <p className="mt-0.5 text-[11px] text-emerald-600 dark:text-emerald-400 truncate max-w-full">
                {data.profile_picture.name}
              </p>
            )}
            {errors.profile_picture && (
              <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.profile_picture}</p>
            )}
          </div>

          {/* Status */}
          <div className="space-y-3 rounded-xl border border-border/70 bg-card/50 p-4">
            <h3 className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
              Account Status
            </h3>
            <Select
              value={data.user_status_id === "" ? "" : String(data.user_status_id)}
              onValueChange={(v) => setData("user_status_id", v ? Number(v) : "")}
            >
              <SelectTrigger id="user-status" className="motion-safe:transition-colors">
                <SelectValue placeholder="Select status" />
              </SelectTrigger>
              <SelectContent>
                {statuses.length > 0 ? (
                  statuses.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>
                      {s.status_name}
                    </SelectItem>
                  ))
                ) : (
                  <SelectItem value="__empty_status" disabled>
                    No statuses available
                  </SelectItem>
                )}
              </SelectContent>
            </Select>
            {errors.user_status_id && (
              <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                {errors.user_status_id}
              </p>
            )}
          </div>

          {/* Roles */}
          <div className="space-y-3 rounded-xl border border-border/70 bg-card/50 p-4">
            <h3 className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
              Roles
            </h3>
            <RolesMultiSelect
              id="roles-select"
              roles={roles}
              value={data.role_ids}
              onChange={(ids) => setData("role_ids", ids)}
            />
            {errors.role_ids && (
              <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                {errors.role_ids}
              </p>
            )}
          </div>

        </aside>

        {/* ── Right main ── */}
        <div className="flex-1 min-w-0 space-y-5">

          {/* Account */}
          <div className="space-y-4 rounded-xl border border-border/70 bg-card/60 p-4 md:p-5">
            <h3 className="border-b border-border/70 pb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
              Account
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="username" className="text-sm font-medium">
                  Username <span className="text-red-500" aria-hidden="true">*</span>
                </Label>
                <Input
                  id="username"
                  value={data.username}
                  onChange={(e) => setData("username", e.target.value)}
                  placeholder="Enter username"
                  autoComplete="username"
                  spellCheck={false}
                  className="motion-safe:transition-colors"
                />
                {errors.username && (
                  <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                    <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                    {errors.username}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="email" className="text-sm font-medium">
                  Email <span className="text-red-500" aria-hidden="true">*</span>
                </Label>
                <Input
                  id="email"
                  type="email"
                  value={data.email}
                  onChange={(e) => setData("email", e.target.value)}
                  placeholder="email@example.com"
                  autoComplete="email"
                  spellCheck={false}
                  className="motion-safe:transition-colors"
                />
                {errors.email && (
                  <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                    <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                    {errors.email}
                  </p>
                )}
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="password" className="text-sm font-medium">
                Password{' '}
                {method === "put" ? (
                  <span className="text-xs font-normal text-muted-foreground">(leave blank to keep current)</span>
                ) : (
                  <span className="text-red-500" aria-hidden="true">*</span>
                )}
              </Label>
              <div className="relative">
                <Input
                  id="password"
                  type={showPassword ? "text" : "password"}
                  value={data.password}
                  onChange={(e) => setData("password", e.target.value)}
                  placeholder={method === "put" ? "Enter new password (optional)" : "Enter password"}
                  autoComplete="new-password"
                  className="pr-10 motion-safe:transition-colors"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  className="absolute inset-y-0 right-3 grid place-items-center text-muted-foreground motion-safe:transition-opacity hover:opacity-70"
                  aria-label={showPassword ? "Hide password" : "Show password"}
                >
                  {showPassword ? (
                    <EyeOff className="h-4 w-4" aria-hidden="true" />
                  ) : (
                    <Eye className="h-4 w-4" aria-hidden="true" />
                  )}
                </button>
              </div>
              {errors.password && (
                <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                  <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                  {errors.password}
                </p>
              )}
            </div>
          </div>

          {/* Personal Information */}
          <div className="space-y-4 rounded-xl border border-border/70 bg-card/60 p-4 md:p-5">
            <h3 className="border-b border-border/70 pb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
              Personal Information
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label htmlFor="first_name" className="text-sm font-medium">
                  First Name <span className="text-red-500" aria-hidden="true">*</span>
                </Label>
                <Input
                  id="first_name"
                  value={data.first_name}
                  onChange={(e) => setData("first_name", e.target.value)}
                  placeholder="First name"
                  className="motion-safe:transition-colors"
                />
                {errors.first_name && (
                  <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                    <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                    {errors.first_name}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="middle_name" className="text-sm font-medium">Middle Name</Label>
                <Input
                  id="middle_name"
                  value={data.middle_name}
                  onChange={(e) => setData("middle_name", e.target.value)}
                  placeholder="Middle name"
                  className="motion-safe:transition-colors"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="last_name" className="text-sm font-medium">
                  Last Name <span className="text-red-500" aria-hidden="true">*</span>
                </Label>
                <Input
                  id="last_name"
                  value={data.last_name}
                  onChange={(e) => setData("last_name", e.target.value)}
                  placeholder="Last name"
                  className="motion-safe:transition-colors"
                />
                {errors.last_name && (
                  <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                    <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                    {errors.last_name}
                  </p>
                )}
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="date_of_birth" className="text-sm font-medium">Date of Birth</Label>
                <Input
                  id="date_of_birth"
                  type="date"
                  value={data.date_of_birth}
                  min="1900-01-01"
                  max={today}
                  autoComplete="bday"
                  onChange={(e) => setData("date_of_birth", e.target.value)}
                  className="motion-safe:transition-colors"
                />
                {errors.date_of_birth && (
                  <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                    <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                    {errors.date_of_birth}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="gender" className="text-sm font-medium">Gender</Label>
                <Select value={data.gender} onValueChange={(v: any) => setData("gender", v)}>
                  <SelectTrigger id="gender" className="motion-safe:transition-colors">
                    <SelectValue placeholder="Select gender" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Male">Male</SelectItem>
                    <SelectItem value="Female">Female</SelectItem>
                    <SelectItem value="Other">Other</SelectItem>
                  </SelectContent>
                </Select>
                {errors.gender && (
                  <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                    <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                    {errors.gender}
                  </p>
                )}
              </div>
            </div>
          </div>

          {/* Contact & Identification */}
          <div className="space-y-4 rounded-xl border border-border/70 bg-card/60 p-4 md:p-5">
            <h3 className="border-b border-border/70 pb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
              Contact &amp; Identification
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="phone" className="text-sm font-medium">Phone</Label>
                <Input
                  id="phone"
                  type="tel"
                  inputMode="tel"
                  value={data.phone}
                  onChange={(e) => setData("phone", e.target.value)}
                  placeholder="Phone number"
                  autoComplete="tel"
                  className="motion-safe:transition-colors"
                />
                {errors.phone && (
                  <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                    <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                    {errors.phone}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="address" className="text-sm font-medium">Address</Label>
                <Input
                  id="address"
                  value={data.address}
                  onChange={(e) => setData("address", e.target.value)}
                  placeholder="Address"
                  className="motion-safe:transition-colors"
                />
                {errors.address && (
                  <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                    <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                    {errors.address}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="student_id" className="text-sm font-medium">
                  Student ID{' '}
                  <span className="text-xs font-normal text-muted-foreground">(Optional)</span>
                </Label>
                <Input
                  id="student_id"
                  value={data.student_id}
                  onChange={(e) => setData("student_id", e.target.value)}
                  placeholder="Student ID"
                  className="motion-safe:transition-colors"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="employee_id" className="text-sm font-medium">
                  Employee ID{' '}
                  <span className="text-xs font-normal text-muted-foreground">(Optional)</span>
                </Label>
                <Input
                  id="employee_id"
                  value={data.employee_id}
                  onChange={(e) => setData("employee_id", e.target.value)}
                  placeholder="Employee ID"
                  className="motion-safe:transition-colors"
                />
              </div>
            </div>
          </div>

        </div>
      </div>

      {/* Sticky footer */}
      <div className="sticky bottom-0 z-20 flex justify-end gap-3 border-t border-border/70 bg-background px-3 pt-4 pb-2">
        <Button
          type="button"
          variant="outline"
          onClick={requestClose}
          disabled={processing}
          className="touch-manipulation"
        >
          Cancel
        </Button>
        <Button
          type="submit"
          disabled={processing}
          className="min-w-[120px] touch-manipulation"
        >
          {processing ? (
            <span className="flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              {method === "put" ? "Saving…" : "Creating…"}
            </span>
          ) : (
            method === "put" ? "Save Changes" : "Create User"
          )}
        </Button>
      </div>

      <AlertDialog open={showLeaveConfirm} onOpenChange={setShowLeaveConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Unsaved changes</AlertDialogTitle>
            <AlertDialogDescription>
              You have unsaved changes. Are you sure you want to close without saving?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Stay</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => { setShowLeaveConfirm(false); onClose(); }}
            >
              Discard &amp; close
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </form>
  );
}