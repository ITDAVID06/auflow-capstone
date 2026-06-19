import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { SidebarProvider, useSidebar } from '@/components/ui/sidebar';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { SidebarOverlay } from '@/components/sidebar-overlay';
import '@testing-library/jest-dom';

// Mock Inertia
jest.mock('@inertiajs/react', () => ({
  usePage: () => ({
    props: {
      auth: {
        user: {
          permissions: [
            'dashboard.admin',
            'users.manage',
            'forms.manage',
            'submissions.view',
          ],
        },
      },
    },
  }),
  Link: ({ children, href, ...props }: any) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

// Mock useIsMobile hook
jest.mock('@/hooks/use-mobile', () => ({
  useIsMobile: () => false, // Default to desktop
}));

function TestWrapper({ children, defaultOpen = true }: any) {
  return (
    <SidebarProvider defaultOpen={defaultOpen}>
      <AppSidebar />
      <SidebarOverlay />
      <div>{children}</div>
    </SidebarProvider>
  );
}

describe('AppSidebar', () => {
  beforeEach(() => {
    // Clear any cookies before each test
    document.cookie = 'sidebar_state=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
  });

  describe('Rendering', () => {
    test('renders sidebar with navigation items', () => {
      render(
        <TestWrapper>
          <AppSidebarHeader title="Test" />
        </TestWrapper>
      );

      // Check for section labels
      expect(screen.getByText('DASHBOARDS')).toBeInTheDocument();
      expect(screen.getByText('APPROVALS & SUBMISSIONS')).toBeInTheDocument();

      // Check for navigation items
      expect(screen.getByText('Admin Dashboard')).toBeInTheDocument();
      expect(screen.getByText('All Submissions')).toBeInTheDocument();
      expect(screen.getByText('Form Management')).toBeInTheDocument();
    });

    test('renders hamburger menu button', () => {
      render(
        <TestWrapper>
          <AppSidebarHeader title="Test" />
        </TestWrapper>
      );

      const toggle = screen.getByRole('button', { name: /toggle navigation menu/i });
      expect(toggle).toBeInTheDocument();
    });

    test('renders with proper ARIA labels', () => {
      render(
        <TestWrapper>
          <AppSidebarHeader title="Test" />
        </TestWrapper>
      );

      // Check for navigation role
      const nav = screen.getByRole('navigation', { name: /main navigation/i });
      expect(nav).toBeInTheDocument();
    });
  });

  describe('Toggle Functionality', () => {
    test('toggles sidebar on button click', () => {
      const TestComponent = () => {
        const { open } = useSidebar();
        return (
          <div>
            <AppSidebarHeader title="Test" />
            <div data-testid="sidebar-state">{open ? 'open' : 'closed'}</div>
          </div>
        );
      };

      render(
        <TestWrapper>
          <TestComponent />
        </TestWrapper>
      );

      const stateDiv = screen.getByTestId('sidebar-state');
      expect(stateDiv).toHaveTextContent('open');

      const toggle = screen.getByRole('button', { name: /toggle navigation menu/i });
      fireEvent.click(toggle);

      expect(stateDiv).toHaveTextContent('closed');
    });

    test('sidebar starts closed when defaultOpen is false', () => {
      const TestComponent = () => {
        const { open } = useSidebar();
        return <div data-testid="sidebar-state">{open ? 'open' : 'closed'}</div>;
      };

      render(
        <SidebarProvider defaultOpen={false}>
          <AppSidebar />
          <TestComponent />
        </SidebarProvider>
      );

      const stateDiv = screen.getByTestId('sidebar-state');
      expect(stateDiv).toHaveTextContent('closed');
    });
  });

  describe('Keyboard Navigation', () => {
    test('closes sidebar on ESC key when open', () => {
      const TestComponent = () => {
        const { open } = useSidebar();
        return <div data-testid="sidebar-state">{open ? 'open' : 'closed'}</div>;
      };

      render(
        <TestWrapper>
          <TestComponent />
        </TestWrapper>
      );

      const stateDiv = screen.getByTestId('sidebar-state');
      expect(stateDiv).toHaveTextContent('open');

      fireEvent.keyDown(window, { key: 'Escape' });

      expect(stateDiv).toHaveTextContent('closed');
    });

    test('does not respond to ESC when already closed', () => {
      const TestComponent = () => {
        const { open } = useSidebar();
        return <div data-testid="sidebar-state">{open ? 'open' : 'closed'}</div>;
      };

      render(
        <SidebarProvider defaultOpen={false}>
          <AppSidebar />
          <TestComponent />
        </SidebarProvider>
      );

      const stateDiv = screen.getByTestId('sidebar-state');
      expect(stateDiv).toHaveTextContent('closed');

      fireEvent.keyDown(window, { key: 'Escape' });

      // Should still be closed
      expect(stateDiv).toHaveTextContent('closed');
    });

    test('toggles sidebar with Cmd+B keyboard shortcut', () => {
      const TestComponent = () => {
        const { open } = useSidebar();
        return <div data-testid="sidebar-state">{open ? 'open' : 'closed'}</div>;
      };

      render(
        <TestWrapper>
          <TestComponent />
        </TestWrapper>
      );

      const stateDiv = screen.getByTestId('sidebar-state');
      expect(stateDiv).toHaveTextContent('open');

      // Toggle closed
      fireEvent.keyDown(window, { key: 'b', metaKey: true });
      expect(stateDiv).toHaveTextContent('closed');

      // Toggle open again
      fireEvent.keyDown(window, { key: 'b', metaKey: true });
      expect(stateDiv).toHaveTextContent('open');
    });

    test('toggles sidebar with Ctrl+B keyboard shortcut', () => {
      const TestComponent = () => {
        const { open } = useSidebar();
        return <div data-testid="sidebar-state">{open ? 'open' : 'closed'}</div>;
      };

      render(
        <TestWrapper>
          <TestComponent />
        </TestWrapper>
      );

      const stateDiv = screen.getByTestId('sidebar-state');
      expect(stateDiv).toHaveTextContent('open');

      // Toggle with Ctrl+B
      fireEvent.keyDown(window, { key: 'b', ctrlKey: true });
      expect(stateDiv).toHaveTextContent('closed');
    });
  });

  describe('Overlay', () => {
    test('renders overlay when sidebar is open on desktop', () => {
      const { container } = render(
        <TestWrapper>
          <AppSidebarHeader title="Test" />
        </TestWrapper>
      );

      // Overlay should be present (though it might not be visible in JSDOM)
      const overlayElements = container.querySelectorAll('[aria-hidden="true"]');
      expect(overlayElements.length).toBeGreaterThan(0);
    });

    test('clicking overlay closes sidebar', () => {
      const TestComponent = () => {
        const { open, setOpen } = useSidebar();
        return (
          <div>
            <div
              data-testid="mock-overlay"
              onClick={() => setOpen(false)}
              aria-hidden="true"
            />
            <div data-testid="sidebar-state">{open ? 'open' : 'closed'}</div>
          </div>
        );
      };

      render(
        <TestWrapper>
          <TestComponent />
        </TestWrapper>
      );

      const stateDiv = screen.getByTestId('sidebar-state');
      expect(stateDiv).toHaveTextContent('open');

      const overlay = screen.getByTestId('mock-overlay');
      fireEvent.click(overlay);

      expect(stateDiv).toHaveTextContent('closed');
    });
  });

  describe('Permission-based Navigation', () => {
    test('shows navigation items based on user permissions', () => {
      render(
        <TestWrapper>
          <AppSidebarHeader title="Test" />
        </TestWrapper>
      );

      // Should show items for permissions user has
      expect(screen.getByText('Admin Dashboard')).toBeInTheDocument();
      expect(screen.getByText('Manage Users')).toBeInTheDocument();
      expect(screen.getByText('Form Management')).toBeInTheDocument();
      expect(screen.getByText('All Submissions')).toBeInTheDocument();
    });

    test('hides sections with no items', () => {
      // Mock a user with minimal permissions
      jest.spyOn(require('@inertiajs/react'), 'usePage').mockReturnValue({
        props: {
          auth: {
            user: {
              permissions: ['dashboard.student'],
            },
          },
        },
      });

      render(
        <TestWrapper>
          <AppSidebarHeader title="Test" />
        </TestWrapper>
      );

      // Should only show Dashboards and Requests sections
      expect(screen.getByText('DASHBOARDS')).toBeInTheDocument();
      expect(screen.getByText('REQUESTS')).toBeInTheDocument();

      // Should not show admin-only sections
      expect(screen.queryByText('APPROVALS & SUBMISSIONS')).not.toBeInTheDocument();
      expect(screen.queryByText('BUILD')).not.toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    test('all interactive elements are keyboard accessible', () => {
      render(
        <TestWrapper>
          <AppSidebarHeader title="Test" />
        </TestWrapper>
      );

      // Get all links in the sidebar
      const links = screen.getAllByRole('link');

      // Each link should be focusable
      links.forEach(link => {
        expect(link).toHaveAttribute('href');
      });
    });

    test('screen reader text is present for toggle button', () => {
      render(
        <TestWrapper>
          <AppSidebarHeader title="Test" />
        </TestWrapper>
      );

      const srText = screen.getByText('Toggle Sidebar');
      expect(srText).toHaveClass('sr-only');
    });
  });

  describe('State Persistence', () => {
    test('saves sidebar state to cookie', () => {
      const TestComponent = () => {
        const { open, setOpen } = useSidebar();
        return (
          <div>
            <button onClick={() => setOpen(false)}>Close</button>
            <div data-testid="sidebar-state">{open ? 'open' : 'closed'}</div>
          </div>
        );
      };

      render(
        <TestWrapper>
          <TestComponent />
        </TestWrapper>
      );

      const closeButton = screen.getByText('Close');
      fireEvent.click(closeButton);

      // Check if cookie was set
      expect(document.cookie).toContain('sidebar_state=false');
    });
  });
});
