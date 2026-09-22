import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { 
    Contact, 
    ArrowLeft, 
    Save, 
    Phone, 
    Mail, 
    MapPin, 
    Briefcase, 
    Sparkles,
    AlertCircle,
    Eye
} from 'lucide-react';

export default function Edit({ contact, users, statuses, leadSources }) {
    const { data, setData, put, processing, errors } = useForm({
        first_name: contact.first_name || '',
        last_name: contact.last_name || '',
        phone: contact.phone || '',
        email: contact.email || '',
        location: contact.location || '',
        occupation: contact.occupation || '',
        preferred_language: contact.preferred_language || 'en',
        lead_source: contact.lead_source || 'whatsapp',
        assigned_user_id: contact.assigned_user_id || '',
        status: contact.status || 'lead',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('contacts.update', contact.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">
                            <Link href={route('contacts.show', contact.id)} className="hover:underline flex items-center gap-1">
                                <ArrowLeft className="h-3.5 w-3.5" />
                                <span>Back to Contact 360 Profile</span>
                            </Link>
                        </div>
                        <h2 className="font-bold text-2xl text-slate-900 dark:text-white leading-tight">
                            Edit Contact: {contact.full_name}
                        </h2>
                    </div>

                    <Link
                        href={route('contacts.show', contact.id)}
                        className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition"
                    >
                        <Eye className="h-3.5 w-3.5" />
                        <span>View 360 Profile</span>
                    </Link>
                </div>
            }
        >
            <Head title={`Edit ${contact.full_name} - Bamcom AI CRM`} />

            <div className="max-w-4xl mx-auto">
                <form onSubmit={submit} className="space-y-6">
                    {/* Basic & Demographic Details */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                            <div className="flex items-center gap-2.5">
                                <Contact className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Contact Demographics
                                </h3>
                            </div>
                            <span className="text-[11px] font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-slate-500">
                                UUID: {contact.uuid}
                            </span>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {/* First Name */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    First Name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.first_name}
                                    onChange={(e) => setData('first_name', e.target.value)}
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.first_name && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.first_name}</p>}
                            </div>

                            {/* Last Name */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Last Name / Surname
                                </label>
                                <input
                                    type="text"
                                    value={data.last_name}
                                    onChange={(e) => setData('last_name', e.target.value)}
                                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                />
                                {errors.last_name && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.last_name}</p>}
                            </div>

                            {/* Phone */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5 flex items-center justify-between">
                                    <span>Phone Number <span className="text-red-500">*</span></span>
                                    <span className="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold lowercase">E.164 auto-normalized</span>
                                </label>
                                <div className="relative">
                                    <Phone className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                                    <input
                                        type="text"
                                        required
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        className="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm font-mono text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                    />
                                </div>
                                {errors.phone && <p className="text-red-500 text-xs mt-1.5 font-medium flex items-center gap-1"><AlertCircle className="h-3.5 w-3.5 flex-shrink-0" /> {errors.phone}</p>}
                            </div>

                            {/* Email */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Email Address
                                </label>
                                <div className="relative">
                                    <Mail className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                    />
                                </div>
                                {errors.email && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.email}</p>}
                            </div>

                            {/* Location */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Location / City
                                </label>
                                <div className="relative">
                                    <MapPin className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                                    <input
                                        type="text"
                                        value={data.location}
                                        onChange={(e) => setData('location', e.target.value)}
                                        className="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                    />
                                </div>
                                {errors.location && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.location}</p>}
                            </div>

                            {/* Occupation */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Occupation / Industry
                                </label>
                                <div className="relative">
                                    <Briefcase className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                                    <input
                                        type="text"
                                        value={data.occupation}
                                        onChange={(e) => setData('occupation', e.target.value)}
                                        className="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                    />
                                </div>
                                {errors.occupation && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.occupation}</p>}
                            </div>
                        </div>
                    </div>

                    {/* CRM Classification & Sales Ownership */}
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center gap-2.5 pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                            <Sparkles className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Lifecycle Status & Ownership
                            </h3>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {/* Contact Lifecycle Status */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Lifecycle Status <span className="text-red-500">*</span>
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

                            {/* Lead Source */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Lead Acquisition Source <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={data.lead_source}
                                    onChange={(e) => setData('lead_source', e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    {leadSources?.map((source) => (
                                        <option key={source.value} value={source.value}>{source.label}</option>
                                    ))}
                                </select>
                                {errors.lead_source && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.lead_source}</p>}
                            </div>

                            {/* Assigned Agent */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Assigned Sales Agent / Rep
                                </label>
                                <select
                                    value={data.assigned_user_id}
                                    onChange={(e) => setData('assigned_user_id', e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option value="">-- Unassigned --</option>
                                    {users?.map((user) => (
                                        <option key={user.id} value={user.id}>{user.name} ({user.role})</option>
                                    ))}
                                </select>
                                {errors.assigned_user_id && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.assigned_user_id}</p>}
                            </div>

                            {/* Preferred Language */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                    Preferred Language
                                </label>
                                <select
                                    value={data.preferred_language}
                                    onChange={(e) => setData('preferred_language', e.target.value)}
                                    className="w-full py-2.5 px-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option value="en">English</option>
                                    <option value="yo">Yoruba</option>
                                    <option value="ha">Hausa</option>
                                    <option value="ig">Igbo</option>
                                    <option value="fr">French</option>
                                </select>
                                {errors.preferred_language && <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.preferred_language}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href={route('contacts.show', contact.id)}
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
                            <span>{processing ? 'Saving Changes...' : 'Update Contact'}</span>
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
