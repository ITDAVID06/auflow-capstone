import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    Bell,
    FileText,
    GitBranch,
    QrCode,
    ShieldCheck,
    Users,
} from 'lucide-react';

import type { SharedData } from '@/types';

type TeamMember = {
    name: string;
    role: string;
    photo: string;
};

type Feature = {
    title: string;
    description: string;
    icon: LucideIcon;
};

const TEAM_MEMBERS: TeamMember[] = [
    { name: 'Don Henessy David', role: 'Project Lead / Scrum Master', photo: '/assets/don.jpg' },
    { name: 'Kevin Miranda', role: 'Full Stack Developer', photo: '/assets/kevin1.jpg' },
    { name: 'Samantha Jane Ticsay', role: 'Software Quality Assurance Engineer', photo: '/assets/sammy.png' },
    { name: 'Carlex Miguel Lazaga', role: 'UI/UX Designer', photo: '/assets/carlex.jpg' },
];

const FEATURES: Feature[] = [
    {
        icon: FileText,
        title: 'Digital Form Submission',
        description: 'Replace paper-based requests with structured digital forms and required attachment checks.',
    },
    {
        icon: GitBranch,
        title: 'Configurable Approval Workflows',
        description: 'Build approval routes that match institutional processes across departments and roles.',
    },
    {
        icon: Bell,
        title: 'Status Notifications',
        description: 'Keep requesters and approvers updated at each stage through timely in-app and email notifications.',
    },
    {
        icon: ShieldCheck,
        title: 'Audit-Ready Records',
        description: 'Maintain complete approval history with clear timestamps, actions, and accountable approvers.',
    },
    {
        icon: Users,
        title: 'Role-Based Access',
        description: 'Ensure each stakeholder sees only the tools and data relevant to their responsibilities.',
    },
    {
        icon: QrCode,
        title: 'Tamper-Proof Verification',
        description: 'Every approved document receives a QR-linked snapshot with cryptographic signing — verifiable by any third party, at any time.',
    },
];

function getDashboardRoute(permissions: string[] | undefined): string {
    if (permissions?.includes('dashboard.admin')) return route('dashboard');
    if (permissions?.includes('dashboard.staff')) return route('staff-dashboard.index');
    if (permissions?.includes('dashboard.student')) return route('student-dashboard.index');
    return route('dashboard');
}

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="AUFlow — Digital Document Approval System" />

            <div className="min-h-screen text-[oklch(0.16_0.025_258)]">
                {/* ── Header ── */}
                <header className="fixed inset-x-0 top-0 z-50 bg-[oklch(0.24_0.045_258)]/95 backdrop-blur-md">
                    <div className="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:h-16 sm:px-6 lg:px-8">
                        <Link href="/" className="flex items-center gap-3">
                            <img src="/assets/auf_logo.png" alt="AUF logo" className="h-9 w-auto object-contain sm:h-11" />
                            <div className="leading-none">
                                <p className="text-sm font-bold text-white sm:text-base">AUFlow</p>
                                <p className="text-[10px] tracking-wide text-white/50">Angeles University Foundation</p>
                            </div>
                        </Link>

                        <nav className="hidden items-center gap-7 text-xs font-semibold uppercase tracking-widest text-white/60 md:flex">
                            <a href="#features" className="transition-colors hover:text-white">Features</a>
                            <a href="#team" className="transition-colors hover:text-white">Team</a>
                        </nav>

                        {auth?.user ? (
                            <Link
                                href={getDashboardRoute(auth.user.permissions)}
                                className="inline-flex items-center gap-2 rounded bg-[oklch(0.36_0.11_258)] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition-opacity hover:opacity-80"
                            >
                                Dashboard <ArrowRight className="h-3.5 w-3.5" />
                            </Link>
                        ) : (
                            <Link
                                href={route('login')}
                                className="inline-flex items-center gap-2 rounded bg-[oklch(0.36_0.11_258)] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition-opacity hover:opacity-80"
                            >
                                Sign In <ArrowRight className="h-3.5 w-3.5" />
                            </Link>
                        )}
                    </div>
                </header>

                {/* ── Hero ── */}
                <section className="relative flex min-h-svh flex-col">
                    {/* Background photo */}
                    <div aria-hidden="true" className="absolute inset-0">
                        <img src="/assets/auf_bg.png" alt="" className="h-full w-full object-cover" />
                        <div className="absolute inset-0 bg-gradient-to-t from-[oklch(0.08_0.025_258)] via-[oklch(0.12_0.03_258)]/70 to-[oklch(0.16_0.03_258)]/30" />
                    </div>

                    {/* Dot-grid texture */}
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 opacity-30"
                        style={{ backgroundImage: 'radial-gradient(circle, oklch(0.86 0.012 258 / 0.15) 1px, transparent 1px)', backgroundSize: '28px 28px' }}
                    />

                    {/* Content — anchored bottom-left */}
                    <div className="relative mt-auto px-6 pb-16 pt-28 sm:px-10 sm:pb-20 lg:px-16 lg:pb-24">
                        <p className="mb-4 text-[10px] font-bold uppercase tracking-[0.2em] text-white/40">
                            Angeles University Foundation · Digital Systems
                        </p>
                        <h1 className="max-w-3xl text-4xl font-extrabold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl xl:text-7xl">
                            Digitize.<br />
                            Automate.<br />
                            Simplify.
                        </h1>
                        <p className="mt-6 max-w-lg text-base leading-relaxed text-white/65 sm:text-lg">
                            AUFlow replaces paper-based document approvals with a structured, trackable, and fully digital workflow platform built for AUF.
                        </p>
                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link
                                href={route('login')}
                                className="inline-flex items-center justify-center gap-2 rounded bg-white px-6 py-3 text-sm font-bold text-[oklch(0.16_0.025_258)] transition-opacity hover:opacity-90"
                            >
                                Access AUFlow <ArrowRight className="h-4 w-4" />
                            </Link>
                            <a
                                href="#features"
                                className="inline-flex items-center justify-center gap-2 rounded border border-white/25 px-6 py-3 text-sm font-semibold text-white/80 transition-colors hover:border-white/50 hover:text-white"
                            >
                                View Features
                            </a>
                        </div>
                    </div>
                </section>

                {/* ── Features ── */}
                <section id="features" className="bg-[oklch(0.982_0.005_85)]">
                    <div className="mx-auto max-w-7xl px-6 py-20 sm:px-10 sm:py-24 lg:px-16">
                        <div className="mb-14 max-w-2xl">
                            <p className="mb-3 text-[10px] font-bold uppercase tracking-[0.2em] text-[oklch(0.36_0.11_258)]">
                                Platform capabilities
                            </p>
                            <h2 className="text-3xl font-extrabold leading-tight tracking-tight text-[oklch(0.16_0.025_258)] sm:text-4xl">
                                Feature-focused.<br />Operations-ready.
                            </h2>
                            <p className="mt-4 text-base leading-relaxed text-[oklch(0.16_0.025_258)]/55">
                                Every capability is designed to improve processing quality, turnaround, and governance across university workflows.
                            </p>
                        </div>

                        <div className="grid divide-y divide-slate-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-3">
                            {FEATURES.map((feature, i) => (
                                <div
                                    key={feature.title}
                                    className={[
                                        'group px-0 py-8 transition-colors hover:bg-white sm:px-8 sm:py-10',
                                        i % 3 !== 2 ? 'lg:border-r lg:border-slate-200' : '',
                                        i < 3 ? 'lg:border-b lg:border-slate-200' : '',
                                    ].join(' ')}
                                >
                                    <div className="mb-5 inline-flex h-10 w-10 items-center justify-center rounded bg-[oklch(0.36_0.11_258)]/10 text-[oklch(0.36_0.11_258)]">
                                        <feature.icon className="h-5 w-5" />
                                    </div>
                                    <h3 className="text-base font-bold text-[oklch(0.16_0.025_258)]">{feature.title}</h3>
                                    <p className="mt-2 text-sm leading-relaxed text-[oklch(0.16_0.025_258)]/55">{feature.description}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ── Team ── */}
                <section id="team" className="bg-[oklch(0.24_0.045_258)]"
                    style={{ backgroundImage: 'radial-gradient(circle, oklch(0.86 0.012 258 / 0.04) 1px, transparent 1px)', backgroundSize: '24px 24px' }}
                >
                    <div className="mx-auto max-w-7xl px-6 py-20 sm:px-10 sm:py-24 lg:px-16">
                        <div className="mb-14 max-w-2xl">
                            <p className="mb-3 text-[10px] font-bold uppercase tracking-[0.2em] text-white/35">
                                The people behind it
                            </p>
                            <h2 className="text-3xl font-extrabold leading-tight tracking-tight text-white sm:text-4xl">
                                Built by the AUFlow team.
                            </h2>
                            <p className="mt-4 text-base leading-relaxed text-white/50">
                                Developed by AUF students and collaborators focused on solving real document workflow challenges for the institution.
                            </p>
                        </div>

                        {/* Mobile scroll */}
                        <div className="-mx-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-6 pb-2 md:hidden">
                            {TEAM_MEMBERS.map((member) => (
                                <div key={member.name} className="min-w-[72%] snap-start overflow-hidden rounded-lg border border-white/10">
                                    <img src={member.photo} alt={member.name} className="aspect-square w-full object-cover" loading="lazy" />
                                    <div className="p-4">
                                        <p className="font-bold text-white">{member.name}</p>
                                        <p className="mt-1 text-xs text-white/45">{member.role}</p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Desktop grid */}
                        <div className="hidden gap-5 md:grid md:grid-cols-2 lg:grid-cols-4">
                            {TEAM_MEMBERS.map((member) => (
                                <div key={member.name} className="group overflow-hidden rounded-lg border border-white/10 transition-colors hover:border-white/20">
                                    <div className="overflow-hidden">
                                        <img
                                            src={member.photo}
                                            alt={member.name}
                                            className="aspect-square w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                            loading="lazy"
                                        />
                                    </div>
                                    <div className="p-4">
                                        <p className="font-bold text-white">{member.name}</p>
                                        <p className="mt-1 text-xs text-white/45">{member.role}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ── CTA ── */}
                <section className="bg-[oklch(0.982_0.005_85)]">
                    <div className="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 px-6 py-16 sm:flex-row sm:items-center sm:px-10 sm:py-20 lg:px-16">
                        <div>
                            <h2 className="text-2xl font-extrabold tracking-tight text-[oklch(0.16_0.025_258)] sm:text-3xl">
                                Ready to streamline your approvals?
                            </h2>
                            <p className="mt-2 text-sm text-[oklch(0.16_0.025_258)]/55">
                                Sign in to AUFlow and start processing requests with clarity and speed.
                            </p>
                        </div>
                        <Link
                            href={route('login')}
                            className="inline-flex shrink-0 items-center gap-2 rounded bg-[oklch(0.36_0.11_258)] px-6 py-3 text-sm font-bold text-white transition-opacity hover:opacity-85"
                        >
                            Sign In to AUFlow <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </section>

                {/* ── Footer ── */}
                <footer className="bg-[oklch(0.14_0.025_258)]">
                    <div className="mx-auto flex max-w-7xl flex-col gap-2 px-6 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-10 lg:px-16">
                        <p className="text-xs text-white/25">© {new Date().getFullYear()} Angeles University Foundation</p>
                        <p className="text-xs text-white/25">AUFlow — Digital Document Approval System</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
