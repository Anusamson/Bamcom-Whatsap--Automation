import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Workflow,
    ArrowLeft,
    Clock,
    Users,
    CheckCircle2,
    XCircle,
    Play,
    Pause,
    Plus,
    MessageSquare,
    CheckSquare,
    TrendingUp,
    Tag,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    Edit,
    AlertCircle,
    UserX,
    UserCheck,
    Eye
} from 'lucide-react';
import { useState } from 'react';

export default function Show({
    sequence,
    enrollments = { data: [], links: [] },
    metrics = {},
    availableContacts = []
}) {
    const [isEnrollModalOpen, setIsEnrollModalOpen] = useState(false);
    const [activeTab, setActiveTab] = useState('steps'); // 'steps' | 'enrollments'

    const enrollForm = useForm({
        contact_id: '',
        lead_id: ''
    });

    const handleEnroll = (e) => {
        e.preventDefault();
        enrollForm.post(route('sequences.enroll', sequence.id), {
            onSuccess: () => {
                setIsEnrollModalOpen(false);
                enrollForm.reset();
            }
        });
    };

    const handleUnenroll = (enrollment) => {
        if (confirm(`Unenroll ${enrollment.contact?.first_name} from this sequence?`)) {
            router.post(route('sequences.enrollments.unenroll', enrollment.id), {
                reason: 'Unenrolled by user from sequence dashboard'
            });
        }
    };

    const handleOptOut = (contact) => {
        if (confirm(`Opt-out ${contact?.first_name} from all communications? This will immediately cancel all active sequences.`)) {
            router.post(route('contacts.opt-out', contact.id), {
                reason: 'Customer requested opt-out'
            });
        }
    };

    const handleOptIn = (contact) => {
        router.post(route('contacts.opt-in', contact.id));
    };

    const toggleStatus = () => {
        router.post(route('sequences.toggle', sequence.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Sequence: ${sequence.name}`} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center space-x-3">
                        <Link
                            href={route('sequences.index')}
                            className="p-2 text-gray-500 hover:text-gray-900 dark:hover:text-white rounded-xl bg-gray-100 dark:bg-gray-800 transition"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <span
                                    className={`px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider ${
                                        sequence.status === 'active'
                                            ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
                                            : sequence.status === 'paused'
                                            ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'
                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
                                    }`}
                                >
                                    {sequence.status}
                                </span>
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    {sequence.name}
                                </h1>
                            </div>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {sequence.description || 'No description provided.'}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={toggleStatus}
                            className={`inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border transition ${
                                sequence.status === 'active'
                                    ? 'bg-amber-50 border-amber-200 text-amber-700 hover:bg-amber-100'
                                    : 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100'
                            }`}
                        >
                            {sequence.status === 'active' ? (
                                <>
                                    <Pause className="w-3.5 h-3.5" />
                                    <span>Pause Sequence</span>
                                </>
                            ) : (
                                <>
                                    <Play className="w-3.5 h-3.5" />
                                    <span>Activate Sequence</span>
                                </>
                            )}
                        </button>

                        <button
                            onClick={() => setIsEnrollModalOpen(true)}
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                        >
                            <Plus className="w-4 h-4" />
                            <span>Enroll Contact</span>
                        </button>

                        <Link
                            href={route('sequences.edit', sequence.id)}
                            className="p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition border border-gray-200 dark:border-gray-700"
                            title="Edit Sequence"
                        >
                            <Edit className="w-4 h-4" />
                        </Link>
                    </div>
                </div>

                {/* Metrics Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Total Enrolled
                            </span>
                            <Users className="w-4 h-4 text-purple-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                            {metrics.total_enrollments ?? 0}
                        </p>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Active Cadences
                            </span>
                            <Clock className="w-4 h-4 text-indigo-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                            {metrics.active_enrollments ?? 0}
                        </p>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Completed
                            </span>
                            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {metrics.completed_enrollments ?? 0}
                        </p>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Cancelled / Exited
                            </span>
                            <ShieldAlert className="w-4 h-4 text-rose-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {metrics.cancelled_enrollments ?? 0}
                        </p>
                    </div>
                </div>

                {/* Guardrails Info Banner */}
                <div className="p-4 rounded-2xl bg-gradient-to-r from-purple-50 via-indigo-50 to-blue-50 dark:from-purple-950/20 dark:via-indigo-950/20 dark:to-blue-950/20 border border-purple-100 dark:border-purple-900/30">
                    <h3 className="text-xs font-bold uppercase tracking-wider text-purple-900 dark:text-purple-300 flex items-center gap-1.5 mb-2">
                        <ShieldCheck className="w-4 h-4 text-purple-600" />
                        Enforced Pre-Execution Guardrails (5 Checks)
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs text-gray-700 dark:text-gray-300">
                        <div className="flex items-start gap-1.5 bg-white/70 dark:bg-gray-800/70 p-2.5 rounded-xl border border-purple-100/50">
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" />
                            <span>1. Lead remains active (not lost or archived)</span>
                        </div>
                        <div className="flex items-start gap-1.5 bg-white/70 dark:bg-gray-800/70 p-2.5 rounded-xl border border-purple-100/50">
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" />
                            <span>2. Customer has not opted out</span>
                        </div>
                        <div className="flex items-start gap-1.5 bg-white/70 dark:bg-gray-800/70 p-2.5 rounded-xl border border-purple-100/50">
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" />
                            <span>3. Deal is not already won</span>
                        </div>
                        <div className="flex items-start gap-1.5 bg-white/70 dark:bg-gray-800/70 p-2.5 rounded-xl border border-purple-100/50">
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" />
                            <span>4. Sequence is not cancelled</span>
                        </div>
                        <div className="flex items-start gap-1.5 bg-white/70 dark:bg-gray-800/70 p-2.5 rounded-xl border border-purple-100/50">
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" />
                            <span>5. Message remains applicable</span>
                        </div>
                    </div>
                </div>

                {/* Navigation Tabs */}
                <div className="border-b border-gray-200 dark:border-gray-700 flex gap-4">
                    <button
                        onClick={() => setActiveTab('steps')}
                        className={`pb-3 text-sm font-semibold border-b-2 transition ${
                            activeTab === 'steps'
                                ? 'border-purple-600 text-purple-600 dark:text-purple-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                        }`}
                    >
                        Cadence Steps ({sequence.steps?.length ?? 0})
                    </button>
                    <button
                        onClick={() => setActiveTab('enrollments')}
                        className={`pb-3 text-sm font-semibold border-b-2 transition ${
                            activeTab === 'enrollments'
                                ? 'border-purple-600 text-purple-600 dark:text-purple-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                        }`}
                    >
                        Enrolled Contacts ({enrollments.data?.length ?? 0})
                    </button>
                </div>

                {/* Tab 1: Cadence Steps View */}
                {activeTab === 'steps' && (
                    <div className="space-y-4">
                        {sequence.steps?.length > 0 ? (
                            sequence.steps.map((step) => (
                                <div
                                    key={step.id}
                                    className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 p-5 flex flex-col md:flex-row md:items-start gap-4"
                                >
                                    <div className="w-9 h-9 rounded-full bg-purple-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
                                        {step.step_number}
                                    </div>

                                    <div className="flex-1 space-y-3">
                                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                            <h3 className="text-base font-bold text-gray-900 dark:text-white">
                                                {step.name || `Step ${step.step_number}`}
                                            </h3>
                                            <span className="text-xs font-medium text-gray-500 flex items-center gap-1">
                                                <Clock className="w-3.5 h-3.5 text-purple-500" />
                                                Delay: {step.delay_minutes} minutes
                                            </span>
                                        </div>

                                        {/* Actions Grid */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                            {/* WhatsApp Message */}
                                            {step.whatsapp_config?.message && (
                                                <div className="p-3 rounded-xl bg-purple-50/50 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/30 space-y-1">
                                                    <span className="font-bold text-purple-700 dark:text-purple-300 flex items-center gap-1 uppercase tracking-wider text-[10px]">
                                                        <MessageSquare className="w-3.5 h-3.5" />
                                                        WhatsApp Outbound
                                                    </span>
                                                    <p className="text-gray-700 dark:text-gray-300 italic">
                                                        "{step.whatsapp_config.message}"
                                                    </p>
                                                </div>
                                            )}

                                            {/* Task */}
                                            {step.task_config?.title && (
                                                <div className="p-3 rounded-xl bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/30 space-y-1">
                                                    <span className="font-bold text-blue-700 dark:text-blue-300 flex items-center gap-1 uppercase tracking-wider text-[10px]">
                                                        <CheckSquare className="w-3.5 h-3.5" />
                                                        Task Created
                                                    </span>
                                                    <p className="text-gray-700 dark:text-gray-300 font-medium">
                                                        {step.task_config.title}
                                                    </p>
                                                    <span className="text-[10px] text-gray-500 block">
                                                        Due in {step.task_config.due_in_days || 1} day(s)
                                                    </span>
                                                </div>
                                            )}

                                            {/* Stage Change */}
                                            {step.stage_change_config?.stage_id && (
                                                <div className="p-3 rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/30 space-y-1">
                                                    <span className="font-bold text-amber-700 dark:text-amber-300 flex items-center gap-1 uppercase tracking-wider text-[10px]">
                                                        <TrendingUp className="w-3.5 h-3.5" />
                                                        Pipeline Movement
                                                    </span>
                                                    <p className="text-gray-700 dark:text-gray-300">
                                                        Target Stage ID: #{step.stage_change_config.stage_id}
                                                    </p>
                                                </div>
                                            )}

                                            {/* Tag */}
                                            {step.tag_config?.add_tags && (
                                                <div className="p-3 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 space-y-1">
                                                    <span className="font-bold text-emerald-700 dark:text-emerald-300 flex items-center gap-1 uppercase tracking-wider text-[10px]">
                                                        <Tag className="w-3.5 h-3.5" />
                                                        Attach Tags
                                                    </span>
                                                    <div className="flex flex-wrap gap-1 mt-1">
                                                        {step.tag_config.add_tags.map((t, idx) => (
                                                            <span key={idx} className="px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 text-[10px] font-semibold">
                                                                {t}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="text-center py-10 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                                <p className="text-sm text-gray-500">No steps defined for this sequence yet.</p>
                            </div>
                        )}
                    </div>
                )}

                {/* Tab 2: Enrollments View */}
                {activeTab === 'enrollments' && (
                    <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                                <thead className="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th className="px-5 py-3">Contact</th>
                                        <th className="px-5 py-3">Phone</th>
                                        <th className="px-5 py-3">Progress</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3">Next Due</th>
                                        <th className="px-5 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    {enrollments.data?.length > 0 ? (
                                        enrollments.data.map((enr) => (
                                            <tr key={enr.id} className="hover:bg-gray-50/50 dark:hover:bg-gray-900/30">
                                                <td className="px-5 py-4 font-medium text-gray-900 dark:text-white">
                                                    <div>
                                                        <span>{enr.contact?.first_name} {enr.contact?.last_name}</span>
                                                        {enr.contact?.has_opted_out && (
                                                            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">
                                                                OPTED OUT
                                                            </span>
                                                        )}
                                                    </div>
                                                    {enr.lead && (
                                                        <span className="text-[11px] text-gray-400 block">
                                                            Lead: {enr.lead.title}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 text-xs font-mono">
                                                    {enr.contact?.phone}
                                                </td>
                                                <td className="px-5 py-4 text-xs">
                                                    Step {enr.current_step_number} of {sequence.steps?.length ?? 0}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span
                                                        className={`inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold uppercase ${
                                                            enr.status === 'active'
                                                                ? 'bg-emerald-100 text-emerald-800'
                                                                : enr.status === 'completed'
                                                                ? 'bg-blue-100 text-blue-800'
                                                                : 'bg-rose-100 text-rose-800'
                                                        }`}
                                                    >
                                                        {enr.status}
                                                    </span>
                                                    {enr.cancellation_reason && (
                                                        <span className="block text-[10px] text-rose-500 mt-0.5 line-clamp-1" title={enr.cancellation_reason}>
                                                            {enr.cancellation_reason}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 text-xs text-gray-500">
                                                    {enr.next_step_due_at ? new Date(enr.next_step_due_at).toLocaleString() : '—'}
                                                </td>
                                                <td className="px-5 py-4 text-right space-x-2">
                                                    {enr.status === 'active' && (
                                                        <button
                                                            onClick={() => handleUnenroll(enr)}
                                                            className="text-xs font-semibold text-rose-600 hover:underline"
                                                        >
                                                            Unenroll
                                                        </button>
                                                    )}
                                                    {enr.contact && !enr.contact.has_opted_out ? (
                                                        <button
                                                            onClick={() => handleOptOut(enr.contact)}
                                                            className="text-xs text-gray-500 hover:text-rose-600"
                                                            title="Opt out contact"
                                                        >
                                                            <UserX className="w-3.5 h-3.5 inline" />
                                                        </button>
                                                    ) : enr.contact && (
                                                        <button
                                                            onClick={() => handleOptIn(enr.contact)}
                                                            className="text-xs text-emerald-600 hover:underline"
                                                            title="Opt in contact"
                                                        >
                                                            Opt In
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="6" className="text-center py-8 text-xs text-gray-500">
                                                No contacts currently enrolled in this sequence.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Enroll Contact Modal */}
                {isEnrollModalOpen && (
                    <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
                            <h3 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Users className="w-5 h-5 text-purple-600" />
                                Enroll Contact into Sequence
                            </h3>

                            <form onSubmit={handleEnroll} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                        Select Contact *
                                    </label>
                                    <select
                                        required
                                        value={enrollForm.data.contact_id}
                                        onChange={(e) => enrollForm.setData('contact_id', e.target.value)}
                                        className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white"
                                    >
                                        <option value="">Select a contact...</option>
                                        {availableContacts.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.first_name} {c.last_name} ({c.phone})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="flex items-center justify-end gap-2 pt-3">
                                    <button
                                        type="button"
                                        onClick={() => setIsEnrollModalOpen(false)}
                                        className="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold rounded-xl"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={enrollForm.processing}
                                        className="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-xl disabled:opacity-50"
                                    >
                                        {enrollForm.processing ? 'Enrolling...' : 'Enroll Now'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
