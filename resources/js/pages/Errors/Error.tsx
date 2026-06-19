import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Home, Lock, ServerCrash, FileQuestion } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

interface ErrorPageProps {
    status: number;
    message?: string;
}

export default function ErrorPage({ status, message }: ErrorPageProps) {
    const errorDetails = {
        503: {
            title: 'Service Unavailable',
            description: 'The service is currently unavailable. Please check back later.',
            icon: <ServerCrash className="h-12 w-12 text-destructive mb-4" />,
        },
        500: {
            title: 'Server Error',
            description: 'Whoops, something went wrong on our servers. Please try again later or contact support.',
            icon: <AlertTriangle className="h-12 w-12 text-destructive mb-4" />,
        },
        404: {
            title: 'Page Not Found',
            description: 'Sorry, the page you are looking for could not be found.',
            icon: <FileQuestion className="h-12 w-12 text-muted-foreground mb-4" />,
        },
        403: {
            title: 'Forbidden',
            description: 'Sorry, you are forbidden from accessing this page.',
            icon: <Lock className="h-12 w-12 text-warning mb-4" />,
        },
    };

    const details = errorDetails[status as keyof typeof errorDetails] || {
        title: 'Unexpected Error',
        description: 'An unexpected error occurred.',
        icon: <AlertTriangle className="h-12 w-12 text-destructive mb-4" />,
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-background p-4">
            <Head title={details.title} />
            <Card className="w-full max-w-md border-border/60 shadow-sm text-center">
                <CardHeader className="flex flex-col items-center pb-2">
                    {details.icon}
                    <CardTitle className="text-2xl font-bold tracking-tight">
                        {status}: {details.title}
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col items-center pt-2">
                    <CardDescription className="text-base mb-6">
                        {message || details.description}
                    </CardDescription>
                    <div className="flex w-full flex-col gap-2 sm:flex-row sm:justify-center">
                        <Button asChild variant="default" className="gap-2">
                            <Link href="/">
                                <Home className="h-4 w-4" />
                                Back to Dashboard
                            </Link>
                        </Button>
                        <Button variant="outline" onClick={() => window.location.reload()}>
                            Try Again
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
