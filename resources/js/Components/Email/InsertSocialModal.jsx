import { useState } from 'react';
import { 
    Share2, 
    Check, 
    X, 
    AlignLeft, 
    AlignCenter, 
    AlignRight
} from 'lucide-react';

const INITIAL_PLATFORMS = [
    {
        id: 'whatsapp',
        name: 'WhatsApp',
        iconFile: 'whatsapp.svg',
        url: 'https://wa.me/2348002262662',
        enabled: true,
        brandColor: '#25D366'
    },
    {
        id: 'instagram',
        name: 'Instagram',
        iconFile: 'instagram.svg',
        url: 'https://instagram.com/bamcomrealestate',
        enabled: true,
        brandColor: '#E1306C'
    },
    {
        id: 'facebook',
        name: 'Facebook',
        iconFile: 'facebook.svg',
        url: 'https://facebook.com/bamcomrealestate',
        enabled: true,
        brandColor: '#1877F2'
    },
    {
        id: 'linkedin',
        name: 'LinkedIn',
        iconFile: 'linkedin.svg',
        url: 'https://linkedin.com/company/bamcom-real-estate',
        enabled: true,
        brandColor: '#0A66C2'
    },
    {
        id: 'x',
        name: 'X (Twitter)',
        iconFile: 'x.svg',
        url: 'https://x.com/bamcomcrm',
        enabled: true,
        brandColor: '#000000'
    },
    {
        id: 'youtube',
        name: 'YouTube',
        iconFile: 'youtube.svg',
        url: 'https://youtube.com/@bamcomcrm',
        enabled: false,
        brandColor: '#FF0000'
    },
    {
        id: 'website',
        name: 'Website',
        iconFile: 'website.svg',
        url: 'https://bamcomcrm.com',
        enabled: true,
        brandColor: '#0284C7'
    },
    {
        id: 'tiktok',
        name: 'TikTok',
        iconFile: 'tiktok.svg',
        url: 'https://tiktok.com/@bamcomcrm',
        enabled: false,
        brandColor: '#000000'
    }
];

export default function InsertSocialModal({ isOpen, onClose, onInsert }) {
    if (!isOpen) return null;

    const [platforms, setPlatforms] = useState(INITIAL_PLATFORMS);
    const [heading, setHeading] = useState('Connect with Bamcom Real Estate:');
    const [size, setSize] = useState('32'); // '24', '32', '40'
    const [spacing, setSpacing] = useState('12'); // '8', '12', '16'
    const [alignment, setAlignment] = useState('center'); // 'left', 'center', 'right'
    const [stylePreset, setStylePreset] = useState('brand'); // 'brand', 'dark', 'sky', 'slate'

    const togglePlatform = (id) => {
        setPlatforms(platforms.map(p => p.id === id ? { ...p, enabled: !p.enabled } : p));
    };

    const updatePlatformUrl = (id, newUrl) => {
        setPlatforms(platforms.map(p => p.id === id ? { ...p, url: newUrl } : p));
    };

    const activePlatforms = platforms.filter(p => p.enabled);

    const handleConfirmInsert = () => {
        if (activePlatforms.length === 0) return;

        const baseUrl = typeof window !== 'undefined' ? window.location.origin : '';
        const margin = alignment === 'center' ? '0 auto' : (alignment === 'right' ? '0 0 0 auto' : '0 auto 0 0');
        const halfSpacing = Math.round(parseInt(spacing, 10) / 2);

        let html = `\n<!-- Social Media Channel Icons -->\n`;
        html += `<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 20px 0;">\n`;
        html += `  <tr>\n`;
        html += `    <td align="${alignment}" style="text-align: ${alignment}; padding: 0;">\n`;

        if (heading.trim()) {
            html += `      <p style="margin: 0 0 12px 0; font-size: 13px; font-weight: 600; color: #475569; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">${heading.trim()}</p>\n`;
        }

        html += `      <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: ${margin}; display: inline-block;">\n`;
        html += `        <tr>\n`;

        activePlatforms.forEach(p => {
            const iconUrl = `${baseUrl}/images/email-icons/${p.iconFile}`;
            html += `          <td style="padding: 0 ${halfSpacing}px;">\n`;
            html += `            <a href="${p.url}" target="_blank" title="${p.name}" style="text-decoration: none; display: inline-block;">\n`;
            html += `              <img src="${iconUrl}" alt="${p.name}" width="${size}" height="${size}" style="display: block; width: ${size}px; height: ${size}px; border: 0; outline: none; text-decoration: none; border-radius: 50%;" />\n`;
            html += `            </a>\n`;
            html += `          </td>\n`;
        });

        html += `        </tr>\n`;
        html += `      </table>\n`;
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
                            <Share2 className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">Insert Social Media Icons</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Add official WhatsApp, Instagram, LinkedIn, and social channel links</p>
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

                {/* Form Content */}
                <div className="p-6 overflow-y-auto space-y-5">
                    {/* Live Preview Bar */}
                    <div className="p-6 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 flex flex-col items-center justify-center min-h-[100px]">
                        <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-2">Live Social Icons Preview</span>
                        {heading.trim() && (
                            <div className="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-3 text-center">
                                {heading}
                            </div>
                        )}
                        <div className={`w-full flex items-center ${alignment === 'left' ? 'justify-start' : (alignment === 'right' ? 'justify-end' : 'justify-center')} gap-3 flex-wrap`}>
                            {activePlatforms.map(p => (
                                <a
                                    key={p.id}
                                    href={p.url}
                                    onClick={(e) => e.preventDefault()}
                                    title={p.name}
                                    className="transition transform hover:scale-110"
                                >
                                    <img
                                        src={`/images/email-icons/${p.iconFile}`}
                                        alt={p.name}
                                        style={{ width: `${size}px`, height: `${size}px` }}
                                        className="rounded-full shadow-sm"
                                    />
                                </a>
                            ))}
                            {activePlatforms.length === 0 && (
                                <span className="text-xs text-slate-400 italic">No social platforms selected. Check below to enable.</span>
                            )}
                        </div>
                    </div>

                    {/* Heading / Subtitle */}
                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Optional Section Header (Displayed above social icons)
                        </label>
                        <input
                            type="text"
                            value={heading}
                            onChange={(e) => setHeading(e.target.value)}
                            placeholder="e.g. Follow Bamcom Real Estate & Investments"
                            className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                        />
                    </div>

                    {/* Platform Selection & Links */}
                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Social Platforms &amp; Profile URLs
                            </label>
                            <span className="text-[11px] text-slate-400">Toggle to enable/disable or edit URLs</span>
                        </div>
                        <div className="space-y-2.5 max-h-56 overflow-y-auto pr-1">
                            {platforms.map((platform) => (
                                <div
                                    key={platform.id}
                                    className={`p-2.5 rounded-xl border flex items-center gap-3 transition ${
                                        platform.enabled
                                            ? 'border-sky-300 dark:border-sky-900 bg-sky-50/30 dark:bg-sky-950/20'
                                            : 'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-850 opacity-60'
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        id={`platform-${platform.id}`}
                                        checked={platform.enabled}
                                        onChange={() => togglePlatform(platform.id)}
                                        className="rounded border-slate-300 dark:border-slate-700 text-sky-600 focus:ring-sky-500 h-4 w-4"
                                    />
                                    <img
                                        src={`/images/email-icons/${platform.iconFile}`}
                                        alt={platform.name}
                                        className="w-6 h-6 rounded-full flex-shrink-0"
                                    />
                                    <label
                                        htmlFor={`platform-${platform.id}`}
                                        className="text-xs font-semibold text-slate-800 dark:text-slate-200 w-24 flex-shrink-0 cursor-pointer"
                                    >
                                        {platform.name}
                                    </label>
                                    <input
                                        type="url"
                                        disabled={!platform.enabled}
                                        value={platform.url}
                                        onChange={(e) => updatePlatformUrl(platform.id, e.target.value)}
                                        placeholder={`https://${platform.id}.com/...`}
                                        className="flex-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-1.5 focus:ring-2 focus:ring-sky-500 disabled:opacity-50"
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Sizing & Alignment Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {/* Icon Size */}
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Icon Dimensions
                            </label>
                            <select
                                value={size}
                                onChange={(e) => setSize(e.target.value)}
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="24">Compact (24px)</option>
                                <option value="32">Standard (32px)</option>
                                <option value="40">Large (40px)</option>
                            </select>
                        </div>

                        {/* Spacing */}
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Horizontal Spacing
                            </label>
                            <select
                                value={spacing}
                                onChange={(e) => setSpacing(e.target.value)}
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="8">Tight (8px)</option>
                                <option value="12">Standard (12px)</option>
                                <option value="16">Relaxed (16px)</option>
                            </select>
                        </div>

                        {/* Alignment */}
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Alignment
                            </label>
                            <select
                                value={alignment}
                                onChange={(e) => setAlignment(e.target.value)}
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="center">Center</option>
                                <option value="left">Left Aligned</option>
                                <option value="right">Right Aligned</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Footer Actions */}
                <div className="px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                    <span className="text-xs text-slate-400">
                        {activePlatforms.length} platform{activePlatforms.length === 1 ? '' : 's'} ready to insert
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
                            disabled={activePlatforms.length === 0}
                            onClick={handleConfirmInsert}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <Share2 className="h-4 w-4" />
                            Insert Social Icons
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
