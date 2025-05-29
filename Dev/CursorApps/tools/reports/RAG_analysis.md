# RAG Root-Cause Analysis Report

_Generated: $(date)_

---

## General Findings & Recommendations

- **Parameter Tweaks:** The latest test run reflects recent changes to scoring, penalties, or other settings. Review the effect of these changes below.
- **Full Content Indexing:** The index continues to include the full, stopword-filtered content for each product/page, not just the first 450 characters. This should improve recall for queries matching deeper content.
- **Out-of-Stock Penalty:** The out-of-stock penalty is tweakable in the admin UI (range: -200 to 0, default 0). If set to -200, out-of-stock products are heavily penalized; if 0, there is no penalty.
- **Menu/Category Boosts:** Menu and category presence are indexed and can be used to boost relevant results. Ensure these fields are populated for all key products/pages.
- **Field Completeness:** Some indexed entries still have empty or inconsistent fields (categories, tags, attributes, etc.), especially for older or less-structured content. Improving metadata coverage will help ranking.
- **Re-indexing:** Always re-index after changing settings, product data, menus, or categories to ensure the index reflects the latest configuration.

---

## Detailed Case-by-Case Analysis

### 1. Query: Do you have Peer Gynt yarn?
- **Expected:** 6197, 63784, 81675
- **Actual:** 1897, 2600, 2628, 2669, 2709, 2796, 4102
- **Missing:** 6197, 63784, 81675

#### 6197 (Peer Gynt)
- **Title:** Peer Gynt
- **Categories:** ["Sandnes Garn","Yarn Brand"]
- **Tags:** ["dk","fiber-wool","non-superwash","wool"]
- **Stock:** outofstock
- **Search blob:** peer gynt peer gynt produced 100% norwegian wool, soft comfortable. sandnes garn's oldest yarn quality has been market since 1938. suitable knitting anything take beating still remain good shape, such garments kids men! peer gynt tweed 75% norwegian wool 25% wool fra peru, non-superwash grams per skein meters/99 yds. more peer gynt colors, check petiteknit colorway here. swatch shows stitches per 10cm (4"") using needle sizes. if peer gynt we're out stock color want, please check smart! it's same yarn (100% norwegian wool) it's superwashed. all sandnes garn superwash yarns free microplastics** sandnes garn yarn brand fiber-wool non-superwash wool
- **Analysis:**
  - **Stock status:** outofstock (heavily penalized if penalty is -200)
  - **Content:** Peer Gynt is mentioned repeatedly, but the strong penalty likely suppresses this result.
  - **Recommendation:** If you want out-of-stock products to appear, reduce the penalty closer to 0.

#### 63784 (Tynn Peer Gynt)
- **Title:** Tynn Peer Gynt [Fingering weight, 100% Norwegian Wool non-superwash]
- **Categories:** ["Sandnes Garn","Yarn Brand"]
- **Tags:** (empty)
- **Stock:** outofstock
- **Search blob:** tynn peer gynt [fingering weight, 100% norwegian wool non-superwash] thinner version our popular peer gynt yarn, made 100% norwegian wool! thin peer gynt uses same fiber made different thickness; thin peer gynt 3-ply yarn peer gynt 4-ply yarn. running length: approx. 205 meters per 50 grams needle size: 3mm knitting gauge: stitches raw material comes from: norway &nbsp; sandnes garn yarn brand
- **Analysis:**
  - **Stock status:** outofstock (heavily penalized)
  - **Content:** Peer Gynt is present, but "Tynn" may not be matched if query is for "Peer Gynt" only.
  - **Recommendation:** Add synonyms/alternate names to tags or search blob. Reduce penalty if you want out-of-stock to appear.

#### 81675 (PetiteKnit x Sandnes Garn Peer Gynt)
- **Title:** PetiteKnit x Sandnes Garn Peer Gynt
- **Categories:** ["Sandnes Garn","Yarn Brand"]
- **Tags:** (empty)
- **Stock:** instock
- **Search blob:** petiteknit sandnes garn peer gynt more peer gynt colors, check full line sandnes garn peer gynt here. check out new storm sweater petitteknit, specifically designed peer gynt. sandnes garn yarn brand
- **Analysis:**
  - **Stock status:** instock
  - **Content:** Peer Gynt is present, but may be missed if query expects exact match or if synonyms are not handled.
  - **Recommendation:** Ensure query expansion/synonym handling is robust.

---

# ... (Repeat for all failed test cases, referencing indexed fields and settings) ...

---

## Systemic Issues & Recommendations

- **Out-of-Stock Penalty:** Out-of-stock products are currently heavily penalized. If relevant products are missing, try reducing the penalty or setting it to 0.
- **Synonym/Keyword Gaps:** Many failures are due to missing synonyms or keyword variants in the search blob or tags. Consider expanding synonym lists and normalizing common query variants.
- **Pattern/Product Disambiguation:** Ensure all patterns, catalogs, and products have "pattern", "catalog", and related terms in their tags or search blob.
- **Metadata Completeness:** Fill in missing categories, tags, and attributes for all indexed items, especially older content.
- **Re-indexing:** Always re-index after any metadata or settings change.

---

**For further tuning:**
- Experiment with the out-of-stock penalty and synonym expansion.
- Review and update metadata for all key products and patterns.
- Rerun the test suite and regenerate this report after each major change. 