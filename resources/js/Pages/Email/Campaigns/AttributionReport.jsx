import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    TrendingUp, 
    ArrowLeft, 
    DollarSign, 
    Target, 
    Compass, 
    Briefcase, 
    Award, 
    Calendar, 
    Filter,
    BarChart3,
    ArrowUpRight
} from 'lucide-react';

export default function AttributionReport({ auth, report, filters }) {
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('email-campaigns.reports.attribution'), {
            start_date: startDate,
            end_date: endDate,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const summary = report.summary || {};
    const attribution = summary.attribution || {};
    const rates = summary.rates || {};
    const topRevenue = report.top_campaigns_by_revenue || [];
    const topLeads = report.top_campaigns_by_leads || [];
    const campaigns = report.campaigns || [];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Link
                                href={route('email-campaigns.index')}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                            <span className="text-xs text-slate-500 font-medium">Email Campaigns / Executive Reporting</span>
                        </div>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <TrendingUp className="h-6 w-6 text-emerald-600" />
                            Executive Sales Attribution & ROI Report
                        </h2>
                    </div>

                    {/* Date Range Filter Form */}
                    <form onSubmit={handleFilter} className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-1.5 bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm text-xs">
                            <Calendar className="h-3.5 w-3.5 text-slate-400" />
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                className="border-none p-0 text-xs text-slate-700 dark:text-slate-200 bg-transparent focus:ring-0"
                                placeholder="Start Date"
                            />
                            <span className="text-slate-400">to</span>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                className="border-none p-0 text-xs text-slate-700 dark:text-slate-200 bg-transparent focus:ring-0"
                                placeholder="End Date"
                            />
                        </div>

                        <button
                            type="submit"
                            className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                        >
                            <Filter className="h-3.5 w-3.5" />
                            Filter
                        </button>
                    </form>
                </div>
            }
        >
            <Head title="Executive Sales Attribution Report - Bamcom AI CRM" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                {/* 1. Macro KPIs */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div className="bg-gradient-to-br from-emerald-600 to-teal-700 text-white p-5 rounded-2xl shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-emerald-100 text-xs font-medium">
                            <span>Total Attributed Revenue</span>
                            <DollarSign className="h-4 w-4 text-emerald-200" />
                        </div>
                        <div className="text-2xl font-black tracking-tight">
                            {attribution.formatted_attributed_revenue || '₦0.00'}
                        </div>
                        <div className="text-xs text-emerald-100 pt-1 border-t border-emerald-500/40">
                            {attribution.campaign_generated_sales || 0} Closed Won Deals
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-slate-500 text-xs font-medium">
                            <span>Generated Leads</span>
                            <Target className="h-4 w-4 text-blue-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {attribution.campaign_generated_leads || 0}
                        </div>
                        <div className="text-xs text-slate-500">Across {summary.total_campaigns || 0} Campaigns</div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-slate-500 text-xs font-medium">
                            <span>Site Inspections</span>
                            <Compass className="h-4 w-4 text-indigo-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {attribution.campaign_generated_inspections || 0}
                        </div>
                        <div className="text-xs text-slate-500">Scheduled Tours</div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-slate-500 text-xs font-medium">
                            <span>Pipeline Deals</span>
                            <Briefcase className="h-4 w-4 text-amber-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {attribution.campaign_generated_opportunities || 0}
                        </div>
                        <div className="text-xs text-slate-500">Active & Won Deals</div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-slate-500 text-xs font-medium">
                            <span>Total Emails Delivered</span>
                            <Award className="h-4 w-4 text-purple-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {(summary.delivered || 0).toLocaleString()}
                        </div>
                        <div className="text-xs text-slate-500">Delivery Rate: {rates.delivery_rate || 0}%</div>
                    </div>
                </div>

                {/* 2. Top Performing Campaigns Leaderboard */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Top by Revenue */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <DollarSign className="h-4 w-4 text-emerald-600" />
                                Top Campaigns by Revenue
                            </h3>
                            <span className="text-xs text-slate-400">Won Sales</span>
                        </div>
                        <div className="space-y-3">
                            {topRevenue.length > 0 ? (
                                topRevenue.map((c, i) => (
                                    <div key={c.id} className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700">
                                        <div className="flex items-center gap-3">
                                            <span className="flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-bold text-xs">
                                                #{i + 1}
                                            </span>
                                            <div>
                                                <Link
                                                    href={route('email-campaigns.attribution', c.id)}
                                                    className="text-xs font-bold text-slate-900 dark:text-white hover:text-sky-600 flex items-center gap-1"
                                                >
                                                    {c.name}
                                                    <ArrowUpRight className="h-3 w-3 text-slate-400" />
                                                </Link>
                                                <div className="text-[11px] text-slate-400">
                                                    {c.generated_sales} sales · {c.generated_leads} leads
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-sm font-black text-emerald-600 dark:text-emerald-400">
                                            {c.formatted_revenue}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-xs text-slate-400 py-6 text-center">No campaign revenue recorded.</div>
                            )}
                        </div>
                    </div>

                    {/* Top by Generated Leads */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Target className="h-4 w-4 text-blue-600" />
                                Top Campaigns by Lead Generation
                            </h3>
                            <span className="text-xs text-slate-400">CRM Leads</span>
                        </div>
                        <div className="space-y-3">
                            {topLeads.length > 0 ? (
                                topLeads.map((c, i) => (
                                    <div key={c.id} className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700">
                                        <div className="flex items-center gap-3">
                                            <span className="flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 font-bold text-xs">
                                                #{i + 1}
                                            </span>
                                            <div>
                                                <Link
                                                    href={route('email-campaigns.attribution', c.id)}
                                                    className="text-xs font-bold text-slate-900 dark:text-white hover:text-sky-600 flex items-center gap-1"
                                                >
                                                    {c.name}
                                                    <ArrowUpRight className="h-3 w-3 text-slate-400" />
                                                </Link>
                                                <div className="text-[11px] text-slate-400">
                                                    {c.delivered_count} delivered · {c.open_rate}% open rate
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-sm font-black text-blue-600 dark:text-blue-400">
                                            {c.generated_leads} Leads
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-xs text-slate-400 py-6 text-center">No campaign leads recorded.</div>
                            )}
                        </div>
                    </div>
                </div>

                {/* 3. Detailed Cross-Campaign Attribution Matrix */}
                <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    <div className="p-5 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <BarChart3 className="h-4 w-4 text-sky-600" />
                            Comprehensive Campaign Attribution Ledger
                        </h3>
                        <span className="text-xs text-slate-400">
                            Derived strictly from stored database entities
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                            <thead className="bg-slate-50 dark:bg-slate-900/50 text-slate-500 font-medium border-b border-slate-200 dark:border-slate-700 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th className="py-3 px-4">Campaign Name</th>
                                    <th className="py-3 px-4">Delivered</th>
                                    <th className="py-3 px-4">Open Rate</th>
                                    <th className="py-3 px-4">Click Rate</th>
                                    <th className="py-3 px-4">Leads</th>
                                    <th className="py-3 px-4">Inspections</th>
                                    <th className="py-3 px-4">Opportunities</th>
                                    <th className="py-3 px-4">Won Sales</th>
                                    <th className="py-3 px-4">Attributed Revenue</th>
                                    <th className="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                {campaigns.length > 0 ? (
                                    campaigns.map((c) => (
                                        <tr key={c.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                            <td className="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                                {c.name}
                                            </td>
                                            <td className="py-3 px-4 font-mono">{c.delivered_count}</td>
                                            <td className="py-3 px-4">{c.open_rate}%</td>
                                            <td className="py-3 px-4">{c.click_rate}%</td>
                                            <td className="py-3 px-4 font-semibold text-blue-600">{c.generated_leads}</td>
                                            <td className="py-3 px-4 font-semibold text-indigo-600">{c.generated_inspections}</td>
                                            <td className="py-3 px-4 font-semibold text-amber-600">{c.generated_opportunities}</td>
                                            <td className="py-3 px-4 font-semibold text-emerald-600">{c.generated_sales}</td>
                                            <td className="py-3 px-4 font-bold text-emerald-700 dark:text-emerald-400">
                                                {c.formatted_revenue}
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <Link
                                                    href={route('email-campaigns.attribution', c.id)}
                                                    className="inline-flex items-center text-xs font-semibold text-sky-600 hover:text-sky-700"
                                                >
                                                    Funnel
                                                    <ArrowUpRight className="h-3 w-3 ml-0.5" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={10} className="py-8 text-center text-slate-400">
                                            No campaigns found for the selected reporting period.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
