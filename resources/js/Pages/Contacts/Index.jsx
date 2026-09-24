import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    Contact, 
    UserPlus, 
    Search, 
    Filter, 
    Edit, 
    Trash2, 
    Eye, 
    Phone, 
    Mail, 
    MapPin, 
    Briefcase, 
    MessageSquare, 
    RotateCcw,
    Users,
    Sparkles,
    UserCheck,
    Clock,
    ExternalLink,
    ChevronLeft,
    ChevronRight,
    Upload
} from 'lucide-react';
import { useState } from 'react';
import ImportContactsModal from './Partials/ImportContactsModal';

export default function Index({ contacts, filters, users, statuses, leadSources, metrics }) {
    const { auth, flash } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;

    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [leadSource, setLeadSource] = useState(filters.lead_source || '');
    const [assignedUserId, setAssignedUserId] = useState(filters.assigned_user_id || '');
    const [perPage, setPerPage] = useState(filters.per_page || '15');
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);

    const handleFilter = (e) => {
        e?.preventDefault();
        router.get(route('contacts.index'), {
            search: search || undefined,
            status: status || undefined,
            lead_source: leadSource || undefined,
            assigned_user_id: assignedUserId || undefined,
            per_page: perPage !== '15' ? perPage : undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        setStatus('');
        setLeadSource('');
        setAssignedUserId('');
        setPerPage('15');
        router.get(route('contacts.index'));
    };

    const handleDelete = (contact) => {
        if (confirm(`Are you sure you want to archive contact "${contact.full_name}"?`)) {
            router.delete(route('contacts.destroy', contact.id));
        }
    };

    const getStatusBadge = (statusVal) => {
        const found = statuses?.find(s => s.value === statusVal);
        if (found) {
            return (
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${found.badge}`}>
                    {found.label}
                </span>
            );
        }
        return (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                {statusVal}
            </span>
        );
    };

    const getSourceBadge = (sourceVal) => {
        const found = leadSources?.find(s => s.value === sourceVal);
        if (found) {
            return (
                <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${found.badge}`}>
                    {found.label}
                </span>
            );
        }
        return <span className="text-xs text-slate-500">{sourceVal}</span>;
    };

    const formatRelativeTime = (dateStr) => {
        if (!dateStr) return 'Never contacted';
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffHours / 24);

        if (diffHours < 1) return 'Just now';
        if (diffHours < 24) return `${diffHours} hr${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 30) return `${diffDays} days ago`;
        return date.toLocaleDateString();
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">
                            <Contact className="h-4 w-4" />
                            <span>CRM Customer Relations</span>
                        </div>
                        <h2 className="font-bold text-2xl text-slate-900 dark:text-white leading-tight">
                            Contacts Directory
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Manage leads, investors, customers, and client communication channels.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {can('contacts.create') && (
                            <button
                                type="button"
                                onClick={() => setIsImportModalOpen(true)}
                                className="inline-flex items-center gap-2 px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-xl shadow-sm transition duration-150 ease-in-out"
                            >
                                <Upload className="h-4 w-4 text-slate-500" />
                                <span>Import Contacts</span>
                            </button>
                        )}

                        {can('contacts.create') && (
                            <Link
                                href={route('contacts.create')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-semibold rounded-xl shadow-sm transition duration-150 ease-in-out"
                            >
                                <UserPlus className="h-4 w-4" />
                                <span>Add New Contact</span>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Contacts Directory - Bamcom AI CRM" />

            <div className="space-y-6">
                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 text-sm flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span>{flash.success}</span>
                        </div>
                    </div>
                )}

                {/* Metrics Summary Strip */}
                {metrics && (
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Contacts</span>
                            <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">{metrics.total}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-blue-600 dark:text-blue-400">Active Leads</span>
                            <div className="text-xl font-bold text-blue-600 dark:text-blue-400 mt-1">{metrics.leads}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-amber-600 dark:text-amber-400">Prospects</span>
                            <div className="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">{metrics.prospects}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">Customers</span>
                            <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{metrics.customers}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-green-600 dark:text-green-400">WhatsApp Origin</span>
                            <div className="text-xl font-bold text-green-600 dark:text-green-400 mt-1">{metrics.whatsapp_leads}</div>
                        </div>
                        <div className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span className="text-xs font-medium text-red-600 dark:text-red-400">Unassigned</span>
                            <div className="text-xl font-bold text-red-600 dark:text-red-400 mt-1">{metrics.unassigned}</div>
                        </div>
                    </div>
                )}

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <form onSubmit={handleFilter} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                        {/* Search Input */}
                        <div className="lg:col-span-2 relative">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search by name, phone, email, occupation..."
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
                                <option value="">All Statuses</option>
                                {statuses?.map((s) => (
                                    <option key={s.value} value={s.value}>{s.label}</option>
                                ))}
                            </select>
                        </div>

                        {/* Lead Source Filter */}
                        <div>
                            <select
                                value={leadSource}
                                onChange={(e) => setLeadSource(e.target.value)}
                                className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            >
                                <option value="">All Lead Sources</option>
                                {leadSources?.map((source) => (
                                    <option key={source.value} value={source.value}>{source.label}</option>
                                ))}
                            </select>
                        </div>

                        {/* Assigned Agent Filter */}
                        <div>
                            <select
                                value={assignedUserId}
                                onChange={(e) => setAssignedUserId(e.target.value)}
                                className="w-full py-2 px-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            >
                                <option value="">All Agents</option>
                                <option value="unassigned">Unassigned Only</option>
                                {users?.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </div>

                        {/* Actions: Filter & Reset */}
                        <div className="flex items-center gap-2">
                            <button
                                type="submit"
                                className="flex-1 inline-flex items-center justify-center gap-1.5 py-2 px-3 bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-white text-white dark:text-slate-900 rounded-lg text-sm font-semibold transition shadow-sm"
                            >
                                <Filter className="h-3.5 w-3.5" />
                                <span>Filter</span>
                            </button>
                            <button
                                type="button"
                                onClick={handleReset}
                                className="inline-flex items-center justify-center p-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg transition"
                                title="Reset Filters"
                            >
                                <RotateCcw className="h-4 w-4" />
                            </button>
                        </div>
                    </form>
                </div>

                {/* Contacts Data Table */}
                <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                            <thead className="bg-slate-50 dark:bg-slate-950/60 text-xs uppercase font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className="px-5 py-3.5">Contact Name & Info</th>
                                    <th className="px-4 py-3.5">Normalized Phone</th>
                                    <th className="px-4 py-3.5">Status</th>
                                    <th className="px-4 py-3.5">Lead Source</th>
                                    <th className="px-4 py-3.5">Assigned Agent</th>
                                    <th className="px-4 py-3.5">Last Contact</th>
                                    <th className="px-5 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
                                {contacts.data && contacts.data.length > 0 ? (
                                    contacts.data.map((contact) => (
                                        <tr key={contact.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                            {/* Name & Occupation */}
                                            <td className="px-5 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="h-10 w-10 rounded-full bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-bold text-sm shadow-sm flex-shrink-0">
                                                        {contact.initials || 'C'}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <Link 
                                                            href={route('contacts.show', contact.id)}
                                                            className="font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition truncate block"
                                                        >
                                                            {contact.full_name}
                                                        </Link>
                                                        <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                            {contact.email && (
                                                                <span className="truncate flex items-center gap-1">
                                                                    <Mail className="h-3 w-3 text-slate-400" />
                                                                    {contact.email}
                                                                </span>
                                                            )}
                                                            {contact.location && (
                                                                <span className="hidden sm:inline-flex items-center gap-1">
                                                                    • <MapPin className="h-3 w-3 text-slate-400" />
                                                                    {contact.location}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Phone & WhatsApp Quick Launcher */}
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-xs font-semibold text-slate-900 dark:text-white">
                                                        {contact.formatted_phone || contact.phone}
                                                    </span>
                                                    {contact.whatsapp_url && (
                                                        <a
                                                            href={contact.whatsapp_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="p-1 rounded-md text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition"
                                                            title="Launch WhatsApp Chat"
                                                        >
                                                            <MessageSquare className="h-3.5 w-3.5" />
                                                        </a>
                                                    )}
                                                </div>
                                                {contact.occupation && (
                                                    <span className="text-[11px] text-slate-400 dark:text-slate-500 block mt-0.5">
                                                        {contact.occupation}
                                                    </span>
                                                )}
                                            </td>

                                            {/* Status Badge */}
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                {getStatusBadge(contact.status)}
                                            </td>

                                            {/* Lead Source Badge */}
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                {getSourceBadge(contact.lead_source)}
                                            </td>

                                            {/* Assigned Agent */}
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                {contact.assigned_user ? (
                                                    <div className="flex items-center gap-1.5">
                                                        <div className="h-5 w-5 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-700 dark:text-slate-300">
                                                            {contact.assigned_user.name.charAt(0)}
                                                        </div>
                                                        <span className="text-xs text-slate-800 dark:text-slate-200 font-medium">
                                                            {contact.assigned_user.name}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                        Unassigned
                                                    </span>
                                                )}
                                            </td>

                                            {/* Last Contact */}
                                            <td className="px-4 py-4 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                                <div className="flex items-center gap-1.5">
                                                    <Clock className="h-3 w-3 text-slate-400" />
                                                    <span>{formatRelativeTime(contact.last_contact_at)}</span>
                                                </div>
                                            </td>

                                            {/* Actions */}
                                            <td className="px-5 py-4 whitespace-nowrap text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {/* View 360 Profile */}
                                                    {can('contacts.view') && (
                                                        <Link
                                                            href={route('contacts.show', contact.id)}
                                                            className="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition"
                                                            title="Contact 360 Profile"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    )}

                                                    {/* Edit */}
                                                    {can('contacts.edit') && (
                                                        <Link
                                                            href={route('contacts.edit', contact.id)}
                                                            className="p-1.5 rounded-lg text-slate-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition"
                                                            title="Edit Contact"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Link>
                                                    )}

                                                    {/* Delete */}
                                                    {can('contacts.delete') && (
                                                        <button
                                                            onClick={() => handleDelete(contact)}
                                                            className="p-1.5 rounded-lg text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                                            title="Archive Contact"
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
                                        <td colSpan="7" className="px-5 py-12 text-center text-slate-400">
                                            <Contact className="h-12 w-12 mx-auto mb-3 opacity-30" />
                                            <p className="text-base font-semibold text-slate-700 dark:text-slate-300">No CRM contacts found</p>
                                            <p className="text-xs text-slate-500 mt-1">Try adjusting your search criteria or register a new contact.</p>
                                            {can('contacts.create') && (
                                                <Link
                                                    href={route('contacts.create')}
                                                    className="inline-flex items-center gap-1.5 mt-4 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                                                >
                                                    <UserPlus className="h-3.5 w-3.5" />
                                                    <span>Add First Contact</span>
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Bar */}
                    {contacts.links && contacts.links.length > 3 && (
                        <div className="px-5 py-3.5 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <div>
                                Showing <span className="font-semibold text-slate-700 dark:text-slate-200">{contacts.from || 0}</span> to{' '}
                                <span className="font-semibold text-slate-700 dark:text-slate-200">{contacts.to || 0}</span> of{' '}
                                <span className="font-semibold text-slate-700 dark:text-slate-200">{contacts.total}</span> contacts
                            </div>

                            <div className="flex items-center gap-1">
                                {contacts.links.map((link, idx) => {
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

            <ImportContactsModal
                show={isImportModalOpen}
                onClose={() => setIsImportModalOpen(false)}
                users={users}
                statuses={statuses}
                leadSources={leadSources}
            />
        </AuthenticatedLayout>
    );
}
