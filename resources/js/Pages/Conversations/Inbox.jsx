import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { 
    Search, 
    Filter, 
    MessageSquare, 
    Bot, 
    User, 
    Sparkles, 
    Flame, 
    Check, 
    CheckCheck, 
    Clock, 
    AlertCircle, 
    Phone, 
    Mail, 
    MapPin, 
    Briefcase, 
    Calendar, 
    Building2, 
    UserCheck, 
    Send, 
    Paperclip, 
    FileText, 
    Eye, 
    ChevronLeft, 
    ChevronRight, 
    X, 
    Plus, 
    Layers, 
    DollarSign, 
    Tag, 
    ExternalLink,
    RefreshCw,
    SlidersHorizontal,
    Compass
} from 'lucide-react';
import { useState, useEffect, useRef } from 'react';

export default function Inbox({ 
    conversations, 
    activeConversation, 
    filters, 
    counts, 
    users = [], 
    templates = [], 
    estates = [],
    modes = [],
    statuses = []
}) {
    const { auth } = usePage().props;
    const currentUser = auth.user;

    // Filters and Search State
    const [currentTab, setCurrentTab] = useState(filters.tab || 'all');
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    // Responsive Mobile Views: 'list' | 'chat' | 'profile'
    const [mobileView, setMobileView] = useState(activeConversation ? 'chat' : 'list');
    const [isRightSidebarOpen, setIsRightSidebarOpen] = useState(true);

    // Modals
    const [isTemplateModalOpen, setIsTemplateModalOpen] = useState(false);
    const [selectedTemplate, setSelectedTemplate] = useState(null);
    const [templateParams, setTemplateParams] = useState({});

    const [isInspectionModalOpen, setIsInspectionModalOpen] = useState(false);

    // Message Input Form
    const { data: messageData, setData: setMessageData, post: postMessage, processing: sendingMessage, reset: resetMessage } = useForm({
        body: '',
        type: 'text',
        template_name: '',
        template_parameters: [],
    });

    // Inspection Scheduling Form
    const { data: inspectionData, setData: setInspectionData, post: postInspection, processing: schedulingInspection, reset: resetInspection, errors: inspectionErrors } = useForm({
        estate_name: estates[0]?.name || '',
        inspection_date: '',
        inspection_time: '10:00 AM',
        inspector_id: currentUser.id,
        notes: '',
    });

    // Auto-scroll message feed to bottom
    const messagesEndRef = useRef(null);
    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [activeConversation?.messages]);

    // Handle filter tabs
    const handleTabChange = (tabKey) => {
        setCurrentTab(tabKey);
        router.get(route('conversations.inbox'), {
            tab: tabKey,
            search: searchTerm || undefined,
            conversation_id: activeConversation?.id,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Handle search input
    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('conversations.inbox'), {
            tab: currentTab,
            search: searchTerm || undefined,
            conversation_id: activeConversation?.id,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Select Conversation
    const handleSelectConversation = (conv) => {
        router.get(route('conversations.inbox'), {
            tab: currentTab,
            search: searchTerm || undefined,
            conversation_id: conv.id,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setMobileView('chat');
            }
        });
    };

    // Send Outbound Message
    const handleSendMessage = (e) => {
        e?.preventDefault();
        if (!messageData.body.trim() || sendingMessage || !activeConversation) return;

        postMessage(route('inbox.messages.store', activeConversation.id), {
            preserveScroll: true,
            onSuccess: () => {
                resetMessage();
                scrollToBottom();
            },
        });
    };

    // Send Pre-Approved WhatsApp Template
    const handleSendTemplate = (e) => {
        e.preventDefault();
        if (!selectedTemplate || !activeConversation) return;

        const paramValues = Object.values(templateParams);

        router.post(route('inbox.messages.store', activeConversation.id), {
            body: selectedTemplate.body_text || `Template: ${selectedTemplate.name}`,
            type: 'template',
            template_name: selectedTemplate.name,
            template_parameters: paramValues,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsTemplateModalOpen(false);
                setSelectedTemplate(null);
                setTemplateParams({});
                scrollToBottom();
            }
        });
    };

    // Quick Update Mode
    const handleUpdateMode = (newMode) => {
        if (!activeConversation) return;
        router.patch(route('inbox.mode', activeConversation.id), {
            mode: newMode,
        }, {
            preserveScroll: true,
        });
    };

    // Quick Update Status
    const handleUpdateStatus = (newStatus) => {
        if (!activeConversation) return;
        router.patch(route('inbox.status', activeConversation.id), {
            status: newStatus,
        }, {
            preserveScroll: true,
        });
    };

    // Quick Assign Agent
    const handleAssign = (userId) => {
        if (!activeConversation) return;
        router.patch(route('inbox.assign', activeConversation.id), {
            assigned_user_id: userId ? parseInt(userId, 10) : null,
        }, {
            preserveScroll: true,
        });
    };

    // Mark as Read
    const handleMarkRead = () => {
        if (!activeConversation) return;
        router.post(route('inbox.read', activeConversation.id), {}, {
            preserveScroll: true,
        });
    };

    // Schedule Inspection
    const handleScheduleInspection = (e) => {
        e.preventDefault();
        if (!activeConversation) return;

        postInspection(route('inbox.inspections.store', activeConversation.id), {
            preserveScroll: true,
            onSuccess: () => {
                setIsInspectionModalOpen(false);
                resetInspection();
            },
        });
    };

    // Helpers
    const formatTime = (dateString) => {
        if (!dateString) return '';
        const d = new Date(dateString);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const formatRelativeTime = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    };

    // Delivery Checkmark Icon
    const renderDeliveryStatus = (status) => {
        switch (status) {
            case 'read':
                return <CheckCheck className="w-3.5 h-3.5 text-blue-500 inline-block" title="Read" />;
            case 'delivered':
                return <CheckCheck className="w-3.5 h-3.5 text-slate-400 inline-block" title="Delivered" />;
            case 'sent':
            case 'received':
                return <Check className="w-3.5 h-3.5 text-slate-400 inline-block" title="Sent" />;
            case 'failed':
                return <AlertCircle className="w-3.5 h-3.5 text-rose-500 inline-block" title="Failed to deliver" />;
            default:
                return null;
        }
    };

    // Active Contact details
    const activeContact = activeConversation?.contact;
    const activeLead = activeContact?.leads?.[0];
    const activeDeals = activeContact?.deals || [];
    const activities = activeContact?.leads?.flatMap(l => l.activities || []) || [];

    // Inspections scheduled
    const scheduledInspections = activities.filter(a => a.activity_type === 'inspection_scheduled');

    // Filter pills definition
    const filterTabs = [
        { key: 'all', label: 'All', count: counts.all, icon: Layers },
        { key: 'mine', label: 'Mine', count: counts.mine, icon: User },
        { key: 'unassigned', label: 'Unassigned', count: counts.unassigned, icon: SlidersHorizontal },
        { key: 'unread', label: 'Unread', count: counts.unread, icon: MessageSquare },
        { key: 'ai', label: 'AI', count: counts.ai, icon: Bot },
        { key: 'human', label: 'Human', count: counts.human, icon: UserCheck },
        { key: 'hybrid', label: 'Hybrid', count: counts.hybrid, icon: Sparkles },
        { key: 'hot_leads', label: 'Hot Leads', count: counts.hot_leads, icon: Flame },
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between w-full">
                    <div className="flex items-center gap-3">
                        <div className="h-9 w-9 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                            <MessageSquare className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="font-bold text-lg text-slate-900 dark:text-white leading-tight">
                                WhatsApp Team Inbox
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Real-time customer messaging, CRM pipeline context, and team collaboration
                            </p>
                        </div>
                    </div>

                    <div className="hidden sm:flex items-center gap-2">
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Meta Cloud API Active
                        </span>
                    </div>
                </div>
            }
        >
            <Head title="WhatsApp Team Inbox - Bamcom CRM" />

            {/* Main Three-Column Container */}
            <div className="h-[calc(100vh-14rem)] min-h-[600px] flex overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm transition-colors">
                
                {/* ========================================================= */}
                {/* 1. LEFT COLUMN: CONVERSATION LIST & FILTERS              */}
                {/* ========================================================= */}
                <div className={`
                    w-full lg:w-80 xl:w-96 flex flex-col border-r border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40 shrink-0
                    ${mobileView === 'list' ? 'flex' : 'hidden lg:flex'}
                `}>
                    {/* Search & Actions Header */}
                    <div className="p-3 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                        <form onSubmit={handleSearch} className="relative">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                placeholder="Search by name, phone, subject..."
                                className="w-full pl-9 pr-8 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                            />
                            {searchTerm && (
                                <button
                                    type="button"
                                    onClick={() => { setSearchTerm(''); router.get(route('conversations.inbox'), { tab: currentTab }); }}
                                    className="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </form>

                        {/* Filter Tabs Grid */}
                        <div className="grid grid-cols-4 gap-1 mt-2.5">
                            {filterTabs.map(tab => {
                                const Icon = tab.icon;
                                const isActive = currentTab === tab.key;
                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => handleTabChange(tab.key)}
                                        className={`
                                            flex flex-col items-center justify-center py-1.5 px-1 rounded-lg text-[10px] font-medium transition-all
                                            ${isActive 
                                                ? 'bg-emerald-600 text-white shadow-sm' 
                                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800'}
                                        `}
                                        title={`${tab.label} (${tab.count})`}
                                    >
                                        <div className="flex items-center gap-1">
                                            <Icon className="h-3 w-3" />
                                            <span className="font-semibold">{tab.count}</span>
                                        </div>
                                        <span className="truncate w-full text-center mt-0.5">{tab.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Conversations Scrollable List */}
                    <div className="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60">
                        {conversations.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center p-8 text-center text-slate-400">
                                <MessageSquare className="h-8 w-8 mb-2 stroke-[1.5] text-slate-300 dark:text-slate-600" />
                                <p className="text-xs font-medium text-slate-600 dark:text-slate-300">No conversations found</p>
                                <p className="text-[11px] text-slate-400 mt-1">Try switching filter tabs or clearing your search.</p>
                            </div>
                        ) : (
                            conversations.data.map(conv => {
                                const isSelected = activeConversation?.id === conv.id;
                                const contact = conv.contact;
                                const isHot = contact?.leads?.some(l => l.score >= 70 || l.temperature === 'hot');
                                const latestMsg = conv.latest_message;

                                return (
                                    <div
                                        key={conv.id}
                                        onClick={() => handleSelectConversation(conv)}
                                        className={`
                                            p-3 cursor-pointer transition-colors relative
                                            ${isSelected 
                                                ? 'bg-emerald-50/80 dark:bg-emerald-950/30 border-l-4 border-emerald-500' 
                                                : 'hover:bg-slate-100/70 dark:hover:bg-slate-800/40'}
                                        `}
                                    >
                                        <div className="flex items-start gap-2.5">
                                            {/* Avatar with Initials */}
                                            <div className="relative shrink-0">
                                                <div className="w-10 h-10 rounded-full bg-gradient-to-tr from-slate-700 to-slate-900 text-white flex items-center justify-center font-bold text-xs uppercase shadow-xs">
                                                    {contact?.initials || (contact?.first_name ? contact.first_name[0] : 'W')}
                                                </div>
                                                {/* Mode Status Dot */}
                                                <span 
                                                    className={`absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full border-2 border-white dark:border-slate-900 flex items-center justify-center text-[8px] font-bold text-white ${
                                                        conv.mode === 'ai' ? 'bg-purple-600' : conv.mode === 'human' ? 'bg-blue-600' : 'bg-emerald-600'
                                                    }`}
                                                    title={`Mode: ${conv.mode.toUpperCase()}`}
                                                >
                                                    {conv.mode === 'ai' ? 'A' : conv.mode === 'human' ? 'H' : 'M'}
                                                </span>
                                            </div>

                                            {/* Details Header */}
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center justify-between gap-1 mb-0.5">
                                                    <div className="flex items-center gap-1.5 truncate">
                                                        <span className="font-semibold text-xs text-slate-900 dark:text-white truncate">
                                                            {contact?.full_name || conv.subject || contact?.phone || 'WhatsApp Visitor'}
                                                        </span>
                                                        {isHot && (
                                                            <Flame className="h-3.5 w-3.5 text-rose-500 shrink-0 animate-bounce" title="Hot Lead (Score >= 70)" />
                                                        )}
                                                    </div>
                                                    <span className="text-[10px] text-slate-400 shrink-0">
                                                        {formatRelativeTime(conv.last_message_at || conv.created_at)}
                                                    </span>
                                                </div>

                                                {/* Last Message Snippet */}
                                                <p className="text-[11px] text-slate-500 dark:text-slate-400 truncate mb-1.5 leading-snug">
                                                    {latestMsg?.body || 'New conversation initiated'}
                                                </p>

                                                {/* Badges / Status row */}
                                                <div className="flex items-center justify-between text-[10px]">
                                                    <div className="flex items-center gap-1">
                                                        <span className={`px-1.5 py-0.5 rounded text-[9px] font-medium uppercase tracking-wider ${
                                                            conv.status === 'open' 
                                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' 
                                                                : conv.status === 'pending'
                                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                                                : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
                                                        }`}>
                                                            {conv.status}
                                                        </span>

                                                        <span className="text-slate-400 truncate max-w-[90px]">
                                                            {conv.assigned_user ? conv.assigned_user.name : 'Unassigned'}
                                                        </span>
                                                    </div>

                                                    {/* Unread Counter Pill */}
                                                    {conv.unread_count > 0 && (
                                                        <span className="h-4 min-w-[16px] px-1 rounded-full bg-emerald-500 text-white font-bold text-[9px] flex items-center justify-center">
                                                            {conv.unread_count}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>

                {/* ========================================================= */}
                {/* 2. CENTER COLUMN: MESSAGE HISTORY & COMPOSER             */}
                {/* ========================================================= */}
                <div className={`
                    flex-1 flex flex-col bg-white dark:bg-slate-900 overflow-hidden
                    ${mobileView === 'chat' ? 'flex' : 'hidden lg:flex'}
                `}>
                    {activeConversation ? (
                        <>
                            {/* Chat Header Bar */}
                            <div className="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-white/90 dark:bg-slate-900/90 backdrop-blur-xs z-10 shrink-0">
                                <div className="flex items-center gap-3 min-w-0">
                                    {/* Mobile Back to List Button */}
                                    <button
                                        type="button"
                                        onClick={() => setMobileView('list')}
                                        className="lg:hidden p-1.5 -ml-1 text-slate-500 hover:text-slate-900 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
                                    >
                                        <ChevronLeft className="h-5 w-5" />
                                    </button>

                                    {/* Contact Avatar & Basic Info */}
                                    <div className="w-9 h-9 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center uppercase shrink-0 shadow-xs">
                                        {activeContact?.initials || 'WA'}
                                    </div>
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <h3 className="font-bold text-sm text-slate-900 dark:text-white truncate">
                                                {activeContact?.full_name || activeConversation.subject || activeContact?.phone}
                                            </h3>
                                            <span className="hidden sm:inline-flex text-xs text-slate-400 font-mono">
                                                {activeContact?.formatted_phone || activeContact?.phone}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-[11px] text-slate-400">
                                            <span>WhatsApp Business</span>
                                            <span>&bull;</span>
                                            <span>Last active {formatRelativeTime(activeConversation.last_message_at)}</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Controls: Mode, Status, Assignee, Info Toggle */}
                                <div className="flex items-center gap-2">
                                    {/* Mode Switcher Pill */}
                                    <div className="hidden sm:flex items-center bg-slate-100 dark:bg-slate-800 rounded-lg p-0.5 border border-slate-200 dark:border-slate-700">
                                        {['ai', 'hybrid', 'human'].map(modeKey => (
                                            <button
                                                key={modeKey}
                                                type="button"
                                                onClick={() => handleUpdateMode(modeKey)}
                                                className={`
                                                    px-2 py-1 rounded-md text-[10px] font-semibold uppercase tracking-wider transition-all
                                                    ${activeConversation.mode === modeKey
                                                        ? modeKey === 'ai' ? 'bg-purple-600 text-white' : modeKey === 'hybrid' ? 'bg-emerald-600 text-white' : 'bg-blue-600 text-white'
                                                        : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'}
                                                `}
                                            >
                                                {modeKey}
                                            </button>
                                        ))}
                                    </div>

                                    {/* Status Switcher Dropdown */}
                                    <select
                                        value={activeConversation.status}
                                        onChange={(e) => handleUpdateStatus(e.target.value)}
                                        className="text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white py-1 px-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    >
                                        <option value="open">Open</option>
                                        <option value="pending">Pending</option>
                                        <option value="closed">Closed</option>
                                    </select>

                                    {/* Mark as Read Button */}
                                    {activeConversation.unread_count > 0 && (
                                        <button
                                            type="button"
                                            onClick={handleMarkRead}
                                            className="px-2 py-1 text-xs font-medium rounded-lg bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 transition"
                                            title="Mark all as read"
                                        >
                                            Mark Read
                                        </button>
                                    )}

                                    {/* Toggle Right Panel (Contact Profile) Button */}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (window.innerWidth < 1024) {
                                                setMobileView('profile');
                                            } else {
                                                setIsRightSidebarOpen(!isRightSidebarOpen);
                                            }
                                        }}
                                        className={`
                                            p-1.5 rounded-lg border transition-colors
                                            ${isRightSidebarOpen 
                                                ? 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white border-slate-300 dark:border-slate-700' 
                                                : 'text-slate-500 hover:text-slate-900 dark:hover:text-white border-transparent hover:bg-slate-100 dark:hover:bg-slate-800'}
                                        `}
                                        title="Toggle CRM Contact Details"
                                    >
                                        <User className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            {/* Conversation Message Stream */}
                            <div className="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/50 dark:bg-slate-950/20">
                                {activeConversation.messages?.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center h-full text-center text-slate-400 py-12">
                                        <div className="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-2">
                                            <MessageSquare className="h-6 w-6 text-slate-400" />
                                        </div>
                                        <p className="text-sm font-semibold text-slate-700 dark:text-slate-300">No messages in this conversation yet</p>
                                        <p className="text-xs text-slate-400 mt-1 max-w-sm">
                                            Use the composer below to send a WhatsApp message or select a pre-approved Meta template.
                                        </p>
                                    </div>
                                ) : (
                                    activeConversation.messages.map(msg => {
                                        const isInbound = msg.direction === 'inbound';

                                        return (
                                            <div 
                                                key={msg.id}
                                                className={`flex items-end gap-2 ${isInbound ? 'justify-start' : 'justify-end'}`}
                                            >
                                                {/* Left avatar for inbound */}
                                                {isInbound && (
                                                    <div className="w-7 h-7 rounded-full bg-slate-700 text-white font-bold text-[10px] flex items-center justify-center shrink-0">
                                                        {activeContact?.initials || 'C'}
                                                    </div>
                                                )}

                                                {/* Bubble Container */}
                                                <div className={`
                                                    max-w-[78%] sm:max-w-[68%] rounded-2xl px-3.5 py-2.5 shadow-xs relative text-xs leading-relaxed
                                                    ${isInbound 
                                                        ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-bl-xs border border-slate-200/80 dark:border-slate-700/80' 
                                                        : 'bg-emerald-600 text-white rounded-br-xs'}
                                                `}>
                                                    {/* Sender Label for Outbound */}
                                                    {!isInbound && (
                                                        <div className="text-[10px] font-semibold text-emerald-100 mb-0.5 flex items-center justify-between gap-2">
                                                            <span>{msg.sender_type === 'user' ? 'Agent' : 'AI System'}</span>
                                                            {msg.type === 'template' && (
                                                                <span className="bg-emerald-700/80 px-1 py-0.2 rounded text-[8px] uppercase">
                                                                    Template
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}

                                                    {/* Message Body */}
                                                    <div className="whitespace-pre-wrap break-words font-normal">
                                                        {msg.body}
                                                    </div>

                                                    {/* Media Metadata Previews */}
                                                    {msg.media_url && (
                                                        <div className="mt-2 rounded-lg overflow-hidden border border-black/10">
                                                            {msg.media_mime_type?.startsWith('image/') ? (
                                                                <img src={msg.media_url} alt="Attached media" className="max-h-48 rounded object-cover" />
                                                            ) : (
                                                                <a 
                                                                    href={msg.media_url} 
                                                                    target="_blank" 
                                                                    rel="noreferrer"
                                                                    className="flex items-center gap-2 p-2 bg-black/10 hover:bg-black/20 text-current text-[11px]"
                                                                >
                                                                    <FileText className="h-4 w-4" />
                                                                    <span>View Attachment</span>
                                                                </a>
                                                            )}
                                                        </div>
                                                    )}

                                                    {/* Message Footer: Timestamp and Delivery status */}
                                                    <div className={`
                                                        flex items-center justify-end gap-1 mt-1 text-[9px]
                                                        ${isInbound ? 'text-slate-400' : 'text-emerald-200'}
                                                    `}>
                                                        <span>{formatTime(msg.created_at)}</span>
                                                        {!isInbound && renderDeliveryStatus(msg.delivery_status)}
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                                <div ref={messagesEndRef} />
                            </div>

                            {/* Reply Composer Bar */}
                            <div className="p-3 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0">
                                <form onSubmit={handleSendMessage} className="space-y-2">
                                    <div className="relative">
                                        <textarea
                                            rows={2}
                                            value={messageData.body}
                                            onChange={(e) => setMessageData('body', e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                                                    e.preventDefault();
                                                    handleSendMessage();
                                                }
                                            }}
                                            placeholder={`Type a WhatsApp message to ${activeContact?.first_name || 'contact'}... (Ctrl+Enter to send)`}
                                            className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white p-3 pr-24 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition resize-none"
                                        />

                                        {/* Action buttons inside composer */}
                                        <div className="absolute right-2.5 bottom-3 flex items-center gap-1.5">
                                            {/* Pre-Approved WhatsApp Template Selector */}
                                            <button
                                                type="button"
                                                onClick={() => setIsTemplateModalOpen(true)}
                                                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-200/50 dark:hover:bg-slate-700 transition"
                                                title="Send Pre-Approved WhatsApp Template"
                                            >
                                                <Layers className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                            </button>

                                            {/* Send Button */}
                                            <button
                                                type="submit"
                                                disabled={!messageData.body.trim() || sendingMessage}
                                                className={`
                                                    p-2 rounded-lg text-white font-semibold transition flex items-center justify-center
                                                    ${messageData.body.trim() && !sendingMessage
                                                        ? 'bg-emerald-600 hover:bg-emerald-700 shadow-sm' 
                                                        : 'bg-slate-300 dark:bg-slate-700 text-slate-500 cursor-not-allowed'}
                                                `}
                                            >
                                                <Send className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    {/* Composer Guidance notice */}
                                    <div className="flex items-center justify-between text-[11px] text-slate-400 px-1">
                                        <div className="flex items-center gap-1.5">
                                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Meta Cloud API Official Channel</span>
                                        </div>
                                        <span>Press <kbd className="font-mono bg-slate-100 dark:bg-slate-800 px-1 rounded text-[10px]">Ctrl+Enter</kbd> to send</span>
                                    </div>
                                </form>
                            </div>
                        </>
                    ) : (
                        <div className="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400">
                            <MessageSquare className="h-12 w-12 text-slate-300 dark:text-slate-700 mb-3 stroke-1" />
                            <h3 className="text-sm font-bold text-slate-700 dark:text-slate-200">No active conversation selected</h3>
                            <p className="text-xs text-slate-400 mt-1 max-w-sm">
                                Choose a conversation thread from the left column to view the chat history, CRM contact profile, and lead opportunities.
                            </p>
                        </div>
                    )}
                </div>

                {/* ========================================================= */}
                {/* 3. RIGHT COLUMN: CRM CONTACT PROFILE, LEAD SCORE, ETC.  */}
                {/* ========================================================= */}
                {activeConversation && (
                    <div className={`
                        w-full lg:w-80 xl:w-96 flex flex-col border-l border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0 overflow-y-auto
                        ${isRightSidebarOpen ? (mobileView === 'profile' ? 'flex' : 'hidden xl:flex') : 'hidden'}
                    `}>
                        {/* Mobile Back to Chat */}
                        <div className="lg:hidden p-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <button
                                type="button"
                                onClick={() => setMobileView('chat')}
                                className="flex items-center gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                            >
                                <ChevronLeft className="h-4 w-4" />
                                <span>Back to Chat</span>
                            </button>
                            <span className="text-xs font-bold text-slate-900 dark:text-white">CRM Details</span>
                        </div>

                        <div className="p-4 space-y-5">
                            {/* 1. Contact Profile Card */}
                            <div className="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                                <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white font-bold text-base flex items-center justify-center shadow-md">
                                    {activeContact?.initials || 'C'}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <h4 className="font-bold text-sm text-slate-900 dark:text-white truncate">
                                        {activeContact?.full_name || 'Visitor Contact'}
                                    </h4>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 truncate">
                                        {activeContact?.occupation || 'Prospective Buyer'}
                                    </p>
                                    <div className="flex items-center gap-2 mt-1">
                                        <a 
                                            href={`https://wa.me/${activeContact?.phone?.replace(/\+/g, '')}`}
                                            target="_blank" 
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 text-[10px] text-emerald-600 hover:underline font-semibold"
                                        >
                                            <Phone className="h-3 w-3" />
                                            WhatsApp
                                        </a>
                                        {activeContact?.email && (
                                            <a 
                                                href={`mailto:${activeContact.email}`}
                                                className="inline-flex items-center gap-1 text-[10px] text-blue-600 hover:underline"
                                            >
                                                <Mail className="h-3 w-3" />
                                                Email
                                            </a>
                                        )}
                                        {activeContact?.id && (
                                            <Link 
                                                href={route('contacts.show', activeContact.id)}
                                                className="inline-flex items-center gap-1 text-[10px] text-slate-500 hover:underline ml-auto"
                                            >
                                                CRM Profile <ExternalLink className="h-2.5 w-2.5" />
                                            </Link>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* 2. Lead Score & Temperature Gauge */}
                            <div className="bg-slate-50 dark:bg-slate-800/60 p-3.5 rounded-xl border border-slate-200/70 dark:border-slate-700/60">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                        Lead Score & Intent
                                    </span>
                                    {activeLead?.is_hot ? (
                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-900">
                                            <Flame className="h-3 w-3 text-rose-500" /> HOT LEAD
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                            {activeLead?.temperature?.toUpperCase() || 'WARM'}
                                        </span>
                                    )}
                                </div>

                                {/* Score Meter */}
                                <div className="space-y-1">
                                    <div className="flex justify-between text-xs font-semibold text-slate-900 dark:text-white">
                                        <span>Score Indicator</span>
                                        <span>{activeLead?.score || 50}/100</span>
                                    </div>
                                    <div className="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                                        <div 
                                            className={`h-2 rounded-full transition-all duration-500 ${
                                                (activeLead?.score || 50) >= 70 ? 'bg-rose-500' : (activeLead?.score || 50) >= 40 ? 'bg-amber-500' : 'bg-blue-500'
                                            }`} 
                                            style={{ width: `${activeLead?.score || 50}%` }}
                                        />
                                    </div>
                                </div>

                                {/* Key Buying Attributes */}
                                <div className="grid grid-cols-2 gap-2 mt-3 pt-2.5 border-t border-slate-200/60 dark:border-slate-700/60 text-[11px]">
                                    <div>
                                        <span className="text-slate-400 block text-[10px]">Timeline:</span>
                                        <span className="font-semibold text-slate-800 dark:text-slate-200">
                                            {activeLead?.purchase_timeline ? activeLead.purchase_timeline.replace(/_/g, ' ') : 'Immediate (1-3 mos)'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-slate-400 block text-[10px]">Budget:</span>
                                        <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                            {activeLead?.formatted_budget || '₦15M - ₦35M'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* 3. Property Interest */}
                            <div className="space-y-2">
                                <span className="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider block">
                                    Property Interest
                                </span>
                                <div className="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
                                    <div className="flex items-start gap-2.5">
                                        <div className="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-600 shrink-0">
                                            <Building2 className="h-4 w-4" />
                                        </div>
                                        <div className="min-w-0 flex-1 text-xs">
                                            <p className="font-bold text-slate-900 dark:text-white truncate">
                                                {activeLead?.property?.title || activeLead?.property_interest || 'Epe Waterfront Residential Plots'}
                                            </p>
                                            <p className="text-slate-500 text-[11px] flex items-center gap-1 mt-0.5">
                                                <MapPin className="h-3 w-3" />
                                                {activeLead?.preferred_location || 'Epe, Lagos State'}
                                            </p>
                                            <p className="text-emerald-600 dark:text-emerald-400 font-semibold text-[11px] mt-1">
                                                Plot Size: {activeLead?.property?.plot_size || '500 SQM'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* 4. Assigned Representative */}
                            <div className="space-y-2">
                                <span className="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider block">
                                    Assigned Representative
                                </span>
                                <div className="flex items-center gap-2">
                                    <select
                                        value={activeConversation.assigned_user_id || ''}
                                        onChange={(e) => handleAssign(e.target.value)}
                                        className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white py-2 px-3 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 font-medium"
                                    >
                                        <option value="">-- Unassigned --</option>
                                        {users.map(u => (
                                            <option key={u.id} value={u.id}>
                                                {u.name} ({u.role?.toUpperCase() || 'SALES'})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* 5. Tasks & Field Inspections */}
                            <div className="space-y-2.5 pt-2 border-t border-slate-100 dark:border-slate-800">
                                <div className="flex items-center justify-between">
                                    <span className="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                        Site Inspections ({scheduledInspections.length})
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setIsInspectionModalOpen(true)}
                                        className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 hover:underline"
                                    >
                                        <Plus className="h-3 w-3" /> Schedule
                                    </button>
                                </div>

                                {scheduledInspections.length === 0 ? (
                                    <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/60 text-center">
                                        <Calendar className="h-5 w-5 text-slate-400 mx-auto mb-1 stroke-1" />
                                        <p className="text-[11px] text-slate-500">No inspections scheduled yet</p>
                                        <button
                                            type="button"
                                            onClick={() => setIsInspectionModalOpen(true)}
                                            className="mt-1 text-[10px] font-semibold text-emerald-600 hover:underline"
                                        >
                                            Book Field Inspection
                                        </button>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {scheduledInspections.map(insp => (
                                            <div 
                                                key={insp.id}
                                                className="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-xs"
                                            >
                                                <div className="flex items-center justify-between font-semibold text-slate-900 dark:text-white">
                                                    <span>{insp.properties?.estate_name || 'Bamcom Estate'}</span>
                                                    <span className="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
                                                        {insp.properties?.status || 'Scheduled'}
                                                    </span>
                                                </div>
                                                <div className="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                                                    <Calendar className="h-3 w-3" />
                                                    {insp.properties?.inspection_date} at {insp.properties?.inspection_time}
                                                </div>
                                                {insp.properties?.notes && (
                                                    <p className="text-[10px] text-slate-400 mt-1 italic">
                                                        "{insp.properties.notes}"
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* 6. Associated Opportunities / Deals */}
                            {activeDeals.length > 0 && (
                                <div className="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                                    <span className="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider block">
                                        Active Deals ({activeDeals.length})
                                    </span>
                                    <div className="space-y-1.5">
                                        {activeDeals.map(deal => (
                                            <div key={deal.id} className="p-2 rounded-lg border border-slate-200 dark:border-slate-800 text-xs flex items-center justify-between">
                                                <div>
                                                    <p className="font-semibold text-slate-900 dark:text-white">{deal.title}</p>
                                                    <p className="text-[10px] text-slate-400">Stage: {deal.stage?.name || 'Qualification'}</p>
                                                </div>
                                                <span className="font-bold text-emerald-600 dark:text-emerald-400 text-xs">
                                                    ₦{Number(deal.deal_value || 0).toLocaleString()}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* ========================================================= */}
            {/* TEMPLATE PICKER MODAL                                     */}
            {/* ========================================================= */}
            {isTemplateModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-5 border border-slate-200 dark:border-slate-800 shadow-xl max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <h3 className="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                                <Layers className="h-5 w-5 text-emerald-600" />
                                Send WhatsApp Approved Template
                            </h3>
                            <button
                                type="button"
                                onClick={() => setIsTemplateModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="py-4 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Select Pre-Approved Template
                                </label>
                                <select
                                    value={selectedTemplate?.name || ''}
                                    onChange={(e) => {
                                        const found = templates.find(t => t.name === e.target.value);
                                        setSelectedTemplate(found || null);
                                    }}
                                    className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 font-medium"
                                >
                                    <option value="">-- Choose Template --</option>
                                    {templates.map(t => (
                                        <option key={t.id} value={t.name}>
                                            {t.name} ({t.category})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {selectedTemplate && (
                                <div className="space-y-3">
                                    <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                                        <span className="text-[10px] font-bold text-slate-400 uppercase block mb-1">Template Content Preview:</span>
                                        <p className="whitespace-pre-wrap text-slate-800 dark:text-slate-200">
                                            {selectedTemplate.body_text}
                                        </p>
                                    </div>

                                    {/* Dynamic Placeholder Inputs (e.g. {{1}}, {{2}}) */}
                                    {Array.from(selectedTemplate.body_text.matchAll(/\{\{(\d+)\}\}/g)).length > 0 && (
                                        <div className="space-y-2">
                                            <span className="text-xs font-semibold text-slate-700 dark:text-slate-300 block">
                                                Template Parameters:
                                            </span>
                                            {Array.from(selectedTemplate.body_text.matchAll(/\{\{(\d+)\}\}/g)).map((match) => {
                                                const idx = match[1];
                                                return (
                                                    <div key={idx} className="flex items-center gap-2">
                                                        <span className="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-slate-600 dark:text-slate-400">
                                                            {`{{${idx}}}`}
                                                        </span>
                                                        <input
                                                            type="text"
                                                            placeholder={`Parameter ${idx} value...`}
                                                            value={templateParams[idx] || ''}
                                                            onChange={(e) => setTemplateParams({ ...templateParams, [idx]: e.target.value })}
                                                            className="flex-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-900 dark:text-white"
                                                        />
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setIsTemplateModalOpen(false)}
                                className="px-3 py-2 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={!selectedTemplate}
                                onClick={handleSendTemplate}
                                className={`
                                    px-4 py-2 rounded-xl text-xs font-semibold text-white transition
                                    ${selectedTemplate ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-slate-400 cursor-not-allowed'}
                                `}
                            >
                                Send Template via WhatsApp
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ========================================================= */}
            {/* SCHEDULE INSPECTION MODAL                                 */}
            {/* ========================================================= */}
            {isInspectionModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-5 border border-slate-200 dark:border-slate-800 shadow-xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <h3 className="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                                <Calendar className="h-5 w-5 text-emerald-600" />
                                Schedule Site Inspection
                            </h3>
                            <button
                                type="button"
                                onClick={() => setIsInspectionModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={handleScheduleInspection} className="py-4 space-y-3">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Estate / Location *
                                </label>
                                <select
                                    value={inspectionData.estate_name}
                                    onChange={(e) => setInspectionData('estate_name', e.target.value)}
                                    className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 text-slate-900 dark:text-white"
                                    required
                                >
                                    {estates.length > 0 ? (
                                        estates.map(est => (
                                            <option key={est.id} value={est.name}>{est.name} ({est.location})</option>
                                        ))
                                    ) : (
                                        <>
                                            <option value="Oasis Heights Estate, Epe">Oasis Heights Estate, Epe</option>
                                            <option value="Grandview Meadows, Ibeju Lekki">Grandview Meadows, Ibeju Lekki</option>
                                            <option value="Apex Luxury Enclave, Ikoyi">Apex Luxury Enclave, Ikoyi</option>
                                        </>
                                    )}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Date *
                                    </label>
                                    <input
                                        type="date"
                                        value={inspectionData.inspection_date}
                                        onChange={(e) => setInspectionData('inspection_date', e.target.value)}
                                        className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-900 dark:text-white"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Time *
                                    </label>
                                    <input
                                        type="text"
                                        value={inspectionData.inspection_time}
                                        onChange={(e) => setInspectionData('inspection_time', e.target.value)}
                                        placeholder="e.g. 10:00 AM"
                                        className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-900 dark:text-white"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Inspection Officer / Escort
                                </label>
                                <select
                                    value={inspectionData.inspector_id}
                                    onChange={(e) => setInspectionData('inspector_id', e.target.value)}
                                    className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 text-slate-900 dark:text-white"
                                >
                                    {users.map(u => (
                                        <option key={u.id} value={u.id}>{u.name} ({u.role?.toUpperCase() || 'AGENT'})</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Notes & Instructions
                                </label>
                                <textarea
                                    rows={2}
                                    value={inspectionData.notes}
                                    onChange={(e) => setInspectionData('notes', e.target.value)}
                                    placeholder="e.g. Client requested pickup from Lekki Toll Gate..."
                                    className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-900 dark:text-white resize-none"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsInspectionModalOpen(false)}
                                    className="px-3 py-2 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={schedulingInspection}
                                    className="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white"
                                >
                                    {schedulingInspection ? 'Scheduling...' : 'Confirm Inspection'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
