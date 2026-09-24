import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import {
    Users,
    Plus,
    Search,
    Edit,
    Trash2,
    Filter,
    Layers,
    Sparkles,
    Megaphone,
    ArrowLeft,
    CheckCircle2,
    ShieldAlert,
    X,
    Eye,
    Sliders,
    Tag as TagIcon,
    DollarSign,
    Calendar,
    Home,
    UserCheck,
    Compass
} from 'lucide-react';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function Audiences({
    audiences = { data: [], links: [] },
    pipelineStages = [],
    temperatures = [],
    tags = [],
    properties = [],
    users = [],
    leadSources = []
}) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingAudience, setEditingAudience] = useState(null);
    const [previewCount, setPreviewCount] = useState(null);
    const [previewLoading, setPreviewLoading] = useState(false);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        name: '',
        description: '',
        filters: {
            stage_ids: [],
            temperatures: [],
            tags: [],
            locations: '',
            property_ids: [],
            min_budget: '',
            max_budget: '',
            agent_ids: [],
            sources: [],
            inspection_statuses: [],
            last_contact_within_days: '',
            last_contact_before_days: '',
            never_contacted: false,
        }
    });

    const openCreateModal = () => {
        setEditingAudience(null);
        reset();
        setIsModalOpen(true);
        fetchPreview({
            stage_ids: [],
            temperatures: [],
            tags: [],
            locations: '',
            property_ids: [],
            min_budget: '',
            max_budget: '',
            agent_ids: [],
            sources: [],
            inspection_statuses: [],
            last_contact_within_days: '',
            last_contact_before_days: '',
            never_contacted: false,
        });
    };

    const openEditModal = (aud) => {
        setEditingAudience(aud);
        const f = aud.filters || {};
        setData({
            name: aud.name,
            description: aud.description || '',
            filters: {
                stage_ids: f.stage_ids || [],
                temperatures: f.temperatures || [],
                tags: f.tags || [],
                locations: Array.isArray(f.locations) ? f.locations.join(', ') : (f.locations || ''),
                property_ids: f.property_ids || [],
                min_budget: f.min_budget || '',
                max_budget: f.max_budget || '',
                agent_ids: f.agent_ids || [],
                sources: f.sources || [],
                inspection_statuses: f.inspection_statuses || [],
                last_contact_within_days: f.last_contact_within_days || '',
                last_contact_before_days: f.last_contact_before_days || '',
                never_contacted: !!f.never_contacted,
            }
        });
        setIsModalOpen(true);
        fetchPreview(f);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setEditingAudience(null);
        reset();
    };

    const fetchPreview = async (filtersToTest) => {
        setPreviewLoading(true);
        try {
            const locs = typeof filtersToTest.locations === 'string'
                ? filtersToTest.locations.split(',').map((s) => s.trim()).filter(Boolean)
                : filtersToTest.locations;

            const payload = {
                ...filtersToTest,
                locations: locs,
            };

            const response = await axios.post(route('audiences.preview'), { filters: payload });
            setPreviewCount(response.data.count);
        } catch (e) {
            console.error('Preview error', e);
        } finally {
            setPreviewLoading(false);
        }
    };

    const handleFilterChange = (key, value) => {
        const updatedFilters = {
            ...data.filters,
            [key]: value
        };
        setData('filters', updatedFilters);
        fetchPreview(updatedFilters);
    };

    const toggleArrayFilter = (key, item) => {
        const current = data.filters[key] || [];
        const exists = current.includes(item);
        const updated = exists ? current.filter((x) => x !== item) : [...current, item];
        handleFilterChange(key, updated);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const locs = typeof data.filters.locations === 'string'
            ? data.filters.locations.split(',').map((s) => s.trim()).filter(Boolean)
            : data.filters.locations;

        const payload = {
            name: data.name,
            description: data.description,
            filters: {
                ...data.filters,
                locations: locs,
            }
        };

        if (editingAudience) {
            router.put(route('audiences.update', editingAudience.id), payload, {
                onSuccess: () => closeModal(),
            });
        } else {
            router.post(route('audiences.store'), payload, {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleDelete = (aud) => {
        if (confirm(`Are you sure you want to delete audience '${aud.name}'?`)) {
            router.delete(route('audiences.destroy', aud.id));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Audience Segments" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('campaigns.index')}
                            className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl text-gray-500 transition"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <div className="flex items-center space-x-3">
                                <div className="p-2 bg-indigo-100 dark:bg-indigo-950/60 rounded-xl text-indigo-600 dark:text-indigo-400">
                                    <Users className="w-6 h-6" />
                                </div>
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    Audience Segments
                                </h1>
                            </div>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Segment contacts across 10 distinct dimensions with automatic opt-out exclusion
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={openCreateModal}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition"
                        >
                            <Plus className="w-4 h-4" />
                            <span>Create Segment</span>
                        </button>
                    </div>
                </div>

                {/* Audiences List */}
                <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 overflow-hidden">
                    {audiences.data.length === 0 ? (
                        <div className="p-12 text-center text-gray-400 space-y-3">
                            <Users className="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600" />
                            <h3 className="text-base font-semibold text-gray-700 dark:text-gray-300">
                                No Audience Segments Yet
                            </h3>
                            <p className="text-xs text-gray-500 max-w-sm mx-auto">
                                Create an audience segment based on lead stage, budget, temperature, property interest, or location to run targeted campaigns.
                            </p>
                            <button
                                onClick={openCreateModal}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl shadow transition"
                            >
                                <Plus className="w-4 h-4" />
                                <span>Create Your First Segment</span>
                            </button>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs sm:text-sm">
                                <thead className="bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 font-semibold border-b border-gray-100 dark:border-gray-700">
                                    <tr>
                                        <th className="py-3 px-6">Segment Name</th>
                                        <th className="py-3 px-6">Audience Size</th>
                                        <th className="py-3 px-6">Campaigns Used</th>
                                        <th className="py-3 px-6">Active Criteria</th>
                                        <th className="py-3 px-6">Created By</th>
                                        <th className="py-3 px-6 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50 text-gray-700 dark:text-gray-300">
                                    {audiences.data.map((aud) => {
                                        const f = aud.filters || {};
                                        const criteriaCount = Object.keys(f).filter(k => {
                                            if (Array.isArray(f[k])) return f[k].length > 0;
                                            return !!f[k];
                                        }).length;

                                        return (
                                            <tr key={aud.id} className="hover:bg-gray-50/50 dark:hover:bg-gray-750">
                                                <td className="py-3.5 px-6">
                                                    <div className="font-semibold text-gray-900 dark:text-white">
                                                        {aud.name}
                                                    </div>
                                                    {aud.description && (
                                                        <div className="text-xs text-gray-400 truncate max-w-xs">
                                                            {aud.description}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-3.5 px-6">
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 font-semibold rounded-lg text-xs">
                                                        <Users className="w-3.5 h-3.5" />
                                                        {aud.cached_count} contacts
                                                    </span>
                                                </td>
                                                <td className="py-3.5 px-6 text-xs text-gray-500">
                                                    {aud.campaigns_count || 0} campaigns
                                                </td>
                                                <td className="py-3.5 px-6">
                                                    <span className="text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                                                        {criteriaCount === 0 ? 'All Active Contacts' : `${criteriaCount} filters active`}
                                                    </span>
                                                </td>
                                                <td className="py-3.5 px-6 text-xs text-gray-400">
                                                    {aud.creator?.name || 'Administrator'}
                                                </td>
                                                <td className="py-3.5 px-6 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link
                                                            href={route('campaigns.create', { audience_id: aud.id })}
                                                            className="p-1.5 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 text-emerald-600 rounded-lg transition"
                                                            title="Launch Campaign with this Audience"
                                                        >
                                                            <Megaphone className="w-4 h-4" />
                                                        </Link>
                                                        <button
                                                            onClick={() => openEditModal(aud)}
                                                            className="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg transition"
                                                            title="Edit Segment"
                                                        >
                                                            <Edit className="w-4 h-4" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(aud)}
                                                            className="p-1.5 hover:bg-rose-50 dark:hover:bg-rose-950/50 text-rose-600 rounded-lg transition"
                                                            title="Delete Segment"
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Create / Edit Segment Modal */}
                {isModalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-gray-800 rounded-3xl max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-700">
                            {/* Modal Header */}
                            <div className="sticky top-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-100 dark:border-gray-700 px-6 py-4 flex items-center justify-between z-10">
                                <div>
                                    <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                                        {editingAudience ? 'Edit Audience Segment' : 'Create New Audience Segment'}
                                    </h2>
                                    <p className="text-xs text-gray-500">
                                        Define audience segmentation criteria across 10 distinct dimensions
                                    </p>
                                </div>
                                <button
                                    onClick={closeModal}
                                    className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-xl"
                                >
                                    <X className="w-5 h-5" />
                                </button>
                            </div>

                            <form onSubmit={handleSubmit} className="p-6 space-y-6">
                                {/* Segment Identity */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                            Segment Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="e.g. Hot Buyers in Lekki"
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
                                            placeholder="Brief notes about this cohort"
                                            className="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>
                                </div>

                                {/* Live Preview Counter */}
                                <div className="p-4 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/40 rounded-2xl flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <Sparkles className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                        <div>
                                            <div className="text-xs font-semibold text-indigo-900 dark:text-indigo-200">
                                                Matching Audience Size
                                            </div>
                                            <div className="text-[11px] text-indigo-700 dark:text-indigo-300">
                                                Contacts who have opted out are excluded automatically
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        {previewLoading ? (
                                            <span className="text-sm font-semibold text-indigo-600">Calculating...</span>
                                        ) : (
                                            <span className="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">
                                                {previewCount !== null ? previewCount : '—'} <span className="text-xs font-normal text-indigo-800 dark:text-indigo-300">contacts</span>
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Segmentation Filters (10 Dimensions) */}
                                <div className="space-y-5">
                                    <h3 className="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                        <Sliders className="w-4 h-4 text-indigo-500" />
                                        <span>Segmentation Dimensions</span>
                                    </h3>

                                    {/* 1. Lead Stage & 2. Lead Temperature */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                1. Pipeline Stage
                                            </label>
                                            <div className="flex flex-wrap gap-1.5">
                                                {pipelineStages.map((st) => {
                                                    const selected = data.filters.stage_ids.includes(st.id);
                                                    return (
                                                        <button
                                                            key={st.id}
                                                            type="button"
                                                            onClick={() => toggleArrayFilter('stage_ids', st.id)}
                                                            className={`px-2.5 py-1 rounded-lg text-xs font-medium transition ${
                                                                selected
                                                                    ? 'bg-indigo-600 text-white'
                                                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'
                                                            }`}
                                                        >
                                                            {st.name}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                2. Lead Temperature
                                            </label>
                                            <div className="flex flex-wrap gap-1.5">
                                                {temperatures.map((temp) => {
                                                    const selected = data.filters.temperatures.includes(temp.value);
                                                    return (
                                                        <button
                                                            key={temp.value}
                                                            type="button"
                                                            onClick={() => toggleArrayFilter('temperatures', temp.value)}
                                                            className={`px-3 py-1 rounded-lg text-xs font-medium capitalize transition ${
                                                                selected
                                                                    ? 'bg-indigo-600 text-white'
                                                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'
                                                            }`}
                                                        >
                                                            {temp.label}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </div>

                                    {/* 3. Tags & 4. Locations */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                3. Contact / Lead Tags
                                            </label>
                                            <div className="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
                                                {tags.map((tg) => {
                                                    const selected = data.filters.tags.includes(tg.name);
                                                    return (
                                                        <button
                                                            key={tg.id}
                                                            type="button"
                                                            onClick={() => toggleArrayFilter('tags', tg.name)}
                                                            className={`px-2.5 py-1 rounded-lg text-xs font-medium transition ${
                                                                selected
                                                                    ? 'bg-indigo-600 text-white'
                                                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'
                                                            }`}
                                                        >
                                                            #{tg.name}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                4. Target Locations
                                            </label>
                                            <input
                                                type="text"
                                                value={data.filters.locations}
                                                onChange={(e) => handleFilterChange('locations', e.target.value)}
                                                placeholder="Lekki, Ikoyi, Victoria Island (comma separated)"
                                                className="w-full text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-800 dark:text-white"
                                            />
                                        </div>
                                    </div>

                                    {/* 5. Property Interest & 6. Budget */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                5. Property Interest
                                            </label>
                                            <div className="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
                                                {properties.map((prop) => {
                                                    const selected = data.filters.property_ids.includes(prop.id);
                                                    return (
                                                        <button
                                                            key={prop.id}
                                                            type="button"
                                                            onClick={() => toggleArrayFilter('property_ids', prop.id)}
                                                            className={`px-2.5 py-1 rounded-lg text-xs font-medium truncate max-w-[200px] transition ${
                                                                selected
                                                                    ? 'bg-indigo-600 text-white'
                                                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'
                                                            }`}
                                                        >
                                                            {prop.title}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                6. Budget Range (NGN)
                                            </label>
                                            <div className="grid grid-cols-2 gap-2">
                                                <input
                                                    type="number"
                                                    value={data.filters.min_budget}
                                                    onChange={(e) => handleFilterChange('min_budget', e.target.value)}
                                                    placeholder="Min Budget"
                                                    className="w-full text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-800 dark:text-white"
                                                />
                                                <input
                                                    type="number"
                                                    value={data.filters.max_budget}
                                                    onChange={(e) => handleFilterChange('max_budget', e.target.value)}
                                                    placeholder="Max Budget"
                                                    className="w-full text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-800 dark:text-white"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* 7. Assigned Agent & 8. Lead Source */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                7. Assigned Agent
                                            </label>
                                            <div className="flex flex-wrap gap-1.5">
                                                {users.map((u) => {
                                                    const selected = data.filters.agent_ids.includes(u.id);
                                                    return (
                                                        <button
                                                            key={u.id}
                                                            type="button"
                                                            onClick={() => toggleArrayFilter('agent_ids', u.id)}
                                                            className={`px-2.5 py-1 rounded-lg text-xs font-medium transition ${
                                                                selected
                                                                    ? 'bg-indigo-600 text-white'
                                                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'
                                                            }`}
                                                        >
                                                            {u.name}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                8. Lead Source
                                            </label>
                                            <div className="flex flex-wrap gap-1.5">
                                                {leadSources.map((src) => {
                                                    const selected = data.filters.sources.includes(src.value);
                                                    return (
                                                        <button
                                                            key={src.value}
                                                            type="button"
                                                            onClick={() => toggleArrayFilter('sources', src.value)}
                                                            className={`px-2.5 py-1 rounded-lg text-xs font-medium capitalize transition ${
                                                                selected
                                                                    ? 'bg-indigo-600 text-white'
                                                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'
                                                            }`}
                                                        >
                                                            {src.label}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </div>

                                    {/* 9. Inspection Status & 10. Last Contact Recency */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                9. Inspection Status
                                            </label>
                                            <div className="flex flex-wrap gap-1.5">
                                                {['requested', 'scheduled', 'completed', 'cancelled'].map((insp) => {
                                                    const selected = data.filters.inspection_statuses.includes(insp);
                                                    return (
                                                        <button
                                                            key={insp}
                                                            type="button"
                                                            onClick={() => toggleArrayFilter('inspection_statuses', insp)}
                                                            className={`px-2.5 py-1 rounded-lg text-xs font-medium capitalize transition ${
                                                                selected
                                                                    ? 'bg-indigo-600 text-white'
                                                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'
                                                            }`}
                                                        >
                                                            {insp}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                                10. Last Contact Recency
                                            </label>
                                            <div className="grid grid-cols-2 gap-2">
                                                <input
                                                    type="number"
                                                    value={data.filters.last_contact_within_days}
                                                    onChange={(e) => handleFilterChange('last_contact_within_days', e.target.value)}
                                                    placeholder="Within X days"
                                                    className="w-full text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-800 dark:text-white"
                                                />
                                                <input
                                                    type="number"
                                                    value={data.filters.last_contact_before_days}
                                                    onChange={(e) => handleFilterChange('last_contact_before_days', e.target.value)}
                                                    placeholder="Inactive > X days"
                                                    className="w-full text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-800 dark:text-white"
                                                />
                                            </div>
                                            <label className="flex items-center gap-2 pt-1 text-xs text-gray-600 dark:text-gray-400 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={data.filters.never_contacted}
                                                    onChange={(e) => handleFilterChange('never_contacted', e.target.checked)}
                                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span>Never contacted yet</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                {/* Modal Footer */}
                                <div className="border-t border-gray-100 dark:border-gray-700 pt-4 flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="px-4 py-2 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl transition"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition disabled:opacity-50"
                                    >
                                        {editingAudience ? 'Update Audience' : 'Save Audience'}
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
