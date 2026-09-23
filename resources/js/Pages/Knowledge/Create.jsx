import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { 
    BookOpen, 
    ArrowLeft, 
    Save, 
    Building, 
    HelpCircle, 
    BadgePercent, 
    MapPin, 
    CalendarCheck, 
    CreditCard, 
    ShieldAlert, 
    FileText,
    Sparkles,
    Calendar,
    ArrowUpDown,
    Tag,
    Info
} from 'lucide-react';
import { useState } from 'react';

const iconMap = {
    Building,
    HelpCircle,
    BadgePercent,
    MapPin,
    CalendarCheck,
    CreditCard,
    ShieldAlert,
    FileText,
};

export default function Create({ categories, statuses, initialCategory }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        category: initialCategory || categories[0]?.value || 'company_information',
        content: '',
        status: 'active',
        priority: 50,
        effective_date: '',
        expiration_date: '',
        keywords: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('knowledge.store'));
    };

    const selectedCategoryMeta = categories.find((c) => c.value === data.category) || {};

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href={route('knowledge.index', { category: data.category })}
                        className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                Create Knowledge Record
                            </h2>
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <Sparkles className="w-3 h-3" />
                                AI Grounding
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Add verified organizational knowledge, policy, or sales scripting to the AI intelligence engine.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Create Knowledge Record" />

            <div className="py-6 max-w-4xl mx-auto">
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Category Selection Grid */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                Knowledge Category *
                            </label>
                            <p className="text-xs text-slate-400">
                                Select which area of real estate knowledge this record governs.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            {categories.map((cat) => {
                                const IconComponent = iconMap[cat.icon] || BookOpen;
                                const isSelected = data.category === cat.value;

                                return (
                                    <button
                                        key={cat.value}
                                        type="button"
                                        onClick={() => setData('category', cat.value)}
                                        className={`p-3 rounded-xl border text-left transition-all ${
                                            isSelected
                                                ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 ring-2 ring-indigo-500/20'
                                                : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                                        }`}
                                    >
                                        <div className="flex items-center gap-2 mb-1.5">
                                            <div className={`p-1.5 rounded-lg ${isSelected ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'}`}>
                                                <IconComponent className="w-4 h-4" />
                                            </div>
                                            <span className="text-xs font-bold text-slate-900 dark:text-white">
                                                {cat.label}
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2">
                                            {cat.description}
                                        </p>
                                    </button>
                                );
                            })}
                        </div>
                        {errors.category && <p className="text-xs text-rose-500 mt-1">{errors.category}</p>}
                    </div>

                    {/* Record Details */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            <BookOpen className="w-4 h-4 text-indigo-500" />
                            Record Information
                        </h3>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Title / Subject *
                                </label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. Legal Title Perfection Protocol or Handling Price Objections"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                {errors.title && <p className="text-xs text-rose-500 mt-1">{errors.title}</p>}
                            </div>

                            {/* Content */}
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        Knowledge Content (Authoritative Facts, Scripts or Policies) *
                                    </label>
                                    <span className="text-[11px] text-slate-400">
                                        {data.content.length} characters
                                    </span>
                                </div>
                                <textarea
                                    required
                                    rows={8}
                                    placeholder="Write detailed, factual instructions or scripts. For FAQs, specify question and detailed answer. For objection handling, specify buyer doubt and recommended rebuttal..."
                                    value={data.content}
                                    onChange={(e) => setData('content', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-3 font-mono focus:ring-2 focus:ring-indigo-500"
                                />
                                {errors.content && <p className="text-xs text-rose-500 mt-1">{errors.content}</p>}
                                <p className="text-[11px] text-slate-400 flex items-center gap-1 mt-1">
                                    <Info className="w-3.5 h-3.5 text-indigo-500" />
                                    This text is ingested directly by the AI model when answering customer inquiries. Keep information clear and unambiguous.
                                </p>
                            </div>

                            {/* Keywords / Search Tags */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                                    <Tag className="w-3.5 h-3.5 text-slate-400" />
                                    Keywords / Matching Triggers (Comma-separated)
                                </label>
                                <input
                                    type="text"
                                    placeholder="e.g. title, c of o, allocation, scam, discount, lekki"
                                    value={data.keywords}
                                    onChange={(e) => setData('keywords', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                <p className="text-[11px] text-slate-400 mt-1">
                                    Helps the AI intent router instantly match customer queries to this knowledge record.
                                </p>
                                {errors.keywords && <p className="text-xs text-rose-500 mt-1">{errors.keywords}</p>}
                            </div>
                        </div>
                    </div>

                    {/* AI Lifecycle & Scheduling Settings */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            <Sparkles className="w-4 h-4 text-indigo-500" />
                            AI Availability & Lifecycle Controls
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Status */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Status *
                                </label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="active">Active (Available for AI inference)</option>
                                    <option value="draft">Draft (Hidden from AI)</option>
                                    <option value="archived">Archived (Historical reference only)</option>
                                </select>
                                {errors.status && <p className="text-xs text-rose-500 mt-1">{errors.status}</p>}
                            </div>

                            {/* Priority */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                                    <ArrowUpDown className="w-3.5 h-3.5 text-slate-400" />
                                    Priority Rank (0 - 1000)
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    max="1000"
                                    value={data.priority}
                                    onChange={(e) => setData('priority', parseInt(e.target.value) || 0)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                <p className="text-[11px] text-slate-400 mt-1">
                                    Higher numbers prioritize this record over others in the AI prompt context window.
                                </p>
                                {errors.priority && <p className="text-xs text-rose-500 mt-1">{errors.priority}</p>}
                            </div>

                            {/* Effective Date */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                                    <Calendar className="w-3.5 h-3.5 text-slate-400" />
                                    Effective Date (Optional)
                                </label>
                                <input
                                    type="date"
                                    value={data.effective_date}
                                    onChange={(e) => setData('effective_date', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                <p className="text-[11px] text-slate-400 mt-1">
                                    If set in the future, the AI will not cite this information until this date arrives.
                                </p>
                                {errors.effective_date && <p className="text-xs text-rose-500 mt-1">{errors.effective_date}</p>}
                            </div>

                            {/* Expiration Date */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                                    <Calendar className="w-3.5 h-3.5 text-slate-400" />
                                    Expiration Date (Optional)
                                </label>
                                <input
                                    type="date"
                                    value={data.expiration_date}
                                    onChange={(e) => setData('expiration_date', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                <p className="text-[11px] text-slate-400 mt-1">
                                    Once expired, the record is immediately and automatically dropped from AI reasoning.
                                </p>
                                {errors.expiration_date && <p className="text-xs text-rose-500 mt-1">{errors.expiration_date}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-3">
                        <Link
                            href={route('knowledge.index', { category: data.category })}
                            className="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-1.5 px-6 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition disabled:opacity-50"
                        >
                            <Save className="w-4 h-4" />
                            {processing ? 'Saving...' : 'Save Knowledge Record'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
