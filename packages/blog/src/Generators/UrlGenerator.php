<?php

namespace Lunar\Blog\Generators;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lunar\Models\Contracts\Language as LanguageContract;
use Lunar\Models\Language;
use Lunar\Models\Url;

class UrlGenerator
{
    /**
     * The instance of the model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * The default language.
     */
    protected LanguageContract $defaultLanguage;

    /**
     * The current language for URL generation.
     */
    protected ?LanguageContract $language = null;

    /**
     * Languages for URL generation.
     * 
     * @var \Illuminate\Database\Eloquent\Collection
     */
    protected Collection $languages;

    /**
     * Construct the class.
     */
    public function __construct()
    {
        $this->defaultLanguage = Language::getDefault();
        $this->languages = Language::all();
    }

    /**
     * Set the language for URL generation.
     *
     * @param  \Lunar\Models\Contracts\Language|null  $language
     * @return $this
     */
    public function setLanguage(?LanguageContract $language)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get the language for URL generation.
     * Returns the currently set language or falls back to default language.
     *
     * @return \Lunar\Models\Contracts\Language
     */
    protected function getLanguage(): LanguageContract
    {
        return $this->language ?? $this->defaultLanguage;
    }

    /**
     * Handle the URL generation.
     * Tries to use 'title' first, then falls back to 'name'.
     *
     * @return void
     */
    public function handle(Model $model)
    {
        $model->load('urls');

        $this->model = $model;

        if ($model->urls->count()) {
            return;
        }

        if ($model->attr('title')) {
            $this->generateUrlsForAttribute('title');

            return;
        }

        if ($model->attr('name')) {
            $this->generateUrlsForAttribute('name');

            return;
        }
    }

    /**
     * Generate URLs for a given attribute across all languages.
     *
     * @param  string  $attribute
     * @return void
     */
    protected function generateUrlsForAttribute(string $attribute)
    {
        $this->languages->each(function ($lang) use ($attribute) {
            $this->setLanguage($lang);

            $value = $this->model->translateAttribute($attribute, $lang->code);

            if ($value) {
                $this->createUrl($value);
            }
        });

        $this->setLanguage(null);
    }

    /**
     * Create default url from an attribute.
     *
     * @param  string  $value
     * @return void
     */
    protected function createUrl($value)
    {
        $uniqueSlug = $this->getUniqueSlug(Str::slug($value));

        $this->model->urls()->create([
            'default' => true,
            'language_id' => $this->getLanguage()->id,
            'slug' => $uniqueSlug,
        ]);
    }

    /**
     * Generates unique slug based on the given slug by adding suffix numbers.
     *
     * @return string
     */
    private function getUniqueSlug($slug)
    {
        $separator = '-';

        $slugs = $this->getExistingSlugs($slug, $separator);

        // it is already unique
        if (! $slugs->count() || $slugs->contains($slug) === false) {
            return $slug;
        }

        if ($slugs->has($this->model->getKey())) {
            $currentSlug = $slugs->get($this->model->getKey());

            if ($currentSlug === $slug || str_starts_with($currentSlug, $slug)) {
                return $currentSlug;
            }
        }

        $suffix = $this->getSuffix($slug, $separator, $slugs);

        return $slug.$separator.$suffix;
    }

    /**
     * Get all urls similar to the given slug.
     *
     * @param  string  $slug
     * @param  string  $separator
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getExistingSlugs($slug, $separator)
    {
        return Url::where(function ($query) use ($slug, $separator) {
            $query->where('slug', $slug)
                ->orWhere('slug', 'like', $slug.$separator.'%');
        })->whereLanguageId($this->getLanguage()->id)
            ->select(['element_id', 'slug'])
            ->get()
            ->toBase()
            ->pluck('slug', 'element_id');
    }

    /**
     * @param  string  $slug
     * @param  string  $separator
     * @param  Collection  $slugs
     * @return string
     */
    private function getSuffix($slug, $separator, $slugs)
    {
        $len = strlen($slug.$separator);

        if ($slugs->search($slug) === $this->model->getKey()) {
            $suffix = explode($separator, $slug);

            return end($suffix);
        }

        $slugs->transform(function ($value, $key) use ($len) {
            return (int) substr($value, $len);
        });

        $max = $slugs->max();

        // starts suffixing from 2, eg: test-post, test-post-2, test-post-3, etc
        return (string) ($max === 0 ? 2 : $max + 1);
    }
}
