<?php
/**
 * GENERAT AUTOMAT de db/extract_icons.php — nu edita manual.
 * Iconitele placeholder ale cardurilor de produs, extrase din paginile statice.
 */

const PRODUCT_ICONS = [
    'icon-09304de8' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="10" width="64" height="40" rx="3"/> <rect x="14" y="18" width="28" height="25" rx="2"/> <rect x="46" y="18" width="20" height="25" rx="2"/> <circle cx="28" cy="30" r="3"/> </svg>',
    'icon-0c99fc29' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 28h48v6a14 14 0 0 1-14 14H22A14 14 0 0 1 8 34z"/><path d="M32 28V14a6 6 0 0 1 12 0"/></svg>',
    'icon-0f80adda' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M44 8a12 12 0 0 0-12 12c0 2 .4 4 1.2 5.6L12 56h0a4 4 0 0 0 5.6 0l21.2-21.2c1.6.8 3.6 1.2 5.6 1.2a12 12 0 0 0 0-24z"/></svg>',
    'icon-109455f0' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="16" width="52" height="20" rx="4"/><line x1="12" y1="26" x2="52" y2="26"/><path d="M18 44c0 4 4 4 4 8M32 44c0 4 4 4 4 8M46 44c0 4 4 4 4 8"/></svg>',
    'icon-17ad7095' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="12" y="6" width="40" height="52" rx="3"/><line x1="32" y1="6" x2="32" y2="58"/><circle cx="28" cy="32" r="1.6"/><circle cx="36" cy="32" r="1.6"/></svg>',
    'icon-191d7a27' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <path d="M0 20 Q10 10 20 20 Q30 10 40 20 Q50 10 60 20 Q70 10 80 20"/> <path d="M0 35 Q10 25 20 35 Q30 25 40 35 Q50 25 60 35 Q70 25 80 35"/> <path d="M0 50 Q10 40 20 50 Q30 40 40 50 Q50 40 60 50 Q70 40 80 50"/> </svg>',
    'icon-1a514afe' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="8" width="48" height="48" rx="4"/><line x1="8" y1="24" x2="56" y2="24"/><line x1="8" y1="40" x2="56" y2="40"/></svg>',
    'icon-1ce80e06' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="8" width="48" height="48" rx="4"/><rect x="16" y="16" width="16" height="20" rx="2"/><line x1="40" y1="20" x2="52" y2="20"/><line x1="40" y1="28" x2="52" y2="28"/><line x1="40" y1="36" x2="52" y2="36"/><circle cx="44" cy="44" r="6"/></svg>',
    'icon-1f13a361' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="25" y="5" width="30" height="50" rx="8"/><line x1="35" y1="20" x2="45" y2="20"/><circle cx="40" cy="35" r="6"/></svg>',
    'icon-1f7b7287' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <line x1="10" y1="20" x2="70" y2="20"/> <line x1="25" y1="20" x2="25" y2="35"/><circle cx="25" cy="37" r="2"/> <line x1="40" y1="20" x2="40" y2="35"/><circle cx="40" cy="37" r="2"/> <line x1="55" y1="20" x2="55" y2="35"/><circle cx="55" cy="37" r="2"/> </svg>',
    'icon-22242f2a' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="10" y="22" width="38" height="18" rx="3"/><path d="M48 28 L70 20 L70 40 L48 32"/><circle cx="18" cy="31" r="4"/><line x1="10" y1="34" x2="5" y2="44"/></svg>',
    'icon-2356c3a3' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <circle cx="40" cy="35" r="6"/> <line x1="40" y1="29" x2="40" y2="10"/> <path d="M30 18 Q40 10 50 18"/> <path d="M20 25 Q40 10 60 25"/> </svg>',
    'icon-264837f5' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <circle cx="40" cy="30" r="20"/> <circle cx="40" cy="30" r="12"/> <circle cx="40" cy="30" r="4"/> <line x1="20" y1="30" x2="5" y2="30"/> <line x1="60" y1="30" x2="75" y2="30"/> </svg>',
    'icon-27f9047b' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <path d="M15 20 Q15 12 25 12 L55 12 Q65 12 65 20 L65 38 Q65 46 55 46 L25 46 Q15 46 15 38 Z"/> <circle cx="40" cy="29" r="5"/> <line x1="40" y1="46" x2="40" y2="55"/> </svg>',
    'icon-28b9ed0d' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="12" y="10" width="40" height="44" rx="3"/><rect x="20" y="22" width="24" height="18" rx="2"/><path d="M32 26c-3 4 2 5 0 9"/><line x1="12" y1="18" x2="52" y2="18"/></svg>',
    'icon-293c7aab' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="8" width="64" height="44" rx="3"/> <path d="M8 20 L72 20"/> <path d="M8 40 L72 40"/> </svg>',
    'icon-296859c8' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="20" y="5" width="20" height="30" rx="2"/><line x1="10" y1="20" x2="20" y2="20"/><line x1="40" y1="20" x2="50" y2="20"/></svg>',
    'icon-29b4013e' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="8" width="64" height="44" rx="2" fill="none"/> <path d="M30 35 Q40 20 50 35" stroke-width="2"/> <circle cx="40" cy="38" r="3"/> </svg>',
    'icon-2aeeb836' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="8" y="8" width="64" height="44" rx="2"/><line x1="8" y1="22" x2="72" y2="22"/><line x1="8" y1="36" x2="72" y2="36"/><line x1="25" y1="8" x2="25" y2="52"/></svg>',
    'icon-2e9f8c6a' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 50 Q20 30 30 25 Q20 20 20 10"/><path d="M40 50 Q40 30 50 25 Q40 20 40 10"/><path d="M60 50 Q60 30 70 25 Q60 20 60 10"/><line x1="10" y1="50" x2="75" y2="50"/></svg>',
    'icon-2fa87064' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 18h44"/><path d="M14 18v10a4 4 0 0 0 4 4h28a4 4 0 0 0 4-4V18"/><path d="M20 32v14M44 32v14"/></svg>',
    'icon-333495b6' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="22" y="4" width="36" height="52" rx="9"/><line x1="34" y1="18" x2="46" y2="18"/><circle cx="40" cy="36" r="7"/></svg>',
    'icon-33421644' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="12" width="48" height="40" rx="4"/><line x1="20" y1="12" x2="20" y2="52"/><line x1="32" y1="12" x2="32" y2="52"/><line x1="44" y1="12" x2="44" y2="52"/></svg>',
    'icon-3481cb71' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="5" y="5" width="70" height="50" rx="2"/> <line x1="5" y1="20" x2="75" y2="20"/> <line x1="5" y1="35" x2="75" y2="35"/> <line x1="5" y1="50" x2="75" y2="50"/> <line x1="20" y1="5" x2="20" y2="55"/> <line x1="40" y1="5" x2="40" y2="55"/> <line x1="60" y1="5" x2="60" y2="55"/> </svg>',
    'icon-3612b68d' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <path d="M0 20 C10 10 20 30 30 20 C40 10 50 30 60 20 C70 10 80 30 80 20"/> <path d="M0 40 C10 30 20 50 30 40 C40 30 50 50 60 40 C70 30 80 50 80 40"/> <line x1="0" y1="20" x2="0" y2="40"/> <line x1="30" y1="20" x2="30" y2="40"/> <line x1="60" y1="20" x2="60" y2="40"/> </svg>',
    'icon-36ff84b3' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="5" y="42" width="70" height="10" rx="1"/> <path d="M5 42 Q5 38 12 38 L70 38"/> </svg>',
    'icon-377373cc' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 56L32 8l24 48Z"/><line x1="18" y1="36" x2="46" y2="36"/></svg>',
    'icon-3ba83274' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.2"> <path d="M5 30 Q20 10 35 30 Q50 50 65 30 Q72 18 75 30"/> <path d="M5 40 Q20 20 35 40 Q50 60 65 40 Q72 28 75 40"/> </svg>',
    'icon-3bc035a1' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M40 8 Q55 28 55 38 A15 15 0 0 1 25 38 Q25 28 40 8Z"/><line x1="30" y1="43" x2="50" y2="43"/></svg>',
    'icon-3c78cfd7' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <path d="M10 30 Q30 10 50 30 Q65 45 70 30"/> <path d="M10 34 Q30 14 50 34 Q65 49 70 34"/> </svg>',
    'icon-3dc8e306' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="40" width="30" height="12"/><rect x="40" y="40" width="35" height="12"/><rect x="18" y="28" width="32" height="12"/><rect x="55" y="28" width="20" height="12"/><rect x="5" y="28" width="10" height="12"/><rect x="8" y="16" width="28" height="12"/><rect x="40" y="16" width="32" height="12"/></svg>',
    'icon-3dffc7d5' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="5,50 40,10 75,50"/><line x1="15" y1="50" x2="15" y2="38"/><line x1="65" y1="50" x2="65" y2="38"/></svg>',
    'icon-3f12bf1f' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="2"> <line x1="5" y1="20" x2="75" y2="20"/> <line x1="5" y1="30" x2="75" y2="30"/> <line x1="5" y1="40" x2="75" y2="40"/> <circle cx="5" cy="20" r="3"/> <circle cx="5" cy="30" r="3"/> <circle cx="5" cy="40" r="3"/> </svg>',
    'icon-4480ace7' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 5 L10 30 Q10 35 15 35 L50 35"/></svg>',
    'icon-452e30cf' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <line x1="0" y1="15" x2="80" y2="15"/> <line x1="0" y1="30" x2="80" y2="30"/> <line x1="0" y1="45" x2="80" y2="45"/> <line x1="20" y1="0" x2="20" y2="60"/> <line x1="40" y1="0" x2="40" y2="60"/> <line x1="60" y1="0" x2="60" y2="60"/> </svg>',
    'icon-464f9790' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 44V24a4 4 0 0 1 4-4h44a4 4 0 0 1 4 4v20"/><rect x="6" y="32" width="52" height="12" rx="3"/><path d="M10 44v8M54 44v8"/></svg>',
    'icon-48628a0c' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="22" y="8" width="36" height="44" rx="3"/> <path d="M38 20 L34 32 H38 L36 44 L46 28 H42 Z"/> </svg>',
    'icon-48f797b2' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 5 L55 5 L55 30 L5 30 Z"/></svg>',
    'icon-490abafa' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><circle cx="32" cy="32" r="20"/><circle cx="32" cy="32" r="8"/></svg>',
    'icon-49a3de6a' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="10" width="64" height="40" rx="1"/> <rect x="18" y="18" width="10" height="24" rx="1"/> <rect x="35" y="18" width="10" height="24" rx="1"/> <rect x="52" y="18" width="10" height="24" rx="1"/> </svg>',
    'icon-49f67640' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.2"> <path d="M10 15 Q15 10 20 15 Q25 10 30 15 Q35 10 40 15 Q45 10 50 15 Q55 10 60 15 Q65 10 70 15"/> <path d="M10 25 Q15 20 20 25 Q25 20 30 25 Q35 20 40 25 Q45 20 50 25 Q55 20 60 25 Q65 20 70 25"/> <path d="M10 35 Q15 30 20 35 Q25 30 30 35 Q35 30 40 35 Q45 30 50 35 Q55 30 60 35 Q65 30 70 35"/> <path d="M10 45 Q15 40 20 45 Q25 40 30 45 Q35 40 40 45 Q45 40 50 45 Q55 40 60 45 Q65 40 70 45"/> </svg>',
    'icon-4ac72045' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="30" cy="20" r="15"/><line x1="30" y1="5" x2="30" y2="35"/><line x1="15" y1="20" x2="45" y2="20"/></svg>',
    'icon-4b39f02c' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="20" width="48" height="36" rx="4"/><polyline points="8,20 32,8 56,20"/><line x1="32" y1="8" x2="32" y2="56"/></svg>',
    'icon-4bcc0441' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="18" width="64" height="24" rx="1"/> <path d="M35 38 Q30 28 38 24 Q34 31 42 28 Q37 35 35 38Z" stroke-width="1.2"/> </svg>',
    'icon-4d8568f0' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="25" y="6" width="30" height="48" rx="15"/> <line x1="40" y1="6" x2="40" y2="4"/> <line x1="40" y1="54" x2="40" y2="58"/> <path d="M36 30 Q40 24 44 30 Q40 36 36 30Z"/> </svg>',
    'icon-502c77f4' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 5 L10 25 Q10 35 20 35 L50 35"/></svg>',
    'icon-50c2c5e2' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="14" y="16" width="36" height="34" rx="3"/><line x1="14" y1="33" x2="50" y2="33"/><circle cx="32" cy="25" r="1.8"/><circle cx="32" cy="42" r="1.8"/></svg>',
    'icon-53b4fa2d' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="10" y="28" width="60" height="20" rx="4"/><rect x="5" y="22" width="12" height="26" rx="3"/><rect x="63" y="22" width="12" height="26" rx="3"/></svg>',
    'icon-54536893' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="15" width="64" height="30" rx="2"/> <line x1="8" y1="25" x2="72" y2="25"/> <line x1="8" y1="35" x2="72" y2="35"/> </svg>',
    'icon-5bef8fac' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="20" y="3" width="40" height="54" rx="10"/><line x1="32" y1="16" x2="48" y2="16"/><circle cx="40" cy="37" r="8"/></svg>',
    'icon-5e4ae2c2' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="14" y="28" width="36" height="28" rx="4"/><path d="M20 28V22a12 12 0 0 1 24 0v6"/></svg>',
    'icon-5fa2094a' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><circle cx="32" cy="32" r="20"/><path d="M20 32h24M32 20v24"/></svg>',
    'icon-68b814f9' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <circle cx="35" cy="35" r="20"/> <circle cx="35" cy="35" r="4"/> <rect x="10" y="10" width="50" height="15" rx="3"/> <line x1="35" y1="15" x2="35" y2="15"/> </svg>',
    'icon-6ecdd6a5' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M30 5 Q5 5 5 30"/><path d="M30 5 Q55 5 55 30"/></svg>',
    'icon-79245c36' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="32" cy="20" rx="20" ry="8"/><line x1="12" y1="20" x2="12" y2="44"/><line x1="52" y1="20" x2="52" y2="44"/><ellipse cx="32" cy="44" rx="20" ry="8"/></svg>',
    'icon-7c867079' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M32 56C32 56 8 44 8 24 8 16 16 8 24 8c4 0 6 2 8 4 2-2 4-4 8-4 8 0 16 8 16 16 0 20-24 32-24 32z"/></svg>',
    'icon-7dc97a02' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <ellipse cx="40" cy="38" rx="22" ry="14"/> <path d="M18 38 Q18 20 40 20 Q62 20 62 38"/> <rect x="28" y="8" width="24" height="14" rx="2"/> </svg>',
    'icon-7de97c3f' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="5" y1="50" x2="75" y2="50"/><line x1="5" y1="28" x2="75" y2="28"/><line x1="15" y1="14" x2="15" y2="50"/><line x1="30" y1="14" x2="30" y2="50"/><line x1="45" y1="14" x2="45" y2="50"/><line x1="60" y1="14" x2="60" y2="50"/><polygon points="15,8 12,14 18,14"/><polygon points="30,8 27,14 33,14"/><polygon points="45,8 42,14 48,14"/><polygon points="60,8 57,14 63,14"/></svg>',
    'icon-7ea6be8c' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="10" y="10" width="44" height="44" rx="4"/><line x1="10" y1="22" x2="54" y2="22"/><circle cx="20" cy="16" r="2" fill="currentColor"/><circle cx="28" cy="16" r="2" fill="currentColor"/></svg>',
    'icon-814ed03c' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="14" width="48" height="36" rx="3"/><line x1="8" y1="26" x2="56" y2="26"/><line x1="8" y1="38" x2="56" y2="38"/></svg>',
    'icon-85c31879' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="5" y="5" width="70" height="50" rx="2"/> <rect x="8" y="8" width="64" height="44" rx="1"/> <line x1="8" y1="22" x2="72" y2="22"/> <line x1="8" y1="37" x2="72" y2="37"/> <line x1="22" y1="8" x2="22" y2="52"/> <line x1="40" y1="8" x2="40" y2="52"/> <line x1="58" y1="8" x2="58" y2="52"/> </svg>',
    'icon-89631121' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <path d="M8 28 Q28 8 50 28 Q65 43 72 28"/> <path d="M8 33 Q28 13 50 33 Q65 48 72 33"/> </svg>',
    'icon-89726a60' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="8" width="64" height="44" rx="2"/> <path d="M35 42 Q30 32 38 28 Q34 35 42 30 Q36 38 44 35 Q38 42 35 42Z"/> </svg>',
    'icon-9140427b' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="16" y="16" width="32" height="32" rx="4"/><circle cx="32" cy="32" r="8"/><line x1="32" y1="8" x2="32" y2="16"/><line x1="32" y1="48" x2="32" y2="56"/></svg>',
    'icon-91793d21' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="10" y="22" width="60" height="32" rx="3"/><polyline points="10,22 40,8 70,22"/><line x1="40" y1="8" x2="40" y2="54"/></svg>',
    'icon-92ca7676' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="10" y="10" width="60" height="40" rx="3"/> <line x1="10" y1="25" x2="70" y2="25"/> <line x1="10" y1="40" x2="70" y2="40"/> <line x1="25" y1="10" x2="25" y2="50"/> <line x1="55" y1="10" x2="55" y2="50"/> </svg>',
    'icon-95e1c6c8' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="10" width="48" height="18" rx="2"/><rect x="8" y="34" width="48" height="20" rx="2"/><line x1="32" y1="10" x2="32" y2="28"/><line x1="8" y1="40" x2="56" y2="40"/></svg>',
    'icon-96d05f58' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="12" y="8" width="40" height="48" rx="2"/><line x1="12" y1="24" x2="52" y2="24"/><line x1="12" y1="40" x2="52" y2="40"/></svg>',
    'icon-9846638b' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <path d="M5 30 Q20 10 35 30 Q50 50 65 30 Q72 20 75 30"/> <path d="M5 35 Q20 15 35 35 Q50 55 65 35 Q72 25 75 35"/> </svg>',
    'icon-9ace65ae' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="30" cy="20" r="10"/><line x1="30" y1="5" x2="30" y2="10"/><path d="M25 10 Q30 5 35 10"/></svg>',
    'icon-9cceb738' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <ellipse cx="40" cy="36" rx="22" ry="12"/> <path d="M18 36 Q18 22 40 22 Q62 22 62 36"/> <line x1="40" y1="48" x2="40" y2="56"/> </svg>',
    'icon-9de477e5' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="15" y="30" width="50" height="22" rx="4"/><line x1="15" y1="37" x2="65" y2="37"/><ellipse cx="40" cy="20" rx="15" ry="10"/></svg>',
    'icon-9eaa1fd0' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="10" width="50" height="20"/><line x1="5" y1="20" x2="55" y2="20"/></svg>',
    'icon-9f50d428' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="18" width="64" height="24" rx="1"/> </svg>',
    'icon-a04e97ad' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="30" y1="5" x2="30" y2="35"/><line x1="15" y1="20" x2="45" y2="20"/><circle cx="30" cy="20" r="6"/></svg>',
    'icon-a0b97c4c' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="30" y1="5" x2="30" y2="35"/><path d="M25 10 L30 5 L35 10"/><circle cx="30" cy="30" r="5"/></svg>',
    'icon-a14d8cec' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="10" y="20" width="40" height="22" rx="3"/> <path d="M50 28 L70 22 L70 38 L50 32"/> <circle cx="18" cy="31" r="5"/> <line x1="10" y1="35" x2="5" y2="42"/> </svg>',
    'icon-a20d1e07' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="25" y="5" width="10" height="30" rx="2"/><line x1="10" y1="15" x2="25" y2="15"/><line x" x1="10" y1="25" x2="25" y2="25"/></svg>',
    'icon-a47cf7df' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 35 L10 15 Q10 5 20 5 L30 5"/><path d="M10 25 L30 25"/></svg>',
    'icon-a49550cd' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="10" y="28" width="60" height="20" rx="4"/><rect x="5" y="22" width="12" height="26" rx="3"/><rect x="63" y="22" width="12" height="26" rx="3"/><line x1="18" y1="48" x2="18" y2="55"/><line x1="62" y1="48" x2="62" y2="55"/></svg>',
    'icon-a759b602' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="12" y="8" width="40" height="48" rx="4"/><rect x="20" y="16" width="24" height="16" rx="2"/><circle cx="24" cy="44" r="4"/><circle cx="40" cy="44" r="4"/></svg>',
    'icon-a819e0bd' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="10" y="8" width="40" height="24" rx="3"/><line x1="10" y1="20" x2="50" y2="20"/><circle cx="30" cy="14" r="3"/><circle cx="30" cy="26" r="3"/></svg>',
    'icon-aa442b16' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.2"> <rect x="5" y="8" width="70" height="44" rx="1"/> <rect x="12" y="14" width="6" height="32" rx="1"/> <rect x="22" y="14" width="6" height="32" rx="1"/> <rect x="32" y="14" width="6" height="32" rx="1"/> <rect x="42" y="14" width="6" height="32" rx="1"/> <rect x="52" y="14" width="6" height="32" rx="1"/> <rect x="62" y="14" width="6" height="32" rx="1"/> </svg>',
    'icon-af183702' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="12" y="18" width="38" height="20" rx="3"/> <path d="M50 25 L68 20 L68 36 L50 31"/> <rect x="20" y="38" width="22" height="10" rx="2"/> <circle cx="17" cy="28" r="4"/> </svg>',
    'icon-b1c9ac6c' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="5" y="10" width="70" height="40" rx="2"/> <line x1="5" y1="25" x2="75" y2="25"/> <line x1="5" y1="40" x2="75" y2="40"/> </svg>',
    'icon-b2e165b5' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="10" y="22" width="38" height="18" rx="3"/><path d="M48 28 L70 20 L70 40 L48 32"/><circle cx="18" cy="31" r="4"/></svg>',
    'icon-b36f4328' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="24" width="48" height="24" rx="3"/><line x1="16" y1="48" x2="16" y2="56"/><line x1="48" y1="48" x2="48" y2="56"/><rect x="8" y="16" width="48" height="8" rx="2"/></svg>',
    'icon-b4c11455' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 20.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l19.77-19.77a12 12 0 0 1-15.94 15.94l-13.81 13.81a4.24 4.24 0 0 1-6-6l13.81-13.81a12 12 0 0 1 15.94-15.94l-17.53 17.53z" transform="scale(0.7) translate(14,14)"/></svg>',
    'icon-bcd49a10' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="15" y="10" width="50" height="40" rx="3"/><line x1="15" y1="22" x2="65" y2="22"/><circle cx="25" cy="16" r="2" fill="currentColor"/><circle cx="33" cy="16" r="2" fill="currentColor"/></svg>',
    'icon-bd27f226' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="10" y="5" width="40" height="30" rx="2"/><line x1="10" y1="15" x2="50" y2="15"/><line x1="10" y1="25" x2="50" y2="25"/></svg>',
    'icon-c06152f8' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="16" y="8" width="32" height="40" rx="16"/><path d="M24 54h16"/></svg>',
    'icon-c0db4fe6' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><circle cx="32" cy="24" r="12"/><line x1="32" y1="36" x2="32" y2="52"/><line x1="22" y1="48" x2="42" y2="48"/></svg>',
    'icon-c1d5c4c1' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="15" y="5" width="30" height="30" rx="4"/><circle cx="30" cy="20" r="8"/></svg>',
    'icon-c1dd1172' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="8" width="64" height="44" rx="3"/> <rect x="16" y="16" width="48" height="28" rx="2"/> </svg>',
    'icon-c276d649' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 20 Q15 5 30 20 Q45 35 55 20"/><line x1="5" y1="30" x2="55" y2="30"/></svg>',
    'icon-c387197d' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="32" width="56" height="24" rx="2"/><rect x="4" y="20" width="56" height="12"/><rect x="4" y="8" width="56" height="12"/></svg>',
    'icon-c68a89f9' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <circle cx="40" cy="30" r="22"/> <circle cx="40" cy="30" r="14"/> <circle cx="40" cy="30" r="6"/> <line x1="18" y1="30" x2="5" y2="30"/> <line x1="62" y1="30" x2="75" y2="30"/> </svg>',
    'icon-c8178642' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M32 8 Q48 28 48 40 A16 16 0 0 1 16 40 Q16 28 32 8Z"/></svg>',
    'icon-cbe8e680' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 32 Q20 20 32 32 Q44 44 56 32"/><path d="M8 44 Q20 32 32 44 Q44 56 56 44"/></svg>',
    'icon-ccb0fd26' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="28" y="8" width="24" height="44" rx="7"/><line x1="35" y1="22" x2="45" y2="22"/><circle cx="40" cy="36" r="5"/></svg>',
    'icon-d084a0e3' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 5 Q30 35 55 5"/><line x1="5" y1="5" x2="55" y2="5"/></svg>',
    'icon-d93d6bc1' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="2"> <rect x="5" y="5" width="70" height="50" rx="2"/> <line x1="5" y1="20" x2="75" y2="20"/> <line x1="5" y1="35" x2="75" y2="35"/> <line x1="5" y1="50" x2="75" y2="50"/> <line x1="20" y1="5" x2="20" y2="55"/> <line x1="40" y1="5" x2="40" y2="55"/> <line x1="60" y1="5" x2="60" y2="55"/> </svg>',
    'icon-d9fe8533' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><circle cx="32" cy="28" r="16"/><path d="M28 44h8M32 44v8"/><path d="M26 22l6 8 6-8"/></svg>',
    'icon-da1c8429' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="8" y="8" width="64" height="44" rx="2"/> <line x1="8" y1="20" x2="72" y2="20"/> <line x1="8" y1="40" x2="72" y2="40"/> <line x1="25" y1="8" x2="25" y2="52"/> <line x1="55" y1="8" x2="55" y2="52"/> </svg>',
    'icon-da56e276' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="20" width="48" height="8" rx="2"/><path d="M14 28v20M50 28v20M14 40h36"/></svg>',
    'icon-dac1b47b' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="8" y="8" width="64" height="44" rx="3"/><line x1="8" y1="22" x2="72" y2="22"/><line x1="8" y1="36" x2="72" y2="36"/></svg>',
    'icon-daf46eb4' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="15" y="12" width="36" height="40" rx="2"/> <rect x="20" y="18" width="26" height="18" rx="1"/> <path d="M35 8 Q40 2 45 8"/> <line x1="33" y1="52" x2="33" y2="58"/> <line x1="47" y1="52" x2="47" y2="58"/> </svg>',
    'icon-db47f4a1' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 55 Q20 30 50 15 Q70 8 70 8 Q70 28 58 42 Q40 58 10 55Z"/><line x1="10" y1="55" x2="50" y2="25"/></svg>',
    'icon-ddddd222' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 22h20a8 8 0 0 1 8 8v4a8 8 0 0 0 8 8h8"/><rect x="6" y="16" width="8" height="12" rx="2"/><rect x="50" y="36" width="8" height="12" rx="2"/></svg>',
    'icon-df3dd280' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="40" width="30" height="12"/><rect x="40" y="40" width="35" height="12"/><rect x="18" y="28" width="32" height="12"/><rect x="5" y="28" width="10" height="12"/><rect x="8" y="16" width="28" height="12"/><rect x="40" y="16" width="32" height="12"/></svg>',
    'icon-e1567faa' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="10" y="10" width="60" height="40" rx="2"/> <line x1="10" y1="25" x2="70" y2="25"/> <line x1="10" y1="40" x2="70" y2="40"/> </svg>',
    'icon-e15f1c77' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="20" y="8" width="40" height="44" rx="3"/> <rect x="26" y="14" width="28" height="16" rx="1"/> <circle cx="32" cy="38" r="4"/> <circle cx="48" cy="38" r="4"/> <line x1="40" y1="52" x2="40" y2="58"/> </svg>',
    'icon-e294f620' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><ellipse cx="30" cy="10" rx="15" ry="6"/><line x1="15" y1="10" x2="15" y2="35"/><line x1="45" y1="10" x2="45" y2="35"/><ellipse cx="30" cy="35" rx="15" ry="6"/></svg>',
    'icon-e46d68fb' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="15" y="22" width="45" height="18" rx="3"/> <circle cx="20" cy="46" r="10"/> <line x1="60" y1="22" x2="65" y2="15"/> </svg>',
    'icon-e4c550a8' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 8h24v26H20z"/><path d="M16 34h32v6H16z"/><path d="M20 40v16M44 40v16"/></svg>',
    'icon-e53371d2' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><circle cx="32" cy="26" r="14"/><path d="M26 44h12M28 52h8"/><path d="M32 4v6M12 26H6M58 26h-6M17 11l-4-4M47 11l4-4"/></svg>',
    'icon-eabfb5eb' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="28" width="48" height="20" rx="4"/><rect x="4" y="24" width="10" height="24" rx="3"/><rect x="50" y="24" width="10" height="24" rx="3"/><line x1="16" y1="48" x2="16" y2="56"/><line x1="48" y1="48" x2="48" y2="56"/></svg>',
    'icon-ef3680cc' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="10" y="12" width="44" height="40" rx="3"/><line x1="32" y1="12" x2="32" y2="52"/><circle cx="27" cy="32" r="1.6"/><circle cx="37" cy="32" r="1.6"/></svg>',
    'icon-f063563a' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="15" y="30" width="50" height="22" rx="4"/><line x1="15" y1="37" x2="65" y2="37"/><ellipse cx="40" cy="20" rx="15" ry="10"/><line x1="40" y1="10" x2="40" y2="6"/></svg>',
    'icon-f2a8f4cf' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="30" cy="20" r="12"/><path d="M30 8v-4M30 36v-4M18 20h-4M46 20h-4"/></svg>',
    'icon-f4603217' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="28" width="28" height="16" rx="3"/><line x1="36" y1="36" x2="56" y2="20"/><line x1="56" y1="20" x2="52" y2="16"/></svg>',
    'icon-f568ac53' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="5" y="12" width="70" height="36" rx="2"/> <circle cx="20" cy="30" r="4"/> <circle cx="40" cy="30" r="4"/> <circle cx="60" cy="30" r="4"/> </svg>',
    'icon-f628a637' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 56 Q16 32 40 16 Q56 8 56 8 Q56 24 48 40 Q32 56 8 56Z"/><line x1="8" y1="56" x2="40" y2="24"/></svg>',
    'icon-fac702b1' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="10" y="10" width="60" height="40" rx="2"/> <path d="M25 30 Q30 20 35 30 Q40 40 45 30 Q50 20 55 30"/> </svg>',
    'icon-fcd87f40' => '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5"> <rect x="5" y="15" width="70" height="30" rx="2"/> <line x1="5" y1="30" x2="75" y2="30"/> <line x1="25" y1="15" x2="25" y2="45"/> <line x1="55" y1="15" x2="55" y2="45"/> </svg>',
    'icon-fdebee59' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="28" width="48" height="28" rx="4"/><path d="M20 28V20a12 12 0 0 1 24 0v8"/></svg>',
    'icon-fe48992b' => '<svg viewBox="0 0 60 40" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 35 L5 15 L55 5 L55 35"/><line x1="5" y1="35" x2="55" y2="35"/></svg>',
];

/** Iconita generica pentru produsele fara icon_key (produse noi din admin). */
const PRODUCT_ICON_FALLBACK =
    '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5">'
    . '<rect x="15" y="12" width="50" height="36" rx="3"/><line x1="15" y1="24" x2="65" y2="24"/></svg>';

/**
 * Randeaza placeholder-ul unui card. Cheile necunoscute cad pe iconita generica,
 * ca un produs adaugat din admin sa nu randeze o gaura in grila.
 * $hidden = true cand cardul are si o fotografie reala (placeholder-ul ramane
 * in DOM ca fallback, dar ascuns).
 *
 * $label nu se mai randeaza (era un <span> cu majuscule care repeta titlul de sub
 * card — vezi raportul design/perf). Parametrul ramane in semnatura ca apelurile
 * existente sa nu se rupa; icon_label ramane in DB neatins, doar nu se mai afiseaza.
 */
function renderIcon(?string $key, ?string $label, bool $hidden = false): string {
    $svg = ($key !== null && isset(PRODUCT_ICONS[$key])) ? PRODUCT_ICONS[$key] : PRODUCT_ICON_FALLBACK;
    $style = $hidden ? 'display:none' : 'display:flex';
    return '<div class="material-img-ph" style="' . $style . '">' . $svg . '</div>';
}

/**
 * Placeholder-ul unui accessory-card. Eticheta nu se mai randeaza (acelasi motiv
 * ca la renderIcon) — parametrul $label ramane in semnatura din compatibilitate.
 */
function renderAccessoryIcon(?string $key, ?string $label = null, bool $hidden = false): string {
    $svg = ($key !== null && isset(PRODUCT_ICONS[$key])) ? PRODUCT_ICONS[$key] : PRODUCT_ICON_FALLBACK;
    return '<div class="accessory-img-ph" style="' . ($hidden ? 'display:none' : 'display:flex') . '">' . $svg . '</div>';
}

/**
 * Placeholder-ul cardului de catalog. Aceeasi biblioteca de iconite, alt container.
 * $hidden = true cand cardul are si o fotografie reala (placeholder-ul ramane
 * in DOM ca fallback, dar ascuns). Eticheta nu se mai randeaza (acelasi motiv
 * ca la renderIcon).
 */
function renderProdIcon(?string $key, ?string $label, bool $hidden = false): string {
    $svg = ($key !== null && isset(PRODUCT_ICONS[$key])) ? PRODUCT_ICONS[$key] : PRODUCT_ICON_FALLBACK;
    $style = $hidden ? 'display:none' : 'display:flex';
    return '<div class="prod-img-ph" style="' . $style . '">' . $svg . '</div>';
}
