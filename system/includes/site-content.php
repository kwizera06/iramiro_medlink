<?php
// includes/site-content.php — text/images of the public landing page (../index.php), editable from frontend.php.
// Saved overrides live in uploads/site/content.json; anything missing falls back to the defaults below.
// No database on purpose: the public page must render even when the DB is down.

const SITE_DIR = __DIR__ . '/../uploads/site/';
const SITE_FILE = SITE_DIR . 'content.json';

function site_defaults(): array {
    return [
        'meta' => ['title' => "Iramiro Clinic — Compassionate Care, Kabuga, Kigali"],
        'brand' => ['small' => "Iramiro", 'name' => "Clinic", 'tagline' => "Compassionate Care"],
        'nav' => [
            'home' => "Home", 'about' => "About Us", 'services' => "Services",
            'team' => "Doctors' Schedule", 'partner' => "Partners", 'contact' => "Contact Us", 'login' => "Login",
        ],
        'hero' => [
            'eyebrow' => "Iramiro Clinic · Kabuga, Kigali · Open 24h",
            'title' => "Welcome to Iramiro Clinic —",
            'title_highlight' => "compassionate care.",
            'text' => "",
            'button_primary' => "Contact Us",
            'button_secondary' => "Our Services",
            'image' => "assets/hero.jpg",
        ],
        'about' => [
            'eyebrow' => "About Us",
            'heading' => "A leading provider of compassionate, patient-centered healthcare.",
            'paragraphs' => [
                "Our Vision: to be the leading provider of compassionate, patient-centered healthcare, transforming lives and fostering healthier communities through innovation and excellence.",
                "Our Mission: to provide high-quality, accessible healthcare services, focusing on personalized care, patient safety, and community well-being. We strive to empower our patients with knowledge and support them to achieve their health goals and enhance their quality of life.",
                "We believe informed patients make healthier decisions. Our online services are tailored to simplify the healthcare experience — accessing medical records and staying in touch with your healthcare provider — while we remain committed to improving and maintaining high standards of medical practice.",
            ],
            'card_eyebrow' => "Our Goals",
            'card_title' => "What guides our work",
            'card_list' => [
                "Patient Care — high-quality, compassionate care",
                "Accessibility — healthcare for all, regardless of background or financial status",
                "Innovation — adopting the latest medical advancements and technologies",
                "Community Engagement — improving the health and well-being of our community",
                "Professional Excellence — continuous improvement and high medical standards",
            ],
            'card_footer' => "Open Monday – Sunday · 24 hours",
        ],
        'services' => [
            'eyebrow' => "Services",
            'heading' => "Care that covers the essentials",
            'intro' => "From general consultations to hospitalization, our services are delivered by one dedicated team at Iramiro Clinic in Kabuga, Kigali.",
            'cards' => [
                ['icon' => 'heart', 'title' => "General Medicine", 'text' => "Consultation, diagnosis and treatment for everyday illness and general health concerns.", 'image' => "assets/svc1.jpg"],
                ['icon' => 'users', 'title' => "Gynecology", 'text' => "Women's health care, from check-ups to reproductive and maternal health services.", 'image' => "assets/svc2.jpg"],
                ['icon' => 'shield', 'title' => "Pediatry", 'text' => "Caring medical attention for babies, children and adolescents.", 'image' => "assets/svc3.jpg"],
                ['icon' => 'flask', 'title' => "Internal Medicine", 'text' => "Diagnosis and long-term management of adult illnesses and chronic conditions.", 'image' => "assets/svc4.jpg"],
                ['icon' => 'tooth', 'title' => "Dentistry", 'text' => "Check-ups, cleaning, fillings and extractions to keep teeth and gums healthy.", 'image' => "assets/svc5.jpg"],
                ['icon' => 'tool', 'title' => "Physiotherapy", 'text' => "Rehabilitation and movement therapy to recover from injury and manage pain.", 'image' => "assets/svc6.jpg"],
                ['icon' => 'bed', 'title' => "Hospitalization", 'text' => "Comfortable inpatient beds with round-the-clock monitoring by our medical team.", 'image' => "assets/svc7.jpg"],
            ],
        ],
        // Source: Doctors' Schedule timetable on iramiroclinics.rw (Oct 2024). Edit in Admin > Front End > Team; add photos there.
        'team' => [
            'eyebrow' => "Doctors' Schedule",
            'heading' => "Our doctors and when to see them",
            'intro' => "Specialists and general practitioners at Iramiro Clinic, with their consultation days and hours.",
            'members' => [
                ['name' => "Dr Emery", 'role' => "Gynecology · Thu–Fri, 9am–5pm", 'photo' => ""],
                ['name' => "Dr Kenneth", 'role' => "Gynecology · Saturday, 9am–5pm", 'photo' => ""],
                ['name' => "Dr Alponse", 'role' => "Internal Medicine · Saturday, 9am–5pm", 'photo' => ""],
                ['name' => "Dr Jean Pierre", 'role' => "Pediatry · 16pm", 'photo' => ""],
                ['name' => "Sother Nyandwi", 'role' => "Physiotherapy · Tue–Sun, 8am–5pm", 'photo' => ""],
                ['name' => "Hagenimana Jean de Dieu", 'role' => "Dentistry · Full time, 8am–5pm", 'photo' => ""],
                ['name' => "Dr Gasasira Felix", 'role' => "Dental Surgeon · Sat–Sun, 8am–5pm", 'photo' => ""],
                ['name' => "Dr Donald", 'role' => "General Practitioner · Full time", 'photo' => ""],
                ['name' => "Dr Hakundimfura Evariste", 'role' => "General Practitioner · Full time", 'photo' => ""],
                ['name' => "Dr Umuhire Diane", 'role' => "General Practitioner · Full time", 'photo' => ""],
                ['name' => "Dr Aimable", 'role' => "General Practitioner · Part-time", 'photo' => ""],
                ['name' => "Dr Arielle", 'role' => "General Practitioner · Part-time", 'photo' => ""],
                ['name' => "Dr Jean Baptiste", 'role' => "General Practitioner · Part-time", 'photo' => ""],
            ],
        ],
        // Partners not published on iramiroclinics.rw; add in Admin > Front End. No items = section hidden.
        'partners' => [
            'eyebrow' => "Our Partners",
            'heading' => "Trusted by insurers and employers.",
            'text' => "We work with insurers and companies so your visit is simple and your costs are covered wherever possible.",
            'button' => "Become a Partner",
            'groups' => [
                ['title' => "Insurance Providers", 'items' => []],
            ],
        ],
        'contact' => [
            'eyebrow' => "Contact Us",
            'heading' => "Let's get you the care you need.",
            'text' => "Reach out to book an appointment, ask about our services or check your insurance coverage. Our front desk is open Monday to Sunday, 24 hours.",
            'email' => "info@iramiroclinics.rw",
            'phone1' => "+250 785 450 270",
            'phone2' => "+250 786 179 187",
            'location' => "KK3 17 Rd, Kabuga, Kigali, Rwanda · P.O. Box 2502 Kigali",
            'label_email' => "Email",
            'label_phone' => "Phone",
            'label_location' => "Location",
            'form' => [
                'name_label' => "Full name", 'name_placeholder' => "Your name",
                'email_label' => "Email", 'email_placeholder' => "you@email.com",
                'subject_label' => "Subject", 'subject_placeholder' => "What's this about?",
                'message_label' => "Message", 'message_placeholder' => "Tell us how we can help",
                'button' => "Send Message",
                'note' => "We usually reply within one business day.",
                'thanks' => "Thank you for reaching out! We will get back to you shortly.",
            ],
        ],
        'footer' => [
            'about' => "Iramiro Clinic — compassionate, patient-centered care in Kabuga, Kigali. General medicine, gynecology, pediatry, internal medicine, dentistry, physiotherapy and hospitalization.",
            'heading_menu' => "Menu",
            'heading_more' => "More",
            'heading_contact' => "Contact",
            'address' => "KK3 17 Rd, Kabuga, Kigali",
            'copyright' => "© 2026 Iramiro Clinic. All rights reserved.",
            'tagline' => "Compassionate Care.",
        ],
    ];
}

// Saved values over defaults. Lists (numeric keys) are replaced whole, objects merged per key.
function site_merge($def, $saved) {
    if (!is_array($def)) return is_array($saved) ? $def : $saved;
    if (!is_array($saved)) return $def;
    if (site_is_list($def)) return site_is_list($saved) ? $saved : $def;
    foreach ($def as $k => $v) if (array_key_exists($k, $saved)) $def[$k] = site_merge($v, $saved[$k]);
    return $def;
}

function site_load(): array {
    $saved = is_file(SITE_FILE) ? json_decode((string)file_get_contents(SITE_FILE), true) : null;
    return site_merge(site_defaults(), is_array($saved) ? $saved : []);
}

// Keep only keys that exist in the defaults, scalars as trimmed strings — editor input is never trusted as-is.
function site_clean($def, $in) {
    if (is_array($def) && !site_is_list($def)) {
        $in = is_array($in) ? $in : [];
        $out = [];
        foreach ($def as $k => $v) $out[$k] = site_clean($v, $in[$k] ?? ($v === [] ? [] : (is_array($v) ? $v : '')));
        return $out;
    }
    if (is_array($def)) { // list: template = first default item; empty default = flat string map
        $out = [];
        foreach (is_array($in) ? $in : [] as $item) {
            if ($def && is_array($def[0])) $out[] = site_clean($def[0], $item);
            elseif ($def) $out[] = site_clean('', $item);
            elseif (is_array($item)) $out[] = array_map(fn($x) => is_scalar($x) ? mb_substr(trim((string)$x), 0, 2000) : '', array_filter($item, fn($k) => in_array($k, ['name', 'label', 'logo', 'role', 'photo'], true), ARRAY_FILTER_USE_KEY));
            else $out[] = site_clean('', $item);
        }
        return $out;
    }
    return is_scalar($in) ? mb_substr(trim((string)$in), 0, 5000) : '';
}

function site_icons(): array {
    return [
        'heart'  => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
        'tooth'  => '<path d="M7 3c-2.5 0-4 2-4 4.5 0 2 1 3.5 1.5 5.5.6 2.5.8 8 2.8 8 1.7 0 1.5-5 3-5h3.4c1.5 0 1.3 5 3 5 2 0 2.2-5.5 2.8-8 .5-2 1.5-3.5 1.5-5.5C21 5 19.5 3 17 3c-2 0-3 1-5 1S9 3 7 3z"/>',
        'tool'   => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'chat'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'shield' => '<path d="M12 22s-8-4.5-8-11.5V5l8-3 8 3v5.5C20 17.5 12 22 12 22z"/><path d="M9 12l2 2 4-4"/>',
        'users'  => '<circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21v-1a6 6 0 0 1 12 0v1"/><path d="M15 21v-.5a4.5 4.5 0 0 1 6-4.2"/>',
        'bed'    => '<path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/>',
        'flask'  => '<path d="M9 2v6L4.5 18a2 2 0 0 0 2 3h11a2 2 0 0 0 2-3L15 8V2"/><path d="M9 2h6"/><path d="M8.5 13h7"/>',
    ];
}

function site_is_list(array $a): bool { return $a === [] || array_keys($a) === range(0, count($a) - 1); }
