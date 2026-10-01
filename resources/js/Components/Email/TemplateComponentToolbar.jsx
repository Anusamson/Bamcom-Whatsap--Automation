import { useState } from 'react';
import { 
    Image as ImageIcon, 
    MousePointerClick, 
    Share2, 
    LayoutGrid, 
    Minus, 
    UserCheck, 
    Building2, 
    Info,
    ChevronDown
} from 'lucide-react';
import InsertImageModal from './InsertImageModal';
import InsertButtonModal from './InsertButtonModal';
import InsertSocialModal from './InsertSocialModal';

export default function TemplateComponentToolbar({ 
    bodyHtml, 
    setBodyHtml, 
    textareaRef,
    sampleVariables
}) {
    const [isImageModalOpen, setIsImageModalOpen] = useState(false);
    const [isButtonModalOpen, setIsButtonModalOpen] = useState(false);
    const [isSocialModalOpen, setIsSocialModalOpen] = useState(false);
    const [isLayoutDropdownOpen, setIsLayoutDropdownOpen] = useState(false);

    /**
     * Insert HTML at the current cursor position or append to body.
     */
    const insertSnippetAtCursor = (snippet) => {
        if (!textareaRef?.current) {
            setBodyHtml((bodyHtml || '') + '\n' + snippet);
            return;
        }

        const textarea = textareaRef.current;
        const start = textarea.selectionStart ?? bodyHtml.length;
        const end = textarea.selectionEnd ?? bodyHtml.length;

        const before = (bodyHtml || '').substring(0, start);
        const after = (bodyHtml || '').substring(end);

        const newContent = before + snippet + after;
        setBodyHtml(newContent);

        // Defer cursor placement to next event loop
        setTimeout(() => {
            textarea.focus();
            const newCursor = start + snippet.length;
            textarea.setSelectionRange(newCursor, newCursor);
        }, 50);
    };

    /**
     * Insert layout snippet presets.
     */
    const insertLayoutPreset = (type) => {
        setIsLayoutDropdownOpen(false);

        let snippet = '';
        switch (type) {
            case 'callout':
                snippet = `\n<!-- Callout Notice Box -->\n<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 16px 0; background-color: #f0f9ff; border-left: 4px solid #0284c7; border-radius: 8px;">\n  <tr>\n    <td style="padding: 16px 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; line-height: 22px; color: #0369a1;">\n      <strong>Important Update:</strong> Exclusive private inspections are reserved strictly for registered clients. Please confirm attendance 24 hours prior to site entry.\n    </td>\n  </tr>\n</table>\n`;
                break;

            case 'property_card':
                snippet = `\n<!-- 2-Column Property Highlights -->\n<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 20px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">\n  <tr>\n    <td style="padding: 20px;">\n      <h3 style="margin: 0 0 12px 0; font-size: 17px; font-weight: 700; color: #0f172a;">{{ property.name }}</h3>\n      <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">\n        <tr>\n          <td width="50%" style="padding: 6px 0; font-size: 13px; color: #64748b;"><strong>Price:</strong> <span style="color: #0284c7; font-weight: 700;">{{ property.price }}</span></td>\n          <td width="50%" style="padding: 6px 0; font-size: 13px; color: #64748b;"><strong>Location:</strong> {{ property.location }}</td>\n        </tr>\n        <tr>\n          <td width="50%" style="padding: 6px 0; font-size: 13px; color: #64748b;"><strong>Title:</strong> Governor's Consent</td>\n          <td width="50%" style="padding: 6px 0; font-size: 13px; color: #64748b;"><strong>Plot Size:</strong> {{ property.plot_size }}</td>\n        </tr>\n      </table>\n    </td>\n  </tr>\n</table>\n`;
                break;

            case 'signature':
                snippet = `\n<!-- Agent Signature Block -->\n<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 24px 0 16px 0; border-top: 1px solid #e2e8f0; padding-top: 16px;">\n  <tr>\n    <td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; line-height: 20px; color: #334155;">\n      <strong style="font-size: 15px; color: #0f172a;">{{ agent.name }}</strong><br>\n      <span style="color: #64748b; font-size: 13px;">{{ agent.role }} &bull; Bamcom Real Estate</span><br>\n      <span style="color: #0284c7; font-size: 13px;">Tel: {{ agent.phone }} | Email: {{ agent.email }}</span>\n    </td>\n  </tr>\n</table>\n`;
                break;

            case 'divider':
                snippet = `\n<!-- Section Divider -->\n<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 24px 0;">\n  <tr>\n    <td style="border-top: 1px solid #e2e8f0; font-size: 1px; line-height: 1px;">&nbsp;</td>\n  </tr>\n</table>\n`;
                break;
        }

        insertSnippetAtCursor(snippet);
    };

    return (
        <>
            {/* Visual Component Insertion Bar */}
            <div className="flex flex-wrap items-center justify-between gap-2 p-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl mb-3">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider pl-1">
                        Rich Components:
                    </span>

                    {/* Add Image Button */}
                    <button
                        type="button"
                        onClick={() => setIsImageModalOpen(true)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 hover:text-sky-600 dark:hover:text-sky-400 text-xs font-semibold shadow-sm transition group"
                    >
                        <ImageIcon className="h-3.5 w-3.5 text-sky-600 group-hover:scale-110 transition" />
                        <span>Add Image</span>
                    </button>

                    {/* Add Link Button */}
                    <button
                        type="button"
                        onClick={() => setIsButtonModalOpen(true)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 hover:text-sky-600 dark:hover:text-sky-400 text-xs font-semibold shadow-sm transition group"
                    >
                        <MousePointerClick className="h-3.5 w-3.5 text-emerald-600 group-hover:scale-110 transition" />
                        <span>Link Button (CTA)</span>
                    </button>

                    {/* Add Social Icons */}
                    <button
                        type="button"
                        onClick={() => setIsSocialModalOpen(true)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 hover:text-sky-600 dark:hover:text-sky-400 text-xs font-semibold shadow-sm transition group"
                    >
                        <Share2 className="h-3.5 w-3.5 text-indigo-600 group-hover:scale-110 transition" />
                        <span>Social Media Icons</span>
                    </button>

                    {/* Layout Snippets Dropdown */}
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => setIsLayoutDropdownOpen(!isLayoutDropdownOpen)}
                            className="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 text-xs font-semibold shadow-sm transition"
                        >
                            <LayoutGrid className="h-3.5 w-3.5 text-amber-500" />
                            <span>Layout Blocks</span>
                            <ChevronDown className="h-3 w-3 text-slate-400" />
                        </button>

                        {isLayoutDropdownOpen && (
                            <div className="absolute left-0 mt-1 w-56 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl z-20 py-1 text-xs">
                                <button
                                    type="button"
                                    onClick={() => insertLayoutPreset('callout')}
                                    className="w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center gap-2 text-slate-700 dark:text-slate-200"
                                >
                                    <Info className="h-3.5 w-3.5 text-sky-500" />
                                    Notice / Callout Box
                                </button>
                                <button
                                    type="button"
                                    onClick={() => insertLayoutPreset('property_card')}
                                    className="w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center gap-2 text-slate-700 dark:text-slate-200"
                                >
                                    <Building2 className="h-3.5 w-3.5 text-emerald-500" />
                                    2-Column Property Card
                                </button>
                                <button
                                    type="button"
                                    onClick={() => insertLayoutPreset('signature')}
                                    className="w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center gap-2 text-slate-700 dark:text-slate-200"
                                >
                                    <UserCheck className="h-3.5 w-3.5 text-indigo-500" />
                                    Agent Signature Block
                                </button>
                                <button
                                    type="button"
                                    onClick={() => insertLayoutPreset('divider')}
                                    className="w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center gap-2 text-slate-700 dark:text-slate-200"
                                >
                                    <Minus className="h-3.5 w-3.5 text-slate-400" />
                                    Horizontal Divider
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                <div className="text-[11px] text-slate-400 hidden sm:block">
                    Inserts at cursor position
                </div>
            </div>

            {/* Modals */}
            <InsertImageModal
                isOpen={isImageModalOpen}
                onClose={() => setIsImageModalOpen(false)}
                onInsert={insertSnippetAtCursor}
            />

            <InsertButtonModal
                isOpen={isButtonModalOpen}
                onClose={() => setIsButtonModalOpen(false)}
                onInsert={insertSnippetAtCursor}
                sampleVariables={sampleVariables}
            />

            <InsertSocialModal
                isOpen={isSocialModalOpen}
                onClose={() => setIsSocialModalOpen(false)}
                onInsert={insertSnippetAtCursor}
            />
        </>
    );
}
