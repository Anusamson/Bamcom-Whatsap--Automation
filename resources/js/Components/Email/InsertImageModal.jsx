import { useState, useRef } from 'react';
import { 
    Image as ImageIcon, 
    Upload, 
    Link2, 
    Sparkles, 
    Check, 
    X, 
    Loader2, 
    Layers, 
    Maximize2, 
    AlignLeft, 
    AlignCenter, 
    AlignRight 
} from 'lucide-react';

const PRESET_IMAGES = [
    {
        name: 'Lekki Luxury Waterfront Villa',
        url: 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=1200&q=80',
        alt: 'Lekki Luxury Waterfront Villa Exterior',
        category: 'Villa'
    },
    {
        name: 'Contemporary Ikoyi 5-Bed Duplex',
        url: 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1200&q=80',
        alt: 'Contemporary Ikoyi Duplex with Swimming Pool',
        category: 'Duplex'
    },
    {
        name: 'Banana Island Skyline Penthouse',
        url: 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
        alt: 'Banana Island Modern Penthouse Living Area',
        category: 'Penthouse'
    },
    {
        name: 'Site Inspection Escort & Welcome',
        url: 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=1200&q=80',
        alt: 'Bamcom Property Consultant Showing Estate Map',
        category: 'Inspection'
    },
    {
        name: 'Bamcom Official Brand Logo',
        url: '/images/bamcom-logo.png',
        alt: 'Bamcom Real Estate & CRM Official Logo',
        category: 'Branding'
    }
];

export default function InsertImageModal({ isOpen, onClose, onInsert }) {
    if (!isOpen) return null;

    const [activeTab, setActiveTab] = useState('upload'); // 'upload', 'url', 'presets'
    const [imageUrl, setImageUrl] = useState('');
    const [altText, setAltText] = useState('');
    const [linkUrl, setLinkUrl] = useState('');
    const [widthPreset, setWidthPreset] = useState('100%'); // '100%', '500', '350', '200', '120', 'custom'
    const [customWidth, setCustomWidth] = useState('400');
    const [alignment, setAlignment] = useState('center'); // 'left', 'center', 'right'
    const [borderRadius, setBorderRadius] = useState('8px'); // '0px', '8px', '16px', '50%'
    const [caption, setCaption] = useState('');
    const [isUploading, setIsUploading] = useState(false);
    const [uploadError, setUploadError] = useState(null);

    const fileInputRef = useRef(null);

    const handleFileUpload = async (file) => {
        if (!file) return;

        setIsUploading(true);
        setUploadError(null);

        const formData = new FormData();
        formData.append('image', file);

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(route('email-templates.upload-image'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token || '',
                    'Accept': 'application/json',
                },
                body: formData,
            });

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.message || 'Image upload failed.');
            }

            const data = await response.json();
            setImageUrl(data.url);
            if (!altText) {
                setAltText(file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' '));
            }
        } catch (err) {
            setUploadError(err.message || 'Failed to upload image. Please try again.');
        } finally {
            setIsUploading(false);
        }
    };

    const handleSelectPreset = (preset) => {
        setImageUrl(preset.url);
        setAltText(preset.alt);
    };

    const handleConfirmInsert = () => {
        if (!imageUrl) return;

        const effectiveWidth = widthPreset === 'custom' ? `${customWidth}px` : (widthPreset === '100%' ? '600' : widthPreset);
        const effectiveStyleWidth = widthPreset === '100%' ? '100%' : `${effectiveWidth}px`;
        const marginStyle = alignment === 'center' ? '0 auto' : (alignment === 'right' ? '0 0 0 auto' : '0 auto 0 0');

        let html = `\n<!-- Email Template Image -->\n`;
        html += `<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 16px 0;">\n`;
        html += `  <tr>\n`;
        html += `    <td align="${alignment}" style="padding: 0;">\n`;

        if (linkUrl) {
            html += `      <a href="${linkUrl}" target="_blank" style="text-decoration: none; display: inline-block;">\n`;
        }

        html += `        <img src="${imageUrl}" alt="${altText || 'Email Image'}" width="${effectiveWidth}" style="display: block; max-width: 100%; width: ${effectiveStyleWidth}; height: auto; border: 0; outline: none; text-decoration: none; border-radius: ${borderRadius}; margin: ${marginStyle};" />\n`;

        if (linkUrl) {
            html += `      </a>\n`;
        }

        if (caption.trim()) {
            html += `      <p style="margin: 8px 0 0 0; font-size: 12px; line-height: 16px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: ${alignment};">${caption.trim()}</p>\n`;
        }

        html += `    </td>\n`;
        html += `  </tr>\n`;
        html += `</table>\n`;

        onInsert(html);
        onClose();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
                    <div className="flex items-center gap-2.5">
                        <div className="p-2 rounded-xl bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                            <ImageIcon className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">Insert Image into Email</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Add responsive property banners, photos, or branding logos</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Tabs */}
                <div className="flex border-b border-slate-200 dark:border-slate-800 px-6 bg-slate-50/30 dark:bg-slate-800/30">
                    <button
                        type="button"
                        onClick={() => setActiveTab('upload')}
                        className={`py-3 px-4 text-xs font-semibold border-b-2 flex items-center gap-1.5 transition ${
                            activeTab === 'upload'
                                ? 'border-sky-600 text-sky-600 dark:text-sky-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                        }`}
                    >
                        <Upload className="h-3.5 w-3.5" />
                        Upload File
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('presets')}
                        className={`py-3 px-4 text-xs font-semibold border-b-2 flex items-center gap-1.5 transition ${
                            activeTab === 'presets'
                                ? 'border-sky-600 text-sky-600 dark:text-sky-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                        }`}
                    >
                        <Sparkles className="h-3.5 w-3.5" />
                        Property Presets
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('url')}
                        className={`py-3 px-4 text-xs font-semibold border-b-2 flex items-center gap-1.5 transition ${
                            activeTab === 'url'
                                ? 'border-sky-600 text-sky-600 dark:text-sky-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                        }`}
                    >
                        <Link2 className="h-3.5 w-3.5" />
                        External Image URL
                    </button>
                </div>

                {/* Body Content */}
                <div className="p-6 overflow-y-auto space-y-5">
                    {/* Tab 1: Upload */}
                    {activeTab === 'upload' && (
                        <div>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
                                className="hidden"
                                onChange={(e) => handleFileUpload(e.target.files?.[0])}
                            />
                            <div
                                onClick={() => fileInputRef.current?.click()}
                                onDragOver={(e) => e.preventDefault()}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    handleFileUpload(e.dataTransfer.files?.[0]);
                                }}
                                className={`border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition ${
                                    isUploading
                                        ? 'border-sky-400 bg-sky-50 dark:bg-sky-950/20'
                                        : 'border-slate-300 dark:border-slate-700 hover:border-sky-500 dark:hover:border-sky-500 hover:bg-slate-50 dark:hover:bg-slate-800/40'
                                }`}
                            >
                                {isUploading ? (
                                    <div className="flex flex-col items-center justify-center gap-2">
                                        <Loader2 className="h-8 w-8 text-sky-600 animate-spin" />
                                        <span className="text-xs font-medium text-slate-600 dark:text-slate-300">
                                            Uploading and optimizing image...
                                        </span>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center justify-center gap-2">
                                        <div className="p-3 rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400">
                                            <Upload className="h-6 w-6" />
                                        </div>
                                        <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                            Click to browse or drag and drop an image
                                        </div>
                                        <div className="text-xs text-slate-400">
                                            Supports JPG, PNG, WEBP, GIF, SVG up to 5MB
                                        </div>
                                    </div>
                                )}
                            </div>
                            {uploadError && (
                                <p className="text-xs text-rose-500 mt-2 font-medium">{uploadError}</p>
                            )}
                        </div>
                    )}

                    {/* Tab 2: Presets */}
                    {activeTab === 'presets' && (
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            {PRESET_IMAGES.map((preset) => (
                                <div
                                    key={preset.name}
                                    onClick={() => handleSelectPreset(preset)}
                                    className={`group cursor-pointer rounded-xl border p-2 transition text-left relative overflow-hidden ${
                                        imageUrl === preset.url
                                            ? 'border-sky-600 ring-2 ring-sky-500/30 bg-sky-50 dark:bg-sky-950/30'
                                            : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800'
                                    }`}
                                >
                                    <div className="aspect-video w-full rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-700 mb-2 relative">
                                        <img src={preset.url} alt={preset.alt} className="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                                        {imageUrl === preset.url && (
                                            <div className="absolute top-1.5 right-1.5 bg-sky-600 text-white rounded-full p-0.5">
                                                <Check className="h-3 w-3" />
                                            </div>
                                        )}
                                    </div>
                                    <div className="text-xs font-semibold text-slate-800 dark:text-slate-200 line-clamp-1">
                                        {preset.name}
                                    </div>
                                    <span className="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">
                                        {preset.category}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Tab 3: URL */}
                    {activeTab === 'url' && (
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Direct Image Web Address (URL) *
                            </label>
                            <input
                                type="url"
                                value={imageUrl}
                                onChange={(e) => setImageUrl(e.target.value)}
                                placeholder="https://example.com/images/property-villa.jpg"
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                            <p className="text-[11px] text-slate-400 mt-1">
                                Must be a publicly reachable URL beginning with http:// or https://
                            </p>
                        </div>
                    )}

                    {/* Active Image URL Preview Bar */}
                    {imageUrl && (
                        <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex items-center gap-3">
                            <div className="w-16 h-12 rounded-lg bg-slate-200 dark:bg-slate-700 overflow-hidden flex-shrink-0">
                                <img src={imageUrl} alt="Selected" className="w-full h-full object-cover" />
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="text-xs font-medium text-slate-800 dark:text-slate-200 truncate">
                                    {imageUrl}
                                </div>
                                <div className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                                    <Check className="h-3 w-3" /> Image asset ready
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Attributes Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Alt Text (Accessibility &amp; Image Blocking) *
                            </label>
                            <input
                                type="text"
                                value={altText}
                                onChange={(e) => setAltText(e.target.value)}
                                placeholder="e.g. Waterfront Duplex Exterior"
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Click Link URL (Optional - Opens when clicked)
                            </label>
                            <input
                                type="url"
                                value={linkUrl}
                                onChange={(e) => setLinkUrl(e.target.value)}
                                placeholder="e.g. https://bamcomcrm.com/properties/villa"
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            />
                        </div>
                    </div>

                    {/* Dimensions & Alignment */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Image Width
                            </label>
                            <select
                                value={widthPreset}
                                onChange={(e) => setWidthPreset(e.target.value)}
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="100%">100% Full Width (600px)</option>
                                <option value="500">Large (500px)</option>
                                <option value="350">Medium (350px)</option>
                                <option value="200">Small (200px)</option>
                                <option value="120">Logo / Badge (120px)</option>
                                <option value="custom">Custom Width</option>
                            </select>
                            {widthPreset === 'custom' && (
                                <div className="mt-2 flex items-center gap-1.5">
                                    <input
                                        type="number"
                                        min="20"
                                        max="600"
                                        value={customWidth}
                                        onChange={(e) => setCustomWidth(e.target.value)}
                                        className="w-24 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs p-1.5"
                                    />
                                    <span className="text-xs text-slate-500">pixels</span>
                                </div>
                            )}
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Alignment
                            </label>
                            <div className="flex rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-0.5">
                                <button
                                    type="button"
                                    onClick={() => setAlignment('left')}
                                    className={`flex-1 py-1.5 rounded-md flex items-center justify-center gap-1 text-xs font-medium transition ${
                                        alignment === 'left' ? 'bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400 font-bold' : 'text-slate-500'
                                    }`}
                                >
                                    <AlignLeft className="h-3.5 w-3.5" /> Left
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setAlignment('center')}
                                    className={`flex-1 py-1.5 rounded-md flex items-center justify-center gap-1 text-xs font-medium transition ${
                                        alignment === 'center' ? 'bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400 font-bold' : 'text-slate-500'
                                    }`}
                                >
                                    <AlignCenter className="h-3.5 w-3.5" /> Center
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setAlignment('right')}
                                    className={`flex-1 py-1.5 rounded-md flex items-center justify-center gap-1 text-xs font-medium transition ${
                                        alignment === 'right' ? 'bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400 font-bold' : 'text-slate-500'
                                    }`}
                                >
                                    <AlignRight className="h-3.5 w-3.5" /> Right
                                </button>
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Rounded Corners
                            </label>
                            <select
                                value={borderRadius}
                                onChange={(e) => setBorderRadius(e.target.value)}
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="0px">Sharp (0px)</option>
                                <option value="8px">Subtle Rounded (8px)</option>
                                <option value="16px">Large Rounded (16px)</option>
                                <option value="50%">Circular Avatar (50%)</option>
                            </select>
                        </div>
                    </div>

                    {/* Caption */}
                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Optional Image Caption (Displayed under image)
                        </label>
                        <input
                            type="text"
                            value={caption}
                            onChange={(e) => setCaption(e.target.value)}
                            placeholder="e.g. Proposed waterfront development scheduled for completion in Q4 2027"
                            className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                        />
                    </div>
                </div>

                {/* Footer Actions */}
                <div className="px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                    <span className="text-xs text-slate-400">
                        Generates CAN-SPAM compliant responsive HTML tables
                    </span>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-3.5 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 transition shadow-sm"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            disabled={!imageUrl}
                            onClick={handleConfirmInsert}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <ImageIcon className="h-4 w-4" />
                            Insert Image
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
