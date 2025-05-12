# CleanSEO Optimizer

Zaawansowana, modularna wtyczka SEO dla WordPressa z obsługą AI (OpenAI, Gemini, Claude, HuggingFace), integracją WooCommerce, analizą trendów Google, rozbudowanym systemem przekierowań, dynamiczną mapą witryny, nowoczesnym panelem administracyjnym i eksportem danych do CSV/PDF.

## Najważniejsze funkcje

- **Nowoczesny panel administracyjny** (PL) z zakładkami: Ustawienia SEO, Mapa witryny, Przekierowania, Konkurencja, Dane strukturalne, Analiza treści, Integracje, SEO Lokalne, Audyt SEO, Trendy, Statystyki AI, Logi
- **Automatyczna analiza SEO** całej strony (meta tagi, nagłówki, ALT, linki, schema.org, 404)
- **Audyt SEO** z raportem i rekomendacjami, eksport do PDF/CSV
- **Zarządzanie konkurencją** i śledzenie pozycji, analiza trendów Google (wykresy, eksport)
- **Zarządzanie lokalizacjami** firmy i generowanie danych LocalBusiness (JSON-LD)
- **Przekierowania 301/302** (CRUD, batch, import/eksport CSV)
- **Edycja robots.txt** i dynamiczna mapa witryny (XML, podział na podmapy, obsługa dużych sklepów, produkty WooCommerce)
- **Integracja z WooCommerce** (produkty, kategorie, AI do meta/description, schema.org)
- **Automatyczne generowanie meta tagów z AI** (OpenAI, Gemini, Claude, HuggingFace)
- **Zaawansowane bezpieczeństwo** (nonce, uprawnienia, sanityzacja)
- **Eksport danych** (ustawienia, konkurenci, audyty, lokalizacje, analityka, logi, błędy 404, raporty SEO) do CSV/PDF z poziomu panelu
- **Logowanie i statystyki AI** (historia zapytań, eksport logów)
- **Wydajność** – AJAX, lazy loading, optymalizacje JS/CSS

## Instalacja

1. Skopiuj katalog wtyczki do folderu `wp-content/plugins/cleanseo-optimizer`.
2. Aktywuj wtyczkę w panelu WordPress.
3. Po aktywacji pojawi się menu **CleanSEO** z wszystkimi funkcjami.

## Obsługa AJAX

Wtyczka korzysta z AJAX do obsługi większości operacji w panelu administracyjnym. Wszystkie akcje wywołuj przez globalną zmienną `ajaxurl` oraz odpowiedni nonce przekazany z PHP do JS (np. przez `wp_localize_script`).

### Przykładowe akcje AJAX (do użycia w cleanseo-admin.js):

```js
// Eksport danych (np. konkurenci, audyty, logi)
$.post(ajaxurl, {
    action: 'cleanseo_export_data',
    export_type: 'seo',
    section: 'competitors', // lub 'audits', 'logs', 'settings', '404', 'all'
    format: 'csv', // lub 'pdf'
    nonce: cleanseo_vars.nonce_export
}, function(response) { /* ... */ });

// Generowanie meta tagów AI dla produktu WooCommerce
$.post(ajaxurl, {
    action: 'cleanseo_generate_ai_meta',
    post_id: productId,
    provider: 'openai', // lub 'gemini', 'claude', 'huggingface'
    nonce: cleanseo_vars.nonce_generate_ai
}, function(response) { /* ... */ });

// Pobierz trendy Google
$.post(ajaxurl, {
    action: 'cleanseo_get_trends',
    keyword: 'twoje_slowo',
    nonce: cleanseo_vars.nonce_trends
}, function(response) { /* ... */ });
```

## Eksport danych

- Eksportuj ustawienia, konkurencję, audyty, lokalizacje, analitykę, logi, błędy 404 oraz raporty SEO do plików CSV lub PDF.
- Eksport dostępny z poziomu panelu (przycisk "Eksportuj").
- Pliki są generowane w katalogu `/wp-content/uploads/cleanseo-exports/` i pobierane przez bezpieczny link.

## Integracja WooCommerce

- Produkty i kategorie zawsze w mapie witryny.
- Pola SEO (meta, OG) dla produktów i wariantów.
- Schema.org dla produktów.
- Przycisk "Wygeneruj meta AI" w edycji produktu (łączy trendy Google i AI).

## Trendy Google

- Analiza trendów dla własnych i konkurencyjnych słów kluczowych.
- Wykresy (Chart.js), eksport do CSV/PDF.
- Sugestie tematów, historia trendów.

## Bezpieczeństwo

- Wszystkie operacje AJAX wymagają nonce i uprawnień administratora.
- Dane są sanityzowane i walidowane po stronie PHP.
- Eksport i pobieranie plików zabezpieczone tokenami.

## Wsparcie

Wszelkie pytania i zgłoszenia błędów prosimy kierować na adres e-mail autora lub przez GitHub.

## FAQ

### 1. Czy wtyczka działa z najnowszym WordPressem i WooCommerce?
Tak, CleanSEO Optimizer jest testowany z najnowszymi wersjami WordPressa i WooCommerce.

### 2. Jak uruchomić eksport danych?
W panelu administracyjnym wybierz odpowiednią sekcję (np. Konkurencja, Audyty, Logi) i kliknij przycisk „Eksportuj”. Możesz wybrać format CSV lub PDF.

### 3. Jak działa generowanie meta tagów AI?
W edycji produktu WooCommerce kliknij „Wygeneruj meta AI”. Wtyczka połączy trendy Google z wybranym modelem AI (OpenAI, Gemini, Claude, HuggingFace) i wygeneruje zoptymalizowane meta tagi.

### 4. Czy eksportowane pliki są bezpieczne?
Tak, pliki eksportu są dostępne tylko dla administratorów i zabezpieczone tokenem (nonce). Pliki są przechowywane w `/wp-content/uploads/cleanseo-exports/`.

### 5. Czy mogę dodać własne modele AI?
Tak, architektura wtyczki pozwala na rozszerzanie obsługiwanych providerów AI przez hooki i filtry WordPressa.

### 6. Jak zgłosić błąd lub uzyskać wsparcie?
Napisz na adres e-mail autora lub zgłoś problem przez GitHub. Szczegóły w sekcji „Wsparcie”.

### 7. Czy wtyczka spowalnia stronę?
Nie, wszystkie operacje są wykonywane asynchronicznie (AJAX), a długie listy są ładowane z użyciem lazy loading.

### 8. Czy mogę wyeksportować całą konfigurację SEO?
Tak, wybierz sekcję „Wszystko” podczas eksportu, aby pobrać pełną konfigurację i dane SEO. 