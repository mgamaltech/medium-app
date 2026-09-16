<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseHardeningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function database_rejects_article_insertion_without_cover_image(): void
    {
        $this->expectException(QueryException::class);

        DB::table('articles')->insert([
            'user_id' => User::factory()->create()->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'content' => 'Sample content',
            'cover_image' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function deleting_a_user_cascades_and_deletes_their_subscriptions(): void
    {
        $user = User::factory()->create();

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_test123',
            'stripe_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->delete();

        $this->assertDatabaseMissing('subscriptions', [
            'id' => $subscriptionId,
        ]);
    }

    #[Test]
    public function deleting_a_subscription_cascades_and_deletes_its_subscription_items(): void
    {
        $user = User::factory()->create();

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_test123',
            'stripe_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('subscription_items')->insertGetId([
            'subscription_id' => $subscriptionId,
            'stripe_id' => 'si_test123',
            'stripe_product' => 'prod_test',
            'stripe_price' => 'price_test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriptions')->where('id', $subscriptionId)->delete();

        $this->assertDatabaseMissing('subscription_items', [
            'id' => $itemId,
        ]);
    }
}
