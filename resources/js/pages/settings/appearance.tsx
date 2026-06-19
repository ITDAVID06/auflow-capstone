import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Palette, Info } from 'lucide-react';

export default function Appearance() {
    return (
        <AppLayout>
            <Head title="Appearance Settings" />

            <SettingsLayout>
                <div className="space-y-7">
                    {/* Section Header */}
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Appearance</h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Customize the look and feel of the application
                        </p>
                    </div>

                    <div className="border-t border-gray-200 dark:border-gray-700" />

                    {/* Theme Selection */}
                    <div className="space-y-4">
                        <div>
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <Palette className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                Theme Preference
                            </h3>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                                Select your preferred theme for the application interface
                            </p>
                        </div>

                        <AppearanceTabs className="w-full sm:w-auto" />

                        {/* Info Alert */}
                        <div className="flex gap-3 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 px-4 py-3">
                            <Info className="h-4 w-4 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <p className="text-sm text-blue-700 dark:text-blue-300">
                                <strong>System</strong> will automatically switch between light and dark mode based on your device settings.
                            </p>
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
