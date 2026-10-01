import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Mail, CheckCircle2, ShieldCheck } from 'lucide-react';
import ApplicationLogo from '@/Components/ApplicationLogo';

export default function Unsubscribe({ email, isSuppressed }) {
    const [unsubscribed, setUnsubscribed] = useState(isSuppressed);

    const { data, setData, post, processing } = useForm({
        email: email || '',
        reason: 'Requested opt-out via web portal',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('emails.unsubscribe.process'), {
            onSuccess: () => setUnsubscribed(true),
        });
    };

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col justify-center items-center p-4">
            <Head title="Email Communication Preferences" />

            <div className="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl overflow-hidden p-6 sm:p-8">
                <div className="flex justify-center mb-6">
                    <ApplicationLogo className="h-10 w-auto" />
                </div>

                {unsubscribed ? (
                    <div className="text-center py-4">
                        <CheckCircle2 className="h-12 w-12 text-emerald-500 mx-auto mb-3" />
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                            Unsubscribed Successfully
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-2">
                            The email address <span className="font-semibold text-slate-700 dark:text-slate-300">{data.email}</span> has been removed from all marketing broadcasts and campaign communications.
                        </p>
                        <div className="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400">
                            Bamcom AI CRM &bull; CAN-SPAM & GDPR Compliant
                        </div>
                    </div>
                ) : (
                    <div>
                        <div className="text-center mb-6">
                            <Mail className="h-8 w-8 text-sky-600 mx-auto mb-2" />
                            <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                                Email Preferences
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                We are sorry to see you go. Confirm your email address below to opt out of marketing announcements.
                            </p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    Email Address
                                </label>
                                <input
                                    type="email"
                                    required
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                    placeholder="your-email@example.com"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    Reason for Unsubscribing (Optional)
                                </label>
                                <select
                                    value={data.reason}
                                    onChange={(e) => setData('reason', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                                >
                                    <option value="Too many emails">Too many emails</option>
                                    <option value="Content is no longer relevant">Content is no longer relevant</option>
                                    <option value="Never signed up for this list">Never signed up for this list</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full py-2.5 px-4 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition shadow-sm disabled:opacity-50"
                            >
                                {processing ? 'Processing...' : 'Unsubscribe from Marketing'}
                            </button>
                        </form>
                    </div>
                )}
            </div>
        </div>
    );
}
