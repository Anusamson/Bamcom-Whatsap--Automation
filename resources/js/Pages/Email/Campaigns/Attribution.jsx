import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    Send, 
    ArrowLeft, 
    Users, 
    CheckCircle, 
    Eye, 
    MousePointer, 
    DollarSign, 
    TrendingUp, 
    Target, 
    Compass, 
    Briefcase, 
    Filter, 
    Calendar,
    ChevronRight,
    Award
} from 'lucide-react';

export default function Attribution({ auth, campaign, attribution }) {
    const [activeTab, setActiveTab] = useState('funnel'); // 'funnel', 'leads', 'inspections', 'deals'
    const [windowDays, setWindowDays] = useState(attribution.campaign.attribution_window_days || 90);

    const handleWindowChange = (days) => {
        setWindowDays(days);
        router.get(route('email-campaigns.attribution', campaign.id), { window_days: days }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const metrics = attribution.attribution || {};
    const engagement = attribution.engagement || {};
    const funnel = attribution.funnel || [];
    const records = attribution.records || { leads: [], inspections: [], deals: [], won_deals: [] };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Link
                                href={route('email-campaigns.show', campaign.id)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                            <span className="text-xs text-slate-500 font-medium">Email Campaigns / {campaign.name}</span>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <TrendingUp className="h-6 w-6 text-emerald-600" />
                                Sales Attribution & Funnel Analytics
                            </h2>
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                {campaign.status?.toUpperCase() || 'COMPLETED'}
                            </span>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                            <Calendar className="h-3.5 w-3.5 text-slate-400" />
                            <span>Attribution Window:</span>
                            <select
                                value={windowDays}
                                onChange={(e) => handleWindowChange(Number(e.target.value))}
                                className="text-xs font-semibold bg-transparent border-none p-0 focus:ring-0 text-sky-600 dark:text-sky-400 cursor-pointer"
                            >
                                <option value={30}>30 Days</option>
                                <option value={60}>60 Days</option>
                                <option value={90}>90 Days</option>
                                <option value={180}>180 Days</option>
                            </select>
                        </div>

                        <Link
                            href={route('email-campaigns.reports.attribution')}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                        >
                            Executive Report
                            <ChevronRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Sales Attribution - ${campaign.name}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                {/* 1. Top Revenue & Key Conversion KPI Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    {/* Attributed Revenue Card */}
                    <div className="bg-gradient-to-br from-emerald-600 to-teal-700 text-white p-5 rounded-2xl shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-emerald-100 text-xs font-medium">
                            <span>Attributed Sales Revenue</span>
                            <DollarSign className="h-4 w-4 text-emerald-200" />
                        </div>
                        <div className="text-2xl font-black tracking-tight">
                            {metrics.formatted_attributed_revenue || '₦0.00'}
                        </div>
                        <div className="text-xs text-emerald-100 pt-1 border-t border-emerald-500/40">
                            {metrics.campaign_generated_sales || 0} Deals Closed Won
                        </div>
                    </div>

                    {/* Generated Leads */}
                    <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                            <span>Generated Leads</span>
                            <Target className="h-4 w-4 text-blue-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {metrics.campaign_generated_leads || 0}
                        </div>
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            Conv. Rate: <span className="font-semibold text-blue-600 dark:text-blue-400">{metrics.rates?.lead_conversion_rate || 0}%</span>
                        </div>
                    </div>

                    {/* Generated Site Inspections */}
                    <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                            <span>Site Inspections</span>
                            <Compass className="h-4 w-4 text-indigo-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {metrics.campaign_generated_inspections || 0}
                        </div>
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            Inspection Rate: <span className="font-semibold text-indigo-600 dark:text-indigo-400">{metrics.rates?.inspection_conversion_rate || 0}%</span>
                        </div>
                    </div>

                    {/* Generated Opportunities */}
                    <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                            <span>Pipeline Deals</span>
                            <Briefcase className="h-4 w-4 text-amber-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {metrics.campaign_generated_opportunities || 0}
                        </div>
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            Win Rate: <span className="font-semibold text-amber-600 dark:text-amber-400">{metrics.rates?.deal_win_rate || 0}%</span>
                        </div>
                    </div>

                    {/* Revenue per Recipient */}
                    <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-2">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                            <span>Rev. / Recipient</span>
                            <Award className="h-4 w-4 text-purple-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            ₦{Number(metrics.revenue_per_recipient || 0).toLocaleString()}
                        </div>
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            Per Delivered: ₦{Number(metrics.revenue_per_delivered || 0).toLocaleString()}
                        </div>
                    </div>
                </div>

                {/* 2. Visual Multi-Stage Sales Funnel */}
                <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-6">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-700 pb-4">
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Campaign-to-Revenue Attribution Funnel
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                End-to-end customer progression from initial email delivery to executed sales contracts.
                            </p>
                        </div>
                        <div className="flex items-center gap-2 text-xs font-medium text-slate-500">
                            <span className="inline-block w-2.5 h-2.5 rounded-full bg-sky-500" /> Marketing Email
                            <span className="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 ml-2" /> CRM Pipeline & Revenue
                        </div>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
                        {funnel.map((step, idx) => {
                            const isSalesStep = idx >= 4;
                            const isRevenue = idx === funnel.length - 1;
                            return (
                                <div
                                    key={idx}
                                    className={`relative p-3.5 rounded-xl border flex flex-col justify-between text-center transition ${
                                        isRevenue
                                            ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700 shadow-sm'
                                            : isSalesStep
                                            ? 'bg-teal-50/60 dark:bg-teal-950/20 border-teal-200 dark:border-teal-800'
                                            : 'bg-slate-50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700'
                                    }`}
                                >
                                    <div className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 truncate mb-1">
                                        {step.stage}
                                    </div>
                                    <div className={`text-xl font-black ${isRevenue ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-900 dark:text-white'}`}>
                                        {step.count.toLocaleString()}
                                    </div>
                                    {idx > 0 && (
                                        <div className="text-[10px] text-slate-400 mt-2">
                                            Step #{idx + 1}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* 3. Deep Dive Tabs: Attributed Leads, Inspections, Deals & Won Revenue */}
                <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    <div className="flex border-b border-slate-200 dark:border-slate-700 px-6 pt-4 gap-6">
                        <button
                            type="button"
                            onClick={() => setActiveTab('funnel')}
                            className={`pb-3 text-xs font-semibold border-b-2 transition ${
                                activeTab === 'funnel'
                                    ? 'border-sky-600 text-sky-600 dark:text-sky-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            Campaign Engagement Rates
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('leads')}
                            className={`pb-3 text-xs font-semibold border-b-2 transition ${
                                activeTab === 'leads'
                                    ? 'border-sky-600 text-sky-600 dark:text-sky-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            Attributed Leads ({records.leads?.length || 0})
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('inspections')}
                            className={`pb-3 text-xs font-semibold border-b-2 transition ${
                                activeTab === 'inspections'
                                    ? 'border-sky-600 text-sky-600 dark:text-sky-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            Attributed Inspections ({records.inspections?.length || 0})
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('deals')}
                            className={`pb-3 text-xs font-semibold border-b-2 transition ${
                                activeTab === 'deals'
                                    ? 'border-sky-600 text-sky-600 dark:text-sky-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            Attributed Sales & Deals ({records.deals?.length || 0})
                        </button>
                    </div>

                    <div className="p-6">
                        {/* Tab 1: Email Engagement Rates */}
                        {activeTab === 'funnel' && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                                    <div className="text-xs text-slate-500">Delivery Rate</div>
                                    <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                                        {engagement.delivery_rate || 0}%
                                    </div>
                                    <div className="text-[11px] text-slate-400 mt-1">{engagement.delivered} / {engagement.sent}</div>
                                </div>
                                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                                    <div className="text-xs text-slate-500">Open Rate</div>
                                    <div className="text-xl font-bold text-teal-600 dark:text-teal-400 mt-1">
                                        {engagement.open_rate || 0}%
                                    </div>
                                    <div className="text-[11px] text-slate-400 mt-1">{engagement.opened} opens</div>
                                </div>
                                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                                    <div className="text-xs text-slate-500">Click Rate</div>
                                    <div className="text-xl font-bold text-sky-600 dark:text-sky-400 mt-1">
                                        {engagement.click_rate || 0}%
                                    </div>
                                    <div className="text-[11px] text-slate-400 mt-1">{engagement.clicked} clicks</div>
                                </div>
                                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                                    <div className="text-xs text-slate-500">Click-to-Open</div>
                                    <div className="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                                        {engagement.click_to_open_rate || 0}%
                                    </div>
                                    <div className="text-[11px] text-slate-400 mt-1">Clicks / Opens</div>
                                </div>
                                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                                    <div className="text-xs text-slate-500">Bounce Rate</div>
                                    <div className="text-xl font-bold text-rose-600 dark:text-rose-400 mt-1">
                                        {engagement.bounce_rate || 0}%
                                    </div>
                                    <div className="text-[11px] text-slate-400 mt-1">{engagement.bounced} bounces</div>
                                </div>
                                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                                    <div className="text-xs text-slate-500">Unsubscribe Rate</div>
                                    <div className="text-xl font-bold text-purple-600 dark:text-purple-400 mt-1">
                                        {engagement.unsubscribe_rate || 0}%
                                    </div>
                                    <div className="text-[11px] text-slate-400 mt-1">{engagement.unsubscribed} unsubs</div>
                                </div>
                            </div>
                        )}

                        {/* Tab 2: Attributed Leads */}
                        {activeTab === 'leads' && (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                                    <thead className="bg-slate-50 dark:bg-slate-900/50 text-slate-500 font-medium border-b border-slate-200 dark:border-slate-700 uppercase tracking-wider text-[10px]">
                                        <tr>
                                            <th className="py-3 px-4">Contact</th>
                                            <th className="py-3 px-4">Lead Title</th>
                                            <th className="py-3 px-4">Assigned Agent</th>
                                            <th className="py-3 px-4">Score</th>
                                            <th className="py-3 px-4">Status</th>
                                            <th className="py-3 px-4">Created Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                        {records.leads?.length > 0 ? (
                                            records.leads.map((lead) => (
                                                <tr key={lead.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                                    <td className="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                                        {lead.contact?.first_name} {lead.contact?.last_name}
                                                    </td>
                                                    <td className="py-3 px-4">{lead.title}</td>
                                                    <td className="py-3 px-4">{lead.assigned_user?.name || 'Unassigned'}</td>
                                                    <td className="py-3 px-4 font-bold text-sky-600">{lead.score || 0}</td>
                                                    <td className="py-3 px-4">
                                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                                            {lead.status}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-4 text-slate-400">{new Date(lead.created_at).toLocaleDateString()}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={6} className="py-8 text-center text-slate-400">
                                                    No leads attributed to this campaign within the {windowDays}-day window.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Tab 3: Attributed Inspections */}
                        {activeTab === 'inspections' && (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                                    <thead className="bg-slate-50 dark:bg-slate-900/50 text-slate-500 font-medium border-b border-slate-200 dark:border-slate-700 uppercase tracking-wider text-[10px]">
                                        <tr>
                                            <th className="py-3 px-4">Contact</th>
                                            <th className="py-3 px-4">Property / Estate</th>
                                            <th className="py-3 px-4">Inspection Date</th>
                                            <th className="py-3 px-4">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                        {records.inspections?.length > 0 ? (
                                            records.inspections.map((insp) => (
                                                <tr key={insp.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                                    <td className="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                                        {insp.contact?.first_name} {insp.contact?.last_name}
                                                    </td>
                                                    <td className="py-3 px-4">{insp.property?.title || insp.estate_name || 'VIP Estate Tour'}</td>
                                                    <td className="py-3 px-4">{insp.inspection_date}</td>
                                                    <td className="py-3 px-4">
                                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                            {insp.status}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={4} className="py-8 text-center text-slate-400">
                                                    No site inspections attributed within the {windowDays}-day window.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Tab 4: Attributed Deals & Won Sales */}
                        {activeTab === 'deals' && (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                                    <thead className="bg-slate-50 dark:bg-slate-900/50 text-slate-500 font-medium border-b border-slate-200 dark:border-slate-700 uppercase tracking-wider text-[10px]">
                                        <tr>
                                            <th className="py-3 px-4">Deal Title</th>
                                            <th className="py-3 px-4">Contact</th>
                                            <th className="py-3 px-4">Deal Value</th>
                                            <th className="py-3 px-4">Status</th>
                                            <th className="py-3 px-4">Agent</th>
                                            <th className="py-3 px-4">Created Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                        {records.deals?.length > 0 ? (
                                            records.deals.map((deal) => {
                                                const isWon = deal.status === 'won';
                                                return (
                                                    <tr key={deal.id} className={isWon ? 'bg-emerald-50/40 dark:bg-emerald-950/20 font-medium' : ''}>
                                                        <td className="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                                            {deal.title}
                                                        </td>
                                                        <td className="py-3 px-4">
                                                            {deal.contact?.first_name} {deal.contact?.last_name}
                                                        </td>
                                                        <td className="py-3 px-4 font-bold text-emerald-700 dark:text-emerald-400">
                                                            ₦{Number(deal.deal_value || 0).toLocaleString()}
                                                        </td>
                                                        <td className="py-3 px-4">
                                                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                                                                isWon 
                                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                                    : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300'
                                                            }`}>
                                                                {deal.status?.toUpperCase()}
                                                            </span>
                                                        </td>
                                                        <td className="py-3 px-4">{deal.assigned_user?.name || 'Unassigned'}</td>
                                                        <td className="py-3 px-4 text-slate-400">{new Date(deal.created_at).toLocaleDateString()}</td>
                                                    </tr>
                                                );
                                            })
                                        ) : (
                                            <tr>
                                                <td colSpan={6} className="py-8 text-center text-slate-400">
                                                    No deals or sales opportunities attributed within the {windowDays}-day window.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
