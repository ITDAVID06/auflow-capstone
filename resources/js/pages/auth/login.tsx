import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<Required<LoginForm>>({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <>
            <Head title="Log in" />

            <div className="flex min-h-svh">
                {/* Left: full-bleed campus photo */}
                <div className="relative hidden lg:flex lg:w-[52%] lg:flex-col lg:shrink-0">
                    <img src="/assets/auf_bg.png" alt="" className="absolute inset-0 h-full w-full object-cover" />
                    <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-slate-950/20" />
                    <div className="relative flex h-full flex-col justify-between p-12">
                        <Link href="/" className="flex items-center gap-3 transition-opacity hover:opacity-80">
                            <img src="/assets/auf_logo.png" alt="AUFlow" className="h-10 w-auto object-contain drop-shadow-lg" />
                            <div>
                                <p className="text-base font-bold text-white">AUFlow</p>
                                <p className="text-xs text-white/50">Angeles University Foundation</p>
                            </div>
                        </Link>
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
                        <div className="w-full max-w-sm">
                            <div className="mb-8">
                                <h1 className="text-2xl font-bold text-[oklch(0.16_0.025_258)]">Welcome back</h1>
                                <p className="mt-1.5 text-sm text-slate-500">Sign in to your AUFlow account to manage your documents</p>
                                {status && (
                                    <div className="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700">
                                        {status}
                                    </div>
                                )}
                            </div>

                            <form onSubmit={submit} className="space-y-5">
                                <div className="space-y-1.5">
                                    <Label htmlFor="email" className="text-xs font-semibold uppercase tracking-widest text-slate-400">
                                        Email Address
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        disabled={processing}
                                        placeholder="name@student.auf.edu.ph"
                                        className="h-11 border-slate-200 bg-white text-sm text-[oklch(0.16_0.025_258)] placeholder:text-slate-300 focus-visible:ring-[oklch(0.36_0.11_258)]/30"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="password" className="text-xs font-semibold uppercase tracking-widest text-slate-400">
                                        Password
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            disabled={processing}
                                            placeholder="Enter your password"
                                            className="h-11 border-slate-200 bg-white pr-10 text-sm text-[oklch(0.16_0.025_258)] placeholder:text-slate-300 focus-visible:ring-[oklch(0.36_0.11_258)]/30"
                                        />
                                        <button
                                            type="button"
                                            tabIndex={-1}
                                            onClick={() => setShowPassword((v) => !v)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        >
                                            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center justify-between">
                                    <label className="flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={data.remember}
                                            onChange={(e) => setData('remember', e.target.checked)}
                                            className="h-4 w-4 cursor-pointer rounded accent-[oklch(0.36_0.11_258)]"
                                        />
                                        <span className="text-sm text-slate-500">Remember me</span>
                                    </label>
                                    {canResetPassword && (
                                        <TextLink href={route('password.request')} className="text-sm font-medium text-[oklch(0.36_0.11_258)] hover:text-[oklch(0.30_0.09_258)]" tabIndex={5}>
                                            Forgot password?
                                        </TextLink>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    className="h-11 w-full bg-[oklch(0.36_0.11_258)] text-sm font-semibold text-white hover:bg-[oklch(0.30_0.09_258)]"
                                    tabIndex={3}
                                    disabled={processing}
                                >
                                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    Sign In
                                </Button>
                            </form>

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
