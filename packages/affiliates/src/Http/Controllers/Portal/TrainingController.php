<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateTrainingModule;
use AIArmada\Affiliates\Models\AffiliateTrainingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

final class TrainingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $modules = AffiliateTrainingModule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($module) use ($affiliate) {
                $progress = AffiliateTrainingProgress::query()
                    ->where('affiliate_id', $affiliate->id)
                    ->where('module_id', $module->id)
                    ->first();

                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'duration_minutes' => $module->duration_minutes,
                    'type' => $module->type,
                    'is_required' => $module->is_required,
                    'progress' => $progress?->progress_percent ?? 0,
                    'completed_at' => $progress?->completed_at,
                    'certificate_url' => $progress?->certificate_url,
                ];
            });

        $stats = [
            'total_modules' => $modules->count(),
            'completed_modules' => $modules->filter(fn ($m) => $m['completed_at'] !== null)->count(),
            'required_completed' => $modules->filter(fn ($m) => $m['is_required'] && $m['completed_at'] !== null)->count(),
            'total_required' => $modules->filter(fn ($m) => $m['is_required'])->count(),
        ];

        return response()->json([
            'modules' => $modules,
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, string $moduleId): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $module = AffiliateTrainingModule::query()
            ->where('is_active', true)
            ->findOrFail($moduleId);

        $progress = AffiliateTrainingProgress::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('module_id', $module->id)
            ->first();

        return response()->json([
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'content' => $module->content,
                'duration_minutes' => $module->duration_minutes,
                'type' => $module->type,
                'video_url' => $module->video_url,
                'resources' => $module->resources,
                'quiz' => $module->quiz,
                'is_required' => $module->is_required,
            ],
            'progress' => [
                'progress_percent' => $progress?->progress_percent ?? 0,
                'last_position' => $progress?->last_position,
                'completed_at' => $progress?->completed_at,
                'quiz_score' => $progress?->quiz_score,
                'quiz_attempts' => $progress?->quiz_attempts ?? 0,
            ],
        ]);
    }

    public function updateProgress(Request $request, string $moduleId): JsonResponse
    {
        $validated = $request->validate([
            'progress_percent' => 'required|integer|min:0|max:100',
            'last_position' => 'nullable|integer|min:0',
        ]);

        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $module = AffiliateTrainingModule::query()
            ->where('is_active', true)
            ->findOrFail($moduleId);

        $progress = AffiliateTrainingProgress::updateOrCreate(
            [
                'affiliate_id' => $affiliate->id,
                'module_id' => $module->id,
            ],
            [
                'progress_percent' => $validated['progress_percent'],
                'last_position' => $validated['last_position'] ?? 0,
                'completed_at' => $validated['progress_percent'] >= 100 ? now() : null,
            ]
        );

        return response()->json([
            'progress' => $progress,
            'message' => 'Progress updated successfully.',
        ]);
    }

    public function submitQuiz(Request $request, string $moduleId): JsonResponse
    {
        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $module = AffiliateTrainingModule::query()
            ->where('is_active', true)
            ->findOrFail($moduleId);

        if (empty($module->quiz)) {
            return response()->json([
                'error' => 'This module does not have a quiz.',
            ], 422);
        }

        $correctAnswers = 0;
        $totalQuestions = count($module->quiz);

        foreach ($module->quiz as $index => $question) {
            if (isset($validated['answers'][$index]) &&
                $validated['answers'][$index] === $question['correct_answer']) {
                $correctAnswers++;
            }
        }

        $score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100) : 0;
        $passed = $score >= ($module->passing_score ?? 70);

        $progress = AffiliateTrainingProgress::updateOrCreate(
            [
                'affiliate_id' => $affiliate->id,
                'module_id' => $module->id,
            ],
            [
                'quiz_score' => $score,
                'quiz_attempts' => DB::raw('COALESCE(quiz_attempts, 0) + 1'),
                'quiz_passed_at' => $passed ? now() : null,
                'completed_at' => $passed ? now() : null,
                'progress_percent' => $passed ? 100 : 90,
            ]
        );

        return response()->json([
            'score' => $score,
            'passed' => $passed,
            'correct_answers' => $correctAnswers,
            'total_questions' => $totalQuestions,
            'progress' => $progress->fresh(),
        ]);
    }

    public function certificate(Request $request, string $moduleId): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $progress = AffiliateTrainingProgress::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('module_id', $moduleId)
            ->whereNotNull('completed_at')
            ->firstOrFail();

        if (! $progress->certificate_url) {
            $progress->update([
                'certificate_url' => route('affiliate.training.certificate.download', [
                    'moduleId' => $moduleId,
                    'token' => hash('sha256', $affiliate->id . $moduleId . config('app.key')),
                ]),
            ]);
        }

        return response()->json([
            'certificate_url' => $progress->certificate_url,
        ]);
    }
}
