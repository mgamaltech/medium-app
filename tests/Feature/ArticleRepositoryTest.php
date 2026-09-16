<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Events\ArticlePublished;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Like;
use App\Models\TrendingArticle;
use App\Models\User;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected ArticleRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(ArticleRepositoryInterface::class);
    }

    #[Test]
    public function can_create_an_article()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);
        $data = [
            'title' => 'Test Article',
            'body' => 'This is a test body',
            'status' => ArticleStatus::PUBLISHED,
            'cover_image' => 'path/to/image.jpg',
            'user_id' => $user->id,
        ];

        $article = $this->repository->create($data);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertEquals('Test Article', $article->title);
        $this->assertDatabaseHas('articles', [
            'title' => 'Test Article',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function can_find_an_article_by_id()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $article = Article::factory()->create(['user_id' => $user->id]);

        $foundArticle = $this->repository->findById($article->id);

        $this->assertInstanceOf(Article::class, $foundArticle);
        $this->assertEquals($article->id, $foundArticle->id);
    }

    #[Test]
    public function can_find_all_articles()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Article::factory()->create(['user_id' => $user->id]);
        Article::factory()->create(['user_id' => $user->id]);
        $articles = $this->repository->all();
        $this->assertCount(2, $articles);
    }

    #[Test]
    public function can_find_articles_by_author()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $article = Article::factory()->create(['user_id' => $user->id]);
        $articles = $this->repository->getByAuthor($user->id);

        $this->assertCount(1, $articles);
        $this->assertEquals($article->id, $articles->first()->id);
    }

    #[Test]
    public function returns_only_published_articles()
    {

        Article::factory()->create(['status' => ArticleStatus::PUBLISHED]);
        Article::factory()->create(['status' => ArticleStatus::DRAFT]);

        $results = $this->repository->getPublished();

        $this->assertCount(1, $results);
        $this->assertEquals(ArticleStatus::PUBLISHED, $results->first()->status);
    }

    #[Test]
    public function dispatches_article_published_event()
    {
        Event::fake();

        $user = User::factory()->create();

        $data = [
            'title' => 'Test Article',
            'body' => 'This is a test body',
            'status' => ArticleStatus::PUBLISHED,
            'cover_image' => 'path/to/image.jpg',
            'user_id' => $user->id,
        ];

        $this->repository->create($data);

        Event::assertDispatched(ArticlePublished::class);
    }

    #[Test]
    public function not_dispatch_fails_event()
    {
        Event::fake();

        $user = User::factory()->create();

        $data = [
            'title' => 'Test Article',
            'body' => 'This is a test body',
            'status' => ArticleStatus::DRAFT,
            'user_id' => $user->id,
        ];

        DB::beginTransaction();

        try {
            $this->repository->create($data);

            throw new \Exception('Database crash');
        } catch (\Exception $e) {
            DB::rollBack();
        }

        Event::assertNotDispatched(ArticlePublished::class);
    }

    #[Test]
    public function can_get_trending_articles_ordered_by_score()
    {
        $articleA = Article::factory()->create();
        $articleB = Article::factory()->create();

        TrendingArticle::factory()->create([
            'article_id' => $articleA->id,
            'trending_score' => 10,
        ]);
        TrendingArticle::factory()->create([
            'article_id' => $articleB->id,
            'trending_score' => 50,
        ]);

        $trending = $this->repository->getTrending();

        $this->assertCount(2, $trending);
        $this->assertEquals($articleB->id, $trending->first()->id);
    }

    #[Test]
    public function can_update_an_article()
    {
        $user = User::factory()->create();
        $article = Article::factory()->create([
            'user_id' => $user->id,
            'title' => 'Old Title',
        ]);

        $updated = $this->repository->update($article->id, ['title' => 'New Title']);

        $this->assertEquals('New Title', $updated->title);
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'title' => 'New Title',
        ]);
    }

    #[Test]
    public function update_throws_exception_when_article_not_found()
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->update(9999, ['title' => 'Anything']);
    }

    #[Test]
    public function prunes_stale_draft_articles_and_calls_callback_with_count()
    {
        $staleDate = now()->subDays(30);

        $this->travelTo(now()->subDays(35));
        $staleDraft1 = Article::factory()->create(['status' => ArticleStatus::DRAFT]);

        $this->travelTo(now()->subDays(40));
        $staleDraft2 = Article::factory()->create(['status' => ArticleStatus::DRAFT]);

        $this->travelBack();

        $freshDraft = Article::factory()->create([
            'status' => ArticleStatus::DRAFT,
        ]);

        $this->travelTo(now()->subDays(40));
        $publishedOld = Article::factory()->create(['status' => ArticleStatus::PUBLISHED]);

        $this->travelBack();

        $prunedTotal = 0;

        $this->repository->pruneStaleDrafts($staleDate, 10, function ($count) use (&$prunedTotal) {
            $prunedTotal += $count;
        });

        $this->assertEquals(2, $prunedTotal);
        $this->assertSoftDeleted('articles', ['id' => $staleDraft1->id]);
        $this->assertSoftDeleted('articles', ['id' => $staleDraft2->id]);
        $this->assertDatabaseHas('articles', ['id' => $freshDraft->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('articles', ['id' => $publishedOld->id, 'deleted_at' => null]);
    }

    #[Test]
    public function prune_stale_drafts_processes_in_chunks()
    {
        $staleDate = now()->subDays(30);

        $this->travelTo(now()->subDays(40));
        $drafts = Article::factory()->count(5)->create(['status' => ArticleStatus::DRAFT]);
        $this->travelBack();

        $callCount = 0;

        $this->repository->pruneStaleDrafts($staleDate, 2, function () use (&$callCount) {
            $callCount++;
        });

        $this->assertEquals(3, $callCount);
    }

    #[Test]
    public function calculates_and_stores_trending_articles_based_on_recent_activity()
    {
        $author = User::factory()->create();
        $article = Article::factory()->create([
            'user_id' => $author->id,
            'status' => ArticleStatus::PUBLISHED,
        ]);

        // ⚠️ عدّل الموديلات/العلاقات دي حسب اللي عندك فعليًا
        Like::factory()->count(3)->create([
            'article_id' => $article->id,
            'created_at' => now()->subDays(2),
        ]);

        Comment::factory()->count(2)->create([
            'article_id' => $article->id,
            'created_at' => now()->subDays(1),
        ]);

        $follower = User::factory()->create(['created_at' => now()->subDays(3)]);
        $author->followers()->attach($follower->id);

        $this->repository->calculateTrendingArticles();

        // score = (3 likes * 2) + (2 comments * 4) + (1 follower * 2) = 16
        $this->assertDatabaseHas('trending_articles', [
            'article_id' => $article->id,
            'trending_score' => 16,
        ]);
    }

    #[Test]
    public function calculate_trending_articles_ignores_articles_with_zero_score()
    {
        Article::factory()->create(['status' => ArticleStatus::PUBLISHED]);

        $this->repository->calculateTrendingArticles();

        $this->assertDatabaseCount('trending_articles', 0);
    }

    #[Test]
    public function calculate_trending_articles_respects_limit()
    {
        foreach (range(1, 3) as $i) {
            $article = Article::factory()->create(['status' => ArticleStatus::PUBLISHED]);

            Like::factory()->count($i * 5)->create([
                'article_id' => $article->id,
                'created_at' => now()->subDays(1),
            ]);
        }

        $this->repository->calculateTrendingArticles(limit: 2);

        $this->assertDatabaseCount('trending_articles', 2);
    }
}
