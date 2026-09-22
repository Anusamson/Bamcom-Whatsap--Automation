import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { UsersRound, ArrowLeft, TrendingUp, Shield, Users, Check } from 'lucide-react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Create({ types, eligibleLeaders, eligibleMembers }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        type: 'sales',
        leader_id: '',
        is_active: true,
        member_ids: [],
    });

    const toggleMember = (userId) => {
        const id = parseInt(userId);
        if (data.member_ids.includes(id)) {
            setData('member_ids', data.member_ids.filter((m) => m !== id));
        } else {
            setData('member_ids', [...data.member_ids, id]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('teams.store'));
    };

    return (
        <AuthenticatedLayout header="Create New Team">
            <Head title="Create Team - Bamcom AI CRM" />

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Header Banner */}
                <div className="flex items-center justify-between bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400">
                                <UsersRound className="h-5 w-5" />
                            </span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                Create Team Unit
                            </h1>
                        </div>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Configure team name, functional type (e.g. Sales Team), designate team leadership, and assign initial members.
                        </p>
                    </div>

                    <Link
                        href={route('teams.index')}
                        className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                    >
                        <ArrowLeft className="h-4 w-4 text-slate-500" />
                        <span>Back</span>
                    </Link>
                </div>

                {/* Form */}
                <form onSubmit={submit} className="space-y-6">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
                        <h2 className="text-sm font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-100 dark:border-slate-800">
                            Team Specifications
                        </h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="name" value="Team Name *" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. West Coast Sales Unit"
                                    className="mt-1 block w-full text-xs"
                                    required
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="type" value="Functional Type *" />
                                <select
                                    id="type"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    className="mt-1 block w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 capitalize"
                                    required
                                >
                                    {types.map((t) => (
                                        <option key={t} value={t}>{t} Team</option>
                                    ))}
                                </select>
                                <InputError message={errors.type} className="mt-1" />
                            </div>

                            <div className="md:col-span-2">
                                <InputLabel htmlFor="leader_id" value="Designated Team Leader / Sales Manager" />
                                <select
                                    id="leader_id"
                                    value={data.leader_id}
                                    onChange={(e) => setData('leader_id', e.target.value)}
                                    className="mt-1 block w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600"
                                >
                                    <option value="">No leader assigned</option>
                                    {eligibleLeaders.map((leader) => (
                                        <option key={leader.id} value={leader.id}>
                                            {leader.name} ({leader.role}) - {leader.email}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.leader_id} className="mt-1" />
                            </div>

                            <div className="md:col-span-2">
                                <InputLabel htmlFor="description" value="Team Purpose / Description" />
                                <textarea
                                    id="description"
                                    rows="3"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Core responsibilities, target territory, or operational scope..."
                                    className="mt-1 block w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600"
                                />
                                <InputError message={errors.description} className="mt-1" />
                            </div>
                        </div>
                    </div>

                    {/* Member Selection */}
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h2 className="text-sm font-bold text-slate-900 dark:text-white">Assign Initial Members</h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Selected members will be attached to this team.
                                </p>
                            </div>
                            <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300">
                                {data.member_ids.length} selected
                            </span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto pr-1">
                            {eligibleMembers.map((member) => {
                                const isChecked = data.member_ids.includes(member.id);
                                return (
                                    <label
                                        key={member.id}
                                        className={`flex items-center gap-3 p-3 rounded-xl border cursor-pointer text-xs transition ${
                                            isChecked
                                                ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/30 text-blue-900 dark:text-blue-200'
                                                : 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/60 text-slate-700 dark:text-slate-300'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={isChecked}
                                            onChange={() => toggleMember(member.id)}
                                            className="rounded text-blue-700 focus:ring-blue-600"
                                        />
                                        <div className="truncate">
                                            <div className="font-semibold truncate">{member.name}</div>
                                            <div className="text-[11px] text-slate-500 dark:text-slate-400">{member.role}</div>
                                        </div>
                                    </label>
                                );
                            })}
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href={route('teams.index')}
                            className="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton disabled={processing} className="bg-blue-700 hover:bg-blue-800 px-6 py-2.5 text-xs">
                            {processing ? 'Creating Team...' : 'Create Team'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
