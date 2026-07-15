<?php

declare(strict_types=1);

use App\Actions\Categories\SaveCategory;
use App\Actions\Tags\SaveTag;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('seeds exactly the approved categories in order on a fresh domain', function (): void {
    $this->seed(MilestoneOneSeeder::class);

    expect(Category::query()->orderBy('sort_order')->pluck('name')->all())->toBe([
        'Governance IT',
        'Sicurezza',
        'Rete',
        'Cablaggio e Infrastruttura Fisica',
        'Server',
        'NAS e Storage',
        'Backup',
        'Endpoint',
        'Identità e Accessi',
        'Cloud e Microsoft 365',
        'Posta Elettronica',
        'VoIP',
        'Videosorveglianza',
        'Continuità Operativa',
        'Monitoraggio',
        'Documentazione',
        'Licenze e Conformità',
        'Altro',
    ]);
});

it('normalizes valid colors and rejects invalid colors', function (): void {
    $category = app(SaveCategory::class)->handle(null, [
        'name' => 'Sicurezza applicativa',
        'color' => '#a1b2c3',
        'sort_order' => 1,
        'is_enabled' => true,
    ]);
    $tag = app(SaveTag::class)->handle(null, [
        'name' => 'Urgente',
        'color' => '#d4e5f6',
    ]);

    expect($category->color)->toBe('#A1B2C3')
        ->and($tag->color)->toBe('#D4E5F6');

    expect(fn () => app(SaveTag::class)->handle(null, [
        'name' => 'Colore non valido',
        'color' => 'red',
    ]))->toThrow(ValidationException::class);
});

it('keeps category and tag slugs reserved after archival', function (): void {
    $category = app(SaveCategory::class)->handle(null, [
        'name' => 'Backup',
        'sort_order' => 1,
        'is_enabled' => true,
    ]);
    $tag = app(SaveTag::class)->handle(null, ['name' => 'Critico']);
    $category->delete();
    $tag->delete();

    $replacementCategory = app(SaveCategory::class)->handle(null, [
        'name' => 'Backup',
        'sort_order' => 2,
        'is_enabled' => true,
    ]);
    $replacementTag = app(SaveTag::class)->handle(null, ['name' => 'Critico']);

    expect($replacementCategory->slug)->toBe('backup-2')
        ->and($replacementTag->slug)->toBe('critico-2');
});

it('creates categories and tags through their Filament resources', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Categoria Filament',
            'color' => '#abcdef',
            'sort_order' => 20,
            'is_enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateTag::class)
        ->fillForm([
            'name' => 'Tag Filament',
            'color' => '#123abc',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::query()->where('slug', 'categoria-filament')->firstOrFail()->color)->toBe('#ABCDEF')
        ->and(Tag::query()->where('slug', 'tag-filament')->firstOrFail()->color)->toBe('#123ABC');
});

it('requires authentication for category and tag resources', function (): void {
    $this->get('/admin/categories')->assertRedirect('/admin/login');
    $this->get('/admin/tags')->assertRedirect('/admin/login');
});
