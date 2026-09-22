import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    TrendingUp, 
    Plus, 
    Search, 
    Filter, 
    Edit, 
    Trash2, 
    Eye, 
    RotateCcw, 
    CheckCircle2, 
    XCircle, 
    RotateCw, 
    Building, 
    User, 
    Calendar, 
    AlertCircle,
    Handshake,
    Check,
    X
} from 'lucide-react';
import { useState } from 'react';

export default function Index({ deals, filters, metrics, users, properties, stages, statuses }) {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [assignedUser, setAssignedUser] = useState(filters.assigned_user_id || '');
    const [propertyId, setPropertyId] = useState(filters.property_id || '');
    const [stageId, setStageId] = useState(filters.pipeline_stage_id || '');
    const [minValue, setMinValue] = useState(filters.min_value || '');
    const [maxValue, setMaxValue] = useState(filters.max_value || '');

    // Lost Modal state
    const [lostModalOpen, setLostModalOpen] = useState(false);
    const [selectedDealForLost, setSelectedDealForLost] = useState(null);
    const [lostReason, setLostReason] = useState('');
    const [submittingLost, setSubmittingLost] = useState(false);

    const handleFilter = (e) => {
        e?.preventDefault();
        router.get(route('deals.index'), {
            search: search || undefined,
            status: status || undefined,
            assigned_user_id: assignedUser || undefined,
            property_id: propertyId || undefined,
            pipeline_stage_id: stageId || undefined,
            min_value: minValue || undefined,
            max_value: maxValue || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        setStatus('');
        setAssignedUser('');
        setPropertyId('');
        setStageId('');
        setMinValue('');
        setMaxValue('');
        router.get(route('deals.index'));
    };

    const handleDelete = (deal) => {
        if (confirm(`Are you sure you want to remove opportunity "${deal.title}"?`)) {
            router.delete(route('deals.destroy', deal.id));
        }
    };

    const handleMarkWon = (deal) => {
        if (confirm(`Confirm marking deal "${deal.title}" as Closed Won?`)) {
            router.post(route('deals.won', deal.id), {}, { preserveScroll: true });
        }
    };

    const openLostModal = (deal) => {
        setSelectedDealForLost(deal);
        setLostReason('');
        setLostModalOpen(true);
    };

    const handleConfirmLost = (e) => {
        e.preventDefault();
        if (!selectedDealForLost || !lostReason.trim()) return;

        setSubmittingLost(true);
        router.post(route('deals.lost', selectedDealForLost.id), {
            lost_reason: lostReason.trim(),
        }, {
            preserveScroll: true,
            onFinish: () => {
                setSubmittingLost(false);
                setLostModalOpen(false);
                setSelectedDealForLost(null);
            }
        });
    };

    const handleReopen = (deal) => {
        router.post(route('deals.reopen', deal.id), {}, { preserveScroll: true });
    };

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '₦0.00';
        return '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'Not specified';
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
                        <div className="p-2.5 bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 rounded-xl">
                            <Handshake className="w-6 h-6" />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                Sales Opportunities & Deals
                                <span className="text-xs px-2.5 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 font-medium border border-indigo-300 dark:border-indigo-800">
                                    Pipeline
                                </span>
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Track high-intent purchase deals, stage milestones, expected close dates, and win/loss analytics.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route('pipelines.index')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors border border-slate-200 dark:border-slate-700"
                        >
                            Kanban Board
                        </Link>

                        {can('deals.create') && (
                            <Link
                                href={route('deals.create')}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition-colors"
                            >
                                <Plus className="w-4 h-4" />
                                New Opportunity
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Sales Opportunities & Deals" />

            <div className="py-6 space-y-6">
                {/* Metrics Bar */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-lg">
                            <Handshake className="w-5 h-5" />
                        </div>
                        <div>
                            <div className="text-2xl font-bold text-slate-900 dark:text-white">
                                {metrics.total_deals}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">Total Deals</div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-lg">
                            <TrendingUp className="w-5 h-5" />
                        </div>
                        <div>
                            <div className="text-xl font-bold text-indigo-600 dark:text-indigo-400">
                                {formatCurrency(metrics.open_pipeline_value)}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                Open Pipeline ({metrics.open_deals_count} deals)
                            </div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-lg">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                        <div>
                            <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                                {formatCurrency(metrics.won_deals_value)}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                Closed Won ({metrics.won_deals_count} deals)
                            </div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 rounded-lg">
                            <TrendingUp className="w-5 h-5" />
                        </div>
                        <div>
                            <div className="text-2xl font-bold text-slate-900 dark:text-white">
                                {metrics.win_rate}%
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                Win Rate ({metrics.lost_deals_count} lost)
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <form onSubmit={handleFilter} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div className="relative">
                                <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                                <input
                                    type="text"
                                    placeholder="Search title, contact, lost reason..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>

                            <div>
                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="w-full py-2 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">All Statuses (Open, Won, Lost)</option>
                                    <option value="open">Open Opportunities</option>
                                    <option value="won">Closed Won</option>
                                    <option value="lost">Closed Lost</option>
                                </select>
                            </div>

                            <div>
                                <select
                                    value={assignedUser}
                                    onChange={(e) => setAssignedUser(e.target.value)}
                                    className="w-full py-2 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">All Sales Representatives</option>
                                    {users.map((u) => (
                                        <option key={u.id} value={u.id}>{u.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <select
                                    value={propertyId}
                                    onChange={(e) => setPropertyId(e.target.value)}
                                    className="w-full py-2 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">All Target Properties</option>
                                    {properties.map((p) => (
                                        <option key={p.id} value={p.id}>{p.title}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                            <div className="flex flex-wrap items-center gap-3">
                                <select
                                    value={stageId}
                                    onChange={(e) => setStageId(e.target.value)}
                                    className="py-1.5 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">All Pipeline Stages</option>
                                    {stages.map((s) => (
                                        <option key={s.id} value={s.id}>{s.name} ({s.probability}%)</option>
                                    ))}
                                </select>

                                <input
                                    type="number"
                                    placeholder="Min Deal (₦)"
                                    value={minValue}
                                    onChange={(e) => setMinValue(e.target.value)}
                                    className="w-32 py-1.5 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                />

                                <input
                                    type="number"
                                    placeholder="Max Deal (₦)"
                                    value={maxValue}
                                    onChange={(e) => setMaxValue(e.target.value)}
                                    className="w-32 py-1.5 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                >
                                    <RotateCcw className="w-3.5 h-3.5" />
                                    Reset
                                </button>

                                <button
                                    type="submit"
                                    className="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors"
                                >
                                    <Filter className="w-3.5 h-3.5" />
                                    Filter Deals
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {/* Deals Table */}
                <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th className="p-3.5">Opportunity Title</th>
                                    <th className="p-3.5">Client Contact</th>
                                    <th className="p-3.5">Property Interest</th>
                                    <th className="p-3.5">Deal Value</th>
                                    <th className="p-3.5">Pipeline Stage</th>
                                    <th className="p-3.5">Status</th>
                                    <th className="p-3.5">Expected Close</th>
                                    <th className="p-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60">
                                {deals.data?.length > 0 ? (
                                    deals.data.map((deal) => (
                                        <tr key={deal.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-750 transition-colors">
                                            <td className="p-3.5">
                                                <Link
                                                    href={route('deals.show', deal.id)}
                                                    className="font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 block"
                                                >
                                                    {deal.title}
                                                </Link>
                                                {deal.assigned_user && (
                                                    <span className="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-0.5">
                                                        <User className="w-3 h-3 text-slate-400" />
                                                        Rep: {deal.assigned_user.name}
                                                    </span>
                                                )}
                                            </td>

                                            <td className="p-3.5">
                                                {deal.contact ? (
                                                    <div>
                                                        <Link
                                                            href={route('contacts.show', deal.contact.id)}
                                                            className="font-semibold text-slate-800 dark:text-slate-200 hover:text-indigo-600 hover:underline"
                                                        >
                                                            {deal.contact.first_name} {deal.contact.last_name}
                                                        </Link>
                                                        <div className="text-[11px] text-slate-500 font-mono">
                                                            {deal.contact.phone}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">N/A</span>
                                                )}
                                            </td>

                                            <td className="p-3.5">
                                                {deal.property ? (
                                                    <div>
                                                        <Link
                                                            href={route('properties.show', deal.property.id)}
                                                            className="font-medium text-slate-800 dark:text-slate-200 hover:text-indigo-600"
                                                        >
                                                            {deal.property.title}
                                                        </Link>
                                                        <div className="text-[11px] text-slate-500">
                                                            {deal.property.plot_size} • {deal.property.estate?.name || deal.property.location}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">Custom Acquisition</span>
                                                )}
                                            </td>

                                            <td className="p-3.5">
                                                <div className="font-extrabold text-sm text-slate-900 dark:text-white">
                                                    {formatCurrency(deal.deal_value)}
                                                </div>
                                                {deal.probability && (
                                                    <div className="text-[10px] text-slate-500">
                                                        Prob: {deal.probability}%
                                                    </div>
                                                )}
                                            </td>

                                            <td className="p-3.5">
                                                <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                                    {deal.stage?.name || 'In Progress'}
                                                </span>
                                            </td>

                                            <td className="p-3.5">
                                                {deal.status === 'won' && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 uppercase">
                                                        <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                                                        Closed Won
                                                    </span>
                                                )}
                                                {deal.status === 'lost' && (
                                                    <div>
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 uppercase">
                                                            <XCircle className="w-3 h-3 text-rose-600" />
                                                            Closed Lost
                                                        </span>
                                                        {deal.lost_reason && (
                                                            <div className="text-[10px] text-slate-500 dark:text-slate-400 truncate max-w-xs mt-0.5" title={deal.lost_reason}>
                                                                {deal.lost_reason}
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                                {deal.status === 'open' && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 uppercase">
                                                        Open Deal
                                                    </span>
                                                )}
                                            </td>

                                            <td className="p-3.5 text-slate-600 dark:text-slate-300 font-medium">
                                                {formatDate(deal.expected_close_date)}
                                            </td>

                                            <td className="p-3.5 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {deal.status === 'open' && (
                                                        <>
                                                            <button
                                                                onClick={() => handleMarkWon(deal)}
                                                                className="p-1.5 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded transition-colors"
                                                                title="Mark Closed Won"
                                                            >
                                                                <Check className="w-4 h-4" />
                                                            </button>
                                                            <button
                                                                onClick={() => openLostModal(deal)}
                                                                className="p-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded transition-colors"
                                                                title="Mark Closed Lost"
                                                            >
                                                                <X className="w-4 h-4" />
                                                            </button>
                                                        </>
                                                    )}

                                                    {deal.status !== 'open' && (
                                                        <button
                                                            onClick={() => handleReopen(deal)}
                                                            className="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40 rounded transition-colors"
                                                            title="Reopen Deal"
                                                        >
                                                            <RotateCw className="w-3.5 h-3.5" />
                                                        </button>
                                                    )}

                                                    <Link
                                                        href={route('deals.show', deal.id)}
                                                        className="p-1.5 text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                        title="View Deal 360"
                                                    >
                                                        <Eye className="w-4 h-4" />
                                                    </Link>

                                                    {can('deals.edit') && (
                                                        <Link
                                                            href={route('deals.edit', deal.id)}
                                                            className="p-1.5 text-slate-500 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                                            title="Edit Deal"
                                                        >
                                                            <Edit className="w-4 h-4" />
                                                        </Link>
                                                    )}

                                                    {can('deals.delete') && (
                                                        <button
                                                            onClick={() => handleDelete(deal)}
                                                            className="p-1.5 text-rose-500 hover:text-rose-700 transition-colors"
                                                            title="Delete Deal"
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="8" className="p-8 text-center text-slate-400">
                                            No opportunities found matching your criteria.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {deals.links?.length > 3 && (
                    <div className="flex items-center justify-between pt-4">
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            Showing {deals.from || 0} to {deals.to || 0} of {deals.total} deals
                        </div>
                        <div className="flex items-center gap-1">
                            {deals.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveScroll
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`px-3 py-1 text-xs rounded-md font-medium transition-colors ${
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : !link.url
                                            ? 'text-slate-400 cursor-not-allowed'
                                            : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    }`}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Lost Reason Modal */}
            {lostModalOpen && selectedDealForLost && (
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
                            Capture the exact reason why <strong>{selectedDealForLost.title}</strong> was not won. This is critical for analytics and re-engagement.
                        </p>

                        <form onSubmit={handleConfirmLost} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Lost Reason *
                                </label>
                                <textarea
                                    required
                                    rows="3"
                                    placeholder="e.g. Client opted for alternative estate in Epe, budget constraints, delayed mortgage approval..."
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
