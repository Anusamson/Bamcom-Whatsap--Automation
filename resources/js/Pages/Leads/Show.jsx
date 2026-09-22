import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    Edit,
    ExternalLink,
    Flame,
    Mail,
    MapPin,
    MessageSquare,
    Phone,
    ShieldCheck,
    Target,
    Trash2,
    TrendingUp,
    User,
    UserCheck,
    Users,
    XCircle,
    AlertCircle,
    Send,
    Sparkles,
    Check
} from 'lucide-react';

export default function Show({ lead, agents, statuses, temperatures }) {
    const { auth, flash } = usePage().props;
    const permissions = auth?.permissions || [];
    const can = (perm) => permissions.includes(perm) || auth?.user?.role === 'Super Admin';

    const [isDeleting, setIsDeleting] = useState(false);
    const [selectedAgentId, setSelectedAgentId] = useState(lead.assigned_user_id || '');
    const [isAssigning, setIsAssigning] = useState(false);
    const [showLostModal, setShowLostModal] = useState(false);
    const [lostReason, setLostReason] = useState(lead.lost_reason || '');

    // Standard linear pipeline stages
    const pipelineStages = [
        { key: 'new', label: 'New Lead' },
        { key: 'contacted', label: 'Contacted' },
        { key: 'qualified', label: 'Qualified' },
        { key: 'proposal_sent', label: 'Proposal Sent' },
        { key: 'negotiation', label: 'Negotiation' },
        { key: 'won', label: 'Won Deal' },
    ];

    const currentStageIndex = pipelineStages.findIndex((s) => s.key === lead.status);
    const isClosedLost = lead.status === 'lost';
    const isDisqualified = lead.status === 'disqualified';

    const handleAdvanceStage = (stageKey) => {
        if (!can('leads.edit')) return;
        if (stageKey === lead.status) return;

        router.post(
            route('leads.status', lead.id),
            { status: stageKey },
            { preserveScroll: true }
        );
    };

    const handleMarkLost = (e) => {
        e.preventDefault();
        router.post(
            route('leads.status', lead.id),
            {
                status: 'lost',
                lost_reason: lostReason,
            },
            {
                preserveScroll: true,
                onSuccess: () => setShowLostModal(false),
            }
        );
    };

    const handleAssignAgent = (e) => {
        e.preventDefault();
        setIsAssigning(true);
        router.post(
            route('leads.assign', lead.id),
            { assigned_user_id: selectedAgentId || null },
            {
                preserveScroll: true,
                onFinish: () => setIsAssigning(false),
            }
        );
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to archive opportunity "${lead.title}"?`)) {
            setIsDeleting(true);
            router.delete(route('leads.destroy', lead.id));
        }
    };

    const getTemperatureBadge = (temp) => {
        switch (temp) {
            case 'hot':
                return (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300 border border-red-200 dark:border-red-800">
                        <Flame className="h-3.5 w-3.5 text-red-600 animate-pulse" />
                        <span>HOT OPPORTUNITY</span>
                    </span>
                );
            case 'warm':
                return (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        <span>☀️ WARM LEAD</span>
                    </span>
                );
            case 'cold':
                return (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                        <span>❄️ COLD LEAD</span>
                    </span>
                );
            default:
                return null;
        }
    };

    const getScoreColor = (score) => {
        if (score >= 75) return 'text-red-600 dark:text-red-400 bg-red-500';
        if (score >= 50) return 'text-amber-600 dark:text-amber-400 bg-amber-500';
        return 'text-blue-600 dark:text-blue-400 bg-blue-500';
    };

    const waCustomText = encodeURIComponent(
        `Hello ${lead.contact?.first_name || 'there'}, I am following up on your real estate interest in "${lead.property_interest || lead.title}" at Bamcom Properties. Do you have a few minutes to connect?`
    );
    const waUrl = lead.contact?.phone
        ? `https://wa.me/${lead.contact.phone.replace('+', '')}?text=${waCustomText}`
        : null;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">
                            <Link href={route('leads.index')} className="hover:underline flex items-center gap-1">
                                <ArrowLeft className="h-3.5 w-3.5" />
                                <span>Sales Pipeline</span>
                            </Link>
                            <span>/</span>
                            <span className="text-slate-500">Opportunity Details</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <h2 className="font-bold text-2xl text-slate-900 dark:text-white leading-tight">
                                {lead.title}
                            </h2>
                            {getTemperatureBadge(lead.temperature)}
                        </div>
                    </div>

                    {/* Quick Header Actions */}
                    <div className="flex items-center flex-wrap gap-2.5">
                        {waUrl && (
                            <a
                                href={waUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition"
                            >
                                <MessageSquare className="h-4 w-4" />
                                <span>WhatsApp Contact</span>
                            </a>
                        )}

                        {lead.contact?.phone && (
                            <a
                                href={`tel:${lead.contact.phone}`}
                                className="inline-flex items-center gap-2 px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-xl transition"
                            >
                                <Phone className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                <span>Call</span>
                            </a>
                        )}

                        {can('leads.edit') && (
                            <Link
                                href={route('leads.edit', lead.id)}
                                className="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 text-white dark:text-slate-900 text-sm font-semibold rounded-xl transition"
                            >
                                <Edit className="h-4 w-4" />
                                <span>Edit Deal</span>
                            </Link>
                        )}

                        {can('leads.delete') && (
                            <button
                                type="button"
                                onClick={handleDelete}
                                disabled={isDeleting}
                                className="inline-flex items-center gap-1.5 px-3 py-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl text-sm font-medium transition"
                            >
                                <Trash2 className="h-4 w-4" />
                                <span>Archive</span>
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`${lead.title} - Opportunity 360 - Bamcom AI CRM`} />

            <div className="space-y-6">
                {/* Flash message */}
                {flash?.success && (
                    <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}

                {/* Pipeline Stage Visualizer Strip */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                <TrendingUp className="h-4 w-4 text-blue-600" />
                                <span>Sales Pipeline Progress</span>
                            </h3>
                            <p className="text-xs text-slate-500">
                                Click on any stage to advance this real estate opportunity.
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {lead.status === 'won' ? (
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300 font-bold text-xs rounded-lg border border-emerald-300 dark:border-emerald-800">
                                    <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                    Closed Won Deal
                                </span>
                            ) : lead.status === 'lost' ? (
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-100 dark:bg-rose-950/50 text-rose-800 dark:text-rose-300 font-bold text-xs rounded-lg border border-rose-300 dark:border-rose-800">
                                    <XCircle className="h-4 w-4 text-rose-600" />
                                    Closed Lost: {lead.lost_reason || 'Deal Cancelled'}
                                </span>
                            ) : lead.status === 'disqualified' ? (
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs rounded-lg">
                                    <AlertCircle className="h-4 w-4 text-slate-500" />
                                    Disqualified
                                </span>
                            ) : (
                                can('leads.edit') && (
                                    <button
                                        type="button"
                                        onClick={() => setShowLostModal(true)}
                                        className="text-xs text-rose-600 hover:text-rose-700 dark:text-rose-400 font-semibold hover:underline"
                                    >
                                        Mark as Lost
                                    </button>
                                )
                            )}
                        </div>
                    </div>

                    {/* Stage Steps */}
                    <div className="grid grid-cols-2 sm:grid-cols-6 gap-2">
                        {pipelineStages.map((stage, idx) => {
                            const isCurrent = lead.status === stage.key;
                            const isPast = currentStageIndex > -1 && idx < currentStageIndex && !isClosedLost && !isDisqualified;

                            let buttonClasses = 'p-3 rounded-xl text-center text-xs font-bold transition border ';
                            if (isCurrent) {
                                buttonClasses += stage.key === 'won'
                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-md ring-2 ring-emerald-400/40 '
                                    : 'bg-blue-600 text-white border-blue-600 shadow-md ring-2 ring-blue-400/40 ';
                            } else if (isPast) {
                                buttonClasses += 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-900 hover:bg-blue-100 ';
                            } else {
                                buttonClasses += 'bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 ';
                            }

                            return (
                                <button
                                    key={stage.key}
                                    type="button"
                                    onClick={() => handleAdvanceStage(stage.key)}
                                    disabled={!can('leads.edit')}
                                    className={buttonClasses}
                                    title={can('leads.edit') ? `Set status to ${stage.label}` : stage.label}
                                >
                                    <div className="flex items-center justify-center gap-1 mb-1">
                                        {isPast && <Check className="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />}
                                        {isCurrent && <Sparkles className="h-3.5 w-3.5" />}
                                        <span>Step {idx + 1}</span>
                                    </div>
                                    <p className="truncate text-xs font-semibold">{stage.label}</p>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Main Content 2-Column Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column (2 Cols): Opportunity Specifications & Lead Intelligence */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Property & Deal Overview Card */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
                            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                                <div className="flex items-center gap-3">
                                    <div className="h-10 w-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                        <Building className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                            Opportunity Specifications
                                        </h3>
                                        <p className="text-xs text-slate-500">
                                            Client requirements, budget range, and timeline.
                                        </p>
                                    </div>
                                </div>
                                <span className="text-xs font-mono text-slate-400">
                                    UUID: {lead.uuid?.slice(0, 8)}...
                                </span>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 space-y-1">
                                    <span className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                                        <Building className="h-3.5 w-3.5 text-blue-500" />
                                        Property Interest
                                    </span>
                                    <p className="font-bold text-slate-900 dark:text-white text-sm">
                                        {lead.property_interest || 'General Residential Acquisition'}
                                    </p>
                                </div>

                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 space-y-1">
                                    <span className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                                        <MapPin className="h-3.5 w-3.5 text-emerald-500" />
                                        Preferred Location
                                    </span>
                                    <p className="font-bold text-slate-900 dark:text-white text-sm">
                                        {lead.preferred_location || 'Lagos State (Flexible)'}
                                    </p>
                                </div>

                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 space-y-1">
                                    <span className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                                        <DollarSign className="h-3.5 w-3.5 text-amber-500" />
                                        Target Budget Range
                                    </span>
                                    <p className="font-bold text-slate-900 dark:text-white text-sm">
                                        {lead.formatted_budget}
                                    </p>
                                    {(lead.budget_min || lead.budget_max) && (
                                        <p className="text-[11px] text-slate-500">
                                            Min: {lead.budget_min ? `₦${Number(lead.budget_min).toLocaleString()}` : '0'} &bull; Max: {lead.budget_max ? `₦${Number(lead.budget_max).toLocaleString()}` : 'Open'}
                                        </p>
                                    )}
                                </div>

                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 space-y-1">
                                    <span className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                                        <Calendar className="h-3.5 w-3.5 text-purple-500" />
                                        Purchase Timeline
                                    </span>
                                    <p className="font-bold text-slate-900 dark:text-white text-sm capitalize">
                                        {lead.purchase_timeline ? lead.purchase_timeline.replace('_', ' ') : 'Flexible'}
                                    </p>
                                </div>

                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 space-y-1">
                                    <span className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                                        <Target className="h-3.5 w-3.5 text-indigo-500" />
                                        Qualification Status
                                    </span>
                                    <p className="font-bold text-slate-900 dark:text-white text-sm capitalize">
                                        {lead.qualification_status ? lead.qualification_status.replace('_', ' ') : 'Unqualified'}
                                    </p>
                                </div>

                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 space-y-1">
                                    <span className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                                        <Sparkles className="h-3.5 w-3.5 text-pink-500" />
                                        Lead Source
                                    </span>
                                    <p className="font-bold text-slate-900 dark:text-white text-sm capitalize">
                                        {lead.lead_source ? lead.lead_source.replace('_', ' ') : 'Direct WhatsApp'}
                                    </p>
                                </div>
                            </div>

                            {/* Detailed Notes */}
                            {lead.notes && (
                                <div className="pt-2 border-t border-slate-100 dark:border-slate-800">
                                    <h4 className="text-xs font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                                        <span>Client Notes & Discovery Observations</span>
                                    </h4>
                                    <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                                        {lead.notes}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* WhatsApp Quick Engagement Section */}
                        <div className="bg-gradient-to-br from-emerald-900/10 via-emerald-950/20 to-slate-900 rounded-2xl border border-emerald-500/20 p-6 shadow-sm space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="h-10 w-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                                        <MessageSquare className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                            Instant WhatsApp Follow-up
                                        </h3>
                                        <p className="text-xs text-slate-500 dark:text-slate-400">
                                            Send tailored property details directly to {lead.contact?.first_name || 'the contact'}.
                                        </p>
                                    </div>
                                </div>
                                <span className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                    WhatsApp Direct
                                </span>
                            </div>

                            <div className="p-4 rounded-xl bg-white dark:bg-slate-950/60 border border-emerald-200 dark:border-emerald-800/40 text-xs text-slate-700 dark:text-slate-300 italic">
                                "Hello {lead.contact?.first_name || 'there'}, I am following up on your real estate interest in "{lead.property_interest || lead.title}" at Bamcom Properties. Do you have a few minutes to connect?"
                            </div>

                            {waUrl ? (
                                <a
                                    href={waUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="w-full flex items-center justify-center gap-2 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-bold rounded-xl shadow-md transition"
                                >
                                    <MessageSquare className="h-4 w-4" />
                                    <span>Launch WhatsApp Chat with Contact</span>
                                    <ExternalLink className="h-3.5 w-3.5 opacity-80" />
                                </a>
                            ) : (
                                <p className="text-xs text-amber-600 dark:text-amber-400">
                                    No phone number recorded on this contact to trigger WhatsApp.
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Right Column: Scorecard, Contact Profile & Agent Assignment */}
                    <div className="space-y-6">
                        {/* Qualification Score Card */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                    <Target className="h-4 w-4 text-blue-600" />
                                    <span>Lead Scorecard</span>
                                </h3>
                                <span className="text-xs text-slate-500">Max: 100</span>
                            </div>

                            <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-center space-y-2">
                                <div className="text-4xl font-black tracking-tight flex items-center justify-center gap-1">
                                    <span className={getScoreColor(lead.score).split(' ')[0]}>
                                        {lead.score}
                                    </span>
                                    <span className="text-slate-400 text-lg font-normal">/100</span>
                                </div>
                                <div className="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                                    <div
                                        className={`h-2.5 rounded-full ${getScoreColor(lead.score).split(' ')[1]}`}
                                        style={{ width: `${Math.min(100, Math.max(0, lead.score))}%` }}
                                    ></div>
                                </div>
                                <p className="text-xs text-slate-500">
                                    {lead.score >= 75
                                        ? '🔥 Priority Deal: High conversion likelihood.'
                                        : lead.score >= 50
                                        ? '☀️ Strong Potential: Ongoing engagement recommended.'
                                        : '❄️ Early Stage: Nurturing and discovery needed.'}
                                </p>
                            </div>

                            <div className="space-y-2 text-xs">
                                <div className="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                    <span className="text-slate-500">Temperature Weight</span>
                                    <span className="font-semibold text-slate-700 dark:text-slate-300 capitalize">
                                        {lead.temperature} ({lead.temperature === 'hot' ? '+35' : lead.temperature === 'warm' ? '+15' : '+0'})
                                    </span>
                                </div>
                                <div className="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                    <span className="text-slate-500">Qualification Status</span>
                                    <span className="font-semibold text-slate-700 dark:text-slate-300 capitalize">
                                        {lead.qualification_status} ({lead.qualification_status === 'qualified' ? '+20' : '+10'})
                                    </span>
                                </div>
                                <div className="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                    <span className="text-slate-500">Timeline Urgency</span>
                                    <span className="font-semibold text-slate-700 dark:text-slate-300 capitalize">
                                        {lead.purchase_timeline ? lead.purchase_timeline.replace('_', ' ') : 'Flexible'}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between py-1">
                                    <span className="text-slate-500">Budget Defined</span>
                                    <span className="font-semibold text-slate-700 dark:text-slate-300">
                                        {lead.budget_max || lead.budget_min ? 'Yes (+10)' : 'No (0)'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Linked 360 Contact Profile Card */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                    <User className="h-4 w-4 text-blue-600" />
                                    <span>Associated Contact</span>
                                </h3>
                                {lead.contact && (
                                    <Link
                                        href={route('contacts.show', lead.contact.id)}
                                        className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold flex items-center gap-1"
                                    >
                                        <span>360 Profile</span>
                                        <ExternalLink className="h-3 w-3" />
                                    </Link>
                                )}
                            </div>

                            {lead.contact ? (
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <div className="h-11 w-11 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-sm">
                                            {lead.contact.initials || 'C'}
                                        </div>
                                        <div>
                                            <Link
                                                href={route('contacts.show', lead.contact.id)}
                                                className="font-bold text-slate-900 dark:text-white text-sm hover:underline"
                                            >
                                                {lead.contact.full_name}
                                            </Link>
                                            <p className="text-xs text-slate-500">
                                                {lead.contact.occupation || 'Client'} &bull; {lead.contact.location || 'Nigeria'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                                        {lead.contact.phone && (
                                            <div className="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                                                <Phone className="h-3.5 w-3.5 text-slate-400" />
                                                <span className="font-mono">{lead.contact.phone}</span>
                                            </div>
                                        )}
                                        {lead.contact.email && (
                                            <div className="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                                                <Mail className="h-3.5 w-3.5 text-slate-400" />
                                                <span>{lead.contact.email}</span>
                                            </div>
                                        )}
                                        {lead.contact.last_contact_at && (
                                            <div className="flex items-center gap-2 text-slate-500">
                                                <Clock className="h-3.5 w-3.5 text-slate-400" />
                                                <span>Last Touchpoint: {new Date(lead.contact.last_contact_at).toLocaleDateString()}</span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <p className="text-xs text-slate-500 italic">No contact assigned to this lead.</p>
                            )}
                        </div>

                        {/* Assigned Sales Representative Card */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                <UserCheck className="h-4 w-4 text-blue-600" />
                                <span>Assigned Representative</span>
                            </h3>

                            {lead.assigned_user ? (
                                <div className="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800">
                                    <div className="h-10 w-10 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-xs">
                                        {lead.assigned_user.name?.charAt(0)}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-bold text-slate-900 dark:text-white text-sm truncate">
                                            {lead.assigned_user.name}
                                        </p>
                                        <p className="text-xs text-slate-500 truncate">
                                            {lead.assigned_user.email} &bull; {lead.assigned_user.team?.name || 'Sales'}
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-300">
                                    No representative assigned yet. Lead is unassigned in pool.
                                </div>
                            )}

                            {/* Representative Reassignment Form */}
                            {can('leads.assign') && (
                                <form onSubmit={handleAssignAgent} className="space-y-3 pt-2">
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        Reassign Deal Officer
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <select
                                            value={selectedAgentId}
                                            onChange={(e) => setSelectedAgentId(e.target.value)}
                                            className="flex-1 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                        >
                                            <option value="">Unassigned</option>
                                            {agents.map((agent) => (
                                                <option key={agent.id} value={agent.id}>
                                                    {agent.name} ({agent.role || 'Agent'})
                                                </option>
                                            ))}
                                        </select>
                                        <button
                                            type="submit"
                                            disabled={isAssigning || String(selectedAgentId) === String(lead.assigned_user_id || '')}
                                            className="px-3 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold text-xs rounded-xl shadow-sm transition"
                                        >
                                            {isAssigning ? 'Saving...' : 'Assign'}
                                        </button>
                                    </div>
                                </form>
                            )}
                        </div>

                        {/* Metadata Audit Trail */}
                        <div className="text-xs text-slate-400 space-y-1 px-1">
                            <p>Created: {new Date(lead.created_at).toLocaleString()}</p>
                            <p>Last Modified: {new Date(lead.updated_at).toLocaleString()}</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal for Marking Deal as Lost */}
            {showLostModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 max-w-md w-full space-y-4 shadow-xl">
                        <div className="flex items-center gap-3 text-rose-600">
                            <XCircle className="h-6 w-6" />
                            <h3 className="font-bold text-lg text-slate-900 dark:text-white">
                                Mark Opportunity as Lost
                            </h3>
                        </div>
                        <p className="text-xs text-slate-500">
                            Provide a reason for closing this opportunity as lost to improve sales pipeline analytics.
                        </p>
                        <form onSubmit={handleMarkLost} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Reason for Loss / Discontinuation
                                </label>
                                <textarea
                                    rows="3"
                                    value={lostReason}
                                    onChange={(e) => setLostReason(e.target.value)}
                                    placeholder="e.g. Client purchased elsewhere, budget constraints, changed location requirements..."
                                    className="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-3 focus:ring-2 focus:ring-rose-500 focus:outline-none"
                                />
                            </div>
                            <div className="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowLostModal(false)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-sm"
                                >
                                    Confirm Mark Lost
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
