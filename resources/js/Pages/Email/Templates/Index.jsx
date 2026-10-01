import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    FileText, 
    PlusCircle, 
    Search, 
    Eye, 
    Edit, 
    Trash2, 
    ArrowLeft, 
    Smartphone, 
    Monitor, 
    Send, 
    Check, 
    AlertTriangle,
    Layers,
    Mail
} from 'lucide-react';

export default function Index({ auth, templates, categories, statuses, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');

    // Preview and Test Modal State
    const [previewModal, setPreviewModal] = useState({
        isOpen: false,
        template: null,
        rendered: null,
        mode: 'desktop', // 'desktop' or 'mobile'
        wrapBrand: true,
        loading: false,
    });

    const [testEmailModal, setTestEmailModal] = useState({
        isOpen: false,
        templateId: null,
        recipientEmail: auth.user?.email || '',
        loading: false,
        successMessage: null,
        errorMessage: null,
    });

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('email-templates.index'), {
            search: search || undefined,
            category: selectedCategory || undefined,
            status: selectedStatus || undefined,
        }, { preserveState: true, replace: true });
    };

    const handleOpenPreview = async (template) => {
        setPreviewModal({
            isOpen: true,
            template: template,
            rendered: null,
            mode: 'desktop',
            wrapBrand: true,
            loading: true,
        });

        try {
            const res = await fetch(route('email-templates.preview', template.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ wrap_brand: true }),
            });
            if (res.ok) {
                const data = await res.json();
                setPreviewModal(prev => ({ ...prev, rendered: data, loading: false }));
            }
        } catch {
            setPreviewModal(prev => ({ ...prev, loading: false }));
        }
    };

    const handleToggleWrapBrand = async (templateId, wrap) => {
        setPreviewModal(prev => ({ ...prev, wrapBrand: wrap, loading: true }));
        try {
            const res = await fetch(route('email-templates.preview', templateId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ wrap_brand: wrap }),
            });
            if (res.ok) {
                const data = await res.json();
                setPreviewModal(prev => ({ ...prev, rendered: data, loading: false }));
            }
        } catch {
            setPreviewModal(prev => ({ ...prev, loading: false }));
        }
    };

    const handleSendTestEmail = async (e) => {
        e.preventDefault();
        setTestEmailModal(prev => ({ ...prev, loading: true, successMessage: null, errorMessage: null }));

        try {
            const res = await fetch(route('email-templates.test', testEmailModal.templateId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    recipient_email: testEmailModal.recipientEmail,
                    wrap_brand: true,
                }),
            });

            const data = await res.json();
            if (res.ok) {
                setTestEmailModal(prev => ({
                    ...prev,
                    loading: false,
                    successMessage: data.message || 'Test email queued successfully.',
                }));
            } else {
                setTestEmailModal(prev => ({
                    ...prev,
                    loading: false,
                    errorMessage: data.message || 'Failed to dispatch test email.',
                }));
            }
        } catch {
            setTestEmailModal(prev => ({
                ...prev,
                loading: false,
                errorMessage: 'Network error communicating with email server.',
            }));
        }
    };

    const handleDelete = (templateId, name) => {
        if (confirm(`Are you sure you want to delete template "${name}"?`)) {
            router.delete(route('email-templates.destroy', templateId));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('emails.index')}
                            className="p-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        >
                            <ArrowLeft className="h-4 w-4 text-slate-600 dark:text-slate-300" />
                        </Link>
                        <div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <FileText className="h-6 w-6 text-sky-600" />
                                Email Template System
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Reusable responsive email designs with dynamic tokens, CAN-SPAM verification, and multi-device previewing
                            </p>
                        </div>
                    </div>
                    <Link
                        href={route('email-templates.create')}
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                    >
                        <PlusCircle className="h-4 w-4" />
                        New Template
                    </Link>
                </div>
            }
        >
            <Head title="Email Template System" />

            {/* Category Filter Tabs */}
            <div className="flex items-center gap-1.5 overflow-x-auto pb-2 mb-4 scrollbar-thin">
                <button
                    onClick={() => { setSelectedCategory(''); router.get(route('email-templates.index'), { status: selectedStatus || undefined }, { preserveState: true }); }}
                    className={`px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition ${
                        selectedCategory === '' 
                            ? 'bg-sky-600 text-white shadow-sm' 
                            : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'
                    }`}
                >
                    All Categories
                </button>
                {categories?.map((cat) => (
                    <button
                        key={cat.value}
                        onClick={() => { setSelectedCategory(cat.value); router.get(route('email-templates.index'), { category: cat.value, status: selectedStatus || undefined }, { preserveState: true }); }}
                        className={`px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition ${
                            selectedCategory === cat.value 
                                ? 'bg-sky-600 text-white shadow-sm font-semibold' 
                                : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'
                        }`}
                    >
                        {cat.label}
                    </button>
                ))}
            </div>

            {/* Filter Search Bar */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 mb-6 shadow-sm">
                <form onSubmit={handleFilter} className="flex flex-col sm:flex-row items-center gap-3">
                    <div className="relative flex-1 w-full">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Search by template name or subject line..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500"
                        />
                    </div>
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <select
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                            className="text-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 py-2 px-3 focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">All Statuses</option>
                            {statuses?.map((st) => (
                                <option key={st.value} value={st.value}>{st.label}</option>
                            ))}
                        </select>
                        <button
                            type="submit"
                            className="px-4 py-2 text-sm font-semibold rounded-lg bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 hover:bg-slate-800 transition"
                        >
                            Search
                        </button>
                    </div>
                </form>
            </div>

            {/* Templates Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {templates.data?.length > 0 ? (
                    templates.data.map((tmpl) => (
                        <div key={tmpl.id} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm flex flex-col justify-between hover:border-sky-500/50 transition">
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300 capitalize">
                                        {tmpl.category?.label || tmpl.category}
                                    </span>
                                    <span className={`inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full ${
                                        tmpl.status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' :
                                        tmpl.status === 'draft' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' :
                                        'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300'
                                    }`}>
                                        {tmpl.status?.toUpperCase() || 'ACTIVE'}
                                    </span>
                                </div>

                                <h3 className="text-base font-semibold text-slate-900 dark:text-white mb-1 line-clamp-1">
                                    {tmpl.name}
                                </h3>

                                <div className="text-xs text-slate-600 dark:text-slate-300 font-medium mb-1 line-clamp-1">
                                    Subject: {tmpl.subject}
                                </div>

                                {tmpl.preheader && (
                                    <div className="text-[11px] text-slate-400 italic mb-3 line-clamp-1">
                                        Preview: {tmpl.preheader}
                                    </div>
                                )}
                            </div>

                            <div className="border-t border-slate-100 dark:border-slate-800 pt-3 mt-3 flex items-center justify-between">
                                <div className="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        onClick={() => handleOpenPreview(tmpl)}
                                        className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                                        title="Preview on Desktop & Mobile"
                                    >
                                        <Eye className="h-3.5 w-3.5 text-sky-600" />
                                        Preview
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTestEmailModal({ isOpen: true, templateId: tmpl.id, recipientEmail: auth.user?.email || '', loading: false, successMessage: null, errorMessage: null })}
                                        className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 transition"
                                        title="Send Test Email"
                                    >
                                        <Send className="h-3.5 w-3.5" />
                                        Test
                                    </button>
                                </div>

                                <div className="flex items-center gap-1">
                                    <Link
                                        href={route('email-templates.edit', tmpl.id)}
                                        className="p-1.5 text-slate-500 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
                                        title="Edit Template"
                                    >
                                        <Edit className="h-3.5 w-3.5" />
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(tmpl.id, tmpl.name)}
                                        className="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                                        title="Delete Template"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="col-span-full py-16 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl">
                        <FileText className="h-10 w-10 text-slate-400 mx-auto mb-2 opacity-50" />
                        <h4 className="text-sm font-semibold text-slate-800 dark:text-slate-200">No email templates found</h4>
                        <p className="text-xs text-slate-500 mt-1">Get started by building your first template in this category.</p>
                        <Link
                            href={route('email-templates.create')}
                            className="inline-flex items-center gap-1.5 px-4 py-2 mt-4 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                        >
                            <PlusCircle className="h-4 w-4" />
                            Create Template
                        </Link>
                    </div>
                )}
            </div>

            {/* Pagination */}
            {templates.links && (
                <div className="mt-6 flex items-center justify-between text-xs text-slate-500">
                    <div>Showing {templates.from ?? 0} to {templates.to ?? 0} of {templates.total} templates</div>
                    <div className="flex gap-1">
                        {templates.links.map((link, idx) => (
                            <Link
                                key={idx}
                                href={link.url || '#'}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-3 py-1 rounded-md text-xs font-medium ${
                                    link.active ? 'bg-sky-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                            />
                        ))}
                    </div>
                </div>
            )}

            {/* Desktop & Mobile Dual-Mode Preview Modal */}
            {previewModal.isOpen && (
                <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden shadow-2xl">
                        {/* Preview Header / Device Switcher */}
                        <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Eye className="h-4 w-4 text-sky-600" />
                                    Template Preview: {previewModal.template?.name}
                                </h3>
                                <div className="text-xs text-slate-500 mt-0.5">
                                    Subject: <span className="font-medium text-slate-700 dark:text-slate-300">{previewModal.rendered?.subject}</span>
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                {/* Device Switcher Buttons */}
                                <div className="flex items-center bg-slate-200 dark:bg-slate-800 p-1 rounded-lg">
                                    <button
                                        type="button"
                                        onClick={() => setPreviewModal(prev => ({ ...prev, mode: 'desktop' }))}
                                        className={`flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md transition ${
                                            previewModal.mode === 'desktop' ? 'bg-white dark:bg-slate-700 text-sky-600 shadow-sm' : 'text-slate-600 dark:text-slate-400'
                                        }`}
                                    >
                                        <Monitor className="h-3.5 w-3.5" />
                                        Desktop (600px)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setPreviewModal(prev => ({ ...prev, mode: 'mobile' }))}
                                        className={`flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md transition ${
                                            previewModal.mode === 'mobile' ? 'bg-white dark:bg-slate-700 text-sky-600 shadow-sm' : 'text-slate-600 dark:text-slate-400'
                                        }`}
                                    >
                                        <Smartphone className="h-3.5 w-3.5" />
                                        Mobile (375px)
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    onClick={() => handleToggleWrapBrand(previewModal.template.id, !previewModal.wrapBrand)}
                                    className={`text-xs px-2.5 py-1 rounded-lg border transition ${
                                        previewModal.wrapBrand ? 'bg-sky-50 dark:bg-sky-950/40 border-sky-300 text-sky-700 dark:text-sky-300' : 'bg-slate-100 dark:bg-slate-800 border-slate-300 text-slate-600'
                                    }`}
                                >
                                    {previewModal.wrapBrand ? 'Brand Header/Footer: ON' : 'Brand Header/Footer: OFF'}
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setPreviewModal(prev => ({ ...prev, isOpen: false }))}
                                    className="text-slate-400 hover:text-slate-600 text-xl font-bold px-1"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>

                        {/* Preview Screen Body */}
                        <div className="flex-1 bg-slate-100 dark:bg-slate-950 p-6 overflow-y-auto flex items-center justify-center">
                            {previewModal.loading ? (
                                <div className="text-slate-400 text-sm">Rendering dynamic preview...</div>
                            ) : previewModal.mode === 'desktop' ? (
                                /* Desktop 600px Container */
                                <div className="w-full max-w-[620px] bg-white rounded-xl shadow-lg overflow-hidden border border-slate-200">
                                    <iframe
                                        title="Desktop Preview"
                                        srcDoc={previewModal.rendered?.html}
                                        className="w-full min-h-[500px] border-0"
                                    />
                                </div>
                            ) : (
                                /* Realistic Mobile 375px Frame */
                                <div className="w-[375px] h-[667px] bg-white rounded-[38px] shadow-2xl overflow-hidden border-[10px] border-slate-800 relative flex flex-col">
                                    {/* Mobile Camera Notch */}
                                    <div className="w-36 h-4 bg-slate-800 rounded-b-xl mx-auto absolute top-0 left-1/2 -translate-x-1/2 z-10"></div>
                                    <iframe
                                        title="Mobile Preview"
                                        srcDoc={previewModal.rendered?.html}
                                        className="w-full flex-1 border-0 pt-4"
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Test Email Dispatch Modal */}
            {testEmailModal.isOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                        <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40">
                            <h3 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                <Send className="h-5 w-5 text-amber-600" />
                                Send Test Email
                            </h3>
                            <button onClick={() => setTestEmailModal(prev => ({ ...prev, isOpen: false }))} className="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                        </div>
                        <form onSubmit={handleSendTestEmail} className="p-6 space-y-4">
                            {testEmailModal.successMessage && (
                                <div className="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 p-3 rounded-lg text-xs">
                                    {testEmailModal.successMessage}
                                </div>
                            )}
                            {testEmailModal.errorMessage && (
                                <div className="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 p-3 rounded-lg text-xs">
                                    {testEmailModal.errorMessage}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Recipient Test Email *</label>
                                <input
                                    type="email"
                                    required
                                    value={testEmailModal.recipientEmail}
                                    onChange={(e) => setTestEmailModal(prev => ({ ...prev, recipientEmail: e.target.value }))}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-amber-500"
                                    placeholder="qa@bamcomcrm.com"
                                />
                                <p className="text-[11px] text-slate-400 mt-1">
                                    Dispatches a live test email interpolated with realistic contact, agent, property, and inspection sample data.
                                </p>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setTestEmailModal(prev => ({ ...prev, isOpen: false }))}
                                    className="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 transition"
                                >
                                    Close
                                </button>
                                <button
                                    type="submit"
                                    disabled={testEmailModal.loading}
                                    className="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition shadow-sm disabled:opacity-50"
                                >
                                    <Send className="h-4 w-4" />
                                    {testEmailModal.loading ? 'Sending...' : 'Send Live Test'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
