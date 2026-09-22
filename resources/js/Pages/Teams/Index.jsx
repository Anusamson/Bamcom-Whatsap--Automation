import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    UsersRound, 
    Plus, 
    Search, 
    Filter, 
    Edit, 
    Trash2, 
    Eye, 
    Shield, 
    UserCheck, 
    Briefcase,
    TrendingUp,
    Users
} from 'lucide-react';
import { useState } from 'react';

export default function Index({ teams, types, filters, canCreate }) {
    const [search, setSearch] = useState(filters.search || '');
    const [type, setType] = useState(filters.type || '');

    const handleFilter = (e) => {
        e?.preventDefault();
        router.get(route('teams.index'), {
            search: search || undefined,
            type: type || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleDelete = (team) => {
        if (confirm(`Are you sure you want to delete team "${team.name}"? Members will be detached but their accounts will not be deleted.`)) {
            router.delete(route('teams.destroy', team.id));
        }
    };

    const getTypeBadge = (teamType) => {
        switch (teamType) {
            case 'sales':
                return 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
            case 'support':
                return 'bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800';
            case 'marketing':
                return 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800';
            case 'inspection':
                return 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
            default:
                return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700';
        }
    };

    return (
        <AuthenticatedLayout header="Teams Management">
            <Head title="Teams Management - Bamcom AI CRM" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400">
                                <UsersRound className="h-5 w-5" />
                            </span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                Teams & Sales Groups
                            </h1>
                        </div>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Organize sales representatives and operational staff into functional team units.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href={route('users.index')}
                            className="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                        >
                            <Users className="h-4 w-4 text-slate-500" />
                            <span>Users Directory</span>
                        </Link>

                        {canCreate && (
                            <Link
                                href={route('teams.create')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold shadow-sm transition"
                            >
                                <Plus className="h-4 w-4" />
                                <span>Create Team</span>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Filter Toolbar */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-sm">
                    <form onSubmit={handleFilter} className="flex flex-col sm:flex-row items-center gap-3">
                        <div className="relative flex-1 w-full">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search teams by name, description, or leader..."
                                className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none"
                            />
                        </div>

                        <select
                            value={type}
                            onChange={(e) => setType(e.target.value)}
                            className="w-full sm:w-48 px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none capitalize"
                        >
                            <option value="">All Team Types</option>
                            {types.map((t) => (
                                <option key={t} value={t}>{t} Team</option>
                            ))}
                        </select>

                        <button
                            type="submit"
                            className="w-full sm:w-auto px-4 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold transition"
                        >
                            Filter
                        </button>
                    </form>
                </div>

                {/* Teams Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {teams.data.length === 0 ? (
                        <div className="col-span-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-12 text-center rounded-2xl text-slate-500">
                            No teams found matching your query.
                        </div>
                    ) : (
                        teams.data.map((team) => (
                            <div
                                key={team.id}
                                className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm hover:border-blue-300 dark:hover:border-blue-900 transition flex flex-col justify-between"
                            >
                                <div className="space-y-4">
                                    {/* Header & Type */}
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border capitalize ${getTypeBadge(team.type)}`}>
                                                {team.type === 'sales' && <TrendingUp className="h-3 w-3" />}
                                                {team.type} Team
                                            </span>
                                            <h3 className="text-base font-bold text-slate-900 dark:text-white mt-2">
                                                {team.name}
                                            </h3>
                                        </div>

                                        <div className="flex items-center gap-1">
                                            <Link
                                                href={route('teams.show', team.id)}
                                                className="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-slate-800 transition"
                                                title="View Team"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                            <Link
                                                href={route('teams.edit', team.id)}
                                                className="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-slate-800 transition"
                                                title="Edit Team"
                                            >
                                                <Edit className="h-4 w-4" />
                                            </Link>
                                            <button
                                                onClick={() => handleDelete(team)}
                                                className="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                                title="Delete Team"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>

                                    {/* Description */}
                                    <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 min-h-[32px]">
                                        {team.description || 'No description provided for this team unit.'}
                                    </p>

                                    {/* Leader Info */}
                                    <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                                        <div className="flex items-center gap-2">
                                            <div className="h-7 w-7 rounded-full bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 font-bold text-[10px] flex items-center justify-center">
                                                {team.leader ? team.leader.name.charAt(0) : '?'}
                                            </div>
                                            <div>
                                                <div className="text-[10px] uppercase font-semibold text-slate-400">Team Leader</div>
                                                <div className="font-semibold text-slate-800 dark:text-slate-200">
                                                    {team.leader ? team.leader.name : 'Unassigned'}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="text-right">
                                            <div className="text-[10px] uppercase font-semibold text-slate-400">Members</div>
                                            <div className="font-bold text-slate-900 dark:text-white">
                                                {team.members_count}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                    <Link
                                        href={route('teams.show', team.id)}
                                        className="text-xs font-semibold text-blue-700 dark:text-blue-400 hover:underline flex items-center gap-1"
                                    >
                                        <span>Manage Roster</span>
                                        <span>→</span>
                                    </Link>
                                    <span className="text-[11px] text-slate-400">
                                        Created {new Date(team.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {/* Pagination */}
                {teams.links && teams.links.length > 3 && (
                    <div className="flex items-center justify-between px-6 py-3.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs">
                        <span className="text-slate-500 dark:text-slate-400">
                            Showing {teams.from || 0} to {teams.to || 0} of {teams.total} teams
                        </span>
                        <div className="flex items-center gap-1">
                            {teams.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url || '#'}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                                        link.active
                                            ? 'bg-blue-700 text-white'
                                            : link.url
                                            ? 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                            : 'text-slate-300 dark:text-slate-600 cursor-not-allowed'
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
