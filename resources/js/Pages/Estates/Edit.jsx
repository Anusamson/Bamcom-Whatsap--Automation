import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { MapPin, ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';

export default function Edit({ estate, titleDocuments }) {
    const { data, setData, put, processing, errors } = useForm({
        name: estate.name || '',
        location: estate.location || '',
        city: estate.city || '',
        state: estate.state || '',
        landmarks: estate.landmarks || '',
        title_document: estate.title_document || '',
        total_land_size: estate.total_land_size || '',
        status: estate.status || 'active',
        cover_image: estate.cover_image || '',
        description: estate.description || '',
        features: estate.features || [],
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
        put(route('estates.update', estate.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href={route('estates.show', estate.id)}
                        className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                            Edit Estate: {estate.name}
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Update estate master-plan attributes and legal title status.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Edit ${estate.name}`} />

            <div className="py-6 max-w-4xl mx-auto">
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            <MapPin className="w-4 h-4 text-teal-500" />
                            Estate Details
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Estate Name *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
                                />
                                {errors.name && <p className="text-xs text-rose-500 mt-1">{errors.name}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Full Location / Corridor *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.location}
                                    onChange={(e) => setData('location', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
                                />
                                {errors.location && <p className="text-xs text-rose-500 mt-1">{errors.location}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    City / LGA
                                </label>
                                <input
                                    type="text"
                                    value={data.city}
                                    onChange={(e) => setData('city', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
                                />
                                {errors.city && <p className="text-xs text-rose-500 mt-1">{errors.city}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    State *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.state}
                                    onChange={(e) => setData('state', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
                                />
                                {errors.state && <p className="text-xs text-rose-500 mt-1">{errors.state}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Legal Title Document *
                                </label>
                                <input
                                    type="text"
                                    required
                                    list="estate-title-documents-list-edit"
                                    value={data.title_document}
                                    onChange={(e) => setData('title_document', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
                                />
                                <datalist id="estate-title-documents-list-edit">
                                    {titleDocuments.map((doc) => (
                                        <option key={doc.value} value={doc.label} />
                                    ))}
                                </datalist>
                                {errors.title_document && <p className="text-xs text-rose-500 mt-1">{errors.title_document}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Total Land Coverage
                                </label>
                                <input
                                    type="text"
                                    value={data.total_land_size}
                                    onChange={(e) => setData('total_land_size', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
                                />
                                {errors.total_land_size && <p className="text-xs text-rose-500 mt-1">{errors.total_land_size}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Prominent Landmarks / Vicinity
                                </label>
                                <input
                                    type="text"
                                    value={data.landmarks}
                                    onChange={(e) => setData('landmarks', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
                                />
                                {errors.landmarks && <p className="text-xs text-rose-500 mt-1">{errors.landmarks}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Cover Photo URL
                                </label>
                                <input
                                    type="url"
                                    value={data.cover_image}
                                    onChange={(e) => setData('cover_image', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
                                />
                                {errors.cover_image && <p className="text-xs text-rose-500 mt-1">{errors.cover_image}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Estate Description
                                </label>
                                <textarea
                                    rows="4"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
                                />
                                {errors.description && <p className="text-xs text-rose-500 mt-1">{errors.description}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Infrastructure & Features
                                </label>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={featureInput}
                                        onChange={(e) => setFeatureInput(e.target.value)}
                                        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleAddFeature(); } }}
                                        className="flex-1 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-teal-500"
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
                                            className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-md bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800"
                                        >
                                            {feat}
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveFeature(i)}
                                                className="text-teal-500 hover:text-rose-500 text-xs font-bold ml-1"
                                            >
                                                ×
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3">
                        <Link
                            href={route('estates.show', estate.id)}
                            className="px-4 py-2 text-xs font-semibold rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-6 py-2.5 text-xs font-semibold rounded-lg bg-teal-600 hover:bg-teal-700 text-white transition-colors shadow-sm disabled:opacity-50"
                        >
                            <Save className="w-4 h-4" />
                            {processing ? 'Saving...' : 'Update Estate'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
