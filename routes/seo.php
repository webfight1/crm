<?php

use App\Http\Controllers\Seo\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SEO pipeline routes — /seo/*, named seo.*
|--------------------------------------------------------------------------
| Playbook (operator-editable rules) + site audits → draft quotations.
| See docs/seo-automation.md.
*/

Route::prefix('seo')->name('seo.')->group(function () {
    Route::get  ('/',                       [SeoController::class, 'playbook'])->name('playbook');
    Route::get  ('/docs',                   [SeoController::class, 'docs'])->name('docs');
    Route::patch('/playbook',               [SeoController::class, 'updatePlaybook'])->name('playbook.update');

    Route::post  ('/checks',                [SeoController::class, 'checksStore'])->name('checks.store');
    Route::patch ('/checks/{check}',        [SeoController::class, 'checksUpdate'])->name('checks.update');
    Route::delete('/checks/{check}',        [SeoController::class, 'checksDestroy'])->name('checks.destroy');

    Route::get ('/companies/search',        [SeoController::class, 'companySearch'])->name('companies.search');
    Route::post('/warm-clients',            [SeoController::class, 'warmStore'])->name('warm.store');

    Route::get ('/audits',                  [SeoController::class, 'auditsIndex'])->name('audits.index');
    Route::post('/audits',                  [SeoController::class, 'auditsStore'])->name('audits.store');
    Route::get ('/audits/{audit}',          [SeoController::class, 'auditsShow'])->name('audits.show');
    Route::post('/audits/{audit}/rerun',    [SeoController::class, 'auditsRerun'])->name('audits.rerun');
    Route::post('/audits/{audit}/deal',     [SeoController::class, 'auditsAttachDeal'])->name('audits.deal');
    Route::post('/audits/{audit}/clarify-answer', [SeoController::class, 'auditsClarifyAnswer'])->name('audits.clarify-answer');
    Route::post('/audits/{audit}/access',   [SeoController::class, 'auditsAccess'])->name('audits.access');
    Route::post('/audits/{audit}/offer',    [SeoController::class, 'auditsOffer'])->name('audits.offer');
});
