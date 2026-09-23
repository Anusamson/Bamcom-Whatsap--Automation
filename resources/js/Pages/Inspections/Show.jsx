import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    Building2,
    Calendar,
    CalendarCheck,
    CheckCircle2,
    Clock,
    Edit3,
    FileText,
    History,
    MapPin,
    MessageSquare,
    Phone,
    RefreshCw,
    ShieldAlert,
    User,
    UserCheck,
    X,
    XCircle
} from 'lucide-react';
import { useState } from 'react';

export default function Show({
    inspection,
    activities = [],
    representatives = [],
    statuses = []
}) {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const canEdit = isSuperAdmin || permissions.includes('inspections.edit') || inspection.representative_id === auth.user?.id;

    // Modals state
    const [isOutcomeModalOpen, setIsOutcomeModalOpen] = useState(false);
    const [targetStatus, setTargetStatus] = useState('');
    const [isRescheduleModalOpen, setIsRescheduleModalOpen] = useState(false);
    const [isReassignModalOpen, setIsReassignModalOpen] = useState(false);

    // Form for status update / outcome
    const statusForm = useForm({
        status: '',
        outcome: inspection.outcome || '',
        sales_notes: inspection.sales_notes || '',
    });

    // Form for rescheduling
    const rescheduleForm = useForm({
        inspection_date: inspection.inspection_date ? inspection.inspection_date.split('T')[0] : '',
        inspection_time: inspection.inspection_time || '10:00 AM',
        reason: '',
        representative_id: inspection.representative_id || '',
    });

    // Form for reassigning representative
    const reassignForm = useForm({
        representative_id: inspection.representative_id || '',
    });

    const handleStatusTransition = (newStatus) => {
        setTargetStatus(newStatus);
        statusForm.setData('status', newStatus);

        if (newStatus === 'completed' || newStatus === 'cancelled' || newStatus === 'no-show') {
            setIsOutcomeModalOpen(true);
        } else {
            statusForm.post(route('inspections.status', inspection.id), {
                preserveScroll: true,
            });
        }
    };

    const handleOutcomeSubmit = (e) => {
        e.preventDefault();
        statusForm.post(route('inspections.status', inspection.id), {
            preserveScroll: true,
            onSuccess: () => setIsOutcomeModalOpen(false),
        });
    };

    const handleRescheduleSubmit = (e) => {
        e.preventDefault();
        rescheduleForm.post(route('inspections.reschedule', inspection.id), {
            preserveScroll: true,
            onSuccess: () => setIsRescheduleModalOpen(false),
        });
    };

    const handleReassignSubmit = (e) => {
        e.preventDefault();
        reassignForm.post(route('inspections.assign', inspection.id), {
            preserveScroll: true,
            onSuccess: () => setIsReassignModalOpen(false),
        });
    };

    const contactName = inspection.contact
        ? `${inspection.contact.first_name} ${inspection.contact.last_name || ''}`
        : 'Client';

    const targetProperty = inspection.property?.title || inspection.estate_name || 'General Site Inspection';

    return (
        <AuthenticatedLayout>
            <Head title={`Site Inspection #${inspection.id} - ${contactName}`} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Back button & Title ribbon */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div className="flex items-center gap-3">
                        <div className="p-3 bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-xl">
                            <CalendarCheck className="w-7 h-7" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={route('inspections.index')}
                                    className="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    &larr; Back to Inspections
                                </Link>
                                <span className="text-slate-300">•</span>
                                <span className="font-mono text-xs text-slate-400">UUID: {inspection.uuid.substring(0, 8)}</span>
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                                {contactName} — {targetProperty}
                            </h1>
                            <p className="text-xs text-slate-500 flex items-center gap-3 mt-1">
                                <span className="flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300">
                                    <Calendar className="w-3.5 h-3.5" />
                                    {inspection.inspection_date}
                                </span>
                                <span className="flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300">
                                    <Clock className="w-3.5 h-3.5" />
                                    {inspection.inspection_time}
                                </span>
                            </p>
                        </div>
                    </div>

                    {/* Status badge & Quick Action triggers */}
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={`px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider border ${
                            inspection.status === 'confirmed'
                                ? 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-400'
                                : inspection.status === 'completed'
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400'
                                : inspection.status === 'cancelled' || inspection.status === 'no-show'
                                ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400'
                                : 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400'
                        }`}>
                            Status: {inspection.status}
                        </span>

                        {canEdit && inspection.status !== 'completed' && inspection.status !== 'cancelled' && (
                            <>
                                {inspection.status !== 'confirmed' && (
                                    <button
                                        type="button"
                                        onClick={() => handleStatusTransition('confirmed')}
                                        className="px-3.5 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition shadow-sm"
                                    >
                                        Confirm Trip
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => handleStatusTransition('completed')}
                                    className="px-3.5 py-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl transition shadow-sm"
                                >
                                    Complete Visit
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setIsRescheduleModalOpen(true)}
                                    className="px-3.5 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl transition"
                                >
                                    Reschedule
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusTransition('cancelled')}
                                    className="px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusTransition('no-show')}
                                    className="px-3 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-100 rounded-xl transition"
                                >
                                    No-Show
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* 3-column Overview */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column: Client & Property */}
                    <div className="space-y-6">
                        {/* Contact Card */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4">
                            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <User className="w-4 h-4 text-blue-600" />
                                    Client Profile
                                </h3>
                                <Link
                                    href={route('contacts.show', inspection.contact_id)}
                                    className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                                >
                                    View Full Contact
                                </Link>
                            </div>

                            <div className="space-y-3 text-sm">
                                <div>
                                    <span className="text-xs text-slate-400">Full Name</span>
                                    <p className="font-semibold text-slate-900 dark:text-white">{contactName}</p>
                                </div>
                                <div>
                                    <span className="text-xs text-slate-400">Phone Number</span>
                                    <p className="font-medium text-slate-800 dark:text-slate-200 flex items-center gap-2 mt-0.5">
                                        <Phone className="w-3.5 h-3.5 text-emerald-500" />
                                        {inspection.contact?.phone}
                                    </p>
                                </div>
                                {inspection.contact?.email && (
                                    <div>
                                        <span className="text-xs text-slate-400">Email Address</span>
                                        <p className="text-slate-700 dark:text-slate-300 text-xs">{inspection.contact.email}</p>
                                    </div>
                                )}
                                <div className="pt-2 border-t border-slate-100 dark:border-slate-800">
                                    <a
                                        href={`https://wa.me/${inspection.contact?.phone?.replace(/[^0-9]/g, '')}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl transition w-full justify-center"
                                    >
                                        <MessageSquare className="w-3.5 h-3.5" />
                                        Open WhatsApp Chat
                                    </a>
                                </div>
                            </div>
                        </div>

                        {/* Property / Estate Card */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4">
                            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Building2 className="w-4 h-4 text-blue-600" />
                                    Property & Estate Details
                                </h3>
                                {inspection.property_id && (
                                    <Link
                                        href={route('properties.show', inspection.property_id)}
                                        className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                                    >
                                        View Property
                                    </Link>
                                )}
                            </div>

                            <div className="space-y-3 text-sm">
                                <div>
                                    <span className="text-xs text-slate-400">Development / Plot</span>
                                    <p className="font-semibold text-slate-900 dark:text-white">{targetProperty}</p>
                                </div>
                                {inspection.property?.location && (
                                    <div>
                                        <span className="text-xs text-slate-400">Corridor Location</span>
                                        <p className="text-slate-700 dark:text-slate-300 text-xs flex items-center gap-1.5 mt-0.5">
                                            <MapPin className="w-3.5 h-3.5 text-rose-500" />
                                            {inspection.property.location}
                                        </p>
                                    </div>
                                )}
                                {inspection.property?.active_price && (
                                    <div>
                                        <span className="text-xs text-slate-400">Official Price</span>
                                        <p className="text-emerald-600 font-bold text-base mt-0.5">
                                            ₦{Number(inspection.property.active_price.price).toLocaleString()}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Assigned Representative Card */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-3">
                            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <UserCheck className="w-4 h-4 text-blue-600" />
                                    Assigned Representative
                                </h3>
                                {canEdit && (
                                    <button
                                        type="button"
                                        onClick={() => setIsReassignModalOpen(true)}
                                        className="text-xs text-blue-600 hover:underline font-semibold"
                                    >
                                        Reassign
                                    </button>
                                )}
                            </div>

                            {inspection.representative ? (
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center text-sm shadow-sm">
                                        {inspection.representative.name.charAt(0)}
                                    </div>
                                    <div>
                                        <p className="font-semibold text-slate-900 dark:text-white text-sm">
                                            {inspection.representative.name}
                                        </p>
                                        <p className="text-xs text-slate-400">{inspection.representative.email}</p>
                                    </div>
                                </div>
                            ) : (
                                <div className="py-2 text-center text-xs text-slate-400 italic">
                                    No sales representative assigned to this site trip yet.
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Middle Column: Logistics, Notes & Recorded Outcome */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Logistics Details */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-5">
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">
                                Meeting Point & Logistics Instructions
                            </h3>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700">
                                    <span className="text-xs text-slate-400 font-medium">Pickup & Meeting Location</span>
                                    <p className="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-1 flex items-start gap-1.5">
                                        <MapPin className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                                        {inspection.meeting_point}
                                    </p>
                                </div>

                                <div className="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700">
                                    <span className="text-xs text-slate-400 font-medium">Escort Transport</span>
                                    <p className="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-1">
                                        Free Air-Conditioned Escort Vehicle provided by Bamcom Properties.
                                    </p>
                                </div>
                            </div>

                            {/* Customer Notes */}
                            {inspection.customer_notes && (
                                <div className="p-4 bg-blue-50/50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/60 rounded-xl">
                                    <span className="text-xs font-bold text-blue-900 dark:text-blue-300 uppercase">
                                        Client Special Requests & Attendee Notes
                                    </span>
                                    <p className="text-sm text-slate-700 dark:text-slate-300 mt-1 whitespace-pre-line">
                                        {inspection.customer_notes}
                                    </p>
                                </div>
                            )}

                            {/* Internal Sales Notes */}
                            {inspection.sales_notes && (
                                <div className="p-4 bg-amber-50/50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/60 rounded-xl">
                                    <span className="text-xs font-bold text-amber-900 dark:text-amber-300 uppercase">
                                        Internal Sales Rep Notes & History
                                    </span>
                                    <p className="text-sm text-slate-700 dark:text-slate-300 mt-1 whitespace-pre-line">
                                        {inspection.sales_notes}
                                    </p>
                                </div>
                            )}

                            {/* Recorded Outcome */}
                            <div className="p-4 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">
                                        Recorded Outcome & Post-Inspection Assessment
                                    </span>
                                    {canEdit && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setTargetStatus(inspection.status);
                                                setIsOutcomeModalOpen(true);
                                            }}
                                            className="text-xs text-blue-600 hover:underline font-semibold"
                                        >
                                            Edit Outcome
                                        </button>
                                    )}
                                </div>
                                {inspection.outcome ? (
                                    <p className="text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line">
                                        {inspection.outcome}
                                    </p>
                                ) : (
                                    <p className="text-xs text-slate-400 italic">
                                        No outcome recorded yet. Click "Complete Visit" or "Edit Outcome" to record feedback after the site trip.
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Audit Trail & CRM Activity Timeline */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                                <History className="w-4 h-4 text-slate-400" />
                                Inspection Activity History & Timeline
                            </h3>

                            {activities.length === 0 ? (
                                <div className="py-6 text-center text-slate-400 text-xs">
                                    No recorded timeline events for this inspection yet.
                                </div>
                            ) : (
                                <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {activities.map((act) => (
                                        <div key={act.id} className="py-3 text-xs flex items-start justify-between gap-4">
                                            <div>
                                                <p className="font-semibold text-slate-800 dark:text-slate-200">
                                                    {act.description}
                                                </p>
                                                <div className="flex items-center gap-2 text-slate-400 mt-0.5">
                                                    <span>Action: {act.activity_type}</span>
                                                    {act.user && <span>• Agent: {act.user.name}</span>}
                                                </div>
                                            </div>
                                            <span className="text-slate-400 whitespace-nowrap font-mono">
                                                {new Date(act.created_at).toLocaleDateString()} {new Date(act.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Outcome Modal */}
            {isOutcomeModalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-lg w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                Record Inspection Outcome & Status
                            </h3>
                            <button
                                type="button"
                                onClick={() => setIsOutcomeModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleOutcomeSubmit} className="space-y-4 text-sm">
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Status
                                </label>
                                <select
                                    value={statusForm.data.status}
                                    onChange={(e) => statusForm.setData('status', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                    required
                                >
                                    {statuses.map(st => (
                                        <option key={st.value} value={st.value}>{st.label}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Outcome Assessment (Client reaction, chosen plots, deposit intent)
                                </label>
                                <textarea
                                    rows="4"
                                    placeholder="Enter full details of site inspection results..."
                                    value={statusForm.data.outcome}
                                    onChange={(e) => statusForm.setData('outcome', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                    required
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsOutcomeModalOpen(false)}
                                    className="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={statusForm.processing}
                                    className="px-4 py-2 font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl transition shadow-sm disabled:opacity-50"
                                >
                                    {statusForm.processing ? 'Saving...' : 'Save Outcome'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reschedule Modal */}
            {isRescheduleModalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-lg w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                Reschedule Site Inspection
                            </h3>
                            <button
                                type="button"
                                onClick={() => setIsRescheduleModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {Object.keys(rescheduleForm.errors).length > 0 && (
                            <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                                <AlertCircle className="w-4 h-4 flex-shrink-0" />
                                <div>
                                    {Object.values(rescheduleForm.errors)[0]}
                                </div>
                            </div>
                        )}

                        <form onSubmit={handleRescheduleSubmit} className="space-y-4 text-sm">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                        New Date *
                                    </label>
                                    <input
                                        type="date"
                                        value={rescheduleForm.data.inspection_date}
                                        onChange={(e) => rescheduleForm.setData('inspection_date', e.target.value)}
                                        className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                        New Time Slot *
                                    </label>
                                    <select
                                        value={rescheduleForm.data.inspection_time}
                                        onChange={(e) => rescheduleForm.setData('inspection_time', e.target.value)}
                                        className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                        required
                                    >
                                        <option value="10:00 AM">10:00 AM (Morning)</option>
                                        <option value="02:00 PM">02:00 PM (Afternoon)</option>
                                        <option value="11:30 AM">11:30 AM</option>
                                        <option value="04:00 PM">04:00 PM</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Assigned Representative
                                </label>
                                <select
                                    value={rescheduleForm.data.representative_id}
                                    onChange={(e) => rescheduleForm.setData('representative_id', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                >
                                    <option value="">Unassigned Pool</option>
                                    {representatives.map(r => (
                                        <option key={r.id} value={r.id}>{r.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Reason for Rescheduling
                                </label>
                                <textarea
                                    rows="2"
                                    placeholder="Explain why trip was rescheduled..."
                                    value={rescheduleForm.data.reason}
                                    onChange={(e) => rescheduleForm.setData('reason', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsRescheduleModalOpen(false)}
                                    className="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={rescheduleForm.processing}
                                    className="px-4 py-2 font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl transition shadow-sm disabled:opacity-50"
                                >
                                    {rescheduleForm.processing ? 'Rescheduling...' : 'Confirm Reschedule'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reassign Representative Modal */}
            {isReassignModalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                Assign Field Representative
                            </h3>
                            <button
                                type="button"
                                onClick={() => setIsReassignModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {Object.keys(reassignForm.errors).length > 0 && (
                            <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                                <AlertCircle className="w-4 h-4 flex-shrink-0" />
                                <div>
                                    {Object.values(reassignForm.errors)[0]}
                                </div>
                            </div>
                        )}

                        <form onSubmit={handleReassignSubmit} className="space-y-4 text-sm">
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Select Representative *
                                </label>
                                <select
                                    value={reassignForm.data.representative_id}
                                    onChange={(e) => reassignForm.setData('representative_id', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                    required
                                >
                                    <option value="">Select agent...</option>
                                    {representatives.map(r => (
                                        <option key={r.id} value={r.id}>{r.name} ({r.email})</option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsReassignModalOpen(false)}
                                    className="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={reassignForm.processing}
                                    className="px-4 py-2 font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl transition shadow-sm disabled:opacity-50"
                                >
                                    {reassignForm.processing ? 'Assigning...' : 'Assign Rep'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
