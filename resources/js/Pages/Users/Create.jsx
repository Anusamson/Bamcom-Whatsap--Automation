import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { UserPlus, ArrowLeft, Shield, UsersRound, Mail, Lock, User, Phone, Briefcase, Building, FileText, CheckCircle2 } from 'lucide-react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Create({ roles, teams, salesTeams, statuses }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'Sales Executive',
        status: 'active',
        team_id: '',
        phone: '',
        job_title: '',
        department: '',
        bio: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('users.store'));
    };

    return (
        <AuthenticatedLayout header="Create New User">
            <Head title="Create User - Bamcom AI CRM" />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header Banner */}
                <div className="flex items-center justify-between bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400">
                                <UserPlus className="h-5 w-5" />
                            </span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                Provision New CRM User
                            </h1>
                        </div>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Set up account credentials, assign organizational role, team membership, and contact profile.
                        </p>
                    </div>

                    <Link
                        href={route('users.index')}
                        className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                    >
                        <ArrowLeft className="h-4 w-4 text-slate-500" />
                        <span>Back to Users</span>
                    </Link>
                </div>

                {/* Main Form Card */}
                <form onSubmit={submit} className="space-y-6">
                    {/* 1. Account Credentials */}
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
                        <div className="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                            <User className="h-4 w-4 text-blue-600" />
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white">Account Information</h2>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="name" value="Full Name *" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Alex Morgan"
                                    className="mt-1 block w-full text-xs"
                                    required
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="email" value="Email Address *" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="alex.morgan@bamcom.ai"
                                    className="mt-1 block w-full text-xs"
                                    required
                                />
                                <InputError message={errors.email} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="password" value="Initial Password *" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••"
                                    className="mt-1 block w-full text-xs"
                                    required
                                />
                                <InputError message={errors.password} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="password_confirmation" value="Confirm Password *" />
                                <TextInput
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    placeholder="••••••••"
                                    className="mt-1 block w-full text-xs"
                                    required
                                />
                                <InputError message={errors.password_confirmation} className="mt-1" />
                            </div>
                        </div>
                    </div>

                    {/* 2. Role & Team Assignment */}
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
                        <div className="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                            <Shield className="h-4 w-4 text-blue-600" />
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white">Role & Team Assignment</h2>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <InputLabel htmlFor="role" value="Assigned Role *" />
                                <select
                                    id="role"
                                    value={data.role}
                                    onChange={(e) => setData('role', e.target.value)}
                                    className="mt-1 block w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600"
                                    required
                                >
                                    {roles.map((role) => (
                                        <option key={role} value={role}>{role}</option>
                                    ))}
                                </select>
                                <InputError message={errors.role} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="team_id" value="Primary Team (Sales / Ops)" />
                                <select
                                    id="team_id"
                                    value={data.team_id}
                                    onChange={(e) => setData('team_id', e.target.value)}
                                    className="mt-1 block w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600"
                                >
                                    <option value="">No Team Assigned</option>
                                    {teams.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.name} {t.type === 'sales' ? '(Sales Team)' : `(${t.type})`}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.team_id} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="status" value="Initial Account Status *" />
                                <select
                                    id="status"
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="mt-1 block w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 capitalize"
                                    required
                                >
                                    {statuses.map((s) => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                                <InputError message={errors.status} className="mt-1" />
                            </div>
                        </div>
                    </div>

                    {/* 3. User Profile Details */}
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
                        <div className="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                            <Briefcase className="h-4 w-4 text-blue-600" />
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white">Profile Details (Optional)</h2>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <InputLabel htmlFor="phone" value="Phone Number" />
                                <TextInput
                                    id="phone"
                                    type="text"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    placeholder="+1 (555) 012-3456"
                                    className="mt-1 block w-full text-xs"
                                />
                                <InputError message={errors.phone} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="job_title" value="Job Title / Designation" />
                                <TextInput
                                    id="job_title"
                                    type="text"
                                    value={data.job_title}
                                    onChange={(e) => setData('job_title', e.target.value)}
                                    placeholder="e.g. Senior Account Executive"
                                    className="mt-1 block w-full text-xs"
                                />
                                <InputError message={errors.job_title} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="department" value="Department" />
                                <TextInput
                                    id="department"
                                    type="text"
                                    value={data.department}
                                    onChange={(e) => setData('department', e.target.value)}
                                    placeholder="e.g. Enterprise Sales"
                                    className="mt-1 block w-full text-xs"
                                />
                                <InputError message={errors.department} className="mt-1" />
                            </div>

                            <div className="md:col-span-3">
                                <InputLabel htmlFor="bio" value="Professional Bio / Notes" />
                                <textarea
                                    id="bio"
                                    rows="3"
                                    value={data.bio}
                                    onChange={(e) => setData('bio', e.target.value)}
                                    placeholder="Brief background or internal notes..."
                                    className="mt-1 block w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600"
                                />
                                <InputError message={errors.bio} className="mt-1" />
                            </div>
                        </div>
                    </div>

                    {/* Actions Toolbar */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href={route('users.index')}
                            className="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton disabled={processing} className="bg-blue-700 hover:bg-blue-800 px-6 py-2.5 text-xs">
                            {processing ? 'Creating User...' : 'Create User'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
