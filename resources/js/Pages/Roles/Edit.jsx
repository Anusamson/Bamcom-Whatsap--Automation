import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Shield, ArrowLeft, Check, AlertCircle } from 'lucide-react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Edit({ role, groupedPermissions }) {
    const { data, setData, put, processing, errors } = useForm({
        name: role.name,
        permissions: role.permissions || [],
    });

    const isSuperAdmin = role.name === 'Super Admin';

    const togglePermission = (permValue) => {
        if (isSuperAdmin) return;
        if (data.permissions.includes(permValue)) {
            setData('permissions', data.permissions.filter((p) => p !== permValue));
        } else {
            setData('permissions', [...data.permissions, permValue]);
        }
    };

    const selectGroup = (groupPerms) => {
        if (isSuperAdmin) return;
        const groupValues = groupPerms.map((p) => p.value);
        const allSelected = groupValues.every((val) => data.permissions.includes(val));

        if (allSelected) {
            setData('permissions', data.permissions.filter((p) => !groupValues.includes(p)));
        } else {
            const newPerms = Array.from(new Set([...data.permissions, ...groupValues]));
            setData('permissions', newPerms);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('roles.update', role.id));
    };

    return (
        <AuthenticatedLayout header={`Edit Role: ${role.name}`}>
            <Head title={`Edit Role - ${role.name}`} />

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
                            Edit Role: {role.name}
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Update permission assignments for this organizational role.
                        </p>
                    </div>
                </div>

                {isSuperAdmin && (
                    <div className="p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 text-red-800 dark:text-red-300 text-xs flex items-center gap-3">
                        <AlertCircle className="h-5 w-5 flex-shrink-0 text-red-600" />
                        <div>
                            <strong>Super Admin Protected:</strong> This role universally possesses all system permissions and cannot have its permissions restricted.
                        </div>
                    </div>
                )}

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
                                disabled={isSuperAdmin}
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
                                    Assigned Permissions
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Selected: <strong>{isSuperAdmin ? 'All System Abilities' : data.permissions.length}</strong> permissions
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
                                            {!isSuperAdmin && (
                                                <button
                                                    type="button"
                                                    onClick={() => selectGroup(perms)}
                                                    className="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1"
                                                >
                                                    {isGroupAllSelected ? 'Deselect Group' : 'Select All in Group'}
                                                </button>
                                            )}
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                                            {perms.map((perm) => {
                                                const isChecked = isSuperAdmin || data.permissions.includes(perm.value);
                                                return (
                                                    <label
                                                        key={perm.value}
                                                        className={`flex items-start gap-2.5 p-2.5 rounded-lg border text-xs cursor-pointer transition select-none ${
                                                            isChecked
                                                                ? 'bg-blue-50 dark:bg-blue-950/60 border-blue-300 dark:border-blue-800 text-blue-900 dark:text-blue-200'
                                                                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-slate-300'
                                                        } ${isSuperAdmin ? 'opacity-90 pointer-events-none' : ''}`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={isChecked}
                                                            onChange={() => togglePermission(perm.value)}
                                                            disabled={isSuperAdmin}
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
                        {!isSuperAdmin && (
                            <PrimaryButton disabled={processing} className="bg-blue-600 hover:bg-blue-500">
                                Save Changes
                            </PrimaryButton>
                        )}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
