import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    MapPin, 
    Plus, 
    Search, 
    Building2, 
    ShieldCheck, 
    Edit, 
    Trash2, 
    Eye, 
    RotateCcw,
    Layers,
    Compass
} from 'lucide-react';
import { useState } from 'react';

export default function Index({ estates, filters }) {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('estates.index'), { search: search || undefined }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        router.get(route('estates.index'));
    };

    const handleDelete = (estate) => {
        if (confirm(`Are you sure you want to delete estate "${estate.name}"?`)) {
            router.delete(route('estates.destroy', estate.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 bg-teal-600/10 dark:bg-teal-500/20 text-teal-600 dark:text-teal-400 rounded-xl">
                            <MapPin className="w-6 h-6" />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                Estate Developments
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Master-planned communities, commercial corridors, and residential layout schemes.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route('properties.index')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors border border-slate-200 dark:border-slate-700"
                        >
                            <Building2 className="w-4 h-4" />
                            View All Properties
                        </Link>

                        {can('properties.create') && (
                            <Link
                                href={route('estates.create')}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-teal-600 hover:bg-teal-700 text-white shadow-sm transition-colors"
                            >
                                <Plus className="w-4 h-4" />
                                Add Estate
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Estate Developments" />

            <div className="py-6 space-y-6">
                {/* Search Bar */}
                <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between gap-3">
                    <form onSubmit={handleSearch} className="flex-1 max-w-md flex items-center gap-2">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search by name, location, city, state, or title..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-4 py-2 text-xs font-semibold rounded-lg bg-teal-600 hover:bg-teal-700 text-white transition-colors"
                        >
                            Search
                        </button>
                        {search && (
                            <button
                                type="button"
                                onClick={handleReset}
                                className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                title="Reset"
                            >
                                <RotateCcw className="w-4 h-4" />
                            </button>
                        )}
                    </form>

                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {estates.total} Registered Estates
                    </div>
                </div>

                {/* Estates Cards Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {estates.data?.length > 0 ? (
                        estates.data.map((estate) => {
                            const coverImg = estate.cover_image || 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=800&q=80';

                            return (
                                <div
                                    key={estate.id}
                                    className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between"
                                >
                                    <div>
                                        <div className="relative h-44 w-full bg-slate-950 overflow-hidden">
                                            <img
                                                src={coverImg}
                                                alt={estate.name}
                                                className="w-full h-full object-cover opacity-90"
                                            />
                                            <div className="absolute top-3 left-3">
                                                <span className="text-[11px] font-bold px-2.5 py-1 rounded-md bg-teal-600 text-white shadow-sm">
                                                    {estate.properties_count || 0} Listed Plots
                                                </span>
                                            </div>
                                            <div className="absolute top-3 right-3">
                                                <span className="text-[11px] font-semibold px-2 py-0.5 rounded-md uppercase tracking-wider bg-black/60 backdrop-blur-sm text-emerald-400 border border-white/10">
                                                    {estate.status}
                                                </span>
                                            </div>
                                            <div className="absolute bottom-2 left-3 right-3 text-white">
                                                <span className="text-xs font-medium drop-shadow flex items-center gap-1">
                                                    <Compass className="w-3.5 h-3.5 text-teal-400" />
                                                    {estate.city || estate.location}, {estate.state}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="p-5 space-y-3">
                                            <div>
                                                <Link
                                                    href={route('estates.show', estate.id)}
                                                    className="text-base font-bold text-slate-900 dark:text-white hover:text-teal-600 dark:hover:text-teal-400 transition-colors line-clamp-1"
                                                >
                                                    {estate.name}
                                                </Link>
                                                <div className="mt-1 flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                                    <ShieldCheck className="w-3.5 h-3.5 shrink-0" />
                                                    <span className="truncate">{estate.title_document}</span>
                                                </div>
                                            </div>

                                            <p className="text-xs text-slate-600 dark:text-slate-300 line-clamp-2 leading-relaxed">
                                                {estate.description || 'Master-planned luxury residential layout scheme with complete infrastructure.'}
                                            </p>

                                            {estate.landmarks && (
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1">
                                                    <strong>Landmarks:</strong> {estate.landmarks}
                                                </div>
                                            )}

                                            {estate.total_land_size && (
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                    <strong>Total Land Size:</strong> {estate.total_land_size}
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="px-5 py-3.5 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                                        <Link
                                            href={route('estates.show', estate.id)}
                                            className="inline-flex items-center gap-1 text-xs font-semibold text-teal-600 dark:text-teal-400 hover:underline"
                                        >
                                            <Eye className="w-3.5 h-3.5" />
                                            View Layout & Plots
                                        </Link>

                                        <div className="flex items-center gap-1">
                                            {can('properties.edit') && (
                                                <Link
                                                    href={route('estates.edit', estate.id)}
                                                    className="p-1.5 text-slate-500 hover:text-slate-900 dark:hover:text-white rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                                                    title="Edit Estate"
                                                >
                                                    <Edit className="w-3.5 h-3.5" />
                                                </Link>
                                            )}
                                            {can('properties.delete') && (
                                                <button
                                                    onClick={() => handleDelete(estate)}
                                                    className="p-1.5 text-rose-500 hover:text-rose-700 rounded hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                                                    title="Delete Estate"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    ) : (
                        <div className="col-span-full py-12 text-center bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                            <MapPin className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                            <h3 className="text-base font-semibold text-slate-900 dark:text-white">No estates found</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Click "Add Estate" to register an estate development.
                            </p>
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {estates.links?.length > 3 && (
                    <div className="flex items-center justify-between pt-4">
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            Showing {estates.from || 0} to {estates.to || 0} of {estates.total} estates
                        </div>
                        <div className="flex items-center gap-1">
                            {estates.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveScroll
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`px-3 py-1 text-xs rounded-md font-medium transition-colors ${
                                        link.active
                                            ? 'bg-teal-600 text-white'
                                            : !link.url
                                            ? 'text-slate-400 cursor-not-allowed'
                                            : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    }`}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
