import { useState, useRef, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    FileText, 
    ArrowLeft, 
    Save, 
    Smartphone, 
    Monitor, 
    Eye, 
    AlertTriangle, 
    Sparkles, 
    Wand2
} from 'lucide-react';
import TemplateComponentToolbar from '@/Components/Email/TemplateComponentToolbar';

export default function Create({ auth, categories, statuses, sampleVariables }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        subject: '',
        preheader: '',
        category: 'marketing',
        status: 'active',
        body_html: `<h2>Exclusive Property Update for {{ contact.first_name }}</h2>
<p>We are delighted to share an exclusive update regarding prime real estate opportunities with Bamcom Real Estate &amp; Investments.</p>

<!-- Email Template Image -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 16px 0;">
  <tr>
    <td align="center" style="padding: 0;">
      <a href="https://bamcomcrm.com/properties/villa" target="_blank" style="text-decoration: none; display: inline-block;">
        <img src="https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=1200&q=80" alt="Lekki Luxury Waterfront Villa" width="600" style="display: block; max-width: 100%; width: 100%; height: auto; border: 0; outline: none; text-decoration: none; border-radius: 8px; margin: 0 auto;" />
      </a>
      <p style="margin: 8px 0 0 0; font-size: 12px; line-height: 16px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: center;">The Grandview Waterfront Villa &bull; Lekki Phase 1</p>
    </td>
  </tr>
</table>

<p>Your dedicated property advisor <strong>{{ agent.name }}</strong> is pleased to arrange a private on-site inspection for you.</p>

<!-- Bulletproof Email CTA Button -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="auto" style="margin: 20px auto; border-collapse: separate;">
  <tr>
    <td align="center" bgcolor="#0284c7" style="border-radius: 8px;">
      <a href="https://bamcomcrm.com/inspections/book" target="_blank" style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 8px; background-color: #0284c7; text-align: center;">
        Schedule Site Inspection &rarr;
      </a>
    </td>
  </tr>
</table>

<!-- Social Media Channel Icons -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 20px 0;">
  <tr>
    <td align="center" style="text-align: center; padding: 0;">
      <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 600; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">Connect with Bamcom Real Estate:</p>
      <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto; display: inline-block;">
        <tr>
          <td style="padding: 0 6px;">
            <a href="https://wa.me/2348002262662" target="_blank" title="WhatsApp" style="text-decoration: none; display: inline-block;">
              <img src="/images/email-icons/whatsapp.svg" alt="WhatsApp" width="28" height="28" style="display: block; width: 28px; height: 28px; border: 0; outline: none; border-radius: 50%;" />
            </a>
          </td>
          <td style="padding: 0 6px;">
            <a href="https://instagram.com/bamcomrealestate" target="_blank" title="Instagram" style="text-decoration: none; display: inline-block;">
              <img src="/images/email-icons/instagram.svg" alt="Instagram" width="28" height="28" style="display: block; width: 28px; height: 28px; border: 0; outline: none; border-radius: 50%;" />
            </a>
          </td>
          <td style="padding: 0 6px;">
            <a href="https://linkedin.com/company/bamcom-real-estate" target="_blank" title="LinkedIn" style="text-decoration: none; display: inline-block;">
              <img src="/images/email-icons/linkedin.svg" alt="LinkedIn" width="28" height="28" style="display: block; width: 28px; height: 28px; border: 0; outline: none; border-radius: 50%;" />
            </a>
          </td>
          <td style="padding: 0 6px;">
            <a href="https://bamcomcrm.com" target="_blank" title="Website" style="text-decoration: none; display: inline-block;">
              <img src="/images/email-icons/website.svg" alt="Website" width="28" height="28" style="display: block; width: 28px; height: 28px; border: 0; outline: none; border-radius: 50%;" />
            </a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<p><a href="{{ unsubscribe_url }}" style="color: #64748b; font-size: 12px;">Unsubscribe from marketing emails</a></p>`,
        body_plain: '',
    });

    const [previewMode, setPreviewMode] = useState('desktop'); // 'desktop' or 'mobile'
    const [previewHtml, setPreviewHtml] = useState('');
    const textareaRef = useRef(null);

    const requiresUnsubscribe = ['marketing', 'newsletter', 'promotion', 're_engagement'].includes(data.category);
    const hasUnsubscribe = /\{\{\s*unsubscribe_url\s*\}\}|\{\s*unsubscribe_url\s*\}|unsubscribe/i.test(data.body_html);

    const insertVariable = (tag) => {
        if (!textareaRef.current) {
            setData('body_html', data.body_html + ' ' + tag);
            return;
        }
        const textarea = textareaRef.current;
        const start = textarea.selectionStart ?? data.body_html.length;
        const end = textarea.selectionEnd ?? data.body_html.length;
        const before = (data.body_html || '').substring(0, start);
        const after = (data.body_html || '').substring(end);
        setData('body_html', before + tag + after);
        setTimeout(() => {
            textarea.focus();
            const newCursor = start + tag.length;
            textarea.setSelectionRange(newCursor, newCursor);
        }, 50);
    };

    const autoGeneratePlainText = () => {
        const text = data.body_html
            .replace(/<[^>]+>/g, '')
            .replace(/&nbsp;/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        setData('body_plain', text);
    };

    // Real-time synchronization of Live Preview
    useEffect(() => {
        let content = data.body_html || '';
        if (sampleVariables) {
            content = content.replace(/\{\{\s*contact\.first_name\s*\}\}/g, sampleVariables.contact?.first_name || 'Babajide');
            content = content.replace(/\{\{\s*contact\.last_name\s*\}\}/g, sampleVariables.contact?.last_name || 'Adeleke');
            content = content.replace(/\{\{\s*agent\.name\s*\}\}/g, sampleVariables.agent?.name || 'Kemi Alabi');
            content = content.replace(/\{\{\s*agent\.phone\s*\}\}/g, sampleVariables.agent?.phone || '+234 812 987 6543');
            content = content.replace(/\{\{\s*agent\.email\s*\}\}/g, sampleVariables.agent?.email || 'kemi.alabi@bamcomcrm.com');
            content = content.replace(/\{\{\s*agent\.role\s*\}\}/g, sampleVariables.agent?.role || 'Senior Investment Specialist');
            content = content.replace(/\{\{\s*property\.name\s*\}\}/g, sampleVariables.property?.name || 'The Grandview Waterfront Villa');
            content = content.replace(/\{\{\s*property\.price\s*\}\}/g, sampleVariables.property?.price || '₦185,000,000');
            content = content.replace(/\{\{\s*property\.location\s*\}\}/g, sampleVariables.property?.location || 'Lekki Phase 1, Lagos');
            content = content.replace(/\{\{\s*property\.plot_size\s*\}\}/g, sampleVariables.property?.plot_size || '850 sqm');
            content = content.replace(/\{\{\s*inspection\.date\s*\}\}/g, sampleVariables.inspection?.date || 'Saturday, 12th October 2026');
            content = content.replace(/\{\{\s*inspection\.time\s*\}\}/g, sampleVariables.inspection?.time || '11:00 AM (WAT)');
            content = content.replace(/\{\{\s*unsubscribe_url\s*\}\}/g, sampleVariables.unsubscribe_url || '#');
            content = content.replace(/\{\{\s*company\.name\s*\}\}/g, sampleVariables.company?.name || 'Bamcom Real Estate & Investments Ltd');
            content = content.replace(/\{\{\s*company\.phone\s*\}\}/g, sampleVariables.company?.phone || '+234 800 226 2662');
            content = content.replace(/\{\{\s*app\.url\s*\}\}/g, sampleVariables.app?.url || window.location.origin);
        }
        setPreviewHtml(content);
    }, [data.body_html, sampleVariables]);

    const updateLivePreview = () => {
        // Trigger manual refresh if needed
        let content = data.body_html;
        if (sampleVariables) {
            content = content.replace(/\{\{\s*contact\.first_name\s*\}\}/g, sampleVariables.contact?.first_name || 'Babajide');
            content = content.replace(/\{\{\s*contact\.last_name\s*\}\}/g, sampleVariables.contact?.last_name || 'Adeleke');
            content = content.replace(/\{\{\s*agent\.name\s*\}\}/g, sampleVariables.agent?.name || 'Kemi Alabi');
            content = content.replace(/\{\{\s*property\.name\s*\}\}/g, sampleVariables.property?.name || 'The Grandview Waterfront Villa');
            content = content.replace(/\{\{\s*property\.price\s*\}\}/g, sampleVariables.property?.price || '₦185,000,000');
            content = content.replace(/\{\{\s*inspection\.date\s*\}\}/g, sampleVariables.inspection?.date || 'Saturday, 12th October 2026');
            content = content.replace(/\{\{\s*inspection\.time\s*\}\}/g, sampleVariables.inspection?.time || '11:00 AM (WAT)');
            content = content.replace(/\{\{\s*unsubscribe_url\s*\}\}/g, sampleVariables.unsubscribe_url || '#');
            content = content.replace(/\{\{\s*company\.name\s*\}\}/g, sampleVariables.company?.name || 'Bamcom Real Estate & Investments Ltd');
        }
        setPreviewHtml(content);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('email-templates.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('email-templates.index')}
                            className="p-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        >
                            <ArrowLeft className="h-4 w-4 text-slate-600 dark:text-slate-300" />
                        </Link>
                        <div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <FileText className="h-6 w-6 text-sky-600" />
                                Create Email Template
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Design high-converting emails with dynamic merge tags and CAN-SPAM verification
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={updateLivePreview}
                            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 transition shadow-sm"
                        >
                            <Eye className="h-4 w-4 text-sky-600" />
                            Live Preview
                        </button>
                        <button
                            type="button"
                            onClick={handleSubmit}
                            disabled={processing}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm disabled:opacity-50"
                        >
                            <Save className="h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Template'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Create Email Template" />

            {/* Compliance Warning Ribbon */}
            {requiresUnsubscribe && !hasUnsubscribe && (
                <div className="mb-6 p-4 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-800 flex items-center justify-between gap-4">
                    <div className="flex items-center gap-2 text-xs text-amber-800 dark:text-amber-200">
                        <AlertTriangle className="h-5 w-5 text-amber-600 flex-shrink-0" />
                        <div>
                            <span className="font-bold">CAN-SPAM & GDPR Notice:</span> Every marketing, newsletter, and promotional email template must include an unsubscribe link.
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => insertVariable('<p><a href="{{ unsubscribe_url }}">Unsubscribe from emails</a></p>')}
                        className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition whitespace-nowrap"
                    >
                        + Insert Unsubscribe Link
                    </button>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Editor Settings & Content Column */}
                <div className="lg:col-span-2 space-y-6">
                    <form onSubmit={handleSubmit} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div className="sm:col-span-2">
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Template Name *</label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                    placeholder="Lekki Luxury Villa Inspection Follow-up"
                                />
                                {errors.name && <div className="text-xs text-rose-500 mt-1">{errors.name}</div>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Category *</label>
                                <select
                                    value={data.category}
                                    onChange={(e) => setData('category', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                >
                                    {categories?.map((cat) => (
                                        <option key={cat.value} value={cat.value}>{cat.label}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div className="sm:col-span-2">
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Subject Line *</label>
                                <input
                                    type="text"
                                    required
                                    value={data.subject}
                                    onChange={(e) => setData('subject', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                    placeholder="Important property update for {{ contact.first_name }}"
                                />
                                {errors.subject && <div className="text-xs text-rose-500 mt-1">{errors.subject}</div>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Lifecycle Status *</label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                >
                                    {statuses?.map((st) => (
                                        <option key={st.value} value={st.value}>{st.label}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Preheader Preview Text (Shown in inbox preview snippet before opening)
                            </label>
                            <input
                                type="text"
                                value={data.preheader}
                                onChange={(e) => setData('preheader', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                placeholder="Exclusive waterfront duplexes now available in Lekki Phase 1..."
                            />
                            {errors.preheader && <div className="text-xs text-rose-500 mt-1">{errors.preheader}</div>}
                        </div>

                        {/* Dynamic Variable Insertion Bar */}
                        <div className="bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                            <span className="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-2 flex items-center gap-1.5">
                                <Sparkles className="h-3.5 w-3.5 text-sky-500" />
                                Dynamic Variables (Click to append):
                            </span>
                            <div className="flex flex-wrap gap-1.5 text-xs">
                                <button type="button" onClick={() => insertVariable('{{ contact.first_name }}')} className="px-2 py-1 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 transition font-mono text-[11px]">&#123;&#123; contact.first_name &#125;&#125;</button>
                                <button type="button" onClick={() => insertVariable('{{ contact.last_name }}')} className="px-2 py-1 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 transition font-mono text-[11px]">&#123;&#123; contact.last_name &#125;&#125;</button>
                                <button type="button" onClick={() => insertVariable('{{ agent.name }}')} className="px-2 py-1 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 transition font-mono text-[11px]">&#123;&#123; agent.name &#125;&#125;</button>
                                <button type="button" onClick={() => insertVariable('{{ property.name }}')} className="px-2 py-1 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 transition font-mono text-[11px]">&#123;&#123; property.name &#125;&#125;</button>
                                <button type="button" onClick={() => insertVariable('{{ property.price }}')} className="px-2 py-1 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 transition font-mono text-[11px]">&#123;&#123; property.price &#125;&#125;</button>
                                <button type="button" onClick={() => insertVariable('{{ inspection.date }}')} className="px-2 py-1 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 transition font-mono text-[11px]">&#123;&#123; inspection.date &#125;&#125;</button>
                                <button type="button" onClick={() => insertVariable('{{ inspection.time }}')} className="px-2 py-1 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-sky-500 transition font-mono text-[11px]">&#123;&#123; inspection.time &#125;&#125;</button>
                                <button type="button" onClick={() => insertVariable('{{ unsubscribe_url }}')} className="px-2 py-1 rounded bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-rose-600 dark:text-rose-400 hover:border-rose-500 transition font-mono text-[11px]">&#123;&#123; unsubscribe_url &#125;&#125;</button>
                            </div>
                        </div>

                        {/* HTML Content Textarea with Rich Component Toolbar */}
                        <div>
                            {/* Rich Component Insertion Toolbar */}
                            <TemplateComponentToolbar
                                bodyHtml={data.body_html}
                                setBodyHtml={(val) => setData('body_html', val)}
                                textareaRef={textareaRef}
                                sampleVariables={sampleVariables}
                            />

                            <div className="flex items-center justify-between mb-1">
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">HTML Template Body *</label>
                                <span className="text-[11px] text-slate-400">Strictly sanitized &bull; Supports Images, Buttons &amp; Social Links</span>
                            </div>
                            <textarea
                                ref={textareaRef}
                                rows="12"
                                required
                                value={data.body_html}
                                onChange={(e) => setData('body_html', e.target.value)}
                                className="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-mono p-3 focus:ring-2 focus:ring-sky-500 leading-relaxed"
                                placeholder="<h2>Hello {{ contact.first_name }},</h2><p>Your message content...</p>"
                            />
                            {errors.body_html && <div className="text-xs text-rose-500 mt-1">{errors.body_html}</div>}
                        </div>

                        {/* Plain Text Fallback */}
                        <div>
                            <div className="flex items-center justify-between mb-1">
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Plain-Text Multipart Fallback (Optional)
                                </label>
                                <button
                                    type="button"
                                    onClick={autoGeneratePlainText}
                                    className="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline"
                                >
                                    <Wand2 className="h-3 w-3" />
                                    Auto-Extract from HTML
                                </button>
                            </div>
                            <textarea
                                rows="4"
                                value={data.body_plain}
                                onChange={(e) => setData('body_plain', e.target.value)}
                                className="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-mono p-3 focus:ring-2 focus:ring-sky-500"
                                placeholder="Plain-text version for email clients that do not render HTML..."
                            />
                        </div>
                    </form>
                </div>

                {/* Live Preview Panel */}
                <div className="lg:col-span-1 space-y-4">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                            <span className="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                <Eye className="h-4 w-4 text-sky-600" />
                                Real-Time Device Simulation
                            </span>
                            <div className="flex items-center bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg">
                                <button
                                    type="button"
                                    onClick={() => setPreviewMode('desktop')}
                                    className={`p-1.5 rounded-md ${previewMode === 'desktop' ? 'bg-white dark:bg-slate-700 text-sky-600 shadow-sm' : 'text-slate-400'}`}
                                    title="Desktop View"
                                >
                                    <Monitor className="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setPreviewMode('mobile')}
                                    className={`p-1.5 rounded-md ${previewMode === 'mobile' ? 'bg-white dark:bg-slate-700 text-sky-600 shadow-sm' : 'text-slate-400'}`}
                                    title="Mobile View"
                                >
                                    <Smartphone className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        {previewMode === 'desktop' ? (
                            <div className="w-full bg-slate-50 dark:bg-slate-800/40 rounded-xl p-3 border border-slate-200 dark:border-slate-700">
                                <div className="text-[11px] text-slate-400 mb-2 font-mono">
                                    Subject: <span className="text-slate-700 dark:text-slate-200 font-bold">{data.subject || 'Subject Preview'}</span>
                                </div>
                                <div 
                                    className="bg-white dark:bg-slate-900 p-4 rounded-lg text-xs text-slate-800 dark:text-slate-200 min-h-[300px] border border-slate-200 dark:border-slate-700 prose prose-sm dark:prose-invert max-w-none"
                                    dangerouslySetInnerHTML={{ __html: previewHtml || data.body_html }}
                                />
                            </div>
                        ) : (
                            <div className="mx-auto w-[280px] bg-slate-800 rounded-[32px] p-2.5 shadow-2xl border-4 border-slate-700">
                                <div className="w-20 h-3 bg-slate-700 rounded-full mx-auto mb-2"></div>
                                <div 
                                    className="bg-white dark:bg-slate-900 rounded-[20px] p-3 text-[11px] text-slate-800 dark:text-slate-200 min-h-[380px] overflow-y-auto"
                                    dangerouslySetInnerHTML={{ __html: previewHtml || data.body_html }}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
