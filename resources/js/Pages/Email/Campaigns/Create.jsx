import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    Send, 
    ArrowLeft, 
    FileText, 
    Users, 
    Calendar, 
    Clock, 
    AlertTriangle, 
    CheckCircle, 
    Smartphone, 
    Monitor, 
    Info, 
    Sparkles, 
    RefreshCw,
    Filter,
    Layers,
    ShieldAlert
} from 'lucide-react';

export default function Create({ auth, templates, smartLists, sampleVariables }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        email_template_id: '',
        subject: '',
        preheader: '',
        body_html: '<h2>Hello {{ contact.first_name }}</h2>\n<p>We are delighted to share exclusive real estate investment insights from {{ company.name }}.</p>\n<p>Explore luxury waterfront duplexes and prime commercial plots available this quarter.</p>\n<p><a href="{{ unsubscribe_url }}">Unsubscribe from marketing emails</a></p>',
        body_plain: "Hello {{ contact.first_name }},\n\nWe are delighted to share exclusive real estate investment insights from Bamcom CRM.\n\nUnsubscribe: {{ unsubscribe_url }}",
        smart_list_id: '',
        segment_criteria: null,
        scheduled_at: '',
        batch_size: 50,
        action: 'draft', // 'draft', 'send_now', 'schedule'
    });

    const [activeTab, setActiveTab] = useState('details'); // 'details', 'content', 'audience', 'schedule'
    const [previewMode, setPreviewMode] = useState('desktop'); // 'desktop' or 'mobile'
    const [segmentMode, setSegmentMode] = useState('smart_list'); // 'smart_list' or 'custom'

    // Custom segmentation filter states
    const [customFilters, setCustomFilters] = useState({
        lead_status: '',
        lead_temperature: '',
        location: '',
        tags: '',
        property_interest: '',
        lead_source: '',
        inspection_status: '',
    });

    // Live Audience Preview state
    const [audiencePreview, setAudiencePreview] = useState(null);
    const [calculatingAudience, setCalculatingAudience] = useState(false);
    const [previewError, setPreviewError] = useState(null);

    // Template selection handler
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

    // Calculate dynamic segment criteria from custom filters
    const buildSegmentCriteria = () => {
        const rules = [];
        if (customFilters.lead_status) {
            rules.push({ field: 'contact.status', operator: 'equals', value: customFilters.lead_status });
        }
        if (customFilters.lead_temperature) {
            rules.push({ field: 'lead.temperature', operator: 'equals', value: customFilters.lead_temperature });
        }
        if (customFilters.location) {
            rules.push({ field: 'contact.location', operator: 'contains', value: customFilters.location });
        }
        if (customFilters.tags) {
            rules.push({ field: 'tags', operator: 'contains', value: customFilters.tags });
        }
        if (customFilters.property_interest) {
            rules.push({ field: 'property.title', operator: 'contains', value: customFilters.property_interest });
        }
        if (customFilters.lead_source) {
            rules.push({ field: 'contact.lead_source', operator: 'equals', value: customFilters.lead_source });
        }
        if (customFilters.inspection_status) {
            rules.push({ field: 'inspection.status', operator: 'equals', value: customFilters.inspection_status });
        }

        if (rules.length === 0) {
            return null;
        }

        return {
            logical_operator: 'AND',
            rules: rules,
        };
    };

    // Preview Audience
    const handlePreviewAudience = async () => {
        setCalculatingAudience(true);
        setPreviewError(null);

        const criteria = segmentMode === 'custom' ? buildSegmentCriteria() : null;
        const smartListId = segmentMode === 'smart_list' && data.smart_list_id ? data.smart_list_id : null;

        try {
            const response = await fetch(route('email-campaigns.preview-audience'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    smart_list_id: smartListId,
                    segment_criteria: criteria,
                }),
            });

            const result = await response.json();
            if (response.ok) {
                setAudiencePreview(result);
                if (criteria) {
                    setData('segment_criteria', criteria);
                }
            } else {
                setPreviewError(result.message || 'Failed to preview audience.');
            }
        } catch (err) {
            setPreviewError('Network error while evaluating audience segmentation.');
        } finally {
            setCalculatingAudience(false);
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

    const handleSubmit = (actionType) => {
        setData('action', actionType);
        if (segmentMode === 'custom') {
            const criteria = buildSegmentCriteria();
            setData('segment_criteria', criteria);
        }
        post(route('email-campaigns.store'));
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
                            <span className="text-xs text-slate-500 font-medium">Email Campaigns / New</span>
                        </div>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Send className="h-6 w-6 text-sky-600" />
                            Create Email Marketing Campaign
                        </h2>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => handleSubmit('draft')}
                            disabled={processing}
                            className="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                        >
                            Save Draft
                        </button>
                        <button
                            type="button"
                            onClick={() => handleSubmit('send_now')}
                            disabled={processing || !hasUnsubscribe}
                            className="px-4 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white flex items-center gap-1.5 transition shadow-sm disabled:opacity-50"
                        >
                            <Send className="h-3.5 w-3.5" />
                            Send Immediately
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Create Email Marketing Campaign" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Navigation Tabs */}
                <div className="flex border-b border-slate-200 dark:border-slate-700 space-x-6 text-sm">
                    <button
                        type="button"
                        onClick={() => setActiveTab('details')}
                        className={`pb-3 font-semibold transition border-b-2 ${
                            activeTab === 'details'
                                ? 'border-sky-600 text-sky-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        1. Campaign Details
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
                        2. Content & Design
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('audience')}
                        className={`pb-3 font-semibold transition border-b-2 ${
                            activeTab === 'audience'
                                ? 'border-sky-600 text-sky-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        3. Audience & Smart Lists
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('schedule')}
                        className={`pb-3 font-semibold transition border-b-2 ${
                            activeTab === 'schedule'
                                ? 'border-sky-600 text-sky-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        4. Schedule & Review
                    </button>
                </div>

                {/* TAB 1: DETAILS */}
                {activeTab === 'details' && (
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-5 max-w-3xl">
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Campaign Name *
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="e.g. Q4 Waterfront Duplex Investor Launch"
                                className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                            {errors.name && <p className="text-xs text-rose-500 mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Internal Description / Objective
                            </label>
                            <textarea
                                rows={3}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Purpose of this broadcast, target personas, and expected response goals..."
                                className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                            {errors.description && <p className="text-xs text-rose-500 mt-1">{errors.description}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Base on Existing Email Template (Optional)
                            </label>
                            <select
                                value={data.email_template_id}
                                onChange={handleTemplateChange}
                                className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="">-- Choose a pre-built template to populate content --</option>
                                {templates.map(t => (
                                    <option key={t.id} value={t.id}>{t.name} ({t.category})</option>
                                ))}
                            </select>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Selecting a template will automatically import its subject, preheader, and responsive design.
                            </p>
                        </div>

                        <div className="pt-4 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setActiveTab('content')}
                                className="px-5 py-2.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition"
                            >
                                Next: Content & Design &rarr;
                            </button>
                        </div>
                    </div>
                )}

                {/* TAB 2: CONTENT & DESIGN */}
                {activeTab === 'content' && (
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        {/* Left Editor */}
                        <div className="lg:col-span-6 bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                    Subject Line *
                                </label>
                                <input
                                    type="text"
                                    value={data.subject}
                                    onChange={(e) => setData('subject', e.target.value)}
                                    placeholder="e.g. Exclusive Waterfront Opportunity for {{ contact.first_name }}"
                                    className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                                />
                                {errors.subject && <p className="text-xs text-rose-500 mt-1">{errors.subject}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                    Preheader (Preview Text)
                                </label>
                                <input
                                    type="text"
                                    value={data.preheader}
                                    onChange={(e) => setData('preheader', e.target.value)}
                                    placeholder="Short snippet visible next to subject in inbox line..."
                                    className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                                />
                            </div>

                            {/* Variable Chips */}
                            <div>
                                <span className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">
                                    Dynamic Merge Variables:
                                </span>
                                <div className="flex flex-wrap gap-1.5 text-xs">
                                    {[
                                        '{{ contact.first_name }}',
                                        '{{ contact.last_name }}',
                                        '{{ contact.email }}',
                                        '{{ agent.name }}',
                                        '{{ property.name }}',
                                        '{{ property.price }}',
                                        '{{ inspection.date }}',
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

                            {/* HTML Content */}
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                        HTML Email Body *
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

                            {/* Plain text fallback */}
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

                            <div className="pt-2 flex justify-between">
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('details')}
                                    className="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50"
                                >
                                    &larr; Back
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('audience')}
                                    className="px-5 py-2.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition"
                                >
                                    Next: Audience &rarr;
                                </button>
                            </div>
                        </div>

                        {/* Right Preview Simulator */}
                        <div className="lg:col-span-6 bg-slate-100 dark:bg-slate-900/60 rounded-xl p-4 border border-slate-200 dark:border-slate-700 flex flex-col">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700 mb-3">
                                <span className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                                    <Monitor className="h-4 w-4 text-sky-600" />
                                    Live Render Simulation
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

                            {/* Inbox Subject & Preheader Bar */}
                            <div className="bg-white dark:bg-slate-800 p-3 rounded-t-lg border border-b-0 border-slate-200 dark:border-slate-700 text-xs">
                                <div className="font-semibold text-slate-800 dark:text-white truncate">
                                    {data.subject ? data.subject.replace('{{ contact.first_name }}', 'Babajide') : 'Subject line preview...'}
                                </div>
                                <div className="text-slate-400 truncate text-[11px] mt-0.5">
                                    {data.preheader || 'No preheader provided'}
                                </div>
                            </div>

                            {/* Simulated Email Container */}
                            <div className="flex-1 flex justify-center items-start overflow-auto p-4 bg-slate-200 dark:bg-slate-950 rounded-b-lg border border-slate-200 dark:border-slate-700">
                                <div
                                    style={{ width: previewMode === 'mobile' ? '375px' : '100%', maxWidth: '600px' }}
                                    className="bg-white text-slate-800 shadow-md rounded-lg overflow-hidden border border-slate-300 transition-all duration-300"
                                >
                                    {/* Header */}
                                    <div className="bg-slate-900 text-white p-4 border-b-2 border-sky-500">
                                        <div className="font-extrabold text-lg tracking-tight">
                                            BAMCOM <span className="text-sky-400">CRM</span>
                                        </div>
                                        <div className="text-[10px] text-slate-400 uppercase tracking-widest">
                                            Real Estate &bull; Site Inspections &bull; Investments
                                        </div>
                                    </div>

                                    {/* Content */}
                                    <div
                                        className="p-6 text-sm leading-relaxed prose prose-sm max-w-none"
                                        dangerouslySetInnerHTML={{
                                            __html: data.body_html
                                                ? data.body_html
                                                    .replace(/\{\{\s*contact\.first_name\s*\}\}/g, 'Babajide')
                                                    .replace(/\{\{\s*contact\.last_name\s*\}\}/g, 'Adeleke')
                                                    .replace(/\{\{\s*company\.name\s*\}\}/g, 'Bamcom Real Estate')
                                                    .replace(/\{\{\s*unsubscribe_url\s*\}\}/g, '#')
                                                : '<p class="text-slate-400 italic">Enter HTML body to preview...</p>',
                                        }}
                                    />

                                    {/* Footer */}
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

                {/* TAB 3: AUDIENCE & SEGMENTATION */}
                {activeTab === 'audience' && (
                    <div className="space-y-6">
                        <div className="bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-5">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Users className="h-5 w-5 text-sky-600" />
                                    Audience Selection & Dynamic Segmentation
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    Filter existing CRM contacts using predefined Smart Lists or ad-hoc multi-factor CRM conditions.
                                </p>
                            </div>

                            {/* Segmentation Mode Toggle */}
                            <div className="flex gap-4">
                                <label className="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="segmentMode"
                                        checked={segmentMode === 'smart_list'}
                                        onChange={() => setSegmentMode('smart_list')}
                                        className="text-sky-600 focus:ring-sky-500"
                                    />
                                    <span>Select CRM Smart List</span>
                                </label>
                                <label className="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="segmentMode"
                                        checked={segmentMode === 'custom'}
                                        onChange={() => setSegmentMode('custom')}
                                        className="text-sky-600 focus:ring-sky-500"
                                    />
                                    <span>Define Custom Segment Rules</span>
                                </label>
                            </div>

                            {/* Smart List Selector */}
                            {segmentMode === 'smart_list' ? (
                                <div className="max-w-md">
                                    <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                        Choose Smart List *
                                    </label>
                                    <select
                                        value={data.smart_list_id}
                                        onChange={(e) => setData('smart_list_id', e.target.value)}
                                        className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                                    >
                                        <option value="">-- All CRM Contacts (No List Filter) --</option>
                                        {smartLists.map(sl => (
                                            <option key={sl.id} value={sl.id}>
                                                {sl.name} ({sl.cached_count || 0} contacts)
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            ) : (
                                /* Custom Segmentation Form */
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-slate-200 dark:border-slate-700">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Lead Status
                                        </label>
                                        <select
                                            value={customFilters.lead_status}
                                            onChange={(e) => setCustomFilters(prev => ({ ...prev, lead_status: e.target.value }))}
                                            className="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2"
                                        >
                                            <option value="">All Statuses</option>
                                            <option value="new">New</option>
                                            <option value="contacted">Contacted</option>
                                            <option value="qualified">Qualified</option>
                                            <option value="proposal">Proposal</option>
                                            <option value="negotiation">Negotiation</option>
                                            <option value="won">Won / Closed</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Lead Temperature
                                        </label>
                                        <select
                                            value={customFilters.lead_temperature}
                                            onChange={(e) => setCustomFilters(prev => ({ ...prev, lead_temperature: e.target.value }))}
                                            className="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2"
                                        >
                                            <option value="">All Temperatures</option>
                                            <option value="hot">Hot</option>
                                            <option value="warm">Warm</option>
                                            <option value="cold">Cold</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Location / Area
                                        </label>
                                        <input
                                            type="text"
                                            value={customFilters.location}
                                            onChange={(e) => setCustomFilters(prev => ({ ...prev, location: e.target.value }))}
                                            placeholder="e.g. Lekki, Ikoyi, Ikeja"
                                            className="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Tags
                                        </label>
                                        <input
                                            type="text"
                                            value={customFilters.tags}
                                            onChange={(e) => setCustomFilters(prev => ({ ...prev, tags: e.target.value }))}
                                            placeholder="e.g. VIP, Diaspora, Cash Buyer"
                                            className="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Property Interest
                                        </label>
                                        <input
                                            type="text"
                                            value={customFilters.property_interest}
                                            onChange={(e) => setCustomFilters(prev => ({ ...prev, property_interest: e.target.value }))}
                                            placeholder="e.g. Waterfront Duplex, Palm Grove"
                                            className="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Inspection Status
                                        </label>
                                        <select
                                            value={customFilters.inspection_status}
                                            onChange={(e) => setCustomFilters(prev => ({ ...prev, inspection_status: e.target.value }))}
                                            className="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2"
                                        >
                                            <option value="">All Inspections</option>
                                            <option value="scheduled">Scheduled</option>
                                            <option value="completed">Completed</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                            )}

                            {/* Calculate Button */}
                            <div className="pt-2">
                                <button
                                    type="button"
                                    onClick={handlePreviewAudience}
                                    disabled={calculatingAudience}
                                    className="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-lg bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 hover:bg-slate-800 transition disabled:opacity-50"
                                >
                                    <RefreshCw className={`h-4 w-4 ${calculatingAudience ? 'animate-spin' : ''}`} />
                                    {calculatingAudience ? 'Evaluating Eligibility...' : 'Calculate Recipients & Verify Eligibility'}
                                </button>
                            </div>
                        </div>

                        {/* Audience Preview Results Card */}
                        {audiencePreview && (
                            <div className="bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-5">
                                <h4 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <CheckCircle className="h-5 w-5 text-emerald-500" />
                                    Audience Verification Summary
                                </h4>

                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                    <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                                        <div className="text-xs text-slate-500">Matching Contacts</div>
                                        <div className="text-2xl font-bold text-slate-800 dark:text-white mt-1">
                                            {audiencePreview.total_matching.toLocaleString()}
                                        </div>
                                    </div>

                                    <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                                        <div className="text-xs text-emerald-700 dark:text-emerald-300 font-semibold">
                                            Eligible To Send
                                        </div>
                                        <div className="text-2xl font-bold text-emerald-800 dark:text-emerald-200 mt-1">
                                            {audiencePreview.eligible_count.toLocaleString()}
                                        </div>
                                    </div>

                                    <div className="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
                                        <div className="text-xs text-amber-700 dark:text-amber-300 font-semibold">
                                            Suppressed / Skipped
                                        </div>
                                        <div className="text-2xl font-bold text-amber-800 dark:text-amber-200 mt-1">
                                            {audiencePreview.skipped_count.toLocaleString()}
                                        </div>
                                    </div>
                                </div>

                                {/* Skipped breakdown */}
                                {audiencePreview.skipped_count > 0 && (
                                    <div className="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-700">
                                        <h5 className="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wider">
                                            Suppression / Ineligibility Reasons:
                                        </h5>
                                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                                            <span className="text-slate-600 dark:text-slate-400">
                                                Unsubscribed: <strong>{audiencePreview.skipped_breakdown?.unsubscribed || 0}</strong>
                                            </span>
                                            <span className="text-slate-600 dark:text-slate-400">
                                                Ledger Suppressed: <strong>{audiencePreview.skipped_breakdown?.suppressed || 0}</strong>
                                            </span>
                                            <span className="text-slate-600 dark:text-slate-400">
                                                Hard Bounced: <strong>{audiencePreview.skipped_breakdown?.hard_bounced || 0}</strong>
                                            </span>
                                            <span className="text-slate-600 dark:text-slate-400">
                                                Spam Complaint: <strong>{audiencePreview.skipped_breakdown?.complained || 0}</strong>
                                            </span>
                                            <span className="text-slate-600 dark:text-slate-400">
                                                Invalid Address: <strong>{audiencePreview.skipped_breakdown?.invalid_email || 0}</strong>
                                            </span>
                                            <span className="text-slate-600 dark:text-slate-400">
                                                Awaiting Opt-in: <strong>{audiencePreview.skipped_breakdown?.not_subscribed || 0}</strong>
                                            </span>
                                        </div>
                                    </div>
                                )}

                                {/* Sample Contacts Table */}
                                {audiencePreview.sample_contacts?.length > 0 && (
                                    <div>
                                        <h5 className="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wider">
                                            Sample Contacts Preview:
                                        </h5>
                                        <div className="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                                            <table className="w-full text-xs text-left">
                                                <thead className="bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-300 font-semibold">
                                                    <tr>
                                                        <th className="p-2.5">Name</th>
                                                        <th className="p-2.5">Email</th>
                                                        <th className="p-2.5">Marketing Status</th>
                                                        <th className="p-2.5">Eligibility</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                                    {audiencePreview.sample_contacts.map(c => (
                                                        <tr key={c.id} className="hover:bg-slate-50 dark:hover:bg-slate-800">
                                                            <td className="p-2.5 font-medium">{c.name}</td>
                                                            <td className="p-2.5 text-slate-500">{c.email || 'N/A'}</td>
                                                            <td className="p-2.5 uppercase font-mono text-[10px]">{c.marketing_status}</td>
                                                            <td className="p-2.5">
                                                                {c.eligible ? (
                                                                    <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                                        Eligible
                                                                    </span>
                                                                ) : (
                                                                    <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800" title={c.reason_label}>
                                                                        Skipped ({c.reason})
                                                                    </span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex justify-between">
                            <button
                                type="button"
                                onClick={() => setActiveTab('content')}
                                className="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50"
                            >
                                &larr; Back
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('schedule')}
                                className="px-5 py-2.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition"
                            >
                                Next: Schedule & Review &rarr;
                            </button>
                        </div>
                    </div>
                )}

                {/* TAB 4: SCHEDULE & REVIEW */}
                {activeTab === 'schedule' && (
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-6 max-w-3xl">
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Clock className="h-5 w-5 text-sky-600" />
                                Delivery Strategy & Queued Batches
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Large campaigns are divided into background queued batches to preserve SES sending quota and server memory.
                            </p>
                        </div>

                        {/* Batch Size */}
                        <div className="max-w-xs">
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Batch Size (Recipients Per Job)
                            </label>
                            <input
                                type="number"
                                min={10}
                                max={500}
                                value={data.batch_size}
                                onChange={(e) => setData('batch_size', parseInt(e.target.value) || 50)}
                                className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                            <p className="text-[11px] text-slate-500 mt-1">
                                Recommended: 50 recipients per batch for optimal concurrency and reputation protection.
                            </p>
                        </div>

                        {/* Schedule DateTime */}
                        <div className="max-w-sm pt-2 border-t border-slate-200 dark:border-slate-700">
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Schedule Future Send (Optional)
                            </label>
                            <input
                                type="datetime-local"
                                value={data.scheduled_at}
                                onChange={(e) => setData('scheduled_at', e.target.value)}
                                className="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                            {errors.scheduled_at && <p className="text-xs text-rose-500 mt-1">{errors.scheduled_at}</p>}
                        </div>

                        {/* Review Summary Alert */}
                        <div className="p-4 bg-sky-50 dark:bg-sky-950/50 rounded-xl border border-sky-200 dark:border-sky-800 text-xs space-y-2">
                            <div className="font-bold text-sky-900 dark:text-sky-200 flex items-center gap-1.5">
                                <Info className="h-4 w-4" />
                                Campaign Ready for Execution
                            </div>
                            <p className="text-sky-800 dark:text-sky-300">
                                Campaign name: <strong>{data.name || 'Untitled Campaign'}</strong><br />
                                Subject: <strong>{data.subject || 'No subject'}</strong><br />
                                Unsubscribe link verified: <strong>{hasUnsubscribe ? 'Yes (CAN-SPAM Compliant)' : 'No (Blocked)'}</strong>
                            </p>
                        </div>

                        <div className="pt-4 flex flex-wrap gap-3 justify-end border-t border-slate-200 dark:border-slate-700">
                            <button
                                type="button"
                                onClick={() => setActiveTab('audience')}
                                className="px-4 py-2.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50"
                            >
                                &larr; Back
                            </button>
                            <button
                                type="button"
                                onClick={() => handleSubmit('draft')}
                                disabled={processing}
                                className="px-5 py-2.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100"
                            >
                                Save as Draft
                            </button>
                            {data.scheduled_at && (
                                <button
                                    type="button"
                                    onClick={() => handleSubmit('schedule')}
                                    disabled={processing || !hasUnsubscribe}
                                    className="px-5 py-2.5 text-xs font-semibold rounded-lg bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1.5 transition disabled:opacity-50"
                                >
                                    <Calendar className="h-3.5 w-3.5" />
                                    Schedule Campaign
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={() => handleSubmit('send_now')}
                                disabled={processing || !hasUnsubscribe}
                                className="px-6 py-2.5 text-xs font-bold rounded-lg bg-sky-600 hover:bg-sky-700 text-white flex items-center gap-1.5 transition shadow-sm disabled:opacity-50"
                            >
                                <Send className="h-4 w-4" />
                                Send Now
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
