import { useState, useRef } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import { 
    Upload, 
    FileSpreadsheet, 
    FileText, 
    Download, 
    CheckCircle2, 
    AlertCircle, 
    AlertTriangle, 
    RefreshCw, 
    X, 
    ExternalLink, 
    Check, 
    ChevronDown, 
    ChevronUp,
    Info,
    ArrowRight
} from 'lucide-react';

export default function ImportContactsModal({ 
    show, 
    onClose, 
    users = [], 
    statuses = [], 
    leadSources = [] 
}) {
    // Mode: 'csv' | 'google_sheet'
    const [importSource, setImportSource] = useState('csv');

    // CSV File state
    const [selectedFile, setSelectedFile] = useState(null);
    const [dragActive, setDragActive] = useState(false);
    const fileInputRef = useRef(null);

    // Google Sheet URL state
    const [sheetUrl, setSheetUrl] = useState('');

    // Configuration Options
    const [duplicateHandling, setDuplicateHandling] = useState('skip'); // 'skip' | 'update'
    const [defaultLeadSource, setDefaultLeadSource] = useState('direct');
    const [defaultStatus, setDefaultStatus] = useState('lead');
    const [defaultAssignedUserId, setDefaultAssignedUserId] = useState('');

    // Processing & Preview States
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewData, setPreviewData] = useState(null);
    const [importing, setImporting] = useState(false);
    const [importReport, setImportReport] = useState(null);
    const [errorMessage, setErrorMessage] = useState('');
    const [showErrorsList, setShowErrorsList] = useState(false);

    // Handle File Drag & Drop
    const handleDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            const file = e.dataTransfer.files[0];
            validateAndSetFile(file);
        }
    };

    const handleFileChange = (e) => {
        if (e.target.files && e.target.files[0]) {
            validateAndSetFile(e.target.files[0]);
        }
    };

    const validateAndSetFile = (file) => {
        setErrorMessage('');
        setPreviewData(null);
        setImportReport(null);

        const ext = file.name.split('.').pop().toLowerCase();
        if (!['csv', 'txt'].includes(ext)) {
            setErrorMessage('Please select a valid CSV file (.csv or .txt).');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setErrorMessage('File size exceeds the 10MB limit.');
            return;
        }

        setSelectedFile(file);
    };

    // Reset Form
    const handleReset = () => {
        setSelectedFile(null);
        setSheetUrl('');
        setPreviewData(null);
        setImportReport(null);
        setErrorMessage('');
        setShowErrorsList(false);
    };

    const handleModalClose = () => {
        if (importing) return;
        handleReset();
        onClose();
    };

    // Preview Request
    const handlePreview = async () => {
        setErrorMessage('');
        setPreviewLoading(true);

        const formData = new FormData();
        formData.append('source_type', importSource);

        if (importSource === 'csv') {
            if (!selectedFile) {
                setErrorMessage('Please select a CSV file first.');
                setPreviewLoading(false);
                return;
            }
            formData.append('file', selectedFile);
        } else {
            if (!sheetUrl.trim()) {
                setErrorMessage('Please enter a Google Sheet URL.');
                setPreviewLoading(false);
                return;
            }
            formData.append('sheet_url', sheetUrl.trim());
        }

        try {
            const res = await fetch(route('contacts.import.preview'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: formData,
            });

            const data = await res.json();

            if (!res.ok) {
                const message = data.errors?.file?.[0] || data.errors?.sheet_url?.[0] || data.message || 'Unable to preview records.';
                setErrorMessage(message);
            } else {
                setPreviewData(data);
            }
        } catch (e) {
            setErrorMessage('Failed to connect to server: ' + e.message);
        } finally {
            setPreviewLoading(false);
        }
    };

    // Execute Import
    const handleImport = async () => {
        setErrorMessage('');
        setImporting(true);

        const formData = new FormData();
        formData.append('duplicate_handling', duplicateHandling);
        formData.append('default_lead_source', defaultLeadSource);
        formData.append('default_status', defaultStatus);
        if (defaultAssignedUserId) {
            formData.append('default_assigned_user_id', defaultAssignedUserId);
        }

        const endpoint = importSource === 'csv'
            ? route('contacts.import.csv')
            : route('contacts.import.google-sheet');

        if (importSource === 'csv') {
            if (!selectedFile) {
                setErrorMessage('Please select a CSV file.');
                setImporting(false);
                return;
            }
            formData.append('file', selectedFile);
        } else {
            if (!sheetUrl.trim()) {
                setErrorMessage('Please enter a Google Sheet URL.');
                setImporting(false);
                return;
            }
            formData.append('sheet_url', sheetUrl.trim());
        }

        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: formData,
            });

            const data = await res.json();

            if (!res.ok) {
                const message = data.errors?.file?.[0] || data.errors?.sheet_url?.[0] || data.message || 'Failed to complete import.';
                setErrorMessage(message);
            } else {
                setImportReport(data);
            }
        } catch (e) {
            setErrorMessage('Network error during import: ' + e.message);
        } finally {
            setImporting(false);
        }
    };

    const handleFinishAndRefresh = () => {
        handleModalClose();
        router.reload({ preserveScroll: true });
    };

    return (
        <Modal show={show} onClose={handleModalClose} maxWidth="2xl">
            <div className="bg-white dark:bg-slate-900 rounded-2xl overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-800">
                {/* Header */}
                <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-blue-600/10 text-blue-600 dark:text-blue-400 flex items-center justify-center border border-blue-200 dark:border-blue-900/50">
                            {importSource === 'csv' ? (
                                <FileSpreadsheet className="h-5 w-5" />
                            ) : (
                                <FileText className="h-5 w-5 text-emerald-600" />
                            )}
                        </div>
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Import Contacts
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Batch import client and lead records from CSV or Google Sheets.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={handleModalClose}
                        disabled={importing}
                        className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Import Report Success Screen */}
                {importReport ? (
                    <div className="p-6 space-y-6">
                        <div className="text-center py-4">
                            <div className="h-16 w-16 mx-auto rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center text-emerald-600 dark:text-emerald-400 mb-3">
                                <CheckCircle2 className="h-8 w-8" />
                            </div>
                            <h4 className="text-lg font-bold text-slate-900 dark:text-white">
                                Import Completed Successfully
                            </h4>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Finished processing records from {importReport.source}.
                            </p>
                        </div>

                        {/* Metric Counts */}
                        <div className="grid grid-cols-4 gap-3 text-center">
                            <div className="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Processed</span>
                                <div className="text-lg font-bold text-slate-900 dark:text-white mt-0.5">{importReport.total_rows}</div>
                            </div>
                            <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 rounded-xl border border-emerald-200 dark:border-emerald-800">
                                <span className="text-[11px] font-medium text-emerald-700 dark:text-emerald-400">New Created</span>
                                <div className="text-lg font-bold text-emerald-600 dark:text-emerald-300 mt-0.5">{importReport.imported_count}</div>
                            </div>
                            <div className="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-xl border border-blue-200 dark:border-blue-800">
                                <span className="text-[11px] font-medium text-blue-700 dark:text-blue-400">Updated</span>
                                <div className="text-lg font-bold text-blue-600 dark:text-blue-300 mt-0.5">{importReport.updated_count}</div>
                            </div>
                            <div className="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-800">
                                <span className="text-[11px] font-medium text-amber-700 dark:text-amber-400">Skipped</span>
                                <div className="text-lg font-bold text-amber-600 dark:text-amber-300 mt-0.5">{importReport.skipped_count}</div>
                            </div>
                        </div>

                        {/* Error Breakdown Accordion */}
                        {importReport.error_count > 0 && (
                            <div className="rounded-xl border border-red-200 dark:border-red-900/60 bg-red-50/50 dark:bg-red-950/30 overflow-hidden">
                                <button
                                    type="button"
                                    onClick={() => setShowErrorsList(!showErrorsList)}
                                    className="w-full px-4 py-2.5 flex items-center justify-between text-xs font-semibold text-red-800 dark:text-red-300 hover:bg-red-100/50 transition"
                                >
                                    <div className="flex items-center gap-2">
                                        <AlertTriangle className="h-4 w-4 text-red-500" />
                                        <span>{importReport.error_count} row(s) had errors and could not be imported</span>
                                    </div>
                                    {showErrorsList ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                                </button>
                                {showErrorsList && (
                                    <div className="p-3 max-h-48 overflow-y-auto divide-y divide-red-200/50 dark:divide-red-900/40 text-[11px] text-red-700 dark:text-red-300">
                                        {importReport.errors.map((err, idx) => (
                                            <div key={idx} className="py-1.5 flex items-start gap-2">
                                                <span className="font-bold shrink-0">Row {err.row}:</span>
                                                <span className="flex-1">{err.message}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex justify-end pt-2">
                            <button
                                type="button"
                                onClick={handleFinishAndRefresh}
                                className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl shadow-sm transition"
                            >
                                Done & Refresh Contacts
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="p-6 space-y-5">
                        {/* Tab Switcher: CSV vs Google Sheets */}
                        <div className="flex rounded-xl bg-slate-100 dark:bg-slate-800 p-1">
                            <button
                                type="button"
                                onClick={() => { setImportSource('csv'); setErrorMessage(''); setPreviewData(null); }}
                                className={`flex-1 flex items-center justify-center gap-2 py-2 text-xs font-bold rounded-lg transition ${
                                    importSource === 'csv'
                                        ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-white shadow-xs'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'
                                }`}
                            >
                                <FileSpreadsheet className="h-4 w-4" />
                                <span>CSV File Upload</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => { setImportSource('google_sheet'); setErrorMessage(''); setPreviewData(null); }}
                                className={`flex-1 flex items-center justify-center gap-2 py-2 text-xs font-bold rounded-lg transition ${
                                    importSource === 'google_sheet'
                                        ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'
                                }`}
                            >
                                <FileText className="h-4 w-4" />
                                <span>Google Sheet Link</span>
                            </button>
                        </div>

                        {/* Error Alert */}
                        {errorMessage && (
                            <div className="p-3.5 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/60 text-red-800 dark:text-red-300 text-xs flex items-start gap-2.5">
                                <AlertCircle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                                <span className="flex-1">{errorMessage}</span>
                            </div>
                        )}

                        {/* TAB 1: CSV FILE UPLOAD */}
                        {importSource === 'csv' && (
                            <div className="space-y-3">
                                <div
                                    onDragEnter={handleDrag}
                                    onDragLeave={handleDrag}
                                    onDragOver={handleDrag}
                                    onDrop={handleDrop}
                                    onClick={() => fileInputRef.current?.click()}
                                    className={`
                                        border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all
                                        ${dragActive
                                            ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/20'
                                            : selectedFile
                                            ? 'border-emerald-400 bg-emerald-50/30 dark:bg-emerald-950/10'
                                            : 'border-slate-300 dark:border-slate-700 hover:border-blue-400 bg-slate-50/50 dark:bg-slate-800/30'}
                                    `}
                                >
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept=".csv,.txt"
                                        onChange={handleFileChange}
                                        className="hidden"
                                    />
                                    {selectedFile ? (
                                        <div className="flex flex-col items-center">
                                            <div className="h-12 w-12 rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-600 flex items-center justify-center mb-2">
                                                <Check className="h-6 w-6" />
                                            </div>
                                            <span className="text-xs font-bold text-slate-900 dark:text-white">
                                                {selectedFile.name}
                                            </span>
                                            <span className="text-[10px] text-slate-400 mt-0.5">
                                                {(selectedFile.size / 1024).toFixed(1)} KB &bull; Click or drag to replace
                                            </span>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center">
                                            <div className="h-12 w-12 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 flex items-center justify-center mb-2">
                                                <Upload className="h-6 w-6" />
                                            </div>
                                            <p className="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                                Click to upload or drag & drop CSV file
                                            </p>
                                            <p className="text-[11px] text-slate-400 mt-1">
                                                Standard UTF-8 CSV with phone, name, email columns (up to 10MB)
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center justify-between pt-1">
                                    <span className="text-[11px] text-slate-400">
                                        Need the standard format?
                                    </span>
                                    <a
                                        href={route('contacts.import.template')}
                                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        <Download className="h-3.5 w-3.5" />
                                        <span>Download CSV Template</span>
                                    </a>
                                </div>
                            </div>
                        )}

                        {/* TAB 2: GOOGLE SHEET LINK */}
                        {importSource === 'google_sheet' && (
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                        Google Sheet Link (Public or Anyone with Link)
                                    </label>
                                    <input
                                        type="url"
                                        value={sheetUrl}
                                        onChange={(e) => { setSheetUrl(e.target.value); setPreviewData(null); }}
                                        placeholder="https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5n.../edit"
                                        className="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                                    />
                                </div>

                                <div className="p-3.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/50 text-xs text-emerald-900 dark:text-emerald-300 space-y-1.5">
                                    <div className="flex items-center gap-1.5 font-bold">
                                        <Info className="h-4 w-4 text-emerald-600 shrink-0" />
                                        <span>How to prepare your Google Sheet:</span>
                                    </div>
                                    <ol className="list-decimal list-inside space-y-1 text-[11px] text-emerald-800 dark:text-emerald-400 pl-1 leading-relaxed">
                                        <li>Open your Google Sheet and click <strong>Share</strong> (top right).</li>
                                        <li>Change General Access to <strong>"Anyone with the link"</strong> set to <strong>Viewer</strong>.</li>
                                        <li>Copy and paste the URL above. First row must contain column headers.</li>
                                    </ol>
                                </div>
                            </div>
                        )}

                        {/* CONFIGURATION OPTIONS */}
                        <div className="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-3.5">
                            <h4 className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                Import Rules & Defaults
                            </h4>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                {/* Duplicate Handling */}
                                <div>
                                    <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                        Duplicate Handling (by Phone / Email)
                                    </label>
                                    <select
                                        value={duplicateHandling}
                                        onChange={(e) => setDuplicateHandling(e.target.value)}
                                        className="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white py-1.5 px-2.5"
                                    >
                                        <option value="skip">Skip existing contacts</option>
                                        <option value="update">Update existing contacts with new data</option>
                                    </select>
                                </div>

                                {/* Default Lead Source */}
                                <div>
                                    <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                        Default Lead Source (if empty in row)
                                    </label>
                                    <select
                                        value={defaultLeadSource}
                                        onChange={(e) => setDefaultLeadSource(e.target.value)}
                                        className="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white py-1.5 px-2.5 capitalize"
                                    >
                                        {leadSources.map(s => (
                                            <option key={s.value} value={s.value}>{s.label}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Default Status */}
                                <div>
                                    <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                        Default Lifecycle Status
                                    </label>
                                    <select
                                        value={defaultStatus}
                                        onChange={(e) => setDefaultStatus(e.target.value)}
                                        className="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white py-1.5 px-2.5 capitalize"
                                    >
                                        {statuses.map(st => (
                                            <option key={st.value} value={st.value}>{st.label}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Assigned Agent */}
                                <div>
                                    <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                        Assign Contacts To (Optional)
                                    </label>
                                    <select
                                        value={defaultAssignedUserId}
                                        onChange={(e) => setDefaultAssignedUserId(e.target.value)}
                                        className="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white py-1.5 px-2.5"
                                    >
                                        <option value="">Leave Unassigned</option>
                                        {users.map(u => (
                                            <option key={u.id} value={u.id}>{u.name} ({u.role})</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        </div>

                        {/* PREVIEW SECTION */}
                        {previewData && (
                            <div className="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-2.5 animate-in fade-in duration-200">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-bold text-slate-900 dark:text-white">
                                            Preview: {previewData.total_rows} total row(s) detected
                                        </span>
                                    </div>
                                    <span className="text-[10px] text-slate-400">
                                        Showing first {previewData.sample_rows.length} rows
                                    </span>
                                </div>

                                <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 max-h-48 text-xs">
                                    <table className="w-full divide-y divide-slate-200 dark:divide-slate-800 text-left">
                                        <thead className="bg-slate-50 dark:bg-slate-800/80 text-[10px] font-bold text-slate-500 uppercase">
                                            <tr>
                                                <th className="px-3 py-2">Name</th>
                                                <th className="px-3 py-2">Phone (Normalized)</th>
                                                <th className="px-3 py-2">Email</th>
                                                <th className="px-3 py-2">Location</th>
                                                <th className="px-3 py-2">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 bg-white dark:bg-slate-900 text-[11px]">
                                            {previewData.sample_rows.map((row, i) => (
                                                <tr key={i} className="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                                                    <td className="px-3 py-2 font-semibold text-slate-900 dark:text-white">
                                                        {row.first_name} {row.last_name}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {row.is_valid_phone ? (
                                                            <span className="text-emerald-600 dark:text-emerald-400 font-mono">
                                                                {row.normalized_phone}
                                                            </span>
                                                        ) : (
                                                            <span className="text-amber-500 font-mono">
                                                                {row.phone || 'None'}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-slate-500">{row.email || '—'}</td>
                                                    <td className="px-3 py-2 text-slate-500">{row.location || '—'}</td>
                                                    <td className="px-3 py-2">
                                                        {row.has_duplicate ? (
                                                            <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                                Duplicate ({row.duplicate_name})
                                                            </span>
                                                        ) : (
                                                            <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                                New
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* ACTION BUTTONS */}
                        <div className="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <button
                                type="button"
                                onClick={handlePreview}
                                disabled={previewLoading || importing || (importSource === 'csv' ? !selectedFile : !sheetUrl.trim())}
                                className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition disabled:opacity-50"
                            >
                                {previewLoading && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                                <span>Preview Data</span>
                            </button>

                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={handleModalClose}
                                    disabled={importing}
                                    className="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={handleImport}
                                    disabled={importing || previewLoading || (importSource === 'csv' ? !selectedFile : !sheetUrl.trim())}
                                    className={`inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-semibold text-white shadow-sm transition ${
                                        importSource === 'csv'
                                            ? 'bg-blue-600 hover:bg-blue-700'
                                            : 'bg-emerald-600 hover:bg-emerald-700'
                                    } disabled:opacity-50`}
                                >
                                    {importing ? (
                                        <>
                                            <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                                            <span>Importing Contacts...</span>
                                        </>
                                    ) : (
                                        <>
                                            <span>Execute Import</span>
                                            <ArrowRight className="h-3.5 w-3.5" />
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
}
