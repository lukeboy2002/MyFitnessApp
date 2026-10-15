<?php

// Gedeelde Tailwind-classes voor de formuliercomponenten (input, password, textarea, select).
// text-base op mobiel (16px) voorkomt dat iOS inzoomt bij focus.
return [
    'control' => [
        'base' => 'block w-full min-w-0 rounded-xl border-0 bg-white px-3.5 py-2.5 text-base text-ink shadow-xs ring-1 ring-inset ring-slate-300 '
            .'placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-600 '
            .'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 '
            .'dark:bg-white/5 dark:text-white dark:ring-line-dark dark:placeholder:text-slate-500 dark:focus:ring-brand-400 '
            .'dark:disabled:bg-white/[.02] sm:text-[15px]',
        'invalid' => 'ring-red-500 focus:ring-red-500 dark:ring-red-400',
    ],
];
