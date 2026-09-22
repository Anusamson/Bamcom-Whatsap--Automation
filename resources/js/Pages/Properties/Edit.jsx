import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, ArrowLeft, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Edit({ property, estates, promotions, propertyTypes, availabilities, titleDocuments }) {
    const { data, setData, put, processing, errors } = useForm({
        estate_id: property.estate_id || '',
        promotion_id: property.promotion_id || '',
        title: property.title || '',
        property_type: property.property_type || 'land',
        plot_size: property.plot_size || '',
        plot_number: property.plot_number || '',
        location: property.location || '',
        regular_price: property.regular_price || '',
        promo_price: property.promo_price || '',
        initial_deposit: property.initial_deposit || '',
        title_document: property.title_document || '',
        availability: property.availability || 'available',
        available_units: property.available_units || 1,
        total_units: property.total_units || 1,
        status: property.status || 'published',
        is_featured: Boolean(property.is_featured),
        cover_image_url: property.primary_media?.file_url || property.primary_media?.file_path || '',
        description: property.description || '',
        features: property.features || [],
    });

    const [featureInput, setFeatureInput] = useState('');

    const handleAddFeature = () => {
        if (featureInput.trim()) {
            setData('features', [...data.features, featureInput.trim()]);
            setFeatureInput('');
        }
    };

    const handleRemoveFeature = (index) => {
        setData('features', data.features.filter((_, i) => i !== index));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('properties.update', property.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href={route('properties.show', property.id)}
                        className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                            Edit Property: {property.title}
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Update pricing, plot availability, and legal title documents.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Edit ${property.title}`} />

            <div className="py-6 max-w-4xl mx-auto">
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* General Info */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            <Building2 className="w-4 h-4 text-emerald-500" />
                            General Information
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Property Title *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.title && <p className="text-xs text-rose-500 mt-1">{errors.title}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Belonging Estate Development
                                </label>
                                <select
                                    value={data.estate_id}
                                    onChange={(e) => setData('estate_id', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                >
                                    <option value="">Standalone / No Estate</option>
                                    {estates.map((est) => (
                                        <option key={est.id} value={est.id}>{est.name} ({est.location})</option>
                                    ))}
                                </select>
                                {errors.estate_id && <p className="text-xs text-rose-500 mt-1">{errors.estate_id}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Property Type *
                                </label>
                                <select
                                    value={data.property_type}
                                    onChange={(e) => setData('property_type', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                >
                                    {propertyTypes.map((type) => (
                                        <option key={type.value} value={type.value}>{type.label}</option>
                                    ))}
                                </select>
                                {errors.property_type && <p className="text-xs text-rose-500 mt-1">{errors.property_type}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Plot / Land Size *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.plot_size}
                                    onChange={(e) => setData('plot_size', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.plot_size && <p className="text-xs text-rose-500 mt-1">{errors.plot_size}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Plot Number / Identifier
                                </label>
                                <input
                                    type="text"
                                    value={data.plot_number}
                                    onChange={(e) => setData('plot_number', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.plot_number && <p className="text-xs text-rose-500 mt-1">{errors.plot_number}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Specific Location / Address
                                </label>
                                <input
                                    type="text"
                                    value={data.location}
                                    onChange={(e) => setData('location', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.location && <p className="text-xs text-rose-500 mt-1">{errors.location}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Pricing */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Pricing, Deposits & Title
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Regular Outright Price (₦) *
                                </label>
                                <input
                                    type="number"
                                    required
                                    min="0"
                                    step="1000"
                                    value={data.regular_price}
                                    onChange={(e) => setData('regular_price', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.regular_price && <p className="text-xs text-rose-500 mt-1">{errors.regular_price}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Promotional Price (₦) (Optional)
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1000"
                                    value={data.promo_price}
                                    onChange={(e) => setData('promo_price', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.promo_price && <p className="text-xs text-rose-500 mt-1">{errors.promo_price}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Initial Deposit (₦)
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1000"
                                    value={data.initial_deposit}
                                    onChange={(e) => setData('initial_deposit', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.initial_deposit && <p className="text-xs text-rose-500 mt-1">{errors.initial_deposit}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Legal Title Document *
                                </label>
                                <input
                                    type="text"
                                    list="title-documents-list-edit"
                                    value={data.title_document}
                                    onChange={(e) => setData('title_document', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                <datalist id="title-documents-list-edit">
                                    {titleDocuments.map((doc) => (
                                        <option key={doc.value} value={doc.label} />
                                    ))}
                                </datalist>
                                {errors.title_document && <p className="text-xs text-rose-500 mt-1">{errors.title_document}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Available Units in Stock *
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={data.available_units}
                                    onChange={(e) => setData('available_units', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.available_units && <p className="text-xs text-rose-500 mt-1">{errors.available_units}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Availability Status *
                                </label>
                                <select
                                    value={data.availability}
                                    onChange={(e) => setData('availability', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                >
                                    {availabilities.map((avail) => (
                                        <option key={avail.value} value={avail.value}>{avail.label}</option>
                                    ))}
                                </select>
                                {errors.availability && <p className="text-xs text-rose-500 mt-1">{errors.availability}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Media & Features */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Description, Media & Highlights
                        </h3>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Cover Photo URL
                                </label>
                                <input
                                    type="url"
                                    value={data.cover_image_url}
                                    onChange={(e) => setData('cover_image_url', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.cover_image_url && <p className="text-xs text-rose-500 mt-1">{errors.cover_image_url}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Description
                                </label>
                                <textarea
                                    rows="4"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.description && <p className="text-xs text-rose-500 mt-1">{errors.description}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Key Features & Selling Points
                                </label>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={featureInput}
                                        onChange={(e) => setFeatureInput(e.target.value)}
                                        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleAddFeature(); } }}
                                        className="flex-1 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                    />
                                    <button
                                        type="button"
                                        onClick={handleAddFeature}
                                        className="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-white transition-colors"
                                    >
                                        Add
                                    </button>
                                </div>

                                <div className="flex flex-wrap gap-2 mt-2">
                                    {data.features.map((feat, i) => (
                                        <span
                                            key={i}
                                            className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-md bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                        >
                                            {feat}
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveFeature(i)}
                                                className="text-emerald-500 hover:text-rose-500 text-xs font-bold ml-1"
                                            >
                                                ×
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="pt-2 flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_featured_edit"
                                    checked={data.is_featured}
                                    onChange={(e) => setData('is_featured', e.target.checked)}
                                    className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <label htmlFor="is_featured_edit" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Feature this property on catalog banner and WhatsApp AI recommendations
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3">
                        <Link
                            href={route('properties.show', property.id)}
                            className="px-4 py-2 text-xs font-semibold rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-6 py-2.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors shadow-sm disabled:opacity-50"
                        >
                            <Save className="w-4 h-4" />
                            {processing ? 'Saving...' : 'Update Property'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
