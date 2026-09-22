export default function ApplicationLogo({ className = 'h-9 w-auto', showText = true }) {
    return (
        <div className={`inline-flex items-center gap-2.5 ${className}`}>
            <svg
                className="h-9 w-9 flex-shrink-0"
                viewBox="0 0 40 40"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                {/* Outer Rounded Shield / Hex - Bamcom Deep Blue */}
                <rect
                    x="2"
                    y="2"
                    width="36"
                    height="36"
                    rx="10"
                    className="fill-blue-700 dark:fill-blue-600"
                />
                
                {/* Background Tech Circuit / AI Lines */}
                <path
                    d="M12 28V20M20 28V12M28 28V16"
                    stroke="#FFFFFF"
                    strokeOpacity="0.25"
                    strokeWidth="2"
                    strokeLinecap="round"
                />

                {/* Bamcom "B" Monogram stylized in White */}
                <path
                    d="M13 13H21.5C23.9853 13 26 14.7909 26 17C26 18.6657 24.8967 20.0768 23.3 20.6923C25.4647 21.3149 27 22.9734 27 25C27 27.2091 24.9853 29 22.5 29H13V13Z"
                    fill="#FFFFFF"
                />
                
                {/* Inner Cutouts in Blue */}
                <path
                    d="M17 16.5H21C21.8284 16.5 22.5 17.1716 22.5 18C22.5 18.8284 21.8284 19.5 21 19.5H17V16.5Z"
                    className="fill-blue-700 dark:fill-blue-600"
                />
                <path
                    d="M17 22.5H22C22.8284 22.5 23.5 23.1716 23.5 24C23.5 24.8284 22.8284 25.5 22 25.5H17V22.5Z"
                    className="fill-blue-700 dark:fill-blue-600"
                />

                {/* Bamcom Red Accent AI Indicator Dot */}
                <circle
                    cx="30"
                    cy="10"
                    r="4"
                    className="fill-red-600 dark:fill-red-500"
                />
                <circle
                    cx="30"
                    cy="10"
                    r="2"
                    fill="#FFFFFF"
                />
            </svg>
            
            {showText && (
                <div className="flex flex-col leading-none">
                    <span className="text-lg font-extrabold tracking-tight text-slate-900 dark:text-white">
                        BAMCOM<span className="text-red-600 dark:text-red-500">.</span>
                    </span>
                    <span className="text-[10px] font-semibold tracking-wider text-blue-700 dark:text-blue-400 uppercase">
                        AI CRM Foundation
                    </span>
                </div>
            )}
        </div>
    );
}
