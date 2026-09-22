import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Shield, ArrowLeft, Check, CheckSquare, Square } from 'lucide-react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Create({ groupedPermissions }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        permissions: [],
    });

    const togglePermission = (permValue) => {
        if (data.permissions.includes(permValue)) {
            setData('permissions', data.permissions.filter((p) => p !== permValue));
        } else {
            setData('permissions', [...data.permissions, permValue]);
        }
    };

    const selectGroup = (groupPerms) => {
        const groupValues = groupPerms.map((p) => p.value);
        const allSelected = groupValues.every((val) => data.permissions.includes(val));

        if (allSelected) {
            // Remove group
            setData('permissions', data.permissions.filter((p) => !groupValues.includes(p)));
        } else {
            // Add all
            const newPerms = Array.from(new Set([...data.permissions, ...groupValues]));
            setData('permissions', newPerms);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('roles.store'));
    };

    return (
        <AuthenticatedLayout header="Create Role">
            <Head title="Create New Role - Bamcom AI CRM" />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex items-center gap-3">
                    <Link
                        href={route('roles.index')}
                        className="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                            Create New Role
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Define a custom role title and select granular access privileges.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Role Details Card */}
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                        <div className="max-w-md">
                            <InputLabel htmlFor="name" value="Role Name" />
                            <TextInput
                                id="name"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="e.g. Finance Auditor"
                                required
                            />
                            <InputError message={errors.name} className="mt-2" />
                        </div>
                    </div>

                    {/* Permissions Selector Matrix */}
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h2 className="text-base font-bold text-slate-900 dark:text-white">
                                    Assign Granular Permissions
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Selected: <strong>{data.permissions.length}</strong> permissions
                                </p>
                            </div>
                        </div>

                        <div className="space-y-6">
                            {Object.entries(groupedPermissions).map(([category, perms]) => {
                                const groupValues = perms.map((p) => p.value);
                                const isGroupAllSelected = groupValues.every((val) => data.permissions.includes(val));

                                return (
                                    <div
                                        key={category}
                                        className="rounded-xl border border-slate-200 dark:border-slate-800 p-4 bg-slate-50/50 dark:bg-slate-950/40 space-y-3"
                                    >
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                                {category}
                                            </h3>
                                            <button
                                                type="button"
                                                onClick={() => selectGroup(perms)}
                                                className="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1"
                                            >
                                                {isGroupAllSelected ? 'Deselect Group' : 'Select All in Group'}
                                            </button>
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                                            {perms.map((perm) => {
                                                const isChecked = data.permissions.includes(perm.value);
                                                return (
                                                    <label
                                                        key={perm.value}
                                                        className={`flex items-start gap-2.5 p-2.5 rounded-lg border text-xs cursor-pointer transition select-none ${
                                                            isChecked
                                                                ? 'bg-blue-50 dark:bg-blue-950/60 border-blue-300 dark:border-blue-800 text-blue-900 dark:text-blue-200'
                                                                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-slate-300'
                                                        }`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={isChecked}
                                                            onChange={() => togglePermission(perm.value)}
                                                            className="rounded border-slate-300 text-blue-600 shadow-sm focus:ring-blue-500 mt-0.5"
                                                        />
                                                        <div className="min-w-0">
                                                            <span className="font-semibold block truncate">
                                                                {perm.label}
                                                            </span>
                                                            <span className="text-[10px] text-slate-400 font-mono">
                                                                {perm.value}
                                                            </span>
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href={route('roles.index')}
                            className="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton disabled={processing} className="bg-blue-600 hover:bg-blue-500">
                            Create Role
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
