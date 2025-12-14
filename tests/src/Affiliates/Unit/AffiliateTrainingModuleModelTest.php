<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\AffiliateTrainingModule;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('AffiliateTrainingModule Model', function (): void {
    it('can be created with required fields', function (): void {
        $module = AffiliateTrainingModule::create([
            'title' => 'Getting Started',
            'description' => 'Introduction to affiliate marketing',
            'type' => 'video',
            'duration_minutes' => 30,
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);

        expect($module)->toBeInstanceOf(AffiliateTrainingModule::class)
            ->and($module->title)->toBe('Getting Started')
            ->and($module->type)->toBe('video')
            ->and($module->is_required)->toBeTrue()
            ->and($module->is_active)->toBeTrue();
    });

    it('has many progress records', function (): void {
        $module = AffiliateTrainingModule::create([
            'title' => 'Progress Test Module',
            'type' => 'article',
            'duration_minutes' => 15,
            'sort_order' => 1,
            'is_required' => false,
            'is_active' => true,
        ]);

        expect($module->progress())->toBeInstanceOf(HasMany::class);
    });

    it('stores video content', function (): void {
        $module = AffiliateTrainingModule::create([
            'title' => 'Video Module',
            'type' => 'video',
            'video_url' => 'https://youtube.com/watch?v=abc123',
            'duration_minutes' => 45,
            'sort_order' => 2,
            'is_required' => true,
            'is_active' => true,
        ]);

        expect($module->video_url)->toBe('https://youtube.com/watch?v=abc123');
    });

    it('stores article content', function (): void {
        $module = AffiliateTrainingModule::create([
            'title' => 'Article Module',
            'type' => 'article',
            'content' => '# How to Promote Products\n\nLearn the basics of promotion...',
            'duration_minutes' => 10,
            'sort_order' => 3,
            'is_required' => false,
            'is_active' => true,
        ]);

        expect($module->content)->toContain('How to Promote Products');
    });

    it('stores resources as array', function (): void {
        $module = AffiliateTrainingModule::create([
            'title' => 'Resources Module',
            'type' => 'article',
            'duration_minutes' => 20,
            'sort_order' => 4,
            'is_required' => false,
            'is_active' => true,
            'resources' => [
                ['name' => 'PDF Guide', 'url' => '/resources/guide.pdf'],
                ['name' => 'Checklist', 'url' => '/resources/checklist.pdf'],
            ],
        ]);

        expect($module->resources)->toBeArray()
            ->and($module->resources)->toHaveCount(2)
            ->and($module->resources[0]['name'])->toBe('PDF Guide');
    });

    it('stores quiz as array', function (): void {
        $module = AffiliateTrainingModule::create([
            'title' => 'Quiz Module',
            'type' => 'quiz',
            'duration_minutes' => 15,
            'sort_order' => 5,
            'is_required' => true,
            'is_active' => true,
            'passing_score' => 80,
            'quiz' => [
                [
                    'question' => 'What is affiliate marketing?',
                    'options' => ['A', 'B', 'C', 'D'],
                    'correct' => 'B',
                ],
                [
                    'question' => 'How do commissions work?',
                    'options' => ['A', 'B', 'C', 'D'],
                    'correct' => 'C',
                ],
            ],
        ]);

        expect($module->quiz)->toBeArray()
            ->and($module->quiz)->toHaveCount(2)
            ->and($module->quiz[0]['question'])->toBe('What is affiliate marketing?')
            ->and($module->passing_score)->toBe(80);
    });

    it('casts boolean fields', function (): void {
        $module = AffiliateTrainingModule::create([
            'title' => 'Boolean Test',
            'type' => 'article',
            'duration_minutes' => 10,
            'sort_order' => 6,
            'is_required' => '1',
            'is_active' => '0',
        ]);

        expect($module->is_required)->toBeBool()
            ->and($module->is_required)->toBeTrue()
            ->and($module->is_active)->toBeBool()
            ->and($module->is_active)->toBeFalse();
    });

    it('casts numeric fields as integers', function (): void {
        $module = AffiliateTrainingModule::create([
            'title' => 'Cast Test',
            'type' => 'video',
            'duration_minutes' => '30',
            'sort_order' => '10',
            'passing_score' => '75',
            'is_required' => true,
            'is_active' => true,
        ]);

        expect($module->duration_minutes)->toBeInt()
            ->and($module->sort_order)->toBeInt()
            ->and($module->passing_score)->toBeInt();
    });

    it('supports different module types', function (): void {
        $videoModule = AffiliateTrainingModule::create([
            'title' => 'Video Type',
            'type' => 'video',
            'duration_minutes' => 30,
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);

        $articleModule = AffiliateTrainingModule::create([
            'title' => 'Article Type',
            'type' => 'article',
            'duration_minutes' => 10,
            'sort_order' => 2,
            'is_required' => false,
            'is_active' => true,
        ]);

        $quizModule = AffiliateTrainingModule::create([
            'title' => 'Quiz Type',
            'type' => 'quiz',
            'duration_minutes' => 15,
            'sort_order' => 3,
            'is_required' => true,
            'is_active' => true,
        ]);

        expect($videoModule->type)->toBe('video')
            ->and($articleModule->type)->toBe('article')
            ->and($quizModule->type)->toBe('quiz');
    });
});
