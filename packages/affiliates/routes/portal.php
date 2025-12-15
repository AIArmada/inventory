<?php

declare(strict_types=1);

use AIArmada\Affiliates\Http\Controllers\Portal\DashboardController;
use AIArmada\Affiliates\Http\Controllers\Portal\LinkController;
use AIArmada\Affiliates\Http\Controllers\Portal\NetworkController;
use AIArmada\Affiliates\Http\Controllers\Portal\PayoutController;
use AIArmada\Affiliates\Http\Controllers\Portal\ProfileController;
use AIArmada\Affiliates\Http\Controllers\Portal\SupportController;
use AIArmada\Affiliates\Http\Controllers\Portal\TrainingController;
use AIArmada\Affiliates\Http\Middleware\AuthenticateAffiliate;
use Illuminate\Support\Facades\Route;

Route::prefix(config('affiliates.portal.prefix', 'affiliate-portal'))
    ->middleware(['api', AuthenticateAffiliate::class])
    ->as('affiliate.portal.')
    ->group(function (): void {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

        // Profile
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/payout-methods', [ProfileController::class, 'payoutMethods'])->name('profile.payout-methods');
        Route::post('/profile/payout-methods', [ProfileController::class, 'addPayoutMethod'])->name('profile.payout-methods.add');
        Route::delete('/profile/payout-methods/{id}', [ProfileController::class, 'removePayoutMethod'])->name('profile.payout-methods.remove');
        Route::post('/profile/payout-methods/{id}/default', [ProfileController::class, 'setDefaultPayoutMethod'])->name('profile.payout-methods.default');

        // Payouts
        Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts.index');
        Route::get('/payouts/summary', [PayoutController::class, 'summary'])->name('payouts.summary');
        Route::get('/payouts/{id}', [PayoutController::class, 'show'])->name('payouts.show');

        if (config('affiliates.network.enabled', false)) {
            // Network
            Route::get('/network', [NetworkController::class, 'index'])->name('network.index');
            Route::get('/network/upline', [NetworkController::class, 'upline'])->name('network.upline');
            Route::get('/network/downline', [NetworkController::class, 'downline'])->name('network.downline');
            Route::get('/network/stats', [NetworkController::class, 'stats'])->name('network.stats');
        }

        // Links & Creatives
        Route::get('/links', [LinkController::class, 'index'])->name('links.index');
        Route::post('/links', [LinkController::class, 'create'])->name('links.create');
        Route::get('/links/{id}', [LinkController::class, 'show'])->name('links.show');
        Route::delete('/links/{id}', [LinkController::class, 'delete'])->name('links.delete');
        Route::get('/creatives', [LinkController::class, 'creatives'])->name('creatives.index');

        // Support Tickets
        Route::get('/support', [SupportController::class, 'index'])->name('support.index');
        Route::post('/support', [SupportController::class, 'store'])->name('support.store');
        Route::get('/support/{ticketId}', [SupportController::class, 'show'])->name('support.show');
        Route::post('/support/{ticketId}/reply', [SupportController::class, 'reply'])->name('support.reply');
        Route::post('/support/{ticketId}/close', [SupportController::class, 'close'])->name('support.close');

        // Training Academy
        Route::get('/training', [TrainingController::class, 'index'])->name('training.index');
        Route::get('/training/{moduleId}', [TrainingController::class, 'show'])->name('training.show');
        Route::post('/training/{moduleId}/progress', [TrainingController::class, 'updateProgress'])->name('training.progress');
        Route::post('/training/{moduleId}/quiz', [TrainingController::class, 'submitQuiz'])->name('training.quiz');
        Route::get('/training/{moduleId}/certificate', [TrainingController::class, 'certificate'])->name('training.certificate');
    });
