import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Calendar,
    Check,
    CheckCircle2,
    CheckSquare,
    ChevronLeft,
    ChevronRight,
    Clock,
    CreditCard,
    Edit,
    ExternalLink,
    FileText,
    Filter,
    MessageSquare,
    Phone,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    User,
    Users,
    X,
    Compass
} from 'lucide-react';
import { useState } from 'react';

export default function Index({
    tasks = { data: [], links: [] },
    view = 'today',
    filters = {},
    metrics = {},
    users = [],
    priorities = [],
    types = [],
    statuses = [],
    contacts = []
}) {
    const { auth, flash } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const canCreate = true;
    const canEdit = true;

    const [activeView, setActiveView] = useState(view);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedPriority, setSelectedPriority] = useState(filters.priority || '');
    const [selectedType, setSelectedType] = useState(filters.type || '');
    const [selectedUser, setSelectedUser] = useState(filters.assigned_user_id || '');
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingTask, setEditingTask] = useState(null);

    // Create / Edit Form
    const taskForm = useForm({
        title: '',
        description: '',
        contact_id: '',
        lead_id: '',
        deal_id: '',
        assigned_user_id: auth.user?.id || '',
        due_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16),
        priority: 'medium',
        type: 'follow_up',
        status: 'pending',
    });

    const handleViewChange = (newView) => {
        setActiveView(newView);
        router.get(
            route('tasks.index'),
            {
                view: newView,
                search: searchTerm || undefined,
                priority: selectedPriority || undefined,
                type: selectedType || undefined,
                assigned_user_id: selectedUser || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleFilterSubmit = (e) => {
        e?.preventDefault();
        router.get(
            route('tasks.index'),
            {
                view: activeView,
                search: searchTerm || undefined,
                priority: selectedPriority || undefined,
                type: selectedType || undefined,
                assigned_user_id: selectedUser || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedPriority('');
        setSelectedType('');
        setSelectedUser('');
        router.get(route('tasks.index'), { view: activeView }, { preserveState: true, replace: true });
    };

    const openCreateModal = () => {
        setEditingTask(null);
        taskForm.reset();
        taskForm.setData({
            title: '',
            description: '',
            contact_id: '',
            lead_id: '',
            deal_id: '',
            assigned_user_id: auth.user?.id || '',
            due_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16),
            priority: 'medium',
            type: 'follow_up',
            status: 'pending',
        });
        setIsCreateModalOpen(true);
    };

    const openEditModal = (task) => {
        setEditingTask(task);
        taskForm.setData({
            title: task.title,
            description: task.description || '',
            contact_id: task.contact_id || '',
            lead_id: task.lead_id || '',
            deal_id: task.deal_id || '',
            assigned_user_id: task.assigned_user_id || '',
            due_at: task.due_at ? new Date(task.due_at).toISOString().slice(0, 16) : '',
            priority: task.priority?.value || task.priority || 'medium',
            type: task.type?.value || task.type || 'follow_up',
            status: task.status?.value || task.status || 'pending',
        });
        setIsCreateModalOpen(true);
    };

    const handleSaveTask = (e) => {
        e.preventDefault();
        if (editingTask) {
            taskForm.put(route('tasks.update', editingTask.id), {
                onSuccess: () => {
                    setIsCreateModalOpen(false);
                    setEditingTask(null);
                },
            });
        } else {
            taskForm.post(route('tasks.store'), {
                onSuccess: () => {
                    setIsCreateModalOpen(false);
                    taskForm.reset();
                },
            });
        }
    };

    const handleToggleComplete = (task) => {
        if (task.status === 'completed' || task.status?.value === 'completed') {
            router.post(route('tasks.reopen', task.id), {}, { preserveScroll: true });
        } else {
            router.post(route('tasks.complete', task.id), {}, { preserveScroll: true });
        }
    };

    const handleDeleteTask = (task) => {
        if (confirm(`Are you sure you want to delete task "${task.title}"?`)) {
            router.delete(route('tasks.destroy', task.id), { preserveScroll: true });
        }
    };

    const formatDateTime = (dateStr) => {
        if (!dateStr) return 'No due date';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getDueDateBadge = (task) => {
        const isCompleted = task.status === 'completed' || task.status?.value === 'completed';
        if (isCompleted) {
            return (
                <span className="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    Completed
                </span>
            );
        }

        const isOverdue = task.is_overdue;
        const isDueToday = task.is_due_today;

        if (isOverdue) {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 animate-pulse">
                    <AlertTriangle className="h-3.5 w-3.5" />
                    Overdue ({formatDateTime(task.due_at)})
                </span>
            );
        }

        if (isDueToday) {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                    <Clock className="h-3.5 w-3.5" />
                    Due Today ({new Date(task.due_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })})
                </span>
            );
        }

        return (
            <span className="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-400">
                <Calendar className="h-3.5 w-3.5" />
                Due {formatDateTime(task.due_at)}
            </span>
        );
    };

    const getTypeIcon = (typeName) => {
        const val = typeof typeName === 'object' ? typeName?.value : typeName;
        switch (val) {
            case 'call': return <Phone className="h-3.5 w-3.5" />;
            case 'whatsapp': return <MessageSquare className="h-3.5 w-3.5" />;
            case 'meeting': return <Users className="h-3.5 w-3.5" />;
            case 'site_inspection': return <Compass className="h-3.5 w-3.5" />;
            case 'document_preparation': return <FileText className="h-3.5 w-3.5" />;
            case 'payment_followup': return <CreditCard className="h-3.5 w-3.5" />;
            default: return <Clock className="h-3.5 w-3.5" />;
        }
    };

    const getTypeBadgeClass = (typeName) => {
        const val = typeof typeName === 'object' ? typeName?.value : typeName;
        switch (val) {
            case 'call': return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300';
            case 'whatsapp': return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
            case 'meeting': return 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300';
            case 'site_inspection': return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
            case 'document_preparation': return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300';
            case 'payment_followup': return 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300';
            default: return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
        }
    };

    const getPriorityBadge = (priority) => {
        const val = typeof priority === 'object' ? priority?.value : priority;
        switch (val) {
            case 'urgent':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-black uppercase tracking-wider bg-rose-600 text-white shadow-sm">
                        Urgent
                    </span>
                );
            case 'high':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        High
                    </span>
                );
            case 'low':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        Low
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                        Medium
                    </span>
                );
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">
                            <CheckSquare className="h-4 w-4" />
                            <span>Sales Execution Engine</span>
                        </div>
                        <h2 className="font-bold text-2xl text-slate-900 dark:text-white leading-tight">
                            CRM Tasks & Follow-up Reminders
                        </h2>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={openCreateModal}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-bold rounded-xl shadow-sm shadow-blue-600/20 transition"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Create New Task</span>
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="CRM Tasks & Follow-up Reminders - Bamcom AI CRM" />

            <div className="space-y-6">
                {/* Flash message notification */}
                {flash?.success && (
                    <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 text-sm flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                            <span>{flash.success}</span>
                        </div>
                    </div>
                )}

                {/* 1. Metrics Ribbon */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    {/* Today's Tasks */}
                    <button
                        type="button"
                        onClick={() => handleViewChange('today')}
                        className={`p-4 rounded-2xl border text-left transition ${
                            activeView === 'today'
                                ? 'bg-amber-500/10 border-amber-500/50 shadow-sm ring-2 ring-amber-500/20'
                                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-amber-300'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">Today's Tasks</span>
                            <div className="p-2 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600">
                                <Clock className="h-4 w-4" />
                            </div>
                        </div>
                        <p className="text-2xl font-black text-slate-900 dark:text-white mt-2">
                            {metrics.today ?? 0}
                        </p>
                        <span className="text-[11px] text-amber-600 dark:text-amber-400 font-medium">
                            Due by end of day
                        </span>
                    </button>

                    {/* Overdue */}
                    <button
                        type="button"
                        onClick={() => handleViewChange('overdue')}
                        className={`p-4 rounded-2xl border text-left transition ${
                            activeView === 'overdue'
                                ? 'bg-rose-500/10 border-rose-500/50 shadow-sm ring-2 ring-rose-500/20'
                                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-rose-300'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">Overdue</span>
                            <div className="p-2 rounded-xl bg-rose-100 dark:bg-rose-950/60 text-rose-600">
                                <AlertTriangle className="h-4 w-4" />
                            </div>
                        </div>
                        <p className="text-2xl font-black text-rose-600 dark:text-rose-400 mt-2">
                            {metrics.overdue ?? 0}
                        </p>
                        <span className="text-[11px] text-rose-600 dark:text-rose-400 font-semibold">
                            Requires immediate follow-up
                        </span>
                    </button>

                    {/* Upcoming */}
                    <button
                        type="button"
                        onClick={() => handleViewChange('upcoming')}
                        className={`p-4 rounded-2xl border text-left transition ${
                            activeView === 'upcoming'
                                ? 'bg-blue-500/10 border-blue-500/50 shadow-sm ring-2 ring-blue-500/20'
                                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-blue-300'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">Upcoming</span>
                            <div className="p-2 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-blue-600">
                                <Calendar className="h-4 w-4" />
                            </div>
                        </div>
                        <p className="text-2xl font-black text-slate-900 dark:text-white mt-2">
                            {metrics.upcoming ?? 0}
                        </p>
                        <span className="text-[11px] text-blue-600 dark:text-blue-400 font-medium">
                            Scheduled after today
                        </span>
                    </button>

                    {/* Completed */}
                    <button
                        type="button"
                        onClick={() => handleViewChange('completed')}
                        className={`p-4 rounded-2xl border text-left transition ${
                            activeView === 'completed'
                                ? 'bg-emerald-500/10 border-emerald-500/50 shadow-sm ring-2 ring-emerald-500/20'
                                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-emerald-300'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">Completed</span>
                            <div className="p-2 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600">
                                <CheckCircle2 className="h-4 w-4" />
                            </div>
                        </div>
                        <p className="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-2">
                            {metrics.completed ?? 0}
                        </p>
                        <span className="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                            Closed follow-ups
                        </span>
                    </button>

                    {/* Urgent Active */}
                    <div className="col-span-2 lg:col-span-1 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Active</span>
                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                {metrics.urgent ?? 0} Urgent
                            </span>
                        </div>
                        <p className="text-2xl font-black text-slate-900 dark:text-white mt-2">
                            {metrics.total_active ?? 0}
                        </p>
                        <span className="text-[11px] text-slate-400 font-medium">
                            Pending & In Progress
                        </span>
                    </div>
                </div>

                {/* 2. Main Navigation Tabs */}
                <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 overflow-x-auto">
                    <div className="flex space-x-2">
                        <button
                            type="button"
                            onClick={() => handleViewChange('today')}
                            className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                                activeView === 'today'
                                    ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                            }`}
                        >
                            <Clock className="h-4 w-4" />
                            <span>Today's Tasks</span>
                            <span className="ml-1 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                {metrics.today ?? 0}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleViewChange('overdue')}
                            className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                                activeView === 'overdue'
                                    ? 'border-rose-500 text-rose-600 dark:text-rose-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                            }`}
                        >
                            <AlertTriangle className="h-4 w-4" />
                            <span>Overdue</span>
                            <span className="ml-1 px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                {metrics.overdue ?? 0}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleViewChange('upcoming')}
                            className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                                activeView === 'upcoming'
                                    ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                            }`}
                        >
                            <Calendar className="h-4 w-4" />
                            <span>Upcoming</span>
                            <span className="ml-1 px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                                {metrics.upcoming ?? 0}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleViewChange('completed')}
                            className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                                activeView === 'completed'
                                    ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                            }`}
                        >
                            <CheckCircle2 className="h-4 w-4" />
                            <span>Completed</span>
                            <span className="ml-1 px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                {metrics.completed ?? 0}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleViewChange('all')}
                            className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                                activeView === 'all'
                                    ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                            }`}
                        >
                            <CheckSquare className="h-4 w-4" />
                            <span>All Tasks</span>
                        </button>
                    </div>
                </div>

                {/* 3. Filter Bar */}
                <div className="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <form onSubmit={handleFilterSubmit} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        {/* Search Input */}
                        <div className="relative">
                            <Search className="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                placeholder="Search tasks by title..."
                                className="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 transition"
                            />
                        </div>

                        {/* Representative Filter */}
                        <div>
                            <select
                                value={selectedUser}
                                onChange={(e) => setSelectedUser(e.target.value)}
                                className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 transition"
                            >
                                <option value="">All Assigned Representatives</option>
                                <option value={auth.user?.id}>Assigned to Me ({auth.user?.name})</option>
                                {users.filter(u => u.id !== auth.user?.id).map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name} ({u.role})
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Priority Filter */}
                        <div>
                            <select
                                value={selectedPriority}
                                onChange={(e) => setSelectedPriority(e.target.value)}
                                className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 transition"
                            >
                                <option value="">All Priorities</option>
                                {priorities.map((p) => (
                                    <option key={p.value} value={p.value}>
                                        {p.label} Priority
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Type Filter */}
                        <div>
                            <select
                                value={selectedType}
                                onChange={(e) => setSelectedType(e.target.value)}
                                className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 transition"
                            >
                                <option value="">All Task Types</option>
                                {types.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-2">
                            <button
                                type="submit"
                                className="flex-1 py-2 px-3 bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 text-white dark:text-slate-900 text-xs font-bold rounded-xl transition"
                            >
                                Filter
                            </button>
                            <button
                                type="button"
                                onClick={handleResetFilters}
                                className="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 rounded-xl transition"
                                title="Reset all filters"
                            >
                                <RotateCcw className="h-4 w-4" />
                            </button>
                        </div>
                    </form>
                </div>

                {/* 4. Task Cards Listing */}
                <div className="space-y-3">
                    {tasks.data && tasks.data.length > 0 ? (
                        tasks.data.map((task) => {
                            const isCompleted = task.status === 'completed' || task.status?.value === 'completed';
                            return (
                                <div
                                    key={task.id}
                                    className={`p-4 rounded-2xl border transition flex flex-col md:flex-row md:items-center justify-between gap-4 ${
                                        isCompleted
                                            ? 'bg-slate-50/60 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800 opacity-75'
                                            : task.is_overdue
                                            ? 'bg-rose-50/30 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/60 hover:border-rose-400'
                                            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700 shadow-sm'
                                    }`}
                                >
                                    <div className="flex items-start gap-3.5 flex-1">
                                        {/* Toggle Checkbox Button */}
                                        <button
                                            type="button"
                                            onClick={() => handleToggleComplete(task)}
                                            className={`mt-1 h-5 w-5 rounded-lg border flex items-center justify-center transition flex-shrink-0 ${
                                                isCompleted
                                                    ? 'bg-emerald-600 border-emerald-600 text-white'
                                                    : 'border-slate-300 dark:border-slate-600 hover:border-blue-600 bg-white dark:bg-slate-800'
                                            }`}
                                            title={isCompleted ? 'Mark task as pending' : 'Mark task as completed'}
                                        >
                                            {isCompleted && <Check className="h-3.5 w-3.5 stroke-[3]" />}
                                        </button>

                                        {/* Task Details */}
                                        <div className="space-y-1.5 flex-1">
                                            <div className="flex items-center flex-wrap gap-2">
                                                <h3
                                                    className={`text-sm font-bold ${
                                                        isCompleted
                                                            ? 'line-through text-slate-500'
                                                            : 'text-slate-900 dark:text-white'
                                                    }`}
                                                >
                                                    {task.title}
                                                </h3>

                                                {/* Priority Badge */}
                                                {getPriorityBadge(task.priority)}

                                                {/* Type Badge */}
                                                <span
                                                    className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold ${getTypeBadgeClass(
                                                        task.type
                                                    )}`}
                                                >
                                                    {getTypeIcon(task.type)}
                                                    <span>{typeof task.type === 'object' ? task.type.label : task.type}</span>
                                                </span>

                                                {/* Due Date Indicator */}
                                                {getDueDateBadge(task)}
                                            </div>

                                            {task.description && (
                                                <p className="text-xs text-slate-600 dark:text-slate-400 line-clamp-2">
                                                    {task.description}
                                                </p>
                                            )}

                                            {/* Association tags: Contact, Lead, Rep */}
                                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 pt-0.5">
                                                {task.contact && (
                                                    <span className="flex items-center gap-1">
                                                        <span>Client:</span>
                                                        <Link
                                                            href={route('contacts.show', task.contact.id)}
                                                            className="font-bold text-blue-600 dark:text-blue-400 hover:underline"
                                                        >
                                                            {task.contact.full_name || `${task.contact.first_name} ${task.contact.last_name || ''}`}
                                                        </Link>
                                                        {task.contact.phone && (
                                                            <a
                                                                href={`https://wa.me/${task.contact.phone.replace('+', '')}`}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-emerald-600 hover:text-emerald-700 ml-1"
                                                                title="Open WhatsApp chat"
                                                            >
                                                                <MessageSquare className="h-3 w-3" />
                                                            </a>
                                                        )}
                                                    </span>
                                                )}

                                                {task.lead && (
                                                    <span className="flex items-center gap-1">
                                                        <span>Lead:</span>
                                                        <Link
                                                            href={route('leads.show', task.lead.id)}
                                                            className="font-semibold text-slate-700 dark:text-slate-300 hover:underline"
                                                        >
                                                            {task.lead.title}
                                                        </Link>
                                                    </span>
                                                )}

                                                {task.assigned_user && (
                                                    <span className="flex items-center gap-1">
                                                        <User className="h-3 w-3 text-slate-400" />
                                                        <span>Assigned: <strong className="text-slate-700 dark:text-slate-300">{task.assigned_user.name}</strong></span>
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="flex items-center gap-2 self-end md:self-center">
                                        <button
                                            type="button"
                                            onClick={() => openEditModal(task)}
                                            className="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 text-xs transition"
                                            title="Edit task"
                                        >
                                            <Edit className="h-3.5 w-3.5" />
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => handleDeleteTask(task)}
                                            className="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-600 text-xs transition"
                                            title="Archive task"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            );
                        })
                    ) : (
                        <div className="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 space-y-3">
                            <CheckSquare className="h-10 w-10 text-slate-300 dark:text-slate-700 mx-auto" />
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                {activeView === 'today'
                                    ? "No tasks due today! You're completely caught up."
                                    : activeView === 'overdue'
                                    ? 'Zero overdue tasks. Great follow-up cadence!'
                                    : activeView === 'upcoming'
                                    ? 'No upcoming tasks scheduled.'
                                    : 'No tasks found matching your filters.'}
                            </h3>
                            <p className="text-xs text-slate-500 max-w-sm mx-auto">
                                Schedule follow-up calls, inspection coordination, or payment reminders for your sales leads.
                            </p>
                            <button
                                type="button"
                                onClick={openCreateModal}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition"
                            >
                                <Plus className="h-3.5 w-3.5" />
                                <span>Schedule a Task</span>
                            </button>
                        </div>
                    )}
                </div>

                {/* 5. Pagination */}
                {tasks.links && tasks.links.length > 3 && (
                    <div className="flex items-center justify-between pt-4">
                        <span className="text-xs text-slate-500">
                            Showing {tasks.from || 0} to {tasks.to || 0} of {tasks.total || 0} tasks
                        </span>
                        <div className="flex items-center gap-1">
                            {tasks.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveScroll
                                    className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${
                                        link.active
                                            ? 'bg-blue-600 text-white'
                                            : !link.url
                                            ? 'text-slate-300 dark:text-slate-600 cursor-not-allowed'
                                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Task Create / Edit Modal */}
            {isCreateModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 w-full max-w-xl shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div className="flex items-center gap-2">
                                <CheckSquare className="h-5 w-5 text-blue-600" />
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    {editingTask ? 'Edit Task' : 'Create Sales Task / Follow-up'}
                                </h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsCreateModalOpen(false)}
                                className="p-1 rounded-lg text-slate-400 hover:text-slate-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveTask} className="space-y-4">
                            {/* Title */}
                            <div>
                                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                    Task Title <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={taskForm.data.title}
                                    onChange={(e) => taskForm.setData('title', e.target.value)}
                                    placeholder="e.g., Send survey plan & payment options for Silverstone Plot 12"
                                    className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                />
                                {taskForm.errors.title && (
                                    <p className="text-rose-500 text-xs mt-1">{taskForm.errors.title}</p>
                                )}
                            </div>

                            {/* Contact Picker */}
                            <div>
                                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                    Linked Contact / Client
                                </label>
                                <select
                                    value={taskForm.data.contact_id}
                                    onChange={(e) => taskForm.setData('contact_id', e.target.value)}
                                    className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Select a Contact (Optional)</option>
                                    {contacts.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name} ({c.phone})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Grid: Type & Priority */}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                        Task Type
                                    </label>
                                    <select
                                        value={taskForm.data.type}
                                        onChange={(e) => taskForm.setData('type', e.target.value)}
                                        className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    >
                                        {types.map((t) => (
                                            <option key={t.value} value={t.value}>
                                                {t.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                        Urgency / Priority
                                    </label>
                                    <select
                                        value={taskForm.data.priority}
                                        onChange={(e) => taskForm.setData('priority', e.target.value)}
                                        className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    >
                                        {priorities.map((p) => (
                                            <option key={p.value} value={p.value}>
                                                {p.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Grid: Assigned User & Due Date */}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                        Assigned Representative
                                    </label>
                                    <select
                                        value={taskForm.data.assigned_user_id}
                                        onChange={(e) => taskForm.setData('assigned_user_id', e.target.value)}
                                        className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">Unassigned</option>
                                        {users.map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.name} ({u.role})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                        Due Date & Time <span className="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="datetime-local"
                                        required
                                        value={taskForm.data.due_at}
                                        onChange={(e) => taskForm.setData('due_at', e.target.value)}
                                        className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                    {taskForm.errors.due_at && (
                                        <p className="text-rose-500 text-xs mt-1">{taskForm.errors.due_at}</p>
                                    )}
                                </div>
                            </div>

                            {/* Description */}
                            <div>
                                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                    Description / Action Instructions
                                </label>
                                <textarea
                                    rows="3"
                                    value={taskForm.data.description}
                                    onChange={(e) => taskForm.setData('description', e.target.value)}
                                    placeholder="Add any specific context, requested documents, customer preferences, or inspection notes..."
                                    className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            {/* Modal Actions */}
                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsCreateModalOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={taskForm.processing}
                                    className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold rounded-xl shadow-sm transition disabled:opacity-50"
                                >
                                    {taskForm.processing ? 'Saving...' : editingTask ? 'Update Task' : 'Create Task'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
