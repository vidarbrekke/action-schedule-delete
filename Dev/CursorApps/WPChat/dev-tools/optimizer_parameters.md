# Optimizer Parameters and UI Mapping

This document lists the 26 parameters managed by the `Wcac_Settings_Optimizer` and their corresponding visibility in the plugin's admin user interface.

## Full Parameter List (26 Keys from `Wcac_Settings_Optimizer::$parameter_keys`)

1.  `wcac_parent_product_weight` (UI: Scoring Rules - Parent Product Weight)
2.  `wcac_variation_product_weight` (UI: Scoring Rules - Variation Product Weight)
3.  `wcac_title_match_weight` (UI: Scoring Rules - Title Match Weight)
4.  `wcac_content_match_weight` (UI: Scoring Rules - Content Match Weight)
5.  `wcac_category_match_weight` (UI: Scoring Rules - Category Match Weight)
6.  `wcac_tag_match_weight` (UI: Scoring Rules - Tag Match Weight)
7.  `wcac_on_sale_weight` (UI: Scoring Rules - On Sale Weight)
8.  `wcac_direct_title_match_bonus` (UI: Scoring Rules - Direct Title Match Bonus)
9.  `wcac_title_category_match_boost` (UI: Scoring Rules - Title-Category Match Boost)
10. `wcac_exact_product_name_boost` (UI: Scoring Rules - Exact Product Name Boost)
11. `wcac_multi_field_match_bonus` (UI: Scoring Rules - Multi-Field Match Bonus)
12. `wcac_all_keywords_in_title_boost` (UI: Scoring Rules - All Keywords in Title Boost)
13. `wcac_parent_preference_margin` (UI: Scoring Rules - Parent Preference Margin)
14. `wcac_relative_score_threshold` (UI: Scoring Rules - Relative Score Threshold)
15. `wcac_max_results_returned` (UI: Scoring Rules - Max Results Returned)
16. `wcac_negative_keyword_penalty` (UI: Scoring Rules - Negative Keyword Penalty)
17. `wcac_boost_terms` (UI: Search Term Boost/Devalue - Boost Terms textarea)
18. `wcac_devalue_terms` (UI: Search Term Boost/Devalue - Devalue Terms textarea)
19. `wcac_compound_product_names` (UI: Customization & Filtering - Compound Product Names textarea)
20. `wcac_menu_match_weight` (UI: Scoring Rules - Menu presence weight)
21. `wcac_attribute_match_weight` (NO DEDICATED UI FIELD - Used for attribute matches)
22. `wcac_parent_category_match_weight` (NO DEDICATED UI FIELD - Used for parent category matches)
23. `wcac_taxonomy_match_weight` (NO DEDICATED UI FIELD - Used for general taxonomy term matches)
24. `wcac_boost_term_value` (UI: Search Term Boost/Devalue - Boost Term Score Value)
25. `wcac_devalue_term_value` (UI: Search Term Boost/Devalue - Devalue Term Score Value)
26. `wcac_recency_boost_multiplier` (UI: Scoring Rules - Recency Boost Multiplier)
27. `wcac_context_compression_algorithm` (UI: Context Compression - Compression Algorithm)
28. `wcac_outofstock_penalty` (UI: Scoring Rules - Out-of-Stock Penalty)
29. `wcac_rating_weight` (UI: Scoring Rules - Rating Weight)

## Summary of Parameters Without Dedicated UI Fields

The following parameters from the optimizer's list do not have a distinct, dedicated input field within the "Scoring Rules" or other primary sections of the admin UI:

*   `wcac_attribute_match_weight`
*   `wcac_parent_category_match_weight`
*   `wcac_taxonomy_match_weight`
*   `wcac_context_compression_algorithm`
*   `wcac_outofstock_penalty`
*   `wcac_rating_weight`

## Note on UI Sections and Optimizer Scope

While `wcac_compound_product_names`, `wcac_boost_terms`, and `wcac_devalue_terms` are part of the optimizer's 26-parameter list, their UI fields are located under "Customization & Filtering" or "Search Term Boost/Devalue" sections, not directly under "Scoring Rules."

The optimizer considers **all 25 parameters** listed above when it runs and applies settings. Fuzzy matching for medium and low similarity (Levenshtein distances 1 and 2) remains active with hardcoded boost values (7.5 and 2.5 respectively) in the `Wcac_ChatbotRules` class. Boost/Devalue terms are now applied at query time using the score values configured.

- **wcac_recency_boost_multiplier**: Score added for recently modified content. The boost is highest for content modified today and linearly decreases to zero for content older than the recency window (default: 30 days). Default: 10.0. Higher values favor newer content more strongly.
- **wcac_context_compression_algorithm**: Controls how retrieved context is compressed before being sent to the LLM. Options: None (no compression), Title (deduplicate by title), Category (deduplicate by category), Fuzzy (cluster by semantic similarity). Set in the admin UI under Context Compression.
- **wcac_outofstock_penalty**: Score penalty applied to out-of-stock products. Range: -200 (strong penalty) to 0 (none). Set in the admin UI under Scoring Rules. Default: 0 (no penalty).
- **wcac_rating_weight**: Score added based on product average rating (0-5 stars, normalized). Higher values prioritize highly rated products. Set to 0 to disable rating-based boosting. Default: 20.0. Tunable in the admin UI under Scoring Rules.

## Grouped and Custom Product Type Support

The plugin now supports all WooCommerce product types, including `simple`, `variable`, `