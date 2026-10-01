import { useState } from 'react';
import { 
    MousePointerClick, 
    Link2, 
    Sparkles, 
    Check, 
    X, 
    AlignLeft, 
    AlignCenter, 
    AlignRight,
    ArrowRight
} from 'lucide-react';

const PRESET_BUTTON_TEXTS = [
    'Schedule Site Inspection',
    'View Property Brochure',
    'Claim Exclusive Launch Offer',
    'Contact Dedicated Agent',
    'Confirm VIP Attendance',
    'Chat on WhatsApp'
];

const PRESET_THEMES = [
    { name: 'Sky Blue', bg: '#0284c7', text: '#ffffff', border: 'none' },
    { name: 'Emerald', bg: '#059669', text: '#ffffff', border: 'none' },
    { name: 'Midnight', bg: '#0f172a', text: '#ffffff', border: 'none' },
    { name: 'Indigo', bg: '#4f46e5', text: '#ffffff', border: 'none' },
    { name: 'Rose', bg: '#e11d48', text: '#ffffff', border: 'none' },
    { name: 'Amber Gold', bg: '#d97706', text: '#ffffff', border: 'none' },
    { name: 'Ghost Blue', bg: '#ffffff', text: '#0284c7', border: '2px solid #0284c7' },
];

export default function InsertButtonModal({ isOpen, onClose, onInsert, sampleVariables }) {
    if (!isOpen) return null;

    const [buttonText, setButtonText] = useState('Schedule Site Inspection');
    const [linkUrl, setLinkUrl] = useState('https://bamcomcrm.com/properties/villa');
    const [selectedTheme, setSelectedTheme] = useState(PRESET_THEMES[0]);
    const [customBg, setCustomBg] = useState('#0284c7');
    const [customText, setCustomText] = useState('#ffffff');
    const [isCustomColor, setIsCustomColor] = useState(false);
    const [size, setSize] = useState('medium'); // 'small', 'medium', 'large'
    const [shape, setShape] = useState('8px'); // '0px', '4px', '8px', '9999px'
    const [alignment, setAlignment] = useState('center'); // 'left', 'center', 'right', 'full'
    const [includeArrow, setIncludeArrow] = useState(true);

    const getDimensions = () => {
        switch (size) {
            case 'small':
                return { padding: '8px 18px', fontSize: '13px' };
            case 'large':
                return { padding: '16px 36px', fontSize: '16px' };
            case 'medium':
            default:
                return { padding: '12px 28px', fontSize: '15px' };
        }
    };

    const handleConfirmInsert = () => {
        if (!buttonText.trim()) return;

        const effectiveBg = isCustomColor ? customBg : selectedTheme.bg;
        const effectiveText = isCustomColor ? customText : selectedTheme.text;
        const effectiveBorder = isCustomColor ? 'none' : selectedTheme.border;
        const { padding, fontSize } = getDimensions();
        const fullWidth = alignment === 'full';
        const effectiveAlign = fullWidth ? 'center' : alignment;

        const margin = alignment === 'center' || fullWidth 
            ? '20px auto' 
            : (alignment === 'right' ? '20px 0 20px auto' : '20px auto 20px 0');

        const arrowText = includeArrow ? ' &rarr;' : '';

        let html = `\n<!-- Bulletproof Email CTA Button -->\n`;
        html += `<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="${fullWidth ? '100%' : 'auto'}" style="margin: ${margin}; border-collapse: separate;">\n`;
        html += `  <tr>\n`;
        html += `    <td align="${effectiveAlign}" bgcolor="${effectiveBg}" style="border-radius: ${shape}; ${effectiveBorder !== 'none' ? `border: ${effectiveBorder};` : ''}">\n`;
        html += `      <a href="${linkUrl || '#'}" target="_blank" style="display: ${fullWidth ? 'block' : 'inline-block'}; padding: ${padding}; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: ${fontSize}; font-weight: 700; color: ${effectiveText}; text-decoration: none; border-radius: ${shape}; background-color: ${effectiveBg}; text-align: center; mso-padding-alt: 0;">\n`;
        html += `        <!--[if mso]>&nbsp;&nbsp;<![endif]-->${buttonText.trim()}${arrowText}<!--[if mso]>&nbsp;&nbsp;<![endif]-->\n`;
        html += `      </a>\n`;
        html += `    </td>\n`;
        html += `  </tr>\n`;
        html += `</table>\n`;

        onInsert(html);
        onClose();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
                    <div className="flex items-center gap-2.5">
                        <div className="p-2 rounded-xl bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                            <MousePointerClick className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">Insert Link Button (CTA)</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Add high-converting, bulletproof buttons that work across all email clients</p>
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
                    {/* Live Preview Card */}
                    <div className="p-6 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 flex flex-col items-center justify-center min-h-[100px]">
                        <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-3">Live Button Preview</span>
                        <div className={`w-full flex ${alignment === 'left' ? 'justify-start' : (alignment === 'right' ? 'justify-end' : 'justify-center')}`}>
                            <a
                                href="#preview"
                                onClick={(e) => e.preventDefault()}
                                style={{
                                    backgroundColor: isCustomColor ? customBg : selectedTheme.bg,
                                    color: isCustomColor ? customText : selectedTheme.text,
                                    border: isCustomColor ? 'none' : selectedTheme.border,
                                    borderRadius: shape,
                                    padding: getDimensions().padding,
                                    fontSize: getDimensions().fontSize,
                                    width: alignment === 'full' ? '100%' : 'auto',
                                    textAlign: 'center',
                                }}
                                className="font-bold shadow-sm inline-flex items-center justify-center gap-1.5 transition select-none"
                            >
                                {buttonText || 'Button Text'}
                                {includeArrow && <ArrowRight className="h-4 w-4" />}
                            </a>
                        </div>
                    </div>

                    {/* Button Text */}
                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Button Label / Text *
                            </label>
                            <span className="text-[11px] text-slate-400">Action oriented (e.g. Schedule, View, Claim)</span>
                        </div>
                        <input
                            type="text"
                            value={buttonText}
                            onChange={(e) => setButtonText(e.target.value)}
                            placeholder="Schedule Site Inspection"
                            className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                        />
                        {/* Quick Text Suggestions */}
                        <div className="flex flex-wrap gap-1.5 mt-2">
                            {PRESET_BUTTON_TEXTS.map((preset) => (
                                <button
                                    key={preset}
                                    type="button"
                                    onClick={() => setButtonText(preset)}
                                    className="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] text-slate-600 dark:text-slate-300 hover:bg-sky-50 dark:hover:bg-sky-950/40 hover:text-sky-600 transition"
                                >
                                    {preset}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Destination Link URL */}
                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Destination Link URL *
                        </label>
                        <input
                            type="text"
                            value={linkUrl}
                            onChange={(e) => setLinkUrl(e.target.value)}
                            placeholder="https://bamcomcrm.com/properties/villa"
                            className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                        />
                        <div className="flex flex-wrap gap-1.5 mt-1.5 text-[11px]">
                            <span className="text-slate-400 py-0.5">Quick tokens:</span>
                            <button
                                type="button"
                                onClick={() => setLinkUrl('{{ app.url }}/properties')}
                                className="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-sky-600 font-mono text-[10px]"
                            >
                                &#123;&#123; app.url &#125;&#125;/properties
                            </button>
                            <button
                                type="button"
                                onClick={() => setLinkUrl('tel:{{ agent.phone }}')}
                                className="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-sky-600 font-mono text-[10px]"
                            >
                                Call Agent
                            </button>
                            <button
                                type="button"
                                onClick={() => setLinkUrl('https://wa.me/2348002262662')}
                                className="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-sky-600 font-mono text-[10px]"
                            >
                                WhatsApp Chat
                            </button>
                        </div>
                    </div>

                    {/* Color Theme Presets */}
                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Color Palette Preset
                        </label>
                        <div className="grid grid-cols-4 sm:grid-cols-7 gap-2">
                            {PRESET_THEMES.map((theme) => (
                                <button
                                    key={theme.name}
                                    type="button"
                                    onClick={() => {
                                        setSelectedTheme(theme);
                                        setIsCustomColor(false);
                                    }}
                                    className={`p-2 rounded-xl border flex flex-col items-center gap-1.5 transition ${
                                        !isCustomColor && selectedTheme.name === theme.name
                                            ? 'border-sky-500 ring-2 ring-sky-500/20 bg-sky-50/50 dark:bg-sky-950/20'
                                            : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
                                    }`}
                                >
                                    <div 
                                        className="w-6 h-6 rounded-full border shadow-sm"
                                        style={{ backgroundColor: theme.bg, borderColor: theme.border !== 'none' ? '#0284c7' : 'transparent' }}
                                    />
                                    <span className="text-[10px] font-medium text-slate-700 dark:text-slate-300 truncate w-full text-center">
                                        {theme.name}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Styling Controls Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {/* Size */}
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Button Size
                            </label>
                            <select
                                value={size}
                                onChange={(e) => setSize(e.target.value)}
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="small">Small (Compact)</option>
                                <option value="medium">Medium (Standard)</option>
                                <option value="large">Large (Prominent)</option>
                            </select>
                        </div>

                        {/* Shape */}
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Corner Shape
                            </label>
                            <select
                                value={shape}
                                onChange={(e) => setShape(e.target.value)}
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="8px">Rounded (8px)</option>
                                <option value="9999px">Full Pill (Pill shape)</option>
                                <option value="4px">Subtle (4px)</option>
                                <option value="0px">Sharp Square (0px)</option>
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
                                <option value="full">Full Width (100%)</option>
                            </select>
                        </div>
                    </div>

                    {/* Arrow Suffix Option */}
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="arrowToggle"
                            checked={includeArrow}
                            onChange={(e) => setIncludeArrow(e.target.checked)}
                            className="rounded border-slate-300 dark:border-slate-700 text-sky-600 focus:ring-sky-500 h-4 w-4"
                        />
                        <label htmlFor="arrowToggle" className="text-xs text-slate-700 dark:text-slate-300 select-none cursor-pointer">
                            Include directional arrow icon (e.g. Schedule Site Inspection &rarr;)
                        </label>
                    </div>
                </div>

                {/* Footer Actions */}
                <div className="px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                    <span className="text-xs text-slate-400">
                        Compatible with Outlook, Gmail, Apple Mail &amp; mobile
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
                            disabled={!buttonText.trim()}
                            onClick={handleConfirmInsert}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <MousePointerClick className="h-4 w-4" />
                            Insert Button
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
