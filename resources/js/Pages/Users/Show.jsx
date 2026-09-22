import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { 
    User, 
    ArrowLeft, 
    Shield, 
    UsersRound, 
    Mail, 
    Phone, 
    Briefcase, 
    Building, 
    Calendar, 
    CheckCircle2, 
    XCircle, 
    Edit, 
    Trash2, 
    UserX, 
    UserCheck,
    AlertTriangle,
    FileText,
    Activity
} from 'lucide-react';

export default function Show({ user, hasCrmActivity, canEdit, canDelete, canDisable }) {
    const handleToggleStatus = () => {
        const action = user.status === 'active' ? 'disable' : 'activate';
        if (confirm(`Are you sure you want to ${action} user "${user.name}"?`)) {
            router.patch(route('users.toggle-status', user.id));
        }
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete user "${user.name}"? If this user has recorded CRM activity, their account will be deactivated instead of permanently deleted.`)) {
            router.delete(route('users.destroy', user.id));
        }
    };

    return (
        <AuthenticatedLayout header={`User Profile: ${user.name}`}>
            <Head title={`${user.name} - User Profile - Bamcom AI CRM`} />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="h-16 w-16 rounded-2xl bg-blue-700 text-white font-bold text-2xl flex items-center justify-center shadow-md shrink-0">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                    {user.name}
                                </h1>
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                    <Shield className="h-3 w-3" />
                                    {user.role}
                                </span>
                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border capitalize ${
                                    user.status === 'active'
                                        ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                                        : 'bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-300 border-red-200 dark:border-red-800'
                                }`}>
                                    <span className={`h-1.5 w-1.5 rounded-full ${user.status === 'active' ? 'bg-emerald-500' : 'bg-red-500'}`}></span>
                                    {user.status}
                                </span>
                            </div>
                            <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                                {user.email} • {user.profile?.job_title || 'No job title'}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {canEdit && (
                            <Link
                                href={route('users.edit', user.id)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold transition"
                            >
                                <Edit className="h-3.5 w-3.5" />
                                <span>Edit Profile</span>
                            </Link>
                        )}
                        <Link
                            href={route('users.index')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                        >
                            <ArrowLeft className="h-3.5 w-3.5 text-slate-500" />
                            <span>Back</span>
                        </Link>
                    </div>
                </div>

                {/* Information Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Column 1 & 2: Main Profile Details */}
                    <div className="md:col-span-2 space-y-6">
                        {/* Profile Info */}
                        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                                <User className="h-4 w-4 text-blue-600" />
                                <span>Personal & Professional Information</span>
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                <div>
                                    <div className="text-slate-500 dark:text-slate-400">Email Address</div>
                                    <div className="font-semibold text-slate-900 dark:text-white mt-0.5">{user.email}</div>
                                </div>

                                <div>
                                    <div className="text-slate-500 dark:text-slate-400">Phone Number</div>
                                    <div className="font-semibold text-slate-900 dark:text-white mt-0.5">{user.profile?.phone || '—'}</div>
                                </div>

                                <div>
                                    <div className="text-slate-500 dark:text-slate-400">Job Title</div>
                                    <div className="font-semibold text-slate-900 dark:text-white mt-0.5">{user.profile?.job_title || '—'}</div>
                                </div>

                                <div>
                                    <div className="text-slate-500 dark:text-slate-400">Department</div>
                                    <div className="font-semibold text-slate-900 dark:text-white mt-0.5">{user.profile?.department || '—'}</div>
                                </div>

                                <div>
                                    <div className="text-slate-500 dark:text-slate-400">Timezone</div>
                                    <div className="font-semibold text-slate-900 dark:text-white mt-0.5">{user.profile?.timezone || 'UTC'}</div>
                                </div>

                                <div>
                                    <div className="text-slate-500 dark:text-slate-400">Joined Date</div>
                                    <div className="font-semibold text-slate-900 dark:text-white mt-0.5">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </div>
                                </div>
                            </div>

                            {user.profile?.bio && (
                                <div className="pt-3 border-t border-slate-100 dark:border-slate-800">
                                    <div className="text-slate-500 dark:text-slate-400 text-xs mb-1">About / Bio</div>
                                    <p className="text-xs text-slate-700 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl">
                                        {user.profile.bio}
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Teams Card */}
                        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                                <UsersRound className="h-4 w-4 text-blue-600" />
                                <span>Team Memberships</span>
                            </h2>

                            {user.team ? (
                                <div className="p-4 rounded-xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50/50 dark:bg-emerald-950/20 flex items-center justify-between">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-bold text-slate-900 dark:text-white">
                                                {user.team.name}
                                            </span>
                                            <span className="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200">
                                                Primary Team
                                            </span>
                                        </div>
                                        <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                            {user.team.description || 'No description provided.'}
                                        </div>
                                    </div>
                                    <Link
                                        href={route('teams.show', user.team.id)}
                                        className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-slate-800 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 transition"
                                    >
                                        View Team
                                    </Link>
                                </div>
                            ) : (
                                <div className="text-xs text-slate-500 p-4 border border-dashed border-slate-200 dark:border-slate-800 rounded-xl text-center">
                                    No primary team assigned to this user.
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Column 3: Governance, Activity Safeguard & Quick Actions */}
                    <div className="space-y-6">
                        {/* CRM Activity Deletion Safeguard Card */}
                        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                                <Activity className="h-4 w-4 text-red-600" />
                                <span>Audit & Deletion Status</span>
                            </h2>

                            {hasCrmActivity ? (
                                <div className="space-y-3">
                                    <div className="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-200 text-xs">
                                        <div className="font-bold flex items-center gap-1.5">
                                            <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
                                            <span>Active CRM Records Detected</span>
                                        </div>
                                        <p className="mt-1 text-[11px] leading-relaxed">
                                            This user is associated with CRM activity (leads, customer records, tickets, or team leadership). Permanent hard deletion is blocked by policy to preserve audit integrity.
                                        </p>
                                    </div>

                                    <div className="text-[11px] text-slate-500">
                                        Attempting to delete this account will safely deactivate it instead.
                                    </div>
                                </div>
                            ) : (
                                <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs">
                                    <div className="font-bold flex items-center gap-1.5 text-slate-900 dark:text-white">
                                        <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
                                        <span>No Historical Activity</span>
                                    </div>
                                    <p className="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                                        User has no recorded CRM entities and can be cleanly soft-deleted if needed.
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Account Actions Card */}
                        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-3">
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-100 dark:border-slate-800">
                                Administrative Actions
                            </h2>

                            {canDisable && !user.is_super_admin && (
                                <button
                                    onClick={handleToggleStatus}
                                    className={`w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold border transition ${
                                        user.status === 'active'
                                            ? 'border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/40'
                                            : 'border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                                    }`}
                                >
                                    {user.status === 'active' ? (
                                        <>
                                            <UserX className="h-4 w-4" />
                                            <span>Disable Account Access</span>
                                        </>
                                    ) : (
                                        <>
                                            <UserCheck className="h-4 w-4" />
                                            <span>Activate Account Access</span>
                                        </>
                                    )}
                                </button>
                            )}

                            {canDelete && !user.is_super_admin && (
                                <button
                                    onClick={handleDelete}
                                    className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold border border-red-200 dark:border-red-900 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                >
                                    <Trash2 className="h-4 w-4" />
                                    <span>Delete / Deactivate User</span>
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
