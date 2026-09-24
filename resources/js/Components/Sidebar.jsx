import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import { 
    LayoutDashboard, 
    Layers, 
    ShieldCheck, 
    Activity, 
    SlidersHorizontal,
    ChevronLeft,
    ChevronRight,
    Sparkles,
    UserCheck,
    Users,
    UsersRound,
    Contact,
    TrendingUp,
    Columns3,
    Building2,
    MapPin,
    Handshake,
    MessageSquare,
    BookOpen,
    Flame,
    CalendarCheck,
    CheckSquare,
    Workflow,
    Megaphone,
    ListFilter,
    BarChart3,
    Bell
} from 'lucide-react';
import ApplicationLogo from '@/Components/ApplicationLogo';

export default function Sidebar({ 
    isOpen, 
    setIsOpen, 
    isMobileOpen, 
    setIsMobileOpen, 
    user 
}) {
    const [unreadConversations, setUnreadConversations] = useState(0);

    useEffect(() => {
        const fetchCounters = async () => {
            try {
                const res = await fetch(route('notifications.unread-count'), {
                    headers: { 'Accept': 'application/json' },
                });
                if (res.ok) {
                    const data = await res.json();
                    setUnreadConversations(data.unread_conversations || 0);
                }
            } catch {
                // Ignore network errors
            }
        };

        fetchCounters();
        const timer = setInterval(fetchCounters, 15000);
        return () => clearInterval(timer);
    }, []);

    const navItems = [
        {
            name: 'Dashboard',
            href: route('dashboard'),
            active: route().current('dashboard'),
            icon: LayoutDashboard,
            badge: 'Live',
            badgeColor: 'bg-emerald-500 text-white',
        },
        {
            name: 'Analytics & Reports',
            href: route('reports.index'),
            active: route().current('reports.*'),
            icon: BarChart3,
            badge: 'Insights',
            badgeColor: 'bg-indigo-600 text-white',
        },
        {
            name: 'AI Sales Intelligence',
            href: route('sales-intelligence.index'),
            active: route().current('sales-intelligence.*'),
            icon: Sparkles,
            badge: 'AI Insights',
            badgeColor: 'bg-purple-600 text-white',
        },
        {
            name: 'CRM Contacts',
            href: route('contacts.index'),
            active: route().current('contacts.*'),
            icon: Contact,
            badge: 'Contacts',
            badgeColor: 'bg-indigo-600 text-white',
        },
        {
            name: 'WhatsApp Inbox',
            href: route('conversations.inbox'),
            active: route().current('conversations.*'),
            icon: MessageSquare,
            badge: unreadConversations > 0 ? `${unreadConversations} New` : 'Live',
            badgeColor: unreadConversations > 0 ? 'bg-red-500 text-white font-bold' : 'bg-emerald-500 text-white',
        },
        {
            name: 'AI Knowledge Base',
            href: route('knowledge.index'),
            active: route().current('knowledge.*'),
            icon: BookOpen,
            badge: 'Grounding',
            badgeColor: 'bg-indigo-600 text-white',
        },
        {
            name: 'Sales Leads',
            href: route('leads.index'),
            active: route().current('leads.*'),
            icon: TrendingUp,
            badge: 'Leads',
            badgeColor: 'bg-amber-500 text-white',
        },
        {
            name: 'Smart Lists',
            href: route('smart-lists.index'),
            active: route().current('smart-lists.*'),
            icon: ListFilter,
            badge: 'Dynamic',
            badgeColor: 'bg-indigo-600 text-white',
        },
        {
            name: 'Tasks & Reminders',
            href: route('tasks.index'),
            active: route().current('tasks.*'),
            icon: CheckSquare,
            badge: 'Tasks',
            badgeColor: 'bg-blue-600 text-white',
        },
        {
            name: 'Lead Scoring',
            href: route('lead-scoring.index'),
            active: route().current('lead-scoring.*'),
            icon: Flame,
            badge: 'Rules',
            badgeColor: 'bg-rose-500 text-white',
        },
        {
            name: 'Pipeline Kanban',
            href: route('pipelines.index'),
            active: route().current('pipelines.*'),
            icon: Columns3,
            badge: 'Kanban',
            badgeColor: 'bg-indigo-500 text-white',
        },
        {
            name: 'Follow-up Sequences',
            href: route('sequences.index'),
            active: route().current('sequences.*'),
            icon: Workflow,
            badge: 'Sequences',
            badgeColor: 'bg-purple-600 text-white',
        },
        {
            name: 'WhatsApp Campaigns',
            href: route('campaigns.index'),
            active: route().current('campaigns.*') || route().current('audiences.*'),
            icon: Megaphone,
            badge: 'Broadcast',
            badgeColor: 'bg-emerald-600 text-white',
        },
        {
            name: 'Deals & Opportunities',
            href: route('deals.index'),
            active: route().current('deals.*'),
            icon: Handshake,
            badge: 'Deals',
            badgeColor: 'bg-emerald-600 text-white',
        },
        {
            name: 'Properties',
            href: route('properties.index'),
            active: route().current('properties.*'),
            icon: Building2,
            badge: 'Inventory',
            badgeColor: 'bg-emerald-600 text-white',
        },
        {
            name: 'Estates',
            href: route('estates.index'),
            active: route().current('estates.*'),
            icon: MapPin,
            badge: 'Estates',
            badgeColor: 'bg-teal-600 text-white',
        },
        {
            name: 'Site Inspections',
            href: route('inspections.index'),
            active: route().current('inspections.*'),
            icon: CalendarCheck,
            badge: 'Field',
            badgeColor: 'bg-blue-600 text-white',
        },
        {
            name: 'Users Directory',
            href: route('users.index'),
            active: route().current('users.*'),
            icon: Users,
            badge: 'Admin',
            badgeColor: 'bg-blue-600 text-white',
        },
        {
            name: 'Teams & Groups',
            href: route('teams.index'),
            active: route().current('teams.*'),
            icon: UsersRound,
            badge: 'Sales',
            badgeColor: 'bg-emerald-600 text-white',
        },
        {
            name: 'Roles & Permissions',
            href: route('roles.index'),
            active: route().current('roles.*') || route().current('permissions.*'),
            icon: ShieldCheck,
            badge: 'RBAC',
            badgeColor: 'bg-red-600 text-white',
        },
        {
            name: 'Authentication Guard',
            href: route('profile.edit'),
            active: route().current('profile.edit'),
            icon: UserCheck,
        },
        {
            name: 'WhatsApp Platform',
            href: route('whatsapp.settings.index'),
            active: route().current('whatsapp.settings.*'),
            icon: MessageSquare,
            badge: 'Meta API',
            badgeColor: 'bg-emerald-600 text-white',
        },
        {
            name: 'System Config',
            href: '#config',
            active: false,
            icon: SlidersHorizontal,
        },
    ];

    const sidebarContent = (
        <div className="flex flex-col h-full bg-slate-900 border-r border-slate-800 text-white">
            {/* Header / Logo */}
            <div className="flex items-center justify-between h-16 px-4 border-b border-slate-800 bg-slate-950/40">
                <Link href={route('dashboard')} className="flex items-center gap-3 overflow-hidden">
                    <ApplicationLogo className="h-8 w-auto" showText={isOpen} />
                </Link>
                <button
                    type="button"
                    onClick={() => setIsOpen(!isOpen)}
                    className="hidden lg:flex items-center justify-center p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
                    title={isOpen ? 'Collapse Sidebar' : 'Expand Sidebar'}
                >
                    {isOpen ? <ChevronLeft className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                </button>
            </div>

            {/* AI Status Badge */}
            {isOpen && (
                <div className="mx-3 mt-3 px-3 py-2 rounded-lg bg-gradient-to-r from-blue-950/60 to-slate-900 border border-blue-900/60 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="relative flex h-2 w-2">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                        </div>
                        <span className="text-xs font-medium text-slate-300">AI Engine Ready</span>
                    </div>
                    <span className="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-blue-600/30 text-blue-300 border border-blue-500/30">
                        Phase 1
                    </span>
                </div>
            )}

            {/* Navigation items */}
            <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const content = (
                        <div
                            className={`group relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all ${
                                item.active
                                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20'
                                    : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                            }`}
                        >
                            {/* Active Red Accent indicator */}
                            {item.active && (
                                <span className="absolute left-0 top-1.5 bottom-1.5 w-1 bg-red-500 rounded-r"></span>
                            )}
                            <Icon
                                className={`h-5 w-5 flex-shrink-0 transition-transform group-hover:scale-110 ${
                                    item.active ? 'text-white' : 'text-slate-400 group-hover:text-blue-400'
                                }`}
                            />
                            {isOpen && (
                                <span className="truncate flex-1">{item.name}</span>
                            )}
                            {isOpen && item.badge && (
                                <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${item.badgeColor}`}>
                                    {item.badge}
                                </span>
                            )}
                        </div>
                    );

                    return item.external ? (
                        <a
                            key={item.name}
                            href={item.href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="block"
                            title={!isOpen ? item.name : undefined}
                        >
                            {content}
                        </a>
                    ) : (
                        <Link
                            key={item.name}
                            href={item.href}
                            className="block"
                            title={!isOpen ? item.name : undefined}
                            onClick={() => setIsMobileOpen(false)}
                        >
                            {content}
                        </Link>
                    );
                })}
            </nav>

            {/* Footer / User Preview */}
            <div className="p-3 border-t border-slate-800 bg-slate-950/50">
                <div className="flex items-center gap-3">
                    <div className="h-9 w-9 rounded-lg bg-gradient-to-tr from-blue-700 to-red-600 flex items-center justify-center text-white font-bold text-sm shadow-md flex-shrink-0">
                        {user?.name ? user.name.charAt(0).toUpperCase() : 'U'}
                    </div>
                    {isOpen && (
                        <div className="flex flex-col min-w-0 flex-1">
                            <span className="text-sm font-semibold text-white truncate">
                                {user?.name || 'Bamcom User'}
                            </span>
                            <div className="flex items-center gap-1.5">
                                <span className="text-[11px] text-slate-400 truncate">
                                    {user?.email || 'admin@bamcom.ai'}
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );

    return (
        <>
            {/* Desktop Sidebar */}
            <aside
                className={`hidden lg:flex flex-col fixed inset-y-0 left-0 z-30 transition-all duration-300 ease-in-out ${
                    isOpen ? 'w-64' : 'w-20'
                }`}
            >
                {sidebarContent}
            </aside>

            {/* Mobile Drawer Backdrop */}
            {isMobileOpen && (
                <div
                    className="fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-sm lg:hidden transition-opacity"
                    onClick={() => setIsMobileOpen(false)}
                />
            )}

            {/* Mobile Sidebar Drawer */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 w-72 transform transition-transform duration-300 ease-in-out lg:hidden ${
                    isMobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {sidebarContent}
            </aside>
        </>
    );
}
