import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Sparkles,
    Calendar,
    RefreshCw,
    TrendingUp,
    Building2,
    HelpCircle,
    AlertTriangle,
    CreditCard,
    DollarSign,
    UserCheck,
    XCircle,
    CalendarCheck,
    CheckCircle2,
    ShieldCheck,
    Clock,
    ArrowUpRight,
    History,
    ChevronRight,
    Layers,
    Bot
} from 'lucide-react';
import { useState } from 'react';

export default function Index({
    currentInsight,
    history = [],
    dateRange = {}
}) {
    const [selectedPeriod, setSelectedPeriod] = useState(dateRange.period || '30d');
    const [dateFrom, setDateFrom] = useState(dateRange.date_from || '');
    const [dateTo, setDateTo] = useState(dateRange.date_to || '');
    const [isGenerating, setIsGenerating] = useState(false);
    const [showHistory, setShowHistory] = useState(false);

    const periods = [
        { key: 'today', label: 'Today' },
        { key: '7d', label: '7D' },
        { key: '30d', label: '30D' },
        { key: '90d', label: '90D' },
        { key: 'this_month', label: 'This Month' },
        { key: 'this_year', label: 'This Year' },
    ];

    const handlePeriodChange = (period) => {
        setSelectedPeriod(period);
        router.get(
            route('sales-intelligence.index'),
            { period },
            { preserveState: true }
        );
    };

    const handleCustomFilter = (e) => {
        e.preventDefault();
        router.get(
            route('sales-intelligence.index'),
            { period: 'custom', date_from: dateFrom, date_to: dateTo },
            { preserveState: true }
        );
    };

    const handleGenerate = () => {
        setIsGenerating(true);
        router.post(
            route('sales-intelligence.generate'),
            { period: selectedPeriod, date_from: dateFrom, date_to: dateTo },
            {
                onFinish: () => setIsGenerating(false),
            }
        );
    };

    const metrics = currentInsight?.metrics_snapshot || {};
    const insights = currentInsight?.insights || {};
    const recommendations = currentInsight?.recommendations || [];

    const l2i = metrics.lead_to_inspection_conversion || { conversion_rate: 0, total_leads: 0, leads_with_inspections: 0 };
    const i2s = metrics.inspection_to_sale_conversion || { conversion_rate: 0, completed_inspections: 0, won_deals: 0, won_revenue: 0 };

    return (
        <AuthenticatedLayout header="AI Sales Intelligence">
            <Head title="AI Sales Intelligence & Executive Insights | BAMCOM Real Estate CRM" />

            <div className="space-y-6 max-w-7xl mx-auto pb-12">
                {/* Header & Controls Toolbar */}
                <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700/60 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <div className="p-2 bg-gradient-to-tr from-purple-600 to-indigo-600 rounded-xl text-white shadow-md shadow-purple-500/20">
                                <Sparkles className="w-5 h-5" />
                            </div>
                            <h1 className="text-xl font-black text-gray-900 dark:text-white tracking-tight">
                                AI Sales Intelligence
                            </h1>
                            <span className="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                Ground Truth
                            </span>
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Executive management insights and pattern discovery synthesized strictly from verified database analytics.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        {/* Period presets */}
                        <div className="flex items-center bg-gray-50 dark:bg-gray-900/60 p-1 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                            {periods.map((p) => (
                                <button
                                    key={p.key}
                                    type="button"
                                    onClick={() => handlePeriodChange(p.key)}
                                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition ${
                                        selectedPeriod === p.key
                                            ? 'bg-purple-600 text-white shadow-sm'
                                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
                                    }`}
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>

                        {/* Date Inputs Form */}
                        <form onSubmit={handleCustomFilter} className="flex items-center gap-2">
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => {
                                    setDateFrom(e.target.value);
                                    setSelectedPeriod('custom');
                                }}
                                className="text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-2.5 py-1.5 text-gray-800 dark:text-gray-200"
                            />
                            <span className="text-xs text-gray-400">to</span>
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => {
                                    setDateTo(e.target.value);
                                    setSelectedPeriod('custom');
                                }}
                                className="text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-2.5 py-1.5 text-gray-800 dark:text-gray-200"
                            />
                            <button
                                type="submit"
                                className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-xl text-xs font-bold transition"
                            >
                                Apply
                            </button>
                        </form>

                        {/* History Toggle Button */}
                        <button
                            type="button"
                            onClick={() => setShowHistory(!showHistory)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-xl transition"
                        >
                            <History className="w-3.5 h-3.5" />
                            <span>History ({history.length})</span>
                        </button>

                        {/* Primary Generate Button */}
                        <button
                            type="button"
                            onClick={handleGenerate}
                            disabled={isGenerating}
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-purple-500/20 transition disabled:opacity-60"
                        >
                            <RefreshCw className={`w-3.5 h-3.5 ${isGenerating ? 'animate-spin' : ''}`} />
                            <span>{isGenerating ? 'Synthesizing...' : 'Generate New Insights'}</span>
                        </button>
                    </div>
                </div>

                {/* History Drawer if toggled */}
                {showHistory && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-5 border border-purple-100 dark:border-purple-900/40 shadow-sm animate-in fade-in duration-200">
                        <div className="flex items-center justify-between mb-3 border-b border-gray-100 dark:border-gray-700 pb-2">
                            <div className="flex items-center gap-2">
                                <History className="w-4 h-4 text-purple-600" />
                                <h3 className="text-xs font-extrabold uppercase tracking-wider text-gray-900 dark:text-white">
                                    Stored Sales Intelligence Reports Archive
                                </h3>
                            </div>
                            <span className="text-[11px] text-gray-400">All reports are stored with exact creation timestamps</span>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            {history.map((item) => (
                                <Link
                                    key={item.uuid}
                                    href={route('sales-intelligence.show', item.uuid)}
                                    className={`p-3 rounded-2xl border transition flex items-center justify-between text-left group ${
                                        currentInsight?.uuid === item.uuid
                                            ? 'bg-purple-50 dark:bg-purple-950/40 border-purple-300 dark:border-purple-700'
                                            : 'bg-gray-50 dark:bg-gray-900/40 border-gray-100 dark:border-gray-700/60 hover:border-purple-200'
                                    }`}
                                >
                                    <div className="min-w-0 pr-2">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-bold text-gray-900 dark:text-white truncate">
                                                {item.title}
                                            </span>
                                            <span className="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300">
                                                {item.period}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-[10px] text-gray-500 dark:text-gray-400 mt-1">
                                            <Clock className="w-3 h-3" />
                                            <span>{new Date(item.created_at).toLocaleString()}</span>
                                            <span>•</span>
                                            <span>{item.model_used}</span>
                                        </div>
                                    </div>
                                    <ChevronRight className="w-4 h-4 text-gray-400 group-hover:text-purple-600 transition shrink-0" />
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                {/* Stored Report Metadata Banner */}
                {currentInsight && (
                    <div className="bg-gradient-to-r from-purple-900/10 via-indigo-900/10 to-transparent dark:from-purple-950/40 dark:via-indigo-950/30 p-4 rounded-2xl border border-purple-200/60 dark:border-purple-800/40 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <div className="flex items-center gap-2">
                            <span className="font-extrabold text-purple-950 dark:text-purple-200">
                                Active Briefing: {currentInsight.title}
                            </span>
                            <span className="text-gray-400">•</span>
                            <span className="text-gray-600 dark:text-gray-300 flex items-center gap-1">
                                <Clock className="w-3.5 h-3.5 text-purple-600" />
                                Stored on: <b>{new Date(currentInsight.created_at).toLocaleString()}</b>
                            </span>
                        </div>
                        <div className="flex items-center gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                            <span>Synthesized via: <b className="text-gray-800 dark:text-gray-200">{currentInsight.model_used}</b></span>
                            <span>•</span>
                            <span>Duration: <b className="text-gray-800 dark:text-gray-200">{currentInsight.duration_ms} ms</b></span>
                            {currentInsight.generated_by_user && (
                                <>
                                    <span>•</span>
                                    <span>Initiator: <b className="text-gray-800 dark:text-gray-200">{currentInsight.generated_by_user.name}</b></span>
                                </>
                            )}
                        </div>
                    </div>
                )}

                {/* Executive Summary Card */}
                <div className="relative overflow-hidden bg-gradient-to-br from-purple-700 via-indigo-700 to-indigo-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl shadow-indigo-950/20">
                    <div className="absolute top-0 right-0 p-8 opacity-10 pointer-events-none">
                        <Bot className="w-48 h-48" />
                    </div>
                    <div className="relative z-10 max-w-4xl">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-purple-200 text-[11px] font-extrabold uppercase tracking-wider mb-4 border border-white/10">
                            <Sparkles className="w-3.5 h-3.5 text-amber-300" />
                            <span>Executive Summary &amp; Management Digest</span>
                        </div>
                        <p className="text-base sm:text-lg text-purple-50 font-medium leading-relaxed">
                            {currentInsight?.executive_summary || 'Generating executive sales intelligence from current CRM activity...'}
                        </p>
                    </div>
                </div>

                {/* Ground Truth Guarantee Alert */}
                <div className="flex items-center gap-3 px-4 py-3 bg-emerald-50 dark:bg-emerald-950/30 rounded-2xl border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 text-xs">
                    <ShieldCheck className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <p>
                        <b>Verified CRM Ground-Truth:</b> All estate demand volumes, inquiry categorizations, objection frequencies, budgets, and conversion rates originate strictly from live database records and are guaranteed free of metric fabrication.
                    </p>
                </div>

                {/* Core Conversions Top Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* 8. Lead-to-Inspection Conversion */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-3">
                                <div className="flex items-center gap-2">
                                    <div className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
                                        <CalendarCheck className="w-5 h-5" />
                                    </div>
                                    <h2 className="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                                        Lead-to-Inspection Conversion
                                    </h2>
                                </div>
                                <span className="text-2xl font-black text-blue-600 dark:text-blue-400">
                                    {l2i.conversion_rate}%
                                </span>
                            </div>
                            <div className="space-y-1 mb-4">
                                <p className="text-xs text-gray-600 dark:text-gray-300 font-medium">
                                    {insights.lead_to_inspection_conversion}
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3 pt-3 border-t border-gray-100 dark:border-gray-700/60 text-xs">
                            <div className="bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-xl">
                                <span className="text-[10px] uppercase font-bold text-gray-400">Total Leads In Period</span>
                                <div className="text-base font-bold text-gray-900 dark:text-white">{l2i.total_leads}</div>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-xl">
                                <span className="text-[10px] uppercase font-bold text-gray-400">Leads With Inspections</span>
                                <div className="text-base font-bold text-blue-600 dark:text-blue-400">{l2i.leads_with_inspections}</div>
                            </div>
                        </div>
                    </div>

                    {/* 9. Inspection-to-Sale Conversion */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-3">
                                <div className="flex items-center gap-2">
                                    <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                                        <TrendingUp className="w-5 h-5" />
                                    </div>
                                    <h2 className="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                                        Inspection-to-Sale Conversion
                                    </h2>
                                </div>
                                <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                                    {i2s.conversion_rate}%
                                </span>
                            </div>
                            <div className="space-y-1 mb-4">
                                <p className="text-xs text-gray-600 dark:text-gray-300 font-medium">
                                    {insights.inspection_to_sale_conversion}
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-2 pt-3 border-t border-gray-100 dark:border-gray-700/60 text-xs">
                            <div className="bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-xl">
                                <span className="text-[10px] uppercase font-bold text-gray-400">Completed Site Visits</span>
                                <div className="text-sm font-bold text-gray-900 dark:text-white">{i2s.completed_inspections}</div>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-xl">
                                <span className="text-[10px] uppercase font-bold text-gray-400">Deals Won</span>
                                <div className="text-sm font-bold text-emerald-600 dark:text-emerald-400">{i2s.won_deals}</div>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-xl">
                                <span className="text-[10px] uppercase font-bold text-gray-400">Closed Revenue</span>
                                <div className="text-sm font-bold text-emerald-600 dark:text-emerald-400">₦{Number(i2s.won_revenue || 0).toLocaleString()}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* 7 Other Insight Domains in 2-Column Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* 1. Frequently Requested Estates */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm">
                        <div className="flex items-center gap-2 mb-3">
                            <div className="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400">
                                <Building2 className="w-5 h-5" />
                            </div>
                            <h2 className="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                                1. Frequently Requested Estates
                            </h2>
                        </div>
                        <p className="text-xs text-gray-600 dark:text-gray-300 font-medium mb-4">
                            {insights.frequently_requested_estates}
                        </p>
                        <div className="space-y-2">
                            {(metrics.frequently_requested_estates || []).length === 0 ? (
                                <p className="text-xs text-gray-400 italic">No estate-specific inquiry data recorded for this period.</p>
                            ) : (
                                (metrics.frequently_requested_estates || []).map((item, idx) => (
                                    <div key={idx} className="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-900/40 rounded-xl">
                                        <span className="text-xs font-bold text-gray-900 dark:text-white">{item.estate}</span>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-gray-500">{item.count} inquiries</span>
                                            <span className="text-xs font-extrabold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/60 px-2 py-0.5 rounded-md">
                                                {item.percentage}%
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* 2. Common Customer Questions */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm">
                        <div className="flex items-center gap-2 mb-3">
                            <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400">
                                <HelpCircle className="w-5 h-5" />
                            </div>
                            <h2 className="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                                2. Common Customer Questions
                            </h2>
                        </div>
                        <p className="text-xs text-gray-600 dark:text-gray-300 font-medium mb-4">
                            {insights.common_customer_questions}
                        </p>
                        <div className="space-y-2.5">
                            {(metrics.common_customer_questions || []).length === 0 ? (
                                <p className="text-xs text-gray-400 italic">No inbound customer questions analyzed for this period.</p>
                            ) : (
                                (metrics.common_customer_questions || []).map((q, idx) => (
                                    <div key={idx} className="space-y-1">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="font-bold text-gray-800 dark:text-gray-200">{q.category}</span>
                                            <span className="text-gray-500 font-medium">{q.count} ({q.percentage}%)</span>
                                        </div>
                                        <div className="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                                            <div
                                                className="bg-indigo-600 h-2 rounded-full"
                                                style={{ width: `${Math.min(100, q.percentage)}%` }}
                                            />
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* 3. Common Objections */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm">
                        <div className="flex items-center gap-2 mb-3">
                            <div className="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400">
                                <AlertTriangle className="w-5 h-5" />
                            </div>
                            <h2 className="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                                3. Common Buyer Objections
                            </h2>
                        </div>
                        <p className="text-xs text-gray-600 dark:text-gray-300 font-medium mb-4">
                            {insights.common_objections}
                        </p>
                        <div className="space-y-2">
                            {(metrics.common_objections || []).length === 0 ? (
                                <p className="text-xs text-gray-400 italic">No explicit buyer objection records detected in this period.</p>
                            ) : (
                                (metrics.common_objections || []).map((obj, idx) => (
                                    <div key={idx} className="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-900/40 rounded-xl">
                                        <span className="text-xs font-bold text-gray-900 dark:text-white">{obj.objection}</span>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-gray-500">{obj.count} flags</span>
                                            <span className="text-xs font-extrabold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded-md">
                                                {obj.percentage}%
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* 4. Requested Price Ranges */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm">
                        <div className="flex items-center gap-2 mb-3">
                            <div className="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                                <DollarSign className="w-5 h-5" />
                            </div>
                            <h2 className="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                                4. Requested Price Ranges
                            </h2>
                        </div>
                        <p className="text-xs text-gray-600 dark:text-gray-300 font-medium mb-4">
                            {insights.requested_price_ranges}
                        </p>
                        <div className="space-y-2">
                            {(metrics.requested_price_ranges || []).length === 0 ? (
                                <p className="text-xs text-gray-400 italic">No budget range data available for this period.</p>
                            ) : (
                                (metrics.requested_price_ranges || []).map((pr, idx) => (
                                    <div key={idx} className="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-900/40 rounded-xl">
                                        <span className="text-xs font-bold text-gray-900 dark:text-white">{pr.range}</span>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-gray-500">{pr.count} leads</span>
                                            <span className="text-xs font-extrabold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-2 py-0.5 rounded-md">
                                                {pr.percentage}%
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* 5. Requested Payment Plans */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm">
                        <div className="flex items-center gap-2 mb-3">
                            <div className="p-2 rounded-xl bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400">
                                <CreditCard className="w-5 h-5" />
                            </div>
                            <h2 className="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                                5. Requested Payment Plans
                            </h2>
                        </div>
                        <p className="text-xs text-gray-600 dark:text-gray-300 font-medium mb-4">
                            {insights.requested_payment_plans}
                        </p>
                        <div className="space-y-2">
                            {(metrics.requested_payment_plans || []).length === 0 ? (
                                <p className="text-xs text-gray-400 italic">No structured payment plan selections recorded for this period.</p>
                            ) : (
                                (metrics.requested_payment_plans || []).map((pl, idx) => (
                                    <div key={idx} className="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-900/40 rounded-xl">
                                        <span className="text-xs font-bold text-gray-900 dark:text-white">{pl.plan}</span>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-gray-500">{pl.count} selections</span>
                                            <span className="text-xs font-extrabold text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-950/60 px-2 py-0.5 rounded-md">
                                                {pl.percentage}%
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* 6. Handover Reasons */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm">
                        <div className="flex items-center gap-2 mb-3">
                            <div className="p-2 rounded-xl bg-violet-50 dark:bg-violet-950/50 text-violet-600 dark:text-violet-400">
                                <UserCheck className="w-5 h-5" />
                            </div>
                            <h2 className="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                                6. Handover &amp; Escalation Triggers
                            </h2>
                        </div>
                        <p className="text-xs text-gray-600 dark:text-gray-300 font-medium mb-4">
                            {insights.handover_reasons}
                        </p>
                        <div className="space-y-2">
                            {(metrics.handover_reasons || []).length === 0 ? (
                                <p className="text-xs text-gray-400 italic">No human representative handovers recorded in this period.</p>
                            ) : (
                                (metrics.handover_reasons || []).map((ho, idx) => (
                                    <div key={idx} className="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-900/40 rounded-xl">
                                        <span className="text-xs font-bold text-gray-900 dark:text-white">{ho.reason}</span>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-gray-500">{ho.count} handovers</span>
                                            <span className="text-xs font-extrabold text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-950/60 px-2 py-0.5 rounded-md">
                                                {ho.percentage}%
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* 7. Lost Deal Reasons */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm lg:col-span-2">
                        <div className="flex items-center gap-2 mb-3">
                            <div className="p-2 rounded-xl bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400">
                                <XCircle className="w-5 h-5" />
                            </div>
                            <h2 className="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                                7. Lost Deal Root Causes &amp; Revenue Attrition
                            </h2>
                        </div>
                        <p className="text-xs text-gray-600 dark:text-gray-300 font-medium mb-4">
                            {insights.lost_deal_reasons}
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            {(metrics.lost_deal_reasons || []).length === 0 ? (
                                <p className="text-xs text-gray-400 italic col-span-3">No lost deal reasons recorded for this period.</p>
                            ) : (
                                (metrics.lost_deal_reasons || []).map((ld, idx) => (
                                    <div key={idx} className="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                                        <div className="text-xs font-bold text-gray-900 dark:text-white mb-1">
                                            {ld.reason}
                                        </div>
                                        <div className="flex items-center justify-between text-xs text-gray-500">
                                            <span>{ld.count} lost deals</span>
                                            {ld.lost_value > 0 && (
                                                <span className="font-bold text-red-600 dark:text-red-400">
                                                    ₦{Number(ld.lost_value).toLocaleString()}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                {/* Strategic Management Recommendations */}
                <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-700/60 shadow-sm">
                    <div className="flex items-center gap-2 mb-4">
                        <div className="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400">
                            <Sparkles className="w-5 h-5" />
                        </div>
                        <h2 className="text-base font-extrabold text-gray-900 dark:text-white tracking-tight">
                            Strategic Management Recommendations
                        </h2>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {recommendations.map((rec, idx) => (
                            <div key={idx} className="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                                <span className="flex items-center justify-center w-6 h-6 rounded-full bg-purple-600 text-white text-xs font-black shrink-0">
                                    {idx + 1}
                                </span>
                                <p className="text-xs text-gray-700 dark:text-gray-300 font-medium leading-relaxed">
                                    {typeof rec === 'string' ? rec : rec.text || JSON.stringify(rec)}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
