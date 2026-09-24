import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Workflow,
    Plus,
    Search,
    Play,
    Pause,
    CheckCircle2,
    XCircle,
    Clock,
    Users,
    ChevronRight,
    Sparkles,
    ShieldAlert,
    Trash2,
    Eye,
    Edit
} from 'lucide-react';
import { useState } from 'react';

export default function Index({
    sequences = { data: [], links: [] },
    metrics = {},
    filters = {},
    statuses = []
}) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('sequences.index'), { search, status }, { preserveState: true });
    };

    const handleStatusFilter = (newStatus) => {
        setStatus(newStatus);
        router.get(route('sequences.index'), { search, status: newStatus }, { preserveState: true });
    };

    const toggleStatus = (id) => {
        router.post(route('sequences.toggle', id), {}, { preserveScroll: true });
    };

    const handleDelete = (sequence) => {
        if (confirm(`Are you sure you want to delete "${sequence.name}"? Active enrollments will be cancelled.`)) {
            router.delete(route('sequences.destroy', sequence.id));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Follow-up Sequences" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center space-x-3">
                            <div className="p-2 bg-purple-100 dark:bg-purple-900/40 rounded-xl text-purple-600 dark:text-purple-400">
                                <Workflow className="w-6 h-6" />
                            </div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                Follow-up Sequences
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Multi-step automated customer cadences with 5 guardrail verification before each step
                        </p>
                    </div>

                    <Link
                        href={route('sequences.create')}
                        className="inline-flex items-center justify-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-xl shadow-sm transition duration-150 ease-in-out gap-2"
                    >
                        <Plus className="w-4 h-4" />
                        <span>Create Sequence</span>
                    </Link>
                </div>

                {/* Metrics Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Total Sequences
                            </span>
                            <Workflow className="w-4 h-4 text-purple-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                            {metrics.total_sequences ?? 0}
                        </p>
                        <span className="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                            {metrics.active_sequences ?? 0} active
                        </span>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Active Enrollments
                            </span>
                            <Users className="w-4 h-4 text-indigo-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                            {metrics.active_enrollments ?? 0}
                        </p>
                        <span className="text-xs text-gray-500">Currently in cadence</span>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Completed
                            </span>
                            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {metrics.completed_enrollments ?? 0}
                        </p>
                        <span className="text-xs text-gray-500">Completed all steps</span>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Cancelled / Opted Out
                            </span>
                            <ShieldAlert className="w-4 h-4 text-rose-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {metrics.cancelled_enrollments ?? 0}
                        </p>
                        <span className="text-xs text-gray-500">Exited by guardrail</span>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 flex flex-col md:flex-row gap-4 items-center justify-between">
                    <form onSubmit={handleSearch} className="flex-1 w-full flex items-center gap-2">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search sequences by name or description..."
                                className="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:outline-none"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-xl transition"
                        >
                            Filter
                        </button>
                    </form>

                    <div className="flex items-center gap-2 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
                        <button
                            onClick={() => handleStatusFilter('all')}
                            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                                status === 'all'
                                    ? 'bg-purple-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'
                            }`}
                        >
                            All
                        </button>
                        {statuses.map((s) => (
                            <button
                                key={s.value}
                                onClick={() => handleStatusFilter(s.value)}
                                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition whitespace-nowrap ${
                                    status === s.value
                                        ? 'bg-purple-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'
                                }`}
                            >
                                {s.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Sequences List */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {sequences.data?.length > 0 ? (
                        sequences.data.map((seq) => (
                            <div
                                key={seq.id}
                                className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 p-5 flex flex-col justify-between hover:shadow-md transition"
                            >
                                <div>
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <span
                                                className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider mb-2 ${
                                                    seq.status === 'active'
                                                        ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
                                                        : seq.status === 'paused'
                                                        ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'
                                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
                                                }`}
                                            >
                                                {seq.status}
                                            </span>
                                            <h3 className="text-lg font-bold text-gray-900 dark:text-white line-clamp-1">
                                                {seq.name}
                                            </h3>
                                        </div>

                                        <button
                                            onClick={() => toggleStatus(seq.id)}
                                            title={seq.status === 'active' ? 'Pause Sequence' : 'Activate Sequence'}
                                            className={`p-2 rounded-xl transition ${
                                                seq.status === 'active'
                                                    ? 'bg-amber-50 dark:bg-amber-900/30 text-amber-600 hover:bg-amber-100'
                                                    : 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 hover:bg-emerald-100'
                                            }`}
                                        >
                                            {seq.status === 'active' ? (
                                                <Pause className="w-4 h-4" />
                                            ) : (
                                                <Play className="w-4 h-4" />
                                            )}
                                        </button>
                                    </div>

                                    <p className="mt-2 text-xs text-gray-500 dark:text-gray-400 line-clamp-2 min-h-[32px]">
                                        {seq.description || 'No description provided.'}
                                    </p>

                                    {/* Sequence Steps Summary */}
                                    <div className="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                                        <div className="flex items-center gap-1.5">
                                            <Clock className="w-3.5 h-3.5 text-purple-500" />
                                            <span className="font-semibold">{seq.steps_count ?? 0}</span> steps
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Users className="w-3.5 h-3.5 text-indigo-500" />
                                            <span className="font-semibold">{seq.active_enrollments_count ?? 0}</span> enrolled
                                        </div>
                                    </div>

                                    {/* Guardrails Badge */}
                                    <div className="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                                        {seq.exit_on_deal_won && (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 font-medium">
                                                ✓ Exit on Deal Won
                                            </span>
                                        )}
                                        <span className="inline-flex items-center px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-medium">
                                            ✓ Opt-Out Guard
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-5 pt-4 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                                    <div className="flex items-center gap-1">
                                        <Link
                                            href={route('sequences.show', seq.id)}
                                            className="p-2 text-gray-500 hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-950/40 rounded-lg transition"
                                            title="View Sequence"
                                        >
                                            <Eye className="w-4 h-4" />
                                        </Link>
                                        <Link
                                            href={route('sequences.edit', seq.id)}
                                            className="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 rounded-lg transition"
                                            title="Edit Sequence"
                                        >
                                            <Edit className="w-4 h-4" />
                                        </Link>
                                        <button
                                            onClick={() => handleDelete(seq)}
                                            className="p-2 text-gray-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                                            title="Delete Sequence"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                    </div>

                                    <Link
                                        href={route('sequences.show', seq.id)}
                                        className="inline-flex items-center gap-1 text-xs font-semibold text-purple-600 dark:text-purple-400 hover:underline"
                                    >
                                        Manage
                                        <ChevronRight className="w-3.5 h-3.5" />
                                    </Link>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="col-span-full py-12 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                            <Workflow className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                            <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                                No follow-up sequences found
                            </h3>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Get started by creating your first automated follow-up sequence.
                            </p>
                            <Link
                                href={route('sequences.create')}
                                className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                            >
                                <Plus className="w-4 h-4" />
                                Create Sequence
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
