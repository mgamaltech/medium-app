# Medium App Retrospective

## 1. Three Decisions That Paid Off Best

1.  **Event-Driven Architecture for Publishing (PR #8)**
    *   **The Decision:** Emitting an `ArticlePublished` domain event instead of hardcoding post-publish logic inside the controller or repository.
    *   **The Payoff:** This paid off massively during PR #15 (Async Notification Queue) and PR #20 (Read Time Estimator). We were able to plug in complex, asynchronous background jobs simply by registering new listeners to the event, leaving the core publishing logic completely untouched and pristine.
2.  **Presigned URLs for Direct-to-S3 Uploads (PR #21)**
    *   **The Decision:** Bypassing the backend server entirely for image uploads by generating presigned S3 URLs for the frontend.
    *   **The Payoff:** As noted in the `bottleneck-analysis.md`, routing files through the PHP worker was a major risk for request timeouts. This decision immediately eliminated that bottleneck and paved the way for the automated S3 cleanup job (PR #23) that handled orphaned images, keeping our storage costs and server memory usage low.
3.  **Implementing a PHPStan Baseline (PR #24)**
    *   **The Decision:** Introducing strict static analysis into the CI pipeline but generating a baseline to ignore existing legacy errors.
    *   **The Payoff:** The payoff showed up in every subsequent code review (like the Weekly Digest feature). It allowed the team to enforce strict typing (Level 8/9) on *new* code immediately without blocking feature development to clean up hundreds of historical type errors. It stopped new bugs at the gate without adding friction.

---

## 2. The Ceremony Without Payoff: Strategy Pattern

**The Pattern:** The Strategy Pattern for Content Visibility (PR #9).
We built a `ContentVisibilityStrategy` interface, a `ContentVisibilityResolver`, and four individual strategy classes (`PublicVisibilityStrategy`, `ArchivedVisibilityStrategy`, etc.) simply to encapsulate single-line boolean checks (e.g., `return $user->id === $article->user_id;`).

**Why I wouldn't build it again:** For an application that only has a few simple states (Public, Draft, Archived, Followers Only), this was textbook over-engineering. It fragmented standard authorization logic across six files, making it harder to read without providing any real functional benefit. 

**What I would do instead:** I would put this logic exactly where Laravel expects it: inside `ArticlePolicy@view`, using a simple, readable `match` statement.

**When the answer flips:** This pattern only becomes valuable at **enterprise scale**—when visibility rules require querying external microservices, validating against complex organizational ABAC/RBAC trees, or enforcing temporal access (e.g., "Available to premium subscribers only for the first 24 hours, then public"). Until you have dozens of highly dynamic rules, stick to Policies.

---

## 3. The Bug That Taught Me the Most

**The Bug:** The N+1 and Memory Leak Issue in the Article Repository (Fixed in Commit `6cd9023`).
We used `Article::all()` in the repository to fetch data for the feed.

**What it cost:** As documented in our `bottleneck-analysis.md`, as the database grew, fetching every article into memory simultaneously triggered massive memory leaks. Furthermore, because relationships weren't eager-loaded, it caused an N+1 query storm that exhausted database CPU and slowed the application to a crawl.

**The habit it created:** I now treat `::all()` as a banned method in production code. My default habit is to immediately write `paginate()` or `cursor()`, explicitly define `with()` for eager-loading relationships, and always seed a local database with 10,000+ records to visually catch missing indexes and N+1s before opening a PR.

---

## 4. If I Rebuilt the Medium App Tomorrow

### The Order I Would Build In
1.  **Core Domain & State Machine:** Users, Articles, and the strict state transitions (Draft -> Published -> Archived).
2.  **The Social Graph & Feed:** The follow system and the algorithmic feed ranking, as this is the core retention loop for a content platform.
3.  **Background Infrastructure:** Setup the async queue workers for non-blocking tasks (email digests, read-time calculations, and orphaned image cleanup).

### What Gets Tested First
**Feed Ranking & Authorization.** 
Tests like `test_it_excludes_non_published_articles_from_followed_users` are the most critical in the app. If a private draft or a restricted article leaks into the public feed, user trust is destroyed instantly. I will manually test if S3 uploads work, but domain authorization logic must be strictly TDD'd.

### What Gets Cut From the MVP Entirely
**The Booking System (PR #28 - #33).**
A Medium clone is a content publishing and reading platform. Building a complex booking module with Laravel Cashier payments, slot management, and idempotent distributed cache locks added immense bloat to the codebase. It is completely orthogonal to the core value proposition of writing articles and should be cut entirely or moved to a separate microservice if the business pivots.
