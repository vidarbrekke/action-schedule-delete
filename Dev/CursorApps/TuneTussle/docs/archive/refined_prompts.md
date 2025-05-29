# Refined Prompt Patterns

## Core Prompting Techniques
- **Conciseness & Clarity:** Favor minimal, self-documenting code; reduce lines without sacrificing readability or maintainability.  
- **Senior Developer Mindset:** Act as a 10x engineer—prioritize best practices, robust architecture, and debt-free solutions.  
- **Completion Guarantee:** Persist until a fully working, tested solution is delivered; do not stop mid-task.

## Error Diagnosis & Resolution
### Difficult Error Workflow
1. **Analyze (4 paragraphs):** Explore potential root causes; question assumptions.  
2. **Propose (4 fixes):** Outline distinct fixes, referencing prior attempts.  
3. **Select & Justify:** Choose the optimal fix based on impact and simplicity.  
4. **Review & Optimize:** Perform a final code review for bugs, edge cases, and performance; deliver runnable code.

## Chat Handoff Summary
- **Goal:** Brief a new developer on project status in 2 short & concise  paragraphs covering:  
  1. Completed work and outcomes  
  2. Failures, open issues, and lessons learned  
  3. Files changed, if that matters for future development, key insights, and “gotchas” to avoid  
  4. Key files and directories
- **Tone:** Technical README style—fact-only, no speculation or fluff.

## Solution Evaluation & Implementation
1. **Evaluate 4 Strategies:** Against complexity, **DRY**, **YAGNI**, and scalability.  
2. **Describe Each:** One paragraph per strategy, detailing trade-offs.  
3. **Compare & Choose:** Summarize side-by-side; pick best overall.  
4. **Implement:** Provide runnable, concise code aligned with chosen approach.
5. **Note:** Don't fix what is not broken


## Ongoing Code Review

Let us take a step back and look at the latest code changes as a whole:
- **Objectives:** Spot logic flaws, edge cases, performance issues, technical debt, and style inconsistencies.  
- **Refactoring Options:** Propose 4 strategies, each with cognitive, performance, DRY, YAGNI, and scalability analysis.  
- **Recommendation & Fix:** Compare, select, and apply the best refactoring in code.
- **IMPORTANT:** Don't over-engineer, and don't fix what is not broken

## Bug Fix Procedure

Let's try to get rid of these bugs:
1. **Review Attempts:** Examine and avoid repeating prior fixes.  
2. **Diagnose (4 causes):** Link each to specific symptoms or code.  
3. **Propose Fixes:** One concise fix per cause, enforcing DRY/YAGNI.  
4. **Select & Implement:** Choose most plausible, update code.  
5. **Verify:** Describe tests to confirm resolution and avoid regressions.

## Implementing Suggestions
- Apply requested changes; verify code correctness through three internal checks.  
- Deliver optimal code with minimal lines and clear structure.

## Learning from Mistakes
- **Trace & Document:** Identify past errors, corrections, and insights.  
- **Consolidate:** Update `@tests.mdc` with lessons to prevent repeat mistakes.

## TypeScript Project Audit
- **Review Area:** Type safety, syntax/style, modularity, architecture, performance.  
- **Deliverables:**  
  1. **Findings:** Gaps and inconsistencies per module.  
  2. **Code Fixes:** `diff` or snippet updates with rationale.  
  3. **Action Plan:** Prioritized list of improvements.  
  4. **Tooling Suggestions:** ESLint/Prettier or extensions.


**From Feature Description → User Stories**

Prompt:

“Given this feature brief: <your description>, generate 3–5 user stories with clear acceptance criteria (Gherkin if possible).”

When to use: before breaking work into tickets.


**Architecture Sketch & Trade-offs**

Prompt:

“Outline a high-level architecture for <project/feature>. Include component diagram, data flow, and at least two technology-stack options. Analyze pros/cons of each.”

When to use: at design kickoff or major scope changes.


**Performance Profiling & Optimization**

Prompt:

“Suggest profiling tools/methods for <language/stack> and identify 3 hotspots to watch. Recommend targeted optimizations and benchmarks to validate.”

When to use: during load tests or if latency/memory is too high.


**Automated Test Generation**

Prompt:

“Generate unit tests (Jest/Mocha/PHPUnit/PyTest) for <module/function>. Cover normal, edge, and error cases. Ensure ≥80% coverage.”

When to use: right after implementing business logic.

**CI/CD Pipeline Configuration**

Prompt:

“Provide a CI/CD config (.github/workflows/ci.yml, GitLab CI, CircleCI, etc.) that runs lint, tests, build, and deploy for <stack>. Include rollout and rollback steps.”

When to use: when automating build/test/deploy.


**Monitoring, Logging & Alerts**

Prompt:

“List key metrics (throughput, error rate, latency) and propose alert thresholds. Suggest logging structure and dashboards (Grafana/Datadog).”

When to use: before or right after deployment.


**leaning up tests**
i want to you to review all tests, one by one, and as your are analyzing each test, look at all the other tests to see if there is some redundancies and opporunities for consolidation. If needed, consolidate tests for DRY and YAGNI.

**adding tests**
Make sure the new functionality have the approriate tests. Check first if the functionality is fully or partially covered by another test. Consolidate tests for DRY and YAGNI.