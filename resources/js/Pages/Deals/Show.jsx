import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    Handshake, 
    ArrowLeft, 
    Edit, 
    CheckCircle2, 
    XCircle, 
    RotateCw, 
    User, 
    Building, 
    Calendar, 
    Clock, 
    DollarSign, 
    AlertCircle, 
    Layers, 
    Phone, 
    Mail, 
    MapPin, 
    ShieldCheck, 
    Check, 
    X,
    TrendingUp
} from 'lucide-react';
import { useState } from 'react';

export default function Show({ deal }) {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    // Lost Modal state
    const [lostModalOpen, setLostModalOpen] = useState(false);
    const [lostReason, setLostReason] = useState('');
    const [submittingLost, setSubmittingLost] = useState(false);

    const handleMarkWon = () => {
        if (confirm(`Confirm marking opportunity "${deal.title}" as Closed Won?`)) {
            router.post(route('deals.won', deal.id), {}, { preserveScroll: true });
        }
    };

    const handleConfirmLost = (e) => {
        e.preventDefault();
        if (!lostReason.trim()) return;

        setSubmittingLost(true);
        router.post(route('deals.lost', deal.id), {
            lost_reason: lostReason.trim(),
        }, {
            preserveScroll: true,
            onFinish: () => {
                setSubmittingLost(false);
                setLostModalOpen(false);
            }
        });
    };

    const handleReopen = () => {
        router.post(route('deals.reopen', deal.id), {}, { preserveScroll: true });
    };

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '₦0.00';
        return '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'Not set';
        return new Date(dateString).toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('deals.index')}
                            className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-xs uppercase tracking-wider font-semibold text-indigo-600 dark:text-indigo-400">
                                    Opportunity 360 Profile
                                </span>
                                {deal.status === 'won' && (
                                    <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 uppercase">
                                        Closed Won
                                    </span>
                                )}
                                {deal.status === 'lost' && (
                                    <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 uppercase">
                                        Closed Lost
                                    </span>
                                )}
                                {deal.status === 'open' && (
                                    <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 uppercase">
                                        Open Deal
                                    </span>
                                )}
                            </div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                {deal.title}
                            </h2>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {deal.status === 'open' && (
                            <>
                                <button
                                    onClick={handleMarkWon}
                                    className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors"
                                >
                                    <Check className="w-4 h-4" />
                                    Mark Closed Won
                                </button>
                                <button
                                    onClick={() => { setLostReason(''); setLostModalOpen(true); }}
                                    className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition-colors"
                                >
                                    <X className="w-4 h-4" />
                                    Mark Closed Lost
                                </button>
                            </>
                        )}

                        {deal.status !== 'open' && (
                            <button
                                onClick={handleReopen}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors"
                            >
                                <RotateCw className="w-4 h-4" />
                                Reopen Opportunity
                            </button>
                        )}

                        {can('deals.edit') && (
                            <Link
                                href={route('deals.edit', deal.id)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors"
                            >
                                <Edit className="w-4 h-4" />
                                Edit Deal
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`${deal.title} - Opportunity 360`} />

            <div className="py-6 space-y-6">
                {/* Lost Reason Alert Box */}
                {deal.status === 'lost' && deal.lost_reason && (
                    <div className="p-4 bg-rose-50 dark:bg-rose-950/40 rounded-2xl border border-rose-200 dark:border-rose-800 flex items-start gap-3">
                        <AlertCircle className="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                        <div>
                            <h4 className="text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider">
                                Opportunity Lost Reason Captured
                            </h4>
                            <p className="text-xs text-rose-700 dark:text-rose-300/90 mt-1 leading-relaxed">
                                {deal.lost_reason}
                            </p>
                            <span className="text-[10px] text-rose-500 block mt-1">
                                Closed on {formatDate(deal.actual_close_date)}
                            </span>
                        </div>
                    </div>
                )}

                {/* Won Celebration Banner */}
                {deal.status === 'won' && (
                    <div className="p-4 bg-emerald-50 dark:bg-emerald-950/40 rounded-2xl border border-emerald-200 dark:border-emerald-800 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <CheckCircle2 className="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                            <div>
                                <h4 className="text-xs font-bold text-emerald-800 dark:text-emerald-300 uppercase tracking-wider">
                                    Deal Closed Won Successfully
                                </h4>
                                <p className="text-xs text-emerald-700 dark:text-emerald-300">
                                    Total revenue generated: <strong>{formatCurrency(deal.deal_value)}</strong> on {formatDate(deal.actual_close_date)}.
                                </p>
                            </div>
                        </div>
                        <span className="text-2xl">🎉</span>
                    </div>
                )}

                {/* Main 2-Column Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Connected Contact, Property & Activities */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Connected Contact Card */}
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                                    <User className="w-4 h-4 text-blue-500" />
                                    Client / Buyer Contact
                                </h3>
                                {deal.contact && (
                                    <Link
                                        href={route('contacts.show', deal.contact.id)}
                                        className="text-xs font-semibold text-indigo-600 hover:underline"
                                    >
                                        View Contact 360 ↗
                                    </Link>
                                )}
                            </div>

                            {deal.contact ? (
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                    <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                        <div className="text-slate-400">Full Name</div>
                                        <div className="font-bold text-slate-900 dark:text-white mt-0.5">
                                            {deal.contact.first_name} {deal.contact.last_name}
                                        </div>
                                    </div>

                                    <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                        <div className="text-slate-400">Phone Number</div>
                                        <div className="font-bold text-slate-900 dark:text-white font-mono mt-0.5">
                                            {deal.contact.phone}
                                        </div>
                                    </div>

                                    <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                        <div className="text-slate-400">Email Address</div>
                                        <div className="font-medium text-slate-900 dark:text-white mt-0.5 truncate">
                                            {deal.contact.email || 'N/A'}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-xs text-slate-400">No contact attached.</p>
                            )}
                        </div>

                        {/* Connected Property Card */}
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                                    <Building className="w-4 h-4 text-emerald-500" />
                                    Target Property / Land Plot
                                </h3>
                                {deal.property && (
                                    <Link
                                        href={route('properties.show', deal.property.id)}
                                        className="text-xs font-semibold text-emerald-600 hover:underline"
                                    >
                                        View Property 360 ↗
                                    </Link>
                                )}
                            </div>

                            {deal.property ? (
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                    <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800 sm:col-span-2">
                                        <div className="text-slate-400">Property Title</div>
                                        <div className="font-bold text-slate-900 dark:text-white mt-0.5">
                                            {deal.property.title}
                                        </div>
                                        <div className="text-slate-500 mt-1 flex items-center gap-1">
                                            <MapPin className="w-3 h-3 text-emerald-500" />
                                            {deal.property.estate?.name || deal.property.location}
                                        </div>
                                    </div>

                                    <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                        <div className="text-slate-400">Plot Size & Type</div>
                                        <div className="font-bold text-slate-900 dark:text-white mt-0.5">
                                            {deal.property.plot_size}
                                        </div>
                                        <div className="text-slate-500 capitalize mt-1">
                                            {deal.property.property_type}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-xs text-slate-400">No specific property attached.</p>
                            )}
                        </div>

                        {/* Notes / Deal Context */}
                        {deal.notes && (
                            <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    Opportunity Notes & Terms
                                </h3>
                                <p className="text-xs text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                                    {deal.notes}
                                </p>
                            </div>
                        )}

                        {/* Activity Audit Log */}
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                                <Clock className="w-4 h-4 text-blue-500" />
                                Deal Activity & Milestones
                            </h3>

                            {deal.activities?.length > 0 ? (
                                <div className="space-y-3">
                                    {deal.activities.map((act) => (
                                        <div key={act.id} className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800 text-xs">
                                            <div className="flex items-center justify-between text-slate-500">
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">
                                                    {act.user ? act.user.name : 'System'}
                                                </span>
                                                <span>{new Date(act.created_at).toLocaleString()}</span>
                                            </div>
                                            <p className="text-slate-700 dark:text-slate-300 mt-1">
                                                {act.description}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-xs text-slate-400 py-3 text-center">
                                    No activity logs recorded yet.
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Right 1 Col: Financial & Pipeline Milestones */}
                    <div className="space-y-6">
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Financial Snapshot
                            </h3>

                            <div className="space-y-3">
                                <div>
                                    <span className="text-[11px] text-slate-400">Deal Value</span>
                                    <div className="text-2xl font-extrabold text-slate-900 dark:text-white">
                                        {formatCurrency(deal.deal_value)}
                                    </div>
                                </div>

                                <div className="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
                                    <span className="text-slate-400">Win Probability</span>
                                    <span className="font-bold text-indigo-600 dark:text-indigo-400">
                                        {deal.probability ? `${deal.probability}%` : 'N/A'}
                                    </span>
                                </div>

                                <div className="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
                                    <span className="text-slate-400">Expected Close Date</span>
                                    <span className="font-semibold text-slate-800 dark:text-slate-200">
                                        {formatDate(deal.expected_close_date)}
                                    </span>
                                </div>

                                {deal.actual_close_date && (
                                    <div className="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
                                        <span className="text-slate-400">Actual Close Date</span>
                                        <span className="font-semibold text-slate-800 dark:text-slate-200">
                                            {formatDate(deal.actual_close_date)}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Pipeline Stage */}
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Pipeline & Stage
                            </h3>
                            <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800 text-xs space-y-1">
                                <div className="text-slate-400">Pipeline</div>
                                <div className="font-bold text-slate-900 dark:text-white">
                                    {deal.pipeline?.name || 'Bamcom Sales Pipeline'}
                                </div>
                                <div className="pt-2 text-slate-400">Current Milestone</div>
                                <div className="font-bold text-indigo-600 dark:text-indigo-400">
                                    {deal.stage?.name || 'Initial Stage'} ({deal.stage?.probability || 0}%)
                                </div>
                            </div>
                        </div>

                        {/* Assigned Sales Rep */}
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Assigned Sales Representative
                            </h3>
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-300 font-bold flex items-center justify-center text-sm">
                                    {deal.assigned_user ? deal.assigned_user.name.substring(0, 2).toUpperCase() : 'UN'}
                                </div>
                                <div>
                                    <div className="font-bold text-xs text-slate-900 dark:text-white">
                                        {deal.assigned_user ? deal.assigned_user.name : 'Unassigned'}
                                    </div>
                                    <div className="text-[11px] text-slate-500">
                                        {deal.assigned_user ? deal.assigned_user.role : 'Awaiting Assignment'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Lost Reason Modal */}
            {lostModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 max-w-md w-full p-6 space-y-4 shadow-xl">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <XCircle className="w-5 h-5 text-rose-500" />
                                Mark Opportunity as Closed Lost
                            </h3>
                            <button
                                onClick={() => setLostModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <p className="text-xs text-slate-500">
                            Capture the exact reason why <strong>{deal.title}</strong> was not won.
                        </p>

                        <form onSubmit={handleConfirmLost} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Lost Reason *
                                </label>
                                <textarea
                                    required
                                    rows="3"
                                    placeholder="e.g. Budget constraints, opted for mainland location, unresponsive after proposal..."
                                    value={lostReason}
                                    onChange={(e) => setLostReason(e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-rose-500"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setLostModalOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={submittingLost || !lostReason.trim()}
                                    className="px-4 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition-colors disabled:opacity-50"
                                >
                                    {submittingLost ? 'Saving...' : 'Confirm Closed Lost'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
