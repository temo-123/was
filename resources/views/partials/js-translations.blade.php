{{-- Texts for the Vue components (resources/js/i18n.js): lang/<locale>.json, nothing for English. --}}
@php($jsonPath = lang_path(app()->getLocale().'.json'))
<script>window.translations = @json(is_file($jsonPath) ? json_decode(file_get_contents($jsonPath), true) : (object) []);</script>
