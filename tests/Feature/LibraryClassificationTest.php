<?php

declare(strict_types=1);

use App\Actions\Categories\SaveCategory;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Models\Category;
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
    expect($category->color)->toBe('#A1B2C3');

    expect(fn () => app(SaveCategory::class)->handle(null, [
        'name' => 'Colore non valido',
        'color' => 'red',
        'sort_order' => 2,
        'is_enabled' => true,
    ]))->toThrow(ValidationException::class);
});

it('keeps category slugs reserved after archival', function (): void {
    $category = app(SaveCategory::class)->handle(null, [
        'name' => 'Backup',
        'sort_order' => 1,
        'is_enabled' => true,
    ]);
    $category->delete();

    $replacementCategory = app(SaveCategory::class)->handle(null, [
        'name' => 'Backup',
        'sort_order' => 2,
        'is_enabled' => true,
    ]);
    expect($replacementCategory->slug)->toBe('backup-2');
});

it('creates categories through their Filament resource', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);

    $component = Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Categoria Filament',
            'color' => '#abcdef',
            'sort_order' => 20,
            'is_enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Category::query()->where('slug', 'categoria-filament')->firstOrFail();
    $component->assertRedirect(CategoryResource::getUrl('edit', ['record' => $created]));

    expect($created->color)->toBe('#ABCDEF');
});

it('requires authentication for the clustered category resource', function (): void {
    $this->get(CategoryResource::getUrl('index'))->assertRedirect('/admin/login');
});
