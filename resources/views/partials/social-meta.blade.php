{{--
    Balises Open Graph rendues CÔTÉ SERVEUR.

    Indispensable : les robots d'aperçu (WhatsApp, Facebook, Telegram, iMessage…)
    n'exécutent pas JavaScript. Un <Head> Inertia est écrit par React après le
    chargement de la page : le robot ne le voit jamais. Ces balises doivent donc
    être présentes dans le HTML initial, sinon le lien partagé s'affiche sans
    image ni description.

    Les valeurs viennent de $meta, transmis par le contrôleur via
    Inertia::render(...)->withViewData(['meta' => ...]). Sans $meta, on retombe
    sur les valeurs génériques du site.
--}}
@php
    $meta = $meta ?? [];

    // Repli en dur plutôt que config('app.name') : APP_NAME vaut encore
    // "Laravel" dans les environnements existants, ce qui s'afficherait tel quel
    // dans les aperçus de partage.
    $metaTitle = $meta['title'] ?? 'Poulet AFC';
    $metaDescription = $meta['description'] ?? 'Boutique en ligne de poulet et produits frais, livrés chez vous.';
    $metaImage = $meta['image'] ?? asset('images/logo.png');
    $metaUrl = $meta['url'] ?? url()->current();
    $metaType = $meta['type'] ?? 'website';
@endphp

<meta name="description" content="{{ $metaDescription }}">

<meta property="og:site_name" content="Poulet AFC">
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="{{ $metaType }}">
<meta property="og:url" content="{{ $metaUrl }}">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:image" content="{{ $metaImage }}">
{{-- WhatsApp ne récupère l'aperçu que sur des liens https : on double l'URL en secure_url. --}}
<meta property="og:image:secure_url" content="{{ $metaImage }}">
<meta property="og:image:alt" content="{{ $metaTitle }}">

@isset($meta['image_width'])
    <meta property="og:image:width" content="{{ $meta['image_width'] }}">
    <meta property="og:image:height" content="{{ $meta['image_height'] }}">
@endisset

@isset($meta['price'])
    <meta property="product:price:amount" content="{{ $meta['price'] }}">
    <meta property="product:price:currency" content="{{ $meta['currency'] ?? 'XAF' }}">
@endisset

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $metaImage }}">
