import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ListFilter,
    ArrowLeft,
    Search,
    Users,
    Phone,
    MessageSquare,
    Flame,
    Clock,
    CalendarCheck,
    DollarSign,
    Home,
    MapPin,
    ExternalLink,
    Filter,
    Sparkles,
    ShieldAlert,
    CheckCircle2,
    Sliders
} from 'lucide-react';
import { useState } from 'react';

export default function Show({
    smartList,
    contacts = { data: [], links: [] },
    filters = {}
}) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('smart-lists.show', smartList.id),
            { search },
            { preserveState: true }
        );
    };

    const getIconComponent = (iconName) => {
        const map = {
            Flame,
            Clock,
            CalendarCheck,
            DollarSign,
            Home,
            Filter,
            Users
        };
        const Comp = map[iconName] || ListFilter;
        return <Comp className="w-6 h-6" />;
    };

    const getTempBadge = (temp) => {
        const t = (temp || '').toLowerCase();
        if (t === 'hot') {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400">
                    🔥 Hot
                </span>
            );
        }
        if (t === 'warm') {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">
                    ⚡ Warm
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400">
                ❄️ Cold
            </span>
        );
    };

    const renderRuleSummary = (rg) => {
        if (!rg || !rg.rules) return 'All Contacts';
        const op = rg.logical_operator || 'AND';
        const parts = rg.rules.map((r) => {
            if (r.rules) {
                const subOp = r.logical_operator || 'OR';
                const subParts = r.rules.map((sr) => `${sr.field?.split('.')[1] || sr.field} ${sr.operator} "${sr.value}"`);
                return `(${subParts.join(` ${subOp} `)})`;
            }
            return `${r.field?.split('.')[1] || r.field} ${r.operator} "${r.value}"`;
        });
        return parts.join(` ${op} `);
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Smart List: ${smartList.name}`} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('smart-lists.index')}
                            className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl text-gray-500 transition"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div className="flex items-center gap-3">
                            <div className="p-3 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-900/50 rounded-2xl text-indigo-600 dark:text-indigo-400">
                                {getIconComponent(smartList.icon)}
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        {smartList.name}
                                    </h1>
                                    <span className="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
                                        {contacts.total || 0} matching
                                    </span>
                                </div>
                                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {smartList.description || 'Dynamic segmentation query over CRM records'}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href={route('smart-lists.index')}
                            className="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-xl transition"
                        >
                            All Smart Lists
                        </Link>
                        <Link
                            href={route('campaigns.create')}
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                        >
                            <MessageSquare className="w-3.5 h-3.5" />
                            <span>Broadcast Campaign</span>
                        </Link>
                    </div>
                </div>

                {/* Dynamic Query Formula Pill */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm text-xs">
                    <div className="flex items-center gap-2 text-gray-500">
                        <Sliders className="w-4 h-4 text-indigo-500 flex-shrink-0" />
                        <span className="font-semibold text-gray-700 dark:text-gray-300">Active Rule Formula:</span>
                        <code className="bg-gray-50 dark:bg-gray-900 px-2.5 py-1 rounded-lg text-indigo-600 dark:text-indigo-400 font-mono font-semibold">
                            {renderRuleSummary(smartList.rule_groups)}
                        </code>
                    </div>

                    <div className="text-[11px] text-gray-400 flex items-center gap-1.5">
                        <Sparkles className="w-3.5 h-3.5 text-emerald-500" />
                        <span>Dynamic query evaluated in real time (zero contact duplication)</span>
                    </div>
                </div>

                {/* Contacts Table Container */}
                <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 overflow-hidden">
                    {/* Filter and Search Bar */}
                    <div className="p-4 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                            <Users className="w-4 h-4 text-indigo-500" />
                            <span>Matching Contacts ({contacts.total || 0})</span>
                        </div>

                        <form onSubmit={handleSearch} className="relative min-w-[260px]">
                            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search within this smart list..."
                                className="w-full text-xs pl-9 pr-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500"
                            />
                        </form>
                    </div>

                    {/* Table */}
                    {contacts.data.length === 0 ? (
                        <div className="p-12 text-center text-gray-400 space-y-2">
                            <Users className="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                No contacts currently match this smart list
                            </p>
                            <p className="text-xs text-gray-400 max-w-sm mx-auto">
                                As soon as CRM contacts or leads meet the rule criteria, they will dynamically appear here.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs sm:text-sm">
                                <thead className="bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 font-semibold border-b border-gray-100 dark:border-gray-700">
                                    <tr>
                                        <th className="py-3 px-6">Contact / Phone</th>
                                        <th className="py-3 px-6">Location</th>
                                        <th className="py-3 px-6">Lead Status & Stage</th>
                                        <th className="py-3 px-6">Temperature</th>
                                        <th className="py-3 px-6">Deals / Value</th>
                                        <th className="py-3 px-6">Last Contact</th>
                                        <th className="py-3 px-6 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50 text-gray-700 dark:text-gray-300">
                                    {contacts.data.map((c) => {
                                        const primaryLead = c.leads?.[0];
                                        const primaryDeal = c.deals?.[0];

                                        return (
                                            <tr key={c.id} className="hover:bg-gray-50/50 dark:hover:bg-gray-750">
                                                <td className="py-3 px-6">
                                                    <div className="flex items-center gap-3">
                                                        <div className="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-bold flex items-center justify-center text-xs">
                                                            {c.initials || 'CL'}
                                                        </div>
                                                        <div>
                                                            <div className="font-semibold text-gray-900 dark:text-white">
                                                                {c.full_name}
                                                            </div>
                                                            <div className="text-xs text-gray-400 flex items-center gap-1">
                                                                <Phone className="w-3 h-3" />
                                                                <span>{c.phone}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="py-3 px-6 text-xs text-gray-600 dark:text-gray-300">
                                                    {c.location ? (
                                                        <span className="flex items-center gap-1">
                                                            <MapPin className="w-3 h-3 text-gray-400" />
                                                            <span>{c.location}</span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-400">—</span>
                                                    )}
                                                </td>

                                                <td className="py-3 px-6">
                                                    {primaryLead ? (
                                                        <div>
                                                            <span className="font-medium text-gray-900 dark:text-white text-xs">
                                                                {primaryLead.pipeline_stage?.name || 'In Pipeline'}
                                                            </span>
                                                            <span className="block text-[11px] text-gray-400">
                                                                Score: {primaryLead.score ?? '—'}
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-gray-400 capitalize">{c.status}</span>
                                                    )}
                                                </td>

                                                <td className="py-3 px-6">
                                                    {primaryLead ? (
                                                        getTempBadge(primaryLead.temperature)
                                                    ) : (
                                                        <span className="text-gray-400 text-xs">—</span>
                                                    )}
                                                </td>

                                                <td className="py-3 px-6">
                                                    {primaryDeal ? (
                                                        <div>
                                                            <span className="font-semibold text-emerald-600 text-xs">
                                                                ₦{Number(primaryDeal.deal_value || 0).toLocaleString()}
                                                            </span>
                                                            <span className="block text-[11px] text-gray-400">
                                                                {primaryDeal.pipeline_stage?.name || 'Active Deal'}
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-gray-400 text-xs">—</span>
                                                    )}
                                                </td>

                                                <td className="py-3 px-6 text-xs text-gray-500">
                                                    {c.last_contact_at
                                                        ? new Date(c.last_contact_at).toLocaleDateString()
                                                        : 'Never'}
                                                </td>

                                                <td className="py-3 px-6 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <a
                                                            href={c.whatsapp_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="p-1.5 hover:bg-emerald-50 dark:hover:bg-emerald-950/60 text-emerald-600 rounded-lg transition"
                                                            title="WhatsApp Chat"
                                                        >
                                                            <MessageSquare className="w-4 h-4" />
                                                        </a>
                                                        <Link
                                                            href={route('contacts.show', c.id)}
                                                            className="p-1.5 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 text-indigo-600 rounded-lg transition"
                                                            title="View Profile"
                                                        >
                                                            <ExternalLink className="w-4 h-4" />
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {contacts.links && contacts.links.length > 3 && (
                        <div className="p-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-1">
                            {contacts.links.map((link, idx) => (
                                <button
                                    key={idx}
                                    disabled={!link.url || link.active}
                                    onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                                    className={`px-3 py-1.5 text-xs rounded-lg font-medium transition ${
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : link.url
                                            ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100'
                                            : 'text-gray-300 dark:text-gray-600 cursor-not-allowed'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
