import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    Send, 
    ArrowLeft, 
    FileText, 
    Users, 
    Calendar, 
    AlertTriangle, 
    Smartphone, 
    Monitor, 
    Save
} from 'lucide-react';

export default function Edit({ auth, campaign, templates, smartLists, sampleVariables }) {
    const { data, setData, put, processing, errors } = useForm({
        name: campaign.name || '',
        description: campaign.description || '',
        email_template_id: campaign.email_template_id || '',
        subject: campaign.subject || '',
        preheader: campaign.preheader || '',
        body_html: campaign.body_html || '',
        body_plain: campaign.body_plain || '',
        smart_list_id: campaign.smart_list_id || '',
        scheduled_at: campaign.scheduled_at ? campaign.scheduled_at.substring(0, 16) : '',
        batch_size: campaign.batch_size || 50,
    });

    const [previewMode, setPreviewMode] = useState('desktop');

    const handleTemplateChange = (e) => {
        const templateId = e.target.value;
        setData('email_template_id', templateId);

        if (templateId) {
            const template = templates.find(t => String(t.id) === String(templateId));
            if (template) {
                setData(prev => ({
                    ...prev,
                    email_template_id: template.id,
                    subject: template.subject || prev.subject,
                    preheader: template.preheader || prev.preheader,
                    body_html: template.body_html || prev.body_html,
                    body_plain: template.body_plain || prev.body_plain,
                }));
            }
        }
    };

    const insertVariable = (token) => {
        setData('body_html', data.body_html + ` ${token} `);
    };

    const hasUnsubscribe = Boolean(
        data.body_html && (
            data.body_html.includes('{{ unsubscribe_url }}') ||
            data.body_html.includes('{{unsubscribe_url}}') ||
            data.body_html.toLowerCase().includes('unsubscribe')
        )
    );

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('email-campaigns.update', campaign.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Link
                                href={route('email-campaigns.show', campaign.id)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                            <span className="text-xs text-slate-500 font-medium">Email Campaigns / Edit</span>
                        </div>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <FileText className="h-6 w-6 text-sky-600" />
                            Edit Campaign: {campaign.name}
                        </h2>
                    </div>
                    <button
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing || !hasUnsubscribe}
                        className="px-5 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white flex items-center gap-1.5 transition shadow-sm disabled:opacity-50"
                    >
                        <Save className="h-4 w-4" />
                        Save Changes
                    </button>
                </div>
            }
        >
            <Head title={`Edit Campaign: ${campaign.name}`} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* Left Form */}
                    <div className="lg:col-span-6 bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Campaign Name *
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                            {errors.name && <p className="text-xs text-rose-500 mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Smart List Audience
                            </label>
                            <select
                                value={data.smart_list_id}
                                onChange={(e) => setData('smart_list_id', e.target.value)}
                                className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="">-- All CRM Contacts --</option>
                                {smartLists.map(sl => (
                                    <option key={sl.id} value={sl.id}>{sl.name} ({sl.cached_count || 0} contacts)</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Subject Line *
                            </label>
                            <input
                                type="text"
                                value={data.subject}
                                onChange={(e) => setData('subject', e.target.value)}
                                className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                            {errors.subject && <p className="text-xs text-rose-500 mt-1">{errors.subject}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Preheader Snippet
                            </label>
                            <input
                                type="text"
                                value={data.preheader}
                                onChange={(e) => setData('preheader', e.target.value)}
                                className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                        </div>

                        {/* Variable Chips */}
                        <div>
                            <span className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">
                                Merge Variables:
                            </span>
                            <div className="flex flex-wrap gap-1.5 text-xs">
                                {[
                                    '{{ contact.first_name }}',
                                    '{{ contact.last_name }}',
                                    '{{ contact.email }}',
                                    '{{ agent.name }}',
                                    '{{ property.name }}',
                                    '{{ property.price }}',
                                    '{{ unsubscribe_url }}',
                                ].map((token) => (
                                    <button
                                        key={token}
                                        type="button"
                                        onClick={() => insertVariable(token)}
                                        className="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-sky-50 dark:hover:bg-sky-950 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-[11px] font-mono transition"
                                    >
                                        {token}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div>
                            <div className="flex items-center justify-between mb-1">
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    HTML Body *
                                </label>
                                {!hasUnsubscribe && (
                                    <span className="text-[11px] text-amber-600 dark:text-amber-400 flex items-center gap-1 font-semibold">
                                        <AlertTriangle className="h-3.5 w-3.5" />
                                        Must include unsubscribe link
                                    </span>
                                )}
                            </div>
                            <textarea
                                rows={12}
                                value={data.body_html}
                                onChange={(e) => setData('body_html', e.target.value)}
                                className="w-full text-xs font-mono rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-3 focus:ring-2 focus:ring-sky-500"
                            />
                            {errors.body_html && <p className="text-xs text-rose-500 mt-1">{errors.body_html}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Plain-Text Alternative
                            </label>
                            <textarea
                                rows={4}
                                value={data.body_plain}
                                onChange={(e) => setData('body_plain', e.target.value)}
                                className="w-full text-xs font-mono rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-3 focus:ring-2 focus:ring-sky-500"
                            />
                        </div>
                    </div>

                    {/* Right Simulator */}
                    <div className="lg:col-span-6 bg-slate-100 dark:bg-slate-900/60 rounded-xl p-4 border border-slate-200 dark:border-slate-700 flex flex-col">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700 mb-3">
                            <span className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                                <Monitor className="h-4 w-4 text-sky-600" />
                                Preview Simulator
                            </span>
                            <div className="flex items-center gap-1 bg-white dark:bg-slate-800 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
                                <button
                                    type="button"
                                    onClick={() => setPreviewMode('desktop')}
                                    className={`p-1.5 rounded text-xs flex items-center gap-1 ${
                                        previewMode === 'desktop'
                                            ? 'bg-sky-600 text-white font-bold'
                                            : 'text-slate-500 hover:text-slate-700'
                                    }`}
                                >
                                    <Monitor className="h-3.5 w-3.5" />
                                    Desktop
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setPreviewMode('mobile')}
                                    className={`p-1.5 rounded text-xs flex items-center gap-1 ${
                                        previewMode === 'mobile'
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
                                style={{ width: previewMode === 'mobile' ? '375px' : '100%', maxWidth: '600px' }}
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
                                        __html: data.body_html
                                            ? data.body_html
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
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
