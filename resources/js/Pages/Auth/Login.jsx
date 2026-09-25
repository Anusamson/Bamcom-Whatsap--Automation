import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Lock, Mail, ArrowRight, ShieldCheck } from 'lucide-react';
import AuthHeroBanner from './Partials/AuthHeroBanner';

export default function Login({ status, canResetPassword }) {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="min-h-screen w-full bg-gradient-to-br from-[#EBF2FC] via-[#F4F7FD] to-[#E3EDFC] dark:bg-slate-950 flex items-center justify-center p-4 sm:p-6 lg:p-10 relative overflow-hidden font-sans">
            <Head title="Sign in" />

            {/* Ambient Background Decorative Elements */}
            <div className="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-blue-200/50 blur-3xl pointer-events-none" />
            <div className="absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-sky-200/40 blur-3xl pointer-events-none" />
            <div className="absolute top-1/2 left-1/4 -translate-y-1/2 w-72 h-72 rounded-full bg-indigo-100/30 blur-2xl pointer-events-none" />

            {/* Main Rounded Card Container */}
            <div className="relative z-10 w-full max-w-5xl bg-white dark:bg-slate-900 rounded-[32px] sm:rounded-[36px] shadow-2xl shadow-blue-500/10 border border-slate-100 dark:border-slate-800 p-3 sm:p-4 lg:p-5 flex flex-col md:flex-row items-stretch gap-6 lg:gap-8">
                {/* Left Side: Royal Blue Hero Banner with Illustration & "Welcome to Bamcomite CRM" */}
                <div className="w-full md:w-1/2 flex">
                    <AuthHeroBanner
                        title="Welcome to Bamcomite CRM"
                        subtitle="One click to manage real estate leads, site inspections & WhatsApp automation."
                    />
                </div>

                {/* Right Side: Authentication Form */}
                <div className="w-full md:w-1/2 px-4 py-6 sm:px-8 sm:py-8 lg:px-12 lg:py-10 flex flex-col justify-center">
                    {/* Form Header */}
                    <div className="mb-6 sm:mb-8">
                        <div className="flex items-center justify-between mb-2">
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800">
                                <ShieldCheck className="w-3.5 h-3.5" />
                                <span>Portal Access</span>
                            </span>
                            <Link
                                href="/"
                                className="text-xs font-medium text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition"
                            >
                                Back to Home &rarr;
                            </Link>
                        </div>
                        <h2 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                            Sign in
                        </h2>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1.5">
                            Welcome back! Please enter your details to sign in.
                        </p>
                    </div>

                    {/* Status Alert */}
                    {status && (
                        <div className="mb-5 p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-medium flex items-center gap-2">
                            <span className="w-2 h-2 rounded-full bg-emerald-500" />
                            <span>{status}</span>
                        </div>
                    )}

                    {/* Form */}
                    <form onSubmit={submit} className="space-y-4 sm:space-y-5">
                        {/* Email Address */}
                        <div>
                            <label
                                htmlFor="email"
                                className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5"
                            >
                                Email Address
                            </label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <Mail className="h-4 w-4" />
                                </div>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    placeholder="Enter your email"
                                    autoComplete="username"
                                    autoFocus
                                    onChange={(e) => setData('email', e.target.value)}
                                    className={`w-full rounded-xl border pl-10 pr-4 py-3.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 bg-slate-50/70 dark:bg-slate-800/60 focus:bg-white dark:focus:bg-slate-800 focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition duration-150 outline-none ${
                                        errors.email
                                            ? 'border-rose-400 focus:border-rose-500'
                                            : 'border-slate-200 dark:border-slate-700 focus:border-blue-600'
                                    }`}
                                    required
                                />
                            </div>
                            {errors.email && (
                                <p className="text-xs text-rose-500 mt-1.5 font-medium">{errors.email}</p>
                            )}
                        </div>

                        {/* Password */}
                        <div>
                            <div className="flex items-center justify-between mb-1.5">
                                <label
                                    htmlFor="password"
                                    className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider"
                                >
                                    Password
                                </label>
                                {canResetPassword && (
                                    <Link
                                        href={route('password.request')}
                                        className="text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 hover:underline"
                                    >
                                        Forgot password?
                                    </Link>
                                )}
                            </div>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <Lock className="h-4 w-4" />
                                </div>
                                <input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    value={data.password}
                                    placeholder="••••••••"
                                    autoComplete="current-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                    className={`w-full rounded-xl border pl-10 pr-11 py-3.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 bg-slate-50/70 dark:bg-slate-800/60 focus:bg-white dark:focus:bg-slate-800 focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition duration-150 outline-none ${
                                        errors.password
                                            ? 'border-rose-400 focus:border-rose-500'
                                            : 'border-slate-200 dark:border-slate-700 focus:border-blue-600'
                                    }`}
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition"
                                    tabIndex={-1}
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-4 w-4" />
                                    ) : (
                                        <Eye className="h-4 w-4" />
                                    )}
                                </button>
                            </div>
                            {errors.password && (
                                <p className="text-xs text-rose-500 mt-1.5 font-medium">{errors.password}</p>
                            )}
                        </div>

                        {/* Remember Me */}
                        <div className="flex items-center pt-0.5">
                            <label className="flex items-center gap-2.5 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="w-4 h-4 rounded text-blue-600 border-slate-300 dark:border-slate-700 focus:ring-blue-500"
                                />
                                <span className="text-xs text-slate-600 dark:text-slate-400 font-medium">
                                    Remember this device
                                </span>
                            </label>
                        </div>

                        {/* Terms & Privacy Statement (Matching reference image) */}
                        <p className="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed pt-1">
                            You are agreeing to the{' '}
                            <span className="text-blue-600 dark:text-blue-400 font-medium hover:underline cursor-pointer">
                                Terms of Services
                            </span>{' '}
                            and{' '}
                            <span className="text-blue-600 dark:text-blue-400 font-medium hover:underline cursor-pointer">
                                Privacy Policy
                            </span>
                        </p>

                        {/* Submit Button ("Get Started") */}
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full py-3.5 px-6 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold text-sm shadow-md shadow-blue-500/25 hover:shadow-lg hover:shadow-blue-500/35 transition duration-150 flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer"
                        >
                            {processing ? (
                                <>
                                    <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                    <span>Signing in...</span>
                                </>
                            ) : (
                                <>
                                    <span>Get Started</span>
                                    <ArrowRight className="w-4 h-4" />
                                </>
                            )}
                        </button>

                        {/* Switch Link: "Already a member? Sign in" / "Don't have an account? Sign up" */}
                        <div className="pt-3 text-center">
                            <p className="text-xs text-slate-600 dark:text-slate-400">
                                Don't have an account?{' '}
                                <Link
                                    href={route('register')}
                                    className="font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 hover:underline"
                                >
                                    Sign up
                                </Link>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
