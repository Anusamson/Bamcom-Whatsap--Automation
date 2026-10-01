import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    Mail, 
    Send, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    Clock, 
    ShieldAlert, 
    Search, 
    FileText, 
    ExternalLink, 
    RefreshCw,
    SlidersHorizontal,
    PlusCircle,
    Eye
} from 'lucide-react';

export default function Index({ auth, messages, stats, filters, statuses, types }) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || '');
    const [isComposeOpen, setIsComposeOpen] = useState(false);
    const [isTestEmailOpen, setIsTestEmailOpen] = useState(false);

    // Form for Sending Live Outbound Email
    const { data: composeData, setData: setComposeData, post: postCompose, processing: composeProcessing, errors: composeErrors, reset: resetCompose } = useForm({
        to_email: '',
        to_name: '',
        subject: '',
        body_html: '',
        type: 'transactional',
    });

    // Form for Quick Test Email
    const { data: testData, setData: setTestData, post: postTest, processing: testProcessing, errors: testErrors, reset: resetTest } = useForm({
        to_email: '',
        subject: '[Test Email] Amazon SES Health Verification',
    });

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('emails.index'), {
            search: search || undefined,
            status: statusFilter || undefined,
            type: typeFilter || undefined,
        }, { preserveState: true, replace: true });
    };

    const handleComposeSubmit = (e) => {
        e.preventDefault();
        postCompose(route('emails.store'), {
            onSuccess: () => {
                setIsComposeOpen(false);
                resetCompose();
            }
        });
    };

    const handleTestSubmit = (e) => {
        e.preventDefault();
        postTest(route('emails.test'), {
            onSuccess: () => {
                setIsTestEmailOpen(false);
                resetTest();
            }
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Mail className="h-6 w-6 text-sky-600" />
                            Email Infrastructure & Amazon SES
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Enterprise transactional & marketing delivery, bounce protection, and SES v2 event tracking
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
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
                        <button
                            type="button"
                            onClick={() => setIsTestEmailOpen(true)}
                            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition shadow-sm"
                        >
                            <Send className="h-4 w-4" />
                            Send Test
                        </button>
                        <button
                            type="button"
                            onClick={() => setIsComposeOpen(true)}
                            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                        >
                            <PlusCircle className="h-4 w-4" />
                            Compose
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Email Infrastructure & Amazon SES" />

            {/* Metrics Ribbon */}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                        <span className="text-xs font-medium uppercase tracking-wider">Total Sent</span>
                        <Send className="h-4 w-4 text-sky-500" />
                    </div>
                    <div className="text-2xl font-bold text-slate-900 dark:text-white">{stats.total_sent?.toLocaleString() ?? 0}</div>
                    <div className="text-[11px] text-slate-400 mt-1">Through Amazon SES</div>
                </div>

                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                        <span className="text-xs font-medium uppercase tracking-wider">Delivered</span>
                        <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                    </div>
                    <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{stats.total_delivered?.toLocaleString() ?? 0}</div>
                    <div className="text-[11px] text-slate-400 mt-1">Confirmed receipts</div>
                </div>

                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                        <span className="text-xs font-medium uppercase tracking-wider">Deliverability</span>
                        <CheckCircle2 className="h-4 w-4 text-indigo-500" />
                    </div>
                    <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{stats.deliverability_rate}%</div>
                    <div className="text-[11px] text-slate-400 mt-1">SES acceptance rate</div>
                </div>

                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                        <span className="text-xs font-medium uppercase tracking-wider">Bounces</span>
                        <AlertTriangle className="h-4 w-4 text-rose-500" />
                    </div>
                    <div className="text-2xl font-bold text-rose-600 dark:text-rose-400">{stats.total_bounced?.toLocaleString() ?? 0}</div>
                    <div className="text-[11px] text-slate-400 mt-1">Bounce rate: {stats.bounce_rate}%</div>
                </div>

                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                        <span className="text-xs font-medium uppercase tracking-wider">Complaints</span>
                        <XCircle className="h-4 w-4 text-orange-500" />
                    </div>
                    <div className="text-2xl font-bold text-orange-600 dark:text-orange-400">{stats.total_complained?.toLocaleString() ?? 0}</div>
                    <div className="text-[11px] text-slate-400 mt-1">Spam reports</div>
                </div>

                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                        <span className="text-xs font-medium uppercase tracking-wider">Suppressed</span>
                        <ShieldAlert className="h-4 w-4 text-slate-500" />
                    </div>
                    <div className="text-2xl font-bold text-slate-900 dark:text-white">{stats.active_suppressions?.toLocaleString() ?? 0}</div>
                    <div className="text-[11px] text-slate-400 mt-1">Protected ledger</div>
                </div>
            </div>

            {/* Filter Bar */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 mb-6 shadow-sm">
                <form onSubmit={handleFilter} className="flex flex-col sm:flex-row items-center gap-3">
                    <div className="relative flex-1 w-full">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Search by recipient email, subject, or SES message ID..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500"
                        />
                    </div>
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="text-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 py-2 px-3 focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">All Statuses</option>
                            {statuses?.map((st) => (
                                <option key={st.value} value={st.value}>{st.label}</option>
                            ))}
                        </select>
                        <select
                            value={typeFilter}
                            onChange={(e) => setTypeFilter(e.target.value)}
                            className="text-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 py-2 px-3 focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">All Types</option>
                            {types?.map((tp) => (
                                <option key={tp.value} value={tp.value}>{tp.label}</option>
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

            {/* Outbound Email Ledger Table */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead className="bg-slate-50 dark:bg-slate-800/60 text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th className="px-5 py-3">Recipient</th>
                                <th className="px-5 py-3">Subject</th>
                                <th className="px-5 py-3">Type</th>
                                <th className="px-5 py-3">Status</th>
                                <th className="px-5 py-3">Sent / Queued</th>
                                <th className="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                            {messages.data?.length > 0 ? (
                                messages.data.map((msg) => (
                                    <tr key={msg.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                        <td className="px-5 py-3">
                                            <div className="font-semibold text-slate-900 dark:text-white">
                                                {msg.to_name || msg.to_email}
                                            </div>
                                            <div className="text-xs text-slate-400">{msg.to_email}</div>
                                            {msg.contact && (
                                                <Link 
                                                    href={route('contacts.show', msg.contact.id)}
                                                    className="inline-flex items-center gap-1 text-[11px] text-sky-600 dark:text-sky-400 hover:underline mt-0.5"
                                                >
                                                    Contact 360 Profile <ExternalLink className="h-2.5 w-2.5" />
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 max-w-xs">
                                            <div className="font-medium text-slate-900 dark:text-slate-200 truncate">
                                                {msg.subject}
                                            </div>
                                            {msg.provider_message_id && (
                                                <div className="text-[11px] text-slate-400 font-mono truncate">
                                                    SES: {msg.provider_message_id}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-5 py-3">
                                            <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                {msg.type}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3">
                                            <span className={`inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full ${
                                                msg.status === 'delivered' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' :
                                                msg.status === 'sent' ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300' :
                                                msg.status === 'bounced' ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' :
                                                msg.status === 'complained' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300' :
                                                msg.status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' :
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                            }`}>
                                                {msg.status}
                                            </span>
                                            {msg.error_message && (
                                                <div className="text-[11px] text-rose-500 dark:text-rose-400 truncate max-w-xs mt-0.5">
                                                    {msg.error_message}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                            <div>{msg.sent_at ? new Date(msg.sent_at).toLocaleString() : 'Pending Queue'}</div>
                                            <div className="text-[11px] text-slate-400">Created: {new Date(msg.created_at).toLocaleDateString()}</div>
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            <Link
                                                href={route('emails.show', msg.id)}
                                                className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                            >
                                                <Eye className="h-3.5 w-3.5" />
                                                Audit Trail
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="6" className="text-center py-10 text-slate-400">
                                        No outbound emails found matching your filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {messages.links && (
                    <div className="px-5 py-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                        <div>Showing {messages.from ?? 0} to {messages.to ?? 0} of {messages.total} messages</div>
                        <div className="flex gap-1">
                            {messages.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`px-3 py-1 rounded-md text-xs font-medium ${
                                        link.active ? 'bg-sky-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Compose Outbound Email Modal */}
            {isComposeOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl">
                        <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40">
                            <h3 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                <Send className="h-5 w-5 text-sky-600" />
                                Compose Outbound Email
                            </h3>
                            <button onClick={() => setIsComposeOpen(false)} className="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                        </div>
                        <form onSubmit={handleComposeSubmit} className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Recipient Email *</label>
                                    <input
                                        type="email"
                                        required
                                        value={composeData.to_email}
                                        onChange={(e) => setComposeData('to_email', e.target.value)}
                                        className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                        placeholder="client@example.com"
                                    />
                                    {composeErrors.to_email && <div className="text-xs text-rose-500 mt-1">{composeErrors.to_email}</div>}
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Recipient Name</label>
                                    <input
                                        type="text"
                                        value={composeData.to_name}
                                        onChange={(e) => setComposeData('to_name', e.target.value)}
                                        className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                        placeholder="Adewale Okonkwo"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div className="col-span-2">
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Subject *</label>
                                    <input
                                        type="text"
                                        required
                                        value={composeData.subject}
                                        onChange={(e) => setComposeData('subject', e.target.value)}
                                        className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                        placeholder="Regarding your enquiry on Lekki Phase 1"
                                    />
                                    {composeErrors.subject && <div className="text-xs text-rose-500 mt-1">{composeErrors.subject}</div>}
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Classification *</label>
                                    <select
                                        value={composeData.type}
                                        onChange={(e) => setComposeData('type', e.target.value)}
                                        className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                    >
                                        <option value="transactional">Transactional</option>
                                        <option value="marketing">Marketing (Consented)</option>
                                        <option value="system">System Notice</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">HTML Message Content *</label>
                                <textarea
                                    rows="6"
                                    required
                                    value={composeData.body_html}
                                    onChange={(e) => setComposeData('body_html', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500 font-mono text-xs"
                                    placeholder="<p>Dear Client,</p><p>We are pleased to inform you that your site inspection has been confirmed...</p>"
                                />
                                {composeErrors.body_html && <div className="text-xs text-rose-500 mt-1">{composeErrors.body_html}</div>}
                                <div className="text-[11px] text-slate-400 mt-1">Plain-text fallback will be automatically extracted and bundled for multipart/alternative MIME.</div>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsComposeOpen(false)}
                                    className="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={composeProcessing}
                                    className="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm disabled:opacity-50"
                                >
                                    <Send className="h-4 w-4" />
                                    {composeProcessing ? 'Queuing...' : 'Queue Outbound Email'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Quick Amazon SES Test Modal */}
            {isTestEmailOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                        <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40">
                            <h3 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                <Send className="h-5 w-5 text-amber-600" />
                                Amazon SES Connection Test
                            </h3>
                            <button onClick={() => setIsTestEmailOpen(false)} className="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                        </div>
                        <form onSubmit={handleTestSubmit} className="p-6 space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Destination Test Email *</label>
                                <input
                                    type="email"
                                    required
                                    value={testData.to_email}
                                    onChange={(e) => setTestData('to_email', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-amber-500"
                                    placeholder="your-email@company.com"
                                />
                                {testErrors.to_email && <div className="text-xs text-rose-500 mt-1">{testErrors.to_email}</div>}
                                <p className="text-[11px] text-slate-400 mt-1">Dispatches an authentic SES verification payload through the queued pipeline.</p>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsTestEmailOpen(false)}
                                    className="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={testProcessing}
                                    className="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition shadow-sm disabled:opacity-50"
                                >
                                    <Send className="h-4 w-4" />
                                    {testProcessing ? 'Dispatching...' : 'Dispatch Test Email'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
