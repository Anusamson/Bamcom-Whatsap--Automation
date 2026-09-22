import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Handshake, ArrowLeft, Save, Sparkles, Building, User } from 'lucide-react';
import { useEffect } from 'react';

export default function Create({ 
    contacts, 
    properties, 
    leads, 
    users, 
    pipeline, 
    initialContactId, 
    initialLeadId, 
    initialPropertyId 
}) {
    const { data, setData, post, processing, errors } = useForm({
        contact_id: initialContactId || (contacts[0]?.id || ''),
        lead_id: initialLeadId || '',
        property_id: initialPropertyId || '',
        assigned_user_id: '',
        pipeline_id: pipeline?.id || '',
        pipeline_stage_id: pipeline?.stages?.[0]?.id || '',
        title: '',
        deal_value: '',
        expected_close_date: '',
        probability: pipeline?.stages?.[0]?.probability || 20,
        notes: '',
    });

    // Auto-fill title or deal value when contact or property changes
    useEffect(() => {
        const contact = contacts.find(c => Number(c.id) === Number(data.contact_id));
        const prop = properties.find(p => Number(p.id) === Number(data.property_id));

        if (!data.title && contact) {
            const propTitle = prop ? prop.title : 'Real Estate Deal';
            setData('title', `${contact.first_name} ${contact.last_name} - ${propTitle}`);
        }

        if (prop && prop.active_price?.regular_price && !data.deal_value) {
            const price = prop.active_price.promo_price || prop.active_price.regular_price;
            setData('deal_value', price);
        }
    }, [data.contact_id, data.property_id]);

    const handleStageChange = (e) => {
        const selectedStageId = e.target.value;
        const stage = pipeline?.stages?.find(s => Number(s.id) === Number(selectedStageId));
        setData({
            ...data,
            pipeline_stage_id: selectedStageId,
            probability: stage ? stage.probability : data.probability,
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('deals.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href={route('deals.index')}
                        className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                            Create Sales Opportunity
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Connect buyer contact, target property plot, sales representative, and transaction value.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Create Opportunity" />

            <div className="py-6 max-w-4xl mx-auto">
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Core Connections */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            <Handshake className="w-4 h-4 text-indigo-500" />
                            Core Connections
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Client / Buyer Contact *
                                </label>
                                <select
                                    required
                                    value={data.contact_id}
                                    onChange={(e) => setData('contact_id', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">Select Buyer Contact</option>
                                    {contacts.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.first_name} {c.last_name} ({c.phone})
                                        </option>
                                    ))}
                                </select>
                                {errors.contact_id && <p className="text-xs text-rose-500 mt-1">{errors.contact_id}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Target Property / Plot (Optional)
                                </label>
                                <select
                                    value={data.property_id}
                                    onChange={(e) => setData('property_id', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">Custom Property / Flexible</option>
                                    {properties.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.title} ({p.plot_size})
                                        </option>
                                    ))}
                                </select>
                                {errors.property_id && <p className="text-xs text-rose-500 mt-1">{errors.property_id}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Assigned Sales Representative
                                </label>
                                <select
                                    value={data.assigned_user_id}
                                    onChange={(e) => setData('assigned_user_id', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">Select Sales Agent</option>
                                    {users.map((u) => (
                                        <option key={u.id} value={u.id}>{u.name} ({u.role})</option>
                                    ))}
                                </select>
                                {errors.assigned_user_id && <p className="text-xs text-rose-500 mt-1">{errors.assigned_user_id}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Associated Lead (Optional)
                                </label>
                                <select
                                    value={data.lead_id}
                                    onChange={(e) => setData('lead_id', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">No linked intake lead</option>
                                    {leads.map((l) => (
                                        <option key={l.id} value={l.id}>{l.title}</option>
                                    ))}
                                </select>
                                {errors.lead_id && <p className="text-xs text-rose-500 mt-1">{errors.lead_id}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Deal Value & Milestone Info */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Deal Value, Title & Pipeline Stage
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Opportunity Title *
                                </label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. Chief Adeleke - Grace Haven 500sqm Purchase"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                {errors.title && <p className="text-xs text-rose-500 mt-1">{errors.title}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Deal Value (₦) *
                                </label>
                                <input
                                    type="number"
                                    required
                                    min="0"
                                    step="1000"
                                    placeholder="15000000"
                                    value={data.deal_value}
                                    onChange={(e) => setData('deal_value', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                {errors.deal_value && <p className="text-xs text-rose-500 mt-1">{errors.deal_value}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Pipeline Stage *
                                </label>
                                <select
                                    value={data.pipeline_stage_id}
                                    onChange={handleStageChange}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                >
                                    {pipeline?.stages?.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name} ({s.probability}%)
                                        </option>
                                    ))}
                                </select>
                                {errors.pipeline_stage_id && <p className="text-xs text-rose-500 mt-1">{errors.pipeline_stage_id}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Expected Close Date
                                </label>
                                <input
                                    type="date"
                                    value={data.expected_close_date}
                                    onChange={(e) => setData('expected_close_date', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                {errors.expected_close_date && <p className="text-xs text-rose-500 mt-1">{errors.expected_close_date}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Win Probability (%)
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={data.probability}
                                    onChange={(e) => setData('probability', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                {errors.probability && <p className="text-xs text-rose-500 mt-1">{errors.probability}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Deal Notes & Agreed Terms
                                </label>
                                <textarea
                                    rows="3"
                                    placeholder="e.g. Buyer requested 6-month installment schedule with 30% initial deposit..."
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                {errors.notes && <p className="text-xs text-rose-500 mt-1">{errors.notes}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex items-center justify-end gap-3">
                        <Link
                            href={route('deals.index')}
                            className="px-4 py-2 text-xs font-semibold rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-6 py-2.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shadow-sm disabled:opacity-50"
                        >
                            <Save className="w-4 h-4" />
                            {processing ? 'Saving...' : 'Register Opportunity'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
