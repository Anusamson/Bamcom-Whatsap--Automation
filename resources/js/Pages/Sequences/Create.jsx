import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Workflow,
    ArrowLeft,
    Plus,
    Trash2,
    Clock,
    MessageSquare,
    CheckSquare,
    TrendingUp,
    Tag,
    UserCheck,
    Bell,
    ChevronDown,
    ChevronUp,
    Sparkles,
    ShieldCheck
} from 'lucide-react';
import { useState } from 'react';

export default function Create({ pipelineStages = [], users = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        status: 'active',
        trigger_type: 'manual',
        exit_on_deal_won: true,
        exit_on_reply: false,
        steps: [
            {
                step_number: 1,
                name: 'Initial WhatsApp Follow-up',
                delay_minutes: 0,
                delay_type: 'minutes',
                whatsapp_config: {
                    message: 'Hello {{contact.first_name}}, thank you for your interest in Bamcom Real Estate! Would you like to schedule an inspection of our premium properties?'
                },
                task_config: {
                    title: 'Check WhatsApp response from {{contact.first_name}}',
                    due_in_days: 1,
                    priority: 'medium',
                    type: 'follow_up'
                },
                stage_change_config: null,
                tag_config: {
                    add_tags: ['sequence-enrolled']
                },
                assignment_config: null,
                notification_config: null,
                applicability_rules: null,
            }
        ]
    });

    const [expandedStep, setExpandedStep] = useState(0);

    const addStep = () => {
        const nextNumber = data.steps.length + 1;
        const newStep = {
            step_number: nextNumber,
            name: `Step ${nextNumber}`,
            delay_minutes: 1440, // 1 day
            delay_type: 'days',
            whatsapp_config: {
                message: 'Hi {{contact.first_name}}, just checking in to see if you have any questions about our listings.'
            },
            task_config: null,
            stage_change_config: null,
            tag_config: null,
            assignment_config: null,
            notification_config: null,
            applicability_rules: null,
        };
        setData('steps', [...data.steps, newStep]);
        setExpandedStep(data.steps.length);
    };

    const removeStep = (index) => {
        if (data.steps.length <= 1) {
            alert('A sequence must contain at least one step.');
            return;
        }
        const updated = data.steps.filter((_, i) => i !== index).map((s, i) => ({
            ...s,
            step_number: i + 1
        }));
        setData('steps', updated);
        setExpandedStep(Math.max(0, index - 1));
    };

    const updateStepField = (index, field, value) => {
        const updated = [...data.steps];
        updated[index] = { ...updated[index], [field]: value };
        setData('steps', updated);
    };

    const updateNestedStepField = (index, parentField, field, value) => {
        const updated = [...data.steps];
        const parent = updated[index][parentField] || {};
        updated[index] = {
            ...updated[index],
            [parentField]: { ...parent, [field]: value }
        };
        setData('steps', updated);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('sequences.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Create Follow-up Sequence" />

            <div className="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                        <Link
                            href={route('sequences.index')}
                            className="p-2 text-gray-500 hover:text-gray-900 dark:hover:text-white rounded-xl bg-gray-100 dark:bg-gray-800 transition"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                Create Follow-up Sequence
                            </h1>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Configure a multi-step customer engagement workflow
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Sequence Details Card */}
                    <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 space-y-4">
                        <h2 className="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <Workflow className="w-5 h-5 text-purple-600" />
                            Sequence Details
                        </h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                    Sequence Name *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. VIP Inspection Follow-up"
                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:outline-none"
                                />
                                {errors.name && <p className="text-xs text-rose-500 mt-1">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                    Initial Status
                                </label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:outline-none"
                                >
                                    <option value="active">Active (Ready to enroll)</option>
                                    <option value="draft">Draft</option>
                                    <option value="paused">Paused</option>
                                </select>
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                    Description
                                </label>
                                <textarea
                                    rows="2"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Explain the purpose of this cadence..."
                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:outline-none"
                                />
                            </div>
                        </div>

                        {/* Guardrails / Exit Rules */}
                        <div className="pt-4 border-t border-gray-100 dark:border-gray-700/60">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-2 flex items-center gap-1.5">
                                <ShieldCheck className="w-4 h-4 text-emerald-500" />
                                Sequence Exit & Safety Guardrails
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <label className="flex items-center gap-2 p-3 rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.exit_on_deal_won}
                                        onChange={(e) => setData('exit_on_deal_won', e.target.checked)}
                                        className="rounded text-purple-600 focus:ring-purple-500"
                                    />
                                    <div>
                                        <span className="font-semibold text-gray-900 dark:text-white text-xs block">
                                            Auto-exit on Deal Won
                                        </span>
                                        <span className="text-[11px] text-gray-500">
                                            Immediately stops cadence if contact wins any deal.
                                        </span>
                                    </div>
                                </label>

                                <label className="flex items-center gap-2 p-3 rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.exit_on_reply}
                                        onChange={(e) => setData('exit_on_reply', e.target.checked)}
                                        className="rounded text-purple-600 focus:ring-purple-500"
                                    />
                                    <div>
                                        <span className="font-semibold text-gray-900 dark:text-white text-xs block">
                                            Auto-exit on Customer Reply
                                        </span>
                                        <span className="text-[11px] text-gray-500">
                                            Stops automation when customer replies on WhatsApp.
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Steps Section */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Clock className="w-5 h-5 text-purple-600" />
                                Cadence Steps ({data.steps.length})
                            </h2>

                            <button
                                type="button"
                                onClick={addStep}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 hover:bg-purple-100 text-xs font-semibold rounded-xl transition"
                            >
                                <Plus className="w-4 h-4" />
                                Add Next Step
                            </button>
                        </div>

                        {data.steps.map((step, idx) => (
                            <div
                                key={idx}
                                className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 overflow-hidden"
                            >
                                {/* Step Header */}
                                <div
                                    onClick={() => setExpandedStep(expandedStep === idx ? null : idx)}
                                    className="p-4 flex items-center justify-between cursor-pointer bg-gray-50/50 dark:bg-gray-900/30 hover:bg-gray-50 dark:hover:bg-gray-900/60 transition"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="w-7 h-7 rounded-full bg-purple-600 text-white flex items-center justify-center font-bold text-xs">
                                            {step.step_number}
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
                                                {step.name || `Step ${step.step_number}`}
                                            </h4>
                                            <span className="text-xs text-gray-500 flex items-center gap-1">
                                                <Clock className="w-3 h-3" />
                                                Delay: {step.delay_minutes} minutes
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                removeStep(idx);
                                            }}
                                            className="p-1.5 text-gray-400 hover:text-rose-600 rounded-lg transition"
                                            title="Delete step"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                        {expandedStep === idx ? (
                                            <ChevronUp className="w-4 h-4 text-gray-400" />
                                        ) : (
                                            <ChevronDown className="w-4 h-4 text-gray-400" />
                                        )}
                                    </div>
                                </div>

                                {/* Step Body */}
                                {expandedStep === idx && (
                                    <div className="p-5 border-t border-gray-100 dark:border-gray-700/60 space-y-5">
                                        {/* Step Details & Delay */}
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div className="md:col-span-2">
                                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                                    Step Name
                                                </label>
                                                <input
                                                    type="text"
                                                    value={step.name}
                                                    onChange={(e) => updateStepField(idx, 'name', e.target.value)}
                                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                                    Delay Before Step (minutes)
                                                </label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={step.delay_minutes}
                                                    onChange={(e) => updateStepField(idx, 'delay_minutes', parseInt(e.target.value) || 0)}
                                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white"
                                                />
                                                <p className="text-[10px] text-gray-400 mt-1">0 = immediate, 1440 = 1 day, 2880 = 2 days</p>
                                            </div>
                                        </div>

                                        {/* 1. WhatsApp Message Action */}
                                        <div className="p-4 rounded-xl border border-purple-100 dark:border-purple-900/40 bg-purple-50/30 dark:bg-purple-950/10 space-y-3">
                                            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-purple-700 dark:text-purple-300">
                                                <MessageSquare className="w-4 h-4" />
                                                Action 1: WhatsApp Message
                                            </div>
                                            <div>
                                                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Message Body (Supports tokens: {'{{contact.first_name}}'}, {'{{contact.name}}'}, {'{{lead.score}}'})
                                                </label>
                                                <textarea
                                                    rows="3"
                                                    value={step.whatsapp_config?.message || ''}
                                                    onChange={(e) => updateNestedStepField(idx, 'whatsapp_config', 'message', e.target.value)}
                                                    placeholder="Type WhatsApp message to send..."
                                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                                                />
                                            </div>
                                        </div>

                                        {/* 2. Task Action */}
                                        <div className="p-4 rounded-xl border border-blue-100 dark:border-blue-900/40 bg-blue-50/30 dark:bg-blue-950/10 space-y-3">
                                            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-300">
                                                <CheckSquare className="w-4 h-4" />
                                                Action 2: Task Assignment
                                            </div>
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div>
                                                    <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                        Task Title
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={step.task_config?.title || ''}
                                                        onChange={(e) => updateNestedStepField(idx, 'task_config', 'title', e.target.value)}
                                                        placeholder="e.g. Call {{contact.first_name}}"
                                                        className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                        Due in Days
                                                    </label>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        value={step.task_config?.due_in_days || 1}
                                                        onChange={(e) => updateNestedStepField(idx, 'task_config', 'due_in_days', parseInt(e.target.value) || 1)}
                                                        className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        {/* 3. Pipeline Stage Change & Tag */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="p-4 rounded-xl border border-amber-100 dark:border-amber-900/40 bg-amber-50/30 dark:bg-amber-950/10 space-y-2">
                                                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">
                                                    <TrendingUp className="w-4 h-4" />
                                                    Action 3: Pipeline Stage Change
                                                </div>
                                                <select
                                                    value={step.stage_change_config?.stage_id || ''}
                                                    onChange={(e) => updateNestedStepField(idx, 'stage_change_config', 'stage_id', e.target.value || null)}
                                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                                                >
                                                    <option value="">Do not change stage</option>
                                                    {pipelineStages.map((stg) => (
                                                        <option key={stg.id} value={stg.id}>
                                                            {stg.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div className="p-4 rounded-xl border border-emerald-100 dark:border-emerald-900/40 bg-emerald-50/30 dark:bg-emerald-950/10 space-y-2">
                                                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                                                    <Tag className="w-4 h-4" />
                                                    Action 4: Add Tag
                                                </div>
                                                <input
                                                    type="text"
                                                    value={step.tag_config?.add_tags?.[0] || ''}
                                                    onChange={(e) => updateNestedStepField(idx, 'tag_config', 'add_tags', e.target.value ? [e.target.value] : [])}
                                                    placeholder="e.g. sequence-completed, follow-up-sent"
                                                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3 pt-4">
                        <Link
                            href={route('sequences.index')}
                            className="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl transition"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Save & Create Sequence'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
