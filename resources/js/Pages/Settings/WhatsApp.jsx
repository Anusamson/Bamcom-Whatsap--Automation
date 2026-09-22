import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { 
    MessageSquare, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    RefreshCw, 
    Copy, 
    Check, 
    ExternalLink, 
    ShieldCheck, 
    Key, 
    Server, 
    Radio, 
    FileText, 
    Eye, 
    Send,
    Building2,
    Clock,
    Lock
} from 'lucide-react';
import { useState } from 'react';

export default function WhatsAppSettings({ settings, accounts, templates, webhookStats }) {
    const { flash } = usePage().props;
    const [testingConnection, setTestingConnection] = useState(false);
    const [syncingTemplates, setSyncingTemplates] = useState(false);
    const [copiedWebhookUrl, setCopiedWebhookUrl] = useState(false);
    const [previewTemplate, setPreviewTemplate] = useState(null);

    const handleTestConnection = () => {
        setTestingConnection(true);
        router.post(route('whatsapp.settings.test-connection'), {}, {
            preserveScroll: true,
            onFinish: () => setTestingConnection(false),
        });
    };

    const handleSyncTemplates = () => {
        setSyncingTemplates(true);
        router.post(route('whatsapp.settings.sync-templates'), {}, {
            preserveScroll: true,
            onFinish: () => setSyncingTemplates(false),
        });
    };

    const handleCopyWebhookUrl = () => {
        navigator.clipboard.writeText(settings.webhook_url);
        setCopiedWebhookUrl(true);
        setTimeout(() => setCopiedWebhookUrl(false), 2500);
    };

    const getQualityBadge = (rating) => {
        const r = (rating || '').toUpperCase();
        if (r === 'GREEN') {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Green (High Quality)
                </span>
            );
        }
        if (r === 'YELLOW') {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                    Yellow (Medium Quality)
                </span>
            );
        }
        if (r === 'RED') {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                    Red (Low Quality / At Risk)
                </span>
            );
        }
        return (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                Unknown
            </span>
        );
    };

    const getTemplateStatusBadge = (status) => {
        const s = (status || '').toUpperCase();
        if (s === 'APPROVED') {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                    <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                    Approved
                </span>
            );
        }
        if (s === 'PENDING') {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                    <Clock className="w-3 h-3 text-amber-600" />
                    In Review
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                <XCircle className="w-3 h-3 text-rose-600" />
                {status}
            </span>
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-xl bg-emerald-600 text-white shadow-sm">
                                <MessageSquare className="h-6 w-6" />
                            </div>
                            <div>
                                <h2 className="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    <span>WhatsApp Business Platform</span>
                                    <span className="text-xs font-mono font-normal px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-md">
                                        Cloud API {settings.api_version}
                                    </span>
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Official Meta WhatsApp Business Cloud API connection, webhook configuration, and template directory.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleTestConnection}
                            disabled={testingConnection || !settings.is_configured}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-sm transition"
                        >
                            <RefreshCw className={`w-3.5 h-3.5 ${testingConnection ? 'animate-spin' : ''}`} />
                            <span>{testingConnection ? 'Testing Connection...' : 'Test Connection'}</span>
                        </button>

                        <button
                            type="button"
                            onClick={handleSyncTemplates}
                            disabled={syncingTemplates || !settings.is_configured}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-slate-200 dark:text-slate-900 text-white text-xs font-bold rounded-xl shadow-sm transition"
                        >
                            <RefreshCw className={`w-3.5 h-3.5 ${syncingTemplates ? 'animate-spin' : ''}`} />
                            <span>{syncingTemplates ? 'Syncing...' : 'Sync Templates'}</span>
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="WhatsApp Platform Settings - Bamcom AI CRM" />

            <div className="space-y-6">
                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-300 text-sm flex items-center gap-2">
                        <AlertTriangle className="h-4 w-4 text-rose-600 flex-shrink-0" />
                        <span>{flash.error}</span>
                    </div>
                )}

                {/* Status Hero Overview */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-4">
                        <div className={`p-3 rounded-xl ${settings.is_configured ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400'}`}>
                            <Radio className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs font-medium text-slate-400 uppercase tracking-wider">Cloud API State</span>
                            <div className="flex items-center gap-2 mt-0.5">
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    {settings.is_configured ? 'Configured & Ready' : 'Incomplete Setup'}
                                </h3>
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">
                                {settings.is_configured ? 'Environment credentials active' : 'Missing token or phone ID'}
                            </p>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-4">
                        <div className="p-3 rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400">
                            <Server className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs font-medium text-slate-400 uppercase tracking-wider">Active Phone Lines</span>
                            <h3 className="text-lg font-black text-slate-900 dark:text-white mt-0.5">
                                {accounts.length} {accounts.length === 1 ? 'Line' : 'Lines'}
                            </h3>
                            <p className="text-[11px] text-slate-500 mt-0.5">
                                {accounts.filter(a => a.status === 'connected').length} connected to Meta
                            </p>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-4">
                        <div className="p-3 rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400">
                            <FileText className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs font-medium text-slate-400 uppercase tracking-wider">Meta Templates</span>
                            <h3 className="text-lg font-black text-slate-900 dark:text-white mt-0.5">
                                {templates.length} Pre-Approved
                            </h3>
                            <p className="text-[11px] text-slate-500 mt-0.5">
                                {templates.filter(t => t.status === 'APPROVED').length} active for campaigns
                            </p>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-4">
                        <div className="p-3 rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-400">
                            <ShieldCheck className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs font-medium text-slate-400 uppercase tracking-wider">Webhook Ingestion</span>
                            <h3 className="text-lg font-black text-slate-900 dark:text-white mt-0.5">
                                {webhookStats.total} Events Received
                            </h3>
                            <p className="text-[11px] text-slate-500 mt-0.5">
                                {webhookStats.processed} processed, {webhookStats.pending} queued
                            </p>
                        </div>
                    </div>
                </div>

                {/* 2-Column: Credentials & Webhook Guide */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Credentials Card (Masked Secrets) */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div className="flex items-center gap-2">
                                <Key className="w-5 h-5 text-indigo-600" />
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Meta Cloud API Credentials
                                </h3>
                            </div>
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                                <Lock className="w-3 h-3" />
                                Environment Secured
                            </span>
                        </div>

                        <div className="space-y-3.5 text-xs">
                            <div>
                                <label className="block font-medium text-slate-500 dark:text-slate-400 mb-1">
                                    System Access Token (WHATSAPP_ACCESS_TOKEN)
                                </label>
                                <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 font-mono text-slate-700 dark:text-slate-300 flex items-center justify-between">
                                    <span>{settings.masked_access_token}</span>
                                    {settings.has_access_token ? (
                                        <CheckCircle2 className="w-4 h-4 text-emerald-500 flex-shrink-0" />
                                    ) : (
                                        <XCircle className="w-4 h-4 text-rose-500 flex-shrink-0" />
                                    )}
                                </div>
                                <p className="text-[11px] text-slate-400 mt-1">
                                    Secret tokens are never transmitted to client browsers and are read securely from the server environment.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="block font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Phone Number ID
                                    </label>
                                    <div className="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 font-mono text-slate-800 dark:text-slate-200">
                                        {settings.phone_number_id || 'Not set'}
                                    </div>
                                </div>
                                <div>
                                    <label className="block font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        WABA Account ID
                                    </label>
                                    <div className="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 font-mono text-slate-800 dark:text-slate-200">
                                        {settings.business_account_id || 'Not set'}
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="block font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        API Target Version
                                    </label>
                                    <div className="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 font-mono text-slate-800 dark:text-slate-200">
                                        {settings.api_version}
                                    </div>
                                </div>
                                <div>
                                    <label className="block font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Meta App Secret & Signature
                                    </label>
                                    <div className="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                        <span>HMAC-SHA256 Auth</span>
                                        {settings.has_app_secret ? (
                                            <span className="text-emerald-600 font-bold text-[11px]">Configured</span>
                                        ) : (
                                            <span className="text-slate-400 text-[11px]">Optional</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Webhook Configuration Card */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div className="flex items-center gap-2">
                                <Radio className="w-5 h-5 text-emerald-600" />
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Meta Webhook Configuration
                                </h3>
                            </div>
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                Realtime Sync
                            </span>
                        </div>

                        <div className="space-y-3.5 text-xs">
                            <div>
                                <label className="block font-medium text-slate-500 dark:text-slate-400 mb-1">
                                    Callback URL (Webhook Endpoint)
                                </label>
                                <div className="flex items-center gap-2">
                                    <div className="flex-1 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 font-mono text-[11px] text-slate-800 dark:text-slate-200 overflow-x-auto whitespace-nowrap">
                                        {settings.webhook_url}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleCopyWebhookUrl}
                                        className="inline-flex items-center gap-1.5 px-3 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold rounded-xl transition flex-shrink-0"
                                        title="Copy Webhook URL"
                                    >
                                        {copiedWebhookUrl ? <Check className="w-4 h-4 text-emerald-500" /> : <Copy className="w-4 h-4" />}
                                        <span>{copiedWebhookUrl ? 'Copied!' : 'Copy'}</span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="block font-medium text-slate-500 dark:text-slate-400 mb-1">
                                    Verify Token (WHATSAPP_VERIFY_TOKEN)
                                </label>
                                <div className="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                    <span className="font-mono text-slate-700 dark:text-slate-300">
                                        {settings.has_verify_token ? '•••••••••••••••• (Verified in .env)' : 'Missing WHATSAPP_VERIFY_TOKEN'}
                                    </span>
                                    {settings.has_verify_token ? (
                                        <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                                    ) : (
                                        <XCircle className="w-4 h-4 text-rose-500" />
                                    )}
                                </div>
                            </div>

                            <div className="p-3.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/60 dark:border-blue-900/40 text-[11px] text-blue-900 dark:text-blue-200 space-y-1.5">
                                <p className="font-bold flex items-center gap-1.5">
                                    <ExternalLink className="w-3.5 h-3.5 text-blue-600" />
                                    Setup in Meta App Dashboard:
                                </p>
                                <ol className="list-decimal list-inside space-y-1 text-slate-600 dark:text-slate-400 text-[11px]">
                                    <li>Go to <strong>Meta Developer Portal &gt; Your App &gt; WhatsApp &gt; Configuration</strong></li>
                                    <li>Click <strong>Edit</strong> on Webhook and paste the Callback URL above</li>
                                    <li>Enter your configured <code>WHATSAPP_VERIFY_TOKEN</code></li>
                                    <li>Click <strong>Verify and Save</strong>, then subscribe to the <code>messages</code> field</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Connected WhatsApp Accounts */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <div className="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Building2 className="w-5 h-5 text-emerald-600" />
                                <span>Connected WhatsApp Phone Lines ({accounts.length})</span>
                            </h3>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Official Meta WhatsApp Business Account phone numbers available for customer communications.
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 dark:bg-slate-950/60 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className="py-3 px-4">Line Name / Label</th>
                                    <th className="py-3 px-4">Display Phone</th>
                                    <th className="py-3 px-4">Phone Number ID</th>
                                    <th className="py-3 px-4">Quality Rating</th>
                                    <th className="py-3 px-4">Status</th>
                                    <th className="py-3 px-4">Last Synced</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                                {accounts.map((acc) => (
                                    <tr key={acc.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                        <td className="py-3.5 px-4">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-slate-900 dark:text-white">{acc.name}</span>
                                                {acc.is_default && (
                                                    <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                                                        Primary Line
                                                    </span>
                                                )}
                                            </div>
                                            {acc.verified_name && (
                                                <p className="text-[11px] text-slate-400 mt-0.5">Verified: {acc.verified_name}</p>
                                            )}
                                        </td>
                                        <td className="py-3.5 px-4 font-mono font-semibold text-slate-800 dark:text-slate-200">
                                            {acc.display_phone_number || '—'}
                                        </td>
                                        <td className="py-3.5 px-4 font-mono text-slate-500">
                                            {acc.phone_number_id}
                                        </td>
                                        <td className="py-3.5 px-4">
                                            {getQualityBadge(acc.quality_rating)}
                                        </td>
                                        <td className="py-3.5 px-4">
                                            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Connected
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-slate-500">
                                            {acc.last_synced_at ? new Date(acc.last_synced_at).toLocaleString() : 'Never'}
                                        </td>
                                    </tr>
                                ))}
                                {accounts.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-slate-400">
                                            No WhatsApp phone accounts registered yet. Run "Test Connection" to link the default configured number.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pre-Approved WhatsApp Message Templates */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <div className="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <FileText className="w-5 h-5 text-indigo-600" />
                                <span>Pre-Approved Message Templates ({templates.length})</span>
                            </h3>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Meta-approved templates for initiating customer conversations and sending inspection updates.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={handleSyncTemplates}
                            disabled={syncingTemplates}
                            className="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl transition"
                        >
                            <RefreshCw className={`w-3.5 h-3.5 ${syncingTemplates ? 'animate-spin' : ''}`} />
                            <span>Sync from Meta</span>
                        </button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 dark:bg-slate-950/60 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className="py-3 px-4">Template Name</th>
                                    <th className="py-3 px-4">Category</th>
                                    <th className="py-3 px-4">Language</th>
                                    <th className="py-3 px-4">Status</th>
                                    <th className="py-3 px-4">Body Preview</th>
                                    <th className="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                                {templates.map((tpl) => (
                                    <tr key={tpl.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                        <td className="py-3.5 px-4">
                                            <span className="font-mono font-bold text-slate-900 dark:text-white">
                                                {tpl.name}
                                            </span>
                                            {tpl.header_content && (
                                                <p className="text-[11px] text-slate-400 mt-0.5">Header: {tpl.header_content}</p>
                                            )}
                                        </td>
                                        <td className="py-3.5 px-4">
                                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 uppercase">
                                                {tpl.category}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-400">
                                            {tpl.language}
                                        </td>
                                        <td className="py-3.5 px-4">
                                            {getTemplateStatusBadge(tpl.status)}
                                        </td>
                                        <td className="py-3.5 px-4 max-w-xs text-slate-600 dark:text-slate-300 truncate font-sans">
                                            {tpl.body_text}
                                        </td>
                                        <td className="py-3.5 px-4 text-right">
                                            <button
                                                type="button"
                                                onClick={() => setPreviewTemplate(tpl)}
                                                className="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 hover:underline"
                                            >
                                                <Eye className="w-3.5 h-3.5" />
                                                <span>Preview</span>
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                                {templates.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-slate-400">
                                            No templates synced yet. Click "Sync Templates" to retrieve approved templates from your Meta WhatsApp Business Account.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Template Preview Modal */}
            {previewTemplate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full overflow-hidden">
                        <div className="p-4 bg-emerald-600 text-white flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <MessageSquare className="w-5 h-5" />
                                <h4 className="font-bold text-sm">WhatsApp Template Preview</h4>
                            </div>
                            <button
                                type="button"
                                onClick={() => setPreviewTemplate(null)}
                                className="text-emerald-100 hover:text-white p-1 rounded-lg transition"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="p-6 space-y-4">
                            <div>
                                <span className="font-mono text-xs font-bold text-slate-900 dark:text-white">
                                    {previewTemplate.name}
                                </span>
                                <div className="flex items-center gap-2 mt-1">
                                    <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        {previewTemplate.category}
                                    </span>
                                    <span className="font-mono text-[10px] text-slate-400">
                                        {previewTemplate.language}
                                    </span>
                                    {getTemplateStatusBadge(previewTemplate.status)}
                                </div>
                            </div>

                            {/* Simulated WhatsApp Chat Bubble */}
                            <div className="p-4 rounded-2xl rounded-tl-none bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-slate-800 dark:text-slate-100 text-xs shadow-sm space-y-2">
                                {previewTemplate.header_content && (
                                    <p className="font-bold text-emerald-900 dark:text-emerald-200 text-sm">
                                        {previewTemplate.header_content}
                                    </p>
                                )}
                                <p className="leading-relaxed whitespace-pre-wrap">
                                    {previewTemplate.body_text}
                                </p>
                                {previewTemplate.footer_text && (
                                    <p className="text-[10px] text-slate-500 dark:text-slate-400 pt-1 border-t border-emerald-200/50 dark:border-emerald-800/50">
                                        {previewTemplate.footer_text}
                                    </p>
                                )}
                            </div>

                            {/* Interactive Buttons Preview */}
                            {previewTemplate.buttons && previewTemplate.buttons.length > 0 && (
                                <div className="space-y-1.5 pt-1">
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                        Template Action Buttons:
                                    </span>
                                    <div className="space-y-1">
                                        {previewTemplate.buttons.map((btn, idx) => (
                                            <div
                                                key={idx}
                                                className="py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-center font-semibold text-xs text-blue-600 dark:text-blue-400"
                                            >
                                                {btn.text}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="pt-2 flex justify-end">
                                <button
                                    type="button"
                                    onClick={() => setPreviewTemplate(null)}
                                    className="px-4 py-2 bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 text-white dark:text-slate-900 text-xs font-bold rounded-xl transition"
                                >
                                    Close Preview
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
