import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    Mail, 
    ArrowLeft, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    Clock, 
    User, 
    FileText, 
    ExternalLink,
    ShieldAlert,
    Activity
} from 'lucide-react';

export default function Show({ auth, emailMessage }) {
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
                                <Mail className="h-5 w-5 text-sky-600" />
                                Outbound Email Audit Trail
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                                UUID: {emailMessage.uuid}
                            </p>
                        </div>
                    </div>
                    <span className={`inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full ${
                        emailMessage.status === 'delivered' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' :
                        emailMessage.status === 'sent' ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300' :
                        emailMessage.status === 'bounced' ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' :
                        emailMessage.status === 'complained' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300' :
                        emailMessage.status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' :
                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                    }`}>
                        {emailMessage.status.toUpperCase()}
                    </span>
                </div>
            }
        >
            <Head title={`Email: ${emailMessage.subject}`} />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Email Metadata Card */}
                <div className="lg:col-span-1 space-y-6">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
                        <h3 className="text-sm font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
                            Message Delivery Details
                        </h3>

                        <div>
                            <span className="text-xs text-slate-400 block">Recipient</span>
                            <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">{emailMessage.to_name || emailMessage.to_email}</div>
                            <div className="text-xs text-slate-500">{emailMessage.to_email}</div>
                            {emailMessage.contact && (
                                <Link 
                                    href={route('contacts.show', emailMessage.contact.id)}
                                    className="inline-flex items-center gap-1 text-xs text-sky-600 dark:text-sky-400 hover:underline mt-1"
                                >
                                    View Contact in CRM <ExternalLink className="h-3 w-3" />
                                </Link>
                            )}
                        </div>

                        <div>
                            <span className="text-xs text-slate-400 block">From Sender</span>
                            <div className="text-sm text-slate-800 dark:text-slate-200">{emailMessage.from_name}</div>
                            <div className="text-xs text-slate-500">{emailMessage.from_email}</div>
                        </div>

                        <div>
                            <span className="text-xs text-slate-400 block">Amazon SES Message ID</span>
                            <div className="text-xs font-mono text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 p-2 rounded-lg break-all">
                                {emailMessage.provider_message_id || 'Not Assigned Yet'}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span className="text-slate-400 block">Classification</span>
                                <span className="font-medium text-slate-700 dark:text-slate-300 capitalize">{emailMessage.type}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block">Dispatched At</span>
                                <span className="font-medium text-slate-700 dark:text-slate-300">
                                    {emailMessage.sent_at ? new Date(emailMessage.sent_at).toLocaleTimeString() : 'Queued'}
                                </span>
                            </div>
                        </div>

                        {emailMessage.error_message && (
                            <div className="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 rounded-lg p-3 text-xs text-rose-700 dark:text-rose-300">
                                <span className="font-bold block mb-1">Provider Error:</span>
                                {emailMessage.error_message}
                            </div>
                        )}
                    </div>

                    {/* Timeline of Delivery Events */}
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
                        <h3 className="text-sm font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-1.5">
                            <Activity className="h-4 w-4 text-sky-600" />
                            SES Lifecycle Timeline
                        </h3>

                        <div className="space-y-3">
                            {emailMessage.events?.length > 0 ? (
                                emailMessage.events.map((evt, idx) => (
                                    <div key={idx} className="flex items-start gap-3 text-xs">
                                        <div className="mt-0.5">
                                            {evt.event_type === 'delivery' ? (
                                                <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                                            ) : evt.event_type === 'bounce' ? (
                                                <AlertTriangle className="h-4 w-4 text-rose-500" />
                                            ) : evt.event_type === 'complaint' ? (
                                                <ShieldAlert className="h-4 w-4 text-orange-500" />
                                            ) : evt.event_type === 'failure' ? (
                                                <XCircle className="h-4 w-4 text-red-500" />
                                            ) : (
                                                <Clock className="h-4 w-4 text-sky-500" />
                                            )}
                                        </div>
                                        <div className="flex-1">
                                            <div className="font-semibold text-slate-800 dark:text-slate-200 capitalize">
                                                {evt.event_type}
                                            </div>
                                            <div className="text-[11px] text-slate-400">
                                                {new Date(evt.occurred_at).toLocaleString()}
                                            </div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-xs text-slate-400">No events recorded yet.</div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Email Body Preview */}
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                        <div className="border-b border-slate-200 dark:border-slate-800 pb-4 mb-4">
                            <h1 className="text-lg font-bold text-slate-900 dark:text-white">
                                {emailMessage.subject}
                            </h1>
                            <div className="text-xs text-slate-400 mt-1">
                                Delivered to {emailMessage.to_name ? `${emailMessage.to_name} <${emailMessage.to_email}>` : emailMessage.to_email}
                            </div>
                        </div>

                        {/* Rendered HTML Container */}
                        <div className="mb-6">
                            <h4 className="text-xs font-semibold uppercase text-slate-400 tracking-wider mb-2">HTML Message Body</h4>
                            <div 
                                className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6 min-h-[240px] text-slate-800 dark:text-slate-100 prose dark:prose-invert max-w-none"
                                dangerouslySetInnerHTML={{ __html: emailMessage.body_html }}
                            />
                        </div>

                        {/* Plain Text Fallback Container */}
                        <div>
                            <h4 className="text-xs font-semibold uppercase text-slate-400 tracking-wider mb-2">Plain-Text Multipart Fallback</h4>
                            <pre className="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-4 font-mono text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                                {emailMessage.body_plain || 'No plain text fallback generated.'}
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
