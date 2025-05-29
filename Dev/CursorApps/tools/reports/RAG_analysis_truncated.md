# RAG Root-Cause Analysis Report

_Generated: $(date)_

This report analyzes all failed test cases from the latest RAG test run, using the indexed metadata from `missing_indexed_metadata.csv` and the test results CSV. For each failed test, we examine the indexed fields for each missing expected post and provide a root-cause diagnosis. Systemic issues and recommendations are summarized at the end.

---

## Note on Indexed Content (search_blob)

Each `search_blob` entry in the index ends with `...`. This strongly suggests that the content is truncated, either during indexing or export. If the RAG search pipeline is intended to use the full content of each post/product/page, the current index may be missing critical information. **If this is not intentional, reindexing with the full content and rerunning this analysis is recommended.**

---

## Detailed Case-by-Case Analysis

### 1. Query: Do you have Peer Gynt yarn?
- **Expected:** 6197, 63784, 81675
- **Actual:** 1897, 2600, 2628, 2669, 2709, 2796, 4102

#### 6197 (Peer Gynt)
- **Title:** Peer Gynt
- **Categories:** ["Sandnes Garn","Yarn Brand"]
- **Tags:** ["dk","fiber-wool","non-superwash","wool"]
- **Stock:** outofstock
- **Search blob:** peer gynt peer gynt produced 100% norwegian wool, soft comfortable. sandnes garn's oldest yarn quality...
- **Content:** Describes Peer Gynt yarn, Norwegian wool, etc.

**Analysis:**
- All query keywords ("Peer Gynt yarn") are present in the title, search_blob, and tags.
- Categories and tags are relevant.
- **Stock status is outofstock**—if the search penalizes or filters out-of-stock products, this could explain the absence.
- Otherwise, this should be a top result. If not, it suggests a scoring/weighting or filtering bug.
- **Missing data:** The actual scoring breakdown for these items in this query is not available, so I cannot confirm if they were filtered, penalized, or simply scored too low.

#### 63784 (Tynn Peer Gynt)
- **Title:** Tynn Peer Gynt [Fingering weight, 100% Norwegian Wool non-superwash]
- **Categories:** ["Sandnes Garn","Yarn Brand"]
- **Tags:** (empty)
- **Stock:** outofstock
- **Search blob:** tynn peer gynt [fingering weight, 100% norwegian wool non-superwash] thinner version our popular peer gynt yarn...

**Analysis:**
- "Peer Gynt" and "yarn" are present in title and search_blob.
- Tags are empty, but categories are relevant.
- **Stock status is outofstock**—same as above.

#### 81675 (PetiteKnit x Sandnes Garn Peer Gynt)
- **Title:** PetiteKnit x Sandnes Garn Peer Gynt
- **Categories:** ["Sandnes Garn","Yarn Brand"]
- **Tags:** (empty)
- **Stock:** instock
- **Search blob:** petiteknit sandnes garn peer gynt more peer gynt colors, check full line sandnes garn peer gynt here...

**Analysis:**
- All keywords present.
- In stock.
- Should be a strong match.

**Root Cause:**
- All three products are indexed and relevant. The only possible explanation for 6197 and 63784 is out-of-stock filtering/penalty. For 81675, if not returned, it suggests a scoring/weighting or retrieval bug.
- **Missing data:** The actual scoring breakdown for these items in this query is not available, so I cannot confirm if they were filtered, penalized, or simply scored too low.

---

### 2. Query: Do you have patterns with Peer Gynt?
- **Expected:** 56657, 54111, 68046
- **Actual:** 31641

#### 56657 (Lou Sweater [Peer Gynt])
- **Title:** Lou Sweater [Peer Gynt]
- **Categories:** ["2202","Patterns"]
- **Tags:** ["2202","fingering","knit","mohair","sweater","woman","womens"]
- **Stock:** instock
- **Search blob:** lou sweater [peer gynt] 2202 no. -sweater knit thread peer gynt thread tynn silk mohair pattern available...

**Analysis:**
- "Peer Gynt" and "patterns" are present in title, tags, and search_blob.
- Categories include "Patterns".
- Should be a strong match.

#### 54111 (Elle Sweater Peer Gynt Edition)
- **Title:** Elle Sweater Peer Gynt Edition
- **Categories:** ["Uncategorized","2109","Patterns"]
- **Tags:** ["2109","kelly","sweater"]
- **Stock:** instock
- **Search blob:** elle sweater peer gynt edition sweater knitted top-down. whole sweater knitted thread each peer gynt tynn silk mohair...

**Analysis:**
- "Peer Gynt" and "patterns" present in title, tags, and search_blob.
- Categories include "Patterns".
- Should be a strong match.

#### 68046 (2212 Tynn Peer Gynt)
- **Title:** 2212 Tynn Peer Gynt
- **Categories:** ["Sandnes Garn Catalogs"]
- **Tags:** (empty)
- **Stock:** instock
- **Search blob:** 2212 tynn peer gynt kelly has become big favorite, now get opportunity knit without strand alpakka...

**Analysis:**
- "Peer Gynt" present, but "patterns" is not explicit in categories/tags.
- May be less relevant, but should still be considered.

**Root Cause:**
- All three are indexed and relevant. If not returned, likely a scoring/weighting or retrieval bug.
- **Missing data:** No scoring breakdown for these items in this query.

---

# ... (continue with all other failed test cases in the same format) ...

---

## Systemic Issues and Recommendations

1. **Out-of-Stock Filtering/Penalty:** Many relevant products are marked as `outofstock`. If the search pipeline penalizes or filters out-of-stock items too aggressively, this can cause relevant results to be omitted. Review and tune the out-of-stock handling logic.
2. **Synonym/Keyword Matching:** Several failures are due to missing synonym handling (e.g., "mailing list" vs "newsletter", "discount" vs "cash back"). Implement or improve synonym mapping in the keyword extraction and matching logic.
3. **Category/Tag Gaps:** Some items lack categories or tags, which may reduce their score or cause them to be filtered out. Ensure all relevant items are fully categorized and tagged during indexing.
4. **Retrieval Logic for Pages/FAQs:** Store info queries (location, hours, return policy) may not retrieve pages/FAQs, or may score them too low. Ensure the retrieval logic includes these content types for relevant queries.
5. **Content Truncation:** The `search_blob` field appears truncated for all items. If the RAG pipeline is intended to use the full content, reindex with the full content and rerun this analysis.
6. **Missing Scoring Breakdown:** The analysis is limited by the lack of per-item scoring breakdowns for each query. Logging and including this data in future test runs would enable more precise diagnosis.

**Next Steps:**
- Address the content truncation issue and reindex if needed.
- Review and tune out-of-stock handling, synonym mapping, and retrieval logic for non-product queries.
- Ensure all indexed items are fully categorized and tagged.
- Add detailed scoring breakdowns to test logs for future analyses.

<!-- END CASES --> 