import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { 
    TrendingUp, 
    ArrowLeft, 
    Save, 
    DollarSign, 
    Building, 
    MapPin, 
    Calendar, 
    User, 
    Sparkles,
    Flame,
    Share2,
    Clock,
    FileText
} from 'lucide-react';

export default function Create({ 
    contacts, 
    agents, 
    statuses, 
    temperatures, 
    timelines, 
    qualifications, 
    sources, 
    preselectedContactId 
}) {
    const { data, setData, post, processing, errors } = useForm({
        contact_id: preselectedContactId || '',
        title: '',
        property_interest: '',
        preferred_location: '',
        temperature: 'hot',
        status: 'new',
        budget_min: '',
        budget_max: '',
        budget_range: '',
        purchase_timeline: 'immediate',
        qualification_status: 'unqualified',
        lead_source: 'whatsapp',
        assigned_user_id: '',
        notes: '',
    });

    // Auto-generate a title if empty when user selects contact or property
    const handleContactChange = (contactId) => {
        setData('contact_id', contactId);
        const contact = contacts?.find(c => c.id === Number(contactId));
        if (contact && !data.title) {
            setData((prev) => ({
                ...prev,
                contact_id: contactId,
                title: `${contact.first_name}'s Property Inquiry`,
            }));
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('leads.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">
                            <Link href={route('leads.index')} className="hover:underline flex items-center gap-1">
                                <ArrowLeft className="h-3.5 w-3.5" />
                                <span>Sales Pipeline</span>
                            </Link>
                        </div>
                        <h2 className="font-bold text-2xl text-slate-900 dark:text-white leading-tight">
                            Register Sales Opportunity
                        </h2>
                    </div>
                </div>
            }
        >
            <Head title="Create Sales Lead - Bamcom AI CRM" />

            <div className="max-w-4xl mx-auto">
                <form onSubmit={submit} className="space-y-6">
                    {/* Opportunity Core & Contact Link */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center gap-2.5 pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                            <TrendingUp className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Opportunity Identification & Contact Mapping
                            </h3>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {/* Associated Contact (1:many relation) */}
                            <div className="md:col-span-2">
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5 flex items-center justify-between">
                                    <span>Associated Contact <span className="text-red-500">*</span></span>
                                    <Link href={route('contacts.create')} className="text-[11px] text-blue-600 hover:underline font-semibold">
                                        + Register New Contact First
                                    </Link>
                                </label>
                                <select
                                    required
                                    value={data.contact_id}
                                    onChange={(e) => handleContactChange(e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option value="">-- Select Contact from CRM --</option>
                                    {contacts?.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.first_name} {c.last_name} ({c.phone}) - {c.location || 'No location'}
                                        </option>
                                    ))}
                                </select>
                                {errors.contact_id && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.contact_id}</p>}
                            </div>

                            {/* Opportunity Title */}
                            <div className="md:col-span-2">
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Opportunity Title <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="e.g. 5-Bedroom Luxury Villa Inquiry - Lekki Phase 1"
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.title && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.title}</p>}
                            </div>

                            {/* Temperature (Urgency & Intent) */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Opportunity Temperature <span className="text-red-500">*</span>
                                </label>
                                <div className="grid grid-cols-3 gap-2">
                                    {temperatures?.map((t) => (
                                        <button
                                            key={t.value}
                                            type="button"
                                            onClick={() => setData('temperature', t.value)}
                                            className={`flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs font-bold border transition ${
                                                data.temperature === t.value
                                                    ? 'bg-blue-600 border-blue-600 text-white shadow-sm'
                                                    : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100'
                                            }`}
                                        >
                                            <span>{t.icon}</span>
                                            <span>{t.label}</span>
                                        </button>
                                    ))}
                                </div>
                                {errors.temperature && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.temperature}</p>}
                            </div>

                            {/* Pipeline Stage */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Initial Pipeline Stage <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    {statuses?.map((s) => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                                {errors.status && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.status}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Property & Financial Specifications */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center gap-2.5 pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                            <Building className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Property Specifications & Budget Range
                            </h3>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {/* Property Interest */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Property Interest
                                </label>
                                <input
                                    type="text"
                                    value={data.property_interest}
                                    onChange={(e) => setData('property_interest', e.target.value)}
                                    placeholder="e.g. 4-Bedroom Terrace Duplex, 600sqm Land"
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.property_interest && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.property_interest}</p>}
                            </div>

                            {/* Preferred Location */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Preferred Location / Territory
                                </label>
                                <input
                                    type="text"
                                    value={data.preferred_location}
                                    onChange={(e) => setData('preferred_location', e.target.value)}
                                    placeholder="e.g. Lekki Phase 1, Ikoyi, Epe, Abuja"
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.preferred_location && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.preferred_location}</p>}
                            </div>

                            {/* Budget Min (₦) */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Budget Min (₦)
                                </label>
                                <input
                                    type="number"
                                    step="500000"
                                    value={data.budget_min}
                                    onChange={(e) => setData('budget_min', e.target.value)}
                                    placeholder="e.g. 50000000"
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.budget_min && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.budget_min}</p>}
                            </div>

                            {/* Budget Max (₦) */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Budget Max (₦)
                                </label>
                                <input
                                    type="number"
                                    step="500000"
                                    value={data.budget_max}
                                    onChange={(e) => setData('budget_max', e.target.value)}
                                    placeholder="e.g. 100000000"
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.budget_max && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.budget_max}</p>}
                            </div>

                            {/* Purchase Timeline */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Purchase Timeframe
                                </label>
                                <select
                                    value={data.purchase_timeline}
                                    onChange={(e) => setData('purchase_timeline', e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    {timelines?.map((t) => (
                                        <option key={t.value} value={t.value}>{t.label}</option>
                                    ))}
                                </select>
                                {errors.purchase_timeline && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.purchase_timeline}</p>}
                            </div>

                            {/* Qualification Status */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Qualification Status
                                </label>
                                <select
                                    value={data.qualification_status}
                                    onChange={(e) => setData('qualification_status', e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    {qualifications?.map((q) => (
                                        <option key={q.value} value={q.value}>{q.label}</option>
                                    ))}
                                </select>
                                {errors.qualification_status && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.qualification_status}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Attribution & Sales Assignment */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center gap-2.5 pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                            <Share2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Lead Attribution & Representative Assignment
                            </h3>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {/* Lead Source */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Attribution Source
                                </label>
                                <select
                                    value={data.lead_source}
                                    onChange={(e) => setData('lead_source', e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    {sources?.map((s) => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                                {errors.lead_source && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.lead_source}</p>}
                            </div>

                            {/* Assigned Sales Representative */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Assigned Sales Representative
                                </label>
                                <select
                                    value={data.assigned_user_id}
                                    onChange={(e) => setData('assigned_user_id', e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option value="">-- Unassigned --</option>
                                    {agents?.map((agent) => (
                                        <option key={agent.id} value={agent.id}>
                                            {agent.name} ({agent.role})
                                        </option>
                                    ))}
                                </select>
                                {errors.assigned_user_id && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.assigned_user_id}</p>}
                            </div>

                            {/* Notes & Requirements */}
                            <div className="md:col-span-2">
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Discovery Notes / Requirements
                                </label>
                                <textarea
                                    rows="3"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="Enter client specifications, title preference (Governor's Consent / C of O), or financing status..."
                                    className="w-full p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.notes && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.notes}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href={route('leads.index')}
                            className="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 text-sm font-semibold transition"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            <Save className="h-4 w-4" />
                            <span>{processing ? 'Saving Opportunity...' : 'Create Opportunity'}</span>
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
