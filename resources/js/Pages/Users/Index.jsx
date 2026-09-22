import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    Users, 
    UserPlus, 
    Search, 
    Filter, 
    Edit, 
    Trash2, 
    Eye, 
    UserCheck, 
    UserX, 
    Shield, 
    UsersRound, 
    CheckCircle2, 
    XCircle,
    Activity,
    RotateCcw
} from 'lucide-react';
import { useState } from 'react';

export default function Index({ users, teams, roles, statuses, filters }) {
    const { auth, flash } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;

    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const [search, setSearch] = useState(filters.search || '');
    const [role, setRole] = useState(filters.role || '');
    const [teamId, setTeamId] = useState(filters.team_id || '');
    const [status, setStatus] = useState(filters.status || '');

    const handleFilter = (e) => {
        e?.preventDefault();
        router.get(route('users.index'), {
            search: search || undefined,
            role: role || undefined,
            team_id: teamId || undefined,
            status: status || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        setRole('');
        setTeamId('');
        setStatus('');
        router.get(route('users.index'));
    };

    const handleToggleStatus = (user) => {
        const action = user.status === 'active' ? 'disable' : 'activate';
        if (confirm(`Are you sure you want to ${action} user "${user.name}"?`)) {
            router.patch(route('users.toggle-status', user.id));
        }
    };

    const handleDelete = (user) => {
        if (confirm(`Are you sure you want to delete user "${user.name}"? If this user has recorded CRM activity, their account will be deactivated instead of permanently deleted to preserve audit trails.`)) {
            router.delete(route('users.destroy', user.id));
        }
    };

    const getStatusBadge = (statusVal) => {
        switch (statusVal) {
            case 'active':
                return 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
            case 'inactive':
                return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700';
            case 'suspended':
                return 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 border-red-200 dark:border-red-800';
            default:
                return 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        }
    };

    return (
        <AuthenticatedLayout header="User Management">
            <Head title="User Management - Bamcom AI CRM" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400">
                                <Users className="h-5 w-5" />
                            </span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                Users Directory
                            </h1>
                        </div>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Manage CRM user profiles, roles, teams, and access status. Total users: <span className="font-semibold text-slate-900 dark:text-white">{users.total}</span>
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        {can('teams.view') && (
                            <Link
                                href={route('teams.index')}
                                className="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                            >
                                <UsersRound className="h-4 w-4 text-blue-600" />
                                <span>Manage Teams</span>
                            </Link>
                        )}
                        {can('users.create') && (
                            <Link
                                href={route('users.create')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold shadow-sm transition"
                            >
                                <UserPlus className="h-4 w-4" />
                                <span>Create User</span>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Filters & Search Toolbar */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-sm">
                    <form onSubmit={handleFilter} className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                        {/* Search */}
                        <div className="relative md:col-span-2">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by name, email, phone, job title..."
                                className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none"
                            />
                        </div>

                        {/* Role Filter */}
                        <div>
                            <select
                                value={role}
                                onChange={(e) => setRole(e.target.value)}
                                className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none"
                            >
                                <option value="">All Roles</option>
                                {roles.map((r) => (
                                    <option key={r} value={r}>{r}</option>
                                ))}
                            </select>
                        </div>

                        {/* Team Filter */}
                        <div>
                            <select
                                value={teamId}
                                onChange={(e) => setTeamId(e.target.value)}
                                className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none"
                            >
                                <option value="">All Teams</option>
                                {teams.map((t) => (
                                    <option key={t.id} value={t.id}>{t.name}</option>
                                ))}
                            </select>
                        </div>

                        {/* Status Filter & Filter Buttons */}
                        <div className="flex items-center gap-2">
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none capitalize"
                            >
                                <option value="">All Statuses</option>
                                {statuses.map((s) => (
                                    <option key={s} value={s}>{s}</option>
                                ))}
                            </select>

                            <button
                                type="submit"
                                className="px-3 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold transition shrink-0"
                            >
                                Filter
                            </button>
                            <button
                                type="button"
                                onClick={handleReset}
                                title="Reset Filters"
                                className="p-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition shrink-0"
                            >
                                <RotateCcw className="h-4 w-4" />
                            </button>
                        </div>
                    </form>
                </div>

                {/* Users Table */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">User</th>
                                    <th className="px-6 py-3.5">Role</th>
                                    <th className="px-6 py-3.5">Assigned Team</th>
                                    <th className="px-6 py-3.5">Status</th>
                                    <th className="px-6 py-3.5">Phone / Department</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-slate-500">
                                            No users found matching your search criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                            {/* User Info */}
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="h-9 w-9 rounded-full bg-blue-600/10 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 font-bold flex items-center justify-center text-xs border border-blue-200 dark:border-blue-900 shrink-0">
                                                        {user.name.charAt(0).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <Link 
                                                            href={route('users.show', user.id)}
                                                            className="font-semibold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition"
                                                        >
                                                            {user.name}
                                                        </Link>
                                                        <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                            {user.email}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Role */}
                                            <td className="px-6 py-4">
                                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                                    <Shield className="h-3 w-3" />
                                                    {user.role}
                                                </span>
                                            </td>

                                            {/* Team */}
                                            <td className="px-6 py-4">
                                                {user.team ? (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                        <UsersRound className="h-3 w-3 text-emerald-600" />
                                                        {user.team.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-[11px] text-slate-400 italic">No team assigned</span>
                                                )}
                                            </td>

                                            {/* Status */}
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border capitalize ${getStatusBadge(user.status)}`}>
                                                    <span className={`h-1.5 w-1.5 rounded-full ${user.status === 'active' ? 'bg-emerald-500' : 'bg-red-500'}`}></span>
                                                    {user.status}
                                                </span>
                                            </td>

                                            {/* Profile Meta */}
                                            <td className="px-6 py-4 text-slate-600 dark:text-slate-300">
                                                <div className="font-medium text-slate-800 dark:text-slate-200">
                                                    {user.profile?.job_title || '—'}
                                                </div>
                                                <div className="text-[11px] text-slate-400">
                                                    {user.profile?.department || user.profile?.phone || 'No department'}
                                                </div>
                                            </td>

                                            {/* Actions */}
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Link
                                                        href={route('users.show', user.id)}
                                                        title="View Profile"
                                                        className="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-slate-800 transition"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>

                                                    {can('users.edit') && (
                                                        <Link
                                                            href={route('users.edit', user.id)}
                                                            title="Edit User"
                                                            className="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-slate-800 transition"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Link>
                                                    )}

                                                    {can('users.disable') && !user.is_super_admin && user.id !== auth.user?.id && (
                                                        <button
                                                            onClick={() => handleToggleStatus(user)}
                                                            title={user.status === 'active' ? 'Disable Account' : 'Activate Account'}
                                                            className={`p-1.5 rounded-lg transition ${
                                                                user.status === 'active'
                                                                    ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40'
                                                                    : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                                                            }`}
                                                        >
                                                            {user.status === 'active' ? (
                                                                <UserX className="h-4 w-4" />
                                                            ) : (
                                                                <UserCheck className="h-4 w-4" />
                                                            )}
                                                        </button>
                                                    )}

                                                    {can('users.delete') && !user.is_super_admin && user.id !== auth.user?.id && (
                                                        <button
                                                            onClick={() => handleDelete(user)}
                                                            title="Delete User"
                                                            className="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {users.links && users.links.length > 3 && (
                        <div className="flex items-center justify-between px-6 py-3.5 border-t border-slate-200 dark:border-slate-800 text-xs bg-slate-50/50 dark:bg-slate-900/50">
                            <span className="text-slate-500 dark:text-slate-400">
                                Showing {users.from || 0} to {users.to || 0} of {users.total} entries
                            </span>
                            <div className="flex items-center gap-1">
                                {users.links.map((link, i) => (
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
            </div>
        </AuthenticatedLayout>
    );
}
