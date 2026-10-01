import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    Send, 
    PlusCircle, 
    Search, 
    Eye, 
    Edit, 
    Trash2, 
    Pause, 
    Play, 
    XCircle, 
    Calendar, 
    Clock, 
    Users, 
    CheckCircle, 
    AlertTriangle, 
    Layers, 
    FileText, 
    ShieldAlert, 
    Mail, 
    BarChart3,
    ArrowUpRight
} from 'lucide-react';

export default function Index({ auth, campaigns, stats, statuses, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');

    // Test send modal
    const [testModal, setTestModal] = useState({
        isOpen: false,
        campaignId: null,
        campaignName: '',
        recipientEmail: auth.user?.email || '',
        loading: false,
        successMessage: null,
        errorMessage: null,
    });

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('email-campaigns.index'), {
            search: search || undefined,
            status: selectedStatus || undefined,
        }, { preserveState: true, replace: true });
    };

    const handleAction = (campaignId, actionName) => {
        if (actionName === 'delete' && !confirm('Are you sure you want to delete this campaign?')) {
            return;
        }
        if (actionName === 'cancel' && !confirm('Are you sure you want to cancel this campaign? Pending recipients will not be sent.')) {
            return;
        }

        const url = actionName === 'delete'
            ? route('email-campaigns.destroy', campaignId)
            : route(`email-campaigns.${actionName}`, campaignId);

        const method = actionName === 'delete' ? 'delete' : 'post';

        router[method](url, {}, {
            preserveScroll: true,
        });
    };

    const handleOpenTestModal = (campaign) => {
        setTestModal({
            isOpen: true,
            campaignId: campaign.id,
            campaignName: campaign.name,
            recipientEmail: auth.user?.email || '',
            loading: false,
            successMessage: null,
            errorMessage: null,
        });
    };

    const handleSendTest = async (e) => {
        e.preventDefault();
        setTestModal(prev => ({ ...prev, loading: true, successMessage: null, errorMessage: null }));

        try {
            const response = await fetch(route('email-campaigns.send-test', testModal.campaignId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    recipient_email: testModal.recipientEmail,
                }),
            });

            const data = await response.json();

            if (response.ok) {
                setTestModal(prev => ({
                    ...prev,
                    loading: false,
                    successMessage: data.message || `Test email dispatched to ${testModal.recipientEmail}.`,
                }));
            } else {
                setTestModal(prev => ({
                    ...prev,
                    loading: false,
                    errorMessage: data.message || 'Failed to dispatch test email.',
                }));
            }
        } catch (err) {
            setTestModal(prev => ({
                ...prev,
                loading: false,
                errorMessage: 'Network error occurred while sending test email.',
            }));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Send className="h-6 w-6 text-sky-600 dark:text-sky-400" />
                            Email Marketing Campaigns
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Audience-targeted broadcasts, Smart List segment filtering, queued batch delivery, and real-time delivery metrics
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={route('emails.index')}
                            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                        >
                            <Mail className="h-4 w-4" />
                            Emails
                        </Link>
                        <Link
                            href={route('email-templates.index')}
                            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                        >
                            <FileText className="h-4 w-4" />
                            Templates
                        </Link>
                        <Link
                            href={route('email-suppressions.index')}
                            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                        >
                            <ShieldAlert className="h-4 w-4 text-rose-500" />
                            Suppressions
                        </Link>
                        <Link
                            href={route('email-campaigns.create')}
                            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                        >
                            <PlusCircle className="h-4 w-4" />
                            Create Campaign
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Email Marketing Campaigns" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Stats Summary Bar */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Campaigns</span>
                            <span className="p-2 rounded-lg bg-sky-50 dark:bg-sky-950 text-sky-600">
                                <Send className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                            {stats.total_campaigns.toLocaleString()}
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Emails Sent</span>
                            <span className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950 text-emerald-600">
                                <CheckCircle className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                            {stats.total_sent.toLocaleString()}
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Opened</span>
                            <span className="p-2 rounded-lg bg-teal-50 dark:bg-teal-950 text-teal-600">
                                <Eye className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                            {stats.total_opened.toLocaleString()}
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Active / Sending</span>
                            <span className="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-600">
                                <Clock className="h-4 w-4 animate-spin" />
                            </span>
                        </div>
                        <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                            {stats.active_count.toLocaleString()}
                        </div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <form onSubmit={handleFilter} className="flex flex-col sm:flex-row gap-3">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search campaigns by name, subject..."
                                className="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                            />
                        </div>

                        <select
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                            className="text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                        >
                            <option value="">All Statuses</option>
                            {statuses.map(s => (
                                <option key={s.value} value={s.value}>{s.label}</option>
                            ))}
                        </select>

                        <button
                            type="submit"
                            className="px-4 py-2 text-sm font-semibold rounded-lg bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-200 transition"
                        >
                            Filter
                        </button>

                        {(search || selectedStatus) && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    setSelectedStatus('');
                                    router.get(route('email-campaigns.index'));
                                }}
                                className="px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                            >
                                Reset
                            </button>
                        )}
                    </form>
                </div>

                {/* Campaigns List */}
                <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    {campaigns.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <Send className="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600 mb-3" />
                            <h3 className="text-base font-semibold text-slate-800 dark:text-slate-200">No campaigns found</h3>
                            <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                                Get started by creating your first audience-targeted marketing campaign.
                            </p>
                            <Link
                                href={route('email-campaigns.create')}
                                className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                            >
                                <PlusCircle className="h-4 w-4" />
                                Create Campaign
                            </Link>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-200 dark:divide-slate-700">
                            {campaigns.data.map((campaign) => {
                                const currentStatus = statuses.find(s => s.value === campaign.status) || {
                                    label: campaign.status,
                                    badgeClass: 'bg-slate-100 text-slate-700',
                                };
                                const progress = campaign.eligible_recipients > 0
                                    ? Math.round(((campaign.sent_count + campaign.failed_count) / campaign.eligible_recipients) * 100)
                                    : (campaign.status === 'completed' ? 100 : 0);

                                return (
                                    <div key={campaign.id} className="p-5 hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div className="space-y-2 flex-1 min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={route('email-campaigns.show', campaign.id)}
                                                    className="text-base font-bold text-slate-900 dark:text-white hover:text-sky-600 dark:hover:text-sky-400 transition truncate"
                                                >
                                                    {campaign.name}
                                                </Link>
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${currentStatus.badgeClass}`}>
                                                    {currentStatus.label}
                                                </span>
                                                {campaign.smart_list && (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                        <Users className="h-3 w-3" />
                                                        {campaign.smart_list.name}
                                                    </span>
                                                )}
                                            </div>

                                            <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-1">
                                                <span className="font-medium text-slate-700 dark:text-slate-300">Subject:</span> {campaign.subject}
                                            </p>

                                            {/* Progress / Stats info */}
                                            <div className="flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                                <span>Recipients: <strong className="text-slate-700 dark:text-slate-200">{campaign.total_recipients}</strong> ({campaign.eligible_recipients} eligible)</span>
                                                <span>Sent: <strong className="text-slate-700 dark:text-slate-200">{campaign.sent_count}</strong></span>
                                                <span>Opened: <strong className="text-slate-700 dark:text-slate-200">{campaign.opened_count}</strong></span>
                                                {campaign.scheduled_at && campaign.status === 'scheduled' && (
                                                    <span className="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 font-medium">
                                                        <Calendar className="h-3.5 w-3.5" />
                                                        Scheduled: {new Date(campaign.scheduled_at).toLocaleString()}
                                                    </span>
                                                )}
                                            </div>

                                            {/* Progress Bar for actively sending or completed */}
                                            {['processing', 'sending', 'completed', 'paused'].includes(campaign.status) && (
                                                <div className="w-full max-w-md pt-1">
                                                    <div className="flex justify-between text-[11px] text-slate-500 mb-1">
                                                        <span>Progress</span>
                                                        <span>{progress}%</span>
                                                    </div>
                                                    <div className="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                                        <div 
                                                            className={`h-1.5 rounded-full transition-all duration-500 ${
                                                                campaign.status === 'completed' ? 'bg-emerald-500' : 'bg-sky-600'
                                                            }`} 
                                                            style={{ width: `${progress}%` }} 
                                                        />
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="flex items-center gap-2 self-start md:self-center shrink-0">
                                            {campaign.status === 'sending' && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleAction(campaign.id, 'pause')}
                                                    className="p-1.5 rounded-lg border border-yellow-300 dark:border-yellow-700 text-yellow-700 dark:text-yellow-400 hover:bg-yellow-50 dark:hover:bg-yellow-950 transition"
                                                    title="Pause Campaign"
                                                >
                                                    <Pause className="h-4 w-4" />
                                                </button>
                                            )}

                                            {campaign.status === 'paused' && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleAction(campaign.id, 'resume')}
                                                    className="p-1.5 rounded-lg border border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950 transition"
                                                    title="Resume Campaign"
                                                >
                                                    <Play className="h-4 w-4" />
                                                </button>
                                            )}

                                            {['draft', 'scheduled'].includes(campaign.status) && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleAction(campaign.id, 'send-now')}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                                                    title="Send Immediately"
                                                >
                                                    <Send className="h-3.5 w-3.5" />
                                                    Send
                                                </button>
                                            )}

                                            <button
                                                type="button"
                                                onClick={() => handleOpenTestModal(campaign)}
                                                className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                                title="Send Test Email"
                                            >
                                                <Mail className="h-4 w-4 text-amber-500" />
                                            </button>

                                            <Link
                                                href={route('email-campaigns.show', campaign.id)}
                                                className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                                title="View Dashboard & Analytics"
                                            >
                                                <BarChart3 className="h-4 w-4 text-sky-600" />
                                            </Link>

                                            {['draft', 'scheduled'].includes(campaign.status) && (
                                                <Link
                                                    href={route('email-campaigns.edit', campaign.id)}
                                                    className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                                    title="Edit Campaign"
                                                >
                                                    <Edit className="h-4 w-4" />
                                                </Link>
                                            )}

                                            {['draft', 'scheduled', 'paused', 'sending'].includes(campaign.status) && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleAction(campaign.id, 'cancel')}
                                                    className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950 transition"
                                                    title="Cancel Campaign"
                                                >
                                                    <XCircle className="h-4 w-4" />
                                                </button>
                                            )}

                                            {['draft', 'cancelled', 'completed', 'failed'].includes(campaign.status) && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleAction(campaign.id, 'delete')}
                                                    className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950 transition"
                                                    title="Delete Campaign"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {/* Pagination */}
                    {campaigns.links && campaigns.links.length > 3 && (
                        <div className="p-4 border-t border-slate-200 dark:border-slate-700 flex justify-between items-center text-xs text-slate-500">
                            <div>
                                Showing {campaigns.from || 0} to {campaigns.to || 0} of {campaigns.total} campaigns
                            </div>
                            <div className="flex gap-1">
                                {campaigns.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1.5 rounded border text-xs ${
                                            link.active
                                                ? 'bg-sky-600 text-white border-sky-600 font-bold'
                                                : link.url
                                                ? 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50'
                                                : 'bg-slate-100 dark:bg-slate-800 text-slate-400 border-slate-200 dark:border-slate-700 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Test Email Modal */}
            {testModal.isOpen && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden p-6 space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Mail className="h-5 w-5 text-amber-500" />
                                Send Test Campaign Email
                            </h3>
                            <button
                                type="button"
                                onClick={() => setTestModal(prev => ({ ...prev, isOpen: false }))}
                                className="text-slate-400 hover:text-slate-600 text-xl font-bold"
                            >
                                &times;
                            </button>
                        </div>

                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Dispatches a live preview of <strong>{testModal.campaignName}</strong> with merged mock variables to verify layout, preheader, and email client rendering.
                        </p>

                        {testModal.successMessage && (
                            <div className="p-3 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs rounded-lg flex items-center gap-2">
                                <CheckCircle className="h-4 w-4 shrink-0" />
                                <span>{testModal.successMessage}</span>
                            </div>
                        )}

                        {testModal.errorMessage && (
                            <div className="p-3 bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs rounded-lg flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 shrink-0" />
                                <span>{testModal.errorMessage}</span>
                            </div>
                        )}

                        <form onSubmit={handleSendTest} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Recipient Email Address
                                </label>
                                <input
                                    type="email"
                                    required
                                    value={testModal.recipientEmail}
                                    onChange={(e) => setTestModal(prev => ({ ...prev, recipientEmail: e.target.value }))}
                                    className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                                    placeholder="your-email@company.com"
                                />
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setTestModal(prev => ({ ...prev, isOpen: false }))}
                                    className="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700"
                                >
                                    Close
                                </button>
                                <button
                                    type="submit"
                                    disabled={testModal.loading}
                                    className="px-4 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white flex items-center gap-1.5 transition disabled:opacity-50"
                                >
                                    {testModal.loading ? 'Sending...' : 'Send Test'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
