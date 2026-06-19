import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Shield, Key, Check, Info } from 'lucide-react';

export default function Password() {
  const passwordInput = useRef<HTMLInputElement>(null);
  const currentPasswordInput = useRef<HTMLInputElement>(null);

  const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const updatePassword: React.FormEventHandler = (e) => {
    e.preventDefault();

    put('/settings/password', {
      preserveScroll: true,
      onSuccess: () => reset(),
      onError: (errs) => {
        if (errs.password) {
          reset('password', 'password_confirmation');
          passwordInput.current?.focus();
        }
        if (errs.current_password) {
          reset('current_password');
          currentPasswordInput.current?.focus();
        }
      },
    });
  };

  return (
    <AppLayout>
      <Head title="Password Settings" />
      <SettingsLayout>
        <div className="space-y-7">
          {/* Section Header */}
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Password & Security</h2>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
              Update your password to keep your account secure
            </p>
          </div>

          <div className="border-t border-gray-200 dark:border-gray-700" />

          {/* Info Alert */}
          <div className="flex gap-3 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 px-4 py-3">
            <Info className="h-4 w-4 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
            <p className="text-sm text-blue-700 dark:text-blue-300">
              Choose a strong password with at least 8 characters, including uppercase, lowercase, numbers, and special characters.
            </p>
          </div>

          {/* Password Form */}
          <form onSubmit={updatePassword} className="space-y-6">
            {/* Current Password */}
            <div className="space-y-1.5">
              <Label htmlFor="current_password" className="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2">
                <Shield className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                Current Password
              </Label>
              <Input
                id="current_password"
                ref={currentPasswordInput}
                value={data.current_password}
                onChange={(e) => setData('current_password', e.target.value)}
                type="password"
                autoComplete="current-password"
                placeholder="Enter your current password"
                className={errors.current_password ? 'border-red-500 dark:border-red-500' : 'border-gray-200 dark:border-gray-700'}
              />
              {errors.current_password && <InputError message={errors.current_password} />}
            </div>

            <div className="border-t border-gray-200 dark:border-gray-700" />

            <div className="grid gap-5 sm:grid-cols-2">
              {/* New Password */}
              <div className="space-y-1.5">
                <Label htmlFor="password" className="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2">
                  <Key className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                  New Password
                </Label>
                <Input
                  id="password"
                  ref={passwordInput}
                  value={data.password}
                  onChange={(e) => setData('password', e.target.value)}
                  type="password"
                  autoComplete="new-password"
                  placeholder="Enter your new password"
                  className={errors.password ? 'border-red-500 dark:border-red-500' : 'border-gray-200 dark:border-gray-700'}
                />
                {errors.password && <InputError message={errors.password} />}
              </div>

              {/* Confirm Password */}
              <div className="space-y-1.5">
                <Label htmlFor="password_confirmation" className="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2">
                  <Key className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                  Confirm New Password
                </Label>
                <Input
                  id="password_confirmation"
                  value={data.password_confirmation}
                  onChange={(e) => setData('password_confirmation', e.target.value)}
                  type="password"
                  autoComplete="new-password"
                  placeholder="Confirm your new password"
                  className={errors.password_confirmation ? 'border-red-500 dark:border-red-500' : 'border-gray-200 dark:border-gray-700'}
                />
                {errors.password_confirmation && <InputError message={errors.password_confirmation} />}
              </div>
            </div>

            <div className="border-t border-gray-200 dark:border-gray-700" />

            {/* Submit */}
            <div className="flex items-center gap-4">
              <Button
                type="submit"
                disabled={processing}
                className="bg-blue-600 hover:bg-blue-700 text-white"
              >
                {processing ? 'Updating…' : 'Update Password'}
              </Button>

              <Transition
                show={recentlySuccessful}
                enter="transition ease-in-out duration-300"
                enterFrom="opacity-0"
                leave="transition ease-in-out duration-300"
                leaveTo="opacity-0"
              >
                <div className="flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                  <Check className="h-4 w-4" />
                  <span>Password updated successfully</span>
                </div>
              </Transition>
            </div>
          </form>
        </div>
      </SettingsLayout>
    </AppLayout>
  );
}