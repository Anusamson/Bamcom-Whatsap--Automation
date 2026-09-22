import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { 
    MapPin, 
    ArrowLeft, 
    Edit, 
    ShieldCheck, 
    Building2, 
    Plus, 
    CheckCircle2, 
    Compass, 
    Eye,
    Tag
} from 'lucide-react';

export default function Show({ estate, properties }) {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const coverImg = estate.cover_image || 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1200&q=80';

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '₦0.00';
        return '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('estates.index')}
                            className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-xs uppercase tracking-wider font-semibold text-teal-600 dark:text-teal-400">
                                    Estate Profile
                                </span>
                                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300 uppercase">
                                    {estate.status}
                                </span>
                            </div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                {estate.name}
                            </h2>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {can('properties.create') && (
                            <Link
                                href={route('properties.create', { estate_id: estate.id })}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors"
                            >
                                <Plus className="w-4 h-4" />
                                Add Plot to Estate
                            </Link>
                        )}

                        {can('properties.edit') && (
                            <Link
                                href={route('estates.edit', estate.id)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors"
                            >
                                <Edit className="w-4 h-4" />
                                Edit Estate
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`${estate.name} - Estate 360`} />

            <div className="py-6 space-y-6">
                {/* Hero Showcase */}
                <div className="relative h-64 md:h-72 w-full rounded-2xl overflow-hidden bg-slate-950 shadow-sm border border-slate-200 dark:border-slate-700">
                    <img
                        src={coverImg}
                        alt={estate.name}
                        className="w-full h-full object-cover opacity-85"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent" />

                    <div className="absolute bottom-6 left-6 right-6 text-white flex flex-col md:flex-row md:items-end justify-between gap-4">
                        <div className="space-y-1">
                            <h1 className="text-2xl md:text-3xl font-extrabold text-white">
                                {estate.name}
                            </h1>
                            <p className="text-xs text-slate-300 flex items-center gap-1.5">
                                <MapPin className="w-4 h-4 text-teal-400 shrink-0" />
                                {estate.location}, {estate.city || ''} {estate.state}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 bg-black/50 backdrop-blur-md p-3 rounded-xl border border-white/10">
                            <div className="px-3 py-1 bg-teal-600/80 rounded-lg text-xs font-semibold">
                                Title: {estate.title_document}
                            </div>
                            {estate.total_land_size && (
                                <div className="px-3 py-1 bg-white/15 rounded-lg text-xs font-semibold">
                                    Size: {estate.total_land_size}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Details Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Properties listed under this estate */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                    <Building2 className="w-4 h-4 text-emerald-500" />
                                    Property Units in this Estate ({properties?.length || 0})
                                </h3>

                                {can('properties.create') && (
                                    <Link
                                        href={route('properties.create', { estate_id: estate.id })}
                                        className="text-xs font-semibold text-emerald-600 hover:underline flex items-center gap-1"
                                    >
                                        <Plus className="w-3.5 h-3.5" />
                                        Add Unit
                                    </Link>
                                )}
                            </div>

                            {properties?.length > 0 ? (
                                <div className="divide-y divide-slate-100 dark:divide-slate-700/60">
                                    {properties.map((prop) => (
                                        <div key={prop.id} className="py-3.5 flex items-center justify-between gap-4">
                                            <div>
                                                <Link
                                                    href={route('properties.show', prop.id)}
                                                    className="font-bold text-xs text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 block"
                                                >
                                                    {prop.title}
                                                </Link>
                                                <div className="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                                    <span>{prop.plot_size}</span>
                                                    <span>•</span>
                                                    <span className="capitalize">{prop.property_type}</span>
                                                    <span>•</span>
                                                    <span className="text-emerald-600 font-medium">
                                                        {prop.available_units} units left
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="text-right">
                                                <div className="text-sm font-extrabold text-emerald-600 dark:text-emerald-400">
                                                    {formatCurrency(prop.effective_price)}
                                                </div>
                                                <Link
                                                    href={route('properties.show', prop.id)}
                                                    className="text-[11px] text-slate-500 hover:text-emerald-600 inline-flex items-center gap-1 mt-0.5"
                                                >
                                                    <Eye className="w-3 h-3" />
                                                    View Details
                                                </Link>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-xs text-slate-400 py-6 text-center">
                                    No property plots registered under this estate yet. Click "Add Plot to Estate" to list one.
                                </p>
                            )}
                        </div>

                        {/* Description */}
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                            <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                About {estate.name}
                            </h3>
                            <p className="text-xs leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-line">
                                {estate.description || 'Master-planned luxury development scheme.'}
                            </p>
                        </div>
                    </div>

                    {/* Right 1 Col: Highlights & Landmarks */}
                    <div className="space-y-6">
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Legal & Location Profile
                            </h3>

                            <div className="space-y-3 text-xs">
                                <div>
                                    <span className="text-slate-400">Title Document</span>
                                    <div className="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 mt-0.5">
                                        <ShieldCheck className="w-4 h-4" />
                                        {estate.title_document}
                                    </div>
                                </div>

                                {estate.landmarks && (
                                    <div className="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                                        <span className="text-slate-400">Notable Landmarks</span>
                                        <p className="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                                            {estate.landmarks}
                                        </p>
                                    </div>
                                )}

                                <div className="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                                    <span className="text-slate-400">Total Land Coverage</span>
                                    <p className="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                                        {estate.total_land_size || 'N/A'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {estate.features?.length > 0 && (
                            <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    Infrastructure & Amenities
                                </h3>
                                <div className="flex flex-wrap gap-2">
                                    {estate.features.map((feat, idx) => (
                                        <span
                                            key={idx}
                                            className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800"
                                        >
                                            <CheckCircle2 className="w-3 h-3 text-teal-500" />
                                            {feat}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
