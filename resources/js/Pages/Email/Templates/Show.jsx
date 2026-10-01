import { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    FileText, 
    ArrowLeft, 
    Smartphone, 
    Monitor, 
    Send, 
    Edit, 
    CheckCircle2, 
    Code2, 
    ExternalLink,
    Sparkles,
    ShieldCheck
} from 'lucide-react';

export default function Show({ auth, template, sampleVariables }) {
    const [previewMode, setPreviewMode] = useState('desktop'); // 'desktop' or 'mobile'
    const [wrapBrand, setWrapBrand] = useState(true);
    const [rendered, setRendered] = useState(null);
    const [loading, setLoading] = useState(true);

    const [testRecipient, setTestRecipient] = useState(auth.user?.email || '');
    const [testLoading, setTestLoading] = useState(false);
    const [testSuccess, setTestSuccess] = useState(null);
    const [testError, setTestError] = useState(null);

    const fetchPreview = async (brand) => {
        setLoading(true);
        try {
            const res = await fetch(route('email-templates.preview', template.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ wrap_brand: brand }),
            });
            if (res.ok) {
                const data = await res.json();
                setRendered(data);
            }
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchPreview(wrapBrand);
    }, [wrapBrand]);

    const handleSendTest = async (e) => {
        e.preventDefault();
        setTestLoading(true);
        setTestSuccess(null);
        setTestError(null);

        try {
            const res = await fetch(route('email-templates.test', template.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    recipient_email: testRecipient,
                    wrap_brand: wrapBrand,
                }),
            });

            const data = await res.json();
            if (res.ok) {
                setTestSuccess(data.message || `Test email dispatched to ${testRecipient}`);
            } else {
                setTestError(data.message || 'Failed to dispatch test email.');
            }
        } catch {
            setTestError('Network error delivering test email.');
        } finally {
            setTestLoading(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('email-templates.index')}
                            className="p-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        >
                            <ArrowLeft className="h-4 w-4 text-slate-600 dark:text-slate-300" />
                        </Link>
                        <div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <FileText className="h-5 w-5 text-sky-600" />
                                {template.name}
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Category: <span className="font-semibold capitalize text-slate-700 dark:text-slate-300">{template.category?.label || template.category}</span> &bull; Status: <span className="font-semibold uppercase text-emerald-600">{template.status?.value || template.status}</span>
                            </p>
                        </div>
                    </div>
                    <Link
                        href={route('email-templates.edit', template.id)}
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                    >
                        <Edit className="h-4 w-4" />
                        Edit Template
                    </Link>
                </div>
            }
        >
            <Head title={`Template: ${template.name}`} />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Simulator Controls & Dispatcher */}
                <div className="lg:col-span-1 space-y-6">
                    {/* Test Email Dispatch Card */}
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                        <h3 className="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
                            <Send className="h-4 w-4 text-amber-600" />
                            Dispatch Live Test Email
                        </h3>

                        {testSuccess && (
                            <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-lg text-xs text-emerald-800 dark:text-emerald-200">
                                {testSuccess}
                            </div>
                        )}
                        {testError && (
                            <div className="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg text-xs text-rose-800 dark:text-rose-200">
                                {testError}
                            </div>
                        )}

                        <form onSubmit={handleSendTest} className="space-y-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    Recipient Email Address
                                </label>
                                <input
                                    type="email"
                                    required
                                    value={testRecipient}
                                    onChange={(e) => setTestRecipient(e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs p-2.5 focus:ring-2 focus:ring-amber-500"
                                    placeholder="your-email@bamcomcrm.com"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={testLoading}
                                className="w-full inline-flex items-center justify-center gap-1.5 py-2 px-4 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs transition shadow-sm disabled:opacity-50"
                            >
                                <Send className="h-3.5 w-3.5" />
                                {testLoading ? 'Queuing Delivery...' : 'Dispatch Live Test'}
                            </button>
                        </form>
                    </div>

                    {/* Metadata Card */}
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-3 text-xs">
                        <h3 className="text-sm font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
                            Template Specifications
                        </h3>

                        <div>
                            <span className="text-slate-400 block">Subject Line</span>
                            <div className="font-semibold text-slate-800 dark:text-slate-200">{template.subject}</div>
                        </div>

                        {template.preheader && (
                            <div>
                                <span className="text-slate-400 block">Preheader</span>
                                <div className="text-slate-600 dark:text-slate-300 italic">{template.preheader}</div>
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <div>
                                <span className="text-slate-400 block">Created By</span>
                                <span className="font-medium text-slate-700 dark:text-slate-300">{template.creator?.name || 'System Admin'}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block">Created On</span>
                                <span className="font-medium text-slate-700 dark:text-slate-300">{new Date(template.created_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Live Responsive Viewport Simulator */}
                <div className="lg:col-span-2 space-y-4">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm flex flex-col">
                        {/* Simulation Bar */}
                        <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40">
                            <div className="flex items-center bg-slate-200 dark:bg-slate-800 p-1 rounded-lg">
                                <button
                                    type="button"
                                    onClick={() => setPreviewMode('desktop')}
                                    className={`flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md transition ${
                                        previewMode === 'desktop' ? 'bg-white dark:bg-slate-700 text-sky-600 shadow-sm' : 'text-slate-600 dark:text-slate-400'
                                    }`}
                                >
                                    <Monitor className="h-3.5 w-3.5" />
                                    Desktop View (600px)
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setPreviewMode('mobile')}
                                    className={`flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md transition ${
                                        previewMode === 'mobile' ? 'bg-white dark:bg-slate-700 text-sky-600 shadow-sm' : 'text-slate-600 dark:text-slate-400'
                                    }`}
                                >
                                    <Smartphone className="h-3.5 w-3.5" />
                                    Mobile View (375px)
                                </button>
                            </div>

                            <button
                                type="button"
                                onClick={() => setWrapBrand(!wrapBrand)}
                                className={`text-xs px-3 py-1 rounded-lg border font-medium transition ${
                                    wrapBrand 
                                        ? 'bg-sky-50 dark:bg-sky-950/40 border-sky-300 text-sky-700 dark:text-sky-300' 
                                        : 'bg-slate-100 dark:bg-slate-800 border-slate-300 text-slate-600'
                                }`}
                            >
                                {wrapBrand ? 'Bamcom Brand Wrapper: ACTIVE' : 'Raw Content Only'}
                            </button>
                        </div>

                        {/* Viewport Frame */}
                        <div className="bg-slate-100 dark:bg-slate-950 p-6 flex items-center justify-center min-h-[580px] overflow-y-auto">
                            {loading ? (
                                <div className="text-slate-400 text-sm">Rendering viewport...</div>
                            ) : previewMode === 'desktop' ? (
                                <div className="w-full max-w-[620px] bg-white rounded-xl shadow-lg overflow-hidden border border-slate-200">
                                    <iframe
                                        title="Desktop Frame"
                                        srcDoc={rendered?.html}
                                        className="w-full min-h-[520px] border-0"
                                    />
                                </div>
                            ) : (
                                <div className="w-[375px] h-[667px] bg-white rounded-[38px] shadow-2xl overflow-hidden border-[10px] border-slate-800 relative flex flex-col">
                                    <div className="w-36 h-4 bg-slate-800 rounded-b-xl mx-auto absolute top-0 left-1/2 -translate-x-1/2 z-10"></div>
                                    <iframe
                                        title="Mobile Frame"
                                        srcDoc={rendered?.html}
                                        className="w-full flex-1 border-0 pt-4"
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
