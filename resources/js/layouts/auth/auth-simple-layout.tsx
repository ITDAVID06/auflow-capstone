// FRONTEND
// File: resources/js/layouts/auth/auth-simple-layout.tsx
import AppLogoIcon from '@/components/app-logo-icon'
import { Link } from '@inertiajs/react'
import { type PropsWithChildren } from 'react'

interface AuthLayoutProps {
  name?: string
  title?: string
  description?: string
  /** set to true if you want the logo shown */
  showLogo?: boolean
  /** href for the logo link if shown; defaults to "/" */
  homeHref?: string
}

export default function AuthSimpleLayout({
  children,
  title,
  description,
  showLogo = false,     // <-- hidden by default
  homeHref = '/',
}: PropsWithChildren<AuthLayoutProps>) {
  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
      <div className="w-full max-w-sm">
        <div className="flex flex-col gap-8">
          <div className="flex flex-col items-center gap-4">

            {showLogo && (
              <Link
                href={homeHref}
                className="flex flex-col items-center gap-2 font-medium focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 rounded-md"
              >
                <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                  <AppLogoIcon className="size-9 fill-current text-foreground" aria-hidden="true" />
                </div>
                <span className="sr-only">Home</span>
              </Link>
            )}

            {(title || description) && (
              <div className="space-y-2 text-center">
                {title && <h1 className="text-xl font-medium">{title}</h1>}
                {description && (
                  <p className="text-center text-sm text-muted-foreground">{description}</p>
                )}
              </div>
            )}
          </div>

          {children}
        </div>
      </div>
    </div>
  )
}
