<?php 
require_once 'includes/connect.php';
$clinic = $pdo->query("SELECT * FROM clinic_info WHERE id = 1")->fetch();
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= htmlspecialchars($clinic['name'] ?? 'Professional Dental Care') ?> | Dental Serenity</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;family=Inter:wght@300;400;500;600;700&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
                    "on-background": "#191c1d",
                    "secondary": "#006a62",
                    "on-secondary-container": "#006f66",
                    "on-secondary-fixed-variant": "#005049",
                    "secondary-container": "#81f3e5",
                    "surface-dim": "#d8dadb",
                    "primary-container": "#006097",
                    "surface-container-highest": "#e1e3e4",
                    "on-surface-variant": "#41474f",
                    "inverse-surface": "#2e3132",
                    "on-tertiary-fixed": "#051e28",
                    "primary-fixed-dim": "#97cbff",
                    "primary-fixed": "#cee5ff",
                    "error-container": "#ffdad6",
                    "surface": "#f8fafb",
                    "tertiary": "#304752",
                    "secondary-fixed-dim": "#66d9cc",
                    "primary": "#004872",
                    "surface-container-low": "#f2f4f5",
                    "surface-container-high": "#e6e8e9",
                    "on-primary": "#ffffff",
                    "on-primary-container": "#b3d8ff",
                    "on-secondary": "#ffffff",
                    "on-primary-fixed-variant": "#004a76",
                    "inverse-primary": "#97cbff",
                    "on-tertiary-container": "#bfd8e5",
                    "secondary-fixed": "#84f5e8",
                    "outline": "#717880",
                    "on-secondary-fixed": "#00201d",
                    "surface-variant": "#e1e3e4",
                    "on-tertiary-fixed-variant": "#334a55",
                    "tertiary-fixed-dim": "#b1cad7",
                    "outline-variant": "#c0c7d1",
                    "on-error-container": "#93000a",
                    "surface-container-lowest": "#ffffff",
                    "tertiary-container": "#485f6a",
                    "on-primary-fixed": "#001d33",
                    "background": "#f8fafb",
                    "surface-bright": "#f8fafb",
                    "on-tertiary": "#ffffff",
                    "surface-container": "#eceeef",
                    "on-surface": "#191c1d",
                    "tertiary-fixed": "#cde6f4",
                    "inverse-on-surface": "#eff1f2",
                    "surface-tint": "#08639a",
                    "on-error": "#ffffff",
                    "error": "#ba1a1a"
            },
            "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
            },
            "fontFamily": {
                    "headline": ["Manrope"],
                    "body": ["Inter"],
                    "label": ["Inter"]
            }
          },
        },
      }
    </script>
<style>
    body { 
        font-family: 'Inter', sans-serif; 
    }
    h1, h2, h3, h4 { 
        font-family: 'Manrope', sans-serif; 
    }
    .material-symbols-outlined {
        font-variation-settings: 
            'FILL' 0, 
            'wght' 400, 
            'GRAD' 0, 
            'opsz' 24;
    }
</style>
</head>
<body class="bg-surface text-on-surface selection:bg-secondary-container selection:text-on-secondary-container">
<header class="fixed top-0 w-full z-50 bg-[#f8fafb]/80 backdrop-blur-md shadow-[0_8px_24px_rgba(25,28,29,0.06)] font-['Manrope'] tracking-tight">
<nav class="flex justify-between items-center w-full px-8 py-4 max-w-7xl mx-auto">
<div class="text-2xl font-bold text-sky-900"><?= htmlspecialchars($clinic['name'] ?? 'Your Clinic') ?></div>
<div class="hidden md:flex items-center space-x-8">
<a class="text-sky-900 font-bold border-b-2 border-sky-900 pb-1 hover:text-sky-700 transition-colors duration-300" href="#">Home</a>
<a class="text-slate-600 font-medium hover:text-sky-700 transition-colors duration-300" href="#about">About</a>
<a class="text-slate-600 font-medium hover:text-sky-700 transition-colors duration-300" href="#team">Team</a>
<a class="text-slate-600 font-medium hover:text-sky-700 transition-colors duration-300" href="#schedule">Schedule</a>
<a class="text-slate-600 font-medium hover:text-sky-700 transition-colors duration-300" href="#location">Location</a>
</div>
<button class="bg-gradient-to-r from-primary to-primary-container text-on-primary px-6 py-2.5 rounded-full font-semibold shadow-md active:scale-95 transition-all duration-200">
                Book Appointment
            </button>
</nav>
</header>
<main class="pt-24">
<!-- Hero Section -->
<section class="relative overflow-hidden min-h-[870px] flex items-center px-8 max-w-7xl mx-auto">
<div class="absolute top-0 right-0 -z-10 w-2/3 h-full opacity-10 pointer-events-none">
<div class="w-full h-full bg-secondary-container rounded-full blur-3xl scale-150 transform translate-x-1/2 -translate-y-1/4"></div>
</div>
<div class="grid md:grid-cols-2 gap-12 items-center">
<div class="space-y-8">
<div class="inline-flex items-center space-x-2 bg-secondary-fixed text-on-secondary-fixed px-4 py-1.5 rounded-full">
<span class="material-symbols-outlined text-sm">spa</span>
<span class="text-xs font-bold uppercase tracking-widest">Clinical Serenity</span>
</div>
<h1 class="text-5xl md:text-7xl font-extrabold text-on-surface leading-[1.1] tracking-tight">
                        Exhale the <span class="text-primary">Ordinary.</span>
</h1>
<p class="text-xl text-on-surface-variant max-w-lg leading-relaxed font-light">
                        Experience a new standard of dental care where precision engineering meets a curated, calming environment. Your comfort is our primary clinical protocol.
                    </p>
<div class="flex flex-wrap gap-4">
<button class="bg-primary text-on-primary px-8 py-4 rounded-full font-bold shadow-lg hover:bg-on-primary-fixed-variant active:scale-95 transition-all">
                            Book Appointment
                        </button>
<button class="border border-outline-variant/40 text-primary px-8 py-4 rounded-full font-bold hover:bg-surface-container-low transition-all">
                            Explore Services
                        </button>
</div>
</div>
<div class="relative">
<div class="aspect-[4/5] rounded-[2rem] overflow-hidden shadow-2xl relative z-10">
<img class="w-full h-full object-cover" data-alt="Modern high-end dental clinic room with soft natural lighting, a comfortable reclining chair, and minimalist organic decor elements" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC1xIbTG7yvJ_dnNR_6565TiT6x37q1WBDSpLC-6orwCNFBV8PNvU1LG8MBljTwI6ykaAo1sk0apu72Fwnx8Kd34sY0QjrnWbLd4u4wsri9CrmkfTq5WemVWkOzq5-yO0T4FYAC-jJ0qCiXBY-qIXe8WtFskhQrPOF-E24-m9ydQZ6L1BK7Xz0QLixe9njuH_EwsSX_WFl4tYmNI4Xi68Np-4ROrt-ulUYA0yI7T1gejLd0VIZ4giBQsRVRvFb1tZNqF5ptfCDKNuo"/>
</div>
<div class="absolute -bottom-6 -left-6 bg-surface-container-lowest p-6 rounded-xl shadow-xl z-20 flex items-center gap-4 max-w-xs">
<div class="w-12 h-12 bg-secondary-container rounded-full flex items-center justify-center text-on-secondary-container">
<span class="material-symbols-outlined">verified_user</span>
</div>
<div>
<p class="font-bold text-primary">Certified Quality</p>
<p class="text-xs text-on-surface-variant">Top-rated patient experience 2024</p>
</div>
</div>
</div>
</div>
</section>
<!-- Philosophy / About Section -->
<section class="py-24 bg-surface-container-low" id="about">
<div class="max-w-7xl mx-auto px-8">
<div class="grid md:grid-cols-12 gap-12 items-center">
<div class="md:col-span-5">
<h2 class="text-4xl font-extrabold text-on-surface mb-6 leading-tight">Professionalism<br/>Refined through Art.</h2>
<p class="text-on-surface-variant leading-relaxed text-lg mb-8">
                            At The Curated Breath, we believe that world-class dentistry should never feel clinical. We’ve removed the harsh lights and sterile sounds, replacing them with a bespoke experience designed to reduce cortisol levels while achieving optimal oral health.
                        </p>
<div class="space-y-4">
<div class="flex items-center gap-4">
<span class="material-symbols-outlined text-secondary">check_circle</span>
<span class="font-medium">Curated Acoustic Environments</span>
</div>
<div class="flex items-center gap-4">
<span class="material-symbols-outlined text-secondary">check_circle</span>
<span class="font-medium">Bio-compatible Premium Materials</span>
</div>
<div class="flex items-center gap-4">
<span class="material-symbols-outlined text-secondary">check_circle</span>
<span class="font-medium">Post-treatment Serenity Lounges</span>
</div>
</div>
</div>
<div class="md:col-span-7 grid grid-cols-2 gap-4">
<div class="space-y-4 pt-12">
<div class="rounded-2xl overflow-hidden shadow-lg h-64">
<img class="w-full h-full object-cover" data-alt="Abstract macro shot of clean water droplets on a mint leaf with soft focus white background" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAFClTvpcJUUHoP5IhpaOwWPPgz8GG6P7H5xT7efg3XWtfz-01tG_XTvOTItatWorrgb4N4vOlyH9_abFeVbVyQJGmNW8keiVgjd5cguQJCy0fU3FW09mBwcP21Y6w7VyCnogTKiwY544oFdoeIhmszgf3kgTdiX9CQQfXdbVpq1oT2b5F2TXunM1WHN0FRUL_O6ogUn2vj5IwOYtpxCyGNTDYjAUiRkt45GLttu1WNn0z2WHGhjzyFT1ZozeNWmMBLy-L4nPuF-1g"/>
</div>
<div class="bg-primary p-8 rounded-2xl text-on-primary">
<p class="text-4xl font-black mb-2">98%</p>
<p class="text-sm font-medium uppercase tracking-widest opacity-80">Patient Comfort Index</p>
</div>
</div>
<div class="rounded-2xl overflow-hidden shadow-lg h-[450px]">
<img class="w-full h-full object-cover" data-alt="Interior of a luxury spa-like clinic waiting area with soft linen chairs and tall indoor plants" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDfL5XxGL2fbsN5rWest-yN7ja8_3q1ZbAiT_yuzB2Fgx5ys1N5W9tBmfwFCQkQgHn0cqNxRsnDX-_YPKxO7-X0HSr8Zeodhe9Zg5LM6KuHoBvrxhQMDkb8QovcTugn_OUH1ZqiFfJJQX-PBr6dihZPL6v7Fe1BldTgtYfpdZ3TWsXCvvMjRyqJ3NmzQM1vyhjj3Tb6gFhPhondxzUJqMifmdm-1PgDRq-wq5JS6FjLUZH24CsmKabNUrpikLejFVuUogJWKoJvc10"/>
</div>
</div>
</div>
</div>
</section>
<!-- Team Section -->
<section class="py-24" id="team">
<div class="max-w-7xl mx-auto px-8">
<div class="text-center max-w-2xl mx-auto mb-16">
<h2 class="text-4xl font-extrabold text-on-surface mb-4">The Architects of Your Smile</h2>
<p class="text-on-surface-variant font-body">Meet our world-renowned specialists dedicated to the intersection of oral health and aesthetic perfection.</p>
</div>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
<!-- Dentist 1 -->
<div class="bg-surface-container-lowest rounded-xl overflow-hidden group hover:shadow-xl transition-all duration-300">
<div class="aspect-square overflow-hidden">
<img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" data-alt="Professional portrait of a male dentist in a modern clinic, smiling warmly with soft focus medical background" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAL6y-PIHQgnWOeLZ5oQKYGgDndV6mBtY3McI8O3gNlbATmsN2z2HgaxB-ro25d2kQLdLGYhOU-mXkv8H1ymAXABErj7Ln5TcPKwyl4vUKofbL7zTdrh1qMT7qHTaUX5Vdp6_XodPSrNA6ITzHaqKly1bE44rPUXUcGVU4Uk4l3stZltFc-XUdPrAY-kWiZ1WkUnwinm2qQLIeboU5FEBIbIngq4zL38-_-JJFPxiIKlpFt4ZfZ-mmfZEXnMz2iprXW9ITFVCdm6ec"/>
</div>
<div class="p-8">
<span class="text-secondary text-xs font-bold uppercase tracking-widest block mb-2">Chief Prosthodontist</span>
<h3 class="text-xl font-bold text-on-surface mb-2">Dr. Julian Vance</h3>
<p class="text-on-surface-variant text-sm leading-relaxed mb-6">Expert in reconstructive aesthetic dentistry with 15 years of clinical excellence.</p>
<div class="flex gap-2">
<span class="px-3 py-1 bg-secondary-fixed text-on-secondary-container rounded-full text-xs font-semibold">Implants</span>
<span class="px-3 py-1 bg-secondary-fixed text-on-secondary-container rounded-full text-xs font-semibold">Veneers</span>
</div>
</div>
</div>
<!-- Dentist 2 -->
<div class="bg-surface-container-lowest rounded-xl overflow-hidden group hover:shadow-xl transition-all duration-300">
<div class="aspect-square overflow-hidden">
<img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" data-alt="Professional female dentist with stethoscope, wearing clean white scrubs in a bright airy studio lighting" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBg81LjkOL5PJs-3Td8bb1ZgBCLAPvxEAdqtUpn67cGf3NLmthtQeSDEQuTK34bONhUB0kdmmBZiPLjbDi8yGyEO-Tsyz1-dRm2R1xJS0pR09srYU5-qxYt_zlgOvCPVXV0-dMwj3nskjmm23Untq7MI233FD1FLJLQJALp8M7fhspLqa7YGnut6EmSYskyCLavjOQgdDoNiZLugEawq3GJp28iLds9YBHjDhgoOHV_W535CyhByi1fyrDdLI5jJnmze3TR7u0WgXE"/>
</div>
<div class="p-8">
<span class="text-secondary text-xs font-bold uppercase tracking-widest block mb-2">Lead Orthodontist</span>
<h3 class="text-xl font-bold text-on-surface mb-2">Dr. Elena Rostova</h3>
<p class="text-on-surface-variant text-sm leading-relaxed mb-6">Specializing in invisible alignment systems and holistic jaw alignment therapies.</p>
<div class="flex gap-2">
<span class="px-3 py-1 bg-secondary-fixed text-on-secondary-container rounded-full text-xs font-semibold">Invisalign</span>
<span class="px-3 py-1 bg-secondary-fixed text-on-secondary-container rounded-full text-xs font-semibold">Alignment</span>
</div>
</div>
</div>
<!-- Dentist 3 -->
<div class="bg-surface-container-lowest rounded-xl overflow-hidden group hover:shadow-xl transition-all duration-300">
<div class="aspect-square overflow-hidden">
<img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" data-alt="Smiling dental specialist in clinical setting, wearing glasses and high-quality professional uniform" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA0eWtPM78tu6eWdZGq4m28rrX6alFZJgIexcRarCMngAQLEPexwrUY-IK_jv6PMQBsCy94KBDN3RYbOxrHW0lIj5adOrqhlGLXUnpygo86x9VOIDFqDuZxHbybXTjCQsZ9eQ02aqP-_-_dXUG2LJm3DqdZcGaskOIiAvJR7Mhm1EoSYxVVpMraYoYNL0rsIfnd4pRPoVLM6PfJZEQCSkAybceC2G9uJVZZbyQd9_VK5JaB3J28og-YJnJOFEZjxmGw7fmItxQG-eQ"/>
</div>
<div class="p-8">
<span class="text-secondary text-xs font-bold uppercase tracking-widest block mb-2">Oral Hygienist</span>
<h3 class="text-xl font-bold text-on-surface mb-2">Marcus Chen</h3>
<p class="text-on-surface-variant text-sm leading-relaxed mb-6">Pioneer of pain-free ultrasonic cleaning protocols and preventive care wellness.</p>
<div class="flex gap-2">
<span class="px-3 py-1 bg-secondary-fixed text-on-secondary-container rounded-full text-xs font-semibold">Cleaning</span>
<span class="px-3 py-1 bg-secondary-fixed text-on-secondary-container rounded-full text-xs font-semibold">Prevention</span>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Bento Grid: Schedule & Announcements -->
<section class="py-24 bg-surface-container-low" id="schedule">
<div class="max-w-7xl mx-auto px-8">
<div class="grid lg:grid-cols-3 gap-8">
<!-- Weekly Schedule -->
<div class="lg:col-span-2 bg-surface-container-lowest p-10 rounded-3xl shadow-sm border border-outline-variant/10">
<div class="flex items-center justify-between mb-8">
<h2 class="text-3xl font-bold text-on-surface">Weekly Schedule</h2>
<span class="material-symbols-outlined text-primary text-3xl">calendar_month</span>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
<div class="flex justify-between items-center py-3 border-b border-outline-variant/20">
<span class="font-semibold">Monday</span>
<span class="text-secondary font-medium">08:00 — 19:00</span>
</div>
<div class="flex justify-between items-center py-3 border-b border-outline-variant/20">
<span class="font-semibold">Tuesday</span>
<span class="text-secondary font-medium">08:00 — 19:00</span>
</div>
<div class="flex justify-between items-center py-3 border-b border-outline-variant/20">
<span class="font-semibold">Wednesday</span>
<span class="text-secondary font-medium">08:00 — 19:00</span>
</div>
<div class="flex justify-between items-center py-3 border-b border-outline-variant/20">
<span class="font-semibold">Thursday</span>
<span class="text-secondary font-medium">08:00 — 21:00</span>
</div>
<div class="flex justify-between items-center py-3 border-b border-outline-variant/20">
<span class="font-semibold">Friday</span>
<span class="text-secondary font-medium">08:00 — 17:00</span>
</div>
<div class="flex justify-between items-center py-3 border-b border-outline-variant/20">
<span class="font-semibold">Saturday</span>
<span class="text-primary font-bold">10:00 — 15:00</span>
</div>
<div class="flex justify-between items-center py-3 border-b border-outline-variant/20">
<span class="font-semibold">Sunday</span>
<span class="text-outline italic">Emergency Only</span>
</div>
<div class="flex justify-between items-center py-3">
<span class="font-bold text-primary">Public Holidays</span>
<span class="text-outline">Closed</span>
</div>
</div>
</div>
<!-- Announcements Feed -->
<div class="bg-primary text-on-primary p-10 rounded-3xl flex flex-col justify-between">
<div>
<div class="flex items-center gap-3 mb-8">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">campaign</span>
<h2 class="text-2xl font-bold">Latest Pulse</h2>
</div>
<div class="space-y-8">
<div>
<span class="text-xs uppercase font-bold tracking-widest opacity-60">Sept 12, 2024</span>
<h4 class="font-bold text-lg mt-1">New Aesthetic Laser Protocol</h4>
<p class="text-on-primary/70 text-sm mt-2">Introducing the Mint-Glow series for 20-minute whitening without sensitivity.</p>
</div>
<div>
<span class="text-xs uppercase font-bold tracking-widest opacity-60">Sept 05, 2024</span>
<h4 class="font-bold text-lg mt-1">Evening Hours Extended</h4>
<p class="text-on-primary/70 text-sm mt-2">Thursday nights are now open until 9 PM for our executive patients.</p>
</div>
</div>
</div>
<button class="mt-12 group flex items-center gap-2 font-bold hover:gap-4 transition-all">
                            View All Updates
                            <span class="material-symbols-outlined">arrow_right_alt</span>
</button>
</div>
</div>
</div>
</section>
<!-- Location & Map Section -->
<section class="py-24" id="location">
<div class="max-w-7xl mx-auto px-8">
<div class="bg-surface-container-lowest rounded-[3rem] overflow-hidden shadow-2xl flex flex-col md:flex-row">
<div class="md:w-1/2 p-12 lg:p-20">
<h2 class="text-4xl font-extrabold text-on-surface mb-8">Visit the Oasis.</h2>
<div class="space-y-10">
<div class="flex gap-6">
<div class="w-12 h-12 bg-primary-fixed rounded-xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary">location_on</span>
</div>
<div>
<h4 class="font-bold text-lg mb-1">Our Studio</h4>
<p class="text-on-surface-variant">1422 Serenity Blvd, Suite 400<br/>Medical District, Metropolis 90210</p>
</div>
</div>
<div class="flex gap-6">
<div class="w-12 h-12 bg-primary-fixed rounded-xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary">call</span>
</div>
<div>
<h4 class="font-bold text-lg mb-1">Contact Concierge</h4>
<p class="text-on-surface-variant">+1 (555) 890-2344<br/>concierge@thecuratedbreath.com</p>
</div>
</div>
<div class="flex gap-6">
<div class="w-12 h-12 bg-primary-fixed rounded-xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary">directions_car</span>
</div>
<div>
<h4 class="font-bold text-lg mb-1">Valet Parking</h4>
<p class="text-on-surface-variant">Complimentary valet is provided for all patients at the main entrance.</p>
</div>
</div>
</div>
</div>
<div class="md:w-1/2 min-h-[400px] relative grayscale">
<img class="w-full h-full object-cover" data-alt="Minimalist street map of a modern metropolitan city area with light blue and grey tones" data-location="Metropolis" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCsRWlBMULDv8ZnFrVini31nZRjWisxLoSoCvPlhqTaYW1oha1LJOtM5QRjEL4gVZ7LY0yLkmEinpX7VwlovQ5A_fr-4t_xqzCNscyhC397pxjreAzCHVv0RLHWu5jO_ztfQHM7c-CpHbspqYgJP4VaitiUv64Rw4BKXGw8jppiikSBupnkawgrCDETven0rquGxgC5bfYixBXcS97M-YIHOMGo0qvM40sKe5rD58HztoXTsusTUCDWxG5mjoL9ASS3fFpDu-RaYu0"/>
<!-- Glassmorphic Overlay -->
<div class="absolute inset-0 bg-primary/10 pointer-events-none"></div>
<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-surface/80 backdrop-blur-md p-6 rounded-2xl shadow-2xl border border-white/20 text-center">
<span class="material-symbols-outlined text-primary text-4xl mb-2" style="font-variation-settings: 'FILL' 1;">location_on</span>
<p class="font-bold text-on-surface">The Curated Breath</p>
<p class="text-xs text-secondary mt-1">Now Arriving</p>
</div>
</div>
</div>
</div>
</section>
</main>
<footer class="w-full py-16 px-8 bg-[#f2f4f5] dark:bg-slate-950 font-['Inter'] text-xs uppercase tracking-widest mt-12">
<div class="flex flex-col items-center text-center space-y-6 w-full max-w-7xl mx-auto">
<div class="text-xl font-bold text-sky-900 mb-4">The Curated Breath</div>
<div class="flex flex-wrap justify-center gap-x-8 gap-y-4">
<a class="text-slate-500 hover:text-sky-600 transition-colors" href="#">Privacy Policy</a>
<a class="text-slate-500 hover:text-sky-600 transition-colors" href="#">Terms of Service</a>
<a class="text-slate-500 hover:text-sky-600 transition-colors" href="#">Patient Portal</a>
<a class="text-slate-500 hover:text-sky-600 transition-colors" href="#">Accessibility</a>
</div>
<div class="pt-8 text-slate-400 border-t border-outline-variant/20 w-full">
                © 2024 The Curated Breath. Professional Dental Serenity.
            </div>
</div>
</footer>
</body></html>