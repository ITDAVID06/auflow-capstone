import React from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

// ── Types ─────────────────────────────────────────────────────────────────────

interface ErrorBoundaryInnerProps {
    children: React.ReactNode;
    userId: number | null;
}

interface ErrorBoundaryInnerState {
    error: Error | null;
    showModal: boolean;
    comment: string;
    sending: boolean;
    submitted: boolean;
}

// ── Utility helpers ───────────────────────────────────────────────────────────

function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function readUserIdFromPage(): number | null {
    try {
        const el = document.getElementById('app');
        const raw = el?.dataset.page ?? '{}';
        const page = JSON.parse(raw) as {
            props?: { auth?: { user?: { id?: number } | null } };
        };
        return page.props?.auth?.user?.account_id ?? null;
    } catch {
        return null;
    }
}

// ── Inner class component (must be a class to use componentDidCatch) ──────────

class ErrorBoundaryInner extends React.Component<ErrorBoundaryInnerProps, ErrorBoundaryInnerState> {
    public constructor(props: ErrorBoundaryInnerProps) {
        super(props);
        this.state = {
            error: null,
            showModal: false,
            comment: '',
            sending: false,
            submitted: false,
        };
    }

    public static getDerivedStateFromError(error: Error): Partial<ErrorBoundaryInnerState> {
        return { error, showModal: true };
    }

    public componentDidCatch(error: Error, info: React.ErrorInfo): void {
        console.error('[ErrorBoundary] Unhandled React render error', error, info);
    }

    private sendReport = async (): Promise<void> => {
        const { error, comment } = this.state;
        if (!error) {
            return;
        }

        this.setState({ sending: true });

        try {
            await fetch('/api/error-reports', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    message: error.message.slice(0, 2000),
                    stack: (error.stack ?? '').slice(0, 10000),
                    url: window.location.href.slice(0, 2048),
                    user_agent: navigator.userAgent.slice(0, 512),
                    comment: comment.slice(0, 1000) || null,
                    user_id: this.props.userId,
                }),
            });

            this.setState({ submitted: true, sending: false });
        } catch {
            // Silently fail — don't break the error UI over a failed report
            this.setState({ sending: false });
        }
    };

    private dismiss = (): void => {
        this.setState({ showModal: false });
    };

    public render(): React.ReactNode {
        const { error, showModal, comment, sending, submitted } = this.state;

        if (!error) {
            return this.props.children;
        }

        return (
            <>
                {/* Fallback UI underneath the modal */}
                <div className="flex min-h-screen items-center justify-center bg-background px-4 py-16">
                    <Card className="w-full max-w-lg border-border/60">
                        <CardHeader>
                            <CardTitle>Something went wrong</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                An unexpected error occurred. Please reload and try again.
                            </p>
                            <Button type="button" onClick={() => window.location.reload()}>
                                Reload page
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {/* Error report modal */}
                <Dialog open={showModal} onOpenChange={this.dismiss}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Something went wrong</DialogTitle>
                            <DialogDescription>
                                Send an error report to help us fix it?
                            </DialogDescription>
                        </DialogHeader>

                        {submitted ? (
                            <div className="space-y-4 py-2">
                                <p className="text-sm text-muted-foreground">
                                    Thank you! Your report was sent. Please reload the page to continue.
                                </p>
                                <Button
                                    type="button"
                                    className="w-full"
                                    onClick={() => window.location.reload()}
                                >
                                    Reload page
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-4 py-2">
                                <div className="space-y-1">
                                    <label
                                        htmlFor="error-comment"
                                        className="text-sm font-medium leading-none"
                                    >
                                        What were you doing?{' '}
                                        <span className="text-muted-foreground">(optional)</span>
                                    </label>
                                    <textarea
                                        id="error-comment"
                                        rows={3}
                                        maxLength={1000}
                                        value={comment}
                                        onChange={(e) => this.setState({ comment: e.target.value })}
                                        placeholder="e.g. I was submitting a form when this happened."
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    />
                                </div>

                                <details className="group text-xs text-muted-foreground">
                                    <summary className="cursor-pointer select-none hover:text-foreground">
                                        Technical details
                                    </summary>
                                    <pre className="mt-2 max-h-32 overflow-auto rounded bg-muted px-3 py-2 text-[11px] leading-relaxed whitespace-pre-wrap break-all">
                                        {error.message}
                                        {'\n\n'}
                                        {error.stack}
                                    </pre>
                                </details>

                                <DialogFooter className="gap-2 sm:gap-0">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={this.dismiss}
                                        disabled={sending}
                                    >
                                        Dismiss
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={this.sendReport}
                                        disabled={sending}
                                    >
                                        {sending ? 'Sending…' : 'Send Report'}
                                    </Button>
                                </DialogFooter>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </>
        );
    }
}

// ── Public wrapper: reads userId from Inertia's initial page data in the DOM ──

export default function ErrorBoundary({ children }: { children: React.ReactNode }) {
    const userId = readUserIdFromPage();
    return <ErrorBoundaryInner userId={userId}>{children}</ErrorBoundaryInner>;
}
