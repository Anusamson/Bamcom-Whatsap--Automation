import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    Contact, 
    ArrowLeft, 
    Edit, 
    Trash2, 
    Phone, 
    Mail, 
    MapPin, 
    Briefcase, 
    Globe, 
    Calendar, 
    Clock, 
    MessageSquare, 
    Share2, 
    ShieldCheck, 
    User, 
    Sparkles, 
    Send, 
    Building, 
    FileText, 
    CheckCircle2, 
    CheckCheck,
    AlertCircle, 
    ExternalLink,
    ChevronRight,
    UsersRound,
    Tag,
    Layers,
    Compass,
    Flame,
    TrendingUp,
    Handshake,
    XCircle
} from 'lucide-react';
import { useState } from 'react';

export default function Show({ contact, users, statuses, leadSources }) {
    const { auth, flash } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const [activeTab, setActiveTab] = useState('overview');
    const [noteText, setNoteText] = useState('');
    const [notesList, setNotesList] = useState([
        {
            id: 1,
            author: contact.assigned_user ? contact.assigned_user.name : 'System Agent',
            content: `Contact registered through ${contact.lead_source?.toUpperCase() || 'CRM'}. Initial status marked as ${contact.status}.`,
            created_at: contact.created_at,
        }
    ]);

    const handleStatusChange = (newStatus) => {
        router.patch(route('contacts.status', contact.id), { status: newStatus }, {
            preserveScroll: true,
        });
    };

    const handleLogTouchpoint = () => {
        router.post(route('contacts.touchpoint', contact.id), {}, {
            preserveScroll: true,
        });
    };

    const handleAddNote = (e) => {
        e.preventDefault();
        if (!noteText.trim()) return;

        setNotesList([
            {
                id: Date.now(),
                author: auth.user?.name || 'CRM Agent',
                content: noteText.trim(),
                created_at: new Date().toISOString(),
            },
            ...notesList
        ]);
        setNoteText('');
    };

    const getStatusBadge = (statusVal) => {
        const found = statuses?.find(s => s.value === statusVal);
        if (found) {
            return (
                <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border ${found.badge}`}>
                    {found.label}
                </span>
            );
        }
        return <span className="text-xs px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800">{statusVal}</span>;
    };

    const getSourceBadge = (sourceVal) => {
        const found = leadSources?.find(s => s.value === sourceVal);
        if (found) {
            return (
                <span className={`inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border ${found.badge}`}>
                    {found.label}
                </span>
            );
        }
        return <span>{sourceVal}</span>;
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return 'Not recorded';
        return new Date(dateStr).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatRelative = (dateStr) => {
        if (!dateStr) return 'Never contacted';
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffHours / 24);

        if (diffHours < 1) return 'Just now';
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 30) return `${diffDays} days ago`;
        return date.toLocaleDateString();
    };

    // Pre-composed WhatsApp message templates for real estate / Bamcom CRM
    const waTemplates = [
        {
            title: 'Welcome & Portfolio Introduction',
            message: `Hello ${contact.first_name}, thank you for connecting with Bamcom AI Real Estate. We have curated prime property developments matching your investment profile. When would be convenient for a brief chat?`,
        },
        {
            title: 'Site Inspection Invitation',
            message: `Hi ${contact.first_name}, we are scheduling private site inspections for our flagship Lekki & Epe estate developments this Saturday. Would you like us to reserve an inspection slot for you?`,
        },
        {
            title: 'Payment & Allocation Follow-up',
            message: `Good day ${contact.first_name}, following up on your requested property allocation documentation. Let me know if you would like me to resend the investment brochure and payment breakdown.`,
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">
                            <Link href={route('contacts.index')} className="hover:underline flex items-center gap-1">
                                <ArrowLeft className="h-3.5 w-3.5" />
                                <span>Contacts Directory</span>
                            </Link>
                            <span>/</span>
                            <span className="text-slate-500">Contact 360 Profile</span>
                        </div>
                        <h2 className="font-bold text-2xl text-slate-900 dark:text-white leading-tight">
                            {contact.full_name}
                        </h2>
                    </div>

                    {/* Header Quick Actions */}
                    <div className="flex items-center flex-wrap gap-2.5">
                        {/* WhatsApp Click-to-Chat */}
                        {contact.whatsapp_url && (
                            <a
                                href={contact.whatsapp_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-bold rounded-xl shadow-sm transition"
                            >
                                <MessageSquare className="h-4 w-4" />
                                <span>WhatsApp Chat</span>
                            </a>
                        )}

                        {/* Telephone Call */}
                        {contact.phone && (
                            <a
                                href={`tel:${contact.phone}`}
                                className="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-xl transition"
                            >
                                <Phone className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                <span>Call</span>
                            </a>
                        )}

                        {/* Log Touchpoint */}
                        <button
                            type="button"
                            onClick={handleLogTouchpoint}
                            className="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 text-sm font-semibold rounded-xl transition"
                            title="Record current time as last contact touchpoint"
                        >
                            <Clock className="h-4 w-4" />
                            <span>Log Touchpoint</span>
                        </button>

                        {/* Edit */}
                        {can('contacts.edit') && (
                            <Link
                                href={route('contacts.edit', contact.id)}
                                className="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 text-white dark:text-slate-900 text-sm font-semibold rounded-xl transition"
                            >
                                <Edit className="h-4 w-4" />
                                <span>Edit Profile</span>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`${contact.full_name} - Contact 360 - Bamcom AI CRM`} />

            <div className="space-y-6">
                {/* Flash Notifications */}
                {flash?.success && (
                    <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}

                {/* 360 Header Profile Hero Banner */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="flex items-start sm:items-center gap-4">
                            {/* Avatar */}
                            <div className="h-16 w-16 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-black text-2xl shadow-md flex-shrink-0">
                                {contact.initials || 'C'}
                            </div>

                            <div>
                                <div className="flex items-center flex-wrap gap-2.5">
                                    <h1 className="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                                        {contact.full_name}
                                    </h1>
                                    {getStatusBadge(contact.status)}
                                    {getSourceBadge(contact.lead_source)}
                                </div>

                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                                    {contact.occupation && (
                                        <span className="flex items-center gap-1.5">
                                            <Briefcase className="h-3.5 w-3.5 text-slate-400" />
                                            {contact.occupation}
                                        </span>
                                    )}
                                    {contact.location && (
                                        <span className="flex items-center gap-1.5">
                                            <MapPin className="h-3.5 w-3.5 text-slate-400" />
                                            {contact.location}
                                        </span>
                                    )}
                                    <span className="font-mono text-[11px] bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-slate-500">
                                        UUID: {contact.uuid}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Quick Lifecycle Status Switcher */}
                        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 dark:border-slate-800">
                            <span className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Change Status:
                            </span>
                            <div className="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl">
                                {statuses?.map((s) => (
                                    <button
                                        key={s.value}
                                        type="button"
                                        onClick={() => handleStatusChange(s.value)}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition ${
                                            contact.status === s.value
                                                ? 'bg-blue-600 text-white shadow-sm'
                                                : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700'
                                        }`}
                                    >
                                        {s.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Metric Micro-Bar */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-5 border-t border-slate-100 dark:border-slate-800">
                        <div>
                            <span className="text-xs text-slate-400">Normalized Phone</span>
                            <p className="font-mono text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                                {contact.formatted_phone || contact.phone}
                            </p>
                        </div>
                        <div>
                            <span className="text-xs text-slate-400">Assigned Sales Rep</span>
                            <p className="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                                {contact.assigned_user ? contact.assigned_user.name : 'Unassigned'}
                            </p>
                        </div>
                        <div>
                            <span className="text-xs text-slate-400">Last Touchpoint</span>
                            <p className="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                                {formatRelative(contact.last_contact_at)}
                            </p>
                        </div>
                        <div>
                            <span className="text-xs text-slate-400">Language & Locale</span>
                            <p className="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200 mt-0.5 uppercase">
                                {contact.preferred_language || 'EN'} (Nigerian Market)
                            </p>
                        </div>
                    </div>
                </div>

                {/* 360 Tab Navigation */}
                <div className="flex border-b border-slate-200 dark:border-slate-800 overflow-x-auto space-x-1 sm:space-x-3">
                    <button
                        type="button"
                        onClick={() => setActiveTab('overview')}
                        className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                            activeTab === 'overview'
                                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                        }`}
                    >
                        <Contact className="h-4 w-4" />
                        <span>360 Demographics</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('whatsapp')}
                        className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                            activeTab === 'whatsapp'
                                ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                        }`}
                    >
                        <MessageSquare className="h-4 w-4" />
                        <span>WhatsApp Communications Shell</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('deals')}
                        className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                            activeTab === 'deals'
                                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                        }`}
                    >
                        <Handshake className="h-4 w-4" />
                        <span>Deals & Opportunities ({(contact.deals?.length || 0) + (contact.leads?.length || 0)})</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('inspections')}
                        className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                            activeTab === 'inspections'
                                ? 'border-amber-600 text-amber-600 dark:text-amber-400'
                                : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                        }`}
                    >
                        <Compass className="h-4 w-4" />
                        <span>Site Inspections Shell</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('activity')}
                        className={`flex items-center gap-2 py-3 px-4 text-sm font-bold border-b-2 whitespace-nowrap transition ${
                            activeTab === 'activity'
                                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                        }`}
                    >
                        <Clock className="h-4 w-4" />
                        <span>Activity & Notes Shell</span>
                    </button>
                </div>

                {/* TAB 1: 360 Demographics & Overview */}
                {activeTab === 'overview' && (
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {/* Contact Information */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                                <User className="h-4 w-4 text-blue-600" />
                                <span>Contact Details</span>
                            </h3>
                            <dl className="space-y-4 text-sm">
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">First & Last Name</dt>
                                    <dd className="font-bold text-slate-900 dark:text-white mt-0.5">{contact.full_name}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">Normalized E.164 Phone</dt>
                                    <dd className="font-mono text-sm font-semibold text-slate-900 dark:text-white mt-0.5">{contact.phone}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">Email Address</dt>
                                    <dd className="font-medium text-slate-900 dark:text-white mt-0.5">{contact.email || 'None registered'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">Location</dt>
                                    <dd className="font-medium text-slate-900 dark:text-white mt-0.5">{contact.location || 'Not specified'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">Occupation / Industry</dt>
                                    <dd className="font-medium text-slate-900 dark:text-white mt-0.5">{contact.occupation || 'Not specified'}</dd>
                                </div>
                            </dl>
                        </div>

                        {/* CRM Lifecycle Information */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                                <Sparkles className="h-4 w-4 text-indigo-600" />
                                <span>Lifecycle & Attribution</span>
                            </h3>
                            <dl className="space-y-4 text-sm">
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">Lifecycle Stage</dt>
                                    <dd className="mt-1">{getStatusBadge(contact.status)}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">Lead Acquisition Source</dt>
                                    <dd className="mt-1">{getSourceBadge(contact.lead_source)}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">System UUID</dt>
                                    <dd className="font-mono text-xs text-slate-600 dark:text-slate-400 mt-0.5 break-all">{contact.uuid}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">Date Registered</dt>
                                    <dd className="font-medium text-slate-900 dark:text-white mt-0.5">{formatDate(contact.created_at)}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400 font-medium">Last Interaction Logged</dt>
                                    <dd className="font-medium text-slate-900 dark:text-white mt-0.5">{formatDate(contact.last_contact_at)}</dd>
                                </div>
                            </dl>
                        </div>

                        {/* Assigned Representative */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                                <UsersRound className="h-4 w-4 text-emerald-600" />
                                <span>Account Ownership</span>
                            </h3>
                            {contact.assigned_user ? (
                                <div className="space-y-4 text-sm">
                                    <div className="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                        <div className="h-10 w-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold">
                                            {contact.assigned_user.name.charAt(0)}
                                        </div>
                                        <div>
                                            <p className="font-bold text-slate-900 dark:text-white">{contact.assigned_user.name}</p>
                                            <p className="text-xs text-slate-500">{contact.assigned_user.role}</p>
                                        </div>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-slate-400 font-medium">Agent Email</dt>
                                        <dd className="font-medium text-slate-900 dark:text-white mt-0.5">{contact.assigned_user.email}</dd>
                                    </div>
                                    {contact.assigned_user.team && (
                                        <div>
                                            <dt className="text-xs text-slate-400 font-medium">Primary Team</dt>
                                            <dd className="font-semibold text-blue-600 dark:text-blue-400 mt-0.5">{contact.assigned_user.team.name}</dd>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="p-6 text-center border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
                                    <p className="text-xs text-slate-500">This contact is currently unassigned.</p>
                                    {can('contacts.edit') && (
                                        <Link
                                            href={route('contacts.edit', contact.id)}
                                            className="inline-block mt-3 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg"
                                        >
                                            Assign Agent
                                        </Link>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* TAB 2: WhatsApp & Omnichannel Communications Shell */}
                {activeTab === 'whatsapp' && (
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* WhatsApp Dispatch & Conversation Shell */}
                        <div className="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-5">
                            <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                                <div className="flex items-center gap-2">
                                    <MessageSquare className="h-5 w-5 text-emerald-600" />
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        WhatsApp Web Automation Shell
                                    </h3>
                                </div>
                                <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    WhatsApp Integration Ready
                                </span>
                            </div>

                            {/* Simulated Chat Feed */}
                            <div className="bg-slate-50 dark:bg-slate-950 rounded-xl p-4 border border-slate-200 dark:border-slate-800 min-h-[220px] flex flex-col justify-end space-y-3">
                                <div className="flex items-start gap-2.5 max-w-[85%]">
                                    <div className="h-7 w-7 rounded-full bg-slate-300 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-800 dark:text-slate-200">
                                        {contact.initials}
                                    </div>
                                    <div className="bg-white dark:bg-slate-900 p-3 rounded-2xl rounded-tl-none border border-slate-200 dark:border-slate-800 shadow-sm text-xs text-slate-800 dark:text-slate-200">
                                        <p className="font-semibold text-slate-500 dark:text-slate-400 text-[10px] mb-1">{contact.full_name}</p>
                                        Hello, I'm interested in the luxury duplex listings in Lekki Phase 1. What are the payment terms?
                                        <span className="block text-[10px] text-slate-400 text-right mt-1">10:45 AM</span>
                                    </div>
                                </div>

                                <div className="flex items-start gap-2.5 max-w-[85%] self-end flex-row-reverse">
                                    <div className="h-7 w-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">
                                        AI
                                    </div>
                                    <div className="bg-blue-600 text-white p-3 rounded-2xl rounded-tr-none shadow-sm text-xs">
                                        <p className="font-semibold text-blue-200 text-[10px] mb-1">Bamcom AI Assistant</p>
                                        Hi {contact.first_name}! We offer flexible milestone payment options (30% deposit with 12 months spread). Our sales rep will connect shortly with the full brochure.
                                        <span className="flex items-center justify-end gap-1 text-[10px] text-blue-200 mt-1">
                                            10:46 AM <CheckCheck className="h-3 w-3 text-emerald-300" />
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Direct WhatsApp Launcher Button */}
                            <div className="pt-2">
                                <a
                                    href={contact.whatsapp_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="w-full flex items-center justify-center gap-2 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition"
                                >
                                    <MessageSquare className="h-4 w-4" />
                                    <span>Launch Direct WhatsApp Conversation with {contact.first_name}</span>
                                    <ExternalLink className="h-3.5 w-3.5 opacity-80" />
                                </a>
                            </div>
                        </div>

                        {/* Quick WhatsApp Templates */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Send className="h-4 w-4 text-emerald-600" />
                                <span>Quick WhatsApp Templates</span>
                            </h3>
                            <p className="text-xs text-slate-500">
                                Click any template below to open WhatsApp with this pre-filled message:
                            </p>

                            <div className="space-y-3">
                                {waTemplates.map((template, idx) => {
                                    const templateWaUrl = `https://wa.me/${contact.phone?.replace('+', '')}?text=${encodeURIComponent(template.message)}`;
                                    return (
                                        <div key={idx} className="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-2">
                                            <p className="text-xs font-bold text-slate-900 dark:text-white">{template.title}</p>
                                            <p className="text-xs text-slate-600 dark:text-slate-400 italic line-clamp-2">"{template.message}"</p>
                                            <a
                                                href={templateWaUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 hover:underline pt-1"
                                            >
                                                <span>Send via WhatsApp</span>
                                                <ExternalLink className="h-3 w-3" />
                                            </a>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                )}

                {/* TAB 3: Deals & Opportunities */}
                {activeTab === 'deals' && (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Handshake className="h-5 w-5 text-indigo-600" />
                                    <span>Sales Opportunities & Deals</span>
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Track property deals, stages, expected close dates, and win/loss records for {contact.full_name}.
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                {can('deals.create') && (
                                    <Link
                                        href={route('deals.create', { contact_id: contact.id })}
                                        className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition"
                                    >
                                        <Sparkles className="h-3.5 w-3.5" />
                                        <span>+ New Deal / Opportunity</span>
                                    </Link>
                                )}
                                {can('leads.create') && (
                                    <Link
                                        href={route('leads.create', { contact_id: contact.id })}
                                        className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition"
                                    >
                                        <span>+ Add Lead</span>
                                    </Link>
                                )}
                            </div>
                        </div>

                        {/* Deals Section */}
                        {contact.deals && contact.deals.length > 0 ? (
                            <div className="space-y-4">
                                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    Active & Historical Deals ({contact.deals.length})
                                </h4>
                                <div className="space-y-3">
                                    {contact.deals.map((deal) => (
                                        <div
                                            key={deal.id}
                                            className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-indigo-300 dark:hover:border-indigo-700 transition"
                                        >
                                            <div className="space-y-1.5 flex-1">
                                                <div className="flex items-center flex-wrap gap-2">
                                                    <Link
                                                        href={route('deals.show', deal.id)}
                                                        className="font-bold text-slate-900 dark:text-white text-sm hover:text-indigo-600 hover:underline"
                                                    >
                                                        {deal.title}
                                                    </Link>

                                                    {deal.status === 'won' && (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                            <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                                                            Won
                                                        </span>
                                                    )}
                                                    {deal.status === 'lost' && (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                                            <XCircle className="w-3 h-3 text-rose-600" />
                                                            Lost
                                                        </span>
                                                    )}
                                                    {deal.status === 'open' && (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                                                            <Clock className="w-3 h-3 text-blue-600" />
                                                            Open
                                                        </span>
                                                    )}

                                                    {deal.stage && (
                                                        <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                            {deal.stage.name}
                                                        </span>
                                                    )}
                                                </div>

                                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                                    <span className="font-bold text-indigo-600 dark:text-indigo-400">
                                                        ₦{Number(deal.deal_value || 0).toLocaleString()}
                                                    </span>
                                                    {deal.property && (
                                                        <span>
                                                            Property: <strong className="text-slate-700 dark:text-slate-300">{deal.property.title}</strong>
                                                            {deal.property.estate && (
                                                                <span className="text-slate-400"> ({deal.property.estate.name})</span>
                                                            )}
                                                        </span>
                                                    )}
                                                    {deal.assigned_user && (
                                                        <span>Rep: <strong className="text-slate-700 dark:text-slate-300">{deal.assigned_user.name}</strong></span>
                                                    )}
                                                    {deal.expected_close_date && (
                                                        <span>Expected Close: <strong className="text-slate-700 dark:text-slate-300">{deal.expected_close_date}</strong></span>
                                                    )}
                                                </div>

                                                {deal.status === 'lost' && deal.lost_reason && (
                                                    <div className="mt-1 text-xs p-2 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 text-rose-700 dark:text-rose-300">
                                                        <span className="font-semibold">Lost Reason:</span> {deal.lost_reason}
                                                    </div>
                                                )}
                                                {deal.status === 'won' && deal.actual_close_date && (
                                                    <div className="mt-1 text-xs p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/50 text-emerald-700 dark:text-emerald-300">
                                                        <span className="font-semibold">Closed Won on:</span> {deal.actual_close_date}
                                                    </div>
                                                )}
                                            </div>

                                            <Link
                                                href={route('deals.show', deal.id)}
                                                className="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex-shrink-0"
                                            >
                                                <span>View Deal</span>
                                                <ChevronRight className="h-3.5 w-3.5" />
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : null}

                        {/* Leads Sub-Section */}
                        {contact.leads && contact.leads.length > 0 && (
                            <div className="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    Associated Leads ({contact.leads.length})
                                </h4>
                                <div className="space-y-3">
                                    {contact.leads.map((lead) => (
                                        <div
                                            key={lead.id}
                                            className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-indigo-300 dark:hover:border-indigo-700 transition"
                                        >
                                            <div className="space-y-1">
                                                <div className="flex items-center flex-wrap gap-2">
                                                    <Link
                                                        href={route('leads.show', lead.id)}
                                                        className="font-bold text-slate-900 dark:text-white text-sm hover:text-indigo-600 hover:underline"
                                                    >
                                                        {lead.title}
                                                    </Link>
                                                    {lead.temperature === 'hot' && (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                                                            <Flame className="h-3 w-3 text-red-600" />
                                                            HOT
                                                        </span>
                                                    )}
                                                    {lead.temperature === 'warm' && (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                                                            WARM
                                                        </span>
                                                    )}
                                                    <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300 capitalize">
                                                        {lead.status?.replace('_', ' ')}
                                                    </span>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                                    {lead.property_interest && (
                                                        <span>Property: <strong className="text-slate-700 dark:text-slate-300">{lead.property_interest}</strong></span>
                                                    )}
                                                    {lead.formatted_budget && (
                                                        <span>Budget: <strong className="text-slate-700 dark:text-slate-300">{lead.formatted_budget}</strong></span>
                                                    )}
                                                    {lead.assigned_user && (
                                                        <span>Agent: <strong className="text-slate-700 dark:text-slate-300">{lead.assigned_user.name}</strong></span>
                                                    )}
                                                    <span>Score: <strong className="text-blue-600 dark:text-blue-400">{lead.score}/100</strong></span>
                                                </div>
                                            </div>

                                            <Link
                                                href={route('leads.show', lead.id)}
                                                className="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex-shrink-0"
                                            >
                                                <span>Manage Lead</span>
                                                <ChevronRight className="h-3.5 w-3.5" />
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {(!contact.deals || contact.deals.length === 0) && (!contact.leads || contact.leads.length === 0) && (
                            <div className="p-8 text-center rounded-xl border border-dashed border-slate-200 dark:border-slate-800 space-y-3">
                                <Handshake className="h-8 w-8 text-slate-400 mx-auto" />
                                <h4 className="text-sm font-bold text-slate-900 dark:text-white">
                                    No sales opportunities or deals recorded yet
                                </h4>
                                <p className="text-xs text-slate-500 max-w-sm mx-auto">
                                    Create a deal or lead to track property interest, budget, timeline, and close status for {contact.full_name}.
                                </p>
                                {can('deals.create') && (
                                    <Link
                                        href={route('deals.create', { contact_id: contact.id })}
                                        className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition"
                                    >
                                        <Sparkles className="h-4 w-4" />
                                        <span>Create First Opportunity</span>
                                    </Link>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {/* TAB 4: Site Inspections Shell */}
                {activeTab === 'inspections' && (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Compass className="h-5 w-5 text-amber-600" />
                                    <span>Field Inspections & Site Visits Shell</span>
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Physical site visits scheduled by {contact.full_name}.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-xl transition"
                                onClick={() => alert('Inspection booking modal shell!')}
                            >
                                + Book Site Inspection
                            </button>
                        </div>

                        <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="font-bold text-slate-900 dark:text-white text-sm">
                                        Epe Royal Waterfront Site Tour
                                    </span>
                                    <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                        Scheduled
                                    </span>
                                </div>
                                <p className="text-xs text-slate-500 mt-1">
                                    Assigned Inspection Officer: <strong className="text-slate-700 dark:text-slate-300">Ibrahim Musa</strong> • Date: Upcoming Saturday 10:00 AM
                                </p>
                            </div>
                            <span className="text-xs text-emerald-600 font-semibold flex items-center gap-1">
                                <CheckCircle2 className="h-3.5 w-3.5" />
                                Inspection Approved
                            </span>
                        </div>
                    </div>
                )}

                {/* TAB 5: Activity & Notes Shell */}
                {activeTab === 'activity' && (
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Note Entry Form */}
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <FileText className="h-4 w-4 text-blue-600" />
                                <span>Log CRM Activity / Note</span>
                            </h3>
                            <form onSubmit={handleAddNote} className="space-y-3">
                                <textarea
                                    rows="4"
                                    value={noteText}
                                    onChange={(e) => setNoteText(e.target.value)}
                                    placeholder="Enter details of conversation, requirements, or next steps..."
                                    className="w-full p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                <button
                                    type="submit"
                                    className="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition"
                                >
                                    Record Note
                                </button>
                            </form>
                        </div>

                        {/* Activity Timeline */}
                        <div className="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Clock className="h-4 w-4 text-blue-600" />
                                <span>Interaction History & Audit Log</span>
                            </h3>

                            <div className="space-y-4">
                                {notesList.map((note) => (
                                    <div key={note.id} className="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-1.5">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="font-bold text-slate-900 dark:text-white">{note.author}</span>
                                            <span className="text-[11px] text-slate-400">{formatDate(note.created_at)}</span>
                                        </div>
                                        <p className="text-xs text-slate-700 dark:text-slate-300">{note.content}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
