import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    Building2, 
    Plus, 
    Search, 
    Filter, 
    Edit, 
    Trash2, 
    Eye, 
    MapPin, 
    Tag, 
    RotateCcw,
    Sparkles,
    CheckCircle2,
    Share2,
    DollarSign,
    Layers,
    FileText,
    Grid,
    List,
    Percent,
    ExternalLink
} from 'lucide-react';
import { useState } from 'react';

export default function Index({ 
    properties, 
    filters, 
    estates, 
    metrics, 
    propertyTypes, 
    availabilities, 
    titleDocuments 
}) {
    const { auth, flash } = usePage().props;
    const permissions = auth.user?.permissions || [];
    const isSuperAdmin = auth.user?.is_super_admin;
    const can = (permission) => isSuperAdmin || permissions.includes(permission);

    const [viewMode, setViewMode] = useState('grid');
    const [search, setSearch] = useState(filters.search || '');
    const [estateId, setEstateId] = useState(filters.estate_id || '');
    const [propertyType, setPropertyType] = useState(filters.property_type || '');
    const [availability, setAvailability] = useState(filters.availability || '');
    const [titleDocument, setTitleDocument] = useState(filters.title_document || '');
    const [minPrice, setMinPrice] = useState(filters.min_price || '');
    const [maxPrice, setMaxPrice] = useState(filters.max_price || '');

    const handleFilter = (e) => {
        e?.preventDefault();
        router.get(route('properties.index'), {
            search: search || undefined,
            estate_id: estateId || undefined,
            property_type: propertyType || undefined,
            availability: availability || undefined,
            title_document: titleDocument || undefined,
            min_price: minPrice || undefined,
            max_price: maxPrice || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        setEstateId('');
        setPropertyType('');
        setAvailability('');
        setTitleDocument('');
        setMinPrice('');
        setMaxPrice('');
        router.get(route('properties.index'));
    };

    const handleDelete = (property) => {
        if (confirm(`Are you sure you want to delete property "${property.title}"?`)) {
            router.delete(route('properties.destroy', property.id));
        }
    };

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '₦0.00';
        return '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 bg-emerald-600/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded-xl">
                                <Building2 className="w-6 h-6" />
                            </div>
                            <div>
                                <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    Property Inventory & AI Catalog
                                    <span className="text-xs px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 font-medium border border-emerald-300 dark:border-emerald-800">
                                        Authoritative AI Source
                                    </span>
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Manage estate plots, luxury homes, live pricing tiers, and AI WhatsApp knowledge bases.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
                            <button
                                onClick={() => setViewMode('grid')}
                                className={`p-1.5 rounded-md text-xs font-medium transition-colors ${
                                    viewMode === 'grid'
                                        ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                        : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                                }`}
                                title="Grid View"
                            >
                                <Grid className="w-4 h-4" />
                            </button>
                            <button
                                onClick={() => setViewMode('table')}
                                className={`p-1.5 rounded-md text-xs font-medium transition-colors ${
                                    viewMode === 'table'
                                        ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                        : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                                }`}
                                title="Table View"
                            >
                                <List className="w-4 h-4" />
                            </button>
                        </div>

                        <Link
                            href={route('estates.index')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors border border-slate-200 dark:border-slate-700"
                        >
                            <MapPin className="w-4 h-4" />
                            Estates ({metrics.total_estates})
                        </Link>

                        {can('properties.create') && (
                            <Link
                                href={route('properties.create')}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-colors"
                            >
                                <Plus className="w-4 h-4" />
                                Add Property
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Properties Catalog & Inventory" />

            <div className="py-6 space-y-6">
                {/* Metric Summary Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-lg">
                            <Building2 className="w-5 h-5" />
                        </div>
                        <div>
                            <div className="text-2xl font-bold text-slate-900 dark:text-white">
                                {metrics.total_properties}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">Total Properties</div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-lg">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                        <div>
                            <div className="text-2xl font-bold text-slate-900 dark:text-white">
                                {metrics.available_units}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">Available Units in Stock</div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 rounded-lg">
                            <Tag className="w-5 h-5" />
                        </div>
                        <div>
                            <div className="text-2xl font-bold text-slate-900 dark:text-white">
                                {metrics.sold_units}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">Sold Out Listings</div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-teal-50 dark:bg-teal-950/40 text-teal-600 dark:text-teal-400 rounded-lg">
                            <MapPin className="w-5 h-5" />
                        </div>
                        <div>
                            <div className="text-2xl font-bold text-slate-900 dark:text-white">
                                {metrics.total_estates}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">Active Estates</div>
                        </div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <form onSubmit={handleFilter} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div className="relative">
                                <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                                <input
                                    type="text"
                                    placeholder="Search title, plot size, location..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
                                />
                            </div>

                            <div>
                                <select
                                    value={estateId}
                                    onChange={(e) => setEstateId(e.target.value)}
                                    className="w-full py-2 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
                                >
                                    <option value="">All Estates</option>
                                    {estates.map((est) => (
                                        <option key={est.id} value={est.id}>{est.name} ({est.city || est.state})</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <select
                                    value={propertyType}
                                    onChange={(e) => setPropertyType(e.target.value)}
                                    className="w-full py-2 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
                                >
                                    <option value="">All Property Types</option>
                                    {propertyTypes.map((type) => (
                                        <option key={type.value} value={type.value}>{type.label}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <select
                                    value={availability}
                                    onChange={(e) => setAvailability(e.target.value)}
                                    className="w-full py-2 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
                                >
                                    <option value="">All Availability</option>
                                    {availabilities.map((avail) => (
                                        <option key={avail.value} value={avail.value}>{avail.label}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                            <div className="flex flex-wrap items-center gap-3">
                                <select
                                    value={titleDocument}
                                    onChange={(e) => setTitleDocument(e.target.value)}
                                    className="py-1.5 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
                                >
                                    <option value="">All Title Documents</option>
                                    {titleDocuments.map((doc) => (
                                        <option key={doc.value} value={doc.label}>{doc.label}</option>
                                    ))}
                                </select>

                                <input
                                    type="number"
                                    placeholder="Min Price (₦)"
                                    value={minPrice}
                                    onChange={(e) => setMinPrice(e.target.value)}
                                    className="w-32 py-1.5 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
                                />

                                <input
                                    type="number"
                                    placeholder="Max Price (₦)"
                                    value={maxPrice}
                                    onChange={(e) => setMaxPrice(e.target.value)}
                                    className="w-32 py-1.5 px-3 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                >
                                    <RotateCcw className="w-3.5 h-3.5" />
                                    Reset
                                </button>

                                <button
                                    type="submit"
                                    className="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-white dark:bg-emerald-600 dark:hover:bg-emerald-700 transition-colors"
                                >
                                    <Filter className="w-3.5 h-3.5" />
                                    Apply Filters
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {/* Grid View of Properties */}
                {viewMode === 'grid' && (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {properties.data?.length > 0 ? (
                            properties.data.map((property) => {
                                const heroImg = property.primary_media?.file_url || property.primary_media?.file_path || 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=800&q=80';
                                const hasPromo = property.promo_price && property.promo_price < property.regular_price;

                                return (
                                    <div
                                        key={property.id}
                                        className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col"
                                    >
                                        {/* Image Header with Badges */}
                                        <div className="relative h-48 w-full bg-slate-100 dark:bg-slate-900 overflow-hidden">
                                            <img
                                                src={heroImg}
                                                alt={property.title}
                                                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            />
                                            <div className="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                                <span className="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur-sm text-white">
                                                    {property.plot_size}
                                                </span>
                                                {property.estate && (
                                                    <span className="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-emerald-600/90 backdrop-blur-sm text-white">
                                                        {property.estate.name}
                                                    </span>
                                                )}
                                            </div>

                                            <div className="absolute top-3 right-3 flex flex-col items-end gap-1.5">
                                                <span className={`text-[11px] font-semibold px-2 py-0.5 rounded-md uppercase tracking-wider ${
                                                    property.availability === 'available'
                                                        ? 'bg-emerald-500 text-white'
                                                        : property.availability === 'sold_out'
                                                        ? 'bg-rose-500 text-white'
                                                        : 'bg-amber-500 text-white'
                                                }`}>
                                                    {property.availability.replace('_', ' ')}
                                                </span>
                                                {hasPromo && (
                                                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-rose-600 text-white animate-pulse">
                                                        PROMO ACTIVE
                                                    </span>
                                                )}
                                            </div>

                                            <div className="absolute bottom-2 left-3 right-3 text-white">
                                                <div className="text-xs flex items-center gap-1 drop-shadow-md">
                                                    <MapPin className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                                                    <span className="truncate">{property.location || property.estate?.location}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Content Body */}
                                        <div className="p-4 flex-1 flex flex-col justify-between space-y-4">
                                            <div>
                                                <Link
                                                    href={route('properties.show', property.id)}
                                                    className="font-bold text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 line-clamp-1 transition-colors"
                                                >
                                                    {property.title}
                                                </Link>

                                                <div className="mt-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                    <FileText className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                    <span className="truncate font-medium text-slate-700 dark:text-slate-300">
                                                        Title: {property.title_document || property.estate?.title_document || 'Deed of Assignment'}
                                                    </span>
                                                </div>

                                                {/* Price Highlights */}
                                                <div className="mt-3 p-3 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-100 dark:border-slate-800">
                                                    <div className="flex items-baseline justify-between">
                                                        <div>
                                                            <div className="text-[11px] uppercase tracking-wider text-slate-400 font-semibold">
                                                                {hasPromo ? 'Special Promo Price' : 'Outright Price'}
                                                            </div>
                                                            <div className="text-lg font-extrabold text-emerald-600 dark:text-emerald-400">
                                                                {formatCurrency(property.effective_price)}
                                                            </div>
                                                        </div>
                                                        {hasPromo && (
                                                            <div className="text-right">
                                                                <div className="text-[11px] text-slate-400 line-through">
                                                                    {formatCurrency(property.regular_price)}
                                                                </div>
                                                                <div className="text-[11px] font-bold text-rose-500">
                                                                    Save {formatCurrency(property.regular_price - property.promo_price)}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div className="mt-2 pt-2 border-t border-slate-200/60 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
                                                        <span>Deposit: <strong>{formatCurrency(property.initial_deposit)}</strong></span>
                                                        <span className="text-emerald-600 dark:text-emerald-400 font-medium">
                                                            {property.available_units} units left
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Action Buttons */}
                                            <div className="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2">
                                                <Link
                                                    href={route('properties.show', property.id)}
                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline"
                                                >
                                                    <Eye className="w-3.5 h-3.5" />
                                                    360 Profile & AI Pitch
                                                </Link>

                                                <div className="flex items-center gap-1">
                                                    {can('properties.edit') && (
                                                        <Link
                                                            href={route('properties.edit', property.id)}
                                                            className="p-1.5 text-slate-500 hover:text-slate-900 dark:hover:text-white rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                                            title="Edit Property"
                                                        >
                                                            <Edit className="w-3.5 h-3.5" />
                                                        </Link>
                                                    )}
                                                    {can('properties.delete') && (
                                                        <button
                                                            onClick={() => handleDelete(property)}
                                                            className="p-1.5 text-rose-500 hover:text-rose-700 rounded hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                                                            title="Delete Property"
                                                        >
                                                            <Trash2 className="w-3.5 h-3.5" />
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <div className="col-span-full py-12 text-center bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <Building2 className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                                <h3 className="text-base font-semibold text-slate-900 dark:text-white">No properties found</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                                    No properties match your active filter criteria. Try resetting filters or adding a new inventory plot.
                                </p>
                            </div>
                        )}
                    </div>
                )}

                {/* Table View of Properties */}
                {viewMode === 'table' && (
                    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th className="p-3.5">Property / Estate</th>
                                        <th className="p-3.5">Type & Size</th>
                                        <th className="p-3.5">Legal Title</th>
                                        <th className="p-3.5">Price & Savings</th>
                                        <th className="p-3.5">Initial Deposit</th>
                                        <th className="p-3.5">Stock</th>
                                        <th className="p-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60">
                                    {properties.data?.length > 0 ? (
                                        properties.data.map((property) => (
                                            <tr key={property.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-750 transition-colors">
                                                <td className="p-3.5">
                                                    <Link
                                                        href={route('properties.show', property.id)}
                                                        className="font-bold text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 block"
                                                    >
                                                        {property.title}
                                                    </Link>
                                                    <span className="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-0.5">
                                                        <MapPin className="w-3 h-3 text-emerald-500" />
                                                        {property.estate?.name || property.location}
                                                    </span>
                                                </td>

                                                <td className="p-3.5">
                                                    <span className="font-semibold text-slate-800 dark:text-slate-200 capitalize">
                                                        {property.property_type}
                                                    </span>
                                                    <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                        {property.plot_size}
                                                    </div>
                                                </td>

                                                <td className="p-3.5">
                                                    <span className="px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                                        {property.title_document || property.estate?.title_document || 'Deed'}
                                                    </span>
                                                </td>

                                                <td className="p-3.5">
                                                    <div className="font-extrabold text-emerald-600 dark:text-emerald-400">
                                                        {formatCurrency(property.effective_price)}
                                                    </div>
                                                    {property.promo_price && property.promo_price < property.regular_price && (
                                                        <div className="text-[10px] text-slate-400 line-through">
                                                            {formatCurrency(property.regular_price)}
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="p-3.5 font-medium text-slate-700 dark:text-slate-300">
                                                    {formatCurrency(property.initial_deposit)}
                                                </td>

                                                <td className="p-3.5">
                                                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                                                        property.availability === 'available'
                                                            ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300'
                                                            : 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300'
                                                    }`}>
                                                        {property.available_units} units ({property.availability})
                                                    </span>
                                                </td>

                                                <td className="p-3.5 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <Link
                                                            href={route('properties.show', property.id)}
                                                            className="p-1.5 text-slate-500 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                                                            title="View 360"
                                                        >
                                                            <Eye className="w-4 h-4" />
                                                        </Link>
                                                        {can('properties.edit') && (
                                                            <Link
                                                                href={route('properties.edit', property.id)}
                                                                className="p-1.5 text-slate-500 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                                                title="Edit"
                                                            >
                                                                <Edit className="w-4 h-4" />
                                                            </Link>
                                                        )}
                                                        {can('properties.delete') && (
                                                            <button
                                                                onClick={() => handleDelete(property)}
                                                                className="p-1.5 text-rose-500 hover:text-rose-700 transition-colors"
                                                                title="Delete"
                                                            >
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="7" className="p-8 text-center text-slate-400">
                                                No properties found matching your criteria.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Pagination */}
                {properties.links?.length > 3 && (
                    <div className="flex items-center justify-between pt-4">
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            Showing {properties.from || 0} to {properties.to || 0} of {properties.total} properties
                        </div>
                        <div className="flex items-center gap-1">
                            {properties.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveScroll
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`px-3 py-1 text-xs rounded-md font-medium transition-colors ${
                                        link.active
                                            ? 'bg-emerald-600 text-white'
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
