<?php
function wcac_synonym_expansion_step(array $context): array {
    $query = $context['query'];
    $keywords = class_exists('Wcac_ChatbotRules') ? Wcac_ChatbotRules::extract_keywords($query) : [];
    $context['keywords'] = $keywords;
    return $context;
} 