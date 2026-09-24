import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    TrendingUp,
    Users,
    Flame,
    MessageSquare,
    CalendarCheck,
    Handshake,
    DollarSign,
    Percent,
    ArrowUpRight,
    ArrowDownRight,
    BarChart3,
    Compass,
    Clock,
    Activity,
    Calendar,
    Filter,
    ChevronRight,
    Eye,
    Layers,
    Sparkles,
    CheckCircle2
} from 'lucide-react';
import { useState } from 'react';

export default function Dashboard({
    kpis = {},
    pipelineFunnel = { stages: [] },
    leadSources = { sources: [] },
    salesPerformance = {},
    recentActivities = [],
    dateRange = {}
}) {
    const [selectedPeriod, setSelectedPeriod] = useState(dateRange.period || '30d');
    const [dateFrom, setDateFrom] = useState(dateRange.date_from || '');
    const [dateTo, setDateTo] = useState(dateRange.date_to || '');

    const handlePeriodChange = (period) => {
        setSelectedPeriod(period);
        router.get(route('dashboard'), { period }, { preserveState: true });
    };

    const handleCustomFilter = (e) => {
        e.preventDefault();
        router.get(
            route('dashboard'),
            { period: 'custom', date_from: dateFrom, date_to: dateTo },
            { preserveState: true }
        );
    };

    const renderChangeBadge = (changePercent) => {
        if (changePercent === undefined || changePercent === null) return null;
        const isPositive = changePercent >= 0;
        return (
            <span
                className={`inline-flex items-center text-[11px] font-bold px-1.5 py-0.5 rounded-md ${
                    isPositive
                        ? 'text-emerald-700 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-950/60'
                        : 'text-rose-700 bg-rose-50 dark:text-rose-400 dark:bg-rose-950/60'
                }`}
            >
                {isPositive ? <ArrowUpRight className="w-3 h-3 mr-0.5" /> : <ArrowDownRight className="w-3 h-3 mr-0.5" />}
                {isPositive ? `+${changePercent}%` : `${changePercent}%`}
            </span>
        );
    };

    return (
        <AuthenticatedLayout header="Executive CRM Dashboard">
            <Head title="Executive Dashboard - Bamcom AI CRM" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header & Date Range Controls */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700/60 shadow-sm">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 bg-indigo-100 dark:bg-indigo-950/60 rounded-2xl text-indigo-600 dark:text-indigo-400">
                                <BarChart3 className="w-6 h-6" />
                            </div>
                            <div>
                                <h1 className="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">
                                    CRM Executive KPIs
                                </h1>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Live metrics queried directly from database records • <span className="font-semibold text-indigo-600 dark:text-indigo-400">{dateRange.label}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Date Filtering Bar */}
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-1 bg-gray-100 dark:bg-gray-900 p-1 rounded-2xl border border-gray-200/60 dark:border-gray-800">
                            {[
                                { key: 'today', label: 'Today' },
                                { key: '7d', label: '7D' },
                                { key: '30d', label: '30D' },
                                { key: '90d', label: '90D' },
                                { key: 'this_month', label: 'This Month' },
                                { key: 'this_year', label: 'Year' },
                            ].map((p) => (
                                <button
                                    key={p.key}
                                    type="button"
                                    onClick={() => handlePeriodChange(p.key)}
                                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition ${
                                        selectedPeriod === p.key
                                            ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-white shadow-sm'
                                            : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'
                                    }`}
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>

                        {/* Custom Date Filter Inputs */}
                        <form onSubmit={handleCustomFilter} className="flex items-center gap-1.5">
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
                                className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm transition"
                            >
                                Apply
                            </button>
                        </form>

                        <Link
                            href={route('reports.index')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white text-xs font-bold rounded-xl transition"
                        >
                            <span>Full Reports</span>
                            <ArrowUpRight className="w-3.5 h-3.5" />
                        </Link>
                    </div>
                </div>

                {/* Dashboard KPIs Grid (8 Database Metrics) */}
                <div className="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    {/* 1. New Leads */}
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-gray-400">New Leads</span>
                            <div className="p-2 bg-blue-50 dark:bg-blue-950/60 text-blue-600 rounded-xl">
                                <Users className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                                {kpis.new_leads?.value ?? 0}
                            </span>
                            {renderChangeBadge(kpis.new_leads?.change_percent)}
                        </div>
                        <p className="text-[11px] text-gray-400">
                            vs {kpis.new_leads?.previous ?? 0} previous period
                        </p>
                    </div>

                    {/* 2. Hot Leads */}
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-gray-400">Hot Leads</span>
                            <div className="p-2 bg-rose-50 dark:bg-rose-950/60 text-rose-600 rounded-xl">
                                <Flame className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl sm:text-3xl font-black text-rose-600 dark:text-rose-400">
                                {kpis.hot_leads?.value ?? 0}
                            </span>
                            {renderChangeBadge(kpis.hot_leads?.change_percent)}
                        </div>
                        <p className="text-[11px] text-gray-400">
                            High intent temperature prospects
                        </p>
                    </div>

                    {/* 3. Active Conversations */}
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-gray-400">Active Chats</span>
                            <div className="p-2 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 rounded-xl">
                                <MessageSquare className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                                {kpis.active_conversations?.value ?? 0}
                            </span>
                            {renderChangeBadge(kpis.active_conversations?.change_percent)}
                        </div>
                        <p className="text-[11px] text-gray-400">
                            Open or pending in period
                        </p>
                    </div>

                    {/* 4. Inspections */}
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-gray-400">Inspections</span>
                            <div className="p-2 bg-amber-50 dark:bg-amber-950/60 text-amber-600 rounded-xl">
                                <CalendarCheck className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                                {kpis.inspections?.value ?? 0}
                            </span>
                            {renderChangeBadge(kpis.inspections?.change_percent)}
                        </div>
                        <p className="text-[11px] text-gray-400">
                            Site inspection bookings
                        </p>
                    </div>

                    {/* 5. Open Deals */}
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-gray-400">Open Deals</span>
                            <div className="p-2 bg-purple-50 dark:bg-purple-950/60 text-purple-600 rounded-xl">
                                <Handshake className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                                {kpis.open_deals?.value ?? 0}
                            </span>
                            {renderChangeBadge(kpis.open_deals?.change_percent)}
                        </div>
                        <p className="text-[11px] text-gray-400">
                            Active pipeline negotiations
                        </p>
                    </div>

                    {/* 6. Pipeline Value */}
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-gray-400">Pipeline Value</span>
                            <div className="p-2 bg-teal-50 dark:bg-teal-950/60 text-teal-600 rounded-xl">
                                <DollarSign className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline justify-between">
                            <span className="text-xl sm:text-2xl font-black text-teal-600 dark:text-teal-400 truncate">
                                {kpis.pipeline_value?.formatted ?? '₦0.00'}
                            </span>
                            {renderChangeBadge(kpis.pipeline_value?.change_percent)}
                        </div>
                        <p className="text-[11px] text-gray-400">
                            Total value of unclosed deals
                        </p>
                    </div>

                    {/* 7. Sales Won */}
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-gray-400">Sales Won</span>
                            <div className="p-2 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 rounded-xl">
                                <CheckCircle2 className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline justify-between">
                            <span className="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 truncate">
                                {kpis.sales_won?.formatted ?? '₦0.00'}
                            </span>
                            {renderChangeBadge(kpis.sales_won?.change_percent)}
                        </div>
                        <p className="text-[11px] text-gray-400">
                            {kpis.sales_won?.count ?? 0} closed deals won
                        </p>
                    </div>

                    {/* 8. Conversion Rate */}
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-gray-400">Conversion Rate</span>
                            <div className="p-2 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 rounded-xl">
                                <Percent className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl sm:text-3xl font-black text-indigo-600 dark:text-indigo-400">
                                {kpis.conversion_rate?.formatted ?? '0.0%'}
                            </span>
                            <span className="text-[10px] font-semibold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded">
                                Real Data
                            </span>
                        </div>
                        <p className="text-[11px] text-gray-400">
                            Won deals vs pipeline throughput
                        </p>
                    </div>
                </div>

                {/* Main Middle Row: Pipeline Funnel + Lead Sources */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Pipeline Funnel Visualizer (2 Cols) */}
                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-5">
                        <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Filter className="w-5 h-5 text-indigo-600" />
                                    <span>Pipeline Funnel Velocity</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Stage-by-stage progression, deal values, and conversion throughput
                                </p>
                            </div>
                            <Link
                                href={route('pipelines.index')}
                                className="text-xs font-bold text-indigo-600 hover:text-indigo-700"
                            >
                                Open Kanban →
                            </Link>
                        </div>

                        {/* Funnel Stage Bars */}
                        <div className="space-y-3.5">
                            {pipelineFunnel.stages && pipelineFunnel.stages.length > 0 ? (
                                pipelineFunnel.stages.map((st) => (
                                    <div key={st.stage_id} className="space-y-1.5">
                                        <div className="flex items-center justify-between text-xs">
                                            <div className="flex items-center gap-2">
                                                <span className="w-2 h-2 rounded-full bg-indigo-500" />
                                                <span className="font-bold text-gray-800 dark:text-gray-200">
                                                    {st.name}
                                                </span>
                                                <span className="text-gray-400 font-mono text-[11px]">
                                                    ({st.deals_count} deals / {st.leads_count} leads)
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className="font-semibold text-gray-700 dark:text-gray-300">
                                                    {st.formatted_value}
                                                </span>
                                                <span className="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 w-12 text-right">
                                                    {st.conversion_rate}%
                                                </span>
                                            </div>
                                        </div>

                                        <div className="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                                            <div
                                                className="bg-indigo-600 dark:bg-indigo-500 h-full rounded-full transition-all duration-500"
                                                style={{ width: `${Math.max(5, st.conversion_rate)}%` }}
                                            />
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center py-8 text-gray-400 text-xs">
                                    No pipeline stages recorded yet.
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Lead Acquisition Sources (1 Col) */}
                    <div className="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-5">
                        <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Compass className="w-5 h-5 text-emerald-600" />
                                    <span>Top Lead Sources</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Acquisition channels & revenue
                                </p>
                            </div>
                            <Link
                                href={route('reports.index', { report: 'lead_source' })}
                                className="text-xs font-bold text-emerald-600 hover:text-emerald-700"
                            >
                                Details →
                            </Link>
                        </div>

                        <div className="space-y-4">
                            {leadSources.sources && leadSources.sources.length > 0 ? (
                                leadSources.sources.slice(0, 5).map((src) => (
                                    <div key={src.source} className="space-y-1">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="font-bold text-gray-800 dark:text-gray-200">
                                                {src.label}
                                            </span>
                                            <div className="text-right">
                                                <span className="font-bold text-gray-900 dark:text-white">
                                                    {src.count} leads
                                                </span>
                                                <span className="text-[11px] text-gray-400 ml-1.5">
                                                    ({src.percentage}%)
                                                </span>
                                            </div>
                                        </div>

                                        <div className="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                                            <div
                                                className="bg-emerald-500 h-full rounded-full transition-all duration-500"
                                                style={{ width: `${src.percentage}%` }}
                                            />
                                        </div>

                                        <div className="flex justify-between text-[11px] text-gray-400 pt-0.5">
                                            <span>Won Revenue:</span>
                                            <span className="font-semibold text-emerald-600">
                                                {src.formatted_won_revenue}
                                            </span>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center py-8 text-gray-400 text-xs">
                                    No lead source data available in selected period.
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Bottom Row: Recent Activities Audit Trail + Sales Summary */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Sales Summary Card */}
                    <div className="bg-gradient-to-br from-indigo-900 via-indigo-800 to-slate-900 rounded-3xl p-6 text-white shadow-xl space-y-4 flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between pb-3 border-b border-indigo-700/60">
                                <span className="text-xs font-bold uppercase tracking-wider text-indigo-300">
                                    Sales Performance Summary
                                </span>
                                <Sparkles className="w-4 h-4 text-amber-400" />
                            </div>

                            <div className="mt-4 space-y-3">
                                <div>
                                    <div className="text-xs text-indigo-200">Closed Won Revenue</div>
                                    <div className="text-2xl sm:text-3xl font-black text-white mt-0.5">
                                        {salesPerformance.formatted_won_revenue ?? '₦0.00'}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-2 pt-2 border-t border-indigo-700/60 text-xs">
                                    <div>
                                        <span className="text-indigo-300">Win Rate:</span>
                                        <div className="text-base font-bold text-white mt-0.5">
                                            {salesPerformance.win_rate ?? 0}%
                                        </div>
                                    </div>
                                    <div>
                                        <span className="text-indigo-300">Avg Deal Size:</span>
                                        <div className="text-base font-bold text-white mt-0.5 truncate">
                                            {salesPerformance.formatted_avg_deal_size ?? '₦0.00'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Link
                            href={route('reports.index', { report: 'sales_performance' })}
                            className="w-full inline-flex items-center justify-center gap-2 py-2.5 bg-white text-indigo-900 hover:bg-indigo-50 font-bold text-xs rounded-xl shadow transition"
                        >
                            <span>View Full Sales Analytics</span>
                            <ArrowUpRight className="w-4 h-4" />
                        </Link>
                    </div>

                    {/* Recent CRM Activity Feed (2 Cols) */}
                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-4">
                        <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Activity className="w-5 h-5 text-indigo-600" />
                                    <span>Real-Time CRM Activity Feed</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Audit trail of status transitions, AI interactions, and site visits
                                </p>
                            </div>
                        </div>

                        <div className="divide-y divide-gray-100 dark:divide-gray-700/60">
                            {recentActivities.length > 0 ? (
                                recentActivities.map((act) => (
                                    <div key={act.id} className="py-2.5 flex items-start gap-3 text-xs">
                                        <div className="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 flex items-center justify-center font-bold text-[11px] flex-shrink-0 mt-0.5">
                                            {act.contact?.first_name ? act.contact.first_name.charAt(0) : 'C'}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between">
                                                <span className="font-bold text-gray-900 dark:text-white truncate">
                                                    {act.contact?.first_name ? `${act.contact.first_name} ${act.contact.last_name || ''}`.trim() : 'Contact'}
                                                </span>
                                                <span className="text-[11px] text-gray-400">
                                                    {new Date(act.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </span>
                                            </div>
                                            <p className="text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                                {act.description || act.activity_type}
                                            </p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center py-8 text-gray-400 text-xs">
                                    No activities logged yet.
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
