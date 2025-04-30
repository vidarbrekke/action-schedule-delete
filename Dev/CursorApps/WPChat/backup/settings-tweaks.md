# WP Customer AI Chatbot: Settings Tweaks Guide

This guide explains how the plugin's scoring and rule settings work, and how to adjust them to improve product matching for customer queries.

---

## How the Settings Work

- **Admin UI:**
  - The plugin's settings page in the WordPress admin lets you set values for all scoring and rule parameters (e.g., product weights, match weights, penalties, etc.).
- **Saving Settings:**
  - When you save settings, they are stored in the `wp_options` table under the key `wcac_settings`.
- **Runtime Application:**
  - On admin load, the plugin fetches the current options and applies them to the chatbot's scoring logic. This ensures the values you set in the UI are used for all product matching and scoring.
- **Default Values:**
  - Each setting has a default value, but as soon as you save a value in the UI, your value is used instead of the default.

---

## Key Settings to Tweak for Better Product Matching

If your bot is not returning specific products for queries (e.g., "do you have malabrigo yarn?"), adjust the following settings:

### 1. Minimum Score Threshold (`wcac_min_score_threshold`)
- **Purpose:** Sets the minimum score a product must reach to be returned.
- **Tip:** Lower this value (e.g., set to 0 or 1) to make it easier for products to be shown.

### 2. Title/Content/Category/Tag Match Weights
- **Purpose:** Control how much a match in each field contributes to the product's score.
- **Tip:** Increase `wcac_title_match_weight` and/or `wcac_content_match_weight` if your product titles or descriptions contain the keywords customers search for. Also consider increasing `wcac_category_match_weight` and `wcac_tag_match_weight` if you use those fields.

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

## Example Settings for Better Matching

| Setting                      | Suggested Value |
|------------------------------|----------------|
| wcac_min_score_threshold     | 0              |
| wcac_title_match_weight      | 3              |
| wcac_content_match_weight    | 3              |
| wcac_category_match_weight   | 2              |
| wcac_tag_match_weight        | 2              |
| wcac_direct_title_match_bonus| 5              |
| wcac_max_results_returned    | 5              |

---

## Troubleshooting Steps

1. **Set `Minimum Score Threshold` to 0** to ensure any product with a nonzero score is considered.
2. **Increase match weights** for fields that contain your product keywords.
3. **Check your product data:**
   - Make sure products have the keywords (e.g., "Malabrigo") in their title, content, category, or tags.
4. **Re-index content** after changing settings or updating product data (use the "Re-index Content" button in the admin UI).
5. **Test with known product names** to verify that products are being returned.
6. **Review negative keyword settings** to ensure you are not unintentionally penalizing relevant products.

---

## Summary
- All scoring and rule settings are tweakable via the admin UI.
- Defaults are only used if you have not set a value.
- Lower the minimum score threshold and increase match weights to improve product matching.
- Always re-index after making changes.

Keep this guide handy for future adjustments to your chatbot's product matching behavior! 