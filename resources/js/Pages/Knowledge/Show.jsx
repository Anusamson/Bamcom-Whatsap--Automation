import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    BookOpen, 
    ArrowLeft, 
    Edit, 
    Trash2, 
    Sparkles, 
    CheckCircle2, 
    Clock, 
    AlertTriangle, 
    Calendar, 
    ArrowUpDown, 
    Tag, 
    User,
    Building, 
    HelpCircle, 
    BadgePercent, 
    MapPin, 
    CalendarCheck, 
    CreditCard, 
    ShieldAlert, 
    FileText,
    Terminal,
    ToggleRight,
    ToggleLeft
} from 'lucide-react';

const iconMap = {
    Building,
    HelpCircle,
    BadgePercent,
    MapPin,
    CalendarCheck,
    CreditCard,
    ShieldAlert,
    FileText,
};

export default function Show({ record }) {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete knowledge record "${record.title}"?`)) {
            router.delete(route('knowledge.destroy', record.id));
        }
    };

    const handleToggleStatus = () => {
        router.patch(route('knowledge.toggle-status', record.id), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('knowledge.index', { category: record.category })}
                            className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                    {record.title}
                                </h2>
                                {record.is_active_for_ai ? (
                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        <CheckCircle2 className="w-3.5 h-3.5" />
                                        AI Active
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        <Clock className="w-3.5 h-3.5" />
                                        Inactive for AI
                                    </span>
                                )}
                            </div>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {record.category_label} &middot; Priority {record.priority}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {can('knowledge.edit') && (
                            <button
                                type="button"
                                onClick={handleToggleStatus}
                                className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition"
                            >
                                {record.status === 'active' ? (
                                    <>
                                        <ToggleRight className="w-4 h-4 text-emerald-500" />
                                        Set as Draft
                                    </>
                                ) : (
                                    <>
                                        <ToggleLeft className="w-4 h-4 text-slate-400" />
                                        Activate for AI
                                    </>
                                )}
                            </button>
                        )}

                        {can('knowledge.edit') && (
                            <Link
                                href={route('knowledge.edit', record.id)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition"
                            >
                                <Edit className="w-4 h-4" />
                                Edit Record
                            </Link>
                        )}

                        {can('knowledge.delete') && (
                            <button
                                type="button"
                                onClick={handleDelete}
                                className="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                                title="Delete Record"
                            >
                                <Trash2 className="w-4 h-4" />
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={record.title} />

            <div className="py-6 max-w-4xl mx-auto space-y-6">
                {/* AI Readiness Banner */}
                {record.is_active_for_ai ? (
                    <div className="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-2xl p-4 flex items-start gap-3">
                        <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                        <div className="text-xs text-emerald-900 dark:text-emerald-200 space-y-0.5">
                            <p className="font-bold">Live in AI Inference Context</p>
                            <p className="text-emerald-700 dark:text-emerald-300">
                                This knowledge record meets all activity and schedule criteria. The AI assistant ingests and cites these facts when answering relevant customer conversations on WhatsApp.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-2xl p-4 flex items-start gap-3">
                        <AlertTriangle className="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                        <div className="text-xs text-amber-900 dark:text-amber-200 space-y-0.5">
                            <p className="font-bold">Excluded from AI Inference</p>
                            <p className="text-amber-700 dark:text-amber-300">
                                {record.status !== 'active'
                                    ? `Record status is '${record.status}'. Set status to 'Active' to enable AI ingestion.`
                                    : record.is_expired
                                    ? `This record expired on ${record.expiration_date} and is no longer available to AI models.`
                                    : record.is_scheduled
                                    ? `This record is scheduled for future activation on ${record.effective_date}.`
                                    : 'Record is currently inactive.'}
                            </p>
                        </div>
                    </div>
                )}

                {/* Primary Content Card */}
                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700 pb-3">
                        <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold border ${record.category_badge}`}>
                            {record.category_label}
                        </span>

                        <span className="text-xs text-slate-400 flex items-center gap-1">
                            <ArrowUpDown className="w-3.5 h-3.5" />
                            Context Priority Rank: <strong className="text-slate-700 dark:text-slate-300">{record.priority}</strong>
                        </span>
                    </div>

                    <div className="prose dark:prose-invert max-w-none text-slate-800 dark:text-slate-200 text-sm leading-relaxed whitespace-pre-wrap font-sans">
                        {record.content}
                    </div>

                    {/* Keywords */}
                    {record.keywords && record.keywords.length > 0 && (
                        <div className="pt-4 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center gap-1.5">
                            <span className="text-xs font-semibold text-slate-500 flex items-center gap-1">
                                <Tag className="w-3.5 h-3.5 text-slate-400" />
                                Target Keywords:
                            </span>
                            {record.keywords.map((kw, i) => (
                                <span
                                    key={i}
                                    className="text-xs px-2.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800"
                                >
                                    {kw}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {/* Simulated AI Prompt Injection Preview */}
                <div className="bg-slate-900 text-slate-200 p-6 rounded-2xl shadow-sm space-y-3 font-mono text-xs border border-slate-800">
                    <div className="flex items-center justify-between text-slate-400 border-b border-slate-800 pb-2">
                        <span className="flex items-center gap-2 font-sans font-bold text-white text-xs">
                            <Terminal className="w-4 h-4 text-indigo-400" />
                            Simulated LLM Grounding Context
                        </span>
                        <span className="text-[11px] text-slate-500 font-sans">
                            {record.is_active_for_ai ? 'Included in prompt' : 'Excluded from prompt'}
                        </span>
                    </div>
                    <p className="text-slate-400 text-[11px] font-sans">
                        How this knowledge record is structured when injected into the AI system instruction:
                    </p>
                    <div className="bg-slate-950 p-4 rounded-xl text-emerald-400 overflow-x-auto whitespace-pre-wrap leading-relaxed">
{`### ${record.title}
${record.content}`}
                    </div>
                </div>

                {/* Audit & Dates Metadata */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <span className="text-xs font-medium text-slate-400 flex items-center gap-1">
                            <Calendar className="w-3.5 h-3.5" />
                            Effective Window
                        </span>
                        <p className="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-2">
                            {record.effective_date ? `From ${record.effective_date}` : 'Immediately effective'}
                        </p>
                        <p className="text-[11px] text-slate-400 mt-0.5">
                            {record.expiration_date ? `Until ${record.expiration_date}` : 'No expiration set'}
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <span className="text-xs font-medium text-slate-400 flex items-center gap-1">
                            <User className="w-3.5 h-3.5" />
                            Author & Ownership
                        </span>
                        <p className="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-2">
                            {record.creator?.name || 'System Admin'}
                        </p>
                        <p className="text-[11px] text-slate-400 mt-0.5">
                            Created: {new Date(record.created_at).toLocaleDateString()}
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <span className="text-xs font-medium text-slate-400 flex items-center gap-1">
                            <Clock className="w-3.5 h-3.5" />
                            Last Modified
                        </span>
                        <p className="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-2">
                            {record.updater?.name || record.creator?.name || 'Staff'}
                        </p>
                        <p className="text-[11px] text-slate-400 mt-0.5">
                            Updated: {new Date(record.updated_at).toLocaleDateString()}
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
