<?php

namespace App\Console\Commands;

use Illuminate\Auth\Events\Failed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Cosmetic;
use App\Models\CosmeticImage;

use function PHPUnit\Framework\isEmpty;

class ImportFortniteCosmetics extends Command
{
    protected $signature = 'app:import-fortnite-cosmetics';

    protected $description = 'Command description';

    public function handle()
    {
        $response = Http::withHeaders([
            'Authorization' => config('services.fortnite.api_key'),
        ])->get('https://fortniteapi.io/v2/items/list', [
            'lang' => 'en',
        ]);

        if ($response->failed()) {
            $this->error('Failed to fetch cosmetics from Fortnite API.');
            return Command::FAILURE;
        }

        $items = $response->json()['items'];

        foreach ($items as $item) {

            if (
                empty($item['added']['date']) ||
                $item['added']['date'] < '2020-01-01' ||
                empty($item['price']) ||
                $item['price'] <= 0
            ) {
                continue;
            }

            $cosmetic = Cosmetic::updateOrCreate(
                ['cosmetic_id' => $item['id']],
                [
                    'type_id' => $item['type']['id'] ?? null,
                    'type_name' => $item['type']['name'] ?? null,
                    'name' => $item['name'] ?? null,
                    'description' => $item['description'] ?? null,
                    'rarity_id' => $item['rarity']['id'] ?? null,
                    'rarity_name' => $item['rarity']['name'] ?? null,
                    'series' => is_array($item['series'] ?? null) ? json_encode($item['series']) : ($item['series'] ?? null),
                    'price' => $item['price'] ?? null,
                    'added_date' => $item['added']['date'] ?? null,
                    'added_version' => $item['added']['version'] ?? null,
                    'copyrighted_audio' => $item['copyrightedAudio'] ?? false,
                    'upcoming' => $item['upcoming'] ?? false,
                    'reactive' => $item['reactive'] ?? false,
                    'release_date' => $item['releaseDate'] ?? null,
                    'last_appearance' => $item['lastAppearance'] ?? null,
                    'interest' => $item['interest'] ?? null,
                    'path' => $item['path'] ?? null,
                    'gameplay_tags' => json_encode($item['gameplayTags'] ?? []),
                    'api_tags' => json_encode($item['apiTags'] ?? []),
                    'battlepass' => json_encode($item['battlepass'] ?? []),
                    'set' => json_encode($item['set'] ?? []),
                ]
            );

            if ($cosmetic && isset($item['images'])) {
                foreach ($item['images'] as $type => $url) {
                    if ($url) {
                        CosmeticImage::updateOrCreate(
                            [
                                'cosmetic_id' => $cosmetic->id,
                                'type' => $type,
                            ],
                            [
                                'url' => $url,
                            ]
                        );
                    }
                }
            }
        }
    }
}