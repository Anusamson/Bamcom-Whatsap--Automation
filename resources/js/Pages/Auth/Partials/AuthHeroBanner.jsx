export default function AuthHeroBanner({
    title = 'Welcome to Bamcomite CRM',
    subtitle = 'One click to manage real estate leads, site inspections & WhatsApp automation.',
}) {
    return (
        <div className="relative w-full h-full min-h-[500px] lg:min-h-[580px] rounded-3xl bg-gradient-to-br from-[#1A62F8] via-[#1554E4] to-[#0D3EB8] p-8 lg:p-10 flex flex-col justify-between overflow-hidden shadow-xl shadow-blue-900/20 text-white">
            {/* Ambient Background Glows */}
            <div className="absolute -top-24 -left-24 w-80 h-80 rounded-full bg-blue-400/25 blur-3xl pointer-events-none" />
            <div className="absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-blue-600/40 blur-3xl pointer-events-none" />

            {/* Top Brand & Headline */}
            <div className="relative z-10 space-y-5">
                {/* Logo Badge */}
                <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 shadow-md">
                    <img
                        src="/images/bamcom-mark.png"
                        alt="Bamcom"
                        className="w-7 h-7 object-contain drop-shadow-sm"
                    />
                </div>

                {/* Headline Text */}
                <div className="space-y-2">
                    <h1 className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight leading-[1.2]">
                        {title.includes('Bamcomite CRM') ? (
                            <>
                                Welcome to<br />
                                <span className="text-white drop-shadow-sm">Bamcomite CRM</span>
                            </>
                        ) : (
                            title
                        )}
                    </h1>
                    <p className="text-blue-100/90 text-sm max-w-sm font-medium leading-relaxed">
                        {subtitle}
                    </p>
                </div>
            </div>

            {/* Illustration Container (Floating Tablet, Character & Plant) */}
            <div className="relative z-10 w-full mt-4 flex items-end justify-center">
                <svg
                    viewBox="0 0 540 380"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    className="w-full max-w-[480px] h-auto drop-shadow-2xl select-none"
                >
                    <defs>
                        {/* Background Floating Hexagon Gradients */}
                        <linearGradient id="hexGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stopColor="#FFFFFF" stopOpacity="0.22" />
                            <stop offset="100%" stopColor="#FFFFFF" stopOpacity="0.04" />
                        </linearGradient>

                        {/* Tablet Outer Glow & Shadow */}
                        <filter id="tabletGlow" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="18" stdDeviation="16" floodColor="#092873" floodOpacity="0.38" />
                        </filter>

                        {/* 3D Sphere Radial Gradient */}
                        <radialGradient id="sphereGrad" cx="35%" cy="30%" r="65%">
                            <stop offset="0%" stopColor="#67E8F9" />
                            <stop offset="35%" stopColor="#0EA5E9" />
                            <stop offset="75%" stopColor="#0284C7" />
                            <stop offset="100%" stopColor="#0369A1" />
                        </radialGradient>

                        {/* Wave Line Gradients */}
                        <linearGradient id="waveCyan" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stopColor="#22D3EE" />
                            <stop offset="100%" stopColor="#06B6D4" />
                        </linearGradient>
                        <linearGradient id="wavePink" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stopColor="#F43F5E" />
                            <stop offset="100%" stopColor="#EC4899" />
                        </linearGradient>

                        {/* Ground Shadow Gradient */}
                        <radialGradient id="groundShadow" cx="50%" cy="50%" r="50%">
                            <stop offset="0%" stopColor="#08256B" stopOpacity="0.65" />
                            <stop offset="60%" stopColor="#0C348E" stopOpacity="0.3" />
                            <stop offset="100%" stopColor="#1554E4" stopOpacity="0" />
                        </radialGradient>
                    </defs>

                    {/* Ground Soft Shadows */}
                    <ellipse cx="270" cy="358" rx="220" ry="18" fill="url(#groundShadow)" />

                    {/* Floating Background Hexagons (Matching reference image) */}
                    <g opacity="0.85">
                        {/* Top-Right Hexagon */}
                        <polygon
                            points="450,45 480,62 480,98 450,115 420,98 420,62"
                            fill="url(#hexGrad)"
                            stroke="rgba(255,255,255,0.25)"
                            strokeWidth="1.5"
                        />
                        {/* Upper-Center Hexagon */}
                        <polygon
                            points="350,75 375,90 375,120 350,135 325,120 325,90"
                            fill="url(#hexGrad)"
                            stroke="rgba(255,255,255,0.2)"
                            strokeWidth="1.5"
                        />
                        {/* Far Right Lower Hexagon */}
                        <polygon
                            points="500,160 528,176 528,210 500,226 472,210 472,176"
                            fill="url(#hexGrad)"
                            stroke="rgba(255,255,255,0.22)"
                            strokeWidth="1.5"
                        />
                        {/* Mid-Lower Hexagon */}
                        <polygon
                            points="480,265 505,280 505,310 480,325 455,310 455,280"
                            fill="url(#hexGrad)"
                            stroke="rgba(255,255,255,0.18)"
                            strokeWidth="1.5"
                        />
                        {/* Behind Tablet Left Hexagon */}
                        <polygon
                            points="220,120 242,133 242,160 220,173 198,160 198,133"
                            fill="url(#hexGrad)"
                            stroke="rgba(255,255,255,0.18)"
                            strokeWidth="1"
                        />
                    </g>

                    {/* ================= FLOATING TABLET DISPLAY ================= */}
                    <g filter="url(#tabletGlow)" transform="translate(18, 0)">
                        {/* Tablet Bezel Outer Frame */}
                        <rect
                            x="180"
                            y="110"
                            width="290"
                            height="195"
                            rx="18"
                            fill="#FFFFFF"
                            stroke="#E2E8F0"
                            strokeWidth="2"
                        />

                        {/* Top Window Navigation Dots (macOS style) */}
                        <circle cx="445" cy="124" r="3" fill="#EF4444" />
                        <circle cx="454" cy="124" r="3" fill="#F59E0B" />
                        <circle cx="463" cy="124" r="3" fill="#10B981" />

                        {/* Inner Screen Background */}
                        <rect
                            x="192"
                            y="134"
                            width="266"
                            height="160"
                            rx="10"
                            fill="#F8FAFC"
                        />

                        {/* Left Top Card: Sales Growth Line / Bar Widget */}
                        <g transform="translate(202, 144)">
                            <rect width="90" height="58" rx="8" fill="#FFFFFF" stroke="#E2E8F0" strokeWidth="1" />
                            {/* Bar Chart Columns */}
                            <rect x="10" y="32" width="6" height="18" rx="2" fill="#E2E8F0" />
                            <rect x="20" y="26" width="6" height="24" rx="2" fill="#E2E8F0" />
                            <rect x="30" y="20" width="6" height="30" rx="2" fill="#93C5FD" />
                            <rect x="40" y="14" width="6" height="36" rx="2" fill="#2563EB" />
                            {/* Upward Red Trend Arrow */}
                            <path
                                d="M12 28 L24 20 L35 22 L45 10"
                                fill="none"
                                stroke="#EF4444"
                                strokeWidth="2.5"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                            <polygon points="45,8 48,15 41,12" fill="#EF4444" />
                            {/* Growth label */}
                            <text x="56" y="24" fill="#0F172A" fontSize="9" fontWeight="bold" fontFamily="sans-serif">+28%</text>
                            <text x="56" y="35" fill="#64748B" fontSize="7" fontFamily="sans-serif">Deals</text>
                        </g>

                        {/* Left Bottom Card: User Profile / Lead Avatar */}
                        <g transform="translate(202, 210)">
                            <rect width="90" height="42" rx="8" fill="#FFFFFF" stroke="#E2E8F0" strokeWidth="1" />
                            {/* Avatar Circle */}
                            <circle cx="22" cy="21" r="12" fill="#E0E7FF" />
                            <circle cx="22" cy="18" r="5" fill="#4F46E5" />
                            <path d="M14 28 C14 24 18 23 22 23 C26 23 30 24 30 28" fill="#4F46E5" />
                            {/* Online Badge */}
                            <circle cx="29" cy="27" r="3" fill="#10B981" stroke="#FFFFFF" strokeWidth="1.5" />
                            {/* Placeholder Lines */}
                            <rect x="40" y="14" width="38" height="5" rx="2.5" fill="#94A3B8" />
                            <rect x="40" y="23" width="26" height="4" rx="2" fill="#CBD5E1" />
                        </g>

                        {/* Right Main Analytics Card (Dark Sleek Theme with Waves) */}
                        <g transform="translate(300, 144)">
                            <rect width="148" height="130" rx="10" fill="#0F172A" />

                            {/* Card Header & Indicator */}
                            <text x="12" y="20" fill="#F8FAFC" fontSize="10" fontWeight="bold" fontFamily="sans-serif">
                                Performance
                            </text>
                            <rect x="100" y="11" width="36" height="12" rx="6" fill="#1E293B" />
                            <circle cx="106" cy="17" r="2.5" fill="#22D3EE" />
                            <text x="113" y="20" fill="#94A3B8" fontSize="7" fontFamily="sans-serif">Live</text>

                            {/* Chart Grid Lines */}
                            <line x1="12" y1="40" x2="136" y2="40" stroke="#334155" strokeWidth="0.8" strokeDasharray="3 3" />
                            <line x1="12" y1="65" x2="136" y2="65" stroke="#334155" strokeWidth="0.8" strokeDasharray="3 3" />
                            <line x1="12" y1="90" x2="136" y2="90" stroke="#334155" strokeWidth="0.8" strokeDasharray="3 3" />
                            <line x1="12" y1="115" x2="136" y2="115" stroke="#334155" strokeWidth="0.8" />

                            {/* Pink Metric Wave Line */}
                            <path
                                d="M12 95 C30 85, 50 102, 70 80 C90 58, 110 82, 136 68"
                                fill="none"
                                stroke="url(#wavePink)"
                                strokeWidth="2.5"
                                strokeLinecap="round"
                            />

                            {/* Cyan Primary Wave Line */}
                            <path
                                d="M12 80 C32 60, 52 75, 75 52 C95 32, 115 65, 136 45"
                                fill="none"
                                stroke="url(#waveCyan)"
                                strokeWidth="2.5"
                                strokeLinecap="round"
                            />

                            {/* Key Glowing Data Points */}
                            <circle cx="75" cy="52" r="4" fill="#06B6D4" stroke="#FFFFFF" strokeWidth="1.5" />
                            <circle cx="136" cy="45" r="3" fill="#22D3EE" />
                        </g>

                        {/* 3D Circular Control Knob / Sphere Widget (Bottom Center) */}
                        <g transform="translate(290, 248)">
                            <ellipse cx="24" cy="28" rx="20" ry="7" fill="#0F172A" opacity="0.35" />
                            <circle cx="24" cy="20" r="18" fill="url(#sphereGrad)" stroke="#FFFFFF" strokeWidth="3" />
                            <circle cx="19" cy="15" r="4" fill="#FFFFFF" opacity="0.65" />
                        </g>
                    </g>

                    {/* ================= CHARACTER ILLUSTRATION ================= */}
                    <g transform="translate(110, 168)">
                        {/* Character Shadow */}
                        <ellipse cx="32" cy="188" rx="24" ry="6" fill="#0A2C7B" opacity="0.45" />

                        {/* Legs / Trousers (Dark Slate) */}
                        {/* Left Leg */}
                        <path d="M22 108 L21 176 L29 176 L31 108 Z" fill="#1E293B" />
                        {/* Right Leg */}
                        <path d="M33 108 L37 176 L45 176 L41 108 Z" fill="#0F172A" />

                        {/* Shoes */}
                        <path d="M17 174 C17 174 20 180 29 180 L29 174 Z" fill="#090D16" />
                        <path d="M36 174 C36 174 40 180 47 180 L47 174 Z" fill="#090D16" />

                        {/* Torso / Coral Red Shirt */}
                        <path
                            d="M17 48 L46 48 L48 108 L15 108 Z"
                            fill="#F43F5E"
                        />
                        {/* Shirt Collar / V-neck */}
                        <polygon points="28,48 35,48 31.5,56" fill="#E11D48" />

                        {/* Crossed Arms */}
                        {/* Left Upper Arm */}
                        <path d="M17 50 L11 82 L20 86 L24 54 Z" fill="#E11D48" />
                        {/* Right Upper Arm */}
                        <path d="M46 50 L51 82 L42 86 L38 54 Z" fill="#BE123C" />
                        {/* Folded Forearms */}
                        <rect x="14" y="74" width="34" height="14" rx="7" fill="#F43F5E" stroke="#BE123C" strokeWidth="1" />
                        {/* Hands */}
                        <circle cx="16" cy="81" r="5" fill="#FBCFE8" />
                        <circle cx="46" cy="81" r="5" fill="#FBCFE8" />

                        {/* Neck */}
                        <rect x="27" y="38" width="9" height="12" fill="#FBCFE8" />

                        {/* Head & Face */}
                        <ellipse cx="32" cy="28" rx="10" ry="12" fill="#FBCFE8" />
                        {/* Beard (Dark Trimmed) */}
                        <path d="M22 28 C22 38 42 38 42 28 L40 27 C40 35 24 35 24 27 Z" fill="#0F172A" />
                        {/* Ear */}
                        <circle cx="21" cy="27" r="2.5" fill="#FBCFE8" />
                        {/* Hair (Modern Pompadour / Side-part) */}
                        <path
                            d="M21 24 C21 14 43 12 43 22 C43 18 36 14 27 16 C22 17 21 21 21 24 Z"
                            fill="#0F172A"
                        />
                    </g>

                    {/* ================= POTTED PLANT ================= */}
                    <g transform="translate(68, 280)">
                        {/* Plant Shadow */}
                        <ellipse cx="14" cy="74" rx="12" ry="4" fill="#0A2C7B" opacity="0.4" />

                        {/* Planter Pot (Warm Mustard / Amber) */}
                        <polygon points="6,50 22,50 19,72 9,72" fill="#F59E0B" />
                        <rect x="5" y="47" width="18" height="4" rx="2" fill="#D97706" />

                        {/* Lush Leaves (Stylized succulent / plant stems) */}
                        <path d="M14 47 C14 36 7 28 8 20 C14 25 15 38 14 47 Z" fill="#38BDF8" />
                        <path d="M14 47 C14 32 21 24 20 16 C15 22 13 36 14 47 Z" fill="#7DD3FC" />
                        <path d="M14 47 C14 28 14 14 14 8 C11 20 11 36 14 47 Z" fill="#BAE6FD" />
                        <path d="M14 47 C12 36 4 36 2 28 C8 32 12 40 14 47 Z" fill="#0284C7" />
                        <path d="M14 47 C16 36 24 36 26 28 C20 32 16 40 14 47 Z" fill="#0369A1" />
                    </g>
                </svg>
            </div>
        </div>
    );
}
