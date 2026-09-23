import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    CalendarCheck,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    Filter,
    HelpCircle,
    List,
    MapPin,
    Phone,
    Plus,
    RefreshCw,
    Search,
    ShieldAlert,
    User,
    UserCheck,
    X,
    XCircle
} from 'lucide-react';
import { useState } from 'react';

export default function Index({
    inspections,
    calendarEvents = [],
    stats = {},
    filters = {},
    statuses = [],
    representatives = [],
    properties = [],
    contacts = []
}) {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const canCreate = isSuperAdmin || permissions.includes('inspections.create');
    const canEdit = isSuperAdmin || permissions.includes('inspections.edit');

    const [viewMode, setViewMode] = useState(filters.view || 'list');
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
    const [selectedRep, setSelectedRep] = useState(filters.representative_id || '');
    const [isScheduleModalOpen, setIsScheduleModalOpen] = useState(false);
    const [quickActionInspection, setQuickActionInspection] = useState(null);
    const [quickActionType, setQuickActionType] = useState(null); // 'reschedule' | 'status'

    // Calendar state
    const [currentCalDate, setCurrentCalDate] = useState(new Date());

    // Schedule new inspection form
    const scheduleForm = useForm({
        contact_id: '',
        property_id: '',
        estate_name: '',
        representative_id: '',
        inspection_date: new Date().toISOString().split('T')[0],
        inspection_time: '10:00 AM',
        meeting_point: 'Bamcom Corporate Office, Plot 12, Admiralty Way, Lekki Phase 1, Lagos',
        customer_notes: '',
        sales_notes: '',
    });

    // Quick action form (reschedule or status change)
    const quickForm = useForm({
        status: '',
        outcome: '',
        sales_notes: '',
        inspection_date: '',
        inspection_time: '10:00 AM',
        reason: '',
        representative_id: '',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('inspections.index'), {
            view: viewMode,
            search: searchTerm || undefined,
            status: selectedStatus || undefined,
            representative_id: selectedRep || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleFilterChange = (statusVal, repVal) => {
        setSelectedStatus(statusVal);
        setSelectedRep(repVal);
        router.get(route('inspections.index'), {
            view: viewMode,
            search: searchTerm || undefined,
            status: statusVal || undefined,
            representative_id: repVal || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleScheduleSubmit = (e) => {
        e.preventDefault();
        scheduleForm.post(route('inspections.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setIsScheduleModalOpen(false);
                scheduleForm.reset();
            },
        });
    };

    const handleQuickActionSubmit = (e) => {
        e.preventDefault();
        if (!quickActionInspection) return;

        if (quickActionType === 'reschedule') {
            quickForm.post(route('inspections.reschedule', quickActionInspection.id), {
                preserveScroll: true,
                onSuccess: () => {
                    setQuickActionInspection(null);
                    quickForm.reset();
                },
            });
        } else if (quickActionType === 'status') {
            quickForm.post(route('inspections.status', quickActionInspection.id), {
                preserveScroll: true,
                onSuccess: () => {
                    setQuickActionInspection(null);
                    quickForm.reset();
                },
            });
        }
    };

    const openQuickStatus = (inspection, targetStatus) => {
        setQuickActionInspection(inspection);
        setQuickActionType('status');
        quickForm.setData({
            status: targetStatus,
            outcome: '',
            sales_notes: '',
            inspection_date: '',
            inspection_time: '',
            reason: '',
            representative_id: '',
        });
    };

    const openQuickReschedule = (inspection) => {
        setQuickActionInspection(inspection);
        setQuickActionType('reschedule');
        quickForm.setData({
            status: '',
            outcome: '',
            sales_notes: '',
            inspection_date: inspection.inspection_date ? inspection.inspection_date.split('T')[0] : '',
            inspection_time: inspection.inspection_time || '10:00 AM',
            reason: '',
            representative_id: inspection.representative_id || '',
        });
    };

    // Calendar generation helpers
    const getDaysInMonth = (year, month) => new Date(year, month + 1, 0).getDate();
    const getFirstDayOfMonth = (year, month) => new Date(year, month, 1).getDay();

    const calYear = currentCalDate.getFullYear();
    const calMonth = currentCalDate.getMonth();
    const daysInCurrentMonth = getDaysInMonth(calYear, calMonth);
    const firstDayIndex = getFirstDayOfMonth(calYear, calMonth);

    const prevMonth = () => setCurrentCalDate(new Date(calYear, calMonth - 1, 1));
    const nextMonth = () => setCurrentCalDate(new Date(calYear, calMonth + 1, 1));
    const todayMonth = () => setCurrentCalDate(new Date());

    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    const getEventsForDay = (dayNum) => {
        const dateStr = `${calYear}-${String(calMonth + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
        return calendarEvents.filter(ev => ev.date === dateStr);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Site Inspection Management" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header ribbon */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-xl">
                            <CalendarCheck className="w-6 h-6" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                                Site Inspections
                            </h1>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Schedule, dispatch, and track field visits across Lekki, Epe, and Ibeju-Lekki corridors.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {/* View toggle */}
                        <div className="inline-flex rounded-xl bg-slate-100 dark:bg-slate-800 p-1 border border-slate-200 dark:border-slate-700">
                            <button
                                type="button"
                                onClick={() => setViewMode('list')}
                                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                                    viewMode === 'list'
                                        ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm'
                                        : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'
                                }`}
                            >
                                <List className="w-3.5 h-3.5" />
                                List View
                            </button>
                            <button
                                type="button"
                                onClick={() => setViewMode('calendar')}
                                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                                    viewMode === 'calendar'
                                        ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm'
                                        : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'
                                }`}
                            >
                                <Calendar className="w-3.5 h-3.5" />
                                Calendar
                            </button>
                        </div>

                        {canCreate && (
                            <button
                                type="button"
                                onClick={() => setIsScheduleModalOpen(true)}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl shadow-sm transition"
                            >
                                <Plus className="w-4 h-4" />
                                Schedule Inspection
                            </button>
                        )}
                    </div>
                </div>

                {/* Metrics Bar */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <span className="text-xs text-slate-500 font-medium">Total Trips</span>
                        <p className="text-xl font-bold text-slate-900 dark:text-white mt-1">{stats.total || 0}</p>
                    </div>
                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <span className="text-xs text-blue-600 font-medium">Scheduled</span>
                        <p className="text-xl font-bold text-blue-600 mt-1">{stats.scheduled || 0}</p>
                    </div>
                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <span className="text-xs text-indigo-600 font-medium">Confirmed</span>
                        <p className="text-xl font-bold text-indigo-600 mt-1">{stats.confirmed || 0}</p>
                    </div>
                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <span className="text-xs text-emerald-600 font-medium">Completed</span>
                        <p className="text-xl font-bold text-emerald-600 mt-1">{stats.completed || 0}</p>
                    </div>
                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <span className="text-xs text-amber-600 font-medium">Today's Visits</span>
                        <p className="text-xl font-bold text-amber-600 mt-1">{stats.today || 0}</p>
                    </div>
                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <span className="text-xs text-rose-600 font-medium">Cancelled / No-Show</span>
                        <p className="text-xl font-bold text-rose-600 mt-1">{stats.cancelled || 0}</p>
                    </div>
                </div>

                {/* Search & Filters */}
                <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                    <form onSubmit={handleSearch} className="relative w-full md:w-80">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3" />
                        <input
                            type="text"
                            placeholder="Search client, estate, phone..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        />
                    </form>

                    <div className="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <select
                            value={selectedStatus}
                            onChange={(e) => handleFilterChange(e.target.value, selectedRep)}
                            className="px-3 py-2 rounded-xl text-xs font-medium border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200"
                        >
                            <option value="">All Statuses</option>
                            {statuses.map(st => (
                                <option key={st.value} value={st.value}>{st.label}</option>
                            ))}
                        </select>

                        <select
                            value={selectedRep}
                            onChange={(e) => handleFilterChange(selectedStatus, e.target.value)}
                            className="px-3 py-2 rounded-xl text-xs font-medium border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200"
                        >
                            <option value="">All Representatives</option>
                            {representatives.map(rep => (
                                <option key={rep.id} value={rep.id}>{rep.name}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Content View: List or Calendar */}
                {viewMode === 'list' ? (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                    <tr>
                                        <th className="px-5 py-3.5">Schedule</th>
                                        <th className="px-5 py-3.5">Client Contact</th>
                                        <th className="px-5 py-3.5">Target Property / Estate</th>
                                        <th className="px-5 py-3.5">Representative</th>
                                        <th className="px-5 py-3.5">Status</th>
                                        <th className="px-5 py-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {inspections.data.length === 0 ? (
                                        <tr>
                                            <td colSpan="6" className="py-12 text-center text-slate-400 text-sm">
                                                No site inspections found matching criteria.
                                            </td>
                                        </tr>
                                    ) : (
                                        inspections.data.map((item) => {
                                            const contactName = item.contact?.first_name 
                                                ? `${item.contact.first_name} ${item.contact.last_name || ''}`
                                                : `Contact #${item.contact_id}`;
                                            const propertyTitle = item.property?.title || item.estate_name || 'General Site Visit';

                                            return (
                                                <tr key={item.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                                    <td className="px-5 py-4">
                                                        <div className="flex flex-col">
                                                            <span className="font-semibold text-slate-900 dark:text-white">
                                                                {item.inspection_date}
                                                            </span>
                                                            <span className="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                                                <Clock className="w-3 h-3" />
                                                                {item.inspection_time}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <div className="flex flex-col">
                                                            <Link
                                                                href={route('contacts.show', item.contact_id)}
                                                                className="font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                                            >
                                                                {contactName}
                                                            </Link>
                                                            <span className="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                                                <Phone className="w-3 h-3" />
                                                                {item.contact?.phone || 'No phone'}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <div className="flex flex-col">
                                                            <span className="font-medium text-slate-800 dark:text-slate-200">
                                                                {propertyTitle}
                                                            </span>
                                                            <span className="text-xs text-slate-400 flex items-center gap-1 mt-0.5 max-w-xs truncate">
                                                                <MapPin className="w-3 h-3 flex-shrink-0" />
                                                                {item.meeting_point}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        {item.representative ? (
                                                            <div className="flex items-center gap-2">
                                                                <div className="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">
                                                                    {item.representative.name.charAt(0)}
                                                                </div>
                                                                <span className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                                                    {item.representative.name}
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-slate-400 italic">Unassigned</span>
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${
                                                            item.status === 'confirmed'
                                                                ? 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-400'
                                                                : item.status === 'completed'
                                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                                : item.status === 'cancelled' || item.status === 'no-show'
                                                                ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400'
                                                                : 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400'
                                                        }`}>
                                                            {item.status.toUpperCase()}
                                                        </span>
                                                    </td>
                                                    <td className="px-5 py-4 text-right">
                                                        <div className="inline-flex items-center gap-2">
                                                            {canEdit && item.status !== 'completed' && item.status !== 'cancelled' && (
                                                                <>
                                                                    {item.status !== 'confirmed' && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => openQuickStatus(item, 'confirmed')}
                                                                            className="px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition"
                                                                            title="Confirm Inspection"
                                                                        >
                                                                            Confirm
                                                                        </button>
                                                                    )}
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openQuickStatus(item, 'completed')}
                                                                        className="px-2 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition"
                                                                        title="Complete Inspection"
                                                                    >
                                                                        Complete
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openQuickReschedule(item)}
                                                                        className="p-1.5 text-slate-500 hover:text-blue-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                                                        title="Reschedule Trip"
                                                                    >
                                                                        <RefreshCw className="w-4 h-4" />
                                                                    </button>
                                                                </>
                                                            )}
                                                            <Link
                                                                href={route('inspections.show', item.id)}
                                                                className="p-1.5 text-slate-600 hover:text-blue-600 dark:text-slate-300 dark:hover:text-blue-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                                                title="View Details"
                                                            >
                                                                <Eye className="w-4 h-4" />
                                                            </Link>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {inspections.links && (
                            <div className="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                                <div>
                                    Showing {inspections.from || 0} to {inspections.to || 0} of {inspections.total} inspections
                                </div>
                                <div className="flex gap-1">
                                    {inspections.links.map((link, idx) => (
                                        <Link
                                            key={idx}
                                            href={link.url || '#'}
                                            className={`px-3 py-1.5 rounded-lg border text-xs ${
                                                link.active
                                                    ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                                                    : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'
                                            } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                ) : (
                    /* Calendar View */
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-4">
                        {/* Calendar Header */}
                        <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div className="flex items-center gap-3">
                                <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                    {monthNames[calMonth]} {calYear}
                                </h2>
                                <button
                                    type="button"
                                    onClick={todayMonth}
                                    className="px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 rounded-lg transition"
                                >
                                    Today
                                </button>
                            </div>

                            <div className="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    onClick={prevMonth}
                                    className="p-2 text-slate-500 hover:text-slate-900 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
                                >
                                    <ChevronLeft className="w-5 h-5" />
                                </button>
                                <button
                                    type="button"
                                    onClick={nextMonth}
                                    className="p-2 text-slate-500 hover:text-slate-900 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
                                >
                                    <ChevronRight className="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        {/* Calendar Grid */}
                        <div className="grid grid-cols-7 gap-px bg-slate-200 dark:bg-slate-800 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800">
                            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
                                <div key={d} className="bg-slate-50 dark:bg-slate-900/80 p-2.5 text-center text-xs font-bold text-slate-500 uppercase">
                                    {d}
                                </div>
                            ))}

                            {/* Leading blank days */}
                            {Array.from({ length: firstDayIndex }).map((_, i) => (
                                <div key={`empty-${i}`} className="bg-white/40 dark:bg-slate-950/40 min-h-[100px] p-2" />
                            ))}

                            {/* Days of the month */}
                            {Array.from({ length: daysInCurrentMonth }).map((_, i) => {
                                const dayNum = i + 1;
                                const dayEvents = getEventsForDay(dayNum);
                                const isToday =
                                    new Date().getDate() === dayNum &&
                                    new Date().getMonth() === calMonth &&
                                    new Date().getFullYear() === calYear;

                                return (
                                    <div
                                        key={`day-${dayNum}`}
                                        className={`bg-white dark:bg-slate-900 min-h-[110px] p-2 transition flex flex-col justify-between hover:bg-slate-50 dark:hover:bg-slate-800/60 ${
                                            isToday ? 'ring-2 ring-blue-500 ring-inset' : ''
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-1">
                                            <span className={`text-xs font-bold ${
                                                isToday
                                                    ? 'w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center'
                                                    : 'text-slate-700 dark:text-slate-300'
                                            }`}>
                                                {dayNum}
                                            </span>
                                            {dayEvents.length > 0 && (
                                                <span className="text-[10px] font-semibold text-blue-600 dark:text-blue-400">
                                                    {dayEvents.length} visit{dayEvents.length > 1 ? 's' : ''}
                                                </span>
                                            )}
                                        </div>

                                        <div className="space-y-1 overflow-y-auto max-h-24">
                                            {dayEvents.map(ev => (
                                                <Link
                                                    key={ev.id}
                                                    href={route('inspections.show', ev.id)}
                                                    className={`block px-2 py-1 rounded text-[11px] font-medium border truncate transition ${
                                                        ev.status === 'completed'
                                                            ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                            : ev.status === 'confirmed'
                                                            ? 'bg-indigo-50 text-indigo-800 border-indigo-200 dark:bg-indigo-950/50 dark:text-indigo-300'
                                                            : ev.status === 'cancelled' || ev.status === 'no-show'
                                                            ? 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300'
                                                            : 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300'
                                                    }`}
                                                    title={`${ev.time} - ${ev.contact?.name} (${ev.estate_name || 'Inspection'})`}
                                                >
                                                    <span className="font-bold mr-1">{ev.time}</span>
                                                    {ev.contact?.name}
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            {/* Schedule New Inspection Modal */}
            {isScheduleModalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-xl w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <CalendarCheck className="w-5 h-5 text-blue-600" />
                                Schedule Site Inspection
                            </h3>
                            <button
                                type="button"
                                onClick={() => setIsScheduleModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Error alert */}
                        {Object.keys(scheduleForm.errors).length > 0 && (
                            <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                                <AlertCircle className="w-4 h-4 flex-shrink-0" />
                                <div>
                                    {Object.values(scheduleForm.errors)[0]}
                                </div>
                            </div>
                        )}

                        <form onSubmit={handleScheduleSubmit} className="space-y-4 text-sm">
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Client / Contact *
                                </label>
                                <select
                                    value={scheduleForm.data.contact_id}
                                    onChange={(e) => scheduleForm.setData('contact_id', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                    required
                                >
                                    <option value="">Select a contact...</option>
                                    {contacts.map(c => (
                                        <option key={c.id} value={c.id}>
                                            {c.first_name} {c.last_name || ''} ({c.phone})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                        Target Property
                                    </label>
                                    <select
                                        value={scheduleForm.data.property_id}
                                        onChange={(e) => scheduleForm.setData('property_id', e.target.value)}
                                        className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                    >
                                        <option value="">Select inventory property...</option>
                                        {properties.map(p => (
                                            <option key={p.id} value={p.id}>{p.title} - {p.location}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                        Or Estate Name
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="e.g. Silverstone Heights"
                                        value={scheduleForm.data.estate_name}
                                        onChange={(e) => scheduleForm.setData('estate_name', e.target.value)}
                                        className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                        Inspection Date *
                                    </label>
                                    <input
                                        type="date"
                                        value={scheduleForm.data.inspection_date}
                                        onChange={(e) => scheduleForm.setData('inspection_date', e.target.value)}
                                        className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                        Time Slot *
                                    </label>
                                    <select
                                        value={scheduleForm.data.inspection_time}
                                        onChange={(e) => scheduleForm.setData('inspection_time', e.target.value)}
                                        className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                        required
                                    >
                                        <option value="10:00 AM">10:00 AM (Morning Session)</option>
                                        <option value="02:00 PM">02:00 PM (Afternoon Session)</option>
                                        <option value="11:30 AM">11:30 AM (Special Slot)</option>
                                        <option value="04:00 PM">04:00 PM (Evening Slot)</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Assigned Representative (Conflict Protected)
                                </label>
                                <select
                                    value={scheduleForm.data.representative_id}
                                    onChange={(e) => scheduleForm.setData('representative_id', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                >
                                    <option value="">Unassigned / Pool Escort</option>
                                    {representatives.map(r => (
                                        <option key={r.id} value={r.id}>{r.name} ({r.email})</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Meeting / Pickup Point
                                </label>
                                <input
                                    type="text"
                                    value={scheduleForm.data.meeting_point}
                                    onChange={(e) => scheduleForm.setData('meeting_point', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                    Customer Notes (Attendees, Vehicle pickup requests)
                                </label>
                                <textarea
                                    rows="2"
                                    value={scheduleForm.data.customer_notes}
                                    onChange={(e) => scheduleForm.setData('customer_notes', e.target.value)}
                                    className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsScheduleModalOpen(false)}
                                    className="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={scheduleForm.processing}
                                    className="px-4 py-2 font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl transition shadow-sm disabled:opacity-50"
                                >
                                    {scheduleForm.processing ? 'Booking Trip...' : 'Schedule Site Trip'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Quick Action Modal (Reschedule or Status change) */}
            {quickActionInspection && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-lg w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                {quickActionType === 'reschedule' ? 'Reschedule Site Inspection' : `Update Status to ${quickForm.data.status?.toUpperCase()}`}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setQuickActionInspection(null)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Error alert */}
                        {Object.keys(quickForm.errors).length > 0 && (
                            <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                                <AlertCircle className="w-4 h-4 flex-shrink-0" />
                                <div>
                                    {Object.values(quickForm.errors)[0]}
                                </div>
                            </div>
                        )}

                        <form onSubmit={handleQuickActionSubmit} className="space-y-4 text-sm">
                            {quickActionType === 'reschedule' ? (
                                <>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                                New Inspection Date *
                                            </label>
                                            <input
                                                type="date"
                                                value={quickForm.data.inspection_date}
                                                onChange={(e) => quickForm.setData('inspection_date', e.target.value)}
                                                className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                                New Time Slot *
                                            </label>
                                            <select
                                                value={quickForm.data.inspection_time}
                                                onChange={(e) => quickForm.setData('inspection_time', e.target.value)}
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
                                            Reason for Rescheduling
                                        </label>
                                        <textarea
                                            rows="2"
                                            placeholder="e.g., Client requested afternoon session due to flight delay"
                                            value={quickForm.data.reason}
                                            onChange={(e) => quickForm.setData('reason', e.target.value)}
                                            className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                                            Outcome / Notes
                                        </label>
                                        <textarea
                                            rows="3"
                                            placeholder={
                                                quickForm.data.status === 'completed'
                                                    ? 'Describe client feedback, selected plots, deposit readiness, etc.'
                                                    : 'Reason for status update...'
                                            }
                                            value={quickForm.data.outcome}
                                            onChange={(e) => quickForm.setData('outcome', e.target.value)}
                                            className="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                                        />
                                    </div>
                                </>
                            )}

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setQuickActionInspection(null)}
                                    className="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={quickForm.processing}
                                    className="px-4 py-2 font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl transition shadow-sm disabled:opacity-50"
                                >
                                    {quickForm.processing ? 'Saving...' : 'Confirm Action'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
