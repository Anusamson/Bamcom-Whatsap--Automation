import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Megaphone,
    ArrowLeft,
    Play,
    Pause,
    XCircle,
    CheckCircle2,
    Clock,
    Users,
    MessageSquare,
    AlertCircle,
    ShieldAlert,
    RefreshCw,
    Send,
    Eye,
    TrendingUp,
    Check,
    X,
    Filter,
    Layers,
    FileText
} from 'lucide-react';
import { useState } from 'react';

export default function Show({
    campaign,
    recipients = { data: [], links: [] },
    events = [],
    recipientStatuses = [],
    filters = {}
}) {
    const [recipientStatus, setRecipientStatus] = useState(filters.recipient_status || 'all');
    const [activeTab, setActiveTab] = useState('recipients'); // 'recipients' | 'events' | 'preview'

    const handleFilterStatus = (status) => {
        setRecipientStatus(status);
        router.get(
            route('campaigns.show', campaign.id),
            { recipient_status: status },
            { preserveState: true, replace: true }
        );
    };

    const handleLaunch = () => {
        if (confirm(`Are you sure you want to launch '${campaign.name}'? Batched sends will begin immediately.`)) {
            router.post(route('campaigns.launch', campaign.id));
        }
    };

    const handlePause = () => {
        router.post(route('campaigns.pause', campaign.id));
    };

    const handleResume = () => {
        router.post(route('campaigns.resume', campaign.id));
    };

    const handleCancel = () => {
        const reason = prompt('Please enter a cancellation reason:', 'Cancelled by administrator');
        if (reason) {
            router.post(route('campaigns.cancel', campaign.id), { reason });
        }
    };

    const totalRecipients = campaign.total_recipients || 0;
    const sentCount = campaign.sent_count || 0;
    const deliveredCount = campaign.delivered_count || 0;
    const readCount = campaign.read_count || 0;
    const failedCount = campaign.failed_count || 0;
    const optedOutCount = campaign.opted_out_count || 0;

    const progressPercentage = totalRecipients > 0
        ? Math.min(100, Math.round(((sentCount + failedCount + optedOutCount) / totalRecipients) * 100))
        : 0;

    const deliveryRate = sentCount > 0
        ? Math.round((deliveredCount / sentCount) * 100)
        : 0;

    const readRate = deliveredCount > 0
        ? Math.round((readCount / deliveredCount) * 100)
        : 0;

    const getStatusBadge = (status) => {
        const map = {
            draft: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            scheduled: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            running: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 animate-pulse',
            paused: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            completed: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
            cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        };
        return map[status] || 'bg-gray-100 text-gray-700';
    };

    const getRecipientBadge = (status) => {
        const map = {
            pending: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            queued: 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
            sent: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300',
            delivered: 'bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300',
            read: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 font-semibold',
            failed: 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
            skipped: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
            opted_out: 'bg-red-100 text-red-800 dark:bg-red-950/80 dark:text-red-300 font-semibold',
        };
        return map[status] || 'bg-gray-100 text-gray-700';
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Campaign: ${campaign.name}`} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Back button and Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <Link
                                href={route('campaigns.index')}
                                className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl text-gray-500 transition"
                            >
                                <ArrowLeft className="w-5 h-5" />
                            </Link>
                            <div>
                                <div className="flex items-center gap-3">
                                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        {campaign.name}
                                    </h1>
                                    <span
                                        className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${getStatusBadge(
                                            campaign.status
                                        )}`}
                                    >
                                        {campaign.status}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {campaign.description || 'WhatsApp Broadcast Campaign'}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Action buttons */}
                    <div className="flex items-center gap-2">
                        {['draft', 'scheduled'].includes(campaign.status) && (
                            <button
                                onClick={handleLaunch}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition"
                            >
                                <Play className="w-4 h-4 fill-white" />
                                <span>Launch Now</span>
                            </button>
                        )}

                        {campaign.status === 'running' && (
                            <button
                                onClick={handlePause}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl shadow-sm transition"
                            >
                                <Pause className="w-4 h-4" />
                                <span>Pause Campaign</span>
                            </button>
                        )}

                        {campaign.status === 'paused' && (
                            <button
                                onClick={handleResume}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition"
                            >
                                <Play className="w-4 h-4 fill-white" />
                                <span>Resume</span>
                            </button>
                        )}

                        {['running', 'paused', 'scheduled', 'draft'].includes(campaign.status) && (
                            <button
                                onClick={handleCancel}
                                className="inline-flex items-center gap-2 px-3.5 py-2 border border-rose-200 dark:border-rose-900/50 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-700 dark:text-rose-300 text-sm font-semibold rounded-xl transition"
                            >
                                <XCircle className="w-4 h-4" />
                                <span>Cancel</span>
                            </button>
                        )}
                    </div>
                </div>

                {/* Opt-out Protection Notice */}
                <div className="flex items-center gap-3 p-4 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 rounded-2xl text-emerald-800 dark:text-emerald-300 text-xs sm:text-sm">
                    <ShieldAlert className="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <div>
                        <span className="font-semibold">Opt-Out Defense Active:</span> Contacts who have unsubscribed or reply STOP are automatically excluded from target audiences and blocked before message transmission.
                    </div>
                </div>

                {/* Progress Bar Card */}
                <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Dispatch Progress
                            </h3>
                            <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                                {progressPercentage}% Complete
                            </p>
                        </div>
                        <div className="text-right text-xs text-gray-500 dark:text-gray-400">
                            <div>Batch Size: <span className="font-semibold text-gray-700 dark:text-gray-300">{campaign.batch_size} / batch</span></div>
                            <div>Pacing Delay: <span className="font-semibold text-gray-700 dark:text-gray-300">{campaign.batch_delay_seconds}s</span></div>
                        </div>
                    </div>

                    {/* Progress track */}
                    <div className="w-full bg-gray-100 dark:bg-gray-700 h-3 rounded-full overflow-hidden">
                        <div
                            className={`h-full transition-all duration-500 ${
                                campaign.status === 'completed'
                                    ? 'bg-purple-600'
                                    : campaign.status === 'running'
                                    ? 'bg-emerald-500'
                                    : 'bg-indigo-500'
                            }`}
                            style={{ width: `${progressPercentage}%` }}
                        />
                    </div>

                    <div className="flex flex-wrap items-center justify-between text-xs text-gray-500 dark:text-gray-400 pt-1">
                        <span>Target: {totalRecipients} recipients</span>
                        <span>Processed: {sentCount + failedCount + optedOutCount} / {totalRecipients}</span>
                    </div>
                </div>

                {/* Key Metrics Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700/60">
                        <div className="text-xs text-gray-500 font-medium">Recipients</div>
                        <div className="mt-1 text-xl font-bold text-gray-900 dark:text-white">{totalRecipients}</div>
                        <div className="text-[11px] text-gray-400 mt-1">Total audience</div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700/60">
                        <div className="text-xs text-indigo-500 font-medium flex items-center gap-1">
                            <Send className="w-3 h-3" />
                            <span>Sent</span>
                        </div>
                        <div className="mt-1 text-xl font-bold text-gray-900 dark:text-white">{sentCount}</div>
                        <div className="text-[11px] text-gray-400 mt-1">Dispatched</div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700/60">
                        <div className="text-xs text-teal-600 font-medium flex items-center gap-1">
                            <Check className="w-3 h-3" />
                            <span>Delivered</span>
                        </div>
                        <div className="mt-1 text-xl font-bold text-gray-900 dark:text-white">{deliveredCount}</div>
                        <div className="text-[11px] text-teal-600 mt-1">{deliveryRate}% delivery rate</div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700/60">
                        <div className="text-xs text-emerald-600 font-medium flex items-center gap-1">
                            <CheckCircle2 className="w-3 h-3" />
                            <span>Read</span>
                        </div>
                        <div className="mt-1 text-xl font-bold text-gray-900 dark:text-white">{readCount}</div>
                        <div className="text-[11px] text-emerald-600 mt-1">{readRate}% open rate</div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700/60">
                        <div className="text-xs text-rose-500 font-medium flex items-center gap-1">
                            <X className="w-3 h-3" />
                            <span>Failed</span>
                        </div>
                        <div className="mt-1 text-xl font-bold text-gray-900 dark:text-white">{failedCount}</div>
                        <div className="text-[11px] text-rose-500 mt-1">API or invalid phone</div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700/60">
                        <div className="text-xs text-red-600 font-medium flex items-center gap-1">
                            <ShieldAlert className="w-3 h-3" />
                            <span>Opted Out</span>
                        </div>
                        <div className="mt-1 text-xl font-bold text-gray-900 dark:text-white">{optedOutCount}</div>
                        <div className="text-[11px] text-red-500 mt-1">Safely skipped</div>
                    </div>
                </div>

                {/* Campaign Configuration Details */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 space-y-3">
                        <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            Audience Segment
                        </h4>
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 rounded-xl">
                                <Users className="w-5 h-5" />
                            </div>
                            <div>
                                <div className="text-sm font-semibold text-gray-900 dark:text-white">
                                    {campaign.audience?.name || 'All Contacts'}
                                </div>
                                <div className="text-xs text-gray-500">
                                    Segment cached count: {campaign.audience?.cached_count ?? 0}
                                </div>
                            </div>
                        </div>
                        {campaign.audience?.description && (
                            <p className="text-xs text-gray-500 border-t border-gray-100 dark:border-gray-700 pt-2">
                                {campaign.audience.description}
                            </p>
                        )}
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 space-y-3">
                        <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            WhatsApp Template / Content
                        </h4>
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 rounded-xl">
                                <MessageSquare className="w-5 h-5" />
                            </div>
                            <div>
                                <div className="text-sm font-semibold text-gray-900 dark:text-white">
                                    {campaign.message_type === 'template'
                                        ? campaign.template?.name || 'Approved Meta Template'
                                        : 'Custom WhatsApp Text'}
                                </div>
                                <div className="text-xs text-gray-500">
                                    Type: <span className="capitalize">{campaign.message_type}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 space-y-3">
                        <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            Timeline & Creator
                        </h4>
                        <div className="text-xs space-y-1.5 text-gray-600 dark:text-gray-300">
                            <div className="flex justify-between">
                                <span className="text-gray-400">Created:</span>
                                <span>{new Date(campaign.created_at).toLocaleString()}</span>
                            </div>
                            {campaign.scheduled_at && (
                                <div className="flex justify-between">
                                    <span className="text-gray-400">Scheduled:</span>
                                    <span>{new Date(campaign.scheduled_at).toLocaleString()}</span>
                                </div>
                            )}
                            {campaign.started_at && (
                                <div className="flex justify-between">
                                    <span className="text-gray-400">Started:</span>
                                    <span>{new Date(campaign.started_at).toLocaleString()}</span>
                                </div>
                            )}
                            {campaign.completed_at && (
                                <div className="flex justify-between">
                                    <span className="text-gray-400">Completed:</span>
                                    <span>{new Date(campaign.completed_at).toLocaleString()}</span>
                                </div>
                            )}
                            <div className="flex justify-between pt-1 border-t border-gray-100 dark:border-gray-700">
                                <span className="text-gray-400">By:</span>
                                <span>{campaign.creator?.name || 'Administrator'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Tabs: Recipients vs Events Audit Log */}
                <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 overflow-hidden">
                    <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 px-6 py-4">
                        <div className="flex items-center gap-4">
                            <button
                                onClick={() => setActiveTab('recipients')}
                                className={`text-sm font-semibold pb-1 flex items-center gap-2 border-b-2 transition ${
                                    activeTab === 'recipients'
                                        ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                                }`}
                            >
                                <Users className="w-4 h-4" />
                                <span>Recipients ({recipients.total || 0})</span>
                            </button>

                            <button
                                onClick={() => setActiveTab('events')}
                                className={`text-sm font-semibold pb-1 flex items-center gap-2 border-b-2 transition ${
                                    activeTab === 'events'
                                        ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                                }`}
                            >
                                <Clock className="w-4 h-4" />
                                <span>Audit Events ({events.length})</span>
                            </button>
                        </div>

                        {activeTab === 'recipients' && (
                            <div className="flex items-center gap-2">
                                <Filter className="w-4 h-4 text-gray-400" />
                                <select
                                    value={recipientStatus}
                                    onChange={(e) => handleFilterStatus(e.target.value)}
                                    className="text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-1.5 text-gray-700 dark:text-gray-200"
                                >
                                    <option value="all">All Recipient Statuses</option>
                                    {recipientStatuses.map((st) => (
                                        <option key={st.value} value={st.value}>
                                            {st.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    {/* Tab 1: Recipients Table */}
                    {activeTab === 'recipients' && (
                        <div>
                            {recipients.data.length === 0 ? (
                                <div className="p-12 text-center text-gray-400 space-y-2">
                                    <Users className="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600" />
                                    <p className="text-sm font-medium">No recipients found</p>
                                    <p className="text-xs text-gray-400">
                                        Recipients are generated when the campaign is launched from its audience segment.
                                    </p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs sm:text-sm">
                                        <thead className="bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 font-semibold border-b border-gray-100 dark:border-gray-700">
                                            <tr>
                                                <th className="py-3 px-6">Contact / Phone</th>
                                                <th className="py-3 px-6">Lead</th>
                                                <th className="py-3 px-6">Status</th>
                                                <th className="py-3 px-6">Sent</th>
                                                <th className="py-3 px-6">Delivered</th>
                                                <th className="py-3 px-6">Read</th>
                                                <th className="py-3 px-6">Notes / Error</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50 text-gray-700 dark:text-gray-300">
                                            {recipients.data.map((r) => (
                                                <tr key={r.id} className="hover:bg-gray-50/50 dark:hover:bg-gray-750">
                                                    <td className="py-3 px-6">
                                                        <div className="font-semibold text-gray-900 dark:text-white">
                                                            {r.contact ? `${r.contact.first_name || ''} ${r.contact.last_name || ''}`.trim() : 'Unknown'}
                                                        </div>
                                                        <div className="text-xs text-gray-400">{r.phone}</div>
                                                    </td>
                                                    <td className="py-3 px-6">
                                                        {r.lead ? (
                                                            <div>
                                                                <span className="font-medium text-gray-800 dark:text-gray-200">
                                                                    {r.lead.title}
                                                                </span>
                                                                <span className="block text-xs text-gray-400 capitalize">
                                                                    {r.lead.status}
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-gray-400">—</span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-6">
                                                        <span
                                                            className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${getRecipientBadge(
                                                                r.status
                                                            )}`}
                                                        >
                                                            {r.status}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-6 text-xs text-gray-500">
                                                        {r.sent_at ? new Date(r.sent_at).toLocaleTimeString() : '—'}
                                                    </td>
                                                    <td className="py-3 px-6 text-xs text-gray-500">
                                                        {r.delivered_at ? new Date(r.delivered_at).toLocaleTimeString() : '—'}
                                                    </td>
                                                    <td className="py-3 px-6 text-xs text-gray-500">
                                                        {r.read_at ? new Date(r.read_at).toLocaleTimeString() : '—'}
                                                    </td>
                                                    <td className="py-3 px-6 text-xs">
                                                        {r.error_message ? (
                                                            <span className="text-rose-600 dark:text-rose-400 font-medium">
                                                                {r.error_message}
                                                            </span>
                                                        ) : r.status === 'opted_out' ? (
                                                            <span className="text-red-500 font-medium">Excluded by opt-out</span>
                                                        ) : (
                                                            <span className="text-gray-400">—</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {/* Pagination links */}
                            {recipients.links && recipients.links.length > 3 && (
                                <div className="p-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-1">
                                    {recipients.links.map((link, idx) => (
                                        <button
                                            key={idx}
                                            disabled={!link.url || link.active}
                                            onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                                            className={`px-3 py-1.5 text-xs rounded-lg font-medium transition ${
                                                link.active
                                                    ? 'bg-emerald-600 text-white'
                                                    : link.url
                                                    ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100'
                                                    : 'text-gray-300 dark:text-gray-600 cursor-not-allowed'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Tab 2: Events Audit Log */}
                    {activeTab === 'events' && (
                        <div className="p-6">
                            {events.length === 0 ? (
                                <div className="text-center py-8 text-gray-400 text-sm">
                                    No audit events recorded yet.
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {events.map((evt) => (
                                        <div
                                            key={evt.id}
                                            className="flex items-start gap-4 p-3.5 bg-gray-50 dark:bg-gray-900/60 rounded-xl border border-gray-100 dark:border-gray-800 text-xs sm:text-sm"
                                        >
                                            <div className="p-2 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 rounded-lg flex-shrink-0">
                                                <Clock className="w-4 h-4" />
                                            </div>
                                            <div className="flex-1">
                                                <div className="flex items-center justify-between">
                                                    <span className="font-semibold text-gray-900 dark:text-white capitalize">
                                                        {evt.event_type.replace('_', ' ')}
                                                    </span>
                                                    <span className="text-xs text-gray-400">
                                                        {new Date(evt.created_at).toLocaleString()}
                                                    </span>
                                                </div>
                                                <p className="text-gray-600 dark:text-gray-300 text-xs mt-0.5">
                                                    {evt.description}
                                                </p>
                                                {evt.metadata && Object.keys(evt.metadata).length > 0 && (
                                                    <pre className="mt-2 p-2 bg-white dark:bg-gray-800 rounded text-[11px] text-gray-500 overflow-x-auto">
                                                        {JSON.stringify(evt.metadata, null, 2)}
                                                    </pre>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
