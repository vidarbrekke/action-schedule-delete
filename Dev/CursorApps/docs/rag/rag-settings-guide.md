# WP Customer AI Chatbot: Settings Tweaks Guide

This guide explains how the plugin's scoring and rule settings work, and how to adjust them to improve product matching for customer queries.

---

## How the Settings Work

- **Admin UI:**
  - The plugin's settings page in the WordPress admin lets you set values for all scoring and rule parameters (e.g., product weights, match weights, penalties, etc.).
  - **New:** You can now also set a Menu Match Weight to boost results for any content linked in any menu.
- **Saving Settings:**
  - When you save settings, they are stored in the `wp_options` table under the key `wcac_settings`.
- **Runtime Application:**
  - On admin load, the plugin fetches the current options and applies them to the chatbot's scoring logic. This ensures the values you set in the UI are used for all product matching and scoring.
- **Default Values:**
  - Each setting has a default value, but as soon as you save a value in the UI, your value is used instead of the default.

---

## Key Settings to Tweak for Better Product Matching

If your bot is not returning specific products for queries (e.g., "do you have malabrigo yarn?"), adjust the following settings:

### 2. Title/Content/Category/Tag/Menu Match Weights
- **Purpose:** Control how much a match in each field contributes to the product's score.
- **Tip:** Increase `wcac_title_match_weight` and/or `wcac_content_match_weight` if your product titles or descriptions contain the keywords customers search for. Also consider increasing `wcac_category_match_weight` and `wcac_tag_match_weight` if you use those fields.
- **New:** Increase `wcac_menu_match_weight` to boost any content linked in any menu (not just the primary menu). This is especially useful for highlighting important or navigational content.

### 3. Direct Title Match Bonus (`wcac_direct_title_match_bonus`)
- **Purpose:** Adds a bonus if the query exactly matches a product title.
- **Tip:** Increase if you expect exact matches to be important.

### 4. Max Results Returned (`wcac_max_results_returned`)
- **Purpose:** Limits the number of products shown.
- **Tip:** Set to a reasonable number (e.g., 5 or 10).

### 5. Negative Keyword Penalty
- **Purpose:** Penalizes products containing certain keywords.
- **Tip:** Make sure you are not penalizing products for having relevant terms.

---

## Out-of-Stock Penalty (Scoring)

- **Setting:** Out-of-Stock Penalty (`wcac_outofstock_penalty`)
- **Where:** Admin > WP Customer AI Chatbot > Settings > Scoring Rules
- **Default:** 0 (no penalty)
- **Range:** -200 (strong penalty) to 0 (none)
- **How it works:**
  - Applies a negative score to out-of-stock products during RAG scoring.
  - Does not filter them out, but makes them less likely to appear in results.
- **How to use:**
  - Set to 0 to ignore stock status in scoring.
  - Set to a negative value (e.g., -50, -100) to penalize out-of-stock items.
  - Use strong penalties (e.g., -200) to almost always hide unavailable products unless no in-stock matches exist.
- **Best Practice:**
  - Tune this value based on your store's inventory turnover and user expectations. For high-turnover stores, a mild penalty is often best. For stores with many discontinued items, a strong penalty may be preferred.

---

## New Indexing Features and Signals

- **Parent Category Hierarchy:**
  - The indexer now stores all parent categories (and their parents, recursively) for each product, post, or page. Boosting category weights will now also affect matches on parent categories. Both `parent_categories` and `parent_category_names` fields are indexed.
- **Menu Hierarchy:**
  - All menu titles from all registered menus are indexed for each item. Boosting the menu match weight will prioritize any content linked in any menu. The `menu_titles` field is now included in the index.
- **Expanded Fields:**
  - The index now includes `attributes_text`, `taxonomies`, `stock_status`, and a `search_blob` for improved matching. While not directly tweakable, these fields improve retrieval accuracy and allow for more granular scoring and filtering.
- **Database Schema:**
  - The plugin activator ensures all required columns are present on fresh install. If you see errors about missing columns (e.g., `parent_categories`), run the provided ALTER TABLE statement or re-activate the plugin to update the schema.

---

## Example Settings for Better Matching

| Setting                      | Suggested Value |
|------------------------------|----------------|
| wcac_title_match_weight      | 3              |
| wcac_content_match_weight    | 3              |
| wcac_category_match_weight   | 2              |
| wcac_tag_match_weight        | 2              |
| wcac_direct_title_match_bonus| 5              |
| wcac_max_results_returned    | 5              |

---

## Troubleshooting Steps

1. **Set `relative_score_threshold` to 0** to ensure any product with a nonzero score is considered.
2. **Increase match weights** for fields that contain your product keywords, including the new menu match weight.
3. **Check your product data:**
   - Make sure products have the keywords (e.g., "Malabrigo") in their title, content, category, tags, or are linked in a menu.
4. **Re-index content** after changing settings, updating product data, or modifying menus/categories (use the "Re-index Content" button in the admin UI).
5. **Test with known product names** to verify that products are being returned.
6. **Review negative keyword settings** to ensure you are not unintentionally penalizing relevant products.
7. **If the UI reports 0 items indexed or you see database errors about missing columns:**
   - This may be caused by a missing or incorrect column in the index table. The plugin activator ensures all columns are present on fresh install, but for existing installs, you may need to run a database migration (ALTER TABLE) or re-activate the plugin to update the schema.

---

## Summary
- All scoring and rule settings are tweakable via the admin UI, including the new menu match weight.
- Parent category and menu hierarchy are now indexed and used for scoring.
- Defaults are only used if you have not set a value.
- Lower the relative score threshold and increase match weights to improve product matching.
- Always re-index after making changes to settings, product data, menus, or categories.
- If you encounter zero results or unexpected behavior, check the troubleshooting steps above and review the debug log for errors.

Keep this guide handy for future adjustments to your chatbot's product matching behavior and to leverage the latest retrieval and scoring features! 