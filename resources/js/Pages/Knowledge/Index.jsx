import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    BookOpen, 
    Plus, 
    Search, 
    Building, 
    HelpCircle, 
    BadgePercent, 
    MapPin, 
    CalendarCheck, 
    CreditCard, 
    ShieldAlert, 
    FileText, 
    Sparkles, 
    Edit, 
    Trash2, 
    Eye, 
    RotateCcw,
    CheckCircle2,
    Clock,
    AlertTriangle,
    Archive,
    ToggleLeft,
    ToggleRight,
    ArrowUpDown,
    Database
} from 'lucide-react';
import { useState } from 'react';

// Map icon strings to Lucide components
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

export default function Index({ records, categories, statuses, stats, filters }) {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const [search, setSearch] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('knowledge.index'), {
            category: selectedCategory || undefined,
            status: selectedStatus || undefined,
            search: search || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleCategoryChange = (categoryValue) => {
        setSelectedCategory(categoryValue);
        router.get(route('knowledge.index'), {
            category: categoryValue || undefined,
            status: selectedStatus || undefined,
            search: search || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleStatusChange = (statusValue) => {
        setSelectedStatus(statusValue);
        router.get(route('knowledge.index'), {
            category: selectedCategory || undefined,
            status: statusValue || undefined,
            search: search || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        setSelectedCategory('');
        setSelectedStatus('');
        router.get(route('knowledge.index'));
    };

    const handleDelete = (record) => {
        if (confirm(`Are you sure you want to delete knowledge record "${record.title}"?`)) {
            router.delete(route('knowledge.destroy', record.id));
        }
    };

    const handleToggleStatus = (record) => {
        router.patch(route('knowledge.toggle-status', record.id), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 rounded-xl">
                            <BookOpen className="w-6 h-6" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                    AI Knowledge Base
                                </h2>
                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    <Sparkles className="w-3 h-3" />
                                    AI Grounding
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Authoritative organizational facts, objection handling, policies, and scripts powering the AI assistant.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {can('knowledge.create') && (
                            <Link
                                href={route('knowledge.create', selectedCategory ? { category: selectedCategory } : {})}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition-colors"
                            >
                                <Plus className="w-4 h-4" />
                                Add Knowledge Record
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="AI Knowledge Base" />

            <div className="py-6 space-y-6">
                {/* Metric Summary Cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Records</span>
                            <BookOpen className="w-4 h-4 text-slate-400" />
                        </div>
                        <p className="text-2xl font-bold text-slate-900 dark:text-white mt-2">{stats.total}</p>
                        <p className="text-[11px] text-slate-400 mt-1">Across 8 real estate domains</p>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">AI Active & Grounded</span>
                            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                        </div>
                        <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2">{stats.active_ai}</p>
                        <p className="text-[11px] text-slate-400 mt-1">Currently usable by AI</p>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-amber-600 dark:text-amber-400">Drafts / In Review</span>
                            <Clock className="w-4 h-4 text-amber-500" />
                        </div>
                        <p className="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">{stats.draft}</p>
                        <p className="text-[11px] text-slate-400 mt-1">Hidden from AI models</p>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Archived Records</span>
                            <Archive className="w-4 h-4 text-slate-400" />
                        </div>
                        <p className="text-2xl font-bold text-slate-700 dark:text-slate-300 mt-2">{stats.archived}</p>
                        <p className="text-[11px] text-slate-400 mt-1">Preserved historical facts</p>
                    </div>
                </div>

                {/* Grounding Transparency Notice */}
                <div className="bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-xl p-3.5 flex items-start gap-3">
                    <Database className="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                    <div className="text-xs text-blue-900 dark:text-blue-200">
                        <span className="font-semibold">Live Property Database Grounding:</span> Property prices, promotional discounts, and unit availability always come strictly from the live property inventory database. The knowledge records here enrich AI reasoning with corporate background, verified legal policies, objection frameworks, and sales scripts.
                    </div>
                </div>

                {/* Category Navigation Tabs */}
                <div className="bg-white dark:bg-slate-800 p-2 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-x-auto">
                    <div className="flex items-center gap-1.5 min-w-max">
                        <button
                            type="button"
                            onClick={() => handleCategoryChange('')}
                            className={`px-3 py-2 rounded-lg text-xs font-semibold transition-colors flex items-center gap-2 ${
                                !selectedCategory
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                            }`}
                        >
                            <BookOpen className="w-3.5 h-3.5" />
                            All Categories
                            <span className={`px-1.5 py-0.2 rounded-full text-[10px] ${
                                !selectedCategory ? 'bg-indigo-800 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'
                            }`}>
                                {stats.total}
                            </span>
                        </button>

                        {categories.map((cat) => {
                            const IconComponent = iconMap[cat.icon] || BookOpen;
                            const isSelected = selectedCategory === cat.value;

                            return (
                                <button
                                    key={cat.value}
                                    type="button"
                                    onClick={() => handleCategoryChange(cat.value)}
                                    className={`px-3 py-2 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 ${
                                        isSelected
                                            ? 'bg-indigo-600 text-white shadow-sm'
                                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                                    }`}
                                >
                                    <IconComponent className="w-3.5 h-3.5" />
                                    {cat.label}
                                    <span className={`px-1.5 py-0.2 rounded-full text-[10px] ${
                                        isSelected ? 'bg-indigo-800 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'
                                    }`}>
                                        {cat.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Filters & Search Row */}
                <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <form onSubmit={handleSearch} className="flex-1 max-w-md flex items-center gap-2">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Search title, content, or keywords..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-3 py-2 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition"
                        >
                            Search
                        </button>
                    </form>

                    <div className="flex items-center gap-2">
                        <select
                            value={selectedStatus}
                            onChange={(e) => handleStatusChange(e.target.value)}
                            className="text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 py-2 px-3 focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>

                        {(search || selectedCategory || selectedStatus) && (
                            <button
                                type="button"
                                onClick={handleReset}
                                className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                title="Reset Filters"
                            >
                                <RotateCcw className="w-4 h-4" />
                            </button>
                        )}
                    </div>
                </div>

                {/* Records Listing */}
                {records.data.length === 0 ? (
                    <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-12 text-center space-y-4">
                        <div className="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto text-slate-400">
                            <BookOpen className="w-6 h-6" />
                        </div>
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">No knowledge records found</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                {search || selectedCategory || selectedStatus
                                    ? 'Try adjusting your search criteria or clearing filters.'
                                    : 'Start by creating your first organizational knowledge record.'}
                            </p>
                        </div>
                        {can('knowledge.create') && (
                            <Link
                                href={route('knowledge.create', selectedCategory ? { category: selectedCategory } : {})}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition"
                            >
                                <Plus className="w-4 h-4" />
                                Add Knowledge Record
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="space-y-3">
                        {records.data.map((record) => {
                            const catMeta = categories.find((c) => c.value === record.category) || {};
                            const IconComp = iconMap[catMeta.icon] || BookOpen;
                            
                            // Check active status for AI
                            const today = new Date().toISOString().split('T')[0];
                            const isStatusActive = record.status === 'active';
                            const isNotExpired = !record.expiration_date || record.expiration_date >= today;
                            const isEffective = !record.effective_date || record.effective_date <= today;
                            const isAiReady = isStatusActive && isNotExpired && isEffective;

                            return (
                                <div
                                    key={record.id}
                                    className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-4 hover:border-indigo-300 dark:hover:border-indigo-600 transition"
                                >
                                    <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                        <div className="space-y-2 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-semibold border ${catMeta.badgeClasses || 'bg-slate-100 text-slate-700'}`}>
                                                    <IconComp className="w-3 h-3" />
                                                    {catMeta.label || record.category}
                                                </span>

                                                {/* AI Readiness Badge */}
                                                {isAiReady ? (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                        <CheckCircle2 className="w-3 h-3" />
                                                        AI Active
                                                    </span>
                                                ) : record.status === 'draft' ? (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                        <Clock className="w-3 h-3" />
                                                        Draft (Hidden from AI)
                                                    </span>
                                                ) : !isNotExpired ? (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                                        <AlertTriangle className="w-3 h-3" />
                                                        Expired ({record.expiration_date})
                                                    </span>
                                                ) : !isEffective ? (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                                        <Clock className="w-3 h-3" />
                                                        Scheduled ({record.effective_date})
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                                        Archived
                                                    </span>
                                                )}

                                                <span className="text-[11px] text-slate-400 flex items-center gap-1">
                                                    <ArrowUpDown className="w-3 h-3" />
                                                    Priority: <strong className="text-slate-700 dark:text-slate-300">{record.priority}</strong>
                                                </span>
                                            </div>

                                            <Link
                                                href={route('knowledge.show', record.id)}
                                                className="text-sm font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition block"
                                            >
                                                {record.title}
                                            </Link>

                                            <p className="text-xs text-slate-600 dark:text-slate-300 line-clamp-2 leading-relaxed">
                                                {record.content}
                                            </p>

                                            {/* Keywords / Tags */}
                                            {record.keywords && record.keywords.length > 0 && (
                                                <div className="flex flex-wrap items-center gap-1.5 pt-1">
                                                    <span className="text-[10px] text-slate-400">Keywords:</span>
                                                    {record.keywords.slice(0, 6).map((kw, i) => (
                                                        <span
                                                            key={i}
                                                            className="text-[10px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300"
                                                        >
                                                            {kw}
                                                        </span>
                                                    ))}
                                                    {record.keywords.length > 6 && (
                                                        <span className="text-[10px] text-slate-400">
                                                            +{record.keywords.length - 6} more
                                                        </span>
                                                    )}
                                                </div>
                                            )}

                                            <div className="flex items-center gap-4 text-[11px] text-slate-400 pt-1">
                                                {record.effective_date && (
                                                    <span>Effective: <strong className="text-slate-600 dark:text-slate-300">{record.effective_date}</strong></span>
                                                )}
                                                {record.expiration_date && (
                                                    <span>Expires: <strong className="text-slate-600 dark:text-slate-300">{record.expiration_date}</strong></span>
                                                )}
                                                {record.creator && (
                                                    <span>Author: {record.creator.name}</span>
                                                )}
                                            </div>
                                        </div>

                                        {/* Actions */}
                                        <div className="flex items-center gap-2 shrink-0 self-end md:self-start">
                                            {can('knowledge.edit') && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleToggleStatus(record)}
                                                    className={`inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium border transition ${
                                                        record.status === 'active'
                                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800'
                                                            : 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'
                                                    }`}
                                                    title={record.status === 'active' ? 'Set to Draft' : 'Activate for AI'}
                                                >
                                                    {record.status === 'active' ? (
                                                        <>
                                                            <ToggleRight className="w-4 h-4 text-emerald-600" />
                                                            Active
                                                        </>
                                                    ) : (
                                                        <>
                                                            <ToggleLeft className="w-4 h-4 text-slate-400" />
                                                            Draft
                                                        </>
                                                    )}
                                                </button>
                                            )}

                                            <Link
                                                href={route('knowledge.show', record.id)}
                                                className="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                                title="View Record"
                                            >
                                                <Eye className="w-4 h-4" />
                                            </Link>

                                            {can('knowledge.edit') && (
                                                <Link
                                                    href={route('knowledge.edit', record.id)}
                                                    className="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                                    title="Edit Record"
                                                >
                                                    <Edit className="w-4 h-4" />
                                                </Link>
                                            )}

                                            {can('knowledge.delete') && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(record)}
                                                    className="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                                                    title="Delete Record"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Pagination */}
                {records.links && records.links.length > 3 && (
                    <div className="flex items-center justify-between border-t border-slate-200 dark:border-slate-700 pt-4">
                        <span className="text-xs text-slate-500 dark:text-slate-400">
                            Showing {records.from || 0} to {records.to || 0} of {records.total} records
                        </span>
                        <div className="flex items-center gap-1">
                            {records.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url || '#'}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`px-3 py-1.5 text-xs rounded-lg transition ${
                                        link.active
                                            ? 'bg-indigo-600 text-white font-bold'
                                            : !link.url
                                            ? 'text-slate-400 cursor-not-allowed'
                                            : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                                    }`}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
