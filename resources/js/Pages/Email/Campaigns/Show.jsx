import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    Send, 
    ArrowLeft, 
    Pause, 
    Play, 
    XCircle, 
    Calendar, 
    Clock, 
    Users, 
    CheckCircle, 
    AlertTriangle, 
    Search, 
    Mail, 
    Eye, 
    MousePointer, 
    ShieldAlert, 
    RefreshCw, 
    Edit, 
    Monitor, 
    Smartphone,
    BarChart3
} from 'lucide-react';

export default function Show({ auth, campaign, recipients, summary, recipientStatuses, filters }) {
    const [activeTab, setActiveTab] = useState('recipients'); // 'recipients' or 'content'
    const [previewDevice, setPreviewDevice] = useState('desktop'); // 'desktop' or 'mobile'
    const [search, setSearch] = useState(filters?.search || '');
    const [selectedRecipientStatus, setSelectedRecipientStatus] = useState(filters?.status || '');

    // Live progress state
    const [liveProgress, setLiveProgress] = useState({
        status: campaign.status,
        progress_percentage: summary.progress_percentage,
        sent_count: summary.sent,
        failed_count: summary.failed,
        delivered_count: summary.delivered,
        opened_count: summary.opened,
        clicked_count: summary.clicked,
    });

    // Test send modal
    const [testModal, setTestModal] = useState({
        isOpen: false,
        recipientEmail: auth.user?.email || '',
        loading: false,
        successMessage: null,
        errorMessage: null,
    });

    // Polling effect for active campaigns
    useEffect(() => {
        let interval = null;

        if (['processing', 'sending'].includes(liveProgress.status)) {
            interval = setInterval(async () => {
                try {
                    const response = await fetch(route('email-campaigns.progress', campaign.id));
                    if (response.ok) {
                        const data = await response.json();
                        setLiveProgress({
                            status: data.status,
                            progress_percentage: data.progress_percentage,
                            sent_count: data.sent_count,
                            failed_count: data.failed_count,
                            delivered_count: data.delivered_count,
                            opened_count: data.opened_count,
                            clicked_count: data.clicked_count,
                        });

                        // If completed or failed, reload data to update tables
                        if (['completed', 'failed', 'paused'].includes(data.status) && data.status !== campaign.status) {
                            router.reload({ only: ['campaign', 'recipients', 'summary'] });
                        }
                    }
                } catch (e) {
                    console.error('Progress polling error:', e);
                }
            }, 3500);
        }

        return () => {
            if (interval) clearInterval(interval);
        };
    }, [liveProgress.status, campaign.id]);

    const handleAction = (actionName) => {
        if (actionName === 'cancel' && !confirm('Are you sure you want to cancel this campaign?')) {
            return;
        }

        router.post(route(`email-campaigns.${actionName}`, campaign.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload();
            }
        });
    };

    const handleFilterRecipients = (e) => {
        e.preventDefault();
        router.get(route('email-campaigns.show', campaign.id), {
            search: search || undefined,
            status: selectedRecipientStatus || undefined,
        }, { preserveState: true, replace: true });
    };

    const handleSendTest = async (e) => {
        e.preventDefault();
        setTestModal(prev => ({ ...prev, loading: true, successMessage: null, errorMessage: null }));

        try {
            const response = await fetch(route('email-campaigns.send-test', campaign.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ recipient_email: testModal.recipientEmail }),
            });

            const data = await response.json();
            if (response.ok) {
                setTestModal(prev => ({
                    ...prev,
                    loading: false,
                    successMessage: data.message || 'Test email dispatched.',
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
                errorMessage: 'Network error occurred.',
            }));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Link
                                href={route('email-campaigns.index')}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                            <span className="text-xs text-slate-500 font-medium">Email Campaigns / Details</span>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Send className="h-6 w-6 text-sky-600" />
                                {campaign.name}
                            </h2>
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300">
                                {liveProgress.status.toUpperCase()}
                            </span>
                        </div>
                    </div>

                    {/* Action Controls */}
                    <div className="flex flex-wrap items-center gap-2">
                        {liveProgress.status === 'sending' && (
                            <button
                                type="button"
                                onClick={() => handleAction('pause')}
                                className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-yellow-300 dark:border-yellow-700 text-yellow-800 dark:text-yellow-300 bg-yellow-50 dark:bg-yellow-950 hover:bg-yellow-100 transition"
                            >
                                <Pause className="h-3.5 w-3.5" />
                                Pause Campaign
                            </button>
                        )}

                        {liveProgress.status === 'paused' && (
                            <button
                                type="button"
                                onClick={() => handleAction('resume')}
                                className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm"
                            >
                                <Play className="h-3.5 w-3.5" />
                                Resume Sending
                            </button>
                        )}

                        {['draft', 'scheduled'].includes(liveProgress.status) && (
                            <button
                                type="button"
                                onClick={() => handleAction('send-now')}
                                className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                            >
                                <Send className="h-3.5 w-3.5" />
                                Send Immediately
                            </button>
                        )}

                        <button
                            type="button"
                            onClick={() => setTestModal(prev => ({ ...prev, isOpen: true }))}
                            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 transition"
                        >
                            <Mail className="h-3.5 w-3.5 text-amber-500" />
                            Send Test
                        </button>

                        {['draft', 'scheduled'].includes(liveProgress.status) && (
                            <Link
                                href={route('email-campaigns.edit', campaign.id)}
                                className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 transition"
                            >
                                <Edit className="h-3.5 w-3.5" />
                                Edit
                            </Link>
                        )}

                        {['draft', 'scheduled', 'sending', 'paused'].includes(liveProgress.status) && (
                            <button
                                type="button"
                                onClick={() => handleAction('cancel')}
                                className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950 transition"
                            >
                                <XCircle className="h-3.5 w-3.5" />
                                Cancel
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`Campaign: ${campaign.name}`} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Active Sending Progress Bar Banner */}
                {['processing', 'sending', 'completed', 'paused'].includes(liveProgress.status) && (
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                        <div className="flex flex-wrap items-center justify-between text-xs text-slate-600 dark:text-slate-400">
                            <span className="font-semibold flex items-center gap-2">
                                <Clock className={`h-4 w-4 ${liveProgress.status === 'sending' ? 'animate-spin text-sky-600' : 'text-slate-400'}`} />
                                Delivery Progress: {liveProgress.progress_percentage}%
                            </span>
                            <span>
                                Dispatched <strong>{liveProgress.sent_count}</strong> of <strong>{summary.eligible}</strong> eligible recipients
                                {summary.skipped > 0 && ` (${summary.skipped} skipped due to suppression/consent)`}
                            </span>
                        </div>
                        <div className="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                            <div
                                className={`h-2.5 rounded-full transition-all duration-700 ${
                                    liveProgress.status === 'completed' ? 'bg-emerald-500' : 'bg-sky-600'
                                }`}
                                style={{ width: `${liveProgress.progress_percentage}%` }}
                            />
                        </div>
                    </div>
                )}

                {/* Performance Metrics Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div className="text-xs text-slate-500">Recipients</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                            {summary.total}
                        </div>
                        <div className="text-[10px] text-slate-400 mt-0.5">{summary.eligible} eligible</div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div className="text-xs text-slate-500">Dispatched</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                            {liveProgress.sent_count}
                        </div>
                        <div className="text-[10px] text-emerald-600 mt-0.5">Queued on SES</div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div className="text-xs text-slate-500">Opens</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                            {liveProgress.opened_count}
                        </div>
                        <div className="text-[10px] text-sky-600 font-semibold mt-0.5">{summary.open_rate}% rate</div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div className="text-xs text-slate-500">Clicks</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                            {liveProgress.clicked_count}
                        </div>
                        <div className="text-[10px] text-indigo-600 font-semibold mt-0.5">{summary.click_rate}% rate</div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div className="text-xs text-slate-500">Bounces</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                            {summary.bounced}
                        </div>
                        <div className="text-[10px] text-rose-500 font-semibold mt-0.5">{summary.bounce_rate}% rate</div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div className="text-xs text-slate-500">Unsubscribes</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                            {summary.unsubscribed}
                        </div>
                        <div className="text-[10px] text-amber-500 mt-0.5">{summary.complained} spam reports</div>
                    </div>
                </div>

                {/* Tabs switcher */}
                <div className="flex border-b border-slate-200 dark:border-slate-700 space-x-6 text-sm">
                    <button
                        type="button"
                        onClick={() => setActiveTab('recipients')}
                        className={`pb-3 font-semibold transition border-b-2 ${
                            activeTab === 'recipients'
                                ? 'border-sky-600 text-sky-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Recipients & Delivery State ({summary.total})
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('content')}
                        className={`pb-3 font-semibold transition border-b-2 ${
                            activeTab === 'content'
                                ? 'border-sky-600 text-sky-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Message Content & Simulator
                    </button>
                </div>

                {/* TAB 1: RECIPIENTS TABLE */}
                {activeTab === 'recipients' && (
                    <div className="space-y-4">
                        {/* Filter Bar */}
                        <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row gap-3">
                            <form onSubmit={handleFilterRecipients} className="flex-1 flex gap-2">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Search recipients by name or email..."
                                        className="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                                    />
                                </div>
                                <select
                                    value={selectedRecipientStatus}
                                    onChange={(e) => setSelectedRecipientStatus(e.target.value)}
                                    className="text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                                >
                                    <option value="">All Recipient Statuses</option>
                                    {recipientStatuses.map(s => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-semibold rounded-lg bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 hover:bg-slate-800 transition"
                                >
                                    Filter
                                </button>
                            </form>
                        </div>

                        {/* Recipients Table */}
                        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold">
                                        <tr>
                                            <th className="p-3">Contact</th>
                                            <th className="p-3">Email Address</th>
                                            <th className="p-3">Status</th>
                                            <th className="p-3">Batch</th>
                                            <th className="p-3">Skip Reason / Notes</th>
                                            <th className="p-3">Dispatched At</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                        {recipients.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="p-8 text-center text-slate-400">
                                                    No recipients matching the selected criteria.
                                                </td>
                                            </tr>
                                        ) : (
                                            recipients.data.map((r) => {
                                                const statusObj = recipientStatuses.find(s => s.value === r.status) || {
                                                    label: r.status,
                                                    badgeClass: 'bg-slate-100 text-slate-700',
                                                };

                                                return (
                                                    <tr key={r.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-700/20">
                                                        <td className="p-3 font-semibold text-slate-900 dark:text-white">
                                                            {r.contact ? `${r.contact.first_name} ${r.contact.last_name || ''}` : 'Contact'}
                                                        </td>
                                                        <td className="p-3 font-mono text-slate-600 dark:text-slate-300">
                                                            {r.email}
                                                        </td>
                                                        <td className="p-3">
                                                            <span className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border ${statusObj.badgeClass}`}>
                                                                {statusObj.label}
                                                            </span>
                                                        </td>
                                                        <td className="p-3 text-slate-500 font-mono">
                                                            {r.batch_number > 0 ? `#${r.batch_number}` : '-'}
                                                        </td>
                                                        <td className="p-3 text-slate-500">
                                                            {r.skip_reason ? (
                                                                <span className="text-amber-700 dark:text-amber-300 font-medium">
                                                                    Skipped: {r.skip_reason}
                                                                </span>
                                                            ) : r.error_message ? (
                                                                <span className="text-rose-600 truncate max-w-xs block" title={r.error_message}>
                                                                    {r.error_message}
                                                                </span>
                                                            ) : (
                                                                '-'
                                                            )}
                                                        </td>
                                                        <td className="p-3 text-slate-500">
                                                            {r.sent_at ? new Date(r.sent_at).toLocaleString() : '-'}
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            {recipients.links && recipients.links.length > 3 && (
                                <div className="p-4 border-t border-slate-200 dark:border-slate-700 flex justify-between items-center text-xs text-slate-500">
                                    <div>
                                        Showing {recipients.from || 0} to {recipients.to || 0} of {recipients.total} recipients
                                    </div>
                                    <div className="flex gap-1">
                                        {recipients.links.map((link, idx) => (
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
                )}

                {/* TAB 2: CONTENT & PREVIEW */}
                {activeTab === 'content' && (
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        <div className="lg:col-span-4 bg-white dark:bg-slate-800 rounded-xl p-5 border border-slate-200 dark:border-slate-700 space-y-4">
                            <h4 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                Message Configuration
                            </h4>

                            <div>
                                <span className="text-xs text-slate-500 block">Subject:</span>
                                <div className="text-sm font-semibold text-slate-900 dark:text-white mt-0.5">
                                    {campaign.subject}
                                </div>
                            </div>

                            <div>
                                <span className="text-xs text-slate-500 block">Preheader:</span>
                                <div className="text-xs text-slate-700 dark:text-slate-300 mt-0.5">
                                    {campaign.preheader || 'None'}
                                </div>
                            </div>

                            <div>
                                <span className="text-xs text-slate-500 block">Smart List / Audience:</span>
                                <div className="text-xs font-semibold text-sky-600 mt-0.5">
                                    {campaign.smart_list ? campaign.smart_list.name : 'Custom CRM Segment'}
                                </div>
                            </div>

                            <div>
                                <span className="text-xs text-slate-500 block">Batch Sizing:</span>
                                <div className="text-xs font-mono text-slate-700 dark:text-slate-300 mt-0.5">
                                    {campaign.batch_size} recipients per queued job
                                </div>
                            </div>

                            <div className="pt-2 border-t border-slate-200 dark:border-slate-700">
                                <button
                                    type="button"
                                    onClick={() => setTestModal(prev => ({ ...prev, isOpen: true }))}
                                    className="w-full py-2 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white flex items-center justify-center gap-1.5 transition"
                                >
                                    <Mail className="h-4 w-4" />
                                    Send Live Test Email
                                </button>
                            </div>
                        </div>

                        {/* Simulator */}
                        <div className="lg:col-span-8 bg-slate-100 dark:bg-slate-900/60 rounded-xl p-4 border border-slate-200 dark:border-slate-700 flex flex-col">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700 mb-3">
                                <span className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                                    <Monitor className="h-4 w-4 text-sky-600" />
                                    Rendered Preview
                                </span>
                                <div className="flex items-center gap-1 bg-white dark:bg-slate-800 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
                                    <button
                                        type="button"
                                        onClick={() => setPreviewDevice('desktop')}
                                        className={`p-1.5 rounded text-xs flex items-center gap-1 ${
                                            previewDevice === 'desktop'
                                                ? 'bg-sky-600 text-white font-bold'
                                                : 'text-slate-500 hover:text-slate-700'
                                        }`}
                                    >
                                        <Monitor className="h-3.5 w-3.5" />
                                        Desktop
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setPreviewDevice('mobile')}
                                        className={`p-1.5 rounded text-xs flex items-center gap-1 ${
                                            previewDevice === 'mobile'
                                                ? 'bg-sky-600 text-white font-bold'
                                                : 'text-slate-500 hover:text-slate-700'
                                        }`}
                                    >
                                        <Smartphone className="h-3.5 w-3.5" />
                                        Mobile
                                    </button>
                                </div>
                            </div>

                            <div className="flex-1 flex justify-center items-start overflow-auto p-4 bg-slate-200 dark:bg-slate-950 rounded-lg">
                                <div
                                    style={{ width: previewDevice === 'mobile' ? '375px' : '100%', maxWidth: '600px' }}
                                    className="bg-white text-slate-800 shadow-md rounded-lg overflow-hidden border border-slate-300 transition-all duration-300"
                                >
                                    <div className="bg-slate-900 text-white p-4 border-b-2 border-sky-500">
                                        <div className="font-extrabold text-lg tracking-tight">
                                            BAMCOM <span className="text-sky-400">CRM</span>
                                        </div>
                                        <div className="text-[10px] text-slate-400 uppercase tracking-widest">
                                            Real Estate &bull; Site Inspections &bull; Investments
                                        </div>
                                    </div>

                                    <div
                                        className="p-6 text-sm leading-relaxed prose prose-sm max-w-none"
                                        dangerouslySetInnerHTML={{
                                            __html: campaign.body_html
                                                ? campaign.body_html
                                                    .replace(/\{\{\s*contact\.first_name\s*\}\}/g, 'Babajide')
                                                    .replace(/\{\{\s*company\.name\s*\}\}/g, 'Bamcom Real Estate')
                                                    .replace(/\{\{\s*unsubscribe_url\s*\}\}/g, '#')
                                                : '',
                                        }}
                                    />

                                    <div className="bg-slate-50 border-t border-slate-200 p-4 text-center text-[11px] text-slate-500">
                                        <p className="font-semibold text-slate-700 m-0">Bamcom Real Estate & Investments Ltd</p>
                                        <p className="m-0 mt-1">Plot 12, Admiralty Way, Lekki Phase 1, Lagos</p>
                                        <p className="m-0 mt-2">
                                            <a href="#" className="text-sky-600 underline">Unsubscribe</a> &bull; <a href="#" className="text-sky-600 underline">Preferences</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Test Send Modal */}
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
                            Dispatches a live preview of <strong>{campaign.name}</strong> to the designated recipient.
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
