import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Key, Shield, Search, ArrowLeft, Layers, CheckCircle2 } from 'lucide-react';
import TextInput from '@/Components/TextInput';

export default function Index({ permissions, groupedCategories, filters }) {
    const [search, setSearch] = useState(filters?.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('permissions.index'),
            { search: search },
            { preserveState: true, replace: true }
        );
    };

    // Group the permissions returned from server by their group property
    const grouped = permissions.reduce((acc, perm) => {
        const group = perm.group || 'General';
        if (!acc[group]) acc[group] = [];
        acc[group].push(perm);
        return acc;
    }, {});

    return (
        <AuthenticatedLayout header="Permissions Directory">
            <Head title="Permissions Directory - Bamcom AI CRM" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400">
                                <Key className="h-5 w-5" />
                            </span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                Granular Permissions Directory
                            </h1>
                        </div>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Browse all functional permissions and inspect which organizational roles possess each ability.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href={route('roles.index')}
                            className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-md shadow-blue-600/25 transition"
                        >
                            <Shield className="h-4 w-4" />
                            <span>Manage Roles</span>
                        </Link>
                    </div>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="max-w-md">
                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <TextInput
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Filter permissions by keyword (e.g. leads, edit)..."
                            className="pl-9 w-full text-xs"
                        />
                    </div>
                </form>

                {/* Grouped Permissions Listing */}
                <div className="space-y-6">
                    {Object.entries(grouped).map(([category, perms]) => (
                        <div
                            key={category}
                            className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden shadow-sm"
                        >
                            <div className="px-6 py-4 bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <Layers className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                    <h2 className="text-sm font-bold text-slate-900 dark:text-white">
                                        {category}
                                    </h2>
                                </div>
                                <span className="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                    {perms.length} {perms.length === 1 ? 'Permission' : 'Permissions'}
                                </span>
                            </div>

                            <div className="divide-y divide-slate-100 dark:divide-slate-800/80">
                                {perms.map((perm) => (
                                    <div
                                        key={perm.id}
                                        className="px-6 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition"
                                    >
                                        <div className="space-y-0.5">
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-bold text-slate-900 dark:text-white">
                                                    {perm.label}
                                                </span>
                                                <code className="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono">
                                                    {perm.name}
                                                </code>
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="text-[10px] text-slate-400 font-medium mr-1">
                                                Granted to:
                                            </span>
                                            {perm.roles && perm.roles.length > 0 ? (
                                                perm.roles.map((role) => (
                                                    <span
                                                        key={role}
                                                        className={`text-[10px] font-semibold px-2 py-0.5 rounded-full border ${
                                                            role === 'Super Admin'
                                                                ? 'bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-300 border-red-200 dark:border-red-800'
                                                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700'
                                                        }`}
                                                    >
                                                        {role}
                                                    </span>
                                                ))
                                            ) : (
                                                <span className="text-[11px] text-slate-400 italic">
                                                    Unassigned
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}

                    {Object.keys(grouped).length === 0 && (
                        <div className="p-8 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
                            <Key className="h-8 w-8 mx-auto text-slate-400 mb-2" />
                            <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                                No permissions matched "{search}"
                            </h3>
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    router.get(route('permissions.index'));
                                }}
                                className="mt-2 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                Clear search filter
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
