export default function ApplicationLogo({ className = 'h-9 w-auto', showText = true }) {
    return (
        <div className={`inline-flex items-center gap-2.5 ${className}`}>
            <div className="h-9 w-9 flex-shrink-0 flex items-center justify-center rounded-xl bg-white shadow-sm p-1 border border-slate-200/80 dark:border-slate-700">
                <img
                    src="/images/bamcom-mark.png"
                    alt="Bamcom CRM"
                    className="h-full w-full object-contain"
                />
            </div>
            
            {showText && (
                <div className="flex flex-col leading-none">
                    <span className="text-lg font-extrabold tracking-tight text-slate-900 dark:text-white">
                        BAMCOM<span className="text-red-600 dark:text-red-500">.</span>
                    </span>
                    <span className="text-[10px] font-semibold tracking-wider text-blue-700 dark:text-blue-400 uppercase">
                        CRM Portal
                    </span>
                </div>
            )}
        </div>
    );
}
