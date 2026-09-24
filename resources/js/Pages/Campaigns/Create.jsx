import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Megaphone,
    ArrowLeft,
    Users,
    MessageSquare,
    Clock,
    Sparkles,
    ShieldCheck,
    CheckCircle2,
    Calendar,
    Send,
    Layers,
    Info
} from 'lucide-react';
import { useState } from 'react';

export default function Create({ audiences = [], templates = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        audience_id: audiences.length > 0 ? audiences[0].id : '',
        whatsapp_template_id: templates.length > 0 ? templates[0].id : '',
        message_type: 'template',
        message_content: '',
        template_parameters: ['{{contact.first_name}}'],
        batch_size: 50,
        batch_delay_seconds: 5,
        scheduled_at: '',
        launch_immediately: false,
    });

    const selectedTemplate = templates.find((t) => t.id === parseInt(data.whatsapp_template_id));
    const selectedAudience = audiences.find((a) => a.id === parseInt(data.audience_id));

    const handleParameterChange = (index, value) => {
        const updated = [...(data.template_parameters || [])];
        updated[index] = value;
        setData('template_parameters', updated);
    };

    const addParameter = () => {
        setData('template_parameters', [...(data.template_parameters || []), '{{contact.first_name}}']);
    };

    const removeParameter = (index) => {
        const updated = (data.template_parameters || []).filter((_, i) => i !== index);
        setData('template_parameters', updated);
    };

    const insertToken = (token) => {
        setData('message_content', (data.message_content || '') + ' ' + token);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('campaigns.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Create WhatsApp Campaign" />

            <div className="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header */}
                <div className="flex items-center space-x-3">
                    <Link
                        href={route('campaigns.index')}
                        className="p-2 text-gray-500 hover:text-gray-900 dark:hover:text-white rounded-xl bg-gray-100 dark:bg-gray-800 transition"
                    >
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                            Create WhatsApp Broadcast Campaign
                        </h1>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Configure targeted audience segmentation, message content, and batch queuing
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* 1. Campaign Details */}
                    <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 space-y-4">
                        <h2 className="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <Megaphone className="w-5 h-5 text-emerald-600" />
                            Campaign Information
                        </h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                    Campaign Name *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Easter Luxury Villa Promo"
                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                />
                                {errors.name && <p className="text-xs text-rose-500 mt-1">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                    Target Audience *
                                </label>
                                <select
                                    required
                                    value={data.audience_id}
                                    onChange={(e) => setData('audience_id', e.target.value)}
                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                >
                                    <option value="">Select an audience...</option>
                                    {audiences.map((aud) => (
                                        <option key={aud.id} value={aud.id}>
                                            {aud.name} ({aud.cached_count ?? 0} eligible contacts)
                                        </option>
                                    ))}
                                </select>
                                {errors.audience_id && <p className="text-xs text-rose-500 mt-1">{errors.audience_id}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                    Description / Objective
                                </label>
                                <textarea
                                    rows="2"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Purpose of this outbound broadcast..."
                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                />
                            </div>
                        </div>

                        {/* Audience Info Badge */}
                        {selectedAudience && (
                            <div className="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 flex items-center justify-between text-xs">
                                <div className="flex items-center gap-2 text-indigo-700 dark:text-indigo-300">
                                    <Users className="w-4 h-4" />
                                    <span>
                                        Selected segment contains approximately <strong>{selectedAudience.cached_count}</strong> contacts.
                                    </span>
                                </div>
                                <span className="text-[11px] text-indigo-500 font-medium">Opt-outs excluded automatically</span>
                            </div>
                        )}
                    </div>

                    {/* 2. Message Content */}
                    <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <MessageSquare className="w-5 h-5 text-emerald-600" />
                                WhatsApp Content
                            </h2>

                            {/* Message Type Toggle */}
                            <div className="flex bg-gray-100 dark:bg-gray-700 p-1 rounded-xl">
                                <button
                                    type="button"
                                    onClick={() => setData('message_type', 'template')}
                                    className={`px-3 py-1 text-xs font-semibold rounded-lg transition ${
                                        data.message_type === 'template'
                                            ? 'bg-white dark:bg-gray-900 text-emerald-600 shadow-xs'
                                            : 'text-gray-600 dark:text-gray-300'
                                    }`}
                                >
                                    Approved Meta Template
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setData('message_type', 'custom_text')}
                                    className={`px-3 py-1 text-xs font-semibold rounded-lg transition ${
                                        data.message_type === 'custom_text'
                                            ? 'bg-white dark:bg-gray-900 text-emerald-600 shadow-xs'
                                            : 'text-gray-600 dark:text-gray-300'
                                    }`}
                                >
                                    Custom Text Message
                                </button>
                            </div>
                        </div>

                        {data.message_type === 'template' ? (
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                        Select Meta Approved Template *
                                    </label>
                                    <select
                                        value={data.whatsapp_template_id}
                                        onChange={(e) => setData('whatsapp_template_id', e.target.value)}
                                        className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                    >
                                        <option value="">Choose template...</option>
                                        {templates.map((tpl) => (
                                            <option key={tpl.id} value={tpl.id}>
                                                {tpl.name} ({tpl.category} - {tpl.language})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {selectedTemplate && (
                                    <div className="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700 space-y-3">
                                        <div className="text-xs text-gray-500 flex items-center justify-between">
                                            <span>Template Preview:</span>
                                            <span className="text-emerald-600 font-semibold uppercase text-[10px]">
                                                {selectedTemplate.category}
                                            </span>
                                        </div>
                                        <p className="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line font-mono bg-white dark:bg-gray-800 p-3 rounded-lg border border-gray-100 dark:border-gray-700">
                                            {selectedTemplate.body_text}
                                        </p>

                                        {/* Dynamic Parameters */}
                                        <div className="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                            <div className="flex items-center justify-between">
                                                <label className="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">
                                                    Template Parameters (Positional)
                                                </label>
                                                <button
                                                    type="button"
                                                    onClick={addParameter}
                                                    className="text-xs text-emerald-600 hover:underline font-semibold"
                                                >
                                                    + Add Param
                                                </button>
                                            </div>

                                            {(data.template_parameters || []).map((param, pIdx) => (
                                                <div key={pIdx} className="flex items-center gap-2">
                                                    <span className="text-xs text-gray-400 font-mono w-16">
                                                        Param {`{{${pIdx + 1}}}`}:
                                                    </span>
                                                    <input
                                                        type="text"
                                                        value={param}
                                                        onChange={(e) => handleParameterChange(pIdx, e.target.value)}
                                                        placeholder="e.g. {{contact.first_name}}"
                                                        className="flex-1 px-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => removeParameter(pIdx)}
                                                        className="text-gray-400 hover:text-rose-500 text-xs px-2"
                                                    >
                                                        ✕
                                                    </button>
                                                </div>
                                            ))}
                                            <p className="text-[11px] text-gray-400">
                                                Tip: Use tokens like <code className="text-purple-600">{'{{contact.first_name}}'}</code>, <code className="text-purple-600">{'{{contact.name}}'}</code>, or <code className="text-purple-600">{'{{contact.location}}'}</code>.
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-3">
                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                                            Custom Message Body *
                                        </label>
                                        <div className="flex gap-1 text-[11px]">
                                            <button
                                                type="button"
                                                onClick={() => insertToken('{{contact.first_name}}')}
                                                className="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-purple-600 font-mono"
                                            >
                                                + First Name
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => insertToken('{{contact.location}}')}
                                                className="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-purple-600 font-mono"
                                            >
                                                + Location
                                            </button>
                                        </div>
                                    </div>
                                    <textarea
                                        rows="4"
                                        required
                                        value={data.message_content}
                                        onChange={(e) => setData('message_content', e.target.value)}
                                        placeholder="Write WhatsApp message to broadcast..."
                                        className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                    />
                                </div>
                                <div className="p-3 bg-amber-50 dark:bg-amber-950/30 rounded-xl border border-amber-200 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
                                    <Info className="w-4 h-4 shrink-0 mt-0.5" />
                                    <span>
                                        Note: WhatsApp guidelines require approved Meta templates for marketing broadcasts to contacts outside the 24-hour service window.
                                    </span>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* 3. Sending & Queue Batches Controls */}
                    <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 space-y-4">
                        <h2 className="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <Layers className="w-5 h-5 text-indigo-600" />
                            Queued Batch Delivery & Scheduling
                        </h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                    Batch Size (recipients per job)
                                </label>
                                <input
                                    type="number"
                                    min="5"
                                    max="500"
                                    value={data.batch_size}
                                    onChange={(e) => setData('batch_size', parseInt(e.target.value) || 50)}
                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white"
                                />
                                <p className="text-[10px] text-gray-400 mt-1">Recommended: 25-50 to respect Meta WhatsApp API throughput</p>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                    Delay Between Batches (seconds)
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="60"
                                    value={data.batch_delay_seconds}
                                    onChange={(e) => setData('batch_delay_seconds', parseInt(e.target.value) || 5)}
                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white"
                                />
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                    Schedule For Later (Optional)
                                </label>
                                <input
                                    type="datetime-local"
                                    value={data.scheduled_at}
                                    onChange={(e) => setData('scheduled_at', e.target.value)}
                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white"
                                />
                                <p className="text-[10px] text-gray-400 mt-1">Leave empty to save as Draft or launch immediately</p>
                            </div>
                        </div>

                        {/* Immediate Launch Checkbox */}
                        {!data.scheduled_at && (
                            <div className="pt-3 border-t border-gray-100 dark:border-gray-700/60">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.launch_immediately}
                                        onChange={(e) => setData('launch_immediately', e.target.checked)}
                                        className="rounded text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span className="text-xs font-bold text-gray-900 dark:text-white">
                                        Launch campaign immediately upon saving
                                    </span>
                                </label>
                            </div>
                        )}
                    </div>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3 pt-4">
                        <Link
                            href={route('campaigns.index')}
                            className="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl transition"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            <Send className="w-4 h-4" />
                            {processing
                                ? 'Saving...'
                                : data.launch_immediately
                                ? 'Save & Launch Now'
                                : data.scheduled_at
                                ? 'Schedule Campaign'
                                : 'Save as Draft'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
