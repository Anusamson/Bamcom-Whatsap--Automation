import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { 
    TrendingUp, 
    ArrowLeft, 
    Save, 
    Building, 
    Share2, 
    Eye,
    AlertCircle
} from 'lucide-react';

export default function Edit({ 
    lead, 
    contacts, 
    agents, 
    statuses, 
    temperatures, 
    timelines, 
    qualifications, 
    sources 
}) {
    const { data, setData, put, processing, errors } = useForm({
        contact_id: lead.contact_id || '',
        title: lead.title || '',
        property_interest: lead.property_interest || '',
        preferred_location: lead.preferred_location || '',
        temperature: lead.temperature || 'warm',
        status: lead.status || 'new',
        score: lead.score ?? '',
        budget_min: lead.budget_min || '',
        budget_max: lead.budget_max || '',
        budget_range: lead.budget_range || '',
        purchase_timeline: lead.purchase_timeline || '1_3_months',
        qualification_status: lead.qualification_status || 'unqualified',
        lead_source: lead.lead_source || 'whatsapp',
        assigned_user_id: lead.assigned_user_id || '',
        notes: lead.notes || '',
        lost_reason: lead.lost_reason || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('leads.update', lead.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">
                            <Link href={route('leads.show', lead.id)} className="hover:underline flex items-center gap-1">
                                <ArrowLeft className="h-3.5 w-3.5" />
                                <span>Back to Opportunity 360</span>
                            </Link>
                        </div>
                        <h2 className="font-bold text-2xl text-slate-900 dark:text-white leading-tight">
                            Edit Opportunity: {lead.title}
                        </h2>
                    </div>

                    <Link
                        href={route('leads.show', lead.id)}
                        className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition"
                    >
                        <Eye className="h-3.5 w-3.5" />
                        <span>View Opportunity</span>
                    </Link>
                </div>
            }
        >
            <Head title={`Edit ${lead.title} - Bamcom AI CRM`} />

            <div className="max-w-4xl mx-auto">
                <form onSubmit={submit} className="space-y-6">
                    {/* Opportunity Core */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                            <div className="flex items-center gap-2.5">
                                <TrendingUp className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Opportunity Specifications
                                </h3>
                            </div>
                            <span className="text-[11px] font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-slate-500">
                                UUID: {lead.uuid}
                            </span>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {/* Associated Contact */}
                            <div className="md:col-span-2">
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Associated Contact <span className="text-red-500">*</span>
                                </label>
                                <select
                                    required
                                    value={data.contact_id}
                                    onChange={(e) => setData('contact_id', e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    {contacts?.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.first_name} {c.last_name} ({c.phone})
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
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.title && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.title}</p>}
                            </div>

                            {/* Temperature */}
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
                                    Pipeline Stage <span className="text-red-500">*</span>
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

                            {/* Lead Score (0 - 100) */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5 flex items-center justify-between">
                                    <span>Lead Score (0 - 100)</span>
                                    <span className="text-[10px] text-slate-400">Leave blank to auto-calculate</span>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={data.score}
                                    onChange={(e) => setData('score', e.target.value)}
                                    placeholder="Auto-calculated"
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.score && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.score}</p>}
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

                    {/* Financials & Timeline */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center gap-2.5 pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                            <Building className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Property Interest & Budget Range
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
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.property_interest && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.property_interest}</p>}
                            </div>

                            {/* Preferred Location */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Preferred Location
                                </label>
                                <input
                                    type="text"
                                    value={data.preferred_location}
                                    onChange={(e) => setData('preferred_location', e.target.value)}
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
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
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
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
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
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
                        </div>
                    </div>

                    {/* Assignment & Notes */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {/* Assigned Sales Representative */}
                            <div className="md:col-span-2">
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

                            {/* Lost Reason (conditional if lost) */}
                            {['lost', 'disqualified'].includes(data.status) && (
                                <div className="md:col-span-2">
                                    <label className="block text-xs font-bold uppercase tracking-wider text-rose-600 mb-1.5">
                                        Lost / Disqualified Reason
                                    </label>
                                    <input
                                        type="text"
                                        value={data.lost_reason}
                                        onChange={(e) => setData('lost_reason', e.target.value)}
                                        placeholder="e.g. Budget mismatch, purchased elsewhere, unresponsive..."
                                        className="w-full px-3.5 py-2.5 bg-rose-50/40 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-500 focus:border-transparent transition"
                                    />
                                    {errors.lost_reason && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.lost_reason}</p>}
                                </div>
                            )}

                            {/* Notes */}
                            <div className="md:col-span-2">
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Discovery Notes
                                </label>
                                <textarea
                                    rows="3"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    className="w-full p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.notes && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.notes}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href={route('leads.show', lead.id)}
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
                            <span>{processing ? 'Saving Changes...' : 'Update Opportunity'}</span>
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
