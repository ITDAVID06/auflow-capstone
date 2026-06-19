import type { Meta, StoryObj } from '@storybook/react';
import { SidebarProvider } from '@/components/ui/sidebar';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { SidebarInset } from '@/components/ui/sidebar';
import { SidebarOverlay } from '@/components/sidebar-overlay';

const meta: Meta<typeof AppSidebar> = {
  title: 'Layout/AppSidebar',
  component: AppSidebar,
  parameters: {
    layout: 'fullscreen',
  },
  decorators: [
    (Story) => (
      <SidebarProvider defaultOpen={true}>
        <div className="flex min-h-screen w-full">
          <Story />
          <SidebarOverlay />
          <SidebarInset>
            <AppSidebarHeader
              title="Dashboard"
              subtitle="Welcome back"
              breadcrumbs={[
                { label: 'Home', href: '/' },
                { label: 'Dashboard' },
              ]}
            />
            <div className="p-6">
              <h2 className="text-xl font-semibold mb-4">Main Content Area</h2>
              <p className="text-muted-foreground mb-4">
                This is the main content area. The sidebar can be toggled using:
              </p>
              <ul className="list-disc list-inside space-y-2 text-sm text-muted-foreground">
                <li>The hamburger menu button in the top left</li>
                <li>Keyboard shortcut: Cmd/Ctrl + B</li>
                <li>ESC key to close</li>
                <li>Clicking the overlay backdrop</li>
              </ul>
              <div className="mt-6 p-4 bg-muted rounded-lg">
                <p className="text-sm">
                  On mobile (resize your browser), the sidebar becomes a drawer overlay.
                </p>
              </div>
            </div>
          </SidebarInset>
        </div>
      </SidebarProvider>
    ),
  ],
};

export default meta;
type Story = StoryObj<typeof AppSidebar>;

/**
 * Default sidebar state - open on desktop
 */
export const Default: Story = {};

/**
 * Sidebar starts closed
 */
export const InitiallyClosed: Story = {
  decorators: [
    (Story) => (
      <SidebarProvider defaultOpen={false}>
        <div className="flex min-h-screen w-full">
          <Story />
          <SidebarOverlay />
          <SidebarInset>
            <AppSidebarHeader
              title="Dashboard"
              subtitle="Click the hamburger menu to open the sidebar"
            />
            <div className="p-6">
              <p className="text-muted-foreground">
                The sidebar starts closed. Use Cmd/Ctrl + B or click the hamburger menu to open it.
              </p>
            </div>
          </SidebarInset>
        </div>
      </SidebarProvider>
    ),
  ],
};

/**
 * Dark theme variant
 */
export const DarkTheme: Story = {
  parameters: {
    backgrounds: { default: 'dark' },
  },
  decorators: [
    (Story) => (
      <div className="dark">
        <SidebarProvider defaultOpen={true}>
          <div className="flex min-h-screen w-full bg-background">
            <Story />
            <SidebarOverlay />
            <SidebarInset>
              <AppSidebarHeader
                title="Dashboard"
                subtitle="Dark theme example"
              />
              <div className="p-6 text-foreground">
                <h2 className="text-xl font-semibold mb-4">Dark Theme</h2>
                <p className="text-muted-foreground">
                  The sidebar supports both light and dark themes seamlessly.
                </p>
              </div>
            </SidebarInset>
          </div>
        </SidebarProvider>
      </div>
    ),
  ],
};

/**
 * With custom actions in header
 */
export const WithHeaderActions: Story = {
  decorators: [
    (Story) => (
      <SidebarProvider defaultOpen={true}>
        <div className="flex min-h-screen w-full">
          <Story />
          <SidebarOverlay />
          <SidebarInset>
            <AppSidebarHeader
              title="Dashboard"
              subtitle="With custom actions"
              breadcrumbs={[
                { label: 'Home', href: '/' },
                { label: 'Dashboard' },
              ]}
              actions={
                <div className="flex gap-2">
                  <button className="px-3 py-1.5 text-sm bg-primary text-primary-foreground rounded-md">
                    Primary Action
                  </button>
                  <button className="px-3 py-1.5 text-sm border border-border rounded-md">
                    Secondary
                  </button>
                </div>
              }
            />
            <div className="p-6">
              <p className="text-muted-foreground">
                The header can include custom action buttons.
              </p>
            </div>
          </SidebarInset>
        </div>
      </SidebarProvider>
    ),
  ],
};

/**
 * Accessibility test - keyboard navigation
 */
export const AccessibilityTest: Story = {
  decorators: [
    (Story) => (
      <SidebarProvider defaultOpen={true}>
        <div className="flex min-h-screen w-full">
          <Story />
          <SidebarOverlay />
          <SidebarInset>
            <AppSidebarHeader
              title="Accessibility Test"
              subtitle="Test keyboard navigation and screen reader support"
            />
            <div className="p-6">
              <h2 className="text-xl font-semibold mb-4">Accessibility Features</h2>
              <div className="space-y-4">
                <div>
                  <h3 className="font-medium mb-2">Keyboard Navigation:</h3>
                  <ul className="list-disc list-inside space-y-1 text-sm text-muted-foreground">
                    <li>Tab/Shift+Tab to navigate through sidebar links</li>
                    <li>Focus trap keeps focus within sidebar when open</li>
                    <li>Cmd/Ctrl + B to toggle sidebar</li>
                    <li>ESC to close sidebar</li>
                  </ul>
                </div>
                <div>
                  <h3 className="font-medium mb-2">Screen Reader Support:</h3>
                  <ul className="list-disc list-inside space-y-1 text-sm text-muted-foreground">
                    <li>ARIA labels on all interactive elements</li>
                    <li>role="navigation" on sidebar</li>
                    <li>Descriptive button labels</li>
                  </ul>
                </div>
              </div>
            </div>
          </SidebarInset>
        </div>
      </SidebarProvider>
    ),
  ],
};
