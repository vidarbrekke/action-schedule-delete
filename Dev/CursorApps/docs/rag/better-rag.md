# Project Handoff Summary

The recent development focused on improving the optimizer for RAG (Retrieval-Augmented Generation) parameter tuning in the WP Customer AI Chatbot plugin. We identified that the existing optimizer, which uses random/grid search, often produced undesirable parameter sets (e.g., high variation product weight), leading to suboptimal retrieval results. To address this, we explored and documented a range of concrete strategies for smarter optimization, including parameter constraints, advanced search algorithms, improved test suite design, per-query optimization, parameter locking, multi-objective scoring, and human-in-the-loop review. The goal was to create a technical roadmap for robust, domain-aware, and efficient parameter tuning.

Attempts to improve optimizer results by simply increasing the number of iterations (e.g., from 100 to 10,000) did not yield consistently better outcomes, due to the large search space and lack of domain constraints. The optimizer continued to select parameter sets that were known to be problematic, such as those favoring product variations over parent products. This highlighted the need for explicit constraints, better test coverage, and more intelligent search strategies rather than brute-force iteration increases.

The main files updated or created in this process include `settings-tweaks.md` (updated to reflect the removal of obsolete parameters and clarify the use of `relative_score_threshold`), `rag.md` (updated to document the current RAG process and filtering logic), and `better-rag.md` (new, containing a dense technical outline of all recommended optimization strategies and approaches). These documents serve as the primary reference for the current state of RAG optimization and should be reviewed before making further changes.

Key mistakes to avoid include: relying solely on random or grid search without constraints, failing to update the test suite to cover real-world pain points, and ignoring the need for parameter locking or weighting. It is also important not to assume that more iterations will solve optimization issues if the search space is unconstrained or the scoring function is not robust. Always log parameter sets and test results for offline analysis, and use domain knowledge to guide both test design and optimization boundaries.

Current challenges include the need for a more intelligent optimizer (potentially Bayesian or genetic), a more comprehensive and weighted test suite, and mechanisms for per-query analysis and parameter synthesis. The project is now at a point where the technical roadmap is clear, the documentation is up to date, and the next steps involve implementing the outlined strategies in `better-rag.md`. All future work should reference this file and the updated documentation to ensure continuity and avoid repeating past mistakes.

**Schema and Migration Note:**
- The index table (`wp_wcac_index`) and activator have been updated to include new fields: `parent_categories`, `parent_category_names`, `attributes_text`, `taxonomies`, and `menu_titles`.
- The plugin activator ensures all required columns are present on fresh install. For existing installs, a database migration (ALTER TABLE) is required to add these columns. See troubleshooting below.

## Troubleshooting
- If you see errors like `Unknown column 'parent_categories' in 'field list'`, your database schema is out of date. Run the provided ALTER TABLE statement or re-activate the plugin to update the schema.

# Better RAG Optimization: Ideas and Approaches

This document outlines concrete, actionable strategies for improving Retrieval-Augmented Generation (RAG) parameter optimization in the WP Customer AI Chatbot plugin. Use this as a technical reference and discussion starter for future development.

---

## 1. Parameter Constraints in Optimizer
- Restrict parameter ranges to prevent known-bad values (e.g., set an upper bound for `variation_product_weight`).
- Implement hard constraints (forbid values) or soft constraints (penalize scores for undesirable values).
- Constraints can be applied during parameter sampling or as a penalty in the scoring function.

## 2. Smarter Search Algorithms
- Replace or supplement random/grid search with more efficient algorithms:
  - **Bayesian Optimization:** Models the score surface and samples promising regions.
  - **Genetic Algorithms:** Evolves parameter sets over generations, favoring high performers.
  - **Local Search:** Perturbs the best-known parameter set to explore its neighborhood.
- Hybrid approaches can combine domain-informed defaults with local or guided search.

## 3. Test Suite Design
- Expand test cases to cover edge cases and known pain points (e.g., queries where only parent products should be returned).
- Balance the suite with both "easy" and "hard" queries.
- Optionally, assign weights to test cases to reflect their importance.
- Ensure the test suite penalizes undesirable behaviors (e.g., high variation weight returning variations instead of parents).

## 4. Per-Query Optimization and Synthesis
- For each test query, optimize parameters individually to find the best set for that query.
- Track the best parameter set for each query.
- Analyze parameter commonalities across queries to identify strong candidates.
- Use these strong candidates as fixed values in a second-stage global optimization.
- This approach reveals if some queries are fundamentally at odds and informs parameter locking or weighting.

## 5. Parameter Locking and Guided Optimization
- Allow certain parameters to be "locked" (fixed) during optimization, based on prior knowledge or per-query analysis.
- Optimize remaining parameters for best combined results.
- Supports a balance between global and local optima.

## 6. Multi-Objective and Weighted Optimization
- Optimize for multiple objectives (e.g., accuracy, parent preference, diversity) rather than a single score.
- Implement weighted scoring to prioritize critical behaviors or test cases.

## 7. Human-in-the-Loop and Post-Processing
- Present top parameter sets to a human for review and selection.
- Allow manual feedback to guide further optimization runs.
- Filter out parameter sets that violate domain knowledge, even if they score well.

## 8. Iteration Count and Search Space
- Increasing the number of iterations (e.g., from 100 to 10,000) increases the chance of finding better parameter sets but has diminishing returns if the search space is large or unconstrained.
- Efficient search and constraints are more effective than brute-force iteration increases.

## 9. Logging and Analysis
- Log all parameter sets, scores, and test case results for offline analysis.
- Use logs to visualize parameter effects and identify patterns in successful configurations.

---

This document is intended as a technical roadmap for improving RAG parameter optimization. Each section can be explored and implemented independently or in combination, depending on project priorities and available resources. 