import { useEffect, useState } from 'react';
import { Sun, Moon } from 'lucide-react';

/**
 * Polished Light/Dark mode switcher for Bamcom AI CRM dashboard.
 */
export default function ThemeToggle({ className = '' }) {
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        const storedTheme = localStorage.getItem('bamcom_theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const activeDark = storedTheme === 'dark' || (!storedTheme && systemPrefersDark);
        
        setIsDark(activeDark);
        if (activeDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }, []);

    const toggleTheme = () => {
        const nextDark = !isDark;
        setIsDark(nextDark);
        
        if (nextDark) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('bamcom_theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('bamcom_theme', 'light');
        }
    };

    return (
        <button
            type="button"
            onClick={toggleTheme}
            className={`inline-flex items-center justify-center p-2 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-600 ${className}`}
            title={isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'}
            aria-label="Toggle theme mode"
        >
            {isDark ? (
                <Sun className="h-5 w-5 text-amber-400 transition-transform duration-200 rotate-0 hover:rotate-45" />
            ) : (
                <Moon className="h-5 w-5 text-blue-700 transition-transform duration-200 rotate-0 hover:-rotate-12" />
            )}
        </button>
    );
}
