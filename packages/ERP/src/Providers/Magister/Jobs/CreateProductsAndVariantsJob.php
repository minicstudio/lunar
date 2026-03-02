<?php

namespace Lunar\ERP\Providers\Magister\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\ERP\Models\ErpSyncTemp;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

class CreateProductsAndVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The article to process.
     */
    public ErpSyncTemp $article;

    /**
     * The related variants.
     */
    public Collection $relatedSmartcashVariants;

    /**
     * Create a new job instance.
     */
    public function __construct(ErpSyncTemp $article, Collection $relatedSmartcashVariants)
    {
        $this->article = $article;
        $this->relatedSmartcashVariants = $relatedSmartcashVariants;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // decide what to do based on the article kind
        switch ($this->article->provider_data['article_kind'] ?? 0) {
            case 0:
                $this->updateOrCreateStandardProduct();
                break;
            case 1:
                $this->updateOrCreateGenericProduct();
                break;
            case 2:
                // variants are handled at the generic product handling
            default:
                break;
        }

        // Clean up temp table entries after processing
        $this->cleanupTempTableEntries();
    }

    /**
     * Update or create a standard product.
     */
    private function updateOrCreateStandardProduct(): void
    {
        $productVariant = ProductVariant::where('sku', $this->article->sku)->first();
        $currency = Currency::firstWhere('code', 'RON');

        if ($productVariant) {
            $this->updateProduct($productVariant->product_id, $this->article);
            $this->updateVariant($productVariant, $this->article, $currency);

            return;
        }

        $product = $this->createDraftProduct($this->article);
        $this->createVariant($product, $this->article, $currency);
    }

    /**
     * Update or create a generic product and variants.
     */
    private function updateOrCreateGenericProduct(): void
    {
        // get all the related variants for the generic article
        if ($this->relatedSmartcashVariants->isEmpty()) {
            // no related variants, nothing to do
            return;
        }

        // create or update the main product for the generic article
        // check if at least one variant exists
        $skus = $this->relatedSmartcashVariants->pluck('sku');
        $existingVariant = ProductVariant::whereIn('sku', $skus)->first();

        $product = $existingVariant
                    ? $this->updateProduct($existingVariant->product_id, $this->article)
                    : $this->createDraftProduct($this->article);

        $currency = Currency::firstWhere('code', 'RON');

        $existingVariants = ProductVariant::whereIn('sku', $skus)->get()->keyBy('sku');

        foreach ($this->relatedSmartcashVariants as $variant) {
            if ($existing = $existingVariants->get($variant->sku)) {
                $this->updateVariant($existing, $variant, $currency);
            } else {
                $this->createVariant($product, $variant, $currency);
            }
        }
    }

    /**
     * Create a draft product.
     */
    private function createDraftProduct(ErpSyncTemp $article): Product
    {
        $productType = ProductType::firstWhere('name', 'Stock');
        if (! $productType) {
            throw new \Exception('Product type "Stock" not found');
        }

        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'draft',
            'attribute_data' => [
                'name' => new TranslatedText(collect([
                    'ro' => new Text($article->name),
                ])),
            ],
        ]);

        // set the product options for generic articles
        if ($article->provider_data['article_kind'] === 1) {
            $this->attachProductOptions($product, $article);
        }

        return $product;
    }

    /**
     * Update existing product.
     */
    private function updateProduct(int $productId, ErpSyncTemp $article): Product
    {
        $product = Product::find($productId);

        // set the product options for generic articles
        if ($article->provider_data['article_kind'] === 1) {
            $this->attachProductOptions($product, $article);
        }

        return $product;
    }

    /**
     * Attach product options to the product based on the article attributes.
     */
    private function attachProductOptions(Product $product, ErpSyncTemp $article): void
    {
        $productAttributes = array_map(fn ($attr) => Str::slug($attr), array_column($article->attributes, 'NAME_TERM'));
        $productOptions = ProductOption::whereIn('handle', $productAttributes)->get();

        foreach ($productOptions as $index => $productOption) {
            if ($product->productOptions()->where('product_option_id', $productOption->id)->exists()) {
                continue; // skip if already attached
            }

            $product->productOptions()->attach($productOption, ['position' => $index + 1]);
        }
    }

    /**
     * Create a new variant for the product.
     */
    private function createVariant(Product $product, ErpSyncTemp $article, Currency $currency): void
    {
        $productVariant = ProductVariant::create([
            'product_id' => $product->id,
            'erp_id' => $article->erp_id,
            'sku' => $article->sku,
            // if the sku length is more then 10 characters, then it is an ean
            // AWG for example uses inner ean codes (that are only ~4 chars long), thats why we check the length
            'ean' => strlen($article->sku) > 10 ? $article->sku : null,
            'tax_class_id' => TaxClass::first()->id,
        ]);

        $productVariant->prices()->create([
            'min_quantity' => 1,
            'currency_id' => $currency->id,
            'price' => $article->price,
        ]);

        // Set stock from temp table
        $productVariant->stock = $article->stock;
        $productVariant->save();

        // set the product option values for variant articles
        if ($article->provider_data['article_kind'] === 2) {
            $this->attachProductOptionValues($productVariant, $article);
        }
    }

    /**
     * Update existing variant.
     */
    private function updateVariant(ProductVariant $productVariant, ErpSyncTemp $article, Currency $currency): void
    {
        // check if the variant price has changed, if so delete the old price and create a new one
        $price = $productVariant->prices()->first();
        if ($price && $price->price->value !== $article->price) {
            $price->delete();

            $productVariant->prices()->create([
                'min_quantity' => 1,
                'currency_id' => $currency->id,
                'price' => $article->price,
            ]);
        }

        // Update stock from temp table
        $productVariant->stock = $article->stock;
        $productVariant->save();

        // set the product option values for variant articles
        if ($article->provider_data['article_kind'] === 2) {
            $this->attachProductOptionValues($productVariant, $article);
        }
    }

    /**
     * Attach product option values to the product variant based on the article attributes.
     */
    private function attachProductOptionValues(ProductVariant $productVariant, ErpSyncTemp $article): void
    {
        $productVariantAttributes = array_column($article->attributes, 'NAME_TERM');
        $productOptions = $productVariant->product->productOptions()->get()->pluck('id');
        // get the product option value that is related to the product variant and the attribute name is in the product variant attributes
        $productOptionValues = ProductOptionValue::whereIn('product_option_id', $productOptions)->whereIn('name->ro', $productVariantAttributes)->get();

        foreach ($productOptionValues as $value) {
            // first check if the value is already attached to the variant
            if ($productVariant->values()->where('value_id', $value->id)->exists()) {
                continue;
            }

            $productVariant->values()->attach($value);
        }
    }

    /**
     * Clean up temporary table entries after processing.
     */
    private function cleanupTempTableEntries(): void
    {
        // Delete the main article from the temp table
        ErpSyncTemp::where('id', $this->article->id)->delete();

        // Delete the related variants too
        if (isset($this->article->provider_data['article_kind']) && $this->article->provider_data['article_kind'] === 1) {
            $erpId = is_numeric($this->article->erp_id) ? (int) $this->article->erp_id : $this->article->erp_id;
            ErpSyncTemp::where('provider_data->generic_article_id', $erpId)
                ->where('stock', '>', 0)
                ->delete();
        } else {
            // For standard articles, delete any related variants
            $erpId = is_numeric($this->article->erp_id) ? (int) $this->article->erp_id : $this->article->erp_id;
            ErpSyncTemp::where('provider_data->generic_article_id', $erpId)->delete();
        }
    }
}
