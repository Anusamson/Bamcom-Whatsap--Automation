import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowUpRight,
    Award,
    CheckCircle2,
    Clock,
    Edit2,
    Flame,
    History,
    Info,
    Plus,
    RefreshCw,
    Sliders,
    Sparkles,
    Thermometer,
    Trash2,
    TrendingUp,
    X
} from 'lucide-react';
import { useState } from 'react';

export default function Index({ rules = [], recentLogs = [], temperatureScale = [], stats = {} }) {
    const { auth, flash } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const canEdit = isSuperAdmin || permissions.includes('settings.edit') || permissions.includes('leads.edit');

    const [editingRule, setEditingRule] = useState(null);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isResetting, setIsResetting] = useState(false);

    // Edit form
    const editForm = useForm({
        name: '',
        points: 0,
        description: '',
        is_active: true,
        allow_multiple: false,
        cooldown_minutes: 0,
    });

    // Create form
    const createForm = useForm({
        name: '',
        event_key: '',
        category: 'crm_event',
        points: 10,
        description: '',
        is_active: true,
        allow_multiple: false,
    });

    const handleEditClick = (rule) => {
        setEditingRule(rule);
        editForm.setData({
            name: rule.name,
            points: rule.points,
            description: rule.description || '',
            is_active: Boolean(rule.is_active),
            allow_multiple: Boolean(rule.allow_multiple),
            cooldown_minutes: rule.cooldown_minutes || 0,
        });
    };

    const handleEditSubmit = (e) => {
        e.preventDefault();
        if (!editingRule) return;

        editForm.put(route('lead-scoring.update', editingRule.id), {
            preserveScroll: true,
            onSuccess: () => setEditingRule(null),
        });
    };

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        createForm.post(route('lead-scoring.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateModalOpen(false);
                createForm.reset();
            },
        });
    };

    const handleToggleRule = (rule) => {
        if (!canEdit) return;
        router.patch(route('lead-scoring.toggle', rule.id), {}, {
            preserveScroll: true,
        });
    };

    const handleResetDefaults = () => {
        if (!canEdit) return;
        if (confirm('Are you sure you want to restore the 10 initial lead scoring rules to system defaults? Any custom modifications will be updated.')) {
            setIsResetting(true);
            router.post(route('lead-scoring.reset-defaults'), {}, {
                preserveScroll: true,
                onFinish: () => setIsResetting(false),
            });
        }
    };

    const handleDeleteRule = (rule) => {
        if (!canEdit) return;
        if (confirm(`Are you sure you want to delete the rule "${rule.name}"?`)) {
            router.delete(route('lead-scoring.destroy', rule.id), {
                preserveScroll: true,
            });
        }
    };

    const getCategoryBadgeClass = (category) => {
        switch (category) {
            case 'profile':
                return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800';
            case 'exploration':
                return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800';
            case 'high_intent':
                return 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-800';
            case 'inspection':
                return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800';
            case 'transaction':
                return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800';
            default:
                return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
        }
    };

    const getTempBadgeClass = (temp) => {
        const val = typeof temp === 'string' ? temp.toLowerCase() : temp?.value?.toLowerCase();
        if (val === 'hot') {
            return 'bg-rose-500/10 text-rose-600 border-rose-500/30 dark:bg-rose-500/20 dark:text-rose-400';
        }
        if (val === 'warm') {
            return 'bg-amber-500/10 text-amber-600 border-amber-500/30 dark:bg-amber-500/20 dark:text-amber-400';
        }
        return 'bg-sky-500/10 text-sky-600 border-sky-500/30 dark:bg-sky-500/20 dark:text-sky-400';
    };

    return (
        <AuthenticatedLayout>
            <Head title="Lead Scoring & Temperature Rules" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header section */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 bg-rose-500/10 text-rose-600 dark:text-rose-400 rounded-xl">
                                <Flame className="w-6 h-6" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                                    Lead Scoring Engine
                                </h1>
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Configurable event-based scoring system dynamically updating lead score and buyer temperature tiers.
                                </p>
                            </div>
                        </div>
                    </div>

                    {canEdit && (
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={handleResetDefaults}
                                disabled={isResetting}
                                className="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl transition"
                            >
                                <RefreshCw className={`w-4 h-4 ${isResetting ? 'animate-spin' : ''}`} />
                                Reset to Defaults
                            </button>
                            <button
                                type="button"
                                onClick={() => setIsCreateModalOpen(true)}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl shadow-sm transition"
                            >
                                <Plus className="w-4 h-4" />
                                Add Custom Rule
                            </button>
                        </div>
                    )}
                </div>

                {/* Temperature Tiers Banner */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {temperatureScale.map((tier) => {
                        const isHot = tier.name.toLowerCase() === 'hot';
                        const isWarm = tier.name.toLowerCase() === 'warm';
                        const isCold = tier.name.toLowerCase() === 'cold';

                        const cardStyles = isHot
                            ? 'from-rose-500/10 via-rose-500/5 to-transparent border-rose-200 dark:border-rose-900/50'
                            : isWarm
                            ? 'from-amber-500/10 via-amber-500/5 to-transparent border-amber-200 dark:border-amber-900/50'
                            : 'from-sky-500/10 via-sky-500/5 to-transparent border-sky-200 dark:border-sky-900/50';

                        const textAccent = isHot
                            ? 'text-rose-600 dark:text-rose-400'
                            : isWarm
                            ? 'text-amber-600 dark:text-amber-400'
                            : 'text-sky-600 dark:text-sky-400';

                        const badgeColor = isHot
                            ? 'bg-rose-500 text-white'
                            : isWarm
                            ? 'bg-amber-500 text-white'
                            : 'bg-sky-500 text-white';

                        return (
                            <div
                                key={tier.name}
                                className={`relative p-5 rounded-2xl bg-gradient-to-b ${cardStyles} bg-white dark:bg-slate-900 border shadow-sm transition hover:shadow-md`}
                            >
                                <div className="flex items-center justify-between mb-3">
                                    <div className="flex items-center gap-2">
                                        <span className="text-2xl" role="img" aria-label={tier.name}>
                                            {tier.icon}
                                        </span>
                                        <h3 className={`text-lg font-bold ${textAccent}`}>
                                            {tier.name} Temperature
                                        </h3>
                                    </div>
                                    <span className={`text-xs font-bold px-2.5 py-1 rounded-full ${badgeColor}`}>
                                        {tier.range}
                                    </span>
                                </div>
                                <p className="text-xs text-slate-600 dark:text-slate-300 leading-relaxed min-h-[36px]">
                                    {tier.description}
                                </p>
                                <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    <span>Threshold</span>
                                    <span className="font-semibold text-slate-700 dark:text-slate-200">
                                        {tier.min} – {tier.max} pts
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Stats Summary Bar */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3">
                        <div className="p-2.5 bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-lg">
                            <Sliders className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Rules</p>
                            <p className="text-lg font-bold text-slate-900 dark:text-white">{stats.total_rules || 0}</p>
                        </div>
                    </div>

                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3">
                        <div className="p-2.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-lg">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">Active Rules</p>
                            <p className="text-lg font-bold text-slate-900 dark:text-white">{stats.active_rules || 0}</p>
                        </div>
                    </div>

                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3">
                        <div className="p-2.5 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-lg">
                            <Activity className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">Events Awarded</p>
                            <p className="text-lg font-bold text-slate-900 dark:text-white">{stats.total_events_awarded || 0}</p>
                        </div>
                    </div>

                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3">
                        <div className="p-2.5 bg-rose-500/10 text-rose-600 dark:text-rose-400 rounded-lg">
                            <Sparkles className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">Points Awarded</p>
                            <p className="text-lg font-bold text-slate-900 dark:text-white">+{stats.total_points_distributed || 0}</p>
                        </div>
                    </div>
                </div>

                {/* Scoring Rules List */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <div className="p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold text-slate-900 dark:text-white">
                                Active Scoring Rules & Points
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Click on any rule to modify points, descriptions, or idempotency rules.
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className="px-5 py-3.5">CRM Event & Name</th>
                                    <th className="px-5 py-3.5">Category</th>
                                    <th className="px-5 py-3.5">Points</th>
                                    <th className="px-5 py-3.5">Type</th>
                                    <th className="px-5 py-3.5">Status</th>
                                    {canEdit && <th className="px-5 py-3.5 text-right">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {rules.map((rule) => {
                                    return (
                                        <tr
                                            key={rule.id}
                                            className={`hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition ${
                                                !rule.is_active ? 'opacity-60 bg-slate-50/40 dark:bg-slate-800/20' : ''
                                            }`}
                                        >
                                            <td className="px-5 py-4">
                                                <div className="flex flex-col">
                                                    <span className="font-semibold text-slate-900 dark:text-white">
                                                        {rule.name}
                                                    </span>
                                                    <span className="font-mono text-xs text-slate-400 dark:text-slate-500">
                                                        {rule.event_key}
                                                    </span>
                                                    {rule.description && (
                                                        <span className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-md">
                                                            {rule.description}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${getCategoryBadgeClass(rule.category)}`}>
                                                    {rule.category}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className={`inline-flex items-center font-bold px-2.5 py-1 rounded-lg text-sm ${
                                                    rule.points >= 20
                                                        ? 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                                                        : rule.points >= 10
                                                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                                        : 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                                }`}>
                                                    +{rule.points} pts
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className="text-xs text-slate-600 dark:text-slate-300">
                                                    {rule.allow_multiple ? (
                                                        <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                            <RefreshCw className="w-3 h-3" />
                                                            Repeatable
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
                                                            <CheckCircle2 className="w-3 h-3" />
                                                            One-off Milestone
                                                        </span>
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <button
                                                    type="button"
                                                    disabled={!canEdit}
                                                    onClick={() => handleToggleRule(rule)}
                                                    className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${
                                                        rule.is_active ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700'
                                                    }`}
                                                >
                                                    <span
                                                        className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                                            rule.is_active ? 'translate-x-5' : 'translate-x-0'
                                                        }`}
                                                    />
                                                </button>
                                            </td>
                                            {canEdit && (
                                                <td className="px-5 py-4 text-right">
                                                    <div className="inline-flex items-center gap-1.5">
                                                        <button
                                                            type="button"
                                                            onClick={() => handleEditClick(rule)}
                                                            className="p-1.5 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                                            title="Edit Rule"
                                                        >
                                                            <Edit2 className="w-4 h-4" />
                                                        </button>
                                                        {rule.category === 'crm_event' && (
                                                            <button
                                                                type="button"
                                                                onClick={() => handleDeleteRule(rule)}
                                                                className="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                                                title="Delete Rule"
                                                            >
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Recent Scoring Activity Feed */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-5">
                    <div className="flex items-center justify-between mb-4">
                        <div className="flex items-center gap-2">
                            <History className="w-5 h-5 text-slate-500 dark:text-slate-400" />
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Recent Lead Scoring Activity
                            </h3>
                        </div>
                        <span className="text-xs text-slate-400">
                            Showing latest {recentLogs.length} events
                        </span>
                    </div>

                    {recentLogs.length === 0 ? (
                        <div className="py-8 text-center text-slate-400 text-sm">
                            No lead scoring events recorded yet. Events will show up here in real-time as leads trigger scoring rules.
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-slate-800">
                            {recentLogs.map((log) => {
                                const leadName = log.lead?.contact?.name || log.lead?.name || `Lead #${log.lead_id}`;
                                const ruleName = log.rule?.name || log.event_key;
                                return (
                                    <div key={log.id} className="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-sm">
                                        <div className="flex items-center gap-3">
                                            <div className="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-bold text-xs">
                                                +{log.points_awarded}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-semibold text-slate-900 dark:text-white">
                                                        {leadName}
                                                    </span>
                                                    <span className="text-xs text-slate-400">•</span>
                                                    <span className="text-xs text-slate-600 dark:text-slate-300">
                                                        {ruleName}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2 text-xs text-slate-400 mt-0.5">
                                                    <span>Score: {log.score_before} → {log.score_after}</span>
                                                    <span>•</span>
                                                    <span className={`px-1.5 py-0.2 rounded font-medium border text-[11px] ${getTempBadgeClass(log.temperature_after)}`}>
                                                        {log.temperature_after}
                                                    </span>
                                                    <span>•</span>
                                                    <span>Source: {log.source || 'system'}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <span className="text-xs text-slate-400 font-mono">
                                            {new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* Edit Rule Modal */}
            {editingRule && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-lg w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                Edit Scoring Rule: {editingRule.name}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setEditingRule(null)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleEditSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Rule Name
                                </label>
                                <input
                                    type="text"
                                    value={editForm.data.name}
                                    onChange={(e) => editForm.setData('name', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Points Awarded (-100 to 100)
                                </label>
                                <input
                                    type="number"
                                    value={editForm.data.points}
                                    onChange={(e) => editForm.setData('points', parseInt(e.target.value) || 0)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Description
                                </label>
                                <textarea
                                    value={editForm.data.description}
                                    onChange={(e) => editForm.setData('description', e.target.value)}
                                    rows="3"
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="allow_multiple"
                                    checked={editForm.data.allow_multiple}
                                    onChange={(e) => editForm.setData('allow_multiple', e.target.checked)}
                                    className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                <label htmlFor="allow_multiple" className="text-xs text-slate-700 dark:text-slate-300 font-medium">
                                    Allow Multiple Awards (Repeatable Event)
                                </label>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setEditingRule(null)}
                                    className="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={editForm.processing}
                                    className="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl shadow-sm transition disabled:opacity-50"
                                >
                                    {editForm.processing ? 'Saving...' : 'Save Rule Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Create Rule Modal */}
            {isCreateModalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-lg w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                Add Custom Scoring Rule
                            </h3>
                            <button
                                type="button"
                                onClick={() => setIsCreateModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Rule Name
                                </label>
                                <input
                                    type="text"
                                    placeholder="e.g., Requested Mortgage Callback"
                                    value={createForm.data.name}
                                    onChange={(e) => createForm.setData('name', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Event Key (unique identifier)
                                </label>
                                <input
                                    type="text"
                                    placeholder="e.g., mortgage_callback_requested"
                                    value={createForm.data.event_key}
                                    onChange={(e) => createForm.setData('event_key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Points Awarded
                                </label>
                                <input
                                    type="number"
                                    value={createForm.data.points}
                                    onChange={(e) => createForm.setData('points', parseInt(e.target.value) || 0)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Description
                                </label>
                                <textarea
                                    value={createForm.data.description}
                                    onChange={(e) => createForm.setData('description', e.target.value)}
                                    rows="2"
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="create_allow_multiple"
                                    checked={createForm.data.allow_multiple}
                                    onChange={(e) => createForm.setData('allow_multiple', e.target.checked)}
                                    className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                <label htmlFor="create_allow_multiple" className="text-xs text-slate-700 dark:text-slate-300 font-medium">
                                    Allow Multiple Awards (Repeatable Event)
                                </label>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsCreateModalOpen(false)}
                                    className="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={createForm.processing}
                                    className="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl shadow-sm transition disabled:opacity-50"
                                >
                                    {createForm.processing ? 'Creating...' : 'Create Rule'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
