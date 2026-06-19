import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, MailCheck } from 'lucide-react';
import { FormEventHandler } from 'react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <>
            <Head title="Verify email" />

            <div className="flex min-h-svh">
                {/* Left: full-bleed campus photo */}
                <div className="relative hidden lg:flex lg:w-[52%] lg:flex-col lg:shrink-0">
                    <img src="/assets/auf_bg.png" alt="" className="absolute inset-0 h-full w-full object-cover" />
                    <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-slate-950/20" />
                    <div className="relative flex h-full flex-col justify-between p-12">
                        <div className="flex items-center gap-3">
                            <img src="/assets/auf_logo.png" alt="AUFlow" className="h-10 w-auto object-contain drop-shadow-lg" />
                            <div>
                                <p className="text-base font-bold text-white">AUFlow</p>
                                <p className="text-xs text-white/50">Angeles University Foundation</p>
                            </div>
                        </div>
                        <div>
                            <div className="mb-4 h-px w-12 bg-white/30" />
                            <h2 className="text-5xl font-extrabold leading-tight tracking-tight text-white">
                                Digitize.<br />
                                Automate.<br />
                                Simplify.
                            </h2>
                            <p className="mt-4 max-w-xs text-sm leading-relaxed text-white/60">
                                Replace paper trails with structured, trackable digital workflows.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Right: warm cream editorial form */}
                <div className="flex flex-1 flex-col bg-[oklch(0.982_0.005_85)]">
                    <div className="flex items-center gap-3 bg-[oklch(0.24_0.045_258)] px-6 py-4 lg:hidden">
                        <img src="/assets/auf_logo.png" alt="AUFlow" className="h-8 w-auto object-contain" />
                        <p className="text-sm font-bold text-white">AUFlow</p>
                    </div>
                    <div className="flex flex-1 flex-col items-center justify-center px-8 py-12">
                        <div className="w-full max-w-sm text-center">
                            <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-[oklch(0.36_0.11_258)]/10">
                                <MailCheck className="h-7 w-7 text-[oklch(0.36_0.11_258)]" />
                            </div>
                            <h1 className="text-2xl font-bold text-[oklch(0.16_0.025_258)]">Check your email</h1>
                            <p className="mt-2 text-sm text-slate-500">
                                We&#39;ve sent a verification link to your email address. Click the link to activate your account.
                            </p>

                            {status === 'verification-link-sent' && (
                                <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700">
                                    A new verification link has been sent to your email address.
                                </div>
                            )}

                            <form onSubmit={submit} className="mt-6">
                                <Button
                                    type="submit"
                                    className="h-11 w-full bg-[oklch(0.36_0.11_258)] text-sm font-semibold text-white hover:bg-[oklch(0.30_0.09_258)]"
                                    disabled={processing}
                                >
                                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    Resend Verification Email
                                </Button>
                            </form>

                            <p className="mt-4 text-sm text-slate-500">
                                <TextLink href={route('logout')} method="post" as="button" className="font-semibold text-[oklch(0.36_0.11_258)] hover:text-[oklch(0.30_0.09_258)]">
                                    Log out
                                </TextLink>
                            </p>
                        </div>
                    </div>
                    <div className="px-8 py-5 text-center">
                        <p className="text-xs text-slate-400">© 2026 AUFlow · Angeles University Foundation. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </>
    );
}
