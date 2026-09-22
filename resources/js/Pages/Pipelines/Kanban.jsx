import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Columns3,
    List,
    Plus,
    Search,
    RotateCcw,
    DollarSign,
    Flame,
    Target,
    User,
    UserCheck,
    MessageSquare,
    ExternalLink,
    Building,
    CheckCircle2,
    XCircle,
    Clock,
    Sparkles,
    MoveRight,
    TrendingUp,
    Filter
} from 'lucide-react';

export default function Kanban({ board, agents, temperatures }) {
    const { auth, flash } = usePage().props;
    const permissions = auth?.permissions || [];
    const isSuperAdmin = auth?.user?.role === 'Super Admin';
    const canMove = isSuperAdmin || permissions.includes('leads.edit');

    // Local optimistic board state for instantaneous drag-and-drop feedback
    const [columns, setColumns] = useState(board.columns || []);
    const [draggedLead, setDraggedLead] = useState(null);
    const [dragOverStageId, setDragOverStageId] = useState(null);
    const [isMoving, setIsMoving] = useState(false);

    // Filter controls
    const [search, setSearch] = useState(board.filters?.search || '');
    const [selectedAgent, setSelectedAgent] = useState(board.filters?.agent || '');
    const [selectedTemperature, setSelectedTemperature] = useState(board.filters?.temperature || '');

    const handleFilterSubmit = (e) => {
        e?.preventDefault();
        router.get(
            route('pipelines.index'),
            {
                pipeline: board.pipeline?.id,
                search: search || undefined,
                agent: selectedAgent || undefined,
                temperature: selectedTemperature || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleClearFilters = () => {
        setSearch('');
        setSelectedAgent('');
        setSelectedTemperature('');
        router.get(route('pipelines.index', { pipeline: board.pipeline?.id }));
    };

    const handlePipelineChange = (pipelineId) => {
        router.get(route('pipelines.index', { pipeline: pipelineId }));
    };

    // Native Drag and Drop handlers
    const handleDragStart = (e, lead, sourceStageId) => {
        if (!canMove) return;
        setDraggedLead({ ...lead, sourceStageId });
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', JSON.stringify({ leadId: lead.id, sourceStageId }));
    };

    const handleDragOver = (e, stageId) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (dragOverStageId !== stageId) {
            setDragOverStageId(stageId);
        }
    };

    const handleDragLeave = () => {
        setDragOverStageId(null);
    };

    const handleDrop = (e, targetStageId) => {
        e.preventDefault();
        setDragOverStageId(null);

        if (!draggedLead || !canMove) return;
        if (draggedLead.sourceStageId === targetStageId) {
            setDraggedLead(null);
            return;
        }

        executeMoveStage(draggedLead.id, targetStageId, draggedLead.sourceStageId);
        setDraggedLead(null);
    };

    const handleQuickMove = (leadId, targetStageId, sourceStageId) => {
        if (!canMove || targetStageId === sourceStageId) return;
        executeMoveStage(leadId, targetStageId, sourceStageId);
    };

    const executeMoveStage = (leadId, targetStageId, sourceStageId) => {
        setIsMoving(true);

        // Optimistic UI state update
        setColumns((prevColumns) => {
            let movedLead = null;
            const newCols = prevColumns.map((col) => {
                if (col.stage.id === sourceStageId) {
                    const filtered = col.leads.filter((l) => {
                        if (l.id === leadId) {
                            movedLead = { ...l, pipeline_stage_id: targetStageId };
                            return false;
                        }
                        return true;
                    });
                    return { ...col, leads: filtered, count: filtered.length };
                }
                return col;
            });

            if (movedLead) {
                return newCols.map((col) => {
                    if (col.stage.id === targetStageId) {
                        const updated = [movedLead, ...col.leads];
                        return { ...col, leads: updated, count: updated.length };
                    }
                    return col;
                });
            }

            return newCols;
        });

        // Trigger backend route
        router.post(
            route('leads.stage.move', leadId),
            { pipeline_stage_id: targetStageId },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setIsMoving(false),
            }
        );
    };

    const getStageColorBadge = (color) => {
        switch (color) {
            case 'blue':
                return 'bg-blue-500 text-white border-blue-600';
            case 'indigo':
                return 'bg-indigo-500 text-white border-indigo-600';
            case 'sky':
                return 'bg-sky-500 text-white border-sky-600';
            case 'violet':
                return 'bg-violet-500 text-white border-violet-600';
            case 'amber':
                return 'bg-amber-500 text-white border-amber-600';
            case 'orange':
                return 'bg-orange-500 text-white border-orange-600';
            case 'purple':
                return 'bg-purple-600 text-white border-purple-700';
            case 'yellow':
                return 'bg-yellow-500 text-slate-900 border-yellow-600';
            case 'emerald':
                return 'bg-emerald-500 text-white border-emerald-600';
            case 'rose':
                return 'bg-rose-500 text-white border-rose-600';
            case 'slate':
            default:
                return 'bg-slate-500 text-white border-slate-600';
        }
    };

    const getTemperatureIcon = (temp) => {
        switch (temp) {
            case 'hot':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300">
                        <Flame className="h-3 w-3 text-red-600 animate-pulse" />
                        HOT
                    </span>
                );
            case 'warm':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                        ☀️ WARM
                    </span>
                );
            case 'cold':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                        ❄️ COLD
                    </span>
                );
            default:
                return null;
        }
    };

    const getScoreBadge = (score) => {
        let colorClasses = 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300';
        if (score >= 75) {
            colorClasses = 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300';
        } else if (score >= 50) {
            colorClasses = 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300';
        }
        return (
            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black ${colorClasses}`}>
                <Target className="h-3 w-3" />
                {score}/100
            </span>
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">
                            <span>Sales Pipeline</span>
                            <span>/</span>
                            <span className="text-slate-500">Kanban Board</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <h2 className="font-bold text-2xl text-slate-900 dark:text-white leading-tight">
                                {board.pipeline?.name || 'Sales Pipeline'}
                            </h2>
                            {board.pipelines && board.pipelines.length > 1 && (
                                <select
                                    value={board.pipeline?.id}
                                    onChange={(e) => handlePipelineChange(e.target.value)}
                                    className="text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-2.5 py-1 focus:ring-2 focus:ring-blue-500"
                                >
                                    {board.pipelines.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} {p.is_default ? '(Default)' : ''}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>
                    </div>

                    {/* View Switcher & Actions */}
                    <div className="flex items-center flex-wrap gap-2.5">
                        {/* View Switcher: Board vs Table */}
                        <div className="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                            <span className="flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 font-bold rounded-lg shadow-sm">
                                <Columns3 className="h-3.5 w-3.5" />
                                <span>Kanban</span>
                            </span>
                            <Link
                                href={route('leads.index')}
                                className="flex items-center gap-1.5 px-3 py-1.5 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-medium rounded-lg transition"
                            >
                                <List className="h-3.5 w-3.5" />
                                <span>Table View</span>
                            </Link>
                        </div>

                        {/* Add Opportunity */}
                        <Link
                            href={route('leads.create')}
                            className="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold rounded-xl shadow-sm transition"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Add Opportunity</span>
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`${board.pipeline?.name || 'Pipeline'} Kanban - Bamcom AI CRM`} />

            <div className="space-y-4">
                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}

                {/* Pipeline Metrics Summary Strip */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 flex items-center justify-center font-black">
                            <TrendingUp className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Pipeline Value</p>
                            <p className="text-lg font-black text-slate-900 dark:text-white">{board.summary?.formatted_total_value || '₦0'}</p>
                        </div>
                    </div>

                    <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 flex items-center justify-center font-black">
                            <Building className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Active Opportunities</p>
                            <p className="text-lg font-black text-slate-900 dark:text-white">{board.summary?.total_leads || 0} Deals</p>
                        </div>
                    </div>

                    <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 flex items-center justify-center font-black">
                            <CheckCircle2 className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Closed Won Deals</p>
                            <p className="text-lg font-black text-emerald-600 dark:text-emerald-400">{board.summary?.won_leads || 0}</p>
                        </div>
                    </div>

                    <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-red-50 dark:bg-red-950/60 text-red-600 flex items-center justify-center font-black">
                            <Flame className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Hot Opportunities</p>
                            <p className="text-lg font-black text-red-600 dark:text-red-400">{board.summary?.hot_leads || 0}</p>
                        </div>
                    </div>
                </div>

                {/* Multifaceted Kanban Filter Toolbar */}
                <form
                    onSubmit={handleFilterSubmit}
                    className="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-wrap items-center justify-between gap-3 text-xs"
                >
                    <div className="flex flex-wrap items-center gap-2 flex-1 min-w-[280px]">
                        {/* Search keyword */}
                        <div className="relative flex-1 min-w-[180px]">
                            <Search className="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search opportunity, client, property..."
                                className="w-full pl-8 pr-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white placeholder-slate-400 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                        </div>

                        {/* Agent Selector */}
                        <select
                            value={selectedAgent}
                            onChange={(e) => setSelectedAgent(e.target.value)}
                            className="rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Representatives</option>
                            <option value="unassigned">Unassigned Pool</option>
                            {agents.map((ag) => (
                                <option key={ag.id} value={ag.id}>
                                    {ag.name}
                                </option>
                            ))}
                        </select>

                        {/* Temperature Selector */}
                        <select
                            value={selectedTemperature}
                            onChange={(e) => setSelectedTemperature(e.target.value)}
                            className="rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Temperatures</option>
                            {temperatures.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.icon} {t.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="submit"
                            className="px-3 py-1.5 bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 text-white dark:text-slate-900 font-bold rounded-xl shadow-sm transition"
                        >
                            Apply Filters
                        </button>
                        {(search || selectedAgent || selectedTemperature) && (
                            <button
                                type="button"
                                onClick={handleClearFilters}
                                className="px-2.5 py-1.5 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition"
                                title="Reset filters"
                            >
                                <RotateCcw className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                </form>

                {/* Horizontal Scrollable Kanban Columns Container */}
                <div className="relative pb-6 overflow-x-auto">
                    <div className="flex items-start gap-4 min-w-max pb-4">
                        {columns.map((col) => {
                            const stage = col.stage;
                            const isOver = dragOverStageId === stage.id;

                            return (
                                <div
                                    key={stage.id}
                                    onDragOver={(e) => handleDragOver(e, stage.id)}
                                    onDragLeave={handleDragLeave}
                                    onDrop={(e) => handleDrop(e, stage.id)}
                                    className={`w-72 flex-shrink-0 rounded-2xl border transition-all duration-200 flex flex-col max-h-[calc(100vh-250px)] ${
                                        isOver
                                            ? 'border-blue-500 bg-blue-50/40 dark:bg-blue-950/30 ring-2 ring-blue-400/40 shadow-lg'
                                            : 'border-slate-200 dark:border-slate-800 bg-slate-100/60 dark:bg-slate-900/40'
                                    }`}
                                >
                                    {/* Stage Header */}
                                    <div className="p-3.5 border-b border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900 rounded-t-2xl space-y-1.5 sticky top-0 z-10">
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-2 min-w-0">
                                                <span className={`h-2.5 w-2.5 rounded-full flex-shrink-0 ${getStageColorBadge(stage.color).split(' ')[0]}`}></span>
                                                <h3 className="font-bold text-xs text-slate-900 dark:text-white truncate" title={stage.name}>
                                                    {stage.name}
                                                </h3>
                                            </div>
                                            <span className="px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex-shrink-0">
                                                {col.count}
                                            </span>
                                        </div>

                                        <div className="flex items-center justify-between text-[11px] text-slate-500 pt-0.5">
                                            <span>Win Prob: {stage.probability}%</span>
                                            <span className="font-bold text-slate-700 dark:text-slate-300">{col.formatted_value}</span>
                                        </div>
                                    </div>

                                    {/* Column Lead Cards Body */}
                                    <div className="p-2 space-y-2.5 overflow-y-auto flex-1 custom-scrollbar min-h-[140px]">
                                        {col.leads && col.leads.length > 0 ? (
                                            col.leads.map((lead) => {
                                                const waText = encodeURIComponent(
                                                    `Hello ${lead.contact?.first_name || 'there'}, following up on "${lead.title}" from Bamcom Properties.`
                                                );
                                                const waUrl = lead.contact?.phone
                                                    ? `https://wa.me/${lead.contact.phone.replace('+', '')}?text=${waText}`
                                                    : null;

                                                return (
                                                    <div
                                                        key={lead.id}
                                                        draggable={canMove}
                                                        onDragStart={(e) => handleDragStart(e, lead, stage.id)}
                                                        className={`p-3.5 rounded-xl border bg-white dark:bg-slate-900 border-slate-200/90 dark:border-slate-800 shadow-sm transition-all hover:shadow-md cursor-grab active:cursor-grabbing group ${
                                                            draggedLead?.id === lead.id ? 'opacity-40 scale-95 ring-2 ring-blue-500' : ''
                                                        }`}
                                                    >
                                                        {/* Top Row: Temperature & Score */}
                                                        <div className="flex items-center justify-between gap-1 mb-2">
                                                            {getTemperatureIcon(lead.temperature?.value || lead.temperature)}
                                                            {getScoreBadge(lead.score)}
                                                        </div>

                                                        {/* Lead Title */}
                                                        <Link
                                                            href={route('leads.show', lead.id)}
                                                            className="block font-bold text-xs text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 hover:underline leading-snug line-clamp-2"
                                                        >
                                                            {lead.title}
                                                        </Link>

                                                        {/* Budget */}
                                                        <p className="text-xs font-black text-slate-800 dark:text-slate-200 mt-1 flex items-center gap-1">
                                                            <span>{lead.formatted_budget}</span>
                                                        </p>

                                                        {/* Contact Details & WhatsApp */}
                                                        {lead.contact && (
                                                            <div className="flex items-center justify-between pt-2 mt-2 border-t border-slate-100 dark:border-slate-800 text-[11px]">
                                                                <div className="flex items-center gap-1.5 min-w-0">
                                                                    <div className="h-5 w-5 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-[9px] flex-shrink-0">
                                                                        {lead.contact.first_name?.[0] || 'C'}
                                                                    </div>
                                                                    <span className="truncate text-slate-600 dark:text-slate-400 font-medium">
                                                                        {lead.contact.first_name} {lead.contact.last_name}
                                                                    </span>
                                                                </div>

                                                                {waUrl && (
                                                                    <a
                                                                        href={waUrl}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="p-1 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 rounded-lg transition"
                                                                        title="Quick WhatsApp message"
                                                                        onClick={(e) => e.stopPropagation()}
                                                                    >
                                                                        <MessageSquare className="h-3.5 w-3.5" />
                                                                    </a>
                                                                )}
                                                            </div>
                                                        )}

                                                        {/* Footer: Agent avatar & Quick Move Select */}
                                                        <div className="flex items-center justify-between pt-2 mt-2 border-t border-slate-100 dark:border-slate-800 text-[10px]">
                                                            <div className="flex items-center gap-1 text-slate-500 truncate" title={lead.assigned_user?.name || 'Unassigned'}>
                                                                <UserCheck className="h-3 w-3 text-slate-400 flex-shrink-0" />
                                                                <span className="truncate">{lead.assigned_user?.name?.split(' ')[0] || 'Unassigned'}</span>
                                                            </div>

                                                            {/* Mobile / Keyboard Stage Move Dropdown */}
                                                            {canMove && (
                                                                <select
                                                                    value={stage.id}
                                                                    onChange={(e) => handleQuickMove(lead.id, Number(e.target.value), stage.id)}
                                                                    onClick={(e) => e.stopPropagation()}
                                                                    className="text-[10px] font-semibold rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 py-0.5 px-1 text-slate-700 dark:text-slate-300 focus:ring-1 focus:ring-blue-500"
                                                                    title="Move to stage"
                                                                >
                                                                    {columns.map((c) => (
                                                                        <option key={c.stage.id} value={c.stage.id}>
                                                                            &rarr; {c.stage.name}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })
                                        ) : (
                                            <div className="p-6 text-center rounded-xl border border-dashed border-slate-300/80 dark:border-slate-800/80 text-slate-400 text-xs">
                                                <p className="font-medium">No opportunities</p>
                                                <p className="text-[10px] text-slate-400 mt-0.5">Drag deals into this stage</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
