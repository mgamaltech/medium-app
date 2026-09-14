<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Article::unsetEventDispatcher();

        $batches = [
            ['count' => 10, 'status' => ArticleStatus::DRAFT->value, 'days' => 100],
            ['count' => 10, 'status' => ArticleStatus::DRAFT->value, 'days' => 30],
            ['count' => 10, 'status' => ArticleStatus::PUBLISHED->value, 'days' => 120],
            ['count' => 10, 'status' => ArticleStatus::PUBLISHED->value, 'days' => 10],
        ];

        $chunkSize = 10;

        foreach ($batches as $batch) {
            $chunks = (int) ceil($batch['count'] / $chunkSize);
            $date = now()->subDays($batch['days'])->format('Y-m-d H:i:s');

            for ($i = 0; $i < $chunks; $i++) {
                $articles = Article::factory()
                    ->count($chunkSize)
                    ->make([
                        'status' => $batch['status'],
                        'created_at' => $date,
                        'updated_at' => $date,
                    ])
                    ->toArray();

                $articles = array_map(function ($article) {
                    if (isset($article['created_at'])) {
                        $article['created_at'] = date('Y-m-d H:i:s', strtotime($article['created_at']));
                    }
                    if (isset($article['updated_at'])) {
                        $article['updated_at'] = date('Y-m-d H:i:s', strtotime($article['updated_at']));
                    }

                    return $article;
                }, $articles);

                Article::insert($articles);
            }
        }
    }
}
