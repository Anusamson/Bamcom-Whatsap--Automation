import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { 
    UsersRound, 
    ArrowLeft, 
    Edit, 
    Trash2, 
    TrendingUp, 
    User, 
    UserPlus, 
    Mail, 
    Shield, 
    UserMinus,
    CheckCircle2
} from 'lucide-react';
import { useState } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Show({ team, eligibleMembers, canEdit, canDelete, canAssign }) {
    const [selectedUserId, setSelectedUserId] = useState('');
    const [roleInTeam, setRoleInTeam] = useState('member');

    const handleAssignMember = (e) => {
        e.preventDefault();
        if (!selectedUserId) return;

        router.post(route('teams.members.assign', team.id), {
            user_ids: [parseInt(selectedUserId)],
            role_in_team: roleInTeam,
        }, {
            onSuccess: () => {
                setSelectedUserId('');
                setRoleInTeam('member');
            },
        });
    };

    const handleRemoveMember = (member) => {
        if (confirm(`Remove "${member.name}" from team "${team.name}"?`)) {
            router.delete(route('teams.members.remove', [team.id, member.id]));
        }
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete team "${team.name}"? Members will be detached.`)) {
            router.delete(route('teams.destroy', team.id));
        }
    };

    return (
        <AuthenticatedLayout header={`Team: ${team.name}`}>
            <Head title={`${team.name} - Team Roster - Bamcom AI CRM`} />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="h-14 w-14 rounded-2xl bg-blue-700 text-white font-bold flex items-center justify-center text-xl shadow-md shrink-0">
                            <UsersRound className="h-7 w-7" />
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                    {team.name}
                                </h1>
                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 capitalize">
                                    {team.type === 'sales' && <TrendingUp className="h-3 w-3" />}
                                    {team.type} Team
                                </span>
                            </div>
                            <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                                {team.description || 'No description provided for this team unit.'}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {canEdit && (
                            <Link
                                href={route('teams.edit', team.id)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold transition"
                            >
                                <Edit className="h-3.5 w-3.5" />
                                <span>Edit Team</span>
                            </Link>
                        )}
                        <Link
                            href={route('teams.index')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                        >
                            <ArrowLeft className="h-3.5 w-3.5 text-slate-500" />
                            <span>Back</span>
                        </Link>
                    </div>
                </div>

                {/* Team Leadership Card */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
                    <h2 className="text-sm font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <span>Team Leadership</span>
                        <span className="text-xs font-normal text-slate-500">
                            Total Roster: <span className="font-bold text-slate-900 dark:text-white">{team.members.length}</span> members
                        </span>
                    </h2>

                    {team.leader ? (
                        <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="h-10 w-10 rounded-full bg-blue-600 text-white font-bold text-sm flex items-center justify-center">
                                    {team.leader.name.charAt(0)}
                                </div>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Link
                                            href={route('users.show', team.leader.id)}
                                            className="font-bold text-slate-900 dark:text-white hover:underline text-xs"
                                        >
                                            {team.leader.name}
                                        </Link>
                                        <span className="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300">
                                            Team Leader
                                        </span>
                                    </div>
                                    <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                        {team.leader.email} • {team.leader.profile?.job_title || team.leader.role}
                                    </div>
                                </div>
                            </div>

                            <Link
                                href={route('users.show', team.leader.id)}
                                className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition"
                            >
                                View Profile
                            </Link>
                        </div>
                    ) : (
                        <div className="p-4 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 text-center text-xs text-slate-500">
                            No designated team leader assigned yet. Edit team to assign a leader.
                        </div>
                    )}
                </div>

                {/* Team Members Roster */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <div className="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white">
                                Assigned Team Members
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Personnel actively assigned to this operational unit.
                            </p>
                        </div>

                        {/* Add Member Bar */}
                        {canAssign && eligibleMembers.length > 0 && (
                            <form onSubmit={handleAssignMember} className="flex items-center gap-2">
                                <select
                                    value={selectedUserId}
                                    onChange={(e) => setSelectedUserId(e.target.value)}
                                    className="px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600"
                                >
                                    <option value="">Select User to Add...</option>
                                    {eligibleMembers.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name} ({user.role})
                                        </option>
                                    ))}
                                </select>

                                <select
                                    value={roleInTeam}
                                    onChange={(e) => setRoleInTeam(e.target.value)}
                                    className="px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 capitalize"
                                >
                                    <option value="member">Member</option>
                                    <option value="specialist">Specialist</option>
                                    <option value="leader">Leader</option>
                                </select>

                                <button
                                    type="submit"
                                    disabled={!selectedUserId}
                                    className="px-3 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 disabled:opacity-50 text-white text-xs font-semibold transition shrink-0"
                                >
                                    Add Member
                                </button>
                            </form>
                        )}
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Member</th>
                                    <th className="px-6 py-3.5">Role in Team</th>
                                    <th className="px-6 py-3.5">System Role</th>
                                    <th className="px-6 py-3.5">Phone / Department</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {team.members.length === 0 ? (
                                    <tr>
                                        <td colSpan="5" className="px-6 py-12 text-center text-slate-500">
                                            No members currently assigned to this team.
                                        </td>
                                    </tr>
                                ) : (
                                    team.members.map((member) => (
                                        <tr key={member.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold flex items-center justify-center text-xs">
                                                        {member.name.charAt(0)}
                                                    </div>
                                                    <div>
                                                        <Link
                                                            href={route('users.show', member.id)}
                                                            className="font-semibold text-slate-900 dark:text-white hover:text-blue-600 transition"
                                                        >
                                                            {member.name}
                                                        </Link>
                                                        <div className="text-[11px] text-slate-500">{member.email}</div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4">
                                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 capitalize">
                                                    {member.pivot?.role_in_team || 'member'}
                                                </span>
                                            </td>

                                            <td className="px-6 py-4">
                                                <span className="text-slate-700 dark:text-slate-300 font-medium">
                                                    {member.role}
                                                </span>
                                            </td>

                                            <td className="px-6 py-4 text-slate-600 dark:text-slate-300">
                                                <div>{member.profile?.job_title || '—'}</div>
                                                <div className="text-[11px] text-slate-400">{member.profile?.department || '—'}</div>
                                            </td>

                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Link
                                                        href={route('users.show', member.id)}
                                                        className="text-xs text-blue-600 hover:underline"
                                                    >
                                                        Profile
                                                    </Link>

                                                    {canAssign && (
                                                        <button
                                                            onClick={() => handleRemoveMember(member)}
                                                            title="Remove Member"
                                                            className="p-1 rounded text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                                        >
                                                            <UserMinus className="h-4 w-4" />
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
                </div>

                {/* Danger Zone */}
                {canDelete && (
                    <div className="bg-white dark:bg-slate-900 border border-red-200 dark:border-red-900/60 p-6 rounded-2xl shadow-sm flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-bold text-red-700 dark:text-red-400">Delete Team Unit</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Disbands this team unit. User accounts assigned to this team will remain intact.
                            </p>
                        </div>
                        <button
                            onClick={handleDelete}
                            className="px-4 py-2 text-xs font-semibold rounded-xl bg-red-600 hover:bg-red-700 text-white transition"
                        >
                            Delete Team
                        </button>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
