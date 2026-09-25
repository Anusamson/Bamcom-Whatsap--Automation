import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, ArrowLeft, Save, Sparkles, CheckCircle2, Upload, Trash2 } from 'lucide-react';
import { useState, useRef } from 'react';

export default function Create({ estates, promotions, propertyTypes, availabilities, titleDocuments }) {
    const fileInputRef = useRef(null);
    const [imagePreview, setImagePreview] = useState(null);
    const [fileError, setFileError] = useState('');
    const [isDragging, setIsDragging] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        estate_id: '',
        promotion_id: '',
        title: '',
        property_type: 'land',
        plot_size: '500sqm',
        plot_number: '',
        location: '',
        regular_price: '',
        promo_price: '',
        initial_deposit: '',
        title_document: 'Governor\'s Consent',
        availability: 'available',
        available_units: 1,
        total_units: 1,
        status: 'published',
        is_featured: false,
        cover_image: null,
        description: '',
        features: ['100% Dry Table Land', 'Gated Security', 'Paved Road Network'],
    });

    const [featureInput, setFeatureInput] = useState('');

    const validateAndSetFile = (file) => {
        setFileError('');
        if (!file) return;

        const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!validTypes.includes(file.type)) {
            setFileError('Please upload an image in JPEG or PNG format.');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setFileError('The cover photo may not be greater than 10MB.');
            return;
        }

        setData('cover_image', file);
        const reader = new FileReader();
        reader.onloadend = () => {
            setImagePreview(reader.result);
        };
        reader.readAsDataURL(file);
    };

    const handleFileChange = (e) => {
        const file = e.target.files?.[0];
        if (file) {
            validateAndSetFile(file);
        }
    };

    const handleRemoveImage = () => {
        setData('cover_image', null);
        setImagePreview(null);
        setFileError('');
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        setIsDragging(true);
    };

    const handleDragLeave = (e) => {
        e.preventDefault();
        setIsDragging(false);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setIsDragging(false);
        const file = e.dataTransfer?.files?.[0];
        if (file) {
            validateAndSetFile(file);
        }
    };

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
        post(route('properties.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href={route('properties.index')}
                        className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                            Add New Property to Inventory
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Register a new estate plot or housing unit into the authoritative database.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Add Property" />

            <div className="py-6 max-w-4xl mx-auto">
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Info Section */}
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
                                    placeholder="e.g. Grace Haven 500sqm Prime Dry Land Plot"
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
                                    placeholder="e.g. 500sqm, 300sqm, 1,000sqm, 1 Acre"
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
                                    placeholder="e.g. GH-BLK-04-P12"
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
                                    placeholder="e.g. Coastal Road corridor, along Eleko Junction, Ibeju-Lekki, Lagos"
                                    value={data.location}
                                    onChange={(e) => setData('location', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.location && <p className="text-xs text-rose-500 mt-1">{errors.location}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Pricing and Payment Plans */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Pricing, Deposits & Discounts
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
                                    placeholder="18000000"
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
                                    placeholder="15000000"
                                    value={data.promo_price}
                                    onChange={(e) => setData('promo_price', e.target.value)}
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                {errors.promo_price && <p className="text-xs text-rose-500 mt-1">{errors.promo_price}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Initial Deposit (₦) (Optional)
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1000"
                                    placeholder="Auto-computes 20% if left blank"
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
                                    list="title-documents-list"
                                    value={data.title_document}
                                    onChange={(e) => setData('title_document', e.target.value)}
                                    placeholder="e.g. Governor's Consent, C of O, Gazette"
                                    className="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-emerald-500"
                                />
                                <datalist id="title-documents-list">
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

                    {/* Media, Description & Features */}
                    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Description, Media & Highlights
                        </h3>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                    Cover Photo <span className="font-normal text-slate-400 dark:text-slate-500">(JPEG or PNG)</span>
                                </label>

                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg"
                                    onChange={handleFileChange}
                                    className="hidden"
                                />

                                {!imagePreview ? (
                                    <div
                                        onDragOver={handleDragOver}
                                        onDragLeave={handleDragLeave}
                                        onDrop={handleDrop}
                                        onClick={() => fileInputRef.current?.click()}
                                        className={`cursor-pointer border-2 border-dashed rounded-xl p-6 transition-all duration-200 text-center flex flex-col items-center justify-center gap-2.5 ${
                                            isDragging
                                                ? 'border-emerald-500 bg-emerald-50/70 dark:bg-emerald-950/20 ring-2 ring-emerald-500/20'
                                                : 'border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/50 hover:bg-slate-100/70 dark:hover:bg-slate-900 hover:border-emerald-400 dark:hover:border-emerald-600'
                                        }`}
                                    >
                                        <div className="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                            <Upload className="w-6 h-6" />
                                        </div>
                                        <div>
                                            <p className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                                Click to upload cover photo <span className="font-normal text-slate-500 dark:text-slate-400">or drag and drop</span>
                                            </p>
                                            <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                                Picture format: <span className="font-semibold text-emerald-600 dark:text-emerald-400">JPEG, PNG</span> (up to 10MB)
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="relative rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-900 group shadow-sm">
                                        <img
                                            src={imagePreview}
                                            alt="Cover preview"
                                            className="w-full h-56 object-cover"
                                        />
                                        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent flex items-end justify-between p-4">
                                            <div className="text-white space-y-1">
                                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500 text-white shadow-sm">
                                                    <CheckCircle2 className="w-3.5 h-3.5" /> Selected Cover Photo
                                                </span>
                                                {data.cover_image && (
                                                    <p className="text-xs text-slate-200 font-medium truncate max-w-sm">
                                                        {data.cover_image.name} ({((data.cover_image.size || 0) / (1024 * 1024)).toFixed(2)} MB)
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => fileInputRef.current?.click()}
                                                    className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white/95 hover:bg-white text-slate-900 transition-colors shadow-md"
                                                >
                                                    Change Picture
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={handleRemoveImage}
                                                    className="p-1.5 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition-colors shadow-md"
                                                    title="Remove picture"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {(fileError || errors.cover_image) && (
                                    <p className="text-xs text-rose-500 mt-1.5 font-medium">
                                        {fileError || errors.cover_image}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Description
                                </label>
                                <textarea
                                    rows="4"
                                    placeholder="Describe plot elevation, dry topography, proximity to roads and infrastructure..."
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
                                        placeholder="e.g. 100% Dry Land, Solar Lighting, Perimeter Gate"
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
                                    id="is_featured"
                                    checked={data.is_featured}
                                    onChange={(e) => setData('is_featured', e.target.checked)}
                                    className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <label htmlFor="is_featured" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Feature this property on catalog banner and WhatsApp AI recommendation priority
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3">
                        <Link
                            href={route('properties.index')}
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
                            {processing ? 'Saving...' : 'Save & Authorize Property'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
