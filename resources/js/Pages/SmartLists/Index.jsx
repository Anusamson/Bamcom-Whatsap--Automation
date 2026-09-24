import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ListFilter,
    Plus,
    Search,
    Flame,
    Clock,
    CalendarCheck,
    DollarSign,
    Home,
    Star,
    Edit,
    Trash2,
    Users,
    ArrowRight,
    Sliders,
    Sparkles,
    Check,
    X,
    Filter,
    Layers,
    ChevronRight,
    Compass
} from 'lucide-react';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function Index({
    smartLists = { data: [], links: [] },
    filters = {},
    fieldCatalog = [],
    pipelineStages = []
}) {
    const [search, setSearch] = useState(filters.search || '');
    const [tab, setTab] = useState(filters.tab || 'all');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingList, setEditingList] = useState(null);
    const [previewCount, setPreviewCount] = useState(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewSample, setPreviewSample] = useState([]);

    const defaultRuleGroup = {
        logical_operator: 'AND',
        rules: [
            {
                field: 'contact.location',
                operator: 'contains',
                value: 'Abuja'
            }
        ]
    };

    const { data, setData, post, put, processing, reset, errors } = useForm({
        name: '',
        description: '',
        icon: 'Filter',
        color: 'indigo',
        is_favorite: false,
        rule_groups: defaultRuleGroup
    });

    const presetTemplates = [
        {
            name: 'Hot Abuja Prospects',
            description: 'High-intent hot leads looking for prime property acquisitions across Abuja',
            icon: 'Flame',
            color: 'rose',
            rule_groups: {
                logical_operator: 'AND',
                rules: [
                    { field: 'contact.location', operator: 'contains', value: 'Abuja' },
                    { field: 'lead.temperature', operator: 'equals', value: 'hot' }
                ]
            }
        },
        {
            name: 'Dormant Prospects',
            description: 'Leads and clients with no touchpoint in the last 30 days',
            icon: 'Clock',
            color: 'amber',
            rule_groups: {
                logical_operator: 'AND',
                rules: [
                    { field: 'contact.last_contact_days', operator: 'greater_than_or_equal', value: 30 },
                    { field: 'contact.status', operator: 'not_equals', value: 'lost' }
                ]
            }
        },
        {
            name: 'Inspection Pending',
            description: 'Clients who requested or are currently scheduled for physical site inspections',
            icon: 'CalendarCheck',
            color: 'teal',
            rule_groups: {
                logical_operator: 'AND',
                rules: [
                    { field: 'inspection.status', operator: 'in', value: 'requested, scheduled' }
                ]
            }
        },
        {
            name: 'Payment Pending',
            description: 'Deals awaiting deposit confirmation or final settlement milestone',
            icon: 'DollarSign',
            color: 'emerald',
            rule_groups: {
                logical_operator: 'AND',
                rules: [
                    { field: 'deal.stage_name', operator: 'contains', value: 'Payment Pending' }
                ]
            }
        },
        {
            name: 'Peace Court Prospects',
            description: 'Prospects interested in Peace Court estate development',
            icon: 'Home',
            color: 'indigo',
            rule_groups: {
                logical_operator: 'OR',
                rules: [
                    { field: 'property.title', operator: 'contains', value: 'Peace Court' },
                    { field: 'inspection.estate_name', operator: 'contains', value: 'Peace Court' }
                ]
            }
        }
    ];

    const fetchLivePreview = async (rg) => {
        setPreviewLoading(true);
        try {
            const res = await axios.post(route('smart-lists.preview'), { rule_groups: rg });
            setPreviewCount(res.data.count);
            setPreviewSample(res.data.sample || []);
        } catch (e) {
            console.error('Preview error', e);
        } finally {
            setPreviewLoading(false);
        }
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('smart-lists.index'), { search, tab }, { preserveState: true });
    };

    const handleTabChange = (newTab) => {
        setTab(newTab);
        router.get(route('smart-lists.index'), { search, tab: newTab }, { preserveState: true });
    };

    const openCreateModal = () => {
        setEditingList(null);
        setData({
            name: '',
            description: '',
            icon: 'Filter',
            color: 'indigo',
            is_favorite: false,
            rule_groups: defaultRuleGroup
        });
        setIsModalOpen(true);
        fetchLivePreview(defaultRuleGroup);
    };

    const openEditModal = (list) => {
        setEditingList(list);
        setData({
            name: list.name,
            description: list.description || '',
            icon: list.icon || 'Filter',
            color: list.color || 'indigo',
            is_favorite: !!list.is_favorite,
            rule_groups: list.rule_groups || defaultRuleGroup
        });
        setIsModalOpen(true);
        fetchLivePreview(list.rule_groups || defaultRuleGroup);
    };

    const applyPresetTemplate = (template) => {
        setData({
            name: template.name,
            description: template.description,
            icon: template.icon,
            color: template.color,
            is_favorite: false,
            rule_groups: template.rule_groups
        });
        fetchLivePreview(template.rule_groups);
    };

    const toggleFavorite = (list, e) => {
        e.preventDefault();
        e.stopPropagation();
        router.post(route('smart-lists.toggle-favorite', list.id), {}, { preserveScroll: true });
    };

    const handleDelete = (list, e) => {
        e.preventDefault();
        e.stopPropagation();
        if (confirm(`Are you sure you want to delete Smart List '${list.name}'?`)) {
            router.delete(route('smart-lists.destroy', list.id));
        }
    };

    // Rule Group Mutation Helpers
    const setRootOperator = (op) => {
        const updated = { ...data.rule_groups, logical_operator: op };
        setData('rule_groups', updated);
        fetchLivePreview(updated);
    };

    const updateRule = (ruleIndex, key, val) => {
        const rules = [...(data.rule_groups.rules || [])];
        rules[ruleIndex] = { ...rules[ruleIndex], [key]: val };
        const updated = { ...data.rule_groups, rules };
        setData('rule_groups', updated);
        fetchLivePreview(updated);
    };

    const addRule = () => {
        const rules = [
            ...(data.rule_groups.rules || []),
            {
                field: 'lead.temperature',
                operator: 'equals',
                value: 'hot'
            }
        ];
        const updated = { ...data.rule_groups, rules };
        setData('rule_groups', updated);
        fetchLivePreview(updated);
    };

    const removeRule = (ruleIndex) => {
        const rules = (data.rule_groups.rules || []).filter((_, idx) => idx !== ruleIndex);
        const updated = { ...data.rule_groups, rules };
        setData('rule_groups', updated);
        fetchLivePreview(updated);
    };

    const addSubGroup = () => {
        const rules = [
            ...(data.rule_groups.rules || []),
            {
                logical_operator: 'OR',
                rules: [
                    { field: 'contact.location', operator: 'contains', value: 'Lekki' },
                    { field: 'contact.location', operator: 'contains', value: 'Ikoyi' }
                ]
            }
        ];
        const updated = { ...data.rule_groups, rules };
        setData('rule_groups', updated);
        fetchLivePreview(updated);
    };

    const updateSubGroupOperator = (groupIndex, op) => {
        const rules = [...(data.rule_groups.rules || [])];
        rules[groupIndex] = { ...rules[groupIndex], logical_operator: op };
        const updated = { ...data.rule_groups, rules };
        setData('rule_groups', updated);
        fetchLivePreview(updated);
    };

    const updateSubGroupRule = (groupIndex, ruleIndex, key, val) => {
        const rules = [...(data.rule_groups.rules || [])];
        const subRules = [...(rules[groupIndex].rules || [])];
        subRules[ruleIndex] = { ...subRules[ruleIndex], [key]: val };
        rules[groupIndex] = { ...rules[groupIndex], rules: subRules };
        const updated = { ...data.rule_groups, rules };
        setData('rule_groups', updated);
        fetchLivePreview(updated);
    };

    const addRuleToSubGroup = (groupIndex) => {
        const rules = [...(data.rule_groups.rules || [])];
        const subRules = [
            ...(rules[groupIndex].rules || []),
            { field: 'lead.score', operator: 'greater_than_or_equal', value: 70 }
        ];
        rules[groupIndex] = { ...rules[groupIndex], rules: subRules };
        const updated = { ...data.rule_groups, rules };
        setData('rule_groups', updated);
        fetchLivePreview(updated);
    };

    const removeRuleFromSubGroup = (groupIndex, ruleIndex) => {
        const rules = [...(data.rule_groups.rules || [])];
        const subRules = (rules[groupIndex].rules || []).filter((_, idx) => idx !== ruleIndex);
        rules[groupIndex] = { ...rules[groupIndex], rules: subRules };
        const updated = { ...data.rule_groups, rules };
        setData('rule_groups', updated);
        fetchLivePreview(updated);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingList) {
            put(route('smart-lists.update', editingList.id), {
                onSuccess: () => setIsModalOpen(false)
            });
        } else {
            post(route('smart-lists.store'), {
                onSuccess: () => setIsModalOpen(false)
            });
        }
    };

    const getIconComponent = (iconName) => {
        const map = {
            Flame,
            Clock,
            CalendarCheck,
            DollarSign,
            Home,
            Filter,
            Users,
            Sliders
        };
        const Comp = map[iconName] || ListFilter;
        return <Comp className="w-5 h-5" />;
    };

    const getColorClasses = (colorName) => {
        const map = {
            rose: 'bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 border-rose-200 dark:border-rose-900/40',
            amber: 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200 dark:border-amber-900/40',
            teal: 'bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400 border-teal-200 dark:border-teal-900/40',
            emerald: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900/40',
            indigo: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400 border-indigo-200 dark:border-indigo-900/40',
            purple: 'bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400 border-purple-200 dark:border-purple-900/40',
        };
        return map[colorName] || map.indigo;
    };

    const renderRuleSummary = (rg) => {
        if (!rg || !rg.rules) return 'All Contacts';
        const op = rg.logical_operator || 'AND';
        const parts = rg.rules.map((r) => {
            if (r.rules) {
                const subOp = r.logical_operator || 'OR';
                const subParts = r.rules.map((sr) => `${sr.field?.split('.')[1] || sr.field} ${sr.operator} "${sr.value}"`);
                return `(${subParts.join(` ${subOp} `)})`;
            }
            return `${r.field?.split('.')[1] || r.field} ${r.operator} "${r.value}"`;
        });
        return parts.join(` ${op} `);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Smart Lists - Dynamic CRM Segmentation" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center space-x-3">
                            <div className="p-2.5 bg-indigo-100 dark:bg-indigo-950/60 rounded-2xl text-indigo-600 dark:text-indigo-400">
                                <ListFilter className="w-6 h-6" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    Smart Lists
                                </h1>
                                <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                    Dynamic CRM segmentation using rule groups with AND/OR conditions without duplicating contacts
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={openCreateModal}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition"
                        >
                            <Plus className="w-4 h-4" />
                            <span>Create Smart List</span>
                        </button>
                    </div>
                </div>

                {/* Filters & Tabs */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm">
                    {/* Tabs */}
                    <div className="flex items-center gap-1.5 overflow-x-auto">
                        {[
                            { key: 'all', label: 'All Smart Lists' },
                            { key: 'favorites', label: '★ Favorites' },
                            { key: 'presets', label: 'Out-of-the-Box Presets' },
                            { key: 'custom', label: 'Custom Lists' },
                        ].map((t) => (
                            <button
                                key={t.key}
                                onClick={() => handleTabChange(t.key)}
                                className={`px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition ${
                                    tab === t.key
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:bg-gray-100'
                                }`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>

                    {/* Search */}
                    <form onSubmit={handleSearch} className="relative min-w-[240px]">
                        <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search smart lists..."
                            className="w-full text-xs pl-9 pr-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500"
                        />
                    </form>
                </div>

                {/* Smart Lists Grid */}
                {smartLists.data.length === 0 ? (
                    <div className="bg-white dark:bg-gray-800 rounded-2xl p-12 text-center text-gray-400 border border-gray-100 dark:border-gray-700/60 space-y-3">
                        <ListFilter className="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600" />
                        <h3 className="text-base font-semibold text-gray-700 dark:text-gray-200">
                            No Smart Lists Found
                        </h3>
                        <p className="text-xs text-gray-500 max-w-sm mx-auto">
                            Create a smart list to segment contacts on the fly by location, temperature, budget, or pending inspections.
                        </p>
                        <button
                            onClick={openCreateModal}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl shadow transition"
                        >
                            <Plus className="w-4 h-4" />
                            <span>Create Smart List</span>
                        </button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        {smartLists.data.map((list) => {
                            const colorCls = getColorClasses(list.color);

                            return (
                                <div
                                    key={list.id}
                                    className="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700/60 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between group"
                                >
                                    <div>
                                        {/* Card Top */}
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-3">
                                                <div className={`p-2.5 rounded-xl border ${colorCls}`}>
                                                    {getIconComponent(list.icon)}
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <h3 className="text-sm font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 transition">
                                                            {list.name}
                                                        </h3>
                                                        {list.is_preset && (
                                                            <span className="px-1.5 py-0.5 text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded">
                                                                Preset
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-[11px] text-gray-400 mt-0.5">
                                                        By {list.creator?.name || 'System CRM'}
                                                    </div>
                                                </div>
                                            </div>

                                            <button
                                                onClick={(e) => toggleFavorite(list, e)}
                                                className={`p-1.5 rounded-lg transition ${
                                                    list.is_favorite
                                                        ? 'text-amber-500 fill-amber-500 hover:text-amber-600'
                                                        : 'text-gray-300 hover:text-gray-400 dark:text-gray-600'
                                                }`}
                                                title="Toggle Favorite"
                                            >
                                                <Star className="w-4 h-4" fill={list.is_favorite ? 'currentColor' : 'none'} />
                                            </button>
                                        </div>

                                        {/* Description */}
                                        <p className="mt-3 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                            {list.description || 'Dynamic segmentation query over CRM records.'}
                                        </p>

                                        {/* Dynamic Count Pill */}
                                        <div className="mt-4 flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-900/60 rounded-xl border border-gray-100 dark:border-gray-800">
                                            <div className="flex items-center gap-2">
                                                <Users className="w-4 h-4 text-indigo-500" />
                                                <span className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                                    Matching Contacts:
                                                </span>
                                            </div>
                                            <span className="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300">
                                                {list.cached_count}
                                            </span>
                                        </div>

                                        {/* Rule Summary */}
                                        <div className="mt-3 text-[11px] font-mono text-gray-400 bg-gray-50/50 dark:bg-gray-900/30 p-2 rounded-lg border border-gray-100/80 dark:border-gray-800/80 truncate" title={renderRuleSummary(list.rule_groups)}>
                                            {renderRuleSummary(list.rule_groups)}
                                        </div>
                                    </div>

                                    {/* Card Footer Actions */}
                                    <div className="mt-5 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => openEditModal(list)}
                                                className="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                title="Edit Rules"
                                            >
                                                <Edit className="w-4 h-4" />
                                            </button>
                                            <button
                                                onClick={(e) => handleDelete(list, e)}
                                                className="p-1.5 text-gray-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/50 transition"
                                                title="Delete Smart List"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>

                                        <Link
                                            href={route('smart-lists.show', list.id)}
                                            className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700"
                                        >
                                            <span>View Contacts</span>
                                            <ArrowRight className="w-3.5 h-3.5" />
                                        </Link>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Create / Edit Modal */}
                {isModalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-gray-800 rounded-3xl max-w-3xl w-full max-h-[92vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-700">
                            {/* Modal Header */}
                            <div className="sticky top-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-100 dark:border-gray-700 px-6 py-4 flex items-center justify-between z-10">
                                <div>
                                    <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                                        {editingList ? `Edit: ${editingList.name}` : 'Create Smart List'}
                                    </h2>
                                    <p className="text-xs text-gray-500">
                                        Define dynamic CRM segmentation rules with AND / OR conditions
                                    </p>
                                </div>
                                <button
                                    onClick={() => setIsModalOpen(false)}
                                    className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-xl"
                                >
                                    <X className="w-5 h-5" />
                                </button>
                            </div>

                            <form onSubmit={handleSubmit} className="p-6 space-y-6">
                                {/* Preset Template Quick Loader */}
                                {!editingList && (
                                    <div className="p-3.5 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-950/40 dark:to-purple-950/40 border border-indigo-100 dark:border-indigo-900/50 rounded-2xl">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Sparkles className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                                <span className="text-xs font-bold text-indigo-900 dark:text-indigo-200">
                                                    Load Example Template:
                                                </span>
                                            </div>
                                            <div className="flex flex-wrap gap-1.5">
                                                {presetTemplates.map((tmpl) => (
                                                    <button
                                                        key={tmpl.name}
                                                        type="button"
                                                        onClick={() => applyPresetTemplate(tmpl)}
                                                        className="px-2.5 py-1 bg-white dark:bg-gray-800 hover:bg-indigo-50 border border-gray-200 dark:border-gray-700 rounded-lg text-[11px] font-semibold text-gray-700 dark:text-gray-300 transition"
                                                    >
                                                        {tmpl.name}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Identity Fields */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                            Smart List Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="e.g. Hot Abuja Prospects"
                                            className="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                            required
                                        />
                                        {errors.name && <p className="text-xs text-rose-500 mt-1">{errors.name}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                            Description
                                        </label>
                                        <input
                                            type="text"
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="Criteria explanation for your team"
                                            className="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>
                                </div>

                                {/* Icon and Color Picker */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                            Icon
                                        </label>
                                        <div className="flex gap-2">
                                            {['Flame', 'Clock', 'CalendarCheck', 'DollarSign', 'Home', 'Filter'].map((ic) => (
                                                <button
                                                    key={ic}
                                                    type="button"
                                                    onClick={() => setData('icon', ic)}
                                                    className={`p-2 rounded-xl border transition ${
                                                        data.icon === ic
                                                            ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950 text-indigo-600'
                                                            : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 text-gray-500'
                                                    }`}
                                                >
                                                    {getIconComponent(ic)}
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                            Theme Color
                                        </label>
                                        <div className="flex gap-2">
                                            {[
                                                { key: 'rose', bg: 'bg-rose-500' },
                                                { key: 'amber', bg: 'bg-amber-500' },
                                                { key: 'teal', bg: 'bg-teal-500' },
                                                { key: 'emerald', bg: 'bg-emerald-500' },
                                                { key: 'indigo', bg: 'bg-indigo-500' },
                                                { key: 'purple', bg: 'bg-purple-500' },
                                            ].map((c) => (
                                                <button
                                                    key={c.key}
                                                    type="button"
                                                    onClick={() => setData('color', c.key)}
                                                    className={`w-7 h-7 rounded-full ${c.bg} transition ${
                                                        data.color === c.key ? 'ring-2 ring-offset-2 ring-indigo-500' : 'opacity-80 hover:opacity-100'
                                                    }`}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                {/* Live Preview Counter Banner */}
                                <div className="p-4 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/40 rounded-2xl flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <Sparkles className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                        <div>
                                            <div className="text-xs font-bold text-indigo-900 dark:text-indigo-200">
                                                Live Dynamic Preview
                                            </div>
                                            <div className="text-[11px] text-indigo-700 dark:text-indigo-300">
                                                Matching contacts are queried dynamically without duplicating records
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        {previewLoading ? (
                                            <span className="text-xs font-semibold text-indigo-600">Evaluating...</span>
                                        ) : (
                                            <span className="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                                                {previewCount !== null ? previewCount : '—'}{' '}
                                                <span className="text-xs font-normal text-indigo-800 dark:text-indigo-300">
                                                    contacts
                                                </span>
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Rule Group Builder */}
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                            <Sliders className="w-4 h-4 text-indigo-500" />
                                            <span>Rule Group Configuration</span>
                                        </h3>

                                        {/* Root Logical Operator Toggle (AND / OR) */}
                                        <div className="flex items-center gap-1 p-1 bg-gray-100 dark:bg-gray-900 rounded-xl">
                                            <button
                                                type="button"
                                                onClick={() => setRootOperator('AND')}
                                                className={`px-3 py-1 rounded-lg text-xs font-bold transition ${
                                                    (data.rule_groups.logical_operator || 'AND').toUpperCase() === 'AND'
                                                        ? 'bg-indigo-600 text-white shadow-sm'
                                                        : 'text-gray-500 hover:text-gray-800'
                                                }`}
                                            >
                                                AND (All Match)
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setRootOperator('OR')}
                                                className={`px-3 py-1 rounded-lg text-xs font-bold transition ${
                                                    (data.rule_groups.logical_operator || 'AND').toUpperCase() === 'OR'
                                                        ? 'bg-indigo-600 text-white shadow-sm'
                                                        : 'text-gray-500 hover:text-gray-800'
                                                }`}
                                            >
                                                OR (Any Match)
                                            </button>
                                        </div>
                                    </div>

                                    {/* Rules List */}
                                    <div className="space-y-3">
                                        {(data.rule_groups.rules || []).map((rule, idx) => {
                                            // Case A: Nested Sub-group
                                            if (rule.rules) {
                                                return (
                                                    <div
                                                        key={idx}
                                                        className="p-4 bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-900/50 rounded-2xl space-y-3"
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-[11px] font-bold text-indigo-700 dark:text-indigo-300">
                                                                    Sub-group Condition:
                                                                </span>
                                                                <div className="flex items-center gap-1 bg-white dark:bg-gray-800 p-0.5 rounded-lg border border-indigo-200 dark:border-indigo-800">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => updateSubGroupOperator(idx, 'AND')}
                                                                        className={`px-2 py-0.5 text-[10px] font-bold rounded ${
                                                                            (rule.logical_operator || 'OR').toUpperCase() === 'AND'
                                                                                ? 'bg-indigo-600 text-white'
                                                                                : 'text-gray-500'
                                                                        }`}
                                                                    >
                                                                        AND
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => updateSubGroupOperator(idx, 'OR')}
                                                                        className={`px-2 py-0.5 text-[10px] font-bold rounded ${
                                                                            (rule.logical_operator || 'OR').toUpperCase() === 'OR'
                                                                                ? 'bg-indigo-600 text-white'
                                                                                : 'text-gray-500'
                                                                        }`}
                                                                    >
                                                                        OR
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onClick={() => removeRule(idx)}
                                                                className="text-xs text-rose-500 hover:text-rose-700"
                                                            >
                                                                Remove Sub-group
                                                            </button>
                                                        </div>

                                                        {/* Sub-group Rules */}
                                                        <div className="space-y-2 pl-2 border-l-2 border-indigo-300 dark:border-indigo-800">
                                                            {rule.rules.map((subRule, sIdx) => (
                                                                <div key={sIdx} className="flex items-center gap-2">
                                                                    <select
                                                                        value={subRule.field}
                                                                        onChange={(e) => updateSubGroupRule(idx, sIdx, 'field', e.target.value)}
                                                                        className="text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-2 py-1.5 text-gray-800 dark:text-white"
                                                                    >
                                                                        {fieldCatalog.map((fc) => (
                                                                            <option key={fc.field} value={fc.field}>
                                                                                [{fc.category}] {fc.label}
                                                                            </option>
                                                                        ))}
                                                                    </select>

                                                                    <select
                                                                        value={subRule.operator}
                                                                        onChange={(e) => updateSubGroupRule(idx, sIdx, 'operator', e.target.value)}
                                                                        className="text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-2 py-1.5 text-gray-800 dark:text-white"
                                                                    >
                                                                        <option value="contains">contains</option>
                                                                        <option value="equals">equals</option>
                                                                        <option value="not_equals">not equals</option>
                                                                        <option value="greater_than_or_equal">&gt;=</option>
                                                                        <option value="less_than_or_equal">&lt;=</option>
                                                                        <option value="in">in list</option>
                                                                    </select>

                                                                    <input
                                                                        type="text"
                                                                        value={subRule.value || ''}
                                                                        onChange={(e) => updateSubGroupRule(idx, sIdx, 'value', e.target.value)}
                                                                        placeholder="value"
                                                                        className="flex-1 text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-1.5 text-gray-800 dark:text-white"
                                                                    />

                                                                    <button
                                                                        type="button"
                                                                        onClick={() => removeRuleFromSubGroup(idx, sIdx)}
                                                                        className="p-1 text-gray-400 hover:text-rose-500"
                                                                    >
                                                                        <X className="w-4 h-4" />
                                                                    </button>
                                                                </div>
                                                            ))}

                                                            <button
                                                                type="button"
                                                                onClick={() => addRuleToSubGroup(idx)}
                                                                className="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 pt-1"
                                                            >
                                                                + Add Rule to Sub-group
                                                            </button>
                                                        </div>
                                                    </div>
                                                );
                                            }

                                            // Case B: Standard Single Rule
                                            return (
                                                <div
                                                    key={idx}
                                                    className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 p-3 bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800 rounded-2xl"
                                                >
                                                    {/* Field Selector */}
                                                    <select
                                                        value={rule.field}
                                                        onChange={(e) => updateRule(idx, 'field', e.target.value)}
                                                        className="text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-800 dark:text-white font-medium"
                                                    >
                                                        {fieldCatalog.map((fc) => (
                                                            <option key={fc.field} value={fc.field}>
                                                                [{fc.category}] {fc.label}
                                                            </option>
                                                        ))}
                                                    </select>

                                                    {/* Operator Selector */}
                                                    <select
                                                        value={rule.operator}
                                                        onChange={(e) => updateRule(idx, 'operator', e.target.value)}
                                                        className="text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-800 dark:text-white font-semibold"
                                                    >
                                                        <option value="contains">contains</option>
                                                        <option value="equals">equals</option>
                                                        <option value="not_equals">not equals</option>
                                                        <option value="greater_than_or_equal">&gt;= (at least)</option>
                                                        <option value="less_than_or_equal">&lt;= (at most)</option>
                                                        <option value="greater_than">&gt; (greater)</option>
                                                        <option value="less_than">&lt; (less)</option>
                                                        <option value="in">is in list</option>
                                                        <option value="not_in">is not in list</option>
                                                        <option value="is_empty">is empty / not set</option>
                                                        <option value="is_not_empty">has value</option>
                                                    </select>

                                                    {/* Value Input */}
                                                    <input
                                                        type="text"
                                                        value={rule.value || ''}
                                                        onChange={(e) => updateRule(idx, 'value', e.target.value)}
                                                        placeholder="Value (e.g. Abuja, hot, 30)"
                                                        className="flex-1 text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-800 dark:text-white"
                                                    />

                                                    {/* Delete rule button */}
                                                    <button
                                                        type="button"
                                                        onClick={() => removeRule(idx)}
                                                        className="p-2 text-gray-400 hover:text-rose-500 rounded-xl hover:bg-white dark:hover:bg-gray-800 transition"
                                                        title="Delete Rule"
                                                    >
                                                        <X className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* Action Buttons to Add Rule or Sub-group */}
                                    <div className="flex items-center gap-3 pt-2">
                                        <button
                                            type="button"
                                            onClick={addRule}
                                            className="px-3.5 py-1.5 border border-indigo-200 dark:border-indigo-800/60 bg-indigo-50/50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 rounded-xl text-xs font-bold transition"
                                        >
                                            + Add Condition Rule
                                        </button>
                                        <button
                                            type="button"
                                            onClick={addSubGroup}
                                            className="px-3.5 py-1.5 border border-purple-200 dark:border-purple-800/60 bg-purple-50/50 dark:bg-purple-950/30 text-purple-600 dark:text-purple-400 hover:bg-purple-100 rounded-xl text-xs font-bold transition"
                                        >
                                            + Add Nested Group (AND/OR)
                                        </button>
                                    </div>
                                </div>

                                {/* Modal Footer */}
                                <div className="border-t border-gray-100 dark:border-gray-700 pt-4 flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setIsModalOpen(false)}
                                        className="px-4 py-2 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl transition"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition disabled:opacity-50"
                                    >
                                        {editingList ? 'Update Smart List' : 'Save Smart List'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
