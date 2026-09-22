import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    TrendingUp, 
    Plus, 
    Search, 
    Filter, 
    Edit, 
    Trash2, 
    Eye, 
    Phone, 
    MapPin, 
    Briefcase, 
    RotateCcw,
    Sparkles,
    Flame,
    SunMedium,
    Snowflake,
    Calendar,
    Clock,
    DollarSign,
    Building,
    User,
    CheckCircle2,
    MessageSquare,
    ExternalLink
} from 'lucide-react';
import { useState } from 'react';

export default function Index({ leads, filters, metrics, agents, statuses, temperatures, sources }) {
    const { auth, flash } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [temperature, setTemperature] = useState(filters.temperature || '');
    const [agent, setAgent] = useState(filters.agent || '');
    const [source, setSource] = useState(filters.source || '');
    const [property, setProperty] = useState(filters.property || '');
    const [date, setDate] = useState(filters.date || '');
    const [perPage, setPerPage] = useState(filters.per_page || '15');

    const handleFilter = (e) => {
        e?.preventDefault();
        router.get(route('leads.index'), {
            search: search || undefined,
            status: status || undefined,
            temperature: temperature || undefined,
            agent: agent || undefined,
            source: source || undefined,
            property: property || undefined,
            date: date || undefined,
            per_page: perPage !== '15' ? perPage : undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        setStatus('');
        setTemperature('');
        setAgent('');
        setSource('');
        setProperty('');
        setDate('');
        setPerPage('15');
        router.get(route('leads.index'));
    };

    const handleDelete = (lead) => {
        if (confirm(`Are you sure you want to archive opportunity "${lead.title}"?`)) {
            router.delete(route('leads.destroy', lead.id));
        }
    };

    const getTemperatureBadge = (tempVal) => {
        const found = temperatures?.find(t => t.value === tempVal);
        if (found) {
            return (
                <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border ${found.badge}`}>
                    <span>{found.icon}</span>
                    <span>{found.label}</span>
                </span>
            );
        }
        return <span className="text-xs">{tempVal}</span>;
    };

    const getStatusBadge = (statusVal) => {
        const found = statuses?.find(s => s.value === statusVal);
        if (found) {
            return (
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border ${found.badge}`}>
                    {found.label}
                </span>
            );
        }
        return <span className="text-xs">{statusVal}</span>;
    };

    const formatCurrency = (amount) => {
        if (!amount) return '₦0';
        return '₦' + Number(amount).toLocaleString();
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return 'N/A';
        return new Date(dateStr).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">
                            <TrendingUp className="h-4 w-4" />
                            <span>Sales Pipeline & Opportunities</span>
                        </div>
                        <h2 className="font-bold text-2xl text-slate-900 dark:text-white leading-tight">
                            Sales Leads Directory
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Track client buying intent, property interests, temperature, and deal progression.
                        </p>
                    </div>

                    {can('leads.create') && (
                        <Link
                            href={route('leads.create')}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-semibold rounded-xl shadow-sm transition"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Create Opportunity</span>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Sales Leads - Bamcom AI CRM" />

            <div className="space-y-6">
                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}

                {/* Pipeline Metrics Strip */}
                {metrics && (
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Leads</span>
                            <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">{metrics.total}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-red-600 dark:text-red-400 flex items-center gap-1">
                                <span>🔥 Hot Leads</span>
                            </span>
                            <div className="text-xl font-bold text-red-600 dark:text-red-400 mt-1">{metrics.hot}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-indigo-600 dark:text-indigo-400">Qualified</span>
                            <div className="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{metrics.qualified}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-amber-600 dark:text-amber-400">In Negotiation</span>
                            <div className="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">{metrics.negotiation}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">Closed Won</span>
                            <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{metrics.won}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-blue-600 dark:text-blue-400">Pipeline Value</span>
                            <div className="text-base sm:text-lg font-bold text-blue-600 dark:text-blue-400 mt-1 truncate">
                                {formatCurrency(metrics.total_pipeline_value)}
                            </div>
                        </div>
                    </div>
                )}

                {/* Multi-faceted Filtering Toolbar */}
                <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <form onSubmit={handleFilter} className="space-y-3">
                        {/* Top row: search & primary filters */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            {/* Text Search */}
                            <div className="relative">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                <input
                                    type="text"
                                    placeholder="Search by opportunity, contact, phone, location..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                            </div>

                            {/* Status Filter */}
                            <div>
                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option value="">All Pipeline Stages</option>
                                    {statuses?.map((s) => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Temperature Filter */}
                            <div>
                                <select
                                    value={temperature}
                                    onChange={(e) => setTemperature(e.target.value)}
                                    className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition font-medium"
                                >
                                    <option value="">All Temperatures (Urgency)</option>
                                    <option value="hot">🔥 Hot Leads</option>
                                    <option value="warm">☀️ Warm Leads</option>
                                    <option value="cold">❄️ Cold Leads</option>
                                </select>
                            </div>

                            {/* Representative / Agent Filter */}
                            <div>
                                <select
                                    value={agent}
                                    onChange={(e) => setAgent(e.target.value)}
                                    className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option value="">All Sales Reps</option>
                                    <option value="unassigned">Unassigned Only</option>
                                    {agents?.map((a) => (
                                        <option key={a.id} value={a.id}>{a.name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Second row: source, property, date, actions */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1 border-t border-slate-100 dark:border-slate-800/80">
                            {/* Lead Source Filter */}
                            <div>
                                <select
                                    value={source}
                                    onChange={(e) => setSource(e.target.value)}
                                    className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option value="">All Lead Sources</option>
                                    {sources?.map((s) => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Property Interest Filter */}
                            <div>
                                <input
                                    type="text"
                                    placeholder="Filter by property interest (e.g. Duplex, Land)..."
                                    value={property}
                                    onChange={(e) => setProperty(e.target.value)}
                                    className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                            </div>

                            {/* Date Filter */}
                            <div>
                                <select
                                    value={date}
                                    onChange={(e) => setDate(e.target.value)}
                                    className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option value="">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="this_week">This Week</option>
                                    <option value="this_month">This Month</option>
                                    <option value="last_30_days">Last 30 Days</option>
                                </select>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex items-center gap-2">
                                <button
                                    type="submit"
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 py-2 px-3 bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-white text-white dark:text-slate-900 rounded-lg text-sm font-semibold transition shadow-sm"
                                >
                                    <Filter className="h-3.5 w-3.5" />
                                    <span>Apply Filters</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="inline-flex items-center justify-center p-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg transition"
                                    title="Reset All Filters"
                                >
                                    <RotateCcw className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {/* Leads Data Table */}
                <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                            <thead className="bg-slate-50 dark:bg-slate-950/60 text-xs uppercase font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className="px-5 py-3.5">Opportunity & Interest</th>
                                    <th className="px-4 py-3.5">Contact Details</th>
                                    <th className="px-4 py-3.5">Temp & Score</th>
                                    <th className="px-4 py-3.5">Budget & Timeline</th>
                                    <th className="px-4 py-3.5">Stage & Qualification</th>
                                    <th className="px-4 py-3.5">Assigned Agent</th>
                                    <th className="px-4 py-3.5">Date</th>
                                    <th className="px-5 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
                                {leads.data && leads.data.length > 0 ? (
                                    leads.data.map((lead) => (
                                        <tr key={lead.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                            {/* Title & Property Interest */}
                                            <td className="px-5 py-4">
                                                <div className="min-w-0">
                                                    <Link
                                                        href={route('leads.show', lead.id)}
                                                        className="font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition block truncate"
                                                    >
                                                        {lead.title}
                                                    </Link>
                                                    <div className="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                        <Building className="h-3 w-3 text-slate-400 flex-shrink-0" />
                                                        <span className="truncate">{lead.property_interest || 'Property unspecified'}</span>
                                                        {lead.preferred_location && (
                                                            <span className="truncate flex items-center gap-1">
                                                                • <MapPin className="h-3 w-3 text-slate-400" />
                                                                {lead.preferred_location}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Contact */}
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                {lead.contact ? (
                                                    <div>
                                                        <Link
                                                            href={route('contacts.show', lead.contact.id)}
                                                            className="font-bold text-slate-800 dark:text-slate-200 hover:text-blue-600 transition block text-xs"
                                                        >
                                                            {lead.contact.full_name}
                                                        </Link>
                                                        <div className="flex items-center gap-1.5 font-mono text-[11px] text-slate-500 mt-0.5">
                                                            <span>{lead.contact.formatted_phone || lead.contact.phone}</span>
                                                            {lead.contact.whatsapp_url && (
                                                                <a
                                                                    href={lead.contact.whatsapp_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="text-emerald-600 hover:text-emerald-700"
                                                                    title="Open WhatsApp Chat"
                                                                >
                                                                    <MessageSquare className="h-3 w-3" />
                                                                </a>
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-slate-400">No Contact</span>
                                                )}
                                            </td>

                                            {/* Temperature & Score */}
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                <div className="flex items-center gap-2">
                                                    {getTemperatureBadge(lead.temperature)}
                                                    <span className="text-xs font-bold font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                        {lead.score}/100
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Budget & Timeline */}
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                <div className="text-xs font-bold text-slate-900 dark:text-white">
                                                    {lead.formatted_budget}
                                                </div>
                                                <span className="text-[11px] text-slate-500 capitalize block mt-0.5">
                                                    {lead.purchase_timeline?.replace(/_/g, ' ') || 'Timeline flexible'}
                                                </span>
                                            </td>

                                            {/* Status & Qualification */}
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                <div>
                                                    {getStatusBadge(lead.status)}
                                                    <span className="text-[10px] text-slate-400 uppercase tracking-wider block mt-1">
                                                        {lead.qualification_status?.replace(/_/g, ' ')}
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Assigned Agent */}
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                {lead.assigned_user ? (
                                                    <div className="flex items-center gap-1.5">
                                                        <div className="h-5 w-5 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-700 dark:text-slate-300">
                                                            {lead.assigned_user.name.charAt(0)}
                                                        </div>
                                                        <span className="text-xs text-slate-800 dark:text-slate-200 font-medium">
                                                            {lead.assigned_user.name}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                        Unassigned
                                                    </span>
                                                )}
                                            </td>

                                            {/* Date */}
                                            <td className="px-4 py-4 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                                {formatDate(lead.created_at)}
                                            </td>

                                            {/* Actions */}
                                            <td className="px-5 py-4 whitespace-nowrap text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {can('leads.view') && (
                                                        <Link
                                                            href={route('leads.show', lead.id)}
                                                            className="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition"
                                                            title="View Lead Opportunity"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    )}

                                                    {can('leads.edit') && (
                                                        <Link
                                                            href={route('leads.edit', lead.id)}
                                                            className="p-1.5 rounded-lg text-slate-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition"
                                                            title="Edit Opportunity"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Link>
                                                    )}

                                                    {can('leads.delete') && (
                                                        <button
                                                            onClick={() => handleDelete(lead)}
                                                            className="p-1.5 rounded-lg text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                                            title="Archive Opportunity"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="8" className="px-5 py-12 text-center text-slate-400">
                                            <TrendingUp className="h-12 w-12 mx-auto mb-3 opacity-30" />
                                            <p className="text-base font-semibold text-slate-700 dark:text-slate-300">No sales opportunities found</p>
                                            <p className="text-xs text-slate-500 mt-1">Try relaxing your filter criteria or register a new sales opportunity.</p>
                                            {can('leads.create') && (
                                                <Link
                                                    href={route('leads.create')}
                                                    className="inline-flex items-center gap-1.5 mt-4 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                                                >
                                                    <Plus className="h-3.5 w-3.5" />
                                                    <span>Create First Opportunity</span>
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {leads.links && leads.links.length > 3 && (
                        <div className="px-5 py-3.5 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <div>
                                Showing <span className="font-semibold text-slate-700 dark:text-slate-200">{leads.from || 0}</span> to{' '}
                                <span className="font-semibold text-slate-700 dark:text-slate-200">{leads.to || 0}</span> of{' '}
                                <span className="font-semibold text-slate-700 dark:text-slate-200">{leads.total}</span> opportunities
                            </div>

                            <div className="flex items-center gap-1">
                                {leads.links.map((link, idx) => {
                                    if (link.url === null) {
                                        return (
                                            <span
                                                key={idx}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-400 cursor-not-allowed text-xs"
                                            />
                                        );
                                    }

                                    return (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition ${
                                                link.active
                                                    ? 'bg-blue-600 border-blue-600 text-white shadow-sm'
                                                    : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                            }`}
                                            preserveScroll
                                            preserveState
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
