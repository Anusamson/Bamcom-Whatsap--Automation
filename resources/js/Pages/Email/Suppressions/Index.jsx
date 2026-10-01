import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    ShieldAlert, 
    PlusCircle, 
    Search, 
    Trash2, 
    ArrowLeft, 
    ExternalLink,
    AlertTriangle,
    XCircle,
    CheckCircle2
} from 'lucide-react';

export default function Index({ auth, suppressions, filters, reasons }) {
    const [search, setSearch] = useState(filters.search || '');
    const [reasonFilter, setReasonFilter] = useState(filters.reason || '');
    const [isAddOpen, setIsAddOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        reason: 'manual',
        details: '',
    });

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('email-suppressions.index'), {
            search: search || undefined,
            reason: reasonFilter || undefined,
        }, { preserveState: true, replace: true });
    };

    const handleAddSubmit = (e) => {
        e.preventDefault();
        post(route('email-suppressions.store'), {
            onSuccess: () => {
                setIsAddOpen(false);
                reset();
            }
        });
    };

    const handleDelete = (suppressionId, email) => {
        if (confirm(`Remove ${email} from suppression list? This will allow future marketing communications to this address.`)) {
            router.delete(route('email-suppressions.destroy', suppressionId));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('emails.index')}
                            className="p-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        >
                            <ArrowLeft className="h-4 w-4 text-slate-600 dark:text-slate-300" />
                        </Link>
                        <div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <ShieldAlert className="h-6 w-6 text-rose-600" />
                                Email Suppression & Compliance Ledger
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Automated AWS bounce protection, spam complaint quarantine, and recipient opt-outs
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => setIsAddOpen(true)}
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition shadow-sm"
                    >
                        <PlusCircle className="h-4 w-4" />
                        Add Suppression
                    </button>
                </div>
            }
        >
            <Head title="Email Suppressions & Compliance" />

            {/* Filter Bar */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 mb-6 shadow-sm">
                <form onSubmit={handleFilter} className="flex flex-col sm:flex-row items-center gap-3">
                    <div className="relative flex-1 w-full">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Search suppressed email addresses..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-500"
                        />
                    </div>
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <select
                            value={reasonFilter}
                            onChange={(e) => setReasonFilter(e.target.value)}
                            className="text-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 py-2 px-3 focus:ring-2 focus:ring-rose-500"
                        >
                            <option value="">All Reasons</option>
                            {reasons?.map((r) => (
                                <option key={r.value} value={r.value}>{r.label}</option>
                            ))}
                        </select>
                        <button
                            type="submit"
                            className="px-4 py-2 text-sm font-semibold rounded-lg bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 hover:bg-slate-800 transition"
                        >
                            Filter
                        </button>
                    </div>
                </form>
            </div>

            {/* Suppressions Table */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead className="bg-slate-50 dark:bg-slate-800/60 text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th className="px-5 py-3">Suppressed Email</th>
                                <th className="px-5 py-3">Reason</th>
                                <th className="px-5 py-3">Details / Root Cause</th>
                                <th className="px-5 py-3">Suppressed Date</th>
                                <th className="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                            {suppressions.data?.length > 0 ? (
                                suppressions.data.map((supp) => (
                                    <tr key={supp.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                        <td className="px-5 py-3">
                                            <div className="font-semibold text-slate-900 dark:text-white font-mono text-xs">
                                                {supp.email}
                                            </div>
                                            {supp.contact && (
                                                <Link 
                                                    href={route('contacts.show', supp.contact.id)}
                                                    className="inline-flex items-center gap-1 text-[11px] text-sky-600 dark:text-sky-400 hover:underline mt-0.5"
                                                >
                                                    Contact: {supp.contact.first_name} {supp.contact.last_name} <ExternalLink className="h-2.5 w-2.5" />
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-5 py-3">
                                            <span className={`inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full ${
                                                supp.reason === 'bounce' ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' :
                                                supp.reason === 'complaint' ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' :
                                                supp.reason === 'unsubscribe' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' :
                                                'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300'
                                            }`}>
                                                {supp.reason}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3 text-xs text-slate-500 max-w-sm truncate">
                                            {supp.details || 'No diagnostic information'}
                                        </td>
                                        <td className="px-5 py-3 text-xs text-slate-500">
                                            {new Date(supp.suppressed_at).toLocaleString()}
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => handleDelete(supp.id, supp.email)}
                                                className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="5" className="text-center py-10 text-slate-400">
                                        No email suppressions recorded. Your sending reputation is pristine.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {suppressions.links && (
                    <div className="px-5 py-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                        <div>Showing {suppressions.from ?? 0} to {suppressions.to ?? 0} of {suppressions.total} entries</div>
                        <div className="flex gap-1">
                            {suppressions.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`px-3 py-1 rounded-md text-xs font-medium ${
                                        link.active ? 'bg-rose-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Add Suppression Modal */}
            {isAddOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                        <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40">
                            <h3 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                <PlusCircle className="h-5 w-5 text-rose-600" />
                                Suppress Email Address
                            </h3>
                            <button onClick={() => setIsAddOpen(false)} className="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                        </div>
                        <form onSubmit={handleAddSubmit} className="p-6 space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
                                <input
                                    type="email"
                                    required
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-rose-500"
                                    placeholder="client@example.com"
                                />
                                {errors.email && <div className="text-xs text-rose-500 mt-1">{errors.email}</div>}
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Suppression Reason *</label>
                                <select
                                    value={data.reason}
                                    onChange={(e) => setData('reason', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-rose-500"
                                >
                                    <option value="manual">Manual Quarantine</option>
                                    <option value="bounce">Hard Bounce</option>
                                    <option value="complaint">Spam Complaint</option>
                                    <option value="unsubscribe">Unsubscribed</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Root Cause / Diagnostic Notes</label>
                                <textarea
                                    rows="3"
                                    value={data.details}
                                    onChange={(e) => setData('details', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-rose-500"
                                    placeholder="Customer requested verbal opt-out or known invalid mailbox."
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsAddOpen(false)}
                                    className="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition shadow-sm disabled:opacity-50"
                                >
                                    {processing ? 'Saving...' : 'Add to Suppression'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
