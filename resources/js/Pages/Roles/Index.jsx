import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { 
    Shield, 
    ShieldCheck, 
    Plus, 
    Edit, 
    Trash2, 
    Users, 
    Key, 
    Check, 
    AlertTriangle,
    Info
} from 'lucide-react';

export default function Index({ roles, groupedPermissions }) {
    const { auth, flash } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;

    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const { delete: destroy, processing } = useForm();

    const handleDelete = (role) => {
        if (confirm(`Are you sure you want to delete the role "${role.name}"? This action cannot be undone.`)) {
            destroy(route('roles.destroy', role.id));
        }
    };

    return (
        <AuthenticatedLayout header="Role Management">
            <Head title="Role Management - Bamcom AI CRM" />

            <div className="space-y-6">
                {/* Header & Actions Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400">
                                <Shield className="h-5 w-5" />
                            </span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                Role-Based Access Control
                            </h1>
                        </div>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Configure granular permissions assigned to each organizational role across the CRM.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href={route('permissions.index')}
                            className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                        >
                            <Key className="h-4 w-4 text-slate-500" />
                            <span>View All Permissions</span>
                        </Link>

                        {can('roles.create') && (
                            <Link
                                href={route('roles.create')}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-md shadow-blue-600/25 transition"
                            >
                                <Plus className="h-4 w-4" />
                                <span>Create New Role</span>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Flash Feedback */}
                {flash?.success && (
                    <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-medium flex items-center gap-2">
                        <Check className="h-4 w-4 text-emerald-600" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="p-4 rounded-xl bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 text-xs font-medium flex items-center gap-2">
                        <AlertTriangle className="h-4 w-4 text-red-600" />
                        <span>{flash.error}</span>
                    </div>
                )}

                {/* Roles Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {roles.map((role) => (
                        <div
                            key={role.id}
                            className="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 shadow-sm flex flex-col justify-between hover:border-slate-300 dark:hover:border-slate-700 transition"
                        >
                            <div>
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-2.5">
                                        <div className="h-9 w-9 rounded-lg bg-blue-50 dark:bg-blue-950/60 border border-blue-200/60 dark:border-blue-800/60 flex items-center justify-center text-blue-700 dark:text-blue-400 font-bold text-sm">
                                            {role.name.charAt(0)}
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                                {role.name}
                                            </h3>
                                            <span className="text-[11px] text-slate-400">
                                                ID #{role.id}
                                            </span>
                                        </div>
                                    </div>

                                    {role.name === 'Super Admin' ? (
                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-300 border border-red-200 dark:border-red-800">
                                            Full System Bypass
                                        </span>
                                    ) : role.is_system ? (
                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            Core System
                                        </span>
                                    ) : (
                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                            Custom
                                        </span>
                                    )}
                                </div>

                                {/* Counts */}
                                <div className="mt-4 grid grid-cols-2 gap-2 text-xs">
                                    <div className="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/50 flex items-center gap-2">
                                        <Users className="h-3.5 w-3.5 text-slate-400" />
                                        <span className="text-slate-600 dark:text-slate-300">
                                            <strong>{role.users_count}</strong> {role.users_count === 1 ? 'User' : 'Users'}
                                        </span>
                                    </div>
                                    <div className="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/50 flex items-center gap-2">
                                        <Key className="h-3.5 w-3.5 text-slate-400" />
                                        <span className="text-slate-600 dark:text-slate-300">
                                            <strong>{role.name === 'Super Admin' ? 'All' : role.permissions_count}</strong> Permissions
                                        </span>
                                    </div>
                                </div>

                                {/* Permissions Sample Preview */}
                                <div className="mt-4">
                                    <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1.5">
                                        Granted Permissions
                                    </span>
                                    {role.name === 'Super Admin' ? (
                                        <div className="p-2.5 rounded-lg bg-red-50/50 dark:bg-red-950/30 border border-red-100 dark:border-red-900/40 text-xs text-red-700 dark:text-red-400 flex items-center gap-1.5">
                                            <ShieldCheck className="h-4 w-4 flex-shrink-0" />
                                            <span>Universal bypass: grants all system abilities.</span>
                                        </div>
                                    ) : role.permissions && role.permissions.length > 0 ? (
                                        <div className="flex flex-wrap gap-1 max-h-24 overflow-y-auto pr-1">
                                            {role.permissions.slice(0, 6).map((perm) => (
                                                <span
                                                    key={perm}
                                                    className="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700"
                                                >
                                                    {perm}
                                                </span>
                                            ))}
                                            {role.permissions.length > 6 && (
                                                <span className="px-1.5 py-0.5 rounded text-[10px] font-semibold text-slate-500">
                                                    +{role.permissions.length - 6} more
                                                </span>
                                            )}
                                        </div>
                                    ) : (
                                        <span className="text-xs text-slate-400 italic">No permissions assigned yet.</span>
                                    )}
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                                {can('roles.edit') && (
                                    <Link
                                        href={route('roles.edit', role.id)}
                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 transition"
                                    >
                                        <Edit className="h-3.5 w-3.5" />
                                        <span>Edit Permissions</span>
                                    </Link>
                                )}

                                {can('roles.delete') && !role.is_system && (
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(role)}
                                        disabled={processing}
                                        className="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-950/50 dark:hover:bg-red-900/50 text-xs font-semibold text-red-600 dark:text-red-400 transition"
                                        title="Delete Role"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                        <span>Delete</span>
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
