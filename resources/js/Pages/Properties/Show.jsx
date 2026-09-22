import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    Building2, 
    Edit, 
    ArrowLeft, 
    MapPin, 
    FileText, 
    Sparkles, 
    DollarSign, 
    Calendar, 
    Share2, 
    CheckCircle2, 
    Users, 
    Layers, 
    MessageSquare, 
    ShieldCheck, 
    Tag, 
    Clock, 
    Copy, 
    ExternalLink,
    ChevronRight,
    Home
} from 'lucide-react';
import { useState } from 'react';

export default function Show({ property, aiFactSheet, whatsappPitch }) {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const [copiedAi, setCopiedAi] = useState(false);
    const [copiedWhatsapp, setCopiedWhatsapp] = useState(false);
    const [activeTab, setActiveTab] = useState('overview'); // overview, payment_plans, ai_knowledge, leads

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '₦0.00';
        return '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    };

    const copyToClipboard = (text, type) => {
        navigator.clipboard.writeText(text);
        if (type === 'ai') {
            setCopiedAi(true);
            setTimeout(() => setCopiedAi(false), 2000);
        } else {
            setCopiedWhatsapp(true);
            setTimeout(() => setCopiedWhatsapp(false), 2000);
        }
    };

    const hasPromo = property.promo_price && property.promo_price < property.regular_price;
    const heroImage = property.primary_media?.file_url || property.primary_media?.file_path || 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1200&q=80';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('properties.index')}
                            className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-xs uppercase tracking-wider font-semibold text-emerald-600 dark:text-emerald-400">
                                    {property.property_type} • {property.plot_size}
                                </span>
                                {hasPromo && (
                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">
                                        PROMO
                                    </span>
                                )}
                            </div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                {property.title}
                            </h2>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => copyToClipboard(whatsappPitch, 'whatsapp')}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800 transition-colors"
                        >
                            {copiedWhatsapp ? <CheckCircle2 className="w-4 h-4 text-emerald-600" /> : <Share2 className="w-4 h-4" />}
                            {copiedWhatsapp ? 'Pitch Copied!' : 'Copy WhatsApp Pitch'}
                        </button>

                        <a
                            href={`https://wa.me/?text=${encodeURIComponent(whatsappPitch)}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors"
                        >
                            <MessageSquare className="w-4 h-4" />
                            Launch WhatsApp
                        </a>

                        {can('properties.edit') && (
                            <Link
                                href={route('properties.edit', property.id)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors"
                            >
                                <Edit className="w-4 h-4" />
                                Edit Property
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`${property.title} - Property 360`} />

            <div className="py-6 space-y-6">
                {/* Hero Showcase Card */}
                <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div className="relative h-64 md:h-80 w-full bg-slate-950 overflow-hidden">
                        <img
                            src={heroImage}
                            alt={property.title}
                            className="w-full h-full object-cover opacity-90"
                        />
                        <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent" />

                        <div className="absolute bottom-6 left-6 right-6 flex flex-col md:flex-row md:items-end justify-between gap-4 text-white">
                            <div className="space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="px-2.5 py-0.5 rounded-md text-xs font-semibold bg-emerald-600 text-white">
                                        {property.property_type.toUpperCase()}
                                    </span>
                                    {property.estate && (
                                        <Link
                                            href={route('estates.show', property.estate.id)}
                                            className="px-2.5 py-0.5 rounded-md text-xs font-semibold bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white transition-colors"
                                        >
                                            {property.estate.name} ↗
                                        </Link>
                                    )}
                                    <span className={`px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase ${
                                        property.availability === 'available'
                                            ? 'bg-emerald-500 text-white'
                                            : property.availability === 'sold_out'
                                            ? 'bg-rose-500 text-white'
                                            : 'bg-amber-500 text-white'
                                    }`}>
                                        {property.availability.replace('_', ' ')}
                                    </span>
                                </div>
                                <h1 className="text-2xl md:text-3xl font-extrabold text-white">
                                    {property.title}
                                </h1>
                                <p className="text-sm text-slate-300 flex items-center gap-1.5">
                                    <MapPin className="w-4 h-4 text-emerald-400 shrink-0" />
                                    {property.location || property.estate?.location}
                                </p>
                            </div>

                            <div className="text-left md:text-right bg-black/40 backdrop-blur-md p-4 rounded-xl border border-white/10">
                                <div className="text-xs uppercase tracking-wider text-slate-300 font-medium">
                                    {hasPromo ? 'Promotional Price' : 'Outright Price'}
                                </div>
                                <div className="text-2xl md:text-3xl font-extrabold text-emerald-400">
                                    {formatCurrency(property.effective_price)}
                                </div>
                                {hasPromo && (
                                    <div className="text-xs text-rose-300 font-medium">
                                        Regular: <span className="line-through">{formatCurrency(property.regular_price)}</span> (Save {formatCurrency(property.regular_price - property.promo_price)})
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Navigation Tabs */}
                    <div className="flex border-b border-slate-200 dark:border-slate-700 px-6 bg-slate-50 dark:bg-slate-900/60">
                        <button
                            onClick={() => setActiveTab('overview')}
                            className={`py-3.5 px-4 text-xs font-semibold border-b-2 transition-colors ${
                                activeTab === 'overview'
                                    ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                    : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            }`}
                        >
                            Overview & Specs
                        </button>
                        <button
                            onClick={() => setActiveTab('payment_plans')}
                            className={`py-3.5 px-4 text-xs font-semibold border-b-2 transition-colors ${
                                activeTab === 'payment_plans'
                                    ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                    : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            }`}
                        >
                            Payment Plans ({property.active_price?.payment_plans?.length || 4})
                        </button>
                        <button
                            onClick={() => setActiveTab('ai_knowledge')}
                            className={`py-3.5 px-4 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 ${
                                activeTab === 'ai_knowledge'
                                    ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                    : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            }`}
                        >
                            <Sparkles className="w-3.5 h-3.5 text-amber-500" />
                            AI Grounding Truth
                        </button>
                        <button
                            onClick={() => setActiveTab('leads')}
                            className={`py-3.5 px-4 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 ${
                                activeTab === 'leads'
                                    ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                    : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            }`}
                        >
                            <Users className="w-3.5 h-3.5 text-blue-500" />
                            Interested Leads ({property.leads?.length || 0})
                        </button>
                    </div>
                </div>

                {/* Tab Content */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main 2-Column Left Section */}
                    <div className="lg:col-span-2 space-y-6">
                        {activeTab === 'overview' && (
                            <>
                                {/* Core Specifications Grid */}
                                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                                    <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Authoritative Property Specifications
                                    </h3>

                                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                        <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                            <div className="text-[11px] text-slate-500 dark:text-slate-400">Plot Size</div>
                                            <div className="text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                                                {property.plot_size}
                                            </div>
                                        </div>

                                        <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                            <div className="text-[11px] text-slate-500 dark:text-slate-400">Legal Title Document</div>
                                            <div className="text-sm font-bold text-emerald-600 dark:text-emerald-400 mt-0.5 flex items-center gap-1">
                                                <ShieldCheck className="w-4 h-4 shrink-0" />
                                                <span className="truncate">{property.title_document || property.estate?.title_document || 'Survey & Deed'}</span>
                                            </div>
                                        </div>

                                        <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                            <div className="text-[11px] text-slate-500 dark:text-slate-400">Inventory Units</div>
                                            <div className="text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                                                {property.available_units} / {property.total_units} Left
                                            </div>
                                        </div>

                                        <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                            <div className="text-[11px] text-slate-500 dark:text-slate-400">Plot Number / Code</div>
                                            <div className="text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                                                {property.plot_number || 'Standard Inventory'}
                                            </div>
                                        </div>

                                        <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                            <div className="text-[11px] text-slate-500 dark:text-slate-400">Minimum Initial Deposit</div>
                                            <div className="text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                                                {formatCurrency(property.initial_deposit)}
                                            </div>
                                        </div>

                                        <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                            <div className="text-[11px] text-slate-500 dark:text-slate-400">Estate Development</div>
                                            <div className="text-sm font-bold text-slate-900 dark:text-white mt-0.5 truncate">
                                                {property.estate?.name || 'Standalone Plot'}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Description */}
                                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                                    <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Full Description & Highlights
                                    </h3>
                                    <p className="text-xs leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-line">
                                        {property.description || 'No detailed description provided for this property listing yet.'}
                                    </p>
                                </div>

                                {/* Features & Amenities Tags */}
                                {property.features?.length > 0 && (
                                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                                        <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Features & Amenities
                                        </h3>
                                        <div className="flex flex-wrap gap-2">
                                            {property.features.map((feat, i) => (
                                                <span
                                                    key={i}
                                                    className="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                                >
                                                    <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" />
                                                    {feat}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {activeTab === 'payment_plans' && (
                            <div className="space-y-4">
                                <div className="p-4 bg-emerald-50 dark:bg-emerald-950/40 rounded-xl border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-300">
                                    <strong>Authoritative Payment Breakdown:</strong> All initial deposits, monthly installments, and tenure milestones are calculated with zero error for customer clarity.
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {property.active_price?.payment_plans?.map((plan, idx) => (
                                        <div
                                            key={idx}
                                            className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                    {plan.duration_months === 0 ? 'Outright Tier' : `${plan.duration_months} Months Plan`}
                                                </span>
                                                <span className="text-xs px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-bold">
                                                    {plan.name}
                                                </span>
                                            </div>

                                            <div>
                                                <div className="text-xs text-slate-500">Total Price</div>
                                                <div className="text-xl font-extrabold text-slate-900 dark:text-white">
                                                    {formatCurrency(plan.total_amount)}
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
                                                <div>
                                                    <div className="text-slate-500">Initial Deposit</div>
                                                    <div className="font-semibold text-slate-800 dark:text-slate-200">
                                                        {formatCurrency(plan.initial_deposit)}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="text-slate-500">Monthly Installment</div>
                                                    <div className="font-semibold text-emerald-600 dark:text-emerald-400">
                                                        {plan.duration_months > 0 ? formatCurrency(plan.monthly_installment) : 'N/A (Outright)'}
                                                    </div>
                                                </div>
                                            </div>

                                            <p className="text-[11px] text-slate-500 dark:text-slate-400 pt-1">
                                                {plan.description}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {activeTab === 'ai_knowledge' && (
                            <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                            <Sparkles className="w-4 h-4 text-emerald-500" />
                                            Authoritative AI Fact Sheet (JSON)
                                        </h3>
                                        <p className="text-xs text-slate-500">
                                            This exact JSON payload is served via <code className="text-emerald-600">/api/v1/properties/ai-context</code> for LLM system prompts and WhatsApp bots.
                                        </p>
                                    </div>
                                    <button
                                        onClick={() => copyToClipboard(JSON.stringify(aiFactSheet, null, 2), 'ai')}
                                        className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition-colors"
                                    >
                                        <Copy className="w-3.5 h-3.5" />
                                        {copiedAi ? 'Copied JSON!' : 'Copy JSON'}
                                    </button>
                                </div>

                                <pre className="p-4 bg-slate-900 text-emerald-400 rounded-xl text-xs overflow-x-auto font-mono max-h-96">
                                    {JSON.stringify(aiFactSheet, null, 2)}
                                </pre>
                            </div>
                        )}

                        {activeTab === 'leads' && (
                            <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Users className="w-4 h-4 text-blue-500" />
                                    Interested Leads & Prospects for this Property
                                </h3>

                                {property.leads?.length > 0 ? (
                                    <div className="divide-y divide-slate-100 dark:divide-slate-700/60">
                                        {property.leads.map((lead) => (
                                            <div key={lead.id} className="py-3 flex items-center justify-between">
                                                <div>
                                                    <Link
                                                        href={route('leads.show', lead.id)}
                                                        className="text-xs font-bold text-slate-900 dark:text-white hover:text-emerald-600"
                                                    >
                                                        {lead.title}
                                                    </Link>
                                                    <div className="text-[11px] text-slate-500">
                                                        Client: {lead.contact?.first_name} {lead.contact?.last_name} ({lead.contact?.phone})
                                                    </div>
                                                </div>
                                                <div className="text-right text-xs">
                                                    <span className="font-semibold text-emerald-600">
                                                        {lead.formatted_budget}
                                                    </span>
                                                    <div className="text-[10px] uppercase text-slate-400">
                                                        {lead.status}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-xs text-slate-400 py-4 text-center">
                                        No active sales leads currently tagged to this property.
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Right Column: Pricing Summary & WhatsApp Pitch Box */}
                    <div className="space-y-6">
                        {/* Investment & Pricing Card */}
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Financial Overview
                            </h3>

                            <div className="space-y-3">
                                <div>
                                    <span className="text-[11px] text-slate-500">Selling Price</span>
                                    <div className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">
                                        {formatCurrency(property.effective_price)}
                                    </div>
                                    {hasPromo && (
                                        <div className="text-xs text-rose-500 font-semibold">
                                            Regular Price: <span className="line-through">{formatCurrency(property.regular_price)}</span>
                                        </div>
                                    )}
                                </div>

                                <div className="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                                    <span className="text-[11px] text-slate-500">Minimum Initial Deposit (20%)</span>
                                    <div className="text-lg font-bold text-slate-900 dark:text-white">
                                        {formatCurrency(property.initial_deposit)}
                                    </div>
                                </div>

                                <div className="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                                    <span className="text-[11px] text-slate-500">Payment Plan Summary</span>
                                    <p className="text-xs text-slate-700 dark:text-slate-300 font-medium mt-0.5">
                                        {property.payment_plan_summary || 'Outright and 3-12 months flexible installment.'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* WhatsApp Ready Pitch */}
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                                    <MessageSquare className="w-3.5 h-3.5 text-emerald-500" />
                                    WhatsApp Sales Copy
                                </h3>
                                <button
                                    onClick={() => copyToClipboard(whatsappPitch, 'whatsapp')}
                                    className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold hover:underline"
                                >
                                    {copiedWhatsapp ? 'Copied!' : 'Copy'}
                                </button>
                            </div>

                            <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300 whitespace-pre-line font-sans leading-relaxed">
                                {whatsappPitch}
                            </div>

                            <a
                                href={`https://wa.me/?text=${encodeURIComponent(whatsappPitch)}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition-colors shadow-sm"
                            >
                                <MessageSquare className="w-4 h-4" />
                                Send to Customer on WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
