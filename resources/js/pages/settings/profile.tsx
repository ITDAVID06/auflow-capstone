import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { toast } from 'sonner';
import { Camera, Loader2, Lock, Mail, Upload, User } from 'lucide-react';

// ─── Types ────────────────────────────────────────────────────────────────────

interface ProfileFormData {
  username: string;
  first_name: string;
  middle_name: string;
  last_name: string;
  address: string;
  student_id: string;
  employee_id: string;
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function Profile() {
  const { auth, flash } = usePage<SharedData & { flash?: { success?: string; error?: string } }>().props;
  const profile = auth.user.profile;

  const profileImageUrl =
    auth.user.avatar ||
    profile?.profile_picture_url ||
    (profile?.profile_picture
      ? profile.profile_picture.startsWith('http') || profile.profile_picture.startsWith('/')
        ? profile.profile_picture
        : `/storage/${profile.profile_picture}`
      : null);

  // ── Profile picture form ───────────────────────────────────────────────────
  const { data: picData, setData: setPicData, post: postPic, processing: picProcessing } = useForm<{
    profile_picture: File | null;
  }>({ profile_picture: null });

  const submitPicture = (e: React.FormEvent) => {
    e.preventDefault();
    postPic(route('profile.picture.update'), {
      forceFormData: true,
      onSuccess: () => {
        toast.success('Profile picture updated successfully');
        setPicData('profile_picture', null);
        window.location.reload();
      },
      onError: (errors) => {
        toast.error(errors.profile_picture || 'Failed to upload profile picture');
      },
    });
  };

  // ── Profile info form ──────────────────────────────────────────────────────
  const {
    data,
    setData,
    patch,
    processing,
    errors,
    reset,
  } = useForm<ProfileFormData>({
    username: auth.user.username ?? '',
    first_name: profile?.first_name ?? '',
    middle_name: profile?.middle_name ?? '',
    last_name: profile?.last_name ?? '',
    address: profile?.address ?? '',
    student_id: profile?.student_id ?? '',
    employee_id: profile?.employee_id ?? '',
  });

  const isStudent = auth.user.roles?.some((r) => r.role_name === 'Student') ?? false;

  // Show Sonner toast on Inertia flash success
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success);
    }
    if (flash?.error) {
      toast.error(flash.error);
    }
  }, [flash?.success, flash?.error]);

  const submitProfile = (e: React.FormEvent) => {
    e.preventDefault();
    patch(route('profile.update'), {
      preserveScroll: true,
      onError: () => {
        // Validation errors are rendered inline — no extra toast needed
      },
    });
  };

  const initials = auth.user.name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase();

  return (
    <AppLayout>
      <Head title="Profile Settings" />
      <SettingsLayout>
        <div className="space-y-7">
          {/* Section Header */}
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Profile Information</h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Manage your profile picture and personal details
            </p>
          </div>

          <div className="border-t border-gray-200 dark:border-gray-700" />

          {/* Profile Picture */}
          <div>
            <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-gray-100">Profile Picture</h3>
            <div className="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
              {/* Avatar */}
              <div className="relative flex-shrink-0">
                {profileImageUrl ? (
                  <img
                    src={profileImageUrl}
                    alt="Profile"
                    className="h-20 w-20 rounded-full border-2 border-gray-200 object-cover dark:border-gray-700"
                  />
                ) : (
                  <div className="flex h-20 w-20 items-center justify-center rounded-full border-2 border-gray-200 bg-gray-100 text-xl font-semibold text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {initials}
                  </div>
                )}
                <div className="absolute bottom-0 right-0 flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-blue-600 dark:border-gray-900">
                  <Camera className="h-3.5 w-3.5 text-white" />
                </div>
              </div>

              {/* Upload Controls */}
              <div className="min-w-0 flex-1">
                <p className="mb-3 text-sm text-gray-500 dark:text-gray-400">
                  Upload a new profile picture. Accepted formats: JPG, PNG, GIF (max 2&nbsp;MB).
                </p>
                <form onSubmit={submitPicture} className="flex flex-wrap items-center gap-2">
                  <input
                    id="profile_picture"
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={(e) => setPicData('profile_picture', e.target.files?.[0] ?? null)}
                  />
                  <Label
                    htmlFor="profile_picture"
                    className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 motion-safe:transition-colors dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                  >
                    <Upload className="h-4 w-4" />
                    Choose File
                  </Label>

                  {picData.profile_picture && (
                    <>
                      <span className="max-w-[200px] truncate text-sm text-gray-500 dark:text-gray-400">
                        {picData.profile_picture.name}
                      </span>
                      <Button
                        type="submit"
                        size="sm"
                        disabled={picProcessing}
                        className="touch-manipulation bg-blue-600 text-white hover:bg-blue-700 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                      >
                        {picProcessing ? 'Uploading…' : 'Upload'}
                      </Button>
                    </>
                  )}
                </form>
              </div>
            </div>
          </div>

          <div className="border-t border-gray-200 dark:border-gray-700" />

          {/* Personal Information Form */}
          <div>
            <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-gray-100">Personal Information</h3>

            <form onSubmit={submitProfile} noValidate>
              <div className="grid gap-5 sm:grid-cols-2">
                {/* Username */}
                <div className="space-y-1.5">
                  <Label
                    htmlFor="username"
                    className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    <User className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                    Username
                  </Label>
                  <Input
                    id="username"
                    value={data.username}
                    onChange={(e) => setData('username', e.target.value)}
                    autoComplete="username"
                    className="focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                    aria-describedby={errors.username ? 'username-error' : undefined}
                    aria-invalid={!!errors.username}
                  />
                  {errors.username ? (
                    <p id="username-error" className="text-xs text-red-600 dark:text-red-400">
                      {errors.username}
                    </p>
                  ) : (
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                      Letters, numbers, periods, underscores, hyphens only. Must start with a letter.
                    </p>
                  )}
                </div>

                {/* Email — read-only */}
                <div className="space-y-1.5">
                  <Label
                    htmlFor="email"
                    className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    <Mail className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                    Email Address
                  </Label>
                  <div className="relative">
                    <Input
                      id="email"
                      type="email"
                      value={auth.user.email}
                      readOnly
                      disabled
                      className="cursor-not-allowed border-gray-200 bg-gray-50 pr-9 dark:border-gray-700 dark:bg-gray-800"
                    />
                    <Lock
                      className="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                      aria-hidden="true"
                    />
                  </div>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Managed by your institution.</p>
                </div>

                {/* First Name */}
                <div className="space-y-1.5">
                  <Label
                    htmlFor="first_name"
                    className="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    First Name
                  </Label>
                  <Input
                    id="first_name"
                    value={data.first_name}
                    onChange={(e) => setData('first_name', e.target.value)}
                    autoComplete="given-name"
                    className="focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                    aria-describedby={errors.first_name ? 'first-name-error' : undefined}
                    aria-invalid={!!errors.first_name}
                  />
                  {errors.first_name && (
                    <p id="first-name-error" className="text-xs text-red-600 dark:text-red-400">
                      {errors.first_name}
                    </p>
                  )}
                </div>

                {/* Last Name */}
                <div className="space-y-1.5">
                  <Label
                    htmlFor="last_name"
                    className="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    Last Name
                  </Label>
                  <Input
                    id="last_name"
                    value={data.last_name}
                    onChange={(e) => setData('last_name', e.target.value)}
                    autoComplete="family-name"
                    className="focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                    aria-describedby={errors.last_name ? 'last-name-error' : undefined}
                    aria-invalid={!!errors.last_name}
                  />
                  {errors.last_name && (
                    <p id="last-name-error" className="text-xs text-red-600 dark:text-red-400">
                      {errors.last_name}
                    </p>
                  )}
                </div>

                {/* Middle Name — full width row */}
                <div className="space-y-1.5 sm:col-span-2">
                  <Label
                    htmlFor="middle_name"
                    className="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    Middle Name{' '}
                    <span className="font-normal text-gray-400 dark:text-gray-500">(optional)</span>
                  </Label>
                  <Input
                    id="middle_name"
                    value={data.middle_name}
                    onChange={(e) => setData('middle_name', e.target.value)}
                    autoComplete="additional-name"
                    className="focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                    aria-describedby={errors.middle_name ? 'middle-name-error' : undefined}
                    aria-invalid={!!errors.middle_name}
                  />
                  {errors.middle_name && (
                    <p id="middle-name-error" className="text-xs text-red-600 dark:text-red-400">
                      {errors.middle_name}
                    </p>
                  )}
                </div>

                {/* Address — full width row */}
                <div className="space-y-1.5 sm:col-span-2">
                  <Label
                    htmlFor="address"
                    className="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    Address{' '}
                    <span className="font-normal text-gray-400 dark:text-gray-500">(optional)</span>
                  </Label>
                  <Input
                    id="address"
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                    autoComplete="street-address"
                    className="focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                    aria-describedby={errors.address ? 'address-error' : undefined}
                    aria-invalid={!!errors.address}
                  />
                  {errors.address && (
                    <p id="address-error" className="text-xs text-red-600 dark:text-red-400">
                      {errors.address}
                    </p>
                  )}
                </div>

                {/* Student ID */}
                {isStudent && (
                  <div className="space-y-1.5 sm:col-span-2">
                    <Label
                      htmlFor="student_id"
                      className="text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                      Student ID{' '}
                      <span className="font-normal text-gray-400 dark:text-gray-500">(optional)</span>
                    </Label>
                    <Input
                      id="student_id"
                      value={data.student_id}
                      onChange={(e) => setData('student_id', e.target.value)}
                      className="focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                      aria-describedby={errors.student_id ? 'student-id-error' : undefined}
                      aria-invalid={!!errors.student_id}
                    />
                    {errors.student_id && (
                      <p id="student-id-error" className="text-xs text-red-600 dark:text-red-400">
                        {errors.student_id}
                      </p>
                    )}
                  </div>
                )}

                {/* Employee / Staff ID */}
                {!isStudent && (
                  <div className="space-y-1.5 sm:col-span-2">
                    <Label
                      htmlFor="employee_id"
                      className="text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                      Employee ID{' '}
                      <span className="font-normal text-gray-400 dark:text-gray-500">(optional)</span>
                    </Label>
                    <Input
                      id="employee_id"
                      value={data.employee_id}
                      onChange={(e) => setData('employee_id', e.target.value)}
                      className="focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                      aria-describedby={errors.employee_id ? 'employee-id-error' : undefined}
                      aria-invalid={!!errors.employee_id}
                    />
                    {errors.employee_id && (
                      <p id="employee-id-error" className="text-xs text-red-600 dark:text-red-400">
                        {errors.employee_id}
                      </p>
                    )}
                  </div>
                )}
              </div>

              {/* Form Actions */}
              <div className="mt-6 flex items-center gap-3">
                <Button
                  type="submit"
                  disabled={processing}
                  className="touch-manipulation bg-blue-600 text-white hover:bg-blue-700 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                >
                  {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />}
                  {processing ? 'Saving…' : 'Save Changes'}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  disabled={processing}
                  onClick={() => reset()}
                  className="touch-manipulation focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                >
                  Reset
                </Button>
              </div>
            </form>
          </div>
        </div>
      </SettingsLayout>
    </AppLayout>
  );
}
