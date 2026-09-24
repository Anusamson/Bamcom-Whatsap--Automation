import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    BarChart3,
    Compass,
    Filter,
    TrendingUp,
    Users,
    CalendarCheck,
    Megaphone,
    Bot,
    UserCheck,
    Download,
    Calendar,
    ArrowUpRight,
    ArrowDownRight,
    CheckCircle2,
    XCircle,
    Clock,
    DollarSign,
    Sparkles,
    Layers,
    ShieldAlert,
    Phone
} from 'lucide-react';
import { useState } from 'react';

export default function Index({
    activeReport = 'lead_source',
    reports = {},
    dateRange = {},
    availableReports = []
}) {
    const [selectedTab, setSelectedTab] = useState(activeReport);
    const [selectedPeriod, setSelectedPeriod] = useState(dateRange.period || '30d');
    const [dateFrom, setDateFrom] = useState(dateRange.date_from || '');
    const [dateTo, setDateTo] = useState(dateRange.date_to || '');

    const handleTabChange = (tabKey) => {
        setSelectedTab(tabKey);
        router.get(
            route('reports.index'),
            { report: tabKey, period: selectedPeriod, date_from: dateFrom, date_to: dateTo },
            { preserveState: true }
        );
    };

    const handlePeriodChange = (period) => {
        setSelectedPeriod(period);
        router.get(
            route('reports.index'),
            { report: selectedTab, period },
            { preserveState: true }
        );
    };

    const handleCustomFilter = (e) => {
        e.preventDefault();
        router.get(
            route('reports.index'),
            { report: selectedTab, period: 'custom', date_from: dateFrom, date_to: dateTo },
            { preserveState: true }
        );
    };

    const getReportIcon = (key) => {
        const map = {
            lead_source: Compass,
            pipeline_funnel: Filter,
            sales_performance: TrendingUp,
            agent_performance: Users,
            inspection_conversion: CalendarCheck,
            campaign_performance: Megaphone,
            ai_conversations: Bot,
            human_handovers: UserCheck,
        };
        const Comp = map[key] || BarChart3;
        return <Comp className="w-4 h-4" />;
    };

    return (
        <AuthenticatedLayout header="Executive CRM Analytics & Reporting">
            <Head title="Reports & Analytics - Bamcom AI CRM" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header & Date Range Bar */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700/60 shadow-sm">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 bg-indigo-100 dark:bg-indigo-950/60 rounded-2xl text-indigo-600 dark:text-indigo-400">
                                <BarChart3 className="w-6 h-6" />
                            </div>
                            <div>
                                <h1 className="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">
                                    Analytics & Performance Reports
                                </h1>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Deep operational reporting queried directly from live database tables • <span className="font-semibold text-indigo-600 dark:text-indigo-400">{dateRange.label}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {/* Period Pills */}
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

                        {/* Custom Date Form */}
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

                        <a
                            href={route('reports.export', { period: selectedPeriod, date_from: dateFrom, date_to: dateTo })}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white text-xs font-bold rounded-xl transition"
                        >
                            <Download className="w-3.5 h-3.5" />
                            <span>Export JSON</span>
                        </a>
                    </div>
                </div>

                {/* 8 Report Tabs Navigation */}
                <div className="flex items-center gap-2 overflow-x-auto pb-1 border-b border-gray-100 dark:border-gray-800">
                    {availableReports.map((rep) => {
                        const active = selectedTab === rep.key;
                        return (
                            <button
                                key={rep.key}
                                type="button"
                                onClick={() => handleTabChange(rep.key)}
                                className={`flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold whitespace-nowrap transition ${
                                    active
                                        ? 'bg-indigo-600 text-white shadow-md'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-100 dark:border-gray-700/60'
                                }`}
                            >
                                {getReportIcon(rep.key)}
                                <span>{rep.label}</span>
                            </button>
                        );
                    })}
                </div>

                {/* Report 1: Lead Source */}
                {selectedTab === 'lead_source' && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Compass className="w-5 h-5 text-indigo-600" />
                                    <span>Lead Source & Acquisition Channels</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Distribution of incoming prospects and closed revenue attribution
                                </p>
                            </div>
                            <span className="text-xs font-extrabold text-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 px-3 py-1 rounded-xl">
                                Total Leads: {reports.lead_source?.total_leads ?? 0}
                            </span>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {reports.lead_source?.sources?.map((src) => (
                                <div key={src.source} className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="font-bold text-sm text-gray-900 dark:text-white">{src.label}</span>
                                        <div className="text-right">
                                            <span className="font-black text-indigo-600 dark:text-indigo-400">{src.count} leads</span>
                                            <span className="text-xs text-gray-400 ml-1.5 font-semibold">({src.percentage}%)</span>
                                        </div>
                                    </div>
                                    <div className="w-full bg-gray-200 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                                        <div className="bg-indigo-600 h-full rounded-full transition-all" style={{ width: `${src.percentage}%` }} />
                                    </div>
                                    <div className="flex justify-between text-xs pt-1 text-gray-500">
                                        <span>Won Revenue Attributed:</span>
                                        <span className="font-extrabold text-emerald-600">{src.formatted_won_revenue}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Report 2: Pipeline Funnel */}
                {selectedTab === 'pipeline_funnel' && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Filter className="w-5 h-5 text-indigo-600" />
                                    <span>Pipeline Funnel Velocity & Drop-Offs</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Stage-by-stage progression from initial lead inquiry to closed won
                                </p>
                            </div>
                        </div>

                        <div className="space-y-4">
                            {reports.pipeline_funnel?.stages?.map((st) => (
                                <div key={st.stage_id} className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800 space-y-2">
                                    <div className="flex items-center justify-between text-xs sm:text-sm">
                                        <div className="flex items-center gap-2.5">
                                            <span className="w-6 h-6 rounded-full bg-indigo-600 text-white font-black text-xs flex items-center justify-center">
                                                {st.order}
                                            </span>
                                            <span className="font-bold text-gray-900 dark:text-white">{st.name}</span>
                                            <span className="text-xs text-gray-400">({st.deals_count} deals • {st.leads_count} leads)</span>
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <span className="font-extrabold text-gray-900 dark:text-white">{st.formatted_value}</span>
                                            <span className="font-black text-indigo-600 dark:text-indigo-400 w-16 text-right">{st.conversion_rate}%</span>
                                        </div>
                                    </div>
                                    <div className="w-full bg-gray-200 dark:bg-gray-700 h-3 rounded-full overflow-hidden">
                                        <div className="bg-indigo-600 h-full rounded-full transition-all" style={{ width: `${Math.max(5, st.conversion_rate)}%` }} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Report 3: Sales Performance */}
                {selectedTab === 'sales_performance' && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <TrendingUp className="w-5 h-5 text-indigo-600" />
                                    <span>Sales Performance & Deal Velocity</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Closed revenue, win rates, and daily transaction throughput
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div className="p-4 bg-emerald-50 dark:bg-emerald-950/40 rounded-2xl border border-emerald-200 dark:border-emerald-800/40">
                                <div className="text-xs text-emerald-800 dark:text-emerald-300 font-semibold">Won Revenue</div>
                                <div className="text-xl sm:text-2xl font-black text-emerald-600 mt-1">{reports.sales_performance?.formatted_won_revenue ?? '₦0.00'}</div>
                                <div className="text-[11px] text-emerald-700 mt-0.5">{reports.sales_performance?.won_deals ?? 0} closed deals won</div>
                            </div>

                            <div className="p-4 bg-indigo-50 dark:bg-indigo-950/40 rounded-2xl border border-indigo-200 dark:border-indigo-800/40">
                                <div className="text-xs text-indigo-800 dark:text-indigo-300 font-semibold">Win Rate</div>
                                <div className="text-xl sm:text-2xl font-black text-indigo-600 mt-1">{reports.sales_performance?.win_rate ?? 0}%</div>
                                <div className="text-[11px] text-indigo-700 mt-0.5">Won vs total deals</div>
                            </div>

                            <div className="p-4 bg-purple-50 dark:bg-purple-950/40 rounded-2xl border border-purple-200 dark:border-purple-800/40">
                                <div className="text-xs text-purple-800 dark:text-purple-300 font-semibold">Average Deal Size</div>
                                <div className="text-xl sm:text-2xl font-black text-purple-600 mt-1">{reports.sales_performance?.formatted_avg_deal_size ?? '₦0.00'}</div>
                                <div className="text-[11px] text-purple-700 mt-0.5">Revenue per won contract</div>
                            </div>

                            <div className="p-4 bg-rose-50 dark:bg-rose-950/40 rounded-2xl border border-rose-200 dark:border-rose-800/40">
                                <div className="text-xs text-rose-800 dark:text-rose-300 font-semibold">Lost Revenue</div>
                                <div className="text-xl sm:text-2xl font-black text-rose-600 mt-1">{reports.sales_performance?.formatted_lost_revenue ?? '₦0.00'}</div>
                                <div className="text-[11px] text-rose-700 mt-0.5">{reports.sales_performance?.lost_deals ?? 0} lost opportunities</div>
                            </div>
                        </div>

                        {/* Daily Trend List */}
                        <div className="space-y-3 pt-4">
                            <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider">Daily Throughput History</h3>
                            <div className="overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-gray-50 dark:bg-gray-900 text-gray-500 font-semibold border-b border-gray-100 dark:border-gray-800">
                                        <tr>
                                            <th className="py-2.5 px-4">Date</th>
                                            <th className="py-2.5 px-4">Deals Recorded</th>
                                            <th className="py-2.5 px-4">Deal Volume</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {reports.sales_performance?.trends?.map((tr) => (
                                            <tr key={tr.date}>
                                                <td className="py-2.5 px-4 font-mono">{tr.date}</td>
                                                <td className="py-2.5 px-4 font-semibold">{tr.count} deals</td>
                                                <td className="py-2.5 px-4 font-bold text-emerald-600">₦{Number(tr.volume).toLocaleString()}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* Report 4: Agent Performance */}
                {selectedTab === 'agent_performance' && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Users className="w-5 h-5 text-indigo-600" />
                                    <span>Sales Agent Quotas & Leaderboard</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Assigned prospects, site inspections conducted, and closed sales volume
                                </p>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs sm:text-sm">
                                <thead className="bg-gray-50 dark:bg-gray-900/50 text-gray-500 font-semibold border-b border-gray-100 dark:border-gray-700">
                                    <tr>
                                        <th className="py-3 px-6">Sales Representative</th>
                                        <th className="py-3 px-6">Assigned Leads</th>
                                        <th className="py-3 px-6">Completed Inspections</th>
                                        <th className="py-3 px-6">Open Deals</th>
                                        <th className="py-3 px-6">Won Deals</th>
                                        <th className="py-3 px-6">Won Revenue</th>
                                        <th className="py-3 px-6 text-right">Win Rate</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    {reports.agent_performance?.agents?.map((agent) => (
                                        <tr key={agent.agent_id} className="hover:bg-gray-50/50 dark:hover:bg-gray-750">
                                            <td className="py-3 px-6">
                                                <div className="font-bold text-gray-900 dark:text-white">{agent.name}</div>
                                                <div className="text-[11px] text-gray-400">{agent.email}</div>
                                            </td>
                                            <td className="py-3 px-6 font-semibold">{agent.assigned_leads}</td>
                                            <td className="py-3 px-6 font-semibold">{agent.completed_inspections}</td>
                                            <td className="py-3 px-6 font-semibold">{agent.open_deals}</td>
                                            <td className="py-3 px-6 font-bold text-emerald-600">{agent.won_deals}</td>
                                            <td className="py-3 px-6 font-black text-emerald-600">{agent.formatted_won_revenue}</td>
                                            <td className="py-3 px-6 text-right font-extrabold text-indigo-600">{agent.win_rate}%</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Report 5: Inspection Conversion */}
                {selectedTab === 'inspection_conversion' && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <CalendarCheck className="w-5 h-5 text-indigo-600" />
                                    <span>Site Inspection Conversion & Outcomes</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Physical estate walkthroughs, outcome completion rate, and closed contracts
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
                            <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-800">
                                <div className="text-xs text-gray-400">Total Bookings</div>
                                <div className="text-xl font-black text-gray-900 dark:text-white mt-1">{reports.inspection_conversion?.total ?? 0}</div>
                            </div>

                            <div className="p-4 bg-emerald-50 dark:bg-emerald-950/40 rounded-2xl border border-emerald-200 dark:border-emerald-800/40">
                                <div className="text-xs text-emerald-800 dark:text-emerald-300">Completed Visits</div>
                                <div className="text-xl font-black text-emerald-600 mt-1">{reports.inspection_conversion?.completed ?? 0}</div>
                            </div>

                            <div className="p-4 bg-indigo-50 dark:bg-indigo-950/40 rounded-2xl border border-indigo-200 dark:border-indigo-800/40">
                                <div className="text-xs text-indigo-800 dark:text-indigo-300">Scheduled / Confirmed</div>
                                <div className="text-xl font-black text-indigo-600 mt-1">{(reports.inspection_conversion?.scheduled ?? 0) + (reports.inspection_conversion?.confirmed ?? 0)}</div>
                            </div>

                            <div className="p-4 bg-amber-50 dark:bg-amber-950/40 rounded-2xl border border-amber-200 dark:border-amber-800/40">
                                <div className="text-xs text-amber-800 dark:text-amber-300">Requested</div>
                                <div className="text-xl font-black text-amber-600 mt-1">{reports.inspection_conversion?.requested ?? 0}</div>
                            </div>

                            <div className="p-4 bg-rose-50 dark:bg-rose-950/40 rounded-2xl border border-rose-200 dark:border-rose-800/40">
                                <div className="text-xs text-rose-800 dark:text-rose-300">Cancelled / No-Show</div>
                                <div className="text-xl font-black text-rose-600 mt-1">{(reports.inspection_conversion?.cancelled ?? 0) + (reports.inspection_conversion?.no_show ?? 0)}</div>
                            </div>

                            <div className="p-4 bg-purple-50 dark:bg-purple-950/40 rounded-2xl border border-purple-200 dark:border-purple-800/40">
                                <div className="text-xs text-purple-800 dark:text-purple-300">Deal Conversion</div>
                                <div className="text-xl font-black text-purple-600 mt-1">{reports.inspection_conversion?.inspection_to_deal_rate ?? 0}%</div>
                            </div>
                        </div>

                        <div className="p-4 bg-indigo-50 dark:bg-indigo-950/30 rounded-2xl border border-indigo-200 dark:border-indigo-800/40 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <CheckCircle2 className="w-5 h-5 text-indigo-600" />
                                <div>
                                    <div className="text-xs font-bold text-indigo-900 dark:text-indigo-200">
                                        Site Visit to Closed Deal Conversion
                                    </div>
                                    <div className="text-[11px] text-indigo-700 dark:text-indigo-300">
                                        {reports.inspection_conversion?.won_deals_from_inspection ?? 0} deals closed won from clients who completed physical site inspections
                                    </div>
                                </div>
                            </div>
                            <span className="text-2xl font-black text-indigo-600">
                                {reports.inspection_conversion?.inspection_to_deal_rate ?? 0}%
                            </span>
                        </div>
                    </div>
                )}

                {/* Report 6: Campaign Performance */}
                {selectedTab === 'campaign_performance' && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Megaphone className="w-5 h-5 text-indigo-600" />
                                    <span>WhatsApp Broadcast Campaign Performance</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Queued batch delivery metrics, read rates, and opt-out rates
                                </p>
                            </div>
                            <Link href={route('campaigns.index')} className="text-xs font-bold text-indigo-600 hover:text-indigo-700">
                                All Campaigns →
                            </Link>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div className="p-4 bg-indigo-50 dark:bg-indigo-950/40 rounded-2xl border border-indigo-200 dark:border-indigo-800/40">
                                <div className="text-xs text-indigo-700">Total Sent</div>
                                <div className="text-2xl font-black text-indigo-600 mt-1">{reports.campaign_performance?.total_sent ?? 0}</div>
                                <div className="text-[11px] text-gray-400">{reports.campaign_performance?.total_campaigns ?? 0} campaigns executed</div>
                            </div>

                            <div className="p-4 bg-teal-50 dark:bg-teal-950/40 rounded-2xl border border-teal-200 dark:border-teal-800/40">
                                <div className="text-xs text-teal-700">Delivery Rate</div>
                                <div className="text-2xl font-black text-teal-600 mt-1">{reports.campaign_performance?.delivery_rate ?? 0}%</div>
                                <div className="text-[11px] text-gray-400">{reports.campaign_performance?.total_delivered ?? 0} delivered</div>
                            </div>

                            <div className="p-4 bg-emerald-50 dark:bg-emerald-950/40 rounded-2xl border border-emerald-200 dark:border-emerald-800/40">
                                <div className="text-xs text-emerald-700">Read / Open Rate</div>
                                <div className="text-2xl font-black text-emerald-600 mt-1">{reports.campaign_performance?.read_rate ?? 0}%</div>
                                <div className="text-[11px] text-gray-400">{reports.campaign_performance?.total_read ?? 0} read receipts</div>
                            </div>

                            <div className="p-4 bg-rose-50 dark:bg-rose-950/40 rounded-2xl border border-rose-200 dark:border-rose-800/40">
                                <div className="text-xs text-rose-700">Opt-Out Rate</div>
                                <div className="text-2xl font-black text-rose-600 mt-1">{reports.campaign_performance?.opt_out_rate ?? 0}%</div>
                                <div className="text-[11px] text-gray-400">{reports.campaign_performance?.opted_out_count ?? 0} contacts opted out</div>
                            </div>
                        </div>

                        {/* Campaigns Table */}
                        <div className="overflow-x-auto pt-2">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-gray-50 dark:bg-gray-900 text-gray-500 font-semibold border-b border-gray-100 dark:border-gray-800">
                                    <tr>
                                        <th className="py-2.5 px-4">Campaign Name</th>
                                        <th className="py-2.5 px-4">Status</th>
                                        <th className="py-2.5 px-4">Recipients</th>
                                        <th className="py-2.5 px-4">Sent</th>
                                        <th className="py-2.5 px-4">Delivered</th>
                                        <th className="py-2.5 px-4">Read</th>
                                        <th className="py-2.5 px-4">Failed</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {reports.campaign_performance?.campaigns?.map((c) => (
                                        <tr key={c.id}>
                                            <td className="py-2.5 px-4 font-bold text-gray-900 dark:text-white">{c.name}</td>
                                            <td className="py-2.5 px-4 capitalize">{c.status}</td>
                                            <td className="py-2.5 px-4">{c.total_recipients}</td>
                                            <td className="py-2.5 px-4 text-indigo-600 font-semibold">{c.sent_count}</td>
                                            <td className="py-2.5 px-4 text-teal-600 font-semibold">{c.delivered_count}</td>
                                            <td className="py-2.5 px-4 text-emerald-600 font-semibold">{c.read_count}</td>
                                            <td className="py-2.5 px-4 text-rose-600">{c.failed_count}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Report 7: AI Conversations */}
                {selectedTab === 'ai_conversations' && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Bot className="w-5 h-5 text-indigo-600" />
                                    <span>AI Conversations & Operational Autonomy</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Automated WhatsApp AI handling, token efficiency, and independent resolution rate
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div className="p-4 bg-purple-50 dark:bg-purple-950/40 rounded-2xl border border-purple-200 dark:border-purple-800/40">
                                <div className="text-xs text-purple-700">AI Handled Chats</div>
                                <div className="text-2xl font-black text-purple-600 mt-1">{reports.ai_conversations?.ai_conversations ?? 0}</div>
                                <div className="text-[11px] text-gray-400">of {reports.ai_conversations?.total_conversations ?? 0} total chats</div>
                            </div>

                            <div className="p-4 bg-indigo-50 dark:bg-indigo-950/40 rounded-2xl border border-indigo-200 dark:border-indigo-800/40">
                                <div className="text-xs text-indigo-700">AI Resolution Rate</div>
                                <div className="text-2xl font-black text-indigo-600 mt-1">{reports.ai_conversations?.ai_resolution_rate ?? 0}%</div>
                                <div className="text-[11px] text-gray-400">Resolved without human escalation</div>
                            </div>

                            <div className="p-4 bg-teal-50 dark:bg-teal-950/40 rounded-2xl border border-teal-200 dark:border-teal-800/40">
                                <div className="text-xs text-teal-700">AI Executions</div>
                                <div className="text-2xl font-black text-teal-600 mt-1">{reports.ai_conversations?.total_ai_executions ?? 0}</div>
                                <div className="text-[11px] text-gray-400">Tool calls & responses</div>
                            </div>

                            <div className="p-4 bg-amber-50 dark:bg-amber-950/40 rounded-2xl border border-amber-200 dark:border-amber-800/40">
                                <div className="text-xs text-amber-700">Avg Latency</div>
                                <div className="text-2xl font-black text-amber-600 mt-1">{reports.ai_conversations?.avg_duration_ms ?? 0}ms</div>
                                <div className="text-[11px] text-gray-400">{Number(reports.ai_conversations?.total_tokens ?? 0).toLocaleString()} tokens used</div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Report 8: Human Handovers */}
                {selectedTab === 'human_handovers' && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/60 p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <UserCheck className="w-5 h-5 text-indigo-600" />
                                    <span>Human Handovers & Escalation Analytics</span>
                                </h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Analysis of triggers causing AI-to-human representative transfers
                                </p>
                            </div>
                            <span className="text-xs font-black text-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 px-3 py-1 rounded-xl">
                                Total Handovers: {reports.human_handovers?.total_handovers ?? 0}
                            </span>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Triggers Breakdown */}
                            <div className="space-y-3">
                                <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider">Escalation Trigger Reasons</h3>
                                <div className="space-y-2">
                                    {reports.human_handovers?.triggers?.map((tr) => (
                                        <div key={tr.trigger} className="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs">
                                            <span className="font-bold text-gray-900 dark:text-white">{tr.trigger}</span>
                                            <div className="flex items-center gap-2">
                                                <span className="font-black text-indigo-600">{tr.count} times</span>
                                                <span className="text-[11px] text-gray-400">({tr.percentage}%)</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Top Representatives Receiving Handovers */}
                            <div className="space-y-3">
                                <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider">Top Assigned Representatives</h3>
                                <div className="space-y-2">
                                    {reports.human_handovers?.top_representatives?.map((rep) => (
                                        <div key={rep.representative} className="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs">
                                            <span className="font-bold text-gray-900 dark:text-white">{rep.representative}</span>
                                            <span className="font-black text-emerald-600">{rep.count} assigned</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
