import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
    FileText, 
    PlusCircle, 
    Search, 
    Eye, 
    Check, 
    Trash2, 
    ArrowLeft,
    Sparkles
} from 'lucide-react';

export default function Index({ auth, templates, filters }) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [previewContent, setPreviewContent] = useState(null);
    const [isPreviewOpen, setIsPreviewOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        subject: '',
        category: 'marketing',
        body_html: '',
        body_plain: '',
    });

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        post(route('email-templates.store'), {
            onSuccess: () => {
                setIsCreateOpen(false);
                reset();
            }
        });
    };

    const handlePreview = async (templateId) => {
        try {
            const res = await fetch(route('email-templates.preview', templateId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({}),
            });
            if (res.ok) {
                const data = await res.json();
                setPreviewContent(data);
                setIsPreviewOpen(true);
            }
        } catch {
            alert('Could not render template preview.');
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
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
                                Email Templates
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Reusable CRM email designs with merge tags and live previewing
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => setIsCreateOpen(true)}
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm"
                    >
                        <PlusCircle className="h-4 w-4" />
                        Create Template
                    </button>
                </div>
            }
        >
            <Head title="Email Templates" />

            {/* Templates Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {templates.data?.length > 0 ? (
                    templates.data.map((tmpl) => (
                        <div key={tmpl.id} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm flex flex-col justify-between hover:border-sky-500/50 transition">
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300 capitalize">
                                        {tmpl.category}
                                    </span>
                                    <span className="text-[11px] text-slate-400">
                                        {new Date(tmpl.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                                <h3 className="text-base font-semibold text-slate-900 dark:text-white mb-1">
                                    {tmpl.name}
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mb-3">
                                    Subject: {tmpl.subject}
                                </p>
                            </div>

                            <div className="border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-between">
                                <span className="text-[11px] text-slate-400">
                                    {tmpl.variables?.length ?? 0} merge tags
                                </span>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => handlePreview(tmpl.id)}
                                        className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                                    >
                                        <Eye className="h-3.5 w-3.5" />
                                        Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="col-span-full py-16 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl">
                        <FileText className="h-10 w-10 text-slate-400 mx-auto mb-2 opacity-50" />
                        <h4 className="text-sm font-semibold text-slate-800 dark:text-slate-200">No email templates found</h4>
                        <p className="text-xs text-slate-500 mt-1">Get started by creating your first reusable transactional or marketing template.</p>
                    </div>
                )}
            </div>

            {/* Create Template Modal */}
            {isCreateOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl">
                        <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40">
                            <h3 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                <PlusCircle className="h-5 w-5 text-sky-600" />
                                Create New Email Template
                            </h3>
                            <button onClick={() => setIsCreateOpen(false)} className="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                        </div>
                        <form onSubmit={handleCreateSubmit} className="p-6 space-y-4">
                            <div className="grid grid-cols-3 gap-4">
                                <div className="col-span-2">
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Template Name *</label>
                                    <input
                                        type="text"
                                        required
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                        placeholder="Lead Welcome & Portfolio Brief"
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
                                        <option value="marketing">Marketing</option>
                                        <option value="transactional">Transactional</option>
                                        <option value="onboarding">Onboarding</option>
                                        <option value="notification">Notification</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Subject Line * (Supports merge tags)</label>
                                <input
                                    type="text"
                                    required
                                    value={data.subject}
                                    onChange={(e) => setData('subject', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm p-2.5 focus:ring-2 focus:ring-sky-500"
                                    placeholder="Welcome to {{company.name}}, {{contact.first_name}}!"
                                />
                                {errors.subject && <div className="text-xs text-rose-500 mt-1">{errors.subject}</div>}
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">HTML Template Body *</label>
                                    <span className="text-[11px] text-sky-600 dark:text-sky-400">Available: &#123;&#123;contact.first_name&#125;&#125;, &#123;&#123;company.name&#125;&#125;, &#123;&#123;unsubscribe_url&#125;&#125;</span>
                                </div>
                                <textarea
                                    rows="7"
                                    required
                                    value={data.body_html}
                                    onChange={(e) => setData('body_html', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-mono p-2.5 focus:ring-2 focus:ring-sky-500"
                                    placeholder="<h2>Hello {{contact.first_name}},</h2><p>Welcome to our luxury property collection.</p><p><a href='{{unsubscribe_url}}'>Unsubscribe</a></p>"
                                />
                                {errors.body_html && <div className="text-xs text-rose-500 mt-1">{errors.body_html}</div>}
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsCreateOpen(false)}
                                    className="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm disabled:opacity-50"
                                >
                                    {processing ? 'Saving...' : 'Save Template'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Preview Render Modal */}
            {isPreviewOpen && previewContent && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto shadow-2xl p-6">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                            <h3 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                <Eye className="h-5 w-5 text-sky-600" />
                                Rendered Template Preview
                            </h3>
                            <button onClick={() => setIsPreviewOpen(false)} className="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                        </div>

                        <div className="mb-4">
                            <span className="text-xs text-slate-400 block mb-0.5">Interpolated Subject</span>
                            <div className="text-sm font-semibold text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-800 p-2.5 rounded-lg">
                                {previewContent.subject}
                            </div>
                        </div>

                        <div className="mb-4">
                            <span className="text-xs text-slate-400 block mb-0.5">Rendered HTML View</span>
                            <div 
                                className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 rounded-xl min-h-[160px] text-sm text-slate-800 dark:text-slate-100"
                                dangerouslySetInnerHTML={{ __html: previewContent.html }}
                            />
                        </div>

                        <div>
                            <span className="text-xs text-slate-400 block mb-0.5">Generated Plain-Text Fallback</span>
                            <pre className="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl font-mono text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                                {previewContent.plain}
                            </pre>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
