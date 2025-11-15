<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Cosmetic;

class PopulateBundleCosmetics extends Command
{
    protected $signature = 'bundle:populate {--fresh}';

    protected $description = 'Popula a tabela bundle_cosmetics relacionando itens aos bundles por tema';

    public function handle()
    {
        $this->info('Iniciando população de bundle_cosmetics...');

        if ($this->option('fresh')) {
            $this->info('Limpando tabela bundle_cosmetics...');
            DB::table('bundle_cosmetics')->truncate();
            $this->info('Tabela bundle_cosmetics limpa!');
        }

        $bundles = Cosmetic::where('type_name', 'Item Bundle')
            ->where('type_id', 'bundle')
            ->get();

        if ($bundles->isEmpty()) {
            $this->warn('Nenhum bundle encontrado.');
            return Command::FAILURE;
        }

        $totalLinked = 0;
        $totalSkipped = 0;

        $existingCosmeticIds = DB::table('bundle_cosmetics')
            ->pluck('cosmetic_id')
            ->toArray();

        foreach ($bundles as $bundle) {
            $bundlePrefix = $this->extractBundlePrefix($bundle->cosmetic_id);

            if (!$bundlePrefix) {
                $this->warn("Bundle '{$bundle->name}' não possui prefixo válido. Pulando...");
                $totalSkipped++;
                continue;
            }

            $cosmetics = Cosmetic::where(function ($query) use ($bundlePrefix) {
                $query->where('cosmetic_id', 'LIKE', '%_' . $bundlePrefix)
                    ->orWhere('cosmetic_id', 'LIKE', $bundlePrefix . '_%');
            })
                ->where('cosmetic_id', '!=', $bundle->cosmetic_id)
                ->where('type_name', '!=', 'Item Bundle')
                ->where('type_id', '!=', 'bundle')
                ->whereNotNull('price')
                ->where('price', '>', 0)
                ->has('images')
                ->get();

            if ($cosmetics->isEmpty() && $bundle->added_date) {
                $cosmetics = Cosmetic::where('added_date', $bundle->added_date)
                    ->where('cosmetic_id', '!=', $bundle->cosmetic_id)
                    ->where('type_name', '!=', 'Item Bundle')
                    ->where('type_id', '!=', 'bundle')
                    ->whereNotNull('price')
                    ->where('price', '>', 0)
                    ->has('images')
                    ->limit(20)
                    ->get();
            }

            $cosmeticsWithImages = $cosmetics->filter(function ($cosmetic) {
                $cosmetic->load('images');
                return $cosmetic->images && $cosmetic->images->count() > 0;
            });

            $availableCosmetics = $cosmeticsWithImages->filter(function ($cosmetic) use (&$existingCosmeticIds) {
                if (in_array($cosmetic->id, $existingCosmeticIds)) {
                    return false;
                }
                $existingCosmeticIds[] = $cosmetic->id;
                return true;
            });

            $itemCount = $availableCosmetics->count();
            
            if ($itemCount >= 3 && $itemCount <= 6) {
                $bundle->bundleItems()->sync($availableCosmetics->pluck('id')->toArray());
                $totalLinked++;
                $this->info("Bundle '{$bundle->name}' (ID: {$bundle->id}) vinculado a {$itemCount} itens.");
            } else {
                $totalSkipped++;
                if ($itemCount > 0) {
                    $this->warn("Bundle '{$bundle->name}' (ID: {$bundle->id}) tem {$itemCount} itens com imagens (mínimo: 3, máximo: 6). Não vinculado.");
                } else {
                    $this->warn("Bundle '{$bundle->name}' (ID: {$bundle->id}) não teve itens com imagens vinculados.");
                }
            }
        }

        $this->info("Processamento concluído!");
        $this->info("Bundles processados: {$bundles->count()}");
        $this->info("Bundles vinculados: {$totalLinked}");
        $this->info("Bundles sem itens: {$totalSkipped}");

        return Command::SUCCESS;
    }

    private function extractBundlePrefix($bundleId)
    {
        if (!str_ends_with($bundleId, '_Bundle')) {
            return null;
        }

        $withoutSuffix = substr($bundleId, 0, -7);
        $parts = explode('_', $withoutSuffix);

        if (count($parts) === 1) {
            return $parts[0];
        }

        return end($parts);
    }
}
